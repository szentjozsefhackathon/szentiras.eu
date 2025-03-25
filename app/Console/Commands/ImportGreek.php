<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Log;
use Normalizer;
use Pgvector\Laravel\Vector;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseEmbedding;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Search\SemanticSearchService;
use Throwable;
use ZipArchive;

class ImportGreek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'szentiras:import-greek
        {--skip-words : Skip the import of the strong words }
        {--skip-verses : Skip importing the verses. Useful if it is already imported, and you only want to generate the vectors. }
        {--create-vectors : Create the vectors based on the existing verses }
        {--source= : filesystem or s3. Must be given if target is db.}        
        {--target=db : filesystem, s3 or db }
        {--text-source=BMT : BMT or OpenGNT }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports greek text. ';

    /** a mapping of BMT abbreviations to USX codes, like 'MAR' => 'MRK'
     */
    const ABBREVIATION_MAPPING = [
        'MAT' => 'MAT',
        'MAR' => 'MRK',
        'LUK' => 'LUK',
        'JOH' => 'JHN',
        'ACT' => 'ACT',
        'ROM' => 'ROM',
        '1CO' => '1CO',
        '2CO' => '2CO',
        'GAL' => 'GAL',
        'EPH' => 'EPH',
        'PHP' => 'PHP',
        'COL' => 'COL',
        '1TH' => '1TH',
        '2TH' => '2TH',
        '1TI' => '1TI',
        '2TI' => '2TI',
        'TIT' => 'TIT',
        'PHM' => 'PHM',
        'HEB' => 'HEB',
        'JAM' => 'JAS',
        '1PE' => '1PE',
        '2PE' => '2PE',
        '1JO' => '1JN',
        '2JO' => '2JN',
        '3JO' => '3JN',
        'JUD' => 'JUD',
        'REV' => 'REV'
    ];

    const GREEK_TRANSLITERATIONS = [
        'α' => 'a',
        'β' => 'b',
        'γ' => 'g',
        'δ' => 'd',
        'ε' => 'e',
        'ζ' => 'z',
        'η' => 'ē',
        'θ' => 'th',
        'ι' => 'i',
        'κ' => 'k',
        'λ' => 'l',
        'μ' => 'm',
        'ν' => 'n',
        'ξ' => 'x',
        'ο' => 'o',
        'π' => 'p',
        'ρ' => 'r',
        'σ' => 's',
        'ς' => 's',
        'τ' => 't',
        'υ' => 'u',
        'φ' => 'ph',
        'χ' => 'ch',
        'ψ' => 'ps',
        'ω' => 'ō',

        'Α' => 'A',
        'Β' => 'B',
        'Γ' => 'G',
        'Δ' => 'D',
        'Ε' => 'E',
        'Ζ' => 'Z',
        'Η' => 'Ē',
        'Θ' => 'Th',
        'Ι' => 'I',
        'Κ' => 'K',
        'Λ' => 'L',
        'Μ' => 'M',
        'Ν' => 'N',
        'Ξ' => 'X',
        'Ο' => 'O',
        'Π' => 'P',
        'Ρ' => 'R',
        'Σ' => 'S',
        'Τ' => 'T',
        'Υ' => 'U',
        'Φ' => 'Ph',
        'Χ' => 'Ch',
        'Ψ' => 'Ps',
        'Ω' => 'Ō',
    ];

    private string $textSource;
    private string $model;
    private int $dimensions;

    private $strongWords = [];

    public function __construct(protected SemanticSearchService $semanticSearchService)
    {
        parent::__construct();
        $this->model = \Config::get("settings.ai.embeddingModel");
        $this->dimensions = \Config::get("settings.ai.embeddingDimensions");
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->textSource = $this->option('text-source');
        if (!$this->option('skip-verses')) {
            GreekVerse::truncate();
            $this->downloadFiles();
        }
        if (!$this->option('skip-words')) {
            StrongWord::truncate();
            $this->fillStrongWordsTable();
        }
        foreach (StrongWord::all() as $strongWord) {
            $this->strongWords[$strongWord->number] = $strongWord;
        }
        if (!$this->option('skip-verses')) {
            $this->fillGreekVerseTable();
        }
        if ($this->option('create-vectors')) {
            $this->createVectors();
        }
    }

    private function fillGreekVerseTable()
    {
        setlocale(LC_ALL, "el_GR.UTF-8");
        $parsedVerses = [];
        $unparsedVerses = [];
        foreach (self::ABBREVIATION_MAPPING as $abbrev => $usxCode) {
            $file = Storage::readStream("greek/{$this->textSource}/parsed/{$usxCode}.csv");
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
                $chapter = (int)$data['chapter'];
                $verse = (int)$data['verse'];
                $parsedVerses[$usxCode][$chapter][$verse] = $data['text'];
            }
            $file = Storage::readStream("greek/{$this->textSource}/unparsed/{$usxCode}.csv");
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
                $chapter = (int) $data['chapter'];
                $verse = (int) $data['verse'];
                $unparsedVerses[$usxCode][$chapter][$verse] = $data['text'];
            }
        }
        $progressBar = $this->output->createProgressBar(count($parsedVerses));
        foreach ($parsedVerses as $usxCode => $parsedBookVerses) {
            $progressBar->advance();
            foreach ($parsedBookVerses as $chapter => $parsedBookVerse) {
                foreach ($parsedBookVerse as $verse => $parsedText) {
                    $unparsedText = $unparsedVerses[$usxCode][$chapter][$verse];
                    if (empty($unparsedText)) {
                        $this->warn("No unparsed text for {$usxCode} {$chapter}:{$verse}");
                        continue;
                    }

                    $parsedTextRegex = '/(\p{L}+) (\d+) \{([^\}]+)\}/u';
                    $matches = [];
                    preg_match_all($parsedTextRegex, $parsedText, $matches, PREG_SET_ORDER);

                    if (empty($matches)) {
                        $this->warn("No regex match for {$usxCode} {$chapter}:{$verse}");
                        continue;
                    }

                    $jsonElements = [];
                    $strongNumbers = [];
                    $strongElements = [];
                    $strongTranslits = [];
                    $strongNormals = [];
                    foreach ($matches as $match) {
                        $word = $match[1];
                        $strongNumber = $match[2];
                        $morphology = $match[3];
                        $jsonElements[] = [
                            'word' => $word,
                            'strong' => $strongNumber,
                            'morphology' => $morphology
                        ];
                        $strongNumbers[] = $strongNumber;
                        if (!array_key_exists($strongNumber, $this->strongWords)) {
                            $this->warn("Unknown Strong number: {$strongNumber} in {$usxCode}");
                            $strongElements[] = "X";
                            $strongTranslits[] = "X";
                            $strongNormals[] = "X";
                        } else {
                            $strongElements[] = $this->strongWords[$strongNumber]->lemma;
                            $strongTranslits[] = $this->strongWords[$strongNumber]->transliteration;
                            $strongNormals[] = $this->strongWords[$strongNumber]->normalized;
                        }
                    }

                    $transliteration = $this->transliterate($unparsedText);
                    $normalization = $this->normalize($transliteration);

                    $greekVerse = new GreekVerse();
                    $greekVerse->source = $this->textSource;
                    $greekVerse->usx_code = $usxCode;
                    $greekVerse->gepi = "{$usxCode}_{$chapter}_{$verse}";
                    $greekVerse->chapter = $chapter;
                    $greekVerse->verse = $verse;
                    $greekVerse->text = $unparsedText;
                    $greekVerse->transliteration = $transliteration;
                    $greekVerse->normalization = $normalization;
                    $greekVerse->json = json_encode($jsonElements);
                    $greekVerse->strongs = implode(' ', $strongElements);
                    $greekVerse->strong_transliterations = implode(' ', $strongTranslits);
                    $greekVerse->strong_normalizations = implode(' ', $strongNormals);
                    $greekVerse->save();

                    $strongWordsToAttach = [];
                    foreach ($strongNumbers as $position => $strongNumber) {
                        if (array_key_exists($strongNumber, $this->strongWords)) {
                            $strongWordsToAttach[$this->strongWords[$strongNumber]->id] = ['position' => $position];
                        }                        
                    }
                    $greekVerse->strongWords()->attach($strongWordsToAttach);
                }
            }
        }

        $progressBar->finish();
        $this->output->newline();
    }

    private function fillStrongWordsTableXml()
    {
        $xmlFile = Storage::get("greek/dictionary.xml");
        $this->info('Fill Strong words');
        // parse the xml
        $xml = simplexml_load_string($xmlFile);
        $progressBar = $this->output->createProgressBar(5624);
        foreach ($xml->xpath('//entry[@strongs]') as $entry) {
            $strongWord = new StrongWord();
            $strongWord->number = (int)$entry['strongs'];
            $strongWord->lemma = (string) $entry->greek['unicode'];
            if (!$strongWord->lemma) {
                continue;
            }
            $strongWord->transliteration = $this->transliterate($strongWord->lemma);
            $normalizedText = $this->normalize($strongWord->transliteration);
            $cleanText = preg_replace('/\p{Mn}/u', '', $normalizedText);
            $normalized = strtolower($cleanText);
            $strongWord->normalized = $normalized;
            $strongWord->save();
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newline();
    }

    private function fillStrongWordsTable()
    {
        $txtFile = Storage::get("greek/dictionary.txt");
        $this->info('Fill Strong words');
        // parse the TSV file
        // skip first rows up until the string "============================================================================================================="
        $lines = explode("\n", $txtFile);
        $start = false;
        $strongWords = [];      
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            if (str_contains($line, "=============================================================================================================")) {
                $start = true;
                continue;
            }
            if (!$start) {
                continue;
            }
            $parts = explode("\t", $line);
            $strongNumber = (int)str_replace("G", "", $parts[0]);
            $lemma = $parts[3];
            $transliteration = $this->transliterate($lemma);
            $normalizedText = $this->normalize($transliteration);
            $cleanText = preg_replace('/\p{Mn}/u', '', $normalizedText);
            $normalized = strtolower($cleanText);
            $strongWords[$strongNumber] = [
                'number' => $strongNumber,
                'lemma' => $lemma,
                'transliteration' => $transliteration,
                'normalized' => $normalized
            ];
        }
        $progressBar = $this->output->createProgressBar(count($strongWords));
        foreach ($strongWords as $strongWordData) {
            $progressBar->advance();
            $strongWord = new StrongWord();
            $strongWord->number = $strongWordData['number'];
            $strongWord->lemma = $strongWordData['lemma'];
            $strongWord->transliteration = $strongWordData['transliteration'];
            $strongWord->normalized = $strongWordData['normalized'];
            $strongWord->save();
        }

        $progressBar->finish();
        $this->output->newline();
    }

    private function normalize($text)
    {
        $normalizedText = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $cleanText = preg_replace('/\p{Mn}/u', '', $normalizedText);
        $normalized = strtolower($cleanText);
        return $normalized;
    }

    private function transliterate($text)
    {
        $text = str_replace('¶', '', $text);
        $text = Normalizer::normalize($text, Normalizer::NFKD);
        $words = explode(' ', $text);
        $transliteratedWords = [];
        foreach ($words as $word) {
            $transliteratedWords[] = $this->transliterateWord($word);
        }
        return implode(' ', $transliteratedWords);
    }

    /**
     * Transliteration inspired by https://github.com/charlesLoder/greek-transliteration 
     * Original is MIT License: https://github.com/charlesLoder/greek-transliteration/blob/main/LICENSE
     * Copyright 2020 Charles Loder
     * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
     * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
     */
    private function transliterateWord($word)
    {
        $unused = "/[xu{00B7}\x{0300}\x{0301}\x{0304}\x{0306}\x{0313}\x{0342}\x{0345}]/u";
        $word = preg_replace($unused, "", $word);

        $word = str_replace("γγ", 'ng', $word);
        $word = str_replace("γκ", 'ng', $word);
        $word = str_replace("γξ", 'nx', $word);
        $word = str_replace("γχ", 'nch', $word);
        $word = str_replace("ρ\u{0314}", "rh", $word);
        $word = str_replace("ρρ", "rr", $word);
        $word = str_replace("Ρ\u{0314}", "Rh", $word);
        $word = str_replace("Ρρ", "Rr", $word);

        $diaeresisPos = mb_strpos($word, "\u{0308}");
        if ($diaeresisPos !== false) {
            $part1 = mb_substr($word, 0, $diaeresisPos - 1);
            $part2 = mb_substr($word, $diaeresisPos - 1, 1);
            $part3 = mb_substr($word, $diaeresisPos);
            $word = $part1 . "\u{0308}" . $part2 . $part3;
        }

        $breathingMark = "\u{0314}";
        $pos = mb_strpos($word, $breathingMark);
        if ($pos !== false) {
            $before = mb_substr($word, 0, $pos, 'UTF-8');
            $after  = mb_substr($word, $pos + mb_strlen($breathingMark, 'UTF-8'), null, 'UTF-8');
            $word = 'h' . $before . $after;
        }
        $word = str_replace("αυ", 'au', $word);
        $word = str_replace("ευ", 'eu', $word);
        $word = str_replace("ηυ", 'ēu', $word);
        $word = str_replace("ου", 'ou', $word);
        $word = str_replace("υι", 'ui', $word);
        $word = str_replace("Αυ", 'Au', $word);
        $word = str_replace("Ευ", 'Eu', $word);
        $word = str_replace("Ηυ", 'Ēu', $word);
        $word = str_replace("Ου", 'Ou', $word);
        $word = str_replace("Υι", 'Ui', $word);

        if ($diaeresisPos !== false) {
            $word = str_replace("\u{0308}", "", $word);
        }

        foreach (self::GREEK_TRANSLITERATIONS as $letter => $replacement) {
            $word = str_replace($letter, $replacement, $word);
        }

        return $word;
    }

    private function downloadFiles()
    {
        $storagePrefix = "greek/{$this->textSource}";
        if ($this->textSource == 'BMT') {
            // contains the Greek text with punctuation etc.
            $unparsedFileDir = 'https://raw.githubusercontent.com/briff/byzantine-majority-text/refs/heads/master/csv-unicode/ccat/no-variants/';
            // contains the Strong words with grammatical analysis
            $parsedFileDir = 'https://raw.githubusercontent.com/briff/byzantine-majority-text/refs/heads/master/csv-unicode/strongs/with-parsing/';
            foreach (self::ABBREVIATION_MAPPING as $abbrev => $usxCode) {
                if (!Storage::exists("{$storagePrefix}/parsed/{$usxCode}.csv")) {
                    $filePath = $parsedFileDir . $abbrev . '.csv';
                    $this->info("Download {$filePath}");
                    $fileContents = Http::get($filePath)->body();
                    Storage::put("{$storagePrefix}/parsed/{$usxCode}.csv", $fileContents);
                }
                if (!Storage::exists("{$storagePrefix}/unparsed/{$usxCode}.csv")) {
                    $filePath = $unparsedFileDir . $abbrev . '.csv';
                    $this->info("Download {$filePath}");
                    $fileContents = Http::get($filePath)->body();
                    Storage::put("{$storagePrefix}/unparsed/{$usxCode}.csv", $fileContents);
                }
            }
        } else if ($this->textSource == 'OpenGNT') {
            if (!Storage::exists("{$storagePrefix}/OpenGNT.zip")) {
                $zippedFile = "https://github.com/briff/OpenGNT/raw/refs/heads/master/OpenGNT_BASE_TEXT.zip";
                $this->info("Download {$zippedFile}");
                $fileContents = Http::get($zippedFile)->body();
                Storage::put("{$storagePrefix}/OpenGNT.zip", $fileContents);
            }
            $zip = new ZipArchive;
            $zip->open(Storage::path("{$storagePrefix}/OpenGNT.zip"));
            $zip->extractTo(Storage::path($storagePrefix), "OpenGNT_version3_3.csv");
            $zip->close();
            // go through each line in this csv file
            $file = Storage::readStream("{$storagePrefix}/OpenGNT_version3_3.csv");
            $header = fgetcsv($file, separator: "\t");
            $parsedVerses = [];
            while (($row = fgetcsv($file, separator: "\t")) !== false) {
                $data = array_combine($header, $row);
                $verseRef = explode("｜", trim($data["〔Book｜Chapter｜Verse〕"], "〔〕"));
                $wordRef = explode("｜", trim($data["〔OGNTk｜OGNTu｜OGNTa｜lexeme｜rmac｜sn〕"], "〔〕"));
                $postfix = preg_replace('/<\/?pm>/', "", explode("｜", trim($data["〔PMpWord｜PMfWord〕"], "〔〕"))[1]);
                $book = (int) $verseRef[0];
                $chapter = $verseRef[1];
                $verse = $verseRef[2];
                $word = $wordRef[2];
                $strongNumber = str_replace("G", "", $wordRef[5]);
                $parsing = "{" . $wordRef[4] . "}";
                $parsedVerses[$book][$chapter][$verse]["parsed"][] = "{$word} {$strongNumber} {$parsing}";
                $parsedVerses[$book][$chapter][$verse]["unparsed"][] = "{$word}{$postfix}";
            }
            $this->info("Loaded text, generating files");
            $parsedFileContent = [];
            $unparsedFileContent = [];
            foreach ($parsedVerses as $book => $parsedChapters) {
                $usxCode = array_values(self::ABBREVIATION_MAPPING)[$book - 40];
                foreach ($parsedChapters as $chapter => $verses) {
                    foreach ($verses as $verse => $parsedTexts) {
                        $unparsedText = implode(" ", $parsedTexts["unparsed"]);
                        $parsedText = implode(" ", $parsedTexts["parsed"]);
                        $parsedFileContent[$usxCode][] = ["chapter" => $chapter, "verse" => $verse, "text" => $parsedText];
                        $unparsedFileContent[$usxCode][] = ["chapter" => $chapter, "verse" => $verse, "text" => $unparsedText];
                    }
                }
            }
            foreach ($parsedFileContent as $usxCode => $parsedVerses) {
                Storage::makeDirectory("{$storagePrefix}/parsed");
                Storage::makeDirectory("{$storagePrefix}/unparsed");
                $file = fopen(Storage::path("{$storagePrefix}/parsed/{$usxCode}.csv"), 'w');
                fputcsv($file, ["chapter", "verse", "text"]);
                foreach ($parsedVerses as $parsedVerse) {
                    fputcsv($file, $parsedVerse);
                }
                fclose($file);
                $file = fopen(Storage::path("{$storagePrefix}/unparsed/{$usxCode}.csv"), 'w');
                fputcsv($file, ["chapter", "verse", "text"]);
                foreach ($unparsedFileContent[$usxCode] as $unparsedVerse) {
                    fputcsv($file, $unparsedVerse);
                }
                fclose($file);
            }
        } else {
            throw new \RuntimeException("Invalid text source: {$this->textSource}");
        }
        $dictionaryFile = 'https://github.com/briff/STEPBible-Data/raw/refs/heads/master/Lexicons/TBESG%20-%20Translators%20Brief%20lexicon%20of%20Extended%20Strongs%20for%20Greek%20-%20STEPBible.org%20CC%20BY.txt';
        if (!Storage::exists("greek/dictionary.txt")) {
            $this->info("Download {$dictionaryFile}");
            $fileContents = Http::get($dictionaryFile)->body();
            Storage::put("greek/dictionary.txt", $fileContents);
        }
    }

    private function createVectors()
    {
        $this->info("Creating vectors");
        $currentChapter = 1;
        foreach (self::ABBREVIATION_MAPPING as $abbrev => $usxCode) {
            $progressBar = $this->output->createProgressBar(50);
            $progressBar->setFormat("[%bar%] %message%");
            $progressBar->setMessage("{$usxCode} {$currentChapter}");
            $progressBar->start();
            do {
                $greekVerses = GreekVerse::where('usx_code', $usxCode)->where('chapter', $currentChapter)->get();
                if ($greekVerses->isEmpty()) {
                    $currentChapter = 1;
                    break;
                }
                $vectorFileName = "vectors_greek/" . $this->textSource . "_{$usxCode}_{$this->model}_{$this->dimensions}.{$currentChapter}";
                $currentDeserializedVectors = null;
                if ($this->option('source')) {
                    $storage = $this->selectInputStorage();
                    $file = $storage->get($vectorFileName);
                    if ($file == null) {
                        $this->error("{$vectorFileName} does not exist.");
                        continue;
                    } else {
                        $currentDeserializedVectors = self::unserialize($file);
                    }
                }
                if ($this->option('target')) {
                    if ($this->option('target') != 'db') {
                        $storage = $this->selectTargetStorage();
                        $file = $storage->get($vectorFileName);
                        if ($file != null) {
                            $currentDeserializedVectors = self::unserialize($file);
                        } else {
                            $currentDeserializedVectors = [];
                        }
                    } else {
                        if (!$this->option('source')) {
                            $this->error("Source is mandatory if target is db.");
                            return;
                        }
                    }
                }
                foreach ($greekVerses as $greekVerse) {
                    $progressBar->setMessage("{$greekVerse->gepi}");
                    $progressBar->display();
                    $existingVector = $currentDeserializedVectors[$greekVerse->gepi]['vector'] ?? null;
                    if (!$existingVector) {
                        // we generate now
                        $retries = 0;
                        $success = false;
                        while ($retries < 3 && !$success) {
                            try {
                                $response = $this->semanticSearchService->generateVector(str_replace('¶', '', $greekVerse->text), $this->model, $this->dimensions);
                                $success = true;
                            } catch (Throwable $e) {
                                $retries++;
                                $progressBar->clear();
                                $this->info($e->getMessage());
                                $this->info("OpenAI error occurred, might have reached the rate limit. Retrying {$retries}. Waiting 15 seconds.");
                                $progressBar->display();
                                sleep(15);
                            }
                        }
                        if (!empty($response)) {
                            $currentDeserializedVectors[$greekVerse->gepi] = [
                                "source" => $this->textSource,
                                "model" => $this->model,
                                "dimensions" => $this->dimensions,
                                "usx_code" => $greekVerse->usx_code,
                                "chapter" => $greekVerse->chapter,
                                "verse" => $greekVerse->verse,
                                "vector" => $response->vector
                            ];
                        }
                    }
                }
                // now we have all the vectors for the current chapter, let's load to the db if the target is db. Otherwise save to the file.
                if ($this->option('target') == 'db') {
                    GreekVerseEmbedding::where('usx_code', $usxCode)->where('chapter', $currentChapter)->delete();
                    foreach ($currentDeserializedVectors as $gepi => $data) {
                        $greekVerseEmbedding = new GreekVerseEmbedding();
                        $greekVerseEmbedding->gepi = $gepi;
                        $greekVerseEmbedding->source = $data['source'];
                        $greekVerseEmbedding->model = $data['model'];
                        $greekVerseEmbedding->usx_code = $data['usx_code'];
                        $greekVerseEmbedding->chapter = $data['chapter'];
                        $greekVerseEmbedding->verse = $data['verse'];
                        $greekVerseEmbedding->embedding = new Vector($data['vector']);
                        $greekVerseEmbedding->save();
                    }
                } else {
                    $storage = $this->selectTargetStorage();
                    $storage->put($vectorFileName, self::serialize($currentDeserializedVectors));
                }

                $currentChapter++;
                $progressBar->advance();
            } while (true);
            $progressBar->finish();
            $this->output->newline();
        }
        $this->info("Vectors created");
    }

    private function selectInputStorage()
    {
        if ($this->option("source") == "s3") {
            return Storage::disk("s3");
        } else if ($this->option("source") == "filesystem") {
            return Storage::disk("local");
        } else {
            throw new \Exception("Invalid input storage.");
        }
    }

    private function selectTargetStorage()
    {
        if ($this->option("target") == "s3") {
            return Storage::disk("s3");
        } else if ($this->option("target") == "filesystem") {
            return Storage::disk("local");
        } else {
            throw new \Exception("Invalid input storage.");
        }
    }


    private static function unserialize($file)
    {
        return json_decode(gzdecode($file), true);
    }

    private static function serialize($object)
    {
        return gzencode(json_encode($object));
    }
}
