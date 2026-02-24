<?php
/**
 * StudyFlow - Register Page
 * Auth layout view
 */
$errors = isset($_session) && $_session instanceof Session ? $_session->getFlash('errors') : [];
$old = isset($_session) && $_session instanceof Session ? $_session->getFlash('old') : [];
?>

<div class="text-center mb-6">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Create your account</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Start your self-teaching journey today</p>
</div>

<?php if (!empty($errors['register'])): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
    <?= htmlspecialchars($errors['register']) ?>
</div>
<?php endif; ?>

<form action="<?= url('/register') ?>" method="POST" class="space-y-4" id="register-form">
    <?= csrf_field() ?>

    <!-- Name -->
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </span>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                   class="w-full pl-10 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition <?= !empty($errors['name']) ? 'border-red-500' : '' ?>"
                   placeholder="John Doe" required autocomplete="name" autofocus>
        </div>
        <?php if (!empty($errors['name'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['name']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Email -->
    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   class="w-full pl-10 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition <?= !empty($errors['email']) ? 'border-red-500' : '' ?>"
                   placeholder="you@example.com" required autocomplete="email">
        </div>
        <?php if (!empty($errors['email'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['email']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Password -->
    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </span>
            <input type="password" id="password" name="password"
                   class="w-full pl-10 pr-12 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                   placeholder="Min 8 chars, 1 upper, 1 lower, 1 number" required autocomplete="new-password">
            <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 text-lg" aria-label="Toggle password visibility">👁️</button>
        </div>
        <?php if (!empty($errors['password'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['password']) ?></p>
        <?php endif; ?>
        <!-- Password strength meter -->
        <div class="mt-2">
            <div class="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div id="password-strength-bar" class="h-full rounded-full transition-all duration-300" style="width:0%"></div>
            </div>
            <p id="password-strength-text" class="text-xs text-gray-400 mt-1"></p>
        </div>
    </div>

    <!-- Confirm Password -->
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
        <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </span>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="w-full pl-10 pr-4 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition"
                   placeholder="Re-enter your password" required autocomplete="new-password">
        </div>
        <?php if (!empty($errors['password_confirmation'])): ?>
            <p class="mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['password_confirmation']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Terms -->
    <div class="flex items-start">
        <input type="checkbox" id="agree_terms" name="agree_terms" class="w-4 h-4 mt-0.5 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700" required>
        <label for="agree_terms" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
            I agree to the <a href="#" class="text-primary-600 dark:text-primary-400 hover:underline">Terms of Service</a> and <a href="#" class="text-primary-600 dark:text-primary-400 hover:underline">Privacy Policy</a>
        </label>
    </div>
    <?php if (!empty($errors['agree_terms'])): ?>
        <p class="text-xs text-red-500 -mt-2"><?= htmlspecialchars($errors['agree_terms']) ?></p>
    <?php endif; ?>

    <!-- Submit -->
    <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 px-4 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:scale-[0.98]">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Create Account
    </button>
</form>

<!-- Divider -->
<div class="relative my-6">
    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-700"></div></div>
    <div class="relative flex justify-center text-xs"><span class="px-2 bg-white dark:bg-gray-800 text-gray-400">or</span></div>
</div>

<!-- Login Link -->
<div class="text-center">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Already have an account?
        <a href="<?= url('/login') ?>" class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">Sign in</a>
    </p>
</div>

<script>
// Password strength meter
document.getElementById('password').addEventListener('input', function() {
    var pw = this.value;
    var strength = 0;
    var bar = document.getElementById('password-strength-bar');
    var text = document.getElementById('password-strength-text');
    if (pw.length >= 8) strength++;
    if (pw.length >= 12) strength++;
    if (/[A-Z]/.test(pw)) strength++;
    if (/[a-z]/.test(pw)) strength++;
    if (/[0-9]/.test(pw)) strength++;
    if (/[^A-Za-z0-9]/.test(pw)) strength++;

    var pct = Math.min(100, (strength / 6) * 100);
    var colors = ['bg-red-500','bg-red-500','bg-orange-500','bg-yellow-500','bg-lime-500','bg-green-500','bg-green-600'];
    var labels = ['','Very Weak','Weak','Fair','Good','Strong','Very Strong'];
    bar.style.width = pct + '%';
    bar.className = 'h-full rounded-full transition-all duration-300 ' + (colors[strength] || 'bg-gray-300');
    text.textContent = labels[strength] || '';
});

document.getElementById('register-form').addEventListener('submit', function(e) {
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Creating account...';
});
</script>
