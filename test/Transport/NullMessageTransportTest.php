<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Dto\Message;
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
