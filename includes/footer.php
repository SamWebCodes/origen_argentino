<?php

/**
 * Origen Argentino — Footer global
 * Tres columnas (logo · dirección · teléfono) y el widget flotante
 * de contacto con teléfono, correo y mapa, igual que en el original.
 *
 * @var string $csp_nonce Nonce de la petición; lo genera includes/header.php
 *                        y llega aquí por el ámbito de index.php. El CSP usa
 *                        'strict-dynamic', así que sin nonce el script se bloquea.
 */

declare(strict_types=1);
?>
<footer class="site-footer">

	<div class="site-footer-col site-footer-col-logo">
		<a href="index.php" class="site-footer-logo" aria-label="Ir al inicio">
			<img src="assets/img/logo-negro.svg" alt="Logo de Origen Argentino" width="48" height="58" loading="lazy">
		</a>
	</div>

	<div class="site-footer-col site-footer-col-datos">
		<a class="boton-subir anima anima-rebote" href="#header" aria-label="Volver arriba">
			<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg">
				<path d="M288.662 352H31.338c-17.818 0-26.741-21.543-14.142-34.142l128.662-128.662c7.81-7.81 20.474-7.81 28.284 0l128.662 128.662c12.6 12.599 3.676 34.142-14.142 34.142z" />
			</svg>
		</a>

		<ul class="site-footer-datos">
			<li>
				<a href="<?= htmlspecialchars(SITIO_MAPS_LINK, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
					<span class="icono">
						<svg aria-hidden="true" viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M172.268 501.67C26.97 291.031 0 269.413 0 192 0 85.961 85.961 0 192 0s192 85.961 192 192c0 77.413-26.97 99.031-172.268 309.67-9.535 13.774-29.93 13.773-39.464 0zM192 272c44.183 0 80-35.817 80-80s-35.817-80-80-80-80 35.817-80 80 35.817 80 80 80z" />
						</svg>
					</span>
					<span class="texto"><?= htmlspecialchars(SITIO_DIRECCION, ENT_QUOTES, 'UTF-8') ?></span>
				</a>
			</li>
		</ul>

		<img class="site-footer-linea" src="assets/img/linea-1.webp" alt="" width="400" height="11" loading="lazy">
	</div>

	<div class="site-footer-col site-footer-col-tel">
		<ul class="site-footer-datos">
			<li>
				<a href="<?= htmlspecialchars(SITIO_TELEFONO_LINK, ENT_QUOTES, 'UTF-8') ?>">
					<span class="icono">
						<svg aria-hidden="true" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
							<path d="M497.39 361.8l-112-48a24 24 0 0 0-28 6.9l-49.6 60.6A370.66 370.66 0 0 1 130.6 204.11l60.6-49.6a23.94 23.94 0 0 0 6.9-28l-48-112A24.16 24.16 0 0 0 122.6.61l-104 24A24 24 0 0 0 0 48c0 256.5 207.9 464 464 464a24 24 0 0 0 23.4-18.6l24-104a24.29 24.29 0 0 0-14.01-27.6z" />
						</svg>
					</span>
					<span class="texto"><?= htmlspecialchars(SITIO_TELEFONO_DISPLAY, ENT_QUOTES, 'UTF-8') ?></span>
				</a>
			</li>
		</ul>

		<img class="site-footer-linea" src="assets/img/linea-2.webp" alt="" width="300" height="8" loading="lazy">
	</div>

</footer>

<!-- ============ Widget flotante de contacto ============ -->
<div class="widget-contacto">

	<div class="widget-contacto-canales" id="canales-contacto" inert>
		<a class="widget-contacto-canal" href="<?= htmlspecialchars(SITIO_TELEFONO_LINK, ENT_QUOTES, 'UTF-8') ?>">
			<span class="widget-contacto-etiqueta">Llámanos</span>
			<svg aria-hidden="true" viewBox="0 0 39 39" xmlns="http://www.w3.org/2000/svg">
				<circle cx="19.4395" cy="19.4395" r="19.4395" fill="#03E78B" />
				<path fill="#ffffff" transform="translate(9.07179 9.07178)" d="M19.3929 14.9176C17.752 14.7684 16.2602 14.3209 14.7684 13.7242C14.0226 13.4259 13.1275 13.7242 12.8292 14.4701L11.7849 16.2602C8.65222 14.6193 6.11623 11.9341 4.47529 8.95057L6.41458 7.90634C7.16046 7.60799 7.45881 6.71293 7.16046 5.96705C6.56375 4.47529 6.11623 2.83435 5.96705 1.34259C5.96705 0.596704 5.22117 0 4.47529 0H0.745882C0.298353 0 0 0.298352 0 0.745881C0 3.72941 0.596704 6.71293 1.93929 9.3981C3.87858 13.575 7.30964 16.8569 11.3374 18.7962C14.0226 20.1388 17.0061 20.7355 19.9896 20.7355C20.4371 20.7355 20.7355 20.4371 20.7355 19.9896V16.4094C20.7355 15.5143 20.1388 14.9176 19.3929 14.9176Z" />
			</svg>
			<span class="visually-hidden">Llámanos por teléfono</span>
		</a>

		<a class="widget-contacto-canal" href="mailto:<?= htmlspecialchars(SITIO_EMAIL, ENT_QUOTES, 'UTF-8') ?>">
			<span class="widget-contacto-etiqueta">Contáctanos</span>
			<svg aria-hidden="true" viewBox="0 0 39 39" xmlns="http://www.w3.org/2000/svg">
				<circle cx="19.4395" cy="19.4395" r="19.4395" fill="#FF485F" />
				<path fill="#ffffff" transform="translate(8.48619 12.3117)" d="M20.5379 14.2557H1.36919C0.547677 14.2557 0 13.7373 0 12.9597V1.29597C0 0.518387 0.547677 0 1.36919 0H20.5379C21.3594 0 21.9071 0.518387 21.9071 1.29597V12.9597C21.9071 13.7373 21.3594 14.2557 20.5379 14.2557ZM1.36919 1.29597V12.9597H20.5379V1.29597H1.36919Z" />
				<path fill="#ffffff" transform="translate(8.47443 12.9478)" d="M10.9659 8.43548C10.829 8.43548 10.692 8.43548 10.5551 8.30588L0.286184 1.17806C0.012346 0.918864 -0.124573 0.530073 0.149265 0.270879C0.423104 0.0116857 0.833862 -0.117911 1.1077 0.141283L10.9659 7.00991L20.8241 0.141283C21.0979 -0.117911 21.5087 0.0116857 21.7825 0.270879C22.0563 0.530073 21.9194 0.918864 21.6456 1.17806L11.3766 8.30588C11.2397 8.43548 11.1028 8.43548 10.9659 8.43548Z" />
				<path fill="#ffffff" transform="translate(20.6183 18.7799)" d="M9.0906 7.13951C8.95368 7.13951 8.81676 7.13951 8.67984 7.00991L0.327768 1.17806C-0.0829894 0.918864 -0.0829899 0.530073 0.190849 0.270879C0.327768 0.0116855 0.738525 -0.117911 1.14928 0.141282L9.50136 5.97314C9.7752 6.23233 9.91212 6.62112 9.63828 6.88032C9.50136 7.00991 9.36444 7.13951 9.0906 7.13951Z" />
				<path fill="#ffffff" transform="translate(8.47443 18.7799)" d="M0.696942 7.13951C0.423104 7.13951 0.286185 7.00991 0.149265 6.88032C-0.124573 6.62112 0.012346 6.23233 0.286185 5.97314L8.63826 0.141282C9.04902 -0.117911 9.45977 0.0116855 9.59669 0.270879C9.87053 0.530073 9.73361 0.918864 9.45977 1.17806L1.1077 7.00991C0.970781 7.13951 0.833862 7.13951 0.696942 7.13951Z" />
			</svg>
			<span class="visually-hidden">Escríbenos por correo</span>
		</a>

		<a class="widget-contacto-canal" href="<?= htmlspecialchars(SITIO_MAPS_WIDGET_LINK, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
			<span class="widget-contacto-etiqueta">Visítanos</span>
			<svg aria-hidden="true" viewBox="0 0 39 39" xmlns="http://www.w3.org/2000/svg">
				<circle cx="19.4395" cy="19.4395" r="19.4395" fill="#37AA66" />
				<path fill="#ffffff" fill-rule="evenodd" clip-rule="evenodd" transform="translate(11.3764 9.07178)" d="M0 8.06381C0 3.68631 3.68633 0 8.06383 0C12.4413 0 16.1276 3.68631 16.1276 8.06381C16.1276 12.2109 9.67659 19.5835 8.9854 20.2747C8.755 20.5051 8.29422 20.7355 8.06383 20.7355C7.83344 20.7355 7.37263 20.5051 7.14224 20.2747C6.45107 19.5835 0 12.2109 0 8.06381ZM11.5203 8.06378C11.5203 9.97244 9.97302 11.5197 8.06436 11.5197C6.15572 11.5197 4.60844 9.97244 4.60844 8.06378C4.60844 6.15515 6.15572 4.60788 8.06436 4.60788C9.97302 4.60788 11.5203 6.15515 11.5203 8.06378Z" />
			</svg>
			<span class="visually-hidden">Cómo llegar</span>
		</a>
	</div>

	<div class="widget-contacto-base">
		<span class="widget-contacto-cta">Contáctenos</span>
		<button class="widget-contacto-boton" type="button" aria-expanded="false" aria-controls="canales-contacto" aria-label="Abrir opciones de contacto">
			<svg class="icono-abrir" aria-hidden="true" viewBox="-496.8 507.1 54 54" xmlns="http://www.w3.org/2000/svg">
				<circle cx="-469.8" cy="534.1" r="27" fill="#000000" />
				<path fill="#ffffff" d="M-459.5,523.5H-482c-2.1,0-3.7,1.7-3.7,3.7v13.1c0,2.1,1.7,3.7,3.7,3.7h19.3l5.4,5.4c0.2,0.2,0.4,0.2,0.7,0.2c0.2,0,0.2,0,0.4,0c0.4-0.2,0.6-0.6,0.6-0.9v-21.5C-455.8,525.2-457.5,523.5-459.5,523.5z" />
				<path fill="none" stroke="#808080" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M-476.5,537.3c2.5,1.1,8.5,2.1,13-2.7" />
				<path fill="none" stroke="#808080" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M-460.8,534.5c-0.1-1.2-0.8-3.4-3.3-2.8" />
			</svg>
			<svg class="icono-cerrar" aria-hidden="true" viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
				<ellipse cx="26" cy="26" rx="26" ry="26" fill="#000000" />
				<rect width="27.1433" height="3.89857" rx="1.94928" transform="translate(18.35 15.6599) rotate(45)" fill="#ffffff" />
				<rect width="27.1433" height="3.89857" rx="1.94928" transform="translate(37.5056 18.422) rotate(135)" fill="#ffffff" />
			</svg>
		</button>
	</div>

</div>

<script src="assets/js/main.js" defer nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>