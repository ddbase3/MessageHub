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

use Base3\MessagingFoundation\Api\IMessageQueueService;
use Base3\MessagingFoundation\Api\IMessageService;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\QueuedMessage;

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
