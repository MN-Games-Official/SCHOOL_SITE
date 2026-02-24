<?php
/**
 * ============================================================================
 * NoteService - Notes Management
 * StudyFlow - Student Self-Teaching App
 *
 * Full-featured note-taking system with tagging, colour coding, pinning,
 * archiving, full-text search, duplication, and export capabilities.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class NoteService
{
    private FileStorage $storage;

    private const COLLECTION_NOTES = 'notes';

    /** @var string[] Allowed note colours */
    private const ALLOWED_COLORS = [
        'default', 'red', 'orange', 'yellow', 'green', 'blue',
        'purple', 'pink', 'gray', 'teal',
    ];

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
     * Create a new note.
     *
     * @param string $userId
     * @param array  $data   Keys: title, content, subject, tags (array), color
     * @return array Created note
     * @throws InvalidArgumentException On missing title
     */
    public function create(string $userId, array $data): array
    {
        if ($userId === '') {
            throw new InvalidArgumentException('NoteService: userId is required.');
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('NoteService: Note title is required.');
        }

        $color = $data['color'] ?? 'default';
        if (!in_array($color, self::ALLOWED_COLORS, true)) {
            $color = 'default';
        }

        $tags = $data['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        $tags = array_values(array_filter(array_unique($tags), fn($t) => $t !== ''));

        $noteId = $this->storage->generateId();
        $now    = date('c');

        $note = [
            'id'         => $noteId,
            'user_id'    => $userId,
            'title'      => $title,
            'content'    => $data['content'] ?? '',
            'subject'    => $data['subject'] ?? '',
            'tags'       => $tags,
            'color'      => $color,
            'pinned'     => false,
            'archived'   => false,
            'word_count' => $this->countWords($data['content'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->storage->write(self::COLLECTION_NOTES, $noteId, $note);

        return $note;
    }

    /**
     * Update a note.
     *
     * @param string $noteId
     * @param array  $data   Keys: title, content, subject, tags, color
     * @return array Updated note
     * @throws RuntimeException If not found
     */
    public function update(string $noteId, array $data): array
    {
        $note = $this->storage->read(self::COLLECTION_NOTES, $noteId);
        if ($note === null) {
            throw new RuntimeException('NoteService: Note not found.');
        }

        $allowed = ['title', 'content', 'subject', 'tags', 'color'];
        $update  = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            if ($key === 'title') {
                $value = trim($value);
                if ($value === '') {
                    throw new InvalidArgumentException('NoteService: Title cannot be empty.');
                }
            }

            if ($key === 'color' && !in_array($value, self::ALLOWED_COLORS, true)) {
                $value = 'default';
            }

            if ($key === 'tags') {
                if (is_string($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $value = array_values(array_filter(array_unique($value), fn($t) => $t !== ''));
            }

            $update[$key] = $value;
        }

        if (isset($update['content'])) {
            $update['word_count'] = $this->countWords($update['content']);
        }

        $update['updated_at'] = date('c');
        $this->storage->update(self::COLLECTION_NOTES, $noteId, $update);

        return $this->get($noteId);
    }

    /**
     * Get a note by ID.
     *
     * @param string $noteId
     * @return array
     * @throws RuntimeException If not found
     */
    public function get(string $noteId): array
    {
        $note = $this->storage->read(self::COLLECTION_NOTES, $noteId);
        if ($note === null) {
            throw new RuntimeException('NoteService: Note not found.');
        }
        return $note;
    }

    /**
     * Get all notes for a user with optional filtering.
     *
     * Filters: subject, tag, search, pinned (bool), archived (bool), color,
     *          sort (updated_at|created_at|title), limit, offset
     *
     * @param string $userId
     * @param array  $filters
     * @return array
     */
    public function getUserNotes(string $userId, array $filters = []): array
    {
        $notes = $this->storage->query(self::COLLECTION_NOTES, function (array $n) use ($userId, $filters) {
            if (($n['user_id'] ?? '') !== $userId) {
                return false;
            }

            // By default, exclude archived notes unless specifically requested
            $showArchived = $filters['archived'] ?? false;
            if (!$showArchived && ($n['archived'] ?? false)) {
                return false;
            }
            if ($showArchived === true && !($n['archived'] ?? false)) {
                return false;
            }

            if (!empty($filters['subject']) && ($n['subject'] ?? '') !== $filters['subject']) {
                return false;
            }

            if (!empty($filters['tag'])) {
                $tags = $n['tags'] ?? [];
                if (!in_array($filters['tag'], $tags, true)) {
                    return false;
                }
            }

            if (!empty($filters['color']) && ($n['color'] ?? 'default') !== $filters['color']) {
                return false;
            }

            if (isset($filters['pinned'])) {
                if (($n['pinned'] ?? false) !== (bool) $filters['pinned']) {
                    return false;
                }
            }

            if (!empty($filters['search'])) {
                $needle  = mb_strtolower($filters['search'], 'UTF-8');
                $title   = mb_strtolower($n['title'] ?? '', 'UTF-8');
                $content = mb_strtolower($n['content'] ?? '', 'UTF-8');
                $tags    = mb_strtolower(implode(' ', $n['tags'] ?? []), 'UTF-8');
                if (!str_contains($title, $needle) && !str_contains($content, $needle) && !str_contains($tags, $needle)) {
                    return false;
                }
            }

            return true;
        });

        // Sort: pinned first, then by sort field
        $sortField = $filters['sort'] ?? 'updated_at';
        usort($notes, function ($a, $b) use ($sortField) {
            // Pinned notes always on top
            $aPinned = ($a['pinned'] ?? false) ? 1 : 0;
            $bPinned = ($b['pinned'] ?? false) ? 1 : 0;
            if ($aPinned !== $bPinned) {
                return $bPinned - $aPinned;
            }

            if ($sortField === 'title') {
                return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
            }

            return strtotime($b[$sortField] ?? '0') - strtotime($a[$sortField] ?? '0');
        });

        $offset = (int) ($filters['offset'] ?? 0);
        $limit  = (int) ($filters['limit'] ?? 100);

        return array_slice($notes, $offset, $limit);
    }

    /**
     * Delete a note.
     *
     * @param string $noteId
     * @return bool
     * @throws RuntimeException If not found
     */
    public function delete(string $noteId): bool
    {
        $note = $this->storage->read(self::COLLECTION_NOTES, $noteId);
        if ($note === null) {
            throw new RuntimeException('NoteService: Note not found.');
        }
        return $this->storage->delete(self::COLLECTION_NOTES, $noteId);
    }

    // -------------------------------------------------------------------------
    // Search & Discovery
    // -------------------------------------------------------------------------

    /**
     * Full-text search across a user's notes.
     *
     * @param string $userId
     * @param string $query
     * @return array Matching notes
     */
    public function search(string $userId, string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        $allNotes = $this->storage->query(self::COLLECTION_NOTES, ['user_id' => $userId]);
        $needle   = mb_strtolower(trim($query), 'UTF-8');
        $results  = [];

        foreach ($allNotes as $note) {
            $score = 0;
            $title   = mb_strtolower($note['title'] ?? '', 'UTF-8');
            $content = mb_strtolower($note['content'] ?? '', 'UTF-8');
            $tags    = mb_strtolower(implode(' ', $note['tags'] ?? []), 'UTF-8');

            if (str_contains($title, $needle)) $score += 3;
            if (str_contains($tags, $needle))  $score += 2;
            if (str_contains($content, $needle)) $score += 1;

            if ($score > 0) {
                $note['_relevance'] = $score;
                $results[] = $note;
            }
        }

        usort($results, fn($a, $b) => $b['_relevance'] <=> $a['_relevance']);

        return $results;
    }

    /**
     * Get notes by a specific tag.
     *
     * @param string $userId
     * @param string $tag
     * @return array
     */
    public function getByTag(string $userId, string $tag): array
    {
        return $this->getUserNotes($userId, ['tag' => $tag]);
    }

    /**
     * Get notes by subject.
     *
     * @param string $userId
     * @param string $subject
     * @return array
     */
    public function getBySubject(string $userId, string $subject): array
    {
        return $this->getUserNotes($userId, ['subject' => $subject]);
    }

    /**
     * Get all unique tags used by a user.
     *
     * @param string $userId
     * @return array Tag list with counts
     */
    public function getTags(string $userId): array
    {
        $notes = $this->storage->query(self::COLLECTION_NOTES, ['user_id' => $userId]);
        $tagCounts = [];

        foreach ($notes as $note) {
            foreach ($note['tags'] ?? [] as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($tagCounts);

        $result = [];
        foreach ($tagCounts as $tag => $count) {
            $result[] = ['tag' => $tag, 'count' => $count];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Pin / Archive / Duplicate
    // -------------------------------------------------------------------------

    /**
     * Toggle pin status of a note.
     *
     * @param string $noteId
     * @return array Updated note
     * @throws RuntimeException If not found
     */
    public function pin(string $noteId): array
    {
        $note = $this->get($noteId);
        $pinned = !($note['pinned'] ?? false);

        $this->storage->update(self::COLLECTION_NOTES, $noteId, [
            'pinned'     => $pinned,
            'updated_at' => date('c'),
        ]);

        return $this->get($noteId);
    }

    /**
     * Toggle archive status of a note.
     *
     * @param string $noteId
     * @return array Updated note
     * @throws RuntimeException If not found
     */
    public function archive(string $noteId): array
    {
        $note = $this->get($noteId);
        $archived = !($note['archived'] ?? false);

        $this->storage->update(self::COLLECTION_NOTES, $noteId, [
            'archived'   => $archived,
            'pinned'     => $archived ? false : ($note['pinned'] ?? false),
            'updated_at' => date('c'),
        ]);

        return $this->get($noteId);
    }

    /**
     * Duplicate a note.
     *
     * @param string $noteId
     * @return array The new note
     * @throws RuntimeException If not found
     */
    public function duplicate(string $noteId): array
    {
        $original = $this->get($noteId);

        return $this->create($original['user_id'] ?? '', [
            'title'   => ($original['title'] ?? 'Untitled') . ' (Copy)',
            'content' => $original['content'] ?? '',
            'subject' => $original['subject'] ?? '',
            'tags'    => $original['tags'] ?? [],
            'color'   => $original['color'] ?? 'default',
        ]);
    }

    // -------------------------------------------------------------------------
    // Export & Statistics
    // -------------------------------------------------------------------------

    /**
     * Export all of a user's notes.
     *
     * @param string $userId
     * @param string $format 'json', 'text', or 'markdown'
     * @return array Export data
     */
    public function exportNotes(string $userId, string $format = 'json'): array
    {
        $notes = $this->getUserNotes($userId, ['limit' => 10000]);

        $content = match ($format) {
            'json'     => json_encode($notes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'text'     => $this->notesToText($notes),
            'markdown' => $this->notesToMarkdown($notes),
            default    => json_encode($notes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        };

        $ext = match ($format) {
            'text'     => 'txt',
            'markdown' => 'md',
            default    => 'json',
        };

        return [
            'format'      => $format,
            'filename'    => 'notes_export_' . date('Y-m-d') . '.' . $ext,
            'content'     => $content,
            'note_count'  => count($notes),
            'exported_at' => date('c'),
        ];
    }

    /**
     * Get note statistics for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getNoteStats(string $userId): array
    {
        $notes = $this->storage->query(self::COLLECTION_NOTES, ['user_id' => $userId]);

        $totalWords   = 0;
        $pinned       = 0;
        $archived     = 0;
        $bySubject    = [];
        $byColor      = [];

        foreach ($notes as $note) {
            $totalWords += ($note['word_count'] ?? 0);
            if ($note['pinned'] ?? false) $pinned++;
            if ($note['archived'] ?? false) $archived++;

            $subject = $note['subject'] ?? 'unclassified';
            $bySubject[$subject] = ($bySubject[$subject] ?? 0) + 1;

            $color = $note['color'] ?? 'default';
            $byColor[$color] = ($byColor[$color] ?? 0) + 1;
        }

        return [
            'user_id'     => $userId,
            'total_notes' => count($notes),
            'total_words' => $totalWords,
            'pinned'      => $pinned,
            'archived'    => $archived,
            'active'      => count($notes) - $archived,
            'by_subject'  => $bySubject,
            'by_color'    => $byColor,
            'unique_tags' => count($this->getTags($userId)),
        ];
    }

    /**
     * Get recently modified notes.
     *
     * @param string $userId
     * @param int    $limit
     * @return array
     */
    public function getRecentNotes(string $userId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return $this->getUserNotes($userId, ['limit' => $limit, 'sort' => 'updated_at']);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

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
     * Convert notes array to plain text.
     */
    private function notesToText(array $notes): string
    {
        $output = "NOTES EXPORT\n";
        $output .= "Exported: " . date('F j, Y g:i A') . "\n";
        $output .= "Total Notes: " . count($notes) . "\n";
        $output .= str_repeat('=', 60) . "\n\n";

        foreach ($notes as $note) {
            $output .= strtoupper($note['title'] ?? 'Untitled') . "\n";
            $output .= str_repeat('-', mb_strlen($note['title'] ?? 'Untitled', 'UTF-8')) . "\n";
            if (!empty($note['subject'])) {
                $output .= "Subject: " . $note['subject'] . "\n";
            }
            if (!empty($note['tags'])) {
                $output .= "Tags: " . implode(', ', $note['tags']) . "\n";
            }
            $output .= "Date: " . date('Y-m-d', strtotime($note['created_at'] ?? 'now')) . "\n\n";
            $output .= ($note['content'] ?? '') . "\n\n";
            $output .= str_repeat('=', 60) . "\n\n";
        }

        return $output;
    }

    /**
     * Convert notes array to Markdown.
     */
    private function notesToMarkdown(array $notes): string
    {
        $output = "# Notes Export\n\n";
        $output .= "*Exported: " . date('F j, Y g:i A') . " | Total: " . count($notes) . " notes*\n\n---\n\n";

        foreach ($notes as $note) {
            $output .= "## " . ($note['title'] ?? 'Untitled') . "\n\n";
            if (!empty($note['subject'])) {
                $output .= "**Subject:** " . $note['subject'] . "  \n";
            }
            if (!empty($note['tags'])) {
                $output .= "**Tags:** " . implode(', ', $note['tags']) . "  \n";
            }
            $output .= "**Date:** " . date('Y-m-d', strtotime($note['created_at'] ?? 'now')) . "\n\n";
            $output .= ($note['content'] ?? '') . "\n\n---\n\n";
        }

        return $output;
    }
}
