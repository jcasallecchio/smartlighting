<?php
/**
 * ============================================================================
 * LOGGER - Centralized Logging System for Smart Lighting
 * ============================================================================
 * Handles all application logging with support for different severity levels,
 * categories, and daily log rotation.
 *
 * Log Levels:
 * - INFO: General informational messages
 * - SUCCESS: Successful operations
 * - WARNING: Warning conditions
 * - ERROR: Error conditions
 */

class Logger {
    /**
     * @var string Path to logs directory
     */
    private $logDir;
    
    /**
     * @var bool Enable debug mode (verbose logging)
     */
    private $debugMode;
    
    /**
     * @var array Log levels and their priorities
     */
    private $levels = [
        'INFO' => 0,
        'SUCCESS' => 1,
        'WARNING' => 2,
        'ERROR' => 3
    ];
    
    /**
     * Constructor
     * 
     * @param string $logDir Path to logs directory
     * @param bool $debugMode Enable debug mode
     */
    public function __construct($logDir, $debugMode = false) {
        $this->logDir = rtrim($logDir, '/');
        $this->debugMode = $debugMode;
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level (INFO, SUCCESS, WARNING, ERROR)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $category Log category (default: 'General')
     * @return bool True on success
     */
    public function log($level, $message, $context = [], $category = 'General') {
        // Validate level
        if (!isset($this->levels[$level])) {
            $level = 'INFO';
        }
        
        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] [%s] [%s] [%s] %s",
            $timestamp,
            str_pad($level, 7),
            str_pad($category, 15),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $logEntry .= PHP_EOL . 'Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        // Add request information for web requests
        if (isset($_SERVER['REQUEST_URI'])) {
            $logEntry .= PHP_EOL . 'Request: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'];
        }
        
        // Add newline
        $logEntry .= PHP_EOL . str_repeat('-', 100) . PHP_EOL;
        
        // Write to daily log file
        $logFile = $this->getLogFile();
        if (!@file_put_contents($logFile, $logEntry, FILE_APPEND)) {
            // Fallback: try to create directory
            if (!is_dir($this->logDir)) {
                mkdir($this->logDir, 0755, true);
                file_put_contents($logFile, $logEntry, FILE_APPEND);
            }
        }
        
        // Also log to PHP error log in debug mode
        if ($this->debugMode) {
            error_log($logEntry);
        }
        
        return true;
    }
    
    /**
     * Get path to today's log file
     * 
     * @return string Path to log file
     */
    private function getLogFile() {
        $date = date('Y-m-d');
        return $this->logDir . '/smartlighting-' . $date . '.log';
    }
    
    /**
     * Get all log files
     * 
     * @return array Array of log file paths
     */
    public function getLogFiles() {
        $files = [];
        if (is_dir($this->logDir)) {
            $items = scandir($this->logDir, SCANDIR_SORT_DESCENDING);
            foreach ($items as $item) {
                if (strpos($item, 'smartlighting-') === 0 && strpos($item, '.log') !== false) {
                    $files[] = $this->logDir . '/' . $item;
                }
            }
        }
        return $files;
    }
    
    /**
     * Read logs from file with optional filtering
     * 
     * @param string|null $date Filter by date (YYYY-MM-DD)
     * @param string|null $level Filter by level
     * @param string|null $category Filter by category
     * @param string|null $search Search in message
     * @param int $limit Maximum entries to return
     * @param int $offset Pagination offset
     * @return array Array of log entries
     */
    public function readLogs($date = null, $level = null, $category = null, $search = null, $limit = 100, $offset = 0) {
        $logFile = $date 
            ? $this->logDir . '/smartlighting-' . $date . '.log'
            : $this->getLogFile();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $content = file_get_contents($logFile);
        $entries = explode(str_repeat('-', 100) . PHP_EOL, $content);
        
        $filtered = [];
        foreach ($entries as $entry) {
            if (empty(trim($entry))) {
                continue;
            }
            
            // Parse entry
            if (!preg_match('/\[(.+?)\] \[(.+?)\] \[(.+?)\] \[(.+?)\] (.+)/s', $entry, $matches)) {
                continue;
            }
            
            $entryData = [
                'timestamp' => trim($matches[1]),
                'level' => trim($matches[2]),
                'category' => trim($matches[3]),
                'method' => trim($matches[4]),
                'message' => trim($matches[5])
            ];
            
            // Apply filters
            if ($level && $entryData['level'] !== $level) {
                continue;
            }
            if ($category && $entryData['category'] !== $category) {
                continue;
            }
            if ($search && stripos($entry, $search) === false) {
                continue;
            }
            
            $filtered[] = $entryData;
        }
        
        // Apply pagination
        return array_slice($filtered, $offset, $limit);
    }
    
    /**
     * Clean old log files
     * 
     * @param int $days Delete logs older than this many days
     * @return int Number of files deleted
     */
    public function cleanOldLogs($days = 30) {
        $deleted = 0;
        $cutoffTime = time() - ($days * 86400);
        
        $files = $this->getLogFiles();
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}
