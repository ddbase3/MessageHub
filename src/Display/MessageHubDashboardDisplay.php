<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Database\Api\IDatabase;

final class MessageHubDashboardDisplay implements IDisplay {

	public function __construct(
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly IDatabase $database
	) {}

	public static function getName(): string { return 'messagehubdashboarddisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'MessageHub dashboard.'; }
	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageHubDashboardDisplay.php');
		$this->view->assign('stats', $this->loadStats());
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		return $this->view->loadTemplate();
	}

	private function loadStats(): array {
		$this->database->connect();
		$stats = ['queued' => 0, 'retry_wait' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'deliveries' => 0];
		try {
			$rows = $this->database->multiQuery('SELECT status, COUNT(*) AS cnt FROM base3_messaging_queue GROUP BY status');
			foreach($rows as $row) { $stats[(string)$row['status']] = (int)$row['cnt']; }
			$stats['deliveries'] = (int)($this->database->scalarQuery('SELECT COUNT(*) FROM base3_messaging_deliveries') ?? 0);
		} catch(\Throwable $exception) {}
		return $stats;
	}
}
