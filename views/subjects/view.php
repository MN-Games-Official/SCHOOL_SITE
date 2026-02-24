<?php
/**
 * StudyFlow - Subject Detail View
 * Shows topics for a specific subject
 */
$subject = $subject ?? [];
$topics = $topics ?? [];
$subjectName = $subject['name'] ?? 'Subject';
$subjectIcon = $subject['icon'] ?? '📘';
$subjectColor = $subject['color'] ?? 'blue';
$subjectDescription = $subject['description'] ?? '';
$subjectCategory = $subject['category'] ?? 'core';

$categoryLabels = ['core' => 'Core Subject', 'test_prep' => 'Test Preparation', 'ap' => 'Advanced Placement'];
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
    <a href="<?= url('/subjects') ?>" class="hover:text-primary-600 dark:hover:text-primary-400 transition">Subjects</a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 dark:text-white font-medium"><?= htmlspecialchars($subjectName) ?></span>
</nav>

<!-- Subject Header -->
<div class="card mb-8 overflow-hidden animate-fade-in">
    <div class="bg-gradient-to-r from-primary-500 to-accent-500 p-6 sm:p-8">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center text-3xl">
                <?= htmlspecialchars($subjectIcon) ?>
            </div>
            <div>
                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-white/20 text-white mb-1">
                    <?= htmlspecialchars($categoryLabels[$subjectCategory] ?? 'Subject') ?>
                </span>
                <h1 class="text-2xl sm:text-3xl font-bold text-white"><?= htmlspecialchars($subjectName) ?></h1>
                <p class="text-white/80 text-sm mt-1"><?= htmlspecialchars($subjectDescription) ?></p>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-200 dark:divide-gray-700">
        <div class="p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= count($topics) ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Topics</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white">0%</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Complete</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white">0</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Quizzes Taken</p>
        </div>
        <div class="p-4 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-white">0h</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Study Time</p>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="<?= url('/study/session?subject=' . urlencode($subject['id'] ?? '')) ?>" class="btn btn-primary btn-sm">🍅 Start Study Session</a>
    <a href="<?= url('/quiz/generate?subject=' . urlencode($subject['id'] ?? '')) ?>" class="btn btn-outline btn-sm">❓ Generate Quiz</a>
    <a href="<?= url('/flashcards/create?subject=' . urlencode($subject['id'] ?? '')) ?>" class="btn btn-outline btn-sm">🃏 Create Flashcards</a>
    <a href="<?= url('/notes/create?subject=' . urlencode($subject['id'] ?? '')) ?>" class="btn btn-ghost btn-sm">📝 Take Notes</a>
</div>

<!-- Topics List -->
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Topics</h2>
    <div class="flex items-center gap-2">
        <button class="btn btn-ghost btn-sm topic-view-btn active" data-view="list">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        </button>
        <button class="btn btn-ghost btn-sm topic-view-btn" data-view="grid">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        </button>
    </div>
</div>

<?php if (empty($topics)): ?>
    <div class="empty-state py-12">
        <span class="empty-state-icon">📋</span>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No topics yet</h3>
        <p class="text-gray-500 dark:text-gray-400 text-sm">Topics will be added soon for this subject.</p>
    </div>
<?php else: ?>
    <!-- List View -->
    <div id="topics-list" class="space-y-3 stagger-children">
        <?php foreach ($topics as $i => $topic): ?>
            <?php
            $diffColors = [
                'beginner' => 'badge-success',
                'elementary' => 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300',
                'intermediate' => 'badge-warning',
                'advanced' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                'expert' => 'badge-danger',
            ];
            $diffClass = $diffColors[$topic['difficulty'] ?? 'intermediate'] ?? 'badge-info';
            ?>
            <a href="<?= url('/subjects/' . htmlspecialchars($subject['id'] ?? '') . '/topics/' . htmlspecialchars($topic['id'])) ?>"
               class="card card-hover p-4 flex items-center gap-4 animate-fade-in-up group" style="opacity:0">
                <!-- Topic Number -->
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-sm font-bold text-primary-700 dark:text-primary-300 shrink-0">
                    <?= $i + 1 ?>
                </div>

                <!-- Topic Info -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        <?= htmlspecialchars($topic['name']) ?>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= htmlspecialchars($topic['description'] ?? '') ?></p>
                </div>

                <!-- Difficulty -->
                <span class="badge <?= $diffClass ?> text-xs hidden sm:inline-flex">
                    <?= ucfirst(htmlspecialchars($topic['difficulty'] ?? 'intermediate')) ?>
                </span>

                <!-- Estimated Time -->
                <span class="text-xs text-gray-400 hidden md:inline">~<?= (int)($topic['estimated_hours'] ?? 0) ?>h</span>

                <!-- Progress -->
                <div class="w-20 hidden lg:block">
                    <div class="progress-bar progress-bar-sm">
                        <div class="progress-bar-fill" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Arrow -->
                <svg class="w-5 h-5 text-gray-300 dark:text-gray-600 group-hover:text-primary-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
