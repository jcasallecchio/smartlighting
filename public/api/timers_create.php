<?php
/**
 * ============================================================================
 * API ENDPOINT - Create Timer
 * ============================================================================
 * POST /api/timers/create
 * 
 * Creates a new timer (on/off schedule).
 * 
 * Request body:
 * {
 *   "id": "morning_on",
 *   "type": "on",
 *   "time": "07:00",
 *   "enabled": true,
 *   "target_color": "colorido" (optional, for 'on' timers)
 * }
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Validate required fields
    $required = ['id', 'type', 'time'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: {$field}",
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    $scheduler = new Scheduler(DATA_PATH, $logger);
    
    // Check if timer already exists
    if ($scheduler->getTimer($input['id'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Timer with this ID already exists',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Create timer
    $success = $scheduler->createTimer(
        $input['id'],
        $input['type'],
        $input['time'],
        $input['enabled'] ?? true,
        $input['target_color'] ?? null
    );
    
    if (!$success) {
        throw new Exception('Failed to create timer');
    }
    
    $timer = $scheduler->getTimer($input['id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer created successfully',
        'data' => $timer,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to create timer: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create timer',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
