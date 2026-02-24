<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/NoteService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class NoteController
{
    private NoteService $noteService;
    private Session $session;

    public function __construct()
    {
        $this->noteService = new NoteService();
        $this->session     = new Session();
    }

    /**
     * Notes list with search and filter.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId  = $this->session->get('user_id');
        $filters = [];

        if ($request->has('subject')) {
            $filters['subject'] = $request->get('subject');
        }
        if ($request->has('tag')) {
            $filters['tag'] = $request->get('tag');
        }
        if ($request->has('archived')) {
            $filters['archived'] = $request->get('archived');
        }

        try {
            $notes = $this->noteService->getUserNotes($userId, $filters);
            $tags  = $this->noteService->getTags($userId);
            $stats = $this->noteService->getNoteStats($userId);

            return View::render('notes/index', [
                'title'   => 'My Notes',
                'notes'   => $notes,
                'tags'    => $tags,
                'stats'   => $stats,
                'filters' => $filters,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load notes.');
            return View::render('notes/index', [
                'title'   => 'My Notes',
                'notes'   => [],
                'tags'    => [],
                'stats'   => [],
                'filters' => $filters,
            ], 'layouts/main');
        }
    }

    /**
     * Show create note form.
     */
    public function create(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $tags = $this->noteService->getTags($userId);
        } catch (\RuntimeException $e) {
            $tags = [];
        }

        return View::render('notes/create', [
            'title'        => 'New Note',
            'existingTags' => $tags,
            'csrf'         => $this->session->csrf_token(),
        ], 'layouts/main');
    }

    /**
     * Save a new note.
     */
    public function store(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $title   = trim($request->post('title') ?? '');
        $content = $request->post('content') ?? '';
        $subject = trim($request->post('subject') ?? '');
        $tags    = $request->post('tags') ?? '';
        $color   = trim($request->post('color') ?? '');

        if (empty($title)) {
            $errors['title'] = 'Title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title must be under 200 characters.';
        }

        if (empty($content)) {
            $errors['content'] = 'Note content is required.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'title'   => $title,
                'content' => $content,
                'subject' => $subject,
                'tags'    => $tags,
                'color'   => $color,
            ]);
            return redirect(url('/notes/create'));
        }

        $tagsArray = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));

        try {
            $note = $this->noteService->create($userId, [
                'title'   => $title,
                'content' => $content,
                'subject' => $subject,
                'tags'    => $tagsArray,
                'color'   => $color,
            ]);

            $this->session->flash('success', 'Note created!');
            return redirect(url('/notes/' . $note['id']));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->session->flash('old', [
                'title'   => $title,
                'content' => $content,
                'subject' => $subject,
                'tags'    => $tags,
                'color'   => $color,
            ]);
            return redirect(url('/notes/create'));
        }
    }

    /**
     * View a single note.
     */
    public function show(Request $request, array $params = []): string
    {
        Middleware::auth();

        $noteId = $params['id'] ?? '';

        if (empty($noteId)) {
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }

        try {
            $note = $this->noteService->get($noteId);

            return View::render('notes/show', [
                'title' => e($note['title'] ?? 'Note'),
                'note'  => $note,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }
    }

    /**
     * Edit note page.
     */
    public function edit(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $noteId = $params['id'] ?? '';

        if (empty($noteId)) {
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }

        try {
            $note = $this->noteService->get($noteId);
            $tags = $this->noteService->getTags($userId);

            return View::render('notes/edit', [
                'title'        => 'Edit: ' . e($note['title'] ?? ''),
                'note'         => $note,
                'existingTags' => $tags,
                'csrf'         => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }
    }

    /**
     * Update a note.
     */
    public function update(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $noteId = $params['id'] ?? '';
        $errors = [];

        if (empty($noteId)) {
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }

        $title   = trim($request->post('title') ?? '');
        $content = $request->post('content') ?? '';
        $subject = trim($request->post('subject') ?? '');
        $tags    = $request->post('tags') ?? '';
        $color   = trim($request->post('color') ?? '');

        if (empty($title)) {
            $errors['title'] = 'Title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title must be under 200 characters.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            return redirect(url('/notes/edit/' . urlencode($noteId)));
        }

        $tagsArray = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));

        try {
            $this->noteService->update($noteId, [
                'title'   => $title,
                'content' => $content,
                'subject' => $subject,
                'tags'    => $tagsArray,
                'color'   => $color,
            ]);

            $this->session->flash('success', 'Note updated!');
            return redirect(url('/notes/' . urlencode($noteId)));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/notes/edit/' . urlencode($noteId)));
        }
    }

    /**
     * Delete a note.
     */
    public function delete(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $noteId = $params['id'] ?? '';

        if (empty($noteId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Note not found.'], 400);
            }
            $this->session->flash('error', 'Note not found.');
            return redirect(url('/notes'));
        }

        try {
            $this->noteService->delete($noteId);

            if ($request->isAjax()) {
                return Response::json(['success' => true, 'message' => 'Note deleted.']);
            }

            $this->session->flash('success', 'Note deleted.');
            return redirect(url('/notes'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/notes'));
        }
    }

    /**
     * AJAX: Search notes.
     */
    public function search(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $query  = trim($request->get('q') ?? '');

        if (empty($query) || strlen($query) < 2) {
            return Response::json([
                'success' => true,
                'notes'   => [],
            ]);
        }

        try {
            $notes = $this->noteService->search($userId, $query);

            return Response::json([
                'success' => true,
                'query'   => $query,
                'notes'   => $notes,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Search failed.',
            ], 500);
        }
    }

    /**
     * AJAX: Pin or unpin a note.
     */
    public function togglePin(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $noteId = $params['id'] ?? '';

        if (empty($noteId)) {
            return Response::json(['success' => false, 'message' => 'Note not found.'], 400);
        }

        try {
            $result = $this->noteService->pin($noteId);

            return Response::json([
                'success' => true,
                'message' => 'Note pin toggled.',
                'pinned'  => $result['pinned'] ?? false,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Archive a note.
     */
    public function archive(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $noteId = $params['id'] ?? '';

        if (empty($noteId)) {
            return Response::json(['success' => false, 'message' => 'Note not found.'], 400);
        }

        try {
            $result = $this->noteService->archive($noteId);

            return Response::json([
                'success'  => true,
                'message'  => 'Note archived.',
                'archived' => $result['archived'] ?? true,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Get all tags for the user.
     */
    public function getTags(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $tags = $this->noteService->getTags($userId);

            return Response::json([
                'success' => true,
                'tags'    => $tags,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load tags.',
            ], 500);
        }
    }

    /**
     * Export notes.
     */
    public function export(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $format = $request->get('format') ?? 'json';

        $validFormats = ['json', 'text', 'html'];
        if (!in_array($format, $validFormats, true)) {
            $format = 'json';
        }

        try {
            $exported = $this->noteService->exportNotes($userId, $format);

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
