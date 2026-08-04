<?php

namespace SzentirasHu\Test\Display;

use SzentirasHu\Test\Common\FastDatabaseTestCase;

class AccessibilityControlsTest extends FastDatabaseTestCase
{
    public function test_verse_page_offers_an_accessibility_toolbar(): void
    {
        $response = $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('aria-label="Akadálymentességi eszközök"', false)
            ->assertSee('data-step="-1"', false)
            ->assertSee('data-step="1"', false)
            ->assertSee('aria-label="Betűméret csökkentése"', false)
            ->assertSee('aria-label="Betűméret növelése"', false)
            ->assertSee('class="text-size-status visually-hidden"', false)
            ->assertSee('aria-label="Olvasási beállítások"', false);

        $this->assertMatchesRegularExpression(
            '/id="readAloudButton".*text-size-step.*readingPreferencesButton/s',
            $response->getContent(),
            'Read aloud, text size and reading preferences are expected in one toolbar, in that order.',
        );
    }

    public function test_read_aloud_button_is_an_icon_with_an_accessible_name(): void
    {
        $response = $this->get('/TESTTRANS/Ter2')->assertOk();

        $response->assertSee('<i class="read-aloud-icon bi bi-volume-up-fill" aria-hidden="true"></i>', false);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="readAloudButton"[^>]*aria-label="Felolvasás"/',
            $response->getContent(),
        );
        $this->assertStringNotContainsString(
            'read-aloud-label',
            $response->getContent(),
            'The button is icon only, its name comes from aria-label.',
        );
    }

    public function test_reading_preferences_offer_font_and_display_switches(): void
    {
        $response = $this->get('/TESTTRANS/Ter2')->assertOk();

        foreach (['serif', 'sans', 'hyperlegible'] as $font) {
            $response->assertSee('data-preference="font"', false)
                ->assertSee('value="' . $font . '"', false);
        }

        foreach ([
            'weight' => 'bold',
            'spacing' => 'comfortable',
            'align' => 'left',
            'contrast' => 'high',
        ] as $preference => $onValue) {
            $response->assertSee(
                'data-preference="' . $preference . '" data-on="' . $onValue . '"',
                false,
            );
        }
    }

    public function test_stored_reading_preferences_are_applied_before_first_paint(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee("localStorage.getItem('readingPreferences')", false)
            ->assertSee('data-text-contrast', false);
    }

    /**
     * A weight the optional font does not ship resolves back to the regular face, leaving the
     * bolder-text switch with no visible effect at all.
     */
    public function test_the_bolder_text_weight_is_one_the_optional_font_ships(): void
    {
        $css = file_get_contents(resource_path('assets/less/app.less'));
        $module = file_get_contents(resource_path('assets/js/readingPreferences.js'));

        $this->assertSame(1, preg_match(
            '/\[data-text-weight="bold"\]\s+\.parsedVerses\s*\{\s*font-weight:\s*(\d+);/',
            $css,
            $switch,
        ));
        $this->assertSame(1, preg_match("/HYPERLEGIBLE_FONT_HREF = '([^']+)'/", $module, $href));
        $this->assertSame(1, preg_match('/wght@([^&\']+)/', urldecode($href[1]), $axis));

        preg_match_all('/\d+/', $axis[1], $requestedWeights);
        $this->assertContains(
            $switch[1],
            $requestedWeights[0],
            "The bolder-text switch uses weight {$switch[1]}, which the font request does not include.",
        );
    }

    /**
     * The inline pre-paint script mirrors readingPreferences.js; a font URL that drifts apart
     * would load a different family than the stylesheet expects.
     */
    public function test_inline_font_loader_matches_the_module(): void
    {
        $module = file_get_contents(resource_path('assets/js/readingPreferences.js'));
        $layout = file_get_contents(resource_path('views/layout.twig'));

        $this->assertSame(
            1,
            preg_match("/HYPERLEGIBLE_FONT_HREF = '([^']+)'/", $module, $matches),
        );
        $this->assertStringContainsString($matches[1], $layout);
        $this->assertStringContainsString('Atkinson+Hyperlegible+Next', $matches[1]);
    }

    public function test_reading_preferences_only_restyle_the_scripture_text(): void
    {
        $css = file_get_contents(resource_path('assets/less/app.less'));

        foreach ([
            'data-text-weight="bold"',
            'data-text-spacing="comfortable"',
            'data-text-align="left"',
            'data-text-contrast="high"',
        ] as $selector) {
            $this->assertMatchesRegularExpression(
                '/:root\[' . preg_quote($selector, '/') . '\] \.parsedVerses/',
                $css,
                "Expected {$selector} to be scoped to .parsedVerses.",
            );
        }
    }

    public function test_system_contrast_and_forced_colors_preferences_are_honoured(): void
    {
        $css = file_get_contents(resource_path('assets/less/app.less'));

        $this->assertStringContainsString('@media (prefers-contrast: more)', $css);
        $this->assertStringContainsString(':root:not([data-text-contrast]) .parsedVerses', $css);
        $this->assertStringContainsString('@media (forced-colors: active)', $css);
    }

    public function test_the_text_size_stepper_stays_out_of_the_navbar(): void
    {
        $navbar = file_get_contents(resource_path('views/navbar.twig'));

        $this->assertStringNotContainsString('text-size-step', $navbar);
    }

    public function test_stored_text_size_is_applied_before_first_paint(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee("localStorage.getItem('fontScale')", false)
            ->assertSee("setProperty('--book-font-size'", false);
    }

    public function test_root_font_size_follows_the_browser_default(): void
    {
        $css = file_get_contents(resource_path('assets/less/app.less'));

        $this->assertStringContainsString("--book-font-size: 1rem;", $css);
        $this->assertMatchesRegularExpression('/html\s*\{\s*font-size:\s*100%;\s*\}/', $css);
    }

    public function test_verse_page_offers_a_read_aloud_control(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('id="readAloudButton"', false)
            ->assertSee('id="readAloudStopButton"', false)
            ->assertSee('aria-label="Felolvasás"', false)
            ->assertSee('aria-label="Felolvasás leállítása"', false)
            ->assertSee('id="readAloudStatus"', false);
    }

    /**
     * Side by side comparison has no single linear reading order, so the control is guarded
     * in the template. The seed data holds only one translation, hence the source assertion
     * instead of a rendered comparison page.
     */
    public function test_read_aloud_control_is_guarded_against_the_comparison_view(): void
    {
        $template = file_get_contents(resource_path('views/textDisplay/accessibilityToolbar.twig'));

        $this->assertMatchesRegularExpression(
            '/\{%\s*if not compareTranslation\s*%\}\s*<button[^>]*id="readAloudButton"/s',
            $template,
        );
    }

    public function test_verses_carry_the_anchors_the_read_aloud_splits_on(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('class="verse-anchor"', false);
    }

    public function test_icon_only_controls_have_accessible_names(): void
    {
        $response = $this->get('/TESTTRANS/Ter2')->assertOk();

        foreach ([
            'aria-label="Kapcsolatfelvétel"',
            'aria-label="Sötét/világos mód váltása"',
            'aria-label="Versszámozás megjelenítése vagy elrejtése"',
            'aria-label="Hivatkozások megjelenítése vagy elrejtése"',
            'aria-label="Címsorok megjelenítése vagy elrejtése"',
            'aria-label="Segédeszközök megjelenítése vagy elrejtése"',
            'aria-label="Fordítások összehasonlítása"',
        ] as $accessibleName) {
            $response->assertSee($accessibleName, false);
        }
    }

    public function test_decorative_icons_are_hidden_from_screen_readers(): void
    {
        $this->get('/TESTTRANS/Ter2')
            ->assertOk()
            ->assertSee('<i class="bi bi-envelope" aria-hidden="true"></i>', false)
            ->assertSee('<i class="bi-123" aria-hidden="true"></i>', false);
    }
}
