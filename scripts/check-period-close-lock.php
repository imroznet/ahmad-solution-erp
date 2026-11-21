<?php

declare(strict_types=1);

use App\Accounting\AccountingException;
use App\Accounting\AccountType;
use App\Accounting\FinancialYearCalendar;
use App\Accounting\JournalWriter;
use App\Accounting\PeriodCloser;
use App\Models\Account;
use App\Models\FinancialYear;
use App\Models\Journal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Proves a post cannot commit into a year after close has calculated retained earnings.
 *
 * One worker closes a past year and pauses while it holds that year's row.
 * The other posts a journal into the same year. The post either commits first,
 * and the carry-forward includes it, or it waits and is then refused.
 * The closed year never contains a posted journal that the carry-forward missed.
 *
 * Point DB_DATABASE at an empty asl_period_* database. This script refuses asl_erp.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_period_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_period_* database. Refusing asl_erp.\n");
    exit(1);
}

periodLockForceDatabase($database);
periodLockBootstrap($database);

if (in_array('--worker', $argv, true)) {
    periodLockWorker($argv);
    exit(0);
}

periodLockParent();
exit(0);

function periodLockParent(): void
{
    $migrated = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrated !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }

    $today = now()->timezone('Asia/Dhaka')->startOfDay();
    $pastStart = $today->copy()->subYear()->startOfMonth();
    $pastEnd = $today->copy()->subMonth()->endOfMonth();
    $nextStart = $pastEnd->copy()->addDay();
    $nextEnd = $nextStart->copy()->addYear()->subDay();
    $calendar = app(FinancialYearCalendar::class);
    $past = $calendar->create($pastStart->toDateString(), $pastEnd->toDateString());
    $calendar->create($nextStart->toDateString(), $nextEnd->toDateString());
    $user = User::query()->create([
        'name' => 'Period accountant',
        'email' => 'period-accountant@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $cash = Account::query()->where('system_key', 'cash')->first();
    $sales = Account::query()->where('system_key', 'sales_revenue')->first();
    $retained = Account::query()->where('system_key', 'retained_earnings')->first();

    if (! $cash instanceof Account || ! $sales instanceof Account || ! $retained instanceof Account) {
        fwrite(STDERR, "A system account is missing.\n");
        exit(1);
    }

    $expense = Account::query()->create([
        'code' => '5100',
        'name' => 'Close expense',
        'type' => AccountType::Expense,
        'is_active' => true,
    ]);
    $journals = app(JournalWriter::class);
    periodLockPost($journals, $user, $pastStart->copy()->addDays(5)->toDateString(), (int) $cash->id, (int) $sales->id, '100.00', 'Opening sale');
    periodLockPost($journals, $user, $pastStart->copy()->addDays(10)->toDateString(), (int) $expense->id, (int) $cash->id, '40.00', 'Opening cost');
    $draft = $journals->createDraft([
        'journal_date' => $pastStart->copy()->addDays(15)->toDateString(),
        'memo' => 'Overlapping sale',
        'lines' => [
            ['account_id' => $cash->id, 'debit' => '10.00', 'credit' => '0', 'party_id' => null, 'memo' => ''],
            ['account_id' => $sales->id, 'debit' => '0', 'credit' => '10.00', 'party_id' => null, 'memo' => ''],
        ],
    ], $user);
    $directory = sys_get_temp_dir().'/asl-period-lock-'.bin2hex(random_bytes(4));

    if (! mkdir($directory) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $go = $directory.'/go';
    $workers = [
        periodLockSpawn($directory, 'close', 'close', (int) $past->id, (int) $draft->id, (int) $user->id, $go),
        periodLockSpawn($directory, 'post', 'post', (int) $past->id, (int) $draft->id, (int) $user->id, $go),
    ];
    periodLockWaitReady($workers);
    file_put_contents($go, 'go');
    periodLockWaitFinished($workers);

    $posted = Journal::query()->where('memo', 'Overlapping sale')->where('status', 'posted')->count();
    $carry = Journal::query()->where('source', 'carry_forward')->first();
    $closed = FinancialYear::query()->whereKey($past->id)->first();
    $retainedAmount = 'missing';

    if ($carry instanceof Journal) {
        $line = $carry->lines()->where('account_id', $retained->id)->first();
        $retainedAmount = $line === null ? '0.00' : Money::of($line->decimalAttribute('credit'))->amount();
    }

    $closeWorker = $workers[0];
    $postWorker = $workers[1];
    $closeOk = $closeWorker['code'] === 0;
    $postRejected = $postWorker['code'] !== 0 && str_contains($postWorker['error'], 'closed financial year');
    $postWon = $postWorker['code'] === 0;
    $consistent = ($posted === 0 && $postRejected && $retainedAmount === '60.00')
        || ($posted === 1 && $postWon && $retainedAmount === '70.00');

    if (! $closeOk || ! $closed instanceof FinancialYear || ! $closed->isClosed() || ! $consistent) {
        periodLockStop($workers, "Close and the overlapping post did not agree. Posted {$posted}, retained earnings {$retainedAmount}.", false);
    }

    $starts = [];
    $ends = [];

    foreach ($workers as $worker) {
        $starts[] = (float) file_get_contents($worker['start']);
        $ends[] = (float) file_get_contents($worker['end']);
    }

    if (max($starts) >= min($ends)) {
        periodLockStop($workers, 'The close and the post did not overlap.', false);
    }

    periodLockClean($directory);
    fwrite(STDOUT, "The overlapping post was serialized with the close. Retained earnings is {$retainedAmount}.\n");
}

function periodLockPost(JournalWriter $journals, User $user, string $date, int $debitAccount, int $creditAccount, string $amount, string $memo): void
{
    $draft = $journals->createDraft([
        'journal_date' => $date,
        'memo' => $memo,
        'lines' => [
            ['account_id' => $debitAccount, 'debit' => $amount, 'credit' => '0', 'party_id' => null, 'memo' => ''],
            ['account_id' => $creditAccount, 'debit' => '0', 'credit' => $amount, 'party_id' => null, 'memo' => ''],
        ],
    ], $user);
    $journals->post($draft, $user);
}

/**
 * @param  list<string>  $argv
 */
function periodLockWorker(array $argv): void
{
    $role = periodLockArgument($argv, 'role');
    $yearId = (int) periodLockArgument($argv, 'year');
    $journalId = (int) periodLockArgument($argv, 'journal');
    $userId = (int) periodLockArgument($argv, 'user');
    $ready = periodLockArgument($argv, 'ready');
    $go = periodLockArgument($argv, 'go');
    $start = periodLockArgument($argv, 'start');
    $end = periodLockArgument($argv, 'end');
    $user = User::query()->find($userId);
    $year = FinancialYear::query()->find($yearId);
    $journal = Journal::query()->find($journalId);

    if (! $user instanceof User || ! $year instanceof FinancialYear || ! $journal instanceof Journal) {
        fwrite(STDERR, "The year, journal, or user was not found.\n");
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

    file_put_contents($start, (string) microtime(true));

    try {
        if ($role === 'close') {
            periodLockSetEnv('ASL_PERIOD_CLOSE_DELAY', '1');
            app(PeriodCloser::class)->close($year, $user, 'Books agreed for the lock rehearsal');
        } else {
            app(JournalWriter::class)->post($journal, $user);
        }

        file_put_contents($end, (string) microtime(true));
    } catch (AccountingException $exception) {
        file_put_contents($end, (string) microtime(true));
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, code: int, output: string, error: string}>  $workers
 */
function periodLockWaitReady(array $workers): void
{
    $deadline = microtime(true) + 20;

    while (microtime(true) < $deadline) {
        $ready = true;

        foreach ($workers as $worker) {
            if (! is_file($worker['ready'])) {
                $ready = false;
            }
        }

        if ($ready) {
            return;
        }

        usleep(50000);
    }

    periodLockStop($workers, 'A worker did not become ready.');
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, code: int, output: string, error: string}>  $workers
 */
function periodLockWaitFinished(array &$workers): void
{
    $deadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $deadline) {
        periodLockDrain($workers);
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
        periodLockStop($workers, 'A worker did not finish.');
    }

    periodLockDrain($workers);

    foreach ($workers as $index => $worker) {
        $workers[$index]['code'] = proc_close($worker['process']);
    }
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, code: int, output: string, error: string}
 */
function periodLockSpawn(string $directory, string $name, string $role, int $yearId, int $journalId, int $userId, string $go): array
{
    $ready = $directory.'/'.$name.'.ready';
    $start = $directory.'/'.$name.'.start';
    $end = $directory.'/'.$name.'.end';
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--role='.$role,
        '--year='.$yearId,
        '--journal='.$journalId,
        '--user='.$userId,
        '--ready='.$ready,
        '--go='.$go,
        '--start='.$start,
        '--end='.$end,
    ];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, periodLockChildEnvironment($role));

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
        'code' => 1,
        'output' => '',
        'error' => '',
    ];
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, code: int, output: string, error: string}>  $workers
 */
function periodLockDrain(array &$workers): void
{
    foreach ($workers as $index => $worker) {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        $workers[$index]['output'] .= is_string($stdout) ? $stdout : '';
        $workers[$index]['error'] .= is_string($stderr) ? $stderr : '';
    }
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, code: int, output: string, error: string}>  $workers
 */
function periodLockStop(array $workers, string $message, bool $terminate = true): never
{
    periodLockDrain($workers);

    foreach ($workers as $worker) {
        if ($terminate) {
            proc_terminate($worker['process']);
        }

        fwrite(STDERR, "Worker {$worker['name']} output:\n{$worker['output']}\n{$worker['error']}\n");
    }

    fwrite(STDERR, $message."\n");
    exit(1);
}

function periodLockClean(string $directory): void
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
function periodLockArgument(array $argv, string $name): string
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

function periodLockForceDatabase(string $database): void
{
    periodLockSetEnv('DB_CONNECTION', 'mysql');
    periodLockSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    putenv('ASL_PERIOD_CLOSE_DELAY');
    unset($_ENV['ASL_PERIOD_CLOSE_DELAY'], $_SERVER['ASL_PERIOD_CLOSE_DELAY']);
}

function periodLockSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function periodLockBootstrap(string $database): void
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
function periodLockChildEnvironment(string $role): array
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
    unset($environment['DB_URL'], $environment['ASL_PERIOD_CLOSE_DELAY']);

    if ($role === 'close') {
        $environment['ASL_PERIOD_CLOSE_DELAY'] = '1';
    }

    return $environment;
}
