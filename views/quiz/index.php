<?php
/**
 * StudyFlow - Quiz Index
 */
$quizzes = $quizzes ?? [];
$quizStats = $quizStats ?? [];
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">❓ Quizzes</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Test your knowledge with auto-generated quizzes.</p>
        </div>
        <a href="<?= url('/quiz/generate') ?>" class="btn btn-primary">📝 Generate Quiz</a>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 stagger-children">
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">📊</span>
        <p class="stat-value mt-2"><?= (int)($quizStats['total_completed'] ?? 0) ?></p>
        <p class="stat-label">Quizzes Taken</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🏆</span>
        <p class="stat-value mt-2"><?= (int)($quizStats['average_score'] ?? 0) ?>%</p>
        <p class="stat-label">Average Score</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">⭐</span>
        <p class="stat-value mt-2"><?= (int)($quizStats['best_score'] ?? 0) ?>%</p>
        <p class="stat-label">Best Score</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🎯</span>
        <p class="stat-value mt-2"><?= (int)($quizStats['perfect_scores'] ?? 0) ?></p>
        <p class="stat-label">Perfect Scores</p>
    </div>
</div>

<!-- Recent Quizzes -->
<div class="card">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📋 Quiz History</h2>
    </div>
    <div class="card-body">
        <?php if (empty($quizzes)): ?>
            <div class="empty-state py-12">
                <span class="empty-state-icon">❓</span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No quizzes taken yet</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Generate a quiz to test your knowledge!</p>
                <a href="<?= url('/quiz/generate') ?>" class="btn btn-primary">Generate Quiz</a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($quizzes as $quiz): ?>
                    <a href="<?= url('/quiz/results/' . htmlspecialchars($quiz['id'] ?? '')) ?>"
                       class="flex items-center gap-4 p-4 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition group">
                        <div class="w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-xl shrink-0">
                            <?= htmlspecialchars($quiz['icon'] ?? '📝') ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition truncate">
                                <?= htmlspecialchars($quiz['title'] ?? 'Quiz') ?>
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($quiz['subject_name'] ?? '') ?> · <?= (int)($quiz['question_count'] ?? 0) ?> questions · <?= htmlspecialchars($quiz['date'] ?? '') ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold <?= (int)($quiz['score'] ?? 0) >= 70 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= (int)($quiz['score'] ?? 0) ?>%
                            </p>
                            <p class="text-[10px] text-gray-400"><?= (int)($quiz['correct'] ?? 0) ?>/<?= (int)($quiz['total'] ?? 0) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
