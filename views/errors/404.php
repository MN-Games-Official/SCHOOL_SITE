<?php
/**
 * StudyFlow - 404 Error Page
 */
$message = $message ?? 'The page you are looking for does not exist.';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | StudyFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: {
            primary: { 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
            accent: { 500: '#10b981' }
        }}}}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        .float { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 flex items-center justify-center p-4">
<script>if(localStorage.getItem('sf_dark_mode')==='1')document.documentElement.classList.add('dark');</script>

<div class="text-center max-w-lg mx-auto">
    <!-- Illustration -->
    <div class="float mb-8">
        <div class="text-[10rem] leading-none font-extrabold bg-gradient-to-r from-primary-500 to-accent-500 bg-clip-text text-transparent select-none">404</div>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-3">Page Not Found</h1>
    <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto"><?= htmlspecialchars($message) ?></p>

    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="/" class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-md transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Go to Dashboard
        </a>
        <button onclick="history.back()" class="inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
            ← Go Back
        </button>
    </div>

    <p class="mt-12 text-xs text-gray-400 dark:text-gray-500">&copy; <?= date('Y') ?> StudyFlow</p>
</div>
</body>
</html>
