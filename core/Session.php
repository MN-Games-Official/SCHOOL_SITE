<?php
/**
 * ============================================================================
 * StudyFlow - Session Manager
 * Student Self-Teaching App
 *
 * Provides secure session management with flash data support, CSRF token
 * generation and verification, session regeneration, and idle timeout
 * handling. Wraps PHP's native session functions with a clean OOP interface.
 * ============================================================================
 */

class Session
{
    /**
     * Whether the session has been started.
     * @var bool
     */
    private bool $started = false;

    /**
     * Session configuration options.
     * @var array
     */
    private array $config = [];

    /**
     * Flash data keys from the previous request (to be cleared after access).
     * @var array
     */
    private array $previousFlashKeys = [];

    // =========================================================================
    // Session Lifecycle
    // =========================================================================

    /**
     * Start the session with the given configuration.
     *
     * @param array $config Session configuration options
     * @return bool True if session started successfully
     */
    public function start(array $config = []): bool
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->initializeFlash();
            return true;
        }

        $this->config = array_merge([
            'name'                => 'studyflow_session',
            'lifetime'            => 7200,
            'path'                => '/',
            'domain'              => '',
            'secure'              => false,
            'httponly'             => true,
            'samesite'            => 'Lax',
            'idle_timeout'        => 1800,
            'regenerate_interval' => 900,
        ], $config);

        // Set session name
        session_name($this->config['name']);

        // Configure session cookie parameters
        $cookieParams = [
            'lifetime' => $this->config['lifetime'],
            'path'     => $this->config['path'],
            'domain'   => $this->config['domain'],
            'secure'   => $this->config['secure'],
            'httponly'  => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ];

        session_set_cookie_params($cookieParams);

        // Use strict mode to reject uninitialized session IDs
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        // Start the session
        if (!session_start()) {
            error_log('[Session] Failed to start session');
            return false;
        }

        $this->started = true;

        // Handle idle timeout
        $this->checkIdleTimeout();

        // Periodically regenerate the session ID
        $this->checkRegenerateInterval();

        // Initialize flash data handling
        $this->initializeFlash();

        return true;
    }

    /**
     * Check if the session has exceeded the idle timeout.
     *
     * @return void
     */
    private function checkIdleTimeout(): void
    {
        $timeout = $this->config['idle_timeout'] ?? 1800;

        if (isset($_SESSION['_last_activity'])) {
            $elapsed = time() - $_SESSION['_last_activity'];

            if ($elapsed > $timeout) {
                // Session expired due to inactivity
                $this->destroy();
                $this->start($this->config);

                $_SESSION['_flash']['warning'] = 'Your session expired due to inactivity. Please log in again.';
                return;
            }
        }

        // Update last activity timestamp
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Check if the session ID should be regenerated.
     *
     * @return void
     */
    private function checkRegenerateInterval(): void
    {
        $interval = $this->config['regenerate_interval'] ?? 900;

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
            return;
        }

        $elapsed = time() - $_SESSION['_created_at'];

        if ($elapsed > $interval) {
            $this->regenerate();
            $_SESSION['_created_at'] = time();
        }
    }

    /**
     * Destroy the current session completely.
     *
     * @return void
     */
    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Clear all session data
        $_SESSION = [];

        // Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly'  => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        // Destroy the session on the server
        session_destroy();
        $this->started = false;
    }

    /**
     * Regenerate the session ID.
     *
     * Creates a new session ID while preserving session data.
     * This helps prevent session fixation attacks.
     *
     * @param bool $deleteOld Whether to delete the old session file
     * @return bool True on success
     */
    public function regenerate(bool $deleteOld = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return session_regenerate_id($deleteOld);
    }

    /**
     * Check if the session is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->started && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get the current session ID.
     *
     * @return string
     */
    public function id(): string
    {
        return session_id() ?: '';
    }

    // =========================================================================
    // Data Access (Get/Set/Has/Remove)
    // =========================================================================

    /**
     * Get a session value.
     *
     * @param string     $key     Session key (supports dot notation for nested)
     * @param mixed|null $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Support dot notation for nested values (e.g., "user.name")
        if (strpos($key, '.') !== false) {
            return $this->getNestedValue($_SESSION, $key, $default);
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     *
     * @param string $key   Session key (supports dot notation)
     * @param mixed  $value Value to store
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        if (strpos($key, '.') !== false) {
            $this->setNestedValue($_SESSION, $key, $value);
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists.
     *
     * @param string $key Session key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (strpos($key, '.') !== false) {
            return $this->getNestedValue($_SESSION, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
        }

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a session value.
     *
     * @param string $key Session key
     * @return void
     */
    public function remove(string $key): void
    {
        if (strpos($key, '.') !== false) {
            $this->removeNestedValue($_SESSION, $key);
            return;
        }

        unset($_SESSION[$key]);
    }

    /**
     * Get all session data (excluding internal keys).
     *
     * @return array
     */
    public function all(): array
    {
        $data = $_SESSION ?? [];

        // Remove internal session management keys
        unset(
            $data['_flash'],
            $data['_previous_flash_keys'],
            $data['_last_activity'],
            $data['_created_at'],
            $data['_csrf_token'],
            $data['_csrf_token_time']
        );

        return $data;
    }

    /**
     * Clear all session data (but keep the session active).
     *
     * @return void
     */
    public function clear(): void
    {
        $_SESSION = [];
        $_SESSION['_last_activity'] = time();
        $_SESSION['_created_at'] = time();
    }

    // =========================================================================
    // Flash Data
    // =========================================================================

    /**
     * Initialize flash data handling.
     *
     * Loads previous request's flash keys and prepares cleanup.
     *
     * @return void
     */
    private function initializeFlash(): void
    {
        // Track which flash keys existed from the previous request
        $this->previousFlashKeys = $_SESSION['_previous_flash_keys'] ?? [];

        // Set up current flash keys to become previous on next request
        $_SESSION['_previous_flash_keys'] = array_keys($_SESSION['_flash'] ?? []);

        // Remove flash data from previous-previous request
        // (flash data lives for exactly one subsequent request)
        foreach ($this->previousFlashKeys as $key) {
            if (isset($_SESSION['_flash'][$key]) && !in_array($key, $_SESSION['_previous_flash_keys'], true)) {
                unset($_SESSION['_flash'][$key]);
            }
        }
    }

    /**
     * Set flash data (available only for the next request).
     *
     * @param string $key   Flash data key
     * @param mixed  $value Flash data value
     * @return void
     */
    public function flash(string $key, mixed $value): void
    {
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        $_SESSION['_flash'][$key] = $value;

        // Add to current flash keys so it survives to next request
        if (!isset($_SESSION['_previous_flash_keys'])) {
            $_SESSION['_previous_flash_keys'] = [];
        }

        if (!in_array($key, $_SESSION['_previous_flash_keys'], true)) {
            $_SESSION['_previous_flash_keys'][] = $key;
        }
    }

    /**
     * Get flash data value and optionally remove it.
     *
     * @param string     $key     Flash data key
     * @param mixed|null $default Default value if not found
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;

        // Remove the flash data after retrieval
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Check if flash data exists for a key.
     *
     * @param string $key Flash data key
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Get all current flash data without removing it.
     *
     * @return array
     */
    public function allFlash(): array
    {
        return $_SESSION['_flash'] ?? [];
    }

    /**
     * Keep specific flash data for another request.
     *
     * @param array $keys Keys to keep for one more request
     * @return void
     */
    public function reflash(array $keys = []): void
    {
        if (empty($keys)) {
            // Keep all flash data
            $keys = array_keys($_SESSION['_flash'] ?? []);
        }

        foreach ($keys as $key) {
            if (isset($_SESSION['_flash'][$key])) {
                $this->flash($key, $_SESSION['_flash'][$key]);
            }
        }
    }

    // =========================================================================
    // CSRF Token Management
    // =========================================================================

    /**
     * Generate or retrieve the current CSRF token.
     *
     * Creates a new token if none exists or if the current token has expired.
     *
     * @return string CSRF token
     */
    public function csrf_token(): string
    {
        $config = $GLOBALS['config'] ?? [];
        $lifetime = $config['security']['csrf_lifetime'] ?? 3600;

        // Check if existing token is still valid
        if (isset($_SESSION['_csrf_token']) && isset($_SESSION['_csrf_token_time'])) {
            $elapsed = time() - $_SESSION['_csrf_token_time'];

            if ($elapsed < $lifetime) {
                return $_SESSION['_csrf_token'];
            }
        }

        // Generate a new CSRF token
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            // Fallback (less secure but functional)
            $token = hash('sha256', uniqid((string) mt_rand(), true) . session_id());
        }

        $_SESSION['_csrf_token'] = $token;
        $_SESSION['_csrf_token_time'] = time();

        return $token;
    }

    /**
     * Verify a CSRF token against the session token.
     *
     * @param string $token Token to verify (usually from form submission)
     * @return bool True if the token is valid
     */
    public function verify_csrf(string $token): bool
    {
        if (empty($token) || !isset($_SESSION['_csrf_token'])) {
            return false;
        }

        // Check token expiry
        $config = $GLOBALS['config'] ?? [];
        $lifetime = $config['security']['csrf_lifetime'] ?? 3600;

        if (isset($_SESSION['_csrf_token_time'])) {
            $elapsed = time() - $_SESSION['_csrf_token_time'];

            if ($elapsed > $lifetime) {
                // Token expired
                unset($_SESSION['_csrf_token'], $_SESSION['_csrf_token_time']);
                return false;
            }
        }

        // Timing-safe comparison to prevent timing attacks
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Generate an HTML hidden input field for CSRF protection.
     *
     * @return string HTML hidden input element
     */
    public function csrfField(): string
    {
        $token = $this->csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get the CSRF token as a meta tag (for AJAX requests).
     *
     * @return string HTML meta element
     */
    public function csrfMeta(): string
    {
        $token = $this->csrf_token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // =========================================================================
    // User Authentication Helpers
    // =========================================================================

    /**
     * Check if a user is currently logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get the currently authenticated user's ID.
     *
     * @return string|null
     */
    public function userId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get the currently authenticated user's data.
     *
     * @return array|null
     */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Log in a user by storing their data in the session.
     *
     * @param string $userId User ID
     * @param array  $userData User data array
     * @return void
     */
    public function login(string $userId, array $userData): void
    {
        // Regenerate session ID on login to prevent session fixation
        $this->regenerate(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = $userData;
        $_SESSION['_login_time'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Log out the current user.
     *
     * @return void
     */
    public function logout(): void
    {
        // Remove user-specific session data
        unset(
            $_SESSION['user_id'],
            $_SESSION['user'],
            $_SESSION['_login_time']
        );

        // Regenerate session ID
        $this->regenerate(true);
    }

    // =========================================================================
    // Nested Value Helpers (Dot Notation)
    // =========================================================================

    /**
     * Get a nested value using dot notation.
     *
     * @param array  $array   Source array
     * @param string $key     Dot-notated key
     * @param mixed  $default Default value
     * @return mixed
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);

        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a nested value using dot notation.
     *
     * @param array  $array Reference to the array
     * @param string $key   Dot-notated key
     * @param mixed  $value Value to set
     * @return void
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Remove a nested value using dot notation.
     *
     * @param array  $array Reference to the array
     * @param string $key   Dot-notated key
     * @return void
     */
    private function removeNestedValue(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($current[$segment]);
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    return;
                }
                $current = &$current[$segment];
            }
        }
    }
}
