// Editor visual do blog (Toast UI Editor) — grava Markdown no textarea original
// e faz rascunho automático enquanto a pessoa escreve.
(function () {
  'use strict';
  var textarea = document.getElementById('body_md');
  var mount = document.getElementById('blog-editor');
  if (!textarea || !mount || !window.toastui || !window.toastui.Editor) return;

  var form = textarea.closest('form');
  var csrfInput = form && form.querySelector('input[name="csrf"]');
  var csrf = csrfInput ? csrfInput.value : '';
  var uploadUrl = mount.getAttribute('data-upload') || 'blog.php';
  var autosaveUrl = mount.getAttribute('data-autosave') || '';
  var idInput = form && form.querySelector('input[name="id"]');
  var titleInput = form && form.querySelector('input[name="title"]');
  var statusEl = document.getElementById('autosave-status');

  mount.style.display = 'block';
  textarea.style.display = 'none';

  var editor = new window.toastui.Editor({
    el: mount,
    height: '560px',
    initialEditType: 'wysiwyg',
    previewStyle: 'vertical',
    language: 'pt-BR',
    usageStatistics: false,
    initialValue: textarea.value || '',
    toolbarItems: [
      ['heading', 'bold', 'italic', 'strike'],
      ['hr', 'quote'],
      ['ul', 'ol'],
      ['table', 'link', 'image'],
      ['code', 'codeblock'],
      ['scrollSync']
    ],
    hooks: {
      addImageBlobHook: function (blob, callback) {
        var data = new FormData();
        data.append('action', 'upload_inline');
        data.append('csrf', csrf);
        data.append('file', blob, blob.name || 'imagem.png');
        fetch(uploadUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (response) { return response.json().then(function (json) { return { ok: response.ok, json: json }; }); })
          .then(function (result) {
            if (result.ok && result.json.url) callback(result.json.url, result.json.alt || 'imagem');
            else window.alert((result.json && result.json.error) || 'Não foi possível enviar a imagem.');
          })
          .catch(function () { window.alert('Não foi possível enviar a imagem.'); });
      }
    }
  });

  function syncToTextarea() { textarea.value = editor.getMarkdown(); }

  // --- Rascunho automático ---------------------------------------------------
  var dirty = false;
  var busy = false;
  var debounce = null;
  var idleTimer = null;

  function setStatus(text, kind) {
    if (!statusEl) return;
    statusEl.textContent = text || '';
    statusEl.className = 'autosave-status' + (kind ? ' is-' + kind : '');
  }

  function autosave() {
    if (!autosaveUrl || busy || !dirty) return;
    var markdown = editor.getMarkdown();
    var title = titleInput ? titleInput.value.trim() : '';
    if (title === '' && markdown.replace(/\s/g, '') === '') return;
    syncToTextarea();
    busy = true;
    setStatus('Salvando rascunho…', 'saving');
    var data = new FormData(form);
    data.set('action', 'autosave');
    if (idInput) data.set('id', idInput.value || '0');
    data.set('body_md', markdown);
    fetch(autosaveUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) { return response.json().then(function (json) { return { ok: response.ok, json: json }; }); })
      .then(function (result) {
        busy = false;
        if (!result.ok) { setStatus((result.json && result.json.error) || 'Falha ao salvar rascunho.', 'error'); return; }
        if (idInput && (!idInput.value || idInput.value === '0')) idInput.value = result.json.id;
        dirty = false;
        setStatus('Rascunho salvo automaticamente às ' + result.json.saved_at + '.', 'saved');
      })
      .catch(function () { busy = false; setStatus('Falha ao salvar rascunho.', 'error'); });
  }

  function schedule() {
    dirty = true;
    setStatus('Alterações não salvas…', 'dirty');
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(autosave, 2500);
  }

  editor.on('change', schedule);
  if (titleInput) titleInput.addEventListener('input', schedule);
  // Rede de segurança: salva a cada 30s se houver alterações pendentes.
  idleTimer = setInterval(function () { if (dirty) autosave(); }, 30000);

  window.addEventListener('beforeunload', function (event) {
    if (dirty) { event.preventDefault(); event.returnValue = ''; }
  });

  function syncBeforeSubmit() { syncToTextarea(); dirty = false; }
  if (form) form.addEventListener('submit', syncBeforeSubmit);
})();
