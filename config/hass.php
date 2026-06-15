<?php
/**
 * ============================================================================
 * HOME ASSISTANT API CLIENT
 * ============================================================================
 * Handles all communication with Home Assistant REST API.
 * Manages authentication, requests, error handling, and logging.
 */

class HomeAssistantClient {
    /**
     * @var string Home Assistant base URL
     */
    private $baseUrl;
    
    /**
     * @var string Authentication token
     */
    private $token;
    
    /**
     * @var Logger Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     * 
     * @param string $baseUrl Home Assistant URL (e.g., http://192.168.1.100:8123)
     * @param string $token Authentication token
     * @param Logger $logger Logger instance
     */
    public function __construct($baseUrl, $token, Logger $logger) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->logger = $logger;
    }
    
    /**
     * Call Home Assistant REST API
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array $data Request data (for POST/PUT)
     * @param int $timeout Request timeout in seconds
     * @return array Response data or null on failure
     */
    private function call($method, $endpoint, $data = null, $timeout = 10) {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        
        // Prepare cURL options
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'User-Agent: SmartLighting/1.0'
            ]
        ]);
        
        // Add request body for POST/PUT requests
        if ($data !== null && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Execute request
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $responseTime = microtime(true) - $startTime;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the request
        $this->logger->log('INFO', "Home Assistant API call: {$method} {$endpoint}", [
            'http_code' => $httpCode,
            'response_time' => number_format($responseTime, 3) . 's',
            'payload_sent' => $data
        ], 'HomeAssistant');
        
        // Handle cURL errors
        if ($error) {
            $this->logger->log('ERROR', "cURL error calling {$endpoint}: {$error}", [], 'HomeAssistant');
            return null;
        }
        
        // Parse JSON response
        $responseData = json_decode($response, true);
        
        // Check HTTP status
        if ($httpCode >= 400) {
            $this->logger->log('ERROR', "Home Assistant API error: {$httpCode}", [
                'endpoint' => $endpoint,
                'response' => $response
            ], 'HomeAssistant');
            return null;
        }
        
        // Log successful response
        $this->logger->log('SUCCESS', "Home Assistant API response received", [
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'response_time' => number_format($responseTime, 3) . 's'
        ], 'HomeAssistant');
        
        return $responseData;
    }
    
    /**
     * Get entity state
     * 
     * @param string $entityId Entity ID (e.g., switch.difusor_socket_1)
     * @return array|null Entity state or null on failure
     */
    public function getEntityState($entityId) {
        return $this->call('GET', "states/{$entityId}");
    }
    
    /**
     * Turn entity on
     * 
     * @param string $entityId Entity ID
     * @return bool True if successful
     */
    public function turnOn($entityId) {
        $response = $this->call('POST', 'services/switch/turn_on', [
            'entity_id' => $entityId
        ]);
        
        return $response !== null;
    }
    
    /**
     * Turn entity off
     * 
     * @param string $entityId Entity ID
     * @return bool True if successful
     */
    public function turnOff($entityId) {
        $response = $this->call('POST', 'services/switch/turn_off', [
            'entity_id' => $entityId
        ]);
        
        return $response !== null;
    }
    
    /**
     * Check if entity is on
     * 
     * @param string $entityId Entity ID
     * @return bool|null True if on, false if off, null if error
     */
    public function isOn($entityId) {
        $state = $this->getEntityState($entityId);
        
        if ($state === null) {
            return null;
        }
        
        return $state['state'] === 'on';
    }
}
