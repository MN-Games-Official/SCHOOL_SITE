<?php
/**
 * ============================================================================
 * AIService - AI Integration Service
 * StudyFlow - Student Self-Teaching App
 *
 * Provides AI-powered features via the Abacus RouteLLM API: chat tutoring,
 * study help, quiz/flashcard generation, writing feedback, concept
 * explanations, text summarization, and study plan suggestions.
 *
 * API Endpoint: POST https://routellm.abacus.ai/v1/chat/completions
 * Auth:         Bearer token via Authorization header
 * Model:        "router"
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class AIService
{
    private FileStorage $storage;

    private const COLLECTION_CHAT  = 'ai_chat_history';

    /** @var string Abacus RouteLLM API endpoint */
    private const API_ENDPOINT = 'https://routellm.abacus.ai/v1/chat/completions';

    /** @var string Model identifier */
    private const MODEL = 'router';

    /** @var int Maximum chat history entries to retain per user */
    private const MAX_HISTORY = 100;

    /** @var int API timeout in seconds */
    private const API_TIMEOUT = 30;

    /** @var string|null Cached API key */
    private ?string $apiKey = null;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Chat
    // -------------------------------------------------------------------------

    /**
     * Send a chat message and get an AI response.
     *
     * @param string $userId
     * @param string $message User's message
     * @param array  $context Optional context: subject, topic, etc.
     * @return array AI response with message content
     * @throws InvalidArgumentException On empty message
     * @throws RuntimeException On API failure
     */
    public function chat(string $userId, string $message, array $context = []): array
    {
        if (trim($message) === '') {
            throw new InvalidArgumentException('AIService: Message cannot be empty.');
        }

        // Build system prompt
        $systemPrompt = $this->buildSystemPrompt($context);

        // Fetch recent chat history for context
        $history  = $this->getChatHistory($userId, 10);
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Add history (oldest first)
        $historyMessages = array_reverse($history);
        foreach ($historyMessages as $h) {
            $messages[] = ['role' => $h['role'] ?? 'user', 'content' => $h['content'] ?? ''];
        }

        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        // Call API
        $response = $this->callAPI($messages);

        // Save user message
        $this->saveChatMessage($userId, 'user', $message);

        // Save assistant response
        $assistantContent = $response['content'] ?? '';
        $this->saveChatMessage($userId, 'assistant', $assistantContent);

        return [
            'message'  => $assistantContent,
            'role'     => 'assistant',
            'context'  => $context,
            'tokens'   => $response['usage'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Study Features
    // -------------------------------------------------------------------------

    /**
     * Generate study help for a specific question.
     *
     * @param string $subject
     * @param string $topic
     * @param string $question
     * @return array AI response
     */
    public function generateStudyHelp(string $subject, string $topic, string $question): array
    {
        if (trim($question) === '') {
            throw new InvalidArgumentException('AIService: Question cannot be empty.');
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a knowledgeable and patient study tutor helping a student learn {$subject}. "
                    . "Focus on the topic: {$topic}. Provide clear, step-by-step explanations. "
                    . "Use examples to illustrate concepts. Encourage understanding over memorization.",
            ],
            [
                'role'    => 'user',
                'content' => $question,
            ],
        ];

        $response = $this->callAPI($messages);

        return [
            'subject'  => $subject,
            'topic'    => $topic,
            'question' => $question,
            'answer'   => $response['content'] ?? '',
        ];
    }

    /**
     * Generate quiz questions.
     *
     * @param string $subject
     * @param string $topic
     * @param string $difficulty easy|medium|hard
     * @param int    $count
     * @return array Generated questions
     */
    public function generateQuizQuestions(string $subject, string $topic, string $difficulty = 'medium', int $count = 5): array
    {
        $count = max(1, min(20, $count));

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a quiz generator. Generate exactly {$count} {$difficulty}-level quiz questions about {$subject} - {$topic}. "
                    . "Return ONLY a valid JSON array. Each question object must have: "
                    . '"question" (string), "type" ("multiple_choice"), "options" (array of 4 strings), '
                    . '"correct_answer" (string matching one option exactly), "explanation" (string). '
                    . 'No markdown, no code fences, just the JSON array.',
            ],
            [
                'role'    => 'user',
                'content' => "Generate {$count} {$difficulty} quiz questions about {$topic} in {$subject}.",
            ],
        ];

        $response = $this->callAPI($messages);
        $content  = $response['content'] ?? '';

        $questions = $this->parseJsonResponse($content);

        return [
            'subject'    => $subject,
            'topic'      => $topic,
            'difficulty' => $difficulty,
            'count'      => $count,
            'questions'  => $questions,
            'raw'        => $content,
        ];
    }

    /**
     * Generate flashcards.
     *
     * @param string $subject
     * @param string $topic
     * @param int    $count
     * @return array Generated flashcards
     */
    public function generateFlashcards(string $subject, string $topic, int $count = 10): array
    {
        $count = max(1, min(30, $count));

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a flashcard generator. Create exactly {$count} flashcards for studying {$subject} - {$topic}. "
                    . 'Return ONLY a valid JSON array. Each card object must have: '
                    . '"front" (question or term), "back" (answer or definition), '
                    . '"hints" (array of 1-2 hint strings). '
                    . 'No markdown, no code fences, just the JSON array.',
            ],
            [
                'role'    => 'user',
                'content' => "Generate {$count} flashcards for {$topic} in {$subject}.",
            ],
        ];

        $response = $this->callAPI($messages);
        $content  = $response['content'] ?? '';

        $cards = $this->parseJsonResponse($content);

        return [
            'subject' => $subject,
            'topic'   => $topic,
            'count'   => $count,
            'cards'   => $cards,
            'raw'     => $content,
        ];
    }

    /**
     * Generate writing feedback.
     *
     * @param string $text The writing to analyze
     * @param string $type essay|report|creative|reflection
     * @return array Feedback
     */
    public function generateWritingFeedback(string $text, string $type = 'essay'): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('AIService: Text cannot be empty for feedback.');
        }

        $wordCount = count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a supportive writing tutor reviewing a student's {$type}. "
                    . "Provide constructive feedback covering: 1) Overall impression, 2) Strengths, "
                    . "3) Areas for improvement, 4) Specific suggestions, 5) Grammar/style notes. "
                    . "Be encouraging but honest. Focus on helping the student improve.",
            ],
            [
                'role'    => 'user',
                'content' => "Please review my {$type} ({$wordCount} words):\n\n{$text}",
            ],
        ];

        $response = $this->callAPI($messages);

        return [
            'type'       => $type,
            'word_count' => $wordCount,
            'feedback'   => $response['content'] ?? '',
        ];
    }

    /**
     * Generate a study plan suggestion.
     *
     * @param array  $subjects      Subjects to study
     * @param array  $goals         Learning goals
     * @param string $timeAvailable Available study time description
     * @return array Suggested plan
     */
    public function generateStudyPlan(array $subjects, array $goals, string $timeAvailable): array
    {
        $subjectList = implode(', ', $subjects);
        $goalList    = implode('; ', $goals);

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a study planning expert. Create a detailed, practical study plan. "
                    . "Consider the student's available time and learning goals. "
                    . "Include specific daily/weekly schedules, recommended techniques, and milestones.",
            ],
            [
                'role'    => 'user',
                'content' => "Create a study plan for these subjects: {$subjectList}.\n"
                    . "My goals: {$goalList}\n"
                    . "Available time: {$timeAvailable}\n"
                    . "Please provide a structured weekly plan with specific tasks.",
            ],
        ];

        $response = $this->callAPI($messages);

        return [
            'subjects'       => $subjects,
            'goals'          => $goals,
            'time_available' => $timeAvailable,
            'plan'           => $response['content'] ?? '',
        ];
    }

    /**
     * Explain a concept in simple terms.
     *
     * @param string $subject
     * @param string $concept
     * @return array Explanation
     */
    public function explainConcept(string $subject, string $concept): array
    {
        if (trim($concept) === '') {
            throw new InvalidArgumentException('AIService: Concept cannot be empty.');
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a patient teacher explaining concepts to a student studying {$subject}. "
                    . "Explain clearly using simple language, analogies, and examples. "
                    . "Structure your explanation with: 1) Simple definition, 2) Detailed explanation, "
                    . "3) Real-world example, 4) Why it matters.",
            ],
            [
                'role'    => 'user',
                'content' => "Please explain: {$concept}",
            ],
        ];

        $response = $this->callAPI($messages);

        return [
            'subject'     => $subject,
            'concept'     => $concept,
            'explanation' => $response['content'] ?? '',
        ];
    }

    /**
     * Summarize a text.
     *
     * @param string $text Text to summarize
     * @return array Summary
     */
    public function summarizeText(string $text): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('AIService: Text cannot be empty for summarization.');
        }

        $wordCount = count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));

        $messages = [
            [
                'role'    => 'system',
                'content' => "You are a summarization assistant. Provide a clear, concise summary that captures "
                    . "the key points. Include: 1) A brief summary (2-3 sentences), 2) Key points (bullet list), "
                    . "3) Main takeaway.",
            ],
            [
                'role'    => 'user',
                'content' => "Please summarize this text ({$wordCount} words):\n\n{$text}",
            ],
        ];

        $response = $this->callAPI($messages);

        return [
            'original_word_count' => $wordCount,
            'summary'             => $response['content'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Chat History
    // -------------------------------------------------------------------------

    /**
     * Get chat history for a user.
     *
     * @param string $userId
     * @param int    $limit
     * @return array Messages (newest first)
     */
    public function getChatHistory(string $userId, int $limit = 50): array
    {
        $limit = max(1, min(self::MAX_HISTORY, $limit));

        $messages = $this->storage->query(self::COLLECTION_CHAT, ['user_id' => $userId]);

        usort($messages, fn($a, $b) => strtotime($b['timestamp'] ?? '0') - strtotime($a['timestamp'] ?? '0'));

        return array_slice($messages, 0, $limit);
    }

    /**
     * Save a chat message.
     *
     * @param string $userId
     * @param string $role    'user', 'assistant', or 'system'
     * @param string $content Message content
     * @return array Saved message
     */
    public function saveChatMessage(string $userId, string $role, string $content): array
    {
        $id  = $this->storage->generateId();
        $now = date('c');

        $message = [
            'id'        => $id,
            'user_id'   => $userId,
            'role'      => $role,
            'content'   => $content,
            'timestamp' => $now,
        ];

        $this->storage->write(self::COLLECTION_CHAT, $id, $message);

        // Prune old messages if over limit
        $this->pruneHistory($userId);

        return $message;
    }

    /**
     * Clear all chat history for a user.
     *
     * @param string $userId
     * @return bool
     */
    public function clearChatHistory(string $userId): bool
    {
        $messages = $this->storage->query(self::COLLECTION_CHAT, ['user_id' => $userId]);

        foreach ($messages as $msg) {
            $id = $msg['id'] ?? $msg['_id'] ?? '';
            if ($id !== '') {
                $this->storage->delete(self::COLLECTION_CHAT, $id);
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // API Communication
    // -------------------------------------------------------------------------

    /**
     * Call the Abacus RouteLLM API.
     *
     * @param array $messages Array of {role, content} message objects
     * @param array $options  Optional: temperature, max_tokens, etc.
     * @return array Response with 'content' and 'usage' keys
     * @throws RuntimeException On API failure
     */
    public function callAPI(array $messages, array $options = []): array
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === '' || $apiKey === null) {
            throw new RuntimeException('AIService: API key is not configured. Set ABACUS_API_KEY in environment or config.');
        }

        $payload = [
            'model'       => self::MODEL,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 2048,
        ];

        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('AIService: Failed to encode request payload.');
        }

        $ch = curl_init(self::API_ENDPOINT);

        if ($ch === false) {
            throw new RuntimeException('AIService: Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('AIService: API request failed – ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorBody = json_decode($response, true);
            $errorMsg  = $errorBody['error']['message'] ?? $response;
            throw new RuntimeException("AIService: API returned HTTP {$httpCode} – {$errorMsg}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new RuntimeException('AIService: Failed to parse API response.');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $usage   = $decoded['usage'] ?? null;

        return [
            'content' => $content,
            'usage'   => $usage,
            'model'   => $decoded['model'] ?? self::MODEL,
        ];
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the API key from environment or config.
     */
    private function getApiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        // Try environment variable
        $key = getenv('ABACUS_API_KEY');
        if ($key !== false && $key !== '') {
            $this->apiKey = $key;
            return $this->apiKey;
        }

        // Try config file
        $configFile = __DIR__ . '/../config/ai.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config) && !empty($config['api_key'])) {
                $this->apiKey = $config['api_key'];
                return $this->apiKey;
            }
        }

        return null;
    }

    /**
     * Build system prompt based on context.
     */
    private function buildSystemPrompt(array $context): string
    {
        $prompt = "You are StudyFlow AI, a helpful study assistant for a self-teaching platform. "
            . "You help students learn effectively by providing clear explanations, asking guiding questions, "
            . "and encouraging independent thinking. Be supportive and patient.";

        if (!empty($context['subject'])) {
            $prompt .= " The student is currently studying {$context['subject']}.";
        }

        if (!empty($context['topic'])) {
            $prompt .= " The current topic is {$context['topic']}.";
        }

        if (!empty($context['level'])) {
            $prompt .= " The student is at level {$context['level']}.";
        }

        return $prompt;
    }

    /**
     * Try to parse a JSON array from AI response text.
     */
    private function parseJsonResponse(string $content): array
    {
        // Try direct parse
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to extract JSON from markdown code fences
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try to find JSON array in the text
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Prune old chat history entries beyond the limit.
     */
    private function pruneHistory(string $userId): void
    {
        $messages = $this->storage->query(self::COLLECTION_CHAT, ['user_id' => $userId]);

        if (count($messages) <= self::MAX_HISTORY) {
            return;
        }

        usort($messages, fn($a, $b) => strtotime($b['timestamp'] ?? '0') - strtotime($a['timestamp'] ?? '0'));

        $toDelete = array_slice($messages, self::MAX_HISTORY);
        foreach ($toDelete as $msg) {
            $id = $msg['id'] ?? $msg['_id'] ?? '';
            if ($id !== '') {
                $this->storage->delete(self::COLLECTION_CHAT, $id);
            }
        }
    }
}
