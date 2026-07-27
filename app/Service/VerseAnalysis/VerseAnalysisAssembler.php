<?php

namespace SzentirasHu\Service\VerseAnalysis;

use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;
use SzentirasHu\Mcp\GreekReferenceResolver;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Service\Reference\CanonicalReference;
use UnexpectedValueException;

class VerseAnalysisAssembler
{
    public function __construct(
        private readonly GreekReferenceResolver $greekReferenceResolver,
        private readonly VerseAnalysisSemanticValidator $semanticValidator,
    ) {}

    public function assemble(
        string $manifestPath,
        string $outputDirectory,
        string $createdBy,
    ): string {
        $manifest = $this->readJson($manifestPath);
        $reference = $manifest['reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            throw new UnexpectedValueException('The manifest has no valid reference.');
        }

        $greekVerses = $this->greekReferenceResolver
            ->versesFor(CanonicalReference::fromString($reference))
            ->values();

        if ($greekVerses->isEmpty()) {
            throw new UnexpectedValueException("No Greek verses found for '{$reference}'.");
        }

        $firstVerse = $greekVerses->firstOrFail();
        $this->validateManifestChapter($manifest, $greekVerses, $firstVerse);
        $semanticVerses = $this->semanticVerses($manifestPath, $manifest);
        $expectedVerseNumbers = $greekVerses->pluck('verse')->all();
        $actualVerseNumbers = array_column($semanticVerses, 'verse');

        if ($actualVerseNumbers !== $expectedVerseNumbers) {
            throw new UnexpectedValueException(
                'The assembled semantic verses do not match the complete chapter.'
            );
        }

        $semanticByVerse = collect($semanticVerses)->keyBy('verse');
        $analysisVerses = $greekVerses
            ->map(function (GreekVerse $verse) use ($semanticByVerse): array {
                $semanticVerse = $semanticByVerse->get($verse->verse);
                $words = $verse->annotatedWords();

                return [
                    'verse' => $verse->verse,
                    'gepi' => $verse->gepi,
                    'greekText' => str_replace('¶', '', $verse->text),
                    'segments' => array_map(
                        function (array $segment) use ($words): array {
                            $result = [
                                'wordIndexes' => $segment['wordIndexes'],
                                'greek' => implode(
                                    ' ',
                                    array_map(
                                        fn (int $index): string => $words[$index]['printed'],
                                        $segment['wordIndexes'],
                                    ),
                                ),
                                'meaning' => $segment['meaning'],
                            ];

                            if (isset($segment['alternatives'])) {
                                $result['alternatives'] = $segment['alternatives'];
                            }

                            if (isset($segment['note'])) {
                                $result['note'] = $segment['note'];
                            }

                            return $result;
                        },
                        $semanticVerse['segments'],
                    ),
                ];
            })
            ->all();
        $analysis = [
            'format' => 1,
            'usxCode' => $firstVerse->usx_code,
            'book' => $manifest['book'],
            'chapter' => $firstVerse->chapter,
            'greekSource' => $firstVerse->source,
            'createdBy' => $createdBy,
            'createdAt' => now()->toDateString(),
            'verses' => $analysisVerses,
        ];
        $this->ensureDirectoryExists($outputDirectory);
        $outputPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            ."{$firstVerse->usx_code}_{$firstVerse->chapter}.json";
        $this->writeJson($outputPath, $analysis);

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  Collection<int, GreekVerse>  $greekVerses
     */
    private function validateManifestChapter(
        array $manifest,
        Collection $greekVerses,
        GreekVerse $firstVerse,
    ): void {
        if (
            ($manifest['format'] ?? null) !== 1
            || ($manifest['usxCode'] ?? null) !== $firstVerse->usx_code
            || ($manifest['chapter'] ?? null) !== $firstVerse->chapter
            || ! is_string($manifest['book'] ?? null)
        ) {
            throw new UnexpectedValueException('The manifest does not match the referenced chapter.');
        }

        $chapters = $greekVerses->groupBy(
            fn (GreekVerse $verse): string => "{$verse->usx_code}_{$verse->chapter}"
        );

        if ($chapters->count() !== 1) {
            throw new UnexpectedValueException('The manifest reference must identify exactly one chapter.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, array{verse: int, segments: array<int, array<string, mixed>>}>
     */
    private function semanticVerses(string $manifestPath, array $manifest): array
    {
        $chunks = $manifest['chunks'] ?? null;

        if (! is_array($chunks) || $chunks === []) {
            throw new UnexpectedValueException('The manifest contains no chunks.');
        }

        $directory = dirname($manifestPath);
        $semanticVerses = [];

        foreach ($chunks as $chunk) {
            if (! is_array($chunk)) {
                throw new UnexpectedValueException('The manifest contains an invalid chunk.');
            }

            $sourcePath = $this->chunkPath($directory, $chunk, 'source');
            $semanticPath = $this->chunkPath($directory, $chunk, 'semantic');

            if (! is_file($semanticPath)) {
                throw new UnexpectedValueException("Semantic chunk is missing: {$semanticPath}");
            }

            $source = $this->readJson($sourcePath);
            $semantic = $this->readJson($semanticPath);
            $sourceHash = hash('sha256', $this->encode($source));

            if (
                ! is_string($chunk['sourceHash'] ?? null)
                || ! hash_equals($chunk['sourceHash'], $sourceHash)
            ) {
                throw new UnexpectedValueException("Compact source hash mismatch: {$sourcePath}");
            }

            try {
                $normalized = $this->semanticValidator->validate($source, $semantic);
            } catch (UnexpectedValueException $exception) {
                throw new UnexpectedValueException(
                    "Invalid semantic chunk {$semanticPath}: {$exception->getMessage()}",
                    previous: $exception,
                );
            }

            array_push($semanticVerses, ...$normalized['verses']);
        }

        return $semanticVerses;
    }

    /**
     * @param  array<string, mixed>  $chunk
     */
    private function chunkPath(string $directory, array $chunk, string $field): string
    {
        $filename = $chunk[$field] ?? null;

        if (! is_string($filename) || $filename === '' || basename($filename) !== $filename) {
            throw new UnexpectedValueException("The manifest chunk has an invalid {$field} filename.");
        }

        return $directory.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new UnexpectedValueException("JSON file does not exist: {$path}");
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(
                "Invalid JSON in {$path}: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException("JSON file does not contain an object: {$path}");
        }

        return $decoded;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create directory '{$directory}'.");
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function writeJson(string $path, array $value): void
    {
        $temporaryPath = tempnam(dirname($path), '.verse-analysis-');

        if ($temporaryPath === false) {
            throw new RuntimeException("Could not create a temporary file for '{$path}'.");
        }

        $json = json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        )."\n";

        if (file_put_contents($temporaryPath, $json) === false || ! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Could not write '{$path}'.");
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
