<?php

namespace SzentirasHu\Test;

use SimpleXMLElement;
use SzentirasHu\Console\Commands\ImportScriptureXml;
use SzentirasHu\Test\Common\TestCase;

/**
 * The publisher's XML (SZIT, KNB) keeps the old wording of revised lines in RegiSzoveg and
 * marks a line the revision deleted with an empty `<Alapszoveg><Null/></Alapszoveg>`.
 */
class ImportScriptureXmlTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function versesOf(string $verseXml, ?string $translationAbbrev = null): array
    {
        $command = $this->app->make(ImportScriptureXml::class);
        if ($translationAbbrev !== null) {
            (new \ReflectionMethod($command, 'loadHeadingMapping'))->invoke($command, $translationAbbrev);
        }
        $verseInserts = [];
        $processVerse = new \ReflectionMethod($command, 'processVerse');
        $processVerse->invokeArgs($command, [new SimpleXMLElement($verseXml), 121, 'PSA', 23, &$verseInserts]);

        return array_map(fn (array $insert): array => ['tip' => $insert['tip'], 'verse' => $insert['verse']], $verseInserts);
    }

    public function test_revised_lines_use_the_new_wording_and_deleted_lines_are_skipped(): void
    {
        $verses = $this->versesOf(<<<'XML'
            <Verse Nr="1" id="PSA 23:1">
                <Strofa Nr="a" Style="pk"><Alapszoveg>Dávid zsoltára.</Alapszoveg></Strofa>
                <Strofa Nr="b" Style="q">
                    <Alapszoveg>Az Úr az én pásztorom,</Alapszoveg>
                    <RegiSzoveg hv="2022" ej="r"><Sz Nr="0" St="n"><T>Az Úr az én pásztorom, nem szűkölködöm,</T></Sz></RegiSzoveg>
                </Strofa>
                <Strofa Nr="c" Style="q">
                    <Alapszoveg>nincs is semmiben hiányom.</Alapszoveg>
                    <RegiSzoveg hv="2022" ej="r"><Sz Nr="0" St="n"><T></T></Sz></RegiSzoveg>
                </Strofa>
                <Strofa Nr="d" Style="pk">
                    <Alapszoveg><Null/></Alapszoveg>
                    <RegiSzoveg hv="2024" ej="r"><Sz Nr="0" St="n"><T>Törölt mondat.</T></Sz></RegiSzoveg>
                </Strofa>
            </Verse>
            XML);

        $this->assertSame([
            ['tip' => 901, 'verse' => 'Dávid zsoltára.'],
            ['tip' => 902, 'verse' => 'Az Úr az én pásztorom,'],
            ['tip' => 902, 'verse' => 'nincs is semmiben hiányom.'],
        ], $verses);
    }

    public function test_synoptic_parallels_become_one_cross_reference_each(): void
    {
        $verses = $this->versesOf(<<<'XML'
            <Verse Nr="7" id="MRK 3:7">
                <Strofa Nr="a" Style="pf"><Alapszoveg>Jézus pedig tanítványaival a tenger felé vonult.</Alapszoveg></Strofa>
                <Labjegyzet>
                    <TobbVersesHiv>3,7-12</TobbVersesHiv>
                    <SzinoptikusHiv>
                        <Sz Nr="0" St="n"><T>ǁ </T></Sz>
                        <K G="MAT 4:23-25" F="Mt 4,23-25"/>
                        <Sz Nr="1" St="n"><T>; 12,15k; </T></Sz>
                        <K G="LUK 6:17-19" F="Lk 6,17-19; "/>
                        <K G="MAT 27:1" F="Mt 27,1k.11-26"/>
                        <Sz Nr="2" St="n"><T>.</T></Sz>
                    </SzinoptikusHiv>
                </Labjegyzet>
            </Verse>
            XML, 'KNB');

        $this->assertSame([
            ['tip' => 901, 'verse' => 'Jézus pedig tanítványaival a tenger felé vonult.'],
            ['tip' => 920, 'verse' => 'Mt 4,23-25'],
            ['tip' => 920, 'verse' => 'Mt 12,15-16'],
            ['tip' => 920, 'verse' => 'Lk 6,17-19'],
            ['tip' => 920, 'verse' => 'Mt 27,1-2.11-26'],
        ], $verses);
    }

    public function test_footnotes_are_imported_and_synoptic_parallels_are_skipped_without_a_cross_reference_type(): void
    {
        $verses = $this->versesOf(<<<'XML'
            <Verse Nr="1" id="MAT 3:1">
                <Strofa Nr="a" Style="pf"><Alapszoveg>Azokban a napokban fellépett Keresztelő János.</Alapszoveg></Strofa>
                <Labjegyzet>
                    <TobbVersesHiv>3,1-6</TobbVersesHiv>
                    <SzinoptikusHiv><Sz Nr="0" St="n"><T>ǁ </T></Sz><K G="MRK 1:2-6" F="Mk 1,2-6"/></SzinoptikusHiv>
                </Labjegyzet>
                <Labjegyzet>
                    <LabjSzoveg><Sz Nr="0" St="n"><T>Vö. </T></Sz><K G="ISA 40:3" F="Iz 40,3"/></LabjSzoveg>
                </Labjegyzet>
            </Verse>
            XML, 'SZIT');

        $this->assertSame([
            ['tip' => 901, 'verse' => 'Azokban a napokban fellépett Keresztelő János.'],
            ['tip' => 2001, 'verse' => 'Vö. Iz 40,3'],
        ], $verses);
    }

    public function test_headings_get_the_type_of_their_level_and_jerome_notes_stay_hidden(): void
    {
        config(['translations.definitions.KNB.verseTypes.heading' => [320 => 1, 330 => 2, 640 => 3, 650 => 4]]);
        $command = $this->app->make(ImportScriptureXml::class);
        (new \ReflectionMethod($command, 'loadHeadingMapping'))->invoke($command, 'KNB');
        $processHeading = new \ReflectionMethod($command, 'processHeading');

        $verseInserts = [];
        foreach ([
            '<Cimsor Szint="4" ElsoVers="DAN 3:24"><Alapszoveg>Azarja imája</Alapszoveg></Cimsor>',
            '<Cimsor Szint="SzJ" ElsoVers="DAN 3:24"><Alapszoveg>Szent Jeromos megjegyzése: …</Alapszoveg></Cimsor>',
        ] as $headingXml) {
            $processHeading->invokeArgs($command, [new SimpleXMLElement($headingXml), 133, 'DAN', &$verseInserts, 3]);
        }

        $this->assertSame([650, ImportScriptureXml::JEROME_NOTE_TYPE], array_column($verseInserts, 'tip'));
        $this->assertSame([3, 3], array_column($verseInserts, 'chapter'));
        $this->assertSame([24, 24], array_column($verseInserts, 'numv'));
    }

    public function test_italic_segments_of_the_web_layout_are_marked(): void
    {
        $verses = $this->versesOf(<<<'XML'
            <Verse Nr="1" id="PSA 4:1">
                <Strofa Nr="a" Style="pk">
                    <Alapszoveg>A karvezetőnek: hárfára. Dávid zsoltára.</Alapszoveg>
                    <TordWeb><Sz Nr="0" St="i"><T>A karvezetőnek: hárfára. </T></Sz><Sz Nr="1" St="id"><T>Dávid zsoltára.</T></Sz></TordWeb>
                </Strofa>
                <Strofa Nr="b" Style="q">
                    <Alapszoveg>„Íme, a szűz méhében fogan” – mondja.</Alapszoveg>
                    <TordWeb><Sz Nr="0" St="id"><T>„Íme, a szűz méhében fogan” </T></Sz><Sz Nr="1" St="n"><T>– mondja.</T></Sz></TordWeb>
                </Strofa>
                <Strofa Nr="c" Style="q">
                    <Alapszoveg>semmiképp sem vagy a legkisebb</Alapszoveg>
                    <TordWeb><Sz Nr="0" St="n"><T>semmiképp sem vagy </T></Sz><Sz Nr="1" St="id1"><T>a łlegkisebb</T></Sz></TordWeb>
                </Strofa>
                <Strofa Nr="d" Style="q">
                    <Alapszoveg>Ha segítségül hívom,</Alapszoveg>
                    <TordWeb><Sz Nr="0" St="i"><T>Ha másként hívom,</T></Sz></TordWeb>
                </Strofa>
                <Strofa Nr="e" Style="q"><Alapszoveg>meghallgat engem.</Alapszoveg></Strofa>
            </Verse>
            XML);

        $this->assertSame([
            ['tip' => 901, 'verse' => '<i>A karvezetőnek: hárfára. Dávid zsoltára.</i>'],
            ['tip' => 902, 'verse' => '<i>„Íme, a szűz méhében fogan”</i> – mondja.'],
            ['tip' => 902, 'verse' => 'semmiképp sem vagy <i>a legkisebb</i>'],
            ['tip' => 902, 'verse' => 'Ha segítségül hívom,'],
            ['tip' => 902, 'verse' => 'meghallgat engem.'],
        ], $verses);
    }

    public function test_with_no_hunspell_the_stems_come_from_the_stems_file(): void
    {
        $command = $this->app->make(ImportScriptureXml::class);
        $command->setOutput(new \Illuminate\Console\OutputStyle(new \Symfony\Component\Console\Input\ArrayInput([]), new \Symfony\Component\Console\Output\NullOutput()));
        (new \ReflectionMethod($command, 'useStemsFile'))->invoke($command);
        $this->assertTrue((new \ReflectionProperty($command, 'stemsFromFileOnly'))->getValue($command));
        \Cache::store('array')->put('hunspell_pásztorom', ['pásztor'], 60);

        $verseroot = (new \ReflectionMethod($command, 'executeStemming'))->invoke($command, 'Az Úr az én pásztorom,', []);

        $this->assertSame('az úr az én pásztor pásztorom', $verseroot);
        $this->assertSame(3, (new \ReflectionProperty($command, 'wordsWithoutStems'))->getValue($command));
    }

    public function test_a_verse_deleted_entirely_keeps_an_empty_record_for_its_number(): void
    {
        $verses = $this->versesOf(<<<'XML'
            <Verse Nr="2" id="PSA 23:2">
                <Strofa Nr="a" Style="pf"><Alapszoveg><Null/></Alapszoveg></Strofa>
            </Verse>
            XML);

        $this->assertSame([['tip' => 901, 'verse' => '']], $verses);
    }
}
