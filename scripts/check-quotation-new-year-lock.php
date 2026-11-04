<?php

declare(strict_types=1);

use App\Models\Party;
use App\Models\Quotation;
use App\Models\User;
use App\Quotations\QuotationNumberAllocator;
use App\Quotations\QuotationWriter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Proves the first two quotations of a Dhaka year can be created together.
 *
 * The sequence table starts empty. Two processes each create a quotation for
 * a different customer. Both must finish, and the numbers must be the first
 * two consecutive numbers for that year. A failed allocation is rolled back
 * afterwards and does not consume the next number.
 *
 * Point DB_DATABASE at an empty asl_quotation_* database. This script refuses
 * asl_erp so it cannot touch the application database.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_quotation_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_quotation_* database. Refusing asl_erp.\n");
    exit(1);
}

quotationYearForceDatabase($database);
quotationYearBootstrap($database);

if (in_array('--worker', $argv, true)) {
    quotationYearWorker($argv);
    exit(0);
}

quotationYearParent();
exit(0);

function quotationYearParent(): void
{
    $migrated = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrated !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }

    if ((int) DB::table('quotation_number_sequences')->count() !== 0) {
        fwrite(STDERR, "The sequence table was not empty before the first allocation.\n");
        exit(1);
    }

    $user = User::query()->create([
        'name' => 'Quotation numbering',
        'email' => 'numbering@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $firstParty = quotationYearParty('First Customer Ltd.', 'first@example.com', '01711000011');
    $secondParty = quotationYearParty('Second Customer Ltd.', 'second@example.com', '01711000012');
    $directory = sys_get_temp_dir().'/asl-quotation-year-'.bin2hex(random_bytes(4));

    if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $go = $directory.'/go';
    $workers = [
        quotationYearSpawn($directory, 'a', (int) $user->getKey(), (int) $firstParty->getKey(), $go),
        quotationYearSpawn($directory, 'b', (int) $user->getKey(), (int) $secondParty->getKey(), $go),
    ];
    $readyDeadline = microtime(true) + 30;

    while (microtime(true) < $readyDeadline) {
        quotationYearDrain($workers);

        if (is_file($workers[0]['ready']) && is_file($workers[1]['ready'])) {
            break;
        }

        usleep(50000);
    }

    if (! is_file($workers[0]['ready']) || ! is_file($workers[1]['ready'])) {
        quotationYearStop($workers, 'A worker did not become ready.');
    }

    file_put_contents($go, 'go');
    $finishedDeadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $finishedDeadline) {
        quotationYearDrain($workers);
        $running = false;

        foreach ($workers as $worker) {
            $status = proc_get_status($worker['process']);

            if ($status['running']) {
                $running = true;
            }
        }

        if ($running) {
            usleep(50000);
        }
    }

    if ($running) {
        quotationYearStop($workers, 'A worker did not finish.');
    }

    foreach ($workers as $index => $worker) {
        $workers[$index]['code'] = proc_close($worker['process']);
    }

    $numbers = [];

    foreach ($workers as $worker) {
        if ($worker['code'] !== 0) {
            quotationYearClean($directory);
            fwrite(STDERR, "Worker exited {$worker['code']}.\n{$worker['stdout']}\n{$worker['stderr']}\n");
            exit(1);
        }

        $number = is_file($worker['result']) ? trim((string) file_get_contents($worker['result'])) : '';
        $numbers[] = $number;
        $start = (float) file_get_contents($worker['start']);
        $end = (float) file_get_contents($worker['end']);

        if ($start <= 0.0 || $end <= $start) {
            quotationYearClean($directory);
            fwrite(STDERR, "Worker {$worker['name']} did not record an overlapping allocation.\n");
            exit(1);
        }
    }

    $firstStart = (float) file_get_contents($workers[0]['start']);
    $firstEnd = (float) file_get_contents($workers[0]['end']);
    $secondStart = (float) file_get_contents($workers[1]['start']);
    $secondEnd = (float) file_get_contents($workers[1]['end']);

    if (! ($firstStart < $secondEnd && $secondStart < $firstEnd)) {
        quotationYearClean($directory);
        fwrite(STDERR, "The two creates did not overlap.\n");
        exit(1);
    }

    $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
    $expected = [sprintf('QT-%d-0001', $year), sprintf('QT-%d-0002', $year)];
    sort($numbers);
    sort($expected);

    if ($numbers !== $expected) {
        quotationYearClean($directory);
        fwrite(STDERR, 'Expected '.implode(' and ', $expected).', got '.implode(' and ', $numbers).".\n");
        exit(1);
    }

    if (Quotation::query()->count() !== 2 || (int) DB::table('quotation_number_sequences')->where('year', $year)->value('last_number') !== 2) {
        quotationYearClean($directory);
        fwrite(STDERR, "The year sequence did not end at 2 after two quotations.\n");
        exit(1);
    }

    DB::beginTransaction();
    $rolledBack = app(QuotationNumberAllocator::class)->allocate();
    DB::rollBack();
    $kept = app(QuotationNumberAllocator::class)->allocate();

    if ($rolledBack !== $kept || $kept !== sprintf('QT-%d-0003', $year)) {
        quotationYearClean($directory);
        fwrite(STDERR, "A rolled-back allocation consumed {$rolledBack}; the next allocation was {$kept}.\n");
        exit(1);
    }

    if ((int) DB::table('quotation_number_sequences')->where('year', $year)->value('last_number') !== 3 || Quotation::query()->count() !== 2) {
        quotationYearClean($directory);
        fwrite(STDERR, "The rollback check changed the stored quotations.\n");
        exit(1);
    }

    quotationYearClean($directory);
    fwrite(STDOUT, "First {$year} allocations overlapped and returned {$expected[0]} and {$expected[1]}. A rolled-back allocation did not consume {$kept}.\n");
}

/**
 * @param  list<string>  $argv
 */
function quotationYearWorker(array $argv): void
{
    $partyId = quotationYearArgument($argv, 'party');
    $userId = quotationYearArgument($argv, 'user');
    $ready = quotationYearArgument($argv, 'ready');
    $go = quotationYearArgument($argv, 'go');
    $start = quotationYearArgument($argv, 'start');
    $end = quotationYearArgument($argv, 'end');
    $result = quotationYearArgument($argv, 'result');
    $user = User::query()->find($userId);

    if (! $user instanceof User) {
        fwrite(STDERR, "User {$userId} was not found.\n");
        exit(1);
    }

    file_put_contents($ready, 'ready');
    $deadline = microtime(true) + 30;

    while (! is_file($go)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "Timed out waiting for the start signal.\n");
            exit(1);
        }

        usleep(20000);
    }

    try {
        file_put_contents($start, (string) microtime(true));
        $quotation = app(QuotationWriter::class)->create($user, [
            'party_id' => $partyId,
            'quotation_date' => '2026-11-04',
            'valid_until' => '2026-12-04',
            'lines' => [[
                'description' => 'Concurrent line',
                'unit' => 'job',
                'quantity' => '1',
                'unit_price' => '10.00',
            ]],
        ]);
        file_put_contents($end, (string) microtime(true));
        file_put_contents($result, $quotation->stringAttribute('number'));
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
        exit(1);
    }
}

function quotationYearParty(string $name, string $email, string $phone): Party
{
    $party = Party::query()->create([
        'legal_name' => $name,
        'party_kind' => 'company',
        'status' => 'active',
        'currency_code' => 'BDT',
        'email' => $email,
        'phone' => $phone,
    ]);
    $party->roles()->create(['role' => 'customer']);

    return $party;
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}
 */
function quotationYearSpawn(string $directory, string $name, int $userId, int $partyId, string $go): array
{
    $ready = $directory.'/'.$name.'.ready';
    $start = $directory.'/'.$name.'.start';
    $end = $directory.'/'.$name.'.end';
    $result = $directory.'/'.$name.'.result';
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--party='.$partyId,
        '--user='.$userId,
        '--ready='.$ready,
        '--go='.$go,
        '--start='.$start,
        '--end='.$end,
        '--result='.$result,
    ];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, quotationYearChildEnvironment());

    if (! is_resource($process) || ! isset($pipes[1], $pipes[2]) || ! is_resource($pipes[1]) || ! is_resource($pipes[2])) {
        fwrite(STDERR, "Could not start worker {$name}.\n");
        exit(1);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return [
        'name' => $name,
        'process' => $process,
        'stdout' => $pipes[1],
        'stderr' => $pipes[2],
        'ready' => $ready,
        'start' => $start,
        'end' => $end,
        'result' => $result,
        'code' => 1,
        'output' => '',
        'error' => '',
    ];
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function quotationYearDrain(array &$workers): void
{
    foreach ($workers as $index => $worker) {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        $workers[$index]['output'] .= is_string($stdout) ? $stdout : '';
        $workers[$index]['error'] .= is_string($stderr) ? $stderr : '';
    }
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function quotationYearStop(array $workers, string $message): void
{
    quotationYearDrain($workers);

    foreach ($workers as $worker) {
        proc_terminate($worker['process']);
        fwrite(STDERR, "Worker {$worker['name']} output:\n{$worker['output']}\n{$worker['error']}\n");
    }

    fwrite(STDERR, $message."\n");
    exit(1);
}

function quotationYearClean(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_string($file) && is_file($file)) {
            unlink($file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

/**
 * @param  list<string>  $argv
 */
function quotationYearArgument(array $argv, string $name): string
{
    $prefix = '--'.$name.'=';

    foreach ($argv as $argument) {
        if (str_starts_with($argument, $prefix)) {
            return substr($argument, strlen($prefix));
        }
    }

    fwrite(STDERR, "Missing --{$name}.\n");
    exit(1);
}

function quotationYearForceDatabase(string $database): void
{
    quotationYearSetEnv('DB_CONNECTION', 'mysql');
    quotationYearSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
}

function quotationYearSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function quotationYearBootstrap(string $database): void
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $configured = config('database.connections.mysql.database');

    if (config('database.default') !== 'mysql' || $configured !== $database) {
        fwrite(STDERR, "Refusing to continue because the application database is not the disposable {$database} database.\n");
        exit(1);
    }
}

/**
 * @return array<string, string>
 */
function quotationYearChildEnvironment(): array
{
    $environment = [];
    $raw = file_get_contents('/proc/self/environ');

    if (is_string($raw)) {
        foreach (explode("\0", $raw) as $entry) {
            if ($entry === '' || ! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $entry, 2);
            $environment[$key] = $value;
        }
    }

    $database = getenv('DB_DATABASE');
    $environment['DB_CONNECTION'] = 'mysql';
    $environment['DB_DATABASE'] = is_string($database) ? $database : '';
    $environment['ASL_QUOTATION_ALLOCATION_DELAY'] = '1';
    unset($environment['DB_URL']);

    return $environment;
}
