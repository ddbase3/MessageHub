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

namespace Base3\MessageHub\Test\Service;

use Base3\MessageHub\Service\MessageSerializer;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use Base3\MessagingFoundation\Dto\MessageAttachment;
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
