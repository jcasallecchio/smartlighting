<?php
/**
 * ============================================================================
 * COLOR MANAGEMENT - Smart Lighting Color Sequence Handling
 * ============================================================================
 * Manages the color sequence for the Six Senses luminaire.
 * Handles color transitions, cycle calculations, and state persistence.
 *
 * Physical color sequence:
 * 1. Colorido (Colorful)
 * 2. Rosa (Pink)
 * 3. Azul (Blue)
 * 4. Vermelho (Red)
 * 5. Verde (Green)
 * 6. Ciano (Cyan)
 * 7. Roxo (Purple)
 * 8. Amarelo (Yellow)
 *
 * NOTE: The luminaire has no intelligent color control. Color changes are
 * achieved by power cycling (off/on). Each cycle advances to the next color.
 */

class ColorManager {
    /**
     * @var array Official color sequence
     */
    private $colorSequence = [
        'colorido',
        'rosa',
        'azul',
        'vermelho',
        'verde',
        'ciano',
        'roxo',
        'amarelo'
    ];
    
    /**
     * @var array Friendly color names (Portuguese)
     */
    private $colorNames = [
        'colorido' => 'Colorido',
        'rosa' => 'Rosa',
        'azul' => 'Azul',
        'vermelho' => 'Vermelho',
        'verde' => 'Verde',
        'ciano' => 'Ciano',
        'roxo' => 'Roxo',
        'amarelo' => 'Amarelo'
    ];
    
    /**
     * @var array Color hex codes for UI display
     */
    private $colorHexes = [
        'colorido' => '#FF6B6B',
        'rosa' => '#FF69B4',
        'azul' => '#4169E1',
        'vermelho' => '#DC143C',
        'verde' => '#32CD32',
        'ciano' => '#00CED1',
        'roxo' => '#9932CC',
        'amarelo' => '#FFD700'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
    }
    
    /**
     * Get complete color sequence
     * 
     * @return array Array of color names
     */
    public function getSequence() {
        return $this->colorSequence;
    }
    
    /**
     * Get color display information
     * 
     * @return array Array of color objects with name and hex code
     */
    public function getColorInfo() {
        $info = [];
        foreach ($this->colorSequence as $color) {
            $info[] = [
                'id' => $color,
                'name' => $this->colorNames[$color],
                'hex' => $this->colorHexes[$color]
            ];
        }
        return $info;
    }
    
    /**
     * Get color name (Portuguese)
     * 
     * @param string $colorId Color identifier
     * @return string|null Friendly color name or null
     */
    public function getColorName($colorId) {
        return $this->colorNames[$colorId] ?? null;
    }
    
    /**
     * Get color hex code
     * 
     * @param string $colorId Color identifier
     * @return string|null Hex code or null
     */
    public function getColorHex($colorId) {
        return $this->colorHexes[$colorId] ?? null;
    }
    
    /**
     * Get color index in sequence
     * 
     * @param string $colorId Color identifier
     * @return int|null Index (0-7) or null if not found
     */
    public function getColorIndex($colorId) {
        return array_search(strtolower($colorId), $this->colorSequence);
    }
    
    /**
     * Is valid color
     * 
     * @param string $colorId Color identifier
     * @return bool True if valid
     */
    public function isValidColor($colorId) {
        return $this->getColorIndex(strtolower($colorId)) !== false;
    }
    
    /**
     * Get next color in sequence (single cycle)
     * 
     * @param string $currentColor Current color identifier
     * @return string Next color in sequence
     */
    public function getNextColor($currentColor) {
        $index = $this->getColorIndex(strtolower($currentColor));
        if ($index === false) {
            // Invalid color, assume colorido
            $index = 0;
        }
        
        // Move to next, wrap around if necessary (circular sequence)
        $nextIndex = ($index + 1) % count($this->colorSequence);
        return $this->colorSequence[$nextIndex];
    }
    
    /**
     * Calculate number of cycles needed to reach target color
     * 
     * From green to pink requires: green -> cyan -> purple -> yellow -> colorful -> pink = 5 cycles
     * 
     * @param string $fromColor Current color
     * @param string $toColor Target color
     * @return int Number of cycles needed (0 if already at target)
     */
    public function calculateCycles($fromColor, $toColor) {
        $fromColor = strtolower($fromColor);
        $toColor = strtolower($toColor);
        
        // Validate colors
        $fromIndex = $this->getColorIndex($fromColor);
        $toIndex = $this->getColorIndex($toColor);
        
        if ($fromIndex === false || $toIndex === false) {
            return 0;
        }
        
        // If same color, no cycles needed
        if ($fromIndex === $toIndex) {
            return 0;
        }
        
        // Calculate forward distance (circular sequence)
        $cycles = ($toIndex - $fromIndex + count($this->colorSequence)) % count($this->colorSequence);
        
        return $cycles;
    }
    
    /**
     * Get color sequence path from source to destination
     * 
     * @param string $fromColor Starting color
     * @param string $toColor Target color
     * @return array Array of colors in the transition sequence
     */
    public function getTransitionPath($fromColor, $toColor) {
        $fromColor = strtolower($fromColor);
        $toColor = strtolower($toColor);
        
        // Validate colors
        if (!$this->isValidColor($fromColor) || !$this->isValidColor($toColor)) {
            return [];
        }
        
        $fromIndex = $this->getColorIndex($fromColor);
        $toIndex = $this->getColorIndex($toColor);
        
        // If same color, return just that color
        if ($fromIndex === $toIndex) {
            return [$this->colorSequence[$fromIndex]];
        }
        
        // Build path
        $path = [];
        $current = $fromIndex;
        
        while ($current !== $toIndex) {
            $path[] = $this->colorSequence[$current];
            $current = ($current + 1) % count($this->colorSequence);
        }
        
        // Add destination
        $path[] = $this->colorSequence[$toIndex];
        
        return $path;
    }
}
