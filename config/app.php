<?php
/**
 * ============================================================================
 * StudyFlow - Application Configuration
 * Student Self-Teaching App
 *
 * Central configuration file for the application. All settings are returned
 * as an associative array and made available throughout the app.
 *
 * Environment-specific values can be overridden via environment variables.
 * ============================================================================
 */

return [

    // -------------------------------------------------------------------------
    // Application Settings
    // -------------------------------------------------------------------------

    // Application name displayed in the UI
    'app_name' => 'StudyFlow',

    // Application tagline
    'tagline' => 'Your Personal Learning Companion',

    // Application version
    'version' => '1.0.0',

    // Base URL of the application (auto-detected or set manually)
    'base_url' => rtrim(
        getenv('APP_BASE_URL') ?: (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
            '://' .
            ($_SERVER['HTTP_HOST'] ?? 'localhost') .
            rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/')
        ),
        '/'
    ),

    // Debug mode (disable in production)
    'debug' => (bool) (getenv('APP_DEBUG') ?: false),

    // Application environment: development, staging, production
    'environment' => getenv('APP_ENV') ?: 'production',

    // Default timezone
    'timezone' => 'UTC',

    // Default locale
    'locale' => 'en_US',

    // Character encoding
    'charset' => 'UTF-8',

    // -------------------------------------------------------------------------
    // Data Storage
    // -------------------------------------------------------------------------

    'data' => [
        // Root directory for all JSON data files
        'directory'  => dirname(__DIR__) . '/data',

        // Individual data paths
        'users'          => dirname(__DIR__) . '/data/users',
        'subjects'       => dirname(__DIR__) . '/data/subjects',
        'study_sessions' => dirname(__DIR__) . '/data/study_sessions',
        'writing'        => dirname(__DIR__) . '/data/writing',
        'flashcards'     => dirname(__DIR__) . '/data/flashcards',
        'quizzes'        => dirname(__DIR__) . '/data/quizzes',
        'notes'          => dirname(__DIR__) . '/data/notes',
        'progress'       => dirname(__DIR__) . '/data/progress',
        'planner'        => dirname(__DIR__) . '/data/planner',
        'uploads'        => dirname(__DIR__) . '/data/uploads',

        // JSON file encoding options
        'json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,

        // File permissions for new data files
        'file_permissions' => 0644,

        // Directory permissions for new data directories
        'dir_permissions' => 0755,
    ],

    // -------------------------------------------------------------------------
    // Session Configuration
    // -------------------------------------------------------------------------

    'session' => [
        // Session name (cookie name)
        'name' => 'studyflow_session',

        // Session lifetime in seconds (2 hours)
        'lifetime' => 7200,

        // Session cookie path
        'path' => '/',

        // Session cookie domain (empty = current domain)
        'domain' => '',

        // Only send cookie over HTTPS
        'secure' => (bool) (getenv('SESSION_SECURE') ?: false),

        // Prevent JavaScript access to session cookie
        'httponly' => true,

        // SameSite cookie attribute
        'samesite' => 'Lax',

        // Idle timeout in seconds (30 minutes of inactivity)
        'idle_timeout' => 1800,

        // Regenerate session ID interval in seconds (15 minutes)
        'regenerate_interval' => 900,
    ],

    // -------------------------------------------------------------------------
    // AI API Configuration (Abacus RouteLLM)
    // -------------------------------------------------------------------------

    'ai' => [
        // Whether AI features are enabled
        'enabled' => true,

        // API endpoint
        'endpoint' => 'https://routellm.abacus.ai/v1/chat/completions',

        // API key (loaded from environment variable for security)
        'api_key' => getenv('ABACUS_API_KEY') ?: '',

        // Authorization header format
        'auth_type' => 'Bearer',

        // Default model to use
        'model' => 'router',

        // Request timeout in seconds
        'timeout' => 30,

        // Maximum tokens per response
        'max_tokens' => 2048,

        // Default temperature (creativity level)
        'temperature' => 0.7,

        // Rate limiting: max requests per user per hour
        'rate_limit' => 60,

        // System prompt for educational context
        'system_prompt' => 'You are a helpful educational tutor for a student self-study platform called StudyFlow. '
            . 'Provide clear, accurate, and encouraging explanations. '
            . 'Focus on helping the student understand concepts rather than giving direct answers. '
            . 'Use examples and analogies when helpful. '
            . 'If a student asks for help with writing, guide them through the process rather than writing for them. '
            . 'Always maintain an encouraging and supportive tone.',

        // Content safety: topics the AI should not engage with
        'blocked_topics' => [
            'violence',
            'explicit content',
            'personal information requests',
        ],
    ],

    // -------------------------------------------------------------------------
    // Upload Configuration
    // -------------------------------------------------------------------------

    'uploads' => [
        // Maximum upload file size in bytes (10 MB)
        'max_file_size' => 10 * 1024 * 1024,

        // Maximum total upload size per request in bytes (12 MB)
        'max_request_size' => 12 * 1024 * 1024,

        // Allowed file extensions by category
        'allowed_extensions' => [
            'images'    => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
            'documents' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'],
            'data'      => ['csv', 'json', 'xml'],
        ],

        // Allowed MIME types by category
        'allowed_mimetypes' => [
            'images' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/svg+xml',
                'image/webp',
            ],
            'documents' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'application/rtf',
                'application/vnd.oasis.opendocument.text',
            ],
            'data' => [
                'text/csv',
                'application/json',
                'application/xml',
                'text/xml',
            ],
        ],

        // Upload directory (inside data/)
        'directory' => dirname(__DIR__) . '/data/uploads',

        // Generate unique filenames to prevent collisions
        'unique_names' => true,
    ],

    // -------------------------------------------------------------------------
    // Security Settings
    // -------------------------------------------------------------------------

    'security' => [
        // Password hashing algorithm
        'hash_algo' => PASSWORD_BCRYPT,

        // Password hashing cost factor
        'hash_cost' => 12,

        // Minimum password length
        'min_password_length' => 8,

        // CSRF token lifetime in seconds (1 hour)
        'csrf_lifetime' => 3600,

        // Maximum login attempts before lockout
        'max_login_attempts' => 5,

        // Account lockout duration in seconds (15 minutes)
        'lockout_duration' => 900,

        // Allowed HTML tags in user content (for sanitization)
        'allowed_html_tags' => '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><a><img>',

        // Content Security Policy
        'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; "
            . "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: https:; "
            . "connect-src 'self' https://routellm.abacus.ai;",
    ],

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    'pagination' => [
        // Default items per page
        'per_page' => 12,

        // Maximum items per page
        'max_per_page' => 50,

        // Number of page links to show
        'link_count' => 5,
    ],

    // -------------------------------------------------------------------------
    // Study Session Settings
    // -------------------------------------------------------------------------

    'study' => [
        // Default study session duration in minutes
        'default_duration' => 25,

        // Break duration in minutes (Pomodoro technique)
        'break_duration' => 5,

        // Long break duration in minutes
        'long_break_duration' => 15,

        // Sessions before a long break
        'sessions_before_long_break' => 4,

        // Minimum study session duration to count (in minutes)
        'min_session_duration' => 5,
    ],

    // -------------------------------------------------------------------------
    // Quiz Settings
    // -------------------------------------------------------------------------

    'quiz' => [
        // Default number of questions per quiz
        'default_question_count' => 10,

        // Maximum questions per quiz
        'max_question_count' => 50,

        // Time limit per question in seconds (0 = no limit)
        'time_per_question' => 60,

        // Passing score percentage
        'passing_score' => 70,

        // Show correct answers after submission
        'show_answers' => true,
    ],

    // -------------------------------------------------------------------------
    // Writing Settings
    // -------------------------------------------------------------------------

    'writing' => [
        // Auto-save interval in seconds
        'autosave_interval' => 30,

        // Maximum word count per writing piece
        'max_word_count' => 10000,

        // Minimum word count for submission
        'min_word_count' => 50,

        // Enable plagiarism-style integrity checks
        'integrity_check' => true,
    ],

    // -------------------------------------------------------------------------
    // Flashcard Settings
    // -------------------------------------------------------------------------

    'flashcards' => [
        // Maximum cards per deck
        'max_cards_per_deck' => 200,

        // Spaced repetition intervals (in days)
        'repetition_intervals' => [1, 3, 7, 14, 30, 90],

        // Maximum decks per user
        'max_decks' => 50,
    ],

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    'logging' => [
        // Enable application logging
        'enabled' => true,

        // Log file path
        'file' => dirname(__DIR__) . '/data/app.log',

        // Log level: debug, info, warning, error, critical
        'level' => getenv('LOG_LEVEL') ?: 'error',

        // Maximum log file size in bytes (5 MB)
        'max_size' => 5 * 1024 * 1024,
    ],

    // -------------------------------------------------------------------------
    // Views / Templates
    // -------------------------------------------------------------------------

    'views' => [
        // View directory path
        'directory' => dirname(__DIR__) . '/views',

        // Default layout file
        'layout' => 'layouts/main',

        // View file extension
        'extension' => '.php',

        // Cache compiled views (future enhancement)
        'cache' => false,
    ],

];
