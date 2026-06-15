<?php
/**
 * ============================================================================
 * API ENDPOINT - Get Progress
 * ============================================================================
 * GET /api/progress
 * 
 * Returns the current progress of a color transition.
 * Used by the UI to show real-time updates.
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    $progress = loadJson(DATA_PATH . '/progress.json', [
        'is_executing' => false,
        'current_step' => 0,
        'total_steps' => 0,
        'from_color' => null,
        'to_color' => null,
        'path' => []
    ]);
    
    // Calculate elapsed and remaining time if executing
    $timeInfo = null;
    if ($progress['is_executing'] && !empty($progress['started_at'])) {
        $intervalTime = intval(getenv('INTERVAL_TIME', 30));
        $startTime = strtotime($progress['started_at']);
        $now = time();
        $elapsedSeconds = $now - $startTime;
        $elapsedMinutes = floor($elapsedSeconds / 60);
        $remainingSteps = $progress['total_steps'] - $progress['current_step'];
        $estimatedSecondsRemaining = $remainingSteps * $intervalTime;
        
        $timeInfo = [
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_minutes' => $elapsedMinutes,
            'estimated_seconds_remaining' => $estimatedSecondsRemaining,
            'estimated_minutes_remaining' => ceil($estimatedSecondsRemaining / 60),
            'estimated_completion' => $progress['estimated_completion']
        ];
    }
    
    $response = [
        'success' => true,
        'message' => 'Progress retrieved',
        'data' => [
            'is_executing' => $progress['is_executing'],
            'current_step' => $progress['current_step'],
            'total_steps' => $progress['total_steps'],
            'progress_percentage' => $progress['total_steps'] > 0 
                ? round(($progress['current_step'] / $progress['total_steps']) * 100, 2)
                : 0,
            'from_color' => $progress['from_color'],
            'to_color' => $progress['to_color'],
            'path' => $progress['path'],
            'time_info' => $timeInfo
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $logger->log('INFO', 'Progress retrieved', [], 'API');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to get progress: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve progress',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
