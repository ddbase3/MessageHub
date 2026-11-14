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
use Base3\MessageHub\Repository\DatabaseMessageDeliveryRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageSerializer;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageDeliveryResult;
use Base3\MessagingFoundation\Dto\QueuedMessage;
use PHPUnit\Framework\TestCase;

final class MessageDeliveryRepositoryBoundaryCoverageTest extends TestCase {

	private function database(array &$writes, array &$reads, ?callable $singleRow = null, ?callable $multipleRows = null): IDatabase {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$database->method('singleQuery')->willReturnCallback(static fn(string $sql): ?array =>
			str_starts_with($sql, 'SHOW ') ? ['present' => true] : ($singleRow !== null ? $singleRow($sql) : null));
		$database->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$writes): void { $writes[] = $sql; });
		$database->method('multiQuery')->willReturnCallback(static function(string $sql) use (&$reads, $multipleRows): array {
			$reads[] = $sql;
			return $multipleRows !== null ? $multipleRows($sql) : [];
		});
		return $database;
	}

	private function repository(IDatabase $database): DatabaseMessageDeliveryRepository {
		$generator = $this->createStub(IMessageIdGenerator::class);
		$generator->method('createId')->willReturnCallback(static fn(string $prefix): string => $prefix . '-123');
		return new DatabaseMessageDeliveryRepository($database, new DatabaseSchema($database), $generator);
	}

	public function testDetailIncludesStoredMessageResultRecipientsAndAttachments(): void {
		$writes = [];
		$reads = [];
		$message = new Message('notice', 'Subject', 'Body');
		$database = $this->database($writes, $reads,
			static fn(string $sql): ?array => [
				'id' => 'del-1', 'queue_id' => 'q-1', 'type_name' => 'notice',
				'transport_name' => 'log', 'subject' => 'Subject', 'status' => 'sent',
				'message_json' => (new MessageSerializer())->encode($message),
				'result_json' => '{"success":true}',
				'created_at' => '2026-10-01', 'updated_at' => '2026-10-02'
			],
			static function(string $sql): array {
				if(str_contains($sql, 'base3_messaging_recipients')) {
					return [['kind' => 'to', 'address' => 'person@example.test', 'label' => 'Person']];
				}
				return [['path' => '/tmp/file', 'filename' => 'file', 'mime_type' => 'text/plain', 'inline_flag' => 0, 'content_id' => '']];
			});
		$detail = $this->repository($database)->detail('del-1');
		self::assertIsArray($detail);
		self::assertSame('sent', $detail['status']);
		self::assertSame('notice', $detail['message']['type_name']);
		self::assertSame(['success' => true], $detail['result']);
		self::assertSame('person@example.test', $detail['recipients'][0]['address']);
		self::assertSame('file', $detail['attachments'][0]['filename']);
		self::assertSame('', $detail['error_message']);
		self::assertSame('', $detail['sent_at']);
		self::assertCount(2, $reads);
	}

	public function testDetailEscapesRequestedIdAndNeverQueriesChildrenWhenMissing(): void {
		$writes = [];
		$reads = [];
		$selected = [];
		$database = $this->database($writes, $reads,
			static function(string $sql) use (&$selected): ?array {
				$selected[] = $sql;
				return null;
			});
		self::assertNull($this->repository($database)->detail("missing'item"));
		self::assertSame([], $reads);
		self::assertStringContainsString("WHERE id='missing\\'item'", $selected[count($selected) - 1]);
	}

	public function testDeliveryPageClampsBoundsWithoutAnySearchOrFilter(): void {
		$writes = [];
		$reads = [];
		$database = $this->database($writes, $reads);
		$database->method('scalarQuery')->willReturn(null);
		$result = $this->repository($database)->page(['page' => -5, 'pageSize' => 9999, 'filters' => 'not an array']);
		self::assertSame(['rows' => [], 'total' => 0], $result);
		self::assertStringContainsString('LIMIT 0, 250', $reads[0]);
		self::assertStringNotContainsString(' WHERE ', $reads[0]);
	}

	public function testDeliveryPageCombinesEscapedSearchAndAllFilters(): void {
		$writes = [];
		$reads = [];
		$database = $this->database($writes, $reads);
		$countSql = [];
		$database->method('scalarQuery')->willReturnCallback(static function(string $sql) use (&$countSql): int {
			$countSql[] = $sql;
			return 7;
		});
		$result = $this->repository($database)->page(['page' => 4, 'pageSize' => 3,
			'search' => "  Error's  ", 'filters' => ['status' => ' failed ', 'transport_name' => ' smtp ', 'type_name' => ' mail ']]);
		self::assertSame(7, $result['total']);
		self::assertSame([], $result['rows']);
		self::assertStringContainsString('LIMIT 9, 3', $reads[0]);
		foreach(["error\\'s", "status='failed'", "transport_name='smtp'", "type_name='mail'"] as $needle) {
			self::assertStringContainsString($needle, $reads[0]);
			self::assertStringContainsString($needle, $countSql[0]);
		}
	}

	public function testCleanupDeletesRecipientsAndAttachmentsBeforeEachDelivery(): void {
		$writes = [];
		$reads = [];
		$database = $this->database($writes, $reads);
		$database->method('listQuery')->willReturn(["old'1", 'old-2']);
		self::assertSame(2, $this->repository($database)->cleanup(30));
		$deletes = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'DELETE')));
		self::assertCount(6, $deletes);
		foreach(['old\\\'1', 'old-2'] as $i => $id) {
			self::assertStringContainsString('base3_messaging_recipients', $deletes[$i * 3]);
			self::assertStringContainsString('base3_messaging_attachments', $deletes[$i * 3 + 1]);
			self::assertStringContainsString('base3_messaging_deliveries', $deletes[$i * 3 + 2]);
			self::assertStringContainsString($id, $deletes[$i * 3]);
		}
	}

	public function testZeroAndNegativeRetentionNeverDeleteDeliveryRecords(): void {
		foreach([0, -3] as $days) {
			$writes = [];
			$reads = [];
			$database = $this->database($writes, $reads);
			$database->method('listQuery')->willReturnCallback(static function(string $sql): array {
				self::fail('Retention is disabled, query should never run: ' . $sql);
			});
			self::assertSame(0, $this->repository($database)->cleanup($days));
			self::assertSame([], array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'DELETE'))));
		}
	}

	public function testFailedDeliveryEscapesErrorAndNeverSetsSentTimestamp(): void {
		$writes = [];
		$reads = [];
		$repository = $this->repository($this->database($writes, $reads));
		$repository->finish('del-1', new MessageDeliveryResult(false, "token's expired"));
		$update = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_deliveries')));
		self::assertCount(1, $update);
		self::assertStringContainsString("status='failed'", $update[0]);
		self::assertStringContainsString("error_message='token\\'s expired'", $update[0]);
		self::assertStringContainsString('sent_at=NULL', $update[0]);
	}

	public function testDeliveryCreateWithoutRecipientsOrAttachmentsWritesOnlyMainRecord(): void {
		$writes = [];
		$reads = [];
		$repository = $this->repository($this->database($writes, $reads));
		$message = new Message('notice', "Person's notice", 'Body');
		$queued = new QueuedMessage('q-1', $message, 'log', 'processing', 0, 3);
		self::assertSame('del-123', $repository->create($queued, $message));
		$inserts = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'INSERT')));
		self::assertCount(1, $inserts);
		self::assertStringContainsString("Person\\'s notice", $inserts[0]);
	}
}
