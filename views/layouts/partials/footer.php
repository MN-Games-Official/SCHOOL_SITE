<?php
/**
 * StudyFlow - Footer Partial
 *
 * Application footer with copyright, version, quick links,
 * and motivational quote section.
 *
 * Available variables:
 *   $_view - View instance
 */

$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

$quotes = [
    'The expert in anything was once a beginner.',
    'Learning never exhausts the mind. — Leonardo da Vinci',
    'Study without desire spoils the memory. — Leonardo da Vinci',
    'The more that you read, the more things you will know.',
    'Self-education is, I firmly believe, the only kind of education there is.',
    'Live as if you were to die tomorrow. Learn as if you were to live forever.',
    'Education is not preparation for life; education is life itself.',
];
$dailyQuote = $quotes[array_rand($quotes)];
?>

<footer class="border-t border-gray-200 dark:border-gray-700 bg-white/50 dark:bg-gray-800/50 mt-auto">
    <div class="px-4 sm:px-6 lg:px-8 py-5">

        <!-- Motivational Quote -->
        <div class="text-center mb-4 pb-4 border-b border-gray-100 dark:border-gray-700/50">
            <p class="text-xs italic text-gray-400 dark:text-gray-500">
                "<?= e($dailyQuote) ?>"
            </p>
        </div>

        <!-- Footer Grid -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">

            <!-- Copyright & Tagline -->
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <div class="w-5 h-5 bg-primary-100 dark:bg-primary-900/50 rounded flex items-center justify-center">
                    <svg class="w-3 h-3 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <span>&copy; <?= date('Y') ?> StudyFlow.</span>
                <span class="hidden sm:inline text-gray-300 dark:text-gray-600">|</span>
                <span class="hidden sm:inline">Your personal self-teaching platform</span>
            </div>

            <!-- Quick Links -->
            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                <a href="<?= url('/help') ?>"
                   class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    Help Center
                </a>
                <a href="<?= url('/privacy') ?>"
                   class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    Privacy
                </a>
                <a href="<?= url('/terms') ?>"
                   class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    Terms
                </a>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <span class="font-mono text-[10px] text-gray-400 dark:text-gray-500"
                      title="Application version">
                    v<?= e($appVersion) ?>
                </span>
            </div>

        </div>
    </div>
</footer>
