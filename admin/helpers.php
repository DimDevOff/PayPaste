<?php
/**
 * Admin shared helper functions.
 *
 * Included by admin pages via require_once.
 */

if (!function_exists('buildUrl')) {
    /**
     * Build a query string URL for pagination and filters.
     *
     * Preserves all current $_GET parameters and merges/overrides
     * with the provided $params. Empty values are stripped.
     *
     * @param array $params Query parameters to set or override.
     * @return string Query string starting with '?'.
     */
    function buildUrl(array $params): string {
        // Start with current query parameters (filter empty values)
        $base = array_filter($_GET, fn($v) => $v !== '' && $v !== null);
        // Merge overrides on top
        $merged = array_merge($base, $params);
        // Final cleanup
        $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
        return '?' . http_build_query($merged);
    }
}
