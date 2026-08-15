<?php

/**
 * Origen Argentino — Constantes del sitio
 * Única fuente de verdad para datos de contacto, enlaces y tracking.
 * Cualquier cambio de teléfono, dirección o redes sociales se hace aquí.
 *
 * @author Lang-Lab / Samuel Ramírez Sánchez
 * @version 1.1.0
 */

declare(strict_types=1);

// Entorno: único dominio que los buscadores pueden indexar.
// Cualquier otro host (origen.wms.guru, IP, localhost) se sirve noindex.
define('SITIO_HOST_PRODUCCION', 'origenargentino.com');

// Identidad del sitio
define('SITIO_NOMBRE', 'Origen Argentino');
define('SITIO_ESLOGAN', 'Somos un restaurante de parrilla estilo Argentino');
define('SITIO_DESCRIPCION', 'Restaurante de parrilla estilo argentino en la Zona Gastronómica de Tijuana. Cortes a la parrilla, empanadas, milanesas, pizzas, ensaladas y pastas. 10 años de sabor, tradición y hospitalidad.');

// Contacto
define('SITIO_TELEFONO_DISPLAY', '(664) 622-9730');
define('SITIO_TELEFONO_LINK', 'tel:+526646229730');
define('SITIO_EMAIL', 'contacto@origenargentino.com');
define('SITIO_DIRECCION', 'Escuadrón 201 3151, Aviación, Tijuana, Mexico, 22014');

// Mapas: el pie y el widget flotante usan enlaces distintos, igual que el original
define('SITIO_MAPS_LINK', 'https://maps.app.goo.gl/8pMRzzpMMKAT1Hnx8');
define('SITIO_MAPS_WIDGET_LINK', 'https://maps.app.goo.gl/S4hqhLoJwPMfvCJC6');

// Reservaciones y redes sociales
define('SITIO_RESERVA_LINK', 'https://www.opentable.com.mx/r/origen-argentino-tijuana');
define('SITIO_FACEBOOK', 'https://web.facebook.com/OrigenArgentino');
define('SITIO_INSTAGRAM', 'https://www.instagram.com/origen.argentino/');

// Analítica
define('SITIO_GTM_ID', 'GT-TXHQGXJ7');
