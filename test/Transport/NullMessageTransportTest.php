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

use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class NullMessageTransportTest extends TestCase {

	public function testDefaultTransportDiscardsMessageSuccessfullyWithoutCredentials(): void {
		$transport = new NullMessageTransport();
		$message = new Message('notice', 'subject', 'body');
		self::assertSame('null', $transport::getName());
		self::assertTrue($transport->supports($message));
		self::assertSame('boolean', $transport->getSchema()['properties']['enabled']['type']);
		$result = $transport->send($message, ['reason' => 'manual testing']);
		self::assertTrue($result->isSuccess());
		self::assertSame('manual testing', $result->getDetails()['reason']);
		self::assertSame('null', $result->getDetails()['transport']);
		self::assertStringContainsString('Reason: manual testing', $transport->getSettingsSummary(['reason' => 'manual testing']));
	}

	public function testDisabledTransportFailsWithoutDelivering(): void {
		$transport = new NullMessageTransport();
		foreach([false, 'false', 'OFF', 0] as $disabled) {
			self::assertFalse($transport->send(new Message('t', 's', 'b'), ['enabled' => $disabled])->isSuccess());
		}
	}
}
