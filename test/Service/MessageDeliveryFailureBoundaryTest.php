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

use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\QueuedMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageDeliveryFailureBoundaryTest extends TestCase {

	private function queued(): QueuedMessage {
		return new QueuedMessage('queue-123', new Message('notice', 'Subject', 'Body'), 'null', 'processing', 0, 3);
	}

	public function testDeliveryRepositoryFailureCannotBeTreatedAsSuccessfulDelivery(): void {
		$delivery = $this->createStub(IMessageDeliveryRepository::class);
		$delivery->method('create')->willThrowException(new RuntimeException('cannot record delivery'));
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::never())->method('markSent');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::never())->method('fire');
		$service = new MessageDeliveryService($this->createStub(IMessageTransportRegistry::class),
			$delivery, $queue, $events, $this->createStub(ILogger::class));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cannot record delivery');
		$service->deliver($this->queued());
	}

	public function testFailedRecordingOfSentResultDoesNotMarkQueueSent(): void {
		$delivery = $this->createStub(IMessageDeliveryRepository::class);
		$delivery->method('create')->willReturn('delivery-123');
		$delivery->method('finish')->willThrowException(new RuntimeException('cannot finish delivery'));
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::never())->method('markSent');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::never())->method('fire');
		$service = new MessageDeliveryService($registry, $delivery, $queue, $events, $this->createStub(ILogger::class));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cannot finish delivery');
		$service->deliver($this->queued());
	}

	public function testBatchDoesNotClaimSuccessWhenDeliveryRecordCreationFails(): void {
		$delivery = $this->createStub(IMessageDeliveryRepository::class);
		$delivery->method('create')->willThrowException(new RuntimeException('storage disconnected'));
		$queue = $this->createStub(IMessageQueueRepository::class);
		$queue->method('claimNext')->willReturn([$this->queued()]);
		$service = new MessageDeliveryService($this->createStub(IMessageTransportRegistry::class),
			$delivery, $queue, $this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('storage disconnected');
		$service->processBatch(1);
	}
}
