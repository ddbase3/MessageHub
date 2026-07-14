<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$modularDialogCssUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$modularDialogJsUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/index.js');
$serviceUrl = (string) $this->_['service'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularDialogCssUrl, ENT_QUOTES); ?>" />
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
	.messagehub-settings-summary { margin: 0; color: #333; font-size: 13px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
	.messagehub-pill { display: inline-flex; align-items: center; padding: 1px 6px; border: 1px solid #d6d6d6; border-radius: 999px; background: #fafafa; font-size: 11px; line-height: 1.35; color: #444; white-space: nowrap; }
	.messagehub-pill-sent, .messagehub-pill-enabled { background: #eef7ee; border-color: #bddfbd; color: #226622; }
	.messagehub-pill-failed, .messagehub-pill-disabled { background: #fff0f0; border-color: #e4b9b9; color: #8a1f1f; }
	.messagehub-pill-processing { background: #edf6ff; border-color: #c3dff5; color: #284f7c; }

	.messagehub-dialog-surface { width: min(920px, 100%); max-height: min(780px, 100%); }
	.messagehub-dialog-surface .md-shell-body { display: grid; gap: 12px; }
	.messagehub-editor { display: grid; gap: 12px; min-width: 0; }
	.messagehub-form-row { display: grid; gap: 5px; }
	.messagehub-form-row-inline { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
	.messagehub-form-label { color: #555; font-size: 12px; font-weight: 600; line-height: 1.3; }
	.messagehub-form-input, .messagehub-form-select, .messagehub-form-textarea { width: 100%; border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; color: #222; font: inherit; font-size: 13px; line-height: 1.4; padding: 7px 9px; }
	.messagehub-form-textarea { min-height: 180px; resize: vertical; }
	.messagehub-form-textarea-monospace { min-height: 260px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; white-space: pre; }
	.messagehub-form-hint { color: #666; font-size: 12px; line-height: 1.35; }
	.messagehub-error { display: none; padding: 8px 10px; border: 1px solid #e4b9b9; border-radius: 6px; background: #fff0f0; color: #8a1f1f; font-size: 13px; line-height: 1.4; }
	.messagehub-error.is-visible { display: block; }
	.messagehub-settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
	.messagehub-settings-grid .messagehub-form-row-full { grid-column: 1 / -1; }
	@media (max-width: 720px) { .messagehub-form-row-inline, .messagehub-settings-grid { grid-template-columns: 1fr; } }
</style>
<div class="messagehub-shell">
	<h1>Message transports</h1>
	<p>Discoverable transports and their active settings.</p>
	<div class="messagehub-grid">
		<div id="messagehub-transport-grid"></div>
		<div id="messagehub-transport-output" class="messagehub-output"></div>
	</div>
</div>

<template id="messagehub-transport-settings-template">
	<div id="messagehub-transport-settings-editor" class="messagehub-editor">
		<div id="messagehub-transport-settings-error" class="messagehub-error"></div>
		<input type="hidden" id="messagehub-transport-settings-name" />
		<div class="messagehub-form-row-inline">
			<label class="messagehub-form-row">
				<span class="messagehub-form-label">Transport</span>
				<input type="text" id="messagehub-transport-settings-label" class="messagehub-form-input" readonly />
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label">Name</span>
				<input type="text" id="messagehub-transport-settings-key" class="messagehub-form-input" readonly />
			</label>
		</div>
		<div id="messagehub-transport-settings-fields" class="messagehub-settings-grid"></div>
	</div>
</template>

<script type="module">
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_DIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	let grid = null;
	let settingsDialog = null;
	let settingsContent = null;
	let currentTransportRecord = null;

	function getText(value, placeholder = '-') {
		if(value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function log(message) {
		const element = document.querySelector('#messagehub-transport-output');

		if(!element) {
			return;
		}

		element.replaceChildren();
		const label = document.createElement('strong');
		label.textContent = 'Last action:';
		element.appendChild(label);
		element.appendChild(document.createTextNode(' ' + getText(message, 'None')));
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

	function parseJsonObject(value, fallback = {}) {
		if(value && typeof value === 'object') {
			return value;
		}

		try {
			const parsed = JSON.parse(String(value || '').trim() || '{}');
			return parsed && typeof parsed === 'object' ? parsed : fallback;
		}
		catch(error) {
			return fallback;
		}
	}

	function renderEnabled(value, row) {
		const container = document.createElement('span');
		const isEnabled = String(row && row.is_enabled ? row.is_enabled : '0') === '1';

		if(!isEnabled) {
			return container;
		}

		container.className = 'messagehub-pill messagehub-pill-enabled';
		container.textContent = 'Enabled';
		return container;
	}

	function renderDefault(value, row) {
		const pill = document.createElement('span');
		const isDefault = String(row && row.is_default ? row.is_default : '0') === '1';
		pill.className = 'messagehub-pill ' + (isDefault ? 'messagehub-pill-enabled' : '');
		pill.textContent = isDefault ? 'Default' : 'No';
		return pill;
	}

	function renderSettingsSummary(value) {
		const text = document.createElement('div');
		text.className = 'messagehub-settings-summary';
		text.textContent = getText(value);
		return text;
	}

	function renderPre(value) {
		const pre = document.createElement('pre');
		pre.className = 'messagehub-value';
		pre.textContent = getText(value, '');
		return pre;
	}

	function createSettingsContent() {
		if(settingsContent) {
			return settingsContent;
		}

		const template = document.querySelector('#messagehub-transport-settings-template');

		if(!template || !template.content) {
			throw new Error('Transport settings editor template not found.');
		}

		const fragment = template.content.cloneNode(true);
		const content = fragment.querySelector('#messagehub-transport-settings-editor');

		if(!content) {
			throw new Error('Transport settings editor content not found.');
		}

		settingsContent = content;
		return settingsContent;
	}

	function getSettingsElements() {
		const root = settingsContent;

		return {
			root,
			error: root ? root.querySelector('#messagehub-transport-settings-error') : null,
			name: root ? root.querySelector('#messagehub-transport-settings-name') : null,
			label: root ? root.querySelector('#messagehub-transport-settings-label') : null,
			key: root ? root.querySelector('#messagehub-transport-settings-key') : null,
			fields: root ? root.querySelector('#messagehub-transport-settings-fields') : null
		};
	}

	function clearSettingsError() {
		const elements = getSettingsElements();

		if(elements.error) {
			elements.error.textContent = '';
			elements.error.classList.remove('is-visible');
		}

		if(settingsDialog && typeof settingsDialog.execute === 'function') {
			settingsDialog.execute('clearStatus');
		}
	}

	function setSettingsError(message) {
		const elements = getSettingsElements();
		const text = getText(message, '');

		if(elements.error) {
			elements.error.textContent = text;
			elements.error.classList.toggle('is-visible', text !== '');
		}

		if(text !== '' && settingsDialog && typeof settingsDialog.execute === 'function') {
			settingsDialog.execute('setStatus', { message: text, type: 'error' });
		}
	}

	function resolveSettingType(definition, value) {
		if(definition && typeof definition.type === 'string') {
			return definition.type;
		}

		if(Array.isArray(definition && definition.enum)) {
			return 'string';
		}

		if(typeof value === 'boolean') {
			return 'boolean';
		}

		if(typeof value === 'number') {
			return Number.isInteger(value) ? 'integer' : 'number';
		}

		if(value && typeof value === 'object') {
			return Array.isArray(value) ? 'array' : 'object';
		}

		return 'string';
	}

	function createFieldControl(key, definition, value) {
		const type = resolveSettingType(definition, value);
		const row = document.createElement('label');
		row.className = 'messagehub-form-row';

		if(type === 'object' || type === 'array') {
			row.classList.add('messagehub-form-row-full');
		}

		const label = document.createElement('span');
		label.className = 'messagehub-form-label';
		label.textContent = key;
		row.appendChild(label);

		let control = null;

		if(type === 'boolean') {
			control = document.createElement('select');
			control.className = 'messagehub-form-select';

			[
				{ value: '1', label: 'true' },
				{ value: '0', label: 'false' }
			].forEach((entry) => {
				const option = document.createElement('option');
				option.value = entry.value;
				option.textContent = entry.label;
				control.appendChild(option);
			});

			control.value = value === true || value === 1 || value === '1' || value === 'true' ? '1' : '0';
		}
		else if(definition && Array.isArray(definition.enum)) {
			control = document.createElement('select');
			control.className = 'messagehub-form-select';

			definition.enum.forEach((entry) => {
				const option = document.createElement('option');
				option.value = String(entry);
				option.textContent = String(entry === '' ? '(empty)' : entry);
				control.appendChild(option);
			});

			control.value = value === null || value === undefined ? '' : String(value);
		}
		else if(type === 'integer' || type === 'number') {
			control = document.createElement('input');
			control.type = 'number';
			control.className = 'messagehub-form-input';
			control.step = type === 'integer' ? '1' : 'any';
			control.value = value === null || value === undefined ? '' : String(value);
		}
		else if(type === 'object' || type === 'array') {
			control = document.createElement('textarea');
			control.className = 'messagehub-form-textarea messagehub-form-textarea-monospace';
			control.spellcheck = false;
			control.value = JSON.stringify(value === undefined ? (type === 'array' ? [] : {}) : value, null, 2);
		}
		else {
			control = document.createElement('input');
			control.type = 'text';
			control.className = 'messagehub-form-input';
			control.value = value === null || value === undefined ? '' : String(value);
		}

		control.dataset.settingKey = key;
		control.dataset.settingType = type;
		row.appendChild(control);

		if(definition && definition.description) {
			const hint = document.createElement('span');
			hint.className = 'messagehub-form-hint';
			hint.textContent = String(definition.description);
			row.appendChild(hint);
		}

		return row;
	}

	function renderSettingsFields(row) {
		const elements = getSettingsElements();

		if(!elements.fields) {
			return;
		}

		const schema = parseJsonObject(row && row.schema_json, {});
		const settings = parseJsonObject(row && row.settings_json, {});
		const properties = schema && schema.properties && typeof schema.properties === 'object' ? schema.properties : {};
		let keys = Object.keys(properties).filter((key) => key !== 'enabled');

		if(keys.length === 0) {
			keys = Object.keys(settings).filter((key) => key !== 'enabled');
		}

		elements.fields.replaceChildren();

		if(keys.length === 0) {
			const hint = document.createElement('div');
			hint.className = 'messagehub-form-hint messagehub-form-row-full';
			hint.textContent = 'This transport does not expose configurable settings.';
			elements.fields.appendChild(hint);
			return;
		}

		keys.forEach((key) => {
			const definition = properties[key] || {};
			const hasStoredValue = Object.prototype.hasOwnProperty.call(settings, key);
			const hasDefaultValue = Object.prototype.hasOwnProperty.call(definition, 'default');
			const value = hasStoredValue ? settings[key] : (hasDefaultValue ? definition.default : undefined);
			elements.fields.appendChild(createFieldControl(key, definition, value));
		});
	}

	function collectSettings() {
		const elements = getSettingsElements();
		const settings = {};

		if(!elements.fields) {
			return settings;
		}

		elements.fields.querySelectorAll('[data-setting-key]').forEach((control) => {
			const key = control.dataset.settingKey || '';
			const type = control.dataset.settingType || 'string';

			if(key === '') {
				return;
			}

			if(type === 'boolean') {
				settings[key] = control.value === '1';
				return;
			}

			if(type === 'integer') {
				settings[key] = control.value === '' ? null : parseInt(control.value, 10);
				return;
			}

			if(type === 'number') {
				settings[key] = control.value === '' ? null : parseFloat(control.value);
				return;
			}

			if(type === 'object' || type === 'array') {
				const fallback = type === 'array' ? [] : {};
				settings[key] = control.value.trim() === '' ? fallback : JSON.parse(control.value);
				return;
			}

			settings[key] = control.value;
		});

		return settings;
	}

	function buildSettingsButtons() {
		return [
			{ key: 'cancel', label: 'Cancel', action: 'close' },
			{ key: 'save', label: 'Save', primary: true, busyLabel: 'Saving...', async action() { await saveSettingsEditor(); } }
		];
	}

	function initSettingsDialog(modularDialogModule) {
		if(settingsDialog) {
			return settingsDialog;
		}

		if(!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') {
			throw new Error('ModularDialog createStandardDialog export not found.');
		}

		const content = createSettingsContent();
		settingsDialog = modularDialogModule.createStandardDialog({
			id: 'messagehub-transport-settings-dialog',
			className: 'messagehub-dialog',
			surfaceClassName: 'messagehub-dialog-surface',
			size: 'large',
			title: 'Transport settings',
			content,
			status: '',
			closeButtonPlugin: { label: 'Close' },
			statusPlugin: { renderEmpty: false },
			buttons: buildSettingsButtons()
		});
		settingsDialog.on('afterClose', () => {
			currentTransportRecord = null;
			clearSettingsError();
		});
		settingsDialog.init();

		return settingsDialog;
	}

	function openSettingsEditor(row) {
		const elements = getSettingsElements();

		if(!settingsDialog || !elements.root) {
			log('Transport settings editor is not available.');
			return;
		}

		currentTransportRecord = row;
		clearSettingsError();
		settingsDialog.execute('setTitle', 'Edit settings: ' + getText(row && row.label, getText(row && row.name, 'Transport')));
		settingsDialog.execute('setButtons', buildSettingsButtons());
		elements.name.value = getText(row && row.name, '');
		elements.key.value = getText(row && row.name, '');
		elements.label.value = getText(row && row.label, '');
		renderSettingsFields(row);
		settingsDialog.open({ source: 'messageTransportSettings', record: row });
	}

	function closeSettingsEditor() {
		if(settingsDialog) {
			settingsDialog.close({ source: 'messageTransportSettings' });
		}
	}

	async function saveSettingsEditor() {
		const elements = getSettingsElements();
		setSettingsError('');

		try {
			const response = await postJson({
				mode: 'save-transport',
				name: elements.name ? elements.name.value : '',
				settings: collectSettings()
			});

			if(!response || response.ok !== true) {
				throw new Error(getText(response && response.error, 'Save failed.'));
			}

			const name = elements.name ? elements.name.value : '';
			closeSettingsEditor();
			await refreshGrid();
			log('Saved transport settings for ' + getText(name) + '.');
		}
		catch(error) {
			setSettingsError(getText(error && error.message, String(error)));
		}
	}

	const modularGridModule = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	let settingsInitializationError = '';

	try {
		const modularDialogModule = await import(new URL(MODULAR_DIALOG_URL, document.baseURI).href);
		initSettingsDialog(modularDialogModule);
	}
	catch(error) {
		console.error('Message transport settings dialog failed:', error);
		settingsInitializationError = 'Transport settings editor failed: ' + getText(error && error.message, String(error));
	}

	const { AjaxAdapter, ModularGrid, SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = modularGridModule;
	const layout = { type: 'stack', children: [
		{ type: 'zone', key: 'topLine1', className: 'messagehub-panel messagehub-panel--main' },
		{ type: 'zone', key: 'topLine2', className: 'messagehub-panel messagehub-panel--filters' },
		{ type: 'view', key: 'main', className: 'messagehub-main' },
		{ type: 'zone', key: 'statusZone', className: 'messagehub-panel messagehub-panel--status' }
	] };
	const adapter = new AjaxAdapter({
		url: ENDPOINT_URL,
		method: 'POST',
		rowsPath: 'data',
		totalPath: 'total',
		mapRequest(request) {
			const state = grid ? grid.getState() : {};
			const sort = request.sortKey ? [{ key: request.sortKey, dir: request.sortDirection || 'asc', type: 'string' }] : [];
			return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || BATCH_SIZE, search: request.search || '', sort, filters: buildFilterPayload(state.filters || {}) };
		}
	});
	grid = new ModularGrid('#messagehub-transport-grid', {
		layout,
		adapter,
		dataMode: 'server',
		server: {
			searchDebounceMs: 220,
			watchStateKeys: ['query', 'filters']
		},
		features: {
			paging: false
		},
		pageSize: BATCH_SIZE,
		plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin, InfiniteScrollPlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: 'Search', placeholder: 'Search name, label or configuration' },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: 'Clear filters', fields: [
				{ key: 'name', label: 'Name', type: 'text', placeholder: 'Name', width: 180 },
				{ key: 'is_enabled', label: 'Enabled', type: 'select', options: [{ value: '', label: 'All' }, { value: '1', label: 'Enabled only' }, { value: '0', label: 'Disabled only' }] },
				{ key: 'is_default', label: 'Default', type: 'select', options: [{ value: '', label: 'All' }, { value: '1', label: 'Default only' }, { value: '0', label: 'Not default' }] }
			] },
			reset: { zone: 'topLine1', order: 20, label: 'Reset', sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'messagehub-transport-grid', sections: ['query', 'filters', 'columns'] },
			info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
			infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' },
			rowActions: { items: [
				{ key: 'default', label: 'Set as default', async onClick(context) { await postJson({ mode: 'save-default', default_transport: context.row.name }); await refreshGrid(); log('Default transport set to ' + context.row.name + '.'); } },
				{ key: 'toggle-enabled', label: 'Toggle enabled', async onClick(context) {
					const enabled = String(context.row && context.row.is_enabled ? context.row.is_enabled : '0') !== '1';
					await postJson({ mode: 'set-enabled', name: context.row.name, enabled });
					await refreshGrid();
					log((enabled ? 'Enabled ' : 'Disabled ') + context.row.name + '.');
				} },
				{ key: 'settings', label: 'Edit settings', onClick(context) { openSettingsEditor(context.row); } },
				{ key: 'reset-settings', label: 'Reset settings', async onClick(context) {
					if(!window.confirm('Reset all stored settings for ' + context.row.name + '?')) {
						return;
					}

					await postJson({ mode: 'reset-transport', name: context.row.name });
					await refreshGrid();
					log('Reset transport settings for ' + context.row.name + '.');
				} }
			] }
		},
		columns: [
			{ key: 'name', label: 'Name', width: 180 },
			{ key: 'label', label: 'Label', width: 260 },
			{ key: 'is_enabled', label: 'Enabled', width: 110, render: renderEnabled },
			{ key: 'is_default', label: 'Default', width: 100, render: renderDefault },
			{ key: 'settings_summary', label: 'Configuration', width: 520, render: renderSettingsSummary },
			{ key: 'schema_json', label: 'Schema', width: 520, visible: false, render: renderPre }
		]
	});
	await grid.init();
	log(settingsInitializationError !== '' ? settingsInitializationError : 'Transports loaded.');
</script>
