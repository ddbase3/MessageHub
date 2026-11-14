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

namespace Base3\MessageHub\Test\Repository;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessageHub\Repository\DatabaseSchema;
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
