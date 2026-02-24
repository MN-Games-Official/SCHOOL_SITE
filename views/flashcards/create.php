<?php
/**
 * StudyFlow - Create Flashcard Deck
 */
$subjects = [];
if (file_exists(DATA_PATH . '/subjects/subjects.json')) {
    $data = json_decode(file_get_contents(DATA_PATH . '/subjects/subjects.json'), true);
    $subjects = $data['subjects'] ?? [];
}
?>

<div class="max-w-2xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/flashcards') ?>" class="hover:text-primary-600 transition">Flashcards</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Create Deck</span>
    </nav>

    <div class="card animate-fade-in">
        <div class="card-header">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">🃏 Create Flashcard Deck</h1>
        </div>
        <div class="card-body">
            <form action="<?= url('/flashcards/save') ?>" method="POST" class="space-y-5" id="create-deck-form">
                <?= csrf_field() ?>

                <div>
                    <label class="form-label">Deck Name</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g., Biology Chapter 5" required>
                </div>

                <div>
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select">
                        <option value="">Select a subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['icon'] . ' ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="2" placeholder="Brief description of this deck..."></textarea>
                </div>

                <!-- Cards -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="form-label mb-0">Cards</label>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addCard()">+ Add Card</button>
                    </div>

                    <div id="cards-container" class="space-y-4">
                        <div class="card-entry p-4 border border-gray-200 dark:border-gray-700 rounded-xl">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Card 1</span>
                                <button type="button" onclick="removeCard(this)" class="text-red-400 hover:text-red-600 text-sm">✕</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Front (Question/Term)</label>
                                    <textarea name="cards[0][front]" class="form-textarea text-sm" rows="2" placeholder="Question or term" required></textarea>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Back (Answer/Definition)</label>
                                    <textarea name="cards[0][back]" class="form-textarea text-sm" rows="2" placeholder="Answer or definition" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="<?= url('/flashcards') ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Deck</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var cardIndex = 1;
function addCard() {
    var container = document.getElementById('cards-container');
    var div = document.createElement('div');
    div.className = 'card-entry p-4 border border-gray-200 dark:border-gray-700 rounded-xl animate-fade-in';
    div.innerHTML = '<div class="flex items-center justify-between mb-2"><span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Card ' + (cardIndex + 1) + '</span><button type="button" onclick="removeCard(this)" class="text-red-400 hover:text-red-600 text-sm">✕</button></div><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label class="text-xs text-gray-500 dark:text-gray-400">Front (Question/Term)</label><textarea name="cards[' + cardIndex + '][front]" class="form-textarea text-sm" rows="2" placeholder="Question or term" required></textarea></div><div><label class="text-xs text-gray-500 dark:text-gray-400">Back (Answer/Definition)</label><textarea name="cards[' + cardIndex + '][back]" class="form-textarea text-sm" rows="2" placeholder="Answer or definition" required></textarea></div></div>';
    container.appendChild(div);
    cardIndex++;
}

function removeCard(btn) {
    var entries = document.querySelectorAll('.card-entry');
    if (entries.length > 1) {
        btn.closest('.card-entry').remove();
    }
}
</script>
