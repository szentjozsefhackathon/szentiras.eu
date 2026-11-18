<?php

namespace SzentirasHu\Service\FamousPassage;

/**
 * Cuts the sentence that cites a Bible reference out of a Church document's unit, so the page can
 * quote the document's own words: „…” (1Kor 13,4–7), or a sentence whose footnote names the verse.
 *
 * A citation that follows a quotation takes the whole quotation with it, even if it runs over
 * several sentences, together with the sentence that introduces it.
 */
class CitationQuoteExtractor
{
    private const MAX_LENGTH = 700;

    private const MAX_TAIL_LENGTH = 200;

    private const ELLIPSIS = '(…)';

    private const MIN_OWN_WORDS = 4;

    private const ABBREVIATIONS = ['vö', 'pl', 'ld', 'lásd', 'ti', 'ill', 'uo', 'ua', 'ford', 'kb', 'ún', 'szt', 'sz', 'ker', 'kr', 'u', 'stb'];

    /**
     * A full stop before a capital, or a quotation closed with its citation and no full stop
     * after it: …tökéletes!" (Mt 5,48) Mindegyikükre…
     */
    private const SENTENCE_BREAK = '/(?:[.!?…][”"’]|[”"’][.!?…])\s*\([^()]*\d[^()]*\)(?:\[\d+\])*\s+(?=[„"]?\p{Lu})|[.!?…][”"’)]*(?:\[\d+\])*\s+(?=[„"(\[]?\p{Lu})/u';

    private const SENTENCE_END = '/[.!?…][”"’)]*(?:\[\d+\])*(?=\s+[„"(\[]?\p{Lu}|\s*$)/u';

    /**
     * @param  string  $text  the unit's text; paragraphs are separated by blank lines
     * @param  array<int, array{n?: int|string, text?: string}>  $footnotes
     * @param  string  $cites  the reference the unit cites, e.g. "1Kor 13,4-7" or "1Kor 13,8;3,14"
     */
    public function extract(string $text, array $footnotes, string $cites): ?string
    {
        $referencePattern = $this->referencePattern($cites);
        if ($referencePattern === null) {
            return null;
        }

        $paragraphs = array_values(array_filter(array_map(
            fn (string $paragraph): string => $this->normalize($paragraph),
            preg_split('/\n\s*\n/u', $text) ?: [],
        )));

        foreach ($paragraphs as $paragraph) {
            if (preg_match($referencePattern, $paragraph, $match, PREG_OFFSET_CAPTURE)) {
                return $this->sentenceAround($paragraph, $match[0][1], $match[0][1] + strlen($match[0][0]));
            }
        }

        foreach ($footnotes as $footnote) {
            if (! isset($footnote['n'], $footnote['text']) || ! preg_match($referencePattern, $this->normalize((string) $footnote['text']))) {
                continue;
            }
            $marker = '['.$footnote['n'].']';
            foreach ($paragraphs as $paragraph) {
                $position = strpos($paragraph, $marker);
                if ($position !== false) {
                    return $this->sentenceAround($paragraph, $position, $position + strlen($marker));
                }
            }
        }

        return null;
    }

    /**
     * One line per paragraph, cross-references (">>954") removed, and the double hyphen of the
     * source ("13,4--7", "Krisztus -- még") turned into a dash.
     */
    private function normalize(string $text): string
    {
        $text = preg_replace('/\s*>>\d+(?:,\s*>>\d+)*/u', '', $text) ?? $text;
        $text = str_replace('--', '–', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * The first reference of the list ("1Kor 13,8" of "1Kor 13,8;3,14"), allowing the spacing and
     * dashes the documents use ("1 Kor 13, 4–7"), and not matching the start of a longer number.
     */
    private function referencePattern(string $cites): ?string
    {
        $firstReference = trim(explode(';', $cites)[0]);
        if (! preg_match('/^(\d?)\s*(\p{L}+)\s*(\d+)(?:,(.+))?$/u', $firstReference, $parts)) {
            return null;
        }
        [, $bookNumber, $bookName, $chapter] = $parts;
        $verses = $parts[4] ?? '';

        $pattern = ($bookNumber === '' ? '' : preg_quote($bookNumber, '/').'\s?')
            .preg_quote($bookName, '/').'\s*'.preg_quote($chapter, '/');
        if ($verses !== '') {
            $versePattern = implode('\s*[-–—]\s*', array_map(
                fn (string $verse): string => preg_quote(trim($verse), '/'),
                preg_split('/[-–—]/u', $verses) ?: [],
            ));
            $pattern .= '\s*,\s*'.$versePattern;
        }

        return '/(?<![\p{L}\d])'.$pattern.'(?!\d)/u';
    }

    private function sentenceAround(string $paragraph, int $citationStart, int $citationEnd): ?string
    {
        $quotationStart = $this->openingQuoteBefore($paragraph, $citationStart) ?? $citationStart;
        $start = $this->sentenceStartBefore($paragraph, $quotationStart);
        if (trim(substr($paragraph, $start, $citationStart - $start), ' (') === '') {
            if ($start === 0) {
                return null;
            }
            $start = $this->sentenceStartBefore($paragraph, $start - 1);
            $end = $citationEnd + strspn($paragraph, ')', $citationEnd);
        } else {
            $end = $this->sentenceEndAfter($paragraph, $citationEnd, $citationStart);
        }

        $head = $this->clean(substr($paragraph, $start, $citationEnd - $start));
        $tail = $this->clean(substr($paragraph, $citationEnd, $end - $citationEnd), keepLeadingSpace: true);

        if (mb_strlen($tail) > self::MAX_TAIL_LENGTH) {
            $tail = $this->cutAtWord($tail, self::MAX_TAIL_LENGTH, fromEnd: false).' '.self::ELLIPSIS;
        }
        $headLength = self::MAX_LENGTH - mb_strlen($tail);
        if (mb_strlen($head) > $headLength) {
            $head = self::ELLIPSIS.' '.$this->cutAtWord($head, $headLength, fromEnd: true);
        }

        $quote = trim($head.$tail);

        return $this->hasOwnWords($quote) ? $quote : null;
    }

    /**
     * Where the quotation a citation closes begins: „A szeretet nem keresi a magáét” (1Kor 13,5),
     * or with straight quotes: "Bocsásd meg a mi vétkeinket" (Mt 6,12).
     */
    private function openingQuoteBefore(string $paragraph, int $citationStart): ?int
    {
        $before = rtrim(substr($paragraph, 0, $citationStart), " ([\u{00A0}");
        $before = rtrim($before, '.!?');
        if (str_ends_with($before, '”')) {
            $openingQuote = strrpos($before, '„');
        } elseif (str_ends_with($before, '"')) {
            $openingQuote = strrpos(substr($before, 0, -1), '"');
        } else {
            return null;
        }

        return $openingQuote === false ? null : $openingQuote;
    }

    private function sentenceStartBefore(string $paragraph, int $position): int
    {
        $start = 0;
        preg_match_all(self::SENTENCE_BREAK, $paragraph, $breaks, PREG_OFFSET_CAPTURE);
        foreach ($breaks[0] as [$break, $offset]) {
            if ($offset + strlen($break) > $position) {
                break;
            }
            if (! $this->isAbbreviation($paragraph, $offset)) {
                $start = $offset + strlen($break);
            }
        }

        return $start;
    }

    /**
     * A footnote marker right after a full stop ("szeretet.[2717]") closes its sentence.
     */
    private function sentenceEndAfter(string $paragraph, int $position, int $citationStart): int
    {
        $before = rtrim(substr($paragraph, 0, $citationStart), ' (');
        if (preg_match('/[.!?…][”"’)]*$/u', $before, $closing, PREG_OFFSET_CAPTURE) && ! $this->isAbbreviation($before, $closing[0][1])) {
            return $position + strspn($paragraph, ')', $position);
        }
        preg_match_all(self::SENTENCE_END, $paragraph, $ends, PREG_OFFSET_CAPTURE, $position);
        foreach ($ends[0] as [$end, $offset]) {
            if (! $this->isAbbreviation($paragraph, $offset)) {
                return $offset + strlen($end);
            }
        }

        return strlen($paragraph);
    }

    /**
     * Whether the full stop at the offset closes an abbreviation ("vö.", "pl."), an initial or an
     * ordinal ("II. János Pál", "8. cikkely") rather than a sentence.
     */
    private function isAbbreviation(string $paragraph, int $offset): bool
    {
        if ($paragraph[$offset] !== '.' || ! preg_match('/([\p{L}\d]+)$/u', substr($paragraph, 0, $offset), $word)) {
            return false;
        }

        return in_array(mb_strtolower($word[1]), self::ABBREVIATIONS, true)
            || preg_match('/^(\d+|[IVXLC]+|\p{Lu})$/u', $word[1]) === 1;
    }

    /**
     * At least a few words of the document's own, not a bare reference such as "(Mt 6,9–13)".
     */
    private function hasOwnWords(string $quote): bool
    {
        $withoutReferences = preg_replace('/\([^()]*\d[^()]*\)?|\(…\)/u', '', $quote) ?? $quote;

        return preg_match_all('/\p{L}{2,}/u', $withoutReferences) >= self::MIN_OWN_WORDS;
    }

    /**
     * Without footnote markers ("[1822]"), which point to notes the quotation does not carry.
     */
    private function clean(string $text, bool $keepLeadingSpace = false): string
    {
        $text = preg_replace('/\s*\[\d+\]/u', '', $text) ?? $text;

        return $keepLeadingSpace ? rtrim($text) : trim($text);
    }

    private function cutAtWord(string $text, int $length, bool $fromEnd): string
    {
        if ($fromEnd) {
            $cut = mb_substr($text, -$length);
            $space = mb_strpos($cut, ' ');

            return $space === false ? $cut : mb_substr($cut, $space + 1);
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return $space === false ? $cut : mb_substr($cut, 0, $space);
    }
}
