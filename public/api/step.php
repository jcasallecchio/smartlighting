<?php
/**
 * ============================================================================
 * API ENDPOINT - Execute Color Transition Step
 * ============================================================================
 * POST /api/step
 * 
 * Executes a single step in the color transition sequence.
 * This is called by the scheduler/cron job every INTERVAL_TIME seconds.
 * 
 * A color transition requires multiple power cycles (off/on).
 * Each cycle advances the luminaire to the next color in the sequence.
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
    
    // Check if a transition is in progress
    $progress = loadJson(DATA_PATH . '/progress.json', ['is_executing' => false]);
    
    if (!$progress['is_executing']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No color transition in progress',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Check if we've completed all steps
    if ($progress['current_step'] >= $progress['total_steps']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Transition already completed',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');
    $colorManager = new ColorManager();
    
    // Perform power cycle: off then on
    // This advances the color to the next in sequence
    $turnOffSuccess = $ha->turnOff($entityId);
    if (!$turnOffSuccess) {
        throw new Exception('Failed to turn off device');
    }
    
    // Wait a moment before turning back on
    sleep(2);
    
    $turnOnSuccess = $ha->turnOn($entityId);
    if (!$turnOnSuccess) {
        throw new Exception('Failed to turn on device');
    }
    
    // Update progress
    $progress['current_step']++;
    
    // Check if we've reached the target
    if ($progress['current_step'] >= $progress['total_steps']) {
        // Transition complete
        $progress['is_executing'] = false;
        
        // Update state with final color
        $state = loadJson(DATA_PATH . '/state.json', ['state' => 'on']);
        $state['current_color'] = $progress['to_color'];
        $state['last_updated'] = date('Y-m-d H:i:s');
        saveJson(DATA_PATH . '/state.json', $state);
        
        $logger->log('INFO', 'Color transition completed', [
            'from_color' => $progress['from_color'],
            'to_color' => $progress['to_color'],
            'total_steps' => $progress['total_steps']
        ], 'Transition');
    }
    
    saveJson(DATA_PATH . '/progress.json', $progress);
    
    echo json_encode([
        'success' => true,
        'message' => 'Step executed',
        'data' => [
            'step' => $progress['current_step'],
            'total_steps' => $progress['total_steps'],
            'progress_percentage' => round(($progress['current_step'] / $progress['total_steps']) * 100, 2),
            'is_executing' => $progress['is_executing'],
            'completed' => !$progress['is_executing']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to execute transition step: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to execute step',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
