<?php

namespace SzentirasHu\Test;

use PHPUnit\Framework\TestCase;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;

/**
 * Runs the shared parser cases in tests/fixtures/reference-parser-cases.json.
 * Magister's Python port runs the same file, so the two parsers cannot drift apart.
 */
class ReferenceParserCasesTest extends TestCase
{
    public function testSharedCases(): void
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../fixtures/reference-parser-cases.json'), true)['cases'];
        foreach ($cases as $case) {
            $input = $case['input'];
            if ($case['canonical'] === null) {
                $this->assertFalse(CanonicalReference::isValid($input), "'{$input}' should be rejected");
                continue;
            }
            try {
                $actual = CanonicalReference::fromString($input)->toString();
            } catch (ParsingException $e) {
                $this->fail("'{$input}' should parse to '{$case['canonical']}' but was rejected");
            }
            $this->assertSame($case['canonical'], $actual, "'{$input}'");
        }
    }
}
