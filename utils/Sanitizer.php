<?php
/**
 * ============================================================================
 * Sanitizer - Input Sanitization
 * StudyFlow - Student Self-Teaching App
 *
 * Provides methods to sanitize user input before storage or display.
 * Handles plain text, rich HTML, emails, URLs, filenames, markdown,
 * JavaScript escaping, and URL slug generation.
 *
 * All methods are static for convenience.
 * ============================================================================
 */

class Sanitizer
{
    /**
     * Default HTML tags allowed in rich-text fields.
     *
     * @var string[]
     */
    private static array $safeTags = [
        'p', 'br', 'b', 'i', 'u', 'em', 'strong', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'span',
        'sub', 'sup', 'mark', 'del', 'ins', 'figure', 'figcaption',
    ];

    /**
     * Attributes allowed on safe HTML tags.
     *
     * @var string[]
     */
    private static array $safeAttributes = [
        'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel',
        'width', 'height', 'colspan', 'rowspan',
    ];

    // -------------------------------------------------------------------------
    // General Sanitization
    // -------------------------------------------------------------------------

    /**
     * Recursively sanitize input (trim whitespace, encode HTML entities).
     *
     * Works on strings, arrays, and nested structures.
     *
     * @param mixed $input
     * @return mixed
     */
    public static function clean(mixed $input): mixed
    {
        if (is_string($input)) {
            $input = trim($input);
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return $input;
        }

        if (is_array($input)) {
            return array_map([static::class, 'clean'], $input);
        }

        // Booleans, integers, floats, null pass through unchanged
        return $input;
    }

    /**
     * Sanitize HTML while allowing a curated list of safe tags.
     *
     * Strips dangerous attributes (event handlers, javascript: URIs)
     * but keeps structural and formatting markup.
     *
     * @param string $input Raw HTML
     * @return string Sanitized HTML
     */
    public static function cleanHtml(string $input): string
    {
        // Build allowed tags string for strip_tags
        $allowedTagStr = implode('', array_map(fn($t) => "<{$t}>", static::$safeTags));

        $input = strip_tags($input, $allowedTagStr);

        // Remove dangerous attributes (on* event handlers, style with expressions)
        $input = preg_replace(
            '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $input
        );

        // Remove javascript: and data: protocol links
        $input = preg_replace(
            '/\b(href|src|action)\s*=\s*(?:"(?:javascript|data|vbscript):[^"]*"|\'(?:javascript|data|vbscript):[^\']*\')/i',
            '$1=""',
            $input
        );

        // Strip any attributes not in the safe list
        $input = preg_replace_callback(
            '/<(\w+)((?:\s+[^>]*)?)>/i',
            function ($matches) {
                $tag       = $matches[1];
                $attrBlock = $matches[2];

                if (trim($attrBlock) === '') {
                    return "<{$tag}>";
                }

                // Parse individual attributes
                preg_match_all(
                    '/\s+([\w-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/',
                    $attrBlock,
                    $attrs,
                    PREG_SET_ORDER
                );

                $safe = '';
                foreach ($attrs as $attr) {
                    $name  = strtolower($attr[1]);
                    $value = $attr[2] ?? $attr[3] ?? $attr[4] ?? '';

                    if (in_array($name, static::$safeAttributes, true)) {
                        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $safe .= " {$name}=\"{$value}\"";
                    }
                }

                return "<{$tag}{$safe}>";
            },
            $input
        );

        return $input;
    }

    // -------------------------------------------------------------------------
    // Specific Type Sanitizers
    // -------------------------------------------------------------------------

    /**
     * Sanitize an email address.
     *
     * @param string $email
     * @return string Sanitized email or empty string if invalid
     */
    public static function cleanEmail(string $email): string
    {
        $email = trim($email);
        $email = strtolower($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if ($email === false) {
            return '';
        }

        // Final validation
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $email;
    }

    /**
     * Sanitize a URL.
     *
     * @param string $url
     * @return string Sanitized URL or empty string if invalid
     */
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if ($url === false) {
            return '';
        }

        // Only allow http and https schemes
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    /**
     * Sanitize a filename for safe storage on the file system.
     *
     * Removes path components, special characters, and limits length.
     *
     * @param string $filename Original filename
     * @return string Safe filename
     */
    public static function cleanFilename(string $filename): string
    {
        // Strip path components
        $filename = basename($filename);

        // Separate name and extension
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Remove non-alphanumeric characters except hyphens and underscores
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_.');

        if ($name === '') {
            $name = 'file_' . time();
        }

        // Limit name length
        $name = mb_substr($name, 0, 200, 'UTF-8');

        // Clean extension
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);

        return $ext !== '' ? "{$name}.{$ext}" : $name;
    }

    /**
     * Sanitize markdown content.
     *
     * Allows standard markdown syntax while removing potentially
     * dangerous HTML and script injections embedded in markdown.
     *
     * @param string $markdown Raw markdown
     * @return string Sanitized markdown
     */
    public static function cleanMarkdown(string $markdown): string
    {
        // Remove raw <script>, <style>, <iframe>, <object>, <embed> tags
        $dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input'];
        foreach ($dangerousTags as $tag) {
            $markdown = preg_replace(
                '/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is',
                '',
                $markdown
            );
            $markdown = preg_replace(
                '/<' . $tag . '\b[^>]*\/?>/is',
                '',
                $markdown
            );
        }

        // Remove on* event handlers that may be embedded in HTML within markdown
        $markdown = preg_replace(
            '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $markdown
        );

        // Remove javascript: protocol links in markdown link syntax
        $markdown = preg_replace(
            '/\[([^\]]*)\]\(javascript:[^)]*\)/i',
            '[$1](#)',
            $markdown
        );

        // Remove data: URIs from image markdown
        $markdown = preg_replace(
            '/!\[([^\]]*)\]\(data:[^)]*\)/i',
            '![$1](#)',
            $markdown
        );

        return trim($markdown);
    }

    /**
     * Strip HTML tags, optionally allowing specific ones.
     *
     * @param string      $input   HTML string
     * @param string|null $allowed Comma-separated list of allowed tags (e.g. "p,b,i")
     * @return string
     */
    public static function stripTags(string $input, ?string $allowed = null): string
    {
        if ($allowed === null) {
            return strip_tags($input);
        }

        $tags = array_map('trim', explode(',', $allowed));
        $allowedStr = implode('', array_map(fn($t) => "<{$t}>", $tags));

        return strip_tags($input, $allowedStr);
    }

    /**
     * Escape a string for safe inclusion in JavaScript.
     *
     * @param string $input
     * @return string
     */
    public static function escapeJs(string $input): string
    {
        $replacements = [
            "\\"   => "\\\\",
            "'"    => "\\'",
            '"'    => '\\"',
            "\n"   => "\\n",
            "\r"   => "\\r",
            "\t"   => "\\t",
            "</"   => "<\\/",  // Prevent closing script tags
            "\x00" => "\\0",
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $input
        );
    }

    // -------------------------------------------------------------------------
    // Array / Batch Sanitization
    // -------------------------------------------------------------------------

    /**
     * Sanitize an array of values using per-field rules.
     *
     * Rules map field names to sanitizer method names:
     *   ['email' => 'cleanEmail', 'bio' => 'cleanHtml', 'name' => 'clean']
     *
     * @param array $array Input data
     * @param array $rules Field => method mapping
     * @return array Sanitized data
     */
    public static function sanitizeArray(array $array, array $rules): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (isset($rules[$key])) {
                $method = $rules[$key];
                if (method_exists(static::class, $method)) {
                    $result[$key] = static::$method($value);
                } else {
                    $result[$key] = static::clean($value);
                }
            } else {
                // Default: basic clean
                $result[$key] = static::clean($value);
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // String Utilities
    // -------------------------------------------------------------------------

    /**
     * Create a URL-safe slug from a string.
     *
     * @param string $string  Input text
     * @param string $separator Word separator (default: '-')
     * @return string URL slug
     */
    public static function slug(string $string, string $separator = '-'): string
    {
        // Transliterate common accented characters
        $translitMap = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ];

        $string = strtr($string, $translitMap);

        // Convert to lowercase
        $string = mb_strtolower($string, 'UTF-8');

        // Replace non-alphanumeric characters with the separator
        $string = preg_replace('/[^a-z0-9]+/', $separator, $string);

        // Collapse multiple separators
        $string = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $string);

        // Trim separators from ends
        return trim($string, $separator);
    }
}
