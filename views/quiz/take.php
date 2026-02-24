<?php
/**
 * StudyFlow - Take Quiz
 */
$quiz = $quiz ?? [];
$questions = $questions ?? [];
$quizTitle = $quiz['title'] ?? 'Quiz';
$timeLimit = $quiz['time_limit'] ?? 60;

// Sample questions if none provided
if (empty($questions)) {
    $questions = [
        ['id' => 1, 'question' => 'What is the chemical symbol for water?', 'type' => 'multiple_choice', 'options' => ['H2O', 'CO2', 'NaCl', 'O2'], 'correct' => 0],
        ['id' => 2, 'question' => 'The Earth revolves around the Sun.', 'type' => 'true_false', 'options' => ['True', 'False'], 'correct' => 0],
        ['id' => 3, 'question' => 'What is the largest planet in our solar system?', 'type' => 'multiple_choice', 'options' => ['Mars', 'Jupiter', 'Saturn', 'Neptune'], 'correct' => 1],
    ];
}
$questionsJson = json_encode($questions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="max-w-3xl mx-auto" id="quiz-container">
    <!-- Quiz Header -->
    <div class="flex items-center justify-between mb-6 animate-fade-in">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($quizTitle) ?></h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Question <span id="q-current">1</span> of <span id="q-total"><?= count($questions) ?></span></p>
        </div>
        <div class="flex items-center gap-4">
            <?php if ($timeLimit > 0): ?>
            <div class="quiz-timer text-lg" id="quiz-timer"><?= $timeLimit ?>s</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="progress-bar mb-6">
        <div class="progress-bar-fill" id="q-progress" style="width: 0%"></div>
    </div>

    <!-- Question -->
    <div class="card mb-6" id="question-card">
        <div class="card-body p-6 sm:p-8">
            <p class="text-lg font-medium text-gray-900 dark:text-white mb-6" id="question-text">Loading...</p>

            <div class="space-y-3" id="options-container">
                <!-- Options rendered by JS -->
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="flex items-center justify-between">
        <button class="btn btn-ghost" onclick="prevQuestion()" id="btn-prev-q" disabled>← Previous</button>
        <div class="flex gap-2">
            <button class="btn btn-outline" onclick="skipQuestion()">Skip</button>
            <button class="btn btn-primary" onclick="nextQuestion()" id="btn-next-q">Next →</button>
            <button class="btn btn-success hidden" onclick="submitQuiz()" id="btn-submit-q">✓ Submit Quiz</button>
        </div>
    </div>
</div>

<!-- Results (hidden initially) -->
<div class="max-w-3xl mx-auto hidden" id="quiz-results">
    <div class="card animate-scale-in">
        <div class="card-body p-8 text-center">
            <div class="text-5xl mb-4" id="result-emoji">🎉</div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2" id="result-title">Quiz Complete!</h2>
            <p class="text-4xl font-bold text-primary-600 dark:text-primary-400 my-4" id="result-score">0%</p>
            <p class="text-gray-500 dark:text-gray-400 mb-6" id="result-details">0 correct out of 0</p>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
                    <p class="text-xl font-bold text-green-600" id="result-correct">0</p>
                    <p class="text-xs text-green-500">Correct</p>
                </div>
                <div class="p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
                    <p class="text-xl font-bold text-red-600" id="result-incorrect">0</p>
                    <p class="text-xs text-red-500">Incorrect</p>
                </div>
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50">
                    <p class="text-xl font-bold text-gray-600 dark:text-gray-300" id="result-skipped">0</p>
                    <p class="text-xs text-gray-500">Skipped</p>
                </div>
            </div>

            <div class="flex items-center justify-center gap-3">
                <a href="<?= url('/quiz/generate') ?>" class="btn btn-primary">Take Another Quiz</a>
                <a href="<?= url('/quiz') ?>" class="btn btn-ghost">View History</a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var questions = <?= $questionsJson ?>;
    var currentQ = 0;
    var answers = {};
    var timeLimit = <?= (int)$timeLimit ?>;
    var timerInterval = null;
    var timeRemaining = timeLimit;

    var questionText = document.getElementById('question-text');
    var optionsContainer = document.getElementById('options-container');
    var currentEl = document.getElementById('q-current');
    var progressBar = document.getElementById('q-progress');
    var btnPrev = document.getElementById('btn-prev-q');
    var btnNext = document.getElementById('btn-next-q');
    var btnSubmit = document.getElementById('btn-submit-q');
    var timerEl = document.getElementById('quiz-timer');

    function showQuestion() {
        var q = questions[currentQ];
        questionText.textContent = q.question;
        currentEl.textContent = currentQ + 1;
        progressBar.style.width = ((currentQ + 1) / questions.length * 100) + '%';

        optionsContainer.innerHTML = '';
        var letters = ['A', 'B', 'C', 'D', 'E', 'F'];
        (q.options || []).forEach(function(opt, i) {
            var div = document.createElement('div');
            div.className = 'quiz-option' + (answers[currentQ] === i ? ' selected' : '');
            div.innerHTML = '<span class="quiz-option-letter">' + letters[i] + '</span><span class="flex-1 text-sm text-left">' + escapeHTML(opt) + '</span>';
            div.addEventListener('click', function() {
                answers[currentQ] = i;
                document.querySelectorAll('.quiz-option').forEach(function(o) { o.classList.remove('selected'); });
                div.classList.add('selected');
            });
            optionsContainer.appendChild(div);
        });

        btnPrev.disabled = currentQ === 0;
        if (currentQ === questions.length - 1) {
            btnNext.classList.add('hidden');
            btnSubmit.classList.remove('hidden');
        } else {
            btnNext.classList.remove('hidden');
            btnSubmit.classList.add('hidden');
        }

        // Reset timer
        if (timeLimit > 0) {
            timeRemaining = timeLimit;
            updateTimer();
            clearInterval(timerInterval);
            timerInterval = setInterval(tickTimer, 1000);
        }
    }

    function tickTimer() {
        timeRemaining--;
        updateTimer();
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            nextQuestion();
        }
    }

    function updateTimer() {
        if (timerEl) {
            timerEl.textContent = timeRemaining + 's';
            timerEl.classList.remove('warning', 'danger');
            if (timeRemaining <= 10) timerEl.classList.add('danger');
            else if (timeRemaining <= 20) timerEl.classList.add('warning');
        }
    }

    function escapeHTML(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.nextQuestion = function() {
        if (currentQ < questions.length - 1) { currentQ++; showQuestion(); }
    };

    window.prevQuestion = function() {
        if (currentQ > 0) { currentQ--; showQuestion(); }
    };

    window.skipQuestion = function() {
        delete answers[currentQ];
        nextQuestion();
    };

    window.submitQuiz = function() {
        clearInterval(timerInterval);
        var correct = 0, incorrect = 0, skipped = 0;
        questions.forEach(function(q, i) {
            if (answers[i] === undefined) skipped++;
            else if (answers[i] === q.correct) correct++;
            else incorrect++;
        });
        var score = Math.round((correct / questions.length) * 100);

        document.getElementById('quiz-container').classList.add('hidden');
        var results = document.getElementById('quiz-results');
        results.classList.remove('hidden');

        document.getElementById('result-score').textContent = score + '%';
        document.getElementById('result-details').textContent = correct + ' correct out of ' + questions.length;
        document.getElementById('result-correct').textContent = correct;
        document.getElementById('result-incorrect').textContent = incorrect;
        document.getElementById('result-skipped').textContent = skipped;

        var emoji = score >= 90 ? '🏆' : score >= 70 ? '🎉' : score >= 50 ? '👍' : '📚';
        var title = score >= 90 ? 'Excellent!' : score >= 70 ? 'Great job!' : score >= 50 ? 'Good effort!' : 'Keep studying!';
        document.getElementById('result-emoji').textContent = emoji;
        document.getElementById('result-title').textContent = title;
    };

    showQuestion();
})();
</script>
