<?php
/**
 * ============================================================================
 * HELPER FUNCTIONS - Utility Functions for Smart Lighting
 * ============================================================================
 * Provides common utility functions used across the application.
 */

/**
 * Load JSON file with error handling
 * 
 * @param string $path File path
 * @param array $default Default value if file doesn't exist
 * @return array|null Decoded JSON data or default
 */
function loadJson($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    
    $content = @file_get_contents($path);
    if ($content === false) {
        return $default;
    }
    
    $data = @json_decode($content, true);
    return is_array($data) ? $data : $default;
}

/**
 * Save data to JSON file with error handling
 * 
 * @param string $path File path
 * @param array $data Data to save
 * @return bool True on success
 */
function saveJson($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        return false;
    }
    
    // Create directory if needed
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Write to temporary file first (atomic write)
    $tmpFile = $path . '.tmp';
    if (@file_put_contents($tmpFile, $json) === false) {
        return false;
    }
    
    // Atomic rename
    return @rename($tmpFile, $path);
}

/**
 * Send JSON response
 * 
 * @param bool $success Operation success status
 * @param string $message Response message
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 * @return void
 */
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    header('Content-Type: application/json', true, $statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Get file lock (prevent concurrent execution)
 * 
 * @param string $lockFile Lock file path
 * @param int $timeout Lock timeout in seconds
 * @return bool True if lock acquired
 */
function acquireLock($lockFile, $timeout = 30) {
    $attempts = 0;
    $maxAttempts = $timeout * 10;
    
    while (file_exists($lockFile) && $attempts < $maxAttempts) {
        // Check if lock is stale
        $lockAge = time() - filemtime($lockFile);
        if ($lockAge > $timeout) {
            @unlink($lockFile);
            break;
        }
        
        usleep(100000); // 100ms
        $attempts++;
    }
    
    // Try to create lock file
    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return @file_put_contents($lockFile, time()) !== false;
}

/**
 * Release file lock
 * 
 * @param string $lockFile Lock file path
 * @return bool True on success
 */
function releaseLock($lockFile) {
    return @unlink($lockFile);
}

/**
 * Calculate time ago from timestamp
 * 
 * @param string $timestamp Timestamp in Y-m-d H:i:s format
 * @return string Human-readable time ago
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'agora';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minuto' . ($minutes > 1 ? 's' : '') . ' atrás';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' atrás';
    } else {
        $days = floor($diff / 86400);
        return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
    }
}

/**
 * Sanitize input string
 * 
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate JSON string
 * 
 * @param string $json JSON string
 * @return bool True if valid JSON
 */
function isValidJson($json) {
    json_decode($json);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Get request IP address
 * 
 * @return string Client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    return sanitize($ip);
}
