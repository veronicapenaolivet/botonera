# La botonera 🔊

Sonidos para la oficina. En vivo en https://veronicapenaolivet.com/botonera/

## Qué tiene

- Buscador (`/` para enfocarlo, Enter reproduce el primer resultado)
- STOP fijo abajo y tecla `Esc` para cortar todo
- Opción para superponer sonidos o reproducir de a uno
- Barra de progreso dentro de cada botón; tocar un sonido que está sonando lo pausa y otro toque lo sigue desde ahí
- ⭐ Favoritos (teclas `1`–`9`) y 🕘 últimos usados, guardados en el navegador
- Filtros por categoría y por sonidos nuevos
- 🎲 Sonido al azar (tecla `R`)
- Compartir cada sonido: copiar el link directo (`#ofortuna`) o mandar el audio desde el celular (WhatsApp, Telegram…); en la compu, descargar el mp3
- Vibración y pulso al tocar un botón
- Control de volumen y modo oscuro

## Agregar un sonido

1. Copiá el mp3 en `audios/`, por ejemplo `audios/bocina.mp3`.
2. En `index.html`, agregá una línea al principio de la lista `SONIDOS`:

   ```js
   ["bocina", "Bocina", "📯", "efectos", "2026-11-11"],
   ```

   Los campos son: archivo sin `.mp3`, nombre del botón, emoji, categoría (`frases`, `musica` o `efectos`) y, opcionalmente, la fecha en que lo agregás. Con fecha, el botón muestra la etiqueta NUEVO durante 5 días (`DIAS_NUEVO`) y después se va sola.

## Frases ocultas para el buscador

En `index.html`, el objeto `FRASES` guarda lo que dice cada audio. No se muestra en el botón, pero el buscador lo usa: por ejemplo, buscar "silvia" encuentra "La peor de las ondas".

```js
const FRASES = {
  lapeordelasondas: "mirá silvia, voy a ir pero con la peor de las ondas",
};
```

## Pedidos de audios

El botón **Pedir un audio** abre un formulario que envía los datos a `pedir.php`:

- Manda un mail a `botonera@veronicapenaolivet.com` con el pedido y el audio adjunto (hasta 10 MB; los más grandes quedan solo guardados).
- Guarda los archivos y un registro `pedidos.csv` en `botonera_pedidos/`, **fuera** de `public_html`.
- El mail trae botones de WhatsApp y Telegram con mensajes ya escritos para quien pidió el audio: **Ya está en la botonera** o **No lo encontré** (le pide que lo suba en el pedido o lo mande por mensaje, con un link `#pedir` que abre el formulario). Solo hay que elegir el contacto.
- Antispam: campo trampa invisible, tiempo mínimo para completar el formulario y hasta 20 pedidos por hora desde una misma IP.

## App instalable

La botonera se puede instalar en el celular, con ícono propio (`iconos/`) y uso sin internet:

- `manifest.json`: nombre, colores e íconos de la app.
- `sw.js`: guarda la página, los estilos y los audios. Si lo cambiás, subí `VERSION`.
- En el celular aparece un cartel abajo para instalarla. En Android con Chrome, Edge o Samsung Internet abre el aviso del sistema; en Firefox para Android y en iPhone (Safari, y Chrome, Edge o Firefox desde iOS 16.4) muestra los pasos para ese navegador. Si lo cierran con la ✕ no vuelve a aparecer, y no se muestra cuando ya está instalada ni en navegadores que no permiten instalar (por ejemplo, el de Instagram).
- Instalada como app, guarda todos los audios para usarla sin conexión.

## Donaciones

El botón **Donar** abre un diálogo con Mercado Pago (https://link.mercadopago.com.uy/vpo). Para sumar PayPal, pegá el link en `PAYPAL_URL` dentro de `index.html`: el botón aparece solo cuando hay link.

## Métricas (GA4)

En `index.html`, pegá el ID de medición en `GA_ID` (formato `G-XXXXXXXXXX`). Eventos que se registran: `reproducir_sonido`, `search`, `compartir`, `favorito`, `pedir_audio`, `abrir_donar` y `donar`.

## Publicar

Subí `index.html`, `pedir.php`, `manifest.json`, `sw.js`, `audios/`, `images/` e `iconos/` a la carpeta `/botonera/` del sitio.
