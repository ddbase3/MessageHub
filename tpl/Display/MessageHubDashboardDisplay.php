<?php $stats = $this->_['stats']; ?>
<style>
	.messagehub-dashboard { max-width: 1200px; }
	.messagehub-dashboard h1 { margin: 0 0 8px 0; font-size: 24px; font-weight: 600; }
	.messagehub-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 16px; }
	.messagehub-dashboard-card { padding: 14px 16px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; }
	.messagehub-dashboard-value { font-size: 28px; font-weight: 700; }
	.messagehub-dashboard-label { color: #666; font-size: 13px; }
</style>
<div class="messagehub-dashboard">
	<h1>MessageHub</h1>
	<p>Queue-first messaging for BASE3. Transports are discoverable and replaceable.</p>
	<div class="messagehub-dashboard-grid">
		<?php foreach($stats as $label => $value): ?>
			<div class="messagehub-dashboard-card"><div class="messagehub-dashboard-value"><?php echo (int)$value; ?></div><div class="messagehub-dashboard-label"><?php echo htmlspecialchars((string)$label, ENT_QUOTES); ?></div></div>
		<?php endforeach; ?>
	</div>
</div>
