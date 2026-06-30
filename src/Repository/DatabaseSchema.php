<?php declare(strict_types=1);

namespace MessageHub\Repository;

use Base3\Database\Api\IDatabase;

final class DatabaseSchema {

	private bool $done = false;

	public function __construct(
		private readonly IDatabase $database
	) {}

	public function ensureTables(): void {
		if($this->done) {
			return;
		}

		$this->database->connect();

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_templates (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				type_name VARCHAR(160) NOT NULL,
				label VARCHAR(255) NOT NULL,
				description TEXT NULL,
				scope_type VARCHAR(40) NOT NULL DEFAULT 'global',
				scope_id VARCHAR(160) NOT NULL DEFAULT '',
				default_transport VARCHAR(80) NOT NULL DEFAULT '',
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				UNIQUE KEY uq_base3_msg_tpl_type_scope (type_name, scope_type, scope_id),
				KEY idx_base3_msg_tpl_enabled (enabled, type_name)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_variants (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				template_id VARCHAR(80) NOT NULL,
				language VARCHAR(12) NOT NULL DEFAULT 'en',
				subject VARCHAR(255) NOT NULL,
				body_text LONGTEXT NOT NULL,
				body_html LONGTEXT NULL,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				UNIQUE KEY uq_base3_msg_var_tpl_lang (template_id, language),
				KEY idx_base3_msg_var_enabled (enabled, language)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_queue (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				type_name VARCHAR(160) NOT NULL,
				transport_name VARCHAR(80) NOT NULL DEFAULT '',
				subject VARCHAR(255) NOT NULL,
				status VARCHAR(30) NOT NULL DEFAULT 'queued',
				priority INT NOT NULL DEFAULT 100,
				attempts INT NOT NULL DEFAULT 0,
				max_attempts INT NOT NULL DEFAULT 3,
				not_before DATETIME NOT NULL,
				locked_until DATETIME NULL,
				message_json LONGTEXT NOT NULL,
				last_error TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				processed_at DATETIME NULL,
				KEY idx_base3_msg_queue_ready (status, not_before, priority, created_at),
				KEY idx_base3_msg_queue_type (type_name),
				KEY idx_base3_msg_queue_transport (transport_name)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_deliveries (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				queue_id VARCHAR(80) NOT NULL DEFAULT '',
				type_name VARCHAR(160) NOT NULL,
				transport_name VARCHAR(80) NOT NULL DEFAULT '',
				subject VARCHAR(255) NOT NULL,
				status VARCHAR(30) NOT NULL DEFAULT 'processing',
				attempts INT NOT NULL DEFAULT 0,
				message_json LONGTEXT NOT NULL,
				error_message TEXT NULL,
				result_json LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				sent_at DATETIME NULL,
				KEY idx_base3_msg_del_status (status, created_at),
				KEY idx_base3_msg_del_queue (queue_id),
				KEY idx_base3_msg_del_type (type_name)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_recipients (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				delivery_id VARCHAR(80) NOT NULL,
				kind VARCHAR(10) NOT NULL DEFAULT 'to',
				address VARCHAR(255) NOT NULL,
				label VARCHAR(255) NOT NULL DEFAULT '',
				KEY idx_base3_msg_rcp_delivery (delivery_id),
				KEY idx_base3_msg_rcp_address (address)
			)
		");

		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS base3_messaging_attachments (
				id VARCHAR(80) NOT NULL PRIMARY KEY,
				delivery_id VARCHAR(80) NOT NULL,
				path TEXT NOT NULL,
				filename VARCHAR(255) NOT NULL DEFAULT '',
				mime_type VARCHAR(160) NOT NULL DEFAULT '',
				inline_flag TINYINT(1) NOT NULL DEFAULT 0,
				content_id VARCHAR(160) NOT NULL DEFAULT '',
				KEY idx_base3_msg_att_delivery (delivery_id)
			)
		");

		$this->done = true;
	}
}
