<?php
/**
 * StudyFlow - Notes Index
 */
$notes = $notes ?? [];
$noteColors = defined('NOTE_COLORS') ? NOTE_COLORS : [];
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">📝 Notes</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Create, organize, and review your study notes.</p>
        </div>
        <a href="<?= url('/notes/create') ?>" class="btn btn-primary">+ New Note</a>
    </div>
</div>

<?php if (empty($notes)): ?>
    <div class="card"><div class="empty-state py-12">
        <span class="empty-state-icon">📝</span>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No notes yet</h3>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Start capturing your thoughts and key concepts.</p>
        <a href="<?= url('/notes/create') ?>" class="btn btn-primary">Create Note</a>
    </div></div>
<?php else: ?>
    <div class="masonry-grid">
        <?php foreach ($notes as $note): ?>
            <a href="<?= url('/notes/view/' . htmlspecialchars($note['id'] ?? '')) ?>"
               class="note-card note-<?= htmlspecialchars($note['color'] ?? 'yellow') ?> block group">
                <h3 class="font-semibold text-sm mb-2 group-hover:underline"><?= htmlspecialchars($note['title'] ?? 'Untitled') ?></h3>
                <p class="text-xs opacity-80 truncate-3"><?= htmlspecialchars($note['excerpt'] ?? '') ?></p>
                <p class="text-[10px] opacity-60 mt-3"><?= htmlspecialchars($note['date'] ?? '') ?></p>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
