<?php
$serviceUrl = (string) $this->_['service'];
$providers = is_array($this->_['providers'] ?? null) ? $this->_['providers'] : [];
?>
<style>
	.message-type-sync-shell {
		max-width: 1400px;
	}

	.message-type-sync-shell h1 {
		margin: 0 0 8px 0;
		font-size: 24px;
		line-height: 1.2;
		font-weight: 600;
	}

	.message-type-sync-shell p {
		margin: 0 0 16px 0;
		max-width: 1000px;
		color: #555;
		line-height: 1.45;
	}

	.message-type-sync-panel,
	.message-type-sync-result {
		margin: 12px 0;
		padding: 12px;
		border: 1px solid #e2e2e2;
		border-radius: 8px;
		background: #fff;
	}

	.message-type-sync-actions {
		display: flex;
		gap: 8px;
		align-items: center;
		flex-wrap: wrap;
	}

	.message-type-sync-button {
		appearance: none;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		color: #222;
		cursor: pointer;
		font: inherit;
		font-size: 13px;
		line-height: 1.3;
		min-height: 28px;
		padding: 4px 10px;
		white-space: nowrap;
	}

	.message-type-sync-button-primary {
		background: #2f5d91;
		border-color: #2f5d91;
		color: #fff;
	}

	.message-type-sync-table {
		width: 100%;
		border-collapse: collapse;
	}

	.message-type-sync-table th,
	.message-type-sync-table td {
		padding: 6px 8px;
		border-bottom: 1px solid #eee;
		font-size: 13px;
		text-align: left;
		vertical-align: top;
	}

	.message-type-sync-result pre {
		margin: 0;
		white-space: pre-wrap;
	}
</style>
<div class="message-type-sync-shell">
	<h1>Message type synchronization</h1>
	<p>Synchronizes discoverable <code>IMessageTypeProvider</code> classes into MessageHub templates and default variants. Existing templates and variants are not overwritten.</p>

	<div class="message-type-sync-panel message-type-sync-actions">
		<label>Language <input id="message-type-sync-language" type="text" value="en" maxlength="12" /></label>
		<button type="button" class="message-type-sync-button message-type-sync-button-primary" id="message-type-sync-all">Sync all providers</button>
		<button type="button" class="message-type-sync-button" id="message-type-sync-refresh">Refresh list</button>
	</div>

	<div class="message-type-sync-panel">
		<table class="message-type-sync-table">
			<thead>
				<tr>
					<th>Name</th>
					<th>Label</th>
					<th>Installed</th>
					<th>Template ID</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody id="message-type-sync-provider-body">
				<?php foreach($providers as $provider): ?>
					<tr>
						<td><code><?php echo htmlspecialchars((string) ($provider['name'] ?? ''), ENT_QUOTES); ?></code></td>
						<td><?php echo htmlspecialchars((string) ($provider['label'] ?? ''), ENT_QUOTES); ?></td>
						<td><?php echo !empty($provider['installed']) ? 'yes' : 'no'; ?></td>
						<td><code><?php echo htmlspecialchars((string) ($provider['template_id'] ?? ''), ENT_QUOTES); ?></code></td>
						<td><button type="button" class="message-type-sync-button" data-sync-one="<?php echo htmlspecialchars((string) ($provider['name'] ?? ''), ENT_QUOTES); ?>">Sync</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="message-type-sync-result"><pre id="message-type-sync-result">Ready.</pre></div>
</div>
<script>
(() => {
	const serviceUrl = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const resultElement = document.getElementById('message-type-sync-result');
	const bodyElement = document.getElementById('message-type-sync-provider-body');
	const languageElement = document.getElementById('message-type-sync-language');

	async function postJson(payload) {
		const response = await fetch(serviceUrl, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(payload)
		});

		return await response.json();
	}

	function show(data) {
		resultElement.textContent = JSON.stringify(data, null, 2);
	}

	function language() {
		return languageElement.value || 'en';
	}

	function renderProviders(providers) {
		bodyElement.innerHTML = '';
		for(const provider of providers || []) {
			const row = document.createElement('tr');
			row.innerHTML = `
				<td><code></code></td>
				<td></td>
				<td></td>
				<td><code></code></td>
				<td><button type="button" class="message-type-sync-button">Sync</button></td>
			`;
			row.children[0].querySelector('code').textContent = provider.name || '';
			row.children[1].textContent = provider.label || '';
			row.children[2].textContent = provider.installed ? 'yes' : 'no';
			row.children[3].querySelector('code').textContent = provider.template_id || '';
			row.children[4].querySelector('button').addEventListener('click', () => syncOne(provider.name || ''));
			bodyElement.appendChild(row);
		}
	}

	async function refresh() {
		const data = await postJson({mode: 'providers'});
		show(data);
		renderProviders(data.providers || []);
	}

	async function syncAll() {
		const data = await postJson({mode: 'sync-all', language: language()});
		show(data);
		await refresh();
	}

	async function syncOne(typeName) {
		const data = await postJson({mode: 'sync-one', type_name: typeName, language: language()});
		show(data);
		await refresh();
	}

	document.getElementById('message-type-sync-all').addEventListener('click', syncAll);
	document.getElementById('message-type-sync-refresh').addEventListener('click', refresh);
	document.querySelectorAll('[data-sync-one]').forEach(button => {
		button.addEventListener('click', () => syncOne(button.getAttribute('data-sync-one') || ''));
	});
})();
</script>
