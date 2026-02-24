<?php
/**
 * StudyFlow - Study Sessions Index
 */
$recentSessions = $recentSessions ?? [];
$todayTime = $todayTime ?? 0;
$weekTime = $weekTime ?? 0;
$totalSessions = $totalSessions ?? 0;
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">📖 Study Sessions</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Focus your learning with timed Pomodoro study sessions.</p>
        </div>
        <a href="<?= url('/study/session') ?>" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
            New Session
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 stagger-children">
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between">
            <span class="text-xl">⏱️</span>
            <span class="badge badge-info">Today</span>
        </div>
        <p class="stat-value mt-2"><?= floor($todayTime / 60) ?>h <?= $todayTime % 60 ?>m</p>
        <p class="stat-label">Study Time</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <div class="flex items-center justify-between">
            <span class="text-xl">📅</span>
            <span class="badge badge-success">Week</span>
        </div>
        <p class="stat-value mt-2"><?= floor($weekTime / 60) ?>h <?= $weekTime % 60 ?>m</p>
        <p class="stat-label">This Week</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">📊</span>
        <p class="stat-value mt-2"><?= (int)$totalSessions ?></p>
        <p class="stat-label">Total Sessions</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🎯</span>
        <p class="stat-value mt-2">25m</p>
        <p class="stat-label">Avg Duration</p>
    </div>
</div>

<!-- Study Mode Selection -->
<div class="card mb-8 animate-fade-in-up" style="opacity:0;animation-delay:0.3s">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">🎓 Study Modes</h2>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php
            $modes = [
                ['icon' => '🍅', 'name' => 'Pomodoro', 'desc' => '25 min focused work', 'time' => '25:00'],
                ['icon' => '📖', 'name' => 'Reading', 'desc' => 'Read study materials', 'time' => '30:00'],
                ['icon' => '✍️', 'name' => 'Practice', 'desc' => 'Work through problems', 'time' => '45:00'],
                ['icon' => '🔄', 'name' => 'Review', 'desc' => 'Review past material', 'time' => '20:00'],
                ['icon' => '🃏', 'name' => 'Flashcards', 'desc' => 'Spaced repetition', 'time' => '15:00'],
                ['icon' => '📝', 'name' => 'Quiz Prep', 'desc' => 'Prepare for quizzes', 'time' => '30:00'],
                ['icon' => '📒', 'name' => 'Note Taking', 'desc' => 'Create study notes', 'time' => '25:00'],
                ['icon' => '🧠', 'name' => 'Deep Focus', 'desc' => 'Extended deep work', 'time' => '50:00'],
            ];
            foreach ($modes as $mode):
            ?>
                <a href="<?= url('/study/session?mode=' . urlencode(strtolower($mode['name']))) ?>"
                   class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all group text-center">
                    <span class="text-2xl block mb-2 group-hover:scale-110 transition-transform"><?= $mode['icon'] ?></span>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white"><?= $mode['name'] ?></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= $mode['desc'] ?></p>
                    <p class="text-xs font-mono text-primary-600 dark:text-primary-400 mt-1"><?= $mode['time'] ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Sessions -->
<div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.4s">
    <div class="card-header flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">🕐 Recent Sessions</h2>
        <a href="<?= url('/study/review') ?>" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">View All →</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentSessions)): ?>
            <div class="empty-state py-8">
                <span class="empty-state-icon">🍅</span>
                <p class="text-gray-500 dark:text-gray-400 text-sm">No study sessions yet. Start your first session!</p>
                <a href="<?= url('/study/session') ?>" class="btn btn-primary btn-sm mt-3">Start Studying</a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentSessions as $session): ?>
                    <div class="flex items-center gap-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xl"><?= htmlspecialchars($session['icon'] ?? '📖') ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($session['subject'] ?? 'General Study') ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($session['date'] ?? '') ?></p>
                        </div>
                        <span class="text-sm font-mono text-gray-600 dark:text-gray-300"><?= htmlspecialchars($session['duration'] ?? '0m') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
