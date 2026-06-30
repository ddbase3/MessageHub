<?php declare(strict_types=1);

namespace MessageHub\Repository;

use Base3\Database\Api\IDatabase;
use MessageHub\Service\MessageSerializer;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\QueuedMessage;

final class DatabaseMessageQueueRepository implements IMessageQueueRepository {

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

	public function insert(Message $message, string $transportName, int $priority, ?int $notBefore): string {
		$this->ensureStorage();
		$id = $this->idGenerator->createId('que');
		$now = $this->now();
		$readyAt = $notBefore !== null ? date('Y-m-d H:i:s', $notBefore) : $now;
		$this->database->nonQuery('INSERT INTO base3_messaging_queue (id, type_name, transport_name, subject, status, priority, attempts, max_attempts, not_before, message_json, created_at, updated_at) VALUES (' . $this->quote($id) . ', ' . $this->quote($message->getTypeName()) . ', ' . $this->quote($transportName) . ', ' . $this->quote($message->getSubject()) . ', ' . $this->quote('queued') . ', ' . (int)$priority . ', 0, 3, ' . $this->quote($readyAt) . ', ' . $this->quote($this->serializer->encode($message)) . ', ' . $this->quote($now) . ', ' . $this->quote($now) . ')');
		return $id;
	}

	public function claimNext(int $limit, int $lockSeconds): array {
		$this->ensureStorage();
		$limit = max(1, min(100, $limit));
		$now = $this->now();
		$lockedUntil = date('Y-m-d H:i:s', time() + max(60, $lockSeconds));
		$rows = $this->database->multiQuery('SELECT * FROM base3_messaging_queue WHERE (status=\'queued\' OR status=\'retry_wait\') AND not_before <= ' . $this->quote($now) . ' ORDER BY priority ASC, created_at ASC LIMIT 0, ' . $limit);
		$result = [];
		foreach($rows as $row) {
			$id = (string)$row['id'];
			$this->database->nonQuery('UPDATE base3_messaging_queue SET status=\'processing\', locked_until=' . $this->quote($lockedUntil) . ', updated_at=' . $this->quote($now) . ' WHERE id=' . $this->quote($id) . ' LIMIT 1');
			$result[] = $this->fromRow($row, 'processing');
		}
		return $result;
	}

	public function markSent(string $queueId): void {
		$this->ensureStorage();
		$now = $this->now();
		$this->database->nonQuery('UPDATE base3_messaging_queue SET status=\'sent\', processed_at=' . $this->quote($now) . ', updated_at=' . $this->quote($now) . ' WHERE id=' . $this->quote($queueId) . ' LIMIT 1');
	}

	public function markFailed(string $queueId, string $errorMessage, int $retryDelaySeconds): void {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT attempts, max_attempts FROM base3_messaging_queue WHERE id=' . $this->quote($queueId) . ' LIMIT 1');
		$attempts = (int)($row['attempts'] ?? 0) + 1;
		$maxAttempts = (int)($row['max_attempts'] ?? 3);
		$status = $attempts >= $maxAttempts ? 'failed' : 'retry_wait';
		$notBefore = date('Y-m-d H:i:s', time() + max(0, $retryDelaySeconds));
		$now = $this->now();
		$this->database->nonQuery('UPDATE base3_messaging_queue SET status=' . $this->quote($status) . ', attempts=' . $attempts . ', last_error=' . $this->quote($errorMessage) . ', not_before=' . $this->quote($notBefore) . ', locked_until=NULL, updated_at=' . $this->quote($now) . ' WHERE id=' . $this->quote($queueId) . ' LIMIT 1');
	}

	public function cancel(string $queueId): void {
		$this->ensureStorage();
		$this->database->nonQuery('UPDATE base3_messaging_queue SET status=\'cancelled\', updated_at=' . $this->quote($this->now()) . ' WHERE id=' . $this->quote($queueId) . ' LIMIT 1');
	}

	public function page(array $request): array {
		$this->ensureStorage();
		[$page, $pageSize, $offset] = $this->normalizePage($request);
		$where = $this->buildWhere($request);
		$total = (int)($this->database->scalarQuery('SELECT COUNT(*) FROM base3_messaging_queue' . $where) ?? 0);
		$rows = $this->database->multiQuery('SELECT id, type_name, transport_name, subject, status, priority, attempts, max_attempts, not_before, last_error, created_at, updated_at, processed_at FROM base3_messaging_queue' . $where . ' ORDER BY created_at DESC LIMIT ' . $offset . ', ' . $pageSize);
		return ['rows' => array_map(fn(array $row) => $this->adminRow($row), $rows), 'total' => $total];
	}

	private function buildWhere(array $request): string {
		$parts = [];
		$search = $this->readSearch($request);
		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
		if($search !== '') {
			$needle = $this->quote('%' . strtolower($search) . '%');
			$parts[] = '(LOWER(id) LIKE ' . $needle . ' OR LOWER(type_name) LIKE ' . $needle . ' OR LOWER(subject) LIKE ' . $needle . ' OR LOWER(last_error) LIKE ' . $needle . ')';
		}
		foreach(['status', 'transport_name', 'type_name'] as $key) {
			$value = trim((string)($filters[$key] ?? ''));
			if($value !== '') {
				$parts[] = $key . '=' . $this->quote($value);
			}
		}
		return count($parts) > 0 ? ' WHERE ' . implode(' AND ', $parts) : '';
	}

	private function fromRow(array $row, string $status = ''): QueuedMessage {
		$message = $this->serializer->decode((string)$row['message_json']);
		return new QueuedMessage((string)$row['id'], $message, (string)$row['transport_name'], $status !== '' ? $status : (string)$row['status'], (int)$row['attempts'], (int)$row['max_attempts']);
	}

	private function adminRow(array $row): array {
		return [
			'id' => (string)$row['id'],
			'type_name' => (string)$row['type_name'],
			'transport_name' => (string)$row['transport_name'],
			'subject' => (string)$row['subject'],
			'status' => (string)$row['status'],
			'priority' => (int)$row['priority'],
			'attempts' => (int)$row['attempts'],
			'max_attempts' => (int)$row['max_attempts'],
			'not_before' => (string)$row['not_before'],
			'last_error' => (string)($row['last_error'] ?? ''),
			'created_at' => (string)$row['created_at'],
			'updated_at' => (string)$row['updated_at'],
			'processed_at' => (string)($row['processed_at'] ?? '')
		];
	}
}
