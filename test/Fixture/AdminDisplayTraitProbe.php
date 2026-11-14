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

namespace Base3\MessageHub\Test\Fixture;

use Base3\MessageHub\Display\AdminDisplayTrait;

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
