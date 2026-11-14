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
use Base3\MessageHub\Transport\PhpMailerMessageTransport;
use Base3\MessageHub\Transport\SendmailMessageTransport;
use Base3\MessageHub\Transport\SlackMessageTransport;
use Base3\MessageHub\Transport\SmtpMessageTransport;
use Base3\MessageHub\Transport\TelegramMessageTransport;
use Base3\MessageHub\Transport\TwilioSmsMessageTransport;
use Base3\MessageHub\Transport\WhatsAppCloudMessageTransport;
use Base3\MessagingFoundation\Api\IMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
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
