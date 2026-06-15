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
define('DATA_PATH', APP_ROOT . '/data');
define('LOGS_PATH', DATA_PATH . '/logs');
define('CACHE_PATH', APP_ROOT . '/storage/cache');
define('TMP_PATH', APP_ROOT . '/storage/tmp');

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
            
            // Set as environment variable and constant
            putenv("{$key}={$value}");
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Load environment variables
loadEnv();

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
$directories = [DATA_PATH, LOGS_PATH, CACHE_PATH, TMP_PATH];
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
$logger = new Logger(LOGS_PATH, getenv('DEBUG_MODE') === 'true');

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
require_once CONFIG_PATH . '/helpers.php';
require_once CONFIG_PATH . '/hass.php';
require_once CONFIG_PATH . '/colors.php';
require_once CONFIG_PATH . '/scheduler.php';

// ============================================================================
// Log application startup
// ============================================================================
$logger->log('INFO', 'Smart Lighting application initialized', [
    'entity_id' => getenv('ENTITY_ID'),
    'ha_url' => getenv('HA_URL'),
    'debug_mode' => getenv('DEBUG_MODE')
], 'Bootstrap');

return [
    'logger' => $logger,
    'app_root' => APP_ROOT
];
