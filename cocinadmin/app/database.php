<?php

/**
 * Origen Argentino — Conexión, esquema y operaciones base de SQLite.
 */

declare(strict_types=1);

if (!defined('ORIGEN_CMS') && !defined('ORIGEN_CMS_CLI')) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/config.php';

const CMS_SCHEMA_VERSION = 1;
const CMS_INITIAL_ADMIN_HASH = '$argon2id$v=19$m=65536,t=3,p=1$NlV2WFNReEdyVVBuQkdSTA$9IM8GKVM9+nOlY8cD4ll/oL+SJX8HWm+8AE7TuIiFm4';

function cms_root_path(): string
{
	return dirname(__DIR__);
}

function cms_storage_path(string $suffix = ''): string
{
	$base = cms_root_path() . '/.storage';
	return $suffix === '' ? $base : $base . '/' . ltrim($suffix, '/');
}

function cms_database_path(): string
{
	return cms_storage_path('origen.sqlite3');
}

/**
 * Prepara directorios privados. Solo lo llama el instalador CLI.
 */
function cms_prepare_storage(): void
{
	$directories = [
		cms_storage_path(),
		cms_storage_path('sessions'),
		cms_storage_path('tmp'),
		cms_storage_path('backups'),
	];

	foreach ($directories as $directory) {
		if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
			throw new RuntimeException('No fue posible crear el almacenamiento privado.');
		}
		@chmod($directory, 0700);
		if (!is_writable($directory)) {
			throw new RuntimeException('El almacenamiento privado no tiene permisos de escritura.');
		}
	}

	$uploadDirectory = cms_root_path() . '/uploads';
	if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
		throw new RuntimeException('No fue posible crear la carpeta pública de imágenes.');
	}
	@chmod($uploadDirectory, 0755);
	if (!is_writable($uploadDirectory)) {
		throw new RuntimeException('La carpeta pública de imágenes no tiene permisos de escritura.');
	}

	$keyPath = cms_storage_path('app.key');
	if (!is_file($keyPath)) {
		$temporary = cms_storage_path('tmp/app-key-' . bin2hex(random_bytes(8)) . '.partial');
		if (file_put_contents($temporary, random_bytes(32), LOCK_EX) !== 32) {
			throw new RuntimeException('No fue posible crear la clave privada.');
		}
		@chmod($temporary, 0600);
		if (!rename($temporary, $keyPath)) {
			@unlink($temporary);
			throw new RuntimeException('No fue posible instalar la clave privada.');
		}
		@chmod($keyPath, 0600);
	}
}

/**
 * @throws RuntimeException Si el CMS no fue instalado previamente por CLI.
 */
function cms_database(): PDO
{
	static $database = null;
	if ($database instanceof PDO) {
		return $database;
	}

	$path = cms_database_path();
	if (!is_file($path)) {
		throw new RuntimeException('El CMS todavía no está instalado.');
	}

	$database = new PDO('sqlite:' . $path, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_TIMEOUT => 5,
	]);
	$database->exec('PRAGMA foreign_keys = ON');
	$database->exec('PRAGMA busy_timeout = 5000');
	$database->exec('PRAGMA synchronous = FULL');
	$database->exec('PRAGMA trusted_schema = OFF');
	$database->exec('PRAGMA secure_delete = ON');

	return $database;
}

/**
 * Crea la base exclusivamente desde el instalador CLI.
 */
function cms_install_database(): PDO
{
	cms_prepare_storage();
	$path = cms_database_path();
	$isNew = !is_file($path);

	$database = new PDO('sqlite:' . $path, null, null, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_TIMEOUT => 5,
	]);
	$database->exec('PRAGMA foreign_keys = ON');
	$database->exec('PRAGMA busy_timeout = 5000');
	$database->exec('PRAGMA journal_mode = WAL');
	$database->exec('PRAGMA synchronous = FULL');
	$database->exec('PRAGMA trusted_schema = OFF');
	$database->exec('PRAGMA secure_delete = ON');

	cms_run_migrations($database);
	@chmod($path, 0600);
	$hasUsers = (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
	if ($isNew || !$hasUsers) {
		cms_seed_database($database);
	} else {
		cms_seed_missing_defaults($database);
	}
	$database->exec('PRAGMA optimize');

	return $database;
}

function cms_run_migrations(PDO $database): void
{
	$statements = [
		'CREATE TABLE IF NOT EXISTS schema_migrations (
			version INTEGER PRIMARY KEY,
			applied_at TEXT NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS users (
			id INTEGER PRIMARY KEY,
			username TEXT NOT NULL COLLATE NOCASE UNIQUE,
			password_hash TEXT NOT NULL,
			must_change_password INTEGER NOT NULL DEFAULT 1 CHECK (must_change_password IN (0, 1)),
			auth_version INTEGER NOT NULL DEFAULT 1,
			last_login_at TEXT,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS content_settings (
			key TEXT PRIMARY KEY,
			value TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS media_slots (
			key TEXT PRIMARY KEY,
			path TEXT NOT NULL,
			alt_text TEXT NOT NULL DEFAULT \'\',
			mime_type TEXT NOT NULL,
			width INTEGER NOT NULL CHECK (width > 0),
			height INTEGER NOT NULL CHECK (height > 0),
			byte_size INTEGER NOT NULL DEFAULT 0 CHECK (byte_size >= 0),
			sha256 TEXT,
			original_name TEXT,
			version INTEGER NOT NULL DEFAULT 1,
			updated_at TEXT NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS app_meta (
			key TEXT PRIMARY KEY,
			value TEXT NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS content_revisions (
			id INTEGER PRIMARY KEY,
			user_id INTEGER NOT NULL,
			reason TEXT NOT NULL,
			settings_json TEXT NOT NULL,
			media_json TEXT NOT NULL,
			created_at TEXT NOT NULL,
			FOREIGN KEY (user_id) REFERENCES users(id)
		)',
		'CREATE TABLE IF NOT EXISTS login_attempts (
			id INTEGER PRIMARY KEY,
			user_hash TEXT NOT NULL,
			ip_hash TEXT NOT NULL,
			succeeded INTEGER NOT NULL DEFAULT 0 CHECK (succeeded IN (0, 1)),
			attempted_at INTEGER NOT NULL
		)',
		'CREATE TABLE IF NOT EXISTS audit_log (
			id INTEGER PRIMARY KEY,
			user_id INTEGER,
			event TEXT NOT NULL,
			details_json TEXT NOT NULL DEFAULT \'{}\',
			ip_hash TEXT,
			created_at TEXT NOT NULL,
			FOREIGN KEY (user_id) REFERENCES users(id)
		)',
		'CREATE INDEX IF NOT EXISTS idx_login_attempts_user_time
		ON login_attempts(user_hash, attempted_at)',
		'CREATE INDEX IF NOT EXISTS idx_login_attempts_user_ip_time
		ON login_attempts(user_hash, ip_hash, attempted_at)',
		'CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time
		ON login_attempts(ip_hash, attempted_at)',
		'CREATE INDEX IF NOT EXISTS idx_audit_log_created_at
		ON audit_log(created_at)',
		'CREATE INDEX IF NOT EXISTS idx_content_revisions_created_at
		ON content_revisions(created_at)',
	];

	foreach ($statements as $sql) {
		$database->exec($sql);
	}

	$statement = $database->prepare(
		'INSERT OR IGNORE INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
	);
	$statement->execute([
		':version' => CMS_SCHEMA_VERSION,
		':applied_at' => gmdate('c'),
	]);
}

function cms_seed_database(PDO $database): void
{
	$now = gmdate('c');
	$database->beginTransaction();
	try {
		$user = $database->prepare(
			'INSERT INTO users (
				username, password_hash, must_change_password, auth_version, created_at, updated_at
			) VALUES (
				:username, :password_hash, 1, 1, :created_at, :updated_at
			)'
		);
		$user->execute([
			':username' => 'admin',
			':password_hash' => CMS_INITIAL_ADMIN_HASH,
			':created_at' => $now,
			':updated_at' => $now,
		]);

		cms_seed_missing_defaults($database, false);
		$database->commit();
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->rollBack();
		}
		throw $exception;
	}
}

function cms_seed_missing_defaults(PDO $database, bool $manageTransaction = true): void
{
	$now = gmdate('c');
	if ($manageTransaction) {
		$database->beginTransaction();
	}

	try {
		$setting = $database->prepare(
			'INSERT OR IGNORE INTO content_settings (key, value, updated_at)
			VALUES (:key, :value, :updated_at)'
		);
		foreach (cms_default_settings() as $key => $value) {
			$setting->execute([
				':key' => $key,
				':value' => $value,
				':updated_at' => $now,
			]);
		}

		$media = $database->prepare(
			'INSERT OR IGNORE INTO media_slots (
				key, path, alt_text, mime_type, width, height, byte_size, version, updated_at
			) VALUES (
				:key, :path, :alt_text, :mime_type, :width, :height, 0, 1, :updated_at
			)'
		);
		foreach (cms_media_slots() as $key => $slot) {
			$mime = str_ends_with((string) $slot['default_path'], '.svg') ? 'image/svg+xml' : 'image/webp';
			$media->execute([
				':key' => $key,
				':path' => $slot['default_path'],
				':alt_text' => $slot['default_alt'],
				':mime_type' => $mime,
				':width' => $slot['width'],
				':height' => $slot['height'],
				':updated_at' => $now,
			]);
		}

		$meta = $database->prepare('INSERT OR IGNORE INTO app_meta (key, value) VALUES (:key, :value)');
		$meta->execute([':key' => 'content_revision', ':value' => '1']);

		if ($manageTransaction) {
			$database->commit();
		}
	} catch (Throwable $exception) {
		if ($manageTransaction && $database->inTransaction()) {
			$database->rollBack();
		}
		throw $exception;
	}
}

function cms_content_revision(PDO $database): int
{
	$value = $database->query("SELECT value FROM app_meta WHERE key = 'content_revision'")->fetchColumn();
	return max(1, (int) $value);
}

function cms_increment_content_revision(PDO $database): int
{
	$database->exec(
		"UPDATE app_meta SET value = CAST(value AS INTEGER) + 1 WHERE key = 'content_revision'"
	);

	return cms_content_revision($database);
}

/**
 * @return array<string, string>
 */
function cms_database_settings(PDO $database): array
{
	$allowed = cms_default_settings();
	$statement = $database->query('SELECT key, value FROM content_settings');
	foreach ($statement as $row) {
		$key = (string) $row['key'];
		if (array_key_exists($key, $allowed)) {
			$allowed[$key] = (string) $row['value'];
		}
	}

	return $allowed;
}

/**
 * @return array<string, array<string, mixed>>
 */
function cms_database_media(PDO $database): array
{
	$slots = cms_media_slots();
	$rows = $database->query('SELECT * FROM media_slots');
	foreach ($rows as $row) {
		$key = (string) $row['key'];
		if (!isset($slots[$key])) {
			continue;
		}
		$slots[$key] = array_merge($slots[$key], [
			'path' => (string) $row['path'],
			'alt_text' => (string) $row['alt_text'],
			'mime_type' => (string) $row['mime_type'],
			'actual_width' => (int) $row['width'],
			'actual_height' => (int) $row['height'],
			'byte_size' => (int) $row['byte_size'],
			'sha256' => $row['sha256'] === null ? null : (string) $row['sha256'],
			'original_name' => $row['original_name'] === null ? null : (string) $row['original_name'],
			'version' => (int) $row['version'],
			'updated_at' => (string) $row['updated_at'],
		]);
	}

	return $slots;
}

function cms_create_revision(PDO $database, int $userId, string $reason): void
{
	$settings = $database->query('SELECT key, value FROM content_settings ORDER BY key')->fetchAll();
	$media = $database->query(
		'SELECT key, path, alt_text, mime_type, width, height, byte_size, sha256, version
		FROM media_slots ORDER BY key'
	)->fetchAll();
	$statement = $database->prepare(
		'INSERT INTO content_revisions (
			user_id, reason, settings_json, media_json, created_at
		) VALUES (
			:user_id, :reason, :settings_json, :media_json, :created_at
		)'
	);
	$statement->execute([
		':user_id' => $userId,
		':reason' => $reason,
		':settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
		':media_json' => json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
		':created_at' => gmdate('c'),
	]);
}

/**
 * Crea una copia consistente antes de modificar contenido o medios.
 * SQLite3::backup() sigue siendo seguro aunque la base principal use WAL.
 */
function cms_create_database_backup(): string
{
	if (!class_exists('SQLite3')) {
		throw new RuntimeException('El servidor no ofrece copias consistentes de SQLite.');
	}

	$backupDirectory = cms_storage_path('backups');
	if (!is_dir($backupDirectory) || !is_writable($backupDirectory)) {
		throw new RuntimeException('El directorio privado de respaldos no está disponible.');
	}

	$basename = 'origen-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sqlite3';
	$partialPath = $backupDirectory . '/.' . $basename . '.partial';
	$finalPath = $backupDirectory . '/' . $basename;
	$source = null;
	$backup = null;

	try {
		$source = new SQLite3(cms_database_path(), SQLITE3_OPEN_READONLY);
		$source->busyTimeout(5000);
		$backup = new SQLite3($partialPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
		$backup->busyTimeout(5000);
		if (!$source->backup($backup)) {
			throw new RuntimeException('SQLite no pudo crear el respaldo.');
		}
		$check = $backup->querySingle('PRAGMA quick_check');
		if ($check !== 'ok') {
			throw new RuntimeException('El respaldo no superó la verificación de integridad.');
		}
		$journalMode = strtolower((string) $backup->querySingle('PRAGMA journal_mode = DELETE'));
		if ($journalMode !== 'delete') {
			throw new RuntimeException('El respaldo no pudo cerrarse como archivo independiente.');
		}
		$backup->close();
		$backup = null;
		$source->close();
		$source = null;
		@chmod($partialPath, 0600);
		if (!rename($partialPath, $finalPath)) {
			throw new RuntimeException('No fue posible finalizar el respaldo.');
		}
		@chmod($finalPath, 0600);
		cms_prune_database_backups($backupDirectory, 20);
		return $finalPath;
	} finally {
		if ($backup instanceof SQLite3) {
			$backup->close();
		}
		if ($source instanceof SQLite3) {
			$source->close();
		}
		@unlink($partialPath);
		@unlink($partialPath . '-wal');
		@unlink($partialPath . '-shm');
	}
}

function cms_prune_database_backups(string $directory, int $keep): void
{
	$files = glob($directory . '/origen-[0-9]*-[a-f0-9]*.sqlite3');
	if (!is_array($files) || count($files) <= $keep) {
		return;
	}
	sort($files, SORT_STRING);
	$remove = array_slice($files, 0, count($files) - $keep);
	foreach ($remove as $path) {
		if (str_starts_with($path, $directory . '/origen-')) {
			@unlink($path);
			@unlink($path . '-wal');
			@unlink($path . '-shm');
		}
	}
}
