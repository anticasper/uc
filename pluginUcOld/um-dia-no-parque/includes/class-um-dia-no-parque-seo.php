<?php
/**
 * SEO (Search Engine Optimization) Class
 *
 * Adiciona dados estruturados Schema.org, meta tags Open Graph / Twitter,
 * metabox de meta description, filtros de sitemap e integração com
 * plugins de SEO (Yoast, Rank Math).
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO class for structured data, meta tags, and SEO integrations.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_SEO {

    /**
     * Singleton instance.
     *
     * @since  1.0.0
     * @var    self|null
     */
    private static $instance = null;

    /**
     * Meta key used to store the custom meta description.
     *
     * @since  1.0.0
     * @var    string
     */
    const META_DESC_KEY = '_umdnp_meta_description';

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton instance.
     *
     * @throws \Exception Always throws.
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Constructor — registers all SEO hooks.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Schema.org JSON-LD no <head>.
        add_action('wp_head', array($this, 'output_schema_markup'), 1);

        // Open Graph e Twitter Cards no <head>.
        add_action('wp_head', array($this, 'output_og_tags'), 2);

        // Meta description tag no <head>.
        add_action('wp_head', array($this, 'output_meta_description_tag'), 3);

        // Metabox de meta description no admin.
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_description'), 10, 2);

        // Sitemap filters (WP 5.5+).
        add_filter('wp_sitemaps_post_types', array($this, 'filter_sitemap_post_types'));
        add_filter('wp_sitemaps_taxonomies', array($this, 'filter_sitemap_taxonomies'));

        // Yoast SEO integration.
        add_filter('wpseo_title', array($this, 'filter_yoast_title'), 10, 2);
        add_filter('wpseo_metadesc', array($this, 'filter_yoast_metadesc'), 10, 2);
        add_filter('wpseo_schema_graph', array($this, 'extend_yoast_schema'), 10, 2);

        // Rank Math integration.
        add_filter('rank_math/frontend/title', array($this, 'filter_rankmath_title'), 10, 1);
        add_filter('rank_math/frontend/description', array($this, 'filter_rankmath_description'), 10, 1);
    }

    // ---------------------------------------------------------------
    // 1. METABOX — META DESCRIPTION
    // ---------------------------------------------------------------

    /**
     * Add meta description metabox Parques.
     *
     * @since 1.0.0
     */
    public function add_meta_boxes() {
        $post_types = $this->get_target_post_types();

        foreach ($post_types as $pt) {
            add_meta_box(
                'umdnp_seo_metabox',
                __('SEO — Meta Description', 'um-dia-no-parque'),
                array($this, 'render_meta_description_metabox'),
                $pt,
                'side',
                'low'
            );
        }
    }

    /**
     * Render the meta description metabox.
     *
     * @since 1.0.0
     * @param WP_Post $post Current post object.
     */
    public function render_meta_description_metabox($post) {
        wp_nonce_field('umdnp_seo_metabox_nonce', 'umdnp_seo_metabox_nonce');

        $value = get_post_meta($post->ID, self::META_DESC_KEY, true);
        ?>
        <p>
            <textarea id="umdnp_meta_description"
                      name="umdnp_meta_description"
                      rows="4"
                      style="width:100%;"
                      maxlength="160"
                      placeholder="<?php esc_attr_e('Breve descrição para resultados de busca (máx. 160 caracteres).', 'um-dia-no-parque'); ?>"><?php echo esc_textarea($value); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e('Usado como meta description e como descrição padrão do Open Graph. Deixe vazio para usar o excerpt ou um resumo automático.', 'um-dia-no-parque'); ?>
        </p>
        <?php
    }

    /**
     * Save the meta description.
     *
     * @since 1.0.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_description($post_id, $post) {
        // Verify nonce.
        if (!isset($_POST['umdnp_seo_metabox_nonce'])
            || !wp_verify_nonce(sanitize_key($_POST['umdnp_seo_metabox_nonce']), 'umdnp_seo_metabox_nonce')) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type.
        if (!in_array($post->post_type, $this->get_target_post_types(), true)) {
            return;
        }

        // Check capabilities.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Sanitize and save.
        if (isset($_POST['umdnp_meta_description'])) {
            $desc = sanitize_textarea_field(wp_unslash($_POST['umdnp_meta_description']));
            $desc = wp_trim_words($desc, 25, '');
            $desc = mb_substr($desc, 0, 160);

            if (!empty($desc)) {
                update_post_meta($post_id, self::META_DESC_KEY, $desc);
            } else {
                delete_post_meta($post_id, self::META_DESC_KEY);
            }
        }
    }

    // ---------------------------------------------------------------
    // 2. META DESCRIPTION TAG
    // ---------------------------------------------------------------

    /**
     * Output the <meta name="description"> tag in <head>.
     *
     * @since 1.0.0
     */
    public function output_meta_description_tag() {
        $description = $this->get_meta_description();

        if (!empty($description)) {
            echo "\n" . '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
    }

    /**
     * Get the meta description for the current page.
     *
     * @since  1.0.0
     * @return string Meta description or empty string.
     */
    private function get_meta_description() {
        $description = '';

        if (is_singular($this->get_target_post_types())) {
            $post_id = get_the_ID();

            // 1. Custom meta description
            $custom = get_post_meta($post_id, self::META_DESC_KEY, true);
            if (!empty($custom)) {
                $description = $custom;
            }

            // 2. Fallback: excerpt
            if (empty($description)) {
                $post = get_post($post_id);
                if (!empty($post->post_excerpt)) {
                    $description = wp_trim_words($post->post_excerpt, 25, '');
                }
            }

            // 3. Fallback: do conteúdo
            if (empty($description)) {
                $description = wp_trim_words(get_the_excerpt(), 25, '');
            }
        }

        // Archive pages: use archive description.
        if (is_post_type_archive($this->get_target_post_types())) {
            $post_type = get_queried_object();
            if ($post_type && !empty($post_type->description)) {
                $description = wp_trim_words($post_type->description, 25, '');
            }
        }

        // Taxonomy archives: use term description.
        if (is_tax(array('bioma', 'dificuldade', 'publico', 'tipo_atividade', 'categoria_parceiro'))) {
            $term = get_queried_object();
            if ($term && !empty($term->description)) {
                $description = wp_trim_words($term->description, 25, '');
            }
        }

        return apply_filters('umdnp_meta_description', $description);
    }

    // ---------------------------------------------------------------
    // 3. SCHEMA.ORG JSON-LD
    // ---------------------------------------------------------------

    /**
     * Output Schema.org JSON-LD markup in <head>.
     *
     * @since 1.0.0
     */
    public function output_schema_markup() {
        $schemas = array();

        // Single parque → Park schema.
        if (is_singular('uc')) {
            $schema = $this->build_park_schema(get_the_ID());
            if ($schema) {
                $schemas[] = $schema;
            }
        }

        // BreadcrumbList on all pages.
        $breadcrumbs = $this->build_breadcrumb_schema();
        if ($breadcrumbs) {
            $schemas[] = $breadcrumbs;
        }

        // BlogPosting for standard posts in our ecosystem.
        if (is_singular('post')) {
            $schemas[] = $this->build_article_schema(get_the_ID());
        }

        // Output each schema.
        foreach ($schemas as $schema) {
            echo "\n" . '<script type="application/ld+json">' . "\n"
                . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"
                . '</script>' . "\n";
        }
    }

    /**
     * Build Park schema (https://schema.org/Park).
     *
     * @since  1.0.0
     * @param  int $post_id Post ID.
     * @return array|null Schema array or null if no data.
     */
    private function build_park_schema($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Park',
            '@id'         => get_permalink($post_id) . '#park',
            'name'        => get_the_title($post_id),
            'url'         => get_permalink($post_id),
            'description' => $this->get_meta_description() ?: wp_trim_words(get_the_excerpt($post_id), 25, ''),
        );

        // Image.
        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $schema['image'] = esc_url($image_url);
            }
        }

        // Address (cidade from taxonomy canonical).
        $endereco   = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_ENDERECO, true);
        $city_terms = wp_get_object_terms($post_id, 'cidade', array('fields' => 'names'));
        $municipio  = !empty($city_terms) ? $city_terms[0] : '';
        $cep        = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_CEP, true);

        $address = array('@type' => 'PostalAddress');
        $has_address = false;

        if (!empty($endereco)) {
            $address['streetAddress'] = $endereco;
            $has_address = true;
        }
        if (!empty($municipio)) {
            $address['addressLocality'] = $municipio;
            $has_address = true;
        }
        if (!empty($cep)) {
            $address['postalCode'] = $cep;
            $has_address = true;
        }
        if ($has_address) {
            $schema['address'] = $address;
        }

        // Email.
        $email = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_EMAIL, true);
        if (!empty($email)) {
            $schema['email'] = $email;
        }

        // Telephone (WhatsApp).
        $whatsapp = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_WHATSAPP, true);
        if (!empty($whatsapp)) {
            $schema['telephone'] = $whatsapp;
        }

        return apply_filters('umdnp_park_schema', $schema, $post_id);
    }

    /**
     * Build BreadcrumbList schema.
     *
     * @since  1.0.0
     * @return array|null Schema array or null.
     */
    private function build_breadcrumb_schema() {
        if (is_front_page() || is_home()) {
            return null;
        }

        $crumbs = array();

        // Home.
        $crumbs[] = array(
            '@type' => 'ListItem',
            'position' => 1,
            'name'  => __('Início', 'um-dia-no-parque'),
            'item'  => home_url(),
        );

        $position = 2;

        // Post type archive.
        if (is_singular($this->get_target_post_types()) || is_post_type_archive($this->get_target_post_types()) || is_tax(array('bioma', 'dificuldade', 'publico', 'tipo_atividade', 'categoria_parceiro'))) {
            $post_type = null;

            if (is_singular('uc') || is_post_type_archive('uc') || is_tax('bioma')) {
                $post_type = 'uc';
                $label = __('Parques', 'um-dia-no-parque');
            }

            if ($post_type) {
                $archive_url = get_post_type_archive_link($post_type);
                if ($archive_url) {
                    $crumbs[] = array(
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name'  => $label,
                        'item'  => $archive_url,
                    );
                }
            }

            // Taxonomy term.
            if (is_tax()) {
                $term = get_queried_object();
                if ($term) {
                    $crumbs[] = array(
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name'  => $term->name,
                        'item'  => get_term_link($term),
                    );
                }
            }

            // Single post.
            if (is_singular($this->get_target_post_types())) {
                $crumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name'  => get_the_title(),
                    'item'  => get_permalink(),
                );
            }
        }

        if (count($crumbs) <= 1) {
            return null;
        }

        return array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        );
    }

    /**
     * Build Article schema for regular posts.
     *
     * @since  1.0.0
     * @param  int $post_id Post ID.
     * @return array
     */
    private function build_article_schema($post_id) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Article',
            '@id'      => get_permalink($post_id) . '#article',
            'headline' => get_the_title($post_id),
            'url'      => get_permalink($post_id),
        );

        $image_id = get_post_thumbnail_id($post_id);
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if ($image_url) {
                $schema['image'] = esc_url($image_url);
            }
        }

        $author_id = get_post_field('post_author', $post_id);
        if ($author_id) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', $author_id),
            );
        }

        $schema['datePublished'] = get_the_date('c', $post_id);
        $schema['dateModified']  = get_the_modified_date('c', $post_id);

        return apply_filters('umdnp_article_schema', $schema, $post_id);
    }

    // ---------------------------------------------------------------
    // 4. OPEN GRAPH / TWITTER CARDS
    // ---------------------------------------------------------------

    /**
     * Output Open Graph and Twitter Card meta tags in <head>.
     *
     * @since 1.0.0
     */
    public function output_og_tags() {
        global $wp;

        $og = array();

        // Default values.
        $og['og:locale']    = get_locale();
        $og['og:site_name'] = get_bloginfo('name');
        $og['og:url']       = home_url(add_query_arg(array(), $wp->request));
        $og['og:type']      = 'website';

        $title       = '';
        $description = '';
        $image_url   = '';

        // Front page.
        if (is_front_page()) {
            $og['og:type'] = 'website';
            $title         = get_bloginfo('name');
            $description   = get_bloginfo('description');
        }

        // Singular (parques, eventos, posts).
        if (is_singular($this->get_target_post_types()) || is_singular('post')) {
            $post_id = get_the_ID();

            $og['og:type'] = 'article';
            $og['og:url']  = get_permalink($post_id);

            $title = get_the_title($post_id);

            $description = $this->get_meta_description();
            if (empty($description)) {
                $description = wp_trim_words(get_the_excerpt($post_id), 25, '');
            }

            $image_id = get_post_thumbnail_id($post_id);
            if ($image_id) {
                $full = wp_get_attachment_image_url($image_id, 'full');
                if ($full) {
                    $image_url = $full;
                    $og['og:image:width']  = (string) get_post_meta($image_id, '_wp_attachment_metadata', true)['width'] ?? '';
                    $og['og:image:height'] = (string) get_post_meta($image_id, '_wp_attachment_metadata', true)['height'] ?? '';
                }
            }
        }

        // Taxonomy / archive pages.
        if (is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $description = wp_trim_words($term->description, 25, '');
            }
        }

        if (is_post_type_archive()) {
            $pt = get_queried_object();
            if ($pt && !empty($pt->description)) {
                $description = wp_trim_words($pt->description, 25, '');
            }
        }

        // Title fallback.
        if (empty($title)) {
            $title = wp_get_document_title();
        }
        $og['og:title'] = $title;

        // Description.
        if (!empty($description)) {
            $og['og:description'] = $description;
        }

        // Image.
        if (!empty($image_url)) {
            $og['og:image'] = esc_url($image_url);
        }

        // Article-specific.
        if (isset($og['og:type']) && 'article' === $og['og:type'] && is_singular()) {
            $post_id = get_the_ID();
            $og['article:published_time'] = get_the_date('c', $post_id);
            $og['article:modified_time']  = get_the_modified_date('c', $post_id);

            // Article author.
            $author_id = get_post_field('post_author', $post_id);
            if ($author_id) {
                $og['article:author'] = get_the_author_meta('display_name', $author_id);
            }
        }

        /**
         * Filter the Open Graph data array before output.
         *
         * @since 1.0.0
         * @param array $og Associative array of OG meta tag properties.
         */
        $og = apply_filters('umdnp_open_graph_data', $og);

        // Output OG tags.
        foreach ($og as $property => $content) {
            if (!empty($content)) {
                echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($content) . '" />' . "\n";
            }
        }

        // Twitter Cards.
        $this->output_twitter_cards($title, $description, $image_url);
    }

    /**
     * Output Twitter Card meta tags.
     *
     * @since  1.0.0
     * @param  string $title       Page title.
     * @param  string $description Meta description.
     * @param  string $image_url   Featured image URL.
     */
    private function output_twitter_cards($title, $description, $image_url) {
        $twitter = array(
            'twitter:card'        => !empty($image_url) ? 'summary_large_image' : 'summary',
            'twitter:title'       => $title,
            'twitter:description' => $description,
            'twitter:image'       => $image_url ?: '',
        );

        /**
         * Filter the Twitter Card data array before output.
         *
         * @since 1.0.0
         * @param array $twitter Associative array of Twitter Card meta properties.
         */
        $twitter = apply_filters('umdnp_twitter_card_data', $twitter);

        foreach ($twitter as $name => $content) {
            if (!empty($content)) {
                echo '<meta name="' . esc_attr($name) . '" content="' . esc_attr($content) . '" />' . "\n";
            }
        }
    }

    // ---------------------------------------------------------------
    // 5. SITEMAP FILTERS (WP 5.5+)
    // ---------------------------------------------------------------

    /**
     * Ensure our CPTs appear in the WordPress core sitemap.
     *
     * @since  1.0.0
     * @param  array $post_types Registered post types for sitemaps.
     * @return array
     */
    public function filter_sitemap_post_types($post_types) {
        // Our CPTs are already public and show_in_rest, so they should
        // appear by default. But ensure our CPTs are included even if
        // another plugin filters them out.
        if (isset($post_types['uc'])) {
            $post_types['uc']->name = 'uc';
        }

        return $post_types;
    }

    /**
     * Ensure our taxonomies appear in the WordPress core sitemap.
     *
     * @since  1.0.0
     * @param  array $taxonomies Registered taxonomies for sitemaps.
     * @return array
     */
    public function filter_sitemap_taxonomies($taxonomies) {
        $our_taxonomies = array(
            'bioma',
            'dificuldade',
            'publico',
            'tipo_atividade',
            'categoria_parceiro',
        );

        foreach ($our_taxonomies as $tax) {
            if (!isset($taxonomies[$tax]) && taxonomy_exists($tax)) {
                $taxonomy_obj = get_taxonomy($tax);
                if ($taxonomy_obj && $taxonomy_obj->public) {
                    $taxonomies[$tax] = $taxonomy_obj;
                }
            }
        }

        return $taxonomies;
    }

    // ---------------------------------------------------------------
    // 6. YOAST SEO INTEGRATION
    // ---------------------------------------------------------------

    /**
     * Filter Yoast SEO title for our CPTs.
     *
     * @since  1.0.0
     * @param  string $title      Yoast-generated title.
     * @param  string $presentation The presentation object or title.
     * @return string
     */
    public function filter_yoast_title($title, $presentation = null) {
        if (!is_singular($this->get_target_post_types())) {
            return $title;
        }

        $post_id = get_the_ID();
        $custom_title = get_post_meta($post_id, '_yoast_wpseo_title', true);

        // If user hasn't set a Yoast title, we can suggest one.
        if (empty($custom_title)) {
            $pt = get_post_type_object(get_post_type($post_id));
            $suggested = get_the_title($post_id) . ' | ' . $pt->labels->singular_name . ' - ' . get_bloginfo('name');

            // Only filter if Yoast doesn't already have a template.
            add_filter('wpseo_title', function ($t) use ($suggested, $title) {
                // If Yoast's default template is still the generic one, use our suggestion.
                return $t;
            }, 20);
        }

        return $title;
    }

    /**
     * Filter Yoast SEO meta description for our CPTs.
     *
     * @since  1.0.0
     * @param  string $metadesc    Yoast-generated meta description.
     * @param  string $presentation The presentation object or description.
     * @return string
     */
    public function filter_yoast_metadesc($metadesc, $presentation = null) {
        if (!is_singular($this->get_target_post_types())) {
            return $metadesc;
        }

        $post_id = get_the_ID();
        $custom_desc = get_post_meta($post_id, self::META_DESC_KEY, true);

        // Use our custom meta description as fallback for Yoast.
        if (empty($metadesc) && !empty($custom_desc)) {
            $metadesc = $custom_desc;
        }

        return $metadesc;
    }

    /**
     * Extend Yoast SEO schema graph with our Park/Event data.
     *
     * Yoast already outputs its own schema. We can add supplementary
     * data that Yoast might not include.
     *
     * @since  1.0.0
     * @param  array  $graphs  Existing schema graph pieces.
     * @param  object $context Yoast schema context.
     * @return array
     */
    public function extend_yoast_schema($graphs, $context) {
        if (!is_singular('uc')) {
            return $graphs;
        }

        $post_id = get_the_ID();

        if (is_singular('uc')) {
            $park = $this->build_park_schema($post_id);
            if ($park) {
                // Add park-specific data that Yoast's Place schema might miss.
                $graphs[] = $park;
            }
        }

        return $graphs;
    }

    // ---------------------------------------------------------------
    // 7. RANK MATH INTEGRATION
    // ---------------------------------------------------------------

    /**
     * Filter Rank Math title for our CPTs.
     *
     * @since  1.0.0
     * @param  string $title Rank Math title.
     * @return string
     */
    public function filter_rankmath_title($title) {
        if (!is_singular($this->get_target_post_types())) {
            return $title;
        }

        $post_id = get_the_ID();
        $custom_title = get_post_meta($post_id, 'rank_math_title', true);

        // Fallback to our meta description if Rank Math has no custom title.
        if (empty($custom_title)) {
            $pt = get_post_type_object(get_post_type($post_id));
            $suggested = get_the_title($post_id) . ' | ' . $pt->labels->singular_name;
            // Rank Math has its own title templates, so only intervene if empty.
            if (empty($title)) {
                $title = $suggested;
            }
        }

        return $title;
    }

    /**
     * Filter Rank Math description for our CPTs.
     *
     * @since  1.0.0
     * @param  string $description Rank Math description.
     * @return string
     */
    public function filter_rankmath_description($description) {
        if (!is_singular($this->get_target_post_types())) {
            return $description;
        }

        $post_id = get_the_ID();
        $custom_desc = get_post_meta($post_id, self::META_DESC_KEY, true);

        // Use our meta description as fallback.
        if (empty($description) && !empty($custom_desc)) {
            $description = $custom_desc;
        }

        return $description;
    }

    // ---------------------------------------------------------------
    // 8. HELPERS
    // ---------------------------------------------------------------

    /**
     * Get the post types targeted by SEO features.
     *
     * @since  1.0.0
     * @return array
     */
    private function get_target_post_types() {
        /**
         * Filter the list of post types that get SEO features.
         *
         * @since 1.0.0
         * @param array $post_types Post type slugs.
         */
        return apply_filters('umdnp_seo_post_types', array('uc', 'atividade', 'depoimento', 'parceiro'));
    }
}
