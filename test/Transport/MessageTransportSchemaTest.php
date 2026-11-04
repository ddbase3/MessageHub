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
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class MessageTransportSchemaTest extends TestCase {

	public function testEveryExternalTransportHasMetadataAndDefaultsToDisabled(): void {
		$message = new Message('notice', 'Subject', 'Body');
		$classes = [HttpWebhookMessageTransport::class, MicrosoftTeamsMessageTransport::class,
			NtfyMessageTransport::class, PhpMailerMessageTransport::class, SendmailMessageTransport::class,
			SlackMessageTransport::class, SmtpMessageTransport::class, TelegramMessageTransport::class,
			TwilioSmsMessageTransport::class, WhatsAppCloudMessageTransport::class];
		$names = [];
		foreach($classes as $class) {
			$transport = new $class($this->createStub(IConfigValueResolver::class), $this->createStub(ILogger::class));
			self::assertInstanceOf(IMessageTransport::class, $transport);
			self::assertNotSame('', $transport::getName());
			self::assertNotSame('', $transport->getLabel());
			self::assertNotSame('', $transport->getSettingsSummary());
			self::assertSame(false, $transport->getSchema()['properties']['enabled']['default']);
			self::assertFalse($transport->send($message)->isSuccess(), $class);
			$names[] = $transport::getName();
		}
		self::assertCount(count($classes), array_unique($names));
	}

	public function testSmtpAndPhpMailerSummariesNeverExposeSecretValues(): void {
		foreach([SmtpMessageTransport::class, PhpMailerMessageTransport::class] as $class) {
			$transport = new $class($this->createStub(IConfigValueResolver::class), $this->createStub(ILogger::class));
			$summary = $transport->getSettingsSummary([
				'host' => 'smtp.example.test', 'smtp_host' => 'smtp.example.test',
				'mode' => 'smtp', 'password' => 'not-a-real-password', 'smtp_password' => 'not-a-real-password'
			]);
			self::assertStringContainsString('Password: configured', $summary);
			self::assertStringNotContainsString('not-a-real-password', $summary);
		}
	}
}
