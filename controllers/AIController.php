<?php

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/View.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/AIService.php';
require_once __DIR__ . '/../utils/Helpers.php';

class AIController
{
    private AIService $aiService;
    private Session $session;

    public function __construct()
    {
        $this->aiService = new AIService();
        $this->session   = new Session();
    }

    /**
     * AI chat page.
     */
    public function index(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');

        try {
            $history = $this->aiService->getChatHistory($userId, 20);

            return View::render('ai/index', [
                'title'   => 'AI Study Assistant',
                'history' => $history,
                'csrf'    => $this->session->csrf_token(),
            ], 'layouts/main');
        } catch (\RuntimeException $e) {
            return View::render('ai/index', [
                'title'   => 'AI Study Assistant',
                'history' => [],
                'csrf'    => $this->session->csrf_token(),
            ], 'layouts/main');
        }
    }

    /**
     * AJAX: Send a chat message.
     */
    public function chat(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId  = $this->session->get('user_id');
        $message = trim($request->post('message') ?? '');

        if (empty($message)) {
            return Response::json([
                'success' => false,
                'message' => 'Please enter a message.',
            ], 400);
        }

        if (strlen($message) > 5000) {
            return Response::json([
                'success' => false,
                'message' => 'Message is too long. Please keep it under 5000 characters.',
            ], 400);
        }

        $context = [];
        if ($request->has('subject')) {
            $context['subject'] = trim($request->post('subject'));
        }
        if ($request->has('topic')) {
            $context['topic'] = trim($request->post('topic'));
        }

        try {
            $this->aiService->saveChatMessage($userId, 'user', $message);

            $response = $this->aiService->chat($userId, $message, $context);

            if (!empty($response['content'])) {
                $this->aiService->saveChatMessage($userId, 'assistant', $response['content']);
            }

            return Response::json([
                'success'  => true,
                'response' => $response,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to get a response. Please try again.',
            ], 500);
        }
    }

    /**
     * AJAX: Get chat history.
     */
    public function getHistory(Request $request, array $params = []): string
    {
        Middleware::auth();

        $userId = $this->session->get('user_id');
        $limit  = (int) ($request->get('limit') ?? 50);
        $limit  = max(1, min($limit, 200));

        try {
            $history = $this->aiService->getChatHistory($userId, $limit);

            return Response::json([
                'success' => true,
                'history' => $history,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to load chat history.',
            ], 500);
        }
    }

    /**
     * AJAX: Clear chat history.
     */
    public function clearHistory(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $userId = $this->session->get('user_id');

        try {
            $this->aiService->clearChatHistory($userId);

            return Response::json([
                'success' => true,
                'message' => 'Chat history cleared.',
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to clear history.',
            ], 500);
        }
    }

    /**
     * AJAX: Generate study help for a topic.
     */
    public function generateHelp(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $subject  = trim($request->post('subject') ?? '');
        $topic    = trim($request->post('topic') ?? '');
        $question = trim($request->post('question') ?? '');

        if (empty($subject)) {
            return Response::json([
                'success' => false,
                'message' => 'Subject is required.',
            ], 400);
        }

        if (empty($question)) {
            return Response::json([
                'success' => false,
                'message' => 'Please describe what you need help with.',
            ], 400);
        }

        try {
            $help = $this->aiService->generateStudyHelp($subject, $topic, $question);

            return Response::json([
                'success' => true,
                'help'    => $help,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to generate study help.',
            ], 500);
        }
    }

    /**
     * AJAX: Explain a concept.
     */
    public function explain(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $subject = trim($request->post('subject') ?? '');
        $concept = trim($request->post('concept') ?? '');

        if (empty($subject)) {
            return Response::json([
                'success' => false,
                'message' => 'Subject is required.',
            ], 400);
        }

        if (empty($concept)) {
            return Response::json([
                'success' => false,
                'message' => 'Please specify the concept to explain.',
            ], 400);
        }

        try {
            $explanation = $this->aiService->explainConcept($subject, $concept);

            return Response::json([
                'success'     => true,
                'explanation' => $explanation,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to generate explanation.',
            ], 500);
        }
    }

    /**
     * AJAX: Summarize text.
     */
    public function summarize(Request $request, array $params = []): string
    {
        Middleware::auth();
        Middleware::csrf();

        $text = trim($request->post('text') ?? '');

        if (empty($text)) {
            return Response::json([
                'success' => false,
                'message' => 'Please provide text to summarize.',
            ], 400);
        }

        if (strlen($text) < 50) {
            return Response::json([
                'success' => false,
                'message' => 'Text must be at least 50 characters for a meaningful summary.',
            ], 400);
        }

        if (strlen($text) > 50000) {
            return Response::json([
                'success' => false,
                'message' => 'Text is too long. Please limit to 50,000 characters.',
            ], 400);
        }

        try {
            $summary = $this->aiService->summarizeText($text);

            return Response::json([
                'success' => true,
                'summary' => $summary,
            ]);
        } catch (\RuntimeException $e) {
            return Response::json([
                'success' => false,
                'message' => 'Unable to generate summary.',
            ], 500);
        }
    }
}
