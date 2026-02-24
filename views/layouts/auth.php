<?php
/**
 * StudyFlow - Authentication Layout
 *
 * Simplified layout for login, register, and password reset pages.
 * Centered card design with gradient background.
 *
 * Available variables:
 *   $content       - Rendered view content
 *   $pageTitle     - Page title
 *   $styles        - Additional CSS files
 *   $scripts       - Additional JS files
 *   $inlineScripts - Inline JS blocks
 *   $inlineStyles  - Inline CSS blocks
 *   $metaTags      - Additional meta tags
 *   $_view         - View instance
 *   $_session      - Session instance
 */

$flashSuccess = isset($_session) && $_session instanceof Session ? $_session->getFlash('success') : null;
$flashError   = isset($_session) && $_session instanceof Session ? $_session->getFlash('error') : null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudyFlow - Sign in to your self-teaching platform">
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

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .auth-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 25%, #818cf8 50%, #10b981 75%, #059669 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .auth-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="h-full font-sans antialiased">

    <div class="min-h-full auth-bg flex flex-col">

        <!-- Decorative Elements -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
            <div class="absolute -top-20 -left-20 w-72 h-72 bg-white/10 rounded-full blur-3xl float-animation"></div>
            <div class="absolute top-1/3 -right-20 w-96 h-96 bg-white/5 rounded-full blur-3xl float-animation" style="animation-delay: -3s;"></div>
            <div class="absolute -bottom-20 left-1/3 w-80 h-80 bg-white/10 rounded-full blur-3xl float-animation" style="animation-delay: -5s;"></div>
        </div>

        <!-- Header / Branding -->
        <header class="relative z-10 pt-8 pb-4 text-center">
            <a href="<?= url('/') ?>" class="inline-flex items-center gap-3 group">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div class="text-left">
                    <h1 class="text-2xl font-bold text-white tracking-tight">StudyFlow</h1>
                    <p class="text-xs text-white/70 font-medium">Self-Teaching Platform</p>
                </div>
            </a>
        </header>

        <!-- Main Content -->
        <main class="relative z-10 flex-1 flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">

                <!-- Flash Messages -->
                <?php if ($flashSuccess): ?>
                <div class="mb-4 flex items-center gap-3 p-4 rounded-lg bg-accent-50/90 border border-accent-200 text-accent-800 shadow-sm" role="alert">
                    <span class="text-lg">✅</span>
                    <p class="text-sm font-medium flex-1"><?= e($flashSuccess) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-accent-500 hover:text-accent-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                <div class="mb-4 flex items-center gap-3 p-4 rounded-lg bg-red-50/90 border border-red-200 text-red-800 shadow-sm" role="alert">
                    <span class="text-lg">❌</span>
                    <p class="text-sm font-medium flex-1"><?= e($flashError) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Auth Card -->
                <div class="auth-card bg-white/95 dark:bg-gray-800/95 rounded-2xl shadow-2xl p-8 border border-white/20">
                    <?= $content ?>
                </div>

                <!-- Footer Links -->
                <div class="mt-6 text-center text-sm text-white/60">
                    <p>&copy; <?= date('Y') ?> StudyFlow. Learn at your own pace.</p>
                </div>
            </div>
        </main>

    </div>

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

    <script>
        // Restore dark mode
        if (localStorage.getItem('sf_dark_mode') === '1') {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>
