<?php
/**
 * ============================================================================
 * API ENDPOINT - Delete Timer
 * ============================================================================
 * DELETE /api/timers/delete
 * 
 * Deletes a timer.
 * 
 * Request body:
 * {
 *   "id": "morning_on"
 * }
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    // Accept DELETE and POST
    if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing timer ID',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $scheduler = new Scheduler(DATA_PATH, $logger);
    $timerId = $input['id'];
    
    // Check if timer exists
    if (!$scheduler->getTimer($timerId)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Timer not found',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Delete timer
    $success = $scheduler->deleteTimer($timerId);
    
    if (!$success) {
        throw new Exception('Failed to delete timer');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer deleted successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to delete timer: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete timer',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
