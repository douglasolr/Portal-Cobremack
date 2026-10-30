(function(){'use strict';
function init(){
 var form=document.getElementById('pwlPollWorkspaceForm'),E=window.wp&&window.wp.element;
 if(!form||!E||!E.createRoot)return;
 var anchor=form.querySelector('.pwle-editor__main'),list=form.querySelector('#pwl-options-list');if(!anchor||!list)return;
 var h=E.createElement, mount=document.createElement('div');mount.className='pwle-react-editor-controls';anchor.insertBefore(mount,anchor.firstChild);
 function rows(){return Array.prototype.slice.call(list.querySelectorAll('.pwle-option-row'));}
 function read(){return {title:(form.querySelector('[name="title"]')||{}).value||'',type:(form.querySelector('[name="pwl_type"]:checked')||{}).value||'single',visibility:(form.querySelector('[name="pwl_results_visibility"]:checked')||{}).value||'after_vote',persistent:!!(form.querySelector('[name="pwl_persistent_until_vote"]')||{}).checked,closes:(form.querySelector('[name="pwl_closes_at"]')||{}).value||'',options:rows().map(function(r){return {key:(r.querySelector('[name="pwl_option_keys[]"]')||{}).value||'',label:(r.querySelector('[name="pwl_option_labels[]"]')||{}).value||'',emoji:(r.querySelector('[name="pwl_option_emojis[]"]')||{}).value||'',image:!!((r.querySelector('[name="pwl_option_image_ids[]"]')||{}).value),imageUrl:(r.querySelector('.pwle-option-image-preview img')||{}).src||''};})};}
 function notify(el,value){if(!el)return;var desc=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value');desc.set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));}
 function App(){var s=E.useState(read()),data=s[0],setData=s[1],selection=E.useState([]),selected=selection[0],setSelected=selection[1],pendingState=E.useState(null),pending=pendingState[0],setPending=pendingState[1];
 E.useEffect(function(){var observer=new MutationObserver(function(){setData(read());});observer.observe(list,{childList:true,subtree:true});var sync=function(){setData(read());};form.addEventListener('input',sync);form.addEventListener('change',sync);return function(){observer.disconnect();form.removeEventListener('input',sync);form.removeEventListener('change',sync);};},[]);
 E.useEffect(function(){if(pending===null)return;function onKey(event){if(event.key==='Escape')setPending(null);}document.addEventListener('keydown',onKey);return function(){document.removeEventListener('keydown',onKey);};},[pending]);
 function changeTitle(value){notify(form.querySelector('[name="title"]'),value);setData(read());}
 function changeOption(i,name,value){var r=rows()[i];if(r)notify(r.querySelector('[name="'+name+'[]"]'),value);setData(read());}
 function setRadio(name,value){var el=form.querySelector('[name="'+name+'"][value="'+value+'"]');if(el){el.checked=true;el.dispatchEvent(new Event('change',{bubbles:true}));}setData(read());}
 function setField(name,value){notify(form.querySelector('[name="'+name+'"]'),value);setData(read());}
 function setCheck(name,value){var el=form.querySelector('[name="'+name+'"]');if(el){el.checked=value;el.dispatchEvent(new Event('change',{bubbles:true}));}setData(read());}
 function clickOption(i,selector){var r=rows()[i],button=r&&r.querySelector(selector);if(button)button.click();setData(read());}
 function move(i,delta){var a=rows(),r=a[i],target=a[i+delta];if(!r||!target)return;if(delta<0)list.insertBefore(r,target);else list.insertBefore(target,r);list.dispatchEvent(new Event('change',{bubbles:true}));setData(read());}
 return h('div',{className:'pwle-react-layout'},
 h('section',{className:'pwl-card pwle-react-edit-card'},h('div',{className:'pwl-card__header'},h('div',null,h('h3',{className:'pwl-card__title'},'Editor de enquete'),h('p',{className:'pwl-card__description'},'Edite as alternativas, escolha emojis e imagens e organize a ordem. O formulário original continua responsável pelo salvamento.'))),
 h('div',{className:'pwl-card__body pwle-react-edit-body'},
 h('div',{className:'pwle-react-editor-intro'},h('span',{className:'pwle-react-editor-kicker'},'CONTEÚDO DA ENQUETE'),h('p',null,'Defina a pergunta e organize as alternativas. As alterações são sincronizadas com o formulário de gravação.')), 
 h('label',{className:'pwl-field'},h('span',{className:'pwl-field__label'},'Pergunta'),h('input',{className:'pwl-input',type:'text',maxLength:180,value:data.title,onChange:function(e){changeTitle(e.target.value);}})),
 h('div',{className:'pwle-react-options'},h('div',{className:'pwle-react-options-heading'},h('span',{className:'pwl-field__label'},'Alternativas'),h('span',{className:'pwle-react-count'},data.options.length+' opções')),data.options.map(function(o,i){return h('div',{className:'pwle-react-option',key:o.key||i},
 h('span',{className:'pwle-react-edit-index'},i+1),
 h('input',{className:'pwl-input pwle-react-emoji',type:'text',maxLength:12,'aria-label':'Emoji da alternativa '+(i+1),placeholder:'😀',value:o.emoji,onChange:function(e){changeOption(i,'pwl_option_emojis',e.target.value);}}),
 h('input',{className:'pwl-input pwle-react-label',type:'text',maxLength:180,required:true,'aria-label':'Texto da alternativa '+(i+1),value:o.label,onChange:function(e){changeOption(i,'pwl_option_labels',e.target.value);}}),
 h('div',{className:'pwle-react-tools'},
 h('button',{type:'button',className:'pwl-button pwl-button--ghost',title:'Escolher emoji',onClick:function(){clickOption(i,'.pwle-emoji-open');}},'☺'),
 h('button',{type:'button',className:'pwl-button pwl-button--ghost',title:'Selecionar imagem',onClick:function(){clickOption(i,'.pwle-option-image-select');}},o.image?'Imagem ✓':'Imagem'),
 o.image?h('button',{type:'button',className:'pwl-button pwl-button--ghost',title:'Remover imagem',onClick:function(){clickOption(i,'.pwle-option-image-remove');}},'×'):null,
 h('button',{type:'button',className:'pwl-button pwl-button--ghost',disabled:i===0,title:'Mover para cima',onClick:function(){move(i,-1);}},'↑'),
 h('button',{type:'button',className:'pwl-button pwl-button--ghost',disabled:i===data.options.length-1,title:'Mover para baixo',onClick:function(){move(i,1);}},'↓'),
 h('button',{type:'button',className:'pwl-button pwl-button--ghost',disabled:data.options.length<=2,title:'Remover alternativa',onClick:function(){setPending({key:o.key,index:i,label:o.label});}},'×')),
 o.imageUrl?h('img',{className:'pwle-react-option-image',src:o.imageUrl,alt:''}):null);})),
 h('button',{type:'button',className:'pwl-button pwl-button--secondary',onClick:function(){var button=form.querySelector('#pwl-add-option');if(button)button.click();setData(read());}},'Adicionar alternativa'))),
 h('section',{className:'pwl-card pwle-react-settings-card'},h('div',{className:'pwl-card__header'},h('div',null,h('h3',{className:'pwl-card__title'},'Configurações da enquete'))),h('div',{className:'pwl-card__body pwle-react-settings-body'},
 h('label',{className:'pwl-field'},h('span',{className:'pwl-field__label'},'Tipo de votação'),h('select',{className:'pwl-input',value:data.type,onChange:function(e){setRadio('pwl_type',e.target.value);}},h('option',{value:'single'},'Escolha única'),h('option',{value:'multiple'},'Múltiplas escolhas'))),
 h('label',{className:'pwl-field'},h('span',{className:'pwl-field__label'},'Exibição dos resultados'),h('select',{className:'pwl-input',value:data.visibility,onChange:function(e){setRadio('pwl_results_visibility',e.target.value);}},h('option',{value:'after_vote'},'Após votar'),h('option',{value:'always'},'Sempre visíveis'),h('option',{value:'admin'},'Somente administradores'))),
 h('label',{className:'pwle-react-check'},h('input',{type:'checkbox',checked:data.persistent,onChange:function(e){setCheck('pwl_persistent_until_vote',e.target.checked);}}),'Manter popup até o colaborador votar'),
 h('label',{className:'pwl-field'},h('span',{className:'pwl-field__label'},'Encerramento'),h('input',{className:'pwl-input',type:'datetime-local',value:data.closes,onChange:function(e){setField('pwl_closes_at',e.target.value);}})))),
 h('section',{className:'pwl-card pwle-react-preview-card'},h('div',{className:'pwl-card__header'},h('div',null,h('h3',{className:'pwl-card__title'},'Prévia da votação'),h('p',{className:'pwl-card__description'},'Simulação local. Nenhum voto será registrado.'))),h('div',{className:'pwl-card__body'},h('h4',null,data.title||'Sua pergunta aparecerá aqui'),h('p',null,data.type==='multiple'?'Selecione uma ou mais alternativas':'Selecione uma alternativa'),h('div',{className:'pwle-react-preview-choices'},data.options.filter(function(o){return o.label.trim();}).map(function(o){var active=selected.indexOf(o.key)!==-1;return h('button',{type:'button',key:o.key,className:'pwle-react-preview-choice'+(active?' is-selected':''),'aria-pressed':active,onClick:function(){setSelected(function(old){return data.type==='single'?[o.key]:old.indexOf(o.key)!==-1?old.filter(function(k){return k!==o.key;}):old.concat(o.key);});}},h('span',{className:'pwle-react-preview-indicator','aria-hidden':'true'},active?'✓':''),o.imageUrl?h('img',{className:'pwle-react-preview-thumb',src:o.imageUrl,alt:''}):null,h('span',null,(o.emoji?o.emoji+' ':'')+o.label));})),h('small',null,'Prévia de seleção, sem envio de voto.'))),
 pending!==null?h('div',{className:'pwle-react-dialog-backdrop',onMouseDown:function(e){if(e.target===e.currentTarget)setPending(null);}},
 h('div',{className:'pwle-react-dialog',role:'alertdialog','aria-modal':'true','aria-labelledby':'pwle-dialog-title','aria-describedby':'pwle-dialog-description'},
 h('h3',{id:'pwle-dialog-title'},'Excluir alternativa?'),
 h('p',{id:'pwle-dialog-description'},'A alternativa será removida do formulário. A alteração só será gravada ao salvar a enquete.'),
 h('div',{className:'pwle-react-dialog-choice'},pending.label||'Alternativa sem texto'),
 h('div',{className:'pwle-react-dialog-actions'},
 h('button',{type:'button',className:'pwl-button pwl-button--secondary',onClick:function(){setPending(null);},ref:function(el){if(el&&!el.dataset.pwleFocused){el.dataset.pwleFocused='1';el.focus();}}},'Cancelar'),
 h('button',{type:'button',className:'pwl-button pwle-react-danger',onClick:function(){var current=rows().findIndex(function(r){return (r.querySelector('[name=\"pwl_option_keys[]\"]')||{}).value===pending.key;});if(current<0)current=pending.index;if(rows().length>2)clickOption(current,'.pwl-remove-option');setPending(null);}},'Excluir alternativa')))):null);
 }
 try{E.createRoot(mount).render(h(App));form.classList.add('pwle-react-editor-ready');}catch(e){mount.remove();}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
