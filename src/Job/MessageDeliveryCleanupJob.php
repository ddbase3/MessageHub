<?php declare(strict_types=1);

namespace MessageHub\Job;

use Base3\Settings\Api\ISettingsStore;
use Base3\Worker\Api\IJob;
use MessagingFoundation\Api\IMessageDeliveryRepository;

final class MessageDeliveryCleanupJob implements IJob {

	public function __construct(
		private readonly IMessageDeliveryRepository $deliveryRepository,
		private readonly ISettingsStore $settingsStore
	) {}

	public static function getName(): string { return 'messagedeliverycleanupjob'; }
	public function isActive() { return true; }
	public function getPriority() { return 10; }
	public function go() {
		$settings = $this->settingsStore->get('messaging', 'default', []);
		$retentionDays = (int)($settings['retention_days'] ?? 365);
		$count = $this->deliveryRepository->cleanup($retentionDays);
		return 'Message delivery cleanup removed ' . $count . ' delivery record(s).';
	}
}
