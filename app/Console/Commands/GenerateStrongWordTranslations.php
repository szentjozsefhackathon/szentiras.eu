<?php

namespace SzentirasHu\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\Etymology;
use SzentirasHu\Models\StrongWord;
use Throwable;

class GenerateStrongWordTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'szentiras:generate-strong-word-translations
        {--source= : s3 or filesystem. If given, it will not try to generate the words, but instead loads to DB }
        {--word= : an optional array of Strong word ids. If given, generate for these words only. }
        {--batch : if set, send the required words in a batch request }
        {--batch-result= : Set the parameter to get the batch results }
        {--model=claude-3-5-haiku-20241022 : The model to use}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Strong word translations. If source is not set, target is the local storage. Only generate if the file doesn\'t exist yet.';

    private $systemPrompt = "You create Koine Greek New Testament dictionary for catholic lay Hungarian people. Format your answer in JSON. Json structure: { word: \"the greek dictionary form (for nouns: the lemma and the genitive, and gender (gender denoted in parenthesis, expressed with a Hungarian word (hímnem for masculine, nőnem for feminine and semlegesnem for neuter); for verbs: nothing)\", \"meanings\": [ { \"meaning\": \"Hungarian meaning\", \"explanation\": \"Explanation in Hungarian.\" }, { \"meaning\": ..., \"explanation\": ... }, ... ], \"etymology\": \"One sentence etymology in Hungarian.\", \"notes\" : \"Include any notes in Hungarian if there are important and well established aspects regarding the word's usage in the new testament. You can leave it empty.\" } Meaning should be one word.    ";

    private $examplePrompt = "<examples>\n<example>\n<word>\nἸησοῦς\n</word>\n<ideal_output>\n{\n  \"word\": \"Ἰησοῦς, -οῦ (hímnem)\",\n  \"meanings\": [\n    {\n      \"meaning\": \"Jézus\",\n      \"explanation\": \"A názáreti Jézus Krisztus.\"\n    },\n    {\n      \"meaning\": \"Józsué\",\n      \"explanation\": \"Az Ószövetségben szereplő vezető, aki Mózes után az izraelitákat az Ígéret földjére vezette.\"\n    }\n  ],\n  \"etymology\": \"A héber יְהוֹשֻׁעַ (Jehosua, 'Jahve a szabadítás') névből származik, amelynek rövidebb formája יֵשׁוּעַ (Jesua), amelyet görögösítettek.\",\n  \"notes\": \"Az Újszövetségben elsősorban Jézus Krisztusra utal, bár az Apostolok Cselekedeteiben (7:45) és a Zsidókhoz írt levélben (4:8) Józsuét is jelölheti. A név jelentése ('Jahve megment' vagy 'Jahve a szabadítás') összefügg Jézus küldetésével, ahogy Máté 1:21-ben is olvasható.\"\n}\n</ideal_output>\n</example>\n<example>\n<word>\nγεννάω\n</word>\n<ideal_output>\n{\n  \"word\": \"γεννάω\",\n  \"meanings\": [\n    {\n      \"meaning\": \"nemz\",\n      \"explanation\": \"Férfi általi nemzés, utód létrehozása biológiai értelemben.\"\n    },\n    {\n      \"meaning\": \"szül\",\n      \"explanation\": \"Női általi szülés, gyermek világra hozása.\"\n    },\n    {\n      \"meaning\": \"létrehoz\",\n      \"explanation\": \"Valaminek vagy valakinek a létrehozása átvitt értelemben.\"\n    },\n    {\n      \"meaning\": \"keletkezik\",\n      \"explanation\": \"Valaminek a létrejötte, keletkezése.\"\n    }\n  ],\n  \"etymology\": \"A γένος (nemzetség, család) szóból származik, rokon a γίνομαι (létrejönni, születni) igével.\",\n  \"notes\": \"Az Újszövetségben gyakran használják Jézus származásának leírásánál (Máté evangéliumának nemzetségtáblájában), illetve a lelki újjászületés metaforájaként János evangéliumában és leveleiben.\"\n}\n</ideal_output>\n</example>\n</examples>\n\n";

    private $folder;
    private $apiKey;
    private $model;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->model = $this->option("model");
        $this->folder = 'translation';
        $this->apiKey = Config::get('services.anthropic.api_key');
        if ($this->option('batch-result')) {
            $batchId = $this->option('batch-result');
            $apiUrl = "https://api.anthropic.com/v1/messages/batches/{$batchId}";
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->get($apiUrl);
            $resultsUrl = $response->json()['results_url'];
            if (empty($resultsUrl)) {
                $this->info("No results yet. :(");
                return;
            }
            $responseFromResult = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->get($resultsUrl);
            $jsonl = $responseFromResult->body();
            Storage::put("{$this->folder}/{$batchId}.jsonl", $jsonl);
            $lines = explode("\n", $jsonl);
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }
                $json = json_decode($line, true);
                $wordNumber = $json['custom_id'];
                $path = "{$this->folder}/{$wordNumber}_{$this->model}.json";
                $responseString = $json['result']['message']['content'][0]['text'];
                $this->decodeAndSaveResponseString($wordNumber, $responseString, $path);
            }

            $this->info("Response saved to {$this->folder}/{$batchId}.jsonl");
            return;
        }

        if ($this->option("word")) {
            $wordNumbers = array_map("trim", explode(",", $this->option("word")));
        } else {
            // get only those words that have usage

            $wordNumbers = StrongWord::has('greekVerses')->pluck("number");
        }

        $progressBar = $this->output->createProgressBar(count($wordNumbers));
        $sourceStorage = null;
        if ($this->option('source') == 'filesystem') {
            $sourceStorage = Storage::disk('local');
        } else if ($this->option('source') == 's3') {
            $sourceStorage = Storage::disk('s3');
        }

        foreach ($wordNumbers as $wordNumber) {
            $progressBar->advance();
            $path = "{$this->folder}/{$wordNumber}_{$this->model}.json";
            if ($sourceStorage) {
                // load the translation from the source file
                $file = $sourceStorage->get($path);
                if (!$file) {
                    $this->error("{$path} doesn't exist");
                    continue;
                }
                $object = json_decode($file);
                if ($object == null) {
                    $progressBar->clear();
                    $this->error("Error decoding $path");
                    $progressBar->display();
                    continue;
                }
                // delete all meanings and etimology for the given word and model
                DictionaryEntry::where('strong_word_number', $wordNumber)->where('source', $this->model)->delete();
                DictionaryMeaning::where('strong_word_number', $wordNumber)->where('source', $this->model)->delete();
                $dictionaryEntry = new DictionaryEntry();
                $dictionaryEntry->strong_word_number = $wordNumber;
                $dictionaryEntry->source = $this->model;
                $dictionaryEntry->paradigm = $object->word;
                $dictionaryEntry->etymology = $object->etymology;
                $dictionaryEntry->notes = $object->notes ?? null;
                $dictionaryEntry->save();
                foreach ($object->meanings as $i => $meaning) {
                    $dictionaryMeaning = new DictionaryMeaning();
                    $dictionaryMeaning->strong_word_number = $wordNumber;
                    $dictionaryMeaning->source = $this->model;
                    $dictionaryMeaning->meaning = $meaning->meaning;
                    $dictionaryMeaning->explanation = $meaning->explanation;
                    $dictionaryMeaning->order = $i;
                    $dictionaryMeaning->save();
                }
            } else {
                // we are generating now, not loading                
                if (Storage::exists($path)) {
                    $this->info("{$wordNumber}: translation already exists. Skipping.");
                    continue;
                }
                if (!$this->option("batch")) {
                    $this->sendDirectRequest($wordNumber, $path);
                } else {
                    $batchRequests[] = [
                        "custom_id" => "$wordNumber",
                        "params" => [
                            "model" => $this->model,
                            "max_tokens" => 1024,
                            "system" => [["type" => "text", "text" => $this->systemPrompt, "cache_control" => ["type" => "ephemeral"]]],
                            "messages" => [
                                [
                                    "role" => "user",
                                    "content" => [
                                        ["type" => "text", "text" => $this->examplePrompt, "cache_control" => ["type" => "ephemeral"]],
                                        ["type" => "text", "text" => "The Greek word is: " . StrongWord::where('number', $wordNumber)->first()->lemma]
                                    ]
                                ]
                            ]
                        ]
                    ];
                }
            }
        }
        if (isset($batchRequests)) {
            $this->sendBatchRequests($batchRequests);
        }
        $progressBar->finish();
        $this->output->newline();
    }

    private function sendDirectRequest($wordNumber, $path)
    {
        $this->info("{$wordNumber}: Generate translation with AI.");
        $apiUrl = "https://api.anthropic.com/v1/messages";
        $data = [
            "model" => $this->model,
            "max_tokens" => 1024,
            "system" => $this->systemPrompt,
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "text", "text" => $this->examplePrompt, "cache_control" => ["type" => "ephemeral"]],
                        ["type" => "text", "text" => "The Greek word is: " . StrongWord::where('number', $wordNumber)->first()->lemma]
                    ]
                ]
            ]
        ];
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ])->post($apiUrl, $data);

        if ($response->successful()) {
            $responseData = $response->json();
            $responseString = $responseData['content'][0]['text'];
            $this->decodeAndSaveResponseString($wordNumber, $responseString, $path);
        } else {
            $this->error("Error: " . $response->status() . " - " . $response->body());
        }
    }

    private function sendBatchRequests($batchRequests)
    {
        $batchApiData = ["requests" => $batchRequests];
        $batchEndpoint = 'https://api.anthropic.com/v1/messages/batches';
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ])->post($batchEndpoint, $batchApiData);
        $responseData = $response->json();
        $responseId = $responseData['id'];
        Storage::put("{$this->folder}/{$responseId}", json_encode($responseData));
        $this->output->newLine();
        $this->info("Get results with: php artisan szentiras:generate-strong-word-translations --batch-result={$responseId}");
    }

    private function decodeAndSaveResponseString($wordNumber, $responseString, $path)
    {
        $responseString = str_replace('```json', '', $responseString);
        $responseString = str_replace('```', '', $responseString);
        $translation  = json_decode($responseString, true);
        if ($translation == NULL) {
            $this->error("Bad response from AI: " . $responseString);
        }
        Storage::put("{$path}", $responseString);
        $this->info("{$wordNumber}: translation saved.");
    }
}
