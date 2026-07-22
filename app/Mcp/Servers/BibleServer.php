<?php

namespace SzentirasHu\Mcp\Servers;

use Laravel\Mcp\Server;
use SzentirasHu\Mcp\Tools\GetGreekVersesTool;
use SzentirasHu\Mcp\Tools\GetVersesTool;
use SzentirasHu\Mcp\Tools\ListTranslationsTool;
use SzentirasHu\Mcp\Tools\LookupGreekWordTool;
use SzentirasHu\Mcp\Tools\SearchGreekTool;
use SzentirasHu\Mcp\Tools\SearchVersesTool;

class BibleServer extends Server
{
    protected string $name = 'Szentírás';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Provides verbatim Hungarian Bible text and the original Greek New Testament from szentiras.eu.

    Always call `get-verses` instead of quoting scripture from memory: the text returned is
    the exact wording of a specific published translation.

    When you quote a verse, always name the translation (its `abbrev`, e.g. RUF or SZIT).
    The translation this endpoint answers with reflects the user's own tradition, so never
    substitute a different one. Only pass the `translation` argument when the user explicitly
    asks for another translation.

    For questions about the original wording of the New Testament — what a Greek word means,
    how a form is parsed, what stands behind a Hungarian rendering — call `get-greek-verses`
    rather than recalling Greek from memory. It returns each word with its lemma, Strong
    number and morphology. Follow a Strong number with `lookup-greek-word` for the full
    dictionary entry, or with `search-greek` to find every verse the word occurs in. The
    Greek text covers the New Testament only.

    When the reference is not known — a half remembered wording, or a question about where
    something is written — call `search-verses` before answering from memory, and quote the
    verses it returns. Both search tools answer with a small number of verses by default;
    narrow the search by book rather than asking for a large limit.

    References use Hungarian notation: a comma separates chapter and verse (`Jn 3,16`), a
    hyphen marks a range (`1Kor 13,4-7`), and a semicolon separates books or chapters (`Jn 1;3`).
    MARKDOWN;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>|\Laravel\Mcp\Server\Tool>
     */
    protected array $tools = [
        GetVersesTool::class,
        SearchVersesTool::class,
        ListTranslationsTool::class,
        GetGreekVersesTool::class,
        SearchGreekTool::class,
        LookupGreekWordTool::class,
    ];
}
