<?php

/**
 * Origen Argentino — Cabeceras, sesión, CSRF y utilidades de seguridad.
 */

declare(strict_types=1);

if (!defined('ORIGEN_CMS')) {
	http_response_code(403);
	exit;
}

const CMS_IDLE_TIMEOUT = 1200;
const CMS_ABSOLUTE_TIMEOUT = 28800;

function cms_is_https(): bool
{
	if (strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on') {
		return true;
	}
	if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
		return true;
	}

	// Las cabeceras forwarded solo son autoridad si el operador declaró
	// explícitamente la IP del proxy. Sin allowlist se ignoran y el panel falla
	// cerrado, evitando que un cliente directo finja estar bajo HTTPS.
	if (!cms_request_comes_from_trusted_proxy()) {
		return false;
	}

	return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
		|| strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
}

function cms_request_comes_from_trusted_proxy(): bool
{
	return in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), cms_trusted_proxy_ips(), true);
}

/**
 * @return list<string>
 */
function cms_trusted_proxy_ips(): array
{
	$configured = getenv('ORIGEN_TRUSTED_PROXY_IPS');
	if (!is_string($configured) || trim($configured) === '') {
		return [];
	}
	$allowed = [];
	foreach (explode(',', $configured) as $candidate) {
		$candidate = trim($candidate);
		if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
			$allowed[] = $candidate;
		}
	}
	return array_values(array_unique($allowed));
}

function cms_client_ip(): string
{
	$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
	$trusted = cms_trusted_proxy_ips();
	if (!in_array($remote, $trusted, true)) {
		return $remote;
	}

	$chain = [];
	foreach (explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $candidate) {
		$candidate = trim($candidate);
		if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
			$chain[] = $candidate;
		}
	}
	$chain[] = $remote;
	for ($index = count($chain) - 1; $index >= 0; $index--) {
		if (!in_array($chain[$index], $trusted, true)) {
			return $chain[$index];
		}
	}

	return $remote;
}

function cms_is_loopback_request(): bool
{
	$remote = strtolower((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
	$host = (string) preg_replace('/:\d+$/D', '', $host);
	return in_array($remote, ['127.0.0.1', '::1'], true)
		&& in_array($host, ['127.0.0.1', 'localhost', '[::1]'], true);
}

function cms_session_cookie_name(): string
{
	return cms_is_https() ? '__Host-oa_admin' : 'oa_admin_local';
}

function cms_has_session_cookie(): bool
{
	$name = cms_session_cookie_name();
	$value = $_COOKIE[$name] ?? null;
	if ($value === null) {
		return false;
	}
	if (is_string($value) && preg_match('/^[A-Za-z0-9,-]{16,128}$/D', $value) === 1) {
		return true;
	}

	unset($_COOKIE[$name]);
	$secure = cms_is_https();
	setcookie($name, '', [
		'expires' => time() - 42000,
		'path' => $secure ? '/' : '/cocinadmin',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Strict',
	]);
	return false;
}

function cms_send_security_headers(): void
{
	header_remove('X-Powered-By');
	header('Cache-Control: no-store, private, max-age=0');
	header('Pragma: no-cache');
	header('Expires: 0');
	header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
	header('X-Frame-Options: DENY');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
	header('Cross-Origin-Opener-Policy: same-origin');
	header('Cross-Origin-Resource-Policy: same-origin');
	header('Permissions-Policy: accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), microphone=(), payment=(), usb=()');
	header(
		"Content-Security-Policy: default-src 'none'; "
		. "base-uri 'none'; object-src 'none'; frame-ancestors 'none'; "
		. "form-action 'self'; script-src 'none'; style-src 'self'; "
		. "img-src 'self'; font-src 'self'; connect-src 'none'; "
		. "media-src 'none'; worker-src 'none'; manifest-src 'none'; "
		. 'upgrade-insecure-requests'
	);
}

/**
 * @throws RuntimeException Si el almacenamiento privado no está instalado.
 */
function cms_start_session(): void
{
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	$sessionPath = dirname(__DIR__) . '/.storage/sessions';
	if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
		throw new RuntimeException('El almacenamiento de sesiones no está disponible.');
	}

	$secure = cms_is_https();
	session_name(cms_session_cookie_name());
	session_save_path($sessionPath);
	ini_set('session.use_strict_mode', '1');
	ini_set('session.use_only_cookies', '1');
	ini_set('session.use_trans_sid', '0');
	ini_set('session.gc_probability', '1');
	ini_set('session.gc_divisor', '100');
	ini_set('session.gc_maxlifetime', (string) CMS_ABSOLUTE_TIMEOUT);
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => $secure ? '/' : '/cocinadmin',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Strict',
	]);

	if (!session_start()) {
		throw new RuntimeException('No fue posible iniciar la sesión segura.');
	}

	if (!isset($_SESSION['csrf_secret']) || !is_string($_SESSION['csrf_secret'])) {
		$_SESSION['csrf_secret'] = bin2hex(random_bytes(32));
	}
}

function cms_app_key(): string
{
	static $key = null;
	if (is_string($key)) {
		return $key;
	}

	$path = dirname(__DIR__) . '/.storage/app.key';
	$key = @file_get_contents($path);
	if (!is_string($key) || strlen($key) !== 32) {
		throw new RuntimeException('La clave privada del CMS no está disponible.');
	}

	return $key;
}

function cms_csrf_token(string $action): string
{
	$secret = (string) ($_SESSION['csrf_secret'] ?? '');
	if (strlen($secret) !== 64) {
		throw new RuntimeException('La sesión no contiene un token válido.');
	}

	return hash_hmac('sha256', $action, $secret);
}

function cms_csrf_is_valid(string $action, mixed $token): bool
{
	return is_string($token) && hash_equals(cms_csrf_token($action), $token);
}

/**
 * Token de login sin sesión persistente. Evita crear un archivo por cada GET
 * anónimo y queda ligado a hora, IP y navegador durante diez minutos.
 */
function cms_login_csrf_token(): string
{
	$payload = time() . '.' . bin2hex(random_bytes(16));
	$context = cms_remote_ip_hash() . '|' . cms_user_agent_hash();
	$signature = hash_hmac('sha256', $payload . '|' . $context, cms_app_key());
	return $payload . '.' . $signature;
}

function cms_login_csrf_is_valid(mixed $token): bool
{
	if (!is_string($token) || strlen($token) !== 108) {
		return false;
	}
	$parts = explode('.', $token);
	if (
		count($parts) !== 3
		|| preg_match('/^[0-9]{10}$/D', $parts[0]) !== 1
		|| preg_match('/^[a-f0-9]{32}$/D', $parts[1]) !== 1
		|| preg_match('/^[a-f0-9]{64}$/D', $parts[2]) !== 1
	) {
		return false;
	}
	$issuedAt = (int) $parts[0];
	$now = time();
	if ($issuedAt > ($now + 30) || ($now - $issuedAt) > 600) {
		return false;
	}
	$payload = $parts[0] . '.' . $parts[1];
	$context = cms_remote_ip_hash() . '|' . cms_user_agent_hash();
	$expected = hash_hmac('sha256', $payload . '|' . $context, cms_app_key());
	return hash_equals($expected, $parts[2]);
}

function cms_validate_request_origin(): bool
{
	$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
	if ($fetchSite === 'cross-site') {
		error_log('Cocinadmin: POST rechazado por Sec-Fetch-Site=cross-site.');
		return false;
	}

	$source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
	if ($source === '') {
		$source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
	}
	if ($source === '') {
		return true;
	}
	if ($source === 'null') {
		// Algunos navegadores controlados aíslan el origen en desarrollo local.
		// Esta excepción no existe en producción y todavía exige cookie Strict,
		// token CSRF y una petición que no sea cross-site.
		return cms_is_loopback_request();
	}

	$effectiveScheme = cms_is_https() ? 'https' : 'http';
	$sourceScheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));
	$sourceHost = parse_url($source, PHP_URL_HOST);
	$sourcePort = parse_url($source, PHP_URL_PORT);
	if (
		$sourceScheme !== $effectiveScheme
		|| !is_string($sourceHost)
		|| $sourceHost === ''
		|| parse_url($source, PHP_URL_USER) !== null
		|| parse_url($source, PHP_URL_PASS) !== null
	) {
		error_log('Cocinadmin: POST rechazado por cabecera Origin/Referer no analizable.');
		return false;
	}
	$sourceAuthority = cms_authority_key($sourceHost, is_int($sourcePort) ? $sourcePort : null, $effectiveScheme);

	$currentHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
	if (preg_match('/^(?:\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::[0-9]{1,5})?$/D', $currentHeader) !== 1) {
		error_log('Cocinadmin: POST rechazado por Host no válido.');
		return false;
	}
	$currentParts = parse_url($effectiveScheme . '://' . $currentHeader);
	if (!is_array($currentParts) || !isset($currentParts['host'])) {
		return false;
	}
	$currentAuthority = cms_authority_key(
		(string) $currentParts['host'],
		isset($currentParts['port']) ? (int) $currentParts['port'] : null,
		$effectiveScheme
	);

	$valid = hash_equals($currentAuthority, $sourceAuthority);
	if (!$valid) {
		error_log('Cocinadmin: POST rechazado por origen distinto al host actual.');
	}
	return $valid;
}

function cms_authority_key(string $host, ?int $port, string $scheme): string
{
	$defaultPort = $scheme === 'https' ? 443 : 80;
	return strtolower($host) . ':' . ($port ?? $defaultPort);
}

function cms_remote_ip_hash(): string
{
	return hash_hmac('sha256', cms_client_ip(), cms_app_key());
}

function cms_user_agent_hash(): string
{
	return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function cms_escape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * @param array<string, mixed> $details
 */
function cms_audit(PDO $database, ?int $userId, string $event, array $details = []): void
{
	$statement = $database->prepare(
		'INSERT INTO audit_log (user_id, event, details_json, ip_hash, created_at)
		VALUES (:user_id, :event, :details_json, :ip_hash, :created_at)'
	);
	$statement->execute([
		':user_id' => $userId,
		':event' => $event,
		':details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
		':ip_hash' => cms_remote_ip_hash(),
		':created_at' => gmdate('c'),
	]);
}

function cms_flash(string $type, string $message): void
{
	$_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * @return array{type: string, message: string}|null
 */
function cms_take_flash(): ?array
{
	$flash = $_SESSION['flash'] ?? null;
	unset($_SESSION['flash']);
	if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
		return null;
	}

	return [
		'type' => (string) $flash['type'],
		'message' => (string) $flash['message'],
	];
}

function cms_redirect(string $section = 'dashboard'): never
{
	$allowed = ['dashboard', 'content', 'business', 'media', 'security'];
	if (!in_array($section, $allowed, true)) {
		$section = 'dashboard';
	}
	header('Location: index.php?section=' . rawurlencode($section), true, 303);
	exit;
}

function cms_destroy_session(): void
{
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', [
			'expires' => time() - 42000,
			'path' => $params['path'],
			'domain' => $params['domain'],
			'secure' => $params['secure'],
			'httponly' => $params['httponly'],
			'samesite' => 'Strict',
		]);
	}
	session_destroy();
}
