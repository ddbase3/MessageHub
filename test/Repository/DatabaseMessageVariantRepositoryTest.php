<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseMessageVariantRepository;
use MessageHub\Repository\DatabaseSchema;
use MessagingFoundation\Api\IMessageIdGenerator;
use PHPUnit\Framework\TestCase;

final class DatabaseMessageVariantRepositoryTest extends TestCase {

	private function repository(IDatabase $db): DatabaseMessageVariantRepository {
		return new DatabaseMessageVariantRepository($db, new DatabaseSchema($db), $this->createStub(IMessageIdGenerator::class));
	}

	public function testFetchByLanguageAndFallbackUseDistinctQueries(): void {
		$queries = [];
		$row = ['id' => 'v1', 'template_id' => 't1', 'language' => 'de', 'subject' => 'Betreff', 'body_text' => 'Text', 'enabled' => 1, 'fallback_flag' => 1];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('singleQuery')->willReturnCallback(function(string $sql) use (&$queries, $row): ?array {
			$queries[] = $sql;
			return str_starts_with($sql, 'SELECT') ? $row : ['ok' => true];
		});
		$repo = $this->repository($db);
		self::assertSame('de', $repo->getForTemplate('t1', 'de')->getLanguage());
		self::assertSame('v1', $repo->getFallbackForTemplate('t1')->getId());
		self::assertStringContainsString('language=', implode(' ', $queries));
		self::assertStringContainsString('fallback_flag=1', implode(' ', $queries));
	}

	public function testPageBuildsReadableSafeBodyPreview(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('scalarQuery')->willReturn(1);
		$db->method('multiQuery')->willReturn([[
			'id' => 'v1', 'template_id' => 't1', 'language' => 'en', 'subject' => 'Subject',
			'body_text' => 'Fallback', 'body_html' => '<style>hidden</style><p>Hello <b>world</b></p><script>evil()</script>',
			'fallback_flag' => 1, 'enabled' => 1
		]]);
		$page = $this->repository($db)->page(['template_id' => 't1', 'pageSize' => 5]);
		self::assertSame(1, $page['total']);
		self::assertSame('Fallback', $page['rows'][0]['fallback_label']);
		self::assertSame('Hello world', $page['rows'][0]['body_text_preview']);
	}

	public function testDeleteRemovesOnlyRequestedVariant(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$this->repository($db)->delete('v1');
		self::assertCount(1, array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'DELETE FROM base3_messaging_variants')));
	}


	public function testListVariantsConvertsAllRowsToDtoAndKeepsLanguageOrder(): void {
		$sql = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['exists' => true]);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('multiQuery')->willReturnCallback(function(string $query) use (&$sql): array {
			$sql[] = $query;
			return [
				['id' => 'de', 'template_id' => 't1', 'language' => 'de', 'subject' => 'DE', 'body_text' => 'Text', 'enabled' => 1],
				['id' => 'en', 'template_id' => 't1', 'language' => 'en', 'subject' => 'EN', 'body_text' => 'Body', 'enabled' => 0]
			];
		});
		$variants = $this->repository($db)->listByTemplate('t1');
		self::assertCount(2, $variants);
		self::assertSame('DE', $variants[0]->getSubject());
		self::assertFalse($variants[1]->isEnabled());
		self::assertStringContainsString('ORDER BY language ASC', $sql[0]);
	}

	public function testVariantPreviewFallsBackToPlainTextAndIsLimitedToTwoHundredFortyCharacters(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['exists' => true]);
		$db->method('scalarQuery')->willReturn(1);
		$db->method('multiQuery')->willReturn([[
			'id' => 'v1', 'template_id' => 't1', 'language' => 'en', 'subject' => 'Subject',
			'body_text' => str_repeat('A', 300), 'body_html' => '<style> hidden </style>   ',
			'fallback_flag' => 0, 'enabled' => 0
		]]);
		$row = $this->repository($db)->page([])['rows'][0];
		self::assertSame(240, strlen($row['body_text_preview']));
		self::assertSame('', $row['fallback_label']);
		self::assertSame('Disabled', $row['enabled_label']);
	}

}
