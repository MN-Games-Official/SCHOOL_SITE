<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/WritingService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class WritingController
{
    private WritingService $writingService;
    private Session $session;

    public function __construct()
    {
        $this->writingService = new WritingService();
        $this->session        = new Session();
    }

    /**
     * Writings list page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId  = $this->session->get('user_id');
        $filters = [];

        if ($request->has('type')) {
            $filters['type'] = $request->get('type');
        }
        if ($request->has('subject')) {
            $filters['subject'] = $request->get('subject');
        }

        try {
            $writings = $this->writingService->getUserWritings($userId, $filters);
            $stats    = $this->writingService->getWritingStats($userId);

            return View::render('writing/index', [
                'title'    => 'My Writing',
                'writings' => $writings,
                'stats'    => $stats,
                'filters'  => $filters,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to load writings.');
            return View::render('writing/index', [
                'title'    => 'My Writing',
                'writings' => [],
                'stats'    => [],
                'filters'  => $filters,
            ], 'layouts/main');
        }
    }

    /**
     * Show create writing form.
     */
    public function create(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $prompts   = $this->writingService->getWritingPrompts();
            $templates = $this->writingService->getWritingTemplates();

            return View::render('writing/create', [
                'title'     => 'New Writing',
                'prompts'   => $prompts,
                'templates' => $templates,
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            return View::render('writing/create', [
                'title'     => 'New Writing',
                'prompts'   => [],
                'templates' => [],
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * Save a new writing piece.
     */
    public function store(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');
        $errors = [];

        $title   = trim($request->post('title') ?? '');
        $type    = trim($request->post('type') ?? '');
        $subject = trim($request->post('subject') ?? '');
        $content = $request->post('content') ?? '';

        if (empty($title)) {
            $errors['title'] = 'Title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title must be under 200 characters.';
        }

        $validTypes = ['essay', 'report', 'creative', 'summary', 'journal', 'other'];
        if (empty($type) || !in_array($type, $validTypes, true)) {
            $errors['type'] = 'Please select a valid writing type.';
        }

        if (!empty($errors)) {
            $this->session->flash('errors', $errors);
            $this->session->flash('old', [
                'title'   => $title,
                'type'    => $type,
                'subject' => $subject,
                'content' => $content,
            ]);
            return redirect(url('/writing/create'));
        }

        try {
            $writing = $this->writingService->create($userId, [
                'title'   => $title,
                'type'    => $type,
                'subject' => $subject,
                'content' => $content,
            ]);

            $this->session->flash('success', 'Writing created successfully!');
            return redirect(url('/writing/editor/' . $writing['id']));
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->session->flash('old', [
                'title'   => $title,
                'type'    => $type,
                'subject' => $subject,
                'content' => $content,
            ]);
            return redirect(url('/writing/create'));
        }
    }

    /**
     * Writing editor page.
     */
    public function editor(Request $request, array $params = []): string
    {
        Middleware::auth();

        $writingId = $params['id'] ?? '';

        if (empty($writingId)) {
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/writing'));
        }

        try {
            $writing  = $this->writingService->get($writingId);
            $versions = $this->writingService->getVersionHistory($writingId);
            $wordCount = $this->writingService->getWordCount($writingId);

            return View::render('writing/editor', [
                'title'     => 'Edit: ' . e($writing['title'] ?? ''),
                'writing'   => $writing,
                'versions'  => $versions,
                'wordCount' => $wordCount,
                'csrf'      => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/writing'));
        }
    }

    /**
     * AJAX: Save / auto-save writing content.
     */
    public function save(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $writingId = $params['id'] ?? '';
        $content   = $request->post('content') ?? '';
        $title     = $request->post('title');
        $autoSave  = $request->post('auto_save') ? true : false;

        if (empty($writingId)) {
            return Response::json(['success' => false, 'message' => 'Writing not found.'], 400);
        }

        try {
            if ($autoSave) {
                $result = $this->writingService->autoSave($writingId, $content);
            } else {
                $data = ['content' => $content];
                if ($title !== null) {
                    $data['title'] = trim($title);
                }
                $result = $this->writingService->update($writingId, $data);
            }

            $wordCount = $this->writingService->getWordCount($writingId);

            return Response::json([
                'success'   => true,
                'message'   => $autoSave ? 'Auto-saved.' : 'Saved successfully.',
                'result'    => $result,
                'wordCount' => $wordCount,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review / analyze writing.
     */
    public function review(Request $request, array $params = []): string
    {
        Middleware::auth();

        $writingId = $params['id'] ?? '';

        if (empty($writingId)) {
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/writing'));
        }

        try {
            $writing   = $this->writingService->get($writingId);
            $analysis  = $this->writingService->analyzeWriting($writingId);
            $wordCount = $this->writingService->getWordCount($writingId);

            return View::render('writing/review', [
                'title'     => 'Review: ' . e($writing['title'] ?? ''),
                'writing'   => $writing,
                'analysis'  => $analysis,
                'wordCount' => $wordCount,
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Unable to review writing.');
            return redirect(url('/writing'));
        }
    }

    /**
     * Delete a writing piece.
     */
    public function delete(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $writingId = $params['id'] ?? '';

        if (empty($writingId)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => 'Writing not found.'], 400);
            }
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/writing'));
        }

        try {
            $this->writingService->delete($writingId);

            if ($request->isAjax()) {
                return Response::json(['success' => true, 'message' => 'Writing deleted.']);
            }

            $this->session->flash('success', 'Writing deleted successfully.');
            return redirect(url('/writing'));
        } catch (\RuntimeException $e) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            $this->session->flash('error', $e->getMessage());
            return redirect(url('/writing'));
        }
    }

    /**
     * Export a writing piece.
     */
    public function export(Request $request, array $params = []): string
    {
        Middleware::auth();

        $writingId = $params['id'] ?? '';
        $format    = $request->get('format') ?? 'text';

        $validFormats = ['text', 'html', 'markdown'];
        if (!in_array($format, $validFormats, true)) {
            $format = 'text';
        }

        if (empty($writingId)) {
            $this->session->flash('error', 'Writing not found.');
            return redirect(url('/writing'));
        }

        try {
            $exported = $this->writingService->exportWriting($writingId, $format);

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

    /**
     * AJAX: Get writing prompts.
     */
    public function getPrompts(Request $request, array $params = []): string
    {
        Middleware::auth();

        $subject = $request->get('subject') ?? '';
        $type    = $request->get('type') ?? '';

        try {
            $prompts = $this->writingService->getWritingPrompts($subject, $type);

            return Response::json([
                'success' => true,
                'prompts' => $prompts,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load prompts.',
            ], 500);
        }
    }

    /**
     * AJAX: Get writing templates.
     */
    public function getTemplates(Request $request, array $params = []): string
    {
        Middleware::auth();

        try {
            $templates = $this->writingService->getWritingTemplates();

            return Response::json([
                'success'   => true,
                'templates' => $templates,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load templates.',
            ], 500);
        }
    }

    /**
     * AJAX: Analyze a writing piece.
     */
    public function analyze(Request $request, array $params = []): string
    {
        Middleware::auth();

        $writingId = $params['id'] ?? '';

        if (empty($writingId)) {
            return Response::json(['success' => false, 'message' => 'Writing not found.'], 400);
        }

        try {
            $analysis = $this->writingService->analyzeWriting($writingId);

            return Response::json([
                'success'  => true,
                'analysis' => $analysis,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
