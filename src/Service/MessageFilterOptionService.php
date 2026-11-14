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

namespace Base3\MessageHub\Service;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessageHub\Repository\DatabaseSchema;

final class MessageFilterOptionService {

	public function __construct(
		private readonly IDatabase $database,
		private readonly DatabaseSchema $schema
	) {}

	public function getTemplateTransportOptions(): array {
		return $this->getDistinctValues('base3_messaging_templates', 'default_transport');
	}

	public function getQueueTransportOptions(): array {
		return $this->getDistinctValues('base3_messaging_queue', 'transport_name');
	}

	public function getQueueTypeOptions(): array {
		return $this->getDistinctValues('base3_messaging_queue', 'type_name');
	}

	public function getDeliveryTransportOptions(): array {
		return $this->getDistinctValues('base3_messaging_deliveries', 'transport_name');
	}

	public function getDeliveryTypeOptions(): array {
		return $this->getDistinctValues('base3_messaging_deliveries', 'type_name');
	}

	private function getDistinctValues(string $table, string $column): array {
		$this->schema->ensureTables();
		$rows = $this->database->multiQuery(
			'SELECT DISTINCT ' . $column . ' AS value FROM ' . $table .
			' WHERE ' . $column . " IS NOT NULL AND " . $column . " <> '' ORDER BY " . $column . ' ASC'
		);
		$options = [];

		foreach($rows as $row) {
			$value = trim((string)($row['value'] ?? ''));

			if($value === '') {
				continue;
			}

			$options[] = [
				'value' => $value,
				'label' => $value
			];
		}

		return $options;
	}
}
