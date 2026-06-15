<?php
/**
 * ============================================================================
 * API ENDPOINT - Turn Off
 * ============================================================================
 * POST /api/off
 * 
 * Turns off the luminaire immediately, stopping any ongoing color transition.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';

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

    $logger->log('INFO', 'Turn-off command received', ['entity_id' => $entityId], 'Device');
    
    $ha->turnOff($entityId);
    
    $logger->log('SUCCESS', 'Device turned off', ['entity_id' => $entityId], 'Device');
    
    echo json_encode([
        'success' => true,
        'message' => 'Device turned off',
        'data' => [
            'state' => 'off'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to turn off device', ['error' => $e->getMessage()], 'Device');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to turn off device',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
