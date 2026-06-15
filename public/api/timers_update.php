<?php
/**
 * ============================================================================
 * API ENDPOINT - Update Timer
 * ============================================================================
 * PUT /api/timers/update
 * 
 * Updates an existing timer.
 * 
 * Request body:
 * {
 *   "id": "morning_on",
 *   "time": "07:30" (optional),
 *   "enabled": false (optional),
 *   "target_color": "azul" (optional)
 * }
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    // Accept PUT and POST
    if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
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
    
    // Build updates
    $updates = [];
    if (isset($input['time'])) {
        $updates['time'] = $input['time'];
    }
    if (isset($input['enabled'])) {
        $updates['enabled'] = (bool)$input['enabled'];
    }
    if (isset($input['target_color'])) {
        $updates['target_color'] = $input['target_color'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Update timer
    $success = $scheduler->updateTimer($timerId, $updates);
    
    if (!$success) {
        throw new Exception('Failed to update timer');
    }
    
    $timer = $scheduler->getTimer($timerId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer updated successfully',
        'data' => $timer,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to update timer: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update timer',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
