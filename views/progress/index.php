<?php
/**
 * StudyFlow - Progress Overview
 */
$progressOverview = $progressOverview ?? [];
$milestones = $milestones ?? [];
$studyStreak = $studyStreak ?? ['current' => 0, 'longest' => 0];
$totalStudyTime = $totalStudyTime ?? 0;
$totalQuizzes = $totalQuizzes ?? 0;
$avgScore = $avgScore ?? 0;
?>

<div class="mb-8 animate-fade-in">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">📊 My Progress</h1>
    <p class="text-gray-500 dark:text-gray-400 mt-1">Track your learning journey and achievements.</p>
</div>

<!-- Overview Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 stagger-children">
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">⏱️</span>
        <p class="stat-value mt-2"><?= floor($totalStudyTime / 60) ?>h</p>
        <p class="stat-label">Total Study Time</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl streak-fire">🔥</span>
        <p class="stat-value mt-2"><?= (int)($studyStreak['current'] ?? 0) ?></p>
        <p class="stat-label">Day Streak</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">📝</span>
        <p class="stat-value mt-2"><?= (int)$totalQuizzes ?></p>
        <p class="stat-label">Quizzes Completed</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🏆</span>
        <p class="stat-value mt-2"><?= (int)$avgScore ?>%</p>
        <p class="stat-label">Avg Quiz Score</p>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="card">
        <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">📈 Study Time Trend</h2></div>
        <div class="card-body"><canvas id="progress-study-chart" height="200"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">🎯 Quiz Scores</h2></div>
        <div class="card-body"><canvas id="progress-quiz-chart" height="200"></canvas></div>
    </div>
</div>

<!-- Milestones -->
<div class="card mb-8">
    <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">🏅 Milestones</h2></div>
    <div class="card-body">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <?php
            $defaultMilestones = [
                ['icon' => '🌱', 'name' => 'First Session', 'desc' => 'Complete your first study session', 'unlocked' => false],
                ['icon' => '🔥', 'name' => '7-Day Streak', 'desc' => 'Study for 7 consecutive days', 'unlocked' => false],
                ['icon' => '📝', 'name' => 'Quiz Master', 'desc' => 'Score 100% on a quiz', 'unlocked' => false],
                ['icon' => '📚', 'name' => '10 Hours', 'desc' => 'Accumulate 10 hours of study', 'unlocked' => false],
                ['icon' => '🃏', 'name' => 'Card Shark', 'desc' => 'Master 50 flashcards', 'unlocked' => false],
                ['icon' => '✍️', 'name' => 'Wordsmith', 'desc' => 'Write 1000+ words', 'unlocked' => false],
                ['icon' => '🏆', 'name' => 'Level 5', 'desc' => 'Reach Level 5', 'unlocked' => false],
                ['icon' => '💎', 'name' => '30-Day Streak', 'desc' => 'Study for 30 consecutive days', 'unlocked' => false],
            ];
            foreach ($defaultMilestones as $ms):
            ?>
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-center <?= $ms['unlocked'] ? '' : 'opacity-50 grayscale' ?>">
                    <span class="text-3xl block mb-2"><?= $ms['icon'] ?></span>
                    <h3 class="text-xs font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($ms['name']) ?></h3>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5"><?= htmlspecialchars($ms['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;
    new Chart(document.getElementById('progress-study-chart'), {
        type: 'line',
        data: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets: [{ label: 'Minutes', data: [30,45,60,25,90,120,55], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('progress-quiz-chart'), {
        type: 'bar',
        data: { labels: ['Quiz 1','Quiz 2','Quiz 3','Quiz 4','Quiz 5'], datasets: [{ label: 'Score %', data: [75,80,65,90,85], backgroundColor: 'rgba(16,185,129,0.6)', borderRadius: 6 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
    });
});
</script>
