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

<div class="messagehub-shell"><h1>Message templates</h1><p>Template definitions identify a message type and scope. Variants contain the actual subject and body per language.</p><div class="messagehub-grid"><div id="messagehub-template-grid"></div><div id="messagehub-template-output" class="messagehub-output"></div></div></div>
<script type="module">
	const { AjaxAdapter, ModularGrid, SearchPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);
	const ENDPOINT_URL = <?php echo json_encode((string)$this->_['service'], JSON_UNESCAPED_SLASHES); ?>; let grid = null; const log = (t) => { const e=document.querySelector('#messagehub-template-output'); if(e)e.textContent=t||''; };
	async function postJson(payload){ const r=await fetch(ENDPOINT_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); return r.json(); }
	async function save(row={}){ const typeName=prompt('Type name', row.type_name || ''); if(!typeName)return; const label=prompt('Label', row.label || typeName) || typeName; const description=prompt('Description', row.description || '') || ''; const transport=prompt('Default transport', row.default_transport || '') || ''; const r=await postJson({mode:'save', id:row.id||'', type_name:typeName, label, description, scope_type:row.scope_type || 'global', scope_id:row.scope_id || '', default_transport:transport, enabled:'1'}); await grid.execute('reloadData'); log(r.ok ? 'Saved template.' : (r.error || 'Save failed.')); }
	const layout={type:'stack',children:[{type:'zone',key:'topLine1',className:'messagehub-panel'},{type:'view',key:'main',className:'messagehub-main'},{type:'zone',key:'statusZone',className:'messagehub-panel'}]};
	const adapter=new AjaxAdapter({url:ENDPOINT_URL,method:'POST',rowsPath:'data',totalPath:'total',mapRequest(req){return{mode:'page',page:req.page||1,pageSize:req.pageSize||50,search:req.search||''};}});
	grid=new ModularGrid('#messagehub-template-grid',{layout,adapter,dataMode:'server',features:{paging:true},plugins:[SearchPlugin,HeaderMenuPlugin,InfoPlugin,RowActionsPlugin,ResetPlugin,SessionStoragePlugin],pluginOptions:{search:{zone:'topLine1',order:10,label:'Search',placeholder:'Search type, label or description'},reset:{zone:'topLine1',order:30,label:'Reset',sections:['query','columns']},sessionStorage:{key:'messagehub-template-grid',sections:['query','columns']},info:{zone:'statusZone',order:10,displayMode:'loaded'},rowActions:{items:[{key:'edit',label:'Edit',onClick(ctx){save(ctx.row)}},{key:'delete',label:'Delete',async onClick(ctx){if(confirm('Delete template '+ctx.row.type_name+'?')){await postJson({mode:'delete',id:ctx.row.id});await grid.execute('reloadData');}}}]}},columns:[{key:'type_name',label:'Type',width:260},{key:'label',label:'Label',width:260},{key:'description',label:'Description',width:420},{key:'default_transport',label:'Transport',width:150},{key:'enabled_label',label:'State',width:100},{key:'id',label:'ID',width:260,visible:false}]});
	await grid.init(); const b=document.createElement('button'); b.className='messagehub-button messagehub-button-primary'; b.textContent='Add template'; b.onclick=()=>save({}); document.querySelector('#messagehub-template-grid .messagehub-panel')?.prepend(b); log('Templates loaded.');
</script>
