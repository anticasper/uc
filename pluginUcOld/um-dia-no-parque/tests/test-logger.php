<?php
/**
 * Tests for the Logger class.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * Logger test case.
 */
class LoggerTest extends UMDNP_UnitTestCase {

    /**
     * @var string Temp dir for log files.
     */
    private $temp_dir = '';

    /**
     * @var string Original log file path.
     */
    private $orig_log_file = '';

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->temp_dir = $this->get_temp_dir();

        // Load the logger.
        require_once dirname(__DIR__) . '/includes/class-um-dia-no-parque-logger.php';

        // Redirect log file to temp dir.
        $this->orig_log_file = $this->get_private_property('Um_Dia_No_Parque_Logger', 'log_file');
        $this->set_private_property('Um_Dia_No_Parque_Logger', 'log_file', $this->temp_dir . '/plugin.log');

        // Enable logging for tests.
        update_option('um_dia_no_parque_settings', array(
            'enable_logging' => 'yes',
            'log_level'      => Um_Dia_No_Parque_Logger::DEBUG,
        ));
    }

    /**
     * Tear down after each test.
     */
    protected function tearDown(): void {
        $this->set_private_property('Um_Dia_No_Parque_Logger', 'log_file', $this->orig_log_file);
        $this->remove_temp_dir($this->temp_dir);
        delete_option('um_dia_no_parque_settings');
        parent::tearDown();
    }

    /**
     * Test logging at different levels.
     */
    public function test_log_levels(): void {
        $this->assertTrue(Um_Dia_No_Parque_Logger::debug('Debug message'));
        $this->assertTrue(Um_Dia_No_Parque_Logger::info('Info message'));
        $this->assertTrue(Um_Dia_No_Parque_Logger::warning('Warning message'));
        $this->assertTrue(Um_Dia_No_Parque_Logger::error('Error message'));

        // Read the log file.
        $content = file_get_contents($this->temp_dir . '/plugin.log');
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('DEBUG: Debug message', $content);
        $this->assertStringContainsString('INFO: Info message', $content);
        $this->assertStringContainsString('WARNING: Warning message', $content);
        $this->assertStringContainsString('ERROR: Error message', $content);
    }

    /**
     * Test logging with context data.
     */
    public function test_log_with_context(): void {
        Um_Dia_No_Parque_Logger::info('User action', array(
            'user_id' => 42,
            'action'  => 'test',
        ));

        $content = file_get_contents($this->temp_dir . '/plugin.log');
        $this->assertStringContainsString('INFO: User action', $content);
        $this->assertStringContainsString('"user_id":42', $content);
        $this->assertStringContainsString('"action":"test"', $content);
    }

    /**
     * Test log level filtering.
     */
    public function test_log_level_filtering(): void {
        // Set minimum level to WARNING.
        update_option('um_dia_no_parque_settings', array(
            'enable_logging' => 'yes',
            'log_level'      => Um_Dia_No_Parque_Logger::WARNING,
        ));

        Um_Dia_No_Parque_Logger::debug('Should not appear');
        Um_Dia_No_Parque_Logger::info('Should not appear');
        Um_Dia_No_Parque_Logger::warning('Should appear warning');
        Um_Dia_No_Parque_Logger::error('Should appear error');

        $content = file_get_contents($this->temp_dir . '/plugin.log');
        $this->assertStringNotContainsString('Should not appear', $content);
        $this->assertStringContainsString('WARNING: Should appear warning', $content);
        $this->assertStringContainsString('ERROR: Should appear error', $content);
    }

    /**
     * Test logging disabled.
     */
    public function test_log_disabled(): void {
        update_option('um_dia_no_parque_settings', array(
            'enable_logging' => 'no',
            'log_level'      => Um_Dia_No_Parque_Logger::DEBUG,
        ));

        $this->assertFalse(Um_Dia_No_Parque_Logger::info('Should not appear'));
        $this->assertFileDoesNotExist($this->temp_dir . '/plugin.log');
    }

    /**
     * Test log rotation when exceeding max size.
     */
    public function test_log_rotation(): void {
        // Set max file size to 100 bytes.
        $this->set_private_property('Um_Dia_No_Parque_Logger', 'max_file_size', 100);

        // Write enough to trigger rotation.
        for ($i = 0; $i < 20; $i++) {
            Um_Dia_No_Parque_Logger::info('Test message ' . $i);
        }

        // Check that rotated files exist.
        $files = glob($this->temp_dir . '/plugin-*.log');
        $this->assertNotEmpty($files, 'Rotated log files should exist');
    }

    /**
     * Test clear logs.
     */
    public function test_clear_logs(): void {
        Um_Dia_No_Parque_Logger::info('Test message');
        $this->assertFileExists($this->temp_dir . '/plugin.log');

        Um_Dia_No_Parque_Logger::clear_logs();
        $content = file_get_contents($this->temp_dir . '/plugin.log');
        $this->assertEmpty(trim($content));
    }

    /**
     * Test get entries.
     */
    public function test_get_entries(): void {
        Um_Dia_No_Parque_Logger::info('First message', array('order' => 1));
        Um_Dia_No_Parque_Logger::warning('Second message', array('order' => 2));
        Um_Dia_No_Parque_Logger::error('Third message', array('order' => 3));

        $entries = Um_Dia_No_Parque_Logger::get_entries();
        $this->assertCount(3, $entries);

        // Newest first.
        $this->assertEquals('Third message', $entries[0]['message']);
        $this->assertEquals('First message', $entries[2]['message']);

        // Filter by level (WARNING+).
        $warnings = Um_Dia_No_Parque_Logger::get_entries(Um_Dia_No_Parque_Logger::WARNING);
        $this->assertCount(2, $warnings);

        // Search.
        $search = Um_Dia_No_Parque_Logger::get_entries(0, 100, 0, 'Second');
        $this->assertCount(1, $search);
    }

    /**
     * Test get file size.
     */
    public function test_get_file_size(): void {
        $size = Um_Dia_No_Parque_Logger::get_file_size();
        $this->assertEquals('0 B', $size);

        Um_Dia_No_Parque_Logger::info('A');
        $size = Um_Dia_No_Parque_Logger::get_file_size();
        $this->assertStringEndsWith('B', $size);
    }

    /**
     * Test get entry count.
     */
    public function test_get_entry_count(): void {
        $this->assertEquals(0, Um_Dia_No_Parque_Logger::get_entry_count());
        Um_Dia_No_Parque_Logger::info('A');
        $this->assertEquals(1, Um_Dia_No_Parque_Logger::get_entry_count());
        Um_Dia_No_Parque_Logger::info('B');
        $this->assertEquals(2, Um_Dia_No_Parque_Logger::get_entry_count());
    }
}
