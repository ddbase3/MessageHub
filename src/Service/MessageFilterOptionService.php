<?php declare(strict_types=1);

namespace MessageHub\Service;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseSchema;

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
