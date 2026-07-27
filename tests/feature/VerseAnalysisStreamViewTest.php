<?php

namespace SzentirasHu\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class VerseAnalysisStreamViewTest extends TestCase
{
    #[Test]
    public function it_marks_a_529_success_result_as_retryable_failure(): void
    {
        $process = $this->runViewer([
            $this->assistantText(
                'API Error: 529 Overloaded. This is a server-side issue, usually temporary.'
            ),
            ['type' => 'result', 'subtype' => 'success', 'total_cost_usd' => 0],
        ]);

        $this->assertSame(75, $process->getExitCode());
        $this->assertStringContainsString('⚠️  API Error: 529 Overloaded.', $process->getOutput());
        $this->assertStringContainsString(
            '❌ result: API hiba (jelzett altípus: success)  ($0.00)',
            $process->getOutput()
        );
        $this->assertStringNotContainsString('✅ result:', $process->getOutput());
    }

    #[Test]
    public function it_does_not_retry_a_non_transient_api_error(): void
    {
        $process = $this->runViewer([
            $this->assistantText('API Error: 401 Invalid API key.'),
            ['type' => 'result', 'subtype' => 'success'],
        ]);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString(
            '❌ result: API hiba (jelzett altípus: success)',
            $process->getOutput()
        );
    }

    #[Test]
    public function it_marks_a_session_limit_as_a_scheduled_retry(): void
    {
        $process = $this->runViewer([
            $this->assistantText("You've hit your session limit · resets 2:20pm (UTC)"),
            [
                'type' => 'result',
                'subtype' => 'success',
                'api_error_status' => 429,
            ],
        ]);

        $this->assertSame(76, $process->getExitCode());
        $this->assertStringContainsString(
            "⏳ You've hit your session limit · resets 2:20pm (UTC)",
            $process->getOutput()
        );
        $this->assertStringNotContainsString('✅ result:', $process->getOutput());
    }

    #[Test]
    public function it_calculates_the_utc_session_reset_delay_from_a_log(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'session-limit-');
        $this->assertNotFalse($logPath);

        try {
            file_put_contents(
                $logPath,
                json_encode(
                    $this->assistantText(
                        "You've hit your session limit · resets 2:20pm (UTC)"
                    ),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                )."\n"
            );

            $process = new Process([
                'python3',
                dirname(__DIR__, 2).'/bible_import/verse-analysis/stream-view.py',
                '--session-reset-delay',
                $logPath,
                '--now',
                '2026-07-26T10:38:00+00:00',
            ]);
            $process->run();

            $this->assertSame(0, $process->getExitCode());
            $this->assertSame("13335\n", $process->getOutput());
        } finally {
            unlink($logPath);
        }
    }

    #[Test]
    public function it_supports_hour_only_reset_times_on_the_following_day(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'session-limit-');
        $this->assertNotFalse($logPath);

        try {
            file_put_contents(
                $logPath,
                json_encode(
                    $this->assistantText(
                        "You've hit your session limit · resets 2am (UTC)"
                    ),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                )."\n"
            );

            $process = new Process([
                'python3',
                dirname(__DIR__, 2).'/bible_import/verse-analysis/stream-view.py',
                '--session-reset-delay',
                $logPath,
                '--now',
                '2026-07-25T21:20:00+00:00',
            ]);
            $process->run();

            $this->assertSame(0, $process->getExitCode());
            $this->assertSame("16815\n", $process->getOutput());
        } finally {
            unlink($logPath);
        }
    }

    #[Test]
    public function it_keeps_successful_sessions_successful(): void
    {
        $process = $this->runViewer([
            $this->assistantText('A fejezet elkészült.'),
            ['type' => 'result', 'subtype' => 'success', 'total_cost_usd' => 1.25],
        ]);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('✅ result: success  ($1.25)', $process->getOutput());
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function runViewer(array $events): Process
    {
        $process = new Process([
            'python3',
            dirname(__DIR__, 2).'/bible_import/verse-analysis/stream-view.py',
        ]);
        $process->setInput(
            implode("\n", array_map(
                static fn (array $event): string => json_encode(
                    $event,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ),
                $events
            ))."\n"
        );
        $process->run();

        return $process;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantText(string $text): array
    {
        return [
            'type' => 'assistant',
            'message' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ],
        ];
    }
}
