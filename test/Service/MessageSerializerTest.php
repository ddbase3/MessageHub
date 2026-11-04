<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use MessageHub\Service\MessageSerializer;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageSerializerTest extends TestCase {

	public function testRoundTripPreservesAllMessageFields(): void {
		$message = new Message('invoice', 'Überweisung', 'Text with / slash', '<b>HTML</b>', [
			new MessageAddress('to', 'example@example.test', 'Änne')
		], [new MessageAttachment('/tmp/file.pdf', 'file.pdf', 'application/pdf', true, 'cid1')],
			'sender@example.test', 'Sender', 'reply@example.test', 'Reply', ['number' => 42]);
		$serializer = new MessageSerializer();
		$encoded = $serializer->encode($message);
		self::assertStringContainsString('Überweisung', $encoded);
		self::assertStringContainsString('Text with / slash', $encoded);
		self::assertSame($message->toArray(), $serializer->decode($encoded)->toArray());
	}

	public function testDecodingRejectsMalformedAndNonObjectJson(): void {
		$serializer = new MessageSerializer();
		foreach(['{', 'null', 'false', '"text"', '42'] as $json) {
			try {
				$serializer->decode($json);
				self::fail('Invalid message JSON accepted: ' . $json);
			} catch(RuntimeException $exception) {
				self::assertStringContainsString('Message JSON could not be decoded', $exception->getMessage());
			}
		}
	}

	public function testEncodingRejectsInvalidUtf8(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Message could not be encoded.');
		(new MessageSerializer())->encode(new Message('type', "\xB1", 'body'));
	}
}
