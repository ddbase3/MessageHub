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
use Base3\MessageHub\Service\MessageService;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageQueueService;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\QueuedMessage;
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
