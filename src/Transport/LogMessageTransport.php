<?php declare(strict_types=1);

namespace MessageHub\Transport;

use Base3\Logger\Api\ILogger;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;

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

	public function supports(Message $message, array $settings = []): bool {
		return true;
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
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
			'properties' => []
		];
	}
}
