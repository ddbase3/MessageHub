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

<div class="messagehub-shell"><h1>Message transports</h1><p>Discoverable transports and their active settings.</p><div class="messagehub-grid"><div id="messagehub-transport-grid"></div><div id="messagehub-transport-output" class="messagehub-output"></div></div></div>
<script type="module">
	const { AjaxAdapter, ModularGrid, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin } = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href); const ENDPOINT_URL=<?php echo json_encode((string)$this->_['service'], JSON_UNESCAPED_SLASHES); ?>; let grid=null; const log=t=>{const e=document.querySelector('#messagehub-transport-output');if(e)e.textContent=t||''}; async function postJson(payload){const r=await fetch(ENDPOINT_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});return r.json();}
	const layout={type:'stack',children:[{type:'zone',key:'topLine1',className:'messagehub-panel'},{type:'view',key:'main',className:'messagehub-main'},{type:'zone',key:'statusZone',className:'messagehub-panel'}]}; const adapter=new AjaxAdapter({url:ENDPOINT_URL,method:'POST',rowsPath:'data',totalPath:'total',mapRequest(){return{mode:'page',page:1,pageSize:250};}});
	grid=new ModularGrid('#messagehub-transport-grid',{layout,adapter,dataMode:'server',features:{paging:false},plugins:[HeaderMenuPlugin,InfoPlugin,RowActionsPlugin,ResetPlugin],pluginOptions:{info:{zone:'statusZone',order:10,displayMode:'loaded'},rowActions:{items:[{key:'default',label:'Set as default',async onClick(ctx){await postJson({mode:'save-default',default_transport:ctx.row.name});await grid.execute('reloadData');log('Default transport set to '+ctx.row.name);}},{key:'settings',label:'Edit settings JSON',async onClick(ctx){const text=prompt('Settings JSON for '+ctx.row.name, ctx.row.settings_json || '{}'); if(text===null)return; let parsed={}; try{parsed=JSON.parse(text||'{}');}catch(e){alert('Invalid JSON: '+e.message);return;} const r=await postJson({mode:'save-transport',name:ctx.row.name,settings:parsed}); await grid.execute('reloadData'); log(r.ok?'Saved transport settings.':(r.error||'Save failed.'));}}]}},columns:[{key:'name',label:'Name',width:180},{key:'label',label:'Label',width:260},{key:'is_default',label:'Default',width:100},{key:'settings_json',label:'Settings',width:520},{key:'schema_json',label:'Schema',width:520}]}); await grid.init(); log('Transports loaded.');
</script>
