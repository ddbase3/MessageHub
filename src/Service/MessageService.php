<?php declare(strict_types=1);

namespace MessageHub\Service;

use MessagingFoundation\Api\IMessageQueueService;
use MessagingFoundation\Api\IMessageService;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\QueuedMessage;

final class MessageService implements IMessageService {

	public function __construct(
		private readonly IMessageQueueService $queueService,
		private readonly MessageDeliveryService $deliveryService,
		private readonly IMessageTransportRegistry $transportRegistry
	) {}

	public function enqueue(Message $message, string $transportName = '', int $priority = 100, ?int $notBefore = null): string {
		return $this->queueService->enqueue($message, $transportName, $priority, $notBefore);
	}

	public function sendNow(Message $message, string $transportName = ''): string {
		$transportName = $transportName !== '' ? $transportName : $this->transportRegistry->getDefaultTransportName();
		$queueId = $this->queueService->enqueue($message, $transportName, 1, time());
		$queuedMessage = new QueuedMessage($queueId, $message, $transportName, 'processing', 0, 1);
		$this->deliveryService->deliver($queuedMessage);
		return $queueId;
	}
}
