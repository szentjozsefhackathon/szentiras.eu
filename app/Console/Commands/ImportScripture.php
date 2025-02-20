<?php

namespace SzentirasHu\Console\Commands;

use App;
use Artisan;
use Cache;
use Config;
use DB;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Common\Entity\Row;
use SzentirasHu\Data\Entity\Translation;
use OpenSpout\Reader\XLSX\RowIterator;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportScripture extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'szentiras:importScripture';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update texts from external source (xls)';

    private $translationRepository;
    private $bookRepository;
    private $hunspellEnabled = false;
    private $newStems = 0;
    private $sourceDirectory;
    private $importableTranslations = ['BD', 'KG', 'KNB', 'RUF', 'UF', 'SZIT', 'STL'];
    // Mapping: Database columns to Books Sheet header column numbers
    private $dbToHeaderColNum = [
        'SZIT' => ['gepi' => 0, 'rov' => 5],
        'KNB' => ['gepi' => 0, 'rov' => 3],
        'UF' => ['gepi' => 0, 'rov' => 4],
        'KG' => ['gepi' => 0, 'rov' => 4],
        'BD' => ['gepi' => 0, 'rov' => 1],
        'RUF' => ['gepi' => 0, 'rov' => 5],
        'STL' => ['gepi' => 0, 'rov' => 2],
    ];
    // Mapping: Database columns to Verses Sheet headers
    private $defaultDbToHeaderMap = ['did' => 'Ssz', 'gepi' => 'hiv', 'tip' => 'jelstatusz', 'verse' => 'jel'];
    private $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    private array $processedStems = [];
    const STEM_FILE = "database/preload/stems.dat";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(TranslationRepository $translationRepository, BookRepository $bookRepository)
    {
        parent::__construct();
        $this->translationRepository = $translationRepository;
        $this->bookRepository = $bookRepository;
        $this->sourceDirectory = Config::get('settings.sourceDirectory');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        Artisan::call("cache:clear");
        if (!$this->option('nohunspell')) {
            $this->testHunspell();
        }
        $transAbbrevToImport = $this->choice('Melyik fordítást töltsük be?', $this->importableTranslations);
        $this->verifyTranslationAbbrev($transAbbrevToImport);

        $translation = $this->translationRepository->getByAbbrev($transAbbrevToImport);
        $this->info("Fordítás: {$translation->name} (id: {$translation->id})");

        ini_set('memory_limit', '1024M');
        if ($this->option('file')) {
            $filePath = $this->option('file');
            $this->info("A fájl betöltése innen: " . $this->option('file'));
            $filePath = $this->ensureProperFile($filePath);
        } else {
            $url = Config::get("translations.{$transAbbrevToImport}.textSource");
            if (empty($url)) {
                App::abort(500, "Nincs megadva a TEXT_SOURCE_{$transAbbrevToImport} konfiguráció.");
            }
            $filePath = $this->downloadTranslation($transAbbrevToImport, $url);
        }

        $this->verifyTranslationBookColumns($transAbbrevToImport);

        $inserts = $this->readInserts($filePath, $translation, $transAbbrevToImport);
        if (count($inserts) > 0) {
            $this->saveToDb($transAbbrevToImport, $translation, $inserts);
        } else {
            $this->info("Nincs mit feltölteni.");
        }
        $this->runIndexer();
        return 0;
    }

    protected function getOptions(): array
    {
        return [
            ['file', null, InputOption::VALUE_OPTIONAL, 'Ha fájlból szeretnéd betölteni, nem dropboxból, az importálandó fájl elérési útja', null],
            ['nohunspell', null, InputOption::VALUE_NONE, 'Szótöveket ne állítsa elő'],
            ['filter', null, InputOption::VALUE_OPTIONAL, 'Szűrés `gepi` (regex) szerint', null],
        ];
    }

    private function readInserts(string $filePath, Translation $translation, string $transAbbrevToImport): array
    {
        $this->info("A $filePath fájl betöltése...");
        $reader = ReaderFactory::createFromFileByMimeType($filePath);
        $reader->open($filePath);
        $this->info("A $filePath fájl megnyitva...");
        $sheets = $this->getSheets($reader);

        $bookNumberToId = $this->getBookNumberToIdMapping($sheets, $translation, $transAbbrevToImport);

        $this->info("A '$transAbbrevToImport' lap betöltése..");
        $versesSheet = $sheets[$transAbbrevToImport];
        $verseRowIterator = $versesSheet->getRowIterator();
        $verseSheetHeaders = $this->getHeaders($verseRowIterator);

        $dbToHeaderMap = $this->mapVerseSheetHeadersToDbColumns($verseSheetHeaders);
    
        $pipes = [];
        if ($this->hunspellEnabled) {
            $hunspellProcess = proc_open(
                'stdbuf -oL hunspell -m -d hu_HU -i UTF-8',
                $this->descriptorspec,
                $pipes,
                null,
                null
            );                    
        }

        // if the stems file exists, unserialize it
        if (file_exists(ImportScripture::STEM_FILE)) {
            $this->info("A szótövek fájl betöltése...");
            $this->processedStems = unserialize(gzuncompress(file_get_contents(ImportScripture::STEM_FILE)));
            // fill the cache with the processed stems
            foreach ($this->processedStems as $word => $stems) {
                Cache::store("array")->put("hunspell_{$word}", $stems, 60 * 60 * 24);
            }
        }
        
        $inserts = $this->readLines(
            $verseRowIterator,
            $verseSheetHeaders,
            $dbToHeaderMap,
            $translation,
            $bookNumberToId,
            $pipes
        );
      
        $reader->close();

        if ($this->hunspellEnabled) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (isset($hunspellProcess)) {
                proc_close($hunspellProcess);
            }
        }

        return $inserts;
    }

    private function downloadTranslation(string $transAbbrev, string $url): string
    {
        try {
            $filePath = $this->sourceDirectory . "/{$transAbbrev}";
            $this->info("A fájl letöltése a $url címről...: $filePath");
            $fp = fopen($filePath, 'w+');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        } catch (Exception $ex) {
            App::abort(500, "Nem sikerült fáljt letölteni a megadott url-ről.");
        }
        return $filePath;
    }

    private function runIndexer(): void
    {
        $sphinxConfig = Config::get('settings.sphinxConfig');
        $indexerProcess = new Process(["indexer", "--config", "{$sphinxConfig}", "--all", "--rotate"]);
        try {
            $indexerProcess->mustRun();
            echo $indexerProcess->getOutput();
        } catch (ProcessFailedException $e) {
            echo $e->getMessage();
        }
    }

    private function testHunspell()
    {
        $hunspellInstalledReturnVal = shell_exec("which hunspell");
        if (empty($hunspellInstalledReturnVal)) {
            App::abort(500, 'Hunspell-hu is not installed. Please install it or use \'--nohunspell\' instead.');
        }
        $hunspellHasDictionaryReturnVal = shell_exec("echo medve | hunspell -d hu_HU -i UTF-8 -m  2>&1");
        if (preg_match('/Can\'t open affix or dictionary files for dictionary/i', $hunspellHasDictionaryReturnVal)) {
            App::abort(500, 'Can\'t open the hu_HU dictionary. Try to install hunspell-hu or use \'--nohunspell\' instead.');
        }
        $this->hunspellEnabled = true;
    }

    private function getSheets(Reader $reader): array
    {
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = $sheet;
        }
        if (empty($sheets)) {
            App::abort(500, 'Sikertelen betöltés.');
        }
        return $sheets;
    }

    private function readLines(RowIterator $verseRowIterator, array $verseSheetHeaders, array $dbToHeaderMap, Translation $translation, array $booksGepiToId, $pipes): array
    {
        $this->info("Beolvasás sorról sorra...\n");
        $progressBar = $this->output->createProgressBar();
        $progressBar->setRedrawFrequency(25);
        $progressBar->setBarWidth(24);
        $progressBar->setFormat("[%bar%] %message%");
        $rowNumber = 0;
        foreach ($verseRowIterator as $verseRow) {
            $rowNumber++;
            if ($this->isVerseHeaderRow($verseRow)) {
                $this->info("Sor átugrása: $rowNumber. (fejlécnek tűnik)");
                continue;
            }
            if (empty($verseRow->getCellAtIndex($verseSheetHeaders[$dbToHeaderMap['gepi']])->getValue())) {
                break;
            }
            $gepi = $verseRow->getCellAtIndex($verseSheetHeaders[$dbToHeaderMap['gepi']])->getValue();
            $search = ['1010020040a', '1010020040b'];
            $replace = ['10100200401', '10100200402'];
            $gepi = str_replace($search, $replace, $gepi);

            if (!$this->option('filter') or preg_match('/' . $this->option('filter') . '/i', $gepi)) {
                $newInsert = $this->toDbRow(
                    $verseRow,
                    $verseSheetHeaders,
                    $translation,
                    $gepi,
                    $booksGepiToId,
                    $pipes
                );
                $inserts[$rowNumber] = $newInsert;
            
            
                $rowNumber++;
                $progressBar->setMessage("$rowNumber - {$newInsert['gepi']} - új szavak: {$this->newStems}");
                $progressBar->advance();
            }            
        }
        $this->info("A szótövek fájl mentése...");
        ksort($this->processedStems);
        $serializedStems = gzcompress(serialize($this->processedStems));
        file_put_contents(ImportScripture::STEM_FILE, $serializedStems);
        $progressBar->finish();

        return $inserts;
    }

    private function toDbRow(Row $row, array $verseSheetHeaders, Translation $translation, string $gepi, array $booksGepiToId, $pipes): array
    {

        $result['trans'] = $translation->id;
        $result['gepi'] = $gepi;
        $result['book_number'] = (int) substr($gepi, 0, 3);
        $result['chapter'] = (int) substr($gepi, 3, 3);
        $result['numv'] = (int) substr($gepi, 6, 3);
        $result['tip'] = $row->getCellAtIndex($verseSheetHeaders['jelstatusz'])->getValue();
        $result['verse'] = $row->getCellAtIndex($verseSheetHeaders['jel'])->getValue();
        $result['verseroot'] = null;

        if ($this->hunspellEnabled && in_array($result['tip'], [60, 6, 901, 5, 10, 20, 30, 1, 2, 3, 401, 501, 601, 701, 703, 704])) {
            $result['verseroot'] = $this->executeStemming($result['verse'], $pipes);
        }

        if (isset($verseSheetHeaders['ido'])) {
            $idoValue = $row->getCellAtIndex($verseSheetHeaders['ido'])->getValue();
            $result['ido'] = $idoValue;
        }

        if (!isset($booksGepiToId[$result['book_number']])) {
            App::abort(500, 'Nincs meg a book number a gepi->id listában');
        }
        $result['book_id'] = $booksGepiToId[$result['book_number']];

        return $result;
    }

    private function saveToDb(string $abbrev, Translation $translation, array $inserts): void
    {
        $this->info("\nFeldolgozott adatok mentése az adatbázisba...");
        $progressBar = $this->output->createProgressBar(count($inserts));

        Artisan::call('down');
        $this->info("A tdveres tábla (részleges) ürítése...");
        if (!$this->option('filter')) {
            DB::table('tdverse')->where('trans', '=', $translation->id)->delete();
        } else {
            DB::table('tdverse')->where('trans', '=', $translation->id)->where('gepi', 'REGEXP', $this->option('filter'))->delete();
        }
        $this->info("A tdverse tábla feltöltése " . count($inserts) . " sorral...");
        echo "\n";
        for ($rowNumber = 0; $rowNumber < count($inserts); $rowNumber += 100) {
            $slice = array_slice($inserts, $rowNumber, 100);
            DB::table('tdverse')->insert($slice);
            $progressBar->advance(100);
        }
        $progressBar->finish();
        Artisan::call('up');
    }

    private function getBookNumberToIdMapping(array $sheets, Translation $translation, string $translationAbbrev): array
    {
        $this->info("Könyvek lap ellenőrzése");
        $bookRowIterator = $sheets['Könyvek']->getRowIterator();
        $linesRead = 0;
        $badAbbrevs = [];
        foreach ($bookRowIterator as $bookRow) {
            $valueInFirstCell = $bookRow->getCellAtIndex(0)?->getValue();
            $linesRead++;
            if (empty($valueInFirstCell)) {
                $this->info("$linesRead sor beolvasva, kész.");
                break;
            }
            if (!is_numeric($valueInFirstCell)) {
                $this->info("Sor átugrása: $linesRead. (nem numerikus tartalom)");
                continue;
            }
            $dbBookAbbrevToId = $this->getAbbrevToIdFromDb($translation);
            $bookNumber = $bookRow->getCellAtIndex($this->dbToHeaderColNum[$translationAbbrev]['gepi'])->getValue();
            $bookAbbrev = $bookRow->getCellAtIndex($this->dbToHeaderColNum[$translationAbbrev]['rov'])->getValue();
            $this->info("Könyv: $bookNumber, $bookAbbrev");
            if ($this->isImportSourceBookAbbrevMissingFromDb($dbBookAbbrevToId, $bookAbbrev)) {
                $book = $this->bookRepository->getByAbbrev($bookAbbrev, $translation->id); // look up the correct abbreviation
                if ($book) {
                    $this->info("Könyv ID az adatbázisban: {$book->id}");
                    $this->info(1);
                    $bookNumberToId[$bookNumber] = $book->id;
                } else {
                    $this->info(2);
                    $badAbbrevs[] = $bookAbbrev;
                }
            } else if ($bookAbbrev != '-' && $bookAbbrev != '') {
                $this->info(3);
                $bookNumberToId[$bookNumber] = $dbBookAbbrevToId[$bookAbbrev];
            }
        }
        $this->checkBadAbbrevs(badAbbrevs: $badAbbrevs);
        return $bookNumberToId;
    }

    private function mapVerseSheetHeadersToDbColumns(array $headers): array
    {
        $this->info("Oszlopok ellenőrzése...");
        $errors = [];
        $dbToHeaderMap = $this->defaultDbToHeaderMap;
        foreach ($dbToHeaderMap as $expectedHeaderCol) {
            if (!isset($headers[$expectedHeaderCol])) {
                $errors[] = $expectedHeaderCol;
            }
        }
        if (!empty($errors)) {
            foreach ($headers as $headerCol => $val) {
                if (preg_match('/[A-Z]{3}_hiv/', $headerCol)) {
                    $dbToHeaderMap['gepi'] = $headerCol;
                }
                if (preg_match('/[A-Z]{3}_old/', $headerCol)) {
                    $dbToHeaderMap['old'] = $headerCol;
                }
                if (preg_match('/ssz$/i', $headerCol)) {
                    $dbToHeaderMap['did'] = $headerCol;
                }
            }
            if (!isset($headers['ido'])) {
                unset($dbToHeaderMap['ido']);
            }
        }
        $errors = [];
        foreach ($dbToHeaderMap as $expectedHeaderCol) {
            if (!isset($headers[$expectedHeaderCol])) {
                $errors[] = $expectedHeaderCol;
            }
        }
        if (!empty($errors)) {
            $this->error('A következő oszlopok hiányoznak az excel táblából: ' . implode(', ', $errors));
            $this->comment("Létező oszlopok: " . implode(', ', array_keys($headers)));
            App::abort(500, "Probléma az oszlopoknál!");
        }
        return $dbToHeaderMap;
    }

    private function executeStemming(string $verse, array $pipes): string
    {
        // actually replace the tags with a space
        $processedVerse = str_replace( '<', ' <', $verse ); 
        $processedVerse = strip_tags($verse); 
        $processedVerse = preg_replace("/(,|:|\?|!|;|\.|„|”|»|«|\")/i", ' ', $processedVerse);
        $processedVerse = preg_replace(['/Í/i', '/Ú/i', '/Ő/i', '/Ó/i', '/Ü/i'], ['í', 'ú', 'ő', 'ó', 'ü'], $processedVerse);

        $verseroots = collect();
        preg_match_all('/(\p{L}+)/u', $processedVerse, $words);
        // take the first match as lower case
        foreach ($words[1] as $word) {
            $word = mb_strtolower($word);
            if (Cache::store("array")->has("hunspell_{$word}")) {
                $cachedStems = Cache::store("array")->get("hunspell_{$word}");
                $verseroots = $verseroots->merge($cachedStems);
            } else {
                fwrite($pipes[0], "{$word}\n"); // send start
                $stems = collect();
                while ($line = fgets($pipes[1])) {
                    if (trim($line) !== '') {
                        if (preg_match_all("/st:(\p{L}+)/u", $line, $matches)) {
                            $stems = $stems->merge($matches[1]);
                        } else {
                            $stems->push($word);
                        }
                    } else {
                        $cachedStems = $stems->unique()->toArray();
                        Cache::store("array")->put("hunspell_{$word}", $cachedStems, 60 * 60 * 24);
                        $this->processedStems[$word] = $cachedStems;
                        $verseroots = $verseroots->merge($stems)->unique();
                        $this->newStems++;
                        break;
                    }
                }
            }
        }
        // serialize the $this->processedStems array to the filesystem, the name is the abbreviation .dat
        return join(' ', $verseroots->toArray());
    }

    private function getAbbrevToIdFromDb(Translation $translation): array
    {
        $books = $this->bookRepository->getBooksByTranslation($translation->id);
        $booksAbbrevToId = [];
        foreach ($books as $book) {
            $booksAbbrevToId[$book->abbrev] = $book->id;
        }
        return $booksAbbrevToId;
    }

    private function checkBadAbbrevs(array $badAbbrevs): void
    {
        if (!empty($badAbbrevs)) {
            $this->info("A következő rövidítések csak a szövegforrásban találhatóak meg, az adatbázisban nem!\n" . implode(', ', $badAbbrevs));
            if (!$this->confirm('Folytassuk?')) {
                App::abort(500, "Kilépés");
            }
        }
    }

    private function verifyTranslationAbbrev(string $abbrev): void
    {
        if (!preg_match("/^(" . Config::get('settings.translationAbbrevRegex') . ")$/", $abbrev)) {
            App::abort(500, 'Hibás fordítás rövidítés!');
        }
    }

    private function verifyTranslationBookColumns(string $translationAbbrev): void
    {
        if (!isset($this->dbToHeaderColNum[$translationAbbrev])) {
            App::abort(
                500,
                'Ennél a szövegforrásnál (' . $translationAbbrev . ') ' .
                'nem tudjuk, hogy hol vannak a könyvek rövidítéseit feloldó oszlopok.'
            );
        }
    }

    private function getHeaders(RowIterator $verseRowIterator): array
    {
        $cols = [];
        $i = 0;
        $this->info("A fejlécek megszerzése...");
        foreach ($verseRowIterator as $row) { // only go through the first row
            foreach ($row->getCells() as $cell) {
                $cols[$cell->getValue()] = $i;
                $this->info("$i.oszlop: {$cell->getValue()}");
                $i++;
            }
            break;
        }
        return $cols;
    }

    private function isImportSourceBookAbbrevMissingFromDb(array $dbBookAbbrevs, string $bookAbbrev): bool
    {
        return !isset($dbBookAbbrevs[$bookAbbrev]) &&
            ($bookAbbrev != '-' && $bookAbbrev != '');
    }

    private function isVerseHeaderRow(Row $row): bool
    {
        $firstCellValue = $row->getCellAtIndex(0)?->getValue();
        $secondCellValue = $row->getCellAtIndex(1)?->getValue();

        return (!is_numeric($firstCellValue) || empty($firstCellValue))
            && !is_numeric($secondCellValue);
    }

    private function ensureProperFile(string $originalFilePath): string
    {
        if (!file_exists($originalFilePath)) {
            App::abort(500, "A fájl nem található: $originalFilePath");
        }

        $fileExtension = pathinfo($originalFilePath, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) == 'xls') {
            $spreadsheet = IOFactory::load($originalFilePath);
            $newXlsxFile = preg_replace('/\.xls$/i', '.xlsx', $originalFilePath);
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $this->info("Régi Excel formátum konvertálása, cél fájl: $newXlsxFile");
            $writer->save($newXlsxFile);
            return $newXlsxFile;
        }

        if (strtolower($fileExtension) == 'xlsx' || strtolower($fileExtension) == 'xlsm') {
            return $originalFilePath;
        }

        App::abort(500, "A fájl nem Excel Sheet: $originalFilePath ($fileExtension)");
    }
}