<?php
/**
 * ============================================================================
 * API ENDPOINT - Get Current State
 * ============================================================================
 * GET /api/state
 * 
 * Returns the current state of the luminaire including:
 * - Power state (on/off)
 * - Current color
 * - Last update time
 * - Execution status
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    // Get current state from file
    $state = loadJson(DATA_PATH . '/state.json', [
        'state' => 'off',
        'current_color' => 'colorido',
        'last_updated' => date('Y-m-d H:i:s'),
        'is_executing' => false
    ]);
    
    // Get progress information
    $progress = loadJson(DATA_PATH . '/progress.json', [
        'is_executing' => false,
        'current_step' => 0,
        'total_steps' => 0,
        'from_color' => null,
        'to_color' => null,
        'path' => []
    ]);
    
    // Verify state with Home Assistant if possible
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');
    $isOn = $ha->isOn($entityId);
    
    // Update state if real status differs
    if ($isOn !== null && (($isOn && $state['state'] !== 'on') || (!$isOn && $state['state'] !== 'off'))) {
        $state['state'] = $isOn ? 'on' : 'off';
        $state['last_updated'] = date('Y-m-d H:i:s');
        saveJson(DATA_PATH . '/state.json', $state);
    }
    
    $response = [
        'success' => true,
        'message' => 'Current state retrieved',
        'data' => [
            'state' => $state['state'],
            'current_color' => $state['current_color'],
            'last_updated' => $state['last_updated'],
            'is_executing' => $progress['is_executing'],
            'execution_progress' => $progress['is_executing'] ? [
                'current_step' => $progress['current_step'],
                'total_steps' => $progress['total_steps'],
                'from_color' => $progress['from_color'],
                'to_color' => $progress['to_color'],
                'estimated_completion' => $progress['estimated_completion']
            ] : null
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $logger->log('INFO', 'State retrieved successfully', [], 'API');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to get state: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve state',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
