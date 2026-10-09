<?php

namespace SzentirasHu\Test;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use SzentirasHu\Console\Commands\ImportScripture;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Enum\VerseFixStatus;
use SzentirasHu\Models\VerseFix;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class ImportScriptureStoreTest extends FastDatabaseTestCase
{
    public function test_cache_is_cleared_after_texts_and_fixes_are_stored_before_site_goes_up(): void
    {
        $verseFix = VerseFix::factory()->create([
            'usx_code' => 'GEN',
            'gepi' => 'GEN_2_3',
            'chapter' => 2,
            'numv' => 3,
            'tip' => 6,
            'original_text' => 'imported text',
            'fixed_text' => 'fixed text',
        ]);

        $artisanCalls = [];
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command) use (&$artisanCalls): int {
            $artisanCalls[] = [
                'command' => $command,
                'storedText' => Verse::query()->where('trans', 1001)->where('gepi', 'GEN_2_3')->value('verse'),
            ];

            return 0;
        });

        $this->storeInDb(
            [['usx_code' => 'GEN', 'name' => 'Teremtés könyve', 'abbrev' => 'Ter', 'link' => 'Ter', 'old_testament' => 1, 'order' => 101]],
            [['order' => 101, 'chapter' => 2, 'numv' => 3, 'tip' => 6, 'verse' => 'imported text', 'verseroot' => 'imported', 'ido' => '2026-10-09']],
        );

        $this->assertSame(['down', 'cache:clear', 'up'], array_column($artisanCalls, 'command'));
        $this->assertSame('fixed text', $artisanCalls[1]['storedText']);
        $this->assertSame(VerseFixStatus::Active, $verseFix->fresh()->status);
    }

    /**
     * @param  list<array<string, mixed>>  $bookInserts
     * @param  list<array<string, mixed>>  $verseInserts
     */
    private function storeInDb(array $bookInserts, array $verseInserts): void
    {
        $command = $this->app->make(ImportScripture::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $storeInDb = new \ReflectionMethod($command, 'storeInDb');
        $storeInDb->invoke($command, Translation::query()->findOrFail(1001), $bookInserts, $verseInserts);
    }
}
