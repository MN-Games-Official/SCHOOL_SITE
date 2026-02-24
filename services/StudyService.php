<?php
/**
 * ============================================================================
 * StudyService - Study Session Management
 * StudyFlow - Student Self-Teaching App
 *
 * Tracks study sessions, calculates study time, manages study goals,
 * generates heatmap data, and produces weekly/monthly study reports.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class StudyService
{
    private FileStorage $storage;

    private const COLLECTION_SESSIONS = 'study_sessions';
    private const COLLECTION_GOALS    = 'study_goals';

    /** @var int XP per minute of study time */
    private const XP_PER_MINUTE = 2;

    /** @var int Minimum session duration (minutes) to award XP */
    private const MIN_SESSION_MINUTES = 1;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Session Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Start a new study session.
     *
     * @param string $userId
     * @param string $subjectId
     * @param string $topicId
     * @return array The created session
     * @throws InvalidArgumentException On missing data
     */
    public function startSession(string $userId, string $subjectId, string $topicId): array
    {
        if ($userId === '' || $subjectId === '' || $topicId === '') {
            throw new InvalidArgumentException('StudyService: userId, subjectId, and topicId are required.');
        }

        // Check for an existing active session for this user
        $active = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId) {
            return ($s['user_id'] ?? '') === $userId && ($s['status'] ?? '') === 'active';
        });

        // Auto-end any lingering active session
        foreach ($active as $session) {
            $this->endSession($session['id'] ?? $session['_id'] ?? '', 'Auto-ended: new session started.');
        }

        $sessionId = $this->storage->generateId();
        $now       = date('c');

        $session = [
            'id'               => $sessionId,
            'user_id'          => $userId,
            'subject_id'       => $subjectId,
            'topic_id'         => $topicId,
            'status'           => 'active',
            'started_at'       => $now,
            'ended_at'         => null,
            'duration_minutes' => 0,
            'notes'            => '',
            'xp_earned'        => 0,
            'created_at'       => $now,
        ];

        $this->storage->write(self::COLLECTION_SESSIONS, $sessionId, $session);

        return $session;
    }

    /**
     * End an active study session.
     *
     * Calculates duration, awards XP, and records the session notes.
     *
     * @param string $sessionId
     * @param string $notes     Optional session notes
     * @return array The completed session
     * @throws RuntimeException If session not found or already ended
     */
    public function endSession(string $sessionId, string $notes = ''): array
    {
        $session = $this->storage->read(self::COLLECTION_SESSIONS, $sessionId);

        if ($session === null) {
            throw new RuntimeException('StudyService: Session not found.');
        }

        if (($session['status'] ?? '') !== 'active') {
            throw new RuntimeException('StudyService: Session is not active.');
        }

        $now       = date('c');
        $startedAt = strtotime($session['started_at']);
        $endedAt   = time();
        $duration  = max(0, round(($endedAt - $startedAt) / 60, 1));

        // Cap individual session at 12 hours to prevent data anomalies
        $duration = min($duration, 720);

        $xpEarned = 0;
        if ($duration >= self::MIN_SESSION_MINUTES) {
            $xpEarned = (int) round($duration * self::XP_PER_MINUTE);
        }

        $update = [
            'status'           => 'completed',
            'ended_at'         => $now,
            'duration_minutes' => $duration,
            'notes'            => $notes,
            'xp_earned'        => $xpEarned,
        ];

        $this->storage->update(self::COLLECTION_SESSIONS, $sessionId, $update);

        return array_merge($session, $update);
    }

    /**
     * Get a study session by ID.
     *
     * @param string $sessionId
     * @return array
     * @throws RuntimeException If not found
     */
    public function getSession(string $sessionId): array
    {
        $session = $this->storage->read(self::COLLECTION_SESSIONS, $sessionId);

        if ($session === null) {
            throw new RuntimeException('StudyService: Session not found.');
        }

        return $session;
    }

    // -------------------------------------------------------------------------
    // Session Queries
    // -------------------------------------------------------------------------

    /**
     * Get all sessions for a user, with optional filtering.
     *
     * Supported filters:
     *   - subject_id: filter by subject
     *   - status:     'active' or 'completed'
     *   - from:       ISO date (sessions after)
     *   - to:         ISO date (sessions before)
     *   - limit:      max results
     *   - offset:     skip N results
     *
     * @param string $userId
     * @param array  $filters
     * @return array
     */
    public function getUserSessions(string $userId, array $filters = []): array
    {
        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId, $filters) {
            if (($s['user_id'] ?? '') !== $userId) {
                return false;
            }

            if (!empty($filters['subject_id']) && ($s['subject_id'] ?? '') !== $filters['subject_id']) {
                return false;
            }

            if (!empty($filters['status']) && ($s['status'] ?? '') !== $filters['status']) {
                return false;
            }

            if (!empty($filters['from'])) {
                $from = strtotime($filters['from']);
                if (strtotime($s['started_at'] ?? '') < $from) {
                    return false;
                }
            }

            if (!empty($filters['to'])) {
                $to = strtotime($filters['to']);
                if (strtotime($s['started_at'] ?? '') > $to) {
                    return false;
                }
            }

            return true;
        });

        // Sort by start time descending
        usort($sessions, fn($a, $b) => strtotime($b['started_at'] ?? '0') - strtotime($a['started_at'] ?? '0'));

        // Apply pagination
        $offset = (int) ($filters['offset'] ?? 0);
        $limit  = (int) ($filters['limit'] ?? 50);

        return array_slice($sessions, $offset, $limit);
    }

    /**
     * Get all sessions for a user on a specific date.
     *
     * @param string $userId
     * @param string $date Date string (Y-m-d)
     * @return array
     */
    public function getSessionsByDate(string $userId, string $date): array
    {
        $targetDate = date('Y-m-d', strtotime($date));

        return $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId, $targetDate) {
            if (($s['user_id'] ?? '') !== $userId) {
                return false;
            }
            $sessionDate = date('Y-m-d', strtotime($s['started_at'] ?? ''));
            return $sessionDate === $targetDate;
        });
    }

    // -------------------------------------------------------------------------
    // Study Time Analysis
    // -------------------------------------------------------------------------

    /**
     * Get total study time for a user within a period.
     *
     * @param string $userId
     * @param string $period 'today', 'week', 'month', 'year', 'all'
     * @return array Time statistics
     */
    public function getTotalStudyTime(string $userId, string $period = 'all'): array
    {
        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, ['user_id' => $userId]);
        $cutoff   = $this->periodCutoff($period);
        $total    = 0;
        $count    = 0;

        foreach ($sessions as $s) {
            if (($s['status'] ?? '') !== 'completed') {
                continue;
            }
            if ($cutoff > 0 && strtotime($s['started_at'] ?? '') < $cutoff) {
                continue;
            }
            $total += ($s['duration_minutes'] ?? 0);
            $count++;
        }

        return [
            'period'         => $period,
            'total_minutes'  => round($total, 1),
            'total_hours'    => round($total / 60, 1),
            'session_count'  => $count,
            'avg_session'    => $count > 0 ? round($total / $count, 1) : 0,
        ];
    }

    /**
     * Get the current study streak (consecutive days with completed sessions).
     *
     * @param string $userId
     * @return array Streak info
     */
    public function getStudyStreak(string $userId): array
    {
        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId) {
            return ($s['user_id'] ?? '') === $userId && ($s['status'] ?? '') === 'completed';
        });

        $dates = [];
        foreach ($sessions as $s) {
            if (!empty($s['started_at'])) {
                $dates[] = date('Y-m-d', strtotime($s['started_at']));
            }
        }

        $dates = array_unique($dates);
        rsort($dates);

        if (empty($dates)) {
            return ['current' => 0, 'longest' => 0];
        }

        // Check if today or yesterday has activity
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        if ($dates[0] !== $today && $dates[0] !== $yesterday) {
            return ['current' => 0, 'longest' => $this->calculateLongestStreak($dates)];
        }

        $streak    = 0;
        $checkDate = $dates[0];

        while (in_array($checkDate, $dates, true)) {
            $streak++;
            $checkDate = date('Y-m-d', strtotime($checkDate . ' -1 day'));
        }

        return [
            'current' => $streak,
            'longest' => max($streak, $this->calculateLongestStreak($dates)),
        ];
    }

    /**
     * Get heatmap data showing study activity per day.
     *
     * @param string $userId
     * @return array Day => minutes mapping for the last 365 days
     */
    public function getStudyHeatmap(string $userId): array
    {
        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId) {
            return ($s['user_id'] ?? '') === $userId && ($s['status'] ?? '') === 'completed';
        });

        $cutoff = strtotime('-365 days');
        $heatmap = [];

        foreach ($sessions as $s) {
            $ts = strtotime($s['started_at'] ?? '');
            if ($ts < $cutoff) {
                continue;
            }
            $day = date('Y-m-d', $ts);
            if (!isset($heatmap[$day])) {
                $heatmap[$day] = ['date' => $day, 'minutes' => 0, 'sessions' => 0];
            }
            $heatmap[$day]['minutes']  += ($s['duration_minutes'] ?? 0);
            $heatmap[$day]['sessions'] += 1;
        }

        // Sort by date
        ksort($heatmap);

        return array_values($heatmap);
    }

    // -------------------------------------------------------------------------
    // Study Goals
    // -------------------------------------------------------------------------

    /**
     * Get a user's study goals.
     *
     * @param string $userId
     * @return array List of goals
     */
    public function getStudyGoals(string $userId): array
    {
        $goals = $this->storage->query(self::COLLECTION_GOALS, ['user_id' => $userId]);

        usort($goals, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        return $goals;
    }

    /**
     * Set a study goal for a user.
     *
     * @param string $userId
     * @param string $type   'daily' or 'weekly'
     * @param int    $target Target minutes
     * @return array The created/updated goal
     * @throws InvalidArgumentException On invalid type or target
     */
    public function setStudyGoal(string $userId, string $type, int $target): array
    {
        if (!in_array($type, ['daily', 'weekly'], true)) {
            throw new InvalidArgumentException('StudyService: Goal type must be "daily" or "weekly".');
        }

        if ($target < 1 || $target > 1440) {
            throw new InvalidArgumentException('StudyService: Target must be between 1 and 1440 minutes.');
        }

        // Check for existing goal of same type
        $existing = $this->storage->query(self::COLLECTION_GOALS, function (array $g) use ($userId, $type) {
            return ($g['user_id'] ?? '') === $userId && ($g['type'] ?? '') === $type;
        });

        $now = date('c');

        if (!empty($existing)) {
            $goalId = $existing[0]['id'] ?? $existing[0]['_id'] ?? '';
            $this->storage->update(self::COLLECTION_GOALS, $goalId, [
                'target_minutes' => $target,
                'updated_at'     => $now,
            ]);
            return array_merge($existing[0], ['target_minutes' => $target]);
        }

        $goalId = $this->storage->generateId();
        $goal = [
            'id'             => $goalId,
            'user_id'        => $userId,
            'type'           => $type,
            'target_minutes' => $target,
            'active'         => true,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $this->storage->write(self::COLLECTION_GOALS, $goalId, $goal);

        return $goal;
    }

    /**
     * Check progress toward study goals.
     *
     * @param string $userId
     * @return array Goal progress details
     */
    public function checkGoalProgress(string $userId): array
    {
        $goals    = $this->getStudyGoals($userId);
        $progress = [];

        foreach ($goals as $goal) {
            if (!($goal['active'] ?? true)) {
                continue;
            }

            $type   = $goal['type'] ?? 'daily';
            $target = $goal['target_minutes'] ?? 30;
            $period = $type === 'daily' ? 'today' : 'week';
            $time   = $this->getTotalStudyTime($userId, $period);

            $actual     = $time['total_minutes'];
            $percentage = $target > 0 ? min(100, round(($actual / $target) * 100, 1)) : 0;

            $progress[] = [
                'goal_id'        => $goal['id'] ?? '',
                'type'           => $type,
                'target_minutes' => $target,
                'actual_minutes' => $actual,
                'percentage'     => $percentage,
                'completed'      => $percentage >= 100,
                'remaining'      => max(0, $target - $actual),
            ];
        }

        return $progress;
    }

    // -------------------------------------------------------------------------
    // Activity & Reports
    // -------------------------------------------------------------------------

    /**
     * Get recent study activity for a user.
     *
     * @param string $userId
     * @param int    $limit  Max items
     * @return array Recent sessions with enriched data
     */
    public function getRecentActivity(string $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId) {
            return ($s['user_id'] ?? '') === $userId && ($s['status'] ?? '') === 'completed';
        });

        usort($sessions, fn($a, $b) => strtotime($b['started_at'] ?? '0') - strtotime($a['started_at'] ?? '0'));

        $recent = array_slice($sessions, 0, $limit);

        return array_map(function (array $s) {
            return [
                'id'               => $s['id'] ?? $s['_id'] ?? '',
                'subject_id'       => $s['subject_id'] ?? '',
                'topic_id'         => $s['topic_id'] ?? '',
                'started_at'       => $s['started_at'] ?? '',
                'ended_at'         => $s['ended_at'] ?? '',
                'duration_minutes' => $s['duration_minutes'] ?? 0,
                'xp_earned'        => $s['xp_earned'] ?? 0,
                'notes'            => $s['notes'] ?? '',
                'time_ago'         => $this->timeAgo($s['started_at'] ?? ''),
            ];
        }, $recent);
    }

    /**
     * Generate a comprehensive study report for a period.
     *
     * @param string $userId
     * @param string $period 'week' or 'month'
     * @return array Report data
     */
    public function generateStudyReport(string $userId, string $period = 'week'): array
    {
        $cutoff   = $this->periodCutoff($period);
        $sessions = $this->storage->query(self::COLLECTION_SESSIONS, function (array $s) use ($userId, $cutoff) {
            return ($s['user_id'] ?? '') === $userId
                && ($s['status'] ?? '') === 'completed'
                && strtotime($s['started_at'] ?? '') >= $cutoff;
        });

        $totalMinutes = 0;
        $totalXP      = 0;
        $subjectTime  = [];
        $dailyTime    = [];

        foreach ($sessions as $s) {
            $minutes = $s['duration_minutes'] ?? 0;
            $totalMinutes += $minutes;
            $totalXP      += ($s['xp_earned'] ?? 0);

            $subjectId = $s['subject_id'] ?? 'unknown';
            $subjectTime[$subjectId] = ($subjectTime[$subjectId] ?? 0) + $minutes;

            $day = date('Y-m-d', strtotime($s['started_at'] ?? ''));
            $dailyTime[$day] = ($dailyTime[$day] ?? 0) + $minutes;
        }

        // Days studied
        $daysStudied = count($dailyTime);
        $periodDays  = $period === 'week' ? 7 : (int) date('t');

        // Goal progress
        $goalProgress = $this->checkGoalProgress($userId);

        ksort($dailyTime);

        return [
            'period'          => $period,
            'from'            => date('Y-m-d', $cutoff),
            'to'              => date('Y-m-d'),
            'total_minutes'   => round($totalMinutes, 1),
            'total_hours'     => round($totalMinutes / 60, 1),
            'total_sessions'  => count($sessions),
            'total_xp'        => $totalXP,
            'days_studied'    => $daysStudied,
            'total_days'      => $periodDays,
            'consistency_pct' => $periodDays > 0 ? round(($daysStudied / $periodDays) * 100, 1) : 0,
            'avg_daily'       => $daysStudied > 0 ? round($totalMinutes / $daysStudied, 1) : 0,
            'subject_breakdown' => $subjectTime,
            'daily_breakdown'   => $dailyTime,
            'goal_progress'     => $goalProgress,
            'generated_at'      => date('c'),
        ];
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Get a Unix timestamp cutoff for a period string.
     */
    private function periodCutoff(string $period): int
    {
        return match ($period) {
            'today' => strtotime('today'),
            'week'  => strtotime('monday this week'),
            'month' => strtotime('first day of this month'),
            'year'  => strtotime('first day of January this year'),
            'all'   => 0,
            default => 0,
        };
    }

    /**
     * Calculate the longest streak from a sorted list of date strings.
     */
    private function calculateLongestStreak(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        sort($dates);
        $longest = 1;
        $current = 1;

        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $diff = (strtotime($dates[$i]) - strtotime($dates[$i - 1])) / 86400;
            if (abs($diff - 1) < 0.001) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    /**
     * Format a timestamp as a human-readable "time ago" string.
     */
    private function timeAgo(string $datetime): string
    {
        $ts   = strtotime($datetime);
        $diff = time() - $ts;

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            $m = (int) floor($diff / 60);
            return $m . ' minute' . ($m !== 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $h = (int) floor($diff / 3600);
            return $h . ' hour' . ($h !== 1 ? 's' : '') . ' ago';
        }
        $d = (int) floor($diff / 86400);
        if ($d < 30) {
            return $d . ' day' . ($d !== 1 ? 's' : '') . ' ago';
        }
        $m = (int) floor($d / 30);
        return $m . ' month' . ($m !== 1 ? 's' : '') . ' ago';
    }
}
