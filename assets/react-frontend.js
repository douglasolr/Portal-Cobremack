/* PWL Enquetes 2.16.1 | Single-screen voting with confirmation dialog. WordPress remains authoritative. */
(function () {
  'use strict';
  if (!window.wp || !window.wp.element || !window.wp.element.createRoot || !window.PWLEnquetes) return;
  var element = window.wp.element, e = element.createElement, useState = element.useState;
  function Questionnaire(props) {
    var selection = useState([]), selected = selection[0], setSelected = selection[1];
    var confirmation = useState(false), confirming = confirmation[0], setConfirming = confirmation[1];
    var busy = useState(false), sending = busy[0], setSending = busy[1];
    var feedback = useState(''), message = feedback[0], setMessage = feedback[1];
    var success = useState(false), done = success[0], setDone = success[1];
    function toggle(key) {
      if (sending) return;
      setSelected(props.multiple ? (selected.indexOf(key) >= 0 ? selected.filter(function (x) { return x !== key; }) : selected.concat(key)) : [key]);
      setMessage('');
    }
    function review(ev) {
      ev.preventDefault();
      if (!selected.length) { setMessage('Selecione pelo menos uma alternativa.'); return; }
      setMessage(''); setConfirming(true);
    }
    function submit(ev) {
      ev.preventDefault();
      if (sending || !selected.length) return;
      var body = new FormData();
      body.append('action', 'pwl_registrar_voto'); body.append('poll_id', props.pollId); body.append('nonce', props.nonce);
      selected.forEach(function (key) { body.append(props.multiple ? 'choices[]' : 'choices', key); });
      setSending(true); setMessage('Registrando seu voto...');
      fetch(window.PWLEnquetes.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
        .then(function (response) { if (!response.ok) throw new Error('Falha de comunicação. Tente novamente.'); return response.json(); })
        .then(function (response) {
          if (!response.success) throw new Error(response.data && response.data.message || window.PWLEnquetes.error);
          setConfirming(false); setDone(true); setMessage(response.data && response.data.message || 'Voto registrado com sucesso.');
          if (response.data && response.data.showResults) {
            var results = props.poll.querySelector('.pwl-poll__results');
            if (results) { results.innerHTML = response.data.resultsHtml || ''; results.hidden = false; }
          }
        })
        .catch(function (error) { setMessage(error.message || window.PWLEnquetes.error); })
        .finally(function () { setSending(false); });
    }
    return e('div', { className: 'pwle-react-questionnaire' },
      !done && e('form', { onSubmit: review, className: 'pwle-react-questionnaire__form' },
        e('p', { className: 'pwle-react-questionnaire__description' }, props.multiple ? 'Selecione uma ou mais alternativas.' : 'Selecione uma alternativa.'),
        e('div', { className: 'pwle-react-questionnaire__choices', role: 'group', 'aria-label': 'Alternativas da enquete' },
          props.options.map(function (option) {
            var active = selected.indexOf(option.key) !== -1;
            return e('label', { key: option.key, className: 'pwle-react-questionnaire__choice' + (active ? ' is-selected' : '') },
              e('input', { type: props.multiple ? 'checkbox' : 'radio', name: 'pwle-choice-' + props.pollId, value: option.key, checked: active, onChange: function () { toggle(option.key); } }),
              e('span', { className: 'pwle-react-questionnaire__indicator', 'aria-hidden': 'true' }, active ? '✓' : ''),
              e('span', { className: 'pwle-react-questionnaire__choice-content', dangerouslySetInnerHTML: { __html: option.content } })
            );
          })
        ),
        e('div', { className: 'pwle-react-questionnaire__actions' },
          e('span', { className: 'pwle-react-questionnaire__count' }, selected.length + (selected.length === 1 ? ' alternativa selecionada' : ' alternativas selecionadas')),
          e('button', { type: 'submit', disabled: !selected.length, className: 'pwl-poll__submit' }, 'Votar')
        )
      ),
      !done && confirming && e('div', { className: 'pwle-vote-confirm-overlay', onMouseDown: function (ev) { if (ev.target === ev.currentTarget && !sending) setConfirming(false); } },
        e('div', { className: 'pwle-vote-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pwle-vote-confirm-title-' + props.pollId, onKeyDown: function (ev) { if (ev.key === 'Escape' && !sending) setConfirming(false); } },
          e('h3', { id: 'pwle-vote-confirm-title-' + props.pollId }, 'Confirmar voto'),
          e('p', null, 'Tem certeza que deseja votar em ', e('strong', null, props.options.filter(function (option) { return selected.indexOf(option.key) !== -1; }).map(function (option) { var temp = document.createElement('div'); temp.innerHTML = option.content; return (temp.textContent || '').trim(); }).join(', ')), '?'),
          e('div', { className: 'pwle-vote-confirm__actions' },
            e('button', { type: 'button', className: 'pwl-poll__submit', disabled: sending, onClick: submit, autoFocus: true }, sending ? 'Registrando...' : 'Sim, votar'),
            e('button', { type: 'button', className: 'pwle-react-questionnaire__back', disabled: sending, onClick: function () { setConfirming(false); setMessage(''); } }, 'Não, cancelar')
          )
        )
      ),
      message && e('div', { className: 'pwl-poll__feedback' + (done ? ' pwl-poll__feedback--success' : sending ? '' : ' pwl-poll__feedback--error'), role: 'status', 'aria-live': 'polite' }, message)
    );
  }
  function mount() {
    document.querySelectorAll('.pwl-poll__form:not([data-react-mounted])').forEach(function (form) {
      var poll = form.closest('.pwl-poll'); if (!poll) return;
      var options = Array.prototype.map.call(form.querySelectorAll('.pwl-poll__option'), function (label) {
        var input = label.querySelector('input');
        return input && { key: input.value, content: label.innerHTML.replace(/<input\b[^>]*>/i, '') };
      }).filter(Boolean);
      var id = form.querySelector('[name="poll_id"]'), nonce = form.querySelector('[name="nonce"]');
      if (!id || !nonce || !options.length) return;
      var root = document.createElement('div'); root.className = 'pwle-react-root';
      form.dataset.reactMounted = '1'; form.replaceWith(root);
      element.createRoot(root).render(e(Questionnaire, { poll: poll, pollId: id.value, nonce: nonce.value, options: options, multiple: !!form.querySelector('input[type="checkbox"]') }));
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
  new MutationObserver(mount).observe(document.documentElement, { childList: true, subtree: true });
})();
