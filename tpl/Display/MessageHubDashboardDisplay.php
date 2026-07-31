<?php
$stats = is_array($this->_['stats'] ?? null) ? $this->_['stats'] : [];
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn(string $key, string $fallback): string => trim((string)($translations[$key] ?? '')) !== ''
	? (string)$translations[$key]
	: $fallback;
$statLabels = [
	'queued' => $t('status_queued', 'Queued'),
	'retry_wait' => $t('status_retry_wait', 'Waiting to retry'),
	'processing' => $t('status_processing', 'Processing'),
	'sent' => $t('status_sent', 'Sent'),
	'failed' => $t('status_failed', 'Failed'),
	'deliveries' => $t('status_deliveries', 'Deliveries')
];
?>
<style>
	.messagehub-dashboard { max-width: 1200px; }
	.messagehub-dashboard h1 { margin: 0 0 8px 0; font-size: 24px; font-weight: 600; }
	.messagehub-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 16px; }
	.messagehub-dashboard-card { padding: 14px 16px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; }
	.messagehub-dashboard-value { font-size: 28px; font-weight: 700; }
	.messagehub-dashboard-label { color: #666; font-size: 13px; }
</style>
<div class="messagehub-dashboard">
	<h1><?php echo $e($t('title', 'MessageHub')); ?></h1>
	<p><?php echo $e($t('lead', 'Queue-first messaging for BASE3. Transports are discoverable and replaceable.')); ?></p>
	<div class="messagehub-dashboard-grid">
		<?php foreach($stats as $label => $value): ?>
			<div class="messagehub-dashboard-card"><div class="messagehub-dashboard-value"><?php echo (int)$value; ?></div><div class="messagehub-dashboard-label"><?php echo $e($statLabels[(string)$label] ?? (string)$label); ?></div></div>
		<?php endforeach; ?>
	</div>
</div>
