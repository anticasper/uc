<?php
/**
 * Abstract base class for all Um Dia No Parque custom post types.
 *
 * Provides singleton boilerplate, common register_post_type args,
 * and standardized hook registration.
 *
 * @since      1.9.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Um_Dia_No_Parque_Post_Type_Base {

    private static $instances = array();

    final public static function get_instance() {
        $class = get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
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

    abstract protected function get_post_type();

    abstract protected function get_labels();

    abstract protected function get_post_type_args();

    protected function get_common_args() {
        return array(
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
        );
    }

    protected function register_post_type() {
        $args = array_merge(
            $this->get_common_args(),
            array('labels' => $this->get_labels()),
            $this->get_post_type_args()
        );
        register_post_type($this->get_post_type(), $args);
    }

    public function save_meta_boxes($post_id, $post) {
        if (!$this->verify_nonce($post_id)) {
            return;
        }
        $this->save_meta_data($post_id, $post);
    }

    protected function verify_nonce($post_id) {
        $nonce_field = $this->get_post_type() . '_nonce';
        $nonce_action = $this->get_post_type() . '_save';
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce(sanitize_key($_POST[$nonce_field]), $nonce_action)) {
            return false;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }
        return true;
    }

    abstract protected function save_meta_data($post_id, $post);
}
