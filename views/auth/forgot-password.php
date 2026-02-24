<?php
/**
 * StudyFlow - Forgot Password Page
 */
$errors = isset($_session) && $_session instanceof Session ? $_session->getFlash('errors') : [];
$old = isset($_session) && $_session instanceof Session ? $_session->getFlash('old') : [];
?>

<div class="text-center mb-6">
    <div class="text-4xl mb-3">🔑</div>
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Reset your password</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Enter your email and we'll send you a reset link</p>
</div>

<form action="<?= url('/forgot-password') ?>" method="POST" class="space-y-5">
    <?= csrf_field() ?>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   class="w-full pl-10 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                   placeholder="you@example.com" required autocomplete="email" autofocus>
        </div>
        <?php if (!empty($errors['email'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['email']) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
        Send Reset Link
    </button>
</form>

<div class="text-center mt-6">
    <a href="<?= url('/login') ?>" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">← Back to sign in</a>
</div>
