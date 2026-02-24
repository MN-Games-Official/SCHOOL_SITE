<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class AuthController
{
    private AuthService $authService;
    private Session $session;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->session = new Session();
    }

    /**
     * Show login form (guest only).
     */
    public function showLogin(Request $request, array $params = []): string
    {
        Middleware::guest();

        return View::render('auth/login', [
            'title' => 'Sign In',
            'csrf'  => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Process login form submission.
     */
    public function login(Request $request, array $params = []): string
    {
        Middleware::guest();
        Middleware::csrf();

        $errors = [];

        $email    = trim($request->post('email') ?? '');
        $password = $request->post('password') ?? '';
        $remember = $request->post('remember') ? true : false;

        // Validate
        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', ['email' => $email]);
            return redirect(url('/login'));
        }

        try {
            $result = $this->authService->login($email, $password);

            $this->session->set('user_id', $result['id']);
            $this->session->set('user', $result);
            $this->session->flash('success', 'Welcome back, ' . e($result['name']) . '!');

            return redirect(url('/dashboard'));
        } catch (\RuntimeException $e) {
            $this->session->flash('errors', ['login' => $e->getMessage()]);
            $this->session->flash('old', ['email' => $email]);
            return redirect(url('/login'));
        }
    }

    /**
     * Show registration form.
     */
    public function showRegister(Request $request, array $params = []): string
    {
        Middleware::guest();

        return View::render('auth/register', [
            'title' => 'Create Account',
            'csrf'  => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Process registration form submission.
     */
    public function register(Request $request, array $params = []): string
    {
        Middleware::guest();
        Middleware::csrf();

        $errors = [];

        $name             = trim($request->post('name') ?? '');
        $email            = trim($request->post('email') ?? '');
        $password         = $request->post('password') ?? '';
        $passwordConfirm  = $request->post('password_confirmation') ?? '';
        $agreeTerms       = $request->post('agree_terms') ? true : false;

        // Validate name
        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Name must be under 100 characters.';
        }

        // Validate email
        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validate password
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number.';
        }

        // Validate password confirmation
        if ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        // Validate terms agreement
        if (!$agreeTerms) {
            $errors['agree_terms'] = 'You must agree to the terms and conditions.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'name'  => $name,
                'email' => $email,
            ]);
            return redirect(url('/register'));
        }

        try {
            $result = $this->authService->register([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
            ]);

            $this->session->set('user_id', $result['id']);
            $this->session->set('user', $result);
            $this->session->flash('success', 'Account created successfully! Welcome aboard.');

            return redirect(url('/dashboard'));
        } catch (\RuntimeException $e) {
            $this->session->flash('errors', ['register' => $e->getMessage()]);
            $this->session->flash('old', [
                'name'  => $name,
                'email' => $email,
            ]);
            return redirect(url('/register'));
        }
    }

    /**
     * Log the user out and destroy session.
     */
    public function logout(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $this->authService->logout();
        } catch (\RuntimeException $e) {
            // Proceed with local logout even if service fails
        }

        $this->session->set('user_id', null);
        $this->session->set('user', null);
        session_destroy();

        $this->session->flash('success', 'You have been signed out.');
        return redirect(url('/login'));
    }

    /**
     * Show forgot password form.
     */
    public function showForgotPassword(Request $request, array $params = []): string
    {
        Middleware::guest();

        return View::render('auth/forgot-password', [
            'title' => 'Forgot Password',
            'csrf'  => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Process forgot password request.
     */
    public function forgotPassword(Request $request, array $params = []): string
    {
        Middleware::guest();
        Middleware::csrf();

        $email = trim($request->post('email') ?? '');

        if (empty($email)) {
            $this->session->flash('errors', ['email' => 'Email address is required.']);
            return redirect(url('/forgot-password'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('errors', ['email' => 'Please enter a valid email address.']);
            $this->session->flash('old', ['email' => $email]);
            return redirect(url('/forgot-password'));
        }

        try {
            $this->authService->requestPasswordReset($email);
            // Always show success to prevent email enumeration
            $this->session->flash('success', 'If an account exists with that email, you will receive a password reset link.');
            return redirect(url('/forgot-password'));
        } catch (\RuntimeException $e) {
            // Show same message to prevent email enumeration
            $this->session->flash('success', 'If an account exists with that email, you will receive a password reset link.');
            return redirect(url('/forgot-password'));
        }
    }

    /**
     * Show password reset form with token.
     */
    public function showResetPassword(Request $request, array $params = []): string
    {
        Middleware::guest();

        $token = $params['token'] ?? '';

        if (empty($token)) {
            $this->session->flash('errors', ['token' => 'Invalid or missing reset token.']);
            return redirect(url('/forgot-password'));
        }

        return View::render('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
            'csrf'  => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Process password reset.
     */
    public function resetPassword(Request $request, array $params = []): string
    {
        Middleware::guest();
        Middleware::csrf();

        $errors = [];

        $token           = $params['token'] ?? $request->post('token') ?? '';
        $password        = $request->post('password') ?? '';
        $passwordConfirm = $request->post('password_confirmation') ?? '';

        if (empty($token)) {
            $errors['token'] = 'Invalid or missing reset token.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number.';
        }

        if ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return redirect(url('/reset-password/' . urlencode($token)));
        }

        try {
            $this->authService->resetPassword($token, $password);
            $this->session->flash('success', 'Your password has been reset. You can now sign in.');
            return redirect(url('/login'));
        } catch (\RuntimeException $e) {
            $this->session->flash('errors', ['reset' => $e->getMessage()]);
            return redirect(url('/reset-password/' . urlencode($token)));
        }
    }
}
