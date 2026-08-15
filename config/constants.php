<?php

/**
 * Origen Argentino — Constantes del sitio
 * Única fuente de verdad para datos de contacto, enlaces y tracking.
 * Cualquier cambio de teléfono, dirección o redes sociales se hace aquí.
 *
 * @author Lang-Lab / Samuel Ramírez Sánchez
 * @version 1.2.0
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

// Entorno: único dominio que los buscadores pueden indexar.
// Cualquier otro host (origen.wms.guru, IP, localhost) se sirve noindex.
define('SITIO_HOST_PRODUCCION', 'origenargentino.com');
define('SITIO_URL_PRODUCCION', 'https://' . SITIO_HOST_PRODUCCION . '/');

// Identidad del sitio
define('SITIO_NOMBRE', 'Origen Argentino');
define('SITIO_ESLOGAN', 'Somos un restaurante de parrilla estilo Argentino');
define('SITIO_DESCRIPCION', 'Restaurante de parrilla estilo argentino en la Zona Gastronómica de Tijuana. Cortes a la parrilla, empanadas, milanesas, pizzas, ensaladas y pastas. 10 años de sabor, tradición y hospitalidad.');

// Contacto
define('SITIO_TELEFONO_DISPLAY', '(664) 622-9730');
define('SITIO_TELEFONO_E164', '+526646229730');
define('SITIO_TELEFONO_LINK', 'tel:' . SITIO_TELEFONO_E164);
define('SITIO_EMAIL', 'contacto@origenargentino.com');
define('SITIO_DIRECCION', 'Escuadrón 201 3151, Aviación, Tijuana, Mexico, 22014');

// Dirección desglosada para los datos estructurados (schema.org PostalAddress)
define('SITIO_DIR_CALLE', 'Escuadrón 201 3151, Aviación');
define('SITIO_DIR_CIUDAD', 'Tijuana');
define('SITIO_DIR_ESTADO', 'Baja California');
define('SITIO_DIR_CP', '22014');
define('SITIO_DIR_PAIS', 'MX');

// Mapas: el pie y el widget flotante usan enlaces distintos, igual que el original
define('SITIO_MAPS_LINK', 'https://maps.app.goo.gl/8pMRzzpMMKAT1Hnx8');
define('SITIO_MAPS_WIDGET_LINK', 'https://maps.app.goo.gl/S4hqhLoJwPMfvCJC6');

// Reservaciones y redes sociales
define('SITIO_RESERVA_LINK', 'https://www.opentable.com.mx/r/origen-argentino-tijuana');
define('SITIO_FACEBOOK', 'https://web.facebook.com/OrigenArgentino');
define('SITIO_INSTAGRAM', 'https://www.instagram.com/origen.argentino/');

// Analítica
define('SITIO_GTM_ID', 'GT-TXHQGXJ7');

/**
 * Escapa texto para insertarlo en HTML (contenido o atributo).
 *
 * Se usa en TODA salida de las plantillas, aunque hoy el origen sean
 * constantes de confianza: cuando llegue el gestor de contenidos, los mismos
 * huecos recibirán texto editable y ya estarán blindados.
 *
 * ENT_SUBSTITUTE es la parte que no se puede omitir: sin él, un byte UTF-8
 * inválido hace que htmlspecialchars devuelva cadena vacía, y un atributo que
 * se vacía de golpe puede desarmar el marcado que lo rodea.
 *
 * @param  string $texto Texto en crudo.
 * @return string Texto seguro para HTML.
 */
if (!function_exists('esc')) {
	function esc(string $texto): string
	{
		return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
