<?php

if (! function_exists('format_cop')) {
    /**
     * Format a numeric amount as Colombian Peso (COP) display string.
     * e.g. 38000 → "$38.000"
     */
    function format_cop(int|float|string $amount): string
    {
        return '$' . number_format((float) $amount, 0, ',', '.');
    }
}
