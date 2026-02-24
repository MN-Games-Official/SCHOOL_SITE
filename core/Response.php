<?php
/**
 * ============================================================================
 * StudyFlow - Response
 * Student Self-Teaching App
 *
 * Provides a clean interface for sending HTTP responses including JSON,
 * HTML, redirects, file downloads, and flash data. Handles headers,
 * status codes, and content type negotiation.
 * ============================================================================
 */

class Response
{
    /**
     * HTTP status code for the response.
     * @var int
     */
    private int $statusCode = 200;

    /**
     * Response headers to send.
     * @var array
     */
    private array $headers = [];

    /**
     * Standard HTTP status text map.
     * @var array
     */
    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        413 => 'Payload Too Large',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    // =========================================================================
    // Status Code
    // =========================================================================

    /**
     * Set the HTTP status code.
     *
     * @param int $code HTTP status code
     * @return self Fluent interface
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get the current status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    // =========================================================================
    // Headers
    // =========================================================================

    /**
     * Set a response header.
     *
     * @param string $name  Header name
     * @param string $value Header value
     * @return self Fluent interface
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers at once.
     *
     * @param array $headers Associative array of header name => value
     * @return self Fluent interface
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Send all queued headers.
     *
     * @return void
     */
    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Set a no-cache header to prevent browser caching.
     *
     * @return self Fluent interface
     */
    public function noCache(): self
    {
        $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['Expires'] = 'Thu, 01 Jan 1970 00:00:00 GMT';
        return $this;
    }

    // =========================================================================
    // JSON Response
    // =========================================================================

    /**
     * Send a JSON response.
     *
     * @param mixed $data       Data to encode as JSON
     * @param int   $statusCode HTTP status code (optional override)
     * @param int   $options    JSON encoding options
     * @return void
     */
    public function json(mixed $data, int $statusCode = 0, int $options = 0): void
    {
        if ($statusCode > 0) {
            $this->statusCode = $statusCode;
        }

        $this->headers['Content-Type'] = 'application/json; charset=UTF-8';

        $defaultOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $options = $options ?: $defaultOptions;

        $json = json_encode($data, $options);

        if ($json === false) {
            $this->statusCode = 500;
            $json = json_encode([
                'success' => false,
                'error'   => 'Failed to encode response data.',
            ]);
        }

        $this->sendHeaders();
        echo $json;
        exit;
    }

    /**
     * Send a successful JSON response.
     *
     * @param mixed  $data    Response data
     * @param string $message Success message
     * @param int    $status  HTTP status code
     * @return void
     */
    public function success(mixed $data = null, string $message = 'Success', int $status = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $status);
    }

    /**
     * Send an error JSON response.
     *
     * @param string $message Error message
     * @param int    $status  HTTP status code
     * @param array  $errors  Detailed error information
     * @return void
     */
    public function error(string $message = 'An error occurred', int $status = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'error'   => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    // =========================================================================
    // HTML Response
    // =========================================================================

    /**
     * Send an HTML response.
     *
     * @param string $content    HTML content
     * @param int    $statusCode HTTP status code (optional override)
     * @return void
     */
    public function html(string $content, int $statusCode = 0): void
    {
        if ($statusCode > 0) {
            $this->statusCode = $statusCode;
        }

        $this->headers['Content-Type'] = 'text/html; charset=UTF-8';

        $this->sendHeaders();
        echo $content;
        exit;
    }

    /**
     * Send plain text response.
     *
     * @param string $content    Text content
     * @param int    $statusCode HTTP status code (optional override)
     * @return void
     */
    public function text(string $content, int $statusCode = 0): void
    {
        if ($statusCode > 0) {
            $this->statusCode = $statusCode;
        }

        $this->headers['Content-Type'] = 'text/plain; charset=UTF-8';

        $this->sendHeaders();
        echo $content;
        exit;
    }

    // =========================================================================
    // Redirects
    // =========================================================================

    /**
     * Send a redirect response.
     *
     * @param string $url        URL to redirect to
     * @param int    $statusCode HTTP redirect status code (301, 302, 303, 307)
     * @return void
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        // Validate the URL to prevent header injection
        $url = $this->sanitizeRedirectUrl($url);

        $this->statusCode = $statusCode;
        $this->headers['Location'] = $url;

        $this->sendHeaders();
        exit;
    }

    /**
     * Redirect back to the previous page.
     *
     * @param string $fallback Fallback URL if no referrer is available
     * @return void
     */
    public function back(string $fallback = '/'): void
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        $this->redirect($referrer);
    }

    /**
     * Redirect to a named route.
     *
     * @param string $routeName Route name
     * @param array  $params    Route parameters
     * @return void
     */
    public function route(string $routeName, array $params = []): void
    {
        $router = $GLOBALS['router'] ?? null;

        if ($router && method_exists($router, 'url')) {
            $url = $router->url($routeName, $params);
            if ($url !== null) {
                $this->redirect($url);
                return;
            }
        }

        $this->redirect('/');
    }

    /**
     * Sanitize a redirect URL to prevent header injection attacks.
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitizeRedirectUrl(string $url): string
    {
        // Remove any newlines (header injection prevention)
        $url = str_replace(["\r", "\n", "\0"], '', $url);

        // If it's a relative URL, that's fine
        if (strpos($url, '/') === 0) {
            return $url;
        }

        // For absolute URLs, only allow http and https schemes
        $parsed = parse_url($url);
        if ($parsed === false) {
            return '/';
        }

        if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return '/';
        }

        return $url;
    }

    // =========================================================================
    // Flash Data (Session-based)
    // =========================================================================

    /**
     * Store flash data in the session and return self for chaining before redirect.
     *
     * Usage: $response->with('success', 'Profile updated!')->redirect('/settings');
     * Note: This method does NOT exit - it stores data and returns for chaining.
     *
     * @param string $key   Flash data key
     * @param mixed  $value Flash data value
     * @return self Fluent interface for chaining with redirect
     */
    public function with(string $key, mixed $value): self
    {
        $session = $GLOBALS['session'] ?? null;

        if ($session instanceof Session) {
            $session->flash($key, $value);
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash'][$key] = $value;
        }

        return $this;
    }

    /**
     * Store validation errors as flash data.
     *
     * @param array $errors Associative array of field => error message
     * @return self Fluent interface
     */
    public function withErrors(array $errors): self
    {
        return $this->with('errors', $errors);
    }

    /**
     * Store old input data as flash data (for form repopulation).
     *
     * @param array $input Input data to preserve
     * @return self Fluent interface
     */
    public function withInput(array $input = []): self
    {
        if (empty($input)) {
            $input = array_merge($_GET, $_POST);
        }

        // Remove sensitive fields
        unset($input['password'], $input['password_confirmation'], $input['csrf_token']);

        return $this->with('old_input', $input);
    }

    // =========================================================================
    // File Downloads
    // =========================================================================

    /**
     * Send a file as a download response.
     *
     * @param string      $filePath File path on the server
     * @param string|null $fileName Display filename for the download
     * @param string|null $mimeType MIME type (auto-detected if null)
     * @return void
     */
    public function download(string $filePath, ?string $fileName = null, ?string $mimeType = null): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->status(404)->json([
                'success' => false,
                'error'   => 'File not found.',
            ]);
            return;
        }

        // Determine the filename for the download
        $fileName = $fileName ?: basename($filePath);

        // Detect MIME type
        $mimeType = $mimeType ?: (mime_content_type($filePath) ?: 'application/octet-stream');

        // Sanitize filename
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

        $this->statusCode = 200;
        $this->headers['Content-Type'] = $mimeType;
        $this->headers['Content-Disposition'] = 'attachment; filename="' . $fileName . '"';
        $this->headers['Content-Length'] = (string) filesize($filePath);
        $this->headers['Cache-Control'] = 'no-cache, must-revalidate';
        $this->headers['Pragma'] = 'no-cache';

        $this->sendHeaders();

        // Stream the file
        readfile($filePath);
        exit;
    }

    // =========================================================================
    // Convenience Methods
    // =========================================================================

    /**
     * Send a 403 Forbidden response.
     *
     * @param string $message Error message
     * @return void
     */
    public function forbidden(string $message = 'Access denied.'): void
    {
        $this->error($message, 403);
    }

    /**
     * Send a 404 Not Found response.
     *
     * @param string $message Error message
     * @return void
     */
    public function notFound(string $message = 'Resource not found.'): void
    {
        $this->error($message, 404);
    }

    /**
     * Send a 422 Validation Error response.
     *
     * @param array  $errors  Validation errors
     * @param string $message General error message
     * @return void
     */
    public function validationError(array $errors, string $message = 'Validation failed.'): void
    {
        $this->error($message, 422, $errors);
    }

    /**
     * Send a 429 Rate Limited response.
     *
     * @param int    $retryAfter Seconds until the client can retry
     * @param string $message    Error message
     * @return void
     */
    public function rateLimited(int $retryAfter = 60, string $message = 'Too many requests.'): void
    {
        $this->header('Retry-After', (string) $retryAfter);
        $this->error($message, 429);
    }
}
