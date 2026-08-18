<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$modularDialogCssUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$modularDialogJsUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/index.js');
$serviceUrl = (string) $this->_['service'];
$transportOptions = is_array($this->_['transport_options'] ?? null) ? $this->_['transport_options'] : [];
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$gridStrings = is_array($this->_['grid_strings'] ?? null) ? $this->_['grid_strings'] : [];
$dialogStrings = is_array($this->_['dialog_strings'] ?? null) ? $this->_['dialog_strings'] : [];
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn(string $key, string $fallback): string => trim((string)($translations[$key] ?? '')) !== ''
	? (string)$translations[$key]
	: $fallback;
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
	.messagehub-form-input[readonly] { background: #f5f5f5; color: #666; cursor: not-allowed; }
	.messagehub-form-textarea { min-height: 180px; resize: vertical; }
	.messagehub-form-textarea-monospace { min-height: 260px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; white-space: pre; }
	.messagehub-form-hint { color: #666; font-size: 12px; line-height: 1.35; }
	.messagehub-error { display: none; padding: 8px 10px; border: 1px solid #e4b9b9; border-radius: 6px; background: #fff0f0; color: #8a1f1f; font-size: 13px; line-height: 1.4; }
	.messagehub-error.is-visible { display: block; }
	@media (max-width: 720px) { .messagehub-form-row-inline { grid-template-columns: 1fr; } }
</style>

<div class="messagehub-shell">
	<h1><?php echo $e($t('title', 'Message templates')); ?></h1>
	<p><?php echo $e($t('lead', 'Message templates define stable message types, labels, descriptions and optional default transports.')); ?></p>
	<div class="messagehub-grid">
		<div id="messagehub-template-grid"></div>
		<div id="messagehub-template-output" class="messagehub-output"></div>
	</div>
</div>

<template id="messagehub-template-editor-template">
	<div id="messagehub-template-editor" class="messagehub-editor">
		<div id="messagehub-template-error" class="messagehub-error"></div>
		<input type="hidden" id="messagehub-template-id" />
		<div class="messagehub-form-row-inline">
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('message_type_id', 'Message type ID')); ?></span>
				<input type="text" id="messagehub-template-type-name" class="messagehub-form-input" autocomplete="off" />
				<span class="messagehub-form-hint"><?php echo $e($t('message_type_hint', 'Stable provider identifier. It cannot be changed after the template is created.')); ?></span>
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('label', 'Label')); ?></span>
				<input type="text" id="messagehub-template-label" class="messagehub-form-input" autocomplete="off" />
				<span class="messagehub-form-hint">&nbsp;</span>
			</label>
		</div>
		<div class="messagehub-form-row-inline">
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('default_transport', 'Default transport')); ?></span>
				<select id="messagehub-template-default-transport" class="messagehub-form-select">
					<option value=""><?php echo $e($t('use_system_default', 'Use system default')); ?></option>
					<?php foreach($transportOptions as $transportOption) { ?>
						<option value="<?php echo htmlspecialchars((string)($transportOption['value'] ?? ''), ENT_QUOTES); ?>"><?php echo htmlspecialchars((string)($transportOption['label'] ?? $transportOption['value'] ?? ''), ENT_QUOTES); ?></option>
					<?php } ?>
				</select>
				<span class="messagehub-form-hint"><?php echo $e($t('default_transport_hint', 'Leave empty to use the system-wide default transport.')); ?></span>
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('enabled', 'Enabled')); ?></span>
				<select id="messagehub-template-enabled" class="messagehub-form-select">
					<option value="1"><?php echo $e($t('enabled', 'Enabled')); ?></option>
					<option value="0"><?php echo $e($t('disabled', 'Disabled')); ?></option>
				</select>
				<span class="messagehub-form-hint">&nbsp;</span>
			</label>
		</div>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label"><?php echo $e($t('description', 'Description')); ?></span>
			<textarea id="messagehub-template-description" class="messagehub-form-textarea" spellcheck="false"></textarea>
			<span class="messagehub-form-hint"><?php echo $e($t('description_hint', 'The technical template ID is generated internally. Scope remains global in the current MessageHub runtime.')); ?></span>
		</label>
	</div>
</template>

<script type="module">
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_DIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const I18N = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_STRINGS = <?php echo json_encode($gridStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DIALOG_STRINGS = <?php echo json_encode($dialogStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const TRANSPORT_FILTER_OPTIONS = [{ value: '', label: tr('all_transports', 'All transports') }, ...<?php echo json_encode($transportOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>];
	const BATCH_SIZE = 50;
	let grid = null;
	let editorDialog = null;
	let editorContent = null;
	let currentEditorRecord = null;

	function tr(key, fallback, replacements = {}) { let text = String(I18N[key] || fallback || key); Object.entries(replacements).forEach(([name, value]) => { text = text.split('{' + name + '}').join(String(value)); }); return text; }

	function getText(value, placeholder = '-') {
		if(value === null || value === undefined || value === '') { return placeholder; }
		return String(value);
	}

	function apiError(response, fallback) {
		const error = getText(response && response.error, '');
		if(error === 'Message template not found.') { return tr('error_template_not_found', 'Message template not found.'); }
		if(error === 'Message type ID is required.') { return tr('error_type_required', 'Message type ID is required.'); }
		if(error.indexOf('Unknown default transport: ') === 0) { return tr('error_unknown_default_transport', 'Unknown default transport: {transport}', { transport: error.substring('Unknown default transport: '.length) }); }
		return error !== '' ? error : fallback;
	}

	function log(message) {
		const output = document.querySelector('#messagehub-template-output');
		if(!output) { return; }
		output.replaceChildren();
		const label = document.createElement('strong');
		label.textContent = tr('last_action', 'Last action:');
		output.appendChild(label);
		output.appendChild(document.createTextNode(' ' + getText(message, tr('none_label', 'None'))));
	}

	function createButton(className, text) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = className;
		button.textContent = text;
		return button;
	}

	function buildFilterPayload(filters) {
		const result = {};
		Object.entries(filters || {}).forEach(([key, value]) => {
			if(value === '' || value === null || value === undefined) { return; }
			result[key] = value;
		});
		return result;
	}

	function rowEnabledValue(row) {
		if(row && (row.enabled === 0 || row.enabled === '0' || row.enabled === false)) { return '0'; }
		if(row && typeof row.enabled_label === 'string' && row.enabled_label.toLowerCase() === 'disabled') { return '0'; }
		return '1';
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		if(!response.ok) { throw new Error(tr('request_failed_status', 'Request failed with status {status}', { status: response.status })); }
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

	function createEditorContent() {
		if(editorContent) { return editorContent; }
		const template = document.querySelector('#messagehub-template-editor-template');
		if(!template || !template.content) { throw new Error(tr('editor_template_not_found', 'Message template editor template not found.')); }
		const fragment = template.content.cloneNode(true);
		const content = fragment.querySelector('#messagehub-template-editor');
		if(!content) { throw new Error(tr('editor_content_not_found', 'Message template editor content not found.')); }
		editorContent = content;
		return editorContent;
	}

	function getEditorElements() {
		const root = editorContent;
		return {
			root,
			error: root ? root.querySelector('#messagehub-template-error') : null,
			id: root ? root.querySelector('#messagehub-template-id') : null,
			typeName: root ? root.querySelector('#messagehub-template-type-name') : null,
			label: root ? root.querySelector('#messagehub-template-label') : null,
			description: root ? root.querySelector('#messagehub-template-description') : null,
			defaultTransport: root ? root.querySelector('#messagehub-template-default-transport') : null,
			enabled: root ? root.querySelector('#messagehub-template-enabled') : null
		};
	}

	function clearEditorError() {
		const elements = getEditorElements();
		if(elements.error) { elements.error.textContent = ''; elements.error.classList.remove('is-visible'); }
		if(editorDialog && typeof editorDialog.execute === 'function') { editorDialog.execute('clearStatus'); }
	}

	function setEditorError(message) {
		const elements = getEditorElements();
		const text = getText(message, '');
		if(elements.error) { elements.error.textContent = text; elements.error.classList.toggle('is-visible', text !== ''); }
		if(text !== '' && editorDialog && typeof editorDialog.execute === 'function') { editorDialog.execute('setStatus', { message: text, type: 'error' }); }
	}

	function buildEditorButtons(isExisting) {
		return [
			{ key: 'delete', label: tr('delete', 'Delete'), danger: true, hidden: !isExisting, async action() { await deleteCurrentEditorRecord(); } },
			{ key: 'cancel', label: tr('cancel', 'Cancel'), action: 'close' },
			{ key: 'save', label: tr('save', 'Save'), primary: true, busyLabel: tr('saving', 'Saving...'), async action() { await saveEditor(); } }
		];
	}

	function initEditorDialog(modularDialogModule) {
		if(editorDialog) { return editorDialog; }
		if(!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') { throw new Error(tr('dialog_unavailable', 'ModularDialog createStandardDialog export not found.')); }
		const content = createEditorContent();
		editorDialog = modularDialogModule.createStandardDialog({
			id: 'messagehub-template-editor-dialog',
			className: 'messagehub-dialog',
			surfaceClassName: 'messagehub-dialog-surface',
			size: 'large',
			title: tr('dialog_title', 'Message template'),
			content,
			status: '',
			closeButtonPlugin: { label: tr('close', 'Close') },
			strings: DIALOG_STRINGS,
			statusPlugin: { renderEmpty: false },
			buttons: buildEditorButtons(false)
		});
		editorDialog.on('afterClose', () => { currentEditorRecord = null; clearEditorError(); });
		editorDialog.init();
		return editorDialog;
	}

	function openEditor(record = null) {
		const elements = getEditorElements();
		if(!editorDialog || !elements.root) { log(tr('editor_unavailable', 'Message template editor is not available.')); return false; }
		const isExisting = !!record;
		currentEditorRecord = record;
		clearEditorError();
		editorDialog.execute('setTitle', isExisting ? tr('edit_message_template', 'Edit message template') : tr('add_message_template', 'Add message template'));
		editorDialog.execute('setButtons', buildEditorButtons(isExisting));
		elements.id.value = isExisting ? getText(record.id, '') : '';
		elements.typeName.value = isExisting ? getText(record.type_name, '') : '';
		elements.typeName.readOnly = isExisting;
		elements.label.value = isExisting ? getText(record.label, '') : '';
		elements.description.value = isExisting ? getText(record.description, '') : '';
		elements.defaultTransport.value = isExisting ? getText(record.default_transport, '') : '';
		elements.enabled.value = isExisting ? rowEnabledValue(record) : '1';
		editorDialog.open({ source: 'messageTemplateEditor', record });
		window.setTimeout(() => { (isExisting ? elements.label : elements.typeName).focus(); }, 0);
		return true;
	}

	function closeEditor() { if(editorDialog) { editorDialog.close({ source: 'messageTemplateEditor' }); } }

	async function saveEditor() {
		const elements = getEditorElements();
		setEditorError('');
		const payload = {
			mode: 'save',
			id: elements.id.value,
			type_name: elements.typeName.value,
			label: elements.label.value || elements.typeName.value,
			description: elements.description.value,
			default_transport: elements.defaultTransport.value,
			enabled: elements.enabled.value
		};
		try {
			const response = await postJson(payload);
			if(!response || response.ok !== true) { throw new Error(apiError(response, tr('save_failed', 'Save failed.'))); }
			closeEditor();
			await refreshGrid();
			log(tr('saved_message_template', 'Saved message template {type}.', { type: getText(payload.type_name) }));
		}
		catch(error) { setEditorError(getText(error && error.message, String(error))); }
	}

	async function deleteRecord(row) {
		if(!row || !row.id) { log(tr('missing_message_template_id', 'Missing message template id.')); return; }
		if(!window.confirm(tr('delete_template_confirm', 'Delete template {type}?', { type: getText(row.type_name) }))) { return; }
		try { await postJson({ mode: 'delete', id: row.id }); await refreshGrid(); log(tr('deleted_message_template', 'Deleted message template {type}.', { type: getText(row.type_name) })); }
		catch(error) { log(tr('delete_message_template_failed', 'Failed to delete message template: {error}', { error: getText(error && error.message, String(error)) })); }
	}

	async function deleteCurrentEditorRecord() {
		if(!currentEditorRecord) { return; }
		const record = currentEditorRecord;
		closeEditor();
		await deleteRecord(record);
	}

	function bindEditorEvents() {
		const elements = getEditorElements();
		if(elements.description) {
			elements.description.addEventListener('keydown', (event) => {
				if((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); saveEditor(); }
				if(event.key === 'Escape') { event.preventDefault(); closeEditor(); }
			});
		}
	}

	function renderState(value, row) {
		const enabled = rowEnabledValue(row) === '1';
		const pill = document.createElement('span');
		pill.className = 'messagehub-pill ' + (enabled ? 'messagehub-pill-enabled' : 'messagehub-pill-disabled');
		pill.textContent = enabled ? tr('enabled', 'Enabled') : tr('disabled', 'Disabled');
		return pill;
	}

	function createTemplateActionsPlugin() {
		return {
			name: 'templateActions',
			layoutContributions() {
				return [{ zone: 'topLine1', order: 5, render() {
					const wrapper = document.createElement('div');
					wrapper.className = 'messagehub-top-actions';
					const addButton = createButton('messagehub-button messagehub-button-primary', tr('add_template', 'Add template'));
					addButton.addEventListener('click', () => openEditor(null));
					wrapper.appendChild(addButton);
					return wrapper;
				} }];
			}
		};
	}

	const modularGridModule = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	let editorInitializationError = '';
	try {
		const modularDialogModule = await import(new URL(MODULAR_DIALOG_URL, document.baseURI).href);
		initEditorDialog(modularDialogModule);
		bindEditorEvents();
	}
	catch(error) {
		console.error('Message template editor dialog failed:', error);
		editorInitializationError = tr('editor_failed', 'Message template editor failed: {error}', { error: getText(error && error.message, String(error)) });
	}

	const { AjaxAdapter, ModularGrid, SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = modularGridModule;
	const layout = { type: 'stack', children: [
		{ type: 'zone', key: 'topLine1', className: 'messagehub-panel messagehub-panel--main' },
		{ type: 'zone', key: 'topLine2', className: 'messagehub-panel messagehub-panel--filters' },
		{ type: 'view', key: 'main', className: 'messagehub-main' },
		{ type: 'zone', key: 'statusZone', className: 'messagehub-panel messagehub-panel--status' }
	] };
	const adapter = new AjaxAdapter({ url: ENDPOINT_URL, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) {
		const state = grid ? grid.getState() : {};
		const sort = request.sortKey ? [{ key: request.sortKey, dir: request.sortDirection || 'asc', type: 'string' }] : [];
		return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || BATCH_SIZE, search: request.search || '', sort, filters: buildFilterPayload(state.filters || {}) };
	} });
	grid = new ModularGrid('#messagehub-template-grid', {
		layout,
		adapter,
		dataMode: 'server',
		server: {
			searchDebounceMs: 220,
			watchStateKeys: ['query', 'filters']
		},
		features: { paging: false },
		strings: GRID_STRINGS,
		pageSize: BATCH_SIZE,
		plugins: [createTemplateActionsPlugin(), SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: tr('search', 'Search'), placeholder: tr('template_search_placeholder', 'Search message type ID, label or description') },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: tr('clear_filters', 'Clear filters'), fields: [
				{ key: 'type_name', label: tr('message_type_id', 'Message type ID'), type: 'text', placeholder: tr('message_type_id', 'Message type ID'), width: 240 },
				{ key: 'label', label: tr('label', 'Label'), type: 'text', placeholder: tr('label', 'Label'), width: 220 },
				{ key: 'default_transport_exact', label: tr('transport', 'Transport'), type: 'select', options: TRANSPORT_FILTER_OPTIONS },
				{ key: 'enabled', label: tr('state', 'State'), type: 'select', options: [{ value: '', label: tr('all_states', 'All states') }, { value: '1', label: tr('enabled', 'Enabled') }, { value: '0', label: tr('disabled', 'Disabled') }] }
			] },
			reset: { zone: 'topLine1', order: 30, label: tr('reset', 'Reset'), sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'messagehub-template-grid', sections: ['query', 'filters', 'columns'] },
			info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
			infiniteScroll: {
				threshold: 180,
				pageSize: BATCH_SIZE,
				containerSelector: '.mg-table-scroll'
			},
			rowActions: { items: [
				{ key: 'edit', label: tr('edit', 'Edit'), onClick(context) { openEditor(context.row); } },
				{ key: 'delete', label: tr('delete', 'Delete'), onClick(context) { deleteRecord(context.row); } }
			] }
		},
		columns: [
			{ key: 'type_name', label: tr('message_type_id', 'Message type ID'), width: 280 },
			{ key: 'label', label: tr('label', 'Label'), width: 260 },
			{ key: 'description', label: tr('description', 'Description'), width: 420 },
			{ key: 'default_transport', label: tr('transport', 'Transport'), width: 150 },
			{ key: 'enabled_label', label: tr('state', 'State'), width: 100, render: renderState },
			{ key: 'id', label: tr('id', 'ID'), width: 260, visible: false }
		]
	});
	await grid.init();
	log(editorInitializationError !== '' ? editorInitializationError : tr('templates_loaded', 'Templates loaded.'));
</script>
