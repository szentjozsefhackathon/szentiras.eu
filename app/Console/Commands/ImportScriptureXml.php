<?php

namespace SzentirasHu\Console\Commands;

use App;
use Artisan;
use Cache;
use Config;
use DB;
use Exception;
use SimpleXMLElement;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\UsxCodes;
use Illuminate\Support\Facades\Storage;

class ImportScriptureXml extends ImportScripture
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'szentiras:importScriptureXml';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update texts from XML source';

    /**
     * Mapping of heading Szint to tip for the current translation.
     *
     * @var array<int, int>
     */
    private $headingSzintToTip = [];

    /**
     * {@inheritdoc}
     */
    protected function readInserts(Translation $translation, string $transAbbrevToImport, string $filePath): array
    {
        $this->info("A $filePath fájl betöltése...");
        $xml = simplexml_load_file($filePath);
        if ($xml === false) {
            App::abort(500, "Nem sikerült betölteni az XML fájlt: $filePath");
        }

        // Load heading mapping for this translation
        $this->loadHeadingMapping($transAbbrevToImport);
        $this->stemmedVerseTypes = self::stemmedVerseTypesFor($transAbbrevToImport);

        // Hunspell stemming setup
        $pipes = [];
        $hunspellProcess = null;
        if ($this->hunspellEnabled) {
            $hunspellProcess = proc_open(
                'stdbuf -oL hunspell -m -d hu_HU -i UTF-8',
                $this->descriptorspec,
                $pipes,
                null,
                null
            );
        }

        // Load stems file if exists
        if (file_exists(ImportScripture::STEM_FILE)) {
            $this->info("A szótövek fájl betöltése...");
            $this->processedStems = json_decode(file_get_contents(ImportScripture::STEM_FILE), true);
            // fill the cache with the processed stems
            foreach ($this->processedStems as $word => $stems) {
                Cache::store("array")->put("hunspell_{$word}", $stems, 60 * 60 * 24);
            }
        }

        $bookInserts = [];
        $verseInserts = [];

        // Determine old testament based on Corpus Megnev
        $oldTestamentCorpusNames = ['ÓSZÖVETSÉG', 'ÓSZÖVETSÉGI', 'ÓSZ'];
        $newTestamentCorpusNames = ['ÚJSZÖVETSÉG', 'ÚJSZÖVETSÉGI', 'ÚJSZ'];

        // Process each Corpus
        foreach ($xml->Corpus as $corpus) {
            $corpusName = (string) $corpus['Megnev'];
            $isOldTestament = in_array($corpusName, $oldTestamentCorpusNames, true);

            // Process each SubCorpus (optional) and Book
            foreach ($corpus->SubCorpus as $subCorpus) {
                foreach ($subCorpus->Book as $book) {
                    $this->processBook($book, $transAbbrevToImport, $isOldTestament, $bookInserts, $verseInserts, $pipes);
                }
            }
            // Some Corpus may have Book directly without SubCorpus
            foreach ($corpus->Book as $book) {
                $this->processBook($book, $transAbbrevToImport, $isOldTestament, $bookInserts, $verseInserts, $pipes);
            }
        }

        // Also handle books at root level (if any)
        foreach ($xml->Book as $book) {
            $this->processBook($book, $transAbbrevToImport, false, $bookInserts, $verseInserts, $pipes);
        }

        // Save stems file (unless in production)
        if ('production' != Config::get("app.env")) {
            $this->info("A szótövek fájl mentése...");
            ksort($this->processedStems["_stems"]);
            ksort($this->processedStems);
            $serializedStems = json_encode($this->processedStems, JSON_PRETTY_PRINT);
            file_put_contents(ImportScripture::STEM_FILE, $serializedStems);
        }

        // Close Hunspell process if opened
        if ($this->hunspellEnabled) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if ($hunspellProcess) {
                proc_close($hunspellProcess);
            }
        }

        $this->info(sprintf("%d könyv és %d vers beolvasva.", count($bookInserts), count($verseInserts)));
        return [$bookInserts, $verseInserts];
    }

    /**
     * Load heading mapping from config for the given translation abbreviation.
     *
     * @param string $transAbbrev
     * @return void
     */
    private function loadHeadingMapping(string $transAbbrev): void
    {
        $this->headingSzintToTip = [];
        $config = Config::get("translations.definitions.{$transAbbrev}.verseTypes.heading", []);
        // config mapping is tip => Szint, we need inverse
        foreach ($config as $tip => $szint) {
            $this->headingSzintToTip[$szint] = $tip;
        }
    }

    /**
     * Process a single Book element.
     *
     * @param SimpleXMLElement $book
     * @param string $translationAbbrev
     * @param bool $isOldTestament
     * @param array &$bookInserts
     * @param array &$verseInserts
     * @param array $pipes Hunspell process pipes
     */
    private function processBook(
        SimpleXMLElement $book,
        string $translationAbbrev,
        bool $isOldTestament,
        array &$bookInserts,
        array &$verseInserts,
        array $pipes = []
    ): void {
        $kvssz = (int) $book['kvssz'];
        $usxrov = (string) $book['usxrov'];
        $abbrev = (string) $book['SzabvanyRov'];
        $name = (string) $book['SajatNev'];

        // Determine USX code from usxrov or abbreviation
        $usxCode = UsxCodes::getUsxFromBookAbbrevAndTranslation($abbrev, $translationAbbrev);
        if (is_null($usxCode)) {
            $usxCode = $usxrov; // fallback
        }

        // Determine old testament based on USX code if not already known
        if (!$isOldTestament) {
            $isOldTestament = self::isOldTestament($usxCode);
        }

        $bookInsert = [
            'order' => $kvssz,
            'abbrev' => $abbrev,
            'usx_code' => $usxCode,
            'translation' => $translationAbbrev,
            'name' => $name,
            'link' => $this->removeAccents($abbrev),
            'old_testament' => $isOldTestament ? 1 : 0,
        ];
        $bookInserts[] = $bookInsert;

        // Process headings (Cimsor) at book level
        foreach ($book->Cimsor as $heading) {
            $this->processHeading($heading, $kvssz, $usxCode, $verseInserts, null, $pipes);
        }

        // Process chapters and verses
        foreach ($book->Chapter as $chapter) {
            $chapterNr = (int) $chapter['Nr'];
            // Process chapter-level headings (Cimsor inside Chapter)
            foreach ($chapter->Cimsor as $heading) {
                $this->processHeading($heading, $kvssz, $usxCode, $verseInserts, $chapterNr, $pipes);
            }
            foreach ($chapter->Verse as $verse) {
                $this->processVerse($verse, $kvssz, $usxCode, $chapterNr, $verseInserts, $pipes);
            }
        }
    }

    /**
     * Process a heading (Cimsor) element.
     *
     * @param SimpleXMLElement $heading
     * @param int $order
     * @param string $usxCode
     * @param array &$verseInserts
     * @param int|null $chapterNr
     * @param array $pipes Hunspell process pipes
     */
    private function processHeading(
        SimpleXMLElement $heading,
        int $order,
        string $usxCode,
        array &$verseInserts,
        ?int $chapterNr = null,
        array $pipes = []
    ): void {
        $szint = (int) $heading['Szint'];
        $tip = $this->headingSzintToTip[$szint] ?? 401;
        $text = (string) $heading->Alapszoveg;

        // Determine chapter and verse from ElsoVers attribute if present
        $elsoVers = (string) $heading['ElsoVers'];
        if ($elsoVers) {
            [$chapter, $verseNr] = $this->parseReference($elsoVers);
        } else {
            $chapter = $chapterNr ?? 1;
            $verseNr = 0; // placeholder for heading without verse number
        }

        $verseroot = null;
        if ($this->hunspellEnabled && in_array($tip, $this->stemmedVerseTypes)) {
            $verseroot = $this->executeStemming($text, $pipes);
        }

        $verseInsert = [
            'original_book_code' => sprintf('%03d%03d%03d', $order, $chapter, $verseNr),
            'order' => $order,
            'chapter' => $chapter,
            'numv' => $verseNr,
            'tip' => $tip,
            'verse' => $text,
            'verseroot' => $verseroot,
            'ido' => null,
        ];
        $verseInserts[] = $verseInsert;
    }

    /**
     * Process a Verse element.
     *
     * @param SimpleXMLElement $verse
     * @param int $order
     * @param string $usxCode
     * @param int $chapterNr
     * @param array &$verseInserts
     */
    private function processVerse(
        SimpleXMLElement $verse,
        int $order,
        string $usxCode,
        int $chapterNr,
        array &$verseInserts,
        array $pipes = []
    ): void {
        $verseNr = (int) $verse['Nr'];
        $hiv = (string) $verse['hiv'];

        // Determine if there are any Strofa elements
        $strofaElements = $verse->Strofa;
        if (count($strofaElements) === 0) {
            // No Strofa, fallback to VersAlapszoveg
            $text = (string) $verse->VersAlapszoveg;
            $tip = 901;
            $verseroot = null;
            if ($this->hunspellEnabled && in_array($tip, $this->stemmedVerseTypes)) {
                $verseroot = $this->executeStemming($text, $pipes);
            }
            $verseInsert = [
                'original_book_code' => sprintf('%03d%03d%03d', $order, $chapterNr, $verseNr),
                'order' => $order,
                'chapter' => $chapterNr,
                'numv' => $verseNr,
                'tip' => $tip,
                'verse' => $text,
                'verseroot' => $verseroot,
                'ido' => null,
            ];
            $verseInserts[] = $verseInsert;
        } else {
            // Process each Strofa as separate verse record
            foreach ($strofaElements as $strofa) {
                $style = (string) $strofa['Style'];
                $text = (string) $strofa->Alapszoveg;
                // Determine tip based on style
                $tip = ($style === 'q') ? 902 : 901;
                // If style pk, add <br> to the end of previous verse if it's normal text (tip 901)
                if ($style === 'pk' && !empty($verseInserts)) {
                    $lastIndex = count($verseInserts) - 1;
                    if ($verseInserts[$lastIndex]['tip'] === 901) {
                        $verseInserts[$lastIndex]['verse'] .= '<br>';
                    }
                }
                $verseroot = null;
                if ($this->hunspellEnabled && in_array($tip, $this->stemmedVerseTypes)) {
                    $verseroot = $this->executeStemming($text, $pipes);
                }
                $verseInsert = [
                    'original_book_code' => sprintf('%03d%03d%03d', $order, $chapterNr, $verseNr),
                    'order' => $order,
                    'chapter' => $chapterNr,
                    'numv' => $verseNr,
                    'tip' => $tip,
                    'verse' => $text,
                    'verseroot' => $verseroot,
                    'ido' => null,
                ];
                $verseInserts[] = $verseInsert;
            }
        }

        // Process footnotes (Labjegyzet) inside this Verse
        foreach ($verse->Labjegyzet as $footnote) {
            $this->processFootnote($footnote, $order, $usxCode, $chapterNr, $verseNr, $verseInserts, $pipes);
        }
    }

    /**
     * Process a footnote (Labjegyzet) element.
     *
     * @param SimpleXMLElement $footnote
     * @param int $order
     * @param string $usxCode
     * @param int $chapterNr
     * @param int $verseNr
     * @param array &$verseInserts
     * @param array $pipes Hunspell process pipes
     */
    private function processFootnote(
        SimpleXMLElement $footnote,
        int $order,
        string $usxCode,
        int $chapterNr,
        int $verseNr,
        array &$verseInserts,
        array $pipes = []
    ): void {
        // Collect text from LabjSzoveg children (Sz and K)
        $text = '';
        foreach ($footnote->LabjSzoveg->children() as $child) {
            if ($child->getName() === 'Sz') {
                $text .= (string) $child->T;
            } elseif ($child->getName() === 'K') {
                // Use the F attribute (Hungarian abbreviated reference)
                $text .= (string) $child['F'];
            }
        }

        $verseroot = null;
        // Footnote tip 2001 is not stemmed! 

        $verseInsert = [
            'original_book_code' => sprintf('%03d%03d%03d', $order, $chapterNr, $verseNr),
            'order' => $order,
            'chapter' => $chapterNr,
            'numv' => $verseNr,
            'tip' => 2001,
            'verse' => $text,
            'verseroot' => $verseroot,
            'ido' => null,
        ];
        $verseInserts[] = $verseInsert;
    }

    /**
     * Parse reference like "GEN 1:1" into chapter and verse.
     *
     * @param string $ref
     * @return array [int $chapter, int $verse]
     */
    private function parseReference(string $ref): array
    {
        $parts = explode(' ', $ref);
        $chapterVerse = end($parts);
        list($chapter, $verse) = explode(':', $chapterVerse);
        return [(int) $chapter, (int) $verse];
    }

    /**
     * Ensure proper file extension (xml) or convert if needed.
     *
     * @param string $originalFilePath
     * @return string
     */
    protected function ensureProperFile(string $originalFilePath): string
    {
        if (!file_exists($originalFilePath)) {
            App::abort(500, "A fájl nem található: $originalFilePath");
        }

        $fileExtension = pathinfo($originalFilePath, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) === 'xml') {
            return $originalFilePath;
        }

        App::abort(500, "A fájl nem XML: $originalFilePath ($fileExtension)");
    }

    /**
     * Skip translation book columns verification for XML import.
     *
     * @param string $translationAbbrev
     * @return void
     */
    protected function verifyTranslationBookColumns(string $translationAbbrev): void
    {
        // No-op: XML import does not need Excel column mapping
    }

    protected function downloadTranslation(string $transAbbrev, string $url): string
    {
        try {
            $filePath = $this->sourceDirectory . '/' . $transAbbrev . '.xml';
            $this->info("A fájl letöltése a $url címről...: $filePath");
            if ($url == 's3') {
                $file = Storage::disk('s3')->get("xml/{$transAbbrev}.xml");
                file_put_contents($filePath, $file);
            } else {
                $fp = fopen($filePath, 'w+');
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            }
        } catch (Exception $ex) {
            App::abort(500, "Nem sikerült fáljt letölteni a megadott url-ről.");
        }
        return $filePath;
    }
}