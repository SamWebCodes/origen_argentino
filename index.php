<?php

/**
 * Origen Argentino — Página principal
 * Sitio one-page: hero, origen, reserva, galería y footer.
 *
 * Flujo:
 *   config/constants.php  → datos del sitio
 *   includes/header.php   → head + navegación
 *   includes/footer.php   → footer + scripts
 */

declare(strict_types=1);

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/header.php';

/**
 * Trazo del titular animado (widget "animated headline" del original).
 * El mismo path se reutiliza en el hero y en la sección de reserva;
 * el color y el grosor los define el CSS de cada sección.
 */
$trazo_svg = '<svg class="titular-trazo" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">'
	. '<path d="M7.7,145.6C109,125,299.9,116.2,401,121.3c42.1,2.2,87.6,11.8,87.3,25.7"/>'
	. '</svg>';
?>

<!-- ============ HERO: Bienvenido a casa ============ -->
<section class="hero" aria-label="Bienvenida">
	<div class="hero-inner">
		<h1 class="titular">
			<span class="titular-marcado">Bienvenido a casa<?= $trazo_svg ?></span>
		</h1>
		<div class="anima anima-tada">
			<a class="boton" href="<?= htmlspecialchars(SITIO_RESERVA_LINK, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
				Reserva Aquí
			</a>
		</div>
	</div>
</section>

<main id="contenido">

	<!-- ============ NUESTRO ORIGEN ============ -->
	<section id="origen" class="seccion-origen">
		<div class="seccion-origen-grid">
			<div class="seccion-origen-texto">
				<h2 class="titulo-seccion anima anima-slide">Nuestro Origen</h2>
				<div>
					<p>Somos un restaurante de parrilla estilo argentino, ubicado en el corazón de la Zona Gastronómica de Tijuana, en una de las fronteras más transitadas del mundo.</p>
					<p>Desde hace 10 años, nos dedicamos a brindar una experiencia gastronómica auténtica y confortable, donde la calidad, el sabor y la tradición argentina se viven en cada detalle.</p>
					<p>De nuestra cocina a tu mesa llegan cortes a la parrilla, empanadas, milanesas, pizzas, ensaladas y pastas, entre muchas otras especialidades que forman parte de nuestro menú y de la historia que compartimos con quienes nos visitan día con día.</p>
					<p class="seccion-origen-firma"><strong>Bienvenido al Origen Argentino.</strong><br>10 años celebrando sabor, tradición y hospitalidad.</p>
				</div>
			</div>
			<div class="seccion-origen-imagen">
				<img src="assets/img/nosotros.webp" alt="Plato de parrilla estilo argentino de Origen Argentino" width="1980" height="1320" loading="lazy">
			</div>
		</div>
	</section>

	<!-- ============ RESERVA ============ -->
	<section id="reserva" class="seccion-reserva">
		<div class="seccion-reserva-inner">
			<div class="seccion-reserva-boton">
				<div class="anima anima-tada">
					<a class="boton" href="<?= htmlspecialchars(SITIO_RESERVA_LINK, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
						Reserva Aquí
					</a>
				</div>
			</div>
			<div class="seccion-reserva-titulo">
				<h2 class="titular">
					<span class="titular-texto">Cocina de origen</span>
					<span class="titular-marcado">Argentino<?= $trazo_svg ?></span>
				</h2>
			</div>
		</div>
	</section>

	<!-- ============ GALERÍA ============ -->
	<section id="galeria" class="seccion-galeria" aria-label="Galería de Origen Argentino">
		<div class="carrusel">
			<div class="carrusel-visor">
				<ul class="carrusel-pista">
					<li class="carrusel-item">
						<span class="carrusel-figura">
							<img src="assets/img/galeria-01.webp" alt="Galería Origen Argentino — fotografía 1" width="1600" height="1067" loading="lazy">
						</span>
					</li>
					<li class="carrusel-item">
						<span class="carrusel-figura">
							<img src="assets/img/galeria-02.webp" alt="Galería Origen Argentino — fotografía 2" width="1600" height="1280" loading="lazy">
						</span>
					</li>
					<li class="carrusel-item">
						<span class="carrusel-figura">
							<img src="assets/img/galeria-03.webp" alt="Galería Origen Argentino — fotografía 3" width="1600" height="914" loading="lazy">
						</span>
					</li>
					<li class="carrusel-item">
						<span class="carrusel-figura">
							<img src="assets/img/galeria-04.webp" alt="Galería Origen Argentino — fotografía 4" width="1600" height="1422" loading="lazy">
						</span>
					</li>
				</ul>
			</div>
			<button class="carrusel-flecha carrusel-flecha-prev" type="button" aria-label="Anterior">
				<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">
					<path d="M34.52 239.03L228.87 44.69c9.37-9.37 24.57-9.37 33.94 0l22.67 22.67c9.36 9.36 9.37 24.52.04 33.9L131.49 256l154.02 154.75c9.34 9.38 9.32 24.54-.04 33.9l-22.67 22.67c-9.37 9.37-24.57 9.37-33.94 0L34.52 272.97c-9.37-9.37-9.37-24.57 0-33.94z" />
				</svg>
			</button>
			<button class="carrusel-flecha carrusel-flecha-next" type="button" aria-label="Siguiente">
				<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">
					<path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z" />
				</svg>
			</button>
		</div>
	</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>