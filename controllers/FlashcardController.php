<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/FlashcardService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class FlashcardController
{
    private FlashcardService $flashcardService;
    private Session $session;

    public function __construct()
    {
        $this->flashcardService = new FlashcardService();
        $this->session          = new Session();
    }

    /**
     * Flashcard decks list page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $decks = $this->flashcardService->getUserDecks($userId);

            return View::render('flashcards/index', [
                'title' => 'Flashcard Decks',
                'decks' => $decks,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load flashcard decks.');
            return View::render('flashcards/index', [
                'title' => 'Flashcard Decks',
                'decks' => [],
            ], 'layouts/main');
        }
    }

    /**
     * Show create deck form.
     */
    public function createDeck(Request $request, array $params = []): string
    {
        Middleware::auth();

        return View::render('flashcards/create', [
            'title' => 'Create Deck',
            'csrf'  => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Save a new deck.
     */
    public function storeDeck(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $name        = trim($request->post('name') ?? '');
        $description = trim($request->post('description') ?? '');
        $subject     = trim($request->post('subject') ?? '');
        $tags        = $request->post('tags') ?? '';

        if (empty($name)) {
            $errors['name'] = 'Deck name is required.';
        } elseif (strlen($name) > 150) {
            $errors['name'] = 'Deck name must be under 150 characters.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'name'        => $name,
                'description' => $description,
                'subject'     => $subject,
                'tags'        => $tags,
            ]);
            return redirect(url('/flashcards/create'));
        }

        $tagsArray = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));

        try {
            $deck = $this->flashcardService->createDeck($userId, [
                'name'        => $name,
                'description' => $description,
                'subject'     => $subject,
                'tags'        => $tagsArray,
            ]);

            $this->session->flash('success', 'Deck created successfully!');
            return redirect(url('/flashcards/deck/' . $deck['id']));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->session->flash('old', [
                'name'        => $name,
                'description' => $description,
                'subject'     => $subject,
                'tags'        => $tags,
            ]);
            return redirect(url('/flashcards/create'));
        }
    }

    /**
     * View a deck with its cards.
     */
    public function deck(Request $request, array $params = []): string
    {
        Middleware::auth();

        $deckId = $params['id'] ?? '';

        if (empty($deckId)) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }

        try {
            $deck  = $this->flashcardService->getDeck($deckId);
            $stats = $this->flashcardService->getDeckStats($deckId);

            return View::render('flashcards/deck', [
                'title' => e($deck['name'] ?? 'Deck'),
                'deck'  => $deck,
                'stats' => $stats,
                'csrf'  => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }
    }

    /**
     * Edit deck page.
     */
    public function editDeck(Request $request, array $params = []): string
    {
        Middleware::auth();

        $deckId = $params['id'] ?? '';

        if (empty($deckId)) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }

        try {
            $deck = $this->flashcardService->getDeck($deckId);

            return View::render('flashcards/edit', [
                'title' => 'Edit: ' . e($deck['name'] ?? ''),
                'deck'  => $deck,
                'csrf'  => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }
    }

    /**
     * Update an existing deck.
     */
    public function updateDeck(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['id'] ?? '';
        $errors = [];

        $name        = trim($request->post('name') ?? '');
        $description = trim($request->post('description') ?? '');
        $subject     = trim($request->post('subject') ?? '');
        $tags        = $request->post('tags') ?? '';

        if (empty($deckId)) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }

        if (empty($name)) {
            $errors['name'] = 'Deck name is required.';
        } elseif (strlen($name) > 150) {
            $errors['name'] = 'Deck name must be under 150 characters.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return redirect(url('/flashcards/edit/' . urlencode($deckId)));
        }

        $tagsArray = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));

        try {
            $this->flashcardService->updateDeck($deckId, [
                'name'        => $name,
                'description' => $description,
                'subject'     => $subject,
                'tags'        => $tagsArray,
            ]);

            $this->session->flash('success', 'Deck updated successfully!');
            return redirect(url('/flashcards/deck/' . urlencode($deckId)));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/flashcards/edit/' . urlencode($deckId)));
        }
    }

    /**
     * Delete a deck.
     */
    public function deleteDeck(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['id'] ?? '';

        if (empty($deckId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Deck not found.'], 400);
            }
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }

        try {
            $this->flashcardService->deleteDeck($deckId);

            if ($request->isAjax()) {
                return Response::json(['success' => true, 'message' => 'Deck deleted.']);
            }

            $this->session->flash('success', 'Deck deleted successfully.');
            return redirect(url('/flashcards'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/flashcards'));
        }
    }

    /**
     * AJAX: Add a card to a deck.
     */
    public function addCard(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['id'] ?? '';
        $front  = trim($request->post('front') ?? '');
        $back   = trim($request->post('back') ?? '');
        $hints  = trim($request->post('hints') ?? '');
        $tags   = $request->post('tags') ?? '';

        if (empty($deckId)) {
            return Response::json(['success' => false, 'message' => 'Deck not found.'], 400);
        }

        if (empty($front)) {
            return Response::json(['success' => false, 'message' => 'Card front is required.'], 400);
        }

        if (empty($back)) {
            return Response::json(['success' => false, 'message' => 'Card back is required.'], 400);
        }

        $tagsArray = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));

        try {
            $card = $this->flashcardService->addCard($deckId, [
                'front' => $front,
                'back'  => $back,
                'hints' => $hints,
                'tags'  => $tagsArray,
            ]);

            return Response::json([
                'success' => true,
                'message' => 'Card added!',
                'card'    => $card,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Update a card.
     */
    public function updateCard(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['deck_id'] ?? '';
        $cardId = $params['card_id'] ?? '';

        if (empty($deckId) || empty($cardId)) {
            return Response::json(['success' => false, 'message' => 'Card not found.'], 400);
        }

        $data = [];
        if ($request->has('front')) {
            $data['front'] = trim($request->post('front'));
        }
        if ($request->has('back')) {
            $data['back'] = trim($request->post('back'));
        }
        if ($request->has('hints')) {
            $data['hints'] = trim($request->post('hints'));
        }
        if ($request->has('tags')) {
            $tags = $request->post('tags');
            $data['tags'] = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));
        }

        if (empty($data)) {
            return Response::json(['success' => false, 'message' => 'No data to update.'], 400);
        }

        try {
            $card = $this->flashcardService->updateCard($deckId, $cardId, $data);

            return Response::json([
                'success' => true,
                'message' => 'Card updated!',
                'card'    => $card,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Delete a card.
     */
    public function deleteCard(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['deck_id'] ?? '';
        $cardId = $params['card_id'] ?? '';

        if (empty($deckId) || empty($cardId)) {
            return Response::json(['success' => false, 'message' => 'Card not found.'], 400);
        }

        try {
            $this->flashcardService->deleteCard($deckId, $cardId);

            return Response::json([
                'success' => true,
                'message' => 'Card deleted.',
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Study mode page (spaced repetition).
     */
    public function study(Request $request, array $params = []): string
    {
        Middleware::auth();

        $deckId = $params['id'] ?? '';
        $maxNew = (int) ($request->get('max_new') ?? 10);
        $maxNew = max(1, min($maxNew, 50));

        if (empty($deckId)) {
            $this->session->flash('error', 'Deck not found.');
            return redirect(url('/flashcards'));
        }

        try {
            $deck  = $this->flashcardService->getDeck($deckId);
            $cards = $this->flashcardService->getStudyCards($deckId, $maxNew);
            $stats = $this->flashcardService->getDeckStats($deckId);

            return View::render('flashcards/study', [
                'title' => 'Study: ' . e($deck['name'] ?? ''),
                'deck'  => $deck,
                'cards' => $cards,
                'stats' => $stats,
                'csrf'  => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to start study session.');
            return redirect(url('/flashcards'));
        }
    }

    /**
     * AJAX: Record a card study result.
     */
    public function recordResult(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $deckId = $params['deck_id'] ?? '';
        $cardId = $params['card_id'] ?? '';
        $result = $request->post('result') ?? '';

        if (empty($deckId) || empty($cardId)) {
            return Response::json(['success' => false, 'message' => 'Card not found.'], 400);
        }

        $validResults = ['correct', 'incorrect', 'partial'];
        if (!in_array($result, $validResults, true)) {
            return Response::json(['success' => false, 'message' => 'Invalid result value.'], 400);
        }

        try {
            $cardResult = $this->flashcardService->recordCardResult($deckId, $cardId, $result);

            return Response::json([
                'success' => true,
                'message' => 'Result recorded.',
                'result'  => $cardResult,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Get deck statistics.
     */
    public function getStats(Request $request, array $params = []): string
    {
        Middleware::auth();

        $deckId = $params['id'] ?? '';

        if (empty($deckId)) {
            return Response::json(['success' => false, 'message' => 'Deck not found.'], 400);
        }

        try {
            $stats = $this->flashcardService->getDeckStats($deckId);

            return Response::json([
                'success' => true,
                'stats'   => $stats,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load deck stats.',
            ], 500);
        }
    }

    /**
     * Import a deck.
     */
    public function importDeck(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $data   = $request->post('data') ?? '';

        if (empty($data)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Import data is required.'], 400);
            }
            $this->session->flash('error', 'Import data is required.');
            return redirect(url('/flashcards'));
        }

        $decoded = is_string($data) ? json_decode($data, true) : $data;
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Invalid import data format.'], 400);
            }
            $this->session->flash('error', 'Invalid import data format.');
            return redirect(url('/flashcards'));
        }

        try {
            $deck = $this->flashcardService->importDeck($userId, $decoded);

            if ($request->isAjax()) {
                return Response::json([
                    'success' => true,
                    'message' => 'Deck imported successfully!',
                    'deck'    => $deck,
                ]);
            }

            $this->session->flash('success', 'Deck imported successfully!');
            return redirect(url('/flashcards/deck/' . $deck['id']));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/flashcards'));
        }
    }

    /**
     * Export a deck.
     */
    public function exportDeck(Request $request, array $params = []): string
    {
        Middleware::auth();

        $deckId = $params['id'] ?? '';

        if (empty($deckId)) {
            return Response::json(['success' => false, 'message' => 'Deck not found.'], 400);
        }

        try {
            $exported = $this->flashcardService->exportDeck($deckId);

            return Response::json([
                'success' => true,
                'data'    => $exported,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
