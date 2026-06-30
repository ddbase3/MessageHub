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

<?php $templateOptions = $this->_['templateOptions']; ?>
<div class="messagehub-shell"><h1>Message variants</h1><p>Language-specific subject, plain text and HTML body variants.</p><label>Template for new variants: <select id="messagehub-variant-template"><?php foreach($templateOptions as $option): ?><option value="<?php echo htmlspecialchars((string)$option['value'], ENT_QUOTES); ?>"><?php echo htmlspecialchars((string)$option['label'], ENT_QUOTES); ?></option><?php endforeach; ?></select></label><div class="messagehub-grid"><div id="messagehub-variant-grid"></div><div id="messagehub-variant-output" class="messagehub-output"></div></div></div>
<script type="module">
	const TEMPLATE_OPTIONS = <?php echo json_encode($templateOptions, JSON_UNESCAPED_SLASHES); ?>;
	const { AjaxAdapter, ModularGrid, SearchPlugin, HeaderMenuPlugin, InfoPlugin, RowActionsPlugin, ResetPlugin, SessionStoragePlugin } = await import(new URL(<?php echo json_encode($modularGridJsUrl, JSON_UNESCAPED_SLASHES); ?>, document.baseURI).href);
	const ENDPOINT_URL = <?php echo json_encode((string)$this->_['service'], JSON_UNESCAPED_SLASHES); ?>; let grid=null; const log=t=>{const e=document.querySelector('#messagehub-variant-output');if(e)e.textContent=t||''}; async function postJson(payload){const r=await fetch(ENDPOINT_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});return r.json();}
	async function save(row={}){ const templateSelect = document.querySelector('#messagehub-variant-template'); const templateId = row.template_id || (templateSelect ? templateSelect.value : (TEMPLATE_OPTIONS[0] ? TEMPLATE_OPTIONS[0].value : '')); if(!templateId){alert('Create a template first.');return;} const language=prompt('Language', row.language||'en')||'en'; const subject=prompt('Subject', row.subject||'')||''; const bodyText=prompt('Plain text body', row.body_text||'')||''; const bodyHtml=prompt('HTML body (optional)', row.body_html||'')||''; const r=await postJson({mode:'save',id:row.id||'',template_id:templateId,language,subject,body_text:bodyText,body_html:bodyHtml,enabled:'1'}); await grid.execute('reloadData'); log(r.ok?'Saved variant.':(r.error||'Save failed.')); }
	const layout={type:'stack',children:[{type:'zone',key:'topLine1',className:'messagehub-panel'},{type:'view',key:'main',className:'messagehub-main'},{type:'zone',key:'statusZone',className:'messagehub-panel'}]}; const adapter=new AjaxAdapter({url:ENDPOINT_URL,method:'POST',rowsPath:'data',totalPath:'total',mapRequest(req){return{mode:'page',page:req.page||1,pageSize:req.pageSize||50,search:req.search||''};}});
	grid=new ModularGrid('#messagehub-variant-grid',{layout,adapter,dataMode:'server',features:{paging:true},plugins:[SearchPlugin,HeaderMenuPlugin,InfoPlugin,RowActionsPlugin,ResetPlugin,SessionStoragePlugin],pluginOptions:{search:{zone:'topLine1',order:10,label:'Search',placeholder:'Search type, language, subject or body'},reset:{zone:'topLine1',order:30,label:'Reset',sections:['query','columns']},sessionStorage:{key:'messagehub-variant-grid',sections:['query','columns']},info:{zone:'statusZone',order:10,displayMode:'loaded'},rowActions:{items:[{key:'edit',label:'Edit',onClick(ctx){save(ctx.row)}},{key:'delete',label:'Delete',async onClick(ctx){if(confirm('Delete variant '+ctx.row.language+'?')){await postJson({mode:'delete',id:ctx.row.id});await grid.execute('reloadData');}}}]}},columns:[{key:'type_name',label:'Template',width:220},{key:'language',label:'Language',width:100},{key:'subject',label:'Subject',width:360},{key:'body_text_preview',label:'Body preview',width:520},{key:'enabled_label',label:'State',width:100},{key:'id',label:'ID',width:260,visible:false}]}); await grid.init(); const b=document.createElement('button'); b.className='messagehub-button messagehub-button-primary'; b.textContent='Add variant'; b.onclick=()=>save({}); document.querySelector('#messagehub-variant-grid .messagehub-panel')?.prepend(b); log('Variants loaded.');
</script>
