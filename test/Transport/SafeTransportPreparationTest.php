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

namespace Base3\MessageHub\Test\Transport;

use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Transport\HttpWebhookMessageTransport;
use Base3\MessageHub\Transport\MicrosoftTeamsMessageTransport;
use Base3\MessageHub\Transport\NtfyMessageTransport;
use Base3\MessageHub\Transport\SlackMessageTransport;
use Base3\MessageHub\Transport\SmtpMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;

final class SafeTransportPreparationTest extends TestCase {

	private function transport(string $class): object {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $definition): mixed => $definition);
		return new $class($resolver, $this->createStub(ILogger::class));
	}

	private function message(string $subject = 'Subject', string $body = 'Body'): Message {
		return new Message('notice', $subject, $body, '<p>HTML body</p>', [new MessageAddress('to', 'topic-1')]);
	}

	public function testWebhookTextFormAndJsonPreparationRejectInvalidEndpointWithoutNetworkTraffic(): void {
		$transport = $this->transport(HttpWebhookMessageTransport::class);
		foreach(['text', 'form', 'json'] as $contentType) {
			$result = $transport->send($this->message(), [
				'enabled' => true,
				'endpoint' => 'not-an-http-url',
				'method' => 'POST',
				'content_type' => $contentType,
				'auth_mode' => 'basic',
				'username' => 'test-user',
				'password' => 'private-password',
				'headers' => ['X-Notice' => '{{subject}}', ' X-Empty ' => ''],
				'custom_payload' => ['subject' => '{{subject}}', 'details' => ['body' => '{{body_text}}', 'count' => 2]],
				'include_message' => true
			]);
			self::assertFalse($result->isSuccess());
			self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
		}
	}

	public function testWebhookBearerModeAndCustomTextTemplateFailBeforeNetwork(): void {
		$result = $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
			'enabled' => true, 'endpoint' => 'bad-endpoint', 'content_type' => 'text',
			'auth_mode' => 'bearer', 'bearer_token' => 'my-secret',
			'text_template' => '{{type_name}}/{{subject}}/{{body_html}}', 'verify_tls' => false
		]);
		self::assertFalse($result->isSuccess());
		self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
	}

	public function testWebhookRejectsUnencodableJsonBeforeAttemptingHttpRequest(): void {
		$result = $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
			'enabled' => true, 'endpoint' => 'invalid-endpoint', 'content_type' => 'json',
			'custom_payload' => ['broken' => "\xB1\xB2"], 'include_message' => false
		]);
		self::assertFalse($result->isSuccess());
		self::assertSame(\JsonException::class, $result->getDetails()['exception']);
	}

	public function testWebhookCustomPayloadCanOmitMessageForValidContent(): void {
		$result = $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
			'enabled' => true, 'endpoint' => 'invalid-endpoint', 'content_type' => 'form',
			'custom_payload' => ['metadata' => ['subject' => '{{subject}}']], 'include_message' => false
		]);
		self::assertFalse($result->isSuccess());
		self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
	}

	public function testTeamsAdaptiveCardBuildsForEmptyAndNonemptyMessagesWithoutOutboundRequest(): void {
		$transport = $this->transport(MicrosoftTeamsMessageTransport::class);
		foreach([$this->message(), $this->message('', '')] as $message) {
			$result = $transport->send($message, [
				'enabled' => true, 'webhook_url' => 'invalid-endpoint', 'payload_mode' => 'adaptive_card'
			]);
			self::assertFalse($result->isSuccess());
			self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
		}
	}

	public function testSlackCustomBlocksMayReplaceEmptyMessageText(): void {
		$transport = $this->transport(SlackMessageTransport::class);
		$validWithoutBlocks = $transport->send(new Message('notice', '', '', ''), [
			'enabled' => true, 'webhook_url' => 'invalid-endpoint', 'blocks' => []
		]);
		self::assertFalse($validWithoutBlocks->isSuccess());
		self::assertStringContainsString('needs message text or configured blocks', $validWithoutBlocks->getMessage());
		$validWithBlocks = $transport->send(new Message('notice', '', '', ''), [
			'enabled' => true, 'webhook_url' => 'invalid-endpoint',
			'blocks' => [['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => 'notice']]]
		]);
		self::assertFalse($validWithBlocks->isSuccess());
		self::assertSame(\RuntimeException::class, $validWithBlocks->getDetails()['exception']);
	}

	public function testNtfyCanPrepareTagsAndAuthHeadersButWillNotSendToInvalidEndpoint(): void {
		$transport = $this->transport(NtfyMessageTransport::class);
		foreach([
			['auth_mode' => 'none', 'tags' => ['warning', '', 'active', ['ignored']], 'cache' => false, 'firebase' => false],
			['auth_mode' => 'bearer', 'token' => 'token', 'tags' => 'priority'],
			['auth_mode' => 'basic', 'username' => 'user', 'password' => 'pass', 'tags' => ['priority']]
		] as $auth) {
			$result = $transport->send($this->message(), ['enabled' => true,
				'base_url' => 'invalid-base-url', 'default_topic' => 'fallback-topic'] + $auth);
			self::assertFalse($result->isSuccess());
			self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
		}
	}

	public function testSmtpHostValidationRunsAfterBuildingValidMessage(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [new MessageAddress('to', 'recipient@example.test')]);
		$result = $this->transport(SmtpMessageTransport::class)->send($message, [
			'enabled' => true, 'from_address' => 'sender@example.test', 'host' => ''
		]);
		self::assertFalse($result->isSuccess());
		self::assertStringContainsString('SMTP host is not configured.', $result->getMessage());
	}

	public function testSummaryReportsConfigurationWithoutLeakingAuthenticationSecrets(): void {
		$webhook = $this->transport(HttpWebhookMessageTransport::class);
		$summary = $webhook->getSettingsSummary(['method' => 'put', 'endpoint' => 'https://example.test/private',
			'auth_mode' => 'bearer', 'bearer_token' => 'secret-value', 'content_type' => 'form']);
		self::assertStringContainsString('Method: PUT', $summary);
		self::assertStringContainsString('Content: form', $summary);
		self::assertStringNotContainsString('secret-value', $summary);
		$ntfy = $this->transport(NtfyMessageTransport::class);
		self::assertStringContainsString('Authentication: basic', $ntfy->getSettingsSummary(['auth_mode' => 'basic', 'priority' => 'high']));
		$teams = $this->transport(MicrosoftTeamsMessageTransport::class);
		self::assertStringContainsString('Adaptive Card payload', $teams->getSettingsSummary(['payload_mode' => 'adaptive_card']));
		$slack = $this->transport(SlackMessageTransport::class);
		self::assertStringContainsString('Custom blocks configured', $slack->getSettingsSummary(['blocks' => [['type' => 'section']]]));
	}
}
