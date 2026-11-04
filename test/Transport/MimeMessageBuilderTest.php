<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use InvalidArgumentException;
use MessageHub\Transport\Support\MimeMessageBuilder;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use PHPUnit\Framework\TestCase;

final class MimeMessageBuilderTest extends TestCase {

	public function testPlainTextMessageIncludesRecipientsAndSanitizesHeaders(): void {
		$message = new Message('notice', "Hello\nBcc: injected", 'Plain text', '', [
			new MessageAddress('to', 'one@example.test', 'Recipient'),
			new MessageAddress('cc', 'two@example.test'),
			new MessageAddress('bcc', 'secret@example.test'),
			new MessageAddress('to', 'one@example.test')
		], [], 'from@example.test', 'Sender', 'reply@example.test');
		$mime = (new MimeMessageBuilder())->build($message);
		self::assertSame('from@example.test', $mime['from']);
		self::assertSame(['one@example.test', 'two@example.test', 'secret@example.test'], $mime['recipients']);
		self::assertStringContainsString('Content-Type: text/plain', $mime['headers']);
		self::assertStringNotContainsString("\nBcc: injected", $mime['headers']);
		self::assertStringContainsString('Reply-To: reply@example.test', $mime['headers']);
		self::assertStringContainsString(base64_encode('Plain text'), $mime['body']);
		self::assertStringContainsString("\r\n\r\n", $mime['raw']);
	}

	public function testHtmlMessageProducesTextAndHtmlAlternatives(): void {
		$message = new Message('notice', 'Subject', '', '<p>Hello <b>HTML</b></p>',
			[new MessageAddress('to', 'recipient@example.test')]);
		$mime = (new MimeMessageBuilder())->build($message, ['from_address' => 'sender@example.test']);
		self::assertStringContainsString('multipart/alternative', $mime['headers']);
		self::assertStringContainsString(base64_encode('<p>Hello <b>HTML</b></p>'), $mime['body']);
		self::assertStringContainsString(base64_encode('Hello HTML'), $mime['body']);
	}

	public function testAttachmentIsIncludedWithMimeHeadersAndBase64Content(): void {
		$path = tempnam(sys_get_temp_dir(), 'messagehub_test_');
		self::assertIsString($path);
		file_put_contents($path, 'document bytes');
		try {
			$message = new Message('notice', 'File', 'Text', '', [new MessageAddress('to', 'to@example.test')], [
				new MessageAttachment($path, 'document.txt', 'text/plain', true, 'inline-cid')
			]);
			$mime = (new MimeMessageBuilder())->build($message, ['from_address' => 'from@example.test']);
			self::assertStringContainsString('multipart/mixed', $mime['headers']);
			self::assertStringContainsString('Content-Disposition: inline', $mime['body']);
			self::assertStringContainsString('Content-ID: <inline-cid>', $mime['body']);
			self::assertStringContainsString(base64_encode('document bytes'), $mime['body']);
		} finally {
			unlink($path);
		}
	}

	public function testMissingSenderOrRecipientsFailBeforeBuildingMime(): void {
		$builder = new MimeMessageBuilder();
		foreach([new Message('notice', 's', 'body'),
			new Message('notice', 's', 'body', '', [], [], 'sender@example.test')] as $message) {
			try {
				$builder->build($message);
				self::fail('Missing sender or recipient was accepted.');
			} catch(InvalidArgumentException $exception) {
				self::assertStringContainsString('needs', $exception->getMessage());
			}
		}
	}

	public function testUnreadableAttachmentFailsWithExplicitPath(): void {
		$message = new Message('notice', 's', 'b', '', [new MessageAddress('to', 'to@example.test')], [
			new MessageAttachment('/this/path/does/not/exist/messagehub-file')
		], 'from@example.test');
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Attachment is not readable');
		(new MimeMessageBuilder())->build($message);
	}


	public function testSettingsFallbackSenderAndReplyToAreAppliedWithoutExtraHeaders(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [new MessageAddress('bcc', 'one@example.test')]);
		$mime = (new MimeMessageBuilder())->build($message, [
			'from_address' => '  sender@example.test  ', 'from_name' => 'Sender',
			'reply_to_address' => 'reply@example.test', 'reply_to_name' => 'Reply'
		]);
		self::assertSame('sender@example.test', $mime['from']);
		self::assertSame(['one@example.test'], $mime['recipients']);
		self::assertStringContainsString('From: Sender <sender@example.test>', $mime['headers']);
		self::assertStringContainsString('Reply-To: Reply <reply@example.test>', $mime['headers']);
		self::assertStringNotContainsString('Bcc:', $mime['headers']);
		self::assertStringContainsString('Content-Type: text/plain', $mime['headers']);
	}

	public function testMessageSenderAndReplyToOverrideSettings(): void {
		$message = new Message('notice', 'Subject', 'Body', '', [new MessageAddress('to', 'to@example.test')], [],
			'actual@example.test', 'Actual', 'real-reply@example.test', 'Real Reply');
		$mime = (new MimeMessageBuilder())->build($message, ['from_address' => 'fallback@example.test',
			'reply_to_address' => 'fallback-reply@example.test']);
		self::assertSame('actual@example.test', $mime['from']);
		self::assertStringContainsString('real-reply@example.test', $mime['headers']);
		self::assertStringNotContainsString('fallback@example.test', $mime['headers']);
		self::assertStringNotContainsString('fallback-reply@example.test', $mime['headers']);
	}

	public function testFileAttachmentUsesDefaultNameAndMimeTypeAndEscapesHeaderQuotes(): void {
		$path = tempnam(sys_get_temp_dir(), 'messagehub_attachment_');
		self::assertIsString($path);
		file_put_contents($path, 'payload');
		try {
			$message = new Message('notice', 'File', 'Body', '', [new MessageAddress('to', 'to@example.test')], [
				new MessageAttachment($path, 'a"file.txt')
			], 'from@example.test');
			$mime = (new MimeMessageBuilder())->build($message);
			self::assertStringContainsString('Content-Type: application/octet-stream', $mime['body']);
			self::assertStringContainsString('Content-Disposition: attachment', $mime['body']);
			self::assertStringContainsString('filename="a\\"file.txt"', $mime['body']);
			self::assertStringContainsString(base64_encode('payload'), $mime['body']);
		} finally {
			unlink($path);
		}
	}

	public function testHtmlAndInlineAttachmentProduceNestedMultipartSections(): void {
		$path = tempnam(sys_get_temp_dir(), 'messagehub_inline_');
		self::assertIsString($path);
		file_put_contents($path, 'image bytes');
		try {
			$message = new Message('notice', 'HTML', '', '<b>Hello</b>', [new MessageAddress('to', 'to@example.test')], [
				new MessageAttachment($path, '', '', true, '')
			], 'from@example.test');
			$mime = (new MimeMessageBuilder())->build($message);
			self::assertStringContainsString('multipart/mixed', $mime['headers']);
			self::assertStringContainsString('multipart/alternative', $mime['body']);
			self::assertStringContainsString('Content-Disposition: inline', $mime['body']);
			self::assertStringContainsString('Content-ID: <' . sha1($path) . '>', $mime['body']);
			self::assertStringContainsString('filename="' . basename($path) . '"', $mime['body']);
		} finally {
			unlink($path);
		}
	}

}
