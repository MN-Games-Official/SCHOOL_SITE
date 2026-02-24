<?php
/**
 * StudyFlow - Subjects Index Page
 * Lists all available subjects organized by category
 */
$subjects = $subjects ?? [];

// Load subjects from JSON if not provided
if (empty($subjects) && file_exists(DATA_PATH . '/subjects/subjects.json')) {
    $data = json_decode(file_get_contents(DATA_PATH . '/subjects/subjects.json'), true);
    $subjects = $data['subjects'] ?? [];
}

// Group by category
$categories = [
    'core' => ['label' => '📚 Core Subjects', 'items' => []],
    'test_prep' => ['label' => '🎯 Test Preparation', 'items' => []],
    'ap' => ['label' => '🏆 Advanced Placement (AP)', 'items' => []],
];

foreach ($subjects as $subject) {
    $cat = $subject['category'] ?? 'core';
    if (isset($categories[$cat])) {
        $categories[$cat]['items'][] = $subject;
    }
}

$colorMap = [
    'blue' => 'from-blue-500 to-blue-600',
    'green' => 'from-green-500 to-green-600',
    'red' => 'from-red-500 to-red-600',
    'amber' => 'from-amber-500 to-amber-600',
    'teal' => 'from-teal-500 to-teal-600',
    'indigo' => 'from-indigo-500 to-indigo-600',
    'pink' => 'from-pink-500 to-pink-600',
    'purple' => 'from-purple-500 to-purple-600',
    'orange' => 'from-orange-500 to-orange-600',
    'cyan' => 'from-cyan-500 to-cyan-600',
    'violet' => 'from-violet-500 to-violet-600',
    'emerald' => 'from-emerald-500 to-emerald-600',
    'sky' => 'from-sky-500 to-sky-600',
    'rose' => 'from-rose-500 to-rose-600',
    'fuchsia' => 'from-fuchsia-500 to-fuchsia-600',
    'lime' => 'from-lime-500 to-lime-600',
];

$bgColorMap = [
    'blue' => 'bg-blue-50 dark:bg-blue-900/20',
    'green' => 'bg-green-50 dark:bg-green-900/20',
    'red' => 'bg-red-50 dark:bg-red-900/20',
    'amber' => 'bg-amber-50 dark:bg-amber-900/20',
    'teal' => 'bg-teal-50 dark:bg-teal-900/20',
    'indigo' => 'bg-indigo-50 dark:bg-indigo-900/20',
    'pink' => 'bg-pink-50 dark:bg-pink-900/20',
    'purple' => 'bg-purple-50 dark:bg-purple-900/20',
    'orange' => 'bg-orange-50 dark:bg-orange-900/20',
    'cyan' => 'bg-cyan-50 dark:bg-cyan-900/20',
    'violet' => 'bg-violet-50 dark:bg-violet-900/20',
    'emerald' => 'bg-emerald-50 dark:bg-emerald-900/20',
    'sky' => 'bg-sky-50 dark:bg-sky-900/20',
    'rose' => 'bg-rose-50 dark:bg-rose-900/20',
    'fuchsia' => 'bg-fuchsia-50 dark:bg-fuchsia-900/20',
    'lime' => 'bg-lime-50 dark:bg-lime-900/20',
];
?>

<!-- Page Header -->
<div class="mb-8 animate-fade-in">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">📚 Subjects</h1>
    <p class="text-gray-500 dark:text-gray-400 mt-1">Choose a subject to start studying. Includes core subjects, ACT, SAT, and AP courses.</p>
</div>

<!-- Search & Filter Bar -->
<div class="flex flex-col sm:flex-row gap-3 mb-8">
    <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" id="subject-search" placeholder="Search subjects..."
               class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500">
    </div>
    <div class="flex gap-2">
        <button class="subject-filter-btn btn btn-sm active" data-filter="all">All</button>
        <button class="subject-filter-btn btn btn-sm btn-ghost" data-filter="core">Core</button>
        <button class="subject-filter-btn btn btn-sm btn-ghost" data-filter="test_prep">Test Prep</button>
        <button class="subject-filter-btn btn btn-sm btn-ghost" data-filter="ap">AP</button>
    </div>
</div>

<!-- Subject Categories -->
<?php foreach ($categories as $catKey => $category): ?>
    <?php if (!empty($category['items'])): ?>
    <div class="mb-10 subject-category" data-category="<?= htmlspecialchars($catKey) ?>">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4"><?= $category['label'] ?></h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 stagger-children">
            <?php foreach ($category['items'] as $subject): ?>
                <?php
                $gradientClass = $colorMap[$subject['color'] ?? 'blue'] ?? 'from-gray-500 to-gray-600';
                $bgClass = $bgColorMap[$subject['color'] ?? 'blue'] ?? 'bg-gray-50 dark:bg-gray-800';
                ?>
                <a href="<?= url('/subjects/' . htmlspecialchars($subject['id'])) ?>"
                   class="subject-card card card-hover animate-fade-in-up group"
                   style="opacity:0"
                   data-subject-id="<?= htmlspecialchars($subject['id']) ?>"
                   data-subject-name="<?= htmlspecialchars($subject['name']) ?>"
                   data-category="<?= htmlspecialchars($subject['category'] ?? 'core') ?>">

                    <!-- Gradient Header -->
                    <div class="h-2 bg-gradient-to-r <?= $gradientClass ?>"></div>

                    <div class="p-5">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl <?= $bgClass ?> flex items-center justify-center text-2xl shrink-0 group-hover:scale-110 transition-transform">
                                <?= htmlspecialchars($subject['icon'] ?? '📘') ?>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors truncate">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= (int)($subject['topics_count'] ?? 0) ?> topics</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate-2">
                            <?= htmlspecialchars($subject['description'] ?? '') ?>
                        </p>

                        <!-- Progress bar (placeholder) -->
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-500">Progress</span>
                                <span class="font-medium text-primary-600 dark:text-primary-400">0%</span>
                            </div>
                            <div class="progress-bar progress-bar-sm">
                                <div class="progress-bar-fill" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search filter
    var searchInput = document.getElementById('subject-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            document.querySelectorAll('.subject-card').forEach(function(card) {
                var name = (card.dataset.subjectName || '').toLowerCase();
                card.style.display = name.includes(query) ? '' : 'none';
            });
        });
    }

    // Category filter buttons
    document.querySelectorAll('.subject-filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var filter = this.dataset.filter;
            document.querySelectorAll('.subject-filter-btn').forEach(function(b) {
                b.classList.remove('active', 'btn-primary');
                b.classList.add('btn-ghost');
            });
            this.classList.add('active', 'btn-primary');
            this.classList.remove('btn-ghost');

            document.querySelectorAll('.subject-category').forEach(function(cat) {
                if (filter === 'all' || cat.dataset.category === filter) {
                    cat.style.display = '';
                } else {
                    cat.style.display = 'none';
                }
            });
        });
    });
});
</script>
