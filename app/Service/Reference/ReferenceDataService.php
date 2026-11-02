<?php

namespace SzentirasHu\Service\Reference;

use SzentirasHu\Data\UsxCodes;

/**
 * The data behind reference parsing (book abbreviations per translation and the vulgata
 * numbering scheme) as one JSON document, so that other projects (e.g. Magister) can parse
 * references the same way without a copy that drifts.
 *
 * The version is the hash of the exact JSON served, so a URL carrying it can be cached forever:
 * any change to the data yields a new version and thus a new URL.
 */
class ReferenceDataService
{
    private ?string $json = null;

    public function json(): string
    {
        if ($this->json === null) {
            $this->json = json_encode($this->data(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return $this->json;
    }

    public function version(): string
    {
        return hash('sha256', $this->json());
    }

    /**
     * @return array{
     *     books: array<string, array{testament: string, abbrevs: array<string, array<int, string>>}>,
     *     numbering: array{vulgata: array<string, mixed>}
     * }
     */
    private function data(): array
    {
        $books = [];
        foreach (['old' => UsxCodes::OLD_TESTAMENT, 'new' => UsxCodes::NEW_TESTAMENT] as $testament => $mapping) {
            foreach ($mapping as $usx => $abbrevs) {
                $books[$usx] = ['testament' => $testament, 'abbrevs' => $abbrevs];
            }
        }

        return [
            'books' => $books,
            'numbering' => [
                'vulgata' => config('numbering-scheme'),
            ],
        ];
    }
}
