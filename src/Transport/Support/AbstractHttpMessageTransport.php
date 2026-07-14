<?php declare(strict_types=1);

namespace MessageHub\Transport\Support;

use RuntimeException;

abstract class AbstractHttpMessageTransport extends AbstractMessageTransport {

	/**
	 * @param array<string,string> $headers
	 */
	protected function request(
		string $method,
		string $url,
		array $headers = [],
		string $body = '',
		int $timeout = 20,
		bool $verifyTls = true
	): HttpResponse {
		if(!function_exists('curl_init')) {
			throw new RuntimeException('The cURL extension is required for this message transport.');
		}

		$url = trim($url);
		if($url === '' || !preg_match('~^https?://~i', $url)) {
			throw new RuntimeException('A valid HTTP or HTTPS endpoint URL is required.');
		}

		$method = strtoupper(trim($method));
		if(!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
			throw new RuntimeException('Unsupported HTTP method: ' . $method);
		}

		$responseHeaders = [];
		$responseBody = '';
		$responseTruncated = false;
		$maximumResponseBytes = 1024 * 1024;
		$curl = curl_init($url);

		if($curl === false) {
			throw new RuntimeException('Unable to initialize cURL.');
		}

		$headerLines = [];
		foreach($headers as $name => $value) {
			$name = trim((string)$name);
			$value = trim((string)$value);
			if($name !== '' && $value !== '') {
				$headerLines[] = $name . ': ' . $value;
			}
		}

		$headerLines[] = 'User-Agent: BASE3-MessageHub/1.0';
		$headerLines[] = 'Accept: application/json, text/plain, */*';

		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headerLines,
			CURLOPT_CONNECTTIMEOUT => max(1, min($timeout, 60)),
			CURLOPT_TIMEOUT => max(1, min($timeout, 120)),
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS => 0,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_SSL_VERIFYPEER => $verifyTls,
			CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
			CURLOPT_HEADERFUNCTION => function($curl, string $line) use (&$responseHeaders): int {
				$length = strlen($line);
				$separator = strpos($line, ':');
				if($separator === false) {
					return $length;
				}

				$name = strtolower(trim(substr($line, 0, $separator)));
				$value = trim(substr($line, $separator + 1));
				if($name !== '') {
					$responseHeaders[$name] = $value;
				}

				return $length;
			},
			CURLOPT_WRITEFUNCTION => function($curl, string $chunk) use (&$responseBody, &$responseTruncated, $maximumResponseBytes): int {
				$remaining = $maximumResponseBytes - strlen($responseBody);
				$length = strlen($chunk);

				if($remaining > 0) {
					$responseBody .= $length > $remaining ? substr($chunk, 0, $remaining) : $chunk;
				}

				if($length > $remaining) {
					$responseTruncated = true;
				}

				return $length;
			}
		]);

		if($method !== 'GET' && $body !== '') {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		}

		$result = curl_exec($curl);
		$error = curl_error($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);

		if($result === false) {
			throw new RuntimeException($error !== '' ? $error : 'The HTTP request failed.');
		}

		if($responseTruncated) {
			$responseHeaders['x-messagehub-response-truncated'] = '1';
		}

		return new HttpResponse($statusCode, $responseBody, $responseHeaders);
	}

	/**
	 * @param array<string,string> $headers
	 * @param array<string,mixed> $payload
	 */
	protected function requestJson(
		string $method,
		string $url,
		array $payload,
		array $headers = [],
		int $timeout = 20,
		bool $verifyTls = true
	): HttpResponse {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$headers['Content-Type'] = 'application/json; charset=UTF-8';

		return $this->request($method, $url, $headers, $json, $timeout, $verifyTls);
	}

	protected function getHttpError(HttpResponse $response, string $fallback): string {
		$json = $response->getJson();
		if(is_array($json)) {
			foreach(['message', 'error_description', 'description', 'detail'] as $key) {
				$value = $json[$key] ?? null;
				if(is_scalar($value) && trim((string)$value) !== '') {
					return trim((string)$value);
				}
			}

			$error = $json['error'] ?? null;
			if(is_array($error) && is_scalar($error['message'] ?? null)) {
				return trim((string)$error['message']);
			}

			if(is_scalar($error) && trim((string)$error) !== '') {
				return trim((string)$error);
			}
		}

		$body = trim($response->getBody());
		if($body !== '') {
			return $this->truncate($body, 500);
		}

		return $fallback . ' (HTTP ' . $response->getStatusCode() . ').';
	}
}
