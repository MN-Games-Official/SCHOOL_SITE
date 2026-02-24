<?php
/**
 * StudyFlow - Planner
 */
$tasks = $tasks ?? [];
$events = $events ?? [];
$eventTypes = defined('PLANNER_EVENT_TYPES') ? PLANNER_EVENT_TYPES : [];
?>

<div class="mb-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">📅 Study Planner</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">Plan and organize your study schedule.</p>
        </div>
        <a href="<?= url('/planner/create') ?>" class="btn btn-primary">+ New Event</a>
    </div>
</div>

<!-- Calendar View -->
<div class="card mb-8 animate-fade-in-up" style="opacity:0;animation-delay:0.1s">
    <div class="card-header flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white" id="calendar-title"><?= date('F Y') ?></h2>
        <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="changeMonth(-1)">←</button>
            <button class="btn btn-ghost btn-sm" onclick="changeMonth(0)">Today</button>
            <button class="btn btn-ghost btn-sm" onclick="changeMonth(1)">→</button>
        </div>
    </div>
    <div class="card-body p-0 sm:p-4">
        <div class="planner-calendar" id="planner-calendar"></div>
    </div>
</div>

<!-- Upcoming Tasks -->
<div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.2s">
    <div class="card-header"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">📋 Upcoming Tasks</h2></div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <div class="empty-state py-8">
                <span class="empty-state-icon">📅</span>
                <p class="text-gray-500 dark:text-gray-400 text-sm">No upcoming tasks. Plan your study schedule!</p>
                <a href="<?= url('/planner/create') ?>" class="btn btn-primary btn-sm mt-3">Add Task</a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($tasks as $task): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <input type="checkbox" class="form-checkbox" <?= !empty($task['completed']) ? 'checked' : '' ?>>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($task['title'] ?? '') ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($task['date'] ?? '') ?> · <?= htmlspecialchars($task['type_name'] ?? '') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    var currentDate = new Date();
    var calendarEl = document.getElementById('planner-calendar');
    var titleEl = document.getElementById('calendar-title');
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    function renderCalendar() {
        var year = currentDate.getFullYear();
        var month = currentDate.getMonth();
        titleEl.textContent = months[month] + ' ' + year;

        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var today = new Date();

        var html = '';
        days.forEach(function(d) { html += '<div class="calendar-header-cell">' + d + '</div>'; });

        for (var i = 0; i < firstDay; i++) { html += '<div class="calendar-cell other-month"></div>'; }

        for (var d = 1; d <= daysInMonth; d++) {
            var isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
            html += '<div class="calendar-cell' + (isToday ? ' today' : '') + '"><span class="calendar-date">' + d + '</span></div>';
        }

        var totalCells = firstDay + daysInMonth;
        var remaining = 7 - (totalCells % 7);
        if (remaining < 7) { for (var i = 0; i < remaining; i++) { html += '<div class="calendar-cell other-month"></div>'; } }

        calendarEl.innerHTML = html;
    }

    window.changeMonth = function(dir) {
        if (dir === 0) { currentDate = new Date(); }
        else { currentDate.setMonth(currentDate.getMonth() + dir); }
        renderCalendar();
    };

    renderCalendar();
})();
</script>
