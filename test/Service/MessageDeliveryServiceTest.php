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
use MessagingFoundation\Dto\MessageDeliveryResult;
use MessagingFoundation\Dto\QueuedMessage;
use MessagingFoundation\Event\MessageFailedEvent;
use MessagingFoundation\Event\MessageSentEvent;
use PHPUnit\Framework\TestCase;

final class MessageDeliveryServiceTest extends TestCase {

	private function message(string $transport = 'null'): QueuedMessage {
		return new QueuedMessage('q1', new Message('notice', 'Hi', 'Body'), $transport, 'processing', 0, 3);
	}

	public function testSuccessfulDeliveryIsRecordedMarkedSentAndEmitsSentEvent(): void {
		$queue = $this->message();
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturn(['reason' => 'testing']);
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->expects(self::once())->method('create')->with($queue, $queue->getMessage())->willReturn('d1');
		$deliveries->expects(self::once())->method('finish')->with('d1', self::callback(function(MessageDeliveryResult $result): bool {
			self::assertTrue($result->isSuccess());
			self::assertSame('testing', $result->getDetails()['reason']);
			return true;
		}));
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('markSent')->with('q1');
		$queues->expects(self::never())->method('markFailed');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::callback(function(object $event): bool {
			self::assertInstanceOf(MessageSentEvent::class, $event);
			self::assertSame('q1', $event->getQueueId());
			self::assertSame('d1', $event->getDeliveryId());
			return true;
		}))->willReturn([]);
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('info')->with('Message delivery succeeded', self::arrayHasKey('delivery_id'));
		self::assertSame('d1', (new MessageDeliveryService($registry, $deliveries, $queues, $events, $logger))->deliver($queue));
	}

	public function testMissingTransportRecordsFailureAndRetry(): void {
		$queue = $this->message('missing');
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(null);
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->expects(self::once())->method('create')->willReturn('d2');
		$deliveries->expects(self::once())->method('finish')->with('d2', self::callback(function(MessageDeliveryResult $result): bool {
			self::assertFalse($result->isSuccess());
			self::assertSame('Message transport not found: missing', $result->getMessage());
			return true;
		}));
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('markFailed')->with('q1', 'Message transport not found: missing', 300);
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::isInstanceOf(MessageFailedEvent::class))->willReturn([]);
		self::assertSame('d2', (new MessageDeliveryService($registry, $deliveries, $queues, $events, $this->createStub(ILogger::class)))->deliver($queue));
	}

	public function testDisabledTransportIsRecordedAsFailure(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturn(['enabled' => false]);
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->method('create')->willReturn('d3');
		$deliveries->expects(self::once())->method('finish')->with('d3', self::callback(function(MessageDeliveryResult $result): bool {
			self::assertFalse($result->isSuccess());
			self::assertSame('Null transport is disabled.', $result->getMessage());
			return true;
		}));
		$queueRepository = $this->createMock(IMessageQueueRepository::class);
		$queueRepository->expects(self::once())->method('markFailed')->with('q1', 'Null transport is disabled.', 300);
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('warning')->with('Message delivery failed', self::arrayHasKey('error'));
		$service = new MessageDeliveryService($registry, $deliveries, $queueRepository, $this->createStub(IEventManager::class), $logger);
		self::assertSame('d3', $service->deliver($this->message()));
	}

	public function testProcessingBatchCountsClaimedMessages(): void {
		$repository = $this->createStub(IMessageQueueRepository::class);
		$repository->method('claimNext')->willReturn([]);
		$service = new MessageDeliveryService($this->createStub(IMessageTransportRegistry::class),
			$this->createStub(IMessageDeliveryRepository::class), $repository, $this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		self::assertSame(0, $service->processBatch());
	}


	public function testEmptyQueuedTransportUsesConfiguredDefault(): void {
		$queued = $this->message('');
		$registry = $this->createMock(IMessageTransportRegistry::class);
		$registry->expects(self::once())->method('getDefaultTransportName')->willReturn('null');
		$registry->expects(self::once())->method('getTransport')->with('null')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturn([]);
		$deliveries = $this->createStub(IMessageDeliveryRepository::class);
		$deliveries->method('create')->willReturn('delivery-default');
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('markSent')->with('q1');
		$service = new MessageDeliveryService($registry, $deliveries, $queues,
			$this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		self::assertSame('delivery-default', $service->deliver($queued));
	}

	public function testTransportConfigurationExceptionIsRecordedWithoutLeavingQueueProcessing(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturnCallback(static function(): array {
			throw new \RuntimeException('credential store unavailable');
		});
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->method('create')->willReturn('delivery-error');
		$deliveries->expects(self::once())->method('finish')->with('delivery-error', self::callback(function(MessageDeliveryResult $result): bool {
			self::assertFalse($result->isSuccess());
			self::assertSame('credential store unavailable', $result->getMessage());
			self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
			return true;
		}));
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('markFailed')->with('q1', 'credential store unavailable', 300);
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::isInstanceOf(MessageFailedEvent::class))->willReturn([]);
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('warning')->with('Message delivery failed', self::arrayHasKey('error'));
		self::assertSame('delivery-error', (new MessageDeliveryService($registry, $deliveries, $queues, $events, $logger))->deliver($this->message()));
	}

	public function testBatchProcessesEveryClaimedMessageAndMarksEachSent(): void {
		$one = $this->message('null');
		$two = new QueuedMessage('q2', new Message('notice', 'Two', 'Body'), 'null', 'processing', 1, 3);
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::once())->method('claimNext')->with(2, 300)->willReturn([$one, $two]);
		$queue->expects(self::exactly(2))->method('markSent');
		$queue->expects(self::never())->method('markFailed');
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturn([]);
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->expects(self::exactly(2))->method('create')->willReturn('delivery-id');
		$deliveries->expects(self::exactly(2))->method('finish');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::exactly(2))->method('fire')->with(self::isInstanceOf(MessageSentEvent::class))->willReturn([]);
		$service = new MessageDeliveryService($registry, $deliveries, $queue, $events, $this->createStub(ILogger::class));
		self::assertSame(2, $service->processBatch(2));
	}

}
