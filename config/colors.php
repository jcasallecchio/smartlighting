<?php

class ColorManager {
    private $colors = [
        'colorido',
        'rosa',
        'azul',
        'vermelho',
        'verde',
        'ciano',
        'roxo',
        'amarelo',
        'branco'
    ];

    private $sequence = [];

    public function __construct() {
        $configured = getenv('COLOR_SEQUENCE') ?: implode(',', $this->colors);
        $sequence = array_values(array_filter(array_map(function($color) {
            return strtolower(trim($color));
        }, explode(',', $configured))));

        if (count($sequence) !== count($this->colors) ||
            count(array_unique($sequence)) !== count($this->colors) ||
            array_diff($this->colors, $sequence)) {
            throw new RuntimeException('COLOR_SEQUENCE must contain every supported color exactly once.');
        }

        $this->sequence = $sequence;
    }

    public function isValidColor($color) {
        return in_array(strtolower((string) $color), $this->colors, true);
    }

    public function getCycleCount($fromColor, $toColor) {
        $from = strtolower((string) $fromColor);
        $to = strtolower((string) $toColor);

        if (!$this->isValidColor($from) || !$this->isValidColor($to)) {
            throw new InvalidArgumentException('Invalid current or target color.');
        }

        $fromIndex = array_search($from, $this->sequence, true);
        $toIndex = array_search($to, $this->sequence, true);

        return ($toIndex - $fromIndex + count($this->sequence)) % count($this->sequence);
    }

    public function getTransitionPath($fromColor, $toColor) {
        $cycles = $this->getCycleCount($fromColor, $toColor);
        $fromIndex = array_search(strtolower($fromColor), $this->sequence, true);
        $path = [];

        for ($step = 0; $step <= $cycles; $step++) {
            $path[] = $this->sequence[($fromIndex + $step) % count($this->sequence)];
        }

        return $path;
    }
}
