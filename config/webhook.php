<?php

class TimerWebhookClient {
    private $url;
    private $logger;
    private $timeout;
    private $username;
    private $password;

    public function __construct($url, $logger = null, $timeout = 10, $username = '', $password = '') {
        $this->url = trim((string) $url);
        $this->logger = $logger;
        $this->timeout = $timeout;
        $this->username = (string) $username;
        $this->password = (string) $password;
    }

    public function send($payload) {
        if ($this->url === '') {
            throw new RuntimeException('TIMER_WEBHOOK_URL não está configurada no .env.');
        }

        if ($this->username === '' || $this->password === '') {
            throw new RuntimeException('Configure TIMER_WEBHOOK_USERNAME e TIMER_WEBHOOK_PASSWORD no .env.');
        }

        $startedAt = microtime(true);
        $authorization = base64_encode($this->username . ':' . $this->password);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('Não foi possível codificar o payload do webhook.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json; charset=UTF-8\r\n" .
                    "Accept: application/json, text/plain, */*\r\n" .
                    "Authorization: Basic {$authorization}",
                'content' => $jsonPayload,
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($this->url, false, $context);
        $statusCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            if ($statusCode === 401) {
                throw new RuntimeException('Webhook retornou HTTP 401. Verifique o usuário e a senha configurados.');
            }
            throw new RuntimeException("Webhook returned HTTP {$statusCode}");
        }

        if ($this->logger) {
            $this->logger->log('SUCCESS', 'Timer webhook delivered', [
                'payload' => $payload,
                'status' => $statusCode,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2)
            ], 'Scheduler');
        }

        return ['status' => $statusCode, 'response' => $response];
    }
}
