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

use Base3\MessageHub\Service\MessageIdGenerator;
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
