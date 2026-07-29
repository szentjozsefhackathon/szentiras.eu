<?php

namespace SzentirasHu\Test;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use SzentirasHu\Models\DictionaryEntry;
use SzentirasHu\Models\DictionaryMeaning;
use SzentirasHu\Models\StrongWord;
use SzentirasHu\Service\Ai\AiPromptService;
use SzentirasHu\Test\Common\TestCase;

class GenerateStrongWordTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function afterRefreshingDatabase(): void
    {
        $this->resetPostgresSequences();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createStrongWord(int $number, string $lemma): StrongWord
    {
        $word = new StrongWord;
        $word->number = $number;
        $word->lemma = $lemma;
        $word->transliteration = 'translit';
        $word->normalized = 'normalized';
        $word->save();

        return $word;
    }

    private function fakeTranslationJson(string $word): string
    {
        return json_encode([
            'word' => $word,
            'meanings' => [
                ['meaning' => 'ige', 'explanation' => 'magyarázat'],
            ],
            'etymology' => 'eredet',
            'notes' => '',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function test_generates_with_openai_and_saves_file(): void
    {
        $this->createStrongWord(1, 'λόγος');
        $json = $this->fakeTranslationJson('λόγος');

        $mock = Mockery::mock(AiPromptService::class);
        $mock->shouldReceive('generate')
            ->once()
            ->with('strong_word_translation', false, [
                'strong_number' => '1',
                'greek_word' => 'λόγος',
                'transliteration' => 'translit',
            ], null, ['model' => 'gpt-5.5'])
            ->andReturn($json);
        $mock->shouldReceive('extractTextAndTokens')
            ->once()
            ->with($json)
            ->andReturn([$json, 42]);
        $this->app->instance(AiPromptService::class, $mock);

        $this->artisan('szentiras:generate-strong-word-translations', [
            '--word' => '1',
            '--provider' => 'openai',
        ])->assertSuccessful();

        Storage::assertExists('translation/1_gpt-5.5.json');
        $this->assertSame($json, Storage::get('translation/1_gpt-5.5.json'));
    }

    public function test_eimi_prompt_treats_the_input_as_a_lexeme_instead_of_a_first_person_form(): void
    {
        $configuration = config('ai.configurations.strong_word_translation');

        $prompts = app(AiPromptService::class)->preparePrompts($configuration, [
            'strong_number' => '1510',
            'greek_word' => 'εἰμί',
            'transliteration' => 'eimi',
        ]);

        $this->assertStringContainsString('lexéma szótári alakja (lemma)', $prompts['system']);
        $this->assertStringContainsString('εἰμί jelentése „van”', $prompts['system']);
        $this->assertStringContainsString('nem „vagyok” vagy „én vagyok”', $prompts['system']);
        $this->assertSame(
            "Strong-szám: G1510\nGörög lemma: εἰμί\nÁtírás: eimi",
            trim($prompts['user'])
        );
    }

    public function test_bad_openai_response_does_not_save_file(): void
    {
        $this->createStrongWord(1, 'λόγος');
        $badResponse = 'this is not json';

        $mock = Mockery::mock(AiPromptService::class);
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn($badResponse);
        $mock->shouldReceive('extractTextAndTokens')
            ->once()
            ->with($badResponse)
            ->andReturn([$badResponse, 7]);
        $this->app->instance(AiPromptService::class, $mock);

        $this->artisan('szentiras:generate-strong-word-translations', [
            '--word' => '1',
            '--provider' => 'openai',
        ])
            ->expectsOutputToContain('Bad response from AI: '.$badResponse)
            ->assertSuccessful();

        Storage::assertMissing('translation/1_gpt-5.5.json');
    }

    public function test_limit_caps_number_of_generated_words(): void
    {
        $this->createStrongWord(1, 'λόγος');
        $this->createStrongWord(2, 'θεός');
        $this->createStrongWord(3, 'πνεῦμα');

        $mock = Mockery::mock(AiPromptService::class);
        $mock->shouldReceive('generate')
            ->twice()
            ->andReturn('{}');
        $mock->shouldReceive('extractTextAndTokens')
            ->twice()
            ->andReturn([$this->fakeTranslationJson('x'), 10]);
        $this->app->instance(AiPromptService::class, $mock);

        $this->artisan('szentiras:generate-strong-word-translations', [
            '--word' => '1,2,3',
            '--provider' => 'openai',
            '--limit' => '2',
        ])->assertSuccessful();

        Storage::assertExists('translation/1_gpt-5.5.json');
        Storage::assertExists('translation/2_gpt-5.5.json');
        Storage::assertMissing('translation/3_gpt-5.5.json');
    }

    public function test_importing_from_source_clears_existing_translations_from_other_sources(): void
    {
        $this->createStrongWord(1, 'λόγος');

        $staleEntry = new DictionaryEntry;
        $staleEntry->strong_word_number = 1;
        $staleEntry->source = 'old-model';
        $staleEntry->paradigm = 'régi';
        $staleEntry->etymology = 'régi eredet';
        $staleEntry->save();

        $staleMeaning = new DictionaryMeaning;
        $staleMeaning->strong_word_number = 1;
        $staleMeaning->source = 'old-model';
        $staleMeaning->meaning = 'régi';
        $staleMeaning->explanation = 'régi magyarázat';
        $staleMeaning->order = 0;
        $staleMeaning->save();

        Storage::disk('local')->put('translation/1_gpt-5.5.json', $this->fakeTranslationJson('λόγος'));

        $this->artisan('szentiras:generate-strong-word-translations', [
            '--word' => '1',
            '--provider' => 'openai',
            '--source' => 'filesystem',
        ])->assertSuccessful();

        $this->assertSame(1, DictionaryEntry::where('strong_word_number', 1)->count());
        $this->assertSame(1, DictionaryMeaning::where('strong_word_number', 1)->count());
        $this->assertSame(0, DictionaryEntry::where('source', 'old-model')->count());
        $this->assertSame(0, DictionaryMeaning::where('source', 'old-model')->count());

        $entry = DictionaryEntry::where('strong_word_number', 1)->first();
        $this->assertSame('gpt-5.5', $entry->source);
        $this->assertSame('eredet', $entry->etymology);
    }

    public function test_openai_provider_rejects_batch_mode(): void
    {
        $mock = Mockery::mock(AiPromptService::class);
        $mock->shouldNotReceive('generate');
        $this->app->instance(AiPromptService::class, $mock);

        $this->artisan('szentiras:generate-strong-word-translations', [
            '--provider' => 'openai',
            '--batch' => true,
        ])->assertFailed();
    }
}
