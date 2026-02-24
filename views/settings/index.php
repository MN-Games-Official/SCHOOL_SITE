<?php
/**
 * StudyFlow - Settings Page
 */
$user = isset($_session) && $_session instanceof Session ? $_session->get('user') : [];
$prefs = $user['preferences'] ?? (defined('DEFAULT_PREFERENCES') ? DEFAULT_PREFERENCES : []);
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-8 animate-fade-in">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">⚙️ Settings</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Manage your account and preferences.</p>
    </div>

    <div data-tabs>
        <!-- Tab Navigation -->
        <div class="tabs mb-6">
            <button class="tab active" data-tab="profile">👤 Profile</button>
            <button class="tab" data-tab="preferences">🎨 Preferences</button>
            <button class="tab" data-tab="study">📖 Study Settings</button>
            <button class="tab" data-tab="notifications">🔔 Notifications</button>
            <button class="tab" data-tab="security">🔒 Security</button>
        </div>

        <!-- Profile Tab -->
        <div class="tab-panel active" data-tab-panel="profile">
            <div class="card">
                <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2></div>
                <div class="card-body">
                    <form class="space-y-4">
                        <?= csrf_field() ?>
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-3xl">
                                <?= strtoupper(mb_substr($user['name'] ?? 'S', 0, 1)) ?>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm">Change Avatar</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><label class="form-label">Full Name</label><input type="text" class="form-input" value="<?= htmlspecialchars($user['name'] ?? '') ?>"></div>
                            <div><label class="form-label">Email</label><input type="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
                        </div>
                        <div><label class="form-label">Bio</label><textarea class="form-textarea" rows="3" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea></div>
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Preferences Tab -->
        <div class="tab-panel" data-tab-panel="preferences">
            <div class="card">
                <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Appearance & Preferences</h2></div>
                <div class="card-body space-y-6">
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Dark Mode</p><p class="text-xs text-gray-500">Switch between light and dark theme</p></div>
                        <label class="toggle"><input type="checkbox" id="pref-dark" onchange="toggleDarkMode()"><span class="toggle-slider"></span></label>
                    </div>
                    <div class="divider"></div>
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Sound Effects</p><p class="text-xs text-gray-500">Play sounds for timers and achievements</p></div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="divider"></div>
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Auto-save</p><p class="text-xs text-gray-500">Automatically save notes and writing</p></div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="divider"></div>
                    <div>
                        <label class="form-label">Font Size</label>
                        <select class="form-select w-48"><option>Small</option><option selected>Medium</option><option>Large</option><option>Extra Large</option></select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Study Settings Tab -->
        <div class="tab-panel" data-tab-panel="study">
            <div class="card">
                <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Study Preferences</h2></div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label class="form-label">Daily Goal (minutes)</label><input type="number" class="form-input" value="<?= (int)($prefs['daily_goal_minutes'] ?? 60) ?>" min="10" max="480"></div>
                        <div><label class="form-label">Pomodoro Duration (min)</label><input type="number" class="form-input" value="<?= (int)($prefs['pomodoro_duration'] ?? 25) ?>" min="5" max="60"></div>
                        <div><label class="form-label">Break Duration (min)</label><input type="number" class="form-input" value="<?= (int)($prefs['break_duration'] ?? 5) ?>" min="1" max="30"></div>
                        <div><label class="form-label">Long Break (min)</label><input type="number" class="form-input" value="15" min="5" max="60"></div>
                    </div>
                    <button class="btn btn-primary">Save Study Settings</button>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="tab-panel" data-tab-panel="notifications">
            <div class="card">
                <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notification Settings</h2></div>
                <div class="card-body space-y-4">
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Study Reminders</p><p class="text-xs text-gray-500">Get reminded to study daily</p></div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="divider"></div>
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Streak Alerts</p><p class="text-xs text-gray-500">Warn before losing your streak</p></div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="divider"></div>
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium text-gray-900 dark:text-white text-sm">Achievement Notifications</p><p class="text-xs text-gray-500">Celebrate milestones</p></div>
                        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-panel" data-tab-panel="security">
            <div class="card">
                <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Security</h2></div>
                <div class="card-body space-y-4">
                    <form class="space-y-4">
                        <?= csrf_field() ?>
                        <div><label class="form-label">Current Password</label><input type="password" class="form-input" placeholder="Enter current password"></div>
                        <div><label class="form-label">New Password</label><input type="password" class="form-input" placeholder="Enter new password"></div>
                        <div><label class="form-label">Confirm New Password</label><input type="password" class="form-input" placeholder="Confirm new password"></div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                    <div class="divider"></div>
                    <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <h3 class="text-sm font-semibold text-red-700 dark:text-red-300 mb-1">Danger Zone</h3>
                        <p class="text-xs text-red-600 dark:text-red-400 mb-3">Permanently delete your account and all data.</p>
                        <button class="btn btn-danger btn-sm">Delete Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
