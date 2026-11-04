<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseSchema;
use PHPUnit\Framework\TestCase;

final class DatabaseSchemaTest extends TestCase {

	public function testCreatesAllTablesOnlyOnceAndPreservesExistingColumn(): void {
		$queries = [];
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::once())->method('connect');
		$database->expects(self::exactly(6))->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void {
			$queries[] = $sql;
		});
		$database->expects(self::exactly(2))->method('singleQuery')->willReturn(['Field' => 'fallback_flag']);
		$schema = new DatabaseSchema($database);
		$schema->ensureTables();
		$schema->ensureTables();
		self::assertCount(6, $queries);
		foreach(['templates', 'variants', 'queue', 'deliveries', 'recipients', 'attachments'] as $table) {
			self::assertNotEmpty(array_filter($queries, static fn(string $sql): bool => str_contains($sql, 'CREATE TABLE IF NOT EXISTS base3_messaging_' . $table)));
		}
	}

	public function testAddsMissingFallbackColumnAndIndex(): void {
		$queries = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(null);
		$database->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		(new DatabaseSchema($database))->ensureTables();
		self::assertCount(8, $queries);
		self::assertStringContainsString('ADD COLUMN fallback_flag', implode(' ', $queries));
		self::assertStringContainsString('ADD KEY idx_base3_msg_var_fallback', implode(' ', $queries));
	}
}
