<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseMessageQueueRepository;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageSerializer;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class DatabaseMessageQueueRepositoryTest extends TestCase {

	private function repository(IDatabase $db): DatabaseMessageQueueRepository {
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturn('queue-1');
		return new DatabaseMessageQueueRepository($db, new DatabaseSchema($db), $ids);
	}

	public function testInsertionPersistsSerializedMessageAndExplicitPriority(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$db->method('singleQuery')->willReturn(['ok' => 1]);
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$message = new Message('alert', 'Subject', 'Body');
		self::assertSame('queue-1', $this->repository($db)->insert($message, 'smtp', 7, 1000));
		$inserts = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO base3_messaging_queue')));
		self::assertCount(1, $inserts);
		self::assertStringContainsString("'smtp'", $inserts[0]);
		self::assertStringContainsString(', 7, 0, 3, ', $inserts[0]);
		self::assertStringContainsString('1970-01-01', $inserts[0]);
	}

	public function testClaimNextCapsLimitAndMarksClaimedRowsProcessing(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('multiQuery')->willReturn([[
			'id' => 'queue-1', 'message_json' => (new MessageSerializer())->encode(new Message('alert', 'Hi', 'body')),
			'transport_name' => 'log', 'status' => 'queued', 'attempts' => '1', 'max_attempts' => '3'
		]]);
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$claimed = $this->repository($db)->claimNext(1000, 5);
		self::assertCount(1, $claimed);
		self::assertSame('processing', $claimed[0]->getStatus());
		self::assertSame('alert', $claimed[0]->getMessage()->getTypeName());
		$selects = array_values(array_filter($queries, static fn(string $sql): bool => str_contains($sql, 'UPDATE base3_messaging_queue')));
		self::assertCount(1, $selects);
		self::assertStringContainsString("status='processing'", $selects[0]);
	}

	public function testFailureSchedulesRetryUntilAttemptLimit(): void {
		foreach([[0, 3, 'retry_wait'], [2, 3, 'failed']] as [$attempts, $maxAttempts, $expectedStatus]) {
			$writes = [];
			$db = $this->createStub(IDatabase::class);
			$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
			$db->method('singleQuery')->willReturn(['attempts' => $attempts, 'max_attempts' => $maxAttempts]);
			$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$writes): void { $writes[] = $sql; });
			$this->repository($db)->markFailed('queue-1', 'remote error', 10);
			$updates = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
			self::assertCount(1, $updates);
			self::assertStringContainsString("status='" . $expectedStatus . "'", $updates[0]);
			self::assertStringContainsString('locked_until=NULL', $updates[0]);
			self::assertStringContainsString("last_error='remote error'", $updates[0]);
		}
	}

	public function testMarkSentCancelAndPageApplyExpectedQueries(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('scalarQuery')->willReturn(2);
		$db->method('multiQuery')->willReturn([[
			'id' => 'q', 'type_name' => 'alert', 'transport_name' => 'log', 'subject' => 'Hello',
			'status' => 'sent', 'priority' => 10, 'attempts' => 1, 'max_attempts' => 3,
			'not_before' => 'date', 'created_at' => 'date', 'updated_at' => 'date'
		]]);
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$repo = $this->repository($db);
		$repo->markSent('q');
		$repo->cancel('other');
		$page = $repo->page(['page' => 2, 'pageSize' => 3, 'search' => 'Hello',
			'filters' => ['status' => 'sent', 'transport_name' => 'log', 'type_name' => 'alert']]);
		self::assertSame(2, $page['total']);
		self::assertSame(10, $page['rows'][0]['priority']);
		self::assertSame('', $page['rows'][0]['last_error']);
		self::assertStringContainsString("status='sent'", implode(' ', $queries));
		self::assertStringContainsString("status='cancelled'", implode(' ', $queries));
	}


	public function testClaimingEmptyQueueClampsMinimumBatchSizeWithoutUpdatingRows(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['column' => 'existing']);
		$db->method('multiQuery')->willReturnCallback(function(string $sql) use (&$queries): array {
			$queries[] = $sql;
			return [];
		});
		$repository = $this->repository($db);
		self::assertSame([], $repository->claimNext(-50, -10));
		self::assertCount(1, $queries);
		self::assertStringContainsString('LIMIT 0, 1', $queries[0]);
	}

	public function testFailedQueueWithMissingAttemptRecordUsesDocumentedRetryDefaults(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(null);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$queries): void { $queries[] = $sql; });
		$this->repository($db)->markFailed('q-missing', 'temporary', -9);
		$update = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
		self::assertCount(1, $update);
		self::assertStringContainsString("status='retry_wait'", $update[0]);
		self::assertStringContainsString('attempts=1', $update[0]);
		self::assertStringContainsString("last_error='temporary'", $update[0]);
	}

	public function testQueuePageAppliesSearchFiltersAndSafeBoundsToBothQueries(): void {
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$db->method('scalarQuery')->willReturnCallback(function(string $sql) use (&$queries): int { $queries[] = $sql; return 0; });
		$db->method('multiQuery')->willReturnCallback(function(string $sql) use (&$queries): array { $queries[] = $sql; return []; });
		$page = $this->repository($db)->page(['page' => 3, 'pageSize' => 999, 'search' => "O'Reilly",
			'filters' => ['status' => ' queued ', 'transport_name' => 'log', 'type_name' => 'invoice']]);
		self::assertSame([], $page['rows']);
		self::assertSame(0, $page['total']);
		self::assertCount(2, $queries);
		foreach($queries as $query) {
			self::assertStringContainsString("status='queued'", $query);
			self::assertStringContainsString("transport_name='log'", $query);
			self::assertStringContainsString("type_name='invoice'", $query);
			self::assertStringContainsString("o\\'reilly", $query);
		}
		self::assertStringContainsString('LIMIT 500, 250', $queries[1]);
	}

}
