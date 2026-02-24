<?php
/**
 * StudyFlow - Sidebar Navigation Partial
 *
 * Sidebar with user info, XP progress, navigation links, and quick stats.
 *
 * Available variables:
 *   $user   - Current user data (from session)
 *   $_view  - View instance
 */

$user = $user ?? null;
$userName   = $user['name'] ?? $user['username'] ?? 'Student';
$userAvatar = $user['avatar'] ?? null;
$userXP     = $user['xp'] ?? 0;
$streak     = $user['streak'] ?? 0;
$todayStudy = $user['today_study_time'] ?? 0;

// Calculate level info
$levelInfo  = function_exists('calculate_xp_level') ? calculate_xp_level($userXP) : ['level' => 1, 'current_xp' => 0, 'needed_xp' => 100];
if (is_array($levelInfo) && count($levelInfo) === 3 && !isset($levelInfo['level'])) {
    [$level, $currentXP, $neededXP] = $levelInfo;
} else {
    $level     = $levelInfo['level'] ?? 1;
    $currentXP = $levelInfo['current_xp'] ?? 0;
    $neededXP  = $levelInfo['needed_xp'] ?? 100;
}
$xpPercent = $neededXP > 0 ? min(100, round(($currentXP / $neededXP) * 100)) : 0;

// Navigation items
$navItems = [
    ['label' => 'Dashboard',      'url' => '/dashboard',  'icon' => '🏠', 'pattern' => '/dashboard'],
    ['label' => 'Subjects',       'url' => '/subjects',   'icon' => '📚', 'pattern' => '/subjects*'],
    ['label' => 'Study Sessions', 'url' => '/study',      'icon' => '📖', 'pattern' => '/study*'],
    ['label' => 'Writing',        'url' => '/writing',    'icon' => '✍️', 'pattern' => '/writing*'],
    ['label' => 'Flashcards',     'url' => '/flashcards', 'icon' => '🃏', 'pattern' => '/flashcards*'],
    ['label' => 'Quizzes',        'url' => '/quiz',       'icon' => '❓', 'pattern' => '/quiz*'],
    ['label' => 'Notes',          'url' => '/notes',      'icon' => '📝', 'pattern' => '/notes*'],
    ['label' => 'AI Tutor',       'url' => '/ai',         'icon' => '🤖', 'pattern' => '/ai*'],
    ['label' => 'Progress',       'url' => '/progress',   'icon' => '📊', 'pattern' => '/progress*'],
    ['label' => 'Planner',        'url' => '/planner',    'icon' => '📅', 'pattern' => '/planner*'],
    ['label' => 'Integrity',      'url' => '/integrity',  'icon' => '🛡️', 'pattern' => '/integrity*'],
];
?>

<div class="flex flex-col h-full">

    <!-- Logo / Brand -->
    <div class="flex items-center gap-3 px-5 h-16 border-b border-gray-200 dark:border-gray-700 shrink-0">
        <a href="<?= url('/dashboard') ?>" class="flex items-center gap-3 group">
            <div class="w-9 h-9 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">StudyFlow</h1>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium -mt-0.5">SELF-TEACHING</p>
            </div>
        </a>

        <!-- Close button (mobile) -->
        <button onclick="toggleSidebar()" class="lg:hidden ml-auto p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-gray-700" aria-label="Close sidebar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- User Info + XP -->
    <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
        <div class="flex items-center gap-3">
            <?php if ($userAvatar): ?>
                <img src="<?= e($userAvatar) ?>" alt="<?= e($userName) ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-primary-200 dark:ring-primary-800">
            <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center ring-2 ring-primary-200 dark:ring-primary-800">
                    <span class="text-sm font-bold text-white"><?= strtoupper(mb_substr($userName, 0, 1)) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= e($userName) ?></p>
                <p class="text-xs text-primary-600 dark:text-primary-400 font-medium">Level <?= (int)$level ?></p>
            </div>
        </div>

        <!-- XP Progress Bar -->
        <div class="mt-3">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">XP Progress</span>
                <span class="text-[11px] font-semibold text-primary-600 dark:text-primary-400"><?= number_format($currentXP) ?> / <?= number_format($neededXP) ?></span>
            </div>
            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-primary-500 to-accent-500 rounded-full transition-all duration-500 ease-out" style="width: <?= $xpPercent ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto custom-scrollbar px-3 py-3 space-y-0.5" aria-label="Main navigation">

        <!-- Section: Learn -->
        <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            Learn
        </p>

        <?php foreach (array_slice($navItems, 0, 3) as $item): ?>
            <?php
                $isActive = isset($_view) ? $_view->isActive($item['pattern']) : false;
                $activeClasses   = 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 border-l-3 border-primary-500';
                $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200 border-l-3 border-transparent';
            ?>
            <a href="<?= url($item['url']) ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $isActive ? $activeClasses : $inactiveClasses ?>"
               aria-current="<?= $isActive ? 'page' : 'false' ?>">
                <span class="text-base w-6 text-center shrink-0"><?= $item['icon'] ?></span>
                <span class="truncate"><?= e($item['label']) ?></span>
                <?php if ($isActive): ?>
                    <span class="ml-auto w-1.5 h-1.5 bg-primary-500 rounded-full"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <!-- Section: Practice -->
        <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            Practice
        </p>

        <?php foreach (array_slice($navItems, 3, 5) as $item): ?>
            <?php
                $isActive = isset($_view) ? $_view->isActive($item['pattern']) : false;
                $activeClasses   = 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 border-l-3 border-primary-500';
                $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200 border-l-3 border-transparent';
            ?>
            <a href="<?= url($item['url']) ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $isActive ? $activeClasses : $inactiveClasses ?>"
               aria-current="<?= $isActive ? 'page' : 'false' ?>">
                <span class="text-base w-6 text-center shrink-0"><?= $item['icon'] ?></span>
                <span class="truncate"><?= e($item['label']) ?></span>
                <?php if ($isActive): ?>
                    <span class="ml-auto w-1.5 h-1.5 bg-primary-500 rounded-full"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <!-- Section: Track -->
        <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            Track
        </p>

        <?php foreach (array_slice($navItems, 8) as $item): ?>
            <?php
                $isActive = isset($_view) ? $_view->isActive($item['pattern']) : false;
                $activeClasses   = 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 border-l-3 border-primary-500';
                $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200 border-l-3 border-transparent';
            ?>
            <a href="<?= url($item['url']) ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 <?= $isActive ? $activeClasses : $inactiveClasses ?>"
               aria-current="<?= $isActive ? 'page' : 'false' ?>">
                <span class="text-base w-6 text-center shrink-0"><?= $item['icon'] ?></span>
                <span class="truncate"><?= e($item['label']) ?></span>
                <?php if ($isActive): ?>
                    <span class="ml-auto w-1.5 h-1.5 bg-primary-500 rounded-full"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

    </nav>

    <!-- Bottom Section: Quick Stats + Settings -->
    <div class="shrink-0 border-t border-gray-200 dark:border-gray-700">

        <!-- Quick Stats -->
        <div class="px-4 py-3 grid grid-cols-2 gap-2">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">Today</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">
                    <?= function_exists('format_duration') ? format_duration($todayStudy) : floor($todayStudy / 60) . 'm' ?>
                </p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">Streak</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">🔥 <?= (int)$streak ?></p>
            </div>
        </div>

        <!-- Settings Link -->
        <div class="px-3 pb-3">
            <a href="<?= url('/settings') ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200 transition-colors <?= isset($_view) && $_view->isActive('/settings*') ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200' : '' ?>">
                <span class="text-base w-6 text-center">⚙️</span>
                <span>Settings</span>
            </a>
        </div>

    </div>

</div>
