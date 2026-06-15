<?php
/**
 * ============================================================================
 * HOME ASSISTANT CLIENT - Communication with Home Assistant
 * ============================================================================
 */

class HomeAssistantClient {
    private $baseUrl;
    private $token;
    private $logger;
    private $timeout = 10;
    
    public function __construct($baseUrl, $token, $logger = null) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->logger = $logger;
    }
    
    /**
     * Check if entity is on
     */
    public function isOn($entityId) {
        try {
            $state = $this->getState($entityId);
            return $state === 'on';
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to check entity state: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Turn on entity
     */
    public function turnOn($entityId) {
        return $this->callEntityService($entityId, 'turn_on');
    }
    
    /**
     * Turn off entity
     */
    public function turnOff($entityId) {
        return $this->callEntityService($entityId, 'turn_off');
    }

    /**
     * Call a service using the entity domain (light, switch, etc.).
     */
    private function callEntityService($entityId, $service) {
        $parts = explode('.', $entityId, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new Exception('Invalid Home Assistant entity ID: ' . $entityId);
        }

        return $this->callService($parts[0], $service, ['entity_id' => $entityId]);
    }
    
    /**
     * Get entity state
     */
    private function getState($entityId) {
        $url = "{$this->baseUrl}/api/states/{$entityId}";
        
        $response = $this->makeRequest('GET', $url);
        
        if (isset($response['state'])) {
            return $response['state'];
        }
        
        return null;
    }
    
    /**
     * Call Home Assistant service
     */
    private function callService($domain, $service, $data = []) {
        $url = "{$this->baseUrl}/api/services/{$domain}/{$service}";

        $this->log('INFO', 'Home Assistant service call started', [
            'domain' => $domain,
            'service' => $service,
            'entity_id' => $data['entity_id'] ?? null
        ]);
        
        // Home Assistant may return an empty JSON array for a successful
        // service call. makeRequest already throws for non-2xx responses.
        $this->makeRequest('POST', $url, $data);

        $this->log('SUCCESS', 'Home Assistant service call completed', [
            'domain' => $domain,
            'service' => $service,
            'entity_id' => $data['entity_id'] ?? null
        ]);

        return true;
    }
    
    /**
     * Make HTTP request
     */
    private function makeRequest($method, $url, $data = null) {
        $startedAt = microtime(true);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$this->token}",
                    'Accept: application/json'
                ],
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ];
        
        if ($data && in_array($method, ['POST', 'PUT'])) {
            $options['http']['content'] = json_encode($data);
        }
        
        try {
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception('Failed to connect to Home Assistant');
            }

            $statusCode = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
                $statusCode = (int) $matches[1];
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                $decodedError = json_decode($response, true);
                $detail = is_array($decodedError)
                    ? ($decodedError['message'] ?? json_encode($decodedError))
                    : trim($response);
                throw new Exception("Home Assistant returned HTTP {$statusCode}" . ($detail ? ": {$detail}" : ''));
            }

            $this->log($method === 'GET' ? 'DEBUG' : 'INFO', 'Home Assistant HTTP response received', [
                'method' => $method,
                'path' => $urlPath,
                'status' => $statusCode,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2)
            ]);
            
            $decoded = json_decode($response, true);
            return $decoded !== null ? $decoded : [];
        } catch (Exception $e) {
            $this->log('ERROR', 'Home Assistant HTTP request failed', [
                'method' => $method,
                'path' => $urlPath,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Log message
     */
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->log($level, $message, $context, 'HomeAssistant');
        }
    }
}
