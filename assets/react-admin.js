(function(){
  'use strict';
  function start(){
    var root=document.getElementById('pwle-react-admin-nav');
    var original=document.querySelector('nav.pwle-workspace__tabs');
    var wpElement=window.wp&&window.wp.element;
    if(!root||!original||!wpElement||!wpElement.createRoot)return;
    var h=wpElement.createElement;
    var items=Array.prototype.map.call(original.querySelectorAll('a'),function(a){
      var icon=a.querySelector('.dashicons');
      return {href:a.href,title:(a.querySelector('strong')||a).textContent.trim(),icon:icon?icon.className:'',active:a.classList.contains('is-selected')};
    });
    if(!items.length)return;
    function Navigation(){
      var search=wpElement.useState('');
      var query=search[0],setQuery=search[1];
      var table=document.querySelector('.pwle-workspace__content table');
      wpElement.useEffect(function(){
        if(!table)return;
        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'),function(row){
          row.hidden=!!query&&row.textContent.toLocaleLowerCase().indexOf(query.toLocaleLowerCase())===-1;
        });
        return function(){Array.prototype.forEach.call(table.querySelectorAll('tbody tr'),function(row){row.hidden=false;});};
      },[query]);
      return h('div',{className:'pwle-react-shell'},
        h('nav',{className:'pwle-react-tablist',role:'navigation','aria-label':'Seções do módulo'},
          items.map(function(item){return h('a',{key:item.href,href:item.href,className:'pwle-react-tab'+(item.active?' is-active':''),'aria-current':item.active?'page':undefined},h('span',null,item.title));})
        ),
        table?h('div',{className:'pwle-react-toolbar'},
          h('label',{className:'pwle-react-search'},h('span',{className:'dashicons dashicons-search','aria-hidden':'true'}),h('span',{className:'screen-reader-text'},'Buscar registros'),h('input',{type:'search',value:query,onChange:function(e){setQuery(e.target.value);},placeholder:'Pesquisar enquetes...',autoComplete:'off'}))
        ):null
      );
    }
    try{
      var reactRoot=wpElement.createRoot(root);
      reactRoot.render(h(Navigation));
      original.classList.add('pwle-legacy-nav-ready');
      root.classList.add('is-mounted');
    }catch(e){root.classList.remove('is-mounted');original.classList.remove('pwle-legacy-nav-ready');}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
