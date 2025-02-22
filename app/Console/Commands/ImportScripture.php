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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use SzentirasHu\Data\Repository\BookRepository;
use SzentirasHu\Data\Repository\TranslationRepository;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Common\Entity\Row;
use SzentirasHu\Data\Entity\Translation;
use OpenSpout\Reader\XLSX\RowIterator;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\UsxCodes;

define('BOOKCODE', 'gepi');
define('BOOKABBREV', 'rov');
define('BOOKNAME', 'nev');

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
    private $headerNameToColNum = [
        'SZIT' => [BOOKCODE => 0, BOOKABBREV => 5, BOOKNAME => 2],
        'KNB' => [BOOKCODE => 0, BOOKABBREV => 3, BOOKNAME => 1],
        'UF' => [BOOKCODE => 0, BOOKABBREV => 4, BOOKNAME => 1],
        'KG' => [BOOKCODE => 0, BOOKABBREV => 4, BOOKNAME => 1],
        'BD' => [BOOKCODE => 0, BOOKABBREV => 1, BOOKNAME => 2],
        'RUF' => [BOOKCODE => 0, BOOKABBREV => 5, BOOKNAME => 1],
        'STL' => [BOOKCODE => 0, BOOKABBREV => 2, BOOKNAME => 1],
    ];
    // Mapping: Database columns to Verses Sheet headers
    private $defaultDbToHeaderMap = ['did' => 'Ssz', BOOKCODE => 'hiv', 'tip' => 'jelstatusz', 'verse' => 'jel'];
    private $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

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

        [$bookInserts, $verseInserts] = $this->readInserts($translation, $transAbbrevToImport, $filePath);
        if (!empty($bookInserts) || !empty($verseInserts)) {
            // $this->saveToDb($transAbbrevToImport, $translation, $bookInserts, $verseInserts);
            $this->storeInDb($translation, $bookInserts, $verseInserts);
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

    private function storeInDb(Translation $translation, array $bookInserts, array $verseInserts): void
    {
        $this->info("Adatok mentése az adatbázisba...");
        Artisan::call('down');
        DB::transaction(function () use ($translation, $bookInserts, $verseInserts): void {
            $this->info("Régi könyvek törlése...");
            Book::where('translation_id', $translation->id)->delete();

            $this->info("Régi versek törlése...");
            Verse::whereHas('book', function ($query) use ($translation) {
                $query->where('translation_id', $translation->id);
            })->delete();

            $this->storeBooks($translation, $bookInserts);

            $this->storeVerses($translation, $verseInserts);
        });
        Artisan::call('up');
        $this->info("Adatok sikeresen elmentve.");
    }

    private function storeBooks(Translation $translation, array $bookInserts): void
    {
        foreach ($bookInserts as $bookInsert) {
            $book = new Book([
                'name' => $bookInsert['name'],
                'abbrev' => $bookInsert['abbrev'],
                'link' => $bookInsert['link'],
                'old_testament' => $bookInsert['old_testament'],
                'order' => $bookInsert['order'],
                'usx_code' => $bookInsert['usx_code'],
            ]);
            $book->translation()->associate($translation);
            $book->save();

            Cache::add(
                $this->getBookCacheKey(
                    $bookInsert['order'],
                    $translation->name
                ),
                $book,
                now()->addMinutes(10)
            );
        }
    }

    private function storeVerses(Translation $translation, array $verseInserts): void
    {
        foreach ($verseInserts as $verseInsert) {
            $book = Cache::remember(
                $this->getBookCacheKey($verseInsert['order'], $translation->name),
                now()->addMinutes(10),
                fn() => $this->fetchBook($verseInsert['order'], $translation)
            );

            if (!$book) {
                App::abort(500, "Hiányzó Book az adatbázisban: {$translation->name}/{$verseInsert['order']}.");
            }

            $syntheticCode = $book->usx_code . "_" . $verseInsert['chapter'] . '_' . $verseInsert['numv'];
            $verse = new Verse([
                'usx_code' => $book->usx_code,
                BOOKCODE => $syntheticCode,
                'verse' => $verseInsert['verse'],
                'order' => $verseInsert['order'],
                'chapter' => $verseInsert['chapter'],
                'numv' => $verseInsert['numv'],
                'tip' => $verseInsert['tip'],
                'verseroot' => $verseInsert['verseroot'],
                'ido' => $verseInsert['ido'] ?? null
            ]);
            $verse->translation()->associate($translation);
            $verse->book()->associate($book);
            $verse->save();
        }
    }

    private function fetchBook($order, Translation $translation): ?Book
    {
        return Book::where('order', $order)
            ->where('translation_id', $translation->id)
            ->first();
    }

    private function getBookCacheKey(int $order, string $translation): string
    {
        return "book_{$order}_{$translation}";
    }

    private function readInserts(Translation $translation, string $transAbbrevToImport, string $filePath): array
    {
        $this->info("A $filePath fájl betöltése...");
        $reader = new Reader();
        $reader->open($filePath);
        $this->info("A $filePath fájl megnyitva...");
        $sheets = $this->getSheets($reader);

        $this->info("Könyvek lap ellenőrzése");
        $bookInserts = $this->getBookInserts(
            $transAbbrevToImport,
            $sheets['Könyvek']
        );

        $this->info("A '$transAbbrevToImport' lap betöltése..");
        $verseInserts = $this->readVerseSheetInserts(
            $sheets[$transAbbrevToImport],
        );
        $reader->close();
        return [$bookInserts, $verseInserts];
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

    private function readVerseSheetInserts(Sheet $versesSheet): array
    {
        $verseRowIterator = $versesSheet->getRowIterator();
        $verseSheetHeaders = $this->getHeaders($verseRowIterator);
        $dbToHeaderMap = $this->mapVerseSheetHeadersToDbColumns($verseSheetHeaders);
        $this->info("Beolvasás sorról sorra...\n");
        $progressBar = $this->createProgressBar();
        $rowNumber = 0;
        foreach ($verseRowIterator as $verseRow) {
            $rowNumber++;
            if ($this->isVerseHeaderRow($verseRow)) {
                $this->info("Sor átugrása: $rowNumber. (fejlécnek tűnik)");
                continue;
            }
            if (empty($verseRow->getCellAtIndex($verseSheetHeaders[$dbToHeaderMap[BOOKCODE]])->getValue())) {
                break;
            }
            $originalBookCode = $verseRow->getCellAtIndex($verseSheetHeaders[$dbToHeaderMap[BOOKCODE]])->getValue();
            if (!$this->option('filter') or $this->checkFilterMatch($originalBookCode)) {
                $newInsert = $this->toVerseInsert(
                    $verseRow,
                    $verseSheetHeaders,
                    $originalBookCode
                );
                $inserts[$rowNumber] = $newInsert;
                $rowNumber++;
                $progressBar->setMessage(
                    "$rowNumber. sor" .
                    " - {$newInsert['original_book_code']}" .
                    " - új szavak: {$this->newStems}"
                );
                $progressBar->advance();
            }
        }
        $progressBar->finish();
        return $inserts;
    }

    private function toVerseInsert(
        Row $row,
        array $verseSheetHeaders,
        string $originalBookCode,
    ): array {
        $pipes = [];
        if ($this->hunspellEnabled) {
            $hunspellProcess = $this->startHunspell($pipes);
        }

        $result['original_book_code'] = $originalBookCode;
        $result['order'] = (int) substr($originalBookCode, 0, 3);
        $result['chapter'] = (int) substr($originalBookCode, 3, 3);
        $result['numv'] = (int) substr($originalBookCode, 6, 3);
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

        if ($this->hunspellEnabled) {
            $this->closeHunspell($hunspellProcess, $pipes);
        }

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
            DB::table('tdverse')->where('trans', '=', $translation->id)->where(BOOKCODE, 'REGEXP', $this->option('filter'))->delete();
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

    private function getBookInserts(
        string $translationAbbrev,
        Sheet $bookSheet
    ): array {
        $linesRead = 0;
        $bookInserts = [];

        foreach ($bookSheet->getRowIterator() as $bookRow) {
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

            $bookOrder = $bookRow->getCellAtIndex($this->headerNameToColNum[$translationAbbrev][BOOKCODE])->getValue();
            $bookAbbrev = $bookRow->getCellAtIndex($this->headerNameToColNum[$translationAbbrev][BOOKABBREV])->getValue();
            $bookName = $bookRow->getCellAtIndex($this->headerNameToColNum[$translationAbbrev][BOOKNAME])->getValue();
            $bookUsx = $this->bookAbbrevToUsxCode($bookAbbrev);
            $this->info("{$bookOrder}. könyv: {$bookAbbrev} (usx: {$bookUsx})");
            $bookInserts[] = [
                'order' => (int) $bookOrder,
                'abbrev' => $bookAbbrev,
                'usx_code' => $bookUsx,
                'translation' => $translationAbbrev,
                'name' => $bookName,
                'link' => $this->removeAccents($bookAbbrev),
                'old_testament' => $this->isOldTestament($bookUsx),
            ];
        }

        return $bookInserts;
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
                    $dbToHeaderMap[BOOKCODE] = $headerCol;
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
        $processedVerse = strip_tags($verse);
        $processedVerse = preg_replace("/(,|:|\?|!|;|\.|„|”|»|«|\")/i", ' ', $processedVerse);
        $processedVerse = preg_replace(['/Í/i', '/Ú/i', '/Ő/i', '/Ó/i', '/Ü/i'], ['í', 'ú', 'ő', 'ó', 'ü'], $processedVerse);

        $verseroots = collect();
        preg_match_all('/(\p{L}+)/u', $processedVerse, $words);
        foreach ($words[1] as $word) {
            if (Cache::has("hunspell_{$word}")) {
                $cachedStems = Cache::get("hunspell_{$word}");
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
                        $cachedStems = $stems->unique();
                        Cache::put("hunspell_{$word}", $cachedStems, 60 * 60 * 24);
                        $verseroots = $verseroots->merge($stems);
                        $this->newStems++;
                        break;
                    }
                }
            }
        }
        return join(' ', $verseroots->unique()->toArray());
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

    private function bookAbbrevToUsxCode(string $bookAbbrev): string
    {
        $mapping = UsxCodes::abbreviationToUsxMapping();

        if (!isset($mapping[$bookAbbrev])) {
            App::abort(500, "Nincs USX kód ehhez a könyvhöz: {$bookAbbrev}");
        }

        return $mapping[$bookAbbrev];
    }

    public static function isOldTestament(string $usxCode): int
    {
        return in_array($usxCode, UsxCodes::oldTestamentUsx()) ? 1 : 0;
    }

    private function verifyTranslationAbbrev(string $abbrev): void
    {
        if (!preg_match("/^(" . Config::get('settings.translationAbbrevRegex') . ")$/", $abbrev)) {
            App::abort(500, 'Hibás fordítás rövidítés!');
        }
    }

    private function verifyTranslationBookColumns(string $translationAbbrev): void
    {
        if (!isset($this->headerNameToColNum[$translationAbbrev])) {
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
        return !isset($dbBookAbbrevs[$bookAbbrev]) && ($bookAbbrev != '-' && $bookAbbrev != '');
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

    private function createProgressBar(): ProgressBar
    {
        $progressBar = $this->output->createProgressBar();
        $progressBar->setRedrawFrequency(25);
        $progressBar->setBarWidth(24);
        $progressBar->setFormat("[%bar%] %message%");
        return $progressBar;
    }

    private function startHunspell(array &$pipes)
    {
        return proc_open(
            'stdbuf -oL hunspell -m -d hu_HU -i UTF-8',
            $this->descriptorspec,
            $pipes,
            null,
            null
        );
    }

    private function closeHunspell($hunspellProcess, array $pipes): void
    {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (isset($hunspellProcess)) {
            proc_close($hunspellProcess);
        }
    }

    private function removeAccents($string): string
    {
        $accents = ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű', 'Á', 'É', 'Í', 'Ó', 'Ö', 'Ő', 'Ú', 'Ü', 'Ű'];
        $replacements = ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'A', 'E', 'I', 'O', 'O', 'O', 'U', 'U', 'U'];
        return str_replace($accents, $replacements, $string);
    }

    private function checkFilterMatch(string $originalBookCode): bool
    {
        return (bool) preg_match(
            '/' . $this->option('filter') . '/i',
            $originalBookCode
        );
    }
}
