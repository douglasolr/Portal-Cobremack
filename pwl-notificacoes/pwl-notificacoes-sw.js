/* PWL Notificacoes: Service Worker - Web Push */
self.addEventListener('push', event => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch (e) { data = { body: event.data.text() }; }
  const url = typeof data.url === 'string' && new URL(data.url, self.location.origin).origin === self.location.origin ? data.url : '/';
  event.waitUntil(self.registration.showNotification(String(data.title || 'Nova notificação'), {
    body: String(data.body || ''), icon: '/favicon.ico', tag: 'pwl-notificacao-' + String(data.id || Date.now()),
    data: { url }, badge: '/favicon.ico'
  }));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = new URL((event.notification.data || {}).url || '/', self.location.origin).href;
  event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(all => {
    for (const c of all) if (c.url === url && 'focus' in c) return c.focus();
    return clients.openWindow(url);
  }));
});
