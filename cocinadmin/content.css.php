<?php

/**
 * Hoja pública mínima para los fondos que viven en CSS.
 * Solo la revisión actual y saludable recibe caché inmutable; cualquier URL
 * adelantada, obsoleta o de fallback queda marcada no-store.
 */

declare(strict_types=1);

define('ORIGEN_PUBLIC_CSS', true);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header_remove('X-Powered-By');
header('Content-Type: text/css; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");

require_once __DIR__ . '/app/content.php';

$revision = oa_content_revision();
$requestedVersion = is_string($_GET['v'] ?? null) ? $_GET['v'] : '';
$isCurrentVersion = preg_match('/^[1-9][0-9]{0,18}$/D', $requestedVersion) === 1
	&& (int) $requestedVersion === $revision
	&& oa_content_is_healthy();
header(
	$isCurrentVersion
		? 'Cache-Control: public, max-age=31536000, immutable'
		: 'Cache-Control: no-store, max-age=0'
);

$hero = oa_media_css_url('hero_background');
$texture = oa_media_css_url('origin_texture');
$reservation = oa_media_css_url('reservation_background');

// Las rutas solo pueden ser defaults del catálogo o WebP con SHA-256 generado
// por el CMS; content.php descarta cualquier otra cadena antes de llegar aquí.
?>
.hero{background-image:url('<?= $hero ?>')}
.seccion-origen-texto,.seccion-reserva-titulo{background-image:url('<?= $texture ?>')}
.seccion-reserva{background-image:url('<?= $reservation ?>')}
