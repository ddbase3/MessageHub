<?php declare(strict_types=1);

namespace MessageHub\Display;

trait AdminDisplayTrait {

	private function json(array $response, bool $final): string {
		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	}

	private function normalizeListRequest(array $payload): array {
		$page = max(1, (int)($payload['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($payload['pageSize'] ?? 50)));
		$search = isset($payload['search']) && is_scalar($payload['search']) ? trim((string)$payload['search']) : '';
		$filters = is_array($payload['filters'] ?? null) ? $this->normalizeListFilters($payload['filters']) : [];

		return [
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'filters' => $filters,
			'sort' => $this->normalizeListSort($payload['sort'] ?? null)
		];
	}

	private function pageResponse(array $page, array $request): array {
		$total = (int)($page['total'] ?? 0);
		$pageNum = (int)$request['page'];
		$pageSize = (int)$request['pageSize'];
		$offset = max(0, ($pageNum - 1) * $pageSize);

		return [
			'ok' => true,
			'mode' => 'page',
			'data' => array_values($page['rows'] ?? []),
			'groups' => [],
			'page' => $pageNum,
			'pageSize' => $pageSize,
			'total' => $total,
			'totalPages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $request['search'] ?? '',
			'appliedSort' => $request['sort'] ?? [],
			'appliedFilters' => $request['filters'] ?? [],
			'appliedGroup' => []
		];
	}


	private function listPageFromRows(array $rows, array $request): array {
		$rows = $this->filterListRows($rows, $request);
		$this->sortListRows($rows, $request);

		$total = count($rows);
		$page = max(1, (int)($request['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($request['pageSize'] ?? 50)));
		$offset = max(0, ($page - 1) * $pageSize);

		return [
			'rows' => array_slice($rows, $offset, $pageSize),
			'total' => $total
		];
	}

	private function repositoryListPage(callable $loader, array $request): array {
		$rows = $this->loadAllRepositoryRows($loader, $request);
		$rows = $this->filterListRows($rows, $request);
		$this->sortListRows($rows, $request);

		$total = count($rows);
		$page = max(1, (int)($request['page'] ?? 1));
		$pageSize = max(1, min(250, (int)($request['pageSize'] ?? 50)));
		$offset = max(0, ($page - 1) * $pageSize);

		return [
			'rows' => array_slice($rows, $offset, $pageSize),
			'total' => $total
		];
	}

	private function loadAllRepositoryRows(callable $loader, array $request): array {
		$rows = [];
		$page = 1;
		$pageSize = 250;
		$total = null;

		do {
			$repositoryRequest = $request;
			$repositoryRequest['page'] = $page;
			$repositoryRequest['pageSize'] = $pageSize;
			$repositoryRequest['search'] = '';
			$repositoryRequest['filters'] = [];
			$repositoryRequest['sort'] = [];

			$result = $loader($repositoryRequest);
			$currentRows = is_array($result['rows'] ?? null) ? $result['rows'] : [];

			foreach($currentRows as $row) {
				if(is_array($row)) {
					$rows[] = $row;
				}
			}

			if($total === null && isset($result['total'])) {
				$total = max(0, (int)$result['total']);
			}

			$page++;
			$hasMoreByTotal = $total !== null && count($rows) < $total;
			$hasMoreByBatch = $total === null && count($currentRows) === $pageSize;
		}
		while(($hasMoreByTotal || $hasMoreByBatch) && $page <= 100);

		return $rows;
	}

	private function filterListRows(array $rows, array $request): array {
		$search = isset($request['search']) && is_scalar($request['search']) ? trim((string)$request['search']) : '';
		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];
		$result = [];

		foreach($rows as $row) {
			if(!is_array($row)) {
				continue;
			}

			if($search !== '' && !$this->listRowMatchesSearch($row, $search)) {
				continue;
			}

			if(!$this->listRowMatchesFilters($row, $filters)) {
				continue;
			}

			$result[] = $row;
		}

		return $result;
	}

	private function listRowMatchesSearch(array $row, string $search): bool {
		return strpos($this->lowerListValue($this->flattenListValue($row)), $this->lowerListValue($search)) !== false;
	}

	private function listRowMatchesFilters(array $row, array $filters): bool {
		foreach($filters as $key => $filterValue) {
			if(!is_string($key) || $filterValue === null || $filterValue === '') {
				continue;
			}

			$needle = trim((string)$filterValue);
			if($needle === '') {
				continue;
			}

			if($this->isExactListFilter($key)) {
				if($this->normalizeComparableListValue($this->readListFilterValue($row, $key)) !== $this->normalizeComparableListValue($needle)) {
					return false;
				}

				continue;
			}

			$haystack = $this->lowerListValue($this->readListFilterValue($row, $key));
			if(strpos($haystack, $this->lowerListValue($needle)) === false) {
				return false;
			}
		}

		return true;
	}

	private function isExactListFilter(string $key): bool {
		return in_array($key, ['status', 'enabled', 'installed', 'is_default', 'template_id'], true);
	}

	private function readListFilterValue(array $row, string $key): string {
		if(array_key_exists($key, $row)) {
			return $this->stringifyListValue($row[$key]);
		}

		if($key === 'enabled' && array_key_exists('enabled_label', $row)) {
			return $this->stringifyListValue($row['enabled_label']);
		}

		if($key === 'is_default' && array_key_exists('default_label', $row)) {
			return $this->stringifyListValue($row['default_label']);
		}

		return '';
	}

	private function sortListRows(array &$rows, array $request): void {
		$sort = is_array($request['sort'] ?? null) ? $request['sort'] : [];
		$firstSort = reset($sort);

		if(!is_array($firstSort)) {
			return;
		}

		$key = isset($firstSort['key']) && is_scalar($firstSort['key']) ? (string)$firstSort['key'] : '';
		if($key === '') {
			return;
		}

		$direction = isset($firstSort['dir']) && strtolower((string)$firstSort['dir']) === 'desc' ? 'desc' : 'asc';

		usort(
			$rows,
			function(array $a, array $b) use($key, $direction): int {
				$aValue = $this->readSortListValue($a, $key);
				$bValue = $this->readSortListValue($b, $key);
				$result = $this->compareListValues($aValue, $bValue);

				if($result === 0) {
					$result = $this->compareListValues($this->readSortListValue($a, 'id'), $this->readSortListValue($b, 'id'));
				}

				return $direction === 'desc' ? -$result : $result;
			}
		);
	}

	private function readSortListValue(array $row, string $key): string {
		return array_key_exists($key, $row) ? $this->stringifyListValue($row[$key]) : '';
	}

	private function compareListValues(string $a, string $b): int {
		if(is_numeric($a) && is_numeric($b)) {
			return (float)$a <=> (float)$b;
		}

		return strnatcasecmp($a, $b);
	}

	private function normalizeListFilters(array $filters): array {
		$result = [];

		foreach($filters as $key => $value) {
			if(!is_string($key) || !is_scalar($value)) {
				continue;
			}

			$value = trim((string)$value);
			if($value !== '') {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	private function normalizeListSort(mixed $sortPayload): array {
		if(!is_array($sortPayload) || count($sortPayload) === 0) {
			return [];
		}

		$first = reset($sortPayload);
		if(!is_array($first)) {
			return [];
		}

		$key = isset($first['key']) && is_scalar($first['key']) ? trim((string)$first['key']) : '';
		if($key === '' || $key === '__sync') {
			return [];
		}

		$dir = isset($first['dir']) && strtolower((string)$first['dir']) === 'desc' ? 'desc' : 'asc';
		$type = isset($first['type']) && is_scalar($first['type']) ? trim((string)$first['type']) : 'string';

		return [
			[
				'key' => $key,
				'dir' => $dir,
				'type' => $type !== '' ? $type : 'string'
			]
		];
	}

	private function normalizeComparableListValue(string $value): string {
		$value = strtolower(trim($value));

		if(in_array($value, ['1', 'true', 'yes', 'on', 'enabled', 'installed', 'default'], true)) {
			return '1';
		}

		if(in_array($value, ['0', 'false', 'no', 'off', 'disabled', 'not installed', 'not default'], true)) {
			return '0';
		}

		return $value;
	}

	private function flattenListValue(mixed $value): string {
		if(is_array($value)) {
			$parts = [];

			foreach($value as $item) {
				$parts[] = $this->flattenListValue($item);
			}

			return implode(' ', $parts);
		}

		return $this->stringifyListValue($value);
	}

	private function stringifyListValue(mixed $value): string {
		if($value === null) {
			return '';
		}

		if(is_bool($value)) {
			return $value ? '1' : '0';
		}

		if(is_scalar($value)) {
			return (string)$value;
		}

		if(is_array($value)) {
			return $this->flattenListValue($value);
		}

		return '';
	}

	private function lowerListValue(string $value): string {
		if(function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}
}
