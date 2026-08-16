<?php

/**
 * Origen Argentino — Header global
 * Head semántico + barra de navegación (escritorio, tablet y móvil).
 *
 * La barra reproduce la del sitio original: fondo negro, logo a la
 * izquierda, menú blanco al centro con subrayado crema y redes a la
 * derecha. Por debajo de 1024px el menú se convierte en hamburguesa.
 *
 * Requiere: config/constants.php (cargado por index.php)
 */

declare(strict_types=1);

/**
 * Este archivo solo se carga desde index.php.
 * El .htaccess ya impide alcanzarlo por URL; esto lo cubre por si un día
 * el sitio corre sin .htaccess (Nginx, otro hosting, un contenedor).
 */
if (!defined('ORIGEN_ARGENTINO')) {
	http_response_code(403);
	exit;
}

// PHP no anuncia su versión.
header_remove('X-Powered-By');

/**
 * Nonce por petición para Content-Security-Policy.
 * Permite los scripts inline propios (gtag y JSON-LD) sin relajar el CSP.
 */
$csp_nonce = base64_encode(random_bytes(16));

/**
 * Indexación por entorno.
 * El sitio es rastreable e indexable: se publica una meta robots con
 * "index, follow" y no se emite ninguna cabecera que lo impida.
 *
 * HTTP_HOST lo controla quien hace la petición, así que se normaliza antes
 * de compararlo (puerto y punto final del FQDN incluidos) y NUNCA se usa
 * para construir URLs: el canonical y og:url salen de la constante.
 */
$host_actual = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host_actual = (string) preg_replace('/:\d+$/', '', $host_actual);
$host_actual = rtrim($host_actual, '.');
$es_produccion = ($host_actual === SITIO_HOST_PRODUCCION || $host_actual === 'www.' . SITIO_HOST_PRODUCCION);

/**
 * Content-Security-Policy.
 *
 * Es lo único que se emite desde PHP porque depende del nonce de la petición;
 * el resto de cabeceras de seguridad vive en el .htaccess, que además las
 * aplica a los assets estáticos y a las páginas de error.
 *
 * Lista blanca cerrada: se parte de 'none' y se abre solo lo que el sitio
 * carga de verdad. Todo lo que no aparezca aquí (iframes, workers, plugins,
 * manifests, media, envíos de formulario) queda bloqueado por default-src.
 */
$csp = [
	// Nada permitido salvo lo que se declare debajo.
	"default-src 'none'",

	// Scripts propios y gtag. 'strict-dynamic' deja que el script con nonce
	// cargue los suyos; a cambio ignora 'self', así que TODO script del sitio
	// necesita su atributo nonce o el navegador lo descarta en silencio.
	"script-src 'nonce-{$csp_nonce}' 'strict-dynamic'",

	// Hojas de estilo propias. Sin 'unsafe-inline': ni style="" ni <style>.
	"style-src 'self'",

	// Imágenes propias. Sin data: — no hay ninguna incrustada en el CSS.
	"img-src 'self'",

	// Las WOFF2 son locales.
	"font-src 'self'",

	// Únicos destinos de red: los recolectores de Google Analytics.
	"connect-src https://www.google-analytics.com https://region1.google-analytics.com https://region1.analytics.google.com https://stats.g.doubleclick.net",

	// Sin <base>: cierra el secuestro de rutas relativas.
	"base-uri 'none'",

	// El sitio no envía formularios a ninguna parte.
	"form-action 'none'",

	// Nadie puede meter el sitio en un marco (clickjacking).
	"frame-ancestors 'none'",

	// Redundantes frente a default-src 'none', pero explícitos para que un
	// cambio futuro tenga que declararlos a propósito.
	"frame-src 'none'",
	"object-src 'none'",
	"media-src 'none'",
	"worker-src 'none'",
	"manifest-src 'none'",

	// Cualquier subrecurso que se cuele en http:// se pide en https://.
	'upgrade-insecure-requests',
];
header('Content-Security-Policy: ' . implode('; ', $csp));

/**
 * Datos estructurados (schema.org Restaurant).
 * Se construyen como arreglo y se serializan con json_encode: escapar JSON
 * con htmlspecialchars no sirve dentro de <script> —el navegador no decodifica
 * entidades ahí— y deja abierta la fuga por "</script>". JSON_HEX_TAG convierte
 * "<" y ">" en </>, que es lo que cierra esa puerta de verdad.
 */
$datos_estructurados = [
	'@context' => 'https://schema.org',
	'@type' => 'Restaurant',
	'name' => SITIO_NOMBRE,
	'description' => SITIO_DESCRIPCION,
	'url' => SITIO_URL_PRODUCCION,
	'telephone' => SITIO_TELEFONO_E164,
	'email' => SITIO_EMAIL,
	'servesCuisine' => [SITIO_COCINA_PRINCIPAL, SITIO_COCINA_SECUNDARIA],
	'address' => [
		'@type' => 'PostalAddress',
		'streetAddress' => SITIO_DIR_CALLE,
		'addressLocality' => SITIO_DIR_CIUDAD,
		'addressRegion' => SITIO_DIR_ESTADO,
		'postalCode' => SITIO_DIR_CP,
		'addressCountry' => SITIO_DIR_PAIS,
	],
	'sameAs' => [SITIO_FACEBOOK, SITIO_INSTAGRAM],
	'acceptsReservations' => SITIO_RESERVA_LINK,
];

$json_ld = (string) json_encode(
	$datos_estructurados,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT
);
// Alinea el bloque con la sangría del <head> al imprimirlo.
$json_ld = str_replace("\n", "\n\t\t", $json_ld);

/**
 * Datos estructurados del autor (schema.org Person).
 * Consolida la identidad pública "Samuel Ramsan" como entidad reconocible
 * y la enlaza a su perfil canónico, sin exponer ningún nombre personal.
 */
$datos_autor = [
	'@context' => 'https://schema.org',
	'@type' => 'Person',
	'name' => SITIO_AUTOR,
	'jobTitle' => SITIO_AUTOR_PROFESION,
	'url' => SITIO_AUTOR_URL,
	'sameAs' => [SITIO_AUTOR_URL],
];

$json_ld_autor = (string) json_encode(
	$datos_autor,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT
);
// Alinea el bloque con la sangría del <head> al imprimirlo.
$json_ld_autor = str_replace("\n", "\n\t\t", $json_ld_autor);
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc(SITIO_NOMBRE) ?> &#8211; <?= esc(SITIO_ESLOGAN) ?></title>
	<meta name="description" content="<?= esc(SITIO_DESCRIPCION) ?>">
	<meta name="author" content="<?= esc(SITIO_AUTOR) ?>">
	<meta name="robots" content="index, follow">
	<?php if ($es_produccion): ?>
		<link rel="canonical" href="<?= esc(SITIO_URL_PRODUCCION) ?>">
	<?php endif; ?>

	<!-- Favicons -->
	<link rel="icon" type="image/webp" href="<?= esc(oa_media_url('favicon_small')) ?>" sizes="32x32">
	<link rel="icon" type="image/webp" href="<?= esc(oa_media_url('favicon_large')) ?>" sizes="192x192">
	<link rel="apple-touch-icon" href="<?= esc(oa_media_url('favicon_large')) ?>">

	<!-- Open Graph -->
	<meta property="og:type" content="restaurant">
	<meta property="og:title" content="<?= esc(SITIO_NOMBRE) ?>">
	<meta property="og:description" content="<?= esc(SITIO_DESCRIPCION) ?>">
	<meta property="og:url" content="<?= esc(SITIO_URL_PRODUCCION) ?>">
	<meta property="og:image" content="<?= esc(SITIO_URL_PRODUCCION . oa_media_url('origin_photo')) ?>">

	<!-- Estilos locales -->
	<link rel="stylesheet" href="assets/css/fonts.css">
	<link rel="stylesheet" href="assets/css/style.css">
	<link rel="stylesheet" href="cocinadmin/content.css.php?v=<?= oa_content_revision() ?>">

	<!-- Precarga de la imagen y las fuentes visibles en el primer pantallazo -->
	<link rel="preload" as="image" href="<?= esc(oa_media_url('hero_background')) ?>" fetchpriority="high">
	<link rel="preload" as="font" type="font/woff2" href="assets/fonts/montserrat-200-italic.woff2" crossorigin>
	<link rel="preload" as="font" type="font/woff2" href="assets/fonts/montserrat-500.woff2" crossorigin>

	<!-- Datos estructurados: Restaurant -->
	<script type="application/ld+json" nonce="<?= esc($csp_nonce) ?>">
		<?= $json_ld ?>
	</script>

	<!-- Datos estructurados: Person (autor) -->
	<script type="application/ld+json" nonce="<?= esc($csp_nonce) ?>">
		<?= $json_ld_autor ?>
	</script>

	<!-- Google Tag Manager (analítica del cliente) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?= rawurlencode(SITIO_GTM_ID) ?>" nonce="<?= esc($csp_nonce) ?>"></script>
	<script nonce="<?= esc($csp_nonce) ?>">
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());
		// El ID sale por json_encode: dentro de <script> el escapado HTML no
		// aplica, y esto lo entrega ya como literal de cadena JS válido.
		gtag('config', <?= json_encode(SITIO_GTM_ID, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>);
	</script>
</head>

<body>

	<a class="skip-link" href="#contenido"><?= esc(oa_setting('skip_link_label')) ?></a>

	<header id="header" class="site-header">
		<div class="site-header-inner">

			<div class="site-header-col site-header-col-logo">
				<a href="index.php" class="site-header-logo" aria-label="<?= esc(oa_setting('home_aria_label')) ?>">
					<picture>
						<source media="(max-width: 767px)" srcset="<?= esc(oa_media_url('logo_compact')) ?>" width="930" height="1127">
						<img src="<?= esc(oa_media_url('logo_desktop')) ?>" alt="<?= esc((string) oa_media('logo_desktop')['alt_text']) ?>" width="802" height="911" fetchpriority="high">
					</picture>
				</a>
			</div>

			<div class="site-header-col site-header-col-nav">
				<nav class="site-nav" aria-label="<?= esc(oa_setting('nav_aria_label')) ?>">
					<ul class="site-nav-list">
						<li><a href="index.php" aria-current="page"><?= esc(oa_setting('nav_home_label')) ?></a></li>
						<li><a href="index.php#origen"><?= esc(oa_setting('nav_origin_label')) ?></a></li>
						<li><a href="index.php#reserva"><?= esc(oa_setting('nav_reservation_label')) ?></a></li>
						<li><a href="index.php#galeria"><?= esc(oa_setting('nav_gallery_label')) ?></a></li>
					</ul>
				</nav>

				<button class="nav-toggle" type="button" aria-label="<?= esc(oa_setting('nav_open_label')) ?>" aria-expanded="false" aria-controls="menu-desplegable" data-label-open="<?= esc(oa_setting('nav_open_label')) ?>" data-label-close="<?= esc(oa_setting('nav_close_label')) ?>">
					<svg class="icono-abrir" aria-hidden="true" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">
						<path d="M104 333H896C929 333 958 304 958 271S929 208 896 208H104C71 208 42 237 42 271S71 333 104 333ZM104 583H896C929 583 958 554 958 521S929 458 896 458H104C71 458 42 487 42 521S71 583 104 583ZM104 833H896C929 833 958 804 958 771S929 708 896 708H104C71 708 42 737 42 771S71 833 104 833Z" />
					</svg>
					<svg class="icono-cerrar" aria-hidden="true" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">
						<path d="M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z" />
					</svg>
				</button>

				<nav id="menu-desplegable" class="site-nav-desplegable" aria-label="<?= esc(oa_setting('nav_dropdown_aria_label')) ?>" inert>
					<ul>
						<li><a href="index.php" aria-current="page"><?= esc(oa_setting('nav_home_label')) ?></a></li>
						<li><a href="index.php#origen"><?= esc(oa_setting('nav_origin_label')) ?></a></li>
						<li><a href="index.php#reserva"><?= esc(oa_setting('nav_reservation_label')) ?></a></li>
						<li><a href="index.php#galeria"><?= esc(oa_setting('nav_gallery_label')) ?></a></li>
					</ul>
				</nav>
			</div>

			<div class="site-header-col site-header-col-social">
				<div class="site-social">
					<a href="<?= esc(SITIO_FACEBOOK) ?>" target="_blank" rel="noopener" aria-label="<?= esc(oa_setting('facebook_aria_label')) ?>">
						<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z" />
						</svg>
					</a>
					<a href="<?= esc(SITIO_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="<?= esc(oa_setting('instagram_aria_label')) ?>">
						<svg aria-hidden="true" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z" />
						</svg>
					</a>
				</div>
			</div>

		</div>
	</header>