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

namespace Base3\MessageHub\Transport\Support;

final class HttpResponse {

	/**
	 * @param array<string,string> $headers
	 */
	public function __construct(
		private readonly int $statusCode,
		private readonly string $body,
		private readonly array $headers = []
	) {}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function getBody(): string {
		return $this->body;
	}

	/**
	 * @return array<string,string>
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	public function isSuccessful(): bool {
		return $this->statusCode >= 200 && $this->statusCode < 300;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function getJson(): ?array {
		$data = json_decode($this->body, true);

		return is_array($data) ? $data : null;
	}
}
