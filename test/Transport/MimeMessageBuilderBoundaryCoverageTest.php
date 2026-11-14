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

use Base3\MessageHub\Transport\Support\MimeMessageBuilder;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use Base3\MessagingFoundation\Dto\MessageAttachment;
use PHPUnit\Framework\TestCase;

final class MimeMessageBuilderBoundaryCoverageTest extends TestCase {

	public function testBccOnlyMessageDoesNotExposeBccHeaderOrRecipientNames(): void {
		$message = new Message('notice', 'Private', 'Body', '', [
			new MessageAddress('bcc', 'private@example.test', 'Private recipient')
		], [], 'sender@example.test');
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertSame(['private@example.test'], $mime['recipients']);
		self::assertStringNotContainsString('Bcc:', $mime['headers']);
		self::assertStringNotContainsString('Private recipient', $mime['headers']);
		self::assertStringNotContainsString('To:', $mime['headers']);
	}

	public function testSenderWithoutDomainUsesLocalhostForMessageIdentifier(): void {
		$message = new Message('notice', 'No domain', 'Body', '', [
			new MessageAddress('to', 'to@example.test')
		], [], 'local-sender');
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertSame('local-sender', $mime['from']);
		self::assertMatchesRegularExpression('/Message-ID: <[a-f0-9]{32}@localhost>/', $mime['headers']);
	}

	public function testHeaderInjectionIsRemovedFromReplyToNamesAndAddresses(): void {
		$message = new Message('notice', 'Header', 'Body', '', [
			new MessageAddress('to', 'one@example.test')
		], [], 'sender@example.test', 'Sender', "reply@example.test\r\nX-Injected: yes", "Reply\r\nInjected");
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertStringContainsString('Reply-To:', $mime['headers']);
		self::assertStringNotContainsString("\r\nX-Injected:", $mime['headers']);
		self::assertStringNotContainsString("\r\nInjected", $mime['headers']);
	}

	public function testMultipleAttachmentsKeepIndividualMimeTypesAndDisposition(): void {
		$first = tempnam(sys_get_temp_dir(), 'messagehub_mime_first_');
		$second = tempnam(sys_get_temp_dir(), 'messagehub_mime_second_');
		self::assertIsString($first);
		self::assertIsString($second);
		file_put_contents($first, 'one');
		file_put_contents($second, 'two');
		try {
			$message = new Message('notice', 'Files', 'Plain', '', [
				new MessageAddress('to', 'person@example.test')
			], [
				new MessageAttachment($first, 'one.txt', 'text/plain', false),
				new MessageAttachment($second, 'two.jpg', 'image/jpeg', true, 'photo-2')
			], 'sender@example.test');
			$mime = (new MimeMessageBuilder())->build($message);
			self::assertStringContainsString('multipart/mixed', $mime['headers']);
			self::assertStringNotContainsString('multipart/alternative', $mime['body']);
			self::assertStringContainsString('Content-Type: text/plain; name="one.txt"', $mime['body']);
			self::assertStringContainsString('Content-Disposition: attachment; filename="one.txt"', $mime['body']);
			self::assertStringContainsString('Content-Type: image/jpeg; name="two.jpg"', $mime['body']);
			self::assertStringContainsString('Content-Disposition: inline; filename="two.jpg"', $mime['body']);
			self::assertStringContainsString('Content-ID: <photo-2>', $mime['body']);
			self::assertStringContainsString(base64_encode('one'), $mime['body']);
			self::assertStringContainsString(base64_encode('two'), $mime['body']);
		} finally {
			unlink($first);
			unlink($second);
		}
	}

	public function testRecipientDeduplicationDoesNotRemoveDifferentAddressTypesFromHeader(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [
			new MessageAddress('to', 'shared@example.test'),
			new MessageAddress('cc', 'shared@example.test'),
			new MessageAddress('cc', 'other@example.test')
		], [], 'sender@example.test');
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertSame(['shared@example.test', 'other@example.test'], $mime['recipients']);
		self::assertStringContainsString('To: shared@example.test', $mime['headers']);
		self::assertStringContainsString('Cc: shared@example.test, other@example.test', $mime['headers']);
	}


	public function testSenderDomainIsSanitizedForMessageIdWhileHeadersStaySingleLine(): void {
		$message = new Message('notice', "Subject\r\nX-Custom: injected", 'Body', '', [
			new MessageAddress('to', 'receiver@example.test')
		], [], 'sender@example.test', "Display\r\nName");
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertMatchesRegularExpression('/Message-ID: <[a-f0-9]{32}@example\\.test>/', $mime['headers']);
		self::assertStringNotContainsString("\r\nX-Custom: injected", $mime['headers']);
		self::assertStringNotContainsString("\r\nName", $mime['headers']);
	}

	public function testHtmlOnlyBodyGetsPlainTextAlternativeWithoutChangingOriginalHtml(): void {
		$message = new Message('notice', 'HTML', '', '<p>First</p><p>Second</p>', [
			new MessageAddress('to', 'user@example.test')
		], [], 'sender@example.test');
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertStringContainsString('multipart/alternative', $mime['headers']);
		self::assertStringContainsString(base64_encode('FirstSecond'), $mime['body']);
		self::assertStringContainsString(base64_encode('<p>First</p><p>Second</p>'), $mime['body']);
	}
}
