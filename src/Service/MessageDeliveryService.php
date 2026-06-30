<?php declare(strict_types=1);

namespace MessageHub\Service;

use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\MessageDeliveryResult;
use MessagingFoundation\Dto\QueuedMessage;
use MessagingFoundation\Event\MessageFailedEvent;
use MessagingFoundation\Event\MessageSentEvent;

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
