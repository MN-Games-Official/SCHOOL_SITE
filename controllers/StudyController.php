<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/StudyService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class StudyController
{
    private StudyService $studyService;
    private SubjectService $subjectService;
    private Session $session;

    public function __construct()
    {
        $this->studyService   = new StudyService();
        $this->subjectService = new SubjectService();
        $this->session        = new Session();
    }

    /**
     * Study home page with sessions list and stats.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $sessions  = $this->studyService->getUserSessions($userId, ['limit' => 10]);
            $streak    = $this->studyService->getStudyStreak($userId);
            $studyTime = $this->studyService->getTotalStudyTime($userId, 'week');
            $goals     = $this->studyService->getStudyGoals($userId);
            $subjects  = $this->subjectService->getAllSubjects();

            return View::render('study/index', [
                'title'     => 'Study Sessions',
                'sessions'  => $sessions,
                'streak'    => $streak,
                'studyTime' => $studyTime,
                'goals'     => $goals,
                'subjects'  => $subjects,
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load study data.');
            return View::render('study/index', [
                'title'     => 'Study Sessions',
                'sessions'  => [],
                'streak'    => ['current' => 0],
                'studyTime' => ['total' => 0],
                'goals'     => [],
                'subjects'  => [],
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Start a new study session.
     */
    public function startSession(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId    = $this->session->get('user_id');
        $subjectId = $request->post('subject_id') ?? '';
        $topicId   = $request->post('topic_id') ?? '';

        if (empty($subjectId)) {
            if ($request->isAjax()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Please select a subject.',
                ], 400);
            }
            $this->session->flash('error', 'Please select a subject to study.');
            return redirect(url('/study'));
        }

        try {
            $session = $this->studyService->startSession($userId, $subjectId, $topicId);

            if ($request->isAjax()) {
                return Response::json([
                    'success'    => true,
                    'message'    => 'Study session started!',
                    'session_id' => $session['id'],
                ]);
            }

            $this->session->flash('success', 'Study session started!');
            return redirect(url('/study/session/' . $session['id']));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/study'));
        }
    }

    /**
     * Active study session page.
     */
    public function session(Request $request, array $params = []): string
    {
        Middleware::auth();

        $sessionId = $params['id'] ?? '';

        if (empty($sessionId)) {
            $this->session->flash('error', 'Study session not found.');
            return redirect(url('/study'));
        }

        try {
            $studySession = $this->studyService->getSession($sessionId);
            $subject      = $this->subjectService->getSubject($studySession['subject_id'] ?? '');

            $topicContent = [];
            if (!empty($studySession['topic_id'])) {
                $topicContent = $this->subjectService->getTopicContent(
                    $studySession['subject_id'],
                    $studySession['topic_id']
                );
            }

            return View::render('study/session', [
                'title'        => 'Study Session',
                'session'      => $studySession,
                'subject'      => $subject,
                'topicContent' => $topicContent,
                'csrf'         => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Study session not found.');
            return redirect(url('/study'));
        }
    }

    /**
     * End an active study session.
     */
    public function endSession(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $sessionId = $params['id'] ?? '';
        $notes     = trim($request->post('notes') ?? '');

        if (empty($sessionId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Session not found.'], 400);
            }
            $this->session->flash('error', 'Session not found.');
            return redirect(url('/study'));
        }

        try {
            $result = $this->studyService->endSession($sessionId, $notes);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Session ended! Great work.',
                    'result'  => $result,
                ]);
            }

            $this->session->flash('success', 'Study session completed! Great work.');
            return redirect(url('/study/review/' . $sessionId));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/study'));
        }
    }

    /**
     * Review a completed study session.
     */
    public function review(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId    = $this->session->get('user_id');
        $sessionId = $params['id'] ?? '';

        if (empty($sessionId)) {
            $this->session->flash('error', 'Session not found.');
            return redirect(url('/study'));
        }

        try {
            $studySession = $this->studyService->getSession($sessionId);
            $streak       = $this->studyService->getStudyStreak($userId);
            $weeklyTime   = $this->studyService->getTotalStudyTime($userId, 'week');

            return View::render('study/review', [
                'title'      => 'Session Review',
                'session'    => $studySession,
                'streak'     => $streak,
                'weeklyTime' => $weeklyTime,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Session not found.');
            return redirect(url('/study'));
        }
    }

    /**
     * AJAX: Get sessions list with filters.
     */
    public function getSessions(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId  = $this->session->get('user_id');
        $filters = [];

        if ($request->has('subject_id')) {
            $filters['subject_id'] = $request->get('subject_id');
        }
        if ($request->has('date')) {
            $filters['date'] = $request->get('date');
        }
        if ($request->has('limit')) {
            $filters['limit'] = max(1, min((int) $request->get('limit'), 100));
        }

        try {
            $sessions = $this->studyService->getUserSessions($userId, $filters);

            return Response::json([
                'success'  => true,
                'sessions' => $sessions,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load sessions.',
            ], 500);
        }
    }

    /**
     * AJAX: Get study heatmap data.
     */
    public function getHeatmap(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $heatmap = $this->studyService->getStudyHeatmap($userId);

            return Response::json([
                'success' => true,
                'heatmap' => $heatmap,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load heatmap data.',
            ], 500);
        }
    }

    /**
     * Set a study goal.
     */
    public function setGoal(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $type   = trim($request->post('type') ?? '');
        $target = (int) ($request->post('target') ?? 0);

        $validTypes = ['daily_minutes', 'weekly_minutes', 'daily_sessions', 'weekly_sessions'];

        if (empty($type) || !in_array($type, $validTypes, true)) {
            return Response::json([
                'success' => false,
                'message' => 'Invalid goal type.',
            ], 400);
        }

        if ($target < 1) {
            return Response::json([
                'success' => false,
                'message' => 'Goal target must be at least 1.',
            ], 400);
        }

        try {
            $goal = $this->studyService->setStudyGoal($userId, $type, $target);

            return Response::json([
                'success' => true,
                'message' => 'Study goal set!',
                'goal'    => $goal,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current study goals.
     */
    public function getGoals(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $goals    = $this->studyService->getStudyGoals($userId);
            $progress = $this->studyService->checkGoalProgress($userId);

            return Response::json([
                'success'  => true,
                'goals'    => $goals,
                'progress' => $progress,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load goals.',
            ], 500);
        }
    }
}
