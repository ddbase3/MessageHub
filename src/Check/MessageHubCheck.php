<?php declare(strict_types=1);

namespace MessageHub\Check;

use Base3\Api\ICheck;
use MessageHub\Repository\DatabaseSchema;
use MessagingFoundation\Api\IMessageTransportRegistry;

final class MessageHubCheck implements ICheck {

	public function __construct(
		private readonly DatabaseSchema $schema,
		private readonly IMessageTransportRegistry $transportRegistry
	) {}

	public static function getName(): string { return 'messagehubcheck'; }

	public function checkDependencies() {
		$this->schema->ensureTables();
		return [
			'messagehub_tables' => 'Ok',
			'messagehub_transports' => implode(', ', array_keys($this->transportRegistry->getTransports()))
		];
	}
}
