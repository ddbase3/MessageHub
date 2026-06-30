<?php declare(strict_types=1);

namespace MessageHub\Service;

use Base3\Event\Api\IEventManager;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageQueueService;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Event\MessageQueuedEvent;

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
