<?php
/**
 * StudyFlow - Writing Index
 */
$writings = $writings ?? [];
$writingTypes = defined('WRITING_TYPES') ? WRITING_TYPES : [];
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">✍️ Writing</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Write essays, reports, and creative pieces with integrity checking.</p>
        </div>
        <a href="<?= url('/writing/editor') ?>" class="btn btn-primary">✏️ New Writing</a>
    </div>
</div>

<!-- Writing Types -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8 stagger-children">
    <?php foreach ($writingTypes as $type): ?>
        <a href="<?= url('/writing/editor?type=' . urlencode($type['id'])) ?>"
           class="card card-hover p-4 text-center animate-fade-in-up group" style="opacity:0">
            <span class="text-2xl block mb-2 group-hover:scale-110 transition-transform"><?= htmlspecialchars($type['icon'] ?? '📝') ?></span>
            <h3 class="text-xs font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($type['name']) ?></h3>
            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5"><?= (int)($type['min_words'] ?? 0) ?>-<?= (int)($type['max_words'] ?? 0) ?> words</p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Recent Writings -->
<div class="card">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📄 Your Writings</h2>
    </div>
    <div class="card-body">
        <?php if (empty($writings)): ?>
            <div class="empty-state py-12">
                <span class="empty-state-icon">✍️</span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No writings yet</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Start your first writing project!</p>
                <a href="<?= url('/writing/editor') ?>" class="btn btn-primary btn-sm">Create New Writing</a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($writings as $writing): ?>
                    <a href="<?= url('/writing/editor/' . htmlspecialchars($writing['id'] ?? '')) ?>"
                       class="flex items-center gap-4 p-4 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition group">
                        <span class="text-xl"><?= htmlspecialchars($writing['icon'] ?? '📝') ?></span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition truncate">
                                <?= htmlspecialchars($writing['title'] ?? 'Untitled') ?>
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars($writing['type_name'] ?? 'Essay') ?> · <?= (int)($writing['word_count'] ?? 0) ?> words · <?= htmlspecialchars($writing['updated'] ?? '') ?>
                            </p>
                        </div>
                        <span class="badge <?= ($writing['status'] ?? '') === 'published' ? 'badge-success' : 'badge-gray' ?>">
                            <?= ucfirst(htmlspecialchars($writing['status'] ?? 'draft')) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
