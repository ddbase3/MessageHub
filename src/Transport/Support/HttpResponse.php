<?php declare(strict_types=1);

namespace MessageHub\Transport\Support;

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
