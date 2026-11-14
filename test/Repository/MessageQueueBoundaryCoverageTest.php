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
use Base3\MessageHub\Repository\DatabaseMessageQueueRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageSerializer;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class MessageQueueBoundaryCoverageTest extends TestCase {

	private function database(array &$writes, array &$reads, ?array $records = null): IDatabase {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$database->method('singleQuery')->willReturnCallback(static fn(string $sql): ?array =>
			str_starts_with($sql, 'SHOW ') ? ['present' => true] : null);
		$database->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$writes): void { $writes[] = $sql; });
		$database->method('multiQuery')->willReturnCallback(static function(string $sql) use (&$reads, $records): array {
			$reads[] = $sql;
			return $records ?? [];
		});
		return $database;
	}

	private function repository(IDatabase $database): DatabaseMessageQueueRepository {
		$generator = $this->createStub(IMessageIdGenerator::class);
		$generator->method('createId')->willReturn('queue-id');
		return new DatabaseMessageQueueRepository($database, new DatabaseSchema($database), $generator);
	}

	public function testEmptyClaimWithNegativeLimitAndLockUsesMinimumSelectionAndNoUpdate(): void {
		$writes = [];
		$reads = [];
		$repository = $this->repository($this->database($writes, $reads));
		self::assertSame([], $repository->claimNext(-10, -100));
		self::assertCount(1, $reads);
		self::assertStringContainsString('LIMIT 0, 1', $reads[0]);
		$updates = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
		self::assertSame([], $updates);
	}

	public function testClaimCapsMaximumBatchAtOneHundredAndDoesNotModifyEmptyQueue(): void {
		$writes = [];
		$reads = [];
		$repository = $this->repository($this->database($writes, $reads));
		self::assertSame([], $repository->claimNext(10000, 300));
		self::assertStringContainsString('LIMIT 0, 100', $reads[0]);
	}

	public function testQueuePagePreservesNullCountsAsZeroAndClampsOversizedPages(): void {
		$writes = [];
		$reads = [];
		$database = $this->database($writes, $reads);
		$database->method('scalarQuery')->willReturn(null);
		$repository = $this->repository($database);
		self::assertSame(['rows' => [], 'total' => 0], $repository->page(['page' => 0, 'pageSize' => 999]));
		self::assertStringContainsString('LIMIT 0, 250', $reads[0]);
		self::assertStringNotContainsString(' WHERE ', $reads[0]);
	}

	public function testQueuePageUsesAllThreeFiltersAndEscapedSearchWithExpectedOffset(): void {
		$writes = [];
		$reads = [];
		$database = $this->database($writes, $reads);
		$count = [];
		$database->method('scalarQuery')->willReturnCallback(static function(string $sql) use (&$count): int {
			$count[] = $sql;
			return 10;
		});
		$repository = $this->repository($database);
		$result = $repository->page(['page' => 3, 'pageSize' => 5, 'search' => "  O'Reilly ",
			'filters' => ['status' => ' retry_wait ', 'transport_name' => ' smtp ', 'type_name' => ' alert ']]);
		self::assertSame(10, $result['total']);
		self::assertSame([], $result['rows']);
		self::assertStringContainsString('LIMIT 10, 5', $reads[0]);
		foreach(["o\\'reilly", "status='retry_wait'", "transport_name='smtp'", "type_name='alert'"] as $needle) {
			self::assertStringContainsString($needle, $reads[0]);
			self::assertStringContainsString($needle, $count[0]);
		}
	}

	public function testClaimedMessageRestoresSerializedPayloadAndOverridesStatus(): void {
		$writes = [];
		$reads = [];
		$message = new Message('important', 'Subject', 'Body');
		$database = $this->database($writes, $reads, [[
			'id' => 'q-1', 'message_json' => (new MessageSerializer())->encode($message),
			'transport_name' => 'log', 'status' => 'retry_wait', 'attempts' => '2', 'max_attempts' => '5'
		]]);
		$repository = $this->repository($database);
		$claimed = $repository->claimNext(1, 0);
		self::assertCount(1, $claimed);
		self::assertSame('processing', $claimed[0]->getStatus());
		self::assertSame('important', $claimed[0]->getMessage()->getTypeName());
		self::assertSame('Body', $claimed[0]->getMessage()->getBodyText());
		self::assertSame(2, $claimed[0]->getAttempts());
		self::assertSame(5, $claimed[0]->getMaxAttempts());
		$updates = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
		self::assertCount(1, $updates);
		self::assertStringContainsString("WHERE id='q-1' LIMIT 1", $updates[0]);
	}
}
