<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/PlannerService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class PlannerController
{
    private PlannerService $plannerService;
    private SubjectService $subjectService;
    private Session $session;

    public function __construct()
    {
        $this->plannerService = new PlannerService();
        $this->subjectService = new SubjectService();
        $this->session        = new Session();
    }

    /**
     * Planner page with today's tasks.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $plans          = $this->plannerService->getUserPlans($userId);
            $todaysTasks    = $this->plannerService->getTodaysTasks($userId);
            $upcoming       = $this->plannerService->getUpcomingTasks($userId, 7);
            $overdue        = $this->plannerService->getOverdueTasks($userId);
            $completionRate = $this->plannerService->getCompletionRate($userId);

            return View::render('planner/index', [
                'title'          => 'Study Planner',
                'plans'          => $plans,
                'todaysTasks'    => $todaysTasks,
                'upcomingTasks'  => $upcoming,
                'overdueTasks'   => $overdue,
                'completionRate' => $completionRate,
                'csrf'           => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load planner.');
            return View::render('planner/index', [
                'title'          => 'Study Planner',
                'plans'          => [],
                'todaysTasks'    => [],
                'upcomingTasks'  => [],
                'overdueTasks'   => [],
                'completionRate' => [],
                'csrf'           => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Show create plan form.
     */
    public function create(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $subjects = $this->subjectService->getAllSubjects();
        } catch (\RuntimeException $e) {
            $subjects = [];
        }

        return View::render('planner/create', [
            'title'    => 'Create Study Plan',
            'subjects' => $subjects,
            'csrf'     => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Save a new plan.
     */
    public function store(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $title       = trim($request->post('title') ?? '');
        $description = trim($request->post('description') ?? '');
        $goals       = $request->post('goals') ?? '';
        $startDate   = trim($request->post('start_date') ?? '');
        $endDate     = trim($request->post('end_date') ?? '');

        if (empty($title)) {
            $errors['title'] = 'Plan title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title must be under 200 characters.';
        }

        if (!empty($startDate) && !strtotime($startDate)) {
            $errors['start_date'] = 'Invalid start date.';
        }

        if (!empty($endDate) && !strtotime($endDate)) {
            $errors['end_date'] = 'Invalid end date.';
        }

        if (!empty($startDate) && !empty($endDate) && strtotime($endDate) < strtotime($startDate)) {
            $errors['end_date'] = 'End date must be after start date.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'title'       => $title,
                'description' => $description,
                'goals'       => $goals,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
            ]);
            return redirect(url('/planner/create'));
        }

        $goalsArray = is_array($goals) ? $goals : array_filter(array_map('trim', explode("\n", $goals)));

        try {
            $plan = $this->plannerService->createPlan($userId, [
                'title'       => $title,
                'description' => $description,
                'goals'       => $goalsArray,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
            ]);

            $this->session->flash('success', 'Study plan created!');
            return redirect(url('/planner/' . $plan['id']));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->session->flash('old', [
                'title'       => $title,
                'description' => $description,
                'goals'       => $goals,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
            ]);
            return redirect(url('/planner/create'));
        }
    }

    /**
     * View a plan.
     */
    public function show(Request $request, array $params = []): string
    {
        Middleware::auth();

        $planId = $params['id'] ?? '';

        if (empty($planId)) {
            $this->session->flash('error', 'Plan not found.');
            return redirect(url('/planner'));
        }

        try {
            $plan = $this->plannerService->getPlan($planId);

            return View::render('planner/show', [
                'title' => e($plan['title'] ?? 'Study Plan'),
                'plan'  => $plan,
                'csrf'  => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Plan not found.');
            return redirect(url('/planner'));
        }
    }

    /**
     * Update a plan.
     */
    public function update(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['id'] ?? '';
        $errors = [];

        if (empty($planId)) {
            $this->session->flash('error', 'Plan not found.');
            return redirect(url('/planner'));
        }

        $title       = trim($request->post('title') ?? '');
        $description = trim($request->post('description') ?? '');
        $goals       = $request->post('goals') ?? '';
        $startDate   = trim($request->post('start_date') ?? '');
        $endDate     = trim($request->post('end_date') ?? '');

        if (empty($title)) {
            $errors['title'] = 'Plan title is required.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return redirect(url('/planner/' . urlencode($planId)));
        }

        $goalsArray = is_array($goals) ? $goals : array_filter(array_map('trim', explode("\n", $goals)));

        try {
            $this->plannerService->updatePlan($planId, [
                'title'       => $title,
                'description' => $description,
                'goals'       => $goalsArray,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
            ]);

            $this->session->flash('success', 'Plan updated!');
            return redirect(url('/planner/' . urlencode($planId)));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/planner/' . urlencode($planId)));
        }
    }

    /**
     * Delete a plan.
     */
    public function delete(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['id'] ?? '';

        if (empty($planId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Plan not found.'], 400);
            }
            $this->session->flash('error', 'Plan not found.');
            return redirect(url('/planner'));
        }

        try {
            $this->plannerService->deletePlan($planId);

            if ($request->isAjax()) {
                return Response::json(['success' => true, 'message' => 'Plan deleted.']);
            }

            $this->session->flash('success', 'Plan deleted.');
            return redirect(url('/planner'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/planner'));
        }
    }

    /**
     * AJAX: Add a task to a plan.
     */
    public function addTask(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['id'] ?? '';

        if (empty($planId)) {
            return Response::json(['success' => false, 'message' => 'Plan not found.'], 400);
        }

        $title            = trim($request->post('title') ?? '');
        $description      = trim($request->post('description') ?? '');
        $subject          = trim($request->post('subject') ?? '');
        $topic            = trim($request->post('topic') ?? '');
        $dueDate          = trim($request->post('due_date') ?? '');
        $priority         = trim($request->post('priority') ?? 'medium');
        $estimatedMinutes = (int) ($request->post('estimated_minutes') ?? 30);

        if (empty($title)) {
            return Response::json(['success' => false, 'message' => 'Task title is required.'], 400);
        }

        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'medium';
        }

        $estimatedMinutes = max(1, min($estimatedMinutes, 480));

        try {
            $task = $this->plannerService->addTask($planId, [
                'title'             => $title,
                'description'       => $description,
                'subject'           => $subject,
                'topic'             => $topic,
                'due_date'          => $dueDate,
                'priority'          => $priority,
                'estimated_minutes' => $estimatedMinutes,
            ]);

            return Response::json([
                'success' => true,
                'message' => 'Task added!',
                'task'    => $task,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Update a task.
     */
    public function updateTask(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['plan_id'] ?? '';
        $taskId = $params['task_id'] ?? '';

        if (empty($planId) || empty($taskId)) {
            return Response::json(['success' => false, 'message' => 'Task not found.'], 400);
        }

        $data = [];
        if ($request->has('title')) {
            $data['title'] = trim($request->post('title'));
        }
        if ($request->has('description')) {
            $data['description'] = trim($request->post('description'));
        }
        if ($request->has('due_date')) {
            $data['due_date'] = trim($request->post('due_date'));
        }
        if ($request->has('priority')) {
            $priority = trim($request->post('priority'));
            $validPriorities = ['low', 'medium', 'high', 'urgent'];
            $data['priority'] = in_array($priority, $validPriorities, true) ? $priority : 'medium';
        }
        if ($request->has('estimated_minutes')) {
            $data['estimated_minutes'] = max(1, min((int) $request->post('estimated_minutes'), 480));
        }

        if (empty($data)) {
            return Response::json(['success' => false, 'message' => 'No data to update.'], 400);
        }

        try {
            $task = $this->plannerService->updateTask($planId, $taskId, $data);

            return Response::json([
                'success' => true,
                'message' => 'Task updated!',
                'task'    => $task,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Mark a task as complete.
     */
    public function completeTask(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['plan_id'] ?? '';
        $taskId = $params['task_id'] ?? '';

        if (empty($planId) || empty($taskId)) {
            return Response::json(['success' => false, 'message' => 'Task not found.'], 400);
        }

        try {
            $result = $this->plannerService->completeTask($planId, $taskId);

            return Response::json([
                'success' => true,
                'message' => 'Task completed! Great job!',
                'result'  => $result,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Delete a task.
     */
    public function deleteTask(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $planId = $params['plan_id'] ?? '';
        $taskId = $params['task_id'] ?? '';

        if (empty($planId) || empty($taskId)) {
            return Response::json(['success' => false, 'message' => 'Task not found.'], 400);
        }

        try {
            $this->plannerService->deleteTask($planId, $taskId);

            return Response::json([
                'success' => true,
                'message' => 'Task deleted.',
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Get today's tasks.
     */
    public function getTodaysTasks(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $tasks = $this->plannerService->getTodaysTasks($userId);

            return Response::json([
                'success' => true,
                'tasks'   => $tasks,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load tasks.',
            ], 500);
        }
    }

    /**
     * AJAX: Get upcoming tasks.
     */
    public function getUpcoming(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $days   = (int) ($request->get('days') ?? 7);
        $days   = max(1, min($days, 30));

        try {
            $tasks = $this->plannerService->getUpcomingTasks($userId, $days);

            return Response::json([
                'success' => true,
                'tasks'   => $tasks,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load upcoming tasks.',
            ], 500);
        }
    }

    /**
     * Generate an AI-powered study plan.
     */
    public function generatePlan(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');

        $subjects      = $request->post('subjects') ?? [];
        $goals         = $request->post('goals') ?? [];
        $timeAvailable = trim($request->post('time_available') ?? '');

        if (empty($subjects) || !is_array($subjects)) {
            return Response::json([
                'success' => false,
                'message' => 'Please select at least one subject.',
            ], 400);
        }

        try {
            $plan = $this->plannerService->generateStudyPlan($userId, [
                'subjects'       => $subjects,
                'goals'          => is_array($goals) ? $goals : [],
                'time_available' => $timeAvailable,
            ]);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Study plan generated!',
                    'plan'    => $plan,
                ]);
            }

            $this->session->flash('success', 'Study plan generated!');
            return redirect(url('/planner/' . ($plan['id'] ?? '')));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/planner'));
        }
    }
}
