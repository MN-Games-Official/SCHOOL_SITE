<?php
/**
 * StudyFlow - Flashcards Index
 */
$decks = $decks ?? [];
$totalCards = $totalCards ?? 0;
$dueCards = $dueCards ?? 0;
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">🃏 Flashcards</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Create and study flashcard decks with spaced repetition.</p>
        </div>
        <a href="<?= url('/flashcards/create') ?>" class="btn btn-primary">+ New Deck</a>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-8 stagger-children">
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">📦</span>
        <p class="stat-value mt-2"><?= count($decks) ?></p>
        <p class="stat-label">Decks</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🃏</span>
        <p class="stat-value mt-2"><?= (int)$totalCards ?></p>
        <p class="stat-label">Total Cards</p>
    </div>
    <div class="stat-card animate-fade-in-up" style="opacity:0">
        <span class="text-xl">🔔</span>
        <p class="stat-value mt-2"><?= (int)$dueCards ?></p>
        <p class="stat-label">Due for Review</p>
    </div>
</div>

<!-- Decks Grid -->
<?php if (empty($decks)): ?>
    <div class="card">
        <div class="empty-state py-12">
            <span class="empty-state-icon">🃏</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No flashcard decks yet</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Create your first deck to start learning with flashcards.</p>
            <a href="<?= url('/flashcards/create') ?>" class="btn btn-primary">Create Deck</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 stagger-children">
        <?php foreach ($decks as $deck): ?>
            <div class="card card-hover animate-fade-in-up" style="opacity:0">
                <div class="h-1.5 bg-gradient-to-r from-primary-500 to-accent-500"></div>
                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($deck['name'] ?? 'Untitled Deck') ?></h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= htmlspecialchars($deck['subject_name'] ?? '') ?></p>
                        </div>
                        <span class="text-2xl"><?= htmlspecialchars($deck['icon'] ?? '📚') ?></span>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate-2 mb-3">
                        <?= htmlspecialchars($deck['description'] ?? 'No description') ?>
                    </p>
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
                        <span><?= (int)($deck['card_count'] ?? 0) ?> cards</span>
                        <span><?= (int)($deck['mastered'] ?? 0) ?> mastered</span>
                    </div>
                    <div class="progress-bar progress-bar-sm mb-4">
                        <div class="progress-bar-fill" style="width: <?= (int)($deck['progress'] ?? 0) ?>%"></div>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?= url('/flashcards/study/' . htmlspecialchars($deck['id'] ?? '')) ?>" class="btn btn-primary btn-sm flex-1">Study</a>
                        <a href="<?= url('/flashcards/deck/' . htmlspecialchars($deck['id'] ?? '')) ?>" class="btn btn-ghost btn-sm">View</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
