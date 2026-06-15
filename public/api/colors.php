<?php
/**
 * ============================================================================
 * API ENDPOINT - Get Available Colors
 * ============================================================================
 * GET /api/colors
 * 
 * Returns the complete list of available colors with display information.
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    $colorManager = new ColorManager();
    
    $response = [
        'success' => true,
        'message' => 'Colors retrieved',
        'data' => [
            'colors' => $colorManager->getColorInfo(),
            'total' => count($colorManager->getSequence())
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $logger->log('INFO', 'Colors list retrieved', [], 'API');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to get colors: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve colors',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
