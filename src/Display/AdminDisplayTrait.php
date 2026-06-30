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
		$filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];

		return [
			'page' => $page,
			'pageSize' => $pageSize,
			'search' => $search,
			'filters' => $filters
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
			'appliedSort' => [],
			'appliedFilters' => $request['filters'] ?? [],
			'appliedGroup' => []
		];
	}
}
