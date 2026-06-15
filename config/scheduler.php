<?php
/**
 * ============================================================================
 * SCHEDULER - Timer and Event Scheduling System
 * ============================================================================
 * Manages scheduled events for turning on/off and color transitions.
 * Persists schedules to JSON for durability across restarts.
 */

class Scheduler {
    /**
     * @var string Path to timers JSON file
     */
    private $timersFile;
    
    /**
     * @var Logger Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     * 
     * @param string $dataPath Path to data directory
     * @param Logger $logger Logger instance
     */
    public function __construct($dataPath, Logger $logger) {
        $this->timersFile = $dataPath . '/timers.json';
        $this->logger = $logger;
    }
    
    /**
     * Create a new timer
     * 
     * @param string $id Unique timer identifier
     * @param string $type Type: 'on' or 'off'
     * @param string $time Time in HH:MM format
     * @param bool $enabled Timer enabled
     * @param string|null $targetColor Target color (for 'on' timers)
     * @return bool True on success
     */
    public function createTimer($id, $type, $time, $enabled = true, $targetColor = null) {
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }
        
        // Validate type
        if (!in_array($type, ['on', 'off'])) {
            return false;
        }
        
        $timers = $this->getTimers();
        
        $timers[$id] = [
            'id' => $id,
            'type' => $type,
            'time' => $time,
            'enabled' => $enabled,
            'target_color' => $type === 'on' ? ($targetColor ?? 'colorido') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'last_executed' => null
        ];
        
        $saved = saveJson($this->timersFile, $timers);
        
        if ($saved) {
            $this->logger->log('INFO', "Timer created: {$id}", [
                'type' => $type,
                'time' => $time,
                'target_color' => $targetColor
            ], 'Scheduler');
        }
        
        return $saved;
    }
    
    /**
     * Update timer
     * 
     * @param string $id Timer identifier
     * @param array $updates Fields to update
     * @return bool True on success
     */
    public function updateTimer($id, $updates) {
        $timers = $this->getTimers();
        
        if (!isset($timers[$id])) {
            return false;
        }
        
        $timers[$id] = array_merge($timers[$id], $updates);
        $saved = saveJson($this->timersFile, $timers);
        
        if ($saved) {
            $this->logger->log('INFO', "Timer updated: {$id}", $updates, 'Scheduler');
        }
        
        return $saved;
    }
    
    /**
     * Delete timer
     * 
     * @param string $id Timer identifier
     * @return bool True on success
     */
    public function deleteTimer($id) {
        $timers = $this->getTimers();
        
        if (!isset($timers[$id])) {
            return false;
        }
        
        unset($timers[$id]);
        $saved = saveJson($this->timersFile, $timers);
        
        if ($saved) {
            $this->logger->log('INFO', "Timer deleted: {$id}", [], 'Scheduler');
        }
        
        return $saved;
    }
    
    /**
     * Get all timers
     * 
     * @return array Array of timer objects
     */
    public function getTimers() {
        return loadJson($this->timersFile, []);
    }
    
    /**
     * Get single timer by ID
     * 
     * @param string $id Timer identifier
     * @return array|null Timer object or null
     */
    public function getTimer($id) {
        $timers = $this->getTimers();
        return $timers[$id] ?? null;
    }
    
    /**
     * Get timers to execute now
     * 
     * Returns enabled timers whose scheduled time matches current time.
     * Only returns timers that haven't been executed in the last hour.
     * 
     * @return array Array of timers to execute
     */
    public function getPendingTimers() {
        $timers = $this->getTimers();
        $pending = [];
        
        $currentTime = date('H:i');
        $now = time();
        
        foreach ($timers as $id => $timer) {
            // Skip disabled timers
            if (!$timer['enabled']) {
                continue;
            }
            
            // Check if time matches
            if ($timer['time'] !== $currentTime) {
                continue;
            }
            
            // Check if already executed recently (within 1 hour)
            $lastExecuted = $timer['last_executed'];
            if ($lastExecuted) {
                $lastTime = strtotime($lastExecuted);
                if (($now - $lastTime) < 3600) {
                    continue;
                }
            }
            
            $pending[] = $timer;
        }
        
        return $pending;
    }
    
    /**
     * Mark timer as executed
     * 
     * @param string $id Timer identifier
     * @return bool True on success
     */
    public function markExecuted($id) {
        return $this->updateTimer($id, [
            'last_executed' => date('Y-m-d H:i:s')
        ]);
    }
}
