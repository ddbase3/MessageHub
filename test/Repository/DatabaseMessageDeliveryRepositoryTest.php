<?php declare(strict_types=1);

namespace MessageHub\Test\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Repository\DatabaseMessageDeliveryRepository;
use MessageHub\Repository\DatabaseSchema;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use MessagingFoundation\Dto\MessageDeliveryResult;
use MessagingFoundation\Dto\QueuedMessage;
use PHPUnit\Framework\TestCase;

final class DatabaseMessageDeliveryRepositoryTest extends TestCase {

	private function repository(IDatabase $db): DatabaseMessageDeliveryRepository {
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturnCallback(static fn(string $prefix): string => $prefix . '-1');
		return new DatabaseMessageDeliveryRepository($db, new DatabaseSchema($db), $ids);
	}

	private function db(array &$writes): IDatabase {
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$db->method('nonQuery')->willReturnCallback(function(string $sql) use (&$writes): void { $writes[] = $sql; });
		return $db;
	}

	public function testCreatePersistsMessageRecipientsAndAttachments(): void {
		$writes = [];
		$db = $this->db($writes);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$message = new Message('invoice', 'Subject', 'Body', '', [new MessageAddress('to', 'user@example.test', 'User')],
			[new MessageAttachment('/tmp/document.pdf', 'document.pdf', 'application/pdf', false)]);
		$queued = new QueuedMessage('q1', $message, 'smtp', 'processing', 1, 3);
		self::assertSame('del-1', $this->repository($db)->create($queued, $message));
		$insert = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO')));
		self::assertCount(3, $insert);
		self::assertStringContainsString('base3_messaging_deliveries', $insert[0]);
		self::assertStringContainsString('base3_messaging_recipients', $insert[1]);
		self::assertStringContainsString('base3_messaging_attachments', $insert[2]);
		self::assertStringContainsString('user@example.test', $insert[1]);
	}

	public function testFinishRecordsSuccessOrErrorWithoutMarkingFailureSent(): void {
		foreach([new MessageDeliveryResult(true, 'ok'), new MessageDeliveryResult(false, 'network')] as $result) {
			$writes = [];
			$db = $this->db($writes);
			$db->method('singleQuery')->willReturn(['ok' => true]);
			$this->repository($db)->finish('d1', $result);
			$updates = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_deliveries')));
			self::assertCount(1, $updates);
			self::assertStringContainsString("status='" . ($result->isSuccess() ? 'sent' : 'failed') . "'", $updates[0]);
			self::assertStringContainsString($result->isSuccess() ? 'sent_at=' : 'sent_at=NULL', $updates[0]);
		}
	}

	public function testDetailDistinguishesMissingAndPresentRecords(): void {
		$row = ['id' => 'd1', 'queue_id' => 'q1', 'type_name' => 'alert', 'transport_name' => 'log',
			'subject' => 'Hello', 'status' => 'sent', 'message_json' => '{"subject":"Hi"}',
			'result_json' => '{"success":true}', 'created_at' => 'now', 'updated_at' => 'now'];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturnCallback(static fn(string $sql): ?array => str_starts_with($sql, 'SELECT * FROM base3_messaging_deliveries') ? $row : ['ok' => true]);
		$db->method('multiQuery')->willReturn([['kind' => 'to', 'address' => 'user@example.test']]);
		$detail = $this->repository($db)->detail('d1');
		self::assertSame('d1', $detail['id']);
		self::assertSame(['subject' => 'Hi'], $detail['message']);
		self::assertTrue($detail['result']['success']);
		self::assertCount(1, $detail['recipients']);
		$dbMissing = $this->createStub(IDatabase::class);
		$dbMissing->method('singleQuery')->willReturn(null);
		self::assertNull($this->repository($dbMissing)->detail('missing'));
	}

	public function testCleanupDeletesChildrenBeforeDeliveryAndCanBeDisabled(): void {
		$writes = [];
		$db = $this->db($writes);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('listQuery')->willReturn(['d1', 'd2']);
		$repo = $this->repository($db);
		self::assertSame(0, $repo->cleanup(0));
		self::assertSame(2, $repo->cleanup(30));
		$deletes = array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'DELETE FROM')));
		self::assertCount(6, $deletes);
		self::assertStringContainsString('base3_messaging_recipients', $deletes[0]);
		self::assertStringContainsString('base3_messaging_attachments', $deletes[1]);
		self::assertStringContainsString('base3_messaging_deliveries', $deletes[2]);
	}

	public function testPageReturnsNormalizedAdminRowsAndTotal(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('scalarQuery')->willReturn(4);
		$db->method('multiQuery')->willReturn([[
			'id' => 'd1', 'queue_id' => 'q1', 'type_name' => 'notice', 'transport_name' => 'null',
			'subject' => 'Hi', 'status' => 'sent', 'attempts' => '2', 'created_at' => 'today', 'updated_at' => 'today'
		]]);
		$page = $this->repository($db)->page(['pageSize' => -1, 'filters' => ['status' => 'sent'], 'search' => 'hello']);
		self::assertSame(4, $page['total']);
		self::assertSame(2, $page['rows'][0]['attempts']);
		self::assertSame('', $page['rows'][0]['error_message']);
	}


	public function testCleanupWithoutExpiredDeliveriesExecutesNoDeletes(): void {
		$writes = [];
		$db = $this->db($writes);
		$db->method('singleQuery')->willReturn(['ok' => true]);
		$db->method('listQuery')->willReturn([]);
		self::assertSame(0, $this->repository($db)->cleanup(15));
		self::assertSame([], array_values(array_filter($writes, static fn(string $sql): bool => str_starts_with($sql, 'DELETE FROM'))));
	}

	public function testDeliveryDetailWithInvalidSerializedFieldsUsesEmptyArrays(): void {
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturnCallback(static function(string $sql): ?array {
			if(str_starts_with($sql, 'SELECT * FROM base3_messaging_deliveries')) {
				return ['id' => 'd', 'queue_id' => 'q', 'type_name' => 'type', 'transport_name' => 'log',
					'subject' => 'Subject', 'status' => 'failed', 'message_json' => '{broken',
					'result_json' => 'false', 'created_at' => 'now', 'updated_at' => 'now'];
			}
			return ['exists' => true];
		});
		$db->method('multiQuery')->willReturn([]);
		$detail = $this->repository($db)->detail('d');
		self::assertSame([], $detail['message']);
		self::assertSame([], $detail['result']);
		self::assertSame('', $detail['error_message']);
		self::assertSame([], $detail['recipients']);
		self::assertSame([], $detail['attachments']);
		self::assertSame('', $detail['sent_at']);
	}

	public function testDeliveryPageFiltersStatusTransportAndTypeWithEscapedSearch(): void {
		$sql = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturn(['ok' => 1]);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$db->method('scalarQuery')->willReturnCallback(function(string $query) use (&$sql): int { $sql[] = $query; return 0; });
		$db->method('multiQuery')->willReturnCallback(function(string $query) use (&$sql): array { $sql[] = $query; return []; });
		$result = $this->repository($db)->page(['page' => 2, 'pageSize' => 1, 'search' => "O'Reilly",
			'filters' => ['status' => ' failed ', 'transport_name' => 'log', 'type_name' => 'notice']]);
		self::assertSame(['rows' => [], 'total' => 0], $result);
		self::assertCount(2, $sql);
		foreach($sql as $query) {
			self::assertStringContainsString("status='failed'", $query);
			self::assertStringContainsString("transport_name='log'", $query);
			self::assertStringContainsString("type_name='notice'", $query);
			self::assertStringContainsString("o\\'reilly", $query);
		}
		self::assertStringContainsString('LIMIT 1, 1', $sql[1]);
	}

}
