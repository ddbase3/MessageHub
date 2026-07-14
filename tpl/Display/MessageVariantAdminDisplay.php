<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$modularDialogCssUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$modularDialogJsUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/index.js');
$serviceUrl = (string) $this->_['service'];
$templateOptions = is_array($this->_['templateOptions'] ?? null) ? $this->_['templateOptions'] : [];
$languageOptions = is_array($this->_['languageOptions'] ?? null) ? $this->_['languageOptions'] : [];
$selectedLanguage = (string) ($this->_['selectedLanguage'] ?? 'en');
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
	.messagehub-form-textarea { min-height: 180px; resize: vertical; }
	.messagehub-form-textarea-monospace { min-height: 260px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; white-space: pre; }
	.messagehub-form-hint { color: #666; font-size: 12px; line-height: 1.35; }
	.messagehub-error { display: none; padding: 8px 10px; border: 1px solid #e4b9b9; border-radius: 6px; background: #fff0f0; color: #8a1f1f; font-size: 13px; line-height: 1.4; }
	.messagehub-error.is-visible { display: block; }
	@media (max-width: 720px) { .messagehub-form-row-inline { grid-template-columns: 1fr; } }
</style>

<div class="messagehub-shell">
	<h1>Message variants</h1>
	<p>Language-specific subject, plain text and HTML body variants.</p>
	<div class="messagehub-grid">
		<div id="messagehub-variant-grid"></div>
		<div id="messagehub-variant-output" class="messagehub-output"></div>
	</div>
</div>

<template id="messagehub-variant-editor-template">
	<div id="messagehub-variant-editor" class="messagehub-editor">
		<div id="messagehub-variant-error" class="messagehub-error"></div>
		<input type="hidden" id="messagehub-variant-id" />
		<label class="messagehub-form-row">
			<span class="messagehub-form-label">Template</span>
			<select id="messagehub-variant-template-id" class="messagehub-form-select">
				<?php foreach($templateOptions as $option): ?>
					<option value="<?php echo htmlspecialchars((string) $option['value'], ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="messagehub-form-row-inline">
			<label class="messagehub-form-row">
				<span class="messagehub-form-label">Language</span>
				<select id="messagehub-variant-language" class="messagehub-form-select">
					<?php foreach($languageOptions as $option): ?>
						<?php $value = (string) ($option['value'] ?? ''); ?>
						<option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"<?php echo $value === $selectedLanguage ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($option['label'] ?? $value), ENT_QUOTES); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label">Enabled</span>
				<select id="messagehub-variant-enabled" class="messagehub-form-select">
					<option value="1">Enabled</option>
					<option value="0">Disabled</option>
				</select>
			</label>
		</div>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label">Subject</span>
			<input type="text" id="messagehub-variant-subject" class="messagehub-form-input" autocomplete="off" />
		</label>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label">Plain text body</span>
			<textarea id="messagehub-variant-body-text" class="messagehub-form-textarea messagehub-form-textarea-monospace" spellcheck="false"></textarea>
		</label>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label">HTML body</span>
			<textarea id="messagehub-variant-body-html" class="messagehub-form-textarea messagehub-form-textarea-monospace" spellcheck="false"></textarea>
			<span class="messagehub-form-hint">HTML is optional. Leave it empty when only plain text should be stored.</span>
		</label>
	</div>
</template>

<script type="module">
	const TEMPLATE_OPTIONS = <?php echo json_encode($templateOptions, JSON_UNESCAPED_SLASHES); ?>;
	const LANGUAGE_OPTIONS = <?php echo json_encode($languageOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const SELECTED_LANGUAGE = <?php echo json_encode($selectedLanguage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_DIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	let grid = null;
	let editorDialog = null;
	let editorContent = null;
	let currentEditorRecord = null;

	function getText(value, placeholder = '-') {
		if(value === null || value === undefined || value === '') { return placeholder; }
		return String(value);
	}

	function log(message) {
		const output = document.querySelector('#messagehub-variant-output');
		if(!output) { return; }
		output.replaceChildren();
		const label = document.createElement('strong');
		label.textContent = 'Last action:';
		output.appendChild(label);
		output.appendChild(document.createTextNode(' ' + getText(message, 'None')));
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

	function getDefaultTemplateId() {
		return TEMPLATE_OPTIONS.length > 0 ? String(TEMPLATE_OPTIONS[0].value || '') : '';
	}

	function ensureLanguageOption(select, value) {
		if(!select || value === '') { return; }
		const exists = Array.from(select.options).some((option) => option.value === value);
		if(exists) { return; }
		const option = document.createElement('option');
		option.value = value;
		option.textContent = value + ' (not available)';
		select.appendChild(option);
	}

	async function postJson(payload) {
		const response = await fetch(ENDPOINT_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
		if(!response.ok) { throw new Error('Request failed with status ' + String(response.status)); }
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
		const template = document.querySelector('#messagehub-variant-editor-template');
		if(!template || !template.content) { throw new Error('Message variant editor template not found.'); }
		const fragment = template.content.cloneNode(true);
		const content = fragment.querySelector('#messagehub-variant-editor');
		if(!content) { throw new Error('Message variant editor content not found.'); }
		editorContent = content;
		return editorContent;
	}

	function getEditorElements() {
		const root = editorContent;
		return {
			root,
			error: root ? root.querySelector('#messagehub-variant-error') : null,
			id: root ? root.querySelector('#messagehub-variant-id') : null,
			templateId: root ? root.querySelector('#messagehub-variant-template-id') : null,
			language: root ? root.querySelector('#messagehub-variant-language') : null,
			subject: root ? root.querySelector('#messagehub-variant-subject') : null,
			bodyText: root ? root.querySelector('#messagehub-variant-body-text') : null,
			bodyHtml: root ? root.querySelector('#messagehub-variant-body-html') : null,
			enabled: root ? root.querySelector('#messagehub-variant-enabled') : null
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
			{ key: 'delete', label: 'Delete', danger: true, hidden: !isExisting, async action() { await deleteCurrentEditorRecord(); } },
			{ key: 'cancel', label: 'Cancel', action: 'close' },
			{ key: 'save', label: 'Save', primary: true, busyLabel: 'Saving...', async action() { await saveEditor(); } }
		];
	}

	function initEditorDialog(modularDialogModule) {
		if(editorDialog) { return editorDialog; }
		if(!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') { throw new Error('ModularDialog createStandardDialog export not found.'); }
		const content = createEditorContent();
		editorDialog = modularDialogModule.createStandardDialog({
			id: 'messagehub-variant-editor-dialog',
			className: 'messagehub-dialog',
			surfaceClassName: 'messagehub-dialog-surface',
			size: 'large',
			title: 'Message variant',
			content,
			status: '',
			closeButtonPlugin: { label: 'Close' },
			statusPlugin: { renderEmpty: false },
			buttons: buildEditorButtons(false)
		});
		editorDialog.on('afterClose', () => { currentEditorRecord = null; clearEditorError(); });
		editorDialog.init();
		return editorDialog;
	}

	function openEditor(record = null) {
		const elements = getEditorElements();
		if(!editorDialog || !elements.root) { log('Message variant editor is not available.'); return false; }
		const isExisting = !!record;
		const templateId = isExisting ? getText(record.template_id, getDefaultTemplateId()) : getDefaultTemplateId();
		if(templateId === '') { log('Create a template first.'); return false; }
		currentEditorRecord = record;
		clearEditorError();
		editorDialog.execute('setTitle', isExisting ? 'Edit message variant' : 'Add message variant');
		editorDialog.execute('setButtons', buildEditorButtons(isExisting));
		elements.id.value = isExisting ? getText(record.id, '') : '';
		elements.templateId.value = templateId;
		const language = isExisting ? getText(record.language, SELECTED_LANGUAGE) : SELECTED_LANGUAGE;
		ensureLanguageOption(elements.language, language);
		elements.language.value = language;
		elements.subject.value = isExisting ? getText(record.subject, '') : '';
		elements.bodyText.value = isExisting ? getText(record.body_text, '') : '';
		elements.bodyHtml.value = isExisting ? getText(record.body_html, '') : '';
		elements.enabled.value = isExisting ? rowEnabledValue(record) : '1';
		editorDialog.open({ source: 'messageVariantEditor', record });
		window.setTimeout(() => { elements.language.focus(); }, 0);
		return true;
	}

	function closeEditor() { if(editorDialog) { editorDialog.close({ source: 'messageVariantEditor' }); } }

	async function saveEditor() {
		const elements = getEditorElements();
		setEditorError('');
		const payload = {
			mode: 'save',
			id: elements.id.value,
			template_id: elements.templateId.value,
			language: elements.language.value || SELECTED_LANGUAGE,
			subject: elements.subject.value,
			body_text: elements.bodyText.value,
			body_html: elements.bodyHtml.value,
			enabled: elements.enabled.value
		};
		try {
			const response = await postJson(payload);
			if(!response || response.ok !== true) { throw new Error(getText(response && response.error, 'Save failed.')); }
			closeEditor();
			await refreshGrid();
			log('Saved message variant ' + getText(payload.language) + '.');
		}
		catch(error) { setEditorError(getText(error && error.message, String(error))); }
	}

	async function deleteRecord(row) {
		if(!row || !row.id) { log('Missing message variant id.'); return; }
		if(!window.confirm('Delete variant ' + getText(row.language) + '?')) { return; }
		try { await postJson({ mode: 'delete', id: row.id }); await refreshGrid(); log('Deleted message variant ' + getText(row.language) + '.'); }
		catch(error) { log('Failed to delete message variant: ' + getText(error && error.message, String(error))); }
	}

	async function deleteCurrentEditorRecord() {
		if(!currentEditorRecord) { return; }
		const record = currentEditorRecord;
		closeEditor();
		await deleteRecord(record);
	}

	function bindEditorEvents() {
		const elements = getEditorElements();
		[elements.bodyText, elements.bodyHtml, elements.subject].forEach((element) => {
			if(!element) { return; }
			element.addEventListener('keydown', (event) => {
				if((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); saveEditor(); }
				if(event.key === 'Escape') { event.preventDefault(); closeEditor(); }
			});
		});
	}

	function renderState(value, row) {
		const enabled = rowEnabledValue(row) === '1';
		const pill = document.createElement('span');
		pill.className = 'messagehub-pill ' + (enabled ? 'messagehub-pill-enabled' : 'messagehub-pill-disabled');
		pill.textContent = getText(row.enabled_label, enabled ? 'Enabled' : 'Disabled');
		return pill;
	}

	function createVariantActionsPlugin() {
		return {
			name: 'variantActions',
			layoutContributions() {
				return [{ zone: 'topLine1', order: 5, render() {
					const wrapper = document.createElement('div');
					wrapper.className = 'messagehub-top-actions';
					const addButton = createButton('messagehub-button messagehub-button-primary', 'Add variant');
					addButton.addEventListener('click', () => openEditor(null));
					wrapper.appendChild(addButton);
					return wrapper;
				} }];
			}
		};
	}

	const templateFilterOptions = [{ value: '', label: 'All templates' }, ...TEMPLATE_OPTIONS.map((option) => { return { value: option.value, label: option.label }; })];
	const languageFilterOptions = [{ value: '', label: 'All languages' }, ...LANGUAGE_OPTIONS.map((option) => { return { value: option.value, label: option.label }; })];
	const modularGridModule = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	let editorInitializationError = '';
	try {
		const modularDialogModule = await import(new URL(MODULAR_DIALOG_URL, document.baseURI).href);
		initEditorDialog(modularDialogModule);
		bindEditorEvents();
	}
	catch(error) {
		console.error('Message variant editor dialog failed:', error);
		editorInitializationError = 'Message variant editor failed: ' + getText(error && error.message, String(error));
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
	grid = new ModularGrid('#messagehub-variant-grid', {
		layout,
		adapter,
		dataMode: 'server',
		server: {
			searchDebounceMs: 220,
			watchStateKeys: ['query', 'filters']
		},
		features: { paging: false },
		pageSize: BATCH_SIZE,
		plugins: [createVariantActionsPlugin(), SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: 'Search', placeholder: 'Search type, language, subject or body' },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: 'Clear filters', fields: [
				{ key: 'template_id', label: 'Template', type: 'select', options: templateFilterOptions },
				{ key: 'language', label: 'Language', type: 'select', options: languageFilterOptions },
				{ key: 'subject', label: 'Subject', type: 'text', placeholder: 'Subject', width: 240 },
				{ key: 'enabled', label: 'State', type: 'select', options: [{ value: '', label: 'All states' }, { value: '1', label: 'Enabled' }, { value: '0', label: 'Disabled' }] }
			] },
			reset: { zone: 'topLine1', order: 30, label: 'Reset', sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'messagehub-variant-grid', sections: ['query', 'filters', 'columns'] },
			info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
			infiniteScroll: {
				threshold: 180,
				pageSize: BATCH_SIZE,
				containerSelector: '.mg-table-scroll'
			},
			rowActions: { items: [
				{ key: 'edit', label: 'Edit', onClick(context) { openEditor(context.row); } },
				{ key: 'delete', label: 'Delete', onClick(context) { deleteRecord(context.row); } }
			] }
		},
		columns: [
			{ key: 'type_name', label: 'Template', width: 220 },
			{ key: 'language', label: 'Language', width: 100 },
			{ key: 'subject', label: 'Subject', width: 360 },
			{ key: 'body_text_preview', label: 'Body preview', width: 520 },
			{ key: 'enabled_label', label: 'State', width: 100, render: renderState },
			{ key: 'id', label: 'ID', width: 260, visible: false }
		]
	});
	await grid.init();
	log(editorInitializationError !== '' ? editorInitializationError : 'Variants loaded.');
</script>
