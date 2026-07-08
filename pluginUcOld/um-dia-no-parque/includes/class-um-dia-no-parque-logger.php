<?php
/**
 * Logger System for Um Dia No Parque
 *
 * Provides structured logging with levels, auto-rotation,
 * and an admin viewer for debugging and monitoring.
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
 * Logger class.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_Logger {

    /**
     * Log level constants.
     */
    const DEBUG   = 0;
    const INFO    = 1;
    const WARNING = 2;
    const ERROR   = 3;
    const OFF     = 4;

    /**
     * Log level labels.
     *
     * @since  1.0.0
     * @access private
     * @var    array
     */
    private static $level_labels = array(
        self::DEBUG   => 'DEBUG',
        self::INFO    => 'INFO',
        self::WARNING => 'WARNING',
        self::ERROR   => 'ERROR',
    );

    /**
     * Log file path.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private static $log_file = '';

    /**
     * Maximum log file size in bytes (1 MB).
     *
     * @since  1.0.0
     * @access private
     * @var    int
     */
    private static $max_file_size = 1048576;

    /**
     * Maximum number of log entries to keep in the viewer.
     *
     * @since  1.0.0
     * @access private
     * @var    int
     */
    private static $max_entries = 1000;

    /**
     * Instance of this class (singleton).
     *
     * @since  1.0.0
     * @access private
     * @var    Um_Dia_No_Parque_Logger|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return Um_Dia_No_Parque_Logger
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
     * Constructor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $upload_dir = wp_get_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/umdnp-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Protect the log directory.
        $htaccess_path = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n"); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file_put_contents
        }

        $index_path = $log_dir . '/index.php';
        if (!file_exists($index_path)) {
            file_put_contents($index_path, "<?php // Silence is golden.\n"); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file_put_contents
        }

        self::$log_file = $log_dir . '/plugin.log';

        // Schedule cleanup hook.
        if (!wp_next_scheduled('umdnp_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'umdnp_log_cleanup');
        }
        add_action('umdnp_log_cleanup', array($this, 'rotate_logs'));
    }

    // ================================================================
    // Public Logging Methods
    // ================================================================

    /**
     * Log a debug message.
     *
     * @since  1.0.0
     * @param  string $message  Log message.
     * @param  array  $context  Optional contextual data.
     * @return bool
     */
    public static function debug($message, array $context = array()) {
        return self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @since  1.0.0
     * @param  string $message  Log message.
     * @param  array  $context  Optional contextual data.
     * @return bool
     */
    public static function info($message, array $context = array()) {
        return self::log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @since  1.0.0
     * @param  string $message  Log message.
     * @param  array  $context  Optional contextual data.
     * @return bool
     */
    public static function warning($message, array $context = array()) {
        return self::log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @since  1.0.0
     * @param  string $message  Log message.
     * @param  array  $context  Optional contextual data.
     * @return bool
     */
    public static function error($message, array $context = array()) {
        return self::log(self::ERROR, $message, $context);
    }

    /**
     * Write a log entry.
     *
     * @since  1.0.0
     * @param  int    $level    Log level (DEBUG, INFO, WARNING, ERROR).
     * @param  string $message  Log message.
     * @param  array  $context  Optional contextual data.
     * @return bool
     */
    public static function log($level, $message, array $context = array()) {
        // Check if logging is enabled.
        $settings = get_option('um_dia_no_parque_settings', array());
        $enabled  = isset($settings['enable_logging']) ? $settings['enable_logging'] : 'yes';
        if ('yes' !== $enabled) {
            return false;
        }

        // Check minimum log level.
        $min_level = isset($settings['log_level']) ? intval($settings['log_level']) : self::INFO;
        if ($level < $min_level) {
            return false;
        }

        if (empty(self::$log_file)) {
            self::get_instance();
        }

        $level_label = isset(self::$level_labels[$level]) ? self::$level_labels[$level] : 'UNKNOWN';

        // Build the log entry.
        $entry = array(
            'timestamp' => current_time('c'),
            'level'     => $level_label,
            'level_code' => $level,
            'message'   => $message,
            'context'   => $context,
            'host'      => isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '',
            'uri'       => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            'method'    => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
        );

        // Format for file: [TIMESTAMP] LEVEL: message {"context":...}
        $line = sprintf(
            "[%s] %s: %s %s\n",
            $entry['timestamp'],
            $entry['level'],
            $message,
            !empty($context) ? wp_json_encode($context) : ''
        );

        $written = file_put_contents(self::$log_file, $line, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file_put_contents

        // Check file size and rotate if needed.
        if ($written && filesize(self::$log_file) > self::$max_file_size) {
            self::rotate_logs();
        }

        // Also send to WP debug log if WP_DEBUG_LOG is enabled.
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[UMDNP][' . $entry['level'] . '] ' . $message . (!empty($context) ? ' ' . wp_json_encode($context) : ''));
        }

        return (bool) $written;
    }

    // ================================================================
    // Log Management
    // ================================================================

    /**
     * Rotate log files when they exceed maximum size.
     *
     * @since 1.0.0
     */
    public static function rotate_logs() {
        if (empty(self::$log_file)) {
            self::get_instance();
        }

        if (!file_exists(self::$log_file)) {
            return;
        }

        $max_age_days = apply_filters('umdnp_log_max_age_days', 30);
        $log_dir      = dirname(self::$log_file);

        // Rotate current log.
        $rotated = $log_dir . '/plugin-' . date('Y-m-d-His') . '.log';
        rename(self::$log_file, $rotated);

        // Start fresh log.
        file_put_contents(self::$log_file, ''); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file_put_contents

        // Delete rotated logs older than max_age_days.
        $files = glob($log_dir . '/plugin-*.log');
        if ($files) {
            $cutoff = strtotime('-' . $max_age_days . ' days');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Clear all log files.
     *
     * @since 1.0.0
     */
    public static function clear_logs() {
        if (empty(self::$log_file)) {
            self::get_instance();
        }

        $log_dir = dirname(self::$log_file);
        $files   = glob($log_dir . '/plugin*.log');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        // Create empty log file.
        file_put_contents(self::$log_file, ''); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file_put_contents
    }

    /**
     * Get log entries for the viewer.
     *
     * @since  1.0.0
     * @param  int    $min_level Minimum log level to show.
     * @param  int    $limit     Maximum entries.
     * @param  int    $offset    Start offset.
     * @param  string $search    Optional search term.
     * @return array Array of log entries.
     */
    public static function get_entries($min_level = 0, $limit = 100, $offset = 0, $search = '') {
        if (empty(self::$log_file)) {
            self::get_instance();
        }

        if (!file_exists(self::$log_file)) {
            return array();
        }

        // Read log file (last N bytes).
        $handle = fopen(self::$log_file, 'r'); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_fopen
        if (!$handle) {
            return array();
        }

        $entries = array();
        $buffer  = '';

        // Read from the end to get the latest entries.
        $file_size = filesize(self::$log_file);
        if ($file_size > 0) {
            $read_size = min($file_size, 51200);
            fseek($handle, -$read_size, SEEK_END);
            $buffer .= fread($handle, $read_size); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_fread
        }
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_fclose

        $lines = explode("\n", $buffer);
        $lines = array_filter($lines);

        foreach ($lines as $line) {
            $entry = self::parse_line($line);
            if (!$entry) {
                continue;
            }

            // Filter by level.
            if ($entry['level_code'] < $min_level) {
                continue;
            }

            // Filter by search term.
            if (!empty($search) && false === stripos($entry['message'], $search) && false === stripos($entry['raw'], $search)) {
                continue;
            }

            $entries[] = $entry;
        }

        // Reverse so newest is first.
        $entries = array_reverse($entries);

        // Apply pagination.
        return array_slice($entries, $offset, $limit);
    }

    /**
     * Get total log entry count.
     *
     * @since  1.0.0
     * @return int
     */
    public static function get_entry_count() {
        if (empty(self::$log_file)) {
            self::get_instance();
        }

        if (!file_exists(self::$log_file)) {
            return 0;
        }

        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // phpcs:ignore WordPress.WP.AlternativeTypes.file_system_operations_file
        return $lines ? count($lines) : 0;
    }

    /**
     * Get log file size in a human-readable format.
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_file_size() {
        if (empty(self::$log_file)) {
            self::get_instance();
        }

        if (!file_exists(self::$log_file)) {
            return '0 B';
        }

        $size = filesize(self::$log_file);
        return size_format($size, 2);
    }

    // ================================================================
    // Internal Helpers
    // ================================================================

    /**
     * Parse a log line into a structured entry.
     *
     * @since  1.0.0
     * @param  string $line Raw log line.
     * @return array|false Parsed entry or false on failure.
     */
    private static function parse_line($line) {
        $line = trim($line);
        if (empty($line)) {
            return false;
        }

        // Format: [2026-06-25T10:30:00+00:00] LEVEL: message {"context":...}
        $pattern = '/^\[([^\]]+)\]\s+(\w+):\s+(.+)$/';
        if (!preg_match($pattern, $line, $matches)) {
            return array(
                'raw'        => $line,
                'timestamp'  => '',
                'level'      => 'UNKNOWN',
                'level_code' => 0,
                'message'    => $line,
                'context'    => array(),
            );
        }

        $timestamp = $matches[1];
        $level_str = $matches[2];
        $rest      = $matches[3];

        // Extract JSON context if present.
        $context = array();
        $message = $rest;
        $json_pos = strrpos($rest, '{"');
        if (false !== $json_pos) {
            $json_part = substr($rest, $json_pos);
            $decoded   = json_decode($json_part, true);
            if (is_array($decoded)) {
                $context = $decoded;
                $message = trim(substr($rest, 0, $json_pos));
            }
        }

        // Map level string to code.
        $labels_flipped = array_flip(self::$level_labels);
        $level_code     = isset($labels_flipped[$level_str]) ? $labels_flipped[$level_str] : self::INFO;

        return array(
            'raw'        => $line,
            'timestamp'  => $timestamp,
            'level'      => $level_str,
            'level_code' => $level_code,
            'message'    => $message,
            'context'    => $context,
        );
    }
}
