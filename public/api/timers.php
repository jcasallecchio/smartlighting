<?php
/**
 * ============================================================================
 * API ENDPOINT - Get Timers
 * ============================================================================
 * GET /api/timers
 * 
 * Returns all configured timers (on/off schedules).
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    $scheduler = new Scheduler(DATA_PATH, $logger);
    $timers = $scheduler->getTimers();
    
    // Convert to array
    $timersList = array_values($timers);
    
    $response = [
        'success' => true,
        'message' => 'Timers retrieved',
        'data' => [
            'timers' => $timersList,
            'total' => count($timersList)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $logger->log('INFO', 'Timers list retrieved', ['count' => count($timersList)], 'API');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to get timers: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve timers',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
