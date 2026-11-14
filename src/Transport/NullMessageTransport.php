<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MessageHub for BASE3 Framework.
 *
 * MessageHub implements queue-based messaging for BASE3 applications.
 * It manages templates, retries, deliveries and transport discovery.
 * Transport-neutral contracts are defined by MessagingFoundation.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/MessageHub
 **********************************************************************/

namespace Base3\MessageHub\Transport;

use Base3\MessagingFoundation\Api\IMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageDeliveryResult;

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
