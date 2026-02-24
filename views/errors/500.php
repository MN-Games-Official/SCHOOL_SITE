<?php
/**
 * StudyFlow - 500 Error Page
 */
$message = $message ?? 'Something went wrong. Please try again later.';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error | StudyFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: {
            primary: { 500: '#6366f1', 600: '#4f46e5' },
            accent: { 500: '#10b981' }
        }}}}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        @keyframes pulse-slow { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .pulse-slow { animation: pulse-slow 2s ease-in-out infinite; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
<script>if(localStorage.getItem('sf_dark_mode')==='1')document.documentElement.classList.add('dark');</script>

<div class="text-center max-w-lg mx-auto">
    <div class="pulse-slow mb-8">
        <div class="text-[10rem] leading-none font-extrabold text-red-400/80 select-none">500</div>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">Server Error</h1>
    <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto"><?= htmlspecialchars($message) ?></p>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="/" class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-md transition-all">
            Go to Dashboard
        </a>
        <button onclick="location.reload()" class="inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
            🔄 Retry
        </button>
    </div>

    <p class="mt-12 text-xs text-gray-400 dark:text-gray-500">&copy; <?= date('Y') ?> StudyFlow</p>
</div>
</body>
</html>
