const CACHE_NAME = "vocab-cache-v6";
const urlsToCache = [
  "/",
  "/index.php",       // landing home
  "/vocabulary.php",  // vocabulary page
  "/register.php",    // register page
  "/about.php",       // about class page
  "/admin.php",       // admin page
  "/registration-success.php",
  "/check-registration-page.php",
  "/manifest.json",
  "/offline.php",
  "/picons/icon-192.png",
  "/picons/icon-512.png"
];

// Install Service Worker
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
});

// Activate and clean old caches
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(key => key !== CACHE_NAME && caches.delete(key)))
    )
  );
});

// Fetch with offline fallback
self.addEventListener("fetch", event => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request).then(response => {
        if (response) return response;
        return caches.match("/offline.php");
      });
    })
  );
});
