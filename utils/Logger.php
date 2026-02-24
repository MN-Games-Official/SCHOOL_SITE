<?php
/**
 * ============================================================================
 * Logger - Logging Utility
 * StudyFlow - Student Self-Teaching App
 *
 * File-based logging system with support for standard log levels, user
 * activity tracking, study session logging, writing activity logging,
 * and statistics aggregation.
 *
 * Log files are stored as:
 *   {logDir}/app-YYYY-MM-DD.log          – general application log
 *   {logDir}/activity/{userId}.json      – per-user activity journal
 *   {logDir}/study/{userId}.json         – per-user study sessions
 *   {logDir}/writing/{userId}.json       – per-user writing activity
 * ============================================================================
 */

class Logger
{
    /** @var string Root directory for log files */
    private string $logDir;

    /** @var int Maximum entries kept in per-user JSON logs */
    private int $maxEntries;

    /** PSR-3-style log levels with numeric severity */
    private const LEVELS = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * @param string $logDir     Directory to store log files
     * @param int    $maxEntries Max entries per user JSON log file (FIFO)
     *
     * @throws RuntimeException If directory cannot be created
     */
    public function __construct(string $logDir, int $maxEntries = 5000)
    {
        $this->logDir     = rtrim($logDir, '/');
        $this->maxEntries = $maxEntries;

        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                throw new RuntimeException(
                    "Logger: Unable to create log directory: {$this->logDir}"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // General Logging
    // -------------------------------------------------------------------------

    /**
     * Write a log entry at the given level.
     *
     * @param string $level   Log level (debug, info, warning, error, etc.)
     * @param string $message Human-readable message
     * @param array  $context Additional key-value context
     *
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }

        $entry = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate($message, $context),
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        $file = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log a debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log an informational message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a warning.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log an error.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    // -------------------------------------------------------------------------
    // User Activity Logging
    // -------------------------------------------------------------------------

    /**
     * Log a user activity event.
     *
     * @param string $userId  User identifier
     * @param string $action  Action performed (e.g. "login", "quiz_completed")
     * @param array  $details Additional details
     *
     * @return void
     */
    public function logActivity(string $userId, string $action, array $details = []): void
    {
        $entry = [
            'action'    => $action,
            'details'   => $details,
            'timestamp' => date('c'),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        ];

        $this->appendToUserLog('activity', $userId, $entry);

        // Also write to the general log
        $this->info("User activity: {$action}", [
            'user_id' => $userId,
            'action'  => $action,
        ]);
    }

    /**
     * Retrieve recent activity entries for a user.
     *
     * @param string $userId User identifier
     * @param int    $limit  Maximum entries to return
     *
     * @return array Most-recent-first activity entries
     */
    public function getActivityLog(string $userId, int $limit = 50): array
    {
        $entries = $this->readUserLog('activity', $userId);

        // Sort newest first
        usort($entries, fn($a, $b) => strtotime($b['timestamp'] ?? '0') <=> strtotime($a['timestamp'] ?? '0'));

        return array_slice($entries, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Study Session Logging
    // -------------------------------------------------------------------------

    /**
     * Log a study session.
     *
     * @param string $userId   User identifier
     * @param string $subject  Subject studied
     * @param int    $duration Duration in seconds
     * @param string $type     Session type (reading, flashcards, quiz, writing, etc.)
     *
     * @return void
     */
    public function logStudy(string $userId, string $subject, int $duration, string $type = 'general'): void
    {
        $entry = [
            'subject'   => $subject,
            'duration'  => $duration,
            'type'      => $type,
            'date'      => date('Y-m-d'),
            'timestamp' => date('c'),
        ];

        $this->appendToUserLog('study', $userId, $entry);

        $this->info("Study session logged", [
            'user_id'  => $userId,
            'subject'  => $subject,
            'duration' => $duration,
            'type'     => $type,
        ]);
    }

    /**
     * Get aggregated study statistics for a user over a time period.
     *
     * @param string $userId User identifier
     * @param string $period Period: "day", "week", "month", "year", or "all"
     *
     * @return array Statistics summary
     */
    public function getStudyStats(string $userId, string $period = 'week'): array
    {
        $entries = $this->readUserLog('study', $userId);
        $cutoff  = $this->periodCutoff($period);

        // Filter by period
        $filtered = array_filter($entries, function (array $entry) use ($cutoff) {
            $ts = strtotime($entry['timestamp'] ?? '0');
            return $ts >= $cutoff;
        });

        $totalDuration     = 0;
        $sessionCount      = 0;
        $subjectDurations  = [];
        $typeDurations     = [];
        $dailyDurations    = [];

        foreach ($filtered as $entry) {
            $dur = (int) ($entry['duration'] ?? 0);
            $totalDuration += $dur;
            $sessionCount++;

            $subject = $entry['subject'] ?? 'unknown';
            $subjectDurations[$subject] = ($subjectDurations[$subject] ?? 0) + $dur;

            $type = $entry['type'] ?? 'general';
            $typeDurations[$type] = ($typeDurations[$type] ?? 0) + $dur;

            $day = $entry['date'] ?? date('Y-m-d', strtotime($entry['timestamp'] ?? 'now'));
            $dailyDurations[$day] = ($dailyDurations[$day] ?? 0) + $dur;
        }

        // Calculate streak (consecutive days with study activity)
        ksort($dailyDurations);
        $streak        = 0;
        $currentStreak = 0;
        $prevDate      = null;

        foreach (array_keys($dailyDurations) as $day) {
            if ($prevDate !== null) {
                $diff = (strtotime($day) - strtotime($prevDate)) / 86400;
                if ($diff === 1.0) {
                    $currentStreak++;
                } else {
                    $currentStreak = 1;
                }
            } else {
                $currentStreak = 1;
            }
            $streak   = max($streak, $currentStreak);
            $prevDate = $day;
        }

        return [
            'period'           => $period,
            'total_duration'   => $totalDuration,
            'session_count'    => $sessionCount,
            'avg_duration'     => $sessionCount > 0 ? round($totalDuration / $sessionCount) : 0,
            'subjects'         => $subjectDurations,
            'types'            => $typeDurations,
            'daily'            => $dailyDurations,
            'streak'           => $streak,
            'days_active'      => count($dailyDurations),
        ];
    }

    // -------------------------------------------------------------------------
    // Writing Activity Logging
    // -------------------------------------------------------------------------

    /**
     * Log a writing-related action (create, edit, submit, review).
     *
     * @param string $userId  User identifier
     * @param string $essayId Essay or writing piece ID
     * @param string $action  Action: "created", "edited", "submitted", "reviewed"
     * @param array  $extra   Extra data (word count, etc.)
     *
     * @return void
     */
    public function logWriting(string $userId, string $essayId, string $action, array $extra = []): void
    {
        $entry = [
            'essay_id'  => $essayId,
            'action'    => $action,
            'extra'     => $extra,
            'timestamp' => date('c'),
        ];

        $this->appendToUserLog('writing', $userId, $entry);

        $this->info("Writing activity: {$action}", [
            'user_id'  => $userId,
            'essay_id' => $essayId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Log Cleanup
    // -------------------------------------------------------------------------

    /**
     * Delete general log files older than the specified number of days.
     *
     * @param int $daysOld Delete files older than this many days
     *
     * @return int Number of files deleted
     */
    public function cleanup(int $daysOld = 30): int
    {
        $cutoff  = time() - ($daysOld * 86400);
        $deleted = 0;

        $files = glob($this->logDir . '/app-*.log');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Log cleanup: removed {$deleted} files older than {$daysOld} days");

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Internal: Per-User JSON Logs
    // -------------------------------------------------------------------------

    /**
     * Append an entry to a per-user JSON log file.
     *
     * @param string $category "activity", "study", or "writing"
     * @param string $userId
     * @param array  $entry
     */
    private function appendToUserLog(string $category, string $userId, array $entry): void
    {
        $file = $this->userLogPath($category, $userId);
        $dir  = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entries = $this->readUserLog($category, $userId);
        $entries[] = $entry;

        // Trim to max entries (FIFO)
        if (count($entries) > $this->maxEntries) {
            $entries = array_slice($entries, -$this->maxEntries);
        }

        $json = json_encode(
            $entries,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        file_put_contents($file, $json, LOCK_EX);
    }

    /**
     * Read all entries from a per-user JSON log.
     *
     * @param string $category
     * @param string $userId
     *
     * @return array
     */
    private function readUserLog(string $category, string $userId): array
    {
        $file = $this->userLogPath($category, $userId);

        if (!file_exists($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            return [];
        }

        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Build the path to a per-user JSON log file.
     *
     * @param string $category
     * @param string $userId
     *
     * @return string
     */
    private function userLogPath(string $category, string $userId): string
    {
        $safeCategory = preg_replace('/[^a-zA-Z0-9_-]/', '', $category);
        $safeUser     = preg_replace('/[^a-zA-Z0-9._-]/', '', $userId);

        return $this->logDir . '/' . $safeCategory . '/' . $safeUser . '.json';
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Interpolate context values into a message string.
     *
     * Replaces {key} placeholders with context values.
     *
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $replacements['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replacements);
    }

    /**
     * Calculate the Unix timestamp cutoff for a named period.
     *
     * @param string $period "day", "week", "month", "year", or "all"
     *
     * @return int
     */
    private function periodCutoff(string $period): int
    {
        return match ($period) {
            'day'   => strtotime('today'),
            'week'  => strtotime('-7 days'),
            'month' => strtotime('-30 days'),
            'year'  => strtotime('-365 days'),
            'all'   => 0,
            default => strtotime('-7 days'),
        };
    }
}
