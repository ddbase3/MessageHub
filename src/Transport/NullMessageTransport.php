<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;

final class NullMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'null';
	}

	public function getLabel(): string {
		return 'Null (discard)';
	}

	public function getSettingsSummary(array $settings = []): string {
		$reason = $this->readString($settings, 'reason', '');

		return $reason !== ''
			? 'Messages are discarded successfully | Reason: ' . $reason
			: 'Messages are discarded successfully.';
	}

	public function supports(Message $message, array $settings = []): bool {
		return true;
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->readBool($settings, 'enabled', true)) {
			return new MessageDeliveryResult(false, 'Null transport is disabled.');
		}

		return new MessageDeliveryResult(true, 'Message discarded by Null transport.', '', [
			'transport' => self::getName(),
			'discarded' => true,
			'reason' => $this->readString($settings, 'reason', '')
		]);
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => true],
				'reason' => ['type' => 'string']
			]
		];
	}

	private function readString(array $settings, string $key, string $default = ''): string {
		$value = $settings[$key] ?? $default;

		return is_scalar($value) || $value === null ? trim((string)$value) : $default;
	}

	private function readBool(array $settings, string $key, bool $default): bool {
		if(!array_key_exists($key, $settings)) {
			return $default;
		}

		$value = $settings[$key];
		if(is_bool($value)) {
			return $value;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'enabled'], true);
	}
}
