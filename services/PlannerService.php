<?php
/**
 * ============================================================================
 * PlannerService - Study Planner & Task Scheduler
 * StudyFlow - Student Self-Teaching App
 *
 * Manages study plans with tasks, due dates, priorities, and recurrence.
 * Supports daily/upcoming/overdue task views, completion statistics,
 * task reordering, and AI-suggested study plan generation.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class PlannerService
{
    private FileStorage $storage;

    private const COLLECTION_PLANS = 'study_plans';

    /** @var string[] Valid task priorities */
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** @var string[] Valid task statuses */
    private const TASK_STATUSES = ['pending', 'in_progress', 'completed', 'skipped'];

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Plan CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new study plan.
     *
     * @param string $userId
     * @param array  $data   Keys: title, description, goals, start_date, end_date
     * @return array Created plan
     * @throws InvalidArgumentException On missing title
     */
    public function createPlan(string $userId, array $data): array
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('PlannerService: Plan title is required.');
        }

        $planId = $this->storage->generateId();
        $now    = date('c');

        $plan = [
            'id'          => $planId,
            'user_id'     => $userId,
            'title'       => $title,
            'description' => trim($data['description'] ?? ''),
            'goals'       => $data['goals'] ?? [],
            'tasks'       => [],
            'task_count'  => 0,
            'completed'   => 0,
            'start_date'  => $data['start_date'] ?? date('Y-m-d'),
            'end_date'    => $data['end_date'] ?? null,
            'status'      => 'active',
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_PLANS, $planId, $plan);

        return $plan;
    }

    /**
     * Get a plan by ID.
     *
     * @param string $planId
     * @return array
     * @throws RuntimeException If not found
     */
    public function getPlan(string $planId): array
    {
        $plan = $this->storage->read(self::COLLECTION_PLANS, $planId);
        if ($plan === null) {
            throw new RuntimeException('PlannerService: Plan not found.');
        }
        return $plan;
    }

    /**
     * Get all plans for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getUserPlans(string $userId): array
    {
        $plans = $this->storage->query(self::COLLECTION_PLANS, ['user_id' => $userId]);

        // Sort: active plans first, then by date
        usort($plans, function ($a, $b) {
            $aActive = ($a['status'] ?? 'active') === 'active' ? 1 : 0;
            $bActive = ($b['status'] ?? 'active') === 'active' ? 1 : 0;
            if ($aActive !== $bActive) {
                return $bActive - $aActive;
            }
            return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0');
        });

        return $plans;
    }

    /**
     * Update a plan's metadata.
     *
     * @param string $planId
     * @param array  $data
     * @return array Updated plan
     * @throws RuntimeException If not found
     */
    public function updatePlan(string $planId, array $data): array
    {
        $plan = $this->getPlan($planId);

        $allowed = ['title', 'description', 'goals', 'start_date', 'end_date', 'status'];
        $update  = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $update[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if (isset($update['title']) && $update['title'] === '') {
            throw new InvalidArgumentException('PlannerService: Plan title cannot be empty.');
        }

        $update['updated_at'] = date('c');
        $this->storage->update(self::COLLECTION_PLANS, $planId, $update);

        return $this->getPlan($planId);
    }

    /**
     * Delete a plan and all its tasks.
     *
     * @param string $planId
     * @return bool
     * @throws RuntimeException If not found
     */
    public function deletePlan(string $planId): bool
    {
        $this->getPlan($planId); // Ensure exists
        return $this->storage->delete(self::COLLECTION_PLANS, $planId);
    }

    // -------------------------------------------------------------------------
    // Task Management
    // -------------------------------------------------------------------------

    /**
     * Add a task to a plan.
     *
     * @param string $planId
     * @param array  $data   Keys: title, description, subject, topic, due_date,
     *                       priority (low|medium|high|urgent), estimated_minutes,
     *                       recurring (bool), recurrence_pattern
     * @return array Created task
     * @throws InvalidArgumentException On missing title
     */
    public function addTask(string $planId, array $data): array
    {
        $plan = $this->getPlan($planId);

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('PlannerService: Task title is required.');
        }

        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }

        $taskId = $this->storage->generateId();
        $now    = date('c');
        $tasks  = $plan['tasks'] ?? [];

        $task = [
            'id'                 => $taskId,
            'title'              => $title,
            'description'        => trim($data['description'] ?? ''),
            'subject'            => $data['subject'] ?? '',
            'topic'              => $data['topic'] ?? '',
            'due_date'           => $data['due_date'] ?? null,
            'priority'           => $priority,
            'estimated_minutes'  => max(0, (int) ($data['estimated_minutes'] ?? 30)),
            'status'             => 'pending',
            'completed_at'       => null,
            'recurring'          => (bool) ($data['recurring'] ?? false),
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'order'              => count($tasks),
            'created_at'         => $now,
        ];

        $tasks[] = $task;

        $this->storage->update(self::COLLECTION_PLANS, $planId, [
            'tasks'      => $tasks,
            'task_count' => count($tasks),
            'updated_at' => $now,
        ]);

        return $task;
    }

    /**
     * Update a task within a plan.
     *
     * @param string $planId
     * @param string $taskId
     * @param array  $data
     * @return array Updated task
     * @throws RuntimeException If task not found
     */
    public function updateTask(string $planId, string $taskId, array $data): array
    {
        $plan  = $this->getPlan($planId);
        $tasks = $plan['tasks'] ?? [];
        $found = false;
        $updatedTask = null;

        $allowed = [
            'title', 'description', 'subject', 'topic', 'due_date',
            'priority', 'estimated_minutes', 'status', 'recurring', 'recurrence_pattern',
        ];

        foreach ($tasks as &$task) {
            if (($task['id'] ?? '') !== $taskId) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (!in_array($key, $allowed, true)) {
                    continue;
                }
                if ($key === 'priority' && !in_array($value, self::PRIORITIES, true)) {
                    continue;
                }
                if ($key === 'status' && !in_array($value, self::TASK_STATUSES, true)) {
                    continue;
                }
                $task[$key] = is_string($value) ? trim($value) : $value;
            }

            $found = true;
            $updatedTask = $task;
            break;
        }
        unset($task);

        if (!$found) {
            throw new RuntimeException('PlannerService: Task not found.');
        }

        $this->storage->update(self::COLLECTION_PLANS, $planId, [
            'tasks'      => $tasks,
            'updated_at' => date('c'),
        ]);

        return $updatedTask;
    }

    /**
     * Mark a task as completed.
     *
     * @param string $planId
     * @param string $taskId
     * @return array Updated task
     */
    public function completeTask(string $planId, string $taskId): array
    {
        $plan  = $this->getPlan($planId);
        $tasks = $plan['tasks'] ?? [];
        $found = false;
        $updatedTask = null;
        $now = date('c');

        foreach ($tasks as &$task) {
            if (($task['id'] ?? '') !== $taskId) {
                continue;
            }
            $task['status']       = 'completed';
            $task['completed_at'] = $now;
            $found = true;
            $updatedTask = $task;

            // Handle recurring tasks
            if ($task['recurring'] ?? false) {
                $newTask = $task;
                $newTask['id']           = $this->storage->generateId();
                $newTask['status']       = 'pending';
                $newTask['completed_at'] = null;
                $newTask['created_at']   = $now;
                $newTask['due_date']     = $this->nextRecurrenceDate(
                    $task['due_date'] ?? date('Y-m-d'),
                    $task['recurrence_pattern'] ?? 'daily'
                );
                $tasks[] = $newTask;
            }
            break;
        }
        unset($task);

        if (!$found) {
            throw new RuntimeException('PlannerService: Task not found.');
        }

        $completedCount = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'completed'));

        $this->storage->update(self::COLLECTION_PLANS, $planId, [
            'tasks'      => $tasks,
            'task_count' => count($tasks),
            'completed'  => $completedCount,
            'updated_at' => $now,
        ]);

        return $updatedTask;
    }

    /**
     * Delete a task from a plan.
     *
     * @param string $planId
     * @param string $taskId
     * @return bool
     * @throws RuntimeException If task not found
     */
    public function deleteTask(string $planId, string $taskId): bool
    {
        $plan     = $this->getPlan($planId);
        $tasks    = $plan['tasks'] ?? [];
        $newTasks = array_values(array_filter($tasks, fn($t) => ($t['id'] ?? '') !== $taskId));

        if (count($newTasks) === count($tasks)) {
            throw new RuntimeException('PlannerService: Task not found.');
        }

        $completedCount = count(array_filter($newTasks, fn($t) => ($t['status'] ?? '') === 'completed'));

        $this->storage->update(self::COLLECTION_PLANS, $planId, [
            'tasks'      => $newTasks,
            'task_count' => count($newTasks),
            'completed'  => $completedCount,
            'updated_at' => date('c'),
        ]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Task Views
    // -------------------------------------------------------------------------

    /**
     * Get all tasks due today across all plans.
     *
     * @param string $userId
     * @return array
     */
    public function getTodaysTasks(string $userId): array
    {
        $today = date('Y-m-d');
        $plans = $this->getUserPlans($userId);
        $tasks = [];

        foreach ($plans as $plan) {
            if (($plan['status'] ?? 'active') !== 'active') {
                continue;
            }

            foreach ($plan['tasks'] ?? [] as $task) {
                if (($task['status'] ?? '') === 'completed' || ($task['status'] ?? '') === 'skipped') {
                    continue;
                }

                $dueDate = $task['due_date'] ?? null;
                if ($dueDate !== null && date('Y-m-d', strtotime($dueDate)) === $today) {
                    $task['plan_id']    = $plan['id'] ?? $plan['_id'] ?? '';
                    $task['plan_title'] = $plan['title'] ?? '';
                    $tasks[] = $task;
                }
            }
        }

        // Sort by priority
        usort($tasks, function ($a, $b) {
            $priorityOrder = array_flip(self::PRIORITIES);
            $aPriority = $priorityOrder[$a['priority'] ?? 'medium'] ?? 1;
            $bPriority = $priorityOrder[$b['priority'] ?? 'medium'] ?? 1;
            return $bPriority - $aPriority;
        });

        return $tasks;
    }

    /**
     * Get upcoming tasks for the next N days.
     *
     * @param string $userId
     * @param int    $days
     * @return array
     */
    public function getUpcomingTasks(string $userId, int $days = 7): array
    {
        $days  = max(1, min(90, $days));
        $today = date('Y-m-d');
        $end   = date('Y-m-d', strtotime("+{$days} days"));
        $plans = $this->getUserPlans($userId);
        $tasks = [];

        foreach ($plans as $plan) {
            if (($plan['status'] ?? 'active') !== 'active') {
                continue;
            }

            foreach ($plan['tasks'] ?? [] as $task) {
                if (($task['status'] ?? '') === 'completed' || ($task['status'] ?? '') === 'skipped') {
                    continue;
                }

                $dueDate = $task['due_date'] ?? null;
                if ($dueDate !== null) {
                    $due = date('Y-m-d', strtotime($dueDate));
                    if ($due >= $today && $due <= $end) {
                        $task['plan_id']    = $plan['id'] ?? $plan['_id'] ?? '';
                        $task['plan_title'] = $plan['title'] ?? '';
                        $tasks[] = $task;
                    }
                }
            }
        }

        // Sort by due date
        usort($tasks, function ($a, $b) {
            return strtotime($a['due_date'] ?? '9999-12-31') - strtotime($b['due_date'] ?? '9999-12-31');
        });

        return $tasks;
    }

    /**
     * Get overdue tasks (past due date, not completed).
     *
     * @param string $userId
     * @return array
     */
    public function getOverdueTasks(string $userId): array
    {
        $today = date('Y-m-d');
        $plans = $this->getUserPlans($userId);
        $tasks = [];

        foreach ($plans as $plan) {
            if (($plan['status'] ?? 'active') !== 'active') {
                continue;
            }

            foreach ($plan['tasks'] ?? [] as $task) {
                if (($task['status'] ?? '') === 'completed' || ($task['status'] ?? '') === 'skipped') {
                    continue;
                }

                $dueDate = $task['due_date'] ?? null;
                if ($dueDate !== null && date('Y-m-d', strtotime($dueDate)) < $today) {
                    $task['plan_id']    = $plan['id'] ?? $plan['_id'] ?? '';
                    $task['plan_title'] = $plan['title'] ?? '';
                    $task['days_overdue'] = (int) ((strtotime($today) - strtotime($dueDate)) / 86400);
                    $tasks[] = $task;
                }
            }
        }

        // Sort by most overdue first
        usort($tasks, fn($a, $b) => ($b['days_overdue'] ?? 0) - ($a['days_overdue'] ?? 0));

        return $tasks;
    }

    // -------------------------------------------------------------------------
    // AI Study Plan & Statistics
    // -------------------------------------------------------------------------

    /**
     * Generate an AI-suggested study plan.
     *
     * Prepares structured data for AI generation rather than calling AI directly.
     *
     * @param string $userId
     * @param array  $params Keys: subjects (array), goals (array), available_hours_per_week, duration_weeks
     * @return array Suggested plan structure
     */
    public function generateStudyPlan(string $userId, array $params): array
    {
        $subjects        = $params['subjects'] ?? [];
        $goals           = $params['goals'] ?? [];
        $hoursPerWeek    = max(1, min(80, (int) ($params['available_hours_per_week'] ?? 10)));
        $durationWeeks   = max(1, min(52, (int) ($params['duration_weeks'] ?? 4)));

        if (empty($subjects)) {
            throw new InvalidArgumentException('PlannerService: At least one subject is required.');
        }

        $minutesPerWeek    = $hoursPerWeek * 60;
        $minutesPerSubject = (int) floor($minutesPerWeek / count($subjects));
        $startDate         = date('Y-m-d');

        // Build suggested tasks
        $suggestedTasks = [];
        $taskOrder      = 0;

        for ($week = 0; $week < $durationWeeks; $week++) {
            foreach ($subjects as $subject) {
                $subjectName = is_array($subject) ? ($subject['name'] ?? $subject['id'] ?? '') : $subject;

                // Distribute study sessions across the week
                $sessionsPerWeek = max(1, (int) floor($minutesPerSubject / 30));
                for ($s = 0; $s < $sessionsPerWeek && $s < 7; $s++) {
                    $dayOffset = $week * 7 + $s;
                    $dueDate   = date('Y-m-d', strtotime("+{$dayOffset} days", strtotime($startDate)));

                    $suggestedTasks[] = [
                        'title'             => "Study {$subjectName}",
                        'description'       => "Study session for {$subjectName} (Week " . ($week + 1) . ")",
                        'subject'           => $subjectName,
                        'due_date'          => $dueDate,
                        'priority'          => 'medium',
                        'estimated_minutes' => 30,
                        'order'             => $taskOrder++,
                    ];
                }
            }
        }

        return [
            'user_id'        => $userId,
            'title'          => 'Study Plan - ' . implode(', ', array_map(fn($s) => is_array($s) ? ($s['name'] ?? '') : $s, $subjects)),
            'description'    => "AI-suggested study plan for {$durationWeeks} weeks",
            'goals'          => $goals,
            'subjects'       => $subjects,
            'hours_per_week' => $hoursPerWeek,
            'duration_weeks' => $durationWeeks,
            'start_date'     => $startDate,
            'end_date'       => date('Y-m-d', strtotime("+{$durationWeeks} weeks")),
            'suggested_tasks' => $suggestedTasks,
            'total_tasks'    => count($suggestedTasks),
        ];
    }

    /**
     * Get task completion statistics for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getCompletionRate(string $userId): array
    {
        $plans         = $this->getUserPlans($userId);
        $totalTasks    = 0;
        $completedTasks = 0;
        $pendingTasks  = 0;
        $overdueTasks  = 0;
        $today         = date('Y-m-d');

        foreach ($plans as $plan) {
            foreach ($plan['tasks'] ?? [] as $task) {
                $totalTasks++;
                $status = $task['status'] ?? 'pending';

                if ($status === 'completed') {
                    $completedTasks++;
                } elseif ($status === 'pending' || $status === 'in_progress') {
                    $pendingTasks++;
                    $dueDate = $task['due_date'] ?? null;
                    if ($dueDate !== null && date('Y-m-d', strtotime($dueDate)) < $today) {
                        $overdueTasks++;
                    }
                }
            }
        }

        return [
            'user_id'          => $userId,
            'total_tasks'      => $totalTasks,
            'completed_tasks'  => $completedTasks,
            'pending_tasks'    => $pendingTasks,
            'overdue_tasks'    => $overdueTasks,
            'completion_rate'  => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            'total_plans'      => count($plans),
            'active_plans'     => count(array_filter($plans, fn($p) => ($p['status'] ?? '') === 'active')),
        ];
    }

    /**
     * Reorder tasks within a plan.
     *
     * @param string $planId
     * @param array  $order  Array of task IDs in desired order
     * @return array Updated tasks
     * @throws RuntimeException If plan not found
     */
    public function reorderTasks(string $planId, array $order): array
    {
        $plan  = $this->getPlan($planId);
        $tasks = $plan['tasks'] ?? [];

        // Build index
        $taskMap = [];
        foreach ($tasks as $task) {
            $taskMap[$task['id'] ?? ''] = $task;
        }

        // Reorder
        $reordered = [];
        $position  = 0;
        foreach ($order as $taskId) {
            if (isset($taskMap[$taskId])) {
                $task = $taskMap[$taskId];
                $task['order'] = $position++;
                $reordered[] = $task;
                unset($taskMap[$taskId]);
            }
        }

        // Append any tasks not in the order array
        foreach ($taskMap as $task) {
            $task['order'] = $position++;
            $reordered[] = $task;
        }

        $this->storage->update(self::COLLECTION_PLANS, $planId, [
            'tasks'      => $reordered,
            'updated_at' => date('c'),
        ]);

        return $reordered;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate the next recurrence date.
     */
    private function nextRecurrenceDate(string $currentDate, string $pattern): string
    {
        return match ($pattern) {
            'daily'   => date('Y-m-d', strtotime($currentDate . ' +1 day')),
            'weekly'  => date('Y-m-d', strtotime($currentDate . ' +1 week')),
            'biweekly' => date('Y-m-d', strtotime($currentDate . ' +2 weeks')),
            'monthly' => date('Y-m-d', strtotime($currentDate . ' +1 month')),
            default   => date('Y-m-d', strtotime($currentDate . ' +1 day')),
        };
    }
}
