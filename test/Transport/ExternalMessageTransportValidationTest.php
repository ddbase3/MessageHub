<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessageHub\Transport\HttpWebhookMessageTransport;
use MessageHub\Transport\MicrosoftTeamsMessageTransport;
use MessageHub\Transport\NtfyMessageTransport;
use MessageHub\Transport\SendmailMessageTransport;
use MessageHub\Transport\SlackMessageTransport;
use MessageHub\Transport\SmtpMessageTransport;
use MessageHub\Transport\TelegramMessageTransport;
use MessageHub\Transport\TwilioSmsMessageTransport;
use MessageHub\Transport\WhatsAppCloudMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use PHPUnit\Framework\TestCase;

final class ExternalMessageTransportValidationTest extends TestCase {

	private function transport(string $class): object {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $definition): mixed => $definition);
		return new $class($resolver, $this->createStub(ILogger::class));
	}

	private function message(bool $recipient = false, string $subject = 'Subject', string $body = 'Body'): Message {
		return new Message('notice', $subject, $body, '', $recipient ? [new MessageAddress('to', '+49123456789')] : []);
	}

	public function testEnabledTransportsRejectMissingMandatoryConfigurationBeforeNetworkRequests(): void {
		$cases = [
			[SlackMessageTransport::class, [], 'webhook_url'],
			[NtfyMessageTransport::class, [], 'default_topic'],
			[TelegramMessageTransport::class, [], 'bot_token'],
			[TwilioSmsMessageTransport::class, [], 'account_sid and auth_token'],
			[WhatsAppCloudMessageTransport::class, [], 'api_version, phone_number_id and access_token'],
			[MicrosoftTeamsMessageTransport::class, [], 'webhook_url'],
			[HttpWebhookMessageTransport::class, [], 'endpoint']
		];
		foreach($cases as [$class, $settings, $error]) {
			$result = $this->transport($class)->send($this->message(), ['enabled' => true] + $settings);
			self::assertFalse($result->isSuccess(), $class);
			self::assertStringContainsString($error, $result->getMessage(), $class);
		}
	}

	public function testTextTransportsRejectAttachmentsWithoutAttemptingDelivery(): void {
		$message = $this->message()->withAttachments([new MessageAttachment('/nonexistent/file.txt')]);
		$classes = [SlackMessageTransport::class, NtfyMessageTransport::class, TelegramMessageTransport::class,
			TwilioSmsMessageTransport::class, WhatsAppCloudMessageTransport::class, MicrosoftTeamsMessageTransport::class];
		foreach($classes as $class) {
			$result = $this->transport($class)->send($message, ['enabled' => true]);
			self::assertFalse($result->isSuccess(), $class);
			self::assertStringContainsString('attachments', $result->getMessage(), $class);
		}
	}

	public function testConfiguredSmsAndChatTransportsRequireRecipients(): void {
		$cases = [
			[TelegramMessageTransport::class, ['bot_token' => 'token'], 'TO recipient'],
			[TwilioSmsMessageTransport::class, ['account_sid' => 'account', 'auth_token' => 'secret', 'from_number' => '+4999'], 'TO recipient'],
			[WhatsAppCloudMessageTransport::class, ['api_version' => 'v22.0', 'phone_number_id' => '123', 'access_token' => 'token'], 'TO recipient']
		];
		foreach($cases as [$class, $settings, $error]) {
			$result = $this->transport($class)->send($this->message(), ['enabled' => true] + $settings);
			self::assertFalse($result->isSuccess(), $class);
			self::assertStringContainsString($error, $result->getMessage(), $class);
		}
	}

	public function testTransportsRejectEmptyTextBeforeSending(): void {
		$cases = [
			[SlackMessageTransport::class, ['webhook_url' => 'https://example.test/hook'], false],
			[NtfyMessageTransport::class, ['default_topic' => 'alerts'], false],
			[TelegramMessageTransport::class, ['bot_token' => 'token'], true],
			[TwilioSmsMessageTransport::class, ['account_sid' => 'sid', 'auth_token' => 'token', 'from_number' => '+49001'], true],
			[WhatsAppCloudMessageTransport::class, ['api_version' => 'v22', 'phone_number_id' => '123', 'access_token' => 'token'], true],
			[MicrosoftTeamsMessageTransport::class, ['webhook_url' => 'https://example.test/hook'], false]
		];
		foreach($cases as [$class, $settings, $recipient]) {
			$result = $this->transport($class)->send($this->message($recipient, '', ''), ['enabled' => true] + $settings);
			self::assertFalse($result->isSuccess(), $class);
			self::assertStringContainsString('message text', $result->getMessage(), $class);
		}
	}

	public function testTwilioRequiresSenderAndNtfyRequiresAuthenticationParameters(): void {
		$twilio = $this->transport(TwilioSmsMessageTransport::class);
		$result = $twilio->send($this->message(true), [
			'enabled' => true, 'account_sid' => 'sid', 'auth_token' => 'token'
		]);
		self::assertFalse($result->isSuccess());
		self::assertStringContainsString('from_number or messaging_service_sid', $result->getMessage());

		$ntfy = $this->transport(NtfyMessageTransport::class);
		foreach([
			['auth_mode' => 'bearer', 'default_topic' => 'alerts', 'token' => ''],
			['auth_mode' => 'basic', 'default_topic' => 'alerts', 'username' => '']
		] as $settings) {
			$result = $ntfy->send($this->message(), ['enabled' => true] + $settings);
			self::assertFalse($result->isSuccess());
			self::assertStringContainsString('authentication needs', $result->getMessage());
		}
	}

	public function testSmtpAndSendmailValidationFailsBeforeAnyExternalDelivery(): void {
		$smtp = $this->transport(SmtpMessageTransport::class);
		$missingSender = $smtp->send($this->message(), ['enabled' => true]);
		self::assertFalse($missingSender->isSuccess());
		self::assertStringContainsString('from_address', $missingSender->getMessage());

		$sendmail = $this->transport(SendmailMessageTransport::class);
		$missingBinary = $sendmail->send($this->message(), ['enabled' => true, 'binary_path' => '/this/sendmail/does/not/exist']);
		self::assertFalse($missingBinary->isSuccess());
		self::assertStringContainsString('Sendmail', $missingBinary->getMessage());
	}
}
