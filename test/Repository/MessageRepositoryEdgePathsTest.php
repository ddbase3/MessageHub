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
use Base3\MessageHub\Repository\DatabaseMessageQueueRepository;
use Base3\MessageHub\Repository\DatabaseMessageTemplateRepository;
use Base3\MessageHub\Repository\DatabaseMessageVariantRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageSerializer;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use Base3\MessagingFoundation\Dto\MessageAttachment;
use Base3\MessagingFoundation\Dto\QueuedMessage;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryEdgePathsTest extends TestCase {

	private function db(array &$queries, ?callable $singleQuery = null): IDatabase {
		$database = $this->createStub(IDatabase::class);
		$database->method('escape')->willReturnCallback(static fn(string $value): string => addslashes($value));
		$database->method('singleQuery')->willReturnCallback($singleQuery ?? static fn(string $sql): ?array => ['ok' => true]);
		$database->method('nonQuery')->willReturnCallback(static function(string $sql) use (&$queries): void { $queries[] = $sql; });
		return $database;
	}

	private function ids(): IMessageIdGenerator {
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturnCallback(static fn(string $prefix): string => $prefix . '-id');
		return $ids;
	}

	public function testQueueInsertWithDefaultScheduleEscapesSubjectAndPersistsMessage(): void {
		$queries = [];
		$database = $this->db($queries);
		$queue = new DatabaseMessageQueueRepository($database, new DatabaseSchema($database), $this->ids());
		self::assertSame('que-id', $queue->insert(new Message('notice', "O'Reilly", 'Some body'), 'log', -5, null));
		$inserts = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO base3_messaging_queue')));
		self::assertCount(1, $inserts);
		self::assertStringContainsString("O\\'Reilly", $inserts[0]);
		self::assertStringContainsString("'queued', -5, 0, 3,", $inserts[0]);
		self::assertStringContainsString('message_json', $inserts[0]);
	}

	public function testQueueClaimConvertsTwoRowsAndLocksEachBeforeReturning(): void {
		$queries = [];
		$database = $this->db($queries);
		$database->method('multiQuery')->willReturn([
			['id' => 'q1', 'message_json' => (new MessageSerializer())->encode(new Message('notice', 'One', '')),
				'transport_name' => 'log', 'status' => 'queued', 'attempts' => 0, 'max_attempts' => 3],
			['id' => 'q2', 'message_json' => (new MessageSerializer())->encode(new Message('notice', 'Two', '')),
				'transport_name' => 'null', 'status' => 'retry_wait', 'attempts' => 2, 'max_attempts' => 5]
		]);
		$queue = new DatabaseMessageQueueRepository($database, new DatabaseSchema($database), $this->ids());
		$claimed = $queue->claimNext(2, 300);
		self::assertCount(2, $claimed);
		self::assertSame('q1', $claimed[0]->getId());
		self::assertSame('q2', $claimed[1]->getId());
		self::assertSame('processing', $claimed[1]->getStatus());
		self::assertSame(2, $claimed[1]->getAttempts());
		self::assertSame(5, $claimed[1]->getMaxAttempts());
		$updates = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
		self::assertCount(2, $updates);
		self::assertStringContainsString("id='q1'", $updates[0]);
		self::assertStringContainsString("id='q2'", $updates[1]);
	}

	public function testQueueFailureWithSingleAllowedAttemptNeverSchedulesRetry(): void {
		$queries = [];
		$database = $this->db($queries, static fn(string $sql): ?array =>
			str_starts_with($sql, 'SELECT attempts, max_attempts FROM base3_messaging_queue')
				? ['attempts' => 0, 'max_attempts' => 1]
				: ['ok' => true]);
		$queue = new DatabaseMessageQueueRepository($database, new DatabaseSchema($database), $this->ids());
		$queue->markFailed('q1', "Auth's expired", -10);
		$updates = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'UPDATE base3_messaging_queue')));
		self::assertCount(1, $updates);
		self::assertStringContainsString("status='failed'", $updates[0]);
		self::assertStringContainsString('attempts=1', $updates[0]);
		self::assertStringContainsString("Auth\\'s expired", $updates[0]);
	}

	public function testDeliveryRecordsAllRecipientKindsAndInlineAttachmentMetadata(): void {
		$queries = [];
		$database = $this->db($queries);
		$repository = new DatabaseMessageDeliveryRepository($database, new DatabaseSchema($database), $this->ids());
		$message = new Message('notice', 'Subject', 'Body', '<p>HTML</p>', [
			new MessageAddress('to', 'to@example.test', 'To'),
			new MessageAddress('cc', 'cc@example.test', 'Copy'),
			new MessageAddress('bcc', 'hidden@example.test', 'Hidden')
		], [new MessageAttachment('/tmp/fake-inline.png', 'inline.png', 'image/png', true, 'img-42')]);
		$queued = new QueuedMessage('q1', $message, 'smtp', 'processing', 1, 3);
		self::assertSame('del-id', $repository->create($queued, $message));
		$recipients = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO base3_messaging_recipients')));
		$attachments = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO base3_messaging_attachments')));
		self::assertCount(3, $recipients);
		self::assertCount(1, $attachments);
		self::assertStringContainsString("'bcc'", $recipients[2]);
		self::assertStringContainsString("'hidden@example.test'", $recipients[2]);
		self::assertStringContainsString("'img-42'", $attachments[0]);
		self::assertStringContainsString(', 1, ', $attachments[0]);
	}

	public function testScopedTemplateLookupQuotesAllThreeDimensions(): void {
		$queries = [];
		$database = $this->db($queries, static function(string $sql) use (&$queries): ?array {
			$queries[] = $sql;
			return str_starts_with($sql, 'SELECT * FROM base3_messaging_templates') ? null : ['ok' => true];
		});
		$repository = new DatabaseMessageTemplateRepository($database, new DatabaseSchema($database), $this->ids());
		self::assertNull($repository->getByType("owner's notice", 'project', 'project-7'));
		$lookup = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'SELECT * FROM base3_messaging_templates')));
		self::assertCount(1, $lookup);
		self::assertStringContainsString("type_name='owner\\'s notice'", $lookup[0]);
		self::assertStringContainsString("scope_type='project'", $lookup[0]);
		self::assertStringContainsString("scope_id='project-7'", $lookup[0]);
	}

	public function testTemplatePageWithoutSearchUsesDefaultBoundsAndZeroTotal(): void {
		$queries = [];
		$database = $this->db($queries);
		$database->method('scalarQuery')->willReturn(null);
		$database->method('multiQuery')->willReturnCallback(static function(string $sql) use (&$queries): array {
			$queries[] = $sql;
			return [];
		});
		$repository = new DatabaseMessageTemplateRepository($database, new DatabaseSchema($database), $this->ids());
		self::assertSame(['rows' => [], 'total' => 0], $repository->page([]));
		self::assertStringContainsString('LIMIT 0, 50', $queries[count($queries) - 1]);
		self::assertStringNotContainsString(' WHERE ', $queries[count($queries) - 1]);
	}

	public function testVariantPreviewWithoutPlainTextDecodesEntitiesAndStructuralBreaks(): void {
		$queries = [];
		$database = $this->db($queries);
		$database->method('scalarQuery')->willReturn(1);
		$database->method('multiQuery')->willReturn([[
			'id' => 'v1', 'template_id' => 't1', 'language' => 'en', 'subject' => 'S',
			'body_text' => '', 'body_html' => '<p>Hello &amp; welcome</p><p>Next</p><script>hidden()</script>',
			'fallback_flag' => 0, 'enabled' => 0
		]]);
		$repository = new DatabaseMessageVariantRepository($database, new DatabaseSchema($database), $this->ids());
		$result = $repository->page([]);
		self::assertSame('Hello & welcome Next', $result['rows'][0]['body_text_preview']);
		self::assertSame('', $result['rows'][0]['fallback_label']);
		self::assertSame('Disabled', $result['rows'][0]['enabled_label']);
	}

	public function testSchemaAddsOnlyMissingFallbackIndexWhenColumnAlreadyExists(): void {
		$queries = [];
		$database = $this->db($queries, static fn(string $sql): ?array =>
			str_starts_with($sql, 'SHOW COLUMNS') ? ['Field' => 'fallback_flag'] : null);
		(new DatabaseSchema($database))->ensureTables();
		$alters = array_values(array_filter($queries, static fn(string $sql): bool => str_starts_with($sql, 'ALTER TABLE')));
		self::assertCount(1, $alters);
		self::assertStringContainsString('ADD KEY idx_base3_msg_var_fallback', $alters[0]);
	}
}
