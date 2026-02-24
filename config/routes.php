<?php
/**
 * ============================================================================
 * StudyFlow - Route Definitions
 * Student Self-Teaching App
 *
 * Maps URL patterns to controller@method handlers.
 * Each route definition includes:
 *   - method:     HTTP method (GET or POST)
 *   - pattern:    URL pattern with optional named parameters {param}
 *   - handler:    Controller@method string
 *   - middleware:  Array of middleware to apply (optional)
 *   - name:       Route name for URL generation (optional)
 *
 * Patterns support named parameters like {id} and {topic_id} which
 * are extracted and passed to the controller method.
 * ============================================================================
 */

return [

    // =========================================================================
    // Authentication Routes
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/login',
        'handler'    => 'AuthController@loginForm',
        'middleware' => ['guest'],
        'name'       => 'login',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/login',
        'handler'    => 'AuthController@login',
        'middleware' => ['guest', 'csrf'],
        'name'       => 'login.submit',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/register',
        'handler'    => 'AuthController@registerForm',
        'middleware' => ['guest'],
        'name'       => 'register',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/register',
        'handler'    => 'AuthController@register',
        'middleware' => ['guest', 'csrf'],
        'name'       => 'register.submit',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/logout',
        'handler'    => 'AuthController@logout',
        'middleware' => ['auth'],
        'name'       => 'logout',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/logout',
        'handler'    => 'AuthController@logout',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'logout.post',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/forgot-password',
        'handler'    => 'AuthController@forgotPasswordForm',
        'middleware' => ['guest'],
        'name'       => 'forgot-password',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/forgot-password',
        'handler'    => 'AuthController@forgotPassword',
        'middleware' => ['guest', 'csrf'],
        'name'       => 'forgot-password.submit',
    ],

    // =========================================================================
    // Dashboard
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/',
        'handler'    => 'DashboardController@index',
        'middleware' => ['auth'],
        'name'       => 'home',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/dashboard',
        'handler'    => 'DashboardController@index',
        'middleware' => ['auth'],
        'name'       => 'dashboard',
    ],

    // =========================================================================
    // Subjects
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/subjects',
        'handler'    => 'SubjectController@index',
        'middleware' => ['auth'],
        'name'       => 'subjects.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/subjects/{id}',
        'handler'    => 'SubjectController@view',
        'middleware' => ['auth'],
        'name'       => 'subjects.view',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/subjects/{id}/topics/{topic_id}',
        'handler'    => 'SubjectController@topic',
        'middleware' => ['auth'],
        'name'       => 'subjects.topic',
    ],

    // =========================================================================
    // Study Sessions
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/study',
        'handler'    => 'StudyController@index',
        'middleware' => ['auth'],
        'name'       => 'study.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/study/session',
        'handler'    => 'StudyController@session',
        'middleware' => ['auth'],
        'name'       => 'study.session',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/study/session',
        'handler'    => 'StudyController@startSession',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'study.session.start',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/study/review',
        'handler'    => 'StudyController@review',
        'middleware' => ['auth'],
        'name'       => 'study.review',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/study/review',
        'handler'    => 'StudyController@saveReview',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'study.review.save',
    ],

    // =========================================================================
    // Writing
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/writing',
        'handler'    => 'WritingController@index',
        'middleware' => ['auth'],
        'name'       => 'writing.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/writing/editor',
        'handler'    => 'WritingController@editor',
        'middleware' => ['auth'],
        'name'       => 'writing.editor',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/writing/editor/{id}',
        'handler'    => 'WritingController@editor',
        'middleware' => ['auth'],
        'name'       => 'writing.editor.edit',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/writing/save',
        'handler'    => 'WritingController@save',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'writing.save',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/writing/review/{id}',
        'handler'    => 'WritingController@review',
        'middleware' => ['auth'],
        'name'       => 'writing.review',
    ],

    // =========================================================================
    // Flashcards
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/flashcards',
        'handler'    => 'FlashcardController@index',
        'middleware' => ['auth'],
        'name'       => 'flashcards.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/flashcards/create',
        'handler'    => 'FlashcardController@create',
        'middleware' => ['auth'],
        'name'       => 'flashcards.create',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/flashcards/save',
        'handler'    => 'FlashcardController@save',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'flashcards.save',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/flashcards/deck/{id}',
        'handler'    => 'FlashcardController@deck',
        'middleware' => ['auth'],
        'name'       => 'flashcards.deck',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/flashcards/study/{id}',
        'handler'    => 'FlashcardController@study',
        'middleware' => ['auth'],
        'name'       => 'flashcards.study',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/flashcards/study/{id}',
        'handler'    => 'FlashcardController@saveStudyProgress',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'flashcards.study.save',
    ],

    // =========================================================================
    // Quizzes
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/quiz',
        'handler'    => 'QuizController@index',
        'middleware' => ['auth'],
        'name'       => 'quiz.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/quiz/generate',
        'handler'    => 'QuizController@generate',
        'middleware' => ['auth'],
        'name'       => 'quiz.generate',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/quiz/generate',
        'handler'    => 'QuizController@generateQuiz',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'quiz.generate.submit',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/quiz/take/{id}',
        'handler'    => 'QuizController@take',
        'middleware' => ['auth'],
        'name'       => 'quiz.take',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/quiz/submit/{id}',
        'handler'    => 'QuizController@submit',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'quiz.submit',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/quiz/results/{id}',
        'handler'    => 'QuizController@results',
        'middleware' => ['auth'],
        'name'       => 'quiz.results',
    ],

    // =========================================================================
    // Notes
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/notes',
        'handler'    => 'NoteController@index',
        'middleware' => ['auth'],
        'name'       => 'notes.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/notes/create',
        'handler'    => 'NoteController@create',
        'middleware' => ['auth'],
        'name'       => 'notes.create',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/notes/save',
        'handler'    => 'NoteController@save',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'notes.save',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/notes/{id}',
        'handler'    => 'NoteController@view',
        'middleware' => ['auth'],
        'name'       => 'notes.view',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/notes/delete/{id}',
        'handler'    => 'NoteController@delete',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'notes.delete',
    ],

    // =========================================================================
    // Progress Tracking
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/progress',
        'handler'    => 'ProgressController@index',
        'middleware' => ['auth'],
        'name'       => 'progress.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/progress/{subject}',
        'handler'    => 'ProgressController@detail',
        'middleware' => ['auth'],
        'name'       => 'progress.detail',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/api/progress/data',
        'handler'    => 'ProgressController@dataApi',
        'middleware' => ['auth'],
        'name'       => 'progress.data',
    ],

    // =========================================================================
    // Settings
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/settings',
        'handler'    => 'SettingsController@index',
        'middleware' => ['auth'],
        'name'       => 'settings.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/settings/profile',
        'handler'    => 'SettingsController@profile',
        'middleware' => ['auth'],
        'name'       => 'settings.profile',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/settings/preferences',
        'handler'    => 'SettingsController@preferences',
        'middleware' => ['auth'],
        'name'       => 'settings.preferences',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/settings/save',
        'handler'    => 'SettingsController@save',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'settings.save',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/settings/profile',
        'handler'    => 'SettingsController@saveProfile',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'settings.profile.save',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/settings/preferences',
        'handler'    => 'SettingsController@savePreferences',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'settings.preferences.save',
    ],

    // =========================================================================
    // Study Planner
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/planner',
        'handler'    => 'PlannerController@index',
        'middleware' => ['auth'],
        'name'       => 'planner.index',
    ],
    [
        'method'     => 'GET',
        'pattern'    => '/planner/create',
        'handler'    => 'PlannerController@create',
        'middleware' => ['auth'],
        'name'       => 'planner.create',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/planner/save',
        'handler'    => 'PlannerController@save',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'planner.save',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/planner/delete/{id}',
        'handler'    => 'PlannerController@delete',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'planner.delete',
    ],

    // =========================================================================
    // AI Chat
    // =========================================================================

    [
        'method'     => 'POST',
        'pattern'    => '/api/ai/chat',
        'handler'    => 'AiController@chat',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'ai.chat',
    ],

    // =========================================================================
    // Writing Integrity
    // =========================================================================

    [
        'method'     => 'GET',
        'pattern'    => '/integrity',
        'handler'    => 'IntegrityController@index',
        'middleware' => ['auth'],
        'name'       => 'integrity.index',
    ],
    [
        'method'     => 'POST',
        'pattern'    => '/integrity/check',
        'handler'    => 'IntegrityController@check',
        'middleware' => ['auth', 'csrf'],
        'name'       => 'integrity.check',
    ],

];
