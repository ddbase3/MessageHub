<?php declare(strict_types=1);

namespace MessageHub\Test\Fixture;

use MessageHub\Transport\Support\AbstractMessageTransport;
use MessagingFoundation\Dto\Message;

final class MessageTransportProbe extends AbstractMessageTransport {

	public function inspectBooleans(array $settings): array {
		return [$this->readBool($settings, 'value', false), $this->readBool($settings, 'other', true)];
	}

	public function inspectString(array $settings): string { return $this->readString($settings, 'value', 'fallback'); }
	public function inspectInt(array $settings): int { return $this->readInt($settings, 'value', 7); }
	public function inspectArray(array $settings): array { return $this->readArray($settings, 'value', ['default']); }
	public function inspectResolved(mixed $definition, string $default = ''): string { return $this->resolveString($definition, $default); }
	public function inspectConfigured(mixed $definition): bool { return $this->hasConfiguredValue($definition); }
	public function inspectBody(Message $message): string { return $this->getMessageBody($message); }
	public function inspectText(Message $message, bool $includeSubject = true): string { return $this->getMessageText($message, $includeSubject); }
	public function inspectRecipients(Message $message, array $types): array { return $this->getRecipients($message, $types); }
	public function inspectSummary(array $parts): string { return $this->createSummary($parts); }
	public function inspectSplit(string $text, int $maximumLength): array { return $this->splitText($text, $maximumLength); }
	public function inspectTruncate(string $text, int $maximumLength): string { return $this->truncate($text, $maximumLength); }
	public function inspectSuccess(): bool { return $this->success('ok')->isSuccess(); }
	public function inspectFailure(): bool { return $this->failure('error')->isSuccess(); }
	public function inspectException(\Throwable $exception): array {
		$result = $this->failureFromException('Probe', $exception);
		return [$result->isSuccess(), $result->getMessage(), $result->getDetails()];
	}
}
