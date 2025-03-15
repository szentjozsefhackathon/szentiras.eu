<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\GreekVerseEmbedding;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Search\SemanticSearchService;
use Throwable;

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

    const SOURCE = 'BMT';
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
            $file = Storage::readStream("greek/parsed/{$usxCode}.csv");
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
                $chapter = (int)$data['chapter'];
                $verse = (int)$data['verse'];
                $parsedVerses[$usxCode][$chapter][$verse] = $data['text'];
            }
            $file = Storage::readStream("greek/unparsed/{$usxCode}.csv");
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
                        $strongElements[] = $this->strongWords[$strongNumber]->lemma;
                        $strongTranslits[] = $this->strongWords[$strongNumber]->transliteration;
                        $strongNormals[] = $this->strongWords[$strongNumber]->normalized;
                    }

                    $greekVerse = new GreekVerse();
                    $greekVerse->source = self::SOURCE;
                    $greekVerse->usx_code = $usxCode;
                    $greekVerse->gepi = "{$usxCode}_{$chapter}_{$verse}";
                    $greekVerse->chapter = $chapter;
                    $greekVerse->verse = $verse;
                    $greekVerse->text = $unparsedText;
                    $greekVerse->json = json_encode($jsonElements);
                    $greekVerse->strongs = implode(' ', $strongElements);
                    $greekVerse->strong_transliterations = implode(' ', $strongTranslits);
                    $greekVerse->strong_normalizations = implode(' ', $strongNormals);
                    $greekVerse->save();

                    $strongWordsToAttach = [];
                    foreach ($strongNumbers as $position => $strongNumber) {
                        $strongWordsToAttach[$this->strongWords[$strongNumber]->id] = ['position' => $position];
                    }
                    $greekVerse->strongWords()->attach($strongWordsToAttach);
                }
            }
        }

        $progressBar->finish();
        $this->output->newline();
    }

    private function fillStrongWordsTable()
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
            $strongWord->transliteration = (string) $entry->greek['translit'];
            $normalizedText = \Normalizer::normalize($strongWord->transliteration, \Normalizer::FORM_D);
            $cleanText = preg_replace('/\p{Mn}/u', '', $normalizedText);
            $normalized = strtolower($cleanText);
            $strongWord->normalized = $normalized;
            $strongWord->save();
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->output->newline();
    }

    private function downloadFiles()
    {
        $parsedFileDir = 'https://raw.githubusercontent.com/briff/byzantine-majority-text/refs/heads/master/csv-unicode/strongs/with-parsing/';
        $unparsedFileDir = 'https://raw.githubusercontent.com/briff/byzantine-majority-text/refs/heads/master/csv-unicode/ccat/no-variants/';
        $dictionaryFile = 'https://raw.githubusercontent.com/briff/strongs-dictionary-xml/refs/heads/master/strongsgreek.xml';
        foreach (self::ABBREVIATION_MAPPING as $abbrev => $usxCode) {
            if (!Storage::exists("greek/parsed/{$usxCode}.csv")) {
                $filePath = $parsedFileDir . $abbrev . '.csv';
                $this->info("Download {$filePath}");
                $fileContents = Http::get($filePath)->body();
                Storage::put("greek/parsed/{$usxCode}.csv", $fileContents);
            }
            if (!Storage::exists("greek/unparsed/{$usxCode}.csv")) {
                $filePath = $unparsedFileDir . $abbrev . '.csv';
                $this->info("Download {$filePath}");
                $fileContents = Http::get($filePath)->body();
                Storage::put("greek/unparsed/{$usxCode}.csv", $fileContents);
            }
            if (!Storage::exists("greek/dictionary.xml")) {
                $this->info("Download {$dictionaryFile}");
                $fileContents = Http::get($dictionaryFile)->body();
                Storage::put("greek/dictionary.xml", $fileContents);
            }
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
                $vectorFileName = "vectors_greek/".self::SOURCE."_{$usxCode}_{$this->model}_{$this->dimensions}.{$currentChapter}";
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
                                "source" => self::SOURCE,
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


    private static function unserialize($file) {
        return json_decode(gzdecode($file), true);
    }

    private static function serialize($object) {
        return gzencode(json_encode($object));
    }

}
