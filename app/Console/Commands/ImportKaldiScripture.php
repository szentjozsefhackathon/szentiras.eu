<?php

namespace SzentirasHu\Console\Commands;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\UsxCodes;
use SzentirasHu\Service\Text\VerseFixService;

class ImportKaldiScripture extends Command
{
    protected $signature = 'szentiras:import-kaldi
                            {--source= : Path to cloned kaldibiblia.gitlab.io root or its public folder}
                            {--translation=KAL : Translation abbreviation in the translations table}';

    protected $description = 'Import Kaldi Bible (1865) from local kaldibiblia.gitlab.io HTML sources.';

    /**
     * @var array<string, string>
     */
    private const BOOK_SLUG_TO_USX = [
        '1moz' => 'GEN',
        '2moz' => 'EXO',
        '3moz' => 'LEV',
        '4moz' => 'NUM',
        '5moz' => 'DEU',
        'jozs' => 'JOS',
        'bir' => 'JDG',
        'rut' => 'RUT',
        '1sam' => '1SA',
        '2sam' => '2SA',
        '1kir' => '1KI',
        '2kir' => '2KI',
        '1kron' => '1CH',
        '2kron' => '2CH',
        'ezd' => 'EZR',
        'neh' => 'NEH',
        'tob' => 'TOB',
        'jud' => 'JDT',
        'eszt' => 'EST',
        'job' => 'JOB',
        'zsolt' => 'PSA',
        'peld' => 'PRO',
        'pred' => 'ECC',
        'en' => 'SNG',
        'bolcs' => 'WIS',
        'sir' => 'SIR',
        'iz' => 'ISA',
        'jer' => 'JER',
        'siral' => 'LAM',
        'bar' => 'BAR',
        'ez' => 'EZK',
        'dan' => 'DAN',
        'oz' => 'HOS',
        'jo' => 'JOL',
        'am' => 'AMO',
        'abd' => 'OBA',
        'jon' => 'JON',
        'mik' => 'MIC',
        'nah' => 'NAM',
        'hab' => 'HAB',
        'szof' => 'ZEP',
        'ag' => 'HAG',
        'zak' => 'ZEC',
        'mal' => 'MAL',
        '1mak' => '1MA',
        '2mak' => '2MA',
        'mt' => 'MAT',
        'mk' => 'MRK',
        'lk' => 'LUK',
        'jn' => 'JHN',
        'apcsel' => 'ACT',
        'rom' => 'ROM',
        '1kor' => '1CO',
        '2kor' => '2CO',
        'gal' => 'GAL',
        'ef' => 'EPH',
        'fil' => 'PHP',
        'kol' => 'COL',
        '1tessz' => '1TH',
        '2tessz' => '2TH',
        '1tim' => '1TI',
        '2tim' => '2TI',
        'tit' => 'TIT',
        'filem' => 'PHM',
        'zsid' => 'HEB',
        'jak' => 'JAS',
        '1pet' => '1PE',
        '2pet' => '2PE',
        '1jn' => '1JN',
        '2jn' => '2JN',
        '3jn' => '3JN',
        'ju' => 'JUD',
        'jel' => 'REV',
    ];

    public function handle(): int
    {
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        DB::connection()->disableQueryLog();

        $translationAbbrev = (string) $this->option('translation');
        $translation = Translation::where('abbrev', $translationAbbrev)->first();
        if (!$translation) {
            throw new RuntimeException("Translation not found: {$translationAbbrev}. Run migrations first.");
        }

        $publicPath = $this->resolvePublicPath();
        $this->info("Import source: {$publicPath}");

        $bookDefinitions = $this->parseBookDefinitions($publicPath);
        if (count($bookDefinitions) === 0) {
            throw new RuntimeException('No books found in tartalom.html');
        }

        DB::transaction(function () use ($translation): void {
            Verse::whereHas('book', function ($query) use ($translation): void {
                $query->where('translation_id', $translation->id);
            })->delete();
            Book::where('translation_id', $translation->id)->delete();
        });

        foreach ($bookDefinitions as $bookDefinition) {
            $importCounts = DB::transaction(function () use ($translation, $publicPath, $bookDefinition): array {
                $book = new Book([
                    'name' => $bookDefinition['name'],
                    'abbrev' => $bookDefinition['abbrev'],
                    'link' => $bookDefinition['slug'],
                    'old_testament' => $bookDefinition['old_testament'],
                    'order' => $bookDefinition['order'],
                    'usx_code' => $bookDefinition['usx_code'],
                ]);
                $book->translation()->associate($translation);
                $book->save();

                $verses = $this->parseVersesForBook($publicPath, $bookDefinition['slug']);
                foreach ($verses as $verseRow) {
                    $verse = new Verse([
                        'usx_code' => $bookDefinition['usx_code'],
                        'gepi' => $bookDefinition['usx_code'] . '_' . $verseRow['chapter'] . '_' . $verseRow['numv'],
                        'verse' => $verseRow['verse'],
                        'chapter' => $verseRow['chapter'],
                        'numv' => $verseRow['numv'],
                        'tip' => $verseRow['tip'],
                        'verseroot' => null,
                        'ido' => null,
                    ]);
                    $verse->book()->associate($book);
                    $verse->translation()->associate($translation);
                    $verse->save();
                }

                $footnotes = $this->parseFootnotesForBook($publicPath, $bookDefinition['slug']);
                foreach ($footnotes as $footnoteRow) {
                    $footnote = new Verse([
                        'usx_code' => $bookDefinition['usx_code'],
                        'gepi' => $bookDefinition['usx_code'] . '_' . $footnoteRow['chapter'] . '_' . $footnoteRow['numv'],
                        'verse' => $footnoteRow['verse'],
                        'chapter' => $footnoteRow['chapter'],
                        'numv' => $footnoteRow['numv'],
                        'tip' => 2001,
                        'verseroot' => null,
                        'ido' => null,
                    ]);
                    $footnote->book()->associate($book);
                    $footnote->translation()->associate($translation);
                    $footnote->save();
                }

                return [
                    'verses' => count($verses),
                    'footnotes' => count($footnotes),
                ];
            });

            $this->info(
                "Imported {$bookDefinition['slug']} ({$bookDefinition['name']}), verses: {$importCounts['verses']}, footnotes: {$importCounts['footnotes']}"
            );
            $this->releaseMemoryAfterBook();
        }

        $this->info('Szövegjavítások alkalmazása...');
        $statusCounts = $this->laravel->make(VerseFixService::class)->reapplyForTranslation($translation->id);
        $this->table(['Állapot', 'Javítások'], collect($statusCounts)->map(fn (int $count, string $status): array => [$status, $count])->values()->all());

        Artisan::call('cache:clear');

        $this->info('Kaldi import finished successfully.');
        $this->info('Reindex Sphinx so search picks up the changes: touch /opt/sphinx/trigger/indexer in the sphinx container (the watcher reindexes within ~30s), or restart the sphinx container for a full rebuild.');

        $this->call('cdn:purge');

        return 0;
    }

    private function resolvePublicPath(): string
    {
        $sourceOption = $this->option('source');
        if (!empty($sourceOption)) {
            $candidate = rtrim((string) $sourceOption, '/');
        } else {
            $repoRoot = base_path('kaldibiblia.gitlab.io');
            if (!is_dir($repoRoot)) {
                $this->info('Default source not found, cloning kaldibiblia.gitlab.io...');
                $this->cloneKaldiRepository($repoRoot);
            }

            $candidate = $repoRoot . '/public';
        }

        if (is_dir($candidate . '/public')) {
            $candidate .= '/public';
        }

        if (!is_dir($candidate)) {
            throw new RuntimeException("Source path not found: {$candidate}");
        }

        if (!file_exists($candidate . '/tartalom.html')) {
            throw new RuntimeException("tartalom.html not found in: {$candidate}");
        }

        return $candidate;
    }

    private function cloneKaldiRepository(string $targetDirectory): void
    {
        $gitBinary = trim((string) shell_exec('command -v git 2>/dev/null'));
        if ($gitBinary === '') {
            throw new RuntimeException('git binary not found, cannot auto-clone kaldibiblia.gitlab.io');
        }

        $escapedGitBinary = escapeshellarg($gitBinary);
        $escapedRepoUrl = escapeshellarg('https://gitlab.com/kaldibiblia/kaldibiblia.gitlab.io.git');
        $escapedTargetDirectory = escapeshellarg($targetDirectory);

        $output = [];
        $exitCode = 0;
        exec("{$escapedGitBinary} clone {$escapedRepoUrl} {$escapedTargetDirectory} 2>&1", $output, $exitCode);

        if ($exitCode !== 0 || !is_dir($targetDirectory)) {
            $details = trim(implode("\n", $output));
            throw new RuntimeException('Failed to clone kaldibiblia.gitlab.io: ' . ($details !== '' ? $details : 'unknown error'));
        }
    }

    /**
     * @return array<int, array{slug:string,name:string,usx_code:string,order:int,abbrev:string,old_testament:int}>
     */
    private function parseBookDefinitions(string $publicPath): array
    {
        $html = file_get_contents($publicPath . '/tartalom.html');
        if ($html === false) {
            throw new RuntimeException('Could not read tartalom.html');
        }

        preg_match_all('/href="([a-z0-9]+)\/szoveg\.html"[^>]*>([^<]+)</iu', $html, $matches, PREG_SET_ORDER);

        $bookDefinitions = [];
        foreach ($matches as $match) {
            $slug = mb_strtolower(trim($match[1]), 'UTF-8');
            if (!isset(self::BOOK_SLUG_TO_USX[$slug])) {
                continue;
            }

            $name = trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $usxCode = self::BOOK_SLUG_TO_USX[$slug];
            $order = $this->detectBookOrder($publicPath, $slug);
            $abbrev = UsxCodes::getPreferredAbbreviation($usxCode, 'KAL')
                ?? UsxCodes::getPreferredAbbreviation($usxCode, 'default')
                ?? $slug;

            $bookDefinitions[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'usx_code' => $usxCode,
                'order' => $order,
                'abbrev' => $abbrev,
                'old_testament' => in_array($usxCode, UsxCodes::oldTestamentUsx(), true) ? 1 : 0,
            ];
        }

        usort($bookDefinitions, fn(array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_values($bookDefinitions);
    }

    private function detectBookOrder(string $publicPath, string $slug): int
    {
        $bookPath = $publicPath . '/' . $slug . '/szoveg.html';
        $html = file_get_contents($bookPath);
        if ($html === false) {
            throw new RuntimeException("Could not read book file: {$bookPath}");
        }

        if (preg_match('/name="(\d{2}):\d{3}(?:\.\d{3})?"/u', $html, $m) === 1) {
            return (int) $m[1];
        }

        throw new RuntimeException("Could not detect order in: {$bookPath}");
    }

    /**
     * @return array<int, array{chapter:int,numv:int,tip:int,verse:string}>
     */
    private function parseVersesForBook(string $publicPath, string $slug): array
    {
        $bookPath = $publicPath . '/' . $slug . '/szoveg.html';
        $html = file_get_contents($bookPath);
        if ($html === false) {
            throw new RuntimeException("Could not read book file: {$bookPath}");
        }

        return $this->parseVersesFromHtml($html, $slug);
    }

    /**
     * @return array<int, array{chapter:int,numv:int,tip:int,verse:string}>
     */
    private function parseVersesFromHtml(string $html, string $slug): array
    {
        $previousLibxmlUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $rows = [];
        $currentChapter = null;
        $currentVerse = null;
        $hasVerseInCurrentChapter = false;

        $collectDiagnostics = $this->output?->isVerbose() ?? false;

        $stats = [
            'paragraphs_total' => 0,
            'class_fejezet' => 0,
            'class_vers' => 0,
            'class_msonormal' => 0,
            'class_other' => 0,
            'chapter_anchor_missing_or_invalid' => 0,
            'verse_anchor_missing_or_invalid' => 0,
            'msonormal_empty_text' => 0,
            'msonormal_without_active_marker' => 0,
            'rows_verse' => 0,
            'chapter_arguments_dropped' => 0,
            'rows_appended_to_previous' => 0,
        ];

        $samples = [
            'unexpected_classes' => [],
            'invalid_chapter_anchor' => [],
            'invalid_verse_anchor' => [],
            'msonormal_without_context' => [],
        ];

        foreach ($xpath->query('//p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }

            if ($collectDiagnostics) {
                $stats['paragraphs_total']++;
            }

            $className = mb_strtolower(trim($paragraph->getAttribute('class')), 'UTF-8');
            if ($className === 'fejezet') {
                if ($collectDiagnostics) {
                    $stats['class_fejezet']++;
                }
                $anchorName = $this->extractAnchorName($paragraph);
                $parsed = $anchorName ? self::parseAnchorName($anchorName) : null;
                if ($collectDiagnostics && $parsed === null) {
                    $stats['chapter_anchor_missing_or_invalid']++;
                    if (count($samples['invalid_chapter_anchor']) < 3) {
                        $samples['invalid_chapter_anchor'][] = [
                            'anchor' => $anchorName,
                            'text' => mb_substr(self::normalizeHtmlText($this->innerHtml($paragraph)), 0, 120),
                        ];
                    }
                }
                $currentChapter = $parsed['chapter'] ?? null;
                $currentVerse = null;
                $hasVerseInCurrentChapter = false;
                continue;
            }

            if ($className === 'vers') {
                if ($collectDiagnostics) {
                    $stats['class_vers']++;
                }
                $anchorName = $this->extractAnchorName($paragraph);
                $currentVerse = $anchorName ? self::parseAnchorName($anchorName) : null;
                if ($collectDiagnostics && $currentVerse === null) {
                    $stats['verse_anchor_missing_or_invalid']++;
                    if (count($samples['invalid_verse_anchor']) < 3) {
                        $samples['invalid_verse_anchor'][] = [
                            'anchor' => $anchorName,
                            'text' => mb_substr(self::normalizeHtmlText($this->innerHtml($paragraph)), 0, 120),
                        ];
                    }
                }
                continue;
            }

            if ($className !== 'msonormal') {
                if ($collectDiagnostics) {
                    $stats['class_other']++;
                }
                if ($collectDiagnostics && count($samples['unexpected_classes']) < 5) {
                    $samples['unexpected_classes'][] = [
                        'class' => $className,
                        'text' => mb_substr(self::normalizeHtmlText($this->innerHtml($paragraph)), 0, 120),
                    ];
                }
                continue;
            }

            if ($collectDiagnostics) {
                $stats['class_msonormal']++;
            }

            $text = self::normalizeHtmlText($this->innerHtml($paragraph));
            if ($text === '') {
                if ($collectDiagnostics) {
                    $stats['msonormal_empty_text']++;
                }
                continue;
            }

            if ($currentVerse && isset($currentVerse['chapter'], $currentVerse['verse'])) {
                $rows[] = [
                    'chapter' => $currentVerse['chapter'],
                    'numv' => $currentVerse['verse'],
                    'tip' => 901,
                    'verse' => $text,
                ];
                if ($collectDiagnostics) {
                    $stats['rows_verse']++;
                }
                $hasVerseInCurrentChapter = true;
                $currentVerse = null;
                continue;
            }

            if ($currentChapter !== null && $hasVerseInCurrentChapter === false) {
                // Káldi prints a summary paragraph ("argumentum") before verse 1 of each
                // chapter. It has no verse number, so we skip it entirely rather than store
                // it as a phantom verse 0, which produced bogus references like "1Jan 2,0".
                if ($collectDiagnostics) {
                    $stats['chapter_arguments_dropped']++;
                }
                continue;
            }

            if (!empty($rows)) {
                $lastIndex = count($rows) - 1;
                if ($rows[$lastIndex]['tip'] === 901) {
                    $rows[$lastIndex]['verse'] .= ' ' . $text;
                    if ($collectDiagnostics) {
                        $stats['rows_appended_to_previous']++;
                    }
                    continue;
                }
            }

            if ($collectDiagnostics) {
                $stats['msonormal_without_active_marker']++;
            }
            if ($collectDiagnostics && count($samples['msonormal_without_context']) < 5) {
                $samples['msonormal_without_context'][] = [
                    'chapter' => $currentChapter,
                    'current_verse' => $currentVerse,
                    'has_verse_in_chapter' => $hasVerseInCurrentChapter,
                    'text' => mb_substr($text, 0, 120),
                ];
            }
        }

        if ($collectDiagnostics || count($rows) === 0) {
            $this->line(sprintf(
                'Kaldi DOM debug [%s]: p=%d, fejezet=%d, vers=%d, msonormal=%d, other=%d, rows=%d',
                $slug,
                $stats['paragraphs_total'],
                $stats['class_fejezet'],
                $stats['class_vers'],
                $stats['class_msonormal'],
                $stats['class_other'],
                count($rows)
            ));

            if ((($this->output?->isVeryVerbose() ?? false) && $collectDiagnostics) || count($rows) === 0) {
                $this->line('Kaldi DOM anomalies [' . $slug . ']: ' . json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        // Explicit release helps long-running imports keep memory usage stable.
        unset($xpath, $dom, $html, $stats, $samples);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlUseInternalErrors);

        return $rows;
    }

    /**
     * @return array<int, array{chapter:int,numv:int,verse:string}>
     */
    private function parseFootnotesForBook(string $publicPath, string $slug): array
    {
        $footnoteFiles = glob($publicPath . '/' . $slug . '/jegyzet.html') ?: [];
        if (count($footnoteFiles) === 0) {
            return [];
        }

        natsort($footnoteFiles);
        $rows = [];

        foreach ($footnoteFiles as $footnoteFile) {
            $html = file_get_contents($footnoteFile);
            if ($html === false) {
                continue;
            }

            $previousLibxmlUseInternalErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);

            $currentReference = null;

            foreach ($xpath->query('//p') as $paragraph) {
                if (!$paragraph instanceof DOMElement) {
                    continue;
                }

                $className = mb_strtolower(trim($paragraph->getAttribute('class')), 'UTF-8');
                if ($className === 'vers') {
                    $anchorName = $this->extractAnchorName($paragraph);
                    $parsedFromAnchor = $anchorName !== null ? self::parseAnchorName($anchorName) : null;
                    $parsedFromLabel = $this->parseChapterVerseFromLabel(self::normalizeHtmlText($this->innerHtml($paragraph)));

                    if ($parsedFromAnchor !== null && isset($parsedFromAnchor['chapter'], $parsedFromAnchor['verse'])) {
                        $currentReference = [
                            'chapter' => (int) $parsedFromAnchor['chapter'],
                            'verse' => (int) $parsedFromAnchor['verse'],
                        ];
                    } elseif ($parsedFromLabel !== null) {
                        $currentReference = $parsedFromLabel;
                    } else {
                        // Avoid carrying over a stale verse reference from previous lines.
                        $currentReference = null;
                    }

                    continue;
                }

                if ($className !== 'msonormal') {
                    continue;
                }

                if (!$currentReference || !isset($currentReference['chapter'], $currentReference['verse'])) {
                    continue;
                }

                $text = self::normalizeHtmlText($this->innerHtml($paragraph));
                if ($text === '') {
                    continue;
                }

                $rows[] = [
                    'chapter' => (int) $currentReference['chapter'],
                    'numv' => (int) $currentReference['verse'],
                    'verse' => $text,
                ];
            }

            unset($xpath, $dom, $html);
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlUseInternalErrors);
        }

        return $rows;
    }

    /**
     * @return array{chapter:int,verse:int}|null
     */
    private function parseChapterVerseFromLabel(string $label): ?array
    {
        if (preg_match('/(\d+)\s*,\s*(\d+)\s*$/u', $label, $m) !== 1) {
            return null;
        }

        return [
            'chapter' => (int) $m[1],
            'verse' => (int) $m[2],
        ];
    }

    private function releaseMemoryAfterBook(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        if ($this->output->isVeryVerbose()) {
            $this->line(sprintf(
                'Kaldi memory: usage=%dMB peak=%dMB',
                (int) round(memory_get_usage(true) / 1024 / 1024),
                (int) round(memory_get_peak_usage(true) / 1024 / 1024)
            ));
        }
    }

    private function extractAnchorName(DOMElement $paragraph): ?string
    {
        foreach ($paragraph->getElementsByTagName('a') as $anchor) {
            if ($anchor->hasAttribute('name')) {
                return trim((string) $anchor->getAttribute('name'));
            }
        }

        return null;
    }

    /**
     * @return array{book_order:int,chapter:int,verse?:int}|null
     */
    private static function parseAnchorName(string $anchorName): ?array
    {
        if (preg_match('/^(\d{2}):(\d{3})(?:\.(\d{3}))?$/', $anchorName, $m) !== 1) {
            return null;
        }

        $result = [
            'book_order' => (int) $m[1],
            'chapter' => (int) $m[2],
        ];

        if (isset($m[3]) && $m[3] !== '') {
            $result['verse'] = (int) $m[3];
        }

        return $result;
    }

    private static function normalizeHtmlText(string $htmlText): string
    {
        $textWithBreaks = preg_replace('/<br\s*\/?>/iu', ' ', $htmlText) ?? $htmlText;
        $stripped = strip_tags($textWithBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        $withoutRefs = preg_replace('/\[[^\]]*\]/u', '', $decoded) ?? $decoded;
        $singleSpaced = preg_replace('/\s+/u', ' ', $withoutRefs) ?? $withoutRefs;

        return trim($singleSpaced);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $childNode) {
            $html .= $element->ownerDocument->saveHTML($childNode);
        }

        return $html;
    }
}
