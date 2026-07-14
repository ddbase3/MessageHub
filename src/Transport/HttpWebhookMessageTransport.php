<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessageHub\Transport\Support\HttpResponse;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class HttpWebhookMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'httpwebhook';
	}

	public function getLabel(): string {
		return 'HTTP Webhook';
	}

	public function getSettingsSummary(array $settings = []): string {
		return $this->createSummary([
			'Method: ' . strtoupper($this->readString($settings, 'method', 'POST')),
			'Endpoint: ' . ($this->hasConfiguredValue($settings['endpoint'] ?? null) ? 'configured' : 'not configured'),
			'Content: ' . strtolower($this->readString($settings, 'content_type', 'json')),
			'Authentication: ' . strtolower($this->readString($settings, 'auth_mode', 'none'))
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init');
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('HTTP webhook transport is disabled.');
		}

		$endpoint = $this->resolveString($settings['endpoint'] ?? '');
		if($endpoint === '') {
			return $this->failure('HTTP webhook transport needs an endpoint setting.');
		}

		$method = strtoupper($this->readString($settings, 'method', 'POST'));
		$contentType = strtolower($this->readString($settings, 'content_type', 'json'));
		$headers = $this->buildHeaders($settings);
		$this->applyAuthentication($headers, $settings);

		try {
			$response = $this->sendRequest($message, $settings, $method, $endpoint, $contentType, $headers);
			$minimumStatus = $this->readInt($settings, 'expected_status_min', 200);
			$maximumStatus = $this->readInt($settings, 'expected_status_max', 299);
			$statusCode = $response->getStatusCode();

			if($statusCode < $minimumStatus || $statusCode > $maximumStatus) {
				return $this->failure($this->getHttpError($response, 'HTTP webhook rejected the message.'), [
					'status_code' => $statusCode
				]);
			}

			$externalId = $this->readResponseValue($response, $this->readString($settings, 'response_id_path', ''));

			$details = [
				'transport' => self::getName(),
				'status_code' => $statusCode
			];
			if($this->readBool($settings, 'include_response_body', false)) {
				$details['response'] = $this->truncate(trim($response->getBody()), 500);
			}

			return $this->success('Message sent by HTTP webhook.', $externalId, $details);
		} catch(Throwable $exception) {
			return $this->failureFromException('HTTP webhook', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'endpoint' => ['description' => 'ConfigValue definition or fixed endpoint URL'],
				'method' => ['type' => 'string', 'enum' => ['POST', 'PUT', 'PATCH']],
				'content_type' => ['type' => 'string', 'enum' => ['json', 'form', 'text']],
				'headers' => ['type' => 'object'],
				'auth_mode' => ['type' => 'string', 'enum' => ['none', 'bearer', 'basic']],
				'bearer_token' => ['description' => 'ConfigValue definition or fixed secret'],
				'username' => ['type' => 'string'],
				'password' => ['description' => 'ConfigValue definition or fixed secret'],
				'include_message' => ['type' => 'boolean'],
				'custom_payload' => ['type' => 'object'],
				'text_template' => ['type' => 'string'],
				'expected_status_min' => ['type' => 'integer'],
				'expected_status_max' => ['type' => 'integer'],
				'response_id_path' => ['type' => 'string'],
				'include_response_body' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function sendRequest(
		Message $message,
		array $settings,
		string $method,
		string $endpoint,
		string $contentType,
		array $headers
	): HttpResponse {
		$timeout = $this->readInt($settings, 'timeout', 20);
		$verifyTls = $this->readBool($settings, 'verify_tls', true);

		if($contentType === 'text') {
			$template = $this->readString($settings, 'text_template', '');
			$body = $template !== ''
				? (string)$this->replacePlaceholders($template, $message)
				: $this->getMessageText($message, true);
			$headers['Content-Type'] = $headers['Content-Type'] ?? 'text/plain; charset=UTF-8';

			return $this->request($method, $endpoint, $headers, $body, $timeout, $verifyTls);
		}

		$payload = $this->buildPayload($message, $settings);
		if($contentType === 'form') {
			$headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded; charset=UTF-8';

			return $this->request(
				$method,
				$endpoint,
				$headers,
				http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
				$timeout,
				$verifyTls
			);
		}

		return $this->requestJson($method, $endpoint, $payload, $headers, $timeout, $verifyTls);
	}

	private function buildPayload(Message $message, array $settings): array {
		$customPayload = $this->readArray($settings, 'custom_payload', []);
		$payload = $customPayload !== []
			? $this->replacePlaceholders($customPayload, $message)
			: [];

		if(!is_array($payload)) {
			$payload = [];
		}

		if($customPayload === [] || $this->readBool($settings, 'include_message', true)) {
			$payload['message'] = $message->toArray();
		}

		return $payload;
	}

	/**
	 * @return array<string,string>
	 */
	private function buildHeaders(array $settings): array {
		$headers = [];
		foreach($this->readArray($settings, 'headers', []) as $name => $definition) {
			$name = trim((string)$name);
			$value = $this->resolveString($definition);
			if($name !== '' && $value !== '') {
				$headers[$name] = $value;
			}
		}

		return $headers;
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function applyAuthentication(array &$headers, array $settings): void {
		$authMode = strtolower($this->readString($settings, 'auth_mode', 'none'));
		if($authMode === 'bearer') {
			$token = $this->resolveString($settings['bearer_token'] ?? '');
			if($token !== '') {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
			return;
		}

		if($authMode === 'basic') {
			$username = $this->readString($settings, 'username', '');
			$password = $this->resolveString($settings['password'] ?? '');
			$headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
		}
	}

	private function replacePlaceholders(mixed $value, Message $message): mixed {
		if(is_array($value)) {
			$result = [];
			foreach($value as $key => $item) {
				$result[$key] = $this->replacePlaceholders($item, $message);
			}
			return $result;
		}

		if(!is_string($value)) {
			return $value;
		}

		return strtr($value, [
			'{{type_name}}' => $message->getTypeName(),
			'{{subject}}' => $message->getSubject(),
			'{{body_text}}' => $message->getBodyText(),
			'{{body_html}}' => $message->getBodyHtml(),
			'{{from_address}}' => $message->getFromAddress(),
			'{{from_name}}' => $message->getFromName(),
			'{{reply_to_address}}' => $message->getReplyToAddress(),
			'{{reply_to_name}}' => $message->getReplyToName()
		]);
	}

	private function readResponseValue(HttpResponse $response, string $path): string {
		$path = trim($path);
		if($path === '') {
			return '';
		}

		$value = $response->getJson();
		foreach(explode('.', $path) as $segment) {
			if(!is_array($value) || !array_key_exists($segment, $value)) {
				return '';
			}
			$value = $value[$segment];
		}

		return is_scalar($value) || $value === null ? (string)$value : '';
	}
}
