<?php
/**
 * ============================================================================
 * WritingService - Writing & Essay Workspace
 * StudyFlow - Student Self-Teaching App
 *
 * Manages student writings (essays, reports, creative pieces), supports
 * auto-save with version history, text analysis (readability, word count),
 * writing prompts, templates, and export.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class WritingService
{
    private FileStorage $storage;

    private const COLLECTION_WRITINGS  = 'writings';
    private const COLLECTION_VERSIONS  = 'writing_versions';
    private const COLLECTION_PROMPTS   = 'writing_prompts';
    private const COLLECTION_TEMPLATES = 'writing_templates';

    /** @var int Auto-save interval minimum (seconds) to prevent spam */
    private const AUTOSAVE_THROTTLE = 10;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a new writing piece.
     *
     * @param string $userId
     * @param array  $data   Keys: title, type (essay|report|creative|reflection), subject, content
     * @return array The created writing
     * @throws InvalidArgumentException On missing data
     */
    public function create(string $userId, array $data): array
    {
        if ($userId === '') {
            throw new InvalidArgumentException('WritingService: userId is required.');
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('WritingService: Title is required.');
        }

        $allowedTypes = ['essay', 'report', 'creative', 'reflection', 'notes', 'research'];
        $type         = $data['type'] ?? 'essay';
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'essay';
        }

        $writingId = $this->storage->generateId();
        $now       = date('c');
        $content   = $data['content'] ?? '';

        $writing = [
            'id'          => $writingId,
            'user_id'     => $userId,
            'title'       => $title,
            'type'        => $type,
            'subject'     => $data['subject'] ?? '',
            'content'     => $content,
            'word_count'  => $this->countWords($content),
            'status'      => 'draft',
            'tags'        => $data['tags'] ?? [],
            'prompt_id'   => $data['prompt_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'auto_saved'  => false,
            'submitted'   => false,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION_WRITINGS, $writingId, $writing);

        // Save initial version
        $this->saveVersion($writingId, $content, 'Initial creation');

        return $writing;
    }

    /**
     * Update (save) a writing piece.
     *
     * @param string $writingId
     * @param array  $data      Keys to update (title, content, tags, subject, type)
     * @return array Updated writing
     * @throws RuntimeException If not found
     */
    public function update(string $writingId, array $data): array
    {
        $writing = $this->storage->read(self::COLLECTION_WRITINGS, $writingId);
        if ($writing === null) {
            throw new RuntimeException('WritingService: Writing not found.');
        }

        $allowed = ['title', 'content', 'tags', 'subject', 'type', 'status'];
        $update  = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $update[$key] = $value;
            }
        }

        if (isset($update['content'])) {
            $update['word_count'] = $this->countWords($update['content']);
            // Save version
            $this->saveVersion($writingId, $update['content'], 'Manual save');
        }

        $update['updated_at'] = date('c');
        $this->storage->update(self::COLLECTION_WRITINGS, $writingId, $update);

        return $this->get($writingId);
    }

    /**
     * Get a writing piece by ID.
     *
     * @param string $writingId
     * @return array
     * @throws RuntimeException If not found
     */
    public function get(string $writingId): array
    {
        $writing = $this->storage->read(self::COLLECTION_WRITINGS, $writingId);
        if ($writing === null) {
            throw new RuntimeException('WritingService: Writing not found.');
        }
        return $writing;
    }

    /**
     * Get all writings for a user with optional filters.
     *
     * Supported filters: type, subject, status, search, limit, offset
     *
     * @param string $userId
     * @param array  $filters
     * @return array
     */
    public function getUserWritings(string $userId, array $filters = []): array
    {
        $writings = $this->storage->query(self::COLLECTION_WRITINGS, function (array $w) use ($userId, $filters) {
            if (($w['user_id'] ?? '') !== $userId) {
                return false;
            }

            if (!empty($filters['type']) && ($w['type'] ?? '') !== $filters['type']) {
                return false;
            }

            if (!empty($filters['subject']) && ($w['subject'] ?? '') !== $filters['subject']) {
                return false;
            }

            if (!empty($filters['status']) && ($w['status'] ?? '') !== $filters['status']) {
                return false;
            }

            if (!empty($filters['search'])) {
                $needle = mb_strtolower($filters['search'], 'UTF-8');
                $title  = mb_strtolower($w['title'] ?? '', 'UTF-8');
                $content = mb_strtolower($w['content'] ?? '', 'UTF-8');
                if (!str_contains($title, $needle) && !str_contains($content, $needle)) {
                    return false;
                }
            }

            return true;
        });

        // Sort by updated_at descending
        usort($writings, fn($a, $b) => strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0'));

        $offset = (int) ($filters['offset'] ?? 0);
        $limit  = (int) ($filters['limit'] ?? 50);

        return array_slice($writings, $offset, $limit);
    }

    /**
     * Delete a writing and its version history.
     *
     * @param string $writingId
     * @return bool
     * @throws RuntimeException If not found
     */
    public function delete(string $writingId): bool
    {
        $writing = $this->storage->read(self::COLLECTION_WRITINGS, $writingId);
        if ($writing === null) {
            throw new RuntimeException('WritingService: Writing not found.');
        }

        // Delete versions
        $versions = $this->storage->query(self::COLLECTION_VERSIONS, ['writing_id' => $writingId]);
        foreach ($versions as $v) {
            $this->storage->delete(self::COLLECTION_VERSIONS, $v['id'] ?? $v['_id'] ?? '');
        }

        return $this->storage->delete(self::COLLECTION_WRITINGS, $writingId);
    }

    // -------------------------------------------------------------------------
    // Auto-Save & Version History
    // -------------------------------------------------------------------------

    /**
     * Auto-save draft content. Throttled to prevent excessive writes.
     *
     * @param string $writingId
     * @param string $content
     * @return array Updated writing (partial)
     * @throws RuntimeException If not found
     */
    public function autoSave(string $writingId, string $content): array
    {
        $writing = $this->storage->read(self::COLLECTION_WRITINGS, $writingId);
        if ($writing === null) {
            throw new RuntimeException('WritingService: Writing not found.');
        }

        // Throttle: skip if last save was too recent
        $lastUpdate = strtotime($writing['updated_at'] ?? '');
        if ((time() - $lastUpdate) < self::AUTOSAVE_THROTTLE) {
            return ['status' => 'throttled', 'writing_id' => $writingId];
        }

        $wordCount = $this->countWords($content);

        $this->storage->update(self::COLLECTION_WRITINGS, $writingId, [
            'content'    => $content,
            'word_count' => $wordCount,
            'auto_saved' => true,
            'updated_at' => date('c'),
        ]);

        // Save version (auto-save)
        $this->saveVersion($writingId, $content, 'Auto-save');

        return [
            'status'     => 'saved',
            'writing_id' => $writingId,
            'word_count' => $wordCount,
            'saved_at'   => date('c'),
        ];
    }

    /**
     * Get version history for a writing piece.
     *
     * @param string $writingId
     * @return array List of versions (newest first)
     */
    public function getVersionHistory(string $writingId): array
    {
        $versions = $this->storage->query(self::COLLECTION_VERSIONS, ['writing_id' => $writingId]);

        usort($versions, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        return array_map(function (array $v) {
            return [
                'id'         => $v['id'] ?? $v['_id'] ?? '',
                'writing_id' => $v['writing_id'] ?? '',
                'word_count' => $v['word_count'] ?? 0,
                'label'      => $v['label'] ?? '',
                'created_at' => $v['created_at'] ?? '',
            ];
        }, $versions);
    }

    /**
     * Restore a previous version of a writing.
     *
     * @param string $writingId
     * @param string $versionId
     * @return array The restored writing
     * @throws RuntimeException If version not found
     */
    public function restoreVersion(string $writingId, string $versionId): array
    {
        $version = $this->storage->read(self::COLLECTION_VERSIONS, $versionId);
        if ($version === null || ($version['writing_id'] ?? '') !== $writingId) {
            throw new RuntimeException('WritingService: Version not found.');
        }

        $content = $version['content'] ?? '';

        $this->storage->update(self::COLLECTION_WRITINGS, $writingId, [
            'content'    => $content,
            'word_count' => $this->countWords($content),
            'updated_at' => date('c'),
        ]);

        $this->saveVersion($writingId, $content, 'Restored from version ' . $versionId);

        return $this->get($writingId);
    }

    // -------------------------------------------------------------------------
    // Text Analysis
    // -------------------------------------------------------------------------

    /**
     * Get the word count for a writing piece.
     *
     * @param string $writingId
     * @return array Word count details
     */
    public function getWordCount(string $writingId): array
    {
        $writing = $this->get($writingId);
        $content = $writing['content'] ?? '';

        return [
            'writing_id' => $writingId,
            'words'      => $this->countWords($content),
            'characters' => mb_strlen($content, 'UTF-8'),
            'characters_no_spaces' => mb_strlen(preg_replace('/\s+/', '', $content), 'UTF-8'),
        ];
    }

    /**
     * Analyze a writing piece for readability, structure, and statistics.
     *
     * @param string $writingId
     * @return array Analysis results
     */
    public function analyzeWriting(string $writingId): array
    {
        $writing = $this->get($writingId);
        $content = $writing['content'] ?? '';

        if (trim($content) === '') {
            return [
                'writing_id'      => $writingId,
                'word_count'      => 0,
                'sentence_count'  => 0,
                'paragraph_count' => 0,
                'readability'     => null,
                'message'         => 'No content to analyze.',
            ];
        }

        $words      = $this->countWords($content);
        $sentences  = $this->countSentences($content);
        $paragraphs = $this->countParagraphs($content);
        $syllables  = $this->estimateSyllables($content);

        // Flesch Reading Ease
        $readabilityScore = null;
        $readabilityLabel = 'N/A';
        if ($sentences > 0 && $words > 0) {
            $readabilityScore = round(
                206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words),
                1
            );
            $readabilityLabel = $this->readabilityLabel($readabilityScore);
        }

        // Average word length
        $chars = mb_strlen(preg_replace('/\s+/', '', $content), 'UTF-8');
        $avgWordLength = $words > 0 ? round($chars / $words, 1) : 0;

        // Unique words
        $wordList    = preg_split('/\s+/', mb_strtolower(trim($content), 'UTF-8'));
        $wordList    = array_filter($wordList, fn($w) => $w !== '');
        $uniqueWords = count(array_unique($wordList));
        $vocabulary  = $words > 0 ? round(($uniqueWords / $words) * 100, 1) : 0;

        return [
            'writing_id'          => $writingId,
            'word_count'          => $words,
            'sentence_count'      => $sentences,
            'paragraph_count'     => $paragraphs,
            'syllable_count'      => $syllables,
            'character_count'     => mb_strlen($content, 'UTF-8'),
            'avg_word_length'     => $avgWordLength,
            'avg_sentence_length' => $sentences > 0 ? round($words / $sentences, 1) : 0,
            'unique_words'        => $uniqueWords,
            'vocabulary_richness' => $vocabulary,
            'readability_score'   => $readabilityScore,
            'readability_label'   => $readabilityLabel,
            'estimated_read_time' => max(1, (int) ceil($words / 200)),
        ];
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    /**
     * Export a writing piece in a specified format.
     *
     * @param string $writingId
     * @param string $format    'text', 'html', or 'markdown'
     * @return array Export data with content and metadata
     * @throws InvalidArgumentException On unsupported format
     */
    public function exportWriting(string $writingId, string $format = 'text'): array
    {
        $writing = $this->get($writingId);
        $title   = $writing['title'] ?? 'Untitled';
        $content = $writing['content'] ?? '';

        $exported = match ($format) {
            'text' => $this->exportAsText($title, $content, $writing),
            'html' => $this->exportAsHtml($title, $content, $writing),
            'markdown' => $this->exportAsMarkdown($title, $content, $writing),
            default => throw new InvalidArgumentException("WritingService: Unsupported format '{$format}'."),
        };

        return [
            'writing_id' => $writingId,
            'format'     => $format,
            'filename'   => $this->sanitizeFilename($title) . '.' . $this->formatExtension($format),
            'content'    => $exported,
            'exported_at' => date('c'),
        ];
    }

    // -------------------------------------------------------------------------
    // Writing Statistics
    // -------------------------------------------------------------------------

    /**
     * Get aggregate writing statistics for a user.
     *
     * @param string $userId
     * @return array Stats
     */
    public function getWritingStats(string $userId): array
    {
        $writings = $this->storage->query(self::COLLECTION_WRITINGS, ['user_id' => $userId]);

        $totalWords   = 0;
        $totalPieces  = count($writings);
        $byType       = [];
        $byStatus     = ['draft' => 0, 'submitted' => 0];

        foreach ($writings as $w) {
            $totalWords += ($w['word_count'] ?? 0);

            $type = $w['type'] ?? 'essay';
            $byType[$type] = ($byType[$type] ?? 0) + 1;

            $status = $w['status'] ?? 'draft';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        return [
            'user_id'       => $userId,
            'total_pieces'  => $totalPieces,
            'total_words'   => $totalWords,
            'avg_words'     => $totalPieces > 0 ? (int) round($totalWords / $totalPieces) : 0,
            'by_type'       => $byType,
            'by_status'     => $byStatus,
            'drafts'        => $byStatus['draft'],
            'submitted'     => $byStatus['submitted'] ?? 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Writing Prompts
    // -------------------------------------------------------------------------

    /**
     * Get writing prompts filtered by subject and type.
     *
     * @param string $subject
     * @param string $type
     * @return array List of prompts
     */
    public function getWritingPrompts(string $subject = '', string $type = ''): array
    {
        $prompts = $this->storage->query(self::COLLECTION_PROMPTS, function (array $p) use ($subject, $type) {
            if ($subject !== '' && ($p['subject'] ?? '') !== $subject) {
                return false;
            }
            if ($type !== '' && ($p['type'] ?? '') !== $type) {
                return false;
            }
            return true;
        });

        if (empty($prompts)) {
            return $this->getDefaultPrompts($subject, $type);
        }

        return $prompts;
    }

    /**
     * Mark a writing as submitted (completed).
     *
     * @param string $writingId
     * @return array Updated writing
     * @throws RuntimeException If not found
     */
    public function submitForReview(string $writingId): array
    {
        $writing = $this->get($writingId);

        if (($writing['status'] ?? '') === 'submitted') {
            throw new RuntimeException('WritingService: Writing is already submitted.');
        }

        $this->storage->update(self::COLLECTION_WRITINGS, $writingId, [
            'status'       => 'submitted',
            'submitted'    => true,
            'submitted_at' => date('c'),
            'updated_at'   => date('c'),
        ]);

        return $this->get($writingId);
    }

    /**
     * Get available writing templates.
     *
     * @return array List of templates
     */
    public function getWritingTemplates(): array
    {
        $templates = $this->storage->read(self::COLLECTION_TEMPLATES);

        if (empty($templates)) {
            return $this->getDefaultTemplates();
        }

        return $templates;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Save a content snapshot as a version.
     */
    private function saveVersion(string $writingId, string $content, string $label = ''): void
    {
        $versionId = $this->storage->generateId();

        $this->storage->write(self::COLLECTION_VERSIONS, $versionId, [
            'id'         => $versionId,
            'writing_id' => $writingId,
            'content'    => $content,
            'word_count' => $this->countWords($content),
            'label'      => $label,
            'created_at' => date('c'),
        ]);
    }

    /**
     * Count words in text.
     */
    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        return count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Count sentences in text.
     */
    private function countSentences(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count(array_filter($sentences, fn($s) => trim($s) !== ''));
    }

    /**
     * Count paragraphs in text.
     */
    private function countParagraphs(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count(array_filter($paragraphs, fn($p) => trim($p) !== ''));
    }

    /**
     * Estimate syllable count (English approximation).
     */
    private function estimateSyllables(string $text): int
    {
        $words = preg_split('/\s+/', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        $total = 0;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if ($word === '') {
                continue;
            }
            $syllables = max(1, preg_match_all('/[aeiouy]+/', $word));
            // Subtract silent e
            if (strlen($word) > 2 && str_ends_with($word, 'e') && !str_ends_with($word, 'le')) {
                $syllables = max(1, $syllables - 1);
            }
            $total += $syllables;
        }

        return $total;
    }

    /**
     * Map Flesch Reading Ease score to a label.
     */
    private function readabilityLabel(float $score): string
    {
        if ($score >= 90) return 'Very Easy';
        if ($score >= 80) return 'Easy';
        if ($score >= 70) return 'Fairly Easy';
        if ($score >= 60) return 'Standard';
        if ($score >= 50) return 'Fairly Difficult';
        if ($score >= 30) return 'Difficult';
        return 'Very Difficult';
    }

    /**
     * Export as plain text.
     */
    private function exportAsText(string $title, string $content, array $writing): string
    {
        $output  = strtoupper($title) . "\n";
        $output .= str_repeat('=', mb_strlen($title, 'UTF-8')) . "\n\n";
        $output .= "Type: " . ucfirst($writing['type'] ?? 'essay') . "\n";
        $output .= "Date: " . date('F j, Y', strtotime($writing['created_at'] ?? 'now')) . "\n";
        $output .= "Words: " . ($writing['word_count'] ?? 0) . "\n\n";
        $output .= str_repeat('-', 40) . "\n\n";
        $output .= $content . "\n";

        return $output;
    }

    /**
     * Export as HTML.
     */
    private function exportAsHtml(string $title, string $content, array $writing): string
    {
        $safeTitle   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        $date        = date('F j, Y', strtotime($writing['created_at'] ?? 'now'));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeTitle}</title>
<style>body{font-family:Georgia,serif;max-width:800px;margin:2em auto;padding:0 1em;line-height:1.8;color:#333;}
h1{border-bottom:2px solid #333;padding-bottom:.3em;}
.meta{color:#666;font-size:.9em;margin-bottom:2em;}</style></head>
<body>
<h1>{$safeTitle}</h1>
<p class="meta">Type: {$writing['type']} | Date: {$date} | Words: {$writing['word_count']}</p>
<div class="content">{$safeContent}</div>
</body></html>
HTML;
    }

    /**
     * Export as Markdown.
     */
    private function exportAsMarkdown(string $title, string $content, array $writing): string
    {
        $date = date('F j, Y', strtotime($writing['created_at'] ?? 'now'));

        $output  = "# {$title}\n\n";
        $output .= "*Type: " . ucfirst($writing['type'] ?? 'essay') . " | Date: {$date} | Words: " . ($writing['word_count'] ?? 0) . "*\n\n";
        $output .= "---\n\n";
        $output .= $content . "\n";

        return $output;
    }

    /**
     * Sanitize a string for use as a filename.
     */
    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $name);
        $name = preg_replace('/\s+/', '_', trim($name));
        return mb_substr($name, 0, 100, 'UTF-8') ?: 'untitled';
    }

    /**
     * Map format to file extension.
     */
    private function formatExtension(string $format): string
    {
        return match ($format) {
            'html'     => 'html',
            'markdown' => 'md',
            default    => 'txt',
        };
    }

    /**
     * Get built-in default prompts if none stored.
     */
    private function getDefaultPrompts(string $subject, string $type): array
    {
        $prompts = [
            ['id' => 'p1', 'subject' => 'english',  'type' => 'essay',      'title' => 'Personal Narrative',       'prompt' => 'Write about a time when you learned something unexpected about yourself.', 'difficulty' => 'beginner'],
            ['id' => 'p2', 'subject' => 'english',  'type' => 'essay',      'title' => 'Persuasive Essay',         'prompt' => 'Should students have more control over their own education? Argue your position.', 'difficulty' => 'intermediate'],
            ['id' => 'p3', 'subject' => 'science',  'type' => 'report',     'title' => 'Scientific Observation',   'prompt' => 'Choose a natural phenomenon and write a detailed observation report.', 'difficulty' => 'intermediate'],
            ['id' => 'p4', 'subject' => 'history',  'type' => 'essay',      'title' => 'Historical Analysis',      'prompt' => 'Choose a historical event and analyze its long-term effects on society.', 'difficulty' => 'advanced'],
            ['id' => 'p5', 'subject' => 'english',  'type' => 'creative',   'title' => 'Short Story',              'prompt' => 'Write a short story that begins with the sentence: "The last light flickered and went out."', 'difficulty' => 'beginner'],
            ['id' => 'p6', 'subject' => 'science',  'type' => 'report',     'title' => 'Lab Report',               'prompt' => 'Document a simple experiment you can do at home and report your findings.', 'difficulty' => 'beginner'],
            ['id' => 'p7', 'subject' => 'english',  'type' => 'reflection', 'title' => 'Learning Reflection',      'prompt' => 'Reflect on your study habits this week. What worked well and what could improve?', 'difficulty' => 'beginner'],
            ['id' => 'p8', 'subject' => 'math',     'type' => 'essay',      'title' => 'Math in Daily Life',       'prompt' => 'Write about how mathematical concepts appear in your everyday activities.', 'difficulty' => 'intermediate'],
        ];

        return array_filter($prompts, function ($p) use ($subject, $type) {
            if ($subject !== '' && $p['subject'] !== $subject) return false;
            if ($type !== '' && $p['type'] !== $type) return false;
            return true;
        });
    }

    /**
     * Get built-in default templates.
     */
    private function getDefaultTemplates(): array
    {
        return [
            [
                'id'       => 'tpl_essay',
                'name'     => 'Five-Paragraph Essay',
                'type'     => 'essay',
                'content'  => "Introduction\n\nProvide background and state your thesis.\n\nBody Paragraph 1\n\nPresent your first main point with evidence.\n\nBody Paragraph 2\n\nPresent your second main point with evidence.\n\nBody Paragraph 3\n\nPresent your third main point with evidence.\n\nConclusion\n\nSummarize your points and restate your thesis.",
            ],
            [
                'id'       => 'tpl_report',
                'name'     => 'Research Report',
                'type'     => 'report',
                'content'  => "Title\n\nAbstract\n\nBrief summary of the report.\n\nIntroduction\n\nBackground and purpose of the research.\n\nMethods\n\nHow you conducted your research.\n\nFindings\n\nWhat you discovered.\n\nDiscussion\n\nAnalysis of your findings.\n\nConclusion\n\nSummary and implications.\n\nReferences\n\nList your sources.",
            ],
            [
                'id'       => 'tpl_reflection',
                'name'     => 'Learning Reflection',
                'type'     => 'reflection',
                'content'  => "What did I learn?\n\nDescribe the key concepts or skills you learned.\n\nWhat went well?\n\nReflect on your successes.\n\nWhat was challenging?\n\nDescribe difficulties you encountered.\n\nWhat will I do differently next time?\n\nPlan for improvement.\n\nKey Takeaways\n\nList your most important learnings.",
            ],
            [
                'id'       => 'tpl_creative',
                'name'     => 'Creative Writing',
                'type'     => 'creative',
                'content'  => "Title\n\nBegin your story here. Set the scene and introduce your characters.\n\n---\n\nDevelop your plot. Build tension and conflict.\n\n---\n\nBring your story to a resolution.",
            ],
        ];
    }
}
