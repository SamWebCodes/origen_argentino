<?php

/**
 * Origen Argentino — Catálogo cerrado de contenido editable.
 *
 * Este archivo es la única lista de claves que el CMS puede leer o escribir.
 * Los formularios nunca deciden dinámicamente qué columnas o ajustes existen.
 */

declare(strict_types=1);

if (
	!defined('ORIGEN_ARGENTINO')
	&& !defined('ORIGEN_CMS')
	&& !defined('ORIGEN_PUBLIC_CONTENT')
	&& !defined('ORIGEN_PUBLIC_CSS')
	&& !defined('ORIGEN_CMS_CLI')
) {
	http_response_code(403);
	exit;
}

/**
 * Valores que reproducen exactamente el contenido actual del sitio.
 *
 * @return array<string, string>
 */
function cms_default_settings(): array
{
	return [
		'site_name' => 'Origen Argentino',
		'site_tagline' => 'Somos un restaurante de parrilla estilo Argentino',
		'site_description' => 'Restaurante de parrilla estilo argentino en la Zona Gastronómica de Tijuana. Cortes a la parrilla, empanadas, milanesas, pizzas, ensaladas y pastas. 10 años de sabor, tradición y hospitalidad.',
		'cuisine_primary' => 'Argentina',
		'cuisine_secondary' => 'Parrilla',

		'phone_display' => '(664) 622-9730',
		'phone_e164' => '+526646229730',
		'email' => 'contacto@origenargentino.com',
		'address_display' => 'Escuadrón 201 3151, Aviación, Tijuana, Mexico, 22014',
		'address_street' => 'Escuadrón 201 3151, Aviación',
		'address_city' => 'Tijuana',
		'address_state' => 'Baja California',
		'address_postal_code' => '22014',
		'address_country_code' => 'MX',
		'maps_footer_url' => 'https://maps.app.goo.gl/8pMRzzpMMKAT1Hnx8',
		'maps_widget_url' => 'https://maps.app.goo.gl/S4hqhLoJwPMfvCJC6',
		'reservation_url' => 'https://www.opentable.com.mx/r/origen-argentino-tijuana',
		'facebook_url' => 'https://web.facebook.com/OrigenArgentino',
		'instagram_url' => 'https://www.instagram.com/origen.argentino/',

		'skip_link_label' => 'Ir al contenido',
		'home_aria_label' => 'Ir al inicio',
		'nav_aria_label' => 'Menú principal',
		'nav_dropdown_aria_label' => 'Menú desplegable',
		'nav_home_label' => 'Inicio',
		'nav_origin_label' => 'Nuestro Origen',
		'nav_reservation_label' => 'Reserva',
		'nav_gallery_label' => 'Galería',
		'nav_open_label' => 'Abrir menú',
		'nav_close_label' => 'Cerrar menú',
		'facebook_aria_label' => 'Facebook',
		'instagram_aria_label' => 'Instagram',

		'hero_aria_label' => 'Bienvenida',
		'hero_title' => 'Bienvenido a casa',
		'hero_cta_label' => 'Reserva Aquí',
		'origin_heading' => 'Nuestro Origen',
		'origin_paragraph_1' => 'Somos un restaurante de parrilla estilo argentino, ubicado en el corazón de la Zona Gastronómica de Tijuana, en una de las fronteras más transitadas del mundo.',
		'origin_paragraph_2' => 'Desde hace 10 años, nos dedicamos a brindar una experiencia gastronómica auténtica y confortable, donde la calidad, el sabor y la tradición argentina se viven en cada detalle.',
		'origin_paragraph_3' => 'De nuestra cocina a tu mesa llegan cortes a la parrilla, empanadas, milanesas, pizzas, ensaladas y pastas, entre muchas otras especialidades que forman parte de nuestro menú y de la historia que compartimos con quienes nos visitan día con día.',
		'origin_signature_lead' => 'Bienvenido al Origen Argentino.',
		'origin_signature_tail' => '10 años celebrando sabor, tradición y hospitalidad.',
		'reservation_cta_label' => 'Reserva Aquí',
		'reservation_title_line_1' => 'Cocina de origen',
		'reservation_title_line_2' => 'Argentino',
		'gallery_aria_label' => 'Galería de Origen Argentino',
		'gallery_previous_label' => 'Anterior',
		'gallery_next_label' => 'Siguiente',
		'footer_back_to_top_label' => 'Volver arriba',
		'widget_phone_label' => 'Llámanos',
		'widget_phone_sr_label' => 'Llámanos por teléfono',
		'widget_email_label' => 'Contáctanos',
		'widget_email_sr_label' => 'Escríbenos por correo',
		'widget_maps_label' => 'Visítanos',
		'widget_maps_sr_label' => 'Cómo llegar',
		'widget_cta_label' => 'Contáctenos',
		'widget_open_label' => 'Abrir opciones de contacto',
		'widget_close_label' => 'Cerrar opciones de contacto',
	];
}

/**
 * Grupos y reglas de los formularios del panel.
 *
 * @return array<string, array{label: string, description: string, area: string, fields: array<string, array<string, mixed>>}>
 */
function cms_setting_groups(): array
{
	return [
		'identity' => [
			'label' => 'Identidad y buscadores',
			'description' => 'Nombre comercial y textos que aparecen al compartir o buscar el sitio.',
			'area' => 'business',
			'fields' => [
				'site_name' => cms_field('Nombre del sitio', 'text', 80, true),
				'site_tagline' => cms_field('Eslogan', 'text', 140, true),
				'site_description' => cms_field('Descripción para buscadores', 'textarea', 320, true, 'Resume el restaurante en una o dos frases.'),
				'cuisine_primary' => cms_field('Tipo de cocina principal', 'text', 60, true),
				'cuisine_secondary' => cms_field('Especialidad', 'text', 60, true),
			],
		],
		'contact' => [
			'label' => 'Contacto',
			'description' => 'Datos visibles en el pie, el widget y los datos estructurados.',
			'area' => 'business',
			'fields' => [
				'phone_display' => cms_field('Teléfono visible', 'text', 40, true, 'Ejemplo: (664) 622-9730'),
				'phone_e164' => cms_field('Teléfono internacional', 'phone_e164', 16, true, 'Incluye +, código de país y solo números.'),
				'email' => cms_field('Correo', 'email', 254, true),
				'address_display' => cms_field('Dirección visible', 'textarea', 240, true),
				'address_street' => cms_field('Calle y número', 'text', 160, true),
				'address_city' => cms_field('Ciudad', 'text', 80, true),
				'address_state' => cms_field('Estado', 'text', 80, true),
				'address_postal_code' => cms_field('Código postal', 'text', 16, true),
				'address_country_code' => cms_field('Código de país', 'country_code', 2, true, 'Dos letras, por ejemplo MX.'),
			],
		],
		'links' => [
			'label' => 'Reservas, mapas y redes',
			'description' => 'Solo se aceptan enlaces HTTPS completos.',
			'area' => 'business',
			'fields' => [
				'reservation_url' => cms_field('Reservaciones', 'url_https', 500, true),
				'maps_footer_url' => cms_field('Mapa del pie', 'url_https', 500, true),
				'maps_widget_url' => cms_field('Mapa del widget', 'url_https', 500, true),
				'facebook_url' => cms_field('Facebook', 'url_https', 500, true),
				'instagram_url' => cms_field('Instagram', 'url_https', 500, true),
			],
		],
		'navigation' => [
			'label' => 'Navegación',
			'description' => 'Etiquetas del menú de escritorio y del menú móvil.',
			'area' => 'content',
			'fields' => [
				'nav_home_label' => cms_field('Inicio', 'text', 30, true),
				'nav_origin_label' => cms_field('Nuestro Origen', 'text', 40, true),
				'nav_reservation_label' => cms_field('Reserva', 'text', 30, true),
				'nav_gallery_label' => cms_field('Galería', 'text', 30, true),
			],
		],
		'hero' => [
			'label' => 'Portada',
			'description' => 'Primer mensaje que recibe el visitante.',
			'area' => 'content',
			'fields' => [
				'hero_title' => cms_field('Titular', 'text', 80, true, 'Un texto muy largo puede salir del trazo decorativo.'),
				'hero_cta_label' => cms_field('Botón de reserva', 'text', 35, true),
			],
		],
		'origin' => [
			'label' => 'Nuestro Origen',
			'description' => 'Historia principal del restaurante. El formato visual permanece fijo.',
			'area' => 'content',
			'fields' => [
				'origin_heading' => cms_field('Título', 'text', 80, true),
				'origin_paragraph_1' => cms_field('Párrafo 1', 'textarea', 700, true),
				'origin_paragraph_2' => cms_field('Párrafo 2', 'textarea', 700, true),
				'origin_paragraph_3' => cms_field('Párrafo 3', 'textarea', 900, true),
				'origin_signature_lead' => cms_field('Firma destacada', 'text', 120, true),
				'origin_signature_tail' => cms_field('Línea bajo la firma', 'text', 160, true),
			],
		],
		'reservation' => [
			'label' => 'Reserva',
			'description' => 'Llamado a la acción y titular de la sección de madera.',
			'area' => 'content',
			'fields' => [
				'reservation_cta_label' => cms_field('Texto del botón', 'text', 35, true),
				'reservation_title_line_1' => cms_field('Primera línea', 'text', 80, true),
				'reservation_title_line_2' => cms_field('Línea destacada', 'text', 60, true),
			],
		],
		'interface' => [
			'label' => 'Galería, pie y contacto flotante',
			'description' => 'Textos visibles y accesibles de los últimos bloques del sitio.',
			'area' => 'content',
			'fields' => [
				'widget_phone_label' => cms_field('Canal teléfono', 'text', 40, true),
				'widget_email_label' => cms_field('Canal correo', 'text', 40, true),
				'widget_maps_label' => cms_field('Canal mapa', 'text', 40, true),
				'widget_cta_label' => cms_field('Etiqueta del widget', 'text', 40, true),
			],
		],
		'accessibility' => [
			'label' => 'Textos de accesibilidad',
			'description' => 'Ayudan a visitantes que navegan con lector de pantalla.',
			'area' => 'content',
			'fields' => [
				'skip_link_label' => cms_field('Saltar al contenido', 'text', 60, true),
				'home_aria_label' => cms_field('Ir al inicio', 'text', 60, true),
				'nav_aria_label' => cms_field('Nombre del menú', 'text', 60, true),
				'nav_dropdown_aria_label' => cms_field('Nombre del menú móvil', 'text', 60, true),
				'nav_open_label' => cms_field('Abrir menú', 'text', 60, true),
				'nav_close_label' => cms_field('Cerrar menú', 'text', 60, true),
				'facebook_aria_label' => cms_field('Facebook', 'text', 60, true),
				'instagram_aria_label' => cms_field('Instagram', 'text', 60, true),
				'hero_aria_label' => cms_field('Nombre de la portada', 'text', 80, true),
				'gallery_aria_label' => cms_field('Nombre de la galería', 'text', 100, true),
				'gallery_previous_label' => cms_field('Foto anterior', 'text', 40, true),
				'gallery_next_label' => cms_field('Foto siguiente', 'text', 40, true),
				'footer_back_to_top_label' => cms_field('Volver arriba', 'text', 60, true),
				'widget_phone_sr_label' => cms_field('Acción de teléfono', 'text', 80, true),
				'widget_email_sr_label' => cms_field('Acción de correo', 'text', 80, true),
				'widget_maps_sr_label' => cms_field('Acción de mapa', 'text', 80, true),
				'widget_open_label' => cms_field('Abrir contacto', 'text', 80, true),
				'widget_close_label' => cms_field('Cerrar contacto', 'text', 80, true),
			],
		],
	];
}

/**
 * @return array{label: string, type: string, max: int, required: bool, help: string}
 */
function cms_field(string $label, string $type, int $max, bool $required, string $help = ''): array
{
	return [
		'label' => $label,
		'type' => $type,
		'max' => $max,
		'required' => $required,
		'help' => $help,
	];
}

/**
 * Slots fijos: cambiar una imagen nunca añade ni elimina bloques del SPA.
 *
 * @return array<string, array<string, mixed>>
 */
function cms_media_slots(): array
{
	return [
		'favicon_small' => cms_media_slot('Favicon pequeño', 'Marca', 'assets/img/favicon-32.webp', '', 32, 32, 'cover', false, 'Icono de 32 × 32 px para pestañas.'),
		'favicon_large' => cms_media_slot('Favicon grande', 'Marca', 'assets/img/favicon-192.webp', '', 192, 192, 'cover', false, 'Icono de 192 × 192 px para móviles.'),
		'logo_desktop' => cms_media_slot('Logo principal', 'Marca', 'assets/img/logo.webp', 'Logo de Origen Argentino', 802, 911, 'contain', true, 'Cabecera de escritorio y tablet.'),
		'logo_compact' => cms_media_slot('Logo compacto', 'Marca', 'assets/img/logo-negro.svg', 'Logo de Origen Argentino', 930, 1127, 'contain', true, 'Cabecera móvil y pie de página. Los SVG nuevos no se aceptan.'),
		'hero_background' => cms_media_slot('Fondo de portada', 'Portada y secciones', 'assets/img/hero-bg.webp', '', 1980, 1131, 'cover', false, 'Se recorta para cubrir la pantalla.'),
		'origin_texture' => cms_media_slot('Textura del origen', 'Portada y secciones', 'assets/img/origen-bg.webp', '', 500, 500, 'contain', false, 'Se reutiliza detrás de Nuestro Origen y Reserva.'),
		'origin_photo' => cms_media_slot('Foto de Nuestro Origen', 'Portada y secciones', 'assets/img/nosotros.webp', 'Plato de parrilla estilo argentino de Origen Argentino', 1980, 1320, 'cover', true, 'También se usa como imagen al compartir el sitio.'),
		'reservation_background' => cms_media_slot('Fondo de Reserva', 'Portada y secciones', 'assets/img/madera-bg.webp', '', 1980, 1485, 'cover', false, 'Textura de madera de la sección Reserva.'),
		'gallery_1' => cms_media_slot('Galería 1', 'Galería', 'assets/img/galeria-01.webp', 'Galería Origen Argentino — fotografía 1', 1600, 1600, 'cover', true, 'Primer cuadro del carrusel.'),
		'gallery_2' => cms_media_slot('Galería 2', 'Galería', 'assets/img/galeria-02.webp', 'Galería Origen Argentino — fotografía 2', 1600, 1600, 'cover', true, 'Segundo cuadro del carrusel.'),
		'gallery_3' => cms_media_slot('Galería 3', 'Galería', 'assets/img/galeria-03.webp', 'Galería Origen Argentino — fotografía 3', 1600, 1600, 'cover', true, 'Tercer cuadro del carrusel.'),
		'gallery_4' => cms_media_slot('Galería 4', 'Galería', 'assets/img/galeria-04.webp', 'Galería Origen Argentino — fotografía 4', 1600, 1600, 'cover', true, 'Cuarto cuadro del carrusel.'),
		'footer_divider_address' => cms_media_slot('Línea de dirección', 'Pie de página', 'assets/img/linea-1.webp', '', 400, 11, 'contain', false, 'Separador decorativo bajo la dirección.'),
		'footer_divider_phone' => cms_media_slot('Línea de teléfono', 'Pie de página', 'assets/img/linea-2.webp', '', 300, 8, 'contain', false, 'Separador decorativo bajo el teléfono.'),
	];
}

/**
 * @return array<string, mixed>
 */
function cms_media_slot(
	string $label,
	string $group,
	string $defaultPath,
	string $defaultAlt,
	int $width,
	int $height,
	string $fit,
	bool $altEditable,
	string $description
): array {
	return [
		'label' => $label,
		'group' => $group,
		'default_path' => $defaultPath,
		'default_alt' => $defaultAlt,
		'width' => $width,
		'height' => $height,
		'fit' => $fit,
		'alt_editable' => $altEditable,
		'description' => $description,
	];
}

/**
 * @return array<string, array<string, mixed>>
 */
function cms_fields_for_area(string $area): array
{
	$fields = [];
	foreach (cms_setting_groups() as $group) {
		if ($group['area'] === $area) {
			$fields = array_merge($fields, $group['fields']);
		}
	}

	return $fields;
}
