(function () {
  var app = document.getElementById('msgApp');
  if (!app) return;
  var api = app.dataset.api, csrf = app.dataset.csrf;
  var $ = function (id) { return document.getElementById(id); };
  var listEl = $('convList'), emptyEl = $('convEmpty'), msgsEl = $('threadMsgs');
  var active = 0, lastId = 0, query = '', searchTimer = null, firstLoad = true;

  function getJSON(url, opts) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {})).then(function (r) {
      var ct = r.headers.get('content-type') || '';
      if (r.redirected || ct.indexOf('json') === -1) { location.href = 'login.html?err=login_required'; throw new Error('auth'); }
      return r.json().then(function (d) { d.__status = r.status; return d; });
    });
  }
  function initials(n) { return (n || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase(); }
  function short(iso) {
    var d = new Date(iso), t = new Date();
    if (d.toDateString() === t.toDateString()) return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }
  function full(iso) { return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function setBadge(n) {
    var b = $('navMsgBadge');
    if (!b) return;
    b.textContent = n > 99 ? '99+' : n; b.hidden = !n;
  }

  function renderList(items) {
    listEl.textContent = '';
    items.forEach(function (c) {
      var b = el('button', 'conv-item' + (c.id === active ? ' active' : '') + (c.unread ? ' unread' : ''));
      b.type = 'button'; b.dataset.id = c.id;
      b.appendChild(el('span', 'avatar', initials(c.name)));
      var body = el('span', 'conv-body');
      var top = el('span', 'conv-top');
      top.appendChild(el('strong', null, c.name)); top.appendChild(el('time', null, short(c.at)));
      body.appendChild(top);
      body.appendChild(el('span', 'conv-prop', c.property));
      var bottom = el('span', 'conv-bottom');
      bottom.appendChild(el('span', 'conv-preview', c.preview));
      if (c.unread) bottom.appendChild(el('span', 'unread-badge', c.unread));
      body.appendChild(bottom);
      b.appendChild(body);
      b.addEventListener('click', function () { openConv(c.id); });
      listEl.appendChild(b);
    });
    emptyEl.hidden = items.length > 0;
    emptyEl.querySelector('.msg-empty-text').textContent = query
      ? 'No conversations match your search.'
      : emptyEl.dataset.default;
  }

  function loadList() {
    return getJSON(api + '?action=list&q=' + encodeURIComponent(query)).then(function (d) {
      renderList(d.conversations || []); setBadge(d.unread_total || 0);
      if (firstLoad) {
        firstLoad = false;
        var want = parseInt(app.dataset.open, 10) || 0;
        if (want) openConv(want);
      }
    }).catch(function () {});
  }

  function openConv(id) {
    active = id; lastId = 0; msgsEl.textContent = '';
    $('threadPlaceholder').hidden = true; $('threadBox').hidden = false;
    app.classList.add('show-thread');
    try { history.replaceState(null, '', '?c=' + id); } catch (e) {}
    [].forEach.call(listEl.children, function (n) { n.classList.toggle('active', Number(n.dataset.id) === id); });
    loadThread(true);
  }

  function loadThread(initial) {
    if (!active) return Promise.resolve();
    var id = active;
    return getJSON(api + '?action=thread&id=' + id + '&after=' + lastId).then(function (d) {
      if (id !== active) return;
      if (d.__status === 404) { closeThread(); return; }
      if (d.meta) {
        $('thName').textContent = d.meta.name; $('thProp').textContent = d.meta.property;
        $('thAvatar').textContent = initials(d.meta.name);
        $('thLink').href = 'property-details.php?id=' + d.meta.property_id;
      }
      var nearBottom = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 80;
      var gotIncoming = false;
      (d.messages || []).forEach(function (m) {
        var row = el('div', 'bubble-row ' + (m.mine ? 'mine' : 'theirs'));
        var bub = el('div', 'bubble'); bub.appendChild(el('p', null, m.body));
        bub.appendChild(el('time', null, full(m.at)));
        row.appendChild(bub); msgsEl.appendChild(row);
        if (m.id > lastId) lastId = m.id;
        if (!m.mine) gotIncoming = true;
      });
      if (d.messages && d.messages.length && (initial || nearBottom || !gotIncoming)) msgsEl.scrollTop = msgsEl.scrollHeight;
      setBadge(d.unread_total || 0);
      if (gotIncoming && !initial) loadList();
      if (initial) loadList();
    }).catch(function () {});
  }

  function closeThread() {
    active = 0; app.classList.remove('show-thread');
    $('threadBox').hidden = true; $('threadPlaceholder').hidden = false;
    try { history.replaceState(null, '', location.pathname); } catch (e) {}
  }

  // Sending
  var form = $('sendForm'), input = $('msgInput'), err = $('sendErr');
  function autosize() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; }
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = input.value.trim();
    if (!body || !active) return;
    var btn = form.querySelector('button'); btn.disabled = true; err.textContent = '';
    var fd = new FormData();
    fd.append('action', 'send'); fd.append('id', active); fd.append('body', body); fd.append('csrf', csrf);
    getJSON(api, { method: 'POST', body: fd }).then(function (d) {
      if (d.ok) { input.value = ''; autosize(); loadThread(false).then(function () { msgsEl.scrollTop = msgsEl.scrollHeight; }); loadList(); }
      else err.textContent = d.error || 'Could not send your message.';
    }).catch(function () { err.textContent = 'Could not send your message.'; })
      .then(function () { btn.disabled = false; input.focus(); });
  });

  $('backBtn').addEventListener('click', function () { app.classList.remove('show-thread'); });
  $('msgSearch').addEventListener('input', function (e) {
    clearTimeout(searchTimer); var v = e.target.value.trim();
    searchTimer = setTimeout(function () { query = v; loadList(); }, 300);
  });

  // Live updates: poll while the tab is visible
  setInterval(function () { if (!document.hidden) loadList(); }, 8000);
  setInterval(function () { if (!document.hidden && active) loadThread(false); }, 3000);

  loadList();
})();
