<?php
/**
 * ============================================================================
 * StudyFlow - Middleware
 * Student Self-Teaching App
 *
 * Provides static middleware methods for request filtering and access control.
 * Middleware checks are executed by the Router before dispatching to controllers.
 *
 * Available middleware:
 *   - auth():  Ensures the user is logged in
 *   - guest(): Ensures the user is NOT logged in
 *   - csrf():  Validates the CSRF token on POST requests
 * ============================================================================
 */

class Middleware
{
    // =========================================================================
    // Authentication Middleware
    // =========================================================================

    /**
     * Require that the user is authenticated (logged in).
     *
     * If the user is not logged in:
     *   - AJAX requests receive a 401 JSON response
     *   - Normal requests are redirected to the login page with a flash message
     *
     * The intended URL is stored in the session so the user can be redirected
     * back after successful login.
     *
     * @param Session  $session  The session instance
     * @param Response $response The response instance
     * @return bool True if the user is authenticated, false if handled
     */
    public static function auth(Session $session, Response $response): bool
    {
        if ($session->isLoggedIn()) {
            return true;
        }

        // Store the intended URL for redirect after login
        $intendedUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $session->set('intended_url', $intendedUrl);

        // Check if this is an AJAX request
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        );

        if ($isAjax) {
            $response->json([
                'success' => false,
                'error'   => 'Authentication required.',
                'code'    => 'AUTH_REQUIRED',
            ], 401);
            return false;
        }

        // Flash a message and redirect to login
        $session->flash('warning', 'Please log in to access that page.');
        $response->redirect('/login');
        return false;
    }

    // =========================================================================
    // Guest Middleware
    // =========================================================================

    /**
     * Require that the user is NOT authenticated (guest only).
     *
     * Used for login and registration pages to redirect already-authenticated
     * users to the dashboard.
     *
     * @param Session  $session  The session instance
     * @param Response $response The response instance
     * @return bool True if the user is a guest, false if handled
     */
    public static function guest(Session $session, Response $response): bool
    {
        if (!$session->isLoggedIn()) {
            return true;
        }

        // Check if this is an AJAX request
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        );

        if ($isAjax) {
            $response->json([
                'success' => false,
                'error'   => 'Already authenticated.',
                'code'    => 'ALREADY_AUTHENTICATED',
            ], 400);
            return false;
        }

        // Redirect authenticated users to the dashboard
        $response->redirect('/dashboard');
        return false;
    }

    // =========================================================================
    // CSRF Middleware
    // =========================================================================

    /**
     * Validate the CSRF token on POST requests.
     *
     * The token can be submitted via:
     *   1. POST body field named 'csrf_token'
     *   2. HTTP header 'X-CSRF-TOKEN' (for AJAX requests)
     *
     * If validation fails:
     *   - AJAX requests receive a 403 JSON response
     *   - Normal requests are redirected back with an error flash message
     *
     * @param Request  $request  The request instance
     * @param Session  $session  The session instance
     * @param Response $response The response instance
     * @return bool True if CSRF token is valid, false if handled
     */
    public static function csrf(Request $request, Session $session, Response $response): bool
    {
        // Only validate on POST requests (GET is read-only and safe)
        if ($request->method() !== 'POST') {
            return true;
        }

        // Try to get the token from POST body first, then from header
        $token = $request->post('csrf_token');

        if (empty($token)) {
            $token = $request->header('X-CSRF-TOKEN');
        }

        // Also check JSON body for AJAX requests
        if (empty($token)) {
            $jsonBody = $request->json();
            $token = $jsonBody['csrf_token'] ?? null;
        }

        if (empty($token)) {
            return self::handleCsrfFailure(
                $request,
                $session,
                $response,
                'CSRF token is missing from the request.'
            );
        }

        // Verify the token
        if (!$session->verify_csrf($token)) {
            return self::handleCsrfFailure(
                $request,
                $session,
                $response,
                'CSRF token validation failed. Your session may have expired.'
            );
        }

        return true;
    }

    /**
     * Handle a CSRF validation failure.
     *
     * @param Request  $request  The request instance
     * @param Session  $session  The session instance
     * @param Response $response The response instance
     * @param string   $message  Error message
     * @return bool Always returns false
     */
    private static function handleCsrfFailure(
        Request $request,
        Session $session,
        Response $response,
        string $message
    ): bool {
        // Log the CSRF failure for security monitoring
        error_log(sprintf(
            '[CSRF] Validation failed: %s | IP: %s | URI: %s | User-Agent: %s',
            $message,
            $request->ip(),
            $request->uri(),
            $request->userAgent()
        ));

        if ($request->isAjax() || $request->expectsJson()) {
            $response->json([
                'success' => false,
                'error'   => $message,
                'code'    => 'CSRF_VALIDATION_FAILED',
            ], 403);
            return false;
        }

        // Flash error and redirect back
        $session->flash('error', 'Your session has expired. Please try again.');
        $response->back('/');
        return false;
    }

    // =========================================================================
    // Rate Limiting Middleware
    // =========================================================================

    /**
     * Apply rate limiting based on session or IP.
     *
     * Tracks request counts in the session and rejects requests that exceed
     * the specified limit within the time window.
     *
     * @param Request  $request    The request instance
     * @param Session  $session    The session instance
     * @param Response $response   The response instance
     * @param int      $maxRequests Maximum requests allowed in the window
     * @param int      $windowSecs Time window in seconds
     * @param string   $key        Rate limit bucket key
     * @return bool True if within rate limit
     */
    public static function rateLimit(
        Request $request,
        Session $session,
        Response $response,
        int $maxRequests = 60,
        int $windowSecs = 3600,
        string $key = 'default'
    ): bool {
        $rateLimitKey = '_rate_limit_' . $key;
        $rateData = $session->get($rateLimitKey, [
            'count'      => 0,
            'window_start' => time(),
        ]);

        // Check if the time window has expired; if so, reset
        if (time() - $rateData['window_start'] > $windowSecs) {
            $rateData = [
                'count'        => 0,
                'window_start' => time(),
            ];
        }

        // Increment the counter
        $rateData['count']++;
        $session->set($rateLimitKey, $rateData);

        // Check if over the limit
        if ($rateData['count'] > $maxRequests) {
            $retryAfter = $windowSecs - (time() - $rateData['window_start']);

            if ($request->isAjax() || $request->expectsJson()) {
                $response->rateLimited($retryAfter, 'Too many requests. Please slow down.');
                return false;
            }

            $session->flash('error', 'You are making too many requests. Please wait a moment and try again.');
            $response->back('/');
            return false;
        }

        return true;
    }

    // =========================================================================
    // Content Type Middleware
    // =========================================================================

    /**
     * Ensure the request has a JSON content type (for API endpoints).
     *
     * @param Request  $request  The request instance
     * @param Response $response The response instance
     * @return bool True if content type is acceptable
     */
    public static function jsonOnly(Request $request, Response $response): bool
    {
        if ($request->method() === 'GET') {
            return true;
        }

        $contentType = $request->contentType();

        if (strpos($contentType, 'application/json') === false &&
            strpos($contentType, 'multipart/form-data') === false &&
            strpos($contentType, 'application/x-www-form-urlencoded') === false) {
            $response->json([
                'success' => false,
                'error'   => 'Unsupported content type.',
                'code'    => 'INVALID_CONTENT_TYPE',
            ], 415);
            return false;
        }

        return true;
    }

    // =========================================================================
    // Security Headers Middleware
    // =========================================================================

    /**
     * Set security-related response headers.
     *
     * This is typically called early in the request lifecycle
     * rather than as a route-specific middleware.
     *
     * @return void
     */
    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $config = $GLOBALS['config'] ?? [];

        // Content Security Policy
        $csp = $config['security']['csp'] ?? '';
        if ($csp) {
            header('Content-Security-Policy: ' . $csp);
        }

        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Clickjacking protection
        header('X-Frame-Options: SAMEORIGIN');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (replaces Feature-Policy)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
