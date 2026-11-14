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
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageQueueService;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Event\MessageQueuedEvent;

final class MessageQueueService implements IMessageQueueService {

	public function __construct(
		private readonly IMessageQueueRepository $queueRepository,
		private readonly IEventManager $eventManager
	) {}

	public function enqueue(Message $message, string $transportName = '', int $priority = 100, ?int $notBefore = null): string {
		$queueId = $this->queueRepository->insert($message, $transportName, $priority, $notBefore);
		$this->eventManager->fire(new MessageQueuedEvent($queueId));
		return $queueId;
	}

	public function claimNext(int $limit = 20, int $lockSeconds = 300): array {
		return $this->queueRepository->claimNext($limit, $lockSeconds);
	}

	public function markSent(string $queueId): void {
		$this->queueRepository->markSent($queueId);
	}

	public function markFailed(string $queueId, string $errorMessage, int $retryDelaySeconds = 300): void {
		$this->queueRepository->markFailed($queueId, $errorMessage, $retryDelaySeconds);
	}

	public function cancel(string $queueId): void {
		$this->queueRepository->cancel($queueId);
	}
}
