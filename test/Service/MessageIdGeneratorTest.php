<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use MessageHub\Service\MessageIdGenerator;
use PHPUnit\Framework\TestCase;

final class MessageIdGeneratorTest extends TestCase {

	public function testGeneratedIdsAreUniqueAndContainSanitizedPrefix(): void {
		$generator = new MessageIdGenerator();
		$first = $generator->createId(' Que.UE / with spaces! ');
		$second = $generator->createId(' Que.UE / with spaces! ');
		self::assertMatchesRegularExpression('/^queuewithspaces_[0-9]{14}_[a-f0-9]{20}$/', $first);
		self::assertNotSame($first, $second);
	}

	public function testEmptyOrInvalidPrefixDefaultsToId(): void {
		$generator = new MessageIdGenerator();
		self::assertStringStartsWith('id_', $generator->createId(' *# '));
		self::assertStringStartsWith('queue_', $generator->createId('QUEUE'));
	}
}
