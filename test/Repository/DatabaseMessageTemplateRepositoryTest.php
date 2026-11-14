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
use Base3\MessageHub\Repository\DatabaseMessageTemplateRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Dto\MessageTemplate;
use PHPUnit\Framework\TestCase;

final class DatabaseMessageTemplateRepositoryTest extends TestCase {

	private function repository(IDatabase $database): DatabaseMessageTemplateRepository {
		return new DatabaseMessageTemplateRepository($database, new DatabaseSchema($database), $this->createStub(IMessageIdGenerator::class));
	}

	public function testInsertAndUpdateProduceSeparateStatements(): void {
		foreach([false, true] as $exists) {
			$statements = [];
			$database = $this->createStub(IDatabase::class);
			$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
			$database->method('singleQuery')->willReturnCallback(static function(string $sql) use ($exists): ?array {
				return str_contains($sql, 'WHERE id=') && $exists ? ['id' => 't1', 'type_name' => 'alert', 'label' => 'old'] : null;
			});
			$database->method('nonQuery')->willReturnCallback(function(string $sql) use (&$statements): void { $statements[] = $sql; });
			$this->repository($database)->save(new MessageTemplate('t1', 'alert', "O'Reilly", 'desc', 'global', '', 'smtp', false));
			$statement = implode('\n', array_filter($statements, static fn(string $sql): bool => str_contains($sql, 'base3_messaging_templates SET') || str_contains($sql, 'INTO base3_messaging_templates')));
			self::assertStringContainsString($exists ? 'UPDATE base3_messaging_templates SET' : 'INSERT INTO base3_messaging_templates', $statement);
			self::assertStringContainsString("O\\'Reilly", $statement);
		}
	}

	public function testGetByIdAndTypeMapFieldsAndHandleMissing(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$database->method('singleQuery')->willReturnCallback(static fn(string $sql): ?array => str_contains($sql, "type_name='unknown'") ? null : [
			'id' => 't1', 'type_name' => 'alert', 'label' => 'Alert', 'description' => 'text',
			'scope_type' => 'team', 'scope_id' => '42', 'default_transport' => 'log', 'enabled' => 1
		]);
		$repo = $this->repository($database);
		self::assertSame('Alert', $repo->getById('t1')->getLabel());
		self::assertSame('team', $repo->getByType('alert', 'team', '42')->getScopeType());
		self::assertNull($repo->getByType('unknown'));
	}

	public function testDeleteRemovesVariantsBeforeTemplate(): void {
		$statements = [];
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$database->method('singleQuery')->willReturn(['ok' => true]);
		$database->method('nonQuery')->willReturnCallback(function(string $sql) use (&$statements): void { $statements[] = $sql; });
		$this->repository($database)->delete('t42');
		$deletes = array_values(array_filter($statements, static fn(string $sql): bool => str_starts_with($sql, 'DELETE')));
		self::assertCount(2, $deletes);
		self::assertStringContainsString('base3_messaging_variants', $deletes[0]);
		self::assertStringContainsString('base3_messaging_templates', $deletes[1]);
	}

	public function testListsAllAndPaginatesWithBoundedSizeAndEscapedSearch(): void {
		$queries = [];
		$row = ['id' => 't1', 'type_name' => 'alert', 'label' => 'Alert', 'enabled' => 0];
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$database->method('singleQuery')->willReturn(['ok' => true]);
		$database->method('scalarQuery')->willReturn(17);
		$database->method('multiQuery')->willReturnCallback(function(string $sql) use ($row, &$queries): array { $queries[] = $sql; return [$row]; });
		$repo = $this->repository($database);
		self::assertSame('alert', $repo->listAll()[0]->getTypeName());
		$page = $repo->page(['page' => 3, 'pageSize' => 9999, 'search' => " Ca'T "]);
		self::assertSame(17, $page['total']);
		self::assertSame('Disabled', $page['rows'][0]['enabled_label']);
		self::assertStringContainsString('LIMIT 500, 250', $queries[1]);
		self::assertStringContainsString("%ca\\'t%", $queries[1]);
	}
}
