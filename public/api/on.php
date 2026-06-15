<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed');
    }

    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');

    $logger->log('INFO', 'Turn-on command received', ['entity_id' => $entityId], 'Device');
    $ha->turnOn($entityId);

    $logger->log('SUCCESS', 'Device turned on', ['entity_id' => $entityId], 'Device');

    echo json_encode([
        'success' => true,
        'message' => 'Device turned on',
        'data' => ['state' => 'on'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->log('ERROR', 'Failed to turn on device', ['error' => $e->getMessage()], 'Device');
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Failed to turn on device',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
