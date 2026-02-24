<?php
/**
 * StudyFlow - Generate Quiz
 */
$subjects = [];
if (file_exists(DATA_PATH . '/subjects/subjects.json')) {
    $data = json_decode(file_get_contents(DATA_PATH . '/subjects/subjects.json'), true);
    $subjects = $data['subjects'] ?? [];
}
$preSelectedSubject = $_GET['subject'] ?? '';
$preSelectedTopic = $_GET['topic'] ?? '';
$quizTypes = defined('QUIZ_TYPES') ? QUIZ_TYPES : [];
$difficultyLevels = defined('DIFFICULTY_LEVELS') ? DIFFICULTY_LEVELS : [];
?>

<div class="max-w-2xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/quiz') ?>" class="hover:text-primary-600 transition">Quizzes</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Generate Quiz</span>
    </nav>

    <div class="card animate-fade-in">
        <div class="card-header">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">📝 Generate a Quiz</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure your quiz settings and test your knowledge.</p>
        </div>
        <div class="card-body">
            <form action="<?= url('/quiz/take') ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Choose a subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= htmlspecialchars($s['id']) ?>" <?= $preSelectedSubject === $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['icon'] . ' ' . $s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Number of Questions</label>
                        <select name="question_count" class="form-select">
                            <option value="5">5 questions</option>
                            <option value="10" selected>10 questions</option>
                            <option value="15">15 questions</option>
                            <option value="20">20 questions</option>
                            <option value="25">25 questions</option>
                            <option value="50">50 questions</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="mixed">Mixed</option>
                            <?php foreach ($difficultyLevels as $dl): ?>
                                <option value="<?= htmlspecialchars($dl['id']) ?>"><?= htmlspecialchars($dl['icon'] . ' ' . $dl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Question Types</label>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <?php foreach ($quizTypes as $qt): ?>
                            <label class="flex items-center gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <input type="checkbox" name="types[]" value="<?= htmlspecialchars($qt['id']) ?>" checked class="form-checkbox">
                                <span class="text-sm"><?= htmlspecialchars($qt['icon'] . ' ' . $qt['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Time Limit</label>
                        <select name="time_limit" class="form-select">
                            <option value="0">No time limit</option>
                            <option value="30">30 seconds per question</option>
                            <option value="60" selected>60 seconds per question</option>
                            <option value="120">2 minutes per question</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Show Answers</label>
                        <select name="show_answers" class="form-select">
                            <option value="after_each">After each question</option>
                            <option value="at_end" selected>At the end</option>
                            <option value="never">Never</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800">
                    <span class="text-2xl">💡</span>
                    <p class="text-sm text-primary-700 dark:text-primary-300">
                        Quizzes are generated based on the study material and topics you've covered. The more you study, the better the quizzes!
                    </p>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    🚀 Start Quiz
                </button>
            </form>
        </div>
    </div>
</div>
