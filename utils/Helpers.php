<?php
/**
 * ============================================================================
 * Helpers - General Helper Functions (Procedural)
 * StudyFlow - Student Self-Teaching App
 *
 * A collection of global helper functions for URL generation, form helpers,
 * debugging, string manipulation, date formatting, XP/level calculation,
 * and other commonly needed utilities.
 *
 * This file should be included early in the bootstrap process so that
 * helpers are available everywhere.
 * ============================================================================
 */

// Guard against double-inclusion
if (defined('STUDYFLOW_HELPERS_LOADED')) {
    return;
}
define('STUDYFLOW_HELPERS_LOADED', true);

// =============================================================================
// URL & Redirect Helpers
// =============================================================================

/**
 * Redirect to a URL and terminate.
 *
 * If the path does not start with "http" it is prefixed with the
 * application's base URL from the config.
 *
 * @param string $url  Path or full URL
 * @param int    $code HTTP status code (302 by default)
 *
 * @return never
 */
function redirect(string $url, int $code = 302): never
{
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        $url = url($url);
    }
    header("Location: {$url}", true, $code);
    exit;
}

/**
 * Generate a URL for a static asset.
 *
 * @param string $path Relative path inside the assets directory
 *
 * @return string Full asset URL
 */
function asset(string $path): string
{
    $base = _base_url();
    $path = ltrim($path, '/');
    return "{$base}/assets/{$path}";
}

/**
 * Generate a full URL from an application path.
 *
 * @param string $path Application path (e.g. "/dashboard")
 *
 * @return string
 */
function url(string $path = ''): string
{
    $base = _base_url();
    $path = ltrim($path, '/');
    return $path !== '' ? "{$base}/{$path}" : $base;
}

// =============================================================================
// Form Helpers
// =============================================================================

/**
 * Retrieve old form input stored in session flash data.
 *
 * @param string $field   Field name
 * @param mixed  $default Default value if not present
 *
 * @return mixed
 */
function old(string $field, mixed $default = ''): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return $default;
    }

    return $_SESSION['_old_input'][$field] ?? $default;
}

/**
 * Generate a hidden input element containing a CSRF token.
 *
 * @return string HTML hidden input
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Get (or generate) the current CSRF token from the session.
 *
 * @return string
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

// =============================================================================
// Debugging
// =============================================================================

/**
 * Dump a variable and terminate (dump & die).
 *
 * @param mixed ...$vars One or more variables to inspect
 *
 * @return never
 */
function dd(mixed ...$vars): never
{
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:16px;font-size:14px;overflow:auto;border-radius:8px;">';
    foreach ($vars as $var) {
        var_dump($var);
        echo "\n";
    }
    echo '</pre>';
    exit(1);
}

// =============================================================================
// String Helpers
// =============================================================================

/**
 * Escape a string for safe HTML output.
 *
 * @param string|null $string
 *
 * @return string
 */
function e(?string $string): string
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Check whether a string contains any of the given needles.
 *
 * @param string   $haystack
 * @param string[] $needles
 *
 * @return bool
 */
function str_contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

/**
 * Truncate a string to the given length, appending an ellipsis if needed.
 *
 * @param string $string
 * @param int    $length Maximum length including suffix
 * @param string $suffix Appended when truncated
 *
 * @return string
 */
function truncate(string $string, int $length = 100, string $suffix = '…'): string
{
    if (mb_strlen($string, 'UTF-8') <= $length) {
        return $string;
    }

    return mb_substr($string, 0, $length - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
}

// =============================================================================
// Date & Time Helpers
// =============================================================================

/**
 * Return a human-readable "time ago" string from a timestamp or date string.
 *
 * @param string|int $timestamp Unix timestamp or parseable date string
 *
 * @return string e.g. "3 minutes ago", "2 days ago"
 */
function time_ago(string|int $timestamp): string
{
    $time = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);
    if ($time === false) {
        return 'unknown';
    }

    $diff = time() - $time;

    if ($diff < 0) {
        return 'just now';
    }

    $intervals = [
        ['year',   31536000],
        ['month',  2592000],
        ['week',   604800],
        ['day',    86400],
        ['hour',   3600],
        ['minute', 60],
        ['second', 1],
    ];

    foreach ($intervals as [$label, $seconds]) {
        $count = (int) floor($diff / $seconds);
        if ($count >= 1) {
            $plural = $count !== 1 ? 's' : '';
            return "{$count} {$label}{$plural} ago";
        }
    }

    return 'just now';
}

/**
 * Format a duration in seconds into a human-readable study-duration string.
 *
 * @param int $seconds Duration in seconds
 *
 * @return string e.g. "1h 23m", "45m", "2h"
 */
function format_duration(int $seconds): string
{
    if ($seconds < 0) {
        $seconds = 0;
    }

    $hours   = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);

    if ($hours > 0 && $minutes > 0) {
        return "{$hours}h {$minutes}m";
    }
    if ($hours > 0) {
        return "{$hours}h";
    }
    if ($minutes > 0) {
        return "{$minutes}m";
    }

    return "{$seconds}s";
}

/**
 * Format a date string or timestamp with a given format.
 *
 * @param string|int $date   Date string or Unix timestamp
 * @param string     $format PHP date() format
 *
 * @return string
 */
function format_date(string|int $date, string $format = 'M j, Y'): string
{
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    if ($ts === false) {
        return (string) $date;
    }
    return date($format, $ts);
}

// =============================================================================
// Color & UI Helpers
// =============================================================================

/**
 * Generate a deterministic HSL colour string from a seed string.
 *
 * Useful for assigning consistent avatar or subject colours.
 *
 * @param string $seed  Any string (e.g. username, subject name)
 * @param int    $s     Saturation percentage (0-100)
 * @param int    $l     Lightness percentage (0-100)
 *
 * @return string CSS hsl() value
 */
function generate_color(string $seed, int $s = 65, int $l = 55): string
{
    $hash = crc32($seed);
    $hue  = abs($hash) % 360;
    return "hsl({$hue}, {$s}%, {$l}%)";
}

// =============================================================================
// Content Helpers
// =============================================================================

/**
 * Estimate reading time for a block of text.
 *
 * Uses an average reading speed of 200 words per minute.
 *
 * @param string $text Body text
 * @param int    $wpm  Words per minute
 *
 * @return int Estimated reading time in minutes (minimum 1)
 */
function calculate_reading_time(string $text, int $wpm = 200): int
{
    $wordCount = str_word_count(strip_tags($text));
    $minutes   = (int) ceil($wordCount / max($wpm, 1));
    return max($minutes, 1);
}

// =============================================================================
// Gamification Helpers
// =============================================================================

/**
 * Calculate the level from total XP points.
 *
 * Each level requires progressively more XP:
 *   Level 1:   0 XP
 *   Level 2: 100 XP
 *   Level 3: 300 XP
 *   Level 4: 600 XP  (formula: level * 100 XP to next level)
 *   ...
 *
 * @param int $xp Total experience points
 *
 * @return array{level: int, current_xp: int, xp_for_next: int, progress: float}
 */
function calculate_xp_level(int $xp): array
{
    $level      = 1;
    $cumulative = 0;

    while (true) {
        $needed = $level * 100;
        if ($cumulative + $needed > $xp) {
            break;
        }
        $cumulative += $needed;
        $level++;
    }

    $xpIntoLevel = $xp - $cumulative;
    $xpForNext   = $level * 100;
    $progress    = $xpForNext > 0 ? round($xpIntoLevel / $xpForNext * 100, 1) : 0;

    return [
        'level'       => $level,
        'current_xp'  => $xpIntoLevel,
        'xp_for_next' => $xpForNext,
        'progress'    => $progress,
    ];
}

// =============================================================================
// Formatting Helpers
// =============================================================================

/**
 * Format a byte count into a human-readable string.
 *
 * @param int $bytes
 * @param int $precision Decimal places
 *
 * @return string e.g. "1.5 MB"
 */
function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow   = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $pow   = min($pow, count($units) - 1);

    $value = $bytes / (1024 ** $pow);

    return round($value, $precision) . ' ' . $units[$pow];
}

/**
 * Get the ordinal suffix for a number (1st, 2nd, 3rd, etc.).
 *
 * @param int $number
 *
 * @return string e.g. "1st", "22nd", "113th"
 */
function ordinal(int $number): string
{
    $abs = abs($number);

    // Special cases for 11, 12, 13
    if (($abs % 100) >= 11 && ($abs % 100) <= 13) {
        return "{$number}th";
    }

    return $number . match ($abs % 10) {
        1       => 'st',
        2       => 'nd',
        3       => 'rd',
        default => 'th',
    };
}

/**
 * Calculate percentage safely (avoids division by zero).
 *
 * @param float|int $part
 * @param float|int $whole
 * @param int       $precision Decimal places
 *
 * @return float
 */
function percentage(float|int $part, float|int $whole, int $precision = 1): float
{
    if ($whole == 0) {
        return 0.0;
    }
    return round(($part / $whole) * 100, $precision);
}

// =============================================================================
// Array Helpers
// =============================================================================

/**
 * Access a nested array value using dot notation.
 *
 * @param array  $array   Source array
 * @param string $dotKey  Dot-separated key (e.g. "user.profile.name")
 * @param mixed  $default Returned when the key does not exist
 *
 * @return mixed
 */
function array_get(array $array, string $dotKey, mixed $default = null): mixed
{
    if (array_key_exists($dotKey, $array)) {
        return $array[$dotKey];
    }

    $keys = explode('.', $dotKey);

    foreach ($keys as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return $default;
        }
        $array = $array[$segment];
    }

    return $array;
}

// =============================================================================
// Internal
// =============================================================================

/**
 * Retrieve the application's base URL from config or fallback.
 *
 * @return string
 */
function _base_url(): string
{
    // Attempt to load from config
    static $baseUrl = null;

    if ($baseUrl === null) {
        $configFile = dirname(__DIR__) . '/config/app.php';
        if (file_exists($configFile)) {
            $config  = require $configFile;
            $baseUrl = rtrim($config['base_url'] ?? '', '/');
        } else {
            $baseUrl = '';
        }
    }

    return $baseUrl;
}
