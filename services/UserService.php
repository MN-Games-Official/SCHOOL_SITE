<?php
/**
 * ============================================================================
 * UserService - User Profile & Gamification Management
 * StudyFlow - Student Self-Teaching App
 *
 * Manages user profiles, preferences, study streaks, achievements,
 * experience points, and self-comparison leaderboard positioning.
 * This is a personal-growth system, not a competitive LMS.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class UserService
{
    private FileStorage $storage;

    private const COLLECTION_USERS        = 'users';
    private const COLLECTION_ACHIEVEMENTS = 'achievements';
    private const COLLECTION_XP_LOG       = 'xp_log';
    private const COLLECTION_ACTIVITY     = 'activity_log';

    /** @var int XP required to advance from level N: N * 100 */
    private const XP_PER_LEVEL = 100;

    /** @var string Base directory for uploaded avatars */
    private const AVATAR_DIR = __DIR__ . '/../assets/avatars';

    /** @var int Maximum avatar file size (2 MB) */
    private const MAX_AVATAR_SIZE = 2097152;

    /** @var string[] Allowed avatar MIME types */
    private const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    /**
     * Get a user's public profile.
     *
     * @param string $userId
     * @return array
     * @throws RuntimeException If user not found
     */
    public function getProfile(string $userId): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        return [
            'id'              => $user['id'],
            'name'            => $user['name'] ?? '',
            'email'           => $user['email'] ?? '',
            'bio'             => $user['bio'] ?? '',
            'avatar'          => $user['avatar'] ?? null,
            'xp'              => $user['xp'] ?? 0,
            'level'           => $user['level'] ?? 1,
            'streak'          => $user['streak'] ?? 0,
            'preferences'     => $user['preferences'] ?? [],
            'email_verified'  => $user['email_verified'] ?? false,
            'last_active'     => $user['last_active'] ?? null,
            'created_at'      => $user['created_at'] ?? null,
        ];
    }

    /**
     * Update a user's profile fields.
     *
     * Allowed fields: name, bio, avatar, display_name.
     *
     * @param string $userId
     * @param array  $data
     * @return array Updated profile
     * @throws RuntimeException If user not found
     * @throws InvalidArgumentException If data contains disallowed fields
     */
    public function updateProfile(string $userId, array $data): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        $allowed = ['name', 'bio', 'avatar', 'display_name'];
        $update  = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("UserService: Field '{$key}' cannot be updated via profile.");
            }
            if (is_string($value)) {
                $value = trim($value);
            }
            $update[$key] = $value;
        }

        if (isset($update['name']) && $update['name'] === '') {
            throw new InvalidArgumentException('UserService: Name cannot be empty.');
        }

        $update['updated_at'] = date('c');
        $this->storage->update(self::COLLECTION_USERS, $userId, $update);

        return $this->getProfile($userId);
    }

    /**
     * Update user preferences (theme, notifications, study reminders, etc.).
     *
     * @param string $userId
     * @param array  $prefs Partial preferences to merge
     * @return array Updated preferences
     * @throws RuntimeException If user not found
     */
    public function updatePreferences(string $userId, array $prefs): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        $allowed = [
            'theme', 'notifications', 'study_reminders', 'reminder_time',
            'language', 'daily_goal_minutes', 'font_size', 'compact_mode',
        ];

        $current = $user['preferences'] ?? [];

        foreach ($prefs as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $current[$key] = $value;
            }
        }

        $this->storage->update(self::COLLECTION_USERS, $userId, [
            'preferences' => $current,
            'updated_at'  => date('c'),
        ]);

        return $current;
    }

    // -------------------------------------------------------------------------
    // Avatar Upload
    // -------------------------------------------------------------------------

    /**
     * Handle avatar file upload for a user.
     *
     * @param string $userId
     * @param array  $file   $_FILES entry (name, tmp_name, size, type, error)
     * @return string Relative path to the saved avatar
     * @throws InvalidArgumentException On validation failure
     * @throws RuntimeException On upload failure
     */
    public function updateAvatar(string $userId, array $file): string
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        // Validate upload error
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? -1;
            throw new InvalidArgumentException("UserService: Upload error (code {$code}).");
        }

        // Validate file size
        if ($file['size'] > self::MAX_AVATAR_SIZE) {
            throw new InvalidArgumentException('UserService: Avatar file exceeds 2 MB limit.');
        }

        // Validate MIME type
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_AVATAR_TYPES, true)) {
            throw new InvalidArgumentException('UserService: Invalid image type. Allowed: JPEG, PNG, GIF, WebP.');
        }

        // Ensure avatar directory exists
        if (!is_dir(self::AVATAR_DIR)) {
            if (!mkdir(self::AVATAR_DIR, 0755, true)) {
                throw new RuntimeException('UserService: Cannot create avatar directory.');
            }
        }

        // Delete old avatar if exists
        if (!empty($user['avatar'])) {
            $oldPath = __DIR__ . '/../' . $user['avatar'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Save new avatar
        $ext      = $this->mimeToExtension($mimeType);
        $filename = $userId . '_' . time() . '.' . $ext;
        $destPath = self::AVATAR_DIR . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('UserService: Failed to move uploaded file.');
        }

        $relativePath = 'assets/avatars/' . $filename;

        $this->storage->update(self::COLLECTION_USERS, $userId, [
            'avatar'     => $relativePath,
            'updated_at' => date('c'),
        ]);

        return $relativePath;
    }

    // -------------------------------------------------------------------------
    // Statistics
    // -------------------------------------------------------------------------

    /**
     * Aggregate user statistics across all activity.
     *
     * @param string $userId
     * @return array Statistics summary
     */
    public function getStats(string $userId): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        // Count study sessions
        $sessions = $this->storage->query('study_sessions', ['user_id' => $userId]);
        $totalStudyMinutes = 0;
        foreach ($sessions as $s) {
            $totalStudyMinutes += ($s['duration_minutes'] ?? 0);
        }

        // Count writings
        $writings     = $this->storage->query('writings', ['user_id' => $userId]);
        $totalWords   = 0;
        foreach ($writings as $w) {
            $totalWords += ($w['word_count'] ?? 0);
        }

        // Count quizzes
        $quizzes = $this->storage->query('quizzes', ['user_id' => $userId]);
        $avgScore = 0;
        if (!empty($quizzes)) {
            $scores   = array_column($quizzes, 'score');
            $scores   = array_filter($scores, fn($s) => $s !== null);
            $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
        }

        // Count flashcard decks
        $decks = $this->storage->query('flashcard_decks', ['user_id' => $userId]);

        // Count notes
        $notes = $this->storage->query('notes', ['user_id' => $userId]);

        return [
            'user_id'             => $userId,
            'xp'                  => $user['xp'] ?? 0,
            'level'               => $user['level'] ?? 1,
            'streak'              => $user['streak'] ?? 0,
            'total_study_minutes' => $totalStudyMinutes,
            'total_study_hours'   => round($totalStudyMinutes / 60, 1),
            'total_sessions'      => count($sessions),
            'total_writings'      => count($writings),
            'total_words_written' => $totalWords,
            'total_quizzes'       => count($quizzes),
            'avg_quiz_score'      => $avgScore,
            'total_decks'         => count($decks),
            'total_notes'         => count($notes),
            'member_since'        => $user['created_at'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Streak
    // -------------------------------------------------------------------------

    /**
     * Calculate the current study streak (consecutive days with activity).
     *
     * @param string $userId
     * @return array Streak info
     */
    public function getStreak(string $userId): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        $sessions = $this->storage->query('study_sessions', ['user_id' => $userId]);

        // Collect unique study dates
        $dates = [];
        foreach ($sessions as $session) {
            if (!empty($session['started_at'])) {
                $dates[] = date('Y-m-d', strtotime($session['started_at']));
            }
        }
        $dates = array_unique($dates);
        rsort($dates);

        $streak      = 0;
        $currentDate = date('Y-m-d');

        // If user studied today, start counting from today; otherwise from yesterday
        if (!empty($dates) && $dates[0] === $currentDate) {
            $checkDate = $currentDate;
        } elseif (!empty($dates) && $dates[0] === date('Y-m-d', strtotime('-1 day'))) {
            $checkDate = date('Y-m-d', strtotime('-1 day'));
        } else {
            return [
                'current'      => 0,
                'longest'      => $user['longest_streak'] ?? 0,
                'last_active'  => $user['last_active'] ?? null,
            ];
        }

        // Count consecutive days backwards
        while (in_array($checkDate, $dates, true)) {
            $streak++;
            $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
        }

        return [
            'current'      => $streak,
            'longest'      => max($streak, $user['longest_streak'] ?? 0),
            'last_active'  => $user['last_active'] ?? null,
        ];
    }

    /**
     * Update a user's streak after an activity. Call this when the user
     * performs any study action.
     *
     * @param string $userId
     * @return int Updated streak count
     */
    public function updateStreak(string $userId): int
    {
        $streakInfo = $this->getStreak($userId);
        $current    = $streakInfo['current'];
        $longest    = $streakInfo['longest'];

        $this->storage->update(self::COLLECTION_USERS, $userId, [
            'streak'         => $current,
            'longest_streak' => max($current, $longest),
            'last_active'    => date('c'),
            'updated_at'     => date('c'),
        ]);

        return $current;
    }

    // -------------------------------------------------------------------------
    // Achievements
    // -------------------------------------------------------------------------

    /**
     * Get all earned achievements for a user.
     *
     * @param string $userId
     * @return array List of achievements
     */
    public function getAchievements(string $userId): array
    {
        $achievements = $this->storage->query(self::COLLECTION_ACHIEVEMENTS, ['user_id' => $userId]);

        usort($achievements, function ($a, $b) {
            return strtotime($b['earned_at'] ?? '0') - strtotime($a['earned_at'] ?? '0');
        });

        return $achievements;
    }

    /**
     * Check for and award any new achievements the user has earned.
     *
     * @param string $userId
     * @return array Newly awarded achievements
     */
    public function checkAchievements(string $userId): array
    {
        $stats    = $this->getStats($userId);
        $existing = $this->getAchievements($userId);
        $earned   = array_column($existing, 'achievement_id');
        $newlyAwarded = [];

        $definitions = $this->getAchievementDefinitions();

        foreach ($definitions as $def) {
            if (in_array($def['id'], $earned, true)) {
                continue;
            }

            if ($this->meetsAchievementCriteria($def, $stats)) {
                $achievement = [
                    'id'             => $this->storage->generateId(),
                    'user_id'        => $userId,
                    'achievement_id' => $def['id'],
                    'name'           => $def['name'],
                    'description'    => $def['description'],
                    'icon'           => $def['icon'],
                    'xp_reward'      => $def['xp_reward'],
                    'earned_at'      => date('c'),
                ];

                $this->storage->write(self::COLLECTION_ACHIEVEMENTS, $achievement['id'], $achievement);
                $this->addXP($userId, $def['xp_reward'], 'Achievement: ' . $def['name']);
                $newlyAwarded[] = $achievement;
            }
        }

        return $newlyAwarded;
    }

    // -------------------------------------------------------------------------
    // Experience Points
    // -------------------------------------------------------------------------

    /**
     * Add experience points to a user and handle level-up.
     *
     * @param string $userId
     * @param int    $amount Points to add
     * @param string $reason Description of why XP was awarded
     * @return array Updated XP/level info
     * @throws InvalidArgumentException If amount < 0
     */
    public function addXP(string $userId, int $amount, string $reason = ''): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('UserService: XP amount cannot be negative.');
        }

        if ($amount === 0) {
            $user = $this->storage->read(self::COLLECTION_USERS, $userId);
            return [
                'xp'    => $user['xp'] ?? 0,
                'level' => $user['level'] ?? 1,
            ];
        }

        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        $currentXP    = ($user['xp'] ?? 0) + $amount;
        $currentLevel = $user['level'] ?? 1;

        // Calculate new level
        while ($currentXP >= $currentLevel * self::XP_PER_LEVEL) {
            $currentXP -= $currentLevel * self::XP_PER_LEVEL;
            $currentLevel++;
        }

        $this->storage->update(self::COLLECTION_USERS, $userId, [
            'xp'         => $currentXP,
            'level'      => $currentLevel,
            'updated_at' => date('c'),
        ]);

        // Log XP event
        $logId = $this->storage->generateId();
        $this->storage->write(self::COLLECTION_XP_LOG, $logId, [
            'id'        => $logId,
            'user_id'   => $userId,
            'amount'    => $amount,
            'reason'    => $reason,
            'new_total' => $currentXP,
            'new_level' => $currentLevel,
            'timestamp' => date('c'),
        ]);

        return [
            'xp'         => $currentXP,
            'level'      => $currentLevel,
            'xp_added'   => $amount,
            'reason'     => $reason,
        ];
    }

    // -------------------------------------------------------------------------
    // Self-Comparison Leaderboard
    // -------------------------------------------------------------------------

    /**
     * Get a user's leaderboard position based on self-comparison over time.
     * Compares current week/month performance to previous periods.
     *
     * @param string $userId
     * @return array Performance comparison
     */
    public function getLeaderboardPosition(string $userId): array
    {
        $user = $this->storage->read(self::COLLECTION_USERS, $userId);
        if ($user === null) {
            throw new RuntimeException('UserService: User not found.');
        }

        $sessions = $this->storage->query('study_sessions', ['user_id' => $userId]);

        $thisWeekMinutes = 0;
        $lastWeekMinutes = 0;
        $thisMonthMinutes = 0;
        $lastMonthMinutes = 0;

        $weekStart     = strtotime('monday this week');
        $lastWeekStart = strtotime('monday last week');
        $monthStart    = strtotime('first day of this month');
        $lastMonthStart = strtotime('first day of last month');

        foreach ($sessions as $s) {
            $ts       = strtotime($s['started_at'] ?? '');
            $duration = $s['duration_minutes'] ?? 0;

            if ($ts >= $weekStart) {
                $thisWeekMinutes += $duration;
            } elseif ($ts >= $lastWeekStart && $ts < $weekStart) {
                $lastWeekMinutes += $duration;
            }

            if ($ts >= $monthStart) {
                $thisMonthMinutes += $duration;
            } elseif ($ts >= $lastMonthStart && $ts < $monthStart) {
                $lastMonthMinutes += $duration;
            }
        }

        $weeklyChange  = $lastWeekMinutes > 0
            ? round((($thisWeekMinutes - $lastWeekMinutes) / $lastWeekMinutes) * 100, 1)
            : ($thisWeekMinutes > 0 ? 100 : 0);

        $monthlyChange = $lastMonthMinutes > 0
            ? round((($thisMonthMinutes - $lastMonthMinutes) / $lastMonthMinutes) * 100, 1)
            : ($thisMonthMinutes > 0 ? 100 : 0);

        return [
            'user_id'            => $userId,
            'xp'                 => $user['xp'] ?? 0,
            'level'              => $user['level'] ?? 1,
            'streak'             => $user['streak'] ?? 0,
            'this_week_minutes'  => $thisWeekMinutes,
            'last_week_minutes'  => $lastWeekMinutes,
            'weekly_change_pct'  => $weeklyChange,
            'this_month_minutes' => $thisMonthMinutes,
            'last_month_minutes' => $lastMonthMinutes,
            'monthly_change_pct' => $monthlyChange,
            'trend'              => $weeklyChange >= 0 ? 'improving' : 'declining',
        ];
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Map MIME type to file extension.
     */
    private function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'bin',
        };
    }

    /**
     * Get all achievement definitions.
     */
    private function getAchievementDefinitions(): array
    {
        return [
            ['id' => 'first_session',     'name' => 'First Steps',         'description' => 'Complete your first study session.',            'icon' => '🎓', 'xp_reward' => 50,  'criteria' => ['total_sessions', '>=', 1]],
            ['id' => 'ten_sessions',      'name' => 'Dedicated Learner',   'description' => 'Complete 10 study sessions.',                   'icon' => '📚', 'xp_reward' => 100, 'criteria' => ['total_sessions', '>=', 10]],
            ['id' => 'fifty_sessions',    'name' => 'Study Master',        'description' => 'Complete 50 study sessions.',                   'icon' => '🏆', 'xp_reward' => 250, 'criteria' => ['total_sessions', '>=', 50]],
            ['id' => 'first_writing',     'name' => 'Aspiring Writer',     'description' => 'Write your first essay or piece.',              'icon' => '✍️', 'xp_reward' => 50,  'criteria' => ['total_writings', '>=', 1]],
            ['id' => 'thousand_words',    'name' => 'Wordsmith',           'description' => 'Write 1,000 words total.',                      'icon' => '📝', 'xp_reward' => 100, 'criteria' => ['total_words_written', '>=', 1000]],
            ['id' => 'first_quiz',        'name' => 'Quiz Taker',          'description' => 'Complete your first quiz.',                     'icon' => '❓', 'xp_reward' => 50,  'criteria' => ['total_quizzes', '>=', 1]],
            ['id' => 'perfect_quiz',      'name' => 'Perfect Score',       'description' => 'Score 100% on any quiz.',                      'icon' => '💯', 'xp_reward' => 200, 'criteria' => ['avg_quiz_score', '>=', 100]],
            ['id' => 'streak_7',          'name' => 'Week Warrior',        'description' => 'Maintain a 7-day study streak.',                'icon' => '🔥', 'xp_reward' => 150, 'criteria' => ['streak', '>=', 7]],
            ['id' => 'streak_30',         'name' => 'Monthly Marathon',    'description' => 'Maintain a 30-day study streak.',               'icon' => '🌟', 'xp_reward' => 500, 'criteria' => ['streak', '>=', 30]],
            ['id' => 'hour_studied',      'name' => 'Hour of Focus',       'description' => 'Study for at least one hour total.',            'icon' => '⏱️', 'xp_reward' => 50,  'criteria' => ['total_study_minutes', '>=', 60]],
            ['id' => 'ten_hours',         'name' => 'Time Investor',       'description' => 'Study for 10 hours total.',                     'icon' => '🕐', 'xp_reward' => 200, 'criteria' => ['total_study_minutes', '>=', 600]],
            ['id' => 'note_taker',        'name' => 'Note Taker',          'description' => 'Create 10 notes.',                              'icon' => '📓', 'xp_reward' => 75,  'criteria' => ['total_notes', '>=', 10]],
        ];
    }

    /**
     * Check if user stats meet achievement criteria.
     */
    private function meetsAchievementCriteria(array $def, array $stats): bool
    {
        if (!isset($def['criteria']) || count($def['criteria']) !== 3) {
            return false;
        }

        [$field, $operator, $threshold] = $def['criteria'];

        $value = $stats[$field] ?? 0;

        return match ($operator) {
            '>='    => $value >= $threshold,
            '>'     => $value > $threshold,
            '=='    => $value == $threshold,
            '<='    => $value <= $threshold,
            '<'     => $value < $threshold,
            default => false,
        };
    }
}
