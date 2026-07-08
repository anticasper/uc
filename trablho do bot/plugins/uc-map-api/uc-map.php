<?php

/**
 * Plugin Name: UC Map API
 * Plugin URI: https://barradois.com
 * Description: Exibe um mapa interativo de Unidades de Conservação via shortcode para uso no Elementor.
 * Version: 1.3.6
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

define('UC_MAP_VERSION', '1.3.6');

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
