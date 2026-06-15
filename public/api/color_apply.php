<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $targetColor = strtolower(trim($input['target_color'] ?? ''));
    $providedCurrentColor = strtolower(trim($input['current_color'] ?? ''));
    $colorManager = new ColorManager();

    if (!$colorManager->isValidColor($targetColor)) {
        http_response_code(400);
        throw new InvalidArgumentException('Selecione uma cor de destino válida.');
    }

    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $entityId = getenv('ENTITY_ID');
    $isOn = $ha->isOn($entityId);

    if ($isOn === null) {
        throw new RuntimeException('Não foi possível consultar o estado da luminária.');
    }

    if ($isOn) {
        if (!$colorManager->isValidColor($providedCurrentColor)) {
            http_response_code(400);
            throw new InvalidArgumentException('Informe a cor atual da luminária.');
        }
        $currentColor = $providedCurrentColor;
    } else {
        $ha->turnOn($entityId);
        $currentColor = 'colorido';
    }

    $cycles = $colorManager->getCycleCount($currentColor, $targetColor);
    $path = $colorManager->getTransitionPath($currentColor, $targetColor);
    $intervalSeconds = max(1, (int) (getenv('INTERVAL_TIME') ?: 2));

    $logger->log('INFO', 'Color transition started', [
        'entity_id' => $entityId,
        'device_was_on' => $isOn,
        'current_color_provided' => $isOn ? $currentColor : null,
        'assumed_start_color' => $currentColor,
        'target_color' => $targetColor,
        'cycles' => $cycles,
        'interval_seconds' => $intervalSeconds,
        'path' => $path
    ], 'Transition');

    for ($cycle = 1; $cycle <= $cycles; $cycle++) {
        $ha->turnOff($entityId);
        sleep($intervalSeconds);
        $ha->turnOn($entityId);

        $logger->log('SUCCESS', 'Color cycle completed', [
            'cycle' => $cycle,
            'total_cycles' => $cycles,
            'reached_color' => $path[$cycle]
        ], 'Transition');
    }

    $logger->log('SUCCESS', 'Color transition completed', [
        'entity_id' => $entityId,
        'target_color' => $targetColor,
        'cycles' => $cycles
    ], 'Transition');

    echo json_encode([
        'success' => true,
        'message' => 'Cor aplicada com sucesso',
        'data' => [
            'target_color' => $targetColor,
            'cycles' => $cycles,
            'path' => $path
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->log('ERROR', 'Failed to apply color', ['error' => $e->getMessage()], 'Transition');
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
