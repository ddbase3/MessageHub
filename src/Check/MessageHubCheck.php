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

namespace Base3\MessageHub\Check;

use Base3\CoreFoundation\Api\ICheck;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;

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
