<?php
/**
 * StudyFlow - Writing Review
 */
$writing = $writing ?? [];
$title = $writing['title'] ?? 'Untitled';
$content = $writing['content'] ?? '';
?>

<div class="max-w-4xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/writing') ?>" class="hover:text-primary-600 transition">Writing</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Review</span>
    </nav>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($title) ?></h1>
            <a href="<?= url('/writing/editor/' . htmlspecialchars($writing['id'] ?? '')) ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
        </div>
        <div class="card-body prose dark:prose-invert max-w-none">
            <?= $content ?: '<p class="text-gray-500">No content to review.</p>' ?>
        </div>
    </div>
</div>
