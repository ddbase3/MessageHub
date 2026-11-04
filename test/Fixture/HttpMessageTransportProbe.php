<?php declare(strict_types=1);

namespace MessageHub\Test\Fixture;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessageHub\Transport\Support\HttpResponse;

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
