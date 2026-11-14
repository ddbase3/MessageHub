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

namespace Base3\MessageHub\Service;

use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Dto\MessageDeliveryResult;
use Base3\MessagingFoundation\Dto\QueuedMessage;
use Base3\MessagingFoundation\Event\MessageFailedEvent;
use Base3\MessagingFoundation\Event\MessageSentEvent;

final class MessageDeliveryService {

	public function __construct(
		private readonly IMessageTransportRegistry $transportRegistry,
		private readonly IMessageDeliveryRepository $deliveryRepository,
		private readonly IMessageQueueRepository $queueRepository,
		private readonly IEventManager $eventManager,
		private readonly ILogger $logger
	) {}

	public function deliver(QueuedMessage $queuedMessage): string {
		$message = $queuedMessage->getMessage();
		$deliveryId = $this->deliveryRepository->create($queuedMessage, $message);
		$transportName = $queuedMessage->getTransportName() !== '' ? $queuedMessage->getTransportName() : $this->transportRegistry->getDefaultTransportName();
		$transport = $this->transportRegistry->getTransport($transportName);

		if($transport === null) {
			$result = new MessageDeliveryResult(false, 'Message transport not found: ' . $transportName);
			$this->deliveryRepository->finish($deliveryId, $result);
			$this->queueRepository->markFailed($queuedMessage->getId(), $result->getMessage(), 300);
			$this->eventManager->fire(new MessageFailedEvent($queuedMessage->getId(), $deliveryId, $result->getMessage()));
			return $deliveryId;
		}

		try {
			$settings = $this->transportRegistry->getTransportSettings($transport::getName());
			$result = $transport->send($message, $settings);
		} catch(\Throwable $exception) {
			$result = new MessageDeliveryResult(false, $exception->getMessage(), '', [
				'exception' => get_class($exception)
			]);
		}

		$this->deliveryRepository->finish($deliveryId, $result);

		if($result->isSuccess()) {
			$this->queueRepository->markSent($queuedMessage->getId());
			$this->eventManager->fire(new MessageSentEvent($queuedMessage->getId(), $deliveryId));
			$this->logger->info('Message delivery succeeded', ['scope' => 'messagehub', 'queue_id' => $queuedMessage->getId(), 'delivery_id' => $deliveryId]);
			return $deliveryId;
		}

		$this->queueRepository->markFailed($queuedMessage->getId(), $result->getMessage(), 300);
		$this->eventManager->fire(new MessageFailedEvent($queuedMessage->getId(), $deliveryId, $result->getMessage()));
		$this->logger->warning('Message delivery failed', ['scope' => 'messagehub', 'queue_id' => $queuedMessage->getId(), 'delivery_id' => $deliveryId, 'error' => $result->getMessage()]);

		return $deliveryId;
	}

	public function processBatch(int $limit = 20): int {
		$count = 0;
		foreach($this->queueRepository->claimNext($limit, 300) as $queuedMessage) {
			$this->deliver($queuedMessage);
			$count++;
		}

		return $count;
	}
}
