<?php
/**
 * ============================================================================
 * QuizService - Self-Assessment Quiz System
 * StudyFlow - Student Self-Teaching App
 *
 * Generates and manages self-assessment quizzes, grades answers, tracks
 * scores over time, identifies weak areas, and supports custom quizzes
 * with question banks and AI-generated questions.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class QuizService
{
    private FileStorage $storage;

    private const COLLECTION_QUIZZES   = 'quizzes';
    private const COLLECTION_QUESTIONS = 'question_bank';

    /** @var int XP per correctly answered question */
    private const XP_PER_CORRECT = 10;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Quiz Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a quiz from the question bank.
     *
     * @param string $userId
     * @param array  $params Keys: subject, topic, difficulty (easy|medium|hard|mixed),
     *                       count (number of questions, default 10)
     * @return array The generated quiz
     * @throws InvalidArgumentException On missing/invalid params
     */
    public function generateQuiz(string $userId, array $params): array
    {
        $subject    = $params['subject'] ?? '';
        $topic      = $params['topic'] ?? '';
        $difficulty = $params['difficulty'] ?? 'mixed';
        $count      = max(1, min(50, (int) ($params['count'] ?? 10)));

        if ($subject === '') {
            throw new InvalidArgumentException('QuizService: Subject is required to generate a quiz.');
        }

        // Fetch from question bank
        $questions = $this->storage->query(self::COLLECTION_QUESTIONS, function (array $q) use ($subject, $topic, $difficulty) {
            if (($q['subject'] ?? '') !== $subject) {
                return false;
            }
            if ($topic !== '' && ($q['topic'] ?? '') !== $topic) {
                return false;
            }
            if ($difficulty !== 'mixed' && ($q['difficulty'] ?? '') !== $difficulty) {
                return false;
            }
            return true;
        });

        // Shuffle and pick
        shuffle($questions);
        $selected = array_slice($questions, 0, $count);

        // If not enough questions, pad with what we have
        if (empty($selected)) {
            return $this->createEmptyQuiz($userId, $subject, $topic, $difficulty, $count);
        }

        $quizId = $this->storage->generateId();
        $now    = date('c');

        // Prepare questions (strip answers for student view)
        $quizQuestions = [];
        foreach ($selected as $i => $q) {
            $quizQuestions[] = [
                'index'      => $i,
                'id'         => $q['id'] ?? $q['_id'] ?? $this->storage->generateId(),
                'question'   => $q['question'] ?? '',
                'type'       => $q['type'] ?? 'multiple_choice',
                'options'    => $q['options'] ?? [],
                'difficulty' => $q['difficulty'] ?? 'medium',
                'topic'      => $q['topic'] ?? '',
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation'    => $q['explanation'] ?? '',
            ];
        }

        $quiz = [
            'id'          => $quizId,
            'user_id'     => $userId,
            'subject'     => $subject,
            'topic'       => $topic,
            'difficulty'  => $difficulty,
            'questions'   => $quizQuestions,
            'answers'     => [],
            'score'       => null,
            'total'       => count($quizQuestions),
            'correct'     => null,
            'percentage'  => null,
            'status'      => 'pending',
            'xp_earned'   => 0,
            'time_limit'  => null,
            'started_at'  => $now,
            'completed_at' => null,
            'created_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_QUIZZES, $quizId, $quiz);

        return $quiz;
    }

    /**
     * Get a quiz by ID.
     *
     * @param string $quizId
     * @return array
     * @throws RuntimeException If not found
     */
    public function getQuiz(string $quizId): array
    {
        $quiz = $this->storage->read(self::COLLECTION_QUIZZES, $quizId);
        if ($quiz === null) {
            throw new RuntimeException('QuizService: Quiz not found.');
        }
        return $quiz;
    }

    /**
     * Get all quizzes for a user with optional filters.
     *
     * @param string $userId
     * @param array  $filters Keys: subject, status, limit, offset
     * @return array
     */
    public function getUserQuizzes(string $userId, array $filters = []): array
    {
        $quizzes = $this->storage->query(self::COLLECTION_QUIZZES, function (array $q) use ($userId, $filters) {
            if (($q['user_id'] ?? '') !== $userId) {
                return false;
            }
            if (!empty($filters['subject']) && ($q['subject'] ?? '') !== $filters['subject']) {
                return false;
            }
            if (!empty($filters['status']) && ($q['status'] ?? '') !== $filters['status']) {
                return false;
            }
            return true;
        });

        usort($quizzes, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        $offset = (int) ($filters['offset'] ?? 0);
        $limit  = (int) ($filters['limit'] ?? 50);

        return array_slice($quizzes, $offset, $limit);
    }

    // -------------------------------------------------------------------------
    // Grading
    // -------------------------------------------------------------------------

    /**
     * Submit answers and grade a quiz.
     *
     * @param string $quizId
     * @param array  $answers Associative array: questionIndex => userAnswer
     * @return array Graded quiz with results
     * @throws RuntimeException If quiz not found or already completed
     */
    public function submitAnswers(string $quizId, array $answers): array
    {
        $quiz = $this->getQuiz($quizId);

        if (($quiz['status'] ?? '') === 'completed') {
            throw new RuntimeException('QuizService: Quiz has already been completed.');
        }

        $questions = $quiz['questions'] ?? [];
        $correct   = 0;
        $total     = count($questions);
        $results   = [];

        foreach ($questions as $q) {
            $index      = $q['index'] ?? 0;
            $userAnswer = $answers[$index] ?? $answers[(string) $index] ?? null;
            $correctAns = $q['correct_answer'] ?? '';

            $isCorrect = $this->compareAnswers($userAnswer, $correctAns, $q['type'] ?? 'multiple_choice');

            if ($isCorrect) {
                $correct++;
            }

            $results[] = [
                'index'          => $index,
                'question'       => $q['question'] ?? '',
                'user_answer'    => $userAnswer,
                'correct_answer' => $correctAns,
                'is_correct'     => $isCorrect,
                'explanation'    => $q['explanation'] ?? '',
                'topic'          => $q['topic'] ?? '',
            ];
        }

        $percentage = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
        $xpEarned   = $correct * self::XP_PER_CORRECT;

        $this->storage->update(self::COLLECTION_QUIZZES, $quizId, [
            'answers'      => $answers,
            'results'      => $results,
            'score'        => $correct,
            'correct'      => $correct,
            'total'        => $total,
            'percentage'   => $percentage,
            'status'       => 'completed',
            'xp_earned'    => $xpEarned,
            'completed_at' => date('c'),
        ]);

        return [
            'quiz_id'    => $quizId,
            'score'      => $correct,
            'total'      => $total,
            'percentage' => $percentage,
            'xp_earned'  => $xpEarned,
            'results'    => $results,
            'passed'     => $percentage >= 70,
        ];
    }

    /**
     * Get detailed results for a completed quiz.
     *
     * @param string $quizId
     * @return array
     * @throws RuntimeException If not found or not completed
     */
    public function getResults(string $quizId): array
    {
        $quiz = $this->getQuiz($quizId);

        if (($quiz['status'] ?? '') !== 'completed') {
            throw new RuntimeException('QuizService: Quiz has not been completed yet.');
        }

        return [
            'quiz_id'    => $quizId,
            'subject'    => $quiz['subject'] ?? '',
            'topic'      => $quiz['topic'] ?? '',
            'difficulty' => $quiz['difficulty'] ?? '',
            'score'      => $quiz['score'] ?? 0,
            'correct'    => $quiz['correct'] ?? 0,
            'total'      => $quiz['total'] ?? 0,
            'percentage' => $quiz['percentage'] ?? 0,
            'xp_earned'  => $quiz['xp_earned'] ?? 0,
            'results'    => $quiz['results'] ?? [],
            'started_at'  => $quiz['started_at'] ?? '',
            'completed_at' => $quiz['completed_at'] ?? '',
        ];
    }

    /**
     * Calculate score for a quiz.
     *
     * @param string $quizId
     * @return array Score summary
     */
    public function calculateScore(string $quizId): array
    {
        $quiz = $this->getQuiz($quizId);

        return [
            'quiz_id'    => $quizId,
            'score'      => $quiz['score'] ?? 0,
            'correct'    => $quiz['correct'] ?? 0,
            'total'      => $quiz['total'] ?? 0,
            'percentage' => $quiz['percentage'] ?? 0,
            'status'     => $quiz['status'] ?? 'pending',
        ];
    }

    // -------------------------------------------------------------------------
    // Statistics & Analysis
    // -------------------------------------------------------------------------

    /**
     * Get overall quiz statistics for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getQuizStats(string $userId): array
    {
        $quizzes = $this->storage->query(self::COLLECTION_QUIZZES, function (array $q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });

        if (empty($quizzes)) {
            return [
                'user_id'       => $userId,
                'total_quizzes' => 0,
                'avg_score'     => 0,
                'best_score'    => 0,
                'worst_score'   => 0,
                'total_correct' => 0,
                'total_questions' => 0,
                'total_xp'      => 0,
            ];
        }

        $scores      = array_column($quizzes, 'percentage');
        $totalCorrect = array_sum(array_column($quizzes, 'correct'));
        $totalQs      = array_sum(array_column($quizzes, 'total'));
        $totalXP      = array_sum(array_column($quizzes, 'xp_earned'));

        return [
            'user_id'         => $userId,
            'total_quizzes'   => count($quizzes),
            'avg_score'       => round(array_sum($scores) / count($scores), 1),
            'best_score'      => max($scores),
            'worst_score'     => min($scores),
            'total_correct'   => $totalCorrect,
            'total_questions' => $totalQs,
            'overall_accuracy' => $totalQs > 0 ? round(($totalCorrect / $totalQs) * 100, 1) : 0,
            'total_xp'        => $totalXP,
        ];
    }

    /**
     * Get average scores per subject.
     *
     * @param string $userId
     * @return array Subject => average score
     */
    public function getSubjectScores(string $userId): array
    {
        $quizzes = $this->storage->query(self::COLLECTION_QUIZZES, function (array $q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });

        $subjects = [];
        foreach ($quizzes as $q) {
            $subject = $q['subject'] ?? 'unknown';
            if (!isset($subjects[$subject])) {
                $subjects[$subject] = ['scores' => [], 'count' => 0];
            }
            $subjects[$subject]['scores'][] = $q['percentage'] ?? 0;
            $subjects[$subject]['count']++;
        }

        $result = [];
        foreach ($subjects as $name => $data) {
            $result[] = [
                'subject'    => $name,
                'avg_score'  => round(array_sum($data['scores']) / count($data['scores']), 1),
                'best_score' => max($data['scores']),
                'quiz_count' => $data['count'],
            ];
        }

        // Sort by average score descending
        usort($result, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        return $result;
    }

    /**
     * Identify topics where the user is weakest.
     *
     * @param string $userId
     * @return array Weak areas sorted by score (lowest first)
     */
    public function getWeakAreas(string $userId): array
    {
        $quizzes = $this->storage->query(self::COLLECTION_QUIZZES, function (array $q) use ($userId) {
            return ($q['user_id'] ?? '') === $userId && ($q['status'] ?? '') === 'completed';
        });

        $topicScores = [];

        foreach ($quizzes as $quiz) {
            foreach ($quiz['results'] ?? [] as $r) {
                $topic = $r['topic'] ?? $quiz['topic'] ?? 'general';
                if (!isset($topicScores[$topic])) {
                    $topicScores[$topic] = ['correct' => 0, 'total' => 0, 'subject' => $quiz['subject'] ?? ''];
                }
                $topicScores[$topic]['total']++;
                if ($r['is_correct'] ?? false) {
                    $topicScores[$topic]['correct']++;
                }
            }
        }

        $weakAreas = [];
        foreach ($topicScores as $topic => $data) {
            if ($data['total'] < 2) {
                continue; // Need at least 2 questions to judge
            }
            $accuracy = round(($data['correct'] / $data['total']) * 100, 1);
            if ($accuracy < 70) { // Below passing threshold
                $weakAreas[] = [
                    'topic'      => $topic,
                    'subject'    => $data['subject'],
                    'accuracy'   => $accuracy,
                    'correct'    => $data['correct'],
                    'total'      => $data['total'],
                    'suggestion' => $this->getStudySuggestion($accuracy),
                ];
            }
        }

        // Sort by accuracy ascending (weakest first)
        usort($weakAreas, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        return $weakAreas;
    }

    // -------------------------------------------------------------------------
    // Custom Quizzes & Question Bank
    // -------------------------------------------------------------------------

    /**
     * Create a custom quiz with user-provided questions.
     *
     * @param string $userId
     * @param array  $questions Each: {question, type, options, correct_answer, explanation}
     * @return array Created quiz
     * @throws InvalidArgumentException If no questions provided
     */
    public function createCustomQuiz(string $userId, array $questions): array
    {
        if (empty($questions)) {
            throw new InvalidArgumentException('QuizService: At least one question is required.');
        }

        $quizId        = $this->storage->generateId();
        $now           = date('c');
        $quizQuestions = [];

        foreach ($questions as $i => $q) {
            $question = trim($q['question'] ?? '');
            if ($question === '') {
                continue;
            }

            $quizQuestions[] = [
                'index'          => $i,
                'id'             => $this->storage->generateId(),
                'question'       => $question,
                'type'           => $q['type'] ?? 'multiple_choice',
                'options'        => $q['options'] ?? [],
                'difficulty'     => $q['difficulty'] ?? 'medium',
                'topic'          => $q['topic'] ?? '',
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation'    => $q['explanation'] ?? '',
            ];
        }

        if (empty($quizQuestions)) {
            throw new InvalidArgumentException('QuizService: No valid questions provided.');
        }

        $quiz = [
            'id'          => $quizId,
            'user_id'     => $userId,
            'subject'     => 'custom',
            'topic'       => 'custom',
            'difficulty'  => 'mixed',
            'questions'   => $quizQuestions,
            'answers'     => [],
            'score'       => null,
            'total'       => count($quizQuestions),
            'correct'     => null,
            'percentage'  => null,
            'status'      => 'pending',
            'xp_earned'   => 0,
            'is_custom'   => true,
            'started_at'  => $now,
            'completed_at' => null,
            'created_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_QUIZZES, $quizId, $quiz);

        return $quiz;
    }

    /**
     * Get pre-built questions from the question bank.
     *
     * @param string $subject
     * @param string $topic
     * @return array Questions
     */
    public function getQuestionBank(string $subject, string $topic = ''): array
    {
        return $this->storage->query(self::COLLECTION_QUESTIONS, function (array $q) use ($subject, $topic) {
            if (($q['subject'] ?? '') !== $subject) {
                return false;
            }
            if ($topic !== '' && ($q['topic'] ?? '') !== $topic) {
                return false;
            }
            return true;
        });
    }

    /**
     * Generate a prompt for AI-based question generation.
     *
     * @param string $subject
     * @param string $topic
     * @param string $difficulty easy|medium|hard
     * @param int    $count
     * @return array Prompt data
     */
    public function generateAIQuestions(string $subject, string $topic, string $difficulty = 'medium', int $count = 5): array
    {
        $count = max(1, min(20, $count));

        $prompt = "Generate {$count} {$difficulty}-difficulty quiz questions about {$subject}"
            . ($topic !== '' ? " - specifically: {$topic}" : '') . ".\n\n"
            . "Format each question as JSON with these fields:\n"
            . "- question: The question text\n"
            . "- type: 'multiple_choice' or 'true_false'\n"
            . "- options: Array of 4 options (for multiple_choice) or ['True', 'False']\n"
            . "- correct_answer: The correct option text\n"
            . "- explanation: Brief explanation of why the answer is correct\n"
            . "- topic: Specific sub-topic\n"
            . "- difficulty: '{$difficulty}'\n\n"
            . "Return a JSON array. Ensure questions test understanding, not just memorization.";

        return [
            'subject'    => $subject,
            'topic'      => $topic,
            'difficulty' => $difficulty,
            'count'      => $count,
            'prompt'     => $prompt,
            'format'     => 'json_array',
        ];
    }

    /**
     * Create a retake of an existing quiz with the same questions.
     *
     * @param string $quizId Original quiz ID
     * @return array New quiz
     * @throws RuntimeException If original quiz not found
     */
    public function retakeQuiz(string $quizId): array
    {
        $original = $this->getQuiz($quizId);
        $newQuizId = $this->storage->generateId();
        $now       = date('c');

        // Reset question results
        $questions = $original['questions'] ?? [];
        shuffle($questions); // Shuffle question order
        foreach ($questions as &$q) {
            $q['index'] = array_search($q, $questions);
        }
        unset($q);

        $quiz = [
            'id'           => $newQuizId,
            'user_id'      => $original['user_id'] ?? '',
            'subject'      => $original['subject'] ?? '',
            'topic'        => $original['topic'] ?? '',
            'difficulty'   => $original['difficulty'] ?? 'mixed',
            'questions'    => $questions,
            'answers'      => [],
            'score'        => null,
            'total'        => count($questions),
            'correct'      => null,
            'percentage'   => null,
            'status'       => 'pending',
            'xp_earned'    => 0,
            'is_retake'    => true,
            'original_id'  => $quizId,
            'started_at'   => $now,
            'completed_at' => null,
            'created_at'   => $now,
        ];

        $this->storage->write(self::COLLECTION_QUIZZES, $newQuizId, $quiz);

        return $quiz;
    }

    /**
     * Get quiz history for a user in a specific subject.
     *
     * @param string $userId
     * @param string $subject
     * @return array Quizzes sorted by date
     */
    public function getQuizHistory(string $userId, string $subject = ''): array
    {
        $quizzes = $this->storage->query(self::COLLECTION_QUIZZES, function (array $q) use ($userId, $subject) {
            if (($q['user_id'] ?? '') !== $userId) {
                return false;
            }
            if ($subject !== '' && ($q['subject'] ?? '') !== $subject) {
                return false;
            }
            return ($q['status'] ?? '') === 'completed';
        });

        usort($quizzes, fn($a, $b) => strtotime($b['completed_at'] ?? '0') - strtotime($a['completed_at'] ?? '0'));

        return array_map(function (array $q) {
            return [
                'id'           => $q['id'] ?? $q['_id'] ?? '',
                'subject'      => $q['subject'] ?? '',
                'topic'        => $q['topic'] ?? '',
                'difficulty'   => $q['difficulty'] ?? '',
                'score'        => $q['score'] ?? 0,
                'total'        => $q['total'] ?? 0,
                'percentage'   => $q['percentage'] ?? 0,
                'xp_earned'    => $q['xp_earned'] ?? 0,
                'is_retake'    => $q['is_retake'] ?? false,
                'completed_at' => $q['completed_at'] ?? '',
            ];
        }, $quizzes);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Compare user answer against correct answer.
     */
    private function compareAnswers(?string $userAnswer, string $correctAnswer, string $type): bool
    {
        if ($userAnswer === null || $userAnswer === '') {
            return false;
        }

        $user    = mb_strtolower(trim($userAnswer), 'UTF-8');
        $correct = mb_strtolower(trim($correctAnswer), 'UTF-8');

        return $user === $correct;
    }

    /**
     * Get a study suggestion based on accuracy.
     */
    private function getStudySuggestion(float $accuracy): string
    {
        if ($accuracy < 30) {
            return 'This topic needs significant review. Start with the basics and study the core concepts.';
        }
        if ($accuracy < 50) {
            return 'Review this topic thoroughly. Focus on understanding key concepts before moving on.';
        }
        return 'You\'re making progress but need more practice. Try re-reading notes and taking more quizzes.';
    }

    /**
     * Create an empty quiz placeholder when no questions available.
     */
    private function createEmptyQuiz(string $userId, string $subject, string $topic, string $difficulty, int $count): array
    {
        $quizId = $this->storage->generateId();
        $now    = date('c');

        $quiz = [
            'id'          => $quizId,
            'user_id'     => $userId,
            'subject'     => $subject,
            'topic'       => $topic,
            'difficulty'  => $difficulty,
            'questions'   => [],
            'answers'     => [],
            'score'       => null,
            'total'       => 0,
            'correct'     => null,
            'percentage'  => null,
            'status'      => 'empty',
            'xp_earned'   => 0,
            'message'     => "No questions available for {$subject}" . ($topic ? " / {$topic}" : '') . ". Try using AI to generate questions.",
            'started_at'  => $now,
            'created_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_QUIZZES, $quizId, $quiz);

        return $quiz;
    }
}
