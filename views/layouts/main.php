<?php
/**
 * StudyFlow - Main Application Layout
 *
 * Primary layout with sidebar navigation, header, and footer.
 * Uses TailwindCSS via CDN with custom theme configuration.
 *
 * Available variables:
 *   $content       - Rendered view content
 *   $pageTitle     - Page title
 *   $styles        - Additional CSS files
 *   $scripts       - Additional JS files
 *   $inlineScripts - Inline JS blocks
 *   $inlineStyles  - Inline CSS blocks
 *   $metaTags      - Additional meta tags
 *   $sections      - Named content sections
 *   $_view         - View instance
 *   $_session      - Session instance
 */

$user = null;
if (isset($_session) && $_session instanceof Session) {
    $user = $_session->get('user');
}
$flashSuccess = isset($_session) && $_session instanceof Session ? $_session->getFlash('success') : null;
$flashError   = isset($_session) && $_session instanceof Session ? $_session->getFlash('error') : null;
$flashInfo    = isset($_session) && $_session instanceof Session ? $_session->getFlash('info') : null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudyFlow - Your personal self-teaching platform">
    <title><?= e($pageTitle ?? 'StudyFlow') ?></title>

    <?php if (!empty($metaTags)): ?>
        <?php foreach ($metaTags as $meta): ?>
            <meta name="<?= $meta['name'] ?>" content="<?= $meta['content'] ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <?= isset($_view) ? $_view->csrfMeta() : '' ?>

    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        },
                        accent: {
                            50:  '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            950: '#022c22',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <!-- Page-specific styles -->
    <?php if (!empty($styles)): ?>
        <?php foreach ($styles as $style): ?>
            <link rel="stylesheet" href="<?= e($style['url']) ?>"<?php
                foreach ($style['attrs'] as $k => $v) {
                    echo ' ' . e($k) . '="' . e($v) . '"';
                }
            ?>>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($inlineStyles)): ?>
        <?php foreach ($inlineStyles as $css): ?>
            <style><?= $css ?></style>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }
        .sidebar-transition { transition: width 0.3s ease, transform 0.3s ease; }
        .content-transition { transition: margin-left 0.3s ease; }
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(99, 102, 241, 0.3); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: rgba(99, 102, 241, 0.5); }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 antialiased">

    <div id="app" class="flex h-full">

        <!-- Sidebar Overlay (mobile) -->
        <div id="sidebar-overlay"
             class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm hidden lg:hidden"
             onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <aside id="sidebar"
               class="sidebar-transition fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-auto overflow-y-auto custom-scrollbar">
            <?php $_view->include('layouts/partials/sidebar', ['user' => $user]); ?>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-h-screen lg:ml-0">

            <!-- Header -->
            <header class="sticky top-0 z-20 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-700">
                <?php $_view->include('layouts/partials/header', ['user' => $user]); ?>
            </header>

            <!-- Flash Messages -->
            <?php if ($flashSuccess || $flashError || $flashInfo): ?>
            <div class="px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
                <?php if ($flashSuccess): ?>
                <div class="flash-message flex items-center gap-3 p-4 rounded-lg bg-accent-50 dark:bg-accent-900/30 border border-accent-200 dark:border-accent-800 text-accent-800 dark:text-accent-200" role="alert">
                    <span class="text-lg">✅</span>
                    <p class="text-sm font-medium flex-1"><?= e($flashSuccess) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-accent-500 hover:text-accent-700 dark:hover:text-accent-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                <div class="flash-message flex items-center gap-3 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200" role="alert">
                    <span class="text-lg">❌</span>
                    <p class="text-sm font-medium flex-1"><?= e($flashError) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 dark:hover:text-red-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($flashInfo): ?>
                <div class="flash-message flex items-center gap-3 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200" role="alert">
                    <span class="text-lg">ℹ️</span>
                    <p class="text-sm font-medium flex-1"><?= e($flashInfo) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <?= $content ?>
            </main>

            <!-- Footer -->
            <?php $_view->include('layouts/partials/footer'); ?>

        </div><!-- /Main Content Wrapper -->

    </div><!-- /app -->

    <!-- Mobile Bottom Navigation -->
    <?php $_view->include('layouts/partials/nav'); ?>

    <!-- Core App JS -->
    <script src="<?= asset('js/app.js') ?>" defer></script>

    <!-- Page-specific scripts -->
    <?php if (!empty($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= e($script['url']) ?>"<?php
                foreach ($script['attrs'] as $k => $v) {
                    if ($v === true) { echo ' ' . e($k); }
                    else { echo ' ' . e($k) . '="' . e($v) . '"'; }
                }
            ?>></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($inlineScripts)): ?>
        <?php foreach ($inlineScripts as $js): ?>
            <script><?= $js ?></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Sidebar Toggle & Dark Mode JS -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden', !overlay.classList.contains('hidden'));
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('sf_dark_mode', isDark ? '1' : '0');
            document.getElementById('dark-mode-icon').textContent = isDark ? '☀️' : '🌙';
        }

        // Restore dark mode preference
        (function() {
            if (localStorage.getItem('sf_dark_mode') === '1') {
                document.documentElement.classList.add('dark');
            }
        })();

        // Auto-dismiss flash messages after 5 seconds
        document.querySelectorAll('.flash-message').forEach(function(el) {
            setTimeout(function() {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            }, 5000);
        });
    </script>
</body>
</html>
