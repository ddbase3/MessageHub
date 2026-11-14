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

namespace Base3\MessageHub\Test\Check;

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessageHub\Check\MessageHubCheck;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageHubCheckTest extends TestCase {

	public function testCheckEnsuresTablesAndListsRegisteredTransports(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::once())->method('connect');
		$database->method('singleQuery')->willReturn(['Field' => 'fallback_flag']);
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willReturn(['null' => new NullMessageTransport()]);
		$check = new MessageHubCheck(new DatabaseSchema($database), $registry);
		self::assertSame('messagehubcheck', $check::getName());
		self::assertSame(['messagehub_tables' => 'Ok', 'messagehub_transports' => 'null'], $check->checkDependencies());
	}
}
