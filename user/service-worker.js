const CACHE_NAME = 'xindapay-v1';
const urlsToCache = [
  '/user/',
  '/user/assets/css/app.css',
  '/assets/vendor/twitter-bootstrap/3.4.1/css/bootstrap.min.css',
  '/assets/img/logo.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) return response;
        return fetch(event.request).then(res => {
          if (!res || res.status !== 200 || res.type !== 'basic') return res;
          let responseClone = res.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
          return res;
        });
      })
  );
});