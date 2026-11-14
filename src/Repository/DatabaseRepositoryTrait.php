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

namespace Base3\MessageHub\Repository;

trait DatabaseRepositoryTrait {

	private function now(): string {
		return date('Y-m-d H:i:s');
	}

	private function quote(?string $value): string {
		if($value === null) {
			return 'NULL';
		}

		return "'" . $this->database->escape($value) . "'";
	}

	private function boolInt(bool $value): int {
		return $value ? 1 : 0;
	}

	private function decodeJson(string $json): array {
		$data = json_decode($json, true);
		return is_array($data) ? $data : [];
	}

	private function encodeJson(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '{}';
	}

	private function normalizePage(array $request): array {
		$page = max(1, (int)($request['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($request['pageSize'] ?? 50)));
		$offset = ($page - 1) * $pageSize;
		return [$page, $pageSize, $offset];
	}

	private function readSearch(array $request): string {
		return trim((string)($request['search'] ?? ''));
	}
}
