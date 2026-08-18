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
$bodyHtmlEditor = (string) ($this->_['bodyHtmlEditor'] ?? '');
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
	.messagehub-pill-processing, .messagehub-pill-fallback { background: #edf6ff; border-color: #c3dff5; color: #284f7c; }

	.messagehub-dialog-surface { width: min(920px, 100%); max-height: min(780px, 100%); }
	.messagehub-dialog-surface .md-shell-body { display: grid; gap: 12px; }
	.messagehub-editor { display: grid; gap: 12px; min-width: 0; }
	.messagehub-form-row { display: grid; gap: 5px; }
	.messagehub-form-row-inline { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
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
	<h1><?php echo $e($t('title', 'Message variants')); ?></h1>
	<p><?php echo $e($t('lead', 'Language-specific subject, plain text and HTML body variants.')); ?></p>
	<div class="messagehub-grid">
		<div id="messagehub-variant-grid"></div>
		<div id="messagehub-variant-output" class="messagehub-output"></div>
	</div>
</div>

<div id="messagehub-variant-editor-template" hidden>
	<div id="messagehub-variant-editor" class="messagehub-editor">
		<div id="messagehub-variant-error" class="messagehub-error"></div>
		<input type="hidden" id="messagehub-variant-id" />
		<label class="messagehub-form-row">
			<span class="messagehub-form-label"><?php echo $e($t('template', 'Template')); ?></span>
			<select id="messagehub-variant-template-id" class="messagehub-form-select">
				<?php foreach($templateOptions as $option): ?>
					<option value="<?php echo htmlspecialchars((string) $option['value'], ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="messagehub-form-row-inline">
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('language', 'Language')); ?></span>
				<select id="messagehub-variant-language" class="messagehub-form-select">
					<?php foreach($languageOptions as $option): ?>
						<?php $value = (string) ($option['value'] ?? ''); ?>
						<option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>"<?php echo $value === $selectedLanguage ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($option['label'] ?? $value), ENT_QUOTES); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('enabled', 'Enabled')); ?></span>
				<select id="messagehub-variant-enabled" class="messagehub-form-select">
					<option value="1"><?php echo $e($t('enabled', 'Enabled')); ?></option>
					<option value="0"><?php echo $e($t('disabled', 'Disabled')); ?></option>
				</select>
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('fallback', 'Fallback')); ?></span>
				<select id="messagehub-variant-fallback" class="messagehub-form-select">
					<option value="0"><?php echo $e($t('option_no', 'No')); ?></option>
					<option value="1"><?php echo $e($t('use_as_fallback', 'Use as fallback')); ?></option>
				</select>
			</label>
		</div>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label"><?php echo $e($t('subject', 'Subject')); ?></span>
			<input type="text" id="messagehub-variant-subject" class="messagehub-form-input" autocomplete="off" />
		</label>
		<label class="messagehub-form-row">
			<span class="messagehub-form-label"><?php echo $e($t('plain_text_body', 'Plain text body')); ?></span>
			<textarea id="messagehub-variant-body-text" class="messagehub-form-textarea messagehub-form-textarea-monospace" spellcheck="false"></textarea>
		</label>
		<div class="messagehub-form-row">
			<span class="messagehub-form-label"><?php echo $e($t('html_body', 'HTML body')); ?></span>
			<?php echo $bodyHtmlEditor; ?>
			<span class="messagehub-form-hint"><?php echo $e($t('html_hint', 'HTML is optional. Leave it empty when only plain text should be stored.')); ?></span>
		</div>
	</div>
</div>

<script type="module">
	const TEMPLATE_OPTIONS = <?php echo json_encode($templateOptions, JSON_UNESCAPED_SLASHES); ?>;
	const LANGUAGE_OPTIONS = <?php echo json_encode($languageOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const SELECTED_LANGUAGE = <?php echo json_encode($selectedLanguage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	const ENDPOINT_URL = <?php echo json_encode($serviceUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_GRID_URL = <?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const MODULAR_DIALOG_URL = <?php echo json_encode($modularDialogJsUrl, JSON_UNESCAPED_SLASHES); ?>;
	const I18N = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_STRINGS = <?php echo json_encode($gridStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DIALOG_STRINGS = <?php echo json_encode($dialogStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
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
		if(error === 'A fallback variant must be enabled.') { return tr('error_fallback_requires_enabled', 'A fallback variant must be enabled.'); }
		return error !== '' ? error : fallback;
	}

	function getRichTextEditorApi(element) {
		if(!element || !element.base3RichTextEditor || typeof element.base3RichTextEditor !== 'object') {
			return null;
		}

		return element.base3RichTextEditor;
	}

	function setRichTextEditorValue(element, value) {
		const text = value === null || value === undefined ? '' : String(value);
		const api = getRichTextEditorApi(element);

		if(api && typeof api.setValue === 'function') {
			api.setValue(text);
			return;
		}

		if(element) {
			element.value = text;
		}
	}

	function getRichTextEditorValue(element) {
		const api = getRichTextEditorApi(element);

		if(api && typeof api.getValue === 'function') {
			const value = api.getValue();
			return value === null || value === undefined ? '' : String(value);
		}

		return element ? String(element.value || '') : '';
	}

	function log(message) {
		const output = document.querySelector('#messagehub-variant-output');
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

	function rowFallbackValue(row) {
		if(row && (row.fallback === 1 || row.fallback === '1' || row.fallback === true)) { return '1'; }
		if(row && typeof row.fallback_label === 'string' && row.fallback_label.toLowerCase() === 'fallback') { return '1'; }
		return '0';
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
		const template = document.querySelector('#messagehub-variant-editor-template');
		if(!template) { throw new Error(tr('editor_template_not_found', 'Message variant editor template not found.')); }
		const content = template.querySelector('#messagehub-variant-editor');
		if(!content) { throw new Error(tr('editor_content_not_found', 'Message variant editor content not found.')); }
		content.remove();
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
			enabled: root ? root.querySelector('#messagehub-variant-enabled') : null,
			fallback: root ? root.querySelector('#messagehub-variant-fallback') : null
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
			id: 'messagehub-variant-editor-dialog',
			className: 'messagehub-dialog',
			surfaceClassName: 'messagehub-dialog-surface',
			size: 'large',
			title: tr('dialog_title', 'Message variant'),
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
		if(!editorDialog || !elements.root) { log(tr('editor_unavailable', 'Message variant editor is not available.')); return false; }
		const isExisting = !!record;
		const templateId = isExisting ? getText(record.template_id, getDefaultTemplateId()) : getDefaultTemplateId();
		if(templateId === '') { log(tr('create_template_first', 'Create a template first.')); return false; }
		currentEditorRecord = record;
		clearEditorError();
		editorDialog.execute('setTitle', isExisting ? tr('edit_message_variant', 'Edit message variant') : tr('add_message_variant', 'Add message variant'));
		editorDialog.execute('setButtons', buildEditorButtons(isExisting));
		elements.id.value = isExisting ? getText(record.id, '') : '';
		elements.templateId.value = templateId;
		const language = isExisting ? getText(record.language, SELECTED_LANGUAGE) : SELECTED_LANGUAGE;
		ensureLanguageOption(elements.language, language);
		elements.language.value = language;
		elements.subject.value = isExisting ? getText(record.subject, '') : '';
		elements.bodyText.value = isExisting ? getText(record.body_text, '') : '';
		setRichTextEditorValue(elements.bodyHtml, isExisting ? getText(record.body_html, '') : '');
		elements.enabled.value = isExisting ? rowEnabledValue(record) : '1';
		elements.fallback.value = isExisting ? rowFallbackValue(record) : '0';
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
			body_html: getRichTextEditorValue(elements.bodyHtml),
			enabled: elements.enabled.value,
			fallback: elements.fallback.value
		};
		try {
			const response = await postJson(payload);
			if(!response || response.ok !== true) { throw new Error(apiError(response, tr('save_failed', 'Save failed.'))); }
			closeEditor();
			await refreshGrid();
			log(tr('saved_message_variant', 'Saved message variant {language}.', { language: getText(payload.language) }));
		}
		catch(error) { setEditorError(getText(error && error.message, String(error))); }
	}

	async function deleteRecord(row) {
		if(!row || !row.id) { log(tr('missing_message_variant_id', 'Missing message variant id.')); return; }
		if(!window.confirm(tr('delete_variant_confirm', 'Delete variant {language}?', { language: getText(row.language) }))) { return; }
		try { await postJson({ mode: 'delete', id: row.id }); await refreshGrid(); log(tr('deleted_message_variant', 'Deleted message variant {language}.', { language: getText(row.language) })); }
		catch(error) { log(tr('delete_message_variant_failed', 'Failed to delete message variant: {error}', { error: getText(error && error.message, String(error)) })); }
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

	function renderLanguage(value, row) {
		const stack = document.createElement('div');
		stack.className = 'messagehub-cell-stack';

		const language = document.createElement('div');
		language.className = 'messagehub-cell-main';
		language.textContent = getText(row && row.language, '-');
		stack.appendChild(language);

		if(rowFallbackValue(row) === '1') {
			const fallback = document.createElement('div');
			fallback.className = 'messagehub-cell-sub';
			const pill = document.createElement('span');
			pill.className = 'messagehub-pill messagehub-pill-fallback';
			pill.textContent = tr('fallback', 'Fallback');
			fallback.appendChild(pill);
			stack.appendChild(fallback);
		}

		return stack;
	}

	function renderState(value, row) {
		const enabled = rowEnabledValue(row) === '1';
		const pill = document.createElement('span');
		pill.className = 'messagehub-pill ' + (enabled ? 'messagehub-pill-enabled' : 'messagehub-pill-disabled');
		pill.textContent = enabled ? tr('enabled', 'Enabled') : tr('disabled', 'Disabled');
		return pill;
	}

	function createVariantActionsPlugin() {
		return {
			name: 'variantActions',
			layoutContributions() {
				return [{ zone: 'topLine1', order: 5, render() {
					const wrapper = document.createElement('div');
					wrapper.className = 'messagehub-top-actions';
					const addButton = createButton('messagehub-button messagehub-button-primary', tr('add_variant', 'Add variant'));
					addButton.addEventListener('click', () => openEditor(null));
					wrapper.appendChild(addButton);
					return wrapper;
				} }];
			}
		};
	}

	const templateFilterOptions = [{ value: '', label: tr('all_templates', 'All templates') }, ...TEMPLATE_OPTIONS.map((option) => { return { value: option.value, label: option.label }; })];
	const languageFilterOptions = [{ value: '', label: tr('all_languages', 'All languages') }, ...LANGUAGE_OPTIONS.map((option) => { return { value: option.value, label: option.label }; })];
	const modularGridModule = await import(new URL(MODULAR_GRID_URL, document.baseURI).href);
	let editorInitializationError = '';
	try {
		const modularDialogModule = await import(new URL(MODULAR_DIALOG_URL, document.baseURI).href);
		initEditorDialog(modularDialogModule);
		bindEditorEvents();
	}
	catch(error) {
		console.error('Message variant editor dialog failed:', error);
		editorInitializationError = tr('editor_failed', 'Message variant editor failed: {error}', { error: getText(error && error.message, String(error)) });
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
		strings: GRID_STRINGS,
		pageSize: BATCH_SIZE,
		plugins: [createVariantActionsPlugin(), SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, InfiniteScrollPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: tr('search', 'Search'), placeholder: tr('variant_search_placeholder', 'Search type, language, subject or body') },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: tr('clear_filters', 'Clear filters'), fields: [
				{ key: 'template_id', label: tr('template', 'Template'), type: 'select', options: templateFilterOptions },
				{ key: 'language', label: tr('language', 'Language'), type: 'select', options: languageFilterOptions },
				{ key: 'subject', label: tr('subject', 'Subject'), type: 'text', placeholder: tr('subject', 'Subject'), width: 240 },
				{ key: 'enabled', label: tr('state', 'State'), type: 'select', options: [{ value: '', label: tr('all_states', 'All states') }, { value: '1', label: tr('enabled', 'Enabled') }, { value: '0', label: tr('disabled', 'Disabled') }] }
			] },
			reset: { zone: 'topLine1', order: 30, label: tr('reset', 'Reset'), sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'messagehub-variant-grid', sections: ['query', 'filters', 'columns'] },
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
			{ key: 'type_name', label: tr('template', 'Template'), width: 220 },
			{ key: 'language', label: tr('language', 'Language'), width: 130, render: renderLanguage },
			{ key: 'subject', label: tr('subject', 'Subject'), width: 360 },
			{ key: 'body_text_preview', label: tr('body_preview', 'Body preview'), width: 520 },
			{ key: 'enabled_label', label: tr('state', 'State'), width: 100, render: renderState },
			{ key: 'id', label: tr('id', 'ID'), width: 260, visible: false }
		]
	});
	await grid.init();
	log(editorInitializationError !== '' ? editorInitializationError : tr('variants_loaded', 'Variants loaded.'));
</script>
