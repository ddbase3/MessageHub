<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\QueuedMessage;
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
