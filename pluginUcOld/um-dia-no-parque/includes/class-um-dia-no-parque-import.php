<?php
/**
 * Import system for Um Dia No Parque.
 *
 * Handles CSV/XLSX upload, parsing, and batch import of UCs and
 * activities into Custom Post Types — all server-side, zero Node.js.
 *
 * @since      1.9.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core import class.
 *
 * AJAX handlers:
 *   - wp_ajax_umdnp_import_upload  → parse file, store data in wp_option
 *   - wp_ajax_umdnp_import_process → batch-insert posts
 *   - wp_ajax_umdnp_import_cleanup → remove temp data
 *
 * @since 1.9.0
 */
class Um_Dia_No_Parque_Import {

    /**
     * Singleton instance.
     *
     * @since  1.9.0
     * @var    self|null
     */
    private static $instance = null;

    /**
     * Option name where parsed import metadata is stored.
     *
     * @since  1.9.0
     * @var    string
     */
    const IMPORT_OPTION = 'umdnp_import_data';

    /**
     * How many rows to process per AJAX batch.
     *
     * @since  1.9.0
     * @var    int
     */
    const BATCH_SIZE = 25;

    /**
     * Nonce action string.
     *
     * @since  1.9.0
     * @var    string
     */
    const NONCE_ACTION = 'umdnp_import_nonce';

    /**
     * Get singleton instance.
     *
     * @since  1.9.0
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     *
     * @throws \Exception Always throws.
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Constructor — register AJAX hooks (admin-only).
     *
     * @since 1.9.0
     */
    private function __construct() {
        add_action('wp_ajax_umdnp_import_upload', array($this, 'ajax_upload'));
        add_action('wp_ajax_umdnp_import_process', array($this, 'ajax_process'));
        add_action('wp_ajax_umdnp_import_cleanup', array($this, 'ajax_cleanup'));
    }

    // ============================================================
    // NONCE VERIFICATION
    // ============================================================

    /**
     * Verify the AJAX nonce and user capability.
     *
     * @since  1.9.0
     * @return bool True if valid.
     */
    private function verify_request(): bool {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), self::NONCE_ACTION)) {
            return false;
        }
        if (!apply_filters('um_dia_no_parque_import_verify_capability', current_user_can('manage_options'))) {
            return false;
        }
        return true;
    }

    /**
     * Send a JSON response and exit.
     *
     * @since  1.9.0
     * @param  bool  $success Whether the operation succeeded.
     * @param  array $data    Additional data to send.
     */
    private function json_response(bool $success, array $data = array()): void {
        wp_send_json(array(
            'success' => $success,
            'data'    => $data,
        ));
    }

    // ============================================================
    // PERSISTENCE: serialized rows file (option stores only metadata)
    // ============================================================

    /**
     * Get the path for the serialized rows file (alongside the uploaded file).
     *
     * @since  1.9.1
     * @param  string $uploaded_path Path of the uploaded original file.
     * @return string Path for .rows.dat companion file.
     */
    private function get_rows_file_path(string $uploaded_path): string {
        return $uploaded_path . '.rows.dat';
    }

    /**
     * Save rows to a persistent file (serialized, not in wp_options).
     *
     * @since  1.9.1
     * @param  string $uploaded_path Path of the uploaded original file.
     * @param  array  $rows          Array of associative rows.
     * @return bool                  True on success.
     */
    private function save_rows_file(string $uploaded_path, array $rows): bool {
        $rows_path = $this->get_rows_file_path($uploaded_path);
        $written   = file_put_contents($rows_path, serialize($rows), LOCK_EX);
        if (false === $written) {
            return false;
        }
        return true;
    }

    /**
     * Read rows from the persistent file.
     *
     * @since  1.9.1
     * @param  string $uploaded_path Path of the uploaded original file.
     * @return array                 Array of associative rows (empty on failure).
     */
    private function read_rows_file(string $uploaded_path): array {
        $rows_path = $this->get_rows_file_path($uploaded_path);
        if (!file_exists($rows_path) || !is_readable($rows_path)) {
            return array();
        }
        $data = file_get_contents($rows_path);
        if (false === $data) {
            return array();
        }
        $rows = @unserialize($data);
        if (!is_array($rows)) {
            return array();
        }
        return $rows;
    }

    /**
     * Delete the rows file if it exists.
     *
     * @since  1.9.1
     * @param  string $uploaded_path Path of the uploaded original file.
     */
    private function delete_rows_file(string $uploaded_path): void {
        $rows_path = $this->get_rows_file_path($uploaded_path);
        if (file_exists($rows_path)) {
            @unlink($rows_path);
        }
    }

    // ============================================================
    // AJAX: UPLOAD
    // ============================================================

    /**
     * Handle file upload, parse CSV, store parsed data in a serialized
     * file alongside the uploaded file (not in wp_options, which has
     * size limits).
     *
     * Expects $_FILES['import_file'] and $_POST['nonce'].
     *
     * @since 1.9.0
     */
    public function ajax_upload(): void {
        if (!$this->verify_request()) {
            $this->json_response(false, array('message' => __('Acesso negado ou nonce inválido.', 'um-dia-no-parque')));
            return;
        }

        if (!isset($_FILES['import_file']) || UPLOAD_ERR_OK !== $_FILES['import_file']['error']) {
            $this->json_response(false, array('message' => __('Nenhum arquivo enviado ou erro no upload.', 'um-dia-no-parque')));
            return;
        }

        $file = $_FILES['import_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Allowed extensions.
        if (!in_array($ext, array('csv', 'xlsx', 'xls'), true)) {
            $this->json_response(false, array(
                'message' => __('Formato não suportado. Use CSV ou XLSX.', 'um-dia-no-parque'),
            ));
            return;
        }

        // Move uploaded file to a temp location.
        $tmp_path = $this->get_temp_path(basename($file['name']));
        if (empty($tmp_path)) {
            $this->json_response(false, array(
                'message' => __('Erro ao criar diretório temporário para o upload.', 'um-dia-no-parque'),
            ));
            return;
        }
        if (!move_uploaded_file($file['tmp_name'], $tmp_path)) {
            $this->json_response(false, array(
                'message' => __('Erro ao salvar o arquivo temporário.', 'um-dia-no-parque'),
            ));
            return;
        }

        // Parse the file.
        $rows = $this->parse_file($tmp_path, $ext);

        if (empty($rows)) {
            $this->cleanup($tmp_path);
            $this->json_response(false, array(
                'message' => __('Arquivo vazio ou formato inválido.', 'um-dia-no-parque'),
            ));
            return;
        }

        // Log how many rows were parsed for diagnostics.
        $total_rows = count($rows);
        if (class_exists('Um_Dia_No_Parque_Logger')) {
            Um_Dia_No_Parque_Logger::log(
                sprintf('[Import] Parse concluído: %d linha(s) em "%s".', $total_rows, basename($file['name'])),
                Um_Dia_No_Parque_Logger::DEBUG
            );
        }

        // Write rows to persistent file (NOT in wp_options — may be too large).
        $saved = $this->save_rows_file($tmp_path, $rows);
        if (!$saved) {
            $this->cleanup($tmp_path);
            $this->json_response(false, array(
                'message' => __('Erro ao salvar dados temporários em disco. Verifique permissões ou espaço em disco.', 'um-dia-no-parque'),
            ));
            return;
        }

        // Store only metadata in wp_options (tiny).
        $meta = array(
            'file_path'   => $tmp_path,
            'file_name'   => basename($file['name']),
            'file_ext'    => $ext,
            'headers'     => array_keys($rows[0]),
            'total'       => count($rows),
            'offset'      => 0,
            'step'        => 0,
            'errors'      => array(),
            'imported_uc' => 0,
            'imported_atv'=> 0,
        );

        update_option(self::IMPORT_OPTION, $meta, false);

        // Free memory — rows are on disk now.
        unset($rows);

        $this->json_response(true, array(
            'message'   => sprintf(
                /* translators: %1$s: file name, %2$d: number of rows */
                __('Arquivo "%1$s" carregado com %2$d registros. Iniciando importação…', 'um-dia-no-parque'),
                esc_html($meta['file_name']),
                $meta['total']
            ),
            'total_rows' => $meta['total'],
        ));
    }

    // ============================================================
    // AJAX: PROCESS
    // ============================================================

    /**
     * Process one batch of rows from the serialized data file.
     *
     * Reads metadata from wp_options (tiny) and rows from the companion
     * .rows.dat file. Processes BATCH_SIZE rows at a time.
     *
     * @since 1.9.0
     */
    public function ajax_process(): void {
        if (!$this->verify_request()) {
            $this->json_response(false, array('message' => __('Acesso negado.', 'um-dia-no-parque')));
            return;
        }

        $meta = get_option(self::IMPORT_OPTION, null);
        if (!$meta || empty($meta['file_path'])) {
            $this->json_response(false, array(
                'message' => __('Nenhum dado para processar. Faça o upload primeiro.', 'um-dia-no-parque'),
            ));
            return;
        }

        $file_path = $meta['file_path'];
        $total     = isset($meta['total']) ? (int) $meta['total'] : 0;
        $offset    = isset($meta['offset']) ? (int) $meta['offset'] : 0;

        // Read rows from disk (not from wp_options).
        $all_rows = $this->read_rows_file($file_path);
        if (empty($all_rows)) {
            $this->json_response(false, array(
                'message' => __('Arquivo de dados temporário não encontrado ou corrompido.', 'um-dia-no-parque'),
            ));
            return;
        }

        // If we've already processed all rows, we're done.
        if ($offset >= $total) {
            $this->finish_import($meta);
            return;
        }

        $batch     = array_slice($all_rows, $offset, self::BATCH_SIZE);
        $processed = 0;
        $errors    = isset($meta['errors']) ? $meta['errors'] : array();

        if (class_exists('Um_Dia_No_Parque_Logger')) {
            Um_Dia_No_Parque_Logger::log(
                sprintf('[Import] Batch: offset=%d, batch_size=%d, total=%d.', $offset, count($batch), $total),
                Um_Dia_No_Parque_Logger::DEBUG
            );
        }

        foreach ($batch as $index => $row) {
            $row_number = $offset + $index + 2; // +2 because 1-indexed + header row.
            $result     = $this->import_row($row, $row_number);

            if (true === $result) {
                $processed++;
            } elseif (is_string($result)) {
                $errors[] = sprintf(
                    /* translators: %1$d: row number, %2$s: error message */
                    __('Linha %1$d: %2$s', 'um-dia-no-parque'),
                    $row_number,
                    $result
                );
            }
        }

        $new_offset = $offset + count($batch);

        // Count imported items so far.
        $imported_uc  = isset($meta['imported_uc']) ? (int) $meta['imported_uc'] : 0;
        $imported_atv = isset($meta['imported_atv']) ? (int) $meta['imported_atv'] : 0;

        $imported_uc  += $this->get_and_reset_counter('uc');
        $imported_atv += $this->get_and_reset_counter('atv');

        $meta['offset']      = $new_offset;
        $meta['imported_uc']  = $imported_uc;
        $meta['imported_atv'] = $imported_atv;
        $meta['errors']       = $errors;

        update_option(self::IMPORT_OPTION, $meta, false);

        // Free memory.
        unset($all_rows, $batch);

        $done = ($new_offset >= $total);

        if ($done) {
            $this->finish_import($meta);
        } else {
            $pct = round(($new_offset / $total) * 100);
            $this->json_response(true, array(
                'done'         => false,
                'offset'       => $new_offset,
                'total'        => $total,
                'total_rows'   => $total,
                'step'         => 0,
                'message'      => sprintf(
                    /* translators: %1$d: processed rows, %2$d: total rows, %3$d: percent */
                    __('Processando %1$d de %2$d (%3$d%%)…', 'um-dia-no-parque'),
                    $new_offset,
                    $total,
                    $pct
                ),
                'imported_uc'  => $imported_uc,
                'imported_atv' => $imported_atv,
                'total_errors' => count($errors),
            ));
        }
    }

    /**
     * Finish the import and return the final summary.
     *
     * @since  1.9.0
     * @param  array $meta Current import metadata (from option).
     */
    private function finish_import(array $meta): void {
        $imported_uc  = isset($meta['imported_uc']) ? (int) $meta['imported_uc'] : 0;
        $imported_atv = isset($meta['imported_atv']) ? (int) $meta['imported_atv'] : 0;
        $errors       = isset($meta['errors']) ? $meta['errors'] : array();

        if (class_exists('Um_Dia_No_Parque_Logger')) {
            Um_Dia_No_Parque_Logger::log(
                sprintf(
                    '[Import] Finalizado: total_rows=%d, imported_uc=%d, imported_atv=%d, errors=%d.',
                    isset($meta['total']) ? $meta['total'] : 0,
                    $imported_uc,
                    $imported_atv,
                    count($errors)
                ),
                Um_Dia_No_Parque_Logger::INFO
            );
        }

        $message = sprintf(
            /* translators: %1$d: imported UCs, %2$d: imported activities */
            __('Importação concluída! %1$d UC(s) e %2$d atividade(s) importadas.', 'um-dia-no-parque'),
            $imported_uc,
            $imported_atv
        );

        if (!empty($errors)) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of errors */
                __('(%d erro(s) — veja os logs para detalhes.)', 'um-dia-no-parque'),
                count($errors)
            );
        }

        // Log errors.
        if (!empty($errors) && class_exists('Um_Dia_No_Parque_Logger')) {
            foreach ($errors as $err) {
                Um_Dia_No_Parque_Logger::log($err, Um_Dia_No_Parque_Logger::WARNING);
            }
        }

        $this->json_response(true, array(
            'done'         => true,
            'message'      => $message,
            'offset'       => $meta['total'],
            'total'        => $meta['total'],
            'total_rows'   => $meta['total'],
            'imported_uc'  => $imported_uc,
            'imported_atv' => $imported_atv,
            'total_errors' => count($errors),
            'errors'       => $errors,
        ));
    }

    // ============================================================
    // AJAX: CLEANUP
    // ============================================================

    /**
     * Clean up temporary file, rows file, and option.
     *
     * @since 1.9.0
     */
    public function ajax_cleanup(): void {
        if (!$this->verify_request()) {
            $this->json_response(false, array('message' => __('Acesso negado.', 'um-dia-no-parque')));
            return;
        }

        $meta      = get_option(self::IMPORT_OPTION, null);
        $file_path = isset($meta['file_path']) ? $meta['file_path'] : '';
        $this->cleanup($file_path);

        $this->json_response(true, array('message' => __('Arquivo temporário removido.', 'um-dia-no-parque')));
    }

    // ============================================================
    // FILE PARSING
    // ============================================================

    /**
     * Get a temp file path within WP's upload dir.
     *
     * @since  1.9.0
     * @param  string $filename Original filename.
     * @return string Absolute path.
     */
    private function get_temp_path(string $filename): string {
        $upload_dir = wp_upload_dir();
        $dir        = wp_normalize_path($upload_dir['basedir']) . '/umdnp-import/';
        if (!is_dir($dir)) {
            $created = wp_mkdir_p($dir);
            if (!$created) {
                return '';
            }
        }
        // Add unique prefix to avoid collisions.
        return $dir . uniqid('import_', true) . '-' . sanitize_file_name($filename);
    }

    /**
     * Parse an uploaded file into an array of associative rows.
     *
     * Supports CSV natively. XLSX falls back gracefully if
     * PhpSpreadsheet is not available.
     *
     * @since  1.9.0
     * @param  string $filepath Absolute path to the uploaded file.
     * @param  string $ext      File extension (csv, xlsx, xls).
     * @return array            Array of associative arrays (column => value).
     */
    private function parse_file(string $filepath, string $ext): array {
        if (in_array($ext, array('xlsx', 'xls'), true)) {
            return $this->parse_xlsx($filepath);
        }
        return $this->parse_csv($filepath);
    }

    /**
     * Parse a CSV file.
     *
     * Handles BOM, varied delimiters, and quoted fields.
     *
     * @since  1.9.0
     * @param  string $filepath Absolute path.
     * @return array            Array of associative rows.
     */
    private function parse_csv(string $filepath): array {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return array();
        }

        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return array();
        }

        // Read first line to detect delimiter and strip BOM.
        $first_line = fgets($handle);
        if (false === $first_line) {
            fclose($handle);
            return array();
        }

        // Strip UTF-8 BOM if present.
        $bom = "\xEF\xBB\xBF";
        if (strpos($first_line, $bom) === 0) {
            $first_line = substr($first_line, strlen($bom));
        }

        // Detect delimiter: try comma, semicolon, then tab.
        $delimiters = array(',', ';', "\t");
        $delimiter  = ',';
        $best_count = 0;
        foreach ($delimiters as $d) {
            $count = substr_count($first_line, $d);
            if ($count > $best_count) {
                $best_count = $count;
                $delimiter  = $d;
            }
        }

        // Reset to the beginning; fgetcsv reads the real header line.
        rewind($handle);

        // Read headers.
        $headers_raw = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (empty($headers_raw) || !is_array($headers_raw)) {
            fclose($handle);
            return array();
        }

        // Clean headers: lowercase, trim, strip BOM residue.
        $headers = array();
        foreach ($headers_raw as $h) {
            $h = trim((string) $h);
            $h = str_replace($bom, '', $h);
            // Normalise key: lowercase, underscores, sanitise.
            $key = sanitize_key(
                str_replace(
                    array(' ', '-', '.', '(', ')', '/', '\\'),
                    '_',
                    strtolower($h)
                )
            );
            if (!empty($key)) {
                $headers[] = $key;
            }
        }

        // Normalise common header aliases.
        $headers = $this->normalise_headers($headers);

        if (empty($headers)) {
            fclose($handle);
            return array();
        }

        // Parse data rows.
        $rows = array();
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (!is_array($line) || null === $line) {
                continue;
            }

            $row = array();
            foreach ($headers as $i => $key) {
                $row[$key] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }

            // Skip completely empty rows.
            $has_value = false;
            foreach ($row as $v) {
                if ('' !== $v) {
                    $has_value = true;
                    break;
                }
            }
            if (!$has_value) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Attempt to parse XLSX via PhpSpreadsheet (if available) or return empty.
     *
     * @since  1.9.0
     * @param  string $filepath Absolute path.
     * @return array            Array of associative rows (empty if unavailable).
     */
    private function parse_xlsx(string $filepath): array {
        // Check if PhpSpreadsheet is available (installed via Composer).
        if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
                $worksheet   = $spreadsheet->getSheet(0);
                $data        = $worksheet->toArray(null, true, true, true);

                if (empty($data)) {
                    return array();
                }

                // First row = headers.
                $header_row = array_shift($data);
                $headers    = array();
                foreach ($header_row as $col => $label) {
                    $h = sanitize_key(
                        str_replace(
                            array(' ', '-', '.', '(', ')', '/', '\\'),
                            '_',
                            strtolower(trim((string) $label))
                        )
                    );
                    $headers[$col] = $h;
                }

                $headers = $this->normalise_headers(array_values($headers));

                $rows    = array();
                $col_keys = array_keys($header_row);
                foreach ($data as $row_data) {
                    $row = array();
                    foreach ($col_keys as $i => $col) {
                        $key = isset($headers[$i]) ? $headers[$i] : 'col_' . $i;
                        $row[$key] = isset($row_data[$col]) ? trim((string) $row_data[$col]) : '';
                    }

                    $values = array_filter($row, function ($v) {
                        return '' !== $v;
                    });
                    if (empty($values)) {
                        continue;
                    }

                    $rows[] = $row;
                }

                return $rows;
            } catch (\Exception $e) {
                return array();
            }
        }

        // PhpSpreadsheet not available — log a helpful message.
        if (class_exists('Um_Dia_No_Parque_Logger')) {
            Um_Dia_No_Parque_Logger::log(
                __('Import XLSX requer phpoffice/phpspreadsheet. Converta para CSV ou instale via Composer.', 'um-dia-no-parque'),
                Um_Dia_No_Parque_Logger::WARNING
            );
        }

        return array();
    }

    /**
     * Normalise common header variants to canonical keys the mapper expects.
     *
     * The WordPress `sanitize_key()` function strips accents from the header
     * labels (e.g. "Município" becomes "municpio"). This method therefore
     * builds a slug without accents and normalises it against the alias map.
     *
     * @since  1.9.1
     * @param  array $headers Sanitised header strings.
     * @return array Normalised headers.
     */
    private function normalise_headers(array $headers): array {
        $aliases = array(
            // UC fields.
            'nome_da_uc'             => 'uc_nome',
            'nome_uc'                => 'uc_nome',
            'uc'                     => 'uc_nome',
            'unidade_de_conservacao' => 'uc_nome',
            'unidade'                => 'uc_nome',
            'breve_descricao_uc'     => 'uc_descricao',
            'uc_breve_descricao'     => 'uc_descricao',
            'breve_descricao'        => 'uc_descricao',
            'descricao_curta'        => 'uc_descricao',
            'descricao_da_uc'        => 'uc_descricao',
            'responsavel_atividade'  => 'uc_responsavel',
            'responsavel'            => 'uc_responsavel',
            'realizador_atividade'   => 'uc_realizador',
            'realizador'             => 'uc_realizador',
            'email'                  => 'uc_email',
            'whatsapp'               => 'uc_whatsapp',
            'telefone'               => 'uc_whatsapp',
            'cep'                    => 'uc_cep',
            'endereco'               => 'uc_endereco',
            'logradouro'             => 'uc_endereco',
            'numero'                 => 'uc_numero',
            'link_do_endereco'       => 'uc_link_endereco',
            'link_endereco'          => 'uc_link_endereco',
            'link_maps'              => 'uc_link_endereco',
            'google_maps'            => 'uc_link_endereco',
            'maps'                   => 'uc_link_endereco',
            'social'                 => 'uc_social',
            'redes_sociais'          => 'uc_social',
            'instagram'              => 'uc_social',
            'bioma'                  => 'uc_bioma',
            'estado'                 => 'uc_uf',
            'uf'                     => 'uc_uf',
            'sigla_uf'               => 'uc_uf',
            'municipio'              => 'uc_municipio',
            'municpio'               => 'uc_municipio',
            'cidade'                 => 'uc_municipio',
            'imagem_uc'              => 'uc_imagem_url',
            'foto_uc'                => 'uc_imagem_url',
            'url_imagem'             => 'uc_imagem_url',

            // Atividade fields.
            'nome_da_atividade'      => 'atv_nome',
            'nome_atividade'         => 'atv_nome',
            'atividade'              => 'atv_nome',
            'titulo_atividade'       => 'atv_nome',
            'atv_descricao'          => 'atv_descricao',
            'descricao_da_atividade' => 'atv_descricao',
            'descricao_atividade'    => 'atv_descricao',
            'data'                   => 'atv_data',
            'data_atividade'         => 'atv_data',
            'horario'                => 'atv_horario',
            'horario_atividade'      => 'atv_horario',
            'dificuldade'            => 'atv_dificuldade',
            'publico'                => 'atv_publico',
            'publico_alvo'           => 'atv_publico',
            'tipo_atividade'         => 'atv_tipo',
            'tipo'                   => 'atv_tipo',
            'atividades'             => 'atividades',
        );

        $normalised = array();
        foreach ($headers as $h) {
            // Remove accents to match the alias map (same as `sanitize_key()` would do).
            $ascii_h = remove_accents($h);
            $normalised[] = isset($aliases[$ascii_h]) ? $aliases[$ascii_h] : $h;
        }

        return $normalised;
    }

    // ============================================================
    // ACTIVITY BLOCK PARSER (Gabarito format)
    // ============================================================

    /**
     * Parse the raw "Atividades" cell into one or more activity blocks.
     *
     * The Gabarito format separates activities with dashed lines and uses
     * labelled fields inside each block (Título, Data, Horário, Descrição,
     * Público, Dificuldade, etc.). A single block can contain multiple
     * lines for the same field (continuation lines).
     *
     * @since  1.9.1
     * @param  string $raw Raw content from the Atividades column.
     * @return array        Array of activity arrays.
     */
    private function parse_atividades(string $raw): array {
        $raw = trim($raw);
        if (empty($raw) || $this->is_empty_atividade($raw)) {
            return array();
        }

        // Normalise line breaks.
        $raw = str_replace(array("\r\n", "\r"), "\n", $raw);

        // Split activities by a dashed line separator (------).
        $parts = preg_split('/\n\s*[-=]{3,}\s*\n/', $raw);
        if (false === $parts) {
            $parts = array($raw);
        }

        $activities = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($this->is_empty_atividade($part)) {
                continue;
            }
            $activity = $this->parse_single_atividade($part);
            if (!empty($activity['atv_nome']) && !$this->is_empty_atividade($activity['atv_nome'], true)) {
                $activities[] = $activity;
            }
        }

        return $activities;
    }

    /**
     * Check whether an activity string is effectively empty or a placeholder.
     *
     * Treats generic titles like "Atividade" as empty unless they carry a
     * meaningful description.
     *
     * @since  1.9.1
     * @param  string $value      Raw activity string.
     * @param  bool   $check_title When true, also reject generic titles without a description.
     * @return bool
     */
    private function is_empty_atividade(string $value, bool $check_title = false): bool {
        $value = trim($value);
        if ('' === $value) {
            return true;
        }
        $normalized = mb_strtolower($value, 'UTF-8');
        $empties = array(
            'não informada',
            'nao informada',
            'não informado',
            'nao informado',
            'não informadas',
            'nao informadas',
            'none',
            '---',
        );
        foreach ($empties as $empty) {
            if ($normalized === $empty) {
                return true;
            }
        }
        if ($check_title) {
            $generic_titles = array('atividade', 'atividades', 'atividade sem nome', 'atividade sem título');
            foreach ($generic_titles as $generic) {
                if ($normalized === $generic) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Parse a single activity block into a normalised row fragment.
     *
     * @since  1.9.1
     * @param  string $part Activity block text.
     * @return array
     */
    private function parse_single_atividade(string $part): array {
        // Each line is a "Label: value" pair. Values may continue on the next line.
        $field_pattern = '/^(Título|Data|Horário|Horario|Descrição|Descricao|Público|Publico|Dificuldade|Tipo|Tipo de Atividade|Vagas|Inscrição|Inscricao|Local|Observação|Observacao|Link)\s*:\s*(.*)$/iu';

        $activity = array(
            'atv_nome'        => '',
            'atv_data'        => '',
            'atv_horario'     => '',
            'atv_descricao'   => '',
            'atv_publico'     => '',
            'atv_dificuldade' => '',
            'atv_tipo'        => '',
        );

        $current_field = null;
        $lines = explode("\n", $part);
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            // Remove leading label marker if the cell started with one.
            $line = preg_replace('/^Título\s*\(Nome\s*da\s*Atividade\)\s*:\s*/iu', '', $line);

            if (preg_match($field_pattern, $line, $matches)) {
                $label = mb_strtolower($matches[1], 'UTF-8');
                $value = trim($matches[2]);

                switch ($label) {
                    case 'título':
                    case 'titulo':
                        $current_field = 'atv_nome';
                        break;
                    case 'data':
                        $current_field = 'atv_data';
                        break;
                    case 'horário':
                    case 'horario':
                        $current_field = 'atv_horario';
                        break;
                    case 'descrição':
                    case 'descricao':
                    case 'descricao':
                        $current_field = 'atv_descricao';
                        break;
                    case 'público':
                    case 'publico':
                        $current_field = 'atv_publico';
                        break;
                    case 'dificuldade':
                        $current_field = 'atv_dificuldade';
                        break;
                    case 'tipo':
                    case 'tipo de atividade':
                        $current_field = 'atv_tipo';
                        break;
                    default:
                        // Unknown fields are appended to the description.
                        $current_field = 'atv_descricao';
                        $value = $matches[1] . ': ' . $value;
                        break;
                }

                if ('atv_descricao' === $current_field && !empty($activity[$current_field])) {
                    $activity[$current_field] .= "\n";
                }
                $activity[$current_field] .= $value;
            } elseif ($current_field) {
                // Continuation line.
                $activity[$current_field] .= ' ' . $line;
            } else {
                // Unlabelled text before the first label becomes description.
                $activity['atv_descricao'] .= $line . ' ';
            }
        }

        // Trim values.
        foreach ($activity as $key => $value) {
            $activity[$key] = trim($value);
        }

        // If no title was found but there is a description, use the first
        // line of the description as the title.
        if (empty($activity['atv_nome']) && !empty($activity['atv_descricao'])) {
            $first_line = strtok($activity['atv_descricao'], "\n");
            $activity['atv_nome'] = trim($first_line);
        }

        // Translate publico values to canonical terms.
        $activity['atv_publico'] = $this->normalise_publico($activity['atv_publico']);

        // Translate dificuldade values to canonical terms.
        $activity['atv_dificuldade'] = $this->normalise_dificuldade($activity['atv_dificuldade']);

        return $activity;
    }

    /**
     * Map free-text publico values to canonical taxonomy terms.
     *
     * Tolerates full sentences (e.g. "Público em geral, menores acompanhados")
     * by extracting keywords from each token.
     *
     * @since  1.9.1
     * @param  string $value Raw publico value.
     * @return string
     */
    private function normalise_publico(string $value): string {
        if (empty($value)) {
            return '';
        }
        $value = mb_strtolower($value, 'UTF-8');
        $parts = array_map('trim', preg_split('/[,;\/e]+/u', $value));
        $mapped = array();

        foreach ($parts as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }
            $category = $this->map_publico_keyword($part);
            if ($category) {
                $mapped[] = $category;
            }
        }

        $mapped = array_unique($mapped);
        return implode(', ', $mapped);
    }

    /**
     * Map a single publico token to a canonical category.
     *
     * @since  1.9.1
     * @param  string $part Lowercase publico token.
     * @return string|null
     */
    private function map_publico_keyword(string $part) {
        $keywords = array(
            'Infantil' => array('infantil', 'criança', 'crianca', 'kids', 'crianças', 'criancas'),
            'Adulto'   => array('adulto', 'adultos', 'jovem', 'jovens', 'adolescente', 'adolescentes'),
            'Geral'    => array(
                'geral', 'todos', 'comunidade', 'aberto', 'aberta', 'público', 'publico',
                'família', 'familia', 'famílias', 'familias', 'visitantes', 'moradores',
                'ribeirinhos', 'indígenas', 'indigenas', 'escola', 'alunos', 'população',
                'populacao', 'turistas', 'tradicionais', 'todas as idades', 'responsáveis',
            ),
        );
        foreach ($keywords as $category => $words) {
            foreach ($words as $word) {
                if (false !== strpos($part, $word)) {
                    return $category;
                }
            }
        }
        return null;
    }

    /**
     * Map free-text dificuldade values to canonical taxonomy terms.
     *
     * @since  1.9.1
     * @param  string $value Raw dificuldade value.
     * @return string
     */
    private function normalise_dificuldade(string $value): string {
        if (empty($value)) {
            return '';
        }
        $value = mb_strtolower($value, 'UTF-8');
        $parts = array_map('trim', preg_split('/[,;\/e]+/u', $value));
        $mapped = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }
            if (false !== strpos($part, 'leve') || false !== strpos($part, 'baixa') || false !== strpos($part, 'fácil') || false !== strpos($part, 'facil') || false !== strpos($part, 'iniciante')) {
                $mapped[] = 'Leve';
            } elseif (false !== strpos($part, 'moderada') || false !== strpos($part, 'média') || false !== strpos($part, 'media') || false !== strpos($part, 'moderado') || false !== strpos($part, 'moderada')) {
                $mapped[] = 'Moderada';
            } elseif (false !== strpos($part, 'avançado') || false !== strpos($part, 'avancado') || false !== strpos($part, 'difícil') || false !== strpos($part, 'dificil') || false !== strpos($part, 'difícil')) {
                $mapped[] = 'Avançado';
            }
        }
        return implode(', ', array_unique($mapped));
    }

    // ============================================================
    // ROW IMPORT
    // ============================================================

    /**
     * Per-row import counters (reset after each batch).
     *
     * @since  1.9.0
     * @var    array
     */
    private static $row_counters = array('uc' => 0, 'atv' => 0);

    /**
     * Get the counter for a given type and reset it.
     *
     * @since  1.9.0
     * @param  string $type 'uc' or 'atv'.
     * @return int
     */
    private function get_and_reset_counter(string $type): int {
        $val = isset(self::$row_counters[$type]) ? self::$row_counters[$type] : 0;
        self::$row_counters[$type] = 0;
        return $val;
    }

    /**
     * Import one row of data.
     *
     * Creates or updates a UC post and any activities described in the
     * Atividades cell. For duplicated UC names, the existing UC is reused
     * and new activities are appended.
     *
     * @since  1.9.0
     * @param  array   $row        Associative row data.
     * @param  int     $row_number Human-readable row number for error messages.
     * @return true|string         True on success, error message string on failure.
     */
    private function import_row(array $row, int $row_number) {
        if (empty($row['uc_nome'])) {
            return __('Nenhum nome de UC encontrado.', 'um-dia-no-parque');
        }

        // --- Import UC ---
        $uc_result = $this->import_single_uc($row);
        if (is_string($uc_result)) {
            return $uc_result;
        }

        $uc_id = $uc_result;
        self::$row_counters['uc']++;

        // --- Import activities ---
        $activities = $this->parse_atividades(isset($row['atividades']) ? $row['atividades'] : '');
        if (empty($activities)) {
            // No activities in this row is not an error.
            return true;
        }

        foreach ($activities as $activity) {
            // Merge UC-level data into the activity row fragment.
            $activity_row = array_merge($row, $activity);
            $atv_result = $this->import_single_atividade($activity_row, $uc_id);
            if (true === $atv_result) {
                self::$row_counters['atv']++;
            } elseif (is_string($atv_result)) {
                return $atv_result;
            }
        }

        return true;
    }

    /**
     * Import or update a single UC from a row of data.
     *
     * @since  1.9.0
     * @param  array     $row Row data.
     * @return int|string     Post ID on success, error message on failure.
     */
    private function import_single_uc(array $row) {
        $title = sanitize_text_field($row['uc_nome']);
        if (empty($title)) {
            return __('Título da UC vazio.', 'um-dia-no-parque');
        }

        // Check for existing UC with the same title.
        $existing = $this->get_post_by_title($title, 'uc');
        if ($existing) {
            $uc_id = $existing;
            if (class_exists('Um_Dia_No_Parque_Logger')) {
                Um_Dia_No_Parque_Logger::log(
                    sprintf('[Import] UC reutilizada (ID %d): "%s".', $uc_id, $title),
                    Um_Dia_No_Parque_Logger::DEBUG
                );
            }
        } else {
            $uc_id = wp_insert_post(array(
                'post_type'   => 'uc',
                'post_title'  => $title,
                'post_status' => 'publish',
            ), true);

            if (is_wp_error($uc_id)) {
                return $uc_id->get_error_message();
            }

            if (class_exists('Um_Dia_No_Parque_Logger')) {
                Um_Dia_No_Parque_Logger::log(
                    sprintf('[Import] UC CRIADA (ID %d): "%s".', $uc_id, $title),
                    Um_Dia_No_Parque_Logger::DEBUG
                );
            }
        }

        // Update meta fields.
        $this->update_uc_meta($uc_id, $row);

        return $uc_id;
    }

    /**
     * Update UC meta fields from parsed row data.
     *
     * @since  1.9.0
     * @param  int   $uc_id Post ID.
     * @param  array $row   Row data.
     */
    private function update_uc_meta(int $uc_id, array $row): void {
        $map = array(
            'uc_descricao'    => Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO,
            'uc_responsavel'  => Um_Dia_No_Parque_Meta::UC_RESPONSAVEL,
            'uc_email'        => Um_Dia_No_Parque_Meta::UC_EMAIL,
            'uc_whatsapp'     => Um_Dia_No_Parque_Meta::UC_WHATSAPP,
            'uc_realizador'   => Um_Dia_No_Parque_Meta::UC_REALIZADOR,
            'uc_cep'          => Um_Dia_No_Parque_Meta::UC_CEP,
            'uc_endereco'     => Um_Dia_No_Parque_Meta::UC_ENDERECO,
            'uc_numero'       => Um_Dia_No_Parque_Meta::UC_NUMERO,
            'uc_link_endereco'=> Um_Dia_No_Parque_Meta::UC_LINK_ENDERECO,
            'uc_social'       => Um_Dia_No_Parque_Meta::UC_SOCIAL,
        );

        foreach ($map as $key => $meta_key) {
            if (!empty($row[$key])) {
                $value = $this->sanitize_uc_field($key, $row[$key]);
                if (null !== $value) {
                    update_post_meta($uc_id, $meta_key, $value);
                }
            }
        }

        // Cidade — save as taxonomy term.
        if (!empty($row['uc_municipio'])) {
            $uf_id = 0;
            if (!empty($row['uc_uf'])) {
                $uf_obj = $this->find_or_create_uf($row['uc_uf']);
                if ($uf_obj > 0) {
                    $uf_id = $uf_obj;
                }
            }
            $this->assign_cidade_to_uc($uc_id, $row['uc_municipio'], $uf_id);
        }

        // Bioma taxonomy.
        if (!empty($row['uc_bioma'])) {
            $this->assign_taxonomy_term($uc_id, 'bioma', $row['uc_bioma']);
        }

        // Handle image URL (download and attach).
        if (!empty($row['uc_imagem_url'])) {
            $attachment_id = $this->import_image_from_url($row['uc_imagem_url'], $uc_id);
            if ($attachment_id > 0) {
                update_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_IMAGEM, $attachment_id);
                set_post_thumbnail($uc_id, $attachment_id);
            }
        }
    }

    /**
     * Sanitize a UC field, returning null for values that should be ignored.
     *
     * E-mails that look like physical addresses are dropped. Social values
     * are cleaned to keep only the most useful handle or URL.
     *
     * @since  1.9.1
     * @param  string $key   Canonical field key.
     * @param  string $value Raw value.
     * @return string|null
     */
    private function sanitize_uc_field(string $key, string $value) {
        $value = trim($value);
        if ('uc_email' === $key) {
            if (!$this->looks_like_email($value)) {
                return null;
            }
            return sanitize_email($value);
        }
        if ('uc_social' === $key) {
            return $this->normalise_social($value);
        }
        if ('uc_whatsapp' === $key) {
            return $this->normalise_whatsapp($value);
        }
        return sanitize_text_field($value);
    }

    /**
     * Check if a string looks like an e-mail address.
     *
     * @since  1.9.1
     * @param  string $value Raw value.
     * @return bool
     */
    private function looks_like_email(string $value): bool {
        $value = trim($value);
        if (empty($value)) {
            return false;
        }
        // Reject values that are clearly physical addresses.
        $red_flags = array('rua ', 'avenida ', 'av.', 'lote ', 'quadra ', 'número ', 'numero ', 'nº ', 'n° ', 'praça ', 'praca ', 'travessa ', 'estrada ');
        $lower = mb_strtolower($value, 'UTF-8');
        foreach ($red_flags as $flag) {
            if (false !== strpos($lower, $flag)) {
                return false;
            }
        }
        return is_email($value) || (false !== strpos($value, '@') && false === strpos($value, ' '));
    }

    /**
     * Normalise social values to a single handle or URL.
     *
     * @since  1.9.1
     * @param  string $value Raw social value.
     * @return string
     */
    private function normalise_social(string $value): string {
        $value = trim($value);
        if (empty($value)) {
            return '';
        }
        // Prefer Instagram handle (@handle) over a generic share URL.
        if (preg_match('/(?:https?:\/\/)?(?:www\.)?instagram\.com\/([A-Za-z0-9_.]+)/i', $value, $matches)) {
            return '@' . $matches[1];
        }
        if (preg_match('/(^|\s)(@[A-Za-z0-9_.]+)/', $value, $matches)) {
            return $matches[2];
        }
        // If there are multiple comma-separated values, keep the first one.
        $parts = array_map('trim', explode(',', $value));
        foreach ($parts as $part) {
            if ($this->looks_like_url($part)) {
                return $part;
            }
        }
        return $parts[0];
    }

    /**
     * Normalise a WhatsApp number.
     *
     * @since  1.9.1
     * @param  string $value Raw WhatsApp value.
     * @return string
     */
    private function normalise_whatsapp(string $value): string {
        $value = trim($value);
        if (empty($value)) {
            return '';
        }
        // Keep only digits, plus, parentheses, dashes and spaces.
        $value = preg_replace('/[^0-9()+\-\s]/', '', $value);
        return $value;
    }

    /**
     * Check if a string looks like a URL.
     *
     * @since  1.9.1
     * @param  string $value Raw value.
     * @return bool
     */
    private function looks_like_url(string $value): bool {
        $value = trim($value);
        return (false !== strpos($value, 'http://') || false !== strpos($value, 'https://'));
    }

    /**
     * Assign one or more cidade terms to a UC, handling composite values.
     *
     * @since  1.9.1
     * @param  int    $uc_id      UC post ID.
     * @param  string $municipio  Raw municipality value.
     * @param  int    $uf_id      UF post ID (0 if unknown).
     */
    private function assign_cidade_to_uc(int $uc_id, string $municipio, int $uf_id): void {
        $municipio = trim($municipio);
        if (empty($municipio)) {
            return;
        }

        // Split on common separators ("/", "-", " e ").
        $parts = preg_split('/\s*(?:\/|\\-|,?\s+e\s+)\s*/u', $municipio);
        if (false === $parts) {
            $parts = array($municipio);
        }

        $term_ids = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            // Remove trailing UF abbreviations like "Agudo/RS" leftover.
            $part = preg_replace('/\/[A-Z]{2}$/u', '', $part);
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            $term_id = $this->find_or_create_cidade($part, $uf_id);
            if ($term_id > 0) {
                $term_ids[] = $term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($uc_id, $term_ids, 'cidade', false);
        }
    }

    /**
     * Find or create a cidade term linked to a UF.
     *
     * @since  1.9.1
     * @param  string $nome  City name.
     * @param  int    $uf_id UF post ID.
     * @return int
     */
    private function find_or_create_cidade(string $nome, int $uf_id): int {
        if (class_exists('Um_Dia_No_Parque_Seed')) {
            $term_id = Um_Dia_No_Parque_Seed::get_instance()->find_or_create_cidade($nome, $uf_id);
            if ($term_id > 0) {
                return $term_id;
            }
        }

        // Fallback if Seed class is not available.
        $term = term_exists($nome, 'cidade');
        if ($term && isset($term['term_id'])) {
            $term_id = (int) $term['term_id'];
        } else {
            $result = wp_insert_term($nome, 'cidade');
            if (is_wp_error($result) || !isset($result['term_id'])) {
                return 0;
            }
            $term_id = (int) $result['term_id'];
        }
        if ($uf_id > 0) {
            $current_uf = get_term_meta($term_id, '_cidade_uf', true);
            if (empty($current_uf)) {
                update_term_meta($term_id, '_cidade_uf', $uf_id);
            }
        }
        return $term_id;
    }

    /**
     * Import or update a single atividade from a row of data.
     *
     * @since  1.9.0
     * @param  array   $row   Row data.
     * @param  int     $uc_id Parent UC post ID (0 if orphan).
     * @return true|string    True on success, error message on failure.
     */
    private function import_single_atividade(array $row, int $uc_id) {
        $title = sanitize_text_field($row['atv_nome']);
        if (empty($title)) {
            return __('Título da atividade vazio.', 'um-dia-no-parque');
        }

        // Check for existing activity with the same title.
        $existing = $this->get_post_by_title($title, 'atividade');
        if ($existing) {
            $atv_id = $existing;
            if (class_exists('Um_Dia_No_Parque_Logger')) {
                Um_Dia_No_Parque_Logger::log(
                    sprintf('[Import] Atividade reutilizada (ID %d): "%s".', $atv_id, $title),
                    Um_Dia_No_Parque_Logger::DEBUG
                );
            }
        } else {
            $atv_id = wp_insert_post(array(
                'post_type'   => 'atividade',
                'post_title'  => $title,
                'post_status' => 'publish',
            ), true);

            if (is_wp_error($atv_id)) {
                return $atv_id->get_error_message();
            }

            if (class_exists('Um_Dia_No_Parque_Logger')) {
                Um_Dia_No_Parque_Logger::log(
                    sprintf('[Import] Atividade CRIADA (ID %d): "%s".', $atv_id, $title),
                    Um_Dia_No_Parque_Logger::DEBUG
                );
            }
        }

        // Update meta fields.
        if (!empty($row['atv_descricao'])) {
            $descricao = sanitize_textarea_field($row['atv_descricao']);
            update_post_meta($atv_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DESCRICAO, $descricao);
        }

        if (!empty($row['atv_data'])) {
            update_post_meta($atv_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA, sanitize_text_field($row['atv_data']));
        }

        if (!empty($row['atv_horario'])) {
            update_post_meta($atv_id, Um_Dia_No_Parque_Meta::ATIVIDADE_HORARIO, sanitize_text_field($row['atv_horario']));
        }

        // Mark as active by default.
        update_post_meta($atv_id, Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO, '1');

        // Taxonomies.
        if (!empty($row['atv_dificuldade'])) {
            $this->assign_taxonomy_term($atv_id, 'dificuldade', $row['atv_dificuldade']);
        }

        if (!empty($row['atv_publico'])) {
            $this->assign_taxonomy_term($atv_id, 'publico', $row['atv_publico']);
        }

        if (!empty($row['atv_tipo'])) {
            $this->assign_taxonomy_term($atv_id, 'tipo_atividade', $row['atv_tipo']);
        }

        // Link activity to UC if provided.
        if ($uc_id > 0) {
            $existing_atvs = get_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS, true) ?: array();
            if (!in_array($atv_id, $existing_atvs)) {
                $existing_atvs[] = $atv_id;
                update_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS, $existing_atvs);
            }
        }

        return true;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Find or create a taxonomy term by name.
     *
     * @since  1.9.0
     * @param  int    $post_id   Post ID.
     * @param  string $taxonomy  Taxonomy slug.
     * @param  string $term_name Term name (comma-separated for multiple).
     */
    private function assign_taxonomy_term(int $post_id, string $taxonomy, string $term_name): void {
        $names = array_map('trim', explode(',', $term_name));
        $term_ids = array();

        foreach ($names as $name) {
            if (empty($name)) {
                continue;
            }

            $term = term_exists($name, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($name, $taxonomy);
            }

            if (!is_wp_error($term) && isset($term['term_id'])) {
                $term_ids[] = (int) $term['term_id'];
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy, true);
        }
    }

    /**
     * Find or create a UF post from its sigla/name.
     *
     * @since  1.9.0
     * @param  string   $sigla_or_name UF sigla (SP) or full name (São Paulo).
     * @return int                     Post ID, 0 on failure.
     */
    private function find_or_create_uf(string $sigla_or_name): int {
        $sigla_or_name = trim($sigla_or_name);
        $sigla_lookup  = strtoupper($sigla_or_name);

        // Try to find by sigla meta first.
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => 1,
            'meta_key'       => Um_Dia_No_Parque_Meta::UF_SIGLA,
            'meta_value'     => $sigla_lookup,
            'fields'         => 'ids',
        ));

        if (!empty($ufs)) {
            return (int) $ufs[0];
        }

        // Try by title (case-insensitive match).
        $existing = $this->get_post_by_title($sigla_or_name, 'uf');
        if ($existing) {
            return $existing;
        }

        // Map common abbreviations to full names.
        $state_names = array(
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        );

        $title = isset($state_names[$sigla_lookup])
            ? $state_names[$sigla_lookup]
            : $sigla_or_name;

        // Create UF post.
        $uf_id = wp_insert_post(array(
            'post_type'   => 'uf',
            'post_title'  => sanitize_text_field($title),
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($uf_id)) {
            return 0;
        }

        // Save sigla.
        if (isset($state_names[$sigla_lookup])) {
            update_post_meta($uf_id, Um_Dia_No_Parque_Meta::UF_SIGLA, $sigla_lookup);
        } else {
            // Try to extract 2-letter sigla from name.
            $sigla = substr($sigla_or_name, 0, 2);
            update_post_meta($uf_id, Um_Dia_No_Parque_Meta::UF_SIGLA, $sigla);
        }

        return (int) $uf_id;
    }

    /**
     * Download an image from a URL and attach it to a post.
     *
     * @since  1.9.0
     * @param  string $url     Image URL.
     * @param  int    $post_id Parent post ID.
     * @return int             Attachment ID, 0 on failure.
     */
    private function import_image_from_url(string $url, int $post_id): int {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image($url, $post_id, null, 'id');

        if (is_wp_error($attachment_id)) {
            return 0;
        }

        return (int) $attachment_id;
    }

    // ============================================================
    // CLEANUP
    // ============================================================

    /**
     * Remove temporary file, rows file, and option.
     *
     * @since  1.9.0
     * @param  string $file_path Optional file path to delete.
     */
    private function cleanup(string $file_path = ''): void {
        // Delete the uploaded file.
        if (!empty($file_path) && file_exists($file_path)) {
            @unlink($file_path);
        }

        // Delete the companion rows file.
        if (!empty($file_path)) {
            $this->delete_rows_file($file_path);
        }

        // Also remove the import directory if empty.
        $upload_dir = wp_upload_dir();
        $dir        = wp_normalize_path($upload_dir['basedir']) . '/umdnp-import/';
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            if (empty($files)) {
                @rmdir($dir);
            }
        }

        delete_option(self::IMPORT_OPTION);
    }

    /**
     * Find a post by title using WP_Query.
     *
     * Compatibility replacement for deprecated get_page_by_title().
     *
     * @since  1.9.0
     * @param  string $title     Post title to search for.
     * @param  string $post_type Post type slug.
     * @return int|false         Post ID or false if not found.
     */
    private function get_post_by_title(string $title, string $post_type) {
        $query = new WP_Query(array(
            'post_type'              => $post_type,
            'title'                  => $title,
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'fields'                 => 'ids',
            'post_status'            => 'any',
        ));

        if (!empty($query->posts)) {
            return (int) $query->posts[0];
        }

        return false;
    }
}
