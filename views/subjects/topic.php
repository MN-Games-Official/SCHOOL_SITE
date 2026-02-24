<?php
/**
 * StudyFlow - Topic Detail View
 */
$subject = $subject ?? [];
$topic = $topic ?? [];
$topicName = $topic['name'] ?? 'Topic';
$subjectName = $subject['name'] ?? 'Subject';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6 flex-wrap">
    <a href="<?= url('/subjects') ?>" class="hover:text-primary-600 transition">Subjects</a>
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <a href="<?= url('/subjects/' . htmlspecialchars($subject['id'] ?? '')) ?>" class="hover:text-primary-600 transition"><?= htmlspecialchars($subjectName) ?></a>
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 dark:text-white font-medium"><?= htmlspecialchars($topicName) ?></span>
</nav>

<!-- Topic Header -->
<div class="card mb-8 animate-fade-in">
    <div class="p-6 sm:p-8">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-2xl shrink-0">
                <?= htmlspecialchars($subject['icon'] ?? '📘') ?>
            </div>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($topicName) ?></h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($topic['description'] ?? '') ?></p>
                <div class="flex flex-wrap items-center gap-3 mt-3">
                    <span class="badge badge-primary"><?= htmlspecialchars($subjectName) ?></span>
                    <span class="badge badge-warning"><?= ucfirst(htmlspecialchars($topic['difficulty'] ?? 'intermediate')) ?></span>
                    <span class="text-xs text-gray-400">~<?= (int)($topic['estimated_hours'] ?? 0) ?> hours</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 stagger-children">
    <a href="<?= url('/study/session?subject=' . urlencode($subject['id'] ?? '') . '&topic=' . urlencode($topic['id'] ?? '')) ?>"
       class="card card-hover p-5 text-center animate-fade-in-up group" style="opacity:0">
        <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">🍅</span>
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Study Session</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Pomodoro focused study</p>
    </a>

    <a href="<?= url('/quiz/generate?subject=' . urlencode($subject['id'] ?? '') . '&topic=' . urlencode($topic['id'] ?? '')) ?>"
       class="card card-hover p-5 text-center animate-fade-in-up group" style="opacity:0">
        <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">❓</span>
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Take Quiz</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Test your knowledge</p>
    </a>

    <a href="<?= url('/flashcards/create?subject=' . urlencode($subject['id'] ?? '') . '&topic=' . urlencode($topic['id'] ?? '')) ?>"
       class="card card-hover p-5 text-center animate-fade-in-up group" style="opacity:0">
        <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">🃏</span>
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Flashcards</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Create & review cards</p>
    </a>

    <a href="<?= url('/notes/create?subject=' . urlencode($subject['id'] ?? '') . '&topic=' . urlencode($topic['id'] ?? '')) ?>"
       class="card card-hover p-5 text-center animate-fade-in-up group" style="opacity:0">
        <span class="text-3xl block mb-2 group-hover:scale-110 transition-transform">📝</span>
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Take Notes</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Capture key concepts</p>
    </a>
</div>

<!-- Topic Content Placeholder -->
<div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.3s">
    <div class="card-header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📖 Study Material</h2>
    </div>
    <div class="card-body">
        <div class="empty-state py-8">
            <span class="empty-state-icon">📚</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Ready to Study</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">Choose an activity above to start learning this topic, or ask the AI tutor for help.</p>
            <a href="<?= url('/ai') ?>" class="btn btn-primary">🤖 Ask AI Tutor about <?= htmlspecialchars($topicName) ?></a>
        </div>
    </div>
</div>
