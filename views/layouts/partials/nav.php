<?php
/**
 * StudyFlow - Mobile Bottom Navigation Partial
 *
 * Fixed bottom navigation bar visible only on small screens.
 * Provides quick access to core sections of the app.
 * Includes haptic-style press feedback and active indicator.
 *
 * Available variables:
 *   $_view  - View instance
 *   $user   - Current user data (optional)
 */

$mobileNav = [
    [
        'label'   => 'Home',
        'url'     => '/dashboard',
        'icon'    => '🏠',
        'pattern' => '/dashboard',
    ],
    [
        'label'   => 'Subjects',
        'url'     => '/subjects',
        'icon'    => '📚',
        'pattern' => '/subjects*',
    ],
    [
        'label'   => 'Study',
        'url'     => '/study',
        'icon'    => '📖',
        'pattern' => '/study*',
    ],
    [
        'label'   => 'Flashcards',
        'url'     => '/flashcards',
        'icon'    => '🃏',
        'pattern' => '/flashcards*',
    ],
    [
        'label'   => 'Progress',
        'url'     => '/progress',
        'icon'    => '📊',
        'pattern' => '/progress*',
    ],
];
?>

<!-- Mobile Bottom Nav - only visible on small screens -->
<nav class="fixed bottom-0 inset-x-0 z-50 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border-t border-gray-200 dark:border-gray-700 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] lg:hidden safe-area-bottom"
     role="navigation"
     aria-label="Mobile navigation">

    <div class="flex items-center justify-around h-16 px-1 max-w-lg mx-auto">
        <?php foreach ($mobileNav as $item): ?>
            <?php
                $isActive = isset($_view) ? $_view->isActive($item['pattern']) : false;
                $activeClass   = 'text-primary-600 dark:text-primary-400';
                $inactiveClass = 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300';
            ?>
            <a href="<?= url($item['url']) ?>"
               class="relative flex flex-col items-center justify-center gap-0.5 flex-1 py-1.5 rounded-xl transition-colors active:scale-95 <?= $isActive ? $activeClass : $inactiveClass ?>"
               aria-current="<?= $isActive ? 'page' : 'false' ?>"
               aria-label="<?= e($item['label']) ?>">

                <?php if ($isActive): ?>
                    <!-- Active indicator bar -->
                    <span class="absolute -top-px inset-x-3 h-0.5 bg-primary-500 rounded-full"></span>
                <?php endif; ?>

                <!-- Icon -->
                <span class="text-xl leading-none <?= $isActive ? 'scale-110' : '' ?> transition-transform">
                    <?= $item['icon'] ?>
                </span>

                <!-- Label -->
                <span class="text-[10px] font-semibold leading-tight mt-0.5">
                    <?= e($item['label']) ?>
                </span>

                <?php if ($isActive): ?>
                    <!-- Active dot -->
                    <span class="absolute bottom-0.5 w-1 h-1 bg-primary-500 rounded-full"></span>
                <?php endif; ?>

            </a>
        <?php endforeach; ?>
    </div>

</nav>

<!-- Spacer to prevent content from being hidden behind mobile nav -->
<div class="h-16 lg:hidden" aria-hidden="true"></div>

<style>
    /* Safe area support for notched devices (iPhone X+) */
    .safe-area-bottom {
        padding-bottom: env(safe-area-inset-bottom, 0);
    }

    /* Press feedback for mobile nav items */
    @media (hover: none) {
        .active\:scale-95:active {
            transform: scale(0.95);
        }
    }
</style>
