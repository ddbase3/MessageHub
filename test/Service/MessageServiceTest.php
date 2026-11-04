<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Service\MessageService;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageQueueService;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\QueuedMessage;
use PHPUnit\Framework\TestCase;

final class MessageServiceTest extends TestCase {

	public function testEnqueueDelegatesPriorityAndTime(): void {
		$message = new Message('type', 'subject', 'body');
		$queue = $this->createMock(IMessageQueueService::class);
		$queue->expects(self::once())->method('enqueue')->with($message, 'smtp', 15, 123)->willReturn('id-12');
		$service = $this->service($queue, $this->createStub(IMessageTransportRegistry::class));
		self::assertSame('id-12', $service->enqueue($message, 'smtp', 15, 123));
	}

	public function testSendNowUsesDefaultTransportAndQueuesHighPriority(): void {
		$message = new Message('alert', 'Hi', 'Text');
		$queue = $this->createMock(IMessageQueueService::class);
		$queue->expects(self::once())->method('enqueue')->with($message, 'null', 1,
			self::callback(static fn(?int $timestamp): bool => $timestamp !== null && abs(time() - $timestamp) <= 3))->willReturn('queue-19');
		$transport = $this->createStub(IMessageTransportRegistry::class);
		$transport->method('getDefaultTransportName')->willReturn('null');
		$transport->method('getTransport')->willReturn(new NullMessageTransport());
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->expects(self::once())->method('create')->with(self::callback(function(QueuedMessage $queued): bool {
			self::assertSame('queue-19', $queued->getId());
			self::assertSame('processing', $queued->getStatus());
			self::assertSame(1, $queued->getMaxAttempts());
			return true;
		}), $message)->willReturn('delivery-19');
		$deliveries->expects(self::once())->method('finish');
		$deliveryService = new MessageDeliveryService($transport, $deliveries, $this->createStub(IMessageQueueRepository::class),
			$this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		$service = new MessageService($queue, $deliveryService, $transport);
		self::assertSame('queue-19', $service->sendNow($message));
	}

	private function service(IMessageQueueService $queue, IMessageTransportRegistry $registry): MessageService {
		$delivery = new MessageDeliveryService($registry, $this->createStub(IMessageDeliveryRepository::class),
			$this->createStub(IMessageQueueRepository::class), $this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		return new MessageService($queue, $delivery, $registry);
	}
}
