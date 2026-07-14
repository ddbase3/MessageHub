<?php declare(strict_types=1);

namespace MessageHub\Transport\Support;

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

abstract class AbstractMessageTransport {

	public function __construct(
		protected readonly IConfigValueResolver $configValueResolver,
		protected readonly ILogger $logger
	) {}

	protected function isEnabled(array $settings): bool {
		return $this->readBool($settings, 'enabled', false);
	}

	protected function readString(array $settings, string $key, string $default = ''): string {
		$value = $settings[$key] ?? $default;

		return is_scalar($value) || $value === null ? trim((string)$value) : $default;
	}

	protected function readInt(array $settings, string $key, int $default): int {
		$value = $settings[$key] ?? $default;

		return is_numeric($value) ? (int)$value : $default;
	}

	protected function readBool(array $settings, string $key, bool $default): bool {
		if(!array_key_exists($key, $settings)) {
			return $default;
		}

		$value = $settings[$key];
		if(is_bool($value)) {
			return $value;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'enabled'], true);
	}

	protected function readArray(array $settings, string $key, array $default = []): array {
		$value = $settings[$key] ?? $default;

		return is_array($value) ? $value : $default;
	}

	protected function resolveString(mixed $definition, string $default = ''): string {
		try {
			$value = $this->configValueResolver->resolve(
				is_array($definition) || is_scalar($definition) || $definition === null ? $definition : $default
			);

			return is_scalar($value) || $value === null ? trim((string)$value) : $default;
		} catch(Throwable $exception) {
			return $default;
		}
	}

	protected function hasConfiguredValue(mixed $definition): bool {
		if(is_array($definition)) {
			return $definition !== [];
		}

		return is_scalar($definition) && trim((string)$definition) !== '';
	}

	protected function getMessageBody(Message $message): string {
		if(trim($message->getBodyText()) !== '') {
			return trim($message->getBodyText());
		}

		return trim(strip_tags($message->getBodyHtml()));
	}

	protected function getMessageText(Message $message, bool $includeSubject = true): string {
		$parts = [];
		$subject = trim($message->getSubject());
		$body = $this->getMessageBody($message);

		if($includeSubject && $subject !== '') {
			$parts[] = $subject;
		}

		if($body !== '') {
			$parts[] = $body;
		}

		return implode("\n\n", $parts);
	}

	/**
	 * @param array<int,string> $types
	 * @return array<int,MessageAddress>
	 */
	protected function getRecipients(Message $message, array $types = ['to']): array {
		$types = array_map(fn(string $type): string => strtolower(trim($type)), $types);
		$recipients = [];

		foreach($message->getRecipients() as $recipient) {
			if(!$recipient instanceof MessageAddress) {
				continue;
			}

			$address = trim($recipient->getAddress());
			if($address === '' || !in_array(strtolower($recipient->getType()), $types, true)) {
				continue;
			}

			$recipients[] = $recipient;
		}

		return $recipients;
	}

	protected function createSummary(array $parts): string {
		$parts = array_values(array_filter(array_map(
			fn(mixed $part): string => is_scalar($part) ? trim((string)$part) : '',
			$parts
		), fn(string $part): bool => $part !== ''));

		return $parts !== [] ? implode(' | ', $parts) : 'Not configured.';
	}

	protected function success(string $message, string $externalId = '', array $details = []): MessageDeliveryResult {
		return new MessageDeliveryResult(true, $message, $externalId, $details);
	}

	protected function failure(string $message, array $details = []): MessageDeliveryResult {
		return new MessageDeliveryResult(false, $message, '', $details);
	}

	protected function failureFromException(string $transport, Throwable $exception, array $details = []): MessageDeliveryResult {
		$this->logger->warning($transport . ' delivery failed', [
			'scope' => 'messagehub',
			'error' => $exception->getMessage()
		]);

		$details['exception'] = get_class($exception);

		return $this->failure($exception->getMessage(), $details);
	}

	/**
	 * @return array<int,string>
	 */
	protected function splitText(string $value, int $maximumLength): array {
		$value = trim($value);
		if($value === '' || $maximumLength <= 0) {
			return [];
		}

		$parts = [];
		while($value !== '') {
			if(function_exists('mb_strlen') && function_exists('mb_substr')) {
				if(mb_strlen($value, 'UTF-8') <= $maximumLength) {
					$parts[] = $value;
					break;
				}

				$parts[] = mb_substr($value, 0, $maximumLength, 'UTF-8');
				$value = ltrim(mb_substr($value, $maximumLength, null, 'UTF-8'));
				continue;
			}

			if(strlen($value) <= $maximumLength) {
				$parts[] = $value;
				break;
			}

			$parts[] = substr($value, 0, $maximumLength);
			$value = ltrim(substr($value, $maximumLength));
		}

		return $parts;
	}

	protected function truncate(string $value, int $maximumLength): string {
		if($maximumLength <= 0) {
			return '';
		}

		if(function_exists('mb_strlen') && function_exists('mb_substr')) {
			return mb_strlen($value, 'UTF-8') > $maximumLength
				? mb_substr($value, 0, max(0, $maximumLength - 1), 'UTF-8') . '…'
				: $value;
		}

		return strlen($value) > $maximumLength
			? substr($value, 0, max(0, $maximumLength - 3)) . '...'
			: $value;
	}
}
