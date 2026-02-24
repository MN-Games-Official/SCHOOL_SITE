<?php
/**
 * ============================================================================
 * ProgressService - Progress Tracking & Analytics
 * StudyFlow - Student Self-Teaching App
 *
 * Comprehensive progress tracking with weekly/monthly reports, study-time
 * charts, quiz-score trends, flashcard mastery, milestone tracking,
 * strengths & weaknesses analysis, and exportable reports.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class ProgressService
{
    private FileStorage $storage;

    private const COLLECTION_MILESTONES = 'milestones';
    private const COLLECTION_PROGRESS   = 'progress_events';

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Overview
    // -------------------------------------------------------------------------

    /**
     * Get a comprehensive progress overview for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getOverview(string $userId): array
    {
        $sessions  = $this->storage->query('study_sessions', ['user_id' => $userId]);
        $quizzes   = $this->storage->query('quizzes', function ($q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });
        $writings  = $this->storage->query('writings', ['user_id' => $userId]);
        $decks     = $this->storage->query('flashcard_decks', ['user_id' => $userId]);
        $notes     = $this->storage->query('notes', ['user_id' => $userId]);
        $user      = $this->storage->read('users', $userId);

        // Study time
        $totalMinutes = 0;
        $completedSessions = 0;
        foreach ($sessions as $s) {
            if (($s['status'] ?? '') === 'completed') {
                $totalMinutes += ($s['duration_minutes'] ?? 0);
                $completedSessions++;
            }
        }

        // Quiz stats
        $quizScores  = array_filter(array_column($quizzes, 'percentage'), fn($s) => $s !== null);
        $avgQuizScore = !empty($quizScores) ? round(array_sum($quizScores) / count($quizScores), 1) : 0;

        // Writing stats
        $totalWords = array_sum(array_column($writings, 'word_count'));

        // Flashcard stats
        $totalCards   = 0;
        $masteredCards = 0;
        foreach ($decks as $d) {
            $totalCards   += count($d['cards'] ?? []);
            $masteredCards += ($d['mastered'] ?? 0);
        }

        $milestones = $this->getMilestones($userId);

        return [
            'user_id'            => $userId,
            'level'              => $user['level'] ?? 1,
            'xp'                 => $user['xp'] ?? 0,
            'streak'             => $user['streak'] ?? 0,
            'total_study_minutes' => round($totalMinutes, 1),
            'total_study_hours'  => round($totalMinutes / 60, 1),
            'total_sessions'     => $completedSessions,
            'total_quizzes'      => count($quizzes),
            'avg_quiz_score'     => $avgQuizScore,
            'total_writings'     => count($writings),
            'total_words'        => $totalWords,
            'total_decks'        => count($decks),
            'total_cards'        => $totalCards,
            'mastered_cards'     => $masteredCards,
            'total_notes'        => count($notes),
            'milestones_earned'  => count($milestones),
            'generated_at'       => date('c'),
        ];
    }

    // -------------------------------------------------------------------------
    // Per-Subject Progress
    // -------------------------------------------------------------------------

    /**
     * Get detailed progress for a specific subject.
     *
     * @param string $userId
     * @param string $subject
     * @return array
     */
    public function getSubjectProgress(string $userId, string $subject): array
    {
        $sessions = $this->storage->query('study_sessions', function ($s) use ($userId, $subject) {
            return ($s['user_id'] ?? '') === $userId && ($s['subject_id'] ?? '') === $subject;
        });

        $quizzes = $this->storage->query('quizzes', function ($q) use ($userId, $subject) {
            return ($q['user_id'] ?? '') === $userId
                && ($q['subject'] ?? '') === $subject
                && ($q['status'] ?? '') === 'completed';
        });

        $totalMinutes = 0;
        foreach ($sessions as $s) {
            if (($s['status'] ?? '') === 'completed') {
                $totalMinutes += ($s['duration_minutes'] ?? 0);
            }
        }

        $scores   = array_column($quizzes, 'percentage');
        $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;

        // Topic progress
        $progress = $this->storage->read('user_subject_progress', $userId . '_' . $subject);
        $completedTopics = $progress ? count($progress['completed_topics'] ?? []) : 0;
        $totalTopics     = $progress['total_topics'] ?? 0;

        return [
            'user_id'           => $userId,
            'subject'           => $subject,
            'total_study_time'  => round($totalMinutes, 1),
            'session_count'     => count($sessions),
            'quiz_count'        => count($quizzes),
            'avg_quiz_score'    => $avgScore,
            'best_quiz_score'   => !empty($scores) ? max($scores) : 0,
            'completed_topics'  => $completedTopics,
            'total_topics'      => $totalTopics,
            'completion_pct'    => $totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100, 1) : 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    /**
     * Generate a weekly progress report.
     *
     * @param string $userId
     * @return array
     */
    public function getWeeklyReport(string $userId): array
    {
        return $this->generateReport($userId, 'week');
    }

    /**
     * Generate a monthly progress report.
     *
     * @param string $userId
     * @return array
     */
    public function getMonthlyReport(string $userId): array
    {
        return $this->generateReport($userId, 'month');
    }

    // -------------------------------------------------------------------------
    // Chart Data
    // -------------------------------------------------------------------------

    /**
     * Get daily study time for the last N days (for chart rendering).
     *
     * @param string $userId
     * @param int    $days
     * @return array Day => minutes data
     */
    public function getStudyTimeByDay(string $userId, int $days = 30): array
    {
        $days   = max(1, min(365, $days));
        $cutoff = strtotime("-{$days} days");

        $sessions = $this->storage->query('study_sessions', function ($s) use ($userId, $cutoff) {
            return ($s['user_id'] ?? '') === $userId
                && ($s['status'] ?? '') === 'completed'
                && strtotime($s['started_at'] ?? '') >= $cutoff;
        });

        // Initialize all days with zero
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[$date] = 0;
        }

        foreach ($sessions as $s) {
            $date = date('Y-m-d', strtotime($s['started_at'] ?? ''));
            if (isset($data[$date])) {
                $data[$date] += ($s['duration_minutes'] ?? 0);
            }
        }

        $result = [];
        foreach ($data as $date => $minutes) {
            $result[] = ['date' => $date, 'minutes' => round($minutes, 1)];
        }

        return $result;
    }

    /**
     * Get quiz scores over time (for trend chart).
     *
     * @param string $userId
     * @param int    $limit
     * @return array
     */
    public function getQuizScoresTrend(string $userId, int $limit = 20): array
    {
        $quizzes = $this->storage->query('quizzes', function ($q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });

        usort($quizzes, fn($a, $b) => strtotime($a['completed_at'] ?? '0') - strtotime($b['completed_at'] ?? '0'));

        $recent = array_slice($quizzes, -$limit);

        return array_map(function ($q) {
            return [
                'quiz_id'    => $q['id'] ?? $q['_id'] ?? '',
                'subject'    => $q['subject'] ?? '',
                'score'      => $q['percentage'] ?? 0,
                'date'       => date('Y-m-d', strtotime($q['completed_at'] ?? '')),
            ];
        }, $recent);
    }

    // -------------------------------------------------------------------------
    // Feature-Specific Progress
    // -------------------------------------------------------------------------

    /**
     * Get writing improvement metrics over time.
     *
     * @param string $userId
     * @return array
     */
    public function getWritingProgress(string $userId): array
    {
        $writings = $this->storage->query('writings', ['user_id' => $userId]);

        usort($writings, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));

        $totalWords    = 0;
        $totalPieces   = count($writings);
        $wordCountTrend = [];

        foreach ($writings as $w) {
            $totalWords += ($w['word_count'] ?? 0);
            $wordCountTrend[] = [
                'date'       => date('Y-m-d', strtotime($w['created_at'] ?? '')),
                'word_count' => $w['word_count'] ?? 0,
                'type'       => $w['type'] ?? 'essay',
            ];
        }

        $avgWords = $totalPieces > 0 ? (int) round($totalWords / $totalPieces) : 0;

        // Compare first half vs second half
        $mid  = (int) floor($totalPieces / 2);
        $firstHalf  = array_slice($writings, 0, max(1, $mid));
        $secondHalf = array_slice($writings, max(1, $mid));

        $avgFirst  = !empty($firstHalf) ? (int) round(array_sum(array_column($firstHalf, 'word_count')) / count($firstHalf)) : 0;
        $avgSecond = !empty($secondHalf) ? (int) round(array_sum(array_column($secondHalf, 'word_count')) / count($secondHalf)) : 0;

        return [
            'user_id'          => $userId,
            'total_pieces'     => $totalPieces,
            'total_words'      => $totalWords,
            'avg_word_count'   => $avgWords,
            'avg_first_half'   => $avgFirst,
            'avg_second_half'  => $avgSecond,
            'improvement'      => $avgFirst > 0 ? round((($avgSecond - $avgFirst) / $avgFirst) * 100, 1) : 0,
            'trend'            => $wordCountTrend,
        ];
    }

    /**
     * Get flashcard mastery progress.
     *
     * @param string $userId
     * @return array
     */
    public function getFlashcardProgress(string $userId): array
    {
        $decks = $this->storage->query('flashcard_decks', ['user_id' => $userId]);

        $totalCards   = 0;
        $mastered     = 0;
        $learning     = 0;
        $newCards     = 0;
        $deckProgress = [];

        foreach ($decks as $d) {
            $cards      = $d['cards'] ?? [];
            $deckTotal  = count($cards);
            $totalCards += $deckTotal;

            $dm = $d['mastered'] ?? 0;
            $dl = $d['learning'] ?? 0;
            $dn = $d['new'] ?? ($deckTotal - $dm - $dl);

            $mastered += $dm;
            $learning += $dl;
            $newCards += $dn;

            $deckProgress[] = [
                'deck_id'    => $d['id'] ?? $d['_id'] ?? '',
                'name'       => $d['name'] ?? '',
                'total'      => $deckTotal,
                'mastered'   => $dm,
                'learning'   => $dl,
                'new'        => $dn,
                'mastery_pct' => $deckTotal > 0 ? round(($dm / $deckTotal) * 100, 1) : 0,
            ];
        }

        return [
            'user_id'      => $userId,
            'total_decks'  => count($decks),
            'total_cards'  => $totalCards,
            'mastered'     => $mastered,
            'learning'     => $learning,
            'new'          => $newCards,
            'overall_mastery' => $totalCards > 0 ? round(($mastered / $totalCards) * 100, 1) : 0,
            'deck_progress' => $deckProgress,
        ];
    }

    // -------------------------------------------------------------------------
    // Goals & Milestones
    // -------------------------------------------------------------------------

    /**
     * Get goal completion rates.
     *
     * @param string $userId
     * @return array
     */
    public function getGoalCompletion(string $userId): array
    {
        $goals = $this->storage->query('study_goals', ['user_id' => $userId]);
        $completed = 0;
        $total     = 0;

        foreach ($goals as $g) {
            if (!($g['active'] ?? true)) continue;
            $total++;

            $type   = $g['type'] ?? 'daily';
            $target = $g['target_minutes'] ?? 30;
            $period = $type === 'daily' ? 'today' : 'week';

            $sessions = $this->storage->query('study_sessions', function ($s) use ($userId, $period) {
                if (($s['user_id'] ?? '') !== $userId || ($s['status'] ?? '') !== 'completed') {
                    return false;
                }
                $cutoff = $period === 'today' ? strtotime('today') : strtotime('monday this week');
                return strtotime($s['started_at'] ?? '') >= $cutoff;
            });

            $actual = array_sum(array_column($sessions, 'duration_minutes'));
            if ($actual >= $target) {
                $completed++;
            }
        }

        return [
            'user_id'         => $userId,
            'total_goals'     => $total,
            'completed_goals' => $completed,
            'completion_rate'  => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get all milestones earned by a user.
     *
     * @param string $userId
     * @return array
     */
    public function getMilestones(string $userId): array
    {
        $milestones = $this->storage->query(self::COLLECTION_MILESTONES, ['user_id' => $userId]);

        usort($milestones, fn($a, $b) => strtotime($b['earned_at'] ?? '0') - strtotime($a['earned_at'] ?? '0'));

        return $milestones;
    }

    /**
     * Check and award any new milestones.
     *
     * @param string $userId
     * @return array Newly earned milestones
     */
    public function checkMilestone(string $userId): array
    {
        $overview = $this->getOverview($userId);
        $existing = $this->getMilestones($userId);
        $earnedIds = array_column($existing, 'milestone_id');

        $definitions = $this->getMilestoneDefinitions();
        $newMilestones = [];

        foreach ($definitions as $def) {
            if (in_array($def['id'], $earnedIds, true)) {
                continue;
            }

            if ($this->meetsMilestone($def, $overview)) {
                $id = $this->storage->generateId();
                $milestone = [
                    'id'           => $id,
                    'user_id'      => $userId,
                    'milestone_id' => $def['id'],
                    'name'         => $def['name'],
                    'description'  => $def['description'],
                    'icon'         => $def['icon'],
                    'earned_at'    => date('c'),
                ];

                $this->storage->write(self::COLLECTION_MILESTONES, $id, $milestone);
                $newMilestones[] = $milestone;
            }
        }

        return $newMilestones;
    }

    // -------------------------------------------------------------------------
    // Calendar & Analysis
    // -------------------------------------------------------------------------

    /**
     * Get activity calendar heatmap data for a month.
     *
     * @param string $userId
     * @param string $month Format: 'YYYY-MM'
     * @return array Day-by-day activity data
     */
    public function getActivityCalendar(string $userId, string $month = ''): array
    {
        if ($month === '') {
            $month = date('Y-m');
        }

        $startDate = $month . '-01';
        $endDate   = date('Y-m-t', strtotime($startDate));
        $startTs   = strtotime($startDate);
        $endTs     = strtotime($endDate . ' 23:59:59');

        // Initialize all days
        $calendar = [];
        $current  = $startTs;
        while ($current <= $endTs) {
            $day = date('Y-m-d', $current);
            $calendar[$day] = [
                'date'     => $day,
                'minutes'  => 0,
                'sessions' => 0,
                'quizzes'  => 0,
                'writings' => 0,
                'notes'    => 0,
                'level'    => 0,
            ];
            $current = strtotime('+1 day', $current);
        }

        // Study sessions
        $sessions = $this->storage->query('study_sessions', function ($s) use ($userId, $startTs, $endTs) {
            $ts = strtotime($s['started_at'] ?? '');
            return ($s['user_id'] ?? '') === $userId && $ts >= $startTs && $ts <= $endTs;
        });

        foreach ($sessions as $s) {
            $day = date('Y-m-d', strtotime($s['started_at'] ?? ''));
            if (isset($calendar[$day])) {
                $calendar[$day]['minutes']  += ($s['duration_minutes'] ?? 0);
                $calendar[$day]['sessions'] += 1;
            }
        }

        // Quizzes
        $quizzes = $this->storage->query('quizzes', function ($q) use ($userId, $startTs, $endTs) {
            $ts = strtotime($q['completed_at'] ?? $q['created_at'] ?? '');
            return ($q['user_id'] ?? '') === $userId && $ts >= $startTs && $ts <= $endTs;
        });

        foreach ($quizzes as $q) {
            $day = date('Y-m-d', strtotime($q['completed_at'] ?? $q['created_at'] ?? ''));
            if (isset($calendar[$day])) {
                $calendar[$day]['quizzes'] += 1;
            }
        }

        // Activity levels (0-4)
        foreach ($calendar as &$day) {
            $day['minutes'] = round($day['minutes'], 1);
            $day['level']   = $this->activityLevel($day['minutes']);
        }
        unset($day);

        return array_values($calendar);
    }

    /**
     * Identify user's strengths and weaknesses across subjects.
     *
     * @param string $userId
     * @return array
     */
    public function getStrengthsAndWeaknesses(string $userId): array
    {
        $quizzes = $this->storage->query('quizzes', function ($q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });

        $subjectScores = [];
        foreach ($quizzes as $q) {
            $subject = $q['subject'] ?? 'unknown';
            if (!isset($subjectScores[$subject])) {
                $subjectScores[$subject] = ['scores' => [], 'count' => 0];
            }
            $subjectScores[$subject]['scores'][] = $q['percentage'] ?? 0;
            $subjectScores[$subject]['count']++;
        }

        $strengths  = [];
        $weaknesses = [];

        foreach ($subjectScores as $subject => $data) {
            if ($data['count'] < 2) continue;

            $avg = round(array_sum($data['scores']) / count($data['scores']), 1);

            $entry = [
                'subject'    => $subject,
                'avg_score'  => $avg,
                'quiz_count' => $data['count'],
                'best'       => max($data['scores']),
                'worst'      => min($data['scores']),
            ];

            if ($avg >= 70) {
                $strengths[] = $entry;
            } else {
                $weaknesses[] = $entry;
            }
        }

        usort($strengths, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);
        usort($weaknesses, fn($a, $b) => $a['avg_score'] <=> $b['avg_score']);

        return [
            'user_id'    => $userId,
            'strengths'  => $strengths,
            'weaknesses' => $weaknesses,
        ];
    }

    // -------------------------------------------------------------------------
    // Export & Event Recording
    // -------------------------------------------------------------------------

    /**
     * Export a comprehensive progress report.
     *
     * @param string $userId
     * @return array Full report
     */
    public function exportProgressReport(string $userId): array
    {
        return [
            'overview'               => $this->getOverview($userId),
            'weekly_report'          => $this->getWeeklyReport($userId),
            'study_time_chart'       => $this->getStudyTimeByDay($userId, 30),
            'quiz_trend'             => $this->getQuizScoresTrend($userId),
            'writing_progress'       => $this->getWritingProgress($userId),
            'flashcard_progress'     => $this->getFlashcardProgress($userId),
            'strengths_weaknesses'   => $this->getStrengthsAndWeaknesses($userId),
            'milestones'             => $this->getMilestones($userId),
            'goal_completion'        => $this->getGoalCompletion($userId),
            'exported_at'            => date('c'),
        ];
    }

    /**
     * Record a generic progress event.
     *
     * @param string $userId
     * @param string $type   Event type (session, quiz, writing, flashcard, note, etc.)
     * @param array  $data   Event-specific data
     * @return array The recorded event
     */
    public function recordProgress(string $userId, string $type, array $data = []): array
    {
        $eventId = $this->storage->generateId();

        $event = array_merge($data, [
            'id'         => $eventId,
            'user_id'    => $userId,
            'type'       => $type,
            'created_at' => date('c'),
        ]);

        $this->storage->write(self::COLLECTION_PROGRESS, $eventId, $event);

        return $event;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a report for a given period.
     */
    private function generateReport(string $userId, string $period): array
    {
        $cutoff = $period === 'week'
            ? strtotime('monday this week')
            : strtotime('first day of this month');

        $sessions = $this->storage->query('study_sessions', function ($s) use ($userId, $cutoff) {
            return ($s['user_id'] ?? '') === $userId
                && ($s['status'] ?? '') === 'completed'
                && strtotime($s['started_at'] ?? '') >= $cutoff;
        });

        $quizzes = $this->storage->query('quizzes', function ($q) use ($userId, $cutoff) {
            return ($q['user_id'] ?? '') === $userId
                && ($q['status'] ?? '') === 'completed'
                && strtotime($q['completed_at'] ?? '') >= $cutoff;
        });

        $writings = $this->storage->query('writings', function ($w) use ($userId, $cutoff) {
            return ($w['user_id'] ?? '') === $userId
                && strtotime($w['created_at'] ?? '') >= $cutoff;
        });

        $totalMinutes = array_sum(array_column($sessions, 'duration_minutes'));
        $quizScores   = array_filter(array_column($quizzes, 'percentage'), fn($s) => $s !== null);
        $totalWords   = array_sum(array_column($writings, 'word_count'));

        $dailyBreakdown = [];
        foreach ($sessions as $s) {
            $day = date('Y-m-d', strtotime($s['started_at'] ?? ''));
            $dailyBreakdown[$day] = ($dailyBreakdown[$day] ?? 0) + ($s['duration_minutes'] ?? 0);
        }

        $daysStudied = count($dailyBreakdown);
        $totalDays   = $period === 'week' ? 7 : (int) date('t');

        return [
            'period'           => $period,
            'from'             => date('Y-m-d', $cutoff),
            'to'               => date('Y-m-d'),
            'total_minutes'    => round($totalMinutes, 1),
            'total_hours'      => round($totalMinutes / 60, 1),
            'sessions'         => count($sessions),
            'days_studied'     => $daysStudied,
            'total_days'       => $totalDays,
            'consistency_pct'  => $totalDays > 0 ? round(($daysStudied / $totalDays) * 100, 1) : 0,
            'quizzes_taken'    => count($quizzes),
            'avg_quiz_score'   => !empty($quizScores) ? round(array_sum($quizScores) / count($quizScores), 1) : 0,
            'writings_created' => count($writings),
            'words_written'    => $totalWords,
            'daily_breakdown'  => $dailyBreakdown,
            'generated_at'     => date('c'),
        ];
    }

    /**
     * Get milestone definitions.
     */
    private function getMilestoneDefinitions(): array
    {
        return [
            ['id' => 'ms_first_hour',   'name' => 'First Hour',        'description' => 'Study for a total of 1 hour.',       'icon' => '⏰', 'field' => 'total_study_minutes', 'threshold' => 60],
            ['id' => 'ms_ten_hours',    'name' => 'Dedicated Student', 'description' => 'Study for a total of 10 hours.',     'icon' => '📖', 'field' => 'total_study_minutes', 'threshold' => 600],
            ['id' => 'ms_fifty_hours',  'name' => 'Knowledge Seeker',  'description' => 'Study for a total of 50 hours.',     'icon' => '🎯', 'field' => 'total_study_minutes', 'threshold' => 3000],
            ['id' => 'ms_ten_quizzes',  'name' => 'Quiz Enthusiast',   'description' => 'Complete 10 quizzes.',               'icon' => '❓', 'field' => 'total_quizzes',       'threshold' => 10],
            ['id' => 'ms_fifty_quizzes','name' => 'Quiz Champion',     'description' => 'Complete 50 quizzes.',               'icon' => '🏆', 'field' => 'total_quizzes',       'threshold' => 50],
            ['id' => 'ms_five_writings','name' => 'Writer\'s Journey', 'description' => 'Write 5 pieces.',                    'icon' => '✍️', 'field' => 'total_writings',      'threshold' => 5],
            ['id' => 'ms_ten_thousand', 'name' => '10K Words',         'description' => 'Write 10,000 words total.',           'icon' => '📝', 'field' => 'total_words',         'threshold' => 10000],
            ['id' => 'ms_card_master',  'name' => 'Card Master',       'description' => 'Master 100 flashcards.',             'icon' => '🃏', 'field' => 'mastered_cards',      'threshold' => 100],
            ['id' => 'ms_level_five',   'name' => 'Level 5',           'description' => 'Reach level 5.',                     'icon' => '⭐', 'field' => 'level',              'threshold' => 5],
            ['id' => 'ms_level_ten',    'name' => 'Level 10',          'description' => 'Reach level 10.',                    'icon' => '🌟', 'field' => 'level',              'threshold' => 10],
        ];
    }

    /**
     * Check if a milestone's criteria are met.
     */
    private function meetsMilestone(array $def, array $overview): bool
    {
        $field     = $def['field'] ?? '';
        $threshold = $def['threshold'] ?? PHP_INT_MAX;
        $value     = $overview[$field] ?? 0;

        return $value >= $threshold;
    }

    /**
     * Map minutes to activity level (0-4) for heatmap.
     */
    private function activityLevel(float $minutes): int
    {
        if ($minutes <= 0)  return 0;
        if ($minutes < 15)  return 1;
        if ($minutes < 30)  return 2;
        if ($minutes < 60)  return 3;
        return 4;
    }
}
