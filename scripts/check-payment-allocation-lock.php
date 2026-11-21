<?php

declare(strict_types=1);

use App\Accounting\AccountingException;
use App\Accounting\AccountType;
use App\Accounting\FinancialYearCalendar;
use App\Accounting\JournalWriter;
use App\Accounting\PaymentWriter;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Proves two payments cannot allocate the same payable journal past its outstanding.
 *
 * Each worker starts a payment for the full obligation. The first locks the journal
 * and pauses. The second waits, then reads the allocation with a current read.
 * One payment is recorded. The other is rejected and posts nothing.
 *
 * Point DB_DATABASE at an empty asl_payment_* database. This script refuses asl_erp.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_payment_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_payment_* database. Refusing asl_erp.\n");
    exit(1);
}

paymentLockForceDatabase($database);
paymentLockBootstrap($database);

if (in_array('--worker', $argv, true)) {
    paymentLockWorker($argv);
    exit(0);
}

paymentLockParent();
exit(0);

function paymentLockParent(): void
{
    $migrated = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrated !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }

    $today = now()->timezone('Asia/Dhaka');
    app(FinancialYearCalendar::class)->create(
        $today->copy()->startOfYear()->toDateString(),
        $today->copy()->endOfYear()->toDateString(),
    );
    $user = User::query()->create([
        'name' => 'Payment accountant',
        'email' => 'payment-accountant@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $party = Party::query()->create([
        'legal_name' => 'Payment Supplier Ltd.',
        'party_kind' => 'company',
        'status' => 'active',
        'currency_code' => 'BDT',
        'email' => 'payment-supplier@example.com',
        'phone' => '01711000041',
    ]);
    $party->roles()->create(['role' => 'supplier']);
    $expense = Account::query()->create([
        'code' => '5100',
        'name' => 'Lock expense',
        'type' => AccountType::Expense,
        'is_active' => true,
    ]);
    $payable = Account::query()->where('system_key', 'accounts_payable')->first();
    $cash = Account::query()->where('system_key', 'cash')->first();

    if (! $payable instanceof Account || ! $cash instanceof Account) {
        fwrite(STDERR, "The payable or cash account is missing.\n");
        exit(1);
    }

    $draft = app(JournalWriter::class)->createDraft([
        'journal_date' => $today->toDateString(),
        'memo' => 'Concurrent payable',
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0', 'party_id' => null, 'memo' => ''],
            ['account_id' => $payable->id, 'debit' => '0', 'credit' => '100.00', 'party_id' => $party->id, 'memo' => ''],
        ],
    ], $user);
    $journal = app(JournalWriter::class)->post($draft, $user);
    $directory = sys_get_temp_dir().'/asl-payment-lock-'.bin2hex(random_bytes(4));

    if (! mkdir($directory) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $go = $directory.'/go';
    $workers = [
        paymentLockSpawn($directory, 'first', (int) $journal->id, (int) $party->id, (int) $cash->id, (int) $user->id, $today->toDateString(), $go),
        paymentLockSpawn($directory, 'second', (int) $journal->id, (int) $party->id, (int) $cash->id, (int) $user->id, $today->toDateString(), $go),
    ];
    $deadline = microtime(true) + 20;

    while (microtime(true) < $deadline) {
        $ready = true;

        foreach ($workers as $worker) {
            if (! is_file($worker['ready'])) {
                $ready = false;
            }
        }

        if ($ready) {
            break;
        }

        usleep(50000);
    }

    foreach ($workers as $worker) {
        if (! is_file($worker['ready'])) {
            paymentLockStop($workers, 'A worker did not become ready.');
        }
    }

    file_put_contents($go, 'go');
    $deadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $deadline) {
        paymentLockDrain($workers);
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
        paymentLockStop($workers, 'A worker did not finish.');
    }

    paymentLockDrain($workers);

    foreach ($workers as $index => $worker) {
        $workers[$index]['code'] = proc_close($worker['process']);
    }

    $succeeded = 0;
    $rejected = 0;

    foreach ($workers as $worker) {
        if ($worker['code'] === 0 && is_file($worker['result'])) {
            $succeeded++;

            continue;
        }

        if ($worker['code'] !== 0 && str_contains($worker['error'], 'more than the obligation outstanding')) {
            $rejected++;

            continue;
        }

        paymentLockStop($workers, 'A worker ended in an unexpected state.', false);
    }

    $allocated = Money::zero();

    foreach (PaymentAllocation::query()->get() as $allocation) {
        $allocated = $allocated->plus(Money::of($allocation->decimalAttribute('amount')));
    }

    if ($succeeded !== 1 || $rejected !== 1
        || Payment::query()->count() !== 1
        || Journal::query()->where('source', 'payment')->count() !== 1
        || $allocated->amount() !== '100.00') {
        paymentLockStop($workers, "Expected one recorded payment of 100.00. Succeeded {$succeeded}, rejected {$rejected}, stored ".$allocated->amount().'.', false);
    }

    $starts = [];
    $ends = [];

    foreach ($workers as $worker) {
        $starts[] = (float) file_get_contents($worker['start']);
        $ends[] = (float) file_get_contents($worker['end']);
    }

    if (max($starts) >= min($ends)) {
        paymentLockStop($workers, 'The two payments did not overlap.', false);
    }

    paymentLockClean($directory);
    fwrite(STDOUT, "One payment allocated 100.00. The overlapping payment was rejected and stored nothing.\n");
}

/**
 * @param  list<string>  $argv
 */
function paymentLockWorker(array $argv): void
{
    paymentLockSetEnv('ASL_PAYMENT_ALLOCATION_DELAY', '1');
    $journalId = (int) paymentLockArgument($argv, 'journal');
    $partyId = (int) paymentLockArgument($argv, 'party');
    $accountId = (int) paymentLockArgument($argv, 'account');
    $userId = (int) paymentLockArgument($argv, 'user');
    $date = paymentLockArgument($argv, 'date');
    $ready = paymentLockArgument($argv, 'ready');
    $go = paymentLockArgument($argv, 'go');
    $start = paymentLockArgument($argv, 'start');
    $end = paymentLockArgument($argv, 'end');
    $result = paymentLockArgument($argv, 'result');
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

    file_put_contents($start, (string) microtime(true));

    try {
        $payment = app(PaymentWriter::class)->record([
            'party_id' => $partyId,
            'account_id' => $accountId,
            'payment_date' => $date,
            'amount' => '100.00',
            'mode' => 'cash',
            'reference' => null,
            'cash_flow_category' => 'operating',
            'allocations' => [[
                'journal_id' => $journalId,
                'amount' => '100.00',
            ]],
        ], $user);
        file_put_contents($end, (string) microtime(true));
        file_put_contents($result, $payment->number);
    } catch (AccountingException $exception) {
        file_put_contents($end, (string) microtime(true));
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}
 */
function paymentLockSpawn(string $directory, string $name, int $journalId, int $partyId, int $accountId, int $userId, string $date, string $go): array
{
    $ready = $directory.'/'.$name.'.ready';
    $start = $directory.'/'.$name.'.start';
    $end = $directory.'/'.$name.'.end';
    $result = $directory.'/'.$name.'.result';
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--journal='.$journalId,
        '--party='.$partyId,
        '--account='.$accountId,
        '--user='.$userId,
        '--date='.$date,
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
    ], $pipes, null, paymentLockChildEnvironment());

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
function paymentLockDrain(array &$workers): void
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
function paymentLockStop(array $workers, string $message, bool $terminate = true): never
{
    paymentLockDrain($workers);

    foreach ($workers as $worker) {
        if ($terminate) {
            proc_terminate($worker['process']);
        }

        fwrite(STDERR, "Worker {$worker['name']} output:\n{$worker['output']}\n{$worker['error']}\n");
    }

    fwrite(STDERR, $message."\n");
    exit(1);
}

function paymentLockClean(string $directory): void
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
function paymentLockArgument(array $argv, string $name): string
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

function paymentLockForceDatabase(string $database): void
{
    paymentLockSetEnv('DB_CONNECTION', 'mysql');
    paymentLockSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    putenv('ASL_PAYMENT_ALLOCATION_DELAY');
    unset($_ENV['ASL_PAYMENT_ALLOCATION_DELAY'], $_SERVER['ASL_PAYMENT_ALLOCATION_DELAY']);
}

function paymentLockSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function paymentLockBootstrap(string $database): void
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
function paymentLockChildEnvironment(): array
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
    $environment['ASL_PAYMENT_ALLOCATION_DELAY'] = '1';
    unset($environment['DB_URL']);

    return $environment;
}
