<?php
/**
 * StudyFlow - Study Session (Pomodoro Timer)
 */
$subject = $subject ?? '';
$topic = $topic ?? '';
$mode = $mode ?? 'pomodoro';
$duration = $duration ?? 25;
?>

<div class="max-w-2xl mx-auto">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/study') ?>" class="hover:text-primary-600 transition">Study</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Session</span>
    </nav>

    <!-- Timer Card -->
    <div class="card animate-fade-in mb-6">
        <div class="p-6 sm:p-8 text-center">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-6" id="session-title">🍅 Pomodoro Session</h1>

            <!-- Circular Timer -->
            <div class="pomodoro-timer">
                <div class="timer-circle mx-auto">
                    <svg viewBox="0 0 250 250">
                        <circle class="timer-bg" cx="125" cy="125" r="110"/>
                        <circle class="timer-progress" id="timer-progress" cx="125" cy="125" r="110"
                                stroke-dasharray="691.15"
                                stroke-dashoffset="0"/>
                    </svg>
                    <div class="timer-display">
                        <span class="timer-time" id="timer-display">25:00</span>
                        <span class="timer-label" id="timer-label">Focus Time</span>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex items-center justify-center gap-3 mt-6">
                <button id="btn-start" class="btn btn-primary btn-lg" onclick="timerStart()">
                    ▶️ Start
                </button>
                <button id="btn-pause" class="btn btn-warning btn-lg hidden" onclick="timerPause()">
                    ⏸️ Pause
                </button>
                <button id="btn-resume" class="btn btn-success btn-lg hidden" onclick="timerResume()">
                    ▶️ Resume
                </button>
                <button id="btn-reset" class="btn btn-ghost btn-lg" onclick="timerReset()">
                    🔄 Reset
                </button>
            </div>

            <!-- Session Info -->
            <div class="grid grid-cols-3 gap-4 mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Sessions</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white" id="session-count">0/4</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Time</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white" id="total-time">0:00</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Mode</p>
                    <p class="text-lg font-bold text-primary-600 dark:text-primary-400" id="current-mode">Focus</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Settings -->
    <div class="card animate-fade-in-up" style="opacity:0;animation-delay:0.2s">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">⚙️ Session Settings</h2>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Subject</label>
                    <select id="session-subject" class="form-select">
                        <option value="">General Study</option>
                        <option value="math">📐 Mathematics</option>
                        <option value="science">🔬 Science</option>
                        <option value="english">📖 English</option>
                        <option value="history">🏛️ History</option>
                        <option value="act">🎯 ACT Prep</option>
                        <option value="sat">📋 SAT Prep</option>
                        <option value="ap_biology">🧬 AP Biology</option>
                        <option value="ap_chemistry">⚗️ AP Chemistry</option>
                        <option value="ap_calculus">∫ AP Calculus</option>
                        <option value="ap_us_history">🇺🇸 AP US History</option>
                        <option value="ap_psychology">🧠 AP Psychology</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Focus Duration</label>
                    <select id="session-duration" class="form-select" onchange="timerSetDuration(this.value)">
                        <option value="15">15 minutes</option>
                        <option value="25" selected>25 minutes (Pomodoro)</option>
                        <option value="30">30 minutes</option>
                        <option value="45">45 minutes</option>
                        <option value="50">50 minutes</option>
                        <option value="60">60 minutes</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Session Notes</label>
                <textarea id="session-notes" class="form-textarea" rows="3" placeholder="What are you focusing on?"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var duration = <?= (int)$duration ?> * 60;
    var remaining = duration;
    var timer = null;
    var isRunning = false;
    var sessions = 0;
    var totalSeconds = 0;
    var isBreak = false;
    var breakDuration = 5 * 60;
    var longBreakDuration = 15 * 60;
    var circumference = 2 * Math.PI * 110;

    var display = document.getElementById('timer-display');
    var progress = document.getElementById('timer-progress');
    var label = document.getElementById('timer-label');
    var btnStart = document.getElementById('btn-start');
    var btnPause = document.getElementById('btn-pause');
    var btnResume = document.getElementById('btn-resume');

    function updateDisplay() {
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        display.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');

        var pct = 1 - (remaining / duration);
        progress.style.strokeDashoffset = circumference * (1 - pct);

        document.getElementById('session-count').textContent = sessions + '/4';
        var tm = Math.floor(totalSeconds / 60);
        var ts = totalSeconds % 60;
        document.getElementById('total-time').textContent = tm + ':' + String(ts).padStart(2, '0');
    }

    function tick() {
        if (remaining <= 0) {
            clearInterval(timer);
            timer = null;
            isRunning = false;

            if (!isBreak) {
                sessions++;
                totalSeconds += duration;
                isBreak = true;
                duration = (sessions % 4 === 0) ? longBreakDuration : breakDuration;
                remaining = duration;
                label.textContent = 'Break Time';
                document.getElementById('current-mode').textContent = 'Break';
                progress.classList.add('break');
                if (typeof SF !== 'undefined') SF.Toast.success('Session complete! Take a break. 🎉');
            } else {
                isBreak = false;
                duration = parseInt(document.getElementById('session-duration').value) * 60;
                remaining = duration;
                label.textContent = 'Focus Time';
                document.getElementById('current-mode').textContent = 'Focus';
                progress.classList.remove('break');
                if (typeof SF !== 'undefined') SF.Toast.info('Break over! Ready for another session?');
            }
            updateDisplay();
            btnStart.classList.remove('hidden');
            btnPause.classList.add('hidden');
            btnResume.classList.add('hidden');
            return;
        }
        remaining--;
        totalSeconds++;
        updateDisplay();
    }

    window.timerStart = function() {
        if (isRunning) return;
        isRunning = true;
        timer = setInterval(tick, 1000);
        btnStart.classList.add('hidden');
        btnPause.classList.remove('hidden');
        btnResume.classList.add('hidden');
    };

    window.timerPause = function() {
        if (!isRunning) return;
        isRunning = false;
        clearInterval(timer);
        timer = null;
        btnPause.classList.add('hidden');
        btnResume.classList.remove('hidden');
    };

    window.timerResume = function() { window.timerStart(); };

    window.timerReset = function() {
        clearInterval(timer);
        timer = null;
        isRunning = false;
        isBreak = false;
        duration = parseInt(document.getElementById('session-duration').value) * 60;
        remaining = duration;
        progress.classList.remove('break');
        label.textContent = 'Focus Time';
        document.getElementById('current-mode').textContent = 'Focus';
        updateDisplay();
        btnStart.classList.remove('hidden');
        btnPause.classList.add('hidden');
        btnResume.classList.add('hidden');
    };

    window.timerSetDuration = function(val) {
        if (!isRunning) {
            duration = parseInt(val) * 60;
            remaining = duration;
            updateDisplay();
        }
    };

    progress.style.strokeDasharray = circumference;
    updateDisplay();
})();
</script>
