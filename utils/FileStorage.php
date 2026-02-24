<?php
/**
 * ============================================================================
 * FileStorage - JSON File Storage Engine
 * StudyFlow - Student Self-Teaching App
 *
 * Core storage engine that reads/writes JSON files as the data persistence
 * layer. Provides CRUD operations, querying, indexing, full-text search,
 * and backup capabilities with robust file locking and atomic writes.
 *
 * Data layout:
 *   {baseDir}/{collection}/{id}.json
 *
 * Thread safety is achieved via LOCK_EX on every write and atomic
 * rename-into-place to prevent partial reads.
 * ============================================================================
 */

class FileStorage
{
    /** @var string Root directory for all data collections */
    private string $baseDir;

    /** @var int JSON encoding options */
    private int $jsonOptions;

    /** @var int Permission bits for new files */
    private int $filePermissions;

    /** @var int Permission bits for new directories */
    private int $dirPermissions;

    /** @var string File extension for data files */
    private string $extension = '.json';

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * Create a new FileStorage instance.
     *
     * @param string $baseDir         Root data directory
     * @param int    $jsonOptions     json_encode flags
     * @param int    $filePermissions chmod for new files
     * @param int    $dirPermissions  chmod for new directories
     *
     * @throws RuntimeException If base directory cannot be created
     */
    public function __construct(
        string $baseDir,
        int    $jsonOptions     = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        int    $filePermissions = 0644,
        int    $dirPermissions  = 0755
    ) {
        $this->baseDir         = rtrim($baseDir, '/');
        $this->jsonOptions     = $jsonOptions;
        $this->filePermissions = $filePermissions;
        $this->dirPermissions  = $dirPermissions;

        if (!is_dir($this->baseDir)) {
            if (!mkdir($this->baseDir, $this->dirPermissions, true)) {
                throw new RuntimeException(
                    "FileStorage: Unable to create base directory: {$this->baseDir}"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // CRUD Operations
    // -------------------------------------------------------------------------

    /**
     * Read a single item or all items in a collection.
     *
     * @param string      $collection Collection name (directory)
     * @param string|null $id         Item ID (filename without extension).
     *                                 Pass null to read every item.
     *
     * @return array|null Single item array, list of items, or null if not found
     */
    public function read(string $collection, ?string $id = null): mixed
    {
        if ($id !== null) {
            return $this->readOne($collection, $id);
        }

        return $this->readAll($collection);
    }

    /**
     * Write data to a JSON file using atomic write + file locking.
     *
     * @param string $collection Collection name
     * @param string $id         Item ID
     * @param array  $data       Data to persist
     *
     * @return bool True on success
     * @throws RuntimeException On write failure
     */
    public function write(string $collection, string $id, array $data): bool
    {
        $dir  = $this->collectionDir($collection);
        $file = $this->filePath($collection, $id);

        // Auto-create collection directory
        if (!is_dir($dir)) {
            if (!mkdir($dir, $this->dirPermissions, true)) {
                throw new RuntimeException(
                    "FileStorage: Unable to create collection directory: {$dir}"
                );
            }
        }

        // Inject metadata
        $data['_id']        = $id;
        $data['_updated_at'] = date('c');
        if (!isset($data['_created_at'])) {
            $existing = $this->readOne($collection, $id);
            $data['_created_at'] = $existing['_created_at'] ?? date('c');
        }

        $json = json_encode($data, $this->jsonOptions);
        if ($json === false) {
            throw new RuntimeException(
                'FileStorage: JSON encode error – ' . json_last_error_msg()
            );
        }

        // Atomic write: write to temp file, then rename into place
        $tmpFile = $file . '.tmp.' . uniqid('', true);

        $handle = @fopen($tmpFile, 'w');
        if ($handle === false) {
            throw new RuntimeException(
                "FileStorage: Cannot open temp file for writing: {$tmpFile}"
            );
        }

        try {
            // Acquire exclusive lock
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(
                    "FileStorage: Cannot acquire lock on: {$tmpFile}"
                );
            }

            $written = fwrite($handle, $json);
            if ($written === false) {
                throw new RuntimeException(
                    "FileStorage: Write failed for: {$tmpFile}"
                );
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        // Set permissions before rename so the target has correct perms
        @chmod($tmpFile, $this->filePermissions);

        // Atomic rename
        if (!rename($tmpFile, $file)) {
            @unlink($tmpFile);
            throw new RuntimeException(
                "FileStorage: Atomic rename failed for: {$file}"
            );
        }

        return true;
    }

    /**
     * Delete a JSON file from a collection.
     *
     * @param string $collection Collection name
     * @param string $id         Item ID
     *
     * @return bool True if deleted, false if file did not exist
     */
    public function delete(string $collection, string $id): bool
    {
        $file = $this->filePath($collection, $id);

        if (!file_exists($file)) {
            return false;
        }

        if (!@unlink($file)) {
            throw new RuntimeException(
                "FileStorage: Unable to delete file: {$file}"
            );
        }

        return true;
    }

    /**
     * Check whether an item exists in a collection.
     *
     * @param string $collection Collection name
     * @param string $id         Item ID
     *
     * @return bool
     */
    public function exists(string $collection, string $id): bool
    {
        return file_exists($this->filePath($collection, $id));
    }

    // -------------------------------------------------------------------------
    // Querying
    // -------------------------------------------------------------------------

    /**
     * Query a collection with filters.
     *
     * Filters can be:
     *   - A callable that receives each item and returns bool
     *   - An associative array of field => value pairs (exact match)
     *
     * @param string         $collection Collection name
     * @param callable|array $filters    Filter criteria
     *
     * @return array Matching items
     */
    public function query(string $collection, callable|array $filters = []): array
    {
        $items = $this->readAll($collection);

        if (empty($filters)) {
            return $items;
        }

        // Callable filter
        if (is_callable($filters)) {
            return array_values(array_filter($items, $filters));
        }

        // Key-value filter
        return array_values(array_filter($items, function (array $item) use ($filters): bool {
            foreach ($filters as $field => $value) {
                if (!array_key_exists($field, $item)) {
                    return false;
                }
                if ($item[$field] !== $value) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Count items in a collection, optionally matching filters.
     *
     * @param string         $collection Collection name
     * @param callable|array $filters    Optional filters (same as query())
     *
     * @return int
     */
    public function count(string $collection, callable|array $filters = []): int
    {
        if (empty($filters)) {
            $dir = $this->collectionDir($collection);
            if (!is_dir($dir)) {
                return 0;
            }
            return count(glob($dir . '/*' . $this->extension));
        }

        return count($this->query($collection, $filters));
    }

    // -------------------------------------------------------------------------
    // Partial Updates
    // -------------------------------------------------------------------------

    /**
     * Merge partial data into an existing item.
     *
     * @param string $collection Collection name
     * @param string $id         Item ID
     * @param array  $partial    Fields to merge
     *
     * @return bool
     * @throws RuntimeException If item does not exist
     */
    public function update(string $collection, string $id, array $partial): bool
    {
        $existing = $this->readOne($collection, $id);

        if ($existing === null) {
            throw new RuntimeException(
                "FileStorage: Cannot update non-existent item {$collection}/{$id}"
            );
        }

        $merged = array_merge($existing, $partial);

        return $this->write($collection, $id, $merged);
    }

    /**
     * Append a value to an array field within an item.
     *
     * If the field does not exist it is created as an array.
     *
     * @param string $collection Collection name
     * @param string $id         Item ID
     * @param string $key        Field name (must hold an array or be absent)
     * @param mixed  $value      Value to append
     *
     * @return bool
     */
    public function append(string $collection, string $id, string $key, mixed $value): bool
    {
        $existing = $this->readOne($collection, $id);

        if ($existing === null) {
            throw new RuntimeException(
                "FileStorage: Cannot append to non-existent item {$collection}/{$id}"
            );
        }

        if (!isset($existing[$key])) {
            $existing[$key] = [];
        }

        if (!is_array($existing[$key])) {
            throw new RuntimeException(
                "FileStorage: Field '{$key}' is not an array in {$collection}/{$id}"
            );
        }

        $existing[$key][] = $value;

        return $this->write($collection, $id, $existing);
    }

    // -------------------------------------------------------------------------
    // Indexing & Search
    // -------------------------------------------------------------------------

    /**
     * Build an associative index of items keyed by a field value.
     *
     * @param string $collection Collection name
     * @param string $field      Field to index by
     *
     * @return array Associative array: fieldValue => [items…]
     */
    public function index(string $collection, string $field): array
    {
        $items  = $this->readAll($collection);
        $result = [];

        foreach ($items as $item) {
            if (!isset($item[$field])) {
                continue;
            }

            $key = $item[$field];

            if (is_array($key)) {
                // If the field itself is an array, index under each value
                foreach ($key as $k) {
                    $result[(string) $k][] = $item;
                }
            } else {
                $result[(string) $key][] = $item;
            }
        }

        return $result;
    }

    /**
     * Full-text search across specified fields.
     *
     * Performs case-insensitive substring matching on each field.
     *
     * @param string   $collection Collection name
     * @param string   $queryStr   Search query
     * @param string[] $fields     Fields to search within
     *
     * @return array Matching items sorted by relevance (number of field hits)
     */
    public function search(string $collection, string $queryStr, array $fields): array
    {
        if ($queryStr === '') {
            return [];
        }

        $items   = $this->readAll($collection);
        $needle  = mb_strtolower($queryStr, 'UTF-8');
        $results = [];

        foreach ($items as $item) {
            $hits = 0;
            foreach ($fields as $field) {
                if (!isset($item[$field])) {
                    continue;
                }

                $haystack = $item[$field];
                if (is_array($haystack)) {
                    $haystack = implode(' ', $haystack);
                }

                $haystack = mb_strtolower((string) $haystack, 'UTF-8');
                if (str_contains($haystack, $needle)) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $item['_relevance'] = $hits;
                $results[] = $item;
            }
        }

        // Sort by relevance descending
        usort($results, fn($a, $b) => $b['_relevance'] <=> $a['_relevance']);

        return $results;
    }

    // -------------------------------------------------------------------------
    // Backup
    // -------------------------------------------------------------------------

    /**
     * Create a timestamped backup of an entire collection.
     *
     * Backups are stored under {baseDir}/_backups/{collection}/{timestamp}/
     *
     * @param string $collection Collection name
     *
     * @return string Path to backup directory
     * @throws RuntimeException On failure
     */
    public function backup(string $collection): string
    {
        $sourceDir = $this->collectionDir($collection);
        if (!is_dir($sourceDir)) {
            throw new RuntimeException(
                "FileStorage: Collection does not exist: {$collection}"
            );
        }

        $timestamp = date('Y-m-d_His');
        $backupDir = $this->baseDir . '/_backups/' . $collection . '/' . $timestamp;

        if (!mkdir($backupDir, $this->dirPermissions, true)) {
            throw new RuntimeException(
                "FileStorage: Unable to create backup directory: {$backupDir}"
            );
        }

        $files = glob($sourceDir . '/*' . $this->extension);
        foreach ($files as $file) {
            $dest = $backupDir . '/' . basename($file);
            if (!copy($file, $dest)) {
                throw new RuntimeException(
                    "FileStorage: Failed to copy {$file} to {$dest}"
                );
            }
        }

        // Write backup manifest
        $manifest = [
            'collection'  => $collection,
            'timestamp'   => $timestamp,
            'file_count'  => count($files),
            'created_at'  => date('c'),
        ];

        file_put_contents(
            $backupDir . '/_manifest.json',
            json_encode($manifest, $this->jsonOptions)
        );

        return $backupDir;
    }

    // -------------------------------------------------------------------------
    // ID Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a unique ID suitable for use as a file name.
     *
     * Format: hex timestamp + random bytes  (e.g. "6652a3f1c4e8b_7a3f")
     *
     * @return string
     */
    public function generateId(): string
    {
        return uniqid('', true) . '_' . bin2hex(random_bytes(4));
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Read a single item by collection and ID.
     *
     * @param string $collection
     * @param string $id
     *
     * @return array|null
     */
    private function readOne(string $collection, string $id): ?array
    {
        $file = $this->filePath($collection, $id);

        if (!file_exists($file)) {
            return null;
        }

        $handle = @fopen($file, 'r');
        if ($handle === false) {
            throw new RuntimeException(
                "FileStorage: Cannot open file for reading: {$file}"
            );
        }

        try {
            // Shared lock for concurrent reads
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException(
                    "FileStorage: Cannot acquire shared lock on: {$file}"
                );
            }

            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($contents === false || $contents === '') {
            return null;
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "FileStorage: JSON decode error in {$file} – " . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Read every item in a collection directory.
     *
     * @param string $collection
     *
     * @return array List of item arrays
     */
    private function readAll(string $collection): array
    {
        $dir = $this->collectionDir($collection);

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*' . $this->extension);
        if ($files === false) {
            return [];
        }

        $items = [];
        foreach ($files as $file) {
            $id = basename($file, $this->extension);
            $item = $this->readOne($collection, $id);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Build the absolute path to a collection directory.
     *
     * @param string $collection
     * @return string
     */
    private function collectionDir(string $collection): string
    {
        $safe = $this->sanitizeName($collection);
        return $this->baseDir . '/' . $safe;
    }

    /**
     * Build the absolute path to a JSON file.
     *
     * @param string $collection
     * @param string $id
     * @return string
     */
    private function filePath(string $collection, string $id): string
    {
        $safeCollection = $this->sanitizeName($collection);
        $safeId         = $this->sanitizeName($id);
        return $this->baseDir . '/' . $safeCollection . '/' . $safeId . $this->extension;
    }

    /**
     * Sanitize a name for safe use as a directory or file name.
     *
     * Only allows alphanumeric characters, hyphens, underscores, and dots.
     * Prevents directory traversal attacks.
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException If name is empty after sanitization
     */
    private function sanitizeName(string $name): string
    {
        // Remove any path traversal attempts
        $name = str_replace(['..', '/', '\\'], '', $name);

        // Allow only safe characters
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        if ($safe === '' || $safe === null) {
            throw new InvalidArgumentException(
                "FileStorage: Name is empty or invalid after sanitization: '{$name}'"
            );
        }

        return $safe;
    }
}
