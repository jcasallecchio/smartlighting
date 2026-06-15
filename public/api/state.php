<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $isOn = $ha->isOn(getenv('ENTITY_ID'));

    if ($isOn === null) {
        throw new RuntimeException('Não foi possível consultar o estado da luminária.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Estado consultado com sucesso',
        'data' => ['state' => $isOn ? 'on' : 'off'],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->log('ERROR', 'Failed to get state', ['error' => $e->getMessage()], 'Device');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
