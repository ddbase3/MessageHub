<?php declare(strict_types=1);

namespace MessageHub\Repository;

use Base3\Database\Api\IDatabase;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageVariantRepository;
use MessagingFoundation\Dto\MessageVariant;

final class DatabaseMessageVariantRepository implements IMessageVariantRepository {

	use DatabaseRepositoryTrait;

	public function __construct(
		private readonly IDatabase $database,
		private readonly DatabaseSchema $schema,
		private readonly IMessageIdGenerator $idGenerator
	) {}

	public function ensureStorage(): void {
		$this->schema->ensureTables();
	}

	public function save(MessageVariant $variant): void {
		$this->ensureStorage();
		$now = $this->now();
		$exists = $this->getById($variant->getId()) !== null;

		if($variant->isFallback()) {
			$this->database->nonQuery(
				'UPDATE base3_messaging_variants SET fallback_flag=0 WHERE template_id=' . $this->quote($variant->getTemplateId()) . ' AND id<>' . $this->quote($variant->getId())
			);
		}

		if($exists) {
			$this->database->nonQuery(
				'UPDATE base3_messaging_variants SET template_id=' . $this->quote($variant->getTemplateId()) . ', language=' . $this->quote($variant->getLanguage()) . ', subject=' . $this->quote($variant->getSubject()) . ', body_text=' . $this->quote($variant->getBodyText()) . ', body_html=' . $this->quote($variant->getBodyHtml()) . ', fallback_flag=' . $this->boolInt($variant->isFallback()) . ', enabled=' . $this->boolInt($variant->isEnabled()) . ', updated_at=' . $this->quote($now) . ' WHERE id=' . $this->quote($variant->getId()) . ' LIMIT 1'
			);
			return;
		}

		$this->database->nonQuery(
			'INSERT INTO base3_messaging_variants (id, template_id, language, subject, body_text, body_html, fallback_flag, enabled, created_at, updated_at) VALUES (' . $this->quote($variant->getId()) . ', ' . $this->quote($variant->getTemplateId()) . ', ' . $this->quote($variant->getLanguage()) . ', ' . $this->quote($variant->getSubject()) . ', ' . $this->quote($variant->getBodyText()) . ', ' . $this->quote($variant->getBodyHtml()) . ', ' . $this->boolInt($variant->isFallback()) . ', ' . $this->boolInt($variant->isEnabled()) . ', ' . $this->quote($now) . ', ' . $this->quote($now) . ')'
		);
	}

	public function getById(string $id): ?MessageVariant {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT * FROM base3_messaging_variants WHERE id=' . $this->quote($id) . ' LIMIT 1');
		return is_array($row) ? $this->fromRow($row) : null;
	}

	public function getForTemplate(string $templateId, string $language): ?MessageVariant {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT * FROM base3_messaging_variants WHERE template_id=' . $this->quote($templateId) . ' AND language=' . $this->quote($language) . ' AND enabled=1 LIMIT 1');
		return is_array($row) ? $this->fromRow($row) : null;
	}

	public function getFallbackForTemplate(string $templateId): ?MessageVariant {
		$this->ensureStorage();
		$row = $this->database->singleQuery('SELECT * FROM base3_messaging_variants WHERE template_id=' . $this->quote($templateId) . ' AND fallback_flag=1 AND enabled=1 ORDER BY updated_at DESC LIMIT 1');
		return is_array($row) ? $this->fromRow($row) : null;
	}

	public function delete(string $id): void {
		$this->ensureStorage();
		$this->database->nonQuery('DELETE FROM base3_messaging_variants WHERE id=' . $this->quote($id) . ' LIMIT 1');
	}

	public function listByTemplate(string $templateId): array {
		$this->ensureStorage();
		$rows = $this->database->multiQuery('SELECT * FROM base3_messaging_variants WHERE template_id=' . $this->quote($templateId) . ' ORDER BY language ASC');
		return array_map(fn(array $row) => $this->fromRow($row), $rows);
	}

	public function page(array $request): array {
		$this->ensureStorage();
		[$page, $pageSize, $offset] = $this->normalizePage($request);
		$search = $this->readSearch($request);
		$whereParts = [];
		if($search !== '') {
			$needle = $this->quote('%' . strtolower($search) . '%');
			$whereParts[] = '(LOWER(v.language) LIKE ' . $needle . ' OR LOWER(v.subject) LIKE ' . $needle . ' OR LOWER(t.type_name) LIKE ' . $needle . ' OR LOWER(t.label) LIKE ' . $needle . ')';
		}
		$templateId = trim((string)($request['template_id'] ?? ''));
		if($templateId !== '') {
			$whereParts[] = 'v.template_id=' . $this->quote($templateId);
		}
		$where = count($whereParts) > 0 ? ' WHERE ' . implode(' AND ', $whereParts) : '';
		$from = ' FROM base3_messaging_variants v LEFT JOIN base3_messaging_templates t ON t.id=v.template_id';
		$total = (int)($this->database->scalarQuery('SELECT COUNT(*)' . $from . $where) ?? 0);
		$rows = $this->database->multiQuery('SELECT v.*, t.type_name, t.label AS template_label' . $from . $where . ' ORDER BY t.type_name ASC, v.language ASC LIMIT ' . $offset . ', ' . $pageSize);
		return ['rows' => array_map(fn(array $row) => $this->rowForAdmin($row), $rows), 'total' => $total];
	}

	private function fromRow(array $row): MessageVariant {
		return new MessageVariant(
			(string)$row['id'],
			(string)$row['template_id'],
			(string)$row['language'],
			(string)$row['subject'],
			(string)$row['body_text'],
			(string)($row['body_html'] ?? ''),
			((int)($row['enabled'] ?? 0)) === 1,
			((int)($row['fallback_flag'] ?? 0)) === 1
		);
	}

	private function rowForAdmin(array $row): array {
		$bodyText = (string)($row['body_text'] ?? '');
		$bodyHtml = (string)($row['body_html'] ?? '');

		return [
			'id' => (string)$row['id'],
			'template_id' => (string)$row['template_id'],
			'type_name' => (string)($row['type_name'] ?? ''),
			'template_label' => (string)($row['template_label'] ?? ''),
			'language' => (string)$row['language'],
			'subject' => (string)$row['subject'],
			'body_text' => $bodyText,
			'body_html' => $bodyHtml,
			'body_text_preview' => $this->createBodyPreview($bodyText, $bodyHtml),
			'fallback' => (int)($row['fallback_flag'] ?? 0),
			'fallback_label' => ((int)($row['fallback_flag'] ?? 0)) === 1 ? 'Fallback' : '',
			'enabled' => (int)($row['enabled'] ?? 0),
			'enabled_label' => ((int)($row['enabled'] ?? 0)) === 1 ? 'Enabled' : 'Disabled',
			'created_at' => (string)($row['created_at'] ?? ''),
			'updated_at' => (string)($row['updated_at'] ?? '')
		];
	}

	private function createBodyPreview(string $bodyText, string $bodyHtml): string {
		$htmlPreview = trim($bodyHtml) !== '' ? $this->htmlToPreviewText($bodyHtml) : '';
		$preview = trim($htmlPreview) !== '' ? $htmlPreview : $bodyText;
		$preview = preg_replace('/\s+/u', ' ', trim($preview)) ?? trim($preview);

		return function_exists('mb_substr') ? mb_substr($preview, 0, 240) : substr($preview, 0, 240);
	}

	private function htmlToPreviewText(string $html): string {
		$html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
		$html = preg_replace('#<(br|hr)\b[^>]*>|</(p|div|li|tr|h[1-6])>#i', ' ', $html) ?? $html;

		return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}
