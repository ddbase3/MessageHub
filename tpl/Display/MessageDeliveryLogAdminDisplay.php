<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$serviceUrl = (string) $this->_['service'];
$transportFilterOptions = is_array($this->_['transport_filter_options'] ?? null) ? $this->_['transport_filter_options'] : [];
$typeFilterOptions = is_array($this->_['type_filter_options'] ?? null) ? $this->_['type_filter_options'] : [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<style>

	.messagehub-shell { max-width: 1700px; }
	.messagehub-shell h1 { margin: 0 0 8px 0; font-size: 24px; line-height: 1.2; font-weight: 600; }
	.messagehub-shell p { margin: 0 0 12px 0; color: #555; max-width: 1200px; line-height: 1.45; }
	.messagehub-grid .messagehub-panel { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; min-width: 0; width: 100%; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; overflow-x: auto; }
	.messagehub-grid .messagehub-panel--filters { align-items: center; flex-wrap: nowrap; overflow-x: auto; }
	.messagehub-grid .messagehub-panel > * { flex: 0 0 auto; }
	.messagehub-main { border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 4px 0; }
	.messagehub-grid .mg-control-group { flex-direction: row; align-items: center; gap: 6px; min-width: auto; }
	.messagehub-grid .mg-label { white-space: nowrap; color: #666; font-size: 12px; }
	.messagehub-grid .mg-inline-buttons, .messagehub-grid .mg-filters { display: inline-flex; align-items: center; flex-wrap: nowrap; gap: 8px; }
	.messagehub-grid .mg-input, .messagehub-grid .mg-select, .messagehub-grid .mg-button { min-height: 28px; font-size: 13px; }
	.messagehub-grid input[type="search"].mg-input { width: 340px; }
	.messagehub-grid .mg-select { width: auto; min-width: 140px; }
	.messagehub-grid .mg-table-scroll { height: 580px; overflow: auto; padding-bottom: 4px; }
	.messagehub-grid .mg-table thead th { position: sticky; top: 0; z-index: 12; background: #fff; }
	.messagehub-grid .mg-table th, .messagehub-grid .mg-table td { padding: 6px 8px; font-size: 13px; vertical-align: top; }
	.messagehub-top-actions { display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto; }
	.messagehub-button { appearance: none; border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; color: #222; cursor: pointer; font: inherit; font-size: 13px; line-height: 1.3; min-height: 28px; padding: 4px 10px; white-space: nowrap; }
	.messagehub-button:hover { background: #f5f5f5; }
	.messagehub-button-primary { background: #2f5d91; border-color: #2f5d91; color: #fff; }
	.messagehub-button-primary:hover { background: #284f7c; }
	.messagehub-button-danger { border-color: #c8a2a2; color: #8a1f1f; }
	.messagehub-button-danger:hover { background: #fff0f0; }
	.messagehub-output { margin-top: 12px; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; color: #555; font-size: 13px; }
	.messagehub-output strong { color: #222; }
	.messagehub-cell-stack { display: grid; gap: 2px; min-width: 0; }
	.messagehub-cell-main { font-weight: 600; color: #222; min-width: 0; overflow-wrap: anywhere; }
	.messagehub-cell-sub { font-size: 12px; color: #666; min-width: 0; overflow-wrap: anywhere; }
	.messagehub-value { margin: 0; max-height: 120px; overflow: auto; color: #333; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
	.messagehub-pill { display: inline-flex; align-items: center; padding: 1px 6px; border: 1px solid #d6d6d6; border-radius: 999px; background: #fafafa; font-size: 11px; line-height: 1.35; color: #444; white-space: nowrap; }
	.messagehub-pill-sent, .messagehub-pill-enabled { background: #eef7ee; border-color: #bddfbd; color: #226622; }
	.messagehub-pill-failed, .messagehub-pill-disabled { background: #fff0f0; border-color: #e4b9b9; color: #8a1f1f; }
	.messagehub-pill-processing { background: #edf6ff; border-color: #c3dff5; color: #284f7c; }
</style>
<div class="messagehub-shell">
	<h1>Message delivery log</h1>
	<p>Delivery attempts and transport responses.</p>
	<div class="messagehub-grid">
		<div id="messagehub-delivery-grid"></div>
		<div id="messagehub-delivery-output" class="messagehub-output"></div>
	</div>
</div>
<script type="module">
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const TRANSPORT_FILTER_OPTIONS = [{ value: '', label: 'All transports' }, ...<?php echo json_encode($transportFilterOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const TYPE_FILTER_OPTIONS = [{ value: '', label: 'All types' }, ...<?php echo json_encode($typeFilterOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	let grid = null;
	function getText(value, placeholder = '-') { if(value === null || value === undefined || value === '') { return placeholder; } return String(value); }
	function setLog(message) { const element = document.querySelector('#messagehub-delivery-output'); if(!element) { return; } element.textContent = getText(message, ''); }
	function statusPill(value) { const pill = document.createElement('span'); pill.className = 'messagehub-pill messagehub-pill-' + getText(value, '').replace(/[^a-z0-9_-]/gi, '-'); pill.textContent = getText(value); return pill; }
	function buildFilterPayload(filters) { const result = {}; Object.entries(filters || {}).forEach(([key, value]) => { if(value !== '' && value !== null && value !== undefined) { result[key] = value; } }); return result; }
	async function postJson(payload) { const response = await fetch(ENDPOINT_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); return response.json(); }

	async function refreshGrid() {
		if(!grid) {
			return;
		}

		const commands = ['reload', 'refresh', 'reloadData', 'refreshData'];

		if(typeof grid.execute === 'function') {
			for(const commandName of commands) {
				try {
					const result = grid.execute(commandName);

					if(result && typeof result.then === 'function') {
						await result;
					}

					return;
				}
				catch(error) {}
			}
		}

		for(const methodName of commands) {
			if(typeof grid[methodName] === 'function') {
				const result = grid[methodName]();

				if(result && typeof result.then === 'function') {
					await result;
				}

				return;
			}
		}

		window.location.reload();
	}

	const { AjaxAdapter, ModularGrid, SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	const layout = { type: 'stack', children: [{ type: 'zone', key: 'topLine1', className: 'messagehub-panel messagehub-panel--main' }, { type: 'zone', key: 'topLine2', className: 'messagehub-panel messagehub-panel--filters' }, { type: 'view', key: 'main', className: 'messagehub-main' }, { type: 'zone', key: 'statusZone', className: 'messagehub-panel messagehub-panel--status' }] };
	const adapter = new AjaxAdapter({ url: ENDPOINT_URL, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) { const state = grid ? grid.getState() : {}; const sort = request.sortKey ? [{ key: request.sortKey, dir: request.sortDirection || 'asc', type: 'string' }] : []; return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || 50, search: request.search || '', sort, filters: buildFilterPayload(state.filters || {}) }; } });
	grid = new ModularGrid('#messagehub-delivery-grid', { layout, adapter, dataMode: 'server', server: { searchDebounceMs: 220, watchStateKeys: ['query', 'filters'] }, features: { paging: false }, plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin, InfiniteScrollPlugin], pluginOptions: { search: { zone: 'topLine1', order: 10, label: 'Search', placeholder: 'Search id, queue, type, subject or error' }, filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: 'Clear filters', fields: [{ key: 'status', label: 'Status', type: 'select', options: [{ value: '', label: 'All statuses' }, { value: 'processing', label: 'Processing' }, { value: 'sent', label: 'Sent' }, { value: 'failed', label: 'Failed' }] }, { key: 'transport_name_exact', label: 'Transport', type: 'select', options: TRANSPORT_FILTER_OPTIONS }, { key: 'type_name_exact', label: 'Type', type: 'select', options: TYPE_FILTER_OPTIONS }] }, reset: { zone: 'topLine1', order: 20, label: 'Reset', sections: ['query', 'filters', 'columns'] }, sessionStorage: { key: 'messagehub-delivery-grid', sections: ['query', 'filters', 'columns'] }, info: { zone: 'statusZone', order: 10, displayMode: 'loaded' }, infiniteScroll: { threshold: 180, pageSize: 50, containerSelector: '.mg-table-scroll' }, rowActions: { items: [{ key: 'detail', label: 'Show detail', async onClick(context) { const response = await postJson({ mode: 'detail', id: context.row.id }); setLog(JSON.stringify(response.detail || {}, null, 2)); } }] } }, columns: [{ key: 'created_at', label: 'Created', width: 170 }, { key: 'status', label: 'Status', width: 120, render: statusPill }, { key: 'type_name', label: 'Type', width: 220 }, { key: 'subject', label: 'Subject', width: 360 }, { key: 'transport_name', label: 'Transport', width: 140 }, { key: 'attempts', label: 'Attempts', width: 90 }, { key: 'error_message', label: 'Error', width: 380 }, { key: 'id', label: 'ID', width: 260, visible: false }] });
	await grid.init();
	setLog('Deliveries loaded.');
</script>
