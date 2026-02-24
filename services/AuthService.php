<?php
/**
 * ============================================================================
 * AuthService - Authentication & Account Management
 * StudyFlow - Student Self-Teaching App
 *
 * Handles user registration, login/logout, password management,
 * email verification, and session-based authentication. All credentials
 * are hashed with password_hash (bcrypt) and verified with password_verify.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class AuthService
{
    private FileStorage $storage;

    /** @var string Collection name for user accounts */
    private const COLLECTION_USERS = 'users';

    /** @var string Collection name for password-reset tokens */
    private const COLLECTION_RESETS = 'password_resets';

    /** @var string Collection name for email-verification tokens */
    private const COLLECTION_VERIFICATIONS = 'email_verifications';

    /** @var int Token lifetime in seconds (24 hours) */
    private const TOKEN_LIFETIME = 86400;

    /** @var int Minimum password length */
    private const MIN_PASSWORD_LENGTH = 8;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register a new user account.
     *
     * Expected $data keys:
     *   - email    (string, required)
     *   - password (string, required, min 8 chars)
     *   - name     (string, required)
     *
     * @param array $data Registration payload
     * @return array The created user record (without password hash)
     * @throws InvalidArgumentException On validation failure
     * @throws RuntimeException If email already exists
     */
    public function register(array $data): array
    {
        // --- Validate required fields ----------------------------------------
        $this->requireFields($data, ['email', 'password', 'name']);

        $email    = $this->normalizeEmail($data['email']);
        $password = $data['password'];
        $name     = trim($data['name']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('AuthService: Invalid email address.');
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'AuthService: Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        if ($name === '') {
            throw new InvalidArgumentException('AuthService: Name cannot be empty.');
        }

        // --- Check uniqueness by email ---------------------------------------
        if ($this->findUserByEmail($email) !== null) {
            throw new RuntimeException('AuthService: An account with this email already exists.');
        }

        // --- Build user record -----------------------------------------------
        $userId = $this->storage->generateId();
        $now    = date('c');

        $user = [
            'id'                => $userId,
            'email'             => $email,
            'password_hash'     => password_hash($password, PASSWORD_BCRYPT),
            'name'              => $name,
            'role'              => 'student',
            'email_verified'    => false,
            'avatar'            => null,
            'bio'               => '',
            'preferences'       => $this->defaultPreferences(),
            'xp'                => 0,
            'level'             => 1,
            'streak'            => 0,
            'last_active'       => $now,
            'status'            => 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        $this->storage->write(self::COLLECTION_USERS, $userId, $user);

        // --- Create email-verification token ---------------------------------
        $this->createEmailVerificationToken($userId, $email);

        return $this->sanitizeUser($user);
    }

    // -------------------------------------------------------------------------
    // Login / Logout / Session
    // -------------------------------------------------------------------------

    /**
     * Authenticate a user by email and password.
     *
     * Starts (or resumes) a PHP session and stores the user ID.
     *
     * @param string $email    User email
     * @param string $password Plain-text password
     * @return array The authenticated user record
     * @throws InvalidArgumentException On empty input
     * @throws RuntimeException On bad credentials or inactive account
     */
    public function login(string $email, string $password): array
    {
        $email = $this->normalizeEmail($email);

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('AuthService: Email and password are required.');
        }

        $user = $this->findUserByEmail($email);

        if ($user === null) {
            throw new RuntimeException('AuthService: Invalid email or password.');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('AuthService: Invalid email or password.');
        }

        if (($user['status'] ?? 'active') !== 'active') {
            throw new RuntimeException('AuthService: This account has been deactivated.');
        }

        // Update last active timestamp
        $this->storage->update(self::COLLECTION_USERS, $user['id'], [
            'last_active' => date('c'),
        ]);

        // Start session
        $this->ensureSession();
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['logged_in']  = true;

        return $this->sanitizeUser($user);
    }

    /**
     * Log out the current user by destroying the session.
     *
     * @return bool True on success
     */
    public function logout(): bool
    {
        $this->ensureSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        return true;
    }

    /**
     * Get the currently logged-in user's data.
     *
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        $this->ensureSession();

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $user = $this->storage->read(self::COLLECTION_USERS, $_SESSION['user_id']);

        if ($user === null) {
            return null;
        }

        return $this->sanitizeUser($user);
    }

    /**
     * Check whether a user is currently logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        $this->ensureSession();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    // -------------------------------------------------------------------------
    // Password Management
    // -------------------------------------------------------------------------

    /**
     * Change a user's password (requires knowing the current password).
     *
     * @param string $userId  User ID
     * @param string $oldPass Current password
     * @param string $newPass New password
     * @return bool
     * @throws InvalidArgumentException On validation failure
     * @throws RuntimeException If current password is wrong
     */
    public function updatePassword(string $userId, string $oldPass, string $newPass): bool
    {
        if (mb_strlen($newPass) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'AuthService: New password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('AuthService: User not found.');
        }

        if (!password_verify($oldPass, $user['password_hash'])) {
            throw new RuntimeException('AuthService: Current password is incorrect.');
        }

        if ($oldPass === $newPass) {
            throw new InvalidArgumentException('AuthService: New password must differ from current password.');
        }

        return $this->storage->update(self::COLLECTION_USERS, $userId, [
            'password_hash' => password_hash($newPass, PASSWORD_BCRYPT),
            'updated_at'    => date('c'),
        ]);
    }

    /**
     * Request a password-reset token for the given email.
     *
     * The token is stored in the password_resets collection and should
     * be delivered to the user by email (not handled here).
     *
     * @param string $email User email
     * @return array Token details ['token' => ..., 'expires_at' => ...]
     * @throws RuntimeException If email not found
     */
    public function requestPasswordReset(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $user  = $this->findUserByEmail($email);

        if ($user === null) {
            // For security, do not reveal whether the email exists.
            // Return a fake response structure, but log nothing.
            return [
                'message' => 'If an account with that email exists, a reset link has been sent.',
            ];
        }

        // Invalidate any existing tokens for this user
        $this->invalidateExistingResetTokens($user['id']);

        $token     = bin2hex(random_bytes(32));
        $tokenId   = $this->storage->generateId();
        $expiresAt = date('c', time() + self::TOKEN_LIFETIME);

        $resetRecord = [
            'id'         => $tokenId,
            'user_id'    => $user['id'],
            'email'      => $email,
            'token'      => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'used'       => false,
            'created_at' => date('c'),
        ];

        $this->storage->write(self::COLLECTION_RESETS, $tokenId, $resetRecord);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
            'message'    => 'If an account with that email exists, a reset link has been sent.',
        ];
    }

    /**
     * Reset a password using a valid reset token.
     *
     * @param string $token       The plain-text reset token
     * @param string $newPassword The new password
     * @return bool
     * @throws InvalidArgumentException On validation failure
     * @throws RuntimeException On invalid or expired token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'AuthService: Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $hashedToken = hash('sha256', $token);
        $resets      = $this->storage->query(self::COLLECTION_RESETS, function (array $r) use ($hashedToken) {
            return $r['token'] === $hashedToken && !$r['used'];
        });

        if (empty($resets)) {
            throw new RuntimeException('AuthService: Invalid or expired reset token.');
        }

        $resetRecord = $resets[0];

        // Check expiry
        if (strtotime($resetRecord['expires_at']) < time()) {
            $this->storage->update(self::COLLECTION_RESETS, $resetRecord['id'], ['used' => true]);
            throw new RuntimeException('AuthService: Reset token has expired.');
        }

        // Update the user's password
        $this->storage->update(self::COLLECTION_USERS, $resetRecord['user_id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'updated_at'    => date('c'),
        ]);

        // Mark token as used
        $this->storage->update(self::COLLECTION_RESETS, $resetRecord['id'], ['used' => true]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Email Verification
    // -------------------------------------------------------------------------

    /**
     * Verify a user's email address using the provided token.
     *
     * @param string $token The plain-text verification token
     * @return bool
     * @throws RuntimeException On invalid or expired token
     */
    public function verifyEmail(string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        $records = $this->storage->query(self::COLLECTION_VERIFICATIONS, function (array $r) use ($hashedToken) {
            return $r['token'] === $hashedToken && !($r['used'] ?? false);
        });

        if (empty($records)) {
            throw new RuntimeException('AuthService: Invalid or expired verification token.');
        }

        $record = $records[0];

        if (strtotime($record['expires_at']) < time()) {
            $this->storage->update(self::COLLECTION_VERIFICATIONS, $record['id'], ['used' => true]);
            throw new RuntimeException('AuthService: Verification token has expired.');
        }

        // Mark email as verified
        $this->storage->update(self::COLLECTION_USERS, $record['user_id'], [
            'email_verified' => true,
            'updated_at'     => date('c'),
        ]);

        // Mark token as used
        $this->storage->update(self::COLLECTION_VERIFICATIONS, $record['id'], ['used' => true]);

        return true;
    }

    // -------------------------------------------------------------------------
    // User Retrieval & Deletion
    // -------------------------------------------------------------------------

    /**
     * Get a user by ID (safe: without password hash).
     *
     * @param string $userId User ID
     * @return array|null
     */
    public function getUserById(string $userId): ?array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            return null;
        }
        return $this->sanitizeUser($user);
    }

    /**
     * Delete a user account and related data.
     *
     * @param string $userId User ID
     * @return bool
     * @throws RuntimeException If user does not exist
     */
    public function deleteAccount(string $userId): bool
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('AuthService: User not found.');
        }

        // Soft-delete: mark account as deleted so data can be recovered
        $this->storage->update(self::COLLECTION_USERS, $userId, [
            'status'     => 'deleted',
            'email'      => 'deleted_' . $userId . '@deleted.local',
            'deleted_at' => date('c'),
            'updated_at' => date('c'),
        ]);

        // Clear session if the deleted user is currently logged in
        $this->ensureSession();
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId) {
            $this->logout();
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a user record by email address.
     *
     * @param string $email Normalized email
     * @return array|null
     */
    private function findUserByEmail(string $email): ?array
    {
        $results = $this->storage->query(self::COLLECTION_USERS, ['email' => $email]);

        if (empty($results)) {
            return null;
        }

        // Return only active accounts
        foreach ($results as $user) {
            if (($user['status'] ?? 'active') !== 'deleted') {
                return $user;
            }
        }

        return null;
    }

    /**
     * Normalize an email address to lowercase.
     *
     * @param string $email
     * @return string
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Remove sensitive fields from a user record before returning it.
     *
     * @param array $user Raw user record
     * @return array Sanitized record
     */
    private function sanitizeUser(array $user): array
    {
        unset(
            $user['password_hash'],
            $user['_id'],
            $user['_created_at'],
            $user['_updated_at']
        );
        return $user;
    }

    /**
     * Ensure a PHP session is started.
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Validate that required fields exist and are non-empty.
     *
     * @param array    $data   Data to validate
     * @param string[] $fields Required field names
     * @throws InvalidArgumentException
     */
    private function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new InvalidArgumentException(
                    "AuthService: The field '{$field}' is required."
                );
            }
        }
    }

    /**
     * Build default user preferences.
     *
     * @return array
     */
    private function defaultPreferences(): array
    {
        return [
            'theme'              => 'light',
            'notifications'      => true,
            'study_reminders'    => true,
            'reminder_time'      => '09:00',
            'language'           => 'en',
            'daily_goal_minutes' => 30,
        ];
    }

    /**
     * Create an email-verification token for a new user.
     *
     * @param string $userId User ID
     * @param string $email  User email
     * @return string The plain-text token (to be emailed)
     */
    private function createEmailVerificationToken(string $userId, string $email): string
    {
        $token   = bin2hex(random_bytes(32));
        $tokenId = $this->storage->generateId();

        $record = [
            'id'         => $tokenId,
            'user_id'    => $userId,
            'email'      => $email,
            'token'      => hash('sha256', $token),
            'expires_at' => date('c', time() + self::TOKEN_LIFETIME),
            'used'       => false,
            'created_at' => date('c'),
        ];

        $this->storage->write(self::COLLECTION_VERIFICATIONS, $tokenId, $record);

        return $token;
    }

    /**
     * Invalidate all existing password-reset tokens for a user.
     *
     * @param string $userId User ID
     */
    private function invalidateExistingResetTokens(string $userId): void
    {
        $existing = $this->storage->query(self::COLLECTION_RESETS, function (array $r) use ($userId) {
            return $r['user_id'] === $userId && !$r['used'];
        });

        foreach ($existing as $record) {
            $this->storage->update(self::COLLECTION_RESETS, $record['id'], ['used' => true]);
        }
    }
}
