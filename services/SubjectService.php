<?php
/**
 * ============================================================================
 * SubjectService - Subject & Topic Management
 * StudyFlow - Student Self-Teaching App
 *
 * Manages the catalogue of subjects and topics, tracks per-user progress,
 * provides topic recommendations, and delivers study materials.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class SubjectService
{
    private FileStorage $storage;

    private const COLLECTION_SUBJECTS = 'subjects';
    private const COLLECTION_PROGRESS = 'user_subject_progress';

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Subject CRUD
    // -------------------------------------------------------------------------

    /**
     * Get all available subjects.
     *
     * @return array List of subjects with basic info
     */
    public function getAllSubjects(): array
    {
        $subjects = $this->storage->read(self::COLLECTION_SUBJECTS);

        if (empty($subjects)) {
            return [];
        }

        // Sort alphabetically by name
        usort($subjects, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return array_map(function (array $s) {
            return [
                'id'          => $s['id'] ?? $s['_id'] ?? '',
                'name'        => $s['name'] ?? '',
                'description' => $s['description'] ?? '',
                'icon'        => $s['icon'] ?? '📘',
                'color'       => $s['color'] ?? '#4A90D9',
                'topic_count' => count($s['topics'] ?? []),
                'difficulty'  => $s['difficulty'] ?? 'mixed',
                'category'    => $s['category'] ?? 'general',
            ];
        }, $subjects);
    }

    /**
     * Get a subject with all its topics.
     *
     * @param string $id Subject ID
     * @return array Subject details
     * @throws RuntimeException If not found
     */
    public function getSubject(string $id): array
    {
        $subject = $this->storage->read(self::COLLECTION_SUBJECTS, $id);

        if ($subject === null) {
            throw new RuntimeException('SubjectService: Subject not found.');
        }

        return [
            'id'          => $subject['id'] ?? $id,
            'name'        => $subject['name'] ?? '',
            'description' => $subject['description'] ?? '',
            'icon'        => $subject['icon'] ?? '📘',
            'color'       => $subject['color'] ?? '#4A90D9',
            'category'    => $subject['category'] ?? 'general',
            'difficulty'  => $subject['difficulty'] ?? 'mixed',
            'topics'      => $subject['topics'] ?? [],
            'prerequisites' => $subject['prerequisites'] ?? [],
            'created_at'  => $subject['created_at'] ?? null,
        ];
    }

    /**
     * Get a specific topic within a subject.
     *
     * @param string $subjectId
     * @param string $topicId
     * @return array Topic details
     * @throws RuntimeException If subject or topic not found
     */
    public function getTopic(string $subjectId, string $topicId): array
    {
        $subject = $this->getSubject($subjectId);
        $topics  = $subject['topics'] ?? [];

        foreach ($topics as $topic) {
            if (($topic['id'] ?? '') === $topicId) {
                $topic['subject_id']   = $subjectId;
                $topic['subject_name'] = $subject['name'];
                return $topic;
            }
        }

        throw new RuntimeException('SubjectService: Topic not found.');
    }

    /**
     * Get detailed content for a specific topic.
     *
     * @param string $subjectId
     * @param string $topicId
     * @return array Content including study materials, key concepts, examples
     * @throws RuntimeException If not found
     */
    public function getTopicContent(string $subjectId, string $topicId): array
    {
        $topic = $this->getTopic($subjectId, $topicId);

        return [
            'id'             => $topic['id'] ?? $topicId,
            'title'          => $topic['title'] ?? $topic['name'] ?? '',
            'subject_id'     => $subjectId,
            'description'    => $topic['description'] ?? '',
            'content'        => $topic['content'] ?? '',
            'key_concepts'   => $topic['key_concepts'] ?? [],
            'examples'       => $topic['examples'] ?? [],
            'resources'      => $topic['resources'] ?? [],
            'difficulty'     => $topic['difficulty'] ?? 'intermediate',
            'estimated_time' => $topic['estimated_time'] ?? 30,
            'objectives'     => $topic['objectives'] ?? [],
        ];
    }

    // -------------------------------------------------------------------------
    // Progress Tracking
    // -------------------------------------------------------------------------

    /**
     * Get a user's progress in a specific subject.
     *
     * @param string $userId
     * @param string $subjectId
     * @return array Progress data
     */
    public function getUserSubjectProgress(string $userId, string $subjectId): array
    {
        $progressId = $this->progressKey($userId, $subjectId);
        $progress   = $this->storage->read(self::COLLECTION_PROGRESS, $progressId);

        if ($progress === null) {
            $subject    = $this->getSubject($subjectId);
            $totalTopics = count($subject['topics'] ?? []);

            return [
                'user_id'          => $userId,
                'subject_id'       => $subjectId,
                'subject_name'     => $subject['name'] ?? '',
                'completed_topics' => [],
                'total_topics'     => $totalTopics,
                'completion_pct'   => 0,
                'total_time'       => 0,
                'last_studied'     => null,
                'started_at'       => null,
            ];
        }

        // Recalculate completion percentage
        $subject     = $this->getSubject($subjectId);
        $totalTopics = count($subject['topics'] ?? []);
        $completed   = count($progress['completed_topics'] ?? []);

        $progress['total_topics']   = $totalTopics;
        $progress['completion_pct'] = $totalTopics > 0
            ? round(($completed / $totalTopics) * 100, 1)
            : 0;

        return $progress;
    }

    /**
     * Mark a topic as completed for a user.
     *
     * @param string $userId
     * @param string $subjectId
     * @param string $topicId
     * @return array Updated progress
     */
    public function markTopicComplete(string $userId, string $subjectId, string $topicId): array
    {
        // Verify topic exists
        $this->getTopic($subjectId, $topicId);

        $progressId = $this->progressKey($userId, $subjectId);
        $progress   = $this->storage->read(self::COLLECTION_PROGRESS, $progressId);
        $now        = date('c');

        if ($progress === null) {
            $subject  = $this->getSubject($subjectId);
            $progress = [
                'id'               => $progressId,
                'user_id'          => $userId,
                'subject_id'       => $subjectId,
                'subject_name'     => $subject['name'] ?? '',
                'completed_topics' => [],
                'total_time'       => 0,
                'last_studied'     => $now,
                'started_at'       => $now,
            ];
        }

        // Add topic if not already completed
        $completedTopics = $progress['completed_topics'] ?? [];
        $topicIds        = array_column($completedTopics, 'topic_id');

        if (!in_array($topicId, $topicIds, true)) {
            $completedTopics[] = [
                'topic_id'     => $topicId,
                'completed_at' => $now,
            ];
        }

        $progress['completed_topics'] = $completedTopics;
        $progress['last_studied']     = $now;

        $this->storage->write(self::COLLECTION_PROGRESS, $progressId, $progress);

        return $this->getUserSubjectProgress($userId, $subjectId);
    }

    // -------------------------------------------------------------------------
    // Recommendations
    // -------------------------------------------------------------------------

    /**
     * Get recommended topics for a user based on their progress and history.
     *
     * Recommends:
     *  1. Next uncompleted topic in subjects they've started
     *  2. Topics from subjects they haven't explored yet
     *  3. Topics matching their recent study patterns
     *
     * @param string $userId
     * @return array List of recommended topics
     */
    public function getRecommendedTopics(string $userId): array
    {
        $allSubjects   = $this->storage->read(self::COLLECTION_SUBJECTS) ?? [];
        $recommendations = [];

        foreach ($allSubjects as $subject) {
            $subjectId   = $subject['id'] ?? $subject['_id'] ?? '';
            $topics      = $subject['topics'] ?? [];
            $progressId  = $this->progressKey($userId, $subjectId);
            $progress    = $this->storage->read(self::COLLECTION_PROGRESS, $progressId);

            $completedIds = [];
            if ($progress !== null) {
                $completedIds = array_column($progress['completed_topics'] ?? [], 'topic_id');
            }

            // Find first uncompleted topic in each subject
            foreach ($topics as $topic) {
                $tid = $topic['id'] ?? '';
                if ($tid !== '' && !in_array($tid, $completedIds, true)) {
                    $recommendations[] = [
                        'subject_id'   => $subjectId,
                        'subject_name' => $subject['name'] ?? '',
                        'topic_id'     => $tid,
                        'topic_name'   => $topic['title'] ?? $topic['name'] ?? '',
                        'difficulty'   => $topic['difficulty'] ?? 'intermediate',
                        'estimated_time' => $topic['estimated_time'] ?? 30,
                        'reason'       => !empty($completedIds) ? 'Continue your progress' : 'Start learning',
                        'priority'     => !empty($completedIds) ? 1 : 2,
                    ];
                    break; // Only first uncompleted topic per subject
                }
            }
        }

        // Sort by priority (in-progress subjects first)
        usort($recommendations, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return array_slice($recommendations, 0, 10);
    }

    // -------------------------------------------------------------------------
    // Search & Discovery
    // -------------------------------------------------------------------------

    /**
     * Search subjects and topics by keyword.
     *
     * @param string $query Search query
     * @return array Matching subjects and topics
     */
    public function searchSubjects(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $results = $this->storage->search(
            self::COLLECTION_SUBJECTS,
            $query,
            ['name', 'description', 'category']
        );

        // Also search within topics
        $allSubjects = $this->storage->read(self::COLLECTION_SUBJECTS) ?? [];
        $needle      = mb_strtolower($query, 'UTF-8');
        $topicResults = [];

        foreach ($allSubjects as $subject) {
            foreach ($subject['topics'] ?? [] as $topic) {
                $title = mb_strtolower($topic['title'] ?? $topic['name'] ?? '', 'UTF-8');
                $desc  = mb_strtolower($topic['description'] ?? '', 'UTF-8');

                if (str_contains($title, $needle) || str_contains($desc, $needle)) {
                    $topicResults[] = [
                        'type'         => 'topic',
                        'subject_id'   => $subject['id'] ?? $subject['_id'] ?? '',
                        'subject_name' => $subject['name'] ?? '',
                        'topic_id'     => $topic['id'] ?? '',
                        'topic_name'   => $topic['title'] ?? $topic['name'] ?? '',
                        'description'  => $topic['description'] ?? '',
                    ];
                }
            }
        }

        return [
            'subjects' => array_map(fn($s) => [
                'type'        => 'subject',
                'id'          => $s['id'] ?? $s['_id'] ?? '',
                'name'        => $s['name'] ?? '',
                'description' => $s['description'] ?? '',
            ], $results),
            'topics'   => $topicResults,
            'total'    => count($results) + count($topicResults),
        ];
    }

    /**
     * Get the most studied topics across all sessions.
     *
     * @return array Popular topics sorted by study count
     */
    public function getPopularTopics(): array
    {
        $sessions = $this->storage->read('study_sessions') ?? [];
        $counts   = [];

        foreach ($sessions as $session) {
            $key = ($session['subject_id'] ?? '') . '|' . ($session['topic_id'] ?? '');
            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'subject_id' => $session['subject_id'] ?? '',
                    'topic_id'   => $session['topic_id'] ?? '',
                    'count'      => 0,
                    'total_time' => 0,
                ];
            }
            $counts[$key]['count']++;
            $counts[$key]['total_time'] += ($session['duration_minutes'] ?? 0);
        }

        usort($counts, fn($a, $b) => $b['count'] <=> $a['count']);

        // Enrich with names
        $popular = [];
        foreach (array_slice($counts, 0, 10) as $item) {
            try {
                $subject = $this->getSubject($item['subject_id']);
                $topicName = '';
                foreach ($subject['topics'] ?? [] as $t) {
                    if (($t['id'] ?? '') === $item['topic_id']) {
                        $topicName = $t['title'] ?? $t['name'] ?? '';
                        break;
                    }
                }
                $popular[] = [
                    'subject_id'   => $item['subject_id'],
                    'subject_name' => $subject['name'] ?? '',
                    'topic_id'     => $item['topic_id'],
                    'topic_name'   => $topicName,
                    'study_count'  => $item['count'],
                    'total_time'   => $item['total_time'],
                ];
            } catch (RuntimeException) {
                continue;
            }
        }

        return $popular;
    }

    // -------------------------------------------------------------------------
    // Per-Subject Statistics
    // -------------------------------------------------------------------------

    /**
     * Get per-subject statistics for a user.
     *
     * @param string $userId
     * @return array Statistics per subject
     */
    public function getSubjectStats(string $userId): array
    {
        $allSubjects = $this->storage->read(self::COLLECTION_SUBJECTS) ?? [];
        $sessions    = $this->storage->query('study_sessions', ['user_id' => $userId]);
        $quizzes     = $this->storage->query('quizzes', ['user_id' => $userId]);

        $stats = [];

        foreach ($allSubjects as $subject) {
            $subjectId = $subject['id'] ?? $subject['_id'] ?? '';

            // Filter sessions for this subject
            $subjectSessions = array_filter($sessions, fn($s) => ($s['subject_id'] ?? '') === $subjectId);
            $totalTime = array_sum(array_column($subjectSessions, 'duration_minutes'));

            // Filter quizzes for this subject
            $subjectQuizzes = array_filter($quizzes, fn($q) => ($q['subject_id'] ?? '') === $subjectId);
            $quizScores     = array_filter(array_column($subjectQuizzes, 'score'), fn($s) => $s !== null);
            $avgQuizScore   = !empty($quizScores) ? round(array_sum($quizScores) / count($quizScores), 1) : 0;

            // Get progress
            $progress = $this->getUserSubjectProgress($userId, $subjectId);

            $stats[] = [
                'subject_id'     => $subjectId,
                'subject_name'   => $subject['name'] ?? '',
                'total_time'     => $totalTime,
                'session_count'  => count($subjectSessions),
                'quiz_count'     => count($subjectQuizzes),
                'avg_quiz_score' => $avgQuizScore,
                'completion_pct' => $progress['completion_pct'],
                'topics_done'    => count($progress['completed_topics'] ?? []),
                'total_topics'   => $progress['total_topics'],
                'last_studied'   => $progress['last_studied'],
            ];
        }

        // Sort by most recently studied
        usort($stats, function ($a, $b) {
            $aTime = $a['last_studied'] ? strtotime($a['last_studied']) : 0;
            $bTime = $b['last_studied'] ? strtotime($b['last_studied']) : 0;
            return $bTime <=> $aTime;
        });

        return $stats;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a deterministic progress key for user + subject.
     */
    private function progressKey(string $userId, string $subjectId): string
    {
        return $userId . '_' . $subjectId;
    }
}
