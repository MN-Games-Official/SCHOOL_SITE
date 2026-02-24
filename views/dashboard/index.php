<?php
/**
 * StudyFlow - Dashboard Page
 * Main layout view
 */
$user = isset($_session) && $_session instanceof Session ? $_session->get('user') : null;
$userName = $user['name'] ?? 'Student';
$userXP = $user['xp'] ?? 0;
$streak = $user['streak'] ?? 0;

$recentActivity = $recentActivity ?? [];
$studyStreak = $studyStreak ?? ['current' => 0, 'longest' => 0];
$studyTime = $studyTime ?? ['total' => 0];
$goals = $goals ?? [];
$goalProgress = $goalProgress ?? [];
$progressOverview = $progressOverview ?? [];
$milestones = $milestones ?? [];
$todaysTasks = $todaysTasks ?? [];
$upcomingTasks = $upcomingTasks ?? [];
$quizStats = $quizStats ?? [];
$subjectStats = $subjectStats ?? [];
?>

<!-- Dashboard Header -->
<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($userName) ?>! 👋
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Here's your learning overview for today.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= url('/study/session') ?>" class="btn btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Start Study Session
            </a>
        </div>
    </div>
</div>

<!-- Quick Stats Row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 stagger-children">
    <!-- Study Time Today -->
    <div class="card card-hover p-5 animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-2xl">⏱️</span>
            <span class="badge badge-info">Today</span>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white" id="stat-study-time">
            <?= isset($studyTime['total']) ? floor($studyTime['total'] / 60) . 'h ' . ($studyTime['total'] % 60) . 'm' : '0m' ?>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Study Time</p>
    </div>

    <!-- Streak -->
    <div class="card card-hover p-5 animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-2xl streak-fire">🔥</span>
            <span class="badge badge-warning">Streak</span>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white">
            <?= (int)($studyStreak['current'] ?? $streak) ?> <span class="text-sm font-normal text-gray-400">days</span>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Best: <?= (int)($studyStreak['longest'] ?? 0) ?> days</p>
    </div>

    <!-- Quizzes Completed -->
    <div class="card card-hover p-5 animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-2xl">📝</span>
            <span class="badge badge-success">Quizzes</span>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white">
            <?= (int)($quizStats['total_completed'] ?? 0) ?>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Avg: <?= (int)($quizStats['average_score'] ?? 0) ?>%</p>
    </div>

    <!-- XP & Level -->
    <div class="card card-hover p-5 animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between mb-3">
            <span class="text-2xl">⭐</span>
            <span class="badge badge-primary">XP</span>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= number_format($userXP) ?></p>
        <div class="xp-bar mt-2">
            <div class="xp-bar-fill" style="width: 45%"></div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

    <!-- Today's Schedule / Tasks -->
    <div class="lg:col-span-2 card animate-fade-in-up" style="opacity:0;animation-delay:0.2s">
        <div class="card-header flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📅 Today's Tasks</h2>
            <a href="<?= url('/planner') ?>" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">View Planner →</a>
        </div>
        <div class="card-body">
            <?php if (empty($todaysTasks)): ?>
                <div class="empty-state py-8">
                    <span class="empty-state-icon">📋</span>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No tasks scheduled for today</p>
                    <a href="<?= url('/planner/create') ?>" class="btn btn-outline btn-sm mt-3">+ Add Task</a>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($todaysTasks, 0, 5) as $task): ?>
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <input type="checkbox" class="form-checkbox" <?= !empty($task['completed']) ? 'checked' : '' ?>>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate <?= !empty($task['completed']) ? 'line-through opacity-50' : '' ?>">
                                    <?= htmlspecialchars($task['title'] ?? 'Untitled Task') ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?= htmlspecialchars($task['time'] ?? '') ?>
                                    <?php if (!empty($task['subject'])): ?>
                                        · <?= htmlspecialchars($task['subject']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="text-lg"><?= htmlspecialchars($task['icon'] ?? '📌') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.3s">
        <div class="card-header">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">⚡ Quick Actions</h2>
        </div>
        <div class="card-body space-y-2">
            <a href="<?= url('/study/session') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">🍅</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Pomodoro Session</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">25 min focused study</p>
                </div>
            </a>
            <a href="<?= url('/flashcards') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">🃏</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Review Flashcards</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Spaced repetition</p>
                </div>
            </a>
            <a href="<?= url('/quiz/generate') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">❓</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Take a Quiz</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Test your knowledge</p>
                </div>
            </a>
            <a href="<?= url('/notes/create') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">📝</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">New Note</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Capture your thoughts</p>
                </div>
            </a>
            <a href="<?= url('/writing/editor') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">✍️</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Writing Editor</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Essays & reports</p>
                </div>
            </a>
            <a href="<?= url('/ai') ?>" class="flex items-center gap-3 p-3 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition group">
                <span class="text-xl group-hover:scale-110 transition-transform">🤖</span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">AI Tutor</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Ask anything</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Subject Progress & Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Subject Progress -->
    <div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.4s">
        <div class="card-header flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📚 Subject Progress</h2>
            <a href="<?= url('/subjects') ?>" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">All Subjects →</a>
        </div>
        <div class="card-body">
            <?php if (empty($subjectStats)): ?>
                <div class="empty-state py-6">
                    <span class="empty-state-icon">📊</span>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Start studying to see your progress</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach (array_slice($subjectStats, 0, 5) as $stat): ?>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <?= htmlspecialchars($stat['icon'] ?? '📘') ?> <?= htmlspecialchars($stat['name'] ?? '') ?>
                                </span>
                                <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">
                                    <?= (int)($stat['progress'] ?? 0) ?>%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: <?= (int)($stat['progress'] ?? 0) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.5s">
        <div class="card-header flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">🕐 Recent Activity</h2>
            <a href="<?= url('/progress') ?>" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">View All →</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentActivity)): ?>
                <div class="empty-state py-6">
                    <span class="empty-state-icon">📋</span>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No recent activity</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($recentActivity, 0, 6) as $activity): ?>
                        <div class="flex items-start gap-3">
                            <span class="text-lg mt-0.5 shrink-0"><?= htmlspecialchars($activity['icon'] ?? '📌') ?></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 dark:text-gray-100"><?= htmlspecialchars($activity['description'] ?? '') ?></p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    <?= htmlspecialchars($activity['time'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Study Heatmap -->
<div class="card animate-fade-in-up mb-8" style="opacity:0;animation-delay:0.6s">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📈 Study Activity Heatmap</h2>
    </div>
    <div class="card-body">
        <div class="heatmap" id="study-heatmap">
            <!-- Heatmap cells rendered by JS -->
        </div>
        <div class="flex items-center justify-end gap-2 mt-3 text-xs text-gray-400">
            <span>Less</span>
            <span class="heatmap-cell inline-block"></span>
            <span class="heatmap-cell level-1 inline-block"></span>
            <span class="heatmap-cell level-2 inline-block"></span>
            <span class="heatmap-cell level-3 inline-block"></span>
            <span class="heatmap-cell level-4 inline-block"></span>
            <span>More</span>
        </div>
    </div>
</div>

<!-- Weekly Chart Placeholder -->
<div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.7s">
    <div class="card-header flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📊 Weekly Study Overview</h2>
        <div class="tabs-pills inline-flex">
            <button class="tab-pill active" data-chart-period="week">Week</button>
            <button class="tab-pill" data-chart-period="month">Month</button>
        </div>
    </div>
    <div class="card-body">
        <canvas id="weekly-chart" height="200"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate heatmap
    var heatmap = document.getElementById('study-heatmap');
    if (heatmap) {
        for (var w = 0; w < 26; w++) {
            var week = document.createElement('div');
            week.className = 'heatmap-week';
            for (var d = 0; d < 7; d++) {
                var cell = document.createElement('div');
                var level = Math.random() > 0.6 ? Math.floor(Math.random() * 4) + 1 : 0;
                cell.className = 'heatmap-cell' + (level > 0 ? ' level-' + level : '');
                cell.title = 'Week ' + (w+1) + ', Day ' + (d+1);
                week.appendChild(cell);
            }
            heatmap.appendChild(week);
        }
    }

    // Weekly chart
    if (typeof Chart !== 'undefined' && document.getElementById('weekly-chart')) {
        var ctx = document.getElementById('weekly-chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Study Minutes',
                    data: [45, 60, 30, 90, 55, 120, 80],
                    backgroundColor: 'rgba(99, 102, 241, 0.6)',
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 1,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
