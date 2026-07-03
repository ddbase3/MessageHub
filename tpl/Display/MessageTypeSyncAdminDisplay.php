<?php
$providers = is_array($this->_['providers'] ?? null) ? $this->_['providers'] : [];
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$serviceUrl = (string) $this->_['service'];
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

	.message-type-sync-result pre { margin: 0; white-space: pre-wrap; }
</style>
<div class="messagehub-shell">
	<h1>Message type synchronization</h1>
	<p>Synchronizes discoverable <code>IMessageTypeProvider</code> classes into MessageHub templates and default variants. Existing templates and variants are not overwritten.</p>
	<div class="messagehub-grid">
		<div id="message-type-sync-grid"></div>
		<div class="messagehub-output message-type-sync-result"><pre id="message-type-sync-result">Ready.</pre></div>
	</div>
</div>
<script type="module">
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const INITIAL_PROVIDERS = <?php echo json_encode(array_values($providers), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const BATCH_SIZE = 50;
	let grid = null;
	let currentProviders = Array.isArray(INITIAL_PROVIDERS) ? INITIAL_PROVIDERS : [];
	let languageValue = 'en';

	function getText(value, placeholder = '-') {
		if(value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function show(data) {
		const resultElement = document.getElementById('message-type-sync-result');

		if(resultElement) {
			resultElement.textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
		}
	}

	function buildFilterPayload(filters) {
		const result = {};
		Object.entries(filters || {}).forEach(([key, value]) => {
			if(value === '' || value === null || value === undefined) {
				return;
			}

			result[key] = value;
		});
		return result;
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		});

		if(!response.ok) {
			throw new Error('Request failed with status ' + String(response.status));
		}

		return response.json();
	}

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

	function filterProviders(request) {
		const state = grid ? grid.getState() : {};
		const filters = buildFilterPayload(state.filters || {});
		const search = String(request.search || '').toLowerCase();
		let rows = currentProviders.slice();

		if(search !== '') {
			rows = rows.filter((provider) => {
				const haystack = [provider.name || '', provider.label || '', provider.template_id || ''].join(' ').toLowerCase();
				return haystack.indexOf(search) !== -1;
			});
		}

		if(filters.installed !== undefined) {
			rows = rows.filter((provider) => (provider.installed ? '1' : '0') === String(filters.installed));
		}

		return rows;
	}

	async function reloadProviders() {
		const data = await postJson({ mode: 'providers' });
		currentProviders = Array.isArray(data.providers) ? data.providers : [];
		show(data);
		await refreshGrid();
	}

	async function syncAll() {
		const data = await postJson({ mode: 'sync-all', language: languageValue || 'en' });
		show(data);
		await reloadProviders();
	}

	async function syncOne(typeName) {
		const data = await postJson({ mode: 'sync-one', type_name: typeName, language: languageValue || 'en' });
		show(data);
		await reloadProviders();
	}

	function createButton(className, text) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = className;
		button.textContent = text;
		return button;
	}

	function createSyncActionsPlugin() {
		return {
			name: 'messageTypeSyncActions',
			layoutContributions() {
				return [{
					zone: 'topLine1',
					order: 5,
					render() {
						const wrapper = document.createElement('div');
						wrapper.className = 'messagehub-top-actions';

						const languageGroup = document.createElement('label');
						languageGroup.className = 'mg-control-group';
						const languageLabel = document.createElement('span');
						languageLabel.className = 'mg-label';
						languageLabel.textContent = 'Language';
						const languageInput = document.createElement('input');
						languageInput.type = 'text';
						languageInput.className = 'mg-input';
						languageInput.maxLength = 12;
						languageInput.value = languageValue;
						languageInput.style.width = '80px';
						languageInput.addEventListener('input', () => { languageValue = languageInput.value || 'en'; });
						languageGroup.appendChild(languageLabel);
						languageGroup.appendChild(languageInput);

						const syncAllButton = createButton('messagehub-button messagehub-button-primary', 'Sync all providers');
						syncAllButton.addEventListener('click', () => { void syncAll(); });

						const refreshButton = createButton('messagehub-button', 'Refresh list');
						refreshButton.addEventListener('click', () => { void reloadProviders(); });

						wrapper.appendChild(languageGroup);
						wrapper.appendChild(syncAllButton);
						wrapper.appendChild(refreshButton);

						return wrapper;
					}
				}];
			}
		};
	}

	function renderInstalled(value, row) {
		const installed = row && row.installed ? true : false;
		const pill = document.createElement('span');
		pill.className = 'messagehub-pill ' + (installed ? 'messagehub-pill-enabled' : 'messagehub-pill-disabled');
		pill.textContent = installed ? 'yes' : 'no';
		return pill;
	}

	function renderName(value) {
		const code = document.createElement('code');
		code.textContent = getText(value, '');
		return code;
	}

	function renderSyncButton(value, row) {
		const button = createButton('messagehub-button', 'Sync');
		button.addEventListener('click', () => { void syncOne(row && row.name ? row.name : ''); });
		return button;
	}

	const modularGridModule = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	const { ModularGrid, SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, ResetPlugin, SessionStoragePlugin } = modularGridModule;
	const providerAdapter = {
		async load(request) {
			const rows = filterProviders(request);
			const page = Math.max(1, Number(request.page) || 1);
			const pageSize = Math.max(1, Number(request.pageSize) || BATCH_SIZE);
			const offset = Math.max(0, (page - 1) * pageSize);

			return {
				rows: rows.slice(offset, offset + pageSize),
				total: rows.length
			};
		}
	};
	const layout = { type: 'stack', children: [
		{ type: 'zone', key: 'topLine1', className: 'messagehub-panel messagehub-panel--main' },
		{ type: 'zone', key: 'topLine2', className: 'messagehub-panel messagehub-panel--filters' },
		{ type: 'view', key: 'main', className: 'messagehub-main' },
		{ type: 'zone', key: 'statusZone', className: 'messagehub-panel messagehub-panel--status' }
	] };
	grid = new ModularGrid('#message-type-sync-grid', {
		layout,
		adapter: providerAdapter,
		dataMode: 'server',
		server: {
			searchDebounceMs: 220,
			watchStateKeys: ['query', 'filters']
		},
		features: {
			paging: false
		},
		pageSize: BATCH_SIZE,
		plugins: [createSyncActionsPlugin(), SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, ResetPlugin, SessionStoragePlugin, InfiniteScrollPlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: 'Search', placeholder: 'Search name, label or template' },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: 'Clear filters', fields: [
				{ key: 'installed', label: 'Installed', type: 'select', options: [{ value: '', label: 'All providers' }, { value: '1', label: 'Installed' }, { value: '0', label: 'Not installed' }] }
			] },
			reset: { zone: 'topLine1', order: 30, label: 'Reset', sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'message-type-sync-grid', sections: ['query', 'filters', 'columns'] },
			info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
			infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' }
		},
		columns: [
			{ key: 'name', label: 'Name', width: 320, render: renderName },
			{ key: 'label', label: 'Label', width: 320 },
			{ key: 'installed', label: 'Installed', width: 120, render: renderInstalled },
			{ key: 'template_id', label: 'Template ID', width: 260, render: renderName },
			{ key: '__sync', label: 'Action', width: 120, sortable: false, render: renderSyncButton }
		]
	});
	await grid.init();
	show('Ready.');
</script>
