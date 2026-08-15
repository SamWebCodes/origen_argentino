<?php

/**
 * Origen Argentino — Validación y persistencia del contenido textual.
 */

declare(strict_types=1);

if (!defined('ORIGEN_CMS')) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

/**
 * @param array<string, mixed> $input
 * @return array{values: array<string, string>, errors: array<string, string>}
 */
function cms_validate_settings(string $area, array $input): array
{
	$definitions = cms_fields_for_area($area);
	$values = [];
	$errors = [];

	foreach ($definitions as $key => $definition) {
		$value = $input[$key] ?? '';
		if (!is_string($value)) {
			$errors[$key] = 'El valor no es válido.';
			continue;
		}
		if (!mb_check_encoding($value, 'UTF-8')) {
			$errors[$key] = 'El texto no contiene UTF-8 válido.';
			continue;
		}
		if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
			$errors[$key] = 'El texto contiene caracteres de control no permitidos.';
			continue;
		}
		$value = str_replace(["\r\n", "\r"], "\n", trim($value));
		if (($definition['required'] ?? false) && $value === '') {
			$errors[$key] = 'Este campo es obligatorio.';
			continue;
		}
		if (mb_strlen($value, 'UTF-8') > (int) $definition['max']) {
			$errors[$key] = 'Supera el máximo de ' . (int) $definition['max'] . ' caracteres.';
			continue;
		}

		$type = (string) $definition['type'];
		if ($type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
			$errors[$key] = 'Escribe un correo válido.';
			continue;
		}
		if ($type === 'url_https' && !cms_is_valid_https_url($value)) {
			$errors[$key] = 'Escribe una URL HTTPS completa y válida.';
			continue;
		}
		if ($type === 'phone_e164' && preg_match('/^\+[1-9][0-9]{6,14}$/D', $value) !== 1) {
			$errors[$key] = 'Usa el formato internacional, por ejemplo +526646229730.';
			continue;
		}
		if ($type === 'country_code') {
			$value = strtoupper($value);
			if (preg_match('/^[A-Z]{2}$/D', $value) !== 1) {
				$errors[$key] = 'Usa dos letras, por ejemplo MX.';
				continue;
			}
		}
		$values[$key] = $value;
	}

	return ['values' => $values, 'errors' => $errors];
}

function cms_is_valid_https_url(string $value): bool
{
	if (filter_var($value, FILTER_VALIDATE_URL) === false) {
		return false;
	}
	$parts = parse_url($value);
	return is_array($parts)
		&& strtolower((string) ($parts['scheme'] ?? '')) === 'https'
		&& isset($parts['host'])
		&& !isset($parts['user'], $parts['pass']);
}

/**
 * @param array<string, string> $values
 */
function cms_save_settings(
	PDO $database,
	int $userId,
	string $area,
	array $values,
	int $expectedRevision
): bool {
	$definitions = cms_fields_for_area($area);
	if ($definitions === [] || array_diff_key($values, $definitions) !== []) {
		throw new InvalidArgumentException('Área de contenido no permitida.');
	}

	cms_create_database_backup();
	$database->exec('BEGIN IMMEDIATE');
	try {
		if (cms_content_revision($database) !== $expectedRevision) {
			$database->exec('ROLLBACK');
			return false;
		}

		cms_create_revision($database, $userId, 'settings.' . $area);
		$statement = $database->prepare(
			'UPDATE content_settings SET value = :value, updated_at = :updated_at WHERE key = :key'
		);
		$now = gmdate('c');
		foreach ($values as $key => $value) {
			$statement->execute([
				':value' => $value,
				':updated_at' => $now,
				':key' => $key,
			]);
		}
		cms_increment_content_revision($database);
		cms_audit($database, $userId, 'content.updated', [
			'area' => $area,
			'keys' => array_keys($values),
		]);
		$database->exec('COMMIT');
		return true;
	} catch (Throwable $exception) {
		if ($database->inTransaction()) {
			$database->exec('ROLLBACK');
		}
		throw $exception;
	}
}
