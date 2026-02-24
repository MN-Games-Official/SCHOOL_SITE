<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/QuizService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class QuizController
{
    private QuizService $quizService;
    private SubjectService $subjectService;
    private Session $session;

    public function __construct()
    {
        $this->quizService    = new QuizService();
        $this->subjectService = new SubjectService();
        $this->session        = new Session();
    }

    /**
     * Quiz home page with history.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $quizzes  = $this->quizService->getUserQuizzes($userId, ['limit' => 10]);
            $stats    = $this->quizService->getQuizStats($userId);
            $subjects = $this->subjectService->getAllSubjects();

            return View::render('quiz/index', [
                'title'    => 'Quizzes',
                'quizzes'  => $quizzes,
                'stats'    => $stats,
                'subjects' => $subjects,
                'csrf'     => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load quizzes.');
            return View::render('quiz/index', [
                'title'    => 'Quizzes',
                'quizzes'  => [],
                'stats'    => [],
                'subjects' => [],
                'csrf'     => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Show quiz generation form.
     */
    public function generate(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $subjects  = $this->subjectService->getAllSubjects();
            $weakAreas = $this->quizService->getWeakAreas($this->session->get('user_id'));

            return View::render('quiz/generate', [
                'title'     => 'Generate Quiz',
                'subjects'  => $subjects,
                'weakAreas' => $weakAreas,
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            return View::render('quiz/generate', [
                'title'     => 'Generate Quiz',
                'subjects'  => [],
                'weakAreas' => [],
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Generate and create a quiz.
     */
    public function create(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $subject    = trim($request->post('subject') ?? '');
        $topic      = trim($request->post('topic') ?? '');
        $difficulty = trim($request->post('difficulty') ?? 'medium');
        $count      = (int) ($request->post('count') ?? 10);

        if (empty($subject)) {
            $errors['subject'] = 'Please select a subject.';
        }

        $validDifficulties = ['easy', 'medium', 'hard', 'mixed'];
        if (!in_array($difficulty, $validDifficulties, true)) {
            $errors['difficulty'] = 'Invalid difficulty level.';
        }

        $count = max(5, min($count, 50));

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'subject'    => $subject,
                'topic'      => $topic,
                'difficulty' => $difficulty,
                'count'      => $count,
            ]);
            return redirect(url('/quiz/generate'));
        }

        try {
            $quiz = $this->quizService->generateQuiz($userId, [
                'subject'    => $subject,
                'topic'      => $topic,
                'difficulty' => $difficulty,
                'count'      => $count,
            ]);

            $this->session->flash('success', 'Quiz generated! Good luck.');
            return redirect(url('/quiz/take/' . $quiz['id']));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/quiz/generate'));
        }
    }

    /**
     * Take a quiz page.
     */
    public function take(Request $request, array $params = []): string
    {
        Middleware::auth();

        $quizId = $params['id'] ?? '';

        if (empty($quizId)) {
            $this->session->flash('error', 'Quiz not found.');
            return redirect(url('/quiz'));
        }

        try {
            $quiz = $this->quizService->getQuiz($quizId);

            return View::render('quiz/take', [
                'title' => 'Quiz: ' . e($quiz['subject'] ?? 'Quiz'),
                'quiz'  => $quiz,
                'csrf'  => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Quiz not found.');
            return redirect(url('/quiz'));
        }
    }

    /**
     * Submit quiz answers.
     */
    public function submit(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $quizId  = $params['id'] ?? '';
        $answers = $request->post('answers') ?? [];

        if (empty($quizId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Quiz not found.'], 400);
            }
            $this->session->flash('error', 'Quiz not found.');
            return redirect(url('/quiz'));
        }

        if (empty($answers) || !is_array($answers)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Please answer at least one question.'], 400);
            }
            $this->session->flash('error', 'Please answer at least one question.');
            return redirect(url('/quiz/take/' . urlencode($quizId)));
        }

        try {
            $result = $this->quizService->submitAnswers($quizId, $answers);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Quiz submitted!',
                    'result'  => $result,
                ]);
            }

            $this->session->flash('success', 'Quiz submitted! Check your results.');
            return redirect(url('/quiz/results/' . urlencode($quizId)));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/quiz/take/' . urlencode($quizId)));
        }
    }

    /**
     * Show quiz results.
     */
    public function results(Request $request, array $params = []): string
    {
        Middleware::auth();

        $quizId = $params['id'] ?? '';

        if (empty($quizId)) {
            $this->session->flash('error', 'Quiz not found.');
            return redirect(url('/quiz'));
        }

        try {
            $quiz    = $this->quizService->getQuiz($quizId);
            $results = $this->quizService->getResults($quizId);
            $score   = $this->quizService->calculateScore($quizId);

            return View::render('quiz/results', [
                'title'   => 'Quiz Results',
                'quiz'    => $quiz,
                'results' => $results,
                'score'   => $score,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Results not available.');
            return redirect(url('/quiz'));
        }
    }

    /**
     * AJAX: Get quiz history.
     */
    public function getHistory(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId  = $this->session->get('user_id');
        $subject = $request->get('subject') ?? '';

        try {
            $history = $this->quizService->getQuizHistory($userId, $subject);

            return Response::json([
                'success' => true,
                'history' => $history,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load quiz history.',
            ], 500);
        }
    }

    /**
     * AJAX: Get quiz score trends.
     */
    public function getScores(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $scores        = $this->quizService->getSubjectScores($userId);
            $stats         = $this->quizService->getQuizStats($userId);

            return Response::json([
                'success' => true,
                'scores'  => $scores,
                'stats'   => $stats,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load scores.',
            ], 500);
        }
    }

    /**
     * AJAX: Get weak areas analysis.
     */
    public function getWeakAreas(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $weakAreas = $this->quizService->getWeakAreas($userId);

            return Response::json([
                'success'   => true,
                'weakAreas' => $weakAreas,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to analyze weak areas.',
            ], 500);
        }
    }

    /**
     * Retake a quiz (generates a new attempt).
     */
    public function retake(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $quizId = $params['id'] ?? '';

        if (empty($quizId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Quiz not found.'], 400);
            }
            $this->session->flash('error', 'Quiz not found.');
            return redirect(url('/quiz'));
        }

        try {
            $newQuiz = $this->quizService->retakeQuiz($quizId);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'quiz_id' => $newQuiz['id'],
                ]);
            }

            return redirect(url('/quiz/take/' . $newQuiz['id']));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/quiz'));
        }
    }

    /**
     * Create a custom quiz with user-defined questions.
     */
    public function createCustom(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId    = $this->session->get('user_id');
        $questions = $request->post('questions') ?? [];

        if (empty($questions) || !is_array($questions)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'At least one question is required.'], 400);
            }
            $this->session->flash('error', 'At least one question is required.');
            return redirect(url('/quiz/generate'));
        }

        // Validate each question
        $validatedQuestions = [];
        foreach ($questions as $i => $q) {
            if (empty($q['question'])) {
                if ($request->isAjax()) {
                    return Response::json([
                        'success' => false,
                        'message' => 'Question ' . ($i + 1) . ' text is required.',
                    ], 400);
                }
                $this->session->flash('error', 'Question ' . ($i + 1) . ' text is required.');
                return redirect(url('/quiz/generate'));
            }
            $validatedQuestions[] = [
                'question' => trim($q['question']),
                'options'  => $q['options'] ?? [],
                'answer'   => $q['answer'] ?? '',
                'type'     => $q['type'] ?? 'multiple_choice',
            ];
        }

        try {
            $quiz = $this->quizService->createCustomQuiz($userId, $validatedQuestions);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Custom quiz created!',
                    'quiz_id' => $quiz['id'],
                ]);
            }

            $this->session->flash('success', 'Custom quiz created!');
            return redirect(url('/quiz/take/' . $quiz['id']));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/quiz/generate'));
        }
    }
}
