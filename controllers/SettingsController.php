<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ProgressService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class SettingsController
{
    private UserService $userService;
    private AuthService $authService;
    private ProgressService $progressService;
    private Session $session;

    public function __construct()
    {
        $this->userService     = new UserService();
        $this->authService     = new AuthService();
        $this->progressService = new ProgressService();
        $this->session         = new Session();
    }

    /**
     * Settings overview page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $profile = $this->userService->getProfile($userId);
            $stats   = $this->userService->getStats($userId);

            return View::render('settings/index', [
                'title'   => 'Settings',
                'profile' => $profile,
                'stats'   => $stats,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load settings.');
            return View::render('settings/index', [
                'title'   => 'Settings',
                'profile' => [],
                'stats'   => [],
            ], 'layouts/main');
        }
    }

    /**
     * Profile settings page.
     */
    public function profile(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $profile      = $this->userService->getProfile($userId);
            $achievements = $this->userService->getAchievements($userId);
            $streak       = $this->userService->getStreak($userId);

            return View::render('settings/profile', [
                'title'        => 'Profile Settings',
                'profile'      => $profile,
                'achievements' => $achievements,
                'streak'       => $streak,
                'csrf'         => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load profile.');
            return redirect(url('/settings'));
        }
    }

    /**
     * Save profile changes.
     */
    public function updateProfile(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $name = trim($request->post('name') ?? '');
        $bio  = trim($request->post('bio') ?? '');

        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Name must be under 100 characters.';
        }

        if (strlen($bio) > 500) {
            $errors['bio'] = 'Bio must be under 500 characters.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', ['name' => $name, 'bio' => $bio]);
            return redirect(url('/settings/profile'));
        }

        try {
            $this->userService->updateProfile($userId, [
                'name' => $name,
                'bio'  => $bio,
            ]);

            // Update session user data
            $user = $this->session->get('user');
            if (is_array($user)) {
                $user['name'] = $name;
                $this->session->set('user', $user);
            }

            $this->session->flash('success', 'Profile updated!');
            return redirect(url('/settings/profile'));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/settings/profile'));
        }
    }

    /**
     * Preferences page.
     */
    public function preferences(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $profile = $this->userService->getProfile($userId);

            return View::render('settings/preferences', [
                'title'       => 'Preferences',
                'preferences' => $profile['preferences'] ?? [],
                'csrf'        => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load preferences.');
            return redirect(url('/settings'));
        }
    }

    /**
     * Save preferences.
     */
    public function updatePreferences(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');

        $prefs = [
            'theme'              => $request->post('theme') ?? 'light',
            'email_notifications' => $request->post('email_notifications') ? true : false,
            'study_reminders'    => $request->post('study_reminders') ? true : false,
            'daily_goal_minutes' => max(0, (int) ($request->post('daily_goal_minutes') ?? 30)),
            'language'           => $request->post('language') ?? 'en',
            'timezone'           => $request->post('timezone') ?? 'UTC',
        ];

        // Validate theme
        $validThemes = ['light', 'dark', 'auto'];
        if (!in_array($prefs['theme'], $validThemes, true)) {
            $prefs['theme'] = 'light';
        }

        try {
            $this->userService->updatePreferences($userId, $prefs);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Preferences saved!',
                ]);
            }

            $this->session->flash('success', 'Preferences saved!');
            return redirect(url('/settings/preferences'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/settings/preferences'));
        }
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $currentPassword = $request->post('current_password') ?? '';
        $newPassword     = $request->post('new_password') ?? '';
        $confirmPassword = $request->post('confirm_password') ?? '';

        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required.';
        }

        if (empty($newPassword)) {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors['new_password'] = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $newPassword)) {
            $errors['new_password'] = 'New password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $errors['new_password'] = 'New password must contain at least one number.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return redirect(url('/settings/profile'));
        }

        try {
            $this->authService->updatePassword($userId, $currentPassword, $newPassword);

            $this->session->flash('success', 'Password changed successfully!');
            return redirect(url('/settings/profile'));
        } catch (\RuntimeException $e) {
            $this->session->flash('errors', ['current_password' => $e->getMessage()]);
            return redirect(url('/settings/profile'));
        }
    }

    /**
     * Upload a new avatar.
     */
    public function updateAvatar(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Please select an image to upload.'], 400);
            }
            $this->session->flash('error', 'Please select an image to upload.');
            return redirect(url('/settings/profile'));
        }

        $file     = $_FILES['avatar'];
        $maxSize  = 2 * 1024 * 1024; // 2MB
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if ($file['size'] > $maxSize) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Image must be under 2MB.'], 400);
            }
            $this->session->flash('error', 'Image must be under 2MB.');
            return redirect(url('/settings/profile'));
        }

        if (!in_array($file['type'], $allowed, true)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed.'], 400);
            }
            $this->session->flash('error', 'Only JPEG, PNG, GIF, and WebP images are allowed.');
            return redirect(url('/settings/profile'));
        }

        try {
            $avatarPath = $this->userService->updateAvatar($userId, $file);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Avatar updated!',
                    'avatar'  => $avatarPath,
                ]);
            }

            $this->session->flash('success', 'Avatar updated!');
            return redirect(url('/settings/profile'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/settings/profile'));
        }
    }

    /**
     * Delete the user account.
     */
    public function deleteAccount(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId  = $this->session->get('user_id');
        $confirm = $request->post('confirm_delete') ?? '';

        if ($confirm !== 'DELETE') {
            $this->session->flash('error', 'Please type DELETE to confirm account deletion.');
            return redirect(url('/settings'));
        }

        try {
            $this->authService->deleteAccount($userId);

            $this->session->set('user_id', null);
            $this->session->set('user', null);
            session_destroy();

            $this->session->flash('success', 'Your account has been deleted.');
            return redirect(url('/login'));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/settings'));
        }
    }

    /**
     * Export all user data.
     */
    public function exportData(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $profile  = $this->userService->getProfile($userId);
            $stats    = $this->userService->getStats($userId);
            $progress = $this->progressService->exportProgressReport($userId);

            $exportData = [
                'profile'  => $profile,
                'stats'    => $stats,
                'progress' => $progress,
                'exported_at' => date('c'),
            ];

            return Response::json([
                'success' => true,
                'data'    => $exportData,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to export data.',
            ], 500);
        }
    }

    /**
     * AJAX: Get current preferences.
     */
    public function getPreferences(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $profile = $this->userService->getProfile($userId);

            return Response::json([
                'success'     => true,
                'preferences' => $profile['preferences'] ?? [],
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load preferences.',
            ], 500);
        }
    }
}
