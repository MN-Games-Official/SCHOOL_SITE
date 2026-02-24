<?php
/**
 * ============================================================================
 * IntegrityService - Academic Integrity Service
 * StudyFlow - Student Self-Teaching App
 *
 * Monitors and promotes academic integrity by analyzing writing patterns,
 * tracking edit behaviour, comparing writing versions, and providing
 * integrity reports and scores. This is a self-accountability tool, not
 * a punitive system.
 * ============================================================================
 */

require_once __DIR__ . '/../utils/FileStorage.php';

class IntegrityService
{
    private FileStorage $storage;

    private const COLLECTION_EVENTS  = 'integrity_events';
    private const COLLECTION_FLAGS   = 'integrity_flags';
    private const COLLECTION_PLEDGES = 'integrity_pledges';

    /** @var float Threshold for vocabulary complexity jump (standard deviations) */
    private const COMPLEXITY_THRESHOLD = 2.0;

    /** @var float Threshold for rapid content increase (words per minute) */
    private const RAPID_TYPING_THRESHOLD = 200;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->storage = new FileStorage(__DIR__ . '/../data');
    }

    // -------------------------------------------------------------------------
    // Writing Integrity Analysis
    // -------------------------------------------------------------------------

    /**
     * Analyze a writing piece for integrity indicators.
     *
     * Checks for: sudden vocabulary jumps, inconsistent writing style,
     * rapid content additions, and other heuristic signals.
     *
     * @param string $writingId
     * @return array Analysis report
     * @throws RuntimeException If writing not found
     */
    public function checkWritingIntegrity(string $writingId): array
    {
        $writing = $this->storage->read('writings', $writingId);
        if ($writing === null) {
            throw new RuntimeException('IntegrityService: Writing not found.');
        }

        $content  = $writing['content'] ?? '';
        $userId   = $writing['user_id'] ?? '';
        $findings = [];
        $score    = 100; // Start at 100, deduct for concerns

        if (trim($content) === '') {
            return [
                'writing_id' => $writingId,
                'score'      => 100,
                'findings'   => [],
                'status'     => 'clean',
                'message'    => 'No content to analyze.',
            ];
        }

        // 1. Vocabulary consistency analysis
        $vocabResult = $this->analyzeVocabularyConsistency($content);
        if ($vocabResult['concern']) {
            $findings[] = [
                'type'        => 'vocabulary_inconsistency',
                'severity'    => 'medium',
                'description' => $vocabResult['description'],
                'details'     => $vocabResult,
            ];
            $score -= 15;
        }

        // 2. Style consistency (sentence length variation)
        $styleResult = $this->analyzeStyleConsistency($content);
        if ($styleResult['concern']) {
            $findings[] = [
                'type'        => 'style_inconsistency',
                'severity'    => 'low',
                'description' => $styleResult['description'],
                'details'     => $styleResult,
            ];
            $score -= 10;
        }

        // 3. Version history analysis (rapid changes)
        $versionResult = $this->analyzeVersionHistory($writingId);
        if ($versionResult['concern']) {
            $findings[] = [
                'type'        => 'rapid_content_change',
                'severity'    => 'medium',
                'description' => $versionResult['description'],
                'details'     => $versionResult,
            ];
            $score -= 20;
        }

        $score  = max(0, $score);
        $status = $score >= 80 ? 'clean' : ($score >= 50 ? 'review' : 'concern');

        // Log the check
        $this->logIntegrityEvent($userId, 'writing_check', [
            'writing_id' => $writingId,
            'score'      => $score,
            'findings'   => count($findings),
            'status'     => $status,
        ]);

        return [
            'writing_id'  => $writingId,
            'score'       => $score,
            'findings'    => $findings,
            'status'      => $status,
            'message'     => $this->statusMessage($status),
            'checked_at'  => date('c'),
        ];
    }

    // -------------------------------------------------------------------------
    // Integrity Report & Score
    // -------------------------------------------------------------------------

    /**
     * Get a user's overall integrity report.
     *
     * @param string $userId
     * @return array Report
     */
    public function getIntegrityReport(string $userId): array
    {
        $events  = $this->storage->query(self::COLLECTION_EVENTS, ['user_id' => $userId]);
        $flags   = $this->storage->query(self::COLLECTION_FLAGS, ['user_id' => $userId]);
        $pledge  = $this->getLatestPledge($userId);

        // Count checks and findings
        $checks          = array_filter($events, fn($e) => ($e['type'] ?? '') === 'writing_check');
        $totalFindings   = 0;
        $avgScore        = 100;
        $scores          = [];

        foreach ($checks as $check) {
            $details = $check['details'] ?? [];
            $totalFindings += ($details['findings'] ?? 0);
            if (isset($details['score'])) {
                $scores[] = $details['score'];
            }
        }

        if (!empty($scores)) {
            $avgScore = round(array_sum($scores) / count($scores), 1);
        }

        $activeFlags   = array_filter($flags, fn($f) => ($f['status'] ?? '') === 'active');
        $resolvedFlags = array_filter($flags, fn($f) => ($f['status'] ?? '') === 'resolved');

        return [
            'user_id'          => $userId,
            'overall_score'    => $avgScore,
            'total_checks'     => count($checks),
            'total_findings'   => $totalFindings,
            'active_flags'     => count($activeFlags),
            'resolved_flags'   => count($resolvedFlags),
            'has_pledge'       => $pledge !== null,
            'pledge_date'      => $pledge['created_at'] ?? null,
            'status'           => $avgScore >= 80 ? 'good_standing' : ($avgScore >= 50 ? 'needs_attention' : 'concern'),
            'generated_at'     => date('c'),
        ];
    }

    /**
     * Log an integrity event.
     *
     * @param string $userId
     * @param string $type    Event type
     * @param array  $details Event details
     * @return array The logged event
     */
    public function logIntegrityEvent(string $userId, string $type, array $details = []): array
    {
        $eventId = $this->storage->generateId();

        $event = [
            'id'         => $eventId,
            'user_id'    => $userId,
            'type'       => $type,
            'details'    => $details,
            'created_at' => date('c'),
        ];

        $this->storage->write(self::COLLECTION_EVENTS, $eventId, $event);

        return $event;
    }

    /**
     * Calculate integrity score for a user.
     *
     * @param string $userId
     * @return array Score details
     */
    public function getIntegrityScore(string $userId): array
    {
        $report = $this->getIntegrityReport($userId);

        $baseScore = $report['overall_score'];

        // Bonus for having a pledge
        if ($report['has_pledge']) {
            $baseScore = min(100, $baseScore + 5);
        }

        // Penalty for active flags
        $baseScore -= $report['active_flags'] * 10;
        $baseScore = max(0, $baseScore);

        return [
            'user_id'   => $userId,
            'score'     => round($baseScore, 1),
            'label'     => $this->scoreLabel($baseScore),
            'breakdown' => [
                'base_score'      => $report['overall_score'],
                'pledge_bonus'    => $report['has_pledge'] ? 5 : 0,
                'flag_penalty'    => $report['active_flags'] * 10,
                'active_flags'    => $report['active_flags'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Originality Analysis
    // -------------------------------------------------------------------------

    /**
     * Basic originality analysis of a text.
     *
     * Analyzes vocabulary level, consistency, and other heuristic indicators.
     *
     * @param string $text
     * @return array Analysis results
     */
    public function analyzeWritingOriginality(string $text): array
    {
        if (trim($text) === '') {
            return [
                'score'   => 100,
                'message' => 'No text to analyze.',
                'indicators' => [],
            ];
        }

        $indicators = [];
        $score      = 100;

        // Vocabulary level
        $vocabLevel = $this->calculateVocabularyLevel($text);
        $indicators['vocabulary_level'] = $vocabLevel;

        // Sentence structure variety
        $structureVariety = $this->analyzeSentenceVariety($text);
        $indicators['sentence_variety'] = $structureVariety;

        // Consistency check
        $consistency = $this->analyzeVocabularyConsistency($text);
        $indicators['consistency'] = $consistency;

        if ($consistency['concern']) {
            $score -= 15;
        }

        // Paragraph coherence
        $paragraphs = preg_split('/\n\s*\n/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $indicators['paragraph_count'] = count($paragraphs);

        $score = max(0, $score);

        return [
            'score'      => $score,
            'status'     => $score >= 80 ? 'likely_original' : ($score >= 50 ? 'review_suggested' : 'concern'),
            'indicators' => $indicators,
            'message'    => $score >= 80
                ? 'The writing appears consistent and original.'
                : 'Some indicators suggest this writing may benefit from review.',
        ];
    }

    // -------------------------------------------------------------------------
    // Version Comparison
    // -------------------------------------------------------------------------

    /**
     * Compare writing versions to detect significant changes.
     *
     * @param string $writingId
     * @return array Comparison results
     */
    public function compareVersions(string $writingId): array
    {
        $versions = $this->storage->query('writing_versions', ['writing_id' => $writingId]);

        if (count($versions) < 2) {
            return [
                'writing_id'  => $writingId,
                'comparisons' => [],
                'message'     => 'Not enough versions to compare.',
            ];
        }

        usort($versions, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));

        $comparisons = [];
        for ($i = 1; $i < count($versions); $i++) {
            $prev    = $versions[$i - 1];
            $current = $versions[$i];

            $prevWords    = $prev['word_count'] ?? 0;
            $currentWords = $current['word_count'] ?? 0;
            $wordDiff     = $currentWords - $prevWords;
            $timeDiff     = max(1, strtotime($current['created_at'] ?? '') - strtotime($prev['created_at'] ?? ''));
            $wordsPerMin  = abs($wordDiff) / ($timeDiff / 60);

            $isRapid = $wordsPerMin > self::RAPID_TYPING_THRESHOLD && abs($wordDiff) > 50;

            $comparisons[] = [
                'from_version'   => $prev['id'] ?? '',
                'to_version'     => $current['id'] ?? '',
                'word_diff'      => $wordDiff,
                'pct_change'     => $prevWords > 0 ? round(($wordDiff / $prevWords) * 100, 1) : 0,
                'time_between'   => $timeDiff,
                'words_per_min'  => round($wordsPerMin, 1),
                'is_rapid'       => $isRapid,
                'from_date'      => $prev['created_at'] ?? '',
                'to_date'        => $current['created_at'] ?? '',
            ];
        }

        return [
            'writing_id'   => $writingId,
            'total_versions' => count($versions),
            'comparisons'  => $comparisons,
            'rapid_changes' => count(array_filter($comparisons, fn($c) => $c['is_rapid'])),
        ];
    }

    // -------------------------------------------------------------------------
    // Writing Behaviour Analysis
    // -------------------------------------------------------------------------

    /**
     * Analyze writing patterns for a user.
     *
     * @param string $userId
     * @return array Behavior analysis
     */
    public function getWritingBehavior(string $userId): array
    {
        $writings = $this->storage->query('writings', ['user_id' => $userId]);
        $versions = $this->storage->query('writing_versions', function ($v) use ($writings) {
            $writingIds = array_column($writings, 'id');
            return in_array($v['writing_id'] ?? '', $writingIds, true);
        });

        // Average time between saves
        $saveTimes    = [];
        $writingGroups = [];

        foreach ($versions as $v) {
            $wid = $v['writing_id'] ?? '';
            $writingGroups[$wid][] = $v;
        }

        foreach ($writingGroups as $wid => $group) {
            usort($group, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));

            for ($i = 1; $i < count($group); $i++) {
                $diff = strtotime($group[$i]['created_at'] ?? '') - strtotime($group[$i - 1]['created_at'] ?? '');
                if ($diff > 0 && $diff < 86400) { // Ignore gaps > 24h
                    $saveTimes[] = $diff;
                }
            }
        }

        $avgSaveInterval = !empty($saveTimes)
            ? round(array_sum($saveTimes) / count($saveTimes))
            : 0;

        // Writing speed patterns
        $speeds = [];
        foreach ($writingGroups as $wid => $group) {
            usort($group, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));

            for ($i = 1; $i < count($group); $i++) {
                $wordDiff = ($group[$i]['word_count'] ?? 0) - ($group[$i - 1]['word_count'] ?? 0);
                $timeDiff = max(1, strtotime($group[$i]['created_at'] ?? '') - strtotime($group[$i - 1]['created_at'] ?? ''));
                if ($wordDiff > 0 && $timeDiff > 0 && $timeDiff < 86400) {
                    $speeds[] = $wordDiff / ($timeDiff / 60);
                }
            }
        }

        $avgSpeed = !empty($speeds) ? round(array_sum($speeds) / count($speeds), 1) : 0;
        $maxSpeed = !empty($speeds) ? round(max($speeds), 1) : 0;

        // Preferred writing times
        $writingHours = [];
        foreach ($writings as $w) {
            $hour = (int) date('G', strtotime($w['updated_at'] ?? $w['created_at'] ?? 'now'));
            $writingHours[$hour] = ($writingHours[$hour] ?? 0) + 1;
        }

        arsort($writingHours);
        $preferredHours = array_slice(array_keys($writingHours), 0, 3, true);

        return [
            'user_id'              => $userId,
            'total_writings'       => count($writings),
            'total_versions'       => count($versions),
            'avg_save_interval'    => $avgSaveInterval,
            'avg_save_interval_min' => round($avgSaveInterval / 60, 1),
            'avg_writing_speed'    => $avgSpeed,
            'max_writing_speed'    => $maxSpeed,
            'preferred_hours'      => $preferredHours,
            'writing_hours'        => $writingHours,
            'pattern_summary'      => $this->summarizeWritingPattern($avgSpeed, $avgSaveInterval),
        ];
    }

    // -------------------------------------------------------------------------
    // Integrity Pledge
    // -------------------------------------------------------------------------

    /**
     * Generate or retrieve an integrity pledge for a user.
     *
     * @param string $userId
     * @return array Pledge details
     */
    public function generateIntegrityPledge(string $userId): array
    {
        $existing = $this->getLatestPledge($userId);
        if ($existing !== null) {
            return $existing;
        }

        $pledgeId = $this->storage->generateId();
        $now      = date('c');

        $pledge = [
            'id'         => $pledgeId,
            'user_id'    => $userId,
            'text'       => "I pledge to maintain academic integrity in all my work. "
                . "I will produce original content, properly attribute sources, "
                . "and use AI tools responsibly as learning aids rather than substitutes for my own thinking. "
                . "I understand that academic integrity is fundamental to meaningful learning.",
            'accepted'   => true,
            'created_at' => $now,
        ];

        $this->storage->write(self::COLLECTION_PLEDGES, $pledgeId, $pledge);

        $this->logIntegrityEvent($userId, 'pledge_signed', [
            'pledge_id' => $pledgeId,
        ]);

        return $pledge;
    }

    // -------------------------------------------------------------------------
    // Flags
    // -------------------------------------------------------------------------

    /**
     * Get integrity flags for a user.
     *
     * @param string $userId
     * @return array
     */
    public function getFlags(string $userId): array
    {
        $flags = $this->storage->query(self::COLLECTION_FLAGS, ['user_id' => $userId]);

        usort($flags, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        return $flags;
    }

    /**
     * Resolve (dismiss) a flag.
     *
     * @param string $flagId
     * @return array Updated flag
     * @throws RuntimeException If flag not found
     */
    public function resolveFlag(string $flagId): array
    {
        $flag = $this->storage->read(self::COLLECTION_FLAGS, $flagId);
        if ($flag === null) {
            throw new RuntimeException('IntegrityService: Flag not found.');
        }

        $this->storage->update(self::COLLECTION_FLAGS, $flagId, [
            'status'      => 'resolved',
            'resolved_at' => date('c'),
        ]);

        $this->logIntegrityEvent($flag['user_id'] ?? '', 'flag_resolved', [
            'flag_id' => $flagId,
        ]);

        return $this->storage->read(self::COLLECTION_FLAGS, $flagId);
    }

    // -------------------------------------------------------------------------
    // Statistics
    // -------------------------------------------------------------------------

    /**
     * Get overall integrity statistics across all users.
     *
     * @return array
     */
    public function getIntegrityStats(): array
    {
        $events  = $this->storage->read(self::COLLECTION_EVENTS) ?? [];
        $flags   = $this->storage->read(self::COLLECTION_FLAGS) ?? [];
        $pledges = $this->storage->read(self::COLLECTION_PLEDGES) ?? [];

        $checks       = array_filter($events, fn($e) => ($e['type'] ?? '') === 'writing_check');
        $activeFlags  = array_filter($flags, fn($f) => ($f['status'] ?? '') === 'active');
        $resolvedFlags = array_filter($flags, fn($f) => ($f['status'] ?? '') === 'resolved');

        // Average score from all checks
        $scores = [];
        foreach ($checks as $check) {
            if (isset($check['details']['score'])) {
                $scores[] = $check['details']['score'];
            }
        }

        $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 100;

        return [
            'total_checks'     => count($checks),
            'total_events'     => count($events),
            'total_flags'      => count($flags),
            'active_flags'     => count($activeFlags),
            'resolved_flags'   => count($resolvedFlags),
            'total_pledges'    => count($pledges),
            'avg_score'        => $avgScore,
            'generated_at'     => date('c'),
        ];
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Analyze vocabulary consistency within a text.
     */
    private function analyzeVocabularyConsistency(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($paragraphs) < 2) {
            return ['concern' => false, 'description' => 'Not enough paragraphs to analyze.'];
        }

        $avgLengths = [];
        foreach ($paragraphs as $para) {
            $words = preg_split('/\s+/', trim($para), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) < 5) continue;

            $totalLen = 0;
            foreach ($words as $w) {
                $totalLen += mb_strlen(preg_replace('/[^a-zA-Z]/', '', $w), 'UTF-8');
            }
            $avgLengths[] = $totalLen / count($words);
        }

        if (count($avgLengths) < 2) {
            return ['concern' => false, 'description' => 'Not enough data for analysis.'];
        }

        $mean   = array_sum($avgLengths) / count($avgLengths);
        $sumSq  = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $avgLengths));
        $stdDev = sqrt($sumSq / count($avgLengths));

        // Check if any paragraph's avg word length deviates significantly
        $outliers = 0;
        foreach ($avgLengths as $len) {
            if ($stdDev > 0 && abs($len - $mean) > self::COMPLEXITY_THRESHOLD * $stdDev) {
                $outliers++;
            }
        }

        $concern = $outliers > 0;

        return [
            'concern'        => $concern,
            'description'    => $concern
                ? 'Vocabulary complexity varies significantly between paragraphs.'
                : 'Vocabulary complexity is consistent.',
            'mean_word_length' => round($mean, 2),
            'std_deviation'  => round($stdDev, 2),
            'outliers'       => $outliers,
            'paragraphs'     => count($avgLengths),
        ];
    }

    /**
     * Analyze sentence length consistency.
     */
    private function analyzeStyleConsistency(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter($sentences, fn($s) => trim($s) !== '');

        if (count($sentences) < 5) {
            return ['concern' => false, 'description' => 'Not enough sentences for style analysis.'];
        }

        $lengths = array_map(function ($s) {
            return count(preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY));
        }, $sentences);

        $mean   = array_sum($lengths) / count($lengths);
        $sumSq  = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $lengths));
        $stdDev = sqrt($sumSq / count($lengths));
        $cv     = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        // A CV < 20% is very uniform (may be robotic), > 80% is very inconsistent
        $concern = $cv < 15 || $cv > 100;

        return [
            'concern'            => $concern,
            'description'        => $concern
                ? ($cv < 15 ? 'Sentence lengths are unusually uniform.' : 'Sentence lengths vary dramatically.')
                : 'Sentence length variation appears natural.',
            'avg_sentence_length' => round($mean, 1),
            'std_deviation'      => round($stdDev, 1),
            'cv_percent'         => round($cv, 1),
            'total_sentences'    => count($sentences),
        ];
    }

    /**
     * Analyze version history for rapid/suspicious changes.
     */
    private function analyzeVersionHistory(string $writingId): array
    {
        $versions = $this->storage->query('writing_versions', ['writing_id' => $writingId]);

        if (count($versions) < 2) {
            return ['concern' => false, 'description' => 'Not enough versions to analyze.'];
        }

        usort($versions, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));

        $rapidChanges = 0;
        for ($i = 1; $i < count($versions); $i++) {
            $wordDiff = ($versions[$i]['word_count'] ?? 0) - ($versions[$i - 1]['word_count'] ?? 0);
            $timeDiff = max(1, strtotime($versions[$i]['created_at'] ?? '') - strtotime($versions[$i - 1]['created_at'] ?? ''));
            $wpm      = abs($wordDiff) / ($timeDiff / 60);

            if ($wpm > self::RAPID_TYPING_THRESHOLD && abs($wordDiff) > 50) {
                $rapidChanges++;
            }
        }

        $concern = $rapidChanges > 0;

        return [
            'concern'        => $concern,
            'description'    => $concern
                ? "Detected {$rapidChanges} instance(s) of unusually rapid content addition."
                : 'Content development pace appears normal.',
            'total_versions' => count($versions),
            'rapid_changes'  => $rapidChanges,
        ];
    }

    /**
     * Calculate vocabulary level of text (average word length as proxy).
     */
    private function calculateVocabularyLevel(string $text): array
    {
        $words    = preg_split('/\s+/', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        $cleaned  = array_map(fn($w) => preg_replace('/[^a-z]/', '', $w), $words);
        $cleaned  = array_filter($cleaned, fn($w) => $w !== '');

        if (empty($cleaned)) {
            return ['level' => 'unknown', 'avg_length' => 0, 'unique_ratio' => 0];
        }

        $totalLen  = array_sum(array_map('strlen', $cleaned));
        $avgLen    = $totalLen / count($cleaned);
        $unique    = count(array_unique($cleaned));
        $ratio     = $unique / count($cleaned);

        $level = 'basic';
        if ($avgLen >= 6) $level = 'advanced';
        elseif ($avgLen >= 5) $level = 'intermediate';

        return [
            'level'        => $level,
            'avg_length'   => round($avgLen, 2),
            'unique_ratio' => round($ratio, 3),
            'total_words'  => count($cleaned),
            'unique_words' => $unique,
        ];
    }

    /**
     * Analyze sentence structure variety.
     */
    private function analyzeSentenceVariety(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter($sentences, fn($s) => trim($s) !== '');

        if (count($sentences) < 3) {
            return ['variety' => 'insufficient_data', 'score' => 0];
        }

        $lengths = array_map(fn($s) => count(preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY)), $sentences);
        $unique  = count(array_unique($lengths));
        $total   = count($lengths);
        $ratio   = $unique / $total;

        $variety = 'low';
        if ($ratio > 0.7) $variety = 'high';
        elseif ($ratio > 0.4) $variety = 'moderate';

        return [
            'variety' => $variety,
            'score'   => round($ratio * 100, 1),
            'unique_lengths' => $unique,
            'total_sentences' => $total,
        ];
    }

    /**
     * Get the latest integrity pledge for a user.
     */
    private function getLatestPledge(string $userId): ?array
    {
        $pledges = $this->storage->query(self::COLLECTION_PLEDGES, ['user_id' => $userId]);

        if (empty($pledges)) {
            return null;
        }

        usort($pledges, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));

        return $pledges[0];
    }

    /**
     * Get a human-readable status message.
     */
    private function statusMessage(string $status): string
    {
        return match ($status) {
            'clean'   => 'No integrity concerns detected. Great work maintaining original content!',
            'review'  => 'Some indicators suggest reviewing this work. This may be a false positive.',
            'concern' => 'Multiple integrity indicators detected. Please review your writing process.',
            default   => 'Analysis complete.',
        };
    }

    /**
     * Map score to a label.
     */
    private function scoreLabel(float $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 40) return 'Needs Improvement';
        return 'Concerning';
    }

    /**
     * Summarize a writing pattern.
     */
    private function summarizeWritingPattern(float $avgSpeed, int $avgInterval): string
    {
        $speedLabel    = $avgSpeed > 100 ? 'fast' : ($avgSpeed > 40 ? 'moderate' : 'careful');
        $intervalLabel = $avgInterval < 120 ? 'frequent' : ($avgInterval < 600 ? 'regular' : 'infrequent');

        return "Writing speed: {$speedLabel} ({$avgSpeed} words/min avg). Save pattern: {$intervalLabel} (every " . round($avgInterval / 60, 1) . " min avg).";
    }
}
