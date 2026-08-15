# Origen Argentino

Sitio one-page del restaurante [Origen Argentino](https://origenargentino.com) — parrilla estilo argentino en la Zona Gastronómica de Tijuana.

Reemplazo del sitio anterior en WordPress + Elementor, replicando el diseño al píxel pero **sin frameworks ni librerías**: PHP plano, CSS puro y JavaScript vanilla, con todos los assets auto-alojados.

## Estructura

```
index.php              Página principal: hero, origen, reserva y galería
config/constants.php   Datos de contacto, enlaces y entorno (única fuente de verdad)
includes/header.php    <head>, cabecera y navegación
includes/footer.php    Pie de página y widget flotante de contacto
assets/css/style.css   Estilos (mobile-first, tres breakpoints)
assets/css/fonts.css   @font-face de las WOFF2 locales
assets/js/main.js      Menú, carrusel, animaciones y widget de contacto
.htaccess              HTTPS forzado, cabeceras de seguridad, compresión y caché
```

## Detalles de implementación

- **Sin dependencias externas.** Tipografías (Bolderist, Poppins, Montserrat) e iconos SVG servidos desde el propio dominio.
- **CSP con nonce.** `includes/header.php` genera un nonce por petición y emite la cabecera `Content-Security-Policy` con `'strict-dynamic'`. Todo `<script>` que se añada **debe** llevar `nonce="<?= $csp_nonce ?>"` o el navegador lo bloqueará.
- **Indexación por entorno.** Solo `SITIO_HOST_PRODUCCION` es rastreable; cualquier otro host se sirve con `noindex, nofollow` y sin `canonical`.
- **Breakpoints.** Escritorio ≥1025 px · tablet 768–1024 px · móvil ≤767 px.
- **Accesibilidad.** Enlace de salto al contenido, `aria-expanded`/`inert` en los desplegables, foco visible y respeto a `prefers-reduced-motion`.

## Desarrollo local

```bash
php -S 127.0.0.1:8000
```

Fuera del dominio de producción el sitio se sirve automáticamente con `noindex`.

## Despliegue

Subida por FTP a la raíz del hosting. La configuración local del cliente FTP vive en `.vscode/sftp.json`, que está excluido del repositorio por contener credenciales.

---

Desarrollado por [Lang-Lab](https://github.com/SamWebCodes) / Samuel Ramírez Sánchez.
