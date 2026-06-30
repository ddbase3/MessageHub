<?php
$resolve = $this->_['resolve'];
$modularGridCssUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css');
$modularGridJsUrl = (string) $resolve('plugin/ClientStack/assets/modulargrid/index.js');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($modularGridCssUrl, ENT_QUOTES); ?>" />
<style>
	.messagehub-shell { max-width: 1700px; }
	.messagehub-shell h1 { margin: 0 0 8px 0; font-size: 24px; font-weight: 600; }
	.messagehub-shell p { margin: 0 0 16px 0; color: #555; max-width: 1200px; line-height: 1.45; }
	.messagehub-panel { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; overflow-x: auto; }
	.messagehub-main { border: 1px solid #e2e2e2; border-radius: 8px; background: #fff; padding: 4px 0; }
	.messagehub-grid .mg-table-scroll { height: 580px; overflow: auto; }
	.messagehub-grid .mg-table th, .messagehub-grid .mg-table td { padding: 6px 8px; font-size: 13px; vertical-align: top; }
	.messagehub-button { appearance: none; border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; color: #222; cursor: pointer; font: inherit; font-size: 13px; padding: 4px 10px; white-space: nowrap; }
	.messagehub-button-primary { background: #2f5d91; border-color: #2f5d91; color: #fff; }
	.messagehub-output { margin-top: 12px; padding: 8px 0 0 0; border-top: 1px solid #e2e2e2; color: #555; font-size: 13px; }
	.messagehub-pill { display: inline-flex; padding: 1px 6px; border: 1px solid #d6d6d6; border-radius: 999px; background: #fafafa; font-size: 11px; }
	.messagehub-pill-sent { background: #eef7ee; border-color: #bddfbd; color: #226622; }
	.messagehub-pill-failed { background: #fff0f0; border-color: #e4b9b9; color: #8a1f1f; }
</style>

<div class="messagehub-shell">
	<h1>Messaging queue</h1>
	<p>Queued, retrying and processed messages. The worker uses the same queue and delivery services as this administration display.</p>
	<div class="messagehub-grid"><div id="messagehub-queue-grid"></div><div id="messagehub-queue-output" class="messagehub-output"></div></div>
</div>
<script type="module">
	const { AjaxAdapter, ModularGrid, SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);
	const ENDPOINT_URL = <?php echo json_encode((string)$this->_['service'], JSON_UNESCAPED_SLASHES); ?>;
	let grid = null;
	function setLog(text) { const el = document.querySelector('#messagehub-queue-output'); if(el) el.textContent = text || ''; }
	async function postJson(payload) { const r = await fetch(ENDPOINT_URL, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)}); return r.json(); }
	function statusPill(value) { const span = document.createElement('span'); span.className = 'messagehub-pill messagehub-pill-' + String(value); span.textContent = String(value || '-'); return span; }
	const layout = { type: 'stack', children: [ {type:'zone', key:'topLine1', className:'messagehub-panel'}, {type:'zone', key:'topLine2', className:'messagehub-panel'}, {type:'view', key:'main', className:'messagehub-main'}, {type:'zone', key:'statusZone', className:'messagehub-panel'} ]};
	const adapter = new AjaxAdapter({ url: ENDPOINT_URL, method: 'POST', rowsPath: 'data', totalPath: 'total', mapRequest(request) { const state = grid ? grid.getState() : {}; return { mode: 'page', page: request.page || 1, pageSize: request.pageSize || 50, search: request.search || '', filters: state.filters || {} }; }});
	grid = new ModularGrid('#messagehub-queue-grid', { layout, adapter, dataMode: 'server', features: { paging: true }, plugins: [SearchPlugin, FiltersPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin], pluginOptions: { search: { zone:'topLine1', order:10, label:'Search', placeholder:'Search id, type, subject or error' }, filters: { zone:'topLine2', order:10, stateKey:'filters', showClearButton:true, fields:[{key:'status', label:'Status', type:'select', options:[{value:'', label:'All statuses'}, {value:'queued', label:'Queued'}, {value:'retry_wait', label:'Retry wait'}, {value:'processing', label:'Processing'}, {value:'sent', label:'Sent'}, {value:'failed', label:'Failed'}, {value:'cancelled', label:'Cancelled'}]}, {key:'transport_name', label:'Transport', type:'text', placeholder:'Transport'}, {key:'type_name', label:'Type', type:'text', placeholder:'Type'}]}, reset: {zone:'topLine1', order:20, label:'Reset', sections:['query','filters','columns']}, sessionStorage: {key:'messagehub-queue-grid', sections:['query','filters','columns']}, info: {zone:'statusZone', order:10, displayMode:'loaded'}, rowActions:{items:[{key:'cancel', label:'Cancel', async onClick(ctx){ await postJson({mode:'cancel', id:ctx.row.id}); await grid.execute('reloadData'); setLog('Cancelled ' + ctx.row.id); }}]} }, columns:[ {key:'created_at', label:'Created', width:170}, {key:'status', label:'Status', width:120, render: statusPill}, {key:'type_name', label:'Type', width:220}, {key:'subject', label:'Subject', width:360}, {key:'transport_name', label:'Transport', width:140}, {key:'attempts', label:'Attempts', width:100}, {key:'last_error', label:'Last error', width:360}, {key:'id', label:'ID', width:260, visible:false} ]});
	await grid.init();
	const button = document.createElement('button'); button.className = 'messagehub-button messagehub-button-primary'; button.textContent = 'Process batch'; button.onclick = async () => { const r = await postJson({mode:'process', limit:20}); await grid.execute('reloadData'); setLog('Processed ' + String(r.processed || 0) + ' queued messages.'); }; document.querySelector('#messagehub-queue-grid .messagehub-panel')?.prepend(button);
	setLog('Queue loaded.');
</script>
