# Origen Argentino

Sitio one-page de [Origen Argentino](https://origenargentino.com), parrilla estilo argentino en la Zona Gastronómica de Tijuana. Reemplaza WordPress + Elementor con PHP plano, CSS puro y JavaScript vanilla, sin dependencias externas y conservando el diseño al píxel.

## Estructura

```text
index.php                    SPA pública: hero, origen, reserva y galería
config/constants.php         Puente entre el contenido dinámico y las plantillas
includes/                    Head, navegación, pie y widget de contacto
assets/                      CSS, JavaScript, tipografías e imágenes originales
cocinadmin/index.php         Punto de entrada único del CMS
cocinadmin/app/              Aplicación privada, seguridad y acceso a SQLite
cocinadmin/.storage/         Base, clave, sesiones y respaldos privados
cocinadmin/uploads/          WebP saneados y publicados por el CMS
cocinadmin/content.css.php   Fondos dinámicos sin estilos inline
.htaccess                    HTTPS, cabeceras, caché y bloqueos de acceso
```

## Cocinadmin

El panel permite editar 56 valores de contenido y negocio, además de 14 posiciones fijas de imagen. La cantidad de bloques, su HTML, clases, SVG, animaciones y retícula no cambian. El sitio público lee SQLite del lado servidor; no hay API JSON, `fetch` ni JavaScript adicional para cargar contenido.

Incluye:

- Textos visibles y accesibles de navegación, portada, Nuestro Origen, Reserva, galería, pie y widget.
- Identidad, SEO, teléfono, correo, dirección, reservas, mapas y redes.
- Logos, favicons, fondos, foto de origen, galería y separadores del pie.
- Recorte y re-codificación de JPEG/PNG/WebP a WebP; nunca publica el archivo recibido.
- Control optimista de versión, historial, auditoría y respaldo consistente antes de escribir.
- Valores originales como fallback si la base no existe o no puede leerse.

### Requisitos

- PHP 8.1 o posterior.
- PDO SQLite y SQLite3.
- Fileinfo, Mbstring y GD con soporte WebP.
- Argon2id disponible en `password_hash`.
- Apache con `AllowOverride` habilitado para aplicar los `.htaccess`.
- Escritura para el usuario de PHP en `cocinadmin/.storage/` y `cocinadmin/uploads/`.

Si TLS termina en un proxy, configurar `ORIGEN_TRUSTED_PROXY_IPS` con las IP exactas que
PHP recibe en `REMOTE_ADDR`, separadas por comas. Cocinadmin ignora `X-Forwarded-Proto` y
`X-Forwarded-SSL` desde cualquier otra IP y falla cerrado por HTTP. Para el rate-limit
descarta de derecha a izquierda los proxies confiables de `X-Forwarded-For`; el proxy debe
sobrescribir o anexar correctamente esa cabecera. No usar rangos abiertos.

### Instalación

Desde la raíz del proyecto:

```bash
php cocinadmin/bin/install.php
```

El instalador es idempotente: comprueba requisitos, crea o migra SQLite, instala una clave aleatoria, siembra el contenido original y valida integridad/referencias. Solo funciona por CLI.

El acceso queda en `/cocinadmin/`. La cuenta temporal es `admin`; su contraseña se entrega fuera del repositorio y el panel obliga a reemplazarla antes de permitir cualquier edición. En el código solo existe su hash Argon2id.

### Despliegue sin acceso CLI

Si el hosting no ofrece SSH, ejecutar el instalador localmente y subir también los archivos ocultos `cocinadmin/.storage/origen.sqlite3` y `cocinadmin/.storage/app.key`. No deben enviarse a Git ni quedar descargables por HTTP. Mantener permisos `0600` para ambos, `0700` para `.storage` y escritura del usuario PHP en `.storage` y `uploads`.

Después del despliegue hay que comprobar por HTTP que `cocinadmin/app/`, `cocinadmin/bin/`, `cocinadmin/.storage/` y cualquier archivo `.sqlite3` devuelvan 403, y que un archivo ejecutable dentro de `uploads/` tampoco pueda servirse.

En el VirtualHost final deben configurarse `TraceEnable Off` y `ServerTokens Prod`; ninguna
de las dos directivas puede imponerse de forma fiable desde `.htaccess`.

La sincronización automática excluye sesiones, temporales, respaldos, WAL/SHM, `app.key` y
WebP de runtime. Nunca desplegar una base SQLite activa copiando solo el archivo principal:
usar el instalador o una copia consistente con la aplicación detenida.

## Seguridad

- Sesiones privadas con cookies `Secure`, `HttpOnly`, `SameSite=Strict`, modo estricto, rotación de ID, expiración por inactividad y límite absoluto.
- CSRF por acción, verificación de origen, bloqueo progresivo de acceso por usuario e IP, y errores de login indistinguibles.
- Contraseñas Argon2id, cambio temporal obligatorio e invalidación de sesiones al cambiar clave.
- CSP cerrada sin JavaScript en el panel; `no-store`, `noindex`, anti-frame y permisos del navegador deshabilitados.
- Catálogos cerrados para campos y medios, consultas preparadas, transacciones `BEGIN IMMEDIATE` y SQLite con FK, WAL, `synchronous=FULL`, `trusted_schema=OFF` y `secure_delete`.
- Base, clave, sesiones, temporales y respaldos dentro de almacenamiento privado con denegación en dos capas.

## Sitio público

- Tipografías e iconos auto-alojados.
- CSP con nonce y `'strict-dynamic'`: todo script nuevo necesita el nonce de la petición.
- `style-src 'self'`: no se permiten estilos inline; los fondos editables salen de una hoja externa versionada.
- Solo el host de producción es rastreable; los demás reciben `noindex` y no emiten canonical.
- Breakpoints: escritorio ≥1025 px, tablet 768–1024 px y móvil ≤767 px.
- Accesibilidad con enlace de salto, estados ARIA, `inert`, foco visible y `prefers-reduced-motion`.

---

Desarrollado por [SamRamSan](https://github.com/SamWebCodes) / Samuel Ramírez Sánchez @ wms.guru.
