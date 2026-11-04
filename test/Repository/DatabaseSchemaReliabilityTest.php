<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseSchemaReliabilityTest extends TestCase {

	public function testFailedSchemaCreationMayRetryInsteadOfClaimingInitializationSucceeded(): void {
		$attempt = 0;
		$queries = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(['Field' => 'fallback_flag']);
		$database->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$attempt, &$queries): void {
			$attempt++;
			$queries[] = $sql;
			if($attempt === 1) {
				throw new RuntimeException('temporary schema failure');
			}
		});
		$schema = new DatabaseSchema($database);
		try {
			$schema->ensureTables();
			self::fail('A failed schema creation must throw.');
		} catch(RuntimeException $exception) {
			self::assertSame('temporary schema failure', $exception->getMessage());
		}
		$schema->ensureTables();
		$schema->ensureTables();
		self::assertCount(7, $queries);
		self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $queries[0]);
		self::assertSame($queries[0], $queries[1]);
	}

	public function testExistingColumnAndMissingIndexOnlyAddIndexWithoutChangingColumn(): void {
		$queries = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturnCallback(static function(string $sql): ?array {
			return str_contains($sql, 'SHOW COLUMNS') ? ['Field' => 'fallback_flag'] : null;
		});
		$database->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$queries): void {
			$queries[] = $sql;
		});
		(new DatabaseSchema($database))->ensureTables();
		self::assertCount(7, $queries);
		self::assertCount(1, array_filter($queries, static fn(string $sql): bool => str_contains($sql, 'ADD KEY idx_base3_msg_var_fallback')));
		self::assertStringNotContainsString('ADD COLUMN fallback_flag', implode(' ', $queries));
	}
}
