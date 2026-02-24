<?php
/**
 * StudyFlow - Quiz Results
 */
$quiz = $quiz ?? [];
$score = $quiz['score'] ?? 0;
$correct = $quiz['correct'] ?? 0;
$total = $quiz['total'] ?? 0;
$answers = $answers ?? [];
?>

<div class="max-w-3xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/quiz') ?>" class="hover:text-primary-600 transition">Quizzes</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Results</span>
    </nav>

    <!-- Score Card -->
    <div class="card mb-6 animate-scale-in">
        <div class="card-body p-8 text-center">
            <div class="text-5xl mb-4"><?= $score >= 90 ? '🏆' : ($score >= 70 ? '🎉' : ($score >= 50 ? '👍' : '📚')) ?></div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                <?= $score >= 90 ? 'Excellent!' : ($score >= 70 ? 'Great job!' : ($score >= 50 ? 'Good effort!' : 'Keep studying!')) ?>
            </h1>
            <p class="text-5xl font-bold text-primary-600 dark:text-primary-400 my-4"><?= (int)$score ?>%</p>
            <p class="text-gray-500 dark:text-gray-400"><?= (int)$correct ?> correct out of <?= (int)$total ?> questions</p>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-center gap-3 mb-8">
        <a href="<?= url('/quiz/generate') ?>" class="btn btn-primary">Take Another Quiz</a>
        <a href="<?= url('/quiz') ?>" class="btn btn-ghost">Back to Quizzes</a>
    </div>

    <!-- Answer Review -->
    <?php if (!empty($answers)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📋 Answer Review</h2>
        </div>
        <div class="card-body space-y-4">
            <?php foreach ($answers as $i => $ans): ?>
                <div class="p-4 rounded-xl border <?= !empty($ans['correct']) ? 'border-green-200 bg-green-50 dark:bg-green-900/10 dark:border-green-800' : 'border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800' ?>">
                    <p class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                        <?= ($i + 1) ?>. <?= htmlspecialchars($ans['question'] ?? '') ?>
                    </p>
                    <p class="text-xs">
                        <span class="text-gray-500">Your answer:</span>
                        <span class="font-semibold <?= !empty($ans['correct']) ? 'text-green-600' : 'text-red-600' ?>">
                            <?= htmlspecialchars($ans['user_answer'] ?? 'Skipped') ?>
                        </span>
                    </p>
                    <?php if (empty($ans['correct'])): ?>
                        <p class="text-xs mt-1">
                            <span class="text-gray-500">Correct answer:</span>
                            <span class="font-semibold text-green-600"><?= htmlspecialchars($ans['correct_answer'] ?? '') ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
