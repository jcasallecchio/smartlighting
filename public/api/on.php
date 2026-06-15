<?php
/**
 * ============================================================================
 * API ENDPOINT - Turn On and Set Color
 * ============================================================================
 * POST /api/on
 * 
 * Turns on the luminaire and optionally sets a target color.
 * 
 * Request body:
 * {
 *   "color": "colorido" (optional, default: colorido)
 * }
 * 
 * The luminaire must be off to initiate a color change.
 * This endpoint handles the complex cycling logic needed.
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
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetColor = $input['color'] ?? 'colorido';
    
    // Validate color
    $colorManager = new ColorManager();
    if (!$colorManager->isValidColor($targetColor)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid color: ' . $targetColor,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Check if already executing
    $progress = loadJson(DATA_PATH . '/progress.json', ['is_executing' => false]);
    if ($progress['is_executing']) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A color transition is already in progress',
            'data' => $progress,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Get current state
    $state = loadJson(DATA_PATH . '/state.json', [
        'state' => 'off',
        'current_color' => 'colorido'
    ]);
    
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');
    
    // If already on and at target color, nothing to do
    if ($state['state'] === 'on' && strtolower($state['current_color']) === strtolower($targetColor)) {
        echo json_encode([
            'success' => true,
            'message' => 'Already on and at target color',
            'data' => [
                'state' => 'on',
                'current_color' => $state['current_color']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Calculate transition path
    $currentColor = $state['current_color'] ?? 'colorido';
    $transitionPath = $colorManager->getTransitionPath($currentColor, $targetColor);
    $cyclesNeeded = count($transitionPath) - 1;
    
    // Update progress
    $newProgress = [
        'is_executing' => true,
        'current_step' => 0,
        'total_steps' => $cyclesNeeded,
        'from_color' => $currentColor,
        'to_color' => $targetColor,
        'path' => $transitionPath,
        'started_at' => date('Y-m-d H:i:s'),
        'estimated_completion' => date('Y-m-d H:i:s', strtotime("+" . ($cyclesNeeded * intval(getenv('INTERVAL_TIME'))) . " seconds"))
    ];
    saveJson(DATA_PATH . '/progress.json', $newProgress);
    
    // Update state
    $state['state'] = 'on';
    $state['last_updated'] = date('Y-m-d H:i:s');
    saveJson(DATA_PATH . '/state.json', $state);
    
    // Turn on device
    $success = $ha->turnOn($entityId);
    
    if (!$success) {
        // Revert progress
        $newProgress['is_executing'] = false;
        saveJson(DATA_PATH . '/progress.json', $newProgress);
        
        throw new Exception('Failed to turn on device');
    }
    
    $logger->log('INFO', 'Device turned on', [
        'target_color' => $targetColor,
        'cycles_needed' => $cyclesNeeded,
        'path' => $transitionPath
    ], 'API');
    
    echo json_encode([
        'success' => true,
        'message' => 'Device turned on',
        'data' => $newProgress,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to turn on device: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to turn on device',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
