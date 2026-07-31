<?php

namespace SzentirasHu\Test\Display;

use SzentirasHu\Test\Common\FastDatabaseTestCase;

class ChapterRequestGuardTest extends FastDatabaseTestCase
{
    public function test_untranslated_url_rejects_more_than_five_chapters(): void
    {
        $this->get('/Mt1-20')
            ->assertStatus(413)
            ->assertSeeText('Egyszerre legfeljebb 5 fejezet kérhető.');
    }

    public function test_translated_url_rejects_a_large_whole_book_request(): void
    {
        $this->get('/TESTTRANS/Ter')
            ->assertStatus(413)
            ->assertSeeText('Egyszerre legfeljebb 5 fejezet kérhető.');
    }

    public function test_request_for_five_chapters_is_allowed(): void
    {
        $this->get('/TESTTRANS/Ter1-5')
            ->assertOk()
            ->assertSee('verse 1001002003');
    }

    public function test_small_whole_book_request_is_allowed(): void
    {
        $this->get('/TESTTRANS/Kiv')
            ->assertOk()
            ->assertSee('verse 1002001001');
    }
}
