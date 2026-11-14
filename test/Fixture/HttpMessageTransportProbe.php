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

use Base3\MessageHub\Transport\Support\AbstractHttpMessageTransport;
use Base3\MessageHub\Transport\Support\HttpResponse;

final class HttpMessageTransportProbe extends AbstractHttpMessageTransport {

	public function error(HttpResponse $response, string $fallback = 'Fallback'): string {
		return $this->getHttpError($response, $fallback);
	}

	public function encodePayload(array $payload): void {
		$this->requestJson('POST', 'https://example.test', $payload);
	}

	public function requestFromFixture(string $url): HttpResponse {
		return $this->request('GET', $url, [], '', 3);
	}
}
