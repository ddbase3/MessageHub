<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageFilterOptionService;
use PHPUnit\Framework\TestCase;

final class MessageFilterOptionServiceTest extends TestCase {

	public function testEachFilterUsesItsOwningTableAndColumn(): void {
		$queries = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(['Field' => 'fallback_flag']);
		$database->method('multiQuery')->willReturnCallback(function(string $sql) use (&$queries): array {
			$queries[] = $sql;
			return [['value' => ' beta '], ['value' => ''], ['value' => 'alpha'], ['invalid' => '']];
		});
		$service = new MessageFilterOptionService($database, new DatabaseSchema($database));
		self::assertSame([['value' => 'beta', 'label' => 'beta'], ['value' => 'alpha', 'label' => 'alpha']], $service->getTemplateTransportOptions());
		$service->getQueueTransportOptions();
		$service->getQueueTypeOptions();
		$service->getDeliveryTransportOptions();
		$service->getDeliveryTypeOptions();
		self::assertCount(5, $queries);
		foreach(['base3_messaging_templates|default_transport', 'base3_messaging_queue|transport_name',
			'base3_messaging_queue|type_name', 'base3_messaging_deliveries|transport_name',
			'base3_messaging_deliveries|type_name'] as $i => $expected) {
			[$table, $column] = explode('|', $expected);
			self::assertStringContainsString('SELECT DISTINCT ' . $column . ' AS value FROM ' . $table, $queries[$i]);
			self::assertStringContainsString('ORDER BY ' . $column . ' ASC', $queries[$i]);
		}
	}
}
