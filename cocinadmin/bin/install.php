<?php

/**
 * Instalador/migrador de Cocinadmin. Solo puede ejecutarse por línea de comandos.
 *
 * Uso desde la raíz del proyecto: php cocinadmin/bin/install.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}

define('ORIGEN_CMS_CLI', true);
umask(0077);

$requirements = [
	'PHP 8.1 o posterior' => version_compare(PHP_VERSION, '8.1.0', '>='),
	'PDO SQLite' => extension_loaded('pdo_sqlite'),
	'SQLite3' => extension_loaded('sqlite3'),
	'Fileinfo' => extension_loaded('fileinfo'),
	'GD' => extension_loaded('gd'),
	'WebP en GD' => function_exists('imagewebp') && function_exists('imagecreatefromwebp'),
	'Mbstring' => extension_loaded('mbstring'),
	'Argon2id' => defined('PASSWORD_ARGON2ID'),
];

$missing = array_keys(array_filter($requirements, static fn(bool $ready): bool => !$ready));
if ($missing !== []) {
	fwrite(STDERR, "Faltan requisitos:\n- " . implode("\n- ", $missing) . "\n");
	exit(1);
}

require_once dirname(__DIR__) . '/app/database.php';

try {
	$database = cms_install_database();
	$integrity = (string) $database->query('PRAGMA quick_check')->fetchColumn();
	$foreignKeys = $database->query('PRAGMA foreign_key_check')->fetchAll();
	if ($integrity !== 'ok' || $foreignKeys !== []) {
		throw new RuntimeException('SQLite no superó las comprobaciones de integridad.');
	}

	fwrite(STDOUT, "Cocinadmin instalado correctamente.\n");
	fwrite(STDOUT, 'Base: ' . cms_database_path() . "\n");
	fwrite(STDOUT, "Usuario temporal: admin\n");
} catch (Throwable $exception) {
	fwrite(STDERR, 'Instalación fallida: ' . $exception->getMessage() . "\n");
	exit(1);
}
