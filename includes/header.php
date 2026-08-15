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
 * Nonce por petición para Content-Security-Policy.
 * Permite los scripts inline propios (gtag y JSON-LD) sin relajar el CSP.
 */
$csp_nonce = base64_encode(random_bytes(16));

/**
 * Indexación por entorno.
 * Solo el dominio de producción es rastreable: cualquier otro host
 * (origen.wms.guru, una IP, localhost) se sirve con noindex/nofollow.
 * Al apuntar el dominio real, la etiqueta desaparece sola.
 */
$host_actual = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host_actual = (string) preg_replace('/:\d+$/', '', $host_actual);
$es_produccion = ($host_actual === SITIO_HOST_PRODUCCION || $host_actual === 'www.' . SITIO_HOST_PRODUCCION);

if (!$es_produccion) {
	header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
}

/**
 * Headers de seguridad emitidos desde PHP para sincronizarlos con el nonce.
 * El resto de headers (HSTS, referrer, etc.) vive en el .htaccess.
 */
header('Content-Security-Policy: ' .
	"default-src 'self'; " .
	"script-src 'nonce-{$csp_nonce}' 'strict-dynamic'; " .
	"style-src 'self'; " .
	"img-src 'self' data:; " .
	"font-src 'self'; " .
	"connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://region1.analytics.google.com https://stats.g.doubleclick.net; " .
	"frame-ancestors 'none'; " .
	"base-uri 'self'; " .
	"form-action 'none'; " .
	"object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars(SITIO_NOMBRE, ENT_QUOTES, 'UTF-8') ?> &#8211; <?= htmlspecialchars(SITIO_ESLOGAN, ENT_QUOTES, 'UTF-8') ?></title>
	<meta name="description" content="<?= htmlspecialchars(SITIO_DESCRIPCION, ENT_QUOTES, 'UTF-8') ?>">
	<?php if ($es_produccion): ?>
		<link rel="canonical" href="https://origenargentino.com/">
	<?php else: ?>
		<!-- Entorno de prueba: fuera de buscadores. Sin canonical, para no
	     arrastrar el noindex hacia el dominio de producción. -->
		<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
	<?php endif; ?>

	<!-- Favicons -->
	<link rel="icon" type="image/webp" href="assets/img/favicon-32.webp" sizes="32x32">
	<link rel="icon" type="image/webp" href="assets/img/favicon-192.webp" sizes="192x192">
	<link rel="apple-touch-icon" href="assets/img/favicon-192.webp">

	<!-- Open Graph -->
	<meta property="og:type" content="restaurant">
	<meta property="og:title" content="<?= htmlspecialchars(SITIO_NOMBRE, ENT_QUOTES, 'UTF-8') ?>">
	<meta property="og:description" content="<?= htmlspecialchars(SITIO_DESCRIPCION, ENT_QUOTES, 'UTF-8') ?>">
	<meta property="og:url" content="https://origenargentino.com/">
	<meta property="og:image" content="https://origenargentino.com/assets/img/nosotros.webp">

	<!-- Estilos locales -->
	<link rel="stylesheet" href="assets/css/fonts.css">
	<link rel="stylesheet" href="assets/css/style.css">

	<!-- Precarga de la imagen y las fuentes visibles en el primer pantallazo -->
	<link rel="preload" as="image" href="assets/img/hero-bg.webp" fetchpriority="high">
	<link rel="preload" as="font" type="font/woff2" href="assets/fonts/montserrat-200-italic.woff2" crossorigin>
	<link rel="preload" as="font" type="font/woff2" href="assets/fonts/montserrat-500.woff2" crossorigin>

	<!-- Datos estructurados: Restaurant -->
	<script type="application/ld+json" nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>">
		{
			"@context": "https://schema.org",
			"@type": "Restaurant",
			"name": "<?= htmlspecialchars(SITIO_NOMBRE, ENT_QUOTES, 'UTF-8') ?>",
			"description": "<?= htmlspecialchars(SITIO_DESCRIPCION, ENT_QUOTES, 'UTF-8') ?>",
			"url": "https://origenargentino.com/",
			"telephone": "+526646229730",
			"email": "<?= htmlspecialchars(SITIO_EMAIL, ENT_QUOTES, 'UTF-8') ?>",
			"servesCuisine": ["Argentina", "Parrilla"],
			"address": {
				"@type": "PostalAddress",
				"streetAddress": "Escuadrón 201 3151, Aviación",
				"addressLocality": "Tijuana",
				"addressRegion": "Baja California",
				"postalCode": "22014",
				"addressCountry": "MX"
			},
			"sameAs": [
				"https://web.facebook.com/OrigenArgentino",
				"https://www.instagram.com/origen.argentino/"
			],
			"acceptsReservations": "https://www.opentable.com.mx/r/origen-argentino-tijuana"
		}
	</script>

	<!-- Google Tag Manager (analítica del cliente) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(SITIO_GTM_ID, ENT_QUOTES, 'UTF-8') ?>" nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>"></script>
	<script nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>">
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());
		gtag('config', '<?= htmlspecialchars(SITIO_GTM_ID, ENT_QUOTES, 'UTF-8') ?>');
	</script>
</head>

<body>

	<a class="skip-link" href="#contenido">Ir al contenido</a>

	<header id="header" class="site-header">
		<div class="site-header-inner">

			<div class="site-header-col site-header-col-logo">
				<a href="index.php" class="site-header-logo" aria-label="Ir al inicio">
					<picture>
						<source media="(max-width: 767px)" srcset="assets/img/logo-negro.svg" width="930" height="1127">
						<img src="assets/img/logo.webp" alt="Logo de Origen Argentino" width="802" height="911" fetchpriority="high">
					</picture>
				</a>
			</div>

			<div class="site-header-col site-header-col-nav">
				<nav class="site-nav" aria-label="Menú principal">
					<ul class="site-nav-list">
						<li><a href="index.php" aria-current="page">Inicio</a></li>
						<li><a href="index.php#origen">Nuestro Origen</a></li>
						<li><a href="index.php#reserva">Reserva</a></li>
						<li><a href="index.php#galeria">Galería</a></li>
					</ul>
				</nav>

				<button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-desplegable">
					<svg class="icono-abrir" aria-hidden="true" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">
						<path d="M104 333H896C929 333 958 304 958 271S929 208 896 208H104C71 208 42 237 42 271S71 333 104 333ZM104 583H896C929 583 958 554 958 521S929 458 896 458H104C71 458 42 487 42 521S71 583 104 583ZM104 833H896C929 833 958 804 958 771S929 708 896 708H104C71 708 42 737 42 771S71 833 104 833Z" />
					</svg>
					<svg class="icono-cerrar" aria-hidden="true" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg">
						<path d="M742 167L500 408 258 167C246 154 233 150 217 150 196 150 179 158 167 167 154 179 150 196 150 212 150 229 154 242 171 254L408 500 167 742C138 771 138 800 167 829 196 858 225 858 254 829L496 587 738 829C750 842 767 846 783 846 800 846 817 842 829 829 842 817 846 804 846 783 846 767 842 750 829 737L588 500 833 258C863 229 863 200 833 171 804 137 775 137 742 167Z" />
					</svg>
				</button>

				<nav id="menu-desplegable" class="site-nav-desplegable" aria-label="Menú desplegable" inert>
					<ul>
						<li><a href="index.php" aria-current="page">Inicio</a></li>
						<li><a href="index.php#origen">Nuestro Origen</a></li>
						<li><a href="index.php#reserva">Reserva</a></li>
						<li><a href="index.php#galeria">Galería</a></li>
					</ul>
				</nav>
			</div>

			<div class="site-header-col site-header-col-social">
				<div class="site-social">
					<a href="<?= htmlspecialchars(SITIO_FACEBOOK, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Facebook">
						<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z" />
						</svg>
					</a>
					<a href="<?= htmlspecialchars(SITIO_INSTAGRAM, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Instagram">
						<svg aria-hidden="true" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z" />
						</svg>
					</a>
				</div>
			</div>

		</div>
	</header>