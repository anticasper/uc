<?php

/**
 * Plugin Name: UC Map API
 * Plugin URI: https://barradois.com
 * Description: Exibe um mapa interativo de Unidades de Conservação via shortcode para uso no Elementor.
 * Version: 1.6.9
 * Author: Diovanni de Souza
 * Author URI: https://barradois.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: uc-map-api
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
  exit;
}

define('UC_MAP_VERSION', '1.6.9');

function uc_map_asset_version($relative_path)
{
  $path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');

  return file_exists($path) ? (string) filemtime($path) : UC_MAP_VERSION;
}

function uc_map_api_data_url($endpoint)
{
  return add_query_arg(
    'per_page',
    500,
    rest_url('api-no-parque/v1/' . ltrim($endpoint, '/'))
  );
}

function uc_map_enqueue_common_assets()
{
  $plugin_url = plugin_dir_url(__FILE__);

  wp_enqueue_style(
    'uc-map-fonts',
    'https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap',
    [],
    null
  );

  wp_enqueue_style(
    'uc-map-leaflet',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    [],
    '1.9.4'
  );

  wp_enqueue_style(
    'uc-map-styles',
    $plugin_url . 'assets/styles.css',
    ['uc-map-fonts', 'uc-map-leaflet'],
    uc_map_asset_version('assets/styles.css')
  );
}

function uc_map_enqueue_assets()
{
  static $enqueued = false;

  if ($enqueued) {
    return;
  }

  $enqueued = true;
  $plugin_url = plugin_dir_url(__FILE__);

  uc_map_enqueue_common_assets();

  wp_enqueue_script(
    'uc-map-leaflet',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    [],
    '1.9.4',
    true
  );

  wp_enqueue_script(
    'uc-map-app',
    $plugin_url . 'assets/app.js',
    ['uc-map-leaflet'],
    uc_map_asset_version('assets/app.js'),
    true
  );

  wp_localize_script('uc-map-app', 'UCMapConfig', [
    'dataUrl' => uc_map_api_data_url('map'),
    'fallbackDataUrl' => $plugin_url . 'assets/map-data.json',
    'markerLogoUrl' => $plugin_url . 'assets/uc-marker-logo.png',
    'siteUrl' => home_url('/'),
    'fallbackUcUrl' => home_url('/modelo-de-unidades-de-conservacao/'),
  ]);
}

function uc_list_enqueue_assets()
{
  static $enqueued = false;

  if ($enqueued) {
    return;
  }

  $enqueued = true;
  $plugin_url = plugin_dir_url(__FILE__);

  uc_map_enqueue_common_assets();

  wp_enqueue_script(
    'uc-list-app',
    $plugin_url . 'assets/list.js',
    [],
    uc_map_asset_version('assets/list.js'),
    true
  );

  wp_localize_script('uc-list-app', 'UCListConfig', [
    'dataUrl' => uc_map_api_data_url('list'),
    'fallbackDataUrl' => $plugin_url . 'assets/map-data.json',
    'defaultImageUrl' => $plugin_url . 'assets/default-uc-card.svg',
  ]);
}

function uc_partner_enqueue_assets()
{
  static $enqueued = false;

  if ($enqueued) {
    return;
  }

  $enqueued = true;
  $plugin_url = plugin_dir_url(__FILE__);

  uc_map_enqueue_common_assets();

  wp_enqueue_script(
    'uc-partners-app',
    $plugin_url . 'assets/partners.js',
    [],
    uc_map_asset_version('assets/partners.js'),
    true
  );
}

function uc_activity_types_enqueue_assets()
{
  static $enqueued = false;

  if ($enqueued) {
    return;
  }

  $enqueued = true;

  uc_map_enqueue_common_assets();

  wp_enqueue_style(
    'uc-map-fontawesome',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
    [],
    '6.5.2'
  );

  $plugin_url = plugin_dir_url(__FILE__);

  wp_enqueue_script(
    'uc-partners-app',
    $plugin_url . 'assets/partners.js',
    [],
    uc_map_asset_version('assets/partners.js'),
    true
  );
}

function uc_testimonial_enqueue_assets($with_slider = false)
{
  $plugin_url = plugin_dir_url(__FILE__);

  uc_map_enqueue_common_assets();

  if ($with_slider) {
    wp_enqueue_script(
      'uc-testimonials-app',
      $plugin_url . 'assets/testimonials.js',
      [],
      uc_map_asset_version('assets/testimonials.js'),
      true
    );
  }
}

function uc_single_enqueue_assets()
{
  if (!is_singular('uc')) {
    return;
  }

  $post_id = get_queried_object_id();

  if (!$post_id) {
    return;
  }

  $plugin_url = plugin_dir_url(__FILE__);

  wp_enqueue_script(
    'uc-single-page',
    $plugin_url . 'assets/single.js',
    [],
    uc_map_asset_version('assets/single.js'),
    true
  );

  wp_localize_script('uc-single-page', 'UCSingleConfig', [
    'apiUrl' => rest_url('api-no-parque/v1/ucs/' . absint($post_id)),
    'mapUrl' => home_url('/mapa-teste/'),
  ]);
}

function uc_map_render_shortcode()
{
  uc_map_enqueue_assets();

  ob_start();
?>
  <div class="uc-map-root">
    <div class="page-shell">
      <header class="filter-bar">
        <form id="filters-form" class="filter-grid">
          <label class="top-search" for="search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M10.8 4.2a6.6 6.6 0 1 0 0 13.2 6.6 6.6 0 0 0 0-13.2ZM2.7 10.8a8.1 8.1 0 1 1 14.4 5.1l3.9 3.9-1.2 1.2-3.9-3.9A8.1 8.1 0 0 1 2.7 10.8Z" />
            </svg>
            <input
              id="search"
              name="search"
              type="text"
              placeholder="CEP, Parque, cidade ou bioma">
          </label>

          <select id="park-filter" name="park" class="filter-select">
            <option value="">Parque</option>
          </select>

          <select id="biome-filter" name="biome" class="filter-select">
            <option value="">Bioma</option>
          </select>

          <select id="city-filter" name="city" class="filter-select">
            <option value="">Cidade</option>
          </select>

          <select id="activity-filter" name="activity" class="filter-select">
            <option value="">Tipo de Atividade</option>
          </select>

          <button type="submit" class="explore-button">Explorar</button>
        </form>
      </header>

      <main class="map-layout">
        <aside class="state-panel">
          <label class="state-search" for="state-search">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M10.8 4.2a6.6 6.6 0 1 0 0 13.2 6.6 6.6 0 0 0 0-13.2ZM2.7 10.8a8.1 8.1 0 1 1 14.4 5.1l3.9 3.9-1.2 1.2-3.9-3.9A8.1 8.1 0 0 1 2.7 10.8Z" />
            </svg>
            <input id="state-search" type="text" placeholder="Buscar estado...">
          </label>

          <div class="state-summary">
            <span id="state-count">0 estados</span>
            <strong><span id="results-count">0</span> UCs previstas</strong>
          </div>

          <div id="results-list" class="results-scroll state-list"></div>
        </aside>

        <section class="map-card">
          <div class="map-wrap">
            <div class="country-dialog">
              <p class="country-dialog__eyebrow">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 2.8a6.7 6.7 0 0 0-6.7 6.7c0 4.9 6.7 11.7 6.7 11.7s6.7-6.8 6.7-11.7A6.7 6.7 0 0 0 12 2.8Zm0 9.2a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
                </svg>
                Brasil
              </p>
              <strong id="dialog-count">0</strong>
              <span>Unidades de Conservacao previstas</span>
              <em>Atividades gratuitas</em>
            </div>

            <div id="map"></div>

            <div class="map-legend">
              <span><i class="legend-dot legend-dot--uc"></i>Unidades de Conservacao</span>
              <span><i class="legend-dot legend-dot--selected"></i>Estado selecionado</span>
            </div>
          </div>
        </section>
      </main>
    </div>

    <template id="state-group-template">
      <section class="state-group">
        <button type="button" class="state-group__header">
          <span class="state-group__badge" data-uf></span>
          <span class="state-group__copy">
            <span class="state-group__name" data-state-name></span>
            <span class="state-group__meta" data-state-meta></span>
          </span>
          <svg class="state-group__arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08-.02Z" clip-rule="evenodd" />
          </svg>
        </button>

        <div class="state-group__body" data-state-body></div>
      </section>
    </template>

    <template id="park-item-template">
      <article class="result-card">
        <p class="result-card__title" data-name></p>
        <p class="result-card__meta" data-location></p>
        <p class="result-card__text" data-description></p>
      </article>
    </template>
  </div>
<?php
  return ob_get_clean();
}

function uc_list_render_shortcode()
{
  uc_list_enqueue_assets();

  ob_start();
?>
  <div class="uc-list-root">
    <aside class="uc-list-sidebar">
      <label class="uc-list-search" for="uc-list-state-search">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10.8 4.2a6.6 6.6 0 1 0 0 13.2 6.6 6.6 0 0 0 0-13.2ZM2.7 10.8a8.1 8.1 0 1 1 14.4 5.1l3.9 3.9-1.2 1.2-3.9-3.9A8.1 8.1 0 0 1 2.7 10.8Z" />
        </svg>
        <input id="uc-list-state-search" type="text" data-list-search placeholder="Buscar estado...">
      </label>

      <div class="uc-list-summary">
        <span><span data-uc-state-count>0</span> estados</span>
        <strong><span data-uc-results-count>0</span> UCs previstas</strong>
      </div>

      <div class="uc-list-biome-filter">
        <div class="uc-list-filter-title">
          <span>Bioma</span>
          <button type="button" data-clear-biomes hidden>Limpar</button>
        </div>
        <div class="uc-list-biomes" data-uc-biome-list></div>
      </div>

      <div class="uc-list-state-scroll" data-uc-state-list></div>
    </aside>

    <section class="uc-list-results">
      <div class="uc-cards-grid" data-uc-cards-grid></div>
      <div class="uc-empty" data-uc-empty hidden>Nenhuma UC encontrada.</div>
    </section>

    <template data-uc-card-template>
      <article class="uc-card">
        <div class="uc-card__overlay"></div>
        <div class="uc-card__content">
          <p data-uc-kicker></p>
          <h3 data-uc-name></h3>
          <p data-uc-description></p>
          <button type="button" data-open-activities>Ver Atividades</button>
        </div>
      </article>
    </template>

    <template data-uc-state-template>
      <button type="button" class="uc-list-state">
        <span class="uc-list-state__badge" data-uf></span>
        <span class="uc-list-state__copy">
          <span class="uc-list-state__name" data-state-name></span>
          <span class="uc-list-state__meta" data-state-meta></span>
        </span>
        <svg class="uc-list-state__arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08-.02Z" clip-rule="evenodd" />
        </svg>
      </button>
    </template>

    <div class="activity-modal" data-activity-modal hidden>
      <div class="activity-modal__backdrop" data-close-activity-modal></div>
      <section class="activity-modal__panel" role="dialog" aria-modal="true" aria-labelledby="activity-modal-title">
        <button type="button" class="activity-modal__close" data-close-activity-modal aria-label="Fechar">×</button>
        <p class="activity-modal__meta" data-activity-modal-meta></p>
        <h2 data-activity-modal-title></h2>
        <div class="activity-modal__body" data-activity-modal-body></div>
      </section>
    </div>
  </div>
<?php
  return ob_get_clean();
}

function uc_partner_render_shortcode($atts)
{
  $atts = shortcode_atts(
    [
      'categoria' => '',
    ],
    $atts,
    'uc_parceiro'
  );

  $category_slug = sanitize_title($atts['categoria']);

  if (!$category_slug || !post_type_exists('parceiro') || !taxonomy_exists('categoria_parceiro')) {
    return '';
  }

  uc_partner_enqueue_assets();

  $partners = new WP_Query([
    'post_type' => 'parceiro',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => [
      'menu_order' => 'ASC',
      'title' => 'ASC',
    ],
    'tax_query' => [
      [
        'taxonomy' => 'categoria_parceiro',
        'field' => 'slug',
        'terms' => $category_slug,
      ],
    ],
    'no_found_rows' => true,
  ]);

  if (!$partners->have_posts()) {
    wp_reset_postdata();
    return '';
  }

  ob_start();
?>
  <div class="uc-partner-slider" data-uc-partner-slider>
    <div class="uc-partner-slider__viewport">
      <div class="uc-partner-slider__track" data-uc-partner-track>
        <?php
        while ($partners->have_posts()) {
          $partners->the_post();
          $post_id = get_the_ID();
          $title = get_the_title();
          $link = get_post_meta($post_id, '_parceiro_link', true);
          $image = get_the_post_thumbnail_url($post_id, 'full');
        ?>
          <div class="uc-partner-slide" data-uc-partner-slide>
            <?php if ($link) : ?>
              <a class="uc-partner-slide__link" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($title); ?>">
            <?php else : ?>
              <span class="uc-partner-slide__frame" aria-label="<?php echo esc_attr($title); ?>">
            <?php endif; ?>

            <?php if ($image) : ?>
              <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
            <?php else : ?>
              <span class="uc-partner-slide__fallback"><?php echo esc_html($title); ?></span>
            <?php endif; ?>

            <?php if ($link) : ?>
              </a>
            <?php else : ?>
              </span>
            <?php endif; ?>
          </div>
        <?php
        }
        wp_reset_postdata();
        ?>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}

function uc_activity_types_render_shortcode($atts)
{
  $atts = shortcode_atts(
    [
      'somente_com_uso' => 'nao',
    ],
    $atts,
    'uc_tipos_atividade'
  );

  if (!taxonomy_exists('tipo_atividade')) {
    return '';
  }

  uc_activity_types_enqueue_assets();

  $hide_empty = in_array(strtolower((string) $atts['somente_com_uso']), ['1', 'true', 'yes', 'sim'], true);
  $terms = get_terms([
    'taxonomy' => 'tipo_atividade',
    'hide_empty' => $hide_empty,
    'meta_key' => '_api_np_order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC',
  ]);

  if (is_wp_error($terms) || empty($terms)) {
    return '';
  }

  usort($terms, function ($a, $b) {
    $order_a = (int) get_term_meta($a->term_id, '_api_np_order', true);
    $order_b = (int) get_term_meta($b->term_id, '_api_np_order', true);

    if ($order_a && $order_b && $order_a !== $order_b) {
      return $order_a <=> $order_b;
    }

    if ($order_a && !$order_b) {
      return -1;
    }

    if (!$order_a && $order_b) {
      return 1;
    }

    return strcasecmp(remove_accents($a->name), remove_accents($b->name));
  });

  ob_start();
?>
  <div class="uc-activity-type-slider" data-uc-partner-slider>
    <div class="uc-activity-type-slider__viewport">
      <div class="uc-activity-type-slider__track" data-uc-partner-track>
        <?php foreach ($terms as $term) :
          $icon = get_term_meta($term->term_id, '_api_np_fa_icon', true);
          $icon = $icon ? sanitize_html_class($icon) : 'fa-circle';
        ?>
          <div class="uc-activity-type-slide" data-uc-partner-slide>
            <article class="uc-activity-type-card">
              <span class="uc-activity-type-card__icon" aria-hidden="true">
                <i class="fa-solid <?php echo esc_attr($icon); ?>"></i>
              </span>
              <h3><?php echo esc_html($term->name); ?></h3>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}

function uc_normalize_fa_icon_class($value)
{
  $parts = preg_split('/\s+/', trim((string) $value));

  foreach ((array) $parts as $part) {
    $part = sanitize_html_class($part);
    if (0 === strpos($part, 'fa-') && !in_array($part, ['fa-solid', 'fa-regular', 'fa-brands'], true)) {
      return $part;
    }
  }

  return 'fa-circle';
}

function uc_carry_items_render_shortcode($atts)
{
  $atts = shortcode_atts(
    [
      'id' => '',
    ],
    $atts,
    'uc_oque_levar'
  );

  $uc_id = absint($atts['id']);

  if (!$uc_id && is_singular('uc')) {
    $uc_id = get_queried_object_id();
  }

  if (!$uc_id || 'uc' !== get_post_type($uc_id) || !post_type_exists('oque_levar')) {
    return '';
  }

  $ids = get_post_meta($uc_id, '_uc_oque_levar_ids', true);
  $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];

  if (empty($ids)) {
    return '';
  }

  uc_activity_types_enqueue_assets();

  $items = get_posts([
    'post_type' => 'oque_levar',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'post__in' => $ids,
    'orderby' => 'post__in',
  ]);

  if (!$items) {
    return '';
  }

  ob_start();
?>
  <div class="uc-carry-slider" data-uc-partner-slider>
    <div class="uc-carry-slider__viewport">
      <div class="uc-carry-slider__track" data-uc-partner-track>
        <?php foreach ($items as $item) :
          $icon = uc_normalize_fa_icon_class(get_post_meta($item->ID, '_oque_levar_icone', true));
        ?>
          <div class="uc-carry-slide" data-uc-partner-slide>
            <article class="uc-carry-card">
              <span class="uc-carry-card__icon" aria-hidden="true">
                <i class="fa-solid <?php echo esc_attr($icon); ?>"></i>
              </span>
              <h3><?php echo esc_html(get_the_title($item)); ?></h3>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}

function uc_testimonial_get_media($post_id)
{
  $video_url = trim((string) get_post_meta($post_id, '_depoimento_url_video', true));

  if ($video_url) {
    $embed = wp_oembed_get($video_url);

    if ($embed) {
      return [
        'type' => 'embed',
        'html' => $embed,
      ];
    }

    return [
      'type' => 'link',
      'url' => $video_url,
    ];
  }

  $upload_id = absint(get_post_meta($post_id, '_depoimento_upload_video_foto', true));

  if ($upload_id) {
    $mime = (string) get_post_mime_type($upload_id);
    $url = wp_get_attachment_url($upload_id);

    if ($url && 0 === strpos($mime, 'video/')) {
      return [
        'type' => 'video',
        'url' => $url,
        'mime' => $mime,
      ];
    }

    $image = wp_get_attachment_image($upload_id, 'large', false, [
      'class' => 'uc-testimonial__image',
      'loading' => 'lazy',
    ]);

    if ($image) {
      return [
        'type' => 'image',
        'html' => $image,
      ];
    }
  }

  $featured = get_the_post_thumbnail($post_id, 'large', [
    'class' => 'uc-testimonial__image',
    'loading' => 'lazy',
  ]);

  if ($featured) {
    return [
      'type' => 'image',
      'html' => $featured,
    ];
  }

  return null;
}

function uc_testimonial_render_media($media)
{
  if (!$media) {
    return '';
  }

  ob_start();
?>
  <div class="uc-testimonial__media uc-testimonial__media--<?php echo esc_attr($media['type']); ?>">
    <?php if ('embed' === $media['type']) : ?>
      <div class="uc-testimonial__embed"><?php echo $media['html']; ?></div>
    <?php elseif ('video' === $media['type']) : ?>
      <video class="uc-testimonial__video" controls preload="metadata">
        <source src="<?php echo esc_url($media['url']); ?>" type="<?php echo esc_attr($media['mime']); ?>">
      </video>
    <?php elseif ('link' === $media['type']) : ?>
      <a class="uc-testimonial__video-link" href="<?php echo esc_url($media['url']); ?>" target="_blank" rel="noopener">Abrir video</a>
    <?php elseif ('image' === $media['type']) : ?>
      <?php echo $media['html']; ?>
    <?php endif; ?>
  </div>
<?php
  return ob_get_clean();
}

function uc_testimonial_is_truthy($value)
{
  return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'sim', 'slider'], true);
}

function uc_testimonial_render_item($post_id, $is_slide = false)
{
  $title = get_the_title($post_id);
  $content = apply_filters('the_content', get_post_field('post_content', $post_id));
  $media = uc_testimonial_get_media($post_id);

  ob_start();
?>
  <article class="uc-testimonial<?php echo $media ? ' uc-testimonial--has-media' : ''; ?><?php echo $is_slide ? ' uc-testimonial--slide' : ''; ?>">
    <div class="uc-testimonial__inner">
      <?php echo uc_testimonial_render_media($media); ?>

      <div class="uc-testimonial__content">
        <?php if ($title) : ?>
          <h3 class="uc-testimonial__title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>

        <?php if ($content) : ?>
          <div class="uc-testimonial__text"><?php echo wp_kses_post($content); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </article>
<?php
  return ob_get_clean();
}

function uc_testimonial_render_shortcode($atts)
{
  $atts = shortcode_atts(
    [
      'id' => 0,
      'slider' => 'nao',
      'quantidade' => 6,
    ],
    $atts,
    'uc_depoimento'
  );

  if (!post_type_exists('depoimento')) {
    return '';
  }

  $post_id = absint($atts['id']);
  $is_slider = !$post_id && uc_testimonial_is_truthy($atts['slider']);
  $quantity = max(1, min(24, absint($atts['quantidade'])));

  uc_testimonial_enqueue_assets($is_slider);

  $query_args = [
    'post_type' => 'depoimento',
    'post_status' => 'publish',
    'posts_per_page' => $is_slider ? $quantity : 1,
    'no_found_rows' => true,
  ];

  if ($post_id) {
    $query_args['p'] = $post_id;
  } else {
    $query_args['orderby'] = 'rand';
  }

  $testimonial = new WP_Query($query_args);

  if (!$testimonial->have_posts()) {
    wp_reset_postdata();
    return '';
  }

  if ($is_slider) {
    ob_start();
?>
    <div class="uc-testimonial-slider" data-uc-testimonial-slider>
      <div class="uc-testimonial-slider__viewport">
        <div class="uc-testimonial-slider__track" data-uc-testimonial-track>
          <?php
          while ($testimonial->have_posts()) {
            $testimonial->the_post();
            echo '<div class="uc-testimonial-slider__slide" data-uc-testimonial-slide>';
            echo uc_testimonial_render_item(get_the_ID(), true);
            echo '</div>';
          }
          ?>
        </div>
      </div>

      <div class="uc-testimonial-slider__controls">
        <button type="button" class="uc-testimonial-slider__button" data-uc-testimonial-prev aria-label="Depoimento anterior">&lsaquo;</button>
        <div class="uc-testimonial-slider__dots" data-uc-testimonial-dots></div>
        <button type="button" class="uc-testimonial-slider__button" data-uc-testimonial-next aria-label="Proximo depoimento">&rsaquo;</button>
      </div>
    </div>
<?php
    wp_reset_postdata();

    return ob_get_clean();
  }

  ob_start();
  $testimonial->the_post();
  echo uc_testimonial_render_item(get_the_ID(), false);
  wp_reset_postdata();

  return ob_get_clean();
}

function uc_map_enqueue_plugin_assets()
{
  uc_map_enqueue_assets();
  uc_list_enqueue_assets();
}

add_action('wp_enqueue_scripts', 'uc_map_enqueue_plugin_assets');
add_action('wp_enqueue_scripts', 'uc_single_enqueue_assets');
add_action('elementor/frontend/after_enqueue_styles', 'uc_map_enqueue_plugin_assets');
add_action('elementor/frontend/after_enqueue_scripts', 'uc_map_enqueue_plugin_assets');
add_action('elementor/preview/enqueue_styles', 'uc_map_enqueue_plugin_assets');
add_action('elementor/preview/enqueue_scripts', 'uc_map_enqueue_plugin_assets');
add_shortcode('uc_map', 'uc_map_render_shortcode');
add_shortcode('uc_list', 'uc_list_render_shortcode');
add_shortcode('uc_parceiro', 'uc_partner_render_shortcode');
add_shortcode('uc_tipos_atividade', 'uc_activity_types_render_shortcode');
add_shortcode('uc_oque_levar', 'uc_carry_items_render_shortcode');
add_shortcode('uc_depoimento', 'uc_testimonial_render_shortcode');
