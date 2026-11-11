# La botonera 🔊

Sonidos para la oficina. En vivo en https://veronicapenaolivet.com/botonera/

## Qué tiene

- Buscador (`/` para enfocarlo, Enter reproduce el primer resultado)
- STOP fijo abajo y tecla `Esc` para cortar todo
- Opción para superponer sonidos o reproducir de a uno
- Barra de progreso dentro de cada botón
- ⭐ Favoritos (teclas `1`–`9`) y 🕘 últimos usados, guardados en el navegador
- Filtros por categoría y por sonidos nuevos
- 🎲 Sonido al azar (tecla `R`)
- 🔗 Link directo a cada sonido, por ejemplo `#ofortuna`
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

## Publicar

Subí `index.html`, `audios/` e `images/` a la carpeta `/botonera/` del sitio.
