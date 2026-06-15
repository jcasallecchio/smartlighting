<?php
/**
 * ============================================================================
 * API ENDPOINT - Get Logs
 * ============================================================================
 * GET /api/logs
 * 
 * Returns application logs with optional filtering.
 * 
 * Query parameters:
 * - date: Filter by date (YYYY-MM-DD)
 * - level: Filter by level (INFO, SUCCESS, WARNING, ERROR)
 * - category: Filter by category
 * - search: Search in message
 * - limit: Maximum entries (default: 100, max: 1000)
 * - offset: Pagination offset (default: 0)
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    // Get query parameters
    $date = $_GET['date'] ?? null;
    $level = $_GET['level'] ?? null;
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $limit = min(intval($_GET['limit'] ?? 100), 1000);
    $offset = intval($_GET['offset'] ?? 0);
    
    // Read logs
    $logs = $logger->readLogs($date, $level, $category, $search, $limit, $offset);
    
    $response = [
        'success' => true,
        'message' => 'Logs retrieved',
        'data' => [
            'logs' => $logs,
            'total' => count($logs),
            'limit' => $limit,
            'offset' => $offset,
            'filters' => [
                'date' => $date,
                'level' => $level,
                'category' => $category,
                'search' => $search
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Failed to get logs: ' . $e->getMessage(), [], 'API');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve logs',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES);
}
