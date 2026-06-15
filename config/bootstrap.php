<?php
/**
 * ============================================================================
 * BOOTSTRAP - Smart Lighting Application Initialization
 * ============================================================================
 * Central configuration file that initializes the entire application.
 * Loads environment variables, initializes logger, and sets error handlers.
 */

define('APP_ROOT', dirname(dirname(__FILE__)));
define('CONFIG_PATH', APP_ROOT . '/config');
define('LOGS_PATH', APP_ROOT . '/data/logs');

// ============================================================================
// Load environment variables from .env file
// ============================================================================
function loadEnv() {
    $envFile = APP_ROOT . '/.env';
    
    if (!file_exists($envFile)) {
        throw new Exception('Configuration file .env not found. Please copy .env.example to .env and configure it.');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Set as environment variable.
            putenv("{$key}={$value}");
        }
    }
}

// Load environment variables
loadEnv();

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

// ============================================================================
// Validate required environment variables
// ============================================================================
$requiredEnv = ['HA_URL', 'HA_TOKEN', 'ENTITY_ID'];
foreach ($requiredEnv as $env) {
    if (!getenv($env)) {
        throw new Exception("Required environment variable '{$env}' is not set in .env file.");
    }
}

// ============================================================================
// Create necessary directories
// ============================================================================
$directories = [LOGS_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            throw new Exception("Failed to create directory: {$dir}");
        }
    }
}

// ============================================================================
// Initialize Logger
// ============================================================================
require_once CONFIG_PATH . '/logger.php';
$logger = new Logger(
    LOGS_PATH,
    getenv('DEBUG_MODE') === 'true',
    getenv('LOG_LEVEL') ?: 'INFO',
    getenv('LOG_MAX_SIZE') ?: 10485760
);

$requestStartedAt = microtime(true);
$requestId = bin2hex(random_bytes(6));
$logger->setRequestId($requestId);

if (PHP_SAPI !== 'cli') {
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $isReadRequest = $requestMethod === 'GET' && http_response_code() < 400;
    $isLogPolling = substr($requestPath, -13) === '/api/logs.php';

    if (!$isLogPolling) {
        $logger->log($isReadRequest ? 'DEBUG' : 'INFO', 'HTTP request started', [
            'method' => $requestMethod,
            'path' => $requestPath,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ], 'HTTP');
    }

    register_shutdown_function(function() use ($logger, $requestStartedAt, $requestId, $requestMethod, $requestPath, $isReadRequest, $isLogPolling) {
        if ($isLogPolling) {
            return;
        }

        $status = http_response_code();
        $durationMs = round((microtime(true) - $requestStartedAt) * 1000, 2);
        $level = $status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARNING' : ($isReadRequest ? 'DEBUG' : 'SUCCESS'));
        $logger->log($level, 'HTTP request completed', [
            'method' => $requestMethod,
            'path' => $requestPath,
            'status' => $status,
            'duration_ms' => $durationMs,
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'request_id' => $requestId
        ], 'HTTP');
    });
}

// ============================================================================
// Set Global Error Handler
// ============================================================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $logger;
    
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $level = match($errno) {
        E_WARNING => 'WARNING',
        E_NOTICE => 'INFO',
        E_USER_ERROR => 'ERROR',
        E_USER_WARNING => 'WARNING',
        E_USER_NOTICE => 'INFO',
        default => 'ERROR'
    };
    
    $logger->log($level, "PHP Error: $errstr", [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ], 'System');
    
    return true;
});

// ============================================================================
// Set Exception Handler
// ============================================================================
set_exception_handler(function($exception) {
    global $logger;
    
    $logger->log('ERROR', 'Uncaught Exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ], 'System');
    
    // Return JSON error response
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred',
        'data' => getenv('DEBUG_MODE') === 'true' ? $exception->getMessage() : null
    ]);
    exit(1);
});

// ============================================================================
// Set Shutdown Handler (catch fatal errors)
// ============================================================================
register_shutdown_function(function() {
    global $logger;
    
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logger->log('ERROR', 'Fatal Error: ' . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type']
        ], 'System');
    }
});

// ============================================================================
// Load Helper Functions and Classes
// ============================================================================
require_once CONFIG_PATH . '/hass.php';
require_once CONFIG_PATH . '/colors.php';
