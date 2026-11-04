<?php declare(strict_types=1);

namespace MessageHub\Test\Check;

use Base3\Database\Api\IDatabase;
use MessageHub\Check\MessageHubCheck;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Api\IMessageTransportRegistry;
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
