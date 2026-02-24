<?php
/**
 * StudyFlow - Login Page
 * Auth layout view
 */
$errors = isset($_session) && $_session instanceof Session ? $_session->getFlash('errors') : [];
$old = isset($_session) && $_session instanceof Session ? $_session->getFlash('old') : [];
?>

<div class="text-center mb-6">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome back</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Sign in to continue your learning journey</p>
</div>

<?php if (!empty($errors['login'])): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
    <?= htmlspecialchars($errors['login']) ?>
</div>
<?php endif; ?>

<form action="<?= url('/login') ?>" method="POST" class="space-y-5" id="login-form">
    <?= csrf_field() ?>

    <!-- Email -->
    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   class="w-full pl-10 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition <?= !empty($errors['email']) ? 'border-red-500' : '' ?>"
                   placeholder="you@example.com" required autocomplete="email" autofocus>
        </div>
        <?php if (!empty($errors['email'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['email']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Password -->
    <div>
        <div class="flex items-center justify-between mb-1">
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
            <a href="<?= url('/forgot-password') ?>" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Forgot password?</a>
        </div>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </span>
            <input type="password" id="password" name="password"
                   class="w-full pl-10 pr-12 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                   placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-lg" aria-label="Toggle password visibility">👁️</button>
        </div>
        <?php if (!empty($errors['password'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['password']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Remember Me -->
    <div class="flex items-center">
        <input type="checkbox" id="remember" name="remember" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700">
        <label for="remember" class="ml-2 text-sm text-gray-600 dark:text-gray-400">Remember me for 30 days</label>
    </div>

    <!-- Submit -->
    <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:scale-[0.98]">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        Sign In
    </button>
</form>

<!-- Divider -->
<div class="relative my-6">
    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
    <div class="relative flex justify-center text-xs"><span class="px-2 bg-white dark:bg-gray-800 text-gray-400">or</span></div>
</div>

<!-- Register Link -->
<div class="text-center">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Don't have an account?
        <a href="<?= url('/register') ?>" class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">Create one free</a>
    </p>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function(e) {
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Signing in...';
});
</script>
