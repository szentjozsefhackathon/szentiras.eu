<?php

namespace SzentirasHu\Test\Display;

use SzentirasHu\Test\Common\FastDatabaseTestCase;

class ChapterRequestGuardTest extends FastDatabaseTestCase
{
    public function test_untranslated_url_rejects_more_than_five_chapters(): void
    {
        $this->get('/Mt1-20')
            ->assertStatus(422)
            ->assertSeeText('Egyszerre legfeljebb 5 fejezetet');
    }

    public function test_rejected_request_offers_the_first_five_chapters(): void
    {
        $this->get('/TESTTRANS/Ter1-20')
            ->assertStatus(422)
            ->assertSee('/TESTTRANS/Ter1-5');
    }

    public function test_whole_book_request_is_never_rejected(): void
    {
        $this->get('/TESTTRANS/Ter')
            ->assertOk();
    }

    public function test_mixed_reference_containing_a_large_whole_book_is_rejected(): void
    {
        $this->get('/TESTTRANS/Ter;Kiv1')
            ->assertStatus(422)
            ->assertSeeText('Egyszerre legfeljebb 5 fejezetet')
            ->assertSee('/TESTTRANS/Ter1-5');
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
