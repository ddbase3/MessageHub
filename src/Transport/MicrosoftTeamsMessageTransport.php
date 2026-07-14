<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class MicrosoftTeamsMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'microsoftteams';
	}

	public function getLabel(): string {
		return 'Microsoft Teams Webhook';
	}

	public function getSettingsSummary(array $settings = []): string {
		$mode = strtolower($this->readString($settings, 'payload_mode', 'workflow_text'));

		return $this->createSummary([
			'Webhook URL: ' . ($this->hasConfiguredValue($settings['webhook_url'] ?? null) ? 'configured' : 'not configured'),
			$mode === 'adaptive_card' ? 'Adaptive Card payload' : 'Workflow text payload'
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('Microsoft Teams transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('Microsoft Teams webhook transport does not support message attachments.');
		}

		$webhookUrl = $this->resolveString($settings['webhook_url'] ?? '');
		if($webhookUrl === '') {
			return $this->failure('Microsoft Teams transport needs a webhook_url setting.');
		}

		$mode = strtolower($this->readString($settings, 'payload_mode', 'workflow_text'));
		$payload = $mode === 'adaptive_card'
			? $this->buildAdaptiveCardPayload($message)
			: ['text' => $this->getMessageText($message, $this->readBool($settings, 'include_subject', true))];

		if($mode !== 'adaptive_card' && trim((string)($payload['text'] ?? '')) === '') {
			return $this->failure('Microsoft Teams transport needs message text.');
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
				return $this->failure($this->getHttpError($response, 'Microsoft Teams rejected the message.'), [
					'status_code' => $response->getStatusCode()
				]);
			}

			return $this->success('Message sent by Microsoft Teams.', '', [
				'transport' => self::getName(),
				'payload_mode' => $mode,
				'status_code' => $response->getStatusCode(),
				'response' => $this->truncate(trim($response->getBody()), 300)
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('Microsoft Teams', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'webhook_url' => ['description' => 'ConfigValue definition or fixed secret'],
				'payload_mode' => ['type' => 'string', 'enum' => ['workflow_text', 'adaptive_card']],
				'include_subject' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}

	private function buildAdaptiveCardPayload(Message $message): array {
		$body = [];
		$subject = trim($message->getSubject());
		$text = $this->getMessageBody($message);

		if($subject !== '') {
			$body[] = [
				'type' => 'TextBlock',
				'text' => $subject,
				'weight' => 'Bolder',
				'size' => 'Medium',
				'wrap' => true
			];
		}

		if($text !== '') {
			$body[] = [
				'type' => 'TextBlock',
				'text' => $text,
				'wrap' => true
			];
		}

		if($body === []) {
			$body[] = [
				'type' => 'TextBlock',
				'text' => 'Message',
				'wrap' => true
			];
		}

		return [
			'type' => 'message',
			'attachments' => [[
				'contentType' => 'application/vnd.microsoft.card.adaptive',
				'contentUrl' => null,
				'content' => [
					'$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
					'type' => 'AdaptiveCard',
					'version' => '1.2',
					'body' => $body
				]
			]]
		];
	}
}
