<?php

class Logger {
    private $logPath;
    private $debugMode;
    private $maxFileSize;
    private $minimumLevel;
    private $requestId;

    private const LEVELS = [
        'DEBUG' => 10,
        'INFO' => 20,
        'SUCCESS' => 25,
        'WARNING' => 30,
        'ERROR' => 40,
        'CRITICAL' => 50
    ];

    public function __construct($logPath, $debugMode = false, $minimumLevel = 'INFO', $maxFileSize = 10485760) {
        $this->logPath = rtrim($logPath, '/\\');
        $this->debugMode = $debugMode;
        $this->minimumLevel = strtoupper($minimumLevel ?: 'INFO');
        $this->maxFileSize = max(1024, (int) $maxFileSize);
        $this->requestId = null;

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0775, true);
        }
    }

    public function setRequestId($requestId) {
        $this->requestId = $requestId;
    }

    public function log($level, $message, $context = [], $category = 'APP') {
        $level = strtoupper($level);
        if (!$this->shouldLog($level)) {
            return false;
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $this->sanitizeContext($context)
        ];

        if ($this->requestId) {
            $entry['request_id'] = $this->requestId;
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $written = $this->writeLog($this->logPath . '/app.log', $line);

        if ($this->debugMode && PHP_SAPI === 'cli') {
            echo $line;
        }

        return $written;
    }

    public function query($filters = [], $limit = 200, $offset = 0) {
        $logFile = $this->logPath . '/app.log';
        $result = [
            'entries' => [],
            'total' => 0,
            'levels' => [],
            'categories' => []
        ];

        if (!file_exists($logFile)) {
            return $result;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $result;
        }

        $matches = [];
        foreach ($lines as $line) {
            $entry = $this->parseLine($line);
            if (!$entry || !$this->matchesFilters($entry, $filters)) {
                continue;
            }

            $matches[] = $entry;
            $result['levels'][$entry['level']] = ($result['levels'][$entry['level']] ?? 0) + 1;
            $result['categories'][$entry['category']] = ($result['categories'][$entry['category']] ?? 0) + 1;
        }

        $matches = array_reverse($matches);
        $result['total'] = count($matches);
        $result['entries'] = array_slice($matches, max(0, $offset), max(1, $limit));
        ksort($result['levels']);
        ksort($result['categories']);

        return $result;
    }

    private function shouldLog($level) {
        $current = self::LEVELS[$level] ?? self::LEVELS['INFO'];
        $minimum = self::LEVELS[$this->minimumLevel] ?? self::LEVELS['INFO'];
        return $current >= $minimum;
    }

    private function sanitizeContext($value, $key = '') {
        $sensitiveKeys = ['token', 'authorization', 'password', 'secret', 'api_key', 'apikey'];
        $normalizedKey = strtolower((string) $key);

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (strpos($normalizedKey, $sensitiveKey) !== false) {
                return '[REDACTED]';
            }
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitizeContext($childValue, $childKey);
            }
            return $clean;
        }

        if (is_object($value)) {
            return $this->sanitizeContext((array) $value, $key);
        }

        if (is_string($value) && strlen($value) > 2000) {
            return substr($value, 0, 2000) . '...[truncated]';
        }

        return $value;
    }

    private function writeLog($filepath, $content) {
        if (file_exists($filepath) && filesize($filepath) >= $this->maxFileSize) {
            $this->rotateLog($filepath);
        }

        return @file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX) !== false;
    }

    private function rotateLog($filepath) {
        $rotatedFile = $filepath . '.' . date('Y-m-d_H-i-s');
        @rename($filepath, $rotatedFile);
    }

    private function parseLine($line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded) && isset($decoded['timestamp'], $decoded['level'], $decoded['category'], $decoded['message'])) {
            $decoded['context'] = $decoded['context'] ?? [];
            return $decoded;
        }

        return null;
    }

    private function matchesFilters($entry, $filters) {
        if (!empty($filters['date']) && strpos($entry['timestamp'], $filters['date']) !== 0) {
            return false;
        }
        if (!empty($filters['level']) && strtoupper($filters['level']) !== $entry['level']) {
            return false;
        }
        if (!empty($filters['category']) && strcasecmp($filters['category'], $entry['category']) !== 0) {
            return false;
        }
        if (!empty($filters['request_id']) && ($entry['request_id'] ?? '') !== $filters['request_id']) {
            return false;
        }
        if (!empty($filters['search'])) {
            $haystack = $entry['message'] . ' ' . json_encode($entry['context'], JSON_UNESCAPED_UNICODE);
            if (stripos($haystack, $filters['search']) === false) {
                return false;
            }
        }
        return true;
    }
}
