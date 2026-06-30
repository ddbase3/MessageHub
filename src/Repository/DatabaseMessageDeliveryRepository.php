<?php declare(strict_types=1);

namespace MessageHub\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Service\MessageSerializer;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use MessagingFoundation\Dto\MessageDeliveryResult;
use MessagingFoundation\Dto\QueuedMessage;

final class DatabaseMessageDeliveryRepository implements IMessageDeliveryRepository {

	use DatabaseRepositoryTrait;

	private MessageSerializer $serializer;

	public function __construct(
		private readonly IDatabase $database,
		private readonly DatabaseSchema $schema,
		private readonly IMessageIdGenerator $idGenerator
	) {
		$this->serializer = new MessageSerializer();
	}

	public function ensureStorage(): void {
		$this->schema->ensureTables();
	}

	public function create(QueuedMessage $queuedMessage, Message $message): string {
		$this->ensureStorage();
		$id = $this->idGenerator->createId('del');
		$now = $this->now();
		$this->database->nonQuery('INSERT INTO base3_messaging_deliveries (id, queue_id, type_name, transport_name, subject, status, attempts, message_json, created_at, updated_at) VALUES (' . $this->quote($id) . ', ' . $this->quote($queuedMessage->getId()) . ', ' . $this->quote($message->getTypeName()) . ', ' . $this->quote($queuedMessage->getTransportName()) . ', ' . $this->quote($message->getSubject()) . ', \'processing\', ' . (int)$queuedMessage->getAttempts() . ', ' . $this->quote($this->serializer->encode($message)) . ', ' . $this->quote($now) . ', ' . $this->quote($now) . ')');
		foreach($message->getRecipients() as $recipient) {
			$this->insertRecipient($id, $recipient);
		}
		foreach($message->getAttachments() as $attachment) {
			$this->insertAttachment($id, $attachment);
		}
		return $id;
	}

	public function finish(string $deliveryId, MessageDeliveryResult $result): void {
		$this->ensureStorage();
		$now = $this->now();
		$status = $result->isSuccess() ? 'sent' : 'failed';
		$resultJson = $this->encodeJson($result->toArray());
		$this->database->nonQuery('UPDATE base3_messaging_deliveries SET status=' . $this->quote($status) . ', error_message=' . $this->quote($result->isSuccess() ? '' : $result->getMessage()) . ', result_json=' . $this->quote($resultJson) . ', updated_at=' . $this->quote($now) . ', sent_at=' . ($result->isSuccess() ? $this->quote($now) : 'NULL') . ' WHERE id=' . $this->quote($deliveryId) . ' LIMIT 1');
	}

	public function page(array $request): array {
		$this->ensureStorage();
		[$page, $pageSize, $offset] = $this->normalizePage($request);
		$where = $this->buildWhere($request);
		$total = (int)($this->database->scalarQuery('SELECT COUNT(*) FROM base3_messaging_deliveries' . $where) ?? 0);
		$rows = $this->database->multiQuery('SELECT id, queue_id, type_name, transport_name, subject, status, attempts, error_message, created_at, updated_at, sent_at FROM base3_messaging_deliveries' . $where . ' ORDER BY created_at DESC LIMIT ' . $offset . ', ' . $pageSize);
		return ['rows' => array_map(fn(array $row) => $this->adminRow($row), $rows), 'total' => $total];
	}

	public function detail(string $deliveryId): ?array {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT * FROM base3_messaging_deliveries WHERE id=' . $this->quote($deliveryId) . ' LIMIT 1');
		if(!is_array($row)) {
			return null;
		}
		$recipients = $this->database->multiQuery('SELECT kind, address, label FROM base3_messaging_recipients WHERE delivery_id=' . $this->quote($deliveryId) . ' ORDER BY kind ASC, address ASC');
		$attachments = $this->database->multiQuery('SELECT path, filename, mime_type, inline_flag, content_id FROM base3_messaging_attachments WHERE delivery_id=' . $this->quote($deliveryId));
		return [
			'id' => (string)$row['id'],
			'queue_id' => (string)$row['queue_id'],
			'type_name' => (string)$row['type_name'],
			'transport_name' => (string)$row['transport_name'],
			'subject' => (string)$row['subject'],
			'status' => (string)$row['status'],
			'error_message' => (string)($row['error_message'] ?? ''),
			'message' => $this->decodeJson((string)$row['message_json']),
			'result' => $this->decodeJson((string)($row['result_json'] ?? '')),
			'recipients' => $recipients,
			'attachments' => $attachments,
			'created_at' => (string)$row['created_at'],
			'updated_at' => (string)$row['updated_at'],
			'sent_at' => (string)($row['sent_at'] ?? '')
		];
	}

	public function cleanup(int $retentionDays): int {
		$this->ensureStorage();
		if($retentionDays <= 0) {
			return 0;
		}
		$cutoff = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
		$ids = $this->database->listQuery('SELECT id FROM base3_messaging_deliveries WHERE created_at < ' . $this->quote($cutoff));
		$count = 0;
		foreach($ids as $id) {
			$id = (string)$id;
			$this->database->nonQuery('DELETE FROM base3_messaging_recipients WHERE delivery_id=' . $this->quote($id));
			$this->database->nonQuery('DELETE FROM base3_messaging_attachments WHERE delivery_id=' . $this->quote($id));
			$this->database->nonQuery('DELETE FROM base3_messaging_deliveries WHERE id=' . $this->quote($id) . ' LIMIT 1');
			$count++;
		}
		return $count;
	}

	private function buildWhere(array $request): string {
		$parts = [];
		$search = $this->readSearch($request);
		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
		if($search !== '') {
			$needle = $this->quote('%' . strtolower($search) . '%');
			$parts[] = '(LOWER(id) LIKE ' . $needle . ' OR LOWER(queue_id) LIKE ' . $needle . ' OR LOWER(type_name) LIKE ' . $needle . ' OR LOWER(subject) LIKE ' . $needle . ' OR LOWER(error_message) LIKE ' . $needle . ')';
		}
		foreach(['status', 'transport_name', 'type_name'] as $key) {
			$value = trim((string)($filters[$key] ?? ''));
			if($value !== '') {
				$parts[] = $key . '=' . $this->quote($value);
			}
		}
		return count($parts) > 0 ? ' WHERE ' . implode(' AND ', $parts) : '';
	}

	private function insertRecipient(string $deliveryId, MessageAddress $recipient): void {
		$this->database->nonQuery('INSERT INTO base3_messaging_recipients (id, delivery_id, kind, address, label) VALUES (' . $this->quote($this->idGenerator->createId('rcp')) . ', ' . $this->quote($deliveryId) . ', ' . $this->quote($recipient->getType()) . ', ' . $this->quote($recipient->getAddress()) . ', ' . $this->quote($recipient->getName()) . ')');
	}

	private function insertAttachment(string $deliveryId, MessageAttachment $attachment): void {
		$this->database->nonQuery('INSERT INTO base3_messaging_attachments (id, delivery_id, path, filename, mime_type, inline_flag, content_id) VALUES (' . $this->quote($this->idGenerator->createId('att')) . ', ' . $this->quote($deliveryId) . ', ' . $this->quote($attachment->getPath()) . ', ' . $this->quote($attachment->getName()) . ', ' . $this->quote($attachment->getMimeType()) . ', ' . ($attachment->isInline() ? 1 : 0) . ', ' . $this->quote($attachment->getContentId()) . ')');
	}

	private function adminRow(array $row): array {
		return [
			'id' => (string)$row['id'], 'queue_id' => (string)$row['queue_id'], 'type_name' => (string)$row['type_name'], 'transport_name' => (string)$row['transport_name'], 'subject' => (string)$row['subject'], 'status' => (string)$row['status'], 'attempts' => (int)$row['attempts'], 'error_message' => (string)($row['error_message'] ?? ''), 'created_at' => (string)$row['created_at'], 'updated_at' => (string)$row['updated_at'], 'sent_at' => (string)($row['sent_at'] ?? '')
		];
	}
}
