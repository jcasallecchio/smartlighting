<?php
/**
 * ============================================================================
 * CRON JOB - Execute Scheduled Timers
 * ============================================================================
 * Path: /cron/execute_timers.php
 * 
 * This script should be called every minute by the system cron:
 * * * * * * php /path/to/cron/execute_timers.php
 * 
 * Checks for pending timers and executes them.
 * Also handles color transition steps for on-timers.
 */

require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

// Prevent concurrent execution
$lockFile = TMP_PATH . '/timer_execution.lock';
if (!acquireLock($lockFile, 60)) {
    $logger->log('WARNING', 'Timer execution already in progress', [], 'Cron');
    exit(1);
}

try {
    $scheduler = new Scheduler(DATA_PATH, $logger);
    $ha = new HomeAssistantClient(getenv('HA_URL'), getenv('HA_TOKEN'), $logger);
    $colorManager = new ColorManager();
    $entityId = getenv('ENTITY_ID');
    $intervalTime = intval(getenv('INTERVAL_TIME', 30));
    
    // Get pending timers to execute
    $pendingTimers = $scheduler->getPendingTimers();
    
    if (empty($pendingTimers)) {
        releaseLock($lockFile);
        exit(0);
    }
    
    $logger->log('INFO', 'Executing pending timers', ['count' => count($pendingTimers)], 'Cron');
    
    // Process each pending timer
    foreach ($pendingTimers as $timer) {
        try {
            if ($timer['type'] === 'on') {
                // Turn on with target color
                $targetColor = $timer['target_color'] ?? 'colorido';
                
                // Get current state
                $state = loadJson(DATA_PATH . '/state.json', [
                    'state' => 'off',
                    'current_color' => 'colorido'
                ]);
                
                if ($state['state'] !== 'on') {
                    // Device is off, need to turn on
                    $success = $ha->turnOn($entityId);
                    
                    if ($success) {
                        // Update state and progress
                        $currentColor = $state['current_color'] ?? 'colorido';
                        $transitionPath = $colorManager->getTransitionPath($currentColor, $targetColor);
                        $cyclesNeeded = count($transitionPath) - 1;
                        
                        $state['state'] = 'on';
                        $state['last_updated'] = date('Y-m-d H:i:s');
                        saveJson(DATA_PATH . '/state.json', $state);
                        
                        $progress = [
                            'is_executing' => $cyclesNeeded > 0,
                            'current_step' => 0,
                            'total_steps' => $cyclesNeeded,
                            'from_color' => $currentColor,
                            'to_color' => $targetColor,
                            'path' => $transitionPath,
                            'started_at' => date('Y-m-d H:i:s'),
                            'estimated_completion' => $cyclesNeeded > 0 
                                ? date('Y-m-d H:i:s', strtotime("+" . ($cyclesNeeded * $intervalTime) . " seconds"))
                                : null
                        ];
                        saveJson(DATA_PATH . '/progress.json', $progress);
                        
                        $logger->log('INFO', "Timer executed: {$timer['id']} (ON)", [
                            'target_color' => $targetColor,
                            'cycles' => $cyclesNeeded
                        ], 'Cron');
                    }
                } else {
                    $logger->log('INFO', "Timer skipped: {$timer['id']} (already on)", [], 'Cron');
                }
                
            } else if ($timer['type'] === 'off') {
                // Turn off device
                $success = $ha->turnOff($entityId);
                
                if ($success) {
                    $state = loadJson(DATA_PATH . '/state.json', ['state' => 'off']);
                    $state['state'] = 'off';
                    $state['last_updated'] = date('Y-m-d H:i:s');
                    saveJson(DATA_PATH . '/state.json', $state);
                    
                    // Cancel any ongoing transition
                    $progress = loadJson(DATA_PATH . '/progress.json', ['is_executing' => false]);
                    $progress['is_executing'] = false;
                    saveJson(DATA_PATH . '/progress.json', $progress);
                    
                    $logger->log('INFO', "Timer executed: {$timer['id']} (OFF)", [], 'Cron');
                }
            }
            
            // Mark timer as executed
            $scheduler->markExecuted($timer['id']);
            
        } catch (Exception $e) {
            $logger->log('ERROR', "Failed to execute timer {$timer['id']}: " . $e->getMessage(), [], 'Cron');
        }
    }
    
    releaseLock($lockFile);
    exit(0);
    
} catch (Exception $e) {
    $logger->log('ERROR', 'Timer execution failed: ' . $e->getMessage(), [], 'Cron');
    releaseLock($lockFile);
    exit(1);
}
