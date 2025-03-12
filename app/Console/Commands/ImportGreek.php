<?php

namespace SzentirasHu\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Models\GreekVerse;
use SzentirasHu\Models\StrongWord;

class ImportGreek extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'szentiras:import-greek
        {--skip-words : Skip the import of the strong words}
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

    private $strongWords = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GreekVerse::truncate();
        $this->downloadFiles();
        if (!$this->option('skip-words')) {
            StrongWord::truncate();
            $this->fillStrongWordsTable();
        }
        foreach (StrongWord::all() as $strongWord) {
            $this->strongWords[$strongWord->number] = $strongWord;
        }
        $this->fillGreekVerseTable();
    }

    private function fillGreekVerseTable() {
        setlocale(LC_ALL, "el_GR.UTF-8");
        $parsedVerses = [];
        $unparsedVerses = [];
        foreach (self::ABBREVIATION_MAPPING as $abbrev => $usxCode) {
            $file = Storage::readStream("greek/parsed/{$usxCode}.csv");
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
                $chapter = $data['chapter'];
                $verse = $data['verse'];
                $parsedVerses[$usxCode][$chapter][$verse] = $data['text'];
            }
            $file = Storage::readStream("greek/unparsed/{$usxCode}.csv");
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($header, $row);
                $chapter = $data['chapter'];
                $verse = $data['verse'];
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
                        $strongWordsToAttach[$this->strongWords[$strongNumber]->id] = [ 'position' => $position ];
                    }
                    $greekVerse->strongWords()->attach($strongWordsToAttach);
                }
            }
        }
        $progressBar->finish();
        $this->output->newline();
                

    }

    private function fillStrongWordsTable() {
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

    private function downloadFiles() {
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
}
