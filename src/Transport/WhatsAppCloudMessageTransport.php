<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class WhatsAppCloudMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'whatsappcloud';
	}

	public function getLabel(): string {
		return 'WhatsApp Cloud API';
	}

	public function getSettingsSummary(array $settings = []): string {
		return $this->createSummary([
			'API version: ' . ($this->readString($settings, 'api_version', '') ?: 'not configured'),
			'Phone number ID: ' . ($this->readString($settings, 'phone_number_id', '') ?: 'not configured'),
			'Access token: ' . ($this->hasConfiguredValue($settings['access_token'] ?? null) ? 'configured' : 'not configured'),
			$this->readBool($settings, 'preview_url', false) ? 'Link preview enabled' : 'Link preview disabled'
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('WhatsApp Cloud API transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('WhatsApp Cloud API text transport does not support message attachments.');
		}

		$version = trim($this->readString($settings, 'api_version', ''), '/');
		$phoneNumberId = trim($this->readString($settings, 'phone_number_id', ''), '/');
		$accessToken = $this->resolveString($settings['access_token'] ?? '');
		$baseUrl = rtrim($this->readString($settings, 'api_base_url', 'https://graph.facebook.com'), '/');
		$recipients = $this->getRecipients($message);
		$textParts = $this->splitText(
			$this->getMessageText($message, $this->readBool($settings, 'include_subject', true)),
			4000
		);

		if($version === '' || $phoneNumberId === '' || $accessToken === '') {
			return $this->failure('WhatsApp Cloud API needs api_version, phone_number_id and access_token settings.');
		}

		if($recipients === []) {
			return $this->failure('WhatsApp Cloud API needs at least one TO recipient.');
		}

		if($textParts === []) {
			return $this->failure('WhatsApp Cloud API needs message text.');
		}

		$endpoint = $baseUrl . '/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '/messages';
		$messageIds = [];
		$deliveries = 0;

		try {
			foreach($recipients as $recipient) {
				$address = preg_replace('/[^0-9]/', '', $recipient->getAddress()) ?: '';
				if($address === '') {
					continue;
				}

				foreach($textParts as $text) {
					$response = $this->requestJson('POST', $endpoint, [
						'messaging_product' => 'whatsapp',
						'recipient_type' => 'individual',
						'to' => $address,
						'type' => 'text',
						'text' => [
							'preview_url' => $this->readBool($settings, 'preview_url', false),
							'body' => $text
						]
					], [
						'Authorization' => 'Bearer ' . $accessToken
					], $this->readInt($settings, 'timeout', 20), $this->readBool($settings, 'verify_tls', true));

					if(!$response->isSuccessful()) {
						return $this->failure($this->getHttpError($response, 'WhatsApp Cloud API rejected the message.'), [
							'status_code' => $response->getStatusCode(),
							'recipient' => $address
						]);
					}

					$json = $response->getJson();
					$messageId = is_array($json)
						&& is_array($json['messages'] ?? null)
						&& is_array($json['messages'][0] ?? null)
						&& is_scalar($json['messages'][0]['id'] ?? null)
						? (string)$json['messages'][0]['id']
						: '';

					if($messageId !== '') {
						$messageIds[] = $messageId;
					}
					$deliveries++;
				}
			}

			if($deliveries === 0) {
				return $this->failure('WhatsApp Cloud API found no usable recipient address.');
			}

			return $this->success('Message sent by WhatsApp Cloud API.', $messageIds[0] ?? '', [
				'transport' => self::getName(),
				'deliveries' => $deliveries,
				'message_ids' => $messageIds
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('WhatsApp Cloud API', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'api_base_url' => ['type' => 'string'],
				'api_version' => ['type' => 'string', 'description' => 'Meta Graph API version, for example vXX.X'],
				'phone_number_id' => ['type' => 'string'],
				'access_token' => ['description' => 'ConfigValue definition or fixed secret'],
				'preview_url' => ['type' => 'boolean'],
				'include_subject' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}
}
