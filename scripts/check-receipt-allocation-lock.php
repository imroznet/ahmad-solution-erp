<?php

declare(strict_types=1);

use App\Accounting\AccountingException;
use App\Accounting\FinancialYearCalendar;
use App\Accounting\ReceiptWriter;
use App\Documents\ChallanWorkflow;
use App\Documents\ChallanWriter;
use App\Documents\InvoiceWorkflow;
use App\Documents\InvoiceWriter;
use App\Models\Account;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Quotations\QuotationWorkflow;
use App\Quotations\QuotationWriter;
use App\Support\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Proves two receipts cannot allocate the same issued invoice past its outstanding.
 *
 * Each worker starts a receipt for the full invoice. The first locks the invoice
 * and pauses. The second waits, then reads the allocation with a current read.
 * One receipt is recorded. The other is rejected and posts nothing.
 *
 * Point DB_DATABASE at an empty asl_receipt_* database. This script refuses asl_erp.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_receipt_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_receipt_* database. Refusing asl_erp.\n");
    exit(1);
}

receiptLockForceDatabase($database);
receiptLockBootstrap($database);

if (in_array('--worker', $argv, true)) {
    receiptLockWorker($argv);
    exit(0);
}

receiptLockParent();
exit(0);

function receiptLockParent(): void
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
    $sales = User::query()->create([
        'name' => 'Receipt sales',
        'email' => 'receipt-sales@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $manager = User::query()->create([
        'name' => 'Receipt manager',
        'email' => 'receipt-manager@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $party = Party::query()->create([
        'legal_name' => 'Receipt Customer Ltd.',
        'party_kind' => 'company',
        'status' => 'active',
        'currency_code' => 'BDT',
        'email' => 'receipt-customer@example.com',
        'phone' => '01711000031',
    ]);
    $party->roles()->create(['role' => 'customer']);
    $quotation = app(QuotationWriter::class)->create($sales, [
        'party_id' => $party->getKey(),
        'quotation_date' => $today->toDateString(),
        'valid_until' => $today->copy()->addMonth()->toDateString(),
        'lines' => [[
            'description' => 'Concurrent receipt',
            'unit' => 'job',
            'quantity' => '1',
            'unit_price' => '100.00',
        ]],
    ]);
    $workflow = app(QuotationWorkflow::class);
    $workflow->submit($quotation, $sales);
    $workflow->approve($quotation, $manager);
    $workflow->send($quotation, $sales, 'Nadia Rahman', null);
    $workflow->respond($quotation, $sales, 'accepted', 'internal', 'Nadia Rahman', null, null, null, null, null);
    $quotation = $quotation->fresh() ?? $quotation;
    $quotation->load('currentRevision.lines');
    $line = $quotation->currentRevision?->lines->first();

    if (! $line instanceof QuotationLine || ! $quotation instanceof Quotation) {
        fwrite(STDERR, "The accepted quotation has no line.\n");
        exit(1);
    }

    $challan = app(ChallanWriter::class)->create($sales, [
        'quotation_id' => $quotation->getKey(),
        'challan_date' => $today->toDateString(),
        'lines' => [[
            'quotation_line_id' => $line->getKey(),
            'quantity' => '1',
        ]],
    ]);
    $challan = app(ChallanWorkflow::class)->issue($challan, $sales);
    $challan->load('lines');
    $challanLine = $challan->lines->first();

    if ($challanLine === null || ! $challan instanceof DeliveryChallan) {
        fwrite(STDERR, "The issued challan has no line.\n");
        exit(1);
    }

    $invoice = app(InvoiceWriter::class)->create($sales, [
        'quotation_id' => $quotation->getKey(),
        'invoice_date' => $today->toDateString(),
        'lines' => [[
            'delivery_challan_line_id' => $challanLine->getKey(),
            'quantity' => '1',
        ]],
    ]);
    $invoice = app(InvoiceWorkflow::class)->issue($invoice, $sales);
    $cash = Account::query()->where('system_key', 'cash')->first();

    if (! $invoice instanceof CommercialInvoice || ! $cash instanceof Account) {
        fwrite(STDERR, "The invoice or cash account was not available.\n");
        exit(1);
    }

    $directory = sys_get_temp_dir().'/asl-receipt-lock-'.bin2hex(random_bytes(4));

    if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $go = $directory.'/go';
    $workers = [
        receiptLockSpawn($directory, 'a', (int) $invoice->getKey(), (int) $party->getKey(), (int) $cash->getKey(), (int) $sales->getKey(), $today->toDateString(), $go),
        receiptLockSpawn($directory, 'b', (int) $invoice->getKey(), (int) $party->getKey(), (int) $cash->getKey(), (int) $sales->getKey(), $today->toDateString(), $go),
    ];
    $readyDeadline = microtime(true) + 30;

    while (microtime(true) < $readyDeadline) {
        receiptLockDrain($workers);
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
            receiptLockStop($workers, 'A worker did not become ready.');
        }
    }

    file_put_contents($go, 'go');
    $deadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $deadline) {
        receiptLockDrain($workers);
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
        receiptLockStop($workers, 'A worker did not finish.');
    }

    receiptLockDrain($workers);

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

        if ($worker['code'] !== 0 && str_contains($worker['error'], 'more than the invoice outstanding')) {
            $rejected++;

            continue;
        }

        receiptLockStop($workers, 'A worker ended in an unexpected state.', false);
    }

    $allocated = Money::zero();

    foreach (ReceiptAllocation::query()->get() as $allocation) {
        $allocated = $allocated->plus(Money::of($allocation->decimalAttribute('amount')));
    }

    if ($succeeded !== 1 || $rejected !== 1
        || Receipt::query()->count() !== 1
        || $allocated->amount() !== '100.00') {
        receiptLockStop($workers, "Expected one recorded receipt of 100.00. Succeeded {$succeeded}, rejected {$rejected}, stored ".$allocated->amount().'.', false);
    }

    $starts = [];
    $ends = [];

    foreach ($workers as $worker) {
        $starts[] = (float) file_get_contents($worker['start']);
        $ends[] = (float) file_get_contents($worker['end']);
    }

    if (max($starts) >= min($ends)) {
        receiptLockStop($workers, 'The two receipts did not overlap.', false);
    }

    receiptLockClean($directory);
    fwrite(STDOUT, "One receipt allocated 100.00. The overlapping receipt was rejected and stored nothing.\n");
}

/**
 * @param  list<string>  $argv
 */
function receiptLockWorker(array $argv): void
{
    receiptLockSetEnv('ASL_RECEIPT_ALLOCATION_DELAY', '1');
    $invoiceId = (int) receiptLockArgument($argv, 'invoice');
    $partyId = (int) receiptLockArgument($argv, 'party');
    $accountId = (int) receiptLockArgument($argv, 'account');
    $userId = (int) receiptLockArgument($argv, 'user');
    $date = receiptLockArgument($argv, 'date');
    $ready = receiptLockArgument($argv, 'ready');
    $go = receiptLockArgument($argv, 'go');
    $start = receiptLockArgument($argv, 'start');
    $end = receiptLockArgument($argv, 'end');
    $result = receiptLockArgument($argv, 'result');
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
        $receipt = app(ReceiptWriter::class)->record([
            'party_id' => $partyId,
            'account_id' => $accountId,
            'receipt_date' => $date,
            'amount_received' => '100.00',
            'vds_deducted' => '0.00',
            'ait_deducted' => '0.00',
            'mode' => 'cash',
            'reference' => null,
            'allocations' => [[
                'commercial_invoice_id' => $invoiceId,
                'amount' => '100.00',
            ]],
        ], $user);
        file_put_contents($end, (string) microtime(true));
        file_put_contents($result, $receipt->number);
    } catch (AccountingException $exception) {
        file_put_contents($end, (string) microtime(true));
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}
 */
function receiptLockSpawn(string $directory, string $name, int $invoiceId, int $partyId, int $accountId, int $userId, string $date, string $go): array
{
    $ready = $directory.'/'.$name.'.ready';
    $start = $directory.'/'.$name.'.start';
    $end = $directory.'/'.$name.'.end';
    $result = $directory.'/'.$name.'.result';
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--invoice='.$invoiceId,
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
    ], $pipes, null, receiptLockChildEnvironment());

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
function receiptLockDrain(array &$workers): void
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
function receiptLockStop(array $workers, string $message, bool $terminate = true): never
{
    receiptLockDrain($workers);

    foreach ($workers as $worker) {
        if ($terminate) {
            proc_terminate($worker['process']);
        }

        fwrite(STDERR, "Worker {$worker['name']} output:\n{$worker['output']}\n{$worker['error']}\n");
    }

    fwrite(STDERR, $message."\n");
    exit(1);
}

function receiptLockClean(string $directory): void
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
function receiptLockArgument(array $argv, string $name): string
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

function receiptLockForceDatabase(string $database): void
{
    receiptLockSetEnv('DB_CONNECTION', 'mysql');
    receiptLockSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    putenv('ASL_RECEIPT_ALLOCATION_DELAY');
    unset($_ENV['ASL_RECEIPT_ALLOCATION_DELAY'], $_SERVER['ASL_RECEIPT_ALLOCATION_DELAY']);
}

function receiptLockSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function receiptLockBootstrap(string $database): void
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
function receiptLockChildEnvironment(): array
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
    $environment['ASL_RECEIPT_ALLOCATION_DELAY'] = '1';
    unset($environment['DB_URL']);

    return $environment;
}
