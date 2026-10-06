/* Waiter app service worker: the push carries no data, so ask the server what to show. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  e.waitUntil(
    fetch('../api/waiter.php?a=push_summary', { credentials: 'include', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .catch(function () { return {}; })
      .then(function (s) {
        var title = s.title || 'Nuova chiamata dal tavolo';
        return self.registration.showNotification(title, {
          body: s.body || 'Apri l\'app per i dettagli',
          tag: s.tag || 'calls',
          renotify: true,
          requireInteraction: s.tag !== 'test',
          vibrate: [300, 120, 300, 120, 300],
          icon: '../icon.php?s=192',
          badge: '../icon.php?s=72'
        });
      })
  );
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].url.indexOf(self.registration.scope) === 0 && 'focus' in list[i]) return list[i].focus();
    }
    return self.clients.openWindow(self.registration.scope);
  }));
});
