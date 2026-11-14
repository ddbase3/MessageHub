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
use Base3\MessageHub\Transport\SendmailMessageTransport;
use Base3\MessageHub\Transport\SmtpMessageTransport;
use Base3\MessageHub\Transport\TelegramMessageTransport;
use Base3\MessageHub\Transport\WhatsAppCloudMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RemainingTransportClassPathsTest extends TestCase {

	private function transport(string $class): object {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new $class($resolver, $this->createStub(ILogger::class));
	}

	public function testTransportCapabilitiesUseTheActualRuntimeFacilities(): void {
		$message = new Message('notice', 'Subject', 'Body');
		self::assertSame(function_exists('stream_socket_client'),
			$this->transport(SmtpMessageTransport::class)->supports($message));
		self::assertSame(function_exists('proc_open'),
			$this->transport(SendmailMessageTransport::class)->supports($message));
		self::assertSame(function_exists('curl_init'),
			$this->transport(HttpWebhookMessageTransport::class)->supports($message));
	}

	public function testTelegramReportsMalformedEndpointThroughItsDeliveryErrorContract(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [
			new MessageAddress('to', '12345')
		]);
		$result = $this->transport(TelegramMessageTransport::class)->send($message, [
			'enabled' => true,
			'api_base_url' => 'invalid-base-url',
			'bot_token' => 'local-test-token'
		]);

		self::assertFalse($result->isSuccess());
		self::assertSame(RuntimeException::class, $result->getDetails()['exception']);
		self::assertNotSame('', $result->getMessage());
	}

	public function testWhatsAppReportsMalformedEndpointThroughItsDeliveryErrorContract(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [
			new MessageAddress('to', '+49 (123) 456')
		]);
		$result = $this->transport(WhatsAppCloudMessageTransport::class)->send($message, [
			'enabled' => true,
			'api_base_url' => 'invalid-base-url',
			'api_version' => 'v22.0',
			'phone_number_id' => '123',
			'access_token' => 'local-test-token'
		]);

		self::assertFalse($result->isSuccess());
		self::assertSame(RuntimeException::class, $result->getDetails()['exception']);
		self::assertNotSame('', $result->getMessage());
	}
}
