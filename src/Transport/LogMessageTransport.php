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

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessagingFoundation\Api\IMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageDeliveryResult;

final class LogMessageTransport implements IMessageTransport {

	public function __construct(
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'log';
	}

	public function getLabel(): string {
		return 'Log only';
	}

	public function getSettingsSummary(array $settings = []): string {
		return 'No delivery settings required.';
	}

	public function supports(Message $message, array $settings = []): bool {
		return true;
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->readBool($settings, 'enabled', true)) {
			return new MessageDeliveryResult(false, 'Log transport is disabled.');
		}

		$this->logger->info('MessageHub log transport received message', [
			'scope' => 'messagehub',
			'type_name' => $message->getTypeName(),
			'subject' => $message->getSubject()
		]);

		return new MessageDeliveryResult(true, 'Message logged.', '', [
			'transport' => self::getName()
		]);
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => true]
			]
		];
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
