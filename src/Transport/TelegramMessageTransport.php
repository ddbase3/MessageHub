<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class TelegramMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'telegram';
	}

	public function getLabel(): string {
		return 'Telegram Bot';
	}

	public function getSettingsSummary(array $settings = []): string {
		$parseMode = $this->readString($settings, 'parse_mode', '');

		return $this->createSummary([
			'Bot token: ' . ($this->hasConfiguredValue($settings['bot_token'] ?? null) ? 'configured' : 'not configured'),
			'Default chat: ' . ($this->readString($settings, 'default_chat_id', '') ?: 'not configured'),
			$parseMode !== '' ? 'Parse mode: ' . $parseMode : 'Plain text',
			$this->readBool($settings, 'disable_notification', false) ? 'Silent delivery' : 'Normal notification'
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('Telegram transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('Telegram text transport does not support message attachments.');
		}

		$token = $this->resolveString($settings['bot_token'] ?? '');
		if($token === '') {
			return $this->failure('Telegram transport needs a bot_token setting.');
		}

		$chatIds = [];
		foreach($this->getRecipients($message) as $recipient) {
			$chatIds[] = trim($recipient->getAddress());
		}
		$defaultChatId = $this->readString($settings, 'default_chat_id', '');
		if($chatIds === [] && $defaultChatId !== '') {
			$chatIds[] = $defaultChatId;
		}
		$chatIds = array_values(array_unique(array_filter($chatIds, fn(string $chatId): bool => $chatId !== '')));

		if($chatIds === []) {
			return $this->failure('Telegram transport needs a TO recipient or default_chat_id.');
		}

		$textParts = $this->splitText(
			$this->getMessageText($message, $this->readBool($settings, 'include_subject', true)),
			4000
		);
		if($textParts === []) {
			return $this->failure('Telegram transport needs message text.');
		}

		$baseUrl = rtrim($this->readString($settings, 'api_base_url', 'https://api.telegram.org'), '/');
		$endpoint = $baseUrl . '/bot' . $token . '/sendMessage';
		$parseMode = $this->readString($settings, 'parse_mode', '');
		$messageIds = [];
		$deliveries = 0;

		try {
			foreach($chatIds as $chatId) {
				foreach($textParts as $text) {
					$payload = [
						'chat_id' => $chatId,
						'text' => $text,
						'disable_notification' => $this->readBool($settings, 'disable_notification', false),
						'link_preview_options' => [
							'is_disabled' => $this->readBool($settings, 'disable_web_page_preview', false)
						]
					];
					if($parseMode !== '') {
						$payload['parse_mode'] = $parseMode;
					}

					$response = $this->requestJson(
						'POST',
						$endpoint,
						$payload,
						[],
						$this->readInt($settings, 'timeout', 20),
						$this->readBool($settings, 'verify_tls', true)
					);

					$json = $response->getJson();
					if(!$response->isSuccessful() || !is_array($json) || empty($json['ok'])) {
						return $this->failure($this->getHttpError($response, 'Telegram rejected the message.'), [
							'status_code' => $response->getStatusCode(),
							'chat_id' => $chatId
						]);
					}

					$messageId = is_array($json['result'] ?? null) && is_scalar($json['result']['message_id'] ?? null)
						? (string)$json['result']['message_id']
						: '';
					if($messageId !== '') {
						$messageIds[] = $messageId;
					}
					$deliveries++;
				}
			}

			return $this->success('Message sent by Telegram.', $messageIds[0] ?? '', [
				'transport' => self::getName(),
				'deliveries' => $deliveries,
				'message_ids' => $messageIds
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('Telegram', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'api_base_url' => ['type' => 'string'],
				'bot_token' => ['description' => 'ConfigValue definition or fixed secret'],
				'default_chat_id' => ['type' => 'string'],
				'parse_mode' => ['type' => 'string', 'enum' => ['', 'HTML', 'Markdown', 'MarkdownV2']],
				'disable_web_page_preview' => ['type' => 'boolean'],
				'disable_notification' => ['type' => 'boolean'],
				'include_subject' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}
}
