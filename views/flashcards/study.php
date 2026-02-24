<?php
/**
 * StudyFlow - Flashcard Study Mode
 */
$deck = $deck ?? [];
$cards = $cards ?? [];
$deckName = $deck['name'] ?? 'Flashcard Deck';

// Sample cards if none provided
if (empty($cards)) {
    $cards = [
        ['front' => 'What is the powerhouse of the cell?', 'back' => 'The mitochondria is the powerhouse of the cell. It generates ATP through cellular respiration.'],
        ['front' => 'What is photosynthesis?', 'back' => 'Photosynthesis is the process by which green plants convert light energy into chemical energy (glucose) using CO₂ and water.'],
        ['front' => 'Define osmosis', 'back' => 'Osmosis is the movement of water molecules through a selectively permeable membrane from an area of lower solute concentration to higher solute concentration.'],
    ];
}
$cardsJson = json_encode($cards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="max-w-xl mx-auto">
    <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
        <a href="<?= url('/flashcards') ?>" class="hover:text-primary-600 transition">Flashcards</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 dark:text-white font-medium">Study</span>
    </nav>

    <div class="text-center mb-6">
        <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($deckName) ?></h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Card <span id="fc-current">1</span> of <span id="fc-total"><?= count($cards) ?></span></p>
    </div>

    <!-- Flashcard -->
    <div class="flashcard-container" id="flashcard-container" onclick="flipCard()">
        <div class="flashcard" id="flashcard">
            <div class="flashcard-front" id="fc-front">
                <p id="fc-front-text">Loading...</p>
            </div>
            <div class="flashcard-back" id="fc-back">
                <p id="fc-back-text">Loading...</p>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-3">Click the card to flip it</p>

    <!-- Progress Dots -->
    <div class="flashcard-progress mt-4" id="fc-dots"></div>

    <!-- Controls -->
    <div class="flex items-center justify-center gap-3 mt-6">
        <button class="btn btn-ghost btn-lg" onclick="prevCard()" id="btn-prev" title="Previous">
            ← Prev
        </button>
        <button class="btn btn-danger btn-lg" onclick="markCard('hard')" title="Hard">
            😣 Hard
        </button>
        <button class="btn btn-warning btn-lg" onclick="markCard('okay')" title="Okay">
            🤔 Okay
        </button>
        <button class="btn btn-success btn-lg" onclick="markCard('easy')" title="Easy">
            😊 Easy
        </button>
        <button class="btn btn-ghost btn-lg" onclick="nextCard()" id="btn-next" title="Next">
            Next →
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mt-8">
        <div class="text-center p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
            <p class="text-xl font-bold text-red-600" id="stat-hard">0</p>
            <p class="text-xs text-red-500">Hard</p>
        </div>
        <div class="text-center p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20">
            <p class="text-xl font-bold text-amber-600" id="stat-okay">0</p>
            <p class="text-xs text-amber-500">Okay</p>
        </div>
        <div class="text-center p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
            <p class="text-xl font-bold text-green-600" id="stat-easy">0</p>
            <p class="text-xs text-green-500">Easy</p>
        </div>
    </div>
</div>

<script>
(function() {
    var cards = <?= $cardsJson ?>;
    var currentIndex = 0;
    var stats = { hard: 0, okay: 0, easy: 0 };

    var flashcard = document.getElementById('flashcard');
    var frontText = document.getElementById('fc-front-text');
    var backText = document.getElementById('fc-back-text');
    var currentEl = document.getElementById('fc-current');
    var dotsEl = document.getElementById('fc-dots');

    function renderDots() {
        dotsEl.innerHTML = '';
        for (var i = 0; i < cards.length; i++) {
            var dot = document.createElement('span');
            dot.className = 'flashcard-dot' + (i === currentIndex ? ' active' : '');
            dotsEl.appendChild(dot);
        }
    }

    function showCard() {
        if (cards.length === 0) return;
        flashcard.classList.remove('flipped');
        var card = cards[currentIndex];
        frontText.textContent = card.front || '';
        backText.textContent = card.back || '';
        currentEl.textContent = currentIndex + 1;
        renderDots();
    }

    window.flipCard = function() {
        flashcard.classList.toggle('flipped');
    };

    window.nextCard = function() {
        if (currentIndex < cards.length - 1) {
            currentIndex++;
            showCard();
        }
    };

    window.prevCard = function() {
        if (currentIndex > 0) {
            currentIndex--;
            showCard();
        }
    };

    window.markCard = function(difficulty) {
        stats[difficulty]++;
        document.getElementById('stat-' + difficulty).textContent = stats[difficulty];
        if (currentIndex < cards.length - 1) {
            currentIndex++;
            showCard();
        } else {
            if (typeof SF !== 'undefined') SF.Toast.success('Deck complete! 🎉');
        }
    };

    showCard();
})();
</script>
