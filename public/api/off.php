<?php
/**
 * ============================================================================
 * API ENDPOINT - Turn Off
 * ============================================================================
 * POST /api/off
 * 
 * Turns off the luminaire immediately, stopping any ongoing color transition.
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
    
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');
    
    // Turn off device
    $success = $ha->turnOff($entityId);
    
    if (!$success) {
        throw new Exception('Failed to turn off device');
    }
    
    // Update state
    $state = loadJson(DATA_PATH . '/state.json', ['state' => 'off']);
    $state['state'] = 'off';
    $state['last_updated'] = date('Y-m-d H:i:s');
    saveJson(DATA_PATH . '/state.json', $state);
    
    // Cancel any ongoing transition
    $progress = loadJson(DATA_PATH . '/progress.json', ['is_executing' => false]);
    $progress['is_executing'] = false;
    saveJson(DATA_PATH . '/progress.json', $progress);
    
    $logger->log('INFO', 'Device turned off', [], 'API');
    
    echo json_encode([
        'success' => true,
        'message' => 'Device turned off',
        'data' => [
            'state' => 'off'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to turn off device: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to turn off device',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
