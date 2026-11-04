<?php declare(strict_types=1);

namespace MessageHub\Test\Service;

use Base3\Event\Api\IEventManager;
use MessageHub\Service\MessageQueueService;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\QueuedMessage;
use MessagingFoundation\Event\MessageQueuedEvent;
use PHPUnit\Framework\TestCase;

final class MessageQueueServiceTest extends TestCase {

	public function testEnqueueForwardsArgumentsAndEmitsEvent(): void {
		$message = new Message('alert', 'Subject', 'Body');
		$repository = $this->createMock(IMessageQueueRepository::class);
		$repository->expects(self::once())->method('insert')->with($message, 'log', 5, 123)->willReturn('queue-1');
		$events = $this->createMock(IEventManager::class);
		$events->expects(self::once())->method('fire')->with(self::callback(function(object $event): bool {
			self::assertInstanceOf(MessageQueuedEvent::class, $event);
			self::assertSame('queue-1', $event->getQueueId());
			return true;
		}))->willReturn([]);
		self::assertSame('queue-1', (new MessageQueueService($repository, $events))->enqueue($message, 'log', 5, 123));
	}

	public function testEnqueueUsesDefaultArguments(): void {
		$repository = $this->createMock(IMessageQueueRepository::class);
		$message = new Message('type', 's', 'b');
		$repository->expects(self::once())->method('insert')->with($message, '', 100, null)->willReturn('id');
		$events = $this->createStub(IEventManager::class);
		self::assertSame('id', (new MessageQueueService($repository, $events))->enqueue($message));
	}

	public function testQueueOperationsDelegateWithoutChangingParameters(): void {
		$queued = new QueuedMessage('q1', new Message('t', 's', 'b'), 'log', 'queued', 0, 3);
		$repository = $this->createMock(IMessageQueueRepository::class);
		$repository->expects(self::once())->method('claimNext')->with(4, 60)->willReturn([$queued]);
		$repository->expects(self::once())->method('markSent')->with('q1');
		$repository->expects(self::once())->method('markFailed')->with('q2', 'network', 12);
		$repository->expects(self::once())->method('cancel')->with('q3');
		$service = new MessageQueueService($repository, $this->createStub(IEventManager::class));
		self::assertSame([$queued], $service->claimNext(4, 60));
		$service->markSent('q1');
		$service->markFailed('q2', 'network', 12);
		$service->cancel('q3');
	}
}
