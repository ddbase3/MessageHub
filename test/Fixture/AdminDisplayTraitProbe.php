<?php declare(strict_types=1);

namespace MessageHub\Test\Fixture;

use MessageHub\Display\AdminDisplayTrait;

final class AdminDisplayTraitProbe {

	use AdminDisplayTrait;

	public function pageRows(array $rows, array $payload): array {
		$request = $this->normalizeListRequest($payload);
		return $this->pageResponse($this->listPageFromRows($rows, $request), $request);
	}

	public function pageRepository(callable $loader, array $payload): array {
		$request = $this->normalizeListRequest($payload);
		return $this->pageResponse($this->repositoryListPage($loader, $request), $request);
	}

	public function encodeResponse(array $response, bool $final = false): string {
		return $this->json($response, $final);
	}
}
