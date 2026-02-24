<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/ProgressService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class ProgressController
{
    private ProgressService $progressService;
    private SubjectService $subjectService;
    private Session $session;

    public function __construct()
    {
        $this->progressService = new ProgressService();
        $this->subjectService  = new SubjectService();
        $this->session         = new Session();
    }

    /**
     * Progress overview page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $overview    = $this->progressService->getOverview($userId);
            $milestones  = $this->progressService->getMilestones($userId);
            $goals       = $this->progressService->getGoalCompletion($userId);
            $strengths   = $this->progressService->getStrengthsAndWeaknesses($userId);
            $subjects    = $this->subjectService->getAllSubjects();

            return View::render('progress/index', [
                'title'      => 'My Progress',
                'overview'   => $overview,
                'milestones' => $milestones,
                'goals'      => $goals,
                'strengths'  => $strengths,
                'subjects'   => $subjects,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load progress data.');
            return View::render('progress/index', [
                'title'      => 'My Progress',
                'overview'   => [],
                'milestones' => [],
                'goals'      => [],
                'strengths'  => [],
                'subjects'   => [],
            ], 'layouts/main');
        }
    }

    /**
     * Subject-specific progress page.
     */
    public function subject(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId    = $this->session->get('user_id');
        $subjectId = $params['id'] ?? '';

        if (empty($subjectId)) {
            $this->session->flash('error', 'Subject not found.');
            return redirect(url('/progress'));
        }

        try {
            $subject         = $this->subjectService->getSubject($subjectId);
            $subjectProgress = $this->progressService->getSubjectProgress($userId, $subjectId);
            $topicProgress   = $this->subjectService->getUserSubjectProgress($userId, $subjectId);

            return View::render('progress/subject', [
                'title'           => 'Progress: ' . e($subject['name'] ?? ''),
                'subject'         => $subject,
                'subjectProgress' => $subjectProgress,
                'topicProgress'   => $topicProgress,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Subject not found.');
            return redirect(url('/progress'));
        }
    }

    /**
     * AJAX: Get progress overview data.
     */
    public function getOverview(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $overview = $this->progressService->getOverview($userId);

            return Response::json([
                'success'  => true,
                'overview' => $overview,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load overview.',
            ], 500);
        }
    }

    /**
     * AJAX: Get chart data (study time, quiz scores, etc.).
     */
    public function getChartData(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $type   = $request->get('type') ?? 'study_time';

        try {
            $data = [];

            switch ($type) {
                case 'study_time':
                    $days = (int) ($request->get('days') ?? 30);
                    $days = max(7, min($days, 365));
                    $data = $this->progressService->getStudyTimeByDay($userId, $days);
                    break;

                case 'quiz_scores':
                    $limit = (int) ($request->get('limit') ?? 20);
                    $limit = max(5, min($limit, 100));
                    $data  = $this->progressService->getQuizScoresTrend($userId, $limit);
                    break;

                case 'writing':
                    $data = $this->progressService->getWritingProgress($userId);
                    break;

                case 'flashcards':
                    $data = $this->progressService->getFlashcardProgress($userId);
                    break;

                case 'goals':
                    $data = $this->progressService->getGoalCompletion($userId);
                    break;

                default:
                    return Response::json([
                        'success' => false,
                        'message' => 'Invalid chart type.',
                    ], 400);
            }

            return Response::json([
                'success' => true,
                'type'    => $type,
                'data'    => $data,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load chart data.',
            ], 500);
        }
    }

    /**
     * AJAX: Get activity calendar data.
     */
    public function getCalendar(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $month  = $request->get('month') ?? '';

        try {
            $calendar = $this->progressService->getActivityCalendar($userId, $month);

            return Response::json([
                'success'  => true,
                'calendar' => $calendar,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load calendar data.',
            ], 500);
        }
    }

    /**
     * AJAX: Get milestones.
     */
    public function getMilestones(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $milestones = $this->progressService->getMilestones($userId);
            $this->progressService->checkMilestone($userId);

            return Response::json([
                'success'    => true,
                'milestones' => $milestones,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load milestones.',
            ], 500);
        }
    }

    /**
     * AJAX: Get weekly or monthly report.
     */
    public function getReport(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $period = $request->get('period') ?? 'week';

        try {
            if ($period === 'month') {
                $report = $this->progressService->getMonthlyReport($userId);
            } else {
                $report = $this->progressService->getWeeklyReport($userId);
            }

            return Response::json([
                'success' => true,
                'period'  => $period,
                'report'  => $report,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to generate report.',
            ], 500);
        }
    }

    /**
     * Export progress report.
     */
    public function exportReport(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $report = $this->progressService->exportProgressReport($userId);

            return Response::json([
                'success' => true,
                'data'    => $report,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to export report.',
            ], 500);
        }
    }

    /**
     * AJAX: Get strengths and weaknesses analysis.
     */
    public function getStrengths(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $strengths = $this->progressService->getStrengthsAndWeaknesses($userId);

            return Response::json([
                'success'   => true,
                'strengths' => $strengths,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to analyze strengths.',
            ], 500);
        }
    }
}
