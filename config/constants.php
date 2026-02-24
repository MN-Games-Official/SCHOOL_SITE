<?php
/**
 * ============================================================================
 * StudyFlow - Application Constants
 * Student Self-Teaching App
 *
 * Defines application-wide constants for subjects, difficulty levels,
 * quiz types, writing types, and other enumerated values used throughout
 * the application.
 * ============================================================================
 */

// -------------------------------------------------------------------------
// Subjects
// -------------------------------------------------------------------------

/**
 * Available subjects for study.
 * Each subject has an ID, name, icon (emoji), color theme, and description.
 */
define('SUBJECTS', [
    [
        'id'          => 'math',
        'name'        => 'Math',
        'icon'        => '📐',
        'color'       => 'blue',
        'description' => 'Algebra, Geometry, Calculus, Statistics, and more',
    ],
    [
        'id'          => 'science',
        'name'        => 'Science',
        'icon'        => '🔬',
        'color'       => 'green',
        'description' => 'Physics, Chemistry, Biology, and Earth Science',
    ],
    [
        'id'          => 'english',
        'name'        => 'English',
        'icon'        => '📖',
        'color'       => 'red',
        'description' => 'Grammar, Literature, Vocabulary, and Comprehension',
    ],
    [
        'id'          => 'history',
        'name'        => 'History',
        'icon'        => '🏛️',
        'color'       => 'amber',
        'description' => 'World History, Civilizations, and Historical Events',
    ],
    [
        'id'          => 'geography',
        'name'        => 'Geography',
        'icon'        => '🌍',
        'color'       => 'teal',
        'description' => 'Physical Geography, Human Geography, and Maps',
    ],
    [
        'id'          => 'computer_science',
        'name'        => 'Computer Science',
        'icon'        => '💻',
        'color'       => 'indigo',
        'description' => 'Programming, Algorithms, Data Structures, and Web Development',
    ],
    [
        'id'          => 'art',
        'name'        => 'Art',
        'icon'        => '🎨',
        'color'       => 'pink',
        'description' => 'Visual Arts, Art History, Techniques, and Design',
    ],
    [
        'id'          => 'music',
        'name'        => 'Music',
        'icon'        => '🎵',
        'color'       => 'purple',
        'description' => 'Music Theory, Instruments, Composition, and History',
    ],
    [
        'id'          => 'physical_education',
        'name'        => 'Physical Education',
        'icon'        => '⚽',
        'color'       => 'orange',
        'description' => 'Health, Fitness, Sports Science, and Nutrition',
    ],
    [
        'id'          => 'foreign_languages',
        'name'        => 'Foreign Languages',
        'icon'        => '🗣️',
        'color'       => 'cyan',
        'description' => 'Spanish, French, German, Mandarin, and more',
    ],
]);

/**
 * Quick lookup: subject IDs mapped to names.
 */
define('SUBJECT_NAMES', array_column(SUBJECTS, 'name', 'id'));

/**
 * Quick lookup: subject IDs mapped to icons.
 */
define('SUBJECT_ICONS', array_column(SUBJECTS, 'icon', 'id'));

/**
 * Quick lookup: subject IDs mapped to colors.
 */
define('SUBJECT_COLORS', array_column(SUBJECTS, 'color', 'id'));

// -------------------------------------------------------------------------
// Difficulty Levels
// -------------------------------------------------------------------------

/**
 * Difficulty levels for quizzes, flashcards, and study materials.
 */
define('DIFFICULTY_LEVELS', [
    [
        'id'          => 'beginner',
        'name'        => 'Beginner',
        'label'       => 'Beginner',
        'color'       => 'green',
        'icon'        => '🌱',
        'description' => 'Foundational concepts and basic understanding',
        'order'       => 1,
    ],
    [
        'id'          => 'elementary',
        'name'        => 'Elementary',
        'label'       => 'Elementary',
        'color'       => 'lime',
        'icon'        => '🌿',
        'description' => 'Building on basics with simple applications',
        'order'       => 2,
    ],
    [
        'id'          => 'intermediate',
        'name'        => 'Intermediate',
        'label'       => 'Intermediate',
        'color'       => 'yellow',
        'icon'        => '📚',
        'description' => 'Moderate complexity requiring deeper understanding',
        'order'       => 3,
    ],
    [
        'id'          => 'advanced',
        'name'        => 'Advanced',
        'label'       => 'Advanced',
        'color'       => 'orange',
        'icon'        => '🎯',
        'description' => 'Complex problems and advanced applications',
        'order'       => 4,
    ],
    [
        'id'          => 'expert',
        'name'        => 'Expert',
        'label'       => 'Expert',
        'color'       => 'red',
        'icon'        => '🏆',
        'description' => 'Mastery-level challenges and critical analysis',
        'order'       => 5,
    ],
]);

/**
 * Quick lookup: difficulty IDs mapped to names.
 */
define('DIFFICULTY_NAMES', array_column(DIFFICULTY_LEVELS, 'name', 'id'));

// -------------------------------------------------------------------------
// Quiz Types
// -------------------------------------------------------------------------

/**
 * Types of quiz questions available.
 */
define('QUIZ_TYPES', [
    [
        'id'          => 'multiple_choice',
        'name'        => 'Multiple Choice',
        'icon'        => '☑️',
        'description' => 'Choose the correct answer from several options',
    ],
    [
        'id'          => 'true_false',
        'name'        => 'True / False',
        'icon'        => '✅',
        'description' => 'Determine if a statement is true or false',
    ],
    [
        'id'          => 'short_answer',
        'name'        => 'Short Answer',
        'icon'        => '✏️',
        'description' => 'Write a brief answer to the question',
    ],
    [
        'id'          => 'fill_blank',
        'name'        => 'Fill in the Blank',
        'icon'        => '📝',
        'description' => 'Complete the sentence with the correct word or phrase',
    ],
    [
        'id'          => 'matching',
        'name'        => 'Matching',
        'icon'        => '🔗',
        'description' => 'Match items from two columns',
    ],
    [
        'id'          => 'ordering',
        'name'        => 'Ordering',
        'icon'        => '🔢',
        'description' => 'Arrange items in the correct sequence',
    ],
    [
        'id'          => 'essay',
        'name'        => 'Essay',
        'icon'        => '📄',
        'description' => 'Write an extended response to a prompt',
    ],
]);

/**
 * Quick lookup: quiz type IDs mapped to names.
 */
define('QUIZ_TYPE_NAMES', array_column(QUIZ_TYPES, 'name', 'id'));

// -------------------------------------------------------------------------
// Writing Types
// -------------------------------------------------------------------------

/**
 * Types of writing projects students can create.
 */
define('WRITING_TYPES', [
    [
        'id'          => 'essay',
        'name'        => 'Essay',
        'icon'        => '📝',
        'description' => 'Structured argument or analysis on a topic',
        'min_words'   => 250,
        'max_words'   => 5000,
        'structure'   => ['Introduction', 'Body Paragraphs', 'Conclusion'],
    ],
    [
        'id'          => 'report',
        'name'        => 'Report',
        'icon'        => '📊',
        'description' => 'Factual presentation of information and findings',
        'min_words'   => 300,
        'max_words'   => 5000,
        'structure'   => ['Title Page', 'Introduction', 'Methodology', 'Findings', 'Conclusion', 'References'],
    ],
    [
        'id'          => 'creative_writing',
        'name'        => 'Creative Writing',
        'icon'        => '✨',
        'description' => 'Stories, poems, scripts, and imaginative pieces',
        'min_words'   => 100,
        'max_words'   => 10000,
        'structure'   => [],
    ],
    [
        'id'          => 'research_paper',
        'name'        => 'Research Paper',
        'icon'        => '🔍',
        'description' => 'In-depth investigation and analysis of a topic',
        'min_words'   => 500,
        'max_words'   => 10000,
        'structure'   => ['Abstract', 'Introduction', 'Literature Review', 'Methodology', 'Results', 'Discussion', 'Conclusion', 'References'],
    ],
    [
        'id'          => 'summary',
        'name'        => 'Summary',
        'icon'        => '📋',
        'description' => 'Condensed overview of a larger work or topic',
        'min_words'   => 50,
        'max_words'   => 1000,
        'structure'   => ['Main Ideas', 'Supporting Details', 'Conclusion'],
    ],
    [
        'id'          => 'review',
        'name'        => 'Review',
        'icon'        => '⭐',
        'description' => 'Critical evaluation of a book, article, or work',
        'min_words'   => 200,
        'max_words'   => 3000,
        'structure'   => ['Introduction', 'Summary', 'Analysis', 'Evaluation', 'Conclusion'],
    ],
]);

/**
 * Quick lookup: writing type IDs mapped to names.
 */
define('WRITING_TYPE_NAMES', array_column(WRITING_TYPES, 'name', 'id'));

// -------------------------------------------------------------------------
// Note Colors
// -------------------------------------------------------------------------

/**
 * Available colors for sticky notes / note cards.
 */
define('NOTE_COLORS', [
    [
        'id'    => 'yellow',
        'name'  => 'Yellow',
        'hex'   => '#FEF3C7',
        'text'  => '#92400E',
        'class' => 'bg-amber-100 text-amber-800',
    ],
    [
        'id'    => 'blue',
        'name'  => 'Blue',
        'hex'   => '#DBEAFE',
        'text'  => '#1E40AF',
        'class' => 'bg-blue-100 text-blue-800',
    ],
    [
        'id'    => 'green',
        'name'  => 'Green',
        'hex'   => '#D1FAE5',
        'text'  => '#065F46',
        'class' => 'bg-green-100 text-green-800',
    ],
    [
        'id'    => 'pink',
        'name'  => 'Pink',
        'hex'   => '#FCE7F3',
        'text'  => '#9D174D',
        'class' => 'bg-pink-100 text-pink-800',
    ],
    [
        'id'    => 'purple',
        'name'  => 'Purple',
        'hex'   => '#EDE9FE',
        'text'  => '#5B21B6',
        'class' => 'bg-purple-100 text-purple-800',
    ],
    [
        'id'    => 'orange',
        'name'  => 'Orange',
        'hex'   => '#FFEDD5',
        'text'  => '#9A3412',
        'class' => 'bg-orange-100 text-orange-800',
    ],
    [
        'id'    => 'teal',
        'name'  => 'Teal',
        'hex'   => '#CCFBF1',
        'text'  => '#115E59',
        'class' => 'bg-teal-100 text-teal-800',
    ],
    [
        'id'    => 'white',
        'name'  => 'White',
        'hex'   => '#FFFFFF',
        'text'  => '#1F2937',
        'class' => 'bg-white text-gray-800',
    ],
]);

/**
 * Quick lookup: color IDs mapped to names.
 */
define('NOTE_COLOR_NAMES', array_column(NOTE_COLORS, 'name', 'id'));

// -------------------------------------------------------------------------
// Study Modes
// -------------------------------------------------------------------------

/**
 * Available study modes / techniques.
 */
define('STUDY_MODES', [
    [
        'id'          => 'reading',
        'name'        => 'Reading',
        'icon'        => '📖',
        'description' => 'Read through study materials and textbook content',
    ],
    [
        'id'          => 'practice',
        'name'        => 'Practice',
        'icon'        => '✍️',
        'description' => 'Work through practice problems and exercises',
    ],
    [
        'id'          => 'review',
        'name'        => 'Review',
        'icon'        => '🔄',
        'description' => 'Review previously studied material',
    ],
    [
        'id'          => 'flashcards',
        'name'        => 'Flashcards',
        'icon'        => '🃏',
        'description' => 'Study using spaced repetition flashcards',
    ],
    [
        'id'          => 'quiz',
        'name'        => 'Quiz',
        'icon'        => '📝',
        'description' => 'Test your knowledge with quizzes',
    ],
    [
        'id'          => 'notes',
        'name'        => 'Note Taking',
        'icon'        => '📒',
        'description' => 'Create and organize study notes',
    ],
    [
        'id'          => 'pomodoro',
        'name'        => 'Pomodoro',
        'icon'        => '🍅',
        'description' => 'Focused study with timed intervals',
    ],
]);

// -------------------------------------------------------------------------
// Planner Event Types
// -------------------------------------------------------------------------

/**
 * Types of planner events.
 */
define('PLANNER_EVENT_TYPES', [
    [
        'id'    => 'study',
        'name'  => 'Study Session',
        'icon'  => '📚',
        'color' => 'blue',
    ],
    [
        'id'    => 'assignment',
        'name'  => 'Assignment',
        'icon'  => '📝',
        'color' => 'red',
    ],
    [
        'id'    => 'review',
        'name'  => 'Review',
        'icon'  => '🔄',
        'color' => 'green',
    ],
    [
        'id'    => 'quiz',
        'name'  => 'Quiz Prep',
        'icon'  => '✅',
        'color' => 'purple',
    ],
    [
        'id'    => 'reading',
        'name'  => 'Reading',
        'icon'  => '📖',
        'color' => 'amber',
    ],
    [
        'id'    => 'project',
        'name'  => 'Project',
        'icon'  => '🎯',
        'color' => 'orange',
    ],
    [
        'id'    => 'break',
        'name'  => 'Break',
        'icon'  => '☕',
        'color' => 'gray',
    ],
]);

// -------------------------------------------------------------------------
// Progress & Achievement Constants
// -------------------------------------------------------------------------

/**
 * Study streak milestones for achievements.
 */
define('STREAK_MILESTONES', [3, 7, 14, 30, 60, 90, 180, 365]);

/**
 * Experience points awarded for various activities.
 */
define('XP_REWARDS', [
    'study_session_complete'  => 10,
    'quiz_complete'           => 15,
    'quiz_perfect_score'      => 25,
    'flashcard_deck_complete' => 10,
    'writing_submitted'       => 20,
    'note_created'            => 5,
    'daily_login'             => 5,
    'streak_maintained'       => 10,
    'subject_mastered'        => 100,
]);

/**
 * Level thresholds (XP required for each level).
 */
define('LEVEL_THRESHOLDS', [
    1  => 0,
    2  => 50,
    3  => 150,
    4  => 300,
    5  => 500,
    6  => 750,
    7  => 1050,
    8  => 1400,
    9  => 1800,
    10 => 2250,
    11 => 2750,
    12 => 3300,
    13 => 3900,
    14 => 4550,
    15 => 5250,
    16 => 6000,
    17 => 6800,
    18 => 7650,
    19 => 8550,
    20 => 9500,
]);

// -------------------------------------------------------------------------
// Date & Time Formats
// -------------------------------------------------------------------------

define('DATE_FORMAT_DISPLAY', 'M j, Y');
define('DATE_FORMAT_INPUT', 'Y-m-d');
define('TIME_FORMAT_DISPLAY', 'g:i A');
define('DATETIME_FORMAT_DISPLAY', 'M j, Y g:i A');
define('DATETIME_FORMAT_STORAGE', 'Y-m-d H:i:s');

// -------------------------------------------------------------------------
// Pagination & Limits
// -------------------------------------------------------------------------

define('DEFAULT_PER_PAGE', 12);
define('MAX_PER_PAGE', 50);

// -------------------------------------------------------------------------
// Content Limits
// -------------------------------------------------------------------------

define('MAX_TITLE_LENGTH', 200);
define('MAX_DESCRIPTION_LENGTH', 1000);
define('MAX_NOTE_LENGTH', 50000);
define('MAX_TAG_LENGTH', 50);
define('MAX_TAGS_PER_ITEM', 10);

// -------------------------------------------------------------------------
// User Preferences Defaults
// -------------------------------------------------------------------------

define('DEFAULT_PREFERENCES', [
    'theme'               => 'light',
    'font_size'           => 'medium',
    'study_reminder'      => true,
    'daily_goal_minutes'  => 60,
    'show_streak'         => true,
    'sound_effects'       => true,
    'auto_save'           => true,
    'pomodoro_duration'   => 25,
    'break_duration'      => 5,
    'notifications'       => true,
]);
