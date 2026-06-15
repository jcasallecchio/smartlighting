<?php

require_once dirname(dirname(dirname(__FILE__))) . '/config/bootstrap.php';
require_once CONFIG_PATH . '/webhook.php';

header('Content-Type: application/json');

function nextTimestampForTime($time) {
    if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time) !== 1) {
        throw new InvalidArgumentException('Horário inválido.');
    }

    $now = new DateTimeImmutable('now');
    $scheduled = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $time . ':00');
    if ($scheduled <= $now) {
        $scheduled = $scheduled->modify('+1 day');
    }
    return $scheduled->getTimestamp();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $actions = $input['actions'] ?? [];
    if (!is_array($actions) || count($actions) === 0) {
        http_response_code(400);
        throw new InvalidArgumentException('Selecione ao menos uma programação.');
    }

    $colorManager = new ColorManager();
    $client = new TimerWebhookClient(
        getenv('TIMER_WEBHOOK_URL'),
        $logger,
        10,
        getenv('TIMER_WEBHOOK_USERNAME'),
        getenv('TIMER_WEBHOOK_PASSWORD')
    );
    $payloads = [];
    $requestInterval = max(0, (int) (getenv('TIMER_WEBHOOK_INTERVAL') ?: 2));

    foreach ($actions as $action) {
        $type = $action['type'] ?? '';

        if ($type === 'on') {
            $timestamp = nextTimestampForTime($action['time'] ?? '');
            $color = strtolower(trim($action['color'] ?? ''));
            if (!$colorManager->isValidColor($color)) {
                throw new InvalidArgumentException('Cor inválida na programação de ligar.');
            }
            $payloads[] = [1, $timestamp, $color];
        } elseif ($type === 'off') {
            $timestamp = nextTimestampForTime($action['time'] ?? '');
            $payloads[] = [0, $timestamp];
        } elseif ($type === 'cancel_on') {
            $payloads[] = [1];
        } elseif ($type === 'cancel_off') {
            $payloads[] = [0];
        } else {
            throw new InvalidArgumentException('Tipo de programação inválido.');
        }
    }

    $sent = [];
    foreach ($payloads as $index => $payload) {
        if ($index > 0 && $requestInterval > 0) {
            $logger->log('INFO', 'Waiting before next timer webhook request', [
                'interval_seconds' => $requestInterval,
                'request_number' => $index + 1,
                'total_requests' => count($payloads)
            ], 'Scheduler');
            sleep($requestInterval);
        }

        $client->send($payload);
        $sent[] = $payload;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Programação enviada com sucesso',
        'data' => [
            'payloads' => $sent,
            'interval_seconds' => count($sent) > 1 ? $requestInterval : 0
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $logger->log('ERROR', 'Failed to submit timer webhook', ['error' => $e->getMessage()], 'Scheduler');
    if (http_response_code() < 400) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
