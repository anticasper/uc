<?php
/**
 * Base test case for unit tests (no WordPress).
 *
 * Provides mocks for WordPress functions used by the plugin.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * Mock for WP_Error.
 */
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code    = '';
        private $message = '';
        private $data    = '';

        public function __construct($code = '', $message = '', $data = '') {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

/**
 * Mock for WP_Query.
 */
if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = array();
        public function have_posts() { return false; }
        public function the_post() {}
        public function get($var) { return null; }
    }
}

/**
 * Base test case with helpers.
 */
abstract class UMDNP_UnitTestCase extends PHPUnit\Framework\TestCase {

    /**
     * Get a temp directory for log files.
     */
    protected function get_temp_dir(): string {
        $dir = sys_get_temp_dir() . '/umdnp-tests-' . uniqid();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Clean up a temp directory.
     */
    protected function remove_temp_dir(string $dir): void {
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*'));
            rmdir($dir);
        }
    }

    /**
     * Call a private/protected method via reflection.
     */
    protected function call_private_method($object, string $method, array $args = array()) {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    /**
     * Get a private/protected property via reflection.
     */
    protected function get_private_property($object, string $property) {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }

    /**
     * Set a private/protected property via reflection.
     */
    protected function set_private_property($object, string $property, $value): void {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
