<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/StudyService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../services/ProgressService.php';
require_once __DIR__ . '/../services/PlannerService.php';
require_once __DIR__ . '/../services/QuizService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class DashboardController
{
    private StudyService $studyService;
    private SubjectService $subjectService;
    private ProgressService $progressService;
    private PlannerService $plannerService;
    private QuizService $quizService;
    private Session $session;

    public function __construct()
    {
        $this->studyService    = new StudyService();
        $this->subjectService  = new SubjectService();
        $this->progressService = new ProgressService();
        $this->plannerService  = new PlannerService();
        $this->quizService     = new QuizService();
        $this->session         = new Session();
    }

    /**
     * Main dashboard page with study overview.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $recentActivity = $this->studyService->getRecentActivity($userId, 5);
            $studyStreak    = $this->studyService->getStudyStreak($userId);
            $studyTime      = $this->studyService->getTotalStudyTime($userId, 'week');
            $goals          = $this->studyService->getStudyGoals($userId);
            $goalProgress   = $this->studyService->checkGoalProgress($userId);

            $progressOverview = $this->progressService->getOverview($userId);
            $milestones       = $this->progressService->getMilestones($userId);

            $todaysTasks = $this->plannerService->getTodaysTasks($userId);
            $upcoming    = $this->plannerService->getUpcomingTasks($userId, 3);

            $quizStats    = $this->quizService->getQuizStats($userId);
            $subjectStats = $this->subjectService->getSubjectStats($userId);

            return View::render('dashboard/index', [
                'title'            => 'Dashboard',
                'recentActivity'   => $recentActivity,
                'studyStreak'      => $studyStreak,
                'studyTime'        => $studyTime,
                'goals'            => $goals,
                'goalProgress'     => $goalProgress,
                'progressOverview' => $progressOverview,
                'milestones'       => $milestones,
                'todaysTasks'      => $todaysTasks,
                'upcomingTasks'    => $upcoming,
                'quizStats'        => $quizStats,
                'subjectStats'     => $subjectStats,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load dashboard data.');
            return View::render('dashboard/index', [
                'title'            => 'Dashboard',
                'recentActivity'   => [],
                'studyStreak'      => ['current' => 0],
                'studyTime'        => ['total' => 0],
                'goals'            => [],
                'goalProgress'     => [],
                'progressOverview' => [],
                'milestones'       => [],
                'todaysTasks'      => [],
                'upcomingTasks'    => [],
                'quizStats'        => [],
                'subjectStats'     => [],
            ], 'layouts/main');
        }
    }

    /**
     * AJAX: Get recent activity feed.
     */
    public function getActivityFeed(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $limit  = (int) ($request->get('limit') ?? 10);
        $limit  = max(1, min($limit, 50));

        try {
            $activity = $this->studyService->getRecentActivity($userId, $limit);

            return Response::json([
                'success'  => true,
                'activity' => $activity,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load activity feed.',
            ], 500);
        }
    }

    /**
     * AJAX: Get weekly study report summary.
     */
    public function getWeeklyReport(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $report    = $this->studyService->generateStudyReport($userId, 'week');
            $streak    = $this->studyService->getStudyStreak($userId);
            $todayTime = $this->studyService->getTotalStudyTime($userId, 'day');

            return Response::json([
                'success'  => true,
                'report'   => $report,
                'streak'   => $streak,
                'todayTime' => $todayTime,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load weekly report.',
            ], 500);
        }
    }

    /**
     * AJAX: Get goal progress data for dashboard widgets.
     */
    public function getGoalProgress(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $goals         = $this->studyService->getStudyGoals($userId);
            $goalProgress  = $this->studyService->checkGoalProgress($userId);
            $completionRate = $this->plannerService->getCompletionRate($userId);
            $overdue        = $this->plannerService->getOverdueTasks($userId);

            return Response::json([
                'success'        => true,
                'goals'          => $goals,
                'goalProgress'   => $goalProgress,
                'completionRate' => $completionRate,
                'overdueCount'   => count($overdue),
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load goal progress.',
            ], 500);
        }
    }

    /**
     * AJAX: Get subject breakdown for dashboard charts.
     */
    public function getSubjectBreakdown(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $subjectStats   = $this->subjectService->getSubjectStats($userId);
            $subjectScores  = $this->quizService->getSubjectScores($userId);
            $recommended    = $this->subjectService->getRecommendedTopics($userId);

            return Response::json([
                'success'       => true,
                'subjectStats'  => $subjectStats,
                'subjectScores' => $subjectScores,
                'recommended'   => $recommended,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load subject breakdown.',
            ], 500);
        }
    }

    /**
     * AJAX: Get chart / stats data for the dashboard.
     */
    public function getStatsData(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $type   = $request->get('type') ?? 'overview';

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
                    $data  = $this->progressService->getQuizScoresTrend($userId, $limit);
                    break;

                case 'subject_progress':
                    $data = $this->subjectService->getSubjectStats($userId);
                    break;

                case 'goals':
                    $data = $this->studyService->checkGoalProgress($userId);
                    break;

                case 'heatmap':
                    $data = $this->studyService->getStudyHeatmap($userId);
                    break;

                case 'overview':
                default:
                    $data = $this->progressService->getOverview($userId);
                    break;
            }

            return Response::json([
                'success' => true,
                'type'    => $type,
                'data'    => $data,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load stats data.',
            ], 500);
        }
    }
}
