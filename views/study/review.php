<?php
/**
 * StudyFlow - Study Review Page
 */
$sessions = $sessions ?? [];
?>

<div class="mb-8 animate-fade-in">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">📊 Study Review</h1>
    <p class="text-gray-500 dark:text-gray-400 mt-1">Review your past study sessions and track your progress.</p>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($sessions)): ?>
            <div class="empty-state py-12">
                <span class="empty-state-icon">📖</span>
                <p class="text-gray-500 dark:text-gray-400">No completed study sessions yet.</p>
                <a href="<?= url('/study/session') ?>" class="btn btn-primary btn-sm mt-3">Start a Session</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 dark:text-gray-400">Date</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 dark:text-gray-400">Subject</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 dark:text-gray-400">Duration</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 dark:text-gray-400">Mode</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 dark:text-gray-400">Rating</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($sessions as $session): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="py-3 px-4 text-gray-900 dark:text-white"><?= htmlspecialchars($session['date'] ?? '') ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($session['subject'] ?? 'General') ?></td>
                                <td class="py-3 px-4 font-mono"><?= htmlspecialchars($session['duration'] ?? '0m') ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($session['mode'] ?? 'Pomodoro') ?></td>
                                <td class="py-3 px-4"><?= str_repeat('⭐', (int)($session['rating'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
