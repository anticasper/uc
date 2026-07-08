/**
 * Elementor Widgets JavaScript
 *
 * Frontend interactivity for Um Dia No Parque Elementor widgets.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 */

(function ($) {
    'use strict';

    /**
     * Initialize the Interactive Map widget (Leaflet.js + AJAX).
     *
     * @since 1.0.0
     */
    function initInteractiveMap() {
        if (typeof console !== 'undefined' && console.log) {
            console.log('[UMDNP Mapa] initInteractiveMap called');
        }
        $('.umdnp-mapa-wrapper').each(function () {
            var $wrapper = $(this);

            // Prevent double initialization.
            if ($wrapper.data('_umdnp_initialized')) {
                return;
            }
            $wrapper.data('_umdnp_initialized', true);

            var elementId = $wrapper.data('umdnp-map');
            if (!elementId) {
                return;
            }

            // Read config from <script type="application/json"> tag.
            var $script = $('#umdnp-config-' + elementId);
            if (!$script.length) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[UMDNP Mapa] Config script not found for:', elementId);
                }
                return;
            }

            var config;
            try {
                config = JSON.parse($script.text());
            } catch (e) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[UMDNP Mapa] Invalid JSON config:', e);
                }
                return;
            }

            if (!config || !config.map_id) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[UMDNP Mapa] Invalid config:', config);
                }
                return;
            }

            var mapId = config.map_id;
            var $mapContainer = $wrapper.find('#' + mapId);
            var $filtros = $wrapper.find('.umdnp-mapa-filtros');
            var $lista = $wrapper.find('.umdnp-mapa-lista-itens');
            var $count = $wrapper.find('.umdnp-mapa-lista-count');
            var $loading = $mapContainer.find('.umdnp-mapa-loading');

            // Estado do mapa
            var map = null;
            var markersLayer = null;
            var allMarkers = [];
            var markerIconCache = {};

            // Config values with defaults
            var markerSize = parseInt(config.marker_size, 10) || 32;
            var markerIconSize = parseInt(config.marker_icon_size, 10) || 18;

            /**
             * Create a custom marker icon.
             * Supports Dashicon or custom image via marker_icon_source.
             */
            function getMarkerIcon() {
                if (markerIconCache._default) {
                    return markerIconCache._default;
                }

                var iconHtml;
                var color = config.marker_color_active || '#4CAF50';

                // Custom image marker
                if (config.marker_icon_source === 'custom_image' && config.marker_custom_icon) {
                    iconHtml = '<div class="umdnp-marker-icon umdnp-marker-icon-image"' +
                        ' style="width:' + markerSize + 'px; height:' + markerSize + 'px;">' +
                        '<img src="' + config.marker_custom_icon + '" alt=""' +
                        ' style="width:' + markerSize + 'px; height:' + markerSize + 'px; object-fit:contain;">' +
                        '</div>';
                } else {
                    // Dashicon marker (existing behavior)
                    var iconClass = config.marker_icon_type || 'dashicons-location';
                    iconHtml = '<div class="umdnp-marker-icon"' +
                        ' style="background-color:' + color + '; width:' + markerSize + 'px; height:' + markerSize + 'px;">' +
                        '<span class="dashicons ' + iconClass + '"' +
                        ' style="font-size:' + markerIconSize + 'px; width:' + markerIconSize + 'px; height:' + markerIconSize + 'px; line-height:' + markerIconSize + 'px;"></span>' +
                        '</div>';
                }

                var icon = L.divIcon({
                    className: '',
                    html: iconHtml,
                    iconSize: [markerSize, markerSize],
                    iconAnchor: [markerSize / 2, markerSize],
                    popupAnchor: [0, -markerSize],
                });

                markerIconCache._default = icon;
                return icon;
            }

            /**
             * Build popup content for a marker.
             */
            function buildPopupContent(marker) {
                var html = '<div class="umdnp-popup-wrapper">';

                if (config.popup_show_image === '1') {
                    var imgUrl = marker.thumbnail || config.popup_default_image || '';
                    if (imgUrl) {
                        html += '<img class="umdnp-popup-thumb" src="' + imgUrl + '" alt="' + marker.name + '" />';
                    } else {
                        html += '<div class="umdnp-popup-thumb-placeholder"><span class="dashicons dashicons-palmtree"></span></div>';
                    }
                }

                html += '<h4>' + $('<span>').text(marker.name).html() + '</h4>';

                if (config.popup_show_city === '1' && marker.cidade) {
                    html += '<span class="umdnp-popup-cidade">' + marker.cidade;
                    if (config.popup_show_uf === '1' && marker.uf) {
                        html += ' - ' + marker.uf;
                    }
                    html += '</span>';
                } else if (config.popup_show_uf === '1' && marker.uf) {
                    html += '<span class="umdnp-popup-cidade">' + marker.uf + '</span>';
                }

                if (config.popup_show_endereco === '1' && (marker.endereco || marker.numero)) {
                    var addr = marker.endereco || '';
                    if (marker.numero) {
                        addr += (addr ? ', ' : '') + marker.numero;
                    }
                    html += '<span class="umdnp-popup-endereco">' + $('<span>').text(addr).html() + '</span>';
                }

                if (config.popup_show_cep === '1' && marker.cep) {
                    html += '<span class="umdnp-popup-cep">CEP: ' + marker.cep + '</span>';
                }

                html += '<br><a class="umdnp-popup-link" href="' + marker.permalink + '" target="_blank">' +
                        config.i18n.more_info + '</a>';

                html += '</div>';
                return html;
            }

            /**
             * Initialize or update the map.
             */
            function initMap() {
                if (map === null) {
                    map = L.map(mapId, {
                        center: [config.default_lat, config.default_lng],
                        zoom: config.default_zoom,
                        zoomControl: config.zoom_controls === '1',
                        scrollWheelZoom: config.scroll_wheel_zoom === '1',
                    });

                    L.tileLayer(config.tile_url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: config.tile_attribution || '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: parseInt(config.tile_max_zoom, 10) || 19,
                    }).addTo(map);
                }

                // Clear existing markers
                if (markersLayer !== null) {
                    map.removeLayer(markersLayer);
                }

                var clusterRadius = parseInt(config.cluster_radius, 10) || 50;

                if (config.marker_clustering === '1') {
                    markersLayer = L.markerClusterGroup({
                        maxClusterRadius: clusterRadius,
                        spiderfyOnMaxZoom: true,
                        showCoverageOnHover: false,
                        zoomToBoundsOnClick: true,
                    });
                } else {
                    markersLayer = L.layerGroup();
                }

                allMarkers = [];

                if (map._umdnpFitBoundsTimeout) {
                    clearTimeout(map._umdnpFitBoundsTimeout);
                }
            }

            /**
             * Load park data via AJAX and add markers.
             */
            function loadMapData() {
                var params = {
                    action: 'umdnp_get_parques_mapa',
                    nonce: config.nonce,
                    search: $wrapper.find('.umdnp-mapa-busca-input').val() || '',
                    parque: $wrapper.find('.umdnp-mapa-filtro-parque select').val() || '',
                    unidade: $wrapper.find('.umdnp-mapa-filtro-unidade select').val() || '',
                    cidade: $wrapper.find('.umdnp-mapa-filtro-cidade select').val() || '',
                    tipo_atividade: $wrapper.find('.umdnp-mapa-filtro-tipo-atividade select').val() || '',
                };

                $loading.show();

                $.ajax({
                    url: config.ajax_url,
                    data: params,
                    dataType: 'json',
                    timeout: 15000 // 15s timeout
                })
                    .done(function (response) {
                        $loading.hide();

                        // Guard: ensure Leaflet is loaded before proceeding.
                        if (typeof L === 'undefined') {
                            if (typeof console !== 'undefined' && console.error) {
                                console.error('[UMDNP Mapa] Leaflet (L) not loaded — skipping map init');
                            }
                            if ($lista.length) {
                                $lista.html('<p class="umdnp-mapa-lista-placeholder">' + config.i18n.error + '</p>');
                            }
                            return;
                        }

                        if (!response.success || !response.data.markers || response.data.markers.length === 0) {
                            // Nenhum resultado
                            if ($count.length) {
                                $count.text('0');
                            }
                            if ($lista.length) {
                                $lista.html('<p class="umdnp-mapa-lista-placeholder">' + config.i18n.no_results + '</p>');
                            }
                            if (map) {
                                map.setView([config.default_lat, config.default_lng], config.default_zoom);
                            }
                            return;
                        }

                        var markers = response.data.markers;
                        if ($count.length) {
                            $count.text(markers.length);
                        }

                        // Add markers to map
                        initMap();

                        var bounds = [];
                        $.each(markers, function (i, markerData) {
                            var icon = getMarkerIcon();
                            var marker = L.marker([markerData.lat, markerData.lng], { icon: icon });

                            marker.bindPopup(buildPopupContent(markerData), {
                                maxWidth: 300,
                                minWidth: 220,
                                className: 'umdnp-popup-container',
                            });

                            marker._umdnpData = markerData;
                            markersLayer.addLayer(marker);
                            allMarkers.push(marker);
                            bounds.push([markerData.lat, markerData.lng]);
                        });

                        map.addLayer(markersLayer);

                        // Fit bounds to show all markers
                        if (bounds.length > 0) {
                            map._umdnpFitBoundsTimeout = setTimeout(function () {
                                var group = L.featureGroup(markersLayer.getLayers());
                                map.fitBounds(group.getBounds().pad(0.1), {
                                    maxZoom: 14,
                                });
                            }, 100);
                        }

                        // Update sidebar list
                        updateSidebarList(markers);

                        // Highlight first marker popup
                        if (allMarkers.length > 0 && $lista.length === 0) {
                            allMarkers[0].openPopup();
                        }
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        $loading.hide();
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('[UMDNP Mapa] AJAX error:', textStatus, errorThrown, jqXHR.responseText);
                        }
                        if ($lista.length) {
                            $lista.html('<p class="umdnp-mapa-lista-placeholder">' + config.i18n.error + '</p>');
                        }
                    });
            }

            /**
             * Update the sidebar list with park items.
             */
            function updateSidebarList(markers) {
                if (!$lista.length) {
                    return;
                }

                if (markers.length === 0) {
                    $lista.html('<p class="umdnp-mapa-lista-placeholder">' + config.i18n.no_results + '</p>');
                    return;
                }

                var showThumb = config.list_show_thumbnail !== '0';
                var showCity = config.list_show_city !== '0';

                var html = '';
                $.each(markers, function (i, marker) {
                    html += '<div class="umdnp-mapa-lista-item" data-index="' + i + '">';

                    if (showThumb) {
                        var thumbHtml = '';
                        var thumbUrl = marker.thumbnail || config.popup_default_image || '';
                        if (thumbUrl) {
                            thumbHtml = '<img src="' + thumbUrl + '" alt="' + marker.name + '">';
                        } else {
                            thumbHtml = '<span class="dashicons dashicons-palmtree" style="font-size:24px;color:#ccc;display:flex;align-items:center;justify-content:center;width:100%;height:100%;"></span>';
                        }
                        html += '<div class="umdnp-mapa-lista-item-thumb">' + thumbHtml + '</div>';
                    }

                    html += '<div class="umdnp-mapa-lista-item-info">';
                    html += '<div class="umdnp-mapa-lista-item-name">' + marker.name + '</div>';

                    if (showCity && marker.cidade) {
                        html += '<span class="umdnp-mapa-lista-item-cidade">' + marker.cidade;
                        if (config.list_show_uf === '1' && marker.uf) {
                            html += ' - ' + marker.uf;
                        }
                        html += '</span>';
                    } else if (config.list_show_uf === '1' && marker.uf) {
                        html += '<span class="umdnp-mapa-lista-item-cidade">' + marker.uf + '</span>';
                    }

                    if (config.list_show_endereco === '1' && (marker.endereco || marker.numero)) {
                        var addr = marker.endereco || '';
                        if (marker.numero) {
                            addr += (addr ? ', ' : '') + marker.numero;
                        }
                        html += '<span class="umdnp-mapa-lista-item-endereco">' + $('<span>').text(addr).html() + '</span>';
                    }

                    html += '</div></div>';
                });

                $lista.html(html);

                // Click on sidebar item → fly to marker
                $lista.find('.umdnp-mapa-lista-item').on('click', function () {
                    var idx = $(this).data('index');
                    if (allMarkers[idx]) {
                        allMarkers[idx].openPopup();
                        map.setView(allMarkers[idx].getLatLng(), Math.max(map.getZoom(), 10));
                    }
                    $lista.find('.umdnp-mapa-lista-item').removeClass('active');
                    $(this).addClass('active');
                });
            }

            // --- Bootstrap ---

            // Botão Buscar → carrega dados do mapa
            $wrapper.on('click', '.umdnp-mapa-btn-buscar', function () {
                loadMapData();
            });

            // Enter no campo de busca também dispara a busca
            $filtros.on('keydown', '.umdnp-mapa-busca-input', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    loadMapData();
                }
            });

            // Auto-load: carrega todas as UCs na inicialização.
            loadMapData();
        });
    }

    // Initialize on Elementor frontend ready
    $(window).on('elementor/frontend/init', function () {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/um-dia-no-parque-mapa-interativo.default', function ($scope) {
                initInteractiveMap();
            });
        }
    });

    // Fallback: initialize on DOM ready if not inside Elementor editor
    if (typeof elementorFrontend === 'undefined') {
        $(document).ready(function () {
            initInteractiveMap();
        });
    }

    // ============================================================
    // EXPLORAR WIDGET — Client-side filter
    // ============================================================

    function initExplorarWidget($scope) {
        var $wrapper = $scope.find('.umdnp-explorar-wrapper');
        if (!$wrapper.length) return;

        var $cards    = $wrapper.find('.umdnp-explorar-card');
        var $search   = $wrapper.find('.umdnp-explorar-search-input');

        function applyFilters() {
            var text = $search.length ? $search.val().toLowerCase().trim() : '';

            // Collect checked filters per group
            var activeBiomas = [];
            var activeUFs    = [];
            $wrapper.find('.umdnp-explorar-filter-options[data-filter="bioma"] input:checked').each(function () {
                activeBiomas.push($(this).val());
            });
            $wrapper.find('.umdnp-explorar-filter-options[data-filter="uf"] input:checked').each(function () {
                activeUFs.push($(this).val());
            });

            $cards.each(function () {
                var $card = $(this);
                var show  = true;

                // Text search
                if (text) {
                    var searchData = $card.data('search') || '';
                    if (searchData.indexOf(text) === -1) {
                        show = false;
                    }
                }

                // Bioma filter
                if (show && activeBiomas.length) {
                    var cardBiomas = ($card.data('bioma') || '').split(',');
                    var match = false;
                    $.each(activeBiomas, function (i, slug) {
                        if ($.inArray(slug, cardBiomas) !== -1) {
                            match = true;
                            return false;
                        }
                    });
                    if (!match) show = false;
                }

                // UF filter
                if (show && activeUFs.length) {
                    var cardUFs = ($card.data('uf') || '').split(',');
                    var match = false;
                    $.each(activeUFs, function (i, uf) {
                        if ($.inArray(uf, cardUFs) !== -1) {
                            match = true;
                            return false;
                        }
                    });
                    if (!match) show = false;
                }

                $card.toggleClass('umdnp-hidden', !show);
            });

            // Show/hide empty message
            var visible = $cards.not('.umdnp-hidden').length;
            var $empty = $wrapper.find('.umdnp-explorar-empty');
            if ($empty.length) {
                $empty.toggle(visible === 0);
            }
        }

        // Bind events
        if ($search.length) {
            $search.on('input', applyFilters);
        }
        $wrapper.on('change', '.umdnp-explorar-filter-options input[type="checkbox"]', applyFilters);
    }

    // Elementor hook
    $(window).on('elementor/frontend/init', function () {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/um-dia-no-parque-explorar.default', function ($scope) {
                initExplorarWidget($scope);
            });
        }
    });

    // Fallback
    if (typeof elementorFrontend === 'undefined') {
        $(document).ready(function () {
            $('.umdnp-explorar-wrapper').each(function () {
                initExplorarWidget($(this).closest('.elementor-widget'));
            });
        });
    }

})(jQuery);
