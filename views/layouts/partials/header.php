<?php
/**
 * StudyFlow - Header Partial
 *
 * Top navigation bar with logo, search, notifications, and user menu.
 *
 * Available variables:
 *   $user   - Current user data (from session)
 *   $_view  - View instance
 */

$user = $user ?? null;
$userName   = $user['name'] ?? $user['username'] ?? 'Student';
$userAvatar = $user['avatar'] ?? null;
$streak     = $user['streak'] ?? 0;
?>

<div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">

    <!-- Left: Mobile Menu Toggle + Logo -->
    <div class="flex items-center gap-3">
        <!-- Mobile menu button -->
        <button onclick="toggleSidebar()"
                class="lg:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                aria-label="Toggle sidebar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <!-- Logo (visible on mobile when sidebar is hidden) -->
        <a href="<?= url('/dashboard') ?>" class="lg:hidden flex items-center gap-2">
            <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">StudyFlow</span>
        </a>
    </div>

    <!-- Center: Search Bar -->
    <div class="hidden sm:flex flex-1 max-w-lg mx-4 lg:mx-8">
        <form action="<?= url('/search') ?>" method="GET" class="w-full relative">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search"
                       name="q"
                       placeholder="Search subjects, notes, flashcards..."
                       class="w-full pl-10 pr-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 border border-transparent rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent focus:bg-white dark:focus:bg-gray-600 transition-all">
                <kbd class="absolute right-3 top-1/2 -translate-y-1/2 hidden lg:inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-gray-400 bg-gray-200 dark:bg-gray-600 rounded">⌘K</kbd>
            </div>
        </form>
    </div>

    <!-- Right: Actions -->
    <div class="flex items-center gap-2 sm:gap-3">

        <!-- Streak Display -->
        <?php if ($streak > 0): ?>
        <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-800" title="Current study streak">
            <span class="text-sm">🔥</span>
            <span class="text-xs font-semibold text-amber-700 dark:text-amber-300"><?= (int)$streak ?> day<?= $streak != 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>

        <!-- Dark Mode Toggle -->
        <button onclick="toggleDarkMode()"
                class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                aria-label="Toggle dark mode"
                title="Toggle dark mode">
            <span id="dark-mode-icon" class="text-lg">🌙</span>
        </button>

        <!-- Notifications -->
        <div class="relative" id="notification-menu">
            <button onclick="document.getElementById('notification-dropdown').classList.toggle('hidden')"
                    class="relative p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors"
                    aria-label="Notifications">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white dark:ring-gray-800" id="notification-dot" style="display:none;"></span>
            </button>

            <!-- Notification Dropdown -->
            <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</h3>
                </div>
                <div class="max-h-64 overflow-y-auto p-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">No new notifications</p>
                </div>
                <a href="<?= url('/notifications') ?>" class="block text-center text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 py-2.5 border-t border-gray-200 dark:border-gray-700">
                    View all notifications
                </a>
            </div>
        </div>

        <!-- User Menu -->
        <div class="relative" id="user-menu">
            <button onclick="document.getElementById('user-dropdown').classList.toggle('hidden')"
                    class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    aria-label="User menu">
                <?php if ($userAvatar): ?>
                    <img src="<?= e($userAvatar) ?>" alt="<?= e($userName) ?>" class="w-8 h-8 rounded-full object-cover ring-2 ring-primary-200 dark:ring-primary-800">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center ring-2 ring-primary-200 dark:ring-primary-800">
                        <span class="text-sm font-semibold text-primary-700 dark:text-primary-300"><?= strtoupper(mb_substr($userName, 0, 1)) ?></span>
                    </div>
                <?php endif; ?>
                <svg class="hidden sm:block w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <!-- User Dropdown -->
            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100"><?= e($userName) ?></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= e($user['email'] ?? '') ?></p>
                </div>
                <div class="py-1">
                    <a href="<?= url('/profile') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <span>👤</span> My Profile
                    </a>
                    <a href="<?= url('/settings') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <span>⚙️</span> Settings
                    </a>
                    <a href="<?= url('/progress') ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <span>📊</span> My Progress
                    </a>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 py-1">
                    <form action="<?= url('/logout') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="flex items-center gap-3 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                            <span>🚪</span> Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /Right Actions -->

</div>

<!-- Mobile Search (visible on small screens) -->
<div class="sm:hidden px-4 pb-3">
    <form action="<?= url('/search') ?>" method="GET">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="search"
                   name="q"
                   placeholder="Search..."
                   class="w-full pl-10 pr-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 border border-transparent">
        </div>
    </form>
</div>

<script>
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        var notifMenu = document.getElementById('notification-menu');
        var userMenu  = document.getElementById('user-menu');
        if (notifMenu && !notifMenu.contains(e.target)) {
            document.getElementById('notification-dropdown').classList.add('hidden');
        }
        if (userMenu && !userMenu.contains(e.target)) {
            document.getElementById('user-dropdown').classList.add('hidden');
        }
    });

    // Keyboard shortcut for search (Cmd/Ctrl + K)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('input[name="q"]');
            if (searchInput) searchInput.focus();
        }
    });
</script>
