<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class SlackMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'slack';
	}

	public function getLabel(): string {
		return 'Slack Incoming Webhook';
	}

	public function getSettingsSummary(array $settings = []): string {
		return $this->createSummary([
			'Webhook URL: ' . ($this->hasConfiguredValue($settings['webhook_url'] ?? null) ? 'configured' : 'not configured'),
			$this->readArray($settings, 'blocks', []) !== [] ? 'Custom blocks configured' : 'Text message',
			$this->readBool($settings, 'unfurl_links', false) ? 'Link unfurling enabled' : 'Link unfurling disabled'
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('Slack transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('Slack incoming webhook transport does not support message attachments.');
		}

		$webhookUrl = $this->resolveString($settings['webhook_url'] ?? '');
		$text = $this->getMessageText($message, $this->readBool($settings, 'include_subject', true));
		if($webhookUrl === '') {
			return $this->failure('Slack transport needs a webhook_url setting.');
		}
		if($text === '' && $this->readArray($settings, 'blocks', []) === []) {
			return $this->failure('Slack transport needs message text or configured blocks.');
		}

		$payload = [
			'text' => $text,
			'unfurl_links' => $this->readBool($settings, 'unfurl_links', false),
			'unfurl_media' => $this->readBool($settings, 'unfurl_media', false)
		];
		$blocks = $this->readArray($settings, 'blocks', []);
		if($blocks !== []) {
			$payload['blocks'] = $blocks;
		}

		try {
			$response = $this->requestJson(
				'POST',
				$webhookUrl,
				$payload,
				[],
				$this->readInt($settings, 'timeout', 20),
				$this->readBool($settings, 'verify_tls', true)
			);

			if(!$response->isSuccessful()) {
				return $this->failure($this->getHttpError($response, 'Slack rejected the message.'), [
					'status_code' => $response->getStatusCode()
				]);
			}

			return $this->success('Message sent by Slack.', '', [
				'transport' => self::getName(),
				'status_code' => $response->getStatusCode(),
				'response' => $this->truncate(trim($response->getBody()), 200)
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('Slack', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'webhook_url' => ['description' => 'ConfigValue definition or fixed secret'],
				'include_subject' => ['type' => 'boolean'],
				'blocks' => ['type' => 'array'],
				'unfurl_links' => ['type' => 'boolean'],
				'unfurl_media' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}
}
