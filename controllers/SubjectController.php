<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../services/ProgressService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class SubjectController
{
    private SubjectService $subjectService;
    private ProgressService $progressService;
    private Session $session;

    public function __construct()
    {
        $this->subjectService  = new SubjectService();
        $this->progressService = new ProgressService();
        $this->session         = new Session();
    }

    /**
     * List all subjects with progress indicators.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $subjects     = $this->subjectService->getAllSubjects();
            $subjectStats = $this->subjectService->getSubjectStats($userId);
            $recommended  = $this->subjectService->getRecommendedTopics($userId);

            return View::render('subjects/index', [
                'title'       => 'Subjects',
                'subjects'    => $subjects,
                'stats'       => $subjectStats,
                'recommended' => $recommended,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load subjects.');
            return View::render('subjects/index', [
                'title'       => 'Subjects',
                'subjects'    => [],
                'stats'       => [],
                'recommended' => [],
            ], 'layouts/main');
        }
    }

    /**
     * Show subject detail with its topics list.
     */
    public function show(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId    = $this->session->get('user_id');
        $subjectId = $params['id'] ?? '';

        if (empty($subjectId)) {
            $this->session->flash('error', 'Subject not found.');
            return redirect(url('/subjects'));
        }

        try {
            $subject  = $this->subjectService->getSubject($subjectId);
            $progress = $this->subjectService->getUserSubjectProgress($userId, $subjectId);
            $popular  = $this->subjectService->getPopularTopics();

            return View::render('subjects/show', [
                'title'    => e($subject['name'] ?? 'Subject'),
                'subject'  => $subject,
                'progress' => $progress,
                'popular'  => $popular,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Subject not found.');
            return redirect(url('/subjects'));
        }
    }

    /**
     * Show topic detail with learning content.
     */
    public function topic(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId    = $this->session->get('user_id');
        $subjectId = $params['subject_id'] ?? '';
        $topicId   = $params['topic_id'] ?? '';

        if (empty($subjectId) || empty($topicId)) {
            $this->session->flash('error', 'Topic not found.');
            return redirect(url('/subjects'));
        }

        try {
            $subject = $this->subjectService->getSubject($subjectId);
            $topic   = $this->subjectService->getTopic($subjectId, $topicId);
            $content = $this->subjectService->getTopicContent($subjectId, $topicId);

            $progress = $this->subjectService->getUserSubjectProgress($userId, $subjectId);

            return View::render('subjects/topic', [
                'title'    => e($topic['name'] ?? 'Topic'),
                'subject'  => $subject,
                'topic'    => $topic,
                'content'  => $content,
                'progress' => $progress,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Topic not found.');
            return redirect(url('/subjects/' . urlencode($subjectId)));
        }
    }

    /**
     * AJAX: Mark a topic as complete.
     */
    public function markComplete(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId    = $this->session->get('user_id');
        $subjectId = $params['subject_id'] ?? '';
        $topicId   = $params['topic_id'] ?? '';

        if (empty($subjectId) || empty($topicId)) {
            return Response::json([
                'success' => false,
                'message' => 'Invalid subject or topic.',
            ], 400);
        }

        try {
            $result   = $this->subjectService->markTopicComplete($userId, $subjectId, $topicId);
            $progress = $this->subjectService->getUserSubjectProgress($userId, $subjectId);

            return Response::json([
                'success'  => true,
                'message'  => 'Topic marked as complete!',
                'result'   => $result,
                'progress' => $progress,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Search subjects and topics.
     */
    public function search(Request $request, array $params = []): string
    {
        Middleware::auth();

        $query = trim($request->get('q') ?? '');

        if (empty($query)) {
            return Response::json([
                'success' => true,
                'results' => [],
            ]);
        }

        if (strlen($query) < 2) {
            return Response::json([
                'success' => true,
                'results' => [],
                'message' => 'Please enter at least 2 characters.',
            ]);
        }

        if (strlen($query) > 200) {
            return Response::json([
                'success' => false,
                'message' => 'Search query is too long.',
            ], 400);
        }

        try {
            $results = $this->subjectService->searchSubjects($query);

            return Response::json([
                'success' => true,
                'query'   => $query,
                'results' => $results,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * AJAX: Get progress data for a subject.
     */
    public function getProgress(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId    = $this->session->get('user_id');
        $subjectId = $params['id'] ?? '';

        if (empty($subjectId)) {
            return Response::json([
                'success' => false,
                'message' => 'Subject ID is required.',
            ], 400);
        }

        try {
            $progress        = $this->subjectService->getUserSubjectProgress($userId, $subjectId);
            $subjectProgress = $this->progressService->getSubjectProgress($userId, $subjectId);

            return Response::json([
                'success'         => true,
                'progress'        => $progress,
                'subjectProgress' => $subjectProgress,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load progress data.',
            ], 500);
        }
    }

    /**
     * Get recommended topics for the student.
     */
    public function recommended(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $recommended = $this->subjectService->getRecommendedTopics($userId);
            $popular     = $this->subjectService->getPopularTopics();

            return View::render('subjects/recommended', [
                'title'       => 'Recommended Topics',
                'recommended' => $recommended,
                'popular'     => $popular,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load recommendations.');
            return View::render('subjects/recommended', [
                'title'       => 'Recommended Topics',
                'recommended' => [],
                'popular'     => [],
            ], 'layouts/main');
        }
    }

    /**
     * Get all subjects statistics overview.
     */
    public function stats(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $subjects     = $this->subjectService->getAllSubjects();
            $subjectStats = $this->subjectService->getSubjectStats($userId);
            $overview     = $this->progressService->getOverview($userId);
            $popular      = $this->subjectService->getPopularTopics();
            $strengths    = $this->progressService->getStrengthsAndWeaknesses($userId);

            return View::render('subjects/stats', [
                'title'        => 'Subject Statistics',
                'subjects'     => $subjects,
                'subjectStats' => $subjectStats,
                'overview'     => $overview,
                'popular'      => $popular,
                'strengths'    => $strengths,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load statistics.');
            return View::render('subjects/stats', [
                'title'        => 'Subject Statistics',
                'subjects'     => [],
                'subjectStats' => [],
                'overview'     => [],
                'popular'      => [],
                'strengths'    => [],
            ], 'layouts/main');
        }
    }
}
