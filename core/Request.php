<?php
/**
 * ============================================================================
 * StudyFlow - Request
 * Student Self-Teaching App
 *
 * Wraps PHP superglobals ($_GET, $_POST, $_SERVER, $_FILES) into a clean
 * object-oriented interface. Provides methods for accessing request data,
 * detecting request type, and handling file uploads securely.
 * ============================================================================
 */

class Request
{
    /**
     * GET parameters.
     * @var array
     */
    private array $query;

    /**
     * POST parameters.
     * @var array
     */
    private array $body;

    /**
     * Server variables.
     * @var array
     */
    private array $server;

    /**
     * Uploaded files.
     * @var array
     */
    private array $files;

    /**
     * Cookie data.
     * @var array
     */
    private array $cookies;

    /**
     * Route parameters extracted by the Router.
     * @var array
     */
    private array $routeParams = [];

    /**
     * Raw request body (cached).
     * @var string|null
     */
    private ?string $rawBody = null;

    /**
     * Parsed JSON body (cached).
     * @var array|null
     */
    private ?array $jsonBody = null;

    /**
     * Constructor.
     *
     * Captures all PHP superglobals at construction time so the Request
     * object contains a consistent snapshot of the incoming request.
     */
    public function __construct()
    {
        $this->query   = $_GET;
        $this->body    = $_POST;
        $this->server  = $_SERVER;
        $this->files   = $_FILES;
        $this->cookies = $_COOKIE;
    }

    // =========================================================================
    // Query Parameters ($_GET)
    // =========================================================================

    /**
     * Get a query parameter (from $_GET).
     *
     * @param string     $key     Parameter name
     * @param mixed|null $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all query parameters.
     *
     * @return array
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * Check if a query parameter exists.
     *
     * @param string $key Parameter name
     * @return bool
     */
    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    // =========================================================================
    // Body Parameters ($_POST)
    // =========================================================================

    /**
     * Get a POST parameter.
     *
     * @param string     $key     Parameter name
     * @param mixed|null $default Default value if not found
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Get all POST parameters.
     *
     * @return array
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * Check if a POST parameter exists.
     *
     * @param string $key Parameter name
     * @return bool
     */
    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->body);
    }

    // =========================================================================
    // Unified Input Access
    // =========================================================================

    /**
     * Get an input value from POST, GET, or JSON body (in that priority order).
     *
     * @param string     $key     Parameter name
     * @param mixed|null $default Default value if not found
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // POST takes priority
        if (isset($this->body[$key])) {
            return $this->body[$key];
        }

        // Then GET
        if (isset($this->query[$key])) {
            return $this->query[$key];
        }

        // Then JSON body
        $json = $this->json();
        if (isset($json[$key])) {
            return $json[$key];
        }

        // Then route params
        if (isset($this->routeParams[$key])) {
            return $this->routeParams[$key];
        }

        return $default;
    }

    /**
     * Get all input data merged from POST, GET, JSON body, and route params.
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge(
            $this->routeParams,
            $this->query,
            $this->json() ?? [],
            $this->body
        );
    }

    /**
     * Check if an input parameter exists in any source.
     *
     * @param string $key Parameter name
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key])
            || isset($this->query[$key])
            || isset(($this->json() ?? [])[$key])
            || isset($this->routeParams[$key]);
    }

    /**
     * Get only the specified keys from all input sources.
     *
     * @param array $keys Array of key names to retrieve
     * @return array Filtered input data
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get all input except the specified keys.
     *
     * @param array $keys Array of key names to exclude
     * @return array Filtered input data
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    // =========================================================================
    // File Uploads ($_FILES)
    // =========================================================================

    /**
     * Get an uploaded file by field name.
     *
     * Returns a normalized file array or null if no file was uploaded.
     *
     * @param string $key File input field name
     * @return array|null Normalized file info or null
     */
    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || $this->files[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $this->files[$key];

        return [
            'name'      => $file['name'],
            'type'      => $file['type'],
            'tmp_name'  => $file['tmp_name'],
            'error'     => $file['error'],
            'size'      => $file['size'],
            'extension' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
            'is_valid'  => $file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']),
        ];
    }

    /**
     * Check if a file was uploaded for the given field.
     *
     * @param string $key File input field name
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Get all uploaded files.
     *
     * @return array
     */
    public function files(): array
    {
        return $this->files;
    }

    // =========================================================================
    // Request Metadata
    // =========================================================================

    /**
     * Get the HTTP request method (GET, POST, etc.).
     *
     * @return string
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if the request uses a specific HTTP method.
     *
     * @param string $method HTTP method to check
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /**
     * Check if this is a GET request.
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /**
     * Check if this is a POST request.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Get the request URI path (without query string).
     *
     * @return string
     */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';

        // Remove query string
        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === false || $path === null) {
            return '/';
        }

        // Decode URL-encoded characters
        $path = rawurldecode($path);

        // Sanitize: remove null bytes and directory traversal
        $path = str_replace(["\0", '..'], '', $path);

        return $path ?: '/';
    }

    /**
     * Get the full request URI including query string.
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get the query string.
     *
     * @return string
     */
    public function queryString(): string
    {
        return $this->server['QUERY_STRING'] ?? '';
    }

    /**
     * Check if the request is an AJAX/XHR request.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        // Check X-Requested-With header
        if (isset($this->server['HTTP_X_REQUESTED_WITH'])) {
            return strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }

        // Check Accept header for JSON preference
        if (isset($this->server['HTTP_ACCEPT'])) {
            return strpos($this->server['HTTP_ACCEPT'], 'application/json') !== false;
        }

        return false;
    }

    /**
     * Check if the request expects a JSON response.
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isAjax() || (
            isset($this->server['HTTP_ACCEPT']) &&
            strpos($this->server['HTTP_ACCEPT'], 'application/json') !== false
        );
    }

    /**
     * Check if the request was made over HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return (
            (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ||
            (isset($this->server['SERVER_PORT']) && (int) $this->server['SERVER_PORT'] === 443) ||
            (isset($this->server['HTTP_X_FORWARDED_PROTO']) && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }

    /**
     * Get a request header value.
     *
     * @param string      $name    Header name (case-insensitive)
     * @param string|null $default Default value
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string
    {
        // Convert header name to $_SERVER key format
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? $default;
    }

    /**
     * Get the Content-Type of the request.
     *
     * @return string
     */
    public function contentType(): string
    {
        return $this->server['CONTENT_TYPE'] ?? '';
    }

    /**
     * Get the client's IP address.
     *
     * Checks proxy headers for forwarded IPs.
     *
     * @return string
     */
    public function ip(): string
    {
        // Check common proxy headers
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (isset($this->server[$header]) && !empty($this->server[$header])) {
                $ip = $this->server[$header];

                // X-Forwarded-For may contain multiple IPs; take the first
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Get the User-Agent string.
     *
     * @return string
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get the referrer URL.
     *
     * @return string|null
     */
    public function referrer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    // =========================================================================
    // JSON Body
    // =========================================================================

    /**
     * Get the parsed JSON body of the request.
     *
     * @return array|null Parsed JSON data or null if not JSON
     */
    public function json(): ?array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $contentType = $this->contentType();

        if (strpos($contentType, 'application/json') === false) {
            return null;
        }

        $raw = $this->rawBody();

        if (empty($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $this->jsonBody = $decoded;
        return $this->jsonBody;
    }

    /**
     * Get the raw request body.
     *
     * @return string
     */
    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }

        return $this->rawBody;
    }

    // =========================================================================
    // Route Parameters
    // =========================================================================

    /**
     * Set route parameters (called by the Router after matching).
     *
     * @param array $params Named parameters from the URL pattern
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get a route parameter.
     *
     * @param string     $key     Parameter name
     * @param mixed|null $default Default value
     * @return mixed
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Get all route parameters.
     *
     * @return array
     */
    public function params(): array
    {
        return $this->routeParams;
    }

    // =========================================================================
    // Cookies
    // =========================================================================

    /**
     * Get a cookie value.
     *
     * @param string     $key     Cookie name
     * @param mixed|null $default Default value
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Check if a cookie exists.
     *
     * @param string $key Cookie name
     * @return bool
     */
    public function hasCookie(string $key): bool
    {
        return isset($this->cookies[$key]);
    }

    // =========================================================================
    // Server Variables
    // =========================================================================

    /**
     * Get a server variable.
     *
     * @param string     $key     Server variable name
     * @param mixed|null $default Default value
     * @return mixed
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    // =========================================================================
    // Validation Helpers
    // =========================================================================

    /**
     * Get sanitized string input (trims and strips tags).
     *
     * @param string $key     Input key
     * @param string $default Default value
     * @return string
     */
    public function clean(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        if (!is_string($value)) {
            return $default;
        }

        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get input as integer.
     *
     * @param string $key     Input key
     * @param int    $default Default value
     * @return int
     */
    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return (int) filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default],
        ]);
    }

    /**
     * Get input as boolean.
     *
     * @param string $key     Input key
     * @param bool   $default Default value
     * @return bool
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Get input as float.
     *
     * @param string $key     Input key
     * @param float  $default Default value
     * @return float
     */
    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        return $filtered !== null ? $filtered : $default;
    }
}
