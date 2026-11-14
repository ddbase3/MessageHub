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

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Transport\LogMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
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
