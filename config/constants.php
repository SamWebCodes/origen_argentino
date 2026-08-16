<?php

/**
 * Origen Argentino — Puente de contenido y configuración técnica.
 * Los datos editables llegan desde Cocinadmin; aquí permanecen únicamente
 * las constantes que consumen las plantillas y la configuración de entorno.
 *
 * @author WMS.GURU / Samuel Ramsan
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

require_once __DIR__ . '/../cocinadmin/app/content.php';

// Entorno: único dominio que los buscadores pueden indexar.
// Cualquier otro host (origen.wms.guru, IP, localhost) se sirve noindex.
define('SITIO_HOST_PRODUCCION', 'origenargentino.com');
define('SITIO_URL_PRODUCCION', 'https://' . SITIO_HOST_PRODUCCION . '/');

// Identidad del sitio
define('SITIO_NOMBRE', oa_setting('site_name'));
define('SITIO_ESLOGAN', oa_setting('site_tagline'));
define('SITIO_DESCRIPCION', oa_setting('site_description'));
define('SITIO_COCINA_PRINCIPAL', oa_setting('cuisine_primary'));
define('SITIO_COCINA_SECUNDARIA', oa_setting('cuisine_secondary'));

// Autor (identidad pública para buscadores)
define('SITIO_AUTOR', 'Samuel Ramsan');
define('SITIO_AUTOR_PROFESION', 'Desarrollador web');
define('SITIO_AUTOR_URL', 'https://github.com/SamWebCodes');

// Contacto
define('SITIO_TELEFONO_DISPLAY', oa_setting('phone_display'));
define('SITIO_TELEFONO_E164', oa_setting('phone_e164'));
define('SITIO_TELEFONO_LINK', 'tel:' . SITIO_TELEFONO_E164);
define('SITIO_EMAIL', oa_setting('email'));
define('SITIO_DIRECCION', oa_setting('address_display'));

// Dirección desglosada para los datos estructurados (schema.org PostalAddress)
define('SITIO_DIR_CALLE', oa_setting('address_street'));
define('SITIO_DIR_CIUDAD', oa_setting('address_city'));
define('SITIO_DIR_ESTADO', oa_setting('address_state'));
define('SITIO_DIR_CP', oa_setting('address_postal_code'));
define('SITIO_DIR_PAIS', oa_setting('address_country_code'));

// Mapas: el pie y el widget flotante usan enlaces distintos, igual que el original
define('SITIO_MAPS_LINK', oa_setting('maps_footer_url'));
define('SITIO_MAPS_WIDGET_LINK', oa_setting('maps_widget_url'));

// Reservaciones y redes sociales
define('SITIO_RESERVA_LINK', oa_setting('reservation_url'));
define('SITIO_FACEBOOK', oa_setting('facebook_url'));
define('SITIO_INSTAGRAM', oa_setting('instagram_url'));

// Analítica
define('SITIO_GTM_ID', 'GT-TXHQGXJ7');

/**
 * Escapa texto para insertarlo en HTML (contenido o atributo).
 *
 * Se usa en TODA salida de las plantillas porque sus valores pueden venir del
 * gestor de contenidos y nunca deben llegar al marcado sin escape contextual.
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
