<?php

/**
 * Cocinadmin — Gestor de contenidos de Origen Argentino.
 *
 * Único punto de entrada del panel. Todas las escrituras pasan por sesión,
 * autorización, token CSRF, catálogo cerrado de campos y consultas preparadas.
 */

declare(strict_types=1);

define('ORIGEN_CMS', true);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header_remove('X-Powered-By');

require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/validation.php';
require_once __DIR__ . '/app/media.php';

cms_send_security_headers();
set_exception_handler(static function (Throwable $exception): void {
	error_log('Cocinadmin: error no controlado. ' . $exception->getMessage());
	if (!headers_sent()) {
		http_response_code(503);
		cms_send_security_headers();
	}
	cms_render_unavailable();
});

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!cms_is_https() && !cms_is_loopback_request()) {
	error_log('Cocinadmin: petición rechazada porque el panel requiere HTTPS.');
	http_response_code(403);
	cms_render_unavailable();
	exit;
}

$database = null;
$bootError = false;
try {
	$database = cms_database();
	cms_app_key();
	if (cms_has_session_cookie()) {
		cms_start_session();
	}
} catch (Throwable $exception) {
	$bootError = true;
	error_log('Cocinadmin: fallo de arranque. ' . $exception->getMessage());
}

if ($bootError || !$database instanceof PDO) {
	http_response_code(503);
	cms_render_unavailable();
	exit;
}

$section = (string) ($_GET['section'] ?? 'dashboard');
$allowedSections = ['dashboard', 'content', 'business', 'media', 'security'];
if (!in_array($section, $allowedSections, true)) {
	$section = 'dashboard';
}

$user = cms_current_user($database);
if ($user === null && session_status() === PHP_SESSION_ACTIVE) {
	// Una cookie inválida o expirada no conserva un archivo de sesión huérfano.
	cms_destroy_session();
}
$pageError = '';
$formErrors = [];
$mediaErrors = [];
$loginUsername = '';
$submittedSettings = [];

if ($requestMethod === 'POST') {
	$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
	if ($contentLength > 12582912) {
		http_response_code(413);
		$pageError = 'La petición supera el máximo de 12 MiB.';
	} elseif (!cms_validate_request_origin()) {
		http_response_code(403);
		$pageError = 'La solicitud fue rechazada por seguridad. Recarga la página e intenta de nuevo.';
	} else {
		$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
		$token = $_POST['_token'] ?? null;
		$processed = null;

		try {
			if ($action === 'login' && $user === null) {
				$loginUsername = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
				$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
				if (!cms_login_csrf_is_valid($token)) {
					http_response_code(403);
					$pageError = 'La sesión del formulario venció. Recarga la página.';
				} else {
					$result = cms_attempt_login($database, $loginUsername, $password);
					if ($result['ok']) {
						$loggedUser = cms_current_user($database);
						cms_redirect(
							is_array($loggedUser) && (int) $loggedUser['must_change_password'] === 1
								? 'security'
								: 'dashboard'
						);
					}
					if ($result['retry_after'] > 0) {
						http_response_code(429);
						header('Retry-After: ' . $result['retry_after']);
					}
					$pageError = $result['message'];
				}
			} elseif ($user === null) {
				http_response_code(403);
				$pageError = 'Inicia sesión para continuar.';
			} elseif (!cms_csrf_is_valid($action, $token)) {
				http_response_code(403);
				$pageError = 'La sesión del formulario venció. Recarga la página.';
			} elseif ($action === 'logout') {
				cms_audit($database, (int) $user['id'], 'auth.logout');
				cms_destroy_session();
				header('Location: index.php', true, 303);
				exit;
			} elseif ($action === 'change_password') {
				$currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
				$newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
				$confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
				$result = cms_change_password($database, $user, $currentPassword, $newPassword, $confirmation);
				if ($result['ok']) {
					cms_flash('success', 'Contraseña actualizada. Tu sesión quedó protegida con la nueva clave.');
					cms_redirect('dashboard');
				}
				$formErrors = $result['errors'];
				$section = 'security';
			} elseif ((int) $user['must_change_password'] === 1) {
				$pageError = 'Primero debes reemplazar la contraseña temporal.';
				$section = 'security';
			} elseif ($action === 'save_settings') {
				$area = is_string($_POST['area'] ?? null) ? $_POST['area'] : '';
				$settingsInput = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
				$expectedRevision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT);
				if (!in_array($area, ['content', 'business'], true) || $expectedRevision === false) {
					$pageError = 'La solicitud de contenido no es válida.';
				} else {
					$validation = cms_validate_settings($area, $settingsInput);
					$submittedSettings = $validation['values'];
					$formErrors = $validation['errors'];
					$section = $area;
					if ($formErrors === []) {
						$saved = cms_save_settings(
							$database,
							(int) $user['id'],
							$area,
							$validation['values'],
							(int) $expectedRevision
						);
						if (!$saved) {
							$pageError = 'Otra sesión guardó cambios antes. Recarga para evitar sobrescribirlos.';
						} else {
							cms_flash('success', 'Los cambios se publicaron correctamente.');
							cms_redirect($area);
						}
					}
				}
			} elseif ($action === 'save_media') {
				$section = 'media';
				$slotKey = is_string($_POST['slot'] ?? null) ? $_POST['slot'] : '';
				$altText = is_string($_POST['alt_text'] ?? null) ? $_POST['alt_text'] : '';
				$expectedRevision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT);
				$slots = cms_media_slots();
				if (!isset($slots[$slotKey]) || $expectedRevision === false) {
					$pageError = 'La posición de imagen no es válida.';
				} else {
					$file = is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [];
					$hasFile = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
					try {
						$altText = cms_validate_media_alt_text($slots[$slotKey], $altText);
						if ($hasFile) {
							$processed = cms_process_image_upload($slotKey, $file, $slots[$slotKey]);
						}
						$saved = cms_save_media_slot(
							$database,
							(int) $user['id'],
							$slotKey,
							$altText,
							$processed,
							(int) $expectedRevision
						);
						if (!$saved) {
							$pageError = 'Otra sesión guardó cambios antes. Recarga para evitar sobrescribirlos.';
						} else {
							cms_flash('success', 'La imagen se publicó de forma segura.');
							cms_redirect('media');
						}
					} catch (InvalidArgumentException $exception) {
						if (($processed['created'] ?? false) && isset($processed['absolute_path'])) {
							@unlink((string) $processed['absolute_path']);
							$processed = null;
						}
						$mediaErrors[$slotKey] = $exception->getMessage();
					}
				}
			} elseif ($action === 'restore_media') {
				$section = 'media';
				$slotKey = is_string($_POST['slot'] ?? null) ? $_POST['slot'] : '';
				$expectedRevision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT);
				$slots = cms_media_slots();
				if (!isset($slots[$slotKey]) || $expectedRevision === false) {
					$pageError = 'La posición de imagen o la revisión no son válidas.';
				} else {
					$restored = cms_restore_media_default(
						$database,
						(int) $user['id'],
						$slotKey,
						(int) $expectedRevision
					);
					if (!$restored) {
						$pageError = 'Otra sesión guardó cambios antes. Recarga para evitar sobrescribirlos.';
					} else {
						cms_flash('success', 'Se restauró la imagen original.');
						cms_redirect('media');
					}
				}
			} else {
				http_response_code(400);
				$pageError = 'La acción solicitada no existe.';
			}
		} catch (Throwable $exception) {
			if (($processed['created'] ?? false) && isset($processed['absolute_path'])) {
				@unlink((string) $processed['absolute_path']);
			}
			error_log('Cocinadmin: operación fallida (' . $action . '). ' . $exception->getMessage());
			http_response_code(500);
			$pageError = 'No fue posible completar la operación. No se publicaron cambios; intenta de nuevo.';
		}
	}
}

$user = cms_current_user($database);
if ($user === null) {
	if (session_status() === PHP_SESSION_ACTIVE) {
		cms_destroy_session();
	}
	cms_render_login($loginUsername, $pageError);
	exit;
}

if ((int) $user['must_change_password'] === 1) {
	$section = 'security';
}

$flash = cms_take_flash();
$settings = cms_database_settings($database);
foreach ($submittedSettings as $key => $value) {
	if (array_key_exists($key, $settings)) {
		$settings[$key] = $value;
	}
}
$media = cms_database_media($database);
$revision = cms_content_revision($database);

cms_render_panel(
	$database,
	$user,
	$section,
	$settings,
	$media,
	$revision,
	$flash,
	$pageError,
	$formErrors,
	$mediaErrors
);

function cms_render_document_start(string $title): void
{
	?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
		<title><?= cms_escape($title) ?> — Cocinadmin</title>
		<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
	</head>

	<body class="admin-body">
	<?php
}

function cms_render_document_end(): void
{
	?>
	</body>

	</html>
	<?php
}

function cms_render_unavailable(): void
{
	cms_render_document_start('Servicio no disponible');
	?>
	<main class="auth-shell">
		<section class="auth-card" aria-labelledby="unavailable-title">
			<div class="auth-brand">
				<span class="auth-mark" aria-hidden="true">OA</span>
				<h1 id="unavailable-title">Cocinadmin no está disponible</h1>
				<p>La instalación privada no está completa o el almacenamiento no tiene permisos.</p>
			</div>
			<div class="alert alert-error" role="alert">
				<p>Por seguridad no se muestran detalles aquí. Revisa el registro del servidor.</p>
			</div>
		</section>
	</main>
	<?php
	cms_render_document_end();
}

function cms_render_login(string $username, string $error): void
{
	cms_render_document_start('Acceso');
	?>
	<main class="auth-shell">
		<section class="auth-card" aria-labelledby="login-title">
			<div class="auth-brand">
				<span class="auth-mark" aria-hidden="true">OA</span>
				<h1 id="login-title">Cocinadmin</h1>
				<p>La cocina digital de Origen Argentino.</p>
			</div>

			<?php if ($error !== ''): ?>
				<div class="alert alert-error" role="alert"><p><?= cms_escape($error) ?></p></div>
			<?php endif; ?>

			<form method="post" action="index.php" autocomplete="on">
				<input type="hidden" name="action" value="login">
				<input type="hidden" name="_token" value="<?= cms_escape(cms_login_csrf_token()) ?>">
				<div class="section-stack">
					<div class="field">
						<label for="username">Usuario</label>
						<input id="username" name="username" type="text" value="<?= cms_escape($username) ?>" maxlength="64" autocomplete="username" required autofocus>
					</div>
					<div class="field">
						<label for="password">Contraseña</label>
						<input id="password" name="password" type="password" maxlength="512" autocomplete="current-password" required>
					</div>
				</div>
				<div class="actions">
					<button class="button button-primary" type="submit">Entrar al panel</button>
				</div>
			</form>
		</section>
	</main>
	<?php
	cms_render_document_end();
}

/**
 * @param array<string, mixed> $user
 * @param array<string, string> $settings
 * @param array<string, array<string, mixed>> $media
 * @param array{type: string, message: string}|null $flash
 * @param array<string, string> $formErrors
 * @param array<string, string> $mediaErrors
 */
function cms_render_panel(
	PDO $database,
	array $user,
	string $section,
	array $settings,
	array $media,
	int $revision,
	?array $flash,
	string $pageError,
	array $formErrors,
	array $mediaErrors
): void {
	$titles = [
		'dashboard' => 'Resumen',
		'content' => 'Contenido del sitio',
		'business' => 'Datos del negocio',
		'media' => 'Biblioteca de imágenes',
		'security' => 'Seguridad',
	];
	cms_render_document_start($titles[$section] ?? 'Panel');
	?>
	<div class="admin-shell">
		<aside class="admin-sidebar">
			<a class="admin-brand" href="index.php?section=dashboard">
				<span class="admin-brand-mark" aria-hidden="true">OA</span>
				<span>Cocinadmin<br>Origen Argentino</span>
			</a>

			<nav aria-label="Secciones del administrador">
				<ul class="admin-nav">
					<?php foreach ($titles as $key => $label): ?>
						<li>
							<a class="admin-nav-link<?= $section === $key ? ' is-active' : '' ?>" href="index.php?section=<?= cms_escape($key) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>>
								<?= cms_escape($label) ?>
							</a>
						</li>
					<?php endforeach; ?>
					<li><a class="admin-nav-link" href="../" target="_blank" rel="noopener">Ver sitio público</a></li>
				</ul>
			</nav>

			<div class="admin-sidebar-footer">
				<span>Sesión segura · <?= cms_escape((string) $user['username']) ?></span>
				<form class="inline-form" method="post" action="index.php">
					<input type="hidden" name="action" value="logout">
					<input type="hidden" name="_token" value="<?= cms_escape(cms_csrf_token('logout')) ?>">
					<button class="button button-secondary" type="submit">Cerrar sesión</button>
				</form>
			</div>
		</aside>

		<div class="admin-main">
			<header class="admin-topbar">
				<p class="admin-topbar-title"><?= cms_escape($titles[$section] ?? 'Panel') ?></p>
				<span class="admin-user">Administrador · <?= cms_escape((string) $user['username']) ?></span>
			</header>

			<main class="admin-content">
				<?php if ((int) $user['must_change_password'] === 1): ?>
					<div class="alert alert-warning" role="alert"><p>La contraseña actual es temporal. Debes reemplazarla antes de editar el sitio.</p></div>
				<?php endif; ?>
				<?php if ($flash !== null): ?>
					<div class="alert alert-<?= cms_escape($flash['type']) ?>" role="status"><p><?= cms_escape($flash['message']) ?></p></div>
				<?php endif; ?>
				<?php if ($pageError !== ''): ?>
					<div class="alert alert-error" role="alert"><p><?= cms_escape($pageError) ?></p></div>
				<?php endif; ?>

				<?php if ($section === 'dashboard'): ?>
					<?php cms_render_dashboard($database, $settings, $media, $revision); ?>
				<?php elseif ($section === 'content' || $section === 'business'): ?>
					<?php cms_render_settings_editor($section, $settings, $revision, $formErrors); ?>
				<?php elseif ($section === 'media'): ?>
					<?php cms_render_media_editor($media, $revision, $mediaErrors); ?>
				<?php else: ?>
					<?php cms_render_security_editor($user, $formErrors); ?>
				<?php endif; ?>
			</main>
		</div>
	</div>
	<?php
	cms_render_document_end();
}

/**
 * @param array<string, string> $settings
 * @param array<string, array<string, mixed>> $media
 */
function cms_render_dashboard(PDO $database, array $settings, array $media, int $revision): void
{
	$customImages = count(array_filter(
		$media,
		static fn(array $item): bool => (string) ($item['path'] ?? '') !== (string) ($item['default_path'] ?? '')
	));
	$revisionCount = (int) $database->query('SELECT COUNT(*) FROM content_revisions')->fetchColumn();
	$activity = $database->query(
		'SELECT event, details_json, created_at FROM audit_log ORDER BY id DESC LIMIT 8'
	)->fetchAll();
	?>
	<div class="page-heading">
		<div>
			<p class="eyebrow">Todo bajo control</p>
			<h1>La cocina digital está lista</h1>
			<p class="lead">Edita el contenido sin tocar la estructura, las animaciones ni el diseño público.</p>
		</div>
		<a class="button button-primary" href="../" target="_blank" rel="noopener">Abrir sitio</a>
	</div>

	<section class="stats-grid" aria-label="Estado del contenido">
		<article class="stat-card"><span>Campos editables</span><strong><?= count($settings) ?></strong><p>Textos, contacto y enlaces.</p></article>
		<article class="stat-card"><span>Imágenes personalizadas</span><strong><?= $customImages ?></strong><p>De <?= count($media) ?> posiciones disponibles.</p></article>
		<article class="stat-card"><span>Revisiones guardadas</span><strong><?= $revisionCount ?></strong><p>Historial previo a cada cambio.</p></article>
		<article class="stat-card"><span>Versión publicada</span><strong><?= $revision ?></strong><p>Carga dinámica desde SQLite.</p></article>
	</section>

	<div class="card-grid">
		<section class="card">
			<div class="card-header"><div><h2>Accesos rápidos</h2><p>Ve directo a lo que quieres cambiar.</p></div></div>
			<div class="section-stack">
				<a class="button button-secondary" href="index.php?section=content">Editar textos del SPA</a>
				<a class="button button-secondary" href="index.php?section=business">Actualizar contacto y enlaces</a>
				<a class="button button-secondary" href="index.php?section=media">Reemplazar imágenes</a>
			</div>
		</section>

		<section class="card">
			<div class="card-header"><div><h2>Actividad reciente</h2><p>Registro de accesos y publicaciones.</p></div></div>
			<?php if ($activity === []): ?>
				<div class="empty-state"><div><strong>Sin actividad todavía</strong><p>Los eventos seguros aparecerán aquí.</p></div></div>
			<?php else: ?>
				<ul class="activity-list">
					<?php foreach ($activity as $event): ?>
						<li>
							<strong><?= cms_escape(cms_event_label((string) $event['event'])) ?></strong>
							<time datetime="<?= cms_escape((string) $event['created_at']) ?>"><?= cms_escape(cms_format_date((string) $event['created_at'])) ?></time>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
	<?php
}

/**
 * @param array<string, string> $settings
 * @param array<string, string> $errors
 */
function cms_render_settings_editor(string $area, array $settings, int $revision, array $errors): void
{
	$title = $area === 'content' ? 'Todos los textos del SPA' : 'Datos del restaurante';
	$lead = $area === 'content'
		? 'El marcado y el diseño permanecen intactos; aquí solo cambias el contenido de cada bloque.'
		: 'Estos datos alimentan el sitio visible, los enlaces y la información para buscadores.';
	?>
	<div class="page-heading">
		<div><p class="eyebrow">Edición segura</p><h1><?= cms_escape($title) ?></h1><p class="lead"><?= cms_escape($lead) ?></p></div>
	</div>

	<form method="post" action="index.php?section=<?= cms_escape($area) ?>">
		<input type="hidden" name="action" value="save_settings">
		<input type="hidden" name="area" value="<?= cms_escape($area) ?>">
		<input type="hidden" name="revision" value="<?= $revision ?>">
		<input type="hidden" name="_token" value="<?= cms_escape(cms_csrf_token('save_settings')) ?>">

		<div class="section-stack">
			<?php foreach (cms_setting_groups() as $group): ?>
				<?php if ($group['area'] !== $area) {
					continue;
				} ?>
				<section class="card">
					<div class="card-header"><div><h2><?= cms_escape((string) $group['label']) ?></h2><p><?= cms_escape((string) $group['description']) ?></p></div></div>
					<div class="form-grid">
						<?php foreach ($group['fields'] as $key => $definition): ?>
							<?php cms_render_setting_field($key, $definition, $settings[$key] ?? '', $errors[$key] ?? ''); ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>

		<div class="actions">
			<a class="button button-secondary" href="../" target="_blank" rel="noopener">Revisar sitio</a>
			<button class="button button-primary" type="submit">Publicar cambios</button>
		</div>
	</form>
	<?php
}

/**
 * @param array<string, mixed> $definition
 */
function cms_render_setting_field(string $key, array $definition, string $value, string $error): void
{
	$type = (string) $definition['type'];
	$isTextarea = $type === 'textarea';
	$htmlType = match ($type) {
		'email' => 'email',
		'url_https' => 'url',
		default => 'text',
	};
	$wide = $isTextarea || $type === 'url_https' || in_array($key, ['site_description', 'address_display'], true);
	?>
	<div class="field<?= $wide ? ' field-wide' : '' ?><?= $error !== '' ? ' has-error' : '' ?>">
		<label for="field-<?= cms_escape($key) ?>"><?= cms_escape((string) $definition['label']) ?></label>
		<?php if ($isTextarea): ?>
			<textarea id="field-<?= cms_escape($key) ?>" name="settings[<?= cms_escape($key) ?>]" maxlength="<?= (int) $definition['max'] ?>"<?= ($definition['required'] ?? false) ? ' required' : '' ?><?= $error !== '' ? ' aria-invalid="true" aria-describedby="error-' . cms_escape($key) . '"' : '' ?>><?= cms_escape($value) ?></textarea>
		<?php else: ?>
			<input id="field-<?= cms_escape($key) ?>" name="settings[<?= cms_escape($key) ?>]" type="<?= $htmlType ?>" value="<?= cms_escape($value) ?>" maxlength="<?= (int) $definition['max'] ?>"<?= ($definition['required'] ?? false) ? ' required' : '' ?><?= $error !== '' ? ' aria-invalid="true" aria-describedby="error-' . cms_escape($key) . '"' : '' ?>>
		<?php endif; ?>
		<?php if ((string) ($definition['help'] ?? '') !== ''): ?><p class="field-help"><?= cms_escape((string) $definition['help']) ?></p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="field-error" id="error-<?= cms_escape($key) ?>"><?= cms_escape($error) ?></p><?php endif; ?>
	</div>
	<?php
}

/**
 * @param array<string, array<string, mixed>> $media
 * @param array<string, string> $errors
 */
function cms_render_media_editor(array $media, int $revision, array $errors): void
{
	?>
	<div class="page-heading">
		<div><p class="eyebrow">Imágenes blindadas</p><h1>Biblioteca visual</h1><p class="lead">Cada archivo se verifica, se recorta a la proporción correcta, se limpia y se vuelve a codificar como WebP.</p></div>
	</div>
	<div class="alert alert-warning"><p>Formatos permitidos: JPEG, PNG o WebP · máximo 8 MiB · no se aceptan SVG nuevos.</p></div>

	<section class="media-grid" aria-label="Imágenes editables">
		<?php foreach ($media as $key => $item): ?>
			<?php $isDefault = (string) $item['path'] === (string) $item['default_path']; ?>
			<article class="media-card">
				<div class="media-preview">
					<img src="<?= cms_escape('../' . (string) $item['path'] . ($isDefault ? '' : '?v=' . (int) $item['version'])) ?>" alt="<?= cms_escape((string) $item['label']) ?>">
				</div>
				<div class="media-meta">
					<span class="badge"><?= cms_escape((string) $item['group']) ?></span>
					<h2><?= cms_escape((string) $item['label']) ?></h2>
					<p><?= cms_escape((string) $item['description']) ?></p>
					<small><?= (int) $item['width'] ?> × <?= (int) $item['height'] ?> px · <?= $isDefault ? 'original' : 'personalizada' ?></small>

					<?php if (isset($errors[$key])): ?><div class="alert alert-error" role="alert"><p><?= cms_escape($errors[$key]) ?></p></div><?php endif; ?>

					<form method="post" action="index.php?section=media" enctype="multipart/form-data">
						<input type="hidden" name="action" value="save_media">
						<input type="hidden" name="slot" value="<?= cms_escape($key) ?>">
						<input type="hidden" name="revision" value="<?= $revision ?>">
						<input type="hidden" name="_token" value="<?= cms_escape(cms_csrf_token('save_media')) ?>">
						<div class="section-stack">
							<div class="field">
								<label for="image-<?= cms_escape($key) ?>">Nueva imagen</label>
								<input id="image-<?= cms_escape($key) ?>" name="image" type="file" accept="image/jpeg,image/png,image/webp"<?= ($item['alt_editable'] ?? false) ? '' : ' required' ?>>
							</div>
							<?php if ($item['alt_editable'] ?? false): ?>
								<div class="field">
									<label for="alt-<?= cms_escape($key) ?>">Descripción accesible</label>
									<input id="alt-<?= cms_escape($key) ?>" name="alt_text" type="text" maxlength="240" value="<?= cms_escape((string) $item['alt_text']) ?>">
								</div>
							<?php else: ?>
								<input type="hidden" name="alt_text" value="">
							<?php endif; ?>
						</div>
						<div class="actions"><button class="button button-primary" type="submit"><?= ($item['alt_editable'] ?? false) ? 'Guardar imagen o descripción' : 'Reemplazar imagen' ?></button></div>
					</form>

					<?php if (!$isDefault): ?>
						<form class="inline-form" method="post" action="index.php?section=media">
							<input type="hidden" name="action" value="restore_media">
							<input type="hidden" name="slot" value="<?= cms_escape($key) ?>">
							<input type="hidden" name="revision" value="<?= $revision ?>">
							<input type="hidden" name="_token" value="<?= cms_escape(cms_csrf_token('restore_media')) ?>">
							<button class="button button-danger" type="submit">Restaurar original</button>
						</form>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</section>
	<?php
}

/**
 * @param array<string, mixed> $user
 * @param array<string, string> $errors
 */
function cms_render_security_editor(array $user, array $errors): void
{
	?>
	<div class="page-heading">
		<div><p class="eyebrow">Cuenta administrativa</p><h1>Seguridad de acceso</h1><p class="lead">Usa una contraseña única y guárdala en un administrador de contraseñas.</p></div>
	</div>
	<section class="card">
		<div class="card-header"><div><h2><?= (int) $user['must_change_password'] === 1 ? 'Reemplaza la contraseña temporal' : 'Cambiar contraseña' ?></h2><p>La nueva clave cerrará cualquier otra sesión anterior.</p></div></div>
		<form method="post" action="index.php?section=security" autocomplete="on">
			<input type="hidden" name="action" value="change_password">
			<input type="hidden" name="_token" value="<?= cms_escape(cms_csrf_token('change_password')) ?>">
			<div class="form-grid">
				<div class="field field-wide<?= isset($errors['current_password']) ? ' has-error' : '' ?>">
					<label for="current-password">Contraseña actual</label>
					<input id="current-password" name="current_password" type="password" maxlength="512" autocomplete="current-password" required<?= isset($errors['current_password']) ? ' aria-invalid="true"' : '' ?>>
					<?php if (isset($errors['current_password'])): ?><p class="field-error"><?= cms_escape($errors['current_password']) ?></p><?php endif; ?>
				</div>
				<div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>">
					<label for="new-password">Nueva contraseña</label>
					<input id="new-password" name="new_password" type="password" minlength="15" maxlength="512" autocomplete="new-password" required<?= isset($errors['new_password']) ? ' aria-invalid="true"' : '' ?>>
					<p class="password-meter-note">Mínimo 15 caracteres. Las frases largas funcionan muy bien.</p>
					<?php if (isset($errors['new_password'])): ?><p class="field-error"><?= cms_escape($errors['new_password']) ?></p><?php endif; ?>
				</div>
				<div class="field<?= isset($errors['password_confirmation']) ? ' has-error' : '' ?>">
					<label for="password-confirmation">Confirma la nueva contraseña</label>
					<input id="password-confirmation" name="password_confirmation" type="password" minlength="15" maxlength="512" autocomplete="new-password" required<?= isset($errors['password_confirmation']) ? ' aria-invalid="true"' : '' ?>>
					<?php if (isset($errors['password_confirmation'])): ?><p class="field-error"><?= cms_escape($errors['password_confirmation']) ?></p><?php endif; ?>
				</div>
			</div>
			<div class="actions"><button class="button button-primary" type="submit">Guardar contraseña</button></div>
		</form>
	</section>
	<?php
}

function cms_event_label(string $event): string
{
	return match ($event) {
		'auth.login' => 'Inicio de sesión',
		'auth.logout' => 'Cierre de sesión',
		'auth.password_changed' => 'Contraseña actualizada',
		'content.updated' => 'Contenido publicado',
		'media.updated' => 'Imagen actualizada',
		'media.restored' => 'Imagen original restaurada',
		default => 'Actividad administrativa',
	};
}

function cms_format_date(string $date): string
{
	try {
		$value = new DateTimeImmutable($date);
		$value = $value->setTimezone(new DateTimeZone('America/Tijuana'));
		return $value->format('d/m/Y · H:i');
	} catch (Throwable) {
		return $date;
	}
}
