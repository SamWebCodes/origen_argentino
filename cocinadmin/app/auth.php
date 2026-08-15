<?php

/**
 * Origen Argentino — Autenticación y control de fuerza bruta.
 */

declare(strict_types=1);

if (!defined('ORIGEN_CMS')) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

const CMS_DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=3,p=1$M1VMcksvL29XSzAxSy5yUw$nNCz4s7dZ5DvG2+xbsQurVB1TNdwYmia38o+RN++So0';

/**
 * @return array<string, mixed>|null
 */
function cms_current_user(PDO $database): ?array
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return null;
	}

	$userId = $_SESSION['user_id'] ?? null;
	if (!is_int($userId) && !ctype_digit((string) $userId)) {
		return null;
	}

	$now = time();
	$startedAt = (int) ($_SESSION['started_at'] ?? 0);
	$lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
	$sessionAgent = (string) ($_SESSION['user_agent_hash'] ?? '');
	if (
		$startedAt < 1
		|| $lastActivity < 1
		|| ($now - $lastActivity) > CMS_IDLE_TIMEOUT
		|| ($now - $startedAt) > CMS_ABSOLUTE_TIMEOUT
		|| !hash_equals($sessionAgent, cms_user_agent_hash())
	) {
		cms_destroy_session();
		return null;
	}

	$statement = $database->prepare(
		'SELECT id, username, password_hash, must_change_password, auth_version, last_login_at
		FROM users WHERE id = :id LIMIT 1'
	);
	$statement->execute([':id' => (int) $userId]);
	$user = $statement->fetch();
	if (!is_array($user)) {
		cms_destroy_session();
		return null;
	}

	if ((int) $user['auth_version'] !== (int) ($_SESSION['auth_version'] ?? 0)) {
		cms_destroy_session();
		return null;
	}

	$_SESSION['last_activity'] = $now;
	$regeneratedAt = (int) ($_SESSION['regenerated_at'] ?? 0);
	if (($now - $regeneratedAt) > 600) {
		session_regenerate_id(true);
		$_SESSION['regenerated_at'] = $now;
	}

	return $user;
}

/**
 * @return array{ok: bool, message: string, retry_after: int}
 */
function cms_attempt_login(PDO $database, string $username, string $password): array
{
	$username = trim($username);
	if (
		!mb_check_encoding($username, 'UTF-8')
		|| mb_strlen($username, 'UTF-8') > 64
		|| strlen($password) > 512
	) {
		return ['ok' => false, 'message' => 'Usuario o contraseña incorrectos.', 'retry_after' => 0];
	}

	$userHash = hash('sha256', mb_strtolower($username, 'UTF-8'));
	$ipHash = cms_remote_ip_hash();
	$reservation = cms_reserve_login_attempt($database, $userHash, $ipHash);
	if (!$reservation['allowed']) {
		return [
			'ok' => false,
			'message' => 'Demasiados intentos. Espera unos minutos antes de volver a intentar.',
			'retry_after' => $reservation['retry_after'],
		];
	}

	$statement = $database->prepare(
		'SELECT id, username, password_hash, must_change_password, auth_version
		FROM users WHERE username = :username COLLATE NOCASE LIMIT 1'
	);
	$statement->execute([':username' => $username]);
	$user = $statement->fetch();
	$statement->closeCursor();
	$storedHash = is_array($user) ? (string) $user['password_hash'] : CMS_DUMMY_PASSWORD_HASH;
	$verification = cms_verify_password_guarded($password, $storedHash);
	if ($verification === null) {
		return [
			'ok' => false,
			'message' => 'El acceso seguro está ocupado. Espera un momento e intenta de nuevo.',
			'retry_after' => 2,
		];
	}

	if (!$verification['valid'] || !is_array($user)) {
		usleep(random_int(120000, 260000));
		return ['ok' => false, 'message' => 'Usuario o contraseña incorrectos.', 'retry_after' => 0];
	}

	$userId = (int) $user['id'];
	if (!cms_mark_login_attempt_success(
		$database,
		(int) $reservation['attempt_id'],
		$userHash,
		$ipHash
	)) {
		return [
			'ok' => false,
			'message' => 'El acceso seguro está ocupado. Espera un momento e intenta de nuevo.',
			'retry_after' => 2,
		];
	}
	$database->prepare(
		'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id'
	)->execute([
		':last_login_at' => gmdate('c'),
		':updated_at' => gmdate('c'),
		':id' => $userId,
	]);

	if (is_string($verification['rehash'])) {
		$database->prepare(
			'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at
			WHERE id = :id AND auth_version = :auth_version AND password_hash = :previous_hash'
		)->execute([
			':password_hash' => $verification['rehash'],
			':updated_at' => gmdate('c'),
			':id' => $userId,
			':auth_version' => (int) $user['auth_version'],
			':previous_hash' => $storedHash,
		]);
	}

	cms_start_session();
	session_regenerate_id(true);
	$now = time();
	$_SESSION['user_id'] = $userId;
	$_SESSION['auth_version'] = (int) $user['auth_version'];
	$_SESSION['started_at'] = $now;
	$_SESSION['last_activity'] = $now;
	$_SESSION['regenerated_at'] = $now;
	$_SESSION['user_agent_hash'] = cms_user_agent_hash();
	$_SESSION['csrf_secret'] = bin2hex(random_bytes(32));
	cms_audit($database, $userId, 'auth.login');

	return ['ok' => true, 'message' => '', 'retry_after' => 0];
}

function cms_password_algorithm(): string|int
{
	if (!defined('PASSWORD_ARGON2ID')) {
		throw new RuntimeException('El servidor no ofrece Argon2id.');
	}

	return PASSWORD_ARGON2ID;
}

/**
 * @return array<string, int>
 */
function cms_password_options(): array
{
	return [
		'memory_cost' => 65536,
		'time_cost' => 3,
		'threads' => 1,
	];
}

/**
 * Reserva el fallo antes de ejecutar Argon2id. BEGIN IMMEDIATE serializa la
 * comprobación y el INSERT: ningún burst puede verificar sin quedar contado.
 *
 * @return array{allowed: bool, attempt_id: int, retry_after: int}
 */
function cms_reserve_login_attempt(PDO $database, string $userHash, string $ipHash): array
{
	$now = time();
	try {
		$database->exec('BEGIN IMMEDIATE');
		$cleanup = $database->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff');
		$cleanup->execute([':cutoff' => $now - 86400]);
		$cleanup->closeCursor();

		$retryAfter = cms_login_retry_after($database, $userHash, $ipHash, $now);
		if ($retryAfter > 0) {
			$database->exec('COMMIT');
			return ['allowed' => false, 'attempt_id' => 0, 'retry_after' => $retryAfter];
		}

		$statement = $database->prepare(
			'INSERT INTO login_attempts (user_hash, ip_hash, succeeded, attempted_at)
			VALUES (:user_hash, :ip_hash, 0, :attempted_at)'
		);
		$statement->execute([
			':user_hash' => $userHash,
			':ip_hash' => $ipHash,
			':attempted_at' => $now,
		]);
		$statement->closeCursor();
		$attemptId = (int) $database->lastInsertId();
		if ($attemptId < 1) {
			throw new RuntimeException('No fue posible reservar el intento de acceso.');
		}
		$database->exec('COMMIT');
		return ['allowed' => true, 'attempt_id' => $attemptId, 'retry_after' => 0];
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		error_log('Cocinadmin: no fue posible contabilizar un intento de acceso. ' . $exception->getMessage());
		return ['allowed' => false, 'attempt_id' => 0, 'retry_after' => 5];
	}
}

function cms_login_retry_after(PDO $database, string $userHash, string $ipHash, int $now): int
{
	$user = $database->prepare(
		'SELECT COUNT(*) AS attempts, MIN(attempted_at) AS first_attempt
		FROM login_attempts
		WHERE user_hash = :user_hash AND ip_hash = :ip_hash
			AND succeeded = 0 AND attempted_at >= :cutoff'
	);
	$user->execute([':user_hash' => $userHash, ':ip_hash' => $ipHash, ':cutoff' => $now - 900]);
	$userRow = $user->fetch();
	$user->closeCursor();
	if (is_array($userRow) && (int) $userRow['attempts'] >= 5) {
		return max(1, ((int) $userRow['first_attempt'] + 900) - $now);
	}

	$ip = $database->prepare(
		'SELECT COUNT(*) AS attempts, MIN(attempted_at) AS first_attempt
		FROM login_attempts
		WHERE ip_hash = :ip_hash AND succeeded = 0 AND attempted_at >= :cutoff'
	);
	$ip->execute([':ip_hash' => $ipHash, ':cutoff' => $now - 3600]);
	$ipRow = $ip->fetch();
	$ip->closeCursor();
	if (is_array($ipRow) && (int) $ipRow['attempts'] >= 20) {
		return max(1, ((int) $ipRow['first_attempt'] + 3600) - $now);
	}

	return 0;
}

/**
 * @return resource|null
 */
function cms_acquire_password_lock(): mixed
{
	$handle = @fopen(cms_storage_path('auth.lock'), 'c');
	if (!is_resource($handle)) {
		return null;
	}
	@chmod(cms_storage_path('auth.lock'), 0600);
	if (!@flock($handle, LOCK_EX | LOCK_NB)) {
		fclose($handle);
		return null;
	}

	return $handle;
}

/**
 * @param resource $handle
 */
function cms_release_password_lock(mixed $handle): void
{
	if (is_resource($handle)) {
		@flock($handle, LOCK_UN);
		fclose($handle);
	}
}

/**
 * @return array{valid: bool, rehash: string|null}|null
 */
function cms_verify_password_guarded(string $password, string $storedHash): ?array
{
	$lock = cms_acquire_password_lock();
	if (!is_resource($lock)) {
		return null;
	}

	try {
		$valid = password_verify($password, $storedHash);
		$rehash = null;
		if ($valid && password_needs_rehash($storedHash, cms_password_algorithm(), cms_password_options())) {
			$candidate = password_hash($password, cms_password_algorithm(), cms_password_options());
			$rehash = is_string($candidate) ? $candidate : null;
		}
		return ['valid' => $valid, 'rehash' => $rehash];
	} finally {
		cms_release_password_lock($lock);
	}
}

function cms_mark_login_attempt_success(
	PDO $database,
	int $attemptId,
	string $userHash,
	string $ipHash
): bool {
	try {
		$database->exec('BEGIN IMMEDIATE');
		$update = $database->prepare(
			'UPDATE login_attempts SET succeeded = 1
			WHERE id = :id AND user_hash = :user_hash AND ip_hash = :ip_hash'
		);
		$update->execute([':id' => $attemptId, ':user_hash' => $userHash, ':ip_hash' => $ipHash]);
		$updated = $update->rowCount() === 1;
		$update->closeCursor();
		if (!$updated) {
			throw new RuntimeException('La reserva de acceso dejó de existir.');
		}
		$cleanup = $database->prepare(
			'DELETE FROM login_attempts
			WHERE user_hash = :user_hash AND ip_hash = :ip_hash AND succeeded = 0'
		);
		$cleanup->execute([':user_hash' => $userHash, ':ip_hash' => $ipHash]);
		$cleanup->closeCursor();
		$database->exec('COMMIT');
		return true;
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		error_log('Cocinadmin: no fue posible confirmar un acceso. ' . $exception->getMessage());
		return false;
	}
}

/**
 * @return array{ok: bool, errors: array<string, string>}
 */
function cms_change_password(
	PDO $database,
	array $user,
	string $currentPassword,
	string $newPassword,
	string $confirmation
): array {
	$errors = [];
	$validEncoding = mb_check_encoding($newPassword, 'UTF-8');
	$length = $validEncoding ? mb_strlen($newPassword, 'UTF-8') : 0;
	if (!$validEncoding || str_contains($newPassword, "\0")) {
		$errors['new_password'] = 'La contraseña contiene caracteres no válidos.';
	} elseif ($length < 15) {
		$errors['new_password'] = 'Usa al menos 15 caracteres.';
	} elseif ($length > 128) {
		$errors['new_password'] = 'Usa un máximo de 128 caracteres.';
	} elseif (strlen($newPassword) > 512) {
		$errors['new_password'] = 'La contraseña supera el máximo seguro de 512 bytes.';
	}
	if (!hash_equals($newPassword, $confirmation)) {
		$errors['password_confirmation'] = 'Las contraseñas no coinciden.';
	}

	$lock = cms_acquire_password_lock();
	if (!is_resource($lock)) {
		$errors['current_password'] = 'La verificación segura está ocupada. Intenta de nuevo.';
		return ['ok' => false, 'errors' => $errors];
	}

	$hash = null;
	try {
		if (
			strlen($currentPassword) > 512
			|| str_contains($currentPassword, "\0")
			|| !password_verify($currentPassword, (string) $user['password_hash'])
		) {
			$errors['current_password'] = 'La contraseña actual no es correcta.';
		}
		if (
			$validEncoding
			&& !str_contains($newPassword, "\0")
			&& password_verify($newPassword, (string) $user['password_hash'])
		) {
			$errors['new_password'] = 'La contraseña nueva debe ser diferente.';
		}
		if ($errors === []) {
			$candidate = password_hash($newPassword, cms_password_algorithm(), cms_password_options());
			$hash = is_string($candidate) ? $candidate : null;
		}
	} finally {
		cms_release_password_lock($lock);
	}

	if ($errors !== []) {
		return ['ok' => false, 'errors' => $errors];
	}
	if (!is_string($hash)) {
		return ['ok' => false, 'errors' => ['new_password' => 'No fue posible proteger la contraseña.']];
	}

	$database->exec('BEGIN IMMEDIATE');
	try {
		$statement = $database->prepare(
			'UPDATE users
			SET password_hash = :password_hash,
				must_change_password = 0,
				auth_version = auth_version + 1,
				updated_at = :updated_at
			WHERE id = :id AND auth_version = :auth_version'
		);
		$statement->execute([
			':password_hash' => $hash,
			':updated_at' => gmdate('c'),
			':id' => (int) $user['id'],
			':auth_version' => (int) $user['auth_version'],
		]);
		$updated = $statement->rowCount() === 1;
		$statement->closeCursor();
		if (!$updated) {
			$database->exec('ROLLBACK');
			return [
				'ok' => false,
				'errors' => ['current_password' => 'Otra sesión cambió la cuenta. Inicia sesión de nuevo.'],
			];
		}
		cms_audit($database, (int) $user['id'], 'auth.password_changed');
		$database->exec('COMMIT');
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		throw $exception;
	}

	session_regenerate_id(true);
	$_SESSION['auth_version'] = (int) $user['auth_version'] + 1;
	$_SESSION['regenerated_at'] = time();
	$_SESSION['csrf_secret'] = bin2hex(random_bytes(32));

	return ['ok' => true, 'errors' => []];
}
