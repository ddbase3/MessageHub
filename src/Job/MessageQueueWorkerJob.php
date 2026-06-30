<?php declare(strict_types=1);

namespace MessageHub\Job;

use Base3\Worker\Api\IJob;
use MessageHub\Service\MessageDeliveryService;

final class MessageQueueWorkerJob implements IJob {

	public function __construct(
		private readonly MessageDeliveryService $deliveryService
	) {}

	public static function getName(): string { return 'messagequeueworkerjob'; }
	public function isActive() { return true; }
	public function getPriority() { return 50; }
	public function go() {
		$count = $this->deliveryService->processBatch(20);
		return 'Message queue worker processed ' . $count . ' message(s).';
	}
}
