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
use MessagingFoundation\Dto\MessageDeliveryResult;
use MessagingFoundation\Dto\QueuedMessage;
use MessagingFoundation\Event\MessageFailedEvent;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageServiceBoundaryTest extends TestCase {

	private function delivery(
		IMessageTransportRegistry $registry,
		?IMessageDeliveryRepository $deliveries = null,
		?IMessageQueueRepository $queue = null,
		?IEventManager $events = null
	): MessageDeliveryService {
		return new MessageDeliveryService(
			$registry,
			$deliveries ?? $this->createStub(IMessageDeliveryRepository::class),
			$queue ?? $this->createStub(IMessageQueueRepository::class),
			$events ?? $this->createStub(IEventManager::class),
			$this->createStub(ILogger::class)
		);
	}

	public function testEnqueueForwardsDefaultArgumentsWithoutCallingDelivery(): void {
		$message = new Message('notice', 'Subject', 'Body');
		$queue = $this->createMock(IMessageQueueService::class);
		$queue->expects(self::once())->method('enqueue')->with($message, '', 100, null)->willReturn('queued-1');
		$registry = $this->createMock(IMessageTransportRegistry::class);
		$registry->expects(self::never())->method('getTransport');
		$service = new MessageService($queue, $this->delivery($registry), $registry);
		self::assertSame('queued-1', $service->enqueue($message));
	}

	public function testSendNowWithExplicitTransportDoesNotResolveDefault(): void {
		$message = new Message('notice', 'Subject', 'Body');
		$registry = $this->createMock(IMessageTransportRegistry::class);
		$registry->expects(self::never())->method('getDefaultTransportName');
		$registry->expects(self::once())->method('getTransport')->with('null')->willReturn(new NullMessageTransport());
		$registry->method('getTransportSettings')->willReturn([]);
		$queue = $this->createMock(IMessageQueueService::class);
		$queue->expects(self::once())->method('enqueue')->with($message, 'null', 1, self::callback(
			static fn(?int $at): bool => $at !== null && abs(time() - $at) <= 5
		))->willReturn('queued-explicit');
		$deliveries = $this->createMock(IMessageDeliveryRepository::class);
		$deliveries->expects(self::once())->method('create')->with(
			self::callback(static fn(QueuedMessage $item): bool =>
				$item->getId() === 'queued-explicit'
				&& $item->getTransportName() === 'null'
				&& $item->getMaxAttempts() === 1), $message
		)->willReturn('delivery-explicit');
		$deliveries->expects(self::once())->method('finish')->with('delivery-explicit',
			self::callback(static fn(MessageDeliveryResult $result): bool => $result->isSuccess()));
		$service = new MessageService($queue, $this->delivery($registry, $deliveries), $registry);
		self::assertSame('queued-explicit', $service->sendNow($message, 'null'));
	}

	public function testSendNowWithUnknownTransportStillRecordsFailureWithoutClaimingSuccess(): void {
		$message = new Message('notice', 'Subject', 'Body');
		$queue = $this->createStub(IMessageQueueService::class);
		$queue->method('enqueue')->willReturn('queued-missing');
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(null);
		$deliveries = $this->createStub(IMessageDeliveryRepository::class);
		$deliveries->method('create')->willReturn('delivery-missing');
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('markFailed')->with('queued-missing', 'Message transport not found: unknown', 300);
		$queues->expects(self::never())->method('markSent');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::isInstanceOf(MessageFailedEvent::class))->willReturn([]);
		$service = new MessageService($queue, $this->delivery($registry, $deliveries, $queues, $events), $registry);
		self::assertSame('queued-missing', $service->sendNow($message, 'unknown'));
	}

	public function testEnqueueErrorIsPropagatedWithoutDispatchingDelivery(): void {
		$queue = $this->createStub(IMessageQueueService::class);
		$queue->method('enqueue')->willThrowException(new RuntimeException('queue unavailable'));
		$registry = $this->createMock(IMessageTransportRegistry::class);
		$registry->expects(self::never())->method('getTransport');
		$service = new MessageService($queue, $this->delivery($registry), $registry);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('queue unavailable');
		$service->enqueue(new Message('notice', 'Subject', 'Body'));
	}

	public function testSendNowCannotReportSuccessfulCompletionIfDeliveryRecordFails(): void {
		$queue = $this->createStub(IMessageQueueService::class);
		$queue->method('enqueue')->willReturn('queued-1');
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturn(new NullMessageTransport());
		$deliveries = $this->createStub(IMessageDeliveryRepository::class);
		$deliveries->method('create')->willThrowException(new RuntimeException('delivery store unavailable'));
		$service = new MessageService($queue, $this->delivery($registry, $deliveries), $registry);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('delivery store unavailable');
		$service->sendNow(new Message('notice', 'Subject', 'Body'), 'null');
	}
}
