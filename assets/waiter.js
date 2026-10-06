/* Waiter app: polls the feed, rings on new requests, push notifications, table codes. */
(function () {
  'use strict';
  var W = window.WAITER;
  var $ = function (id) { return document.getElementById(id); };
  var state = { calls: [], tables: [], zones: [], my_zones: [] };
  var fetchedAt = Date.now(), seen = null, audio = null, wakeLock = null, pollTimer = null, failures = 0;

  // ---------------------------------------------------------------- API
  function get(action) {
    return fetch(W.api + '?a=' + action, { credentials: 'same-origin', cache: 'no-store' }).then(handle);
  }
  function post(action, data) {
    data = data || {}; data.a = action;
    return fetch(W.api, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': W.csrf },
      body: JSON.stringify(data)
    }).then(handle);
  }
  function handle(r) {
    if (r.status === 401) { location.reload(); throw new Error('login'); }
    return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'error'); return j; });
  }

  function toast(msg) {
    var t = $('toast'); t.textContent = msg; t.hidden = false;
    clearTimeout(toast.h); toast.h = setTimeout(function () { t.hidden = true; }, 3000);
  }

  // ---------------------------------------------------------------- feed
  function apply(data) {
    if (!data || !data.calls) return;
    var ring = null;
    if (seen) {
      data.calls.forEach(function (c) {
        var prev = seen[c.id];
        if (c.status === 'open' && (prev === undefined || c.repeat > prev)) ring = ring === 'bill' ? 'bill' : c.type;
      });
    }
    seen = {};
    data.calls.forEach(function (c) { seen[c.id] = c.repeat; });
    state = data; fetchedAt = Date.now();
    render();
    if (ring) alertNew(ring);
  }

  function refresh() {
    return get('feed').then(function (d) { failures = 0; setConn(true); apply(d); })
      .catch(function () { failures++; if (failures > 1) setConn(false); });
  }
  function schedule() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(function () { refresh().then(schedule, schedule); }, document.hidden ? 15000 : 3000);
  }
  function setConn(ok) { $('conn').className = 'conn ' + (ok ? 'ok' : 'bad'); }
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { refresh(); requestWakeLock(); }
    schedule();
  });

  // ---------------------------------------------------------------- render
  function ago(sec) {
    sec += Math.round((Date.now() - fetchedAt) / 1000);
    if (sec < 60) return 'adesso';
    var m = Math.floor(sec / 60);
    return m < 60 ? m + ' min fa' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min fa';
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function render() {
    var list = $('tab-calls'); list.innerHTML = '';
    var open = state.calls.filter(function (c) { return c.status === 'open'; }).length;
    $('callCount').textContent = open; $('callCount').hidden = !open;
    document.title = (open ? '(' + open + ') ' : '') + 'Chiamate';

    if (!state.calls.length) {
      list.appendChild(el('p', 'empty', 'Nessuna richiesta in attesa 👌'));
    }
    state.calls.forEach(function (c) {
      var age = c.age + Math.round((Date.now() - fetchedAt) / 1000);
      var card = el('article', 'call ' + c.type + ' ' + c.status + (c.status === 'open' && age > 300 ? ' late' : c.status === 'open' && age > 120 ? ' slow' : ''));
      var head = el('div', 'call-head');
      head.appendChild(el('span', 'call-table', c.label));
      if (c.zone) head.appendChild(el('span', 'call-zone', c.zone));
      card.appendChild(head);
      var what = c.type === 'bill' ? '🧾 Conto' + (c.payment ? ' · ' + (c.payment === 'card' ? 'carta 💳' : 'contanti 💶') : '') : '🙋 Chiama il cameriere';
      card.appendChild(el('div', 'call-what', what));
      var meta = ago(c.age);
      if (c.repeat) meta += ' · sollecitato ' + c.repeat + '×';
      if (c.status === 'taken') meta += ' · preso da ' + (c.mine ? 'te' : c.taken_name);
      card.appendChild(el('div', 'call-meta', meta));
      var btns = el('div', 'call-btns');
      if (c.status === 'open') {
        var take = el('button', 'btn', 'Prendo io');
        take.onclick = function () { take.disabled = true; post('take', { id: c.id }).then(apply).catch(errToast); };
        btns.appendChild(take);
      }
      var done = el('button', 'btn primary', c.type === 'bill' ? 'Conto fatto' : 'Fatto');
      done.onclick = function () {
        if (c.type === 'bill') askClose(c.label, function (close) { post('done', { id: c.id, close: close }).then(apply).catch(errToast); }, true);
        else { done.disabled = true; post('done', { id: c.id }).then(apply).catch(errToast); }
      };
      btns.appendChild(done);
      card.appendChild(btns);
      list.appendChild(card);
    });
    renderTables();
  }

  function renderTables() {
    var grid = $('tableGrid'), q = $('tableSearch').value.trim().toLowerCase();
    grid.innerHTML = '';
    var active = {};
    state.calls.forEach(function (c) { active[c.table_id] = (active[c.table_id] || '') + (c.type === 'bill' ? '🧾' : '🙋'); });
    var zone = null;
    state.tables.forEach(function (t) {
      if (q && (t.label + ' ' + (t.zone || '')).toLowerCase().indexOf(q) < 0) return;
      if ((t.zone || '') !== zone) {
        zone = t.zone || '';
        if (state.zones.length) grid.appendChild(el('h3', 'zone-title', zone || 'Senza zona'));
      }
      var card = el('button', 'tcard' + (active[t.id] ? ' busy' : ''));
      card.appendChild(el('span', 'tlabel', t.label));
      card.appendChild(el('span', 'tcode', t.code || '—'));
      card.appendChild(el('span', 'tflag', active[t.id] || ''));
      card.onclick = function () {
        askClose(t.label + ' · codice ' + t.code, function (close) {
          if (close) post('close_table', { table_id: t.id }).then(function (d) {
            apply(d);
            var nt = d.tables.filter(function (x) { return x.id === t.id; })[0];
            if (nt) toast('Tavolo ' + nt.label + ': nuovo codice ' + nt.code);
          }).catch(errToast);
        }, false);
      };
      grid.appendChild(card);
    });
    if (!grid.children.length) grid.appendChild(el('p', 'empty', 'Nessun tavolo.'));
  }

  function errToast() { toast('Operazione non riuscita, riprova.'); refresh(); }

  var closeCb = null;
  function askClose(title, cb, withJustDone) {
    closeCb = cb;
    $('closeTitle').textContent = 'Chiudere il tavolo ' + title + '?';
    $('closeNo').hidden = !withJustDone;
    $('closeDialog').showModal();
  }
  $('closeYes').onclick = function () { $('closeDialog').close(); if (closeCb) closeCb(true); };
  $('closeNo').onclick = function () { $('closeDialog').close(); if (closeCb) closeCb(false); };
  document.querySelectorAll('[data-close]').forEach(function (b) { b.onclick = function () { b.closest('dialog').close(); }; });

  document.querySelectorAll('.w-tabs button').forEach(function (b) {
    b.onclick = function () {
      document.querySelectorAll('.w-tabs button').forEach(function (x) { x.classList.toggle('on', x === b); });
      $('tab-calls').hidden = b.dataset.tab !== 'calls';
      $('tab-tables').hidden = b.dataset.tab !== 'tables';
    };
  });
  $('tableSearch').oninput = renderTables;
  setInterval(function () { if (!document.hidden) render(); }, 30000);

  // ---------------------------------------------------------------- sound
  function tone(freq, start, dur) {
    var o = audio.createOscillator(), g = audio.createGain();
    o.type = 'sine'; o.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, audio.currentTime + start);
    g.gain.exponentialRampToValueAtTime(0.6, audio.currentTime + start + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + start + dur);
    o.connect(g); g.connect(audio.destination);
    o.start(audio.currentTime + start); o.stop(audio.currentTime + start + dur + 0.05);
  }
  function alertNew(type) {
    if (navigator.vibrate) navigator.vibrate([250, 100, 250]);
    if (!audio) return;
    if (audio.state === 'suspended') audio.resume();
    if (type === 'bill') { tone(660, 0, 0.25); tone(880, 0.28, 0.25); tone(1100, 0.56, 0.4); }
    else { tone(988, 0, 0.35); tone(784, 0.4, 0.5); }
  }
  function requestWakeLock() {
    if (!audio || !('wakeLock' in navigator) || document.hidden) return;
    navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
  }

  // ---------------------------------------------------------------- push
  var pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;

  function b64ToBytes(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4), raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  function pushState() {
    if (!pushSupported) {
      return Promise.resolve(isIos && !standalone
        ? 'Su iPhone le notifiche funzionano solo dall\'app: tocca Condividi › "Aggiungi a schermata Home" e apri Chiamate da lì.'
        : 'Questo browser non supporta le notifiche push: tieni l\'app aperta per sentire le chiamate.');
    }
    if (Notification.permission === 'denied') return Promise.resolve('Notifiche bloccate: abilitale nelle impostazioni del browser per questo sito.');
    return navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); })
      .then(function (sub) { return sub ? 'Notifiche attive su questo dispositivo ✓' : 'Notifiche non ancora attive.'; });
  }

  function subscribe() {
    if (!pushSupported) return Promise.resolve(false);
    return Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') return false;
      return navigator.serviceWorker.ready.then(function (reg) {
        return reg.pushManager.getSubscription().then(function (sub) {
          return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(W.vapid) });
        });
      }).then(function (sub) { return post('push_subscribe', sub.toJSON()).then(function () { return true; }); });
    }).catch(function () { return false; });
  }

  $('enableBtn').onclick = function () {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      audio.resume(); tone(880, 0, 0.12);
    } catch (e) { audio = null; }
    requestWakeLock();
    subscribe().then(function (ok) {
      $('enable').hidden = true;
      toast(ok ? 'Suono e notifiche attivi' : 'Suono attivo (tieni l\'app aperta)');
    });
  };

  if (pushSupported) {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  }
  // Sound always needs a tap after opening the page (browser rule).
  $('enable').hidden = false;
  pushState().then(function (txt) {
    if (/attive/.test(txt)) $('enableText').textContent = 'Tocca per attivare il suono delle chiamate.';
  });

  // ---------------------------------------------------------------- options
  $('menuBtn').onclick = function () {
    var box = $('zoneList'); box.innerHTML = '';
    $('zoneBox').hidden = !state.zones.length;
    state.zones.forEach(function (z) {
      var lab = el('label', 'chip'), cb = el('input');
      cb.type = 'checkbox'; cb.value = z; cb.checked = state.my_zones.indexOf(z) >= 0;
      cb.onchange = function () {
        var zones = Array.prototype.map.call(box.querySelectorAll('input:checked'), function (i) { return i.value; });
        post('set_zones', { zones: zones }).then(function (d) { seen = null; apply(d); }).catch(errToast);
      };
      lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + z));
      box.appendChild(lab);
    });
    pushState().then(function (t) { $('pushState').textContent = t; });
    $('menuDialog').showModal();
  };
  $('pushTest').onclick = function () {
    subscribe().then(function (ok) {
      if (!ok) { pushState().then(toast); return; }
      post('push_test').then(function (r) {
        toast(r.sent ? 'Notifica inviata: dovrebbe arrivare tra pochi secondi' : 'Invio non riuscito');
        pushState().then(function (t) { $('pushState').textContent = t; });
      }).catch(errToast);
    });
  };

  refresh().then(schedule, schedule);
})();
