<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class NtfyMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'ntfy';
	}

	public function getLabel(): string {
		return 'ntfy';
	}

	public function getSettingsSummary(array $settings = []): string {
		$authMode = strtolower($this->readString($settings, 'auth_mode', 'none'));

		return $this->createSummary([
			'Server: ' . ($this->readString($settings, 'base_url', 'https://ntfy.sh') ?: 'not configured'),
			'Default topic: ' . ($this->readString($settings, 'default_topic', '') ?: 'not configured'),
			'Authentication: ' . $authMode,
			'Priority: ' . ($this->readString($settings, 'priority', 'default') ?: 'default')
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('ntfy transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('ntfy text transport does not support message attachments.');
		}

		$topics = [];
		foreach($this->getRecipients($message) as $recipient) {
			$topics[] = trim($recipient->getAddress(), " \t\n\r\0\x0B/");
		}
		$defaultTopic = trim($this->readString($settings, 'default_topic', ''), '/');
		if($topics === [] && $defaultTopic !== '') {
			$topics[] = $defaultTopic;
		}
		$topics = array_values(array_unique(array_filter($topics, fn(string $topic): bool => $topic !== '')));

		if($topics === []) {
			return $this->failure('ntfy transport needs a TO recipient or default_topic.');
		}

		$body = $this->getMessageBody($message);
		if($body === '') {
			$body = trim($message->getSubject());
		}
		if($body === '') {
			return $this->failure('ntfy transport needs message text.');
		}

		$headers = [
			'Title' => trim($message->getSubject()),
			'Priority' => $this->readString($settings, 'priority', ''),
			'Tags' => $this->formatTags($settings['tags'] ?? []),
			'Click' => $this->readString($settings, 'click_url', ''),
			'Markdown' => $this->readBool($settings, 'markdown', false) ? 'yes' : '',
			'Cache' => $this->readBool($settings, 'cache', true) ? 'yes' : 'no',
			'Firebase' => $this->readBool($settings, 'firebase', true) ? 'yes' : 'no',
			'Content-Type' => 'text/plain; charset=UTF-8'
		];
		$authMode = strtolower($this->readString($settings, 'auth_mode', 'none'));
		if($authMode === 'bearer') {
			$token = $this->resolveString($settings['token'] ?? '');
			if($token === '') {
				return $this->failure('ntfy bearer authentication needs a token.');
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		} elseif($authMode === 'basic') {
			$username = $this->readString($settings, 'username', '');
			$password = $this->resolveString($settings['password'] ?? '');
			if($username === '') {
				return $this->failure('ntfy basic authentication needs a username.');
			}
			$headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
		}

		$baseUrl = rtrim($this->readString($settings, 'base_url', 'https://ntfy.sh'), '/');
		$messageIds = [];

		try {
			foreach($topics as $topic) {
				$response = $this->request(
					'POST',
					$baseUrl . '/' . rawurlencode($topic),
					$headers,
					$body,
					$this->readInt($settings, 'timeout', 20),
					$this->readBool($settings, 'verify_tls', true)
				);

				if(!$response->isSuccessful()) {
					return $this->failure($this->getHttpError($response, 'ntfy rejected the message.'), [
						'status_code' => $response->getStatusCode(),
						'topic' => $topic
					]);
				}

				$json = $response->getJson();
				$id = is_array($json) && is_scalar($json['id'] ?? null) ? (string)$json['id'] : '';
				if($id !== '') {
					$messageIds[] = $id;
				}
			}

			return $this->success('Message sent by ntfy.', $messageIds[0] ?? '', [
				'transport' => self::getName(),
				'deliveries' => count($topics),
				'message_ids' => $messageIds
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('ntfy', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'base_url' => ['type' => 'string'],
				'default_topic' => ['type' => 'string'],
				'auth_mode' => ['type' => 'string', 'enum' => ['none', 'bearer', 'basic']],
				'token' => ['description' => 'ConfigValue definition or fixed secret'],
				'username' => ['type' => 'string'],
				'password' => ['description' => 'ConfigValue definition or fixed secret'],
				'priority' => ['type' => 'string', 'enum' => ['', 'min', 'low', 'default', 'high', 'max']],
				'tags' => ['type' => 'array'],
				'click_url' => ['type' => 'string'],
				'markdown' => ['type' => 'boolean'],
				'cache' => ['type' => 'boolean'],
				'firebase' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}

	private function formatTags(mixed $tags): string {
		if(is_array($tags)) {
			return implode(',', array_values(array_filter(array_map(
				fn(mixed $tag): string => is_scalar($tag) ? trim((string)$tag) : '',
				$tags
			), fn(string $tag): bool => $tag !== '')));
		}

		return is_scalar($tags) ? trim((string)$tags) : '';
	}
}
