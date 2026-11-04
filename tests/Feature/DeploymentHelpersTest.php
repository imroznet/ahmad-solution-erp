<?php

namespace Tests\Feature;

use App\Authorization\AccessSqlExporter;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_sql_contains_roles_and_no_users(): void
    {
        $sql = AccessSqlExporter::toSql();

        $this->assertStringContainsString('super-administrator', $sql);
        $this->assertStringContainsString('INSERT INTO `permissions`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `users`', $sql);
        $this->assertStringNotContainsString('DELETE FROM', $sql);
        $this->assertStringNotContainsString('password', strtolower($sql));
    }

    public function test_admin_sql_generator_prints_a_hash_and_refuses_a_weak_password(): void
    {
        $success = $this->generator([
            'ADMIN_NAME' => 'Amina Rahman',
            'ADMIN_EMAIL' => 'Admin@Example.com',
            'ADMIN_PASSWORD' => 'Correct-Horse-1',
        ]);

        $this->assertSame(0, $success->getExitCode(), $success->getErrorOutput());
        $this->assertStringContainsString('super-administrator', $success->getOutput());
        $this->assertStringContainsString('admin@example.com', $success->getOutput());
        $this->assertStringNotContainsString('Correct-Horse-1', $success->getOutput());
        $this->assertMatchesRegularExpression('/\$2[aby]\$/', $success->getOutput());

        $failure = $this->generator([
            'ADMIN_NAME' => 'Amina Rahman',
            'ADMIN_EMAIL' => 'admin@example.com',
            'ADMIN_PASSWORD' => 'short',
        ]);

        $this->assertSame(1, $failure->getExitCode());
        $this->assertStringNotContainsString('INSERT INTO', $failure->getOutput());
    }

    public function test_admin_sql_escapes_a_backslash_before_an_apostrophe(): void
    {
        $success = $this->generator([
            'ADMIN_NAME' => "A\\'B",
            'ADMIN_EMAIL' => 'admin@example.com',
            'ADMIN_PASSWORD' => 'Correct-Horse-1',
        ]);

        $this->assertSame(0, $success->getExitCode(), $success->getErrorOutput());

        $output = $success->getOutput();
        $safe = "'A\\\\\\'B'";
        $unsafe = "'A\\'''";

        $this->assertStringContainsString($safe, $output);
        $this->assertStringNotContainsString($unsafe, $output);
        $this->assertStringContainsString('NO_BACKSLASH_ESCAPES', $output);
        $this->assertSame($safe, AccessSqlExporter::sqlLiteral("A\\'B"));
    }

    public function test_reference_sql_matches_the_committed_file(): void
    {
        $committed = file_get_contents(base_path('deploy/reference-data.sql'));

        $this->assertIsString($committed);
        $this->assertSame($committed, AccessSqlExporter::toSql());
    }

    public function test_create_admin_command_uses_the_environment_without_echoing_the_password(): void
    {
        putenv('ADMIN_NAME=Amina Rahman');
        putenv('ADMIN_EMAIL=amina@example.com');
        putenv('ADMIN_PASSWORD=Correct-Horse-1');

        try {
            $this->artisan('asl:create-admin --no-interaction')
                ->expectsOutputToContain('Super administrator created')
                ->doesntExpectOutputToContain('Correct-Horse-1')
                ->assertSuccessful();
        } finally {
            putenv('ADMIN_NAME');
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertDatabaseHas('users', ['email' => 'amina@example.com']);
        $this->assertStringNotContainsString(
            'Correct-Horse-1',
            (string) AuditLog::query()->get()->toJson(),
        );
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function generator(array $environment): Process
    {
        $inherited = getenv();
        $process = new Process(
            [PHP_BINARY, base_path('deploy/generate-admin-sql.php')],
            base_path(),
            array_merge(is_array($inherited) ? $inherited : [], $environment),
        );
        $process->run();

        return $process;
    }
}
