<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseMessageVariantRepository;
use MessageHub\Repository\DatabaseSchema;
use MessagingFoundation\Api\IMessageIdGenerator;
use PHPUnit\Framework\TestCase;

final class MessageVariantReadCoverageTest extends TestCase {

	private function database(array &$queries, ?array $record = null): IDatabase {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$database->method('singleQuery')->willReturnCallback(static function(string $sql) use (&$queries, $record): ?array {
			$queries[] = $sql;
			return str_starts_with($sql, 'SHOW ') ? ['present' => true] : $record;
		});
		return $database;
	}

	private function repository(IDatabase $database): DatabaseMessageVariantRepository {
		return new DatabaseMessageVariantRepository(
			$database, new DatabaseSchema($database), $this->createStub(IMessageIdGenerator::class));
	}

	private function record(array $extra = []): array {
		return array_replace(['id' => 'var-1', 'template_id' => 'tpl-1', 'language' => 'en',
			'subject' => 'Subject', 'body_text' => 'Body', 'body_html' => '<p>Body</p>',
			'enabled' => 1, 'fallback_flag' => 0], $extra);
	}

	public function testFetchByIdReturnsTypedDtoWithOriginalContent(): void {
		$queries = [];
		$repository = $this->repository($this->database($queries, $this->record(['enabled' => 0])));
		$variant = $repository->getById('var-1');
		self::assertNotNull($variant);
		self::assertSame('var-1', $variant->getId());
		self::assertSame('tpl-1', $variant->getTemplateId());
		self::assertSame('en', $variant->getLanguage());
		self::assertSame('Subject', $variant->getSubject());
		self::assertSame('Body', $variant->getBodyText());
		self::assertSame('<p>Body</p>', $variant->getBodyHtml());
		self::assertFalse($variant->isEnabled());
		self::assertStringContainsString("WHERE id='var-1' LIMIT 1", $queries[count($queries) - 1]);
	}

	public function testMissingVariantByIdReturnsNullAndEscapesLookup(): void {
		$queries = [];
		$repository = $this->repository($this->database($queries));
		self::assertNull($repository->getById("var'bad"));
		self::assertStringContainsString("WHERE id='var\\'bad'", $queries[count($queries) - 1]);
	}

	public function testFallbackLookupRequestsEnabledFlagAndLatestVariant(): void {
		$queries = [];
		$repository = $this->repository($this->database($queries, $this->record(['fallback_flag' => 1])));
		$variant = $repository->getFallbackForTemplate('tpl-1');
		self::assertNotNull($variant);
		self::assertSame('var-1', $variant->getId());
		$sql = $queries[count($queries) - 1];
		self::assertStringContainsString("template_id='tpl-1'", $sql);
		self::assertStringContainsString('fallback_flag=1 AND enabled=1 ORDER BY updated_at DESC LIMIT 1', $sql);
	}

	public function testLanguageLookupRequiresEnabledVariantAndQuotesBothParts(): void {
		$queries = [];
		$repository = $this->repository($this->database($queries));
		self::assertNull($repository->getForTemplate("tpl'a", "de'b"));
		$sql = $queries[count($queries) - 1];
		self::assertStringContainsString("template_id='tpl\\'a'", $sql);
		self::assertStringContainsString("language='de\\'b'", $sql);
		self::assertStringContainsString('AND enabled=1 LIMIT 1', $sql);
	}

	public function testListByTemplateKeepsDatabaseOrderingAndMapsEachRecord(): void {
		$queries = [];
		$database = $this->database($queries);
		$database->method('multiQuery')->willReturn([
			$this->record(['id' => 'a', 'language' => 'de']),
			$this->record(['id' => 'b', 'language' => 'en', 'enabled' => 0])
		]);
		$rows = $this->repository($database)->listByTemplate('tpl-1');
		self::assertCount(2, $rows);
		self::assertSame(['de', 'en'], array_map(static fn($item): string => $item->getLanguage(), $rows));
		self::assertTrue($rows[0]->isEnabled());
		self::assertFalse($rows[1]->isEnabled());
	}
}
