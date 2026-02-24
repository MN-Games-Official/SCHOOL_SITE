<?php
/**
 * StudyFlow - Flashcard Deck View
 */
$deck = $deck ?? [];
$cards = $cards ?? [];
$deckName = $deck['name'] ?? 'Flashcard Deck';
?>

<div class="max-w-3xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/flashcards') ?>" class="hover:text-primary-600 transition">Flashcards</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium"><?= htmlspecialchars($deckName) ?></span>
    </nav>

    <div class="card mb-6 animate-fade-in">
        <div class="card-header flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($deckName) ?></h1>
                <p class="text-sm text-gray-500 dark:text-gray-400"><?= count($cards) ?> cards</p>
            </div>
            <a href="<?= url('/flashcards/study/' . htmlspecialchars($deck['id'] ?? '')) ?>" class="btn btn-primary">📖 Study</a>
        </div>
        <div class="card-body">
            <?php if (empty($cards)): ?>
                <div class="empty-state py-8">
                    <span class="empty-state-icon">🃏</span>
                    <p class="text-gray-500 dark:text-gray-400">No cards in this deck yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($cards as $i => $card): ?>
                        <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <span class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-xs font-bold text-primary-700 dark:text-primary-300 shrink-0 mt-0.5"><?= $i + 1 ?></span>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase mb-1">Front</p>
                                    <p class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($card['front'] ?? '') ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase mb-1">Back</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($card['back'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
