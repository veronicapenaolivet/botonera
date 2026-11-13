/*
 * Service worker de la botonera: la deja usar sin internet.
 * - La página (index.html) se busca primero en internet, así siempre ves lo último;
 *   si no hay conexión, se usa la copia guardada.
 * - Los audios, estilos, íconos y fuentes se sirven de lo guardado y se actualizan
 *   en segundo plano.
 * - Si cambiás este archivo, subí el número de VERSION para que se renueve.
 */
const VERSION = "botonera-v1";

const BASICOS = [
  "./",
  "manifest.json",
  "images/favicon.png",
  "iconos/icon-192.png",
  "iconos/icon-512.png",
  "iconos/apple-touch-icon.png",
  "../images/avatar.jpg",
  "../images/11.png",
  "../assets/css/main.css",
  "../assets/css/fontawesome-all.min.css",
  "../assets/css/fontawesome.css",
  "../assets/css/brands.css",
  "../assets/css/solid.css",
  "../assets/webfonts/fa-solid-900.woff2",
  "../assets/webfonts/fa-brands-400.woff2",
  "../assets/js/jquery.min.js",
  "../assets/js/jquery.poptrox.min.js",
  "../assets/js/browser.min.js",
  "../assets/js/breakpoints.min.js",
  "../assets/js/util.js",
  "../assets/js/main.js",
];

self.addEventListener("install", e => {
  e.waitUntil((async () => {
    const cache = await caches.open(VERSION);
    // De a uno: si alguno falla, los demás se guardan igual.
    await Promise.all(BASICOS.map(u => cache.add(u).catch(() => {})));
    await self.skipWaiting();
  })());
});

self.addEventListener("activate", e => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k !== VERSION) await caches.delete(k);
    await self.clients.claim();
  })());
});

// La página (cuando está instalada como app) pide guardar todos los audios.
self.addEventListener("message", e => {
  if (!e.data || e.data.tipo !== "guardar-audios") return;
  e.waitUntil((async () => {
    const cache = await caches.open(VERSION);
    for (const u of e.data.urls) {
      if (!(await cache.match(u))) await cache.add(u).catch(() => {});
    }
  })());
});

self.addEventListener("fetch", e => {
  const req = e.request;
  if (req.method !== "GET") return;                       // pedir.php y demás POST: directo a internet
  const url = new URL(req.url);
  const mismoSitio = url.origin === self.location.origin;
  const fuentes = /^fonts\.(googleapis|gstatic)\.com$/.test(url.hostname);
  if (!mismoSitio && !fuentes) return;                    // Analytics y otros: sin tocar

  if (req.mode === "navigate") { e.respondWith(paginaPrimeroRed(req)); return; }
  if (mismoSitio && /\/audios\/[^/]+\.mp3$/.test(url.pathname)) { e.respondWith(audio(req)); return; }
  e.respondWith(guardadoYActualizar(req));
});

async function paginaPrimeroRed(req) {
  const cache = await caches.open(VERSION);
  try {
    const res = await Promise.race([
      fetch(req),
      new Promise((_, rej) => setTimeout(() => rej(new Error("lento")), 4000)),
    ]);
    if (res.ok) cache.put("./", res.clone());
    return res;
  } catch {
    return (await cache.match("./")) || (await cache.match(req)) || Response.error();
  }
}

async function guardadoYActualizar(req) {
  const cache = await caches.open(VERSION);
  const guardado = await cache.match(req);
  const red = fetch(req).then(res => {
    if (res.ok || res.type === "opaque") cache.put(req, res.clone());
    return res;
  }).catch(() => null);
  return guardado || (await red) || Response.error();
}

// Audios: desde lo guardado. Safari pide los audios "por partes" (Range),
// así que respondemos el pedazo pedido.
async function audio(req) {
  const cache = await caches.open(VERSION);
  const clave = req.url.split("?")[0];
  let res = await cache.match(clave);
  if (res) {
    fetch(clave).then(r => r.ok && cache.put(clave, r)).catch(() => {});   // actualizar en segundo plano
  } else {
    try {
      const r = await fetch(clave);
      if (!r.ok) return r;
      await cache.put(clave, r.clone());
      res = r;
    } catch {
      return Response.error();
    }
  }
  const rango = req.headers.get("range");
  if (!rango) return res;
  const datos = await res.clone().arrayBuffer();
  const m = /bytes=(\d*)-(\d*)/.exec(rango) || [];
  const total = datos.byteLength;
  const desde = m[1] ? +m[1] : 0;
  const hasta = m[2] ? Math.min(+m[2], total - 1) : total - 1;
  return new Response(datos.slice(desde, hasta + 1), {
    status: 206,
    headers: {
      "Content-Type": "audio/mpeg",
      "Content-Length": String(hasta - desde + 1),
      "Content-Range": `bytes ${desde}-${hasta}/${total}`,
      "Accept-Ranges": "bytes",
    },
  });
}
