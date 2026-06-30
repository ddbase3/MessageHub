<?php declare(strict_types=1);

namespace MessageHub\Repository;

use Base3\Database\Api\IDatabase;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Dto\MessageTemplate;

final class DatabaseMessageTemplateRepository implements IMessageTemplateRepository {

	use DatabaseRepositoryTrait;

	public function __construct(
		private readonly IDatabase $database,
		private readonly DatabaseSchema $schema,
		private readonly IMessageIdGenerator $idGenerator
	) {}

	public function ensureStorage(): void {
		$this->schema->ensureTables();
	}

	public function save(MessageTemplate $template): void {
		$this->ensureStorage();
		$now = $this->now();
		$exists = $this->getById($template->getId()) !== null;

		if($exists) {
			$this->database->nonQuery(
				'UPDATE base3_messaging_templates SET ' .
				'type_name=' . $this->quote($template->getTypeName()) . ', ' .
				'label=' . $this->quote($template->getLabel()) . ', ' .
				'description=' . $this->quote($template->getDescription()) . ', ' .
				'scope_type=' . $this->quote($template->getScopeType()) . ', ' .
				'scope_id=' . $this->quote($template->getScopeId()) . ', ' .
				'default_transport=' . $this->quote($template->getDefaultTransport()) . ', ' .
				'enabled=' . $this->boolInt($template->isEnabled()) . ', ' .
				'updated_at=' . $this->quote($now) . ' ' .
				'WHERE id=' . $this->quote($template->getId()) . ' LIMIT 1'
			);
			return;
		}

		$this->database->nonQuery(
			'INSERT INTO base3_messaging_templates (id, type_name, label, description, scope_type, scope_id, default_transport, enabled, created_at, updated_at) VALUES (' .
			$this->quote($template->getId()) . ', ' .
			$this->quote($template->getTypeName()) . ', ' .
			$this->quote($template->getLabel()) . ', ' .
			$this->quote($template->getDescription()) . ', ' .
			$this->quote($template->getScopeType()) . ', ' .
			$this->quote($template->getScopeId()) . ', ' .
			$this->quote($template->getDefaultTransport()) . ', ' .
			$this->boolInt($template->isEnabled()) . ', ' .
			$this->quote($now) . ', ' .
			$this->quote($now) . ')'
		);
	}

	public function getById(string $id): ?MessageTemplate {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT * FROM base3_messaging_templates WHERE id=' . $this->quote($id) . ' LIMIT 1');
		return is_array($row) ? $this->fromRow($row) : null;
	}

	public function getByType(string $typeName, string $scopeType = 'global', string $scopeId = ''): ?MessageTemplate {
		$this->ensureStorage();
		$row = $this->database->singleQuery(
			'SELECT * FROM base3_messaging_templates WHERE type_name=' . $this->quote($typeName) .
			' AND scope_type=' . $this->quote($scopeType) .
			' AND scope_id=' . $this->quote($scopeId) . ' LIMIT 1'
		);
		return is_array($row) ? $this->fromRow($row) : null;
	}

	public function delete(string $id): void {
		$this->ensureStorage();
		$this->database->nonQuery('DELETE FROM base3_messaging_variants WHERE template_id=' . $this->quote($id));
		$this->database->nonQuery('DELETE FROM base3_messaging_templates WHERE id=' . $this->quote($id) . ' LIMIT 1');
	}

	public function listAll(): array {
		$this->ensureStorage();
		$rows = $this->database->multiQuery('SELECT * FROM base3_messaging_templates ORDER BY type_name ASC');
		return array_map(fn(array $row) => $this->fromRow($row), $rows);
	}

	public function page(array $request): array {
		$this->ensureStorage();
		[$page, $pageSize, $offset] = $this->normalizePage($request);
		$search = $this->readSearch($request);
		$where = '';
		if($search !== '') {
			$needle = $this->quote('%' . strtolower($search) . '%');
			$where = ' WHERE LOWER(type_name) LIKE ' . $needle . ' OR LOWER(label) LIKE ' . $needle . ' OR LOWER(description) LIKE ' . $needle;
		}
		$total = (int)($this->database->scalarQuery('SELECT COUNT(*) FROM base3_messaging_templates' . $where) ?? 0);
		$rows = $this->database->multiQuery('SELECT * FROM base3_messaging_templates' . $where . ' ORDER BY type_name ASC LIMIT ' . $offset . ', ' . $pageSize);
		return ['rows' => array_map(fn(array $row) => $this->rowForAdmin($row), $rows), 'total' => $total];
	}

	private function fromRow(array $row): MessageTemplate {
		return new MessageTemplate(
			(string)$row['id'],
			(string)$row['type_name'],
			(string)$row['label'],
			(string)($row['description'] ?? ''),
			(string)($row['scope_type'] ?? 'global'),
			(string)($row['scope_id'] ?? ''),
			(string)($row['default_transport'] ?? ''),
			((int)($row['enabled'] ?? 0)) === 1
		);
	}

	private function rowForAdmin(array $row): array {
		return [
			'id' => (string)$row['id'],
			'type_name' => (string)$row['type_name'],
			'label' => (string)$row['label'],
			'description' => (string)($row['description'] ?? ''),
			'scope_type' => (string)($row['scope_type'] ?? 'global'),
			'scope_id' => (string)($row['scope_id'] ?? ''),
			'default_transport' => (string)($row['default_transport'] ?? ''),
			'enabled' => (int)($row['enabled'] ?? 0),
			'enabled_label' => ((int)($row['enabled'] ?? 0)) === 1 ? 'Enabled' : 'Disabled',
			'created_at' => (string)($row['created_at'] ?? ''),
			'updated_at' => (string)($row['updated_at'] ?? '')
		];
	}
}
