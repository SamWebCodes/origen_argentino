<?php

/**
 * Origen Argentino — Puente público de contenido.
 *
 * Lee SQLite en modo consulta y vuelve a los valores originales ante cualquier
 * fallo. Nunca crea la base ni inicia sesiones desde el sitio público.
 */

declare(strict_types=1);

if (
	!defined('ORIGEN_ARGENTINO')
	&& !defined('ORIGEN_PUBLIC_CONTENT')
	&& !defined('ORIGEN_PUBLIC_CSS')
	&& !defined('ORIGEN_CMS')
) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/config.php';

/**
 * @return array{settings: array<string, string>, media: array<string, array<string, mixed>>, revision: int, healthy: bool}
 */
function oa_public_content(): array
{
	static $content = null;
	if (is_array($content)) {
		return $content;
	}

	$settings = cms_default_settings();
	$media = [];
	foreach (cms_media_slots() as $key => $slot) {
		$media[$key] = array_merge($slot, [
			'path' => $slot['default_path'],
			'alt_text' => $slot['default_alt'],
			'mime_type' => str_ends_with((string) $slot['default_path'], '.svg') ? 'image/svg+xml' : 'image/webp',
			'actual_width' => $slot['width'],
			'actual_height' => $slot['height'],
			'byte_size' => 0,
			'version' => 1,
			'is_default' => true,
		]);
	}
	$revision = 1;
	$healthy = false;

	$databasePath = dirname(__DIR__) . '/.storage/origen.sqlite3';
	if (!is_file($databasePath)) {
		$content = compact('settings', 'media', 'revision', 'healthy');
		return $content;
	}

	try {
		$database = new PDO('sqlite:' . $databasePath, null, null, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_TIMEOUT => 2,
		]);
		$database->exec('PRAGMA query_only = ON');
		$database->exec('PRAGMA busy_timeout = 2000');
		$database->exec('PRAGMA trusted_schema = OFF');
		$database->beginTransaction();
		$loadedSettings = $settings;
		$loadedMedia = $media;

		foreach ($database->query('SELECT key, value FROM content_settings') as $row) {
			$key = (string) $row['key'];
			if (array_key_exists($key, $loadedSettings)) {
				$value = (string) $row['value'];
				if (!mb_check_encoding($value, 'UTF-8')) {
					throw new UnexpectedValueException('SQLite contiene texto con codificación inválida.');
				}
				$loadedSettings[$key] = $value;
			}
		}

		foreach ($database->query('SELECT key, path, alt_text, mime_type, width, height, byte_size, version FROM media_slots') as $row) {
			$key = (string) $row['key'];
			if (!isset($loadedMedia[$key])) {
				continue;
			}

			$path = (string) $row['path'];
			$altText = (string) $row['alt_text'];
			if (!mb_check_encoding($path, 'UTF-8') || !mb_check_encoding($altText, 'UTF-8')) {
				throw new UnexpectedValueException('SQLite contiene medios con codificación inválida.');
			}
			if (!oa_media_path_is_safe($key, $path)) {
				throw new UnexpectedValueException('SQLite contiene una ruta de medio no permitida.');
			}
			$absolute = dirname(__DIR__, 2) . '/' . $path;
			if (!is_file($absolute)) {
				throw new UnexpectedValueException('SQLite referencia un medio inexistente.');
			}

			$loadedMedia[$key] = array_merge($loadedMedia[$key], [
				'path' => $path,
				'alt_text' => $altText,
				'mime_type' => (string) $row['mime_type'],
				'actual_width' => max(1, (int) $row['width']),
				'actual_height' => max(1, (int) $row['height']),
				'byte_size' => max(0, (int) $row['byte_size']),
				'version' => max(1, (int) $row['version']),
				'is_default' => $path === $loadedMedia[$key]['default_path'],
			]);
		}

		$revisionValue = $database->query(
			"SELECT value FROM app_meta WHERE key = 'content_revision'"
		)->fetchColumn();
		if (!is_string($revisionValue) || preg_match('/^[1-9][0-9]*$/D', $revisionValue) !== 1) {
			throw new UnexpectedValueException('SQLite no contiene una revisión válida.');
		}
		$database->commit();
		$settings = $loadedSettings;
		$media = $loadedMedia;
		$revision = max(1, (int) $revisionValue);
		$healthy = true;
	} catch (Throwable $exception) {
		if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
			$database->rollBack();
		}
		error_log('Origen Argentino: no se pudo leer el contenido dinámico. ' . $exception->getMessage());
	}

	$content = compact('settings', 'media', 'revision', 'healthy');
	return $content;
}

function oa_media_path_is_safe(string $key, string $path): bool
{
	$slots = cms_media_slots();
	if (!isset($slots[$key])) {
		return false;
	}
	if (hash_equals((string) $slots[$key]['default_path'], $path)) {
		return true;
	}

	$quotedKey = preg_quote($key, '/');
	return preg_match(
		'/^cocinadmin\/uploads\/' . $quotedKey . '-[a-f0-9]{64}-[a-f0-9]{32}\.webp$/D',
		$path
	) === 1;
}

function oa_setting(string $key): string
{
	$content = oa_public_content();
	if (!array_key_exists($key, $content['settings'])) {
		throw new InvalidArgumentException('Clave de contenido desconocida.');
	}

	return $content['settings'][$key];
}

/**
 * @return array<string, mixed>
 */
function oa_media(string $key): array
{
	$content = oa_public_content();
	if (!array_key_exists($key, $content['media'])) {
		throw new InvalidArgumentException('Slot de imagen desconocido.');
	}

	return $content['media'][$key];
}

function oa_media_url(string $key): string
{
	$media = oa_media($key);
	$url = (string) $media['path'];
	if (!($media['is_default'] ?? false)) {
		$url .= '?v=' . max(1, (int) $media['version']);
	}

	return $url;
}

function oa_media_css_url(string $key): string
{
	return '../' . oa_media_url($key);
}

function oa_content_revision(): int
{
	return oa_public_content()['revision'];
}

function oa_content_is_healthy(): bool
{
	return oa_public_content()['healthy'];
}
