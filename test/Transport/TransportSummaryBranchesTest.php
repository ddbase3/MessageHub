<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessageHub\Transport\HttpWebhookMessageTransport;
use MessageHub\Transport\MicrosoftTeamsMessageTransport;
use MessageHub\Transport\NtfyMessageTransport;
use MessageHub\Transport\PhpMailerMessageTransport;
use MessageHub\Transport\SendmailMessageTransport;
use MessageHub\Transport\SlackMessageTransport;
use MessageHub\Transport\SmtpMessageTransport;
use MessageHub\Transport\TelegramMessageTransport;
use MessageHub\Transport\TwilioSmsMessageTransport;
use MessageHub\Transport\WhatsAppCloudMessageTransport;
use MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class TransportSummaryBranchesTest extends TestCase {

	private function create(string $class): object {
		return new $class($this->createStub(IConfigValueResolver::class), $this->createStub(ILogger::class));
	}

	public function testEveryConcreteTransportExposesItsSchemaAndTechnicalNameWithoutOptionalDependencies(): void {
		$classes = [SmtpMessageTransport::class, SendmailMessageTransport::class, PhpMailerMessageTransport::class,
			HttpWebhookMessageTransport::class, MicrosoftTeamsMessageTransport::class, NtfyMessageTransport::class,
			SlackMessageTransport::class, TelegramMessageTransport::class, TwilioSmsMessageTransport::class,
			WhatsAppCloudMessageTransport::class];
		foreach($classes as $class) {
			$transport = $this->create($class);
			self::assertNotEmpty($transport::getName());
			self::assertNotEmpty($transport->getLabel());
			self::assertSame('object', $transport->getSchema()['type']);
			self::assertArrayHasKey('enabled', $transport->getSchema()['properties']);
			self::assertNotEmpty($transport->getSettingsSummary());
		}
	}

	public function testMailSummaryBranchesNeverRevealPasswords(): void {
		$smtp = $this->create(SmtpMessageTransport::class);
		$summary = $smtp->getSettingsSummary(['host' => 'smtp.example.test', 'port' => '2525', 'encryption' => '',
			'auth' => 'yes', 'username' => 'alice', 'password' => 'private-password']);
		self::assertStringContainsString('smtp.example.test:2525', $summary);
		self::assertStringContainsString('Encryption: None', $summary);
		self::assertStringContainsString('Username: alice', $summary);
		self::assertStringContainsString('Password: configured', $summary);
		self::assertStringNotContainsString('private-password', $summary);
		$withoutAuth = $smtp->getSettingsSummary(['host' => 'smtp.example.test', 'auth' => 'no']);
		self::assertStringContainsString('Authentication: disabled', $withoutAuth);
		self::assertStringNotContainsString('Username:', $withoutAuth);

		$phpMailer = $this->create(PhpMailerMessageTransport::class);
		$mail = $phpMailer->getSettingsSummary(['mode' => 'mail', 'from_address' => 'sender@example.test', 'from_name' => 'Sender']);
		self::assertStringContainsString('PHP mail', $mail);
		self::assertStringContainsString('Sender <sender@example.test>', $mail);
		$smtpSummary = $phpMailer->getSettingsSummary(['mode' => 'smtp', 'smtp_host' => 'smtp.example.test',
			'smtp_secure' => 'ssl', 'smtp_auth' => false, 'smtp_password' => 'secret']);
		self::assertStringContainsString('Encryption: SSL', $smtpSummary);
		self::assertStringContainsString('Authentication: disabled', $smtpSummary);
		self::assertStringNotContainsString('secret', $smtpSummary);
		self::assertFalse($phpMailer->send(new Message('notice', 'Subject', 'Body'), ['enabled' => false])->isSuccess());
	}

	public function testProviderSummariesReportConfiguredModesAndNeverRevealSecrets(): void {
		$cases = [
			[SendmailMessageTransport::class, ['binary_path' => '/tmp/custom-sendmail', 'from_address' => 'sender@example.test'], 'Binary: /tmp/custom-sendmail'],
			[HttpWebhookMessageTransport::class, ['method' => 'PATCH', 'content_type' => 'text', 'endpoint' => 'private-endpoint', 'auth_mode' => 'basic'], 'Content: text'],
			[MicrosoftTeamsMessageTransport::class, ['payload_mode' => 'workflow_text'], 'Workflow text payload'],
			[NtfyMessageTransport::class, ['base_url' => '', 'default_topic' => 'alerts', 'priority' => 'high'], 'Default topic: alerts'],
			[SlackMessageTransport::class, ['unfurl_links' => true], 'Link unfurling enabled'],
			[TelegramMessageTransport::class, ['bot_token' => 'private-token', 'parse_mode' => 'MarkdownV2', 'disable_notification' => true], 'Silent delivery'],
			[TwilioSmsMessageTransport::class, ['account_sid' => 'A123', 'messaging_service_sid' => 'MG123', 'auth_token' => 'secret'], 'Messaging service: MG123'],
			[WhatsAppCloudMessageTransport::class, ['phone_number_id' => 'phone-id', 'access_token' => 'secret'], 'phone-id']
		];
		foreach($cases as [$class, $settings, $expected]) {
			$summary = $this->create($class)->getSettingsSummary($settings);
			self::assertStringContainsString($expected, $summary, $class);
			self::assertStringNotContainsString('private-token', $summary, $class);
			self::assertStringNotContainsString('secret', $summary, $class);
		}
	}

	public function testTextOnlyProvidersDoNotAdvertiseSupportForAttachments(): void {
		$original = new Message('notice', 'Subject', 'Body');
		$withAttachment = $original->withAttachments([new \MessagingFoundation\Dto\MessageAttachment('/not/a/file')]);
		foreach([SlackMessageTransport::class, NtfyMessageTransport::class,
			TelegramMessageTransport::class, MicrosoftTeamsMessageTransport::class,
			TwilioSmsMessageTransport::class, WhatsAppCloudMessageTransport::class] as $class) {
			$transport = $this->create($class);
			self::assertSame(function_exists('curl_init'), $transport->supports($original), $class);
			self::assertFalse($transport->supports($withAttachment), $class);
		}
	}
}
