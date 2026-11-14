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
use Base3\MessageHub\Service\MessageQueueService;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\QueuedMessage;
use Base3\MessagingFoundation\Event\MessageQueuedEvent;
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
