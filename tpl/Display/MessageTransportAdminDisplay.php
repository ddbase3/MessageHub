<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
$modularDialogCssUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/styles/modulardialog.css');
$modularDialogJsUrl = (string) $resolve('plugin/ClientStack/assets/modulardialog/index.js');
$serviceUrl = (string) $this->_['service'];
$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
$gridStrings = is_array($this->_['grid_strings'] ?? null) ? $this->_['grid_strings'] : [];
$dialogStrings = is_array($this->_['dialog_strings'] ?? null) ? $this->_['dialog_strings'] : [];
$schemaTranslations = is_array($this->_['schema_translations'] ?? null) ? $this->_['schema_translations'] : [];
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
	<h1><?php echo $e($t('title', 'Message transports')); ?></h1>
	<p><?php echo $e($t('lead', 'Discoverable transports and their active settings.')); ?></p>
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
				<span class="messagehub-form-label"><?php echo $e($t('transport', 'Transport')); ?></span>
				<input type="text" id="messagehub-transport-settings-label" class="messagehub-form-input" readonly />
			</label>
			<label class="messagehub-form-row">
				<span class="messagehub-form-label"><?php echo $e($t('name', 'Name')); ?></span>
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
	const I18N = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const GRID_STRINGS = <?php echo json_encode($gridStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const DIALOG_STRINGS = <?php echo json_encode($dialogStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const SCHEMA_I18N = <?php echo json_encode($schemaTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
	const BATCH_SIZE = 50;
	let grid = null;
	let settingsDialog = null;
	let settingsContent = null;
	let currentTransportRecord = null;

	function tr(key, fallback, replacements = {}) { let text = String(I18N[key] || fallback || key); Object.entries(replacements).forEach(([name, value]) => { text = text.split('{' + name + '}').join(String(value)); }); return text; }
	function schemaTr(key, fallback) { const value = String(SCHEMA_I18N[key] || '').trim(); return value !== '' ? value : fallback; }
	function transportLabel(name, fallback) { return tr('transport_label_' + String(name || '').trim(), fallback); }
	function humanizeKey(key) { return String(key || '').replaceAll('_', ' ').replace(/(^|\s)\S/g, (match) => match.toUpperCase()); }

	function getText(value, placeholder = '-') {
		if(value === null || value === undefined || value === '') {
			return placeholder;
		}

		return String(value);
	}

	function apiError(response, fallback) {
		const error = getText(response && response.error, '');
		if(error === 'Invalid transport settings payload.') { return tr('error_invalid_transport_settings', 'Invalid transport settings payload.'); }
		if(error === 'Unknown message transport.') { return tr('error_unknown_transport', 'Unknown message transport.'); }
		return error !== '' ? error : fallback;
	}

	function enumLabel(key, entry) {
		const value = String(entry === '' ? 'empty' : entry).toLowerCase();
		return schemaTr('enum_' + key + '_' + value, schemaTr('enum_' + value, entry === '' ? tr('empty_value', '(empty)') : String(entry)));
	}

	function schemaDescription(key, fallback) {
		const direct = String(SCHEMA_I18N['description_' + key] || '').trim();
		if(direct !== '') { return direct; }
		if(fallback === 'ConfigValue definition or fixed secret') { return schemaTr('description_configvalue_secret', fallback); }
		if(fallback === 'ConfigValue definition or fixed endpoint URL') { return schemaTr('description_configvalue_endpoint', fallback); }
		return fallback;
	}

	function localizeSummary(value) {
		const exact = {
			'No delivery settings required.': tr('summary_no_delivery_settings', 'No delivery settings required.'),
			'Not configured.': tr('summary_not_configured', 'Not configured.'),
			'Messages are discarded successfully.': tr('summary_discarded', 'Messages are discarded successfully.'),
			'Messages are discarded successfully': tr('summary_discarded', 'Messages are discarded successfully.'),
			'Adaptive Card payload': tr('summary_adaptive_card', 'Adaptive Card payload'),
			'Workflow text payload': tr('summary_workflow_text', 'Workflow text payload'),
			'Custom blocks configured': tr('summary_custom_blocks', 'Custom blocks configured'),
			'Text message': tr('summary_text_message', 'Text message'),
			'Link unfurling enabled': tr('summary_link_unfurling_enabled', 'Link unfurling enabled'),
			'Link unfurling disabled': tr('summary_link_unfurling_disabled', 'Link unfurling disabled'),
			'Plain text': tr('summary_plain_text', 'Plain text'),
			'Silent delivery': tr('summary_silent_delivery', 'Silent delivery'),
			'Normal notification': tr('summary_normal_notification', 'Normal notification'),
			'Link preview enabled': tr('summary_link_preview_enabled', 'Link preview enabled'),
			'Link preview disabled': tr('summary_link_preview_disabled', 'Link preview disabled'),
			'Sender from message': tr('summary_sender_from_message', 'Sender from message'),
			'PHP mail': tr('summary_php_mail', 'PHP mail')
		};
		const prefixes = {
			'Method': tr('summary_method', 'Method'), 'Endpoint': tr('summary_endpoint', 'Endpoint'), 'Content': tr('summary_content', 'Content'),
			'Authentication': tr('summary_authentication', 'Authentication'), 'Webhook URL': tr('summary_webhook_url', 'Webhook URL'),
			'Server': tr('summary_server', 'Server'), 'Default topic': tr('summary_default_topic', 'Default topic'), 'Priority': tr('summary_priority', 'Priority'),
			'Reason': tr('summary_reason', 'Reason'), 'From': tr('summary_from', 'From'), 'Encryption': tr('summary_encryption', 'Encryption'),
			'Password': tr('summary_password', 'Password'), 'Binary': tr('summary_binary', 'Binary'), 'Bot token': tr('summary_bot_token', 'Bot token'),
			'Default chat': tr('summary_default_chat', 'Default chat'), 'Parse mode': tr('summary_parse_mode', 'Parse mode'),
			'Messaging service': tr('summary_messaging_service', 'Messaging service'), 'Account SID': tr('summary_account_sid', 'Account SID'),
			'Auth token': tr('summary_auth_token', 'Auth token'), 'API version': tr('summary_api_version', 'API version'),
			'Phone number ID': tr('summary_phone_number_id', 'Phone number ID'), 'Access token': tr('summary_access_token', 'Access token'),
			'Username': tr('summary_username', 'Username')
		};
		return String(value || '').split(' | ').map((part) => {
			part = String(part || '').trim();
			if(exact[part]) { return exact[part]; }
			if(part.indexOf('Messages are discarded successfully | Reason: ') === 0) { return part; }
			const separator = part.indexOf(': ');
			if(separator < 0) { return part; }
			const prefix = part.substring(0, separator);
			let tail = part.substring(separator + 2);
			const tailMap = { configured: tr('summary_configured', 'configured'), 'not configured': tr('summary_not_configured_short', 'not configured'), enabled: tr('summary_enabled', 'enabled'), disabled: tr('summary_disabled', 'disabled'), None: tr('summary_none', 'None'), none: tr('summary_none_lower', 'none'), default: tr('summary_default', 'default') };
			if(Object.prototype.hasOwnProperty.call(tailMap, tail)) { tail = tailMap[tail]; }
			return (prefixes[prefix] || prefix) + ': ' + tail;
		}).join(' | ');
	}

	function log(message) {
		const element = document.querySelector('#messagehub-transport-output');

		if(!element) {
			return;
		}

		element.replaceChildren();
		const label = document.createElement('strong');
		label.textContent = tr('last_action', 'Last action:');
		element.appendChild(label);
		element.appendChild(document.createTextNode(' ' + getText(message, tr('none_label', 'None'))));
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
			throw new Error(tr('request_failed_status', 'Request failed with status {status}', { status: response.status }));
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
		container.textContent = tr('enabled', 'Enabled');
		return container;
	}

	function renderDefault(value, row) {
		const pill = document.createElement('span');
		const isDefault = String(row && row.is_default ? row.is_default : '0') === '1';
		pill.className = 'messagehub-pill ' + (isDefault ? 'messagehub-pill-enabled' : '');
		pill.textContent = isDefault ? tr('default', 'Default') : tr('no_label', 'No');
		return pill;
	}

	function renderSettingsSummary(value) {
		const text = document.createElement('div');
		text.className = 'messagehub-settings-summary';
		text.textContent = localizeSummary(getText(value));
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
			throw new Error(tr('editor_template_not_found', 'Transport settings editor template not found.'));
		}

		const fragment = template.content.cloneNode(true);
		const content = fragment.querySelector('#messagehub-transport-settings-editor');

		if(!content) {
			throw new Error(tr('editor_content_not_found', 'Transport settings editor content not found.'));
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
		label.textContent = schemaTr('field_' + key, humanizeKey(key));
		row.appendChild(label);

		let control = null;

		if(type === 'boolean') {
			control = document.createElement('select');
			control.className = 'messagehub-form-select';

			[
				{ value: '1', label: tr('true_label', 'true') },
				{ value: '0', label: tr('false_label', 'false') }
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
				option.textContent = enumLabel(key, entry);
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
			hint.textContent = schemaDescription(key, String(definition.description));
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
			hint.textContent = tr('no_configurable_settings', 'This transport does not expose configurable settings.');
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
			{ key: 'cancel', label: tr('cancel', 'Cancel'), action: 'close' },
			{ key: 'save', label: tr('save', 'Save'), primary: true, busyLabel: tr('saving', 'Saving...'), async action() { await saveSettingsEditor(); } }
		];
	}

	function initSettingsDialog(modularDialogModule) {
		if(settingsDialog) {
			return settingsDialog;
		}

		if(!modularDialogModule || typeof modularDialogModule.createStandardDialog !== 'function') {
			throw new Error(tr('dialog_unavailable', 'ModularDialog createStandardDialog export not found.'));
		}

		const content = createSettingsContent();
		settingsDialog = modularDialogModule.createStandardDialog({
			id: 'messagehub-transport-settings-dialog',
			className: 'messagehub-dialog',
			surfaceClassName: 'messagehub-dialog-surface',
			size: 'large',
			title: tr('transport_settings', 'Transport settings'),
			content,
			status: '',
			closeButtonPlugin: { label: tr('close', 'Close') },
			strings: DIALOG_STRINGS,
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
			log(tr('editor_unavailable', 'Transport settings editor is not available.'));
			return;
		}

		currentTransportRecord = row;
		clearSettingsError();
		settingsDialog.execute('setTitle', tr('edit_settings_title', 'Edit settings: {transport}', { transport: transportLabel(row && row.name, getText(row && row.label, getText(row && row.name, tr('transport', 'Transport')))) }));
		settingsDialog.execute('setButtons', buildSettingsButtons());
		elements.name.value = getText(row && row.name, '');
		elements.key.value = getText(row && row.name, '');
		elements.label.value = transportLabel(row && row.name, getText(row && row.label, ''));
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
				throw new Error(apiError(response, tr('save_failed', 'Save failed.')));
			}

			const name = elements.name ? elements.name.value : '';
			closeSettingsEditor();
			await refreshGrid();
			log(tr('saved_transport_settings', 'Saved transport settings for {transport}.', { transport: getText(name) }));
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
		settingsInitializationError = tr('editor_failed', 'Transport settings editor failed: {error}', { error: getText(error && error.message, String(error)) });
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
		strings: GRID_STRINGS,
		pageSize: BATCH_SIZE,
		plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin, InfiniteScrollPlugin],
		pluginOptions: {
			search: { zone: 'topLine1', order: 10, label: tr('search', 'Search'), placeholder: tr('transport_search_placeholder', 'Search name, label or configuration') },
			filters: { zone: 'topLine2', order: 10, stateKey: 'filters', showClearButton: true, clearLabel: tr('clear_filters', 'Clear filters'), fields: [
				{ key: 'name', label: tr('name', 'Name'), type: 'text', placeholder: tr('name', 'Name'), width: 180 },
				{ key: 'is_enabled', label: tr('enabled', 'Enabled'), type: 'select', options: [{ value: '', label: tr('all', 'All') }, { value: '1', label: tr('enabled_only', 'Enabled only') }, { value: '0', label: tr('disabled_only', 'Disabled only') }] },
				{ key: 'is_default', label: tr('default', 'Default'), type: 'select', options: [{ value: '', label: tr('all', 'All') }, { value: '1', label: tr('default_only', 'Default only') }, { value: '0', label: tr('not_default', 'Not default') }] }
			] },
			reset: { zone: 'topLine1', order: 20, label: tr('reset', 'Reset'), sections: ['query', 'filters', 'columns'] },
			sessionStorage: { key: 'messagehub-transport-grid', sections: ['query', 'filters', 'columns'] },
			info: { zone: 'statusZone', order: 10, displayMode: 'loaded' },
			infiniteScroll: { threshold: 180, pageSize: BATCH_SIZE, containerSelector: '.mg-table-scroll' },
			rowActions: { items: [
				{ key: 'default', label: tr('set_as_default', 'Set as default'), async onClick(context) { await postJson({ mode: 'save-default', default_transport: context.row.name }); await refreshGrid(); log(tr('default_transport_set', 'Default transport set to {transport}.', { transport: context.row.name })); } },
				{ key: 'toggle-enabled', label: tr('toggle_enabled', 'Toggle enabled'), async onClick(context) {
					const enabled = String(context.row && context.row.is_enabled ? context.row.is_enabled : '0') !== '1';
					await postJson({ mode: 'set-enabled', name: context.row.name, enabled });
					await refreshGrid();
					log(enabled ? tr('enabled_transport', 'Enabled {transport}.', { transport: context.row.name }) : tr('disabled_transport', 'Disabled {transport}.', { transport: context.row.name }));
				} },
				{ key: 'settings', label: tr('edit_settings', 'Edit settings'), onClick(context) { openSettingsEditor(context.row); } },
				{ key: 'reset-settings', label: tr('reset_settings', 'Reset settings'), async onClick(context) {
					if(!window.confirm(tr('reset_settings_confirm', 'Reset all stored settings for {transport}?', { transport: context.row.name }))) {
						return;
					}

					await postJson({ mode: 'reset-transport', name: context.row.name });
					await refreshGrid();
					log(tr('reset_transport_settings', 'Reset transport settings for {transport}.', { transport: context.row.name }));
				} }
			] }
		},
		columns: [
			{ key: 'name', label: tr('name', 'Name'), width: 180 },
			{ key: 'label', label: tr('label', 'Label'), width: 260, render(value, row) { return transportLabel(row && row.name, getText(value)); } },
			{ key: 'is_enabled', label: tr('enabled', 'Enabled'), width: 110, render: renderEnabled },
			{ key: 'is_default', label: tr('default', 'Default'), width: 100, render: renderDefault },
			{ key: 'settings_summary', label: tr('configuration', 'Configuration'), width: 520, render: renderSettingsSummary },
			{ key: 'schema_json', label: tr('schema', 'Schema'), width: 520, visible: false, render: renderPre }
		]
	});
	await grid.init();
	log(settingsInitializationError !== '' ? settingsInitializationError : tr('transports_loaded', 'Transports loaded.'));
</script>
