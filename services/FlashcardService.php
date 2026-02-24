<?php
/**
 * ============================================================================
 * FlashcardService - Flashcard System with Spaced Repetition
 * StudyFlow - Student Self-Teaching App
 *
 * Full flashcard management with the SM-2 (SuperMemo 2) spaced repetition
 * algorithm. Supports deck CRUD, card management, study sessions, import/
 * export, and AI-generated card prompts.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class FlashcardService
{
    private FileStorage $storage;

    private const COLLECTION_DECKS = 'flashcard_decks';

    /** SM-2 default easiness factor */
    private const DEFAULT_EASINESS = 2.5;

    /** Minimum easiness factor */
    private const MIN_EASINESS = 1.3;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Deck CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new flashcard deck.
     *
     * @param string $userId
     * @param array  $data   Keys: name, description, subject, tags
     * @return array Created deck
     * @throws InvalidArgumentException On missing name
     */
    public function createDeck(string $userId, array $data): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('FlashcardService: Deck name is required.');
        }

        $deckId = $this->storage->generateId();
        $now    = date('c');

        $deck = [
            'id'          => $deckId,
            'user_id'     => $userId,
            'name'        => $name,
            'description' => trim($data['description'] ?? ''),
            'subject'     => $data['subject'] ?? '',
            'tags'        => $data['tags'] ?? [],
            'cards'       => [],
            'card_count'  => 0,
            'mastered'    => 0,
            'learning'    => 0,
            'new'         => 0,
            'last_studied' => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_DECKS, $deckId, $deck);

        return $deck;
    }

    /**
     * Get a deck with all its cards.
     *
     * @param string $deckId
     * @return array
     * @throws RuntimeException If not found
     */
    public function getDeck(string $deckId): array
    {
        $deck = $this->storage->read(self::COLLECTION_DECKS, $deckId);
        if ($deck === null) {
            throw new RuntimeException('FlashcardService: Deck not found.');
        }
        return $deck;
    }

    /**
     * Get all decks for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getUserDecks(string $userId): array
    {
        $decks = $this->storage->query(self::COLLECTION_DECKS, ['user_id' => $userId]);

        usort($decks, fn($a, $b) => strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0'));

        return array_map(function (array $d) {
            return [
                'id'           => $d['id'] ?? $d['_id'] ?? '',
                'name'         => $d['name'] ?? '',
                'description'  => $d['description'] ?? '',
                'subject'      => $d['subject'] ?? '',
                'tags'         => $d['tags'] ?? [],
                'card_count'   => $d['card_count'] ?? count($d['cards'] ?? []),
                'mastered'     => $d['mastered'] ?? 0,
                'learning'     => $d['learning'] ?? 0,
                'new'          => $d['new'] ?? 0,
                'last_studied' => $d['last_studied'] ?? null,
                'created_at'   => $d['created_at'] ?? null,
                'updated_at'   => $d['updated_at'] ?? null,
            ];
        }, $decks);
    }

    /**
     * Update a deck's metadata.
     *
     * @param string $deckId
     * @param array  $data   Keys: name, description, subject, tags
     * @return array Updated deck
     * @throws RuntimeException If not found
     */
    public function updateDeck(string $deckId, array $data): array
    {
        $deck = $this->getDeck($deckId);

        $allowed = ['name', 'description', 'subject', 'tags'];
        $update  = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $update[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if (isset($update['name']) && $update['name'] === '') {
            throw new InvalidArgumentException('FlashcardService: Deck name cannot be empty.');
        }

        $update['updated_at'] = date('c');
        $this->storage->update(self::COLLECTION_DECKS, $deckId, $update);

        return $this->getDeck($deckId);
    }

    /**
     * Delete a deck and all its cards.
     *
     * @param string $deckId
     * @return bool
     * @throws RuntimeException If not found
     */
    public function deleteDeck(string $deckId): bool
    {
        $deck = $this->getDeck($deckId);
        return $this->storage->delete(self::COLLECTION_DECKS, $deckId);
    }

    // -------------------------------------------------------------------------
    // Card Management
    // -------------------------------------------------------------------------

    /**
     * Add a card to a deck.
     *
     * @param string $deckId
     * @param array  $data   Keys: front, back, hints, tags
     * @return array The created card
     * @throws InvalidArgumentException On missing front/back
     */
    public function addCard(string $deckId, array $data): array
    {
        $deck = $this->getDeck($deckId);

        $front = trim($data['front'] ?? '');
        $back  = trim($data['back'] ?? '');

        if ($front === '' || $back === '') {
            throw new InvalidArgumentException('FlashcardService: Card front and back are required.');
        }

        $cardId = $this->storage->generateId();
        $now    = date('c');

        $card = [
            'id'              => $cardId,
            'front'           => $front,
            'back'            => $back,
            'hints'           => $data['hints'] ?? [],
            'tags'            => $data['tags'] ?? [],
            'easiness'        => self::DEFAULT_EASINESS,
            'interval'        => 0,
            'repetitions'     => 0,
            'next_review'     => $now,
            'last_reviewed'   => null,
            'status'          => 'new',
            'correct_count'   => 0,
            'incorrect_count' => 0,
            'created_at'      => $now,
        ];

        $cards   = $deck['cards'] ?? [];
        $cards[] = $card;

        $this->storage->update(self::COLLECTION_DECKS, $deckId, [
            'cards'      => $cards,
            'card_count' => count($cards),
            'new'        => count(array_filter($cards, fn($c) => ($c['status'] ?? '') === 'new')),
            'updated_at' => $now,
        ]);

        return $card;
    }

    /**
     * Update an existing card.
     *
     * @param string $deckId
     * @param string $cardId
     * @param array  $data   Keys: front, back, hints, tags
     * @return array Updated card
     * @throws RuntimeException If card not found
     */
    public function updateCard(string $deckId, string $cardId, array $data): array
    {
        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];
        $found = false;
        $updatedCard = null;

        foreach ($cards as &$card) {
            if (($card['id'] ?? '') === $cardId) {
                if (isset($data['front'])) $card['front'] = trim($data['front']);
                if (isset($data['back']))  $card['back']  = trim($data['back']);
                if (isset($data['hints'])) $card['hints'] = $data['hints'];
                if (isset($data['tags']))  $card['tags']  = $data['tags'];
                $found = true;
                $updatedCard = $card;
                break;
            }
        }
        unset($card);

        if (!$found) {
            throw new RuntimeException('FlashcardService: Card not found.');
        }

        $this->storage->update(self::COLLECTION_DECKS, $deckId, [
            'cards'      => $cards,
            'updated_at' => date('c'),
        ]);

        return $updatedCard;
    }

    /**
     * Delete a card from a deck.
     *
     * @param string $deckId
     * @param string $cardId
     * @return bool
     * @throws RuntimeException If card not found
     */
    public function deleteCard(string $deckId, string $cardId): bool
    {
        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];
        $newCards = array_values(array_filter($cards, fn($c) => ($c['id'] ?? '') !== $cardId));

        if (count($newCards) === count($cards)) {
            throw new RuntimeException('FlashcardService: Card not found.');
        }

        $this->storage->update(self::COLLECTION_DECKS, $deckId, [
            'cards'      => $newCards,
            'card_count' => count($newCards),
            'updated_at' => date('c'),
        ]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Spaced Repetition Study
    // -------------------------------------------------------------------------

    /**
     * Get cards due for review in a deck (spaced repetition).
     *
     * Returns cards whose next_review date is at or before now, plus any new cards
     * up to a configurable limit.
     *
     * @param string $deckId
     * @param int    $maxNew Maximum new cards to introduce per session
     * @return array Cards to study
     */
    public function getStudyCards(string $deckId, int $maxNew = 10): array
    {
        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];
        $now   = time();

        $dueCards = [];
        $newCards = [];

        foreach ($cards as $card) {
            $status = $card['status'] ?? 'new';

            if ($status === 'new') {
                $newCards[] = $card;
            } elseif ($status !== 'mastered' || strtotime($card['next_review'] ?? '') <= $now) {
                // Due for review
                if (strtotime($card['next_review'] ?? '') <= $now) {
                    $dueCards[] = $card;
                }
            }
        }

        // Limit new cards
        $newCards = array_slice($newCards, 0, $maxNew);

        // Combine: due cards first, then new cards
        $studyCards = array_merge($dueCards, $newCards);

        // Shuffle to prevent order bias
        shuffle($studyCards);

        return $studyCards;
    }

    /**
     * Record a study result for a card and calculate next review.
     *
     * @param string $deckId
     * @param string $cardId
     * @param string $result 'correct', 'incorrect', 'partial'
     * @return array Updated card with next review date
     * @throws InvalidArgumentException On invalid result
     */
    public function recordCardResult(string $deckId, string $cardId, string $result): array
    {
        $validResults = ['correct', 'incorrect', 'partial'];
        if (!in_array($result, $validResults, true)) {
            throw new InvalidArgumentException("FlashcardService: Result must be one of: " . implode(', ', $validResults));
        }

        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];
        $updatedCard = null;

        foreach ($cards as &$card) {
            if (($card['id'] ?? '') !== $cardId) {
                continue;
            }

            // Map result to SM-2 quality (0-5)
            $quality = match ($result) {
                'correct'   => 5,
                'partial'   => 3,
                'incorrect' => 1,
            };

            $card = $this->calculateNextReview($card, $quality);

            // Update counters
            if ($result === 'correct') {
                $card['correct_count'] = ($card['correct_count'] ?? 0) + 1;
            } else {
                $card['incorrect_count'] = ($card['incorrect_count'] ?? 0) + 1;
            }

            $card['last_reviewed'] = date('c');
            $updatedCard = $card;
            break;
        }
        unset($card);

        if ($updatedCard === null) {
            throw new RuntimeException('FlashcardService: Card not found.');
        }

        // Recalculate deck statistics
        $mastered = 0;
        $learning = 0;
        $new      = 0;
        foreach ($cards as $c) {
            $status = $c['status'] ?? 'new';
            if ($status === 'mastered') $mastered++;
            elseif ($status === 'learning') $learning++;
            else $new++;
        }

        $this->storage->update(self::COLLECTION_DECKS, $deckId, [
            'cards'        => $cards,
            'mastered'     => $mastered,
            'learning'     => $learning,
            'new'          => $new,
            'last_studied' => date('c'),
            'updated_at'   => date('c'),
        ]);

        return $updatedCard;
    }

    /**
     * SM-2 spaced repetition algorithm.
     *
     * Calculates the next review interval and updated easiness factor.
     *
     * @param array $card    Current card data
     * @param int   $quality Response quality (0-5): 5=perfect, 4=correct with hesitation,
     *                       3=correct with difficulty, 2=incorrect but close,
     *                       1=incorrect, 0=complete blackout
     * @return array Updated card with new interval, easiness, and next_review
     */
    public function calculateNextReview(array $card, int $quality): array
    {
        $quality = max(0, min(5, $quality));

        $easiness    = $card['easiness'] ?? self::DEFAULT_EASINESS;
        $interval    = $card['interval'] ?? 0;
        $repetitions = $card['repetitions'] ?? 0;

        // Update easiness factor
        $easiness = $easiness + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        $easiness = max(self::MIN_EASINESS, $easiness);

        if ($quality >= 3) {
            // Correct response
            if ($repetitions === 0) {
                $interval = 1; // 1 day
            } elseif ($repetitions === 1) {
                $interval = 6; // 6 days
            } else {
                $interval = (int) round($interval * $easiness);
            }
            $repetitions++;
        } else {
            // Incorrect response: reset
            $repetitions = 0;
            $interval    = 1;
        }

        // Determine status
        $status = 'learning';
        if ($repetitions === 0) {
            $status = $card['status'] === 'new' ? 'new' : 'learning';
        } elseif ($interval >= 21) {
            $status = 'mastered';
        }

        $card['easiness']    = round($easiness, 2);
        $card['interval']    = $interval;
        $card['repetitions'] = $repetitions;
        $card['status']      = $status;
        $card['next_review'] = date('c', strtotime("+{$interval} days"));

        return $card;
    }

    // -------------------------------------------------------------------------
    // Deck Statistics
    // -------------------------------------------------------------------------

    /**
     * Get study statistics for a deck.
     *
     * @param string $deckId
     * @return array
     */
    public function getDeckStats(string $deckId): array
    {
        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];

        $totalCorrect   = 0;
        $totalIncorrect = 0;
        $totalReviews   = 0;
        $statusCounts   = ['new' => 0, 'learning' => 0, 'mastered' => 0];
        $dueNow         = 0;
        $now             = time();

        foreach ($cards as $card) {
            $totalCorrect   += ($card['correct_count'] ?? 0);
            $totalIncorrect += ($card['incorrect_count'] ?? 0);
            $totalReviews   += ($card['correct_count'] ?? 0) + ($card['incorrect_count'] ?? 0);

            $status = $card['status'] ?? 'new';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if (strtotime($card['next_review'] ?? '') <= $now) {
                $dueNow++;
            }
        }

        $accuracy = $totalReviews > 0 ? round(($totalCorrect / $totalReviews) * 100, 1) : 0;
        $masteryPct = count($cards) > 0
            ? round(($statusCounts['mastered'] / count($cards)) * 100, 1)
            : 0;

        return [
            'deck_id'        => $deckId,
            'deck_name'      => $deck['name'] ?? '',
            'total_cards'    => count($cards),
            'new'            => $statusCounts['new'],
            'learning'       => $statusCounts['learning'],
            'mastered'       => $statusCounts['mastered'],
            'mastery_pct'    => $masteryPct,
            'due_now'        => $dueNow,
            'total_reviews'  => $totalReviews,
            'total_correct'  => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'accuracy_pct'   => $accuracy,
            'last_studied'   => $deck['last_studied'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Import / Export
    // -------------------------------------------------------------------------

    /**
     * Import a flashcard deck from JSON data.
     *
     * Expected structure:
     *   { "name": "...", "description": "...", "cards": [{"front": "...", "back": "..."}, ...] }
     *
     * @param string $userId
     * @param array  $data   Parsed JSON data
     * @return array The created deck
     * @throws InvalidArgumentException On invalid data
     */
    public function importDeck(string $userId, array $data): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('FlashcardService: Import data must include a deck name.');
        }

        $importedCards = $data['cards'] ?? [];
        if (empty($importedCards)) {
            throw new InvalidArgumentException('FlashcardService: Import data must include at least one card.');
        }

        // Create the deck
        $deck = $this->createDeck($userId, [
            'name'        => $name,
            'description' => $data['description'] ?? 'Imported deck',
            'subject'     => $data['subject'] ?? '',
            'tags'        => $data['tags'] ?? ['imported'],
        ]);

        // Add each card
        $added = 0;
        foreach ($importedCards as $cardData) {
            $front = trim($cardData['front'] ?? '');
            $back  = trim($cardData['back'] ?? '');

            if ($front === '' || $back === '') {
                continue;
            }

            $this->addCard($deck['id'], [
                'front' => $front,
                'back'  => $back,
                'hints' => $cardData['hints'] ?? [],
                'tags'  => $cardData['tags'] ?? [],
            ]);
            $added++;
        }

        if ($added === 0) {
            // Clean up empty deck
            $this->deleteDeck($deck['id']);
            throw new InvalidArgumentException('FlashcardService: No valid cards found in import data.');
        }

        return $this->getDeck($deck['id']);
    }

    /**
     * Export a deck as a structured array (ready for JSON encoding).
     *
     * @param string $deckId
     * @return array
     */
    public function exportDeck(string $deckId): array
    {
        $deck  = $this->getDeck($deckId);
        $cards = $deck['cards'] ?? [];

        return [
            'name'        => $deck['name'] ?? '',
            'description' => $deck['description'] ?? '',
            'subject'     => $deck['subject'] ?? '',
            'tags'        => $deck['tags'] ?? [],
            'exported_at' => date('c'),
            'card_count'  => count($cards),
            'cards'       => array_map(function (array $card) {
                return [
                    'front' => $card['front'] ?? '',
                    'back'  => $card['back'] ?? '',
                    'hints' => $card['hints'] ?? [],
                    'tags'  => $card['tags'] ?? [],
                ];
            }, $cards),
        ];
    }

    // -------------------------------------------------------------------------
    // AI Card Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a prompt for AI-based flashcard generation.
     *
     * This does NOT call an AI API directly. It prepares a structured prompt
     * that can be passed to the AIService for card generation.
     *
     * @param string $subject
     * @param string $topic
     * @param int    $count   Number of cards to generate
     * @return array Prompt data for AI
     */
    public function generateCards(string $subject, string $topic, int $count = 10): array
    {
        $count = max(1, min(50, $count));

        $prompt = "Generate {$count} flashcards for studying {$subject} - specifically the topic: {$topic}.\n\n"
            . "Format each card as a JSON object with 'front' (question/term), 'back' (answer/definition), "
            . "and 'hints' (array of 1-2 hints).\n\n"
            . "Return a JSON array of card objects. Example:\n"
            . '[{"front": "What is photosynthesis?", "back": "The process by which plants convert sunlight into energy.", "hints": ["Think about what plants need to grow", "Involves chlorophyll"]}]'
            . "\n\nMake the cards progressively more challenging. Include a mix of definition, conceptual, and application questions.";

        return [
            'subject' => $subject,
            'topic'   => $topic,
            'count'   => $count,
            'prompt'  => $prompt,
            'format'  => 'json_array',
            'expected_schema' => [
                'type'  => 'array',
                'items' => [
                    'front' => 'string',
                    'back'  => 'string',
                    'hints' => 'string[]',
                ],
            ],
        ];
    }
}
