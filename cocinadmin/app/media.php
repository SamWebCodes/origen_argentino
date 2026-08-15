<?php

/**
 * Origen Argentino — Validación, normalización y persistencia de imágenes.
 */

declare(strict_types=1);

if (!defined('ORIGEN_CMS')) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

const CMS_MAX_UPLOAD_BYTES = 8388608;
const CMS_MAX_IMAGE_SIDE = 6000;
const CMS_MAX_IMAGE_PIXELS = 20000000;

/**
 * @param array<string, mixed> $file
 * @param array<string, mixed> $slot
 * @return array<string, mixed>
 */
function cms_process_image_upload(string $slotKey, array $file, array $slot): array
{
	$outputTemp = null;
	$publishedPath = null;
	$completed = false;
	$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($error !== UPLOAD_ERR_OK) {
		$message = match ($error) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite permitido.',
			UPLOAD_ERR_PARTIAL => 'La carga quedó incompleta. Intenta de nuevo.',
			UPLOAD_ERR_NO_FILE => 'Selecciona una imagen.',
			default => 'No fue posible recibir la imagen.',
		};
		throw new InvalidArgumentException($message);
	}

	$temporaryUpload = (string) ($file['tmp_name'] ?? '');
	$size = (int) ($file['size'] ?? 0);
	if (!is_uploaded_file($temporaryUpload) || $size < 1 || $size > CMS_MAX_UPLOAD_BYTES) {
		throw new InvalidArgumentException('La imagen no es una carga válida o supera 8 MiB.');
	}

	$privateTemp = dirname(__DIR__) . '/.storage/tmp/upload-' . bin2hex(random_bytes(16)) . '.partial';
	if (!move_uploaded_file($temporaryUpload, $privateTemp)) {
		throw new RuntimeException('No fue posible aislar la carga para revisarla.');
	}
	@chmod($privateTemp, 0600);

	try {
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = (string) $finfo->file($privateTemp);
		$decoders = [
			'image/jpeg' => 'imagecreatefromjpeg',
			'image/png' => 'imagecreatefrompng',
			'image/webp' => 'imagecreatefromwebp',
		];
		if (!isset($decoders[$mime])) {
			throw new InvalidArgumentException('Solo se aceptan imágenes JPEG, PNG o WebP.');
		}

		$info = @getimagesize($privateTemp);
		if (!is_array($info) || (string) ($info['mime'] ?? '') !== $mime) {
			throw new InvalidArgumentException('El archivo no contiene una imagen válida.');
		}
		$sourceWidth = (int) $info[0];
		$sourceHeight = (int) $info[1];
		if (
			$sourceWidth < 1
			|| $sourceHeight < 1
			|| $sourceWidth > CMS_MAX_IMAGE_SIDE
			|| $sourceHeight > CMS_MAX_IMAGE_SIDE
			|| ($sourceWidth * $sourceHeight) > CMS_MAX_IMAGE_PIXELS
		) {
			throw new InvalidArgumentException('La imagen supera 6000 px por lado o 20 megapíxeles.');
		}

		$decoder = $decoders[$mime];
		$source = @$decoder($privateTemp);
		if (!$source instanceof GdImage) {
			throw new InvalidArgumentException('La imagen está dañada o no se puede decodificar.');
		}
		if ($mime === 'image/jpeg') {
			$source = cms_apply_exif_orientation($source, $privateTemp);
			$sourceWidth = imagesx($source);
			$sourceHeight = imagesy($source);
		}

		$targetWidth = (int) $slot['width'];
		$targetHeight = (int) $slot['height'];
		$target = imagecreatetruecolor($targetWidth, $targetHeight);
		if (!$target instanceof GdImage) {
			throw new RuntimeException('No fue posible preparar la imagen final.');
		}
		imagealphablending($target, false);
		imagesavealpha($target, true);
		$transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
		imagefill($target, 0, 0, $transparent);

		cms_resample_image(
			$target,
			$source,
			$sourceWidth,
			$sourceHeight,
			$targetWidth,
			$targetHeight,
			(string) $slot['fit']
		);
		$outputTemp = dirname(__DIR__) . '/.storage/tmp/output-' . bin2hex(random_bytes(16)) . '.partial';
		if (!imagewebp($target, $outputTemp, 90)) {
			throw new RuntimeException('No fue posible codificar la imagen segura.');
		}
		@chmod($outputTemp, 0600);
		$outputInfo = @getimagesize($outputTemp);
		$outputSize = @filesize($outputTemp);
		if (
			!is_array($outputInfo)
			|| (string) ($outputInfo['mime'] ?? '') !== 'image/webp'
			|| (int) $outputInfo[0] !== $targetWidth
			|| (int) $outputInfo[1] !== $targetHeight
			|| !is_int($outputSize)
			|| $outputSize < 1
		) {
			throw new RuntimeException('La imagen final no superó la verificación.');
		}

		$hash = hash_file('sha256', $outputTemp);
		if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
			@unlink($outputTemp);
			throw new RuntimeException('No fue posible verificar la imagen final.');
		}
		$basename = $slotKey . '-' . $hash . '-' . bin2hex(random_bytes(16)) . '.webp';
		$uploadDirectory = dirname(__DIR__) . '/uploads';
		$finalPath = $uploadDirectory . '/' . $basename;
		// link() crea el destino de forma exclusiva: nunca reemplaza un archivo
		// concurrente y mantiene la publicación dentro del mismo filesystem.
		if (!@link($outputTemp, $finalPath)) {
			throw new RuntimeException('No fue posible publicar la imagen procesada.');
		}
		$publishedPath = $finalPath;
		@chmod($finalPath, 0644);

		$originalName = str_replace('\\', '/', (string) ($file['name'] ?? 'imagen'));
		$originalName = basename($originalName);
		if (!mb_check_encoding($originalName, 'UTF-8')) {
			$originalName = 'imagen';
		}
		$originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? 'imagen';
		$result = [
			'path' => 'cocinadmin/uploads/' . $basename,
			'absolute_path' => $finalPath,
			'mime_type' => 'image/webp',
			'width' => $targetWidth,
			'height' => $targetHeight,
			'byte_size' => $outputSize,
			'sha256' => $hash,
			'original_name' => mb_substr($originalName === '' ? 'imagen' : $originalName, 0, 180, 'UTF-8'),
			'created' => true,
		];
		$completed = true;
		return $result;
	} finally {
		@unlink($privateTemp);
		if (is_string($outputTemp)) {
			@unlink($outputTemp);
		}
		if (!$completed && is_string($publishedPath)) {
			@unlink($publishedPath);
		}
	}
}

function cms_apply_exif_orientation(GdImage $image, string $path): GdImage
{
	if (!function_exists('exif_read_data')) {
		return $image;
	}
	$exif = @exif_read_data($path);
	$orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

	return match ($orientation) {
		2 => cms_flip_image($image, IMG_FLIP_HORIZONTAL),
		3 => cms_rotate_image($image, 180),
		4 => cms_flip_image($image, IMG_FLIP_VERTICAL),
		5 => cms_rotate_image(cms_flip_image($image, IMG_FLIP_HORIZONTAL), 270),
		6 => cms_rotate_image($image, 270),
		7 => cms_rotate_image(cms_flip_image($image, IMG_FLIP_HORIZONTAL), 90),
		8 => cms_rotate_image($image, 90),
		default => $image,
	};
}

function cms_flip_image(GdImage $image, int $mode): GdImage
{
	imageflip($image, $mode);
	return $image;
}

function cms_rotate_image(GdImage $image, int $angle): GdImage
{
	$rotated = imagerotate($image, $angle, 0);
	if (!$rotated instanceof GdImage) {
		return $image;
	}
	return $rotated;
}

function cms_resample_image(
	GdImage $target,
	GdImage $source,
	int $sourceWidth,
	int $sourceHeight,
	int $targetWidth,
	int $targetHeight,
	string $fit
): void {
	if ($fit === 'contain') {
		$scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
		$drawWidth = max(1, (int) round($sourceWidth * $scale));
		$drawHeight = max(1, (int) round($sourceHeight * $scale));
		$destinationX = (int) floor(($targetWidth - $drawWidth) / 2);
		$destinationY = (int) floor(($targetHeight - $drawHeight) / 2);
		imagecopyresampled(
			$target,
			$source,
			$destinationX,
			$destinationY,
			0,
			0,
			$drawWidth,
			$drawHeight,
			$sourceWidth,
			$sourceHeight
		);
		return;
	}

	$scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
	$cropWidth = $targetWidth / $scale;
	$cropHeight = $targetHeight / $scale;
	$sourceX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
	$sourceY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));
	imagecopyresampled(
		$target,
		$source,
		0,
		0,
		$sourceX,
		$sourceY,
		$targetWidth,
		$targetHeight,
		(int) round($cropWidth),
		(int) round($cropHeight)
	);
}

/**
 * @param array<string, mixed> $slot
 */
function cms_validate_media_alt_text(array $slot, string $altText): string
{
	if (!($slot['alt_editable'] ?? false)) {
		return (string) $slot['default_alt'];
	}
	if (!mb_check_encoding($altText, 'UTF-8')) {
		throw new InvalidArgumentException('El texto alternativo no contiene UTF-8 válido.');
	}
	if (preg_match('/[\x00-\x1F\x7F]/', $altText) === 1) {
		throw new InvalidArgumentException('El texto alternativo contiene caracteres no permitidos.');
	}
	$altText = trim($altText);
	if (mb_strlen($altText, 'UTF-8') > 240) {
		throw new InvalidArgumentException('El texto alternativo supera 240 caracteres.');
	}

	return $altText;
}

/**
 * @param array<string, mixed>|null $processed
 */
function cms_save_media_slot(
	PDO $database,
	int $userId,
	string $slotKey,
	string $altText,
	?array $processed,
	int $expectedRevision
): bool {
	$slots = cms_media_slots();
	if (!isset($slots[$slotKey])) {
		throw new InvalidArgumentException('La posición de imagen no existe.');
	}
	$slot = $slots[$slotKey];
	$altText = cms_validate_media_alt_text($slot, $altText);
	if ($processed === null && !($slot['alt_editable'] ?? false)) {
		throw new InvalidArgumentException('Selecciona una imagen nueva.');
	}

	cms_create_database_backup();
	$database->exec('BEGIN IMMEDIATE');
	try {
		if (cms_content_revision($database) !== $expectedRevision) {
			$database->exec('ROLLBACK');
			if ($processed !== null && ($processed['created'] ?? false) && isset($processed['absolute_path'])) {
				@unlink((string) $processed['absolute_path']);
			}
			return false;
		}
		cms_create_revision($database, $userId, 'media.' . $slotKey);

		if ($processed !== null) {
			$statement = $database->prepare(
				'UPDATE media_slots SET
					path = :path,
					alt_text = :alt_text,
					mime_type = :mime_type,
					width = :width,
					height = :height,
					byte_size = :byte_size,
					sha256 = :sha256,
					original_name = :original_name,
					version = version + 1,
					updated_at = :updated_at
				WHERE key = :key'
			);
			$statement->execute([
				':path' => $processed['path'],
				':alt_text' => $altText,
				':mime_type' => $processed['mime_type'],
				':width' => $processed['width'],
				':height' => $processed['height'],
				':byte_size' => $processed['byte_size'],
				':sha256' => $processed['sha256'],
				':original_name' => $processed['original_name'],
				':updated_at' => gmdate('c'),
				':key' => $slotKey,
			]);
		} else {
			$database->prepare(
				'UPDATE media_slots
				SET alt_text = :alt_text, version = version + 1, updated_at = :updated_at
				WHERE key = :key'
			)->execute([
				':alt_text' => $altText,
				':updated_at' => gmdate('c'),
				':key' => $slotKey,
			]);
		}

		cms_increment_content_revision($database);
		cms_audit($database, $userId, 'media.updated', ['slot' => $slotKey]);
		$database->exec('COMMIT');
		return true;
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		if ($processed !== null && ($processed['created'] ?? false) && isset($processed['absolute_path'])) {
			@unlink((string) $processed['absolute_path']);
		}
		throw $exception;
	}
}

function cms_restore_media_default(
	PDO $database,
	int $userId,
	string $slotKey,
	int $expectedRevision
): bool {
	$slots = cms_media_slots();
	if (!isset($slots[$slotKey])) {
		throw new InvalidArgumentException('La posición de imagen no existe.');
	}
	$slot = $slots[$slotKey];
	$mime = str_ends_with((string) $slot['default_path'], '.svg') ? 'image/svg+xml' : 'image/webp';

	cms_create_database_backup();
	$database->exec('BEGIN IMMEDIATE');
	try {
		if (cms_content_revision($database) !== $expectedRevision) {
			$database->exec('ROLLBACK');
			return false;
		}
		cms_create_revision($database, $userId, 'media.restore.' . $slotKey);
		$database->prepare(
			'UPDATE media_slots SET
				path = :path,
				alt_text = :alt_text,
				mime_type = :mime_type,
				width = :width,
				height = :height,
				byte_size = 0,
				sha256 = NULL,
				original_name = NULL,
				version = version + 1,
				updated_at = :updated_at
			WHERE key = :key'
		)->execute([
			':path' => $slot['default_path'],
			':alt_text' => $slot['default_alt'],
			':mime_type' => $mime,
			':width' => $slot['width'],
			':height' => $slot['height'],
			':updated_at' => gmdate('c'),
			':key' => $slotKey,
		]);
		cms_increment_content_revision($database);
		cms_audit($database, $userId, 'media.restored', ['slot' => $slotKey]);
		$database->exec('COMMIT');
		return true;
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		throw $exception;
	}
}
