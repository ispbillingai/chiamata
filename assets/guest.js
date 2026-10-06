/* Guest page of a table: code, call the waiter, ask for the bill, live status. */
(function () {
  'use strict';
  var G = window.GUEST, T = G.t;
  var $ = function (id) { return document.getElementById(id); };
  var codeBox = $('codeBox'), actions = $('actions'), statusBox = $('status'), toastEl = $('toast');
  var pollTimer = null, calls = [];

  function api(data) {
    data.k = G.token;
    return fetch(G.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return { error: 'error' }; });
    });
  }

  function toast(msg, isErr) {
    toastEl.textContent = msg;
    toastEl.className = 'toast' + (isErr ? ' err' : '');
    toastEl.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.hidden = true; }, 3500);
  }

  function showCode(expired) {
    actions.hidden = true;
    statusBox.innerHTML = '';
    codeBox.hidden = false;
    if (expired && !$('expiredMsg')) {
      var p = document.createElement('p');
      p.className = 'notice'; p.id = 'expiredMsg'; p.textContent = T.expired;
      codeBox.insertBefore(p, codeBox.firstChild);
    }
    $('code').value = '';
    stopPoll();
  }

  function showActions() {
    codeBox.hidden = true;
    actions.hidden = false;
    startPoll();
  }

  function handleError(res) {
    if (res.error === 'expired') { showCode(true); return true; }
    if (res.error === 'code') { showCode(false); return true; }
    return false;
  }

  function render(list) {
    calls = list || [];
    statusBox.innerHTML = '';
    calls.forEach(function (c) {
      var div = document.createElement('div');
      div.className = 'status-item ' + c.status;
      var text = c.waiter ? T[c.type + '_taken_name'].replace('{name}', c.waiter) : (T[c.type + '_' + c.status] || '');
      if (c.type === 'bill' && c.payment) text += ' (' + T[c.payment] + ')';
      var span = document.createElement('span');
      span.textContent = (c.status === 'taken' ? '🏃 ' : '✓ ') + text;
      div.appendChild(span);
      if (c.status === 'open') {
        var b = document.createElement('button');
        b.className = 'link'; b.textContent = T.cancel;
        b.onclick = function () {
          api({ a: 'cancel', id: c.id }).then(function (res) { if (!handleError(res)) render(res.calls); });
        };
        div.appendChild(b);
      }
      statusBox.appendChild(div);
    });
    document.querySelectorAll('.guest-actions .action').forEach(function (btn) {
      var active = calls.some(function (c) { return c.type === btn.dataset.type; });
      btn.classList.toggle('sent', active);
      var label = btn.querySelector('.again');
      if (active && !label) {
        label = document.createElement('small'); label.className = 'again'; label.textContent = T.call_again;
        btn.appendChild(label);
      } else if (!active && label) label.remove();
    });
  }

  function refresh() {
    if (document.hidden) return;
    api({ a: 'status' }).then(function (res) {
      if (!handleError(res) && res.calls) render(res.calls);
    }).catch(function () {});
  }

  function startPoll() {
    stopPoll();
    refresh();
    pollTimer = setInterval(refresh, 6000);
  }
  function stopPoll() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && pollTimer) refresh(); });

  $('codeForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('codeBtn'), err = $('codeErr');
    btn.disabled = true; err.hidden = true;
    api({ a: 'verify', code: $('code').value }).then(function (res) {
      btn.disabled = false;
      if (res.ok) {
        var ex = $('expiredMsg'); if (ex) ex.remove();
        showActions(); render(res.calls);
      } else {
        err.textContent = T[res.error] || T.error; err.hidden = false;
        $('code').select();
      }
    }).catch(function () { btn.disabled = false; err.textContent = T.error; err.hidden = false; });
  });

  function sendCall(type, payment, btn) {
    btn.disabled = true;
    api({ a: 'call', type: type, payment: payment || null }).then(function (res) {
      btn.disabled = false;
      if (handleError(res)) return;
      if (!res.ok) { toast(T.error, true); return; }
      render(res.calls);
      if (res.result === 'wait') toast(T.wait);
      else if (res.result === 'repeated') toast(T.reminded);
      if (navigator.vibrate) navigator.vibrate(60);
    }).catch(function () { btn.disabled = false; toast(T.error, true); });
  }

  var payDialog = $('payDialog'), pendingBtn = null;
  document.querySelectorAll('.guest-actions .action').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.dataset.type;
      var already = calls.some(function (c) { return c.type === type; });
      if (type === 'bill' && G.askPayment && !already && payDialog.showModal) {
        pendingBtn = btn; payDialog.showModal(); return;
      }
      sendCall(type, null, btn);
    });
  });
  payDialog.querySelectorAll('[data-pay]').forEach(function (b) {
    b.addEventListener('click', function () {
      payDialog.close();
      if (b.dataset.pay && pendingBtn) sendCall('bill', b.dataset.pay, pendingBtn);
    });
  });

  if (G.verified) showActions();
  else setTimeout(function () { $('code').focus(); }, 300);
})();
