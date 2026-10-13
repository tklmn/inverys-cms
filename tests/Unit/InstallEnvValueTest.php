<?php

namespace Tests\Unit;

use App\Http\Controllers\InstallController;
use Dotenv\Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Values the installer writes to the .env come back unchanged when read —
 * even with spaces, quotes and $.
 */
class InstallEnvValueTest extends TestCase
{
    public static function values(): array
    {
        return [
            'space' => ['pa ss word'],
            'quote' => ['p"q'],
            'backslash' => ['a\\b\\'],
            'variable' => ['x${APP_NAME}y'],
            'single quote' => ["it's"],
            'plain' => ['secret'],
        ];
    }

    #[DataProvider('values')]
    public function test_value_round_trips_through_dotenv(string $raw): void
    {
        $entries = (new Parser)->parse('KEY='.InstallController::envValue($raw)."\n");

        $this->assertSame($raw, $entries[0]->getValue()->get()->getChars());
        $this->assertSame([], $entries[0]->getValue()->get()->getVars());
    }

    public function test_line_breaks_cannot_add_lines(): void
    {
        $entries = (new Parser)->parse('KEY='.InstallController::envValue("a\nAPP_DEBUG=true")."\n");

        $this->assertCount(1, $entries);
        $this->assertSame('aAPP_DEBUG=true', $entries[0]->getValue()->get()->getChars());
    }
}
