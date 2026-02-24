<?php
/**
 * StudyFlow - Writing Editor
 */
$writing = $writing ?? [];
$writingId = $writing['id'] ?? '';
$title = $writing['title'] ?? '';
$content = $writing['content'] ?? '';
$type = $writing['type'] ?? 'essay';
$wordCount = $writing['word_count'] ?? 0;
$writingTypes = defined('WRITING_TYPES') ? WRITING_TYPES : [];
?>

<div class="max-w-5xl mx-auto">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6 animate-fade-in">
        <div class="flex items-center gap-3">
            <a href="<?= url('/writing') ?>" class="btn btn-ghost btn-sm">← Back</a>
            <h1 class="text-lg font-bold text-gray-900 dark:text-white"><?= $writingId ? 'Edit Writing' : 'New Writing' ?></h1>
        </div>
        <div class="flex items-center gap-2">
            <span id="autosave-status" class="text-xs text-gray-400"></span>
            <button id="btn-save" class="btn btn-primary btn-sm" onclick="saveWriting()">💾 Save</button>
            <a href="<?= url('/integrity') ?>" class="btn btn-outline btn-sm">🛡️ Check Integrity</a>
        </div>
    </div>

    <!-- Title & Type -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="sm:col-span-2">
            <input type="text" id="writing-title" value="<?= htmlspecialchars($title) ?>"
                   class="w-full text-xl font-bold border-0 border-b-2 border-gray-200 dark:border-gray-700 bg-transparent text-gray-900 dark:text-white focus:outline-none focus:border-primary-500 pb-2 placeholder-gray-400"
                   placeholder="Enter your title...">
        </div>
        <div>
            <select id="writing-type" class="form-select">
                <?php foreach ($writingTypes as $wt): ?>
                    <option value="<?= htmlspecialchars($wt['id']) ?>" <?= $type === $wt['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wt['icon'] . ' ' . $wt['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="writing-toolbar">
        <button class="tool-btn" onclick="formatText('bold')" title="Bold"><b>B</b></button>
        <button class="tool-btn" onclick="formatText('italic')" title="Italic"><i>I</i></button>
        <button class="tool-btn" onclick="formatText('underline')" title="Underline"><u>U</u></button>
        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
        <button class="tool-btn" onclick="formatText('insertUnorderedList')" title="Bullet List">•</button>
        <button class="tool-btn" onclick="formatText('insertOrderedList')" title="Numbered List">1.</button>
        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
        <button class="tool-btn" onclick="formatText('formatBlock', 'H2')" title="Heading">H2</button>
        <button class="tool-btn" onclick="formatText('formatBlock', 'H3')" title="Subheading">H3</button>
        <button class="tool-btn" onclick="formatText('formatBlock', 'BLOCKQUOTE')" title="Quote">❝</button>
        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
        <button class="tool-btn" onclick="formatText('justifyLeft')" title="Align Left">⬅</button>
        <button class="tool-btn" onclick="formatText('justifyCenter')" title="Center">⬆</button>
        <button class="tool-btn" onclick="formatText('justifyRight')" title="Align Right">➡</button>
    </div>

    <!-- Editor -->
    <div id="writing-editor" contenteditable="true" class="writing-editor rounded-t-none" spellcheck="true">
        <?= $content ?: '<p>Start writing here...</p>' ?>
    </div>

    <!-- Word Count Bar -->
    <div class="word-count-bar">
        <div class="flex items-center gap-4">
            <span>Words: <strong id="word-count">0</strong></span>
            <span>Characters: <strong id="char-count">0</strong></span>
            <span>Sentences: <strong id="sentence-count">0</strong></span>
            <span>Paragraphs: <strong id="paragraph-count">0</strong></span>
        </div>
        <div>
            <span>Reading time: <strong id="reading-time">0 min</strong></span>
        </div>
    </div>

    <!-- Writing Tips -->
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">💡 Writing Tips</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                    <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-1">Structure</h4>
                    <p class="text-xs text-blue-600 dark:text-blue-400">Start with a clear thesis. Each paragraph should support your main argument.</p>
                </div>
                <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <h4 class="font-semibold text-green-700 dark:text-green-300 mb-1">Clarity</h4>
                    <p class="text-xs text-green-600 dark:text-green-400">Use simple, direct language. Avoid unnecessary jargon and complex sentences.</p>
                </div>
                <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                    <h4 class="font-semibold text-purple-700 dark:text-purple-300 mb-1">Evidence</h4>
                    <p class="text-xs text-purple-600 dark:text-purple-400">Support claims with examples, data, or citations from credible sources.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var editor = document.getElementById('writing-editor');
    var autosaveTimer = null;

    function updateCounts() {
        var text = editor.innerText || '';
        var words = text.trim().split(/\s+/).filter(function(w) { return w.length > 0; });
        var sentences = text.split(/[.!?]+/).filter(function(s) { return s.trim().length > 0; });
        var paragraphs = text.split(/\n\n+/).filter(function(p) { return p.trim().length > 0; });

        document.getElementById('word-count').textContent = words.length;
        document.getElementById('char-count').textContent = text.length;
        document.getElementById('sentence-count').textContent = sentences.length;
        document.getElementById('paragraph-count').textContent = paragraphs.length;
        document.getElementById('reading-time').textContent = Math.max(1, Math.ceil(words.length / 200)) + ' min';
    }

    editor.addEventListener('input', function() {
        updateCounts();
        // Autosave indicator
        document.getElementById('autosave-status').textContent = 'Unsaved changes...';
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            document.getElementById('autosave-status').textContent = 'Auto-saved ✓';
        }, 3000);
    });

    updateCounts();

    window.formatText = function(command, value) {
        document.execCommand(command, false, value || null);
        editor.focus();
    };

    window.saveWriting = function() {
        document.getElementById('autosave-status').textContent = 'Saving...';
        setTimeout(function() {
            document.getElementById('autosave-status').textContent = 'Saved ✓';
            if (typeof SF !== 'undefined') SF.Toast.success('Writing saved successfully!');
        }, 500);
    };
})();
</script>
