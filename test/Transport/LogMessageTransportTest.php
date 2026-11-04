<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\Logger\Api\ILogger;
use MessageHub\Transport\LogMessageTransport;
use MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class LogMessageTransportTest extends TestCase {

	public function testEnabledTransportLogsMessageWithoutExternalDelivery(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('info')->with('MessageHub log transport received message', [
			'scope' => 'messagehub', 'type_name' => 'notice', 'subject' => 'Subject'
		]);
		$transport = new LogMessageTransport($logger);
		self::assertSame('log', $transport::getName());
		self::assertSame('No delivery settings required.', $transport->getSettingsSummary());
		self::assertTrue($transport->supports(new Message('notice', 'Subject', 'Body')));
		self::assertTrue($transport->send(new Message('notice', 'Subject', 'Body'))->isSuccess());
	}

	public function testDisabledTransportDoesNotLog(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::never())->method('info');
		$transport = new LogMessageTransport($logger);
		self::assertFalse($transport->send(new Message('t', 's', 'b'), ['enabled' => 'no'])->isSuccess());
	}
}
