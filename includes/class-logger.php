<?php
/**
 * InterSoccer Player Management Logger
 *
 * @package InterSoccer_Player_Management
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for debugging and error tracking
 */
class InterSoccer_Player_Logger {

    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * Log file path
     */
    private $log_file;

    /**
     * Whether logging is enabled
     */
    private $logging_enabled;

    /**
     * Maximum log file size (in bytes)
     */
    private $max_file_size = 10485760; // 10MB

    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/debug-intersoccer-players.log';
        $this->logging_enabled = $this->is_logging_enabled();
        
        // Create log directory if it doesn't exist
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private function is_logging_enabled() {
        // Enable logging if WP_DEBUG is true or if specifically enabled for our plugin
        return (defined('WP_DEBUG') && WP_DEBUG) || 
               get_option('intersoccer_player_enable_logging', false);
    }

    /**
     * Log emergency message
     *
     * @param string $message
     * @param array $context
     */
    public function emergency($message, array $context = array()) {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     *
     * @param string $message
     * @param array $context
     */
    public function alert($message, array $context = array()) {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     *
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context = array()) {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = array()) {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, array $context = array()) {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     *
     * @param string $message
     * @param array $context
     */
    public function notice($message, array $context = array()) {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = array()) {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, array $context = array()) {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Main logging method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array()) {
        if (!$this->logging_enabled) {
            return;
        }

        // Check file size and rotate if necessary
        $this->rotate_log_if_needed();

        // Format log entry
        $log_entry = $this->format_log_entry($level, $message, $context);

        // Write to file
        $this->write_to_file($log_entry);

        // Also log to WordPress error log for critical errors
        if (in_array($level, array(self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR))) {
            error_log("InterSoccer Players [{$level}]: {$message}");
        }
    }

    /**
     * Format log entry
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function format_log_entry($level, $message, array $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        // Interpolate context values into message placeholders
        $message = $this->interpolate($message, $context);

        // Build log entry
        $entry = sprintf(
            "[%s] [%s] [User:%d] [IP:%s] [URI:%s] %s",
            $timestamp,
            strtoupper($level),
            $user_id,
            $ip_address,
            $request_uri,
            $message
        );

        // Add context data if present
        if (!empty($context)) {
            $entry .= ' | Context: ' . json_encode($context);
        }

        return $entry . PHP_EOL;
    }

    /**
     * Interpolate context values into message placeholders
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate($message, array $context = array()) {
        // Build replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // Check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Write to log file
     *
     * @param string $entry
     */
    private function write_to_file($entry) {
        if (file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX) === false) {
            // Fallback to WordPress error log if file write fails
            error_log("InterSoccer Players: Failed to write to log file. Message: " . trim($entry));
        }
    }

    /**
     * Rotate log file if it exceeds maximum size
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }

        if (filesize($this->log_file) > $this->max_file_size) {
            $backup_file = $this->log_file . '.old';
            
            // Remove old backup
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            
            // Move current log to backup
            rename($this->log_file, $backup_file);
            
            $this->info('Log file rotated due to size limit');
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Log player action
     *
     * @param string $action
     * @param int $user_id
     * @param int $player_index
     * @param array $data
     */
    public function log_player_action($action, $user_id, $player_index, array $data = array()) {
        $context = array(
            'action' => $action,
            'user_id' => $user_id,
            'player_index' => $player_index,
            'data' => $data
        );

        $this->info("Player action: {action} for user {user_id}, player {player_index}", $context);
    }

    /**
     * Log order processing
     *
     * @param int $order_id
     * @param string $status
     * @param array $roster_data
     */
    public function log_order_processing($order_id, $status, array $roster_data = array()) {
        $context = array(
            'order_id' => $order_id,
            'status' => $status,
            'roster_data' => $roster_data
        );

        $this->info("Order processing: Order {order_id} - {status}", $context);
    }

    /**
     * Log export activity
     *
     * @param string $export_type
     * @param int $record_count
     * @param string $format
     * @param int $user_id
     */
    public function log_export_activity($export_type, $record_count, $format, $user_id) {
        $context = array(
            'export_type' => $export_type,
            'record_count' => $record_count,
            'format' => $format,
            'user_id' => $user_id
        );

        $this->info("Export activity: {export_type} export ({record_count} records) in {format} format by user {user_id}", $context);
    }

    /**
     * Log database operation
     *
     * @param string $operation
     * @param string $table
     * @param array $data
     * @param bool $success
     */
    public function log_database_operation($operation, $table, array $data = array(), $success = true) {
        $level = $success ? self::DEBUG : self::ERROR;
        $status = $success ? 'successful' : 'failed';
        
        $context = array(
            'operation' => $operation,
            'table' => $table,
            'data' => $data,
            'success' => $success
        );

        $this->log($level, "Database {operation} on {table} - {$status}", $context);
    }

    /**
     * Clear log file
     *
     * @return bool
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            $success = unlink($this->log_file);
            if ($success) {
                $this->info('Log file cleared by user ' . get_current_user_id());
            }
            return $success;
        }
        return true;
    }

    /**
     * Get log file contents
     *
     * @param int $lines Number of lines to read from end of file
     * @return string
     */
    public function get_log_contents($lines = 100) {
        if (!file_exists($this->log_file)) {
            return '';
        }

        if ($lines <= 0) {
            return file_get_contents($this->log_file);
        }

        // Read last N lines efficiently
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        $content = '';
        
        for ($line = $start_line; $line <= $total_lines; $line++) {
            $file->seek($line);
            $content .= $file->current();
        }

        return $content;
    }

    /**
     * Get log file size
     *
     * @return int
     */
    public function get_log_file_size() {
        return file_exists($this->log_file) ? filesize($this->log_file) : 0;
    }

    /**
     * Enable/disable logging
     *
     * @param bool $enabled
     */
    public function set_logging_enabled($enabled) {
        $this->logging_enabled = (bool) $enabled;
        update_option('intersoccer_player_enable_logging', $enabled);
        
        $status = $enabled ? 'enabled' : 'disabled';
        $this->info("Logging {$status} by user " . get_current_user_id());
    }

    /**
     * Check if logging is currently enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->logging_enabled;
    }
}