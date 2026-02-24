<?php
/**
 * StudyFlow - View Note
 */
$note = $note ?? [];
?>
<div class="max-w-3xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/notes') ?>" class="hover:text-primary-600 transition">Notes</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium"><?= htmlspecialchars($note['title'] ?? 'Note') ?></span>
    </nav>
    <div class="card animate-fade-in">
        <div class="card-header flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($note['title'] ?? 'Untitled') ?></h1>
            <div class="flex gap-2">
                <a href="<?= url('/notes/create?edit=' . htmlspecialchars($note['id'] ?? '')) ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                <button class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ Print</button>
            </div>
        </div>
        <div class="card-body prose dark:prose-invert max-w-none">
            <?= nl2br(htmlspecialchars($note['content'] ?? 'No content.')) ?>
        </div>
        <?php if (!empty($note['tags'])): ?>
        <div class="card-footer flex flex-wrap gap-2">
            <?php foreach ((array)$note['tags'] as $tag): ?>
                <span class="badge badge-gray">#<?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
