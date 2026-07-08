<?php
/**
 * Developer Information for the plugin.
 *
 * Adds an admin page with developer/agency details, version info,
 * and useful links — plus a quick-access link on the Plugins screen.
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
 * Developer Information class.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */
class Um_Dia_No_Parque_Developer_Info {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $plugin_name The plugin slug.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string $version The current version.
	 */
	protected $version;

	/**
	 * Developer data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array $developer Developer information.
	 */
	protected $developer;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if (defined('UM_DIA_NO_PARQUE_VERSION')) {
			$this->version = UM_DIA_NO_PARQUE_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$this->plugin_name = 'um-dia-no-parque';

		// Developer data uses plain strings (no __()) to avoid
		// _load_textdomain_just_in_time notices in WP 6.7+.
		// Translations are applied lazily via get_developer_data().
		$this->developer = $this->get_raw_developer_data();

		$this->register_hooks();
	}

	/**
	 * Raw developer data (untranslated — strings are safe placeholders).
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array
	 */
	private function get_raw_developer_data() {
		return array(
			'name'          => 'Sua Agência ou Nome',
			'tagline'       => 'Desenvolvimento de soluções web sob medida.',
			'website'       => 'https://seusite.com',
			'email'         => 'contato@seusite.com',
			'support_email' => 'suporte@seusite.com',
			'phone'         => '',
			'logo'          => '',
			'social'        => array(
				'github'   => 'https://github.com/seu-usuario',
				'linkedin' => '',
				'twitter'  => '',
			),
			'skills'        => array(
				'WordPress',
				'PHP',
				'Elementor',
				'Front-end',
				'UX Design',
			),
			'credits'       => array(
				array(
					'role' => 'Desenvolvimento',
					'name' => 'Equipe de Desenvolvimento',
				),
				array(
					'role' => 'Design',
					'name' => 'Equipe de Design',
				),
			),
		);
	}

	/**
	 * Get translated developer data for display.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array
	 */
	private function get_developer_data() {
		$raw = $this->developer;

		return array(
			'name'          => __($raw['name'], 'um-dia-no-parque'),
			'tagline'       => __($raw['tagline'], 'um-dia-no-parque'),
			'website'       => $raw['website'],
			'email'         => $raw['email'],
			'support_email' => $raw['support_email'],
			'phone'         => $raw['phone'],
			'logo'          => $raw['logo'],
			'social'        => $raw['social'],
			'skills'        => array_map(function ($s) {
				return __($s, 'um-dia-no-parque');
			}, $raw['skills']),
			'credits'       => array_map(function ($c) {
				return array(
					'role' => __($c['role'], 'um-dia-no-parque'),
					'name' => __($c['name'], 'um-dia-no-parque'),
				);
			}, $raw['credits']),
		);
	}

	/**
	 * Register all hooks for the developer info.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function register_hooks() {
	    // Add link in the plugins list.
	    add_filter(
	        'plugin_action_links_' . plugin_basename(UM_DIA_NO_PARQUE_PLUGIN_DIR . 'um-dia-no-parque.php'),
	        array($this, 'add_plugin_action_links')
	    );

	    // Register the about page under the UC menu.
	    add_action('admin_menu', array($this, 'register_about_page'));

	    // Enqueue admin styles for the about page.
	    add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
	}

	/**
	 * Register the "Sobre / Informações do Desenvolvedor" submenu page.
	 *
	 * @since 1.8.0
	 * @access public
	 * @return void
	 */
	public function register_about_page() {
		add_submenu_page(
			'um-dia-no-parque',
			esc_html__('Informações do Desenvolvedor', 'um-dia-no-parque'),
			esc_html__('Sobre', 'um-dia-no-parque'),
			'manage_options',
			'um-dia-no-parque-about',
			array($this, 'render_about_page')
		);
	}

	/**
	 * Add a "Sobre" link in the plugin action links.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function add_plugin_action_links($links) {
		$about_link = sprintf(
			'<a href="%s" style="color:#006600;font-weight:500;">%s</a>',
			esc_url(admin_url('admin.php?page=um-dia-no-parque-about')),
			esc_html__('Informações do Desenvolvedor', 'um-dia-no-parque')
		);

		array_unshift($links, $about_link);
		return $links;
	}

	/**
	 * Enqueue styles for the about page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles($hook) {
		if ('um-dia-no-parque_page_um-dia-no-parque-about' !== $hook) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name . '-admin',
			UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/im-dia-no-parque-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Render the "Sobre / Informações do Desenvolvedor" page.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function render_about_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'um-dia-no-parque'));
		}

		$dev = $this->get_developer_data();
		$plugin_data = $this->get_plugin_data();
		?>
		<div class="wrap umdnp-about-wrap">
			<h1><?php echo esc_html__('Informações do Desenvolvedor', 'um-dia-no-parque'); ?></h1>

			<div class="umdnp-about-content">
				<!-- Plugin Info Card -->
				<div class="umdnp-about-card umdnp-about-card--plugin">
					<h2><?php echo esc_html__('Sobre o Plugin', 'um-dia-no-parque'); ?></h2>
					<table class="umdnp-about-table">
						<tbody>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('Plugin', 'um-dia-no-parque'); ?></td>
								<td><strong><?php echo esc_html($plugin_data['name']); ?></strong></td>
							</tr>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('Versão', 'um-dia-no-parque'); ?></td>
								<td><span class="umdnp-badge"><?php echo esc_html($plugin_data['version']); ?></span></td>
							</tr>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('Descrição', 'um-dia-no-parque'); ?></td>
								<td><?php echo esc_html($plugin_data['description']); ?></td>
							</tr>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('WordPress', 'um-dia-no-parque'); ?></td>
								<td><?php echo esc_html($plugin_data['wp_min']); ?>+</td>
							</tr>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('PHP', 'um-dia-no-parque'); ?></td>
								<td><?php echo esc_html($plugin_data['php_min']); ?>+</td>
							</tr>
							<tr>
								<td class="umdnp-label"><?php esc_html_e('Licença', 'um-dia-no-parque'); ?></td>
								<td><?php echo esc_html($plugin_data['license']); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Developer / Agency Info Card -->
				<div class="umdnp-about-card umdnp-about-card--developer">
					<h2><?php echo esc_html__('Desenvolvedor', 'um-dia-no-parque'); ?></h2>

					<?php if (!empty($dev['logo'])) : ?>
						<div class="umdnp-dev-logo">
							<img src="<?php echo esc_url($dev['logo']); ?>" alt="<?php echo esc_attr($dev['name']); ?>" />
						</div>
					<?php endif; ?>

					<h3 class="umdnp-dev-name"><?php echo esc_html($dev['name']); ?></h3>

					<?php if (!empty($dev['tagline'])) : ?>
						<p class="umdnp-dev-tagline"><?php echo esc_html($dev['tagline']); ?></p>
					<?php endif; ?>

					<ul class="umdnp-dev-contacts">
						<?php if (!empty($dev['website'])) : ?>
							<li>
								<span class="dashicons dashicons-admin-home"></span>
								<a href="<?php echo esc_url($dev['website']); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html($dev['website']); ?>
								</a>
							</li>
						<?php endif; ?>

						<?php if (!empty($dev['email'])) : ?>
							<li>
								<span class="dashicons dashicons-email"></span>
								<a href="mailto:<?php echo esc_attr($dev['email']); ?>">
									<?php echo esc_html($dev['email']); ?>
								</a>
							</li>
						<?php endif; ?>

						<?php if (!empty($dev['phone'])) : ?>
							<li>
								<span class="dashicons dashicons-phone"></span>
								<?php echo esc_html($dev['phone']); ?>
							</li>
						<?php endif; ?>
					</ul>

					<?php if (!empty($dev['social'])) : ?>
						<div class="umdnp-dev-social">
							<h4><?php esc_html_e('Redes Sociais', 'um-dia-no-parque'); ?></h4>
							<ul>
								<?php foreach ($dev['social'] as $network => $url) : ?>
									<?php if (!empty($url)) : ?>
										<li>
											<a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html(ucfirst($network)); ?>
											</a>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>

				<!-- Support & Links Card -->
				<div class="umdnp-about-card umdnp-about-card--links">
					<h2><?php echo esc_html__('Links Úteis', 'um-dia-no-parque'); ?></h2>
					<ul class="umdnp-links-list">
						<li>
							<a href="<?php echo esc_url($plugin_data['plugin_uri']); ?>" target="_blank" rel="noopener noreferrer">
								<span class="dashicons dashicons-admin-links"></span>
								<?php esc_html_e('Site do Plugin', 'um-dia-no-parque'); ?>
							</a>
						</li>
						<?php if (!empty($dev['support_email'])) : ?>
							<li>
								<a href="mailto:<?php echo esc_attr($dev['support_email']); ?>">
									<span class="dashicons dashicons-sos"></span>
									<?php esc_html_e('Suporte por E-mail', 'um-dia-no-parque'); ?>
								</a>
							</li>
						<?php endif; ?>
						<?php if (!empty($dev['social']['github'])) : ?>
							<li>
								<a href="<?php echo esc_url($dev['social']['github']); ?>" target="_blank" rel="noopener noreferrer">
									<span class="dashicons dashicons-code-standards"></span>
									<?php esc_html_e('Repositório no GitHub', 'um-dia-no-parque'); ?>
								</a>
							</li>
						<?php endif; ?>
						<li>
							<a href="<?php echo esc_url(admin_url('edit.php?post_type=uc')); ?>">
								<span class="dashicons dashicons-admin-post"></span>
								<?php esc_html_e('Gerenciar UCs', 'um-dia-no-parque'); ?>
							</a>
						</li>
					</ul>
				</div>

				<!-- Skills Card -->
				<?php if (!empty($dev['skills'])) : ?>
					<div class="umdnp-about-card umdnp-about-card--skills">
						<h2><?php echo esc_html__('Especialidades', 'um-dia-no-parque'); ?></h2>
						<div class="umdnp-skills-grid">
							<?php foreach ($dev['skills'] as $skill) : ?>
								<span class="umdnp-skill-tag"><?php echo esc_html($skill); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Credits Card -->
				<?php if (!empty($dev['credits'])) : ?>
					<div class="umdnp-about-card umdnp-about-card--credits">
						<h2><?php echo esc_html__('Créditos', 'um-dia-no-parque'); ?></h2>
						<table class="umdnp-about-table">
							<tbody>
								<?php foreach ($dev['credits'] as $credit) : ?>
									<tr>
										<td class="umdnp-label"><?php echo esc_html($credit['role']); ?></td>
										<td><?php echo esc_html($credit['name']); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<!-- System Info Card -->
				<div class="umdnp-about-card umdnp-about-card--system">
					<h2>
						<?php esc_html_e('Informações do Sistema', 'um-dia-no-parque'); ?>
						<button type="button" class="umdnp-toggle-btn" aria-expanded="false" data-target="umdnp-sysinfo">
							<span class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
					</h2>
					<div id="umdnp-sysinfo" class="umdnp-toggle-content" style="display:none;">
						<?php $this->render_system_info(); ?>
					</div>
				</div>
			</div>

			<div class="umdnp-about-footer">
				<p>
					<?php
					printf(
						/* translators: %s: Plugin version */
						esc_html__('Um Dia No Parque • Versão %s', 'um-dia-no-parque'),
						esc_html($plugin_data['version'])
					);
					?>
					&mdash;
					<?php esc_html_e('Feito com ♥ para WordPress.', 'um-dia-no-parque'); ?>
				</p>
			</div>
		</div>
		<?php
		// Inline JS for the toggle.
		$this->render_toggle_script();
	}

	/**
	 * Render system information table.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_system_info() {
		global $wpdb;
		$wp_version = get_bloginfo('version');
		$php_version = phpversion();
		$mysql_version = $wpdb->db_version();
		$server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
		$active_theme = wp_get_theme()->get('Name');
		$multisite = is_multisite() ? __('Sim', 'um-dia-no-parque') : __('Não', 'um-dia-no-parque');
		$debug_mode = (defined('WP_DEBUG') && WP_DEBUG) ? __('Ativado', 'um-dia-no-parque') : __('Desativado', 'um-dia-no-parque');
		$memory_limit = WP_MEMORY_LIMIT;
		$max_execution_time = ini_get('max_execution_time');
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size = ini_get('post_max_size');

		// Get active plugins list.
		$active_plugins = get_option('active_plugins');
		$plugins_list = array();
		if (is_array($active_plugins)) {
			foreach ($active_plugins as $plugin_path) {
				$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
				if (!empty($plugin_data['Name'])) {
					$plugins_list[] = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
				}
			}
		}
		?>
		<table class="umdnp-about-table widefat">
			<tbody>
				<tr><td class="umdnp-label"><?php esc_html_e('Versão do WordPress', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($wp_version); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Versão do PHP', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($php_version); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Versão do MySQL', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($mysql_version); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Servidor Web', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($server_software); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Tema Ativo', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($active_theme); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Multisite', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($multisite); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('WP_DEBUG', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($debug_mode); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Limite de Memória WP', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($memory_limit); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Max Execution Time', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($max_execution_time); ?>s</td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Upload Max', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($upload_max_filesize); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Post Max Size', 'um-dia-no-parque'); ?></td><td><?php echo esc_html($post_max_size); ?></td></tr>
				<tr><td class="umdnp-label"><?php esc_html_e('Plugins Ativos', 'um-dia-no-parque'); ?></td><td><pre class="umdnp-sysinfo-plugins"><?php echo esc_textarea(implode("\n", $plugins_list)); ?></pre></td></tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the toggle JavaScript.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function render_toggle_script() {
		?>
		<script>
		jQuery(document).ready(function($) {
			$('.umdnp-toggle-btn').on('click', function() {
				var target = $('#' + $(this).data('target'));
				var expanded = 'true' === $(this).attr('aria-expanded');
				$(this).attr('aria-expanded', !expanded);
				target.slideToggle(200);
			});
		});
		</script>
		<?php
	}

	/**
	 * Get plugin header data.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Plugin data.
	 */
	private function get_plugin_data() {
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data(UM_DIA_NO_PARQUE_PLUGIN_DIR . 'um-dia-no-parque.php', false, false);

		return array(
			'name'        => $data['Name'],
			'version'     => $data['Version'],
			'description' => $data['Description'],
			'author'      => $data['Author'],
			'author_uri'  => $data['AuthorURI'],
			'plugin_uri'  => $data['PluginURI'],
			'license'     => isset($data['License']) ? $data['License'] : '',
			'wp_min'      => !empty($data['RequiresWP']) ? $data['RequiresWP'] : '5.0',
			'php_min'     => !empty($data['RequiresPHP']) ? $data['RequiresPHP'] : '7.0',
		);
	}
}
