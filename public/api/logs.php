<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $limit = min(max((int) ($_GET['limit'] ?? 250), 1), 1000);
    $offset = max((int) ($_GET['offset'] ?? 0), 0);
    $filters = [
        'date' => trim($_GET['date'] ?? ''),
        'level' => trim($_GET['level'] ?? ''),
        'category' => trim($_GET['category'] ?? ''),
        'search' => trim($_GET['search'] ?? ''),
        'request_id' => trim($_GET['request_id'] ?? '')
    ];

    $result = $logger->query($filters, $limit, $offset);

    echo json_encode([
        'success' => true,
        'message' => 'Logs retrieved',
        'data' => [
            'logs' => $result['entries'],
            'total' => $result['total'],
            'levels' => $result['levels'],
            'categories' => $result['categories'],
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $filters
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->log('ERROR', 'Failed to retrieve logs', ['error' => $e->getMessage()], 'Logs');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve logs',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
