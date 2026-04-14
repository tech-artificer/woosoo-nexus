<?php

namespace Tests\Unit;

use Tests\TestCase;

class LogLevelConfigTest extends TestCase
{
    public function test_env_log_level_is_error(): void
    {
        $envPath = base_path('.env');
        $this->assertFileExists($envPath);

        $contents = file_get_contents($envPath);
        $matches = [];
        preg_match('/^LOG_LEVEL=(.*)$/m', $contents, $matches);

        $this->assertNotEmpty($matches, 'LOG_LEVEL not found in .env');
        $this->assertSame('error', trim($matches[1]));
    }
}
