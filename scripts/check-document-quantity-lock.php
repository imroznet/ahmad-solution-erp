<?php

declare(strict_types=1);

use App\Documents\ChallanWorkflow;
use App\Documents\ChallanWriter;
use App\Documents\DocumentBalance;
use App\Documents\DocumentRuleException;
use App\Documents\InvoiceWorkflow;
use App\Documents\InvoiceWriter;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use App\Quotations\QuotationWorkflow;
use App\Quotations\QuotationWriter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Proves two issue transactions cannot pass an accepted quantity or amount.
 *
 * The workers issue two drafts of the same quotation. Each draft may hold the
 * full remainder. The quotation row is locked before the quantity check, and
 * the number is allocated only after that check. The losing issue stays a
 * draft and does not consume a number.
 *
 * Point DB_DATABASE at an empty asl_document_quantity_* database. This script
 * refuses asl_erp, staging, and production.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database)
    || $database === 'asl_erp'
    || preg_match('/staging|production/i', $database) === 1
    || preg_match('/^asl_document_quantity_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_document_quantity_* database. Refusing asl_erp, staging, and production.\n");
    exit(1);
}

quantityLockForceDatabase($database);
quantityLockBootstrap($database);

if (in_array('--worker', $argv, true)) {
    quantityLockWorker($argv);
    exit(0);
}

quantityLockParent();
exit(0);

function quantityLockParent(): void
{
    $migrated = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrated !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }

    $sales = User::query()->create([
        'name' => 'Quantity sales',
        'email' => 'quantity-sales@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $manager = User::query()->create([
        'name' => 'Quantity manager',
        'email' => 'quantity-manager@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $directory = sys_get_temp_dir().'/asl-document-quantity-'.bin2hex(random_bytes(4));

    if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
    $challanQuotation = quantityLockAccept($sales, $manager, 'Challan Customer Ltd.', 'challan-quantity@example.com', '01711000031');
    $challanLine = quantityLockLine($challanQuotation);
    $firstChallan = quantityLockChallan($sales, $challanQuotation, '2');
    $secondChallan = quantityLockChallan($sales, $challanQuotation, '2');
    $challanRace = quantityLockRace($directory, 'challan', [
        'a' => (int) $firstChallan->getKey(),
        'b' => (int) $secondChallan->getKey(),
    ], (int) $sales->getKey());
    quantityLockAssertChallan($challanRace, (int) $challanLine->getKey(), $year);

    $invoiceQuotation = quantityLockAccept($sales, $manager, 'Invoice Customer Ltd.', 'invoice-quantity@example.com', '01711000032');
    $invoiceLine = quantityLockLine($invoiceQuotation);
    $delivered = quantityLockIssue($sales, quantityLockChallan($sales, $invoiceQuotation, '2'));
    $firstInvoice = quantityLockInvoice($sales, $invoiceQuotation, $delivered, '2');
    $secondInvoice = quantityLockInvoice($sales, $invoiceQuotation, $delivered, '2');
    $invoiceRace = quantityLockRace($directory, 'invoice', [
        'a' => (int) $firstInvoice->getKey(),
        'b' => (int) $secondInvoice->getKey(),
    ], (int) $sales->getKey());
    quantityLockAssertInvoice($invoiceRace, (int) $invoiceLine->getKey(), (int) $invoiceQuotation->getKey(), $year);
    quantityLockClean($directory);
}

function quantityLockAccept(User $sales, User $manager, string $name, string $email, string $phone): Quotation
{
    $today = now()->timezone('Asia/Dhaka');
    $party = Party::query()->create([
        'legal_name' => $name,
        'party_kind' => 'company',
        'status' => 'active',
        'currency_code' => 'BDT',
        'email' => $email,
        'phone' => $phone,
    ]);
    $party->roles()->create(['role' => 'customer']);
    $quotation = app(QuotationWriter::class)->create($sales, [
        'party_id' => $party->getKey(),
        'quotation_date' => $today->toDateString(),
        'valid_until' => $today->copy()->addMonth()->toDateString(),
        'lines' => [[
            'description' => 'Concurrent quantity',
            'unit' => 'job',
            'quantity' => '2',
            'unit_price' => '10.00',
        ]],
    ]);
    $workflow = app(QuotationWorkflow::class);
    $workflow->submit($quotation, $sales);
    $workflow->approve($quotation, $manager);
    $workflow->send($quotation, $sales, 'Nadia Rahman', null);
    $workflow->respond($quotation, $sales, 'accepted', 'internal', 'Nadia Rahman', null, null, null, null, null);

    return $quotation->fresh() ?? $quotation;
}

function quantityLockLine(Quotation $quotation): QuotationLine
{
    $quotation->load('currentRevision.lines');
    $line = $quotation->currentRevision?->lines->first();

    if (! $line instanceof QuotationLine) {
        fwrite(STDERR, "The accepted quotation has no line.\n");
        exit(1);
    }

    return $line;
}

function quantityLockChallan(User $actor, Quotation $quotation, string $quantity): DeliveryChallan
{
    $line = quantityLockLine($quotation);

    return app(ChallanWriter::class)->create($actor, [
        'quotation_id' => $quotation->getKey(),
        'challan_date' => now()->timezone('Asia/Dhaka')->toDateString(),
        'lines' => [[
            'quotation_line_id' => $line->getKey(),
            'quantity' => $quantity,
        ]],
    ]);
}

function quantityLockIssue(User $actor, DeliveryChallan $challan): DeliveryChallan
{
    return app(ChallanWorkflow::class)->issue($challan, $actor);
}

function quantityLockInvoice(User $actor, Quotation $quotation, DeliveryChallan $challan, string $quantity): CommercialInvoice
{
    $challan->load('lines');
    $line = $challan->lines->first();

    if ($line === null) {
        fwrite(STDERR, "The issued challan has no current line.\n");
        exit(1);
    }

    return app(InvoiceWriter::class)->create($actor, [
        'quotation_id' => $quotation->getKey(),
        'invoice_date' => now()->timezone('Asia/Dhaka')->toDateString(),
        'lines' => [[
            'delivery_challan_line_id' => $line->getKey(),
            'quantity' => $quantity,
        ]],
    ]);
}

/**
 * @param  array{issued: string, draft: string, overlap: string}  $race
 */
function quantityLockAssertChallan(array $race, int $quotationLineId, int $year): void
{
    $expected = sprintf('DC-%d-0001', $year);
    $delivered = app(DocumentBalance::class)->deliveredQuantity($quotationLineId, null);
    $numbered = DeliveryChallan::query()->whereNotNull('number')->count();
    $drafts = DeliveryChallan::query()->whereNull('number')->where('status', 'draft')->count();
    $last = DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number');

    if ($race['issued'] !== $expected || $numbered !== 1 || $drafts !== 1 || (int) $last !== 1 || $delivered !== '2.0000') {
        fwrite(STDERR, "Challan cap failed. Issued [{$race['issued']}], draft [{$race['draft']}], delivered [{$delivered}], numbered {$numbered}, drafts {$drafts}, last_number {$last}.\n");
        exit(1);
    }

    fwrite(STDOUT, "Challan race: one issue returned {$race['issued']}. The other stayed draft with no number ({$race['draft']}). {$race['overlap']} Delivered quantity {$delivered} of accepted 2.0000. Sequence last_number {$last}. The failed issue consumed no number.\n");
}

/**
 * @param  array{issued: string, draft: string, overlap: string}  $race
 */
function quantityLockAssertInvoice(array $race, int $quotationLineId, int $quotationId, int $year): void
{
    $expected = sprintf('CI-%d-0001', $year);
    $balance = app(DocumentBalance::class);
    $quantity = $balance->invoicedQuantityForQuotationLine($quotationLineId, null);
    $amount = $balance->invoicedAmountForQuotationLine($quotationLineId, null);
    $total = $balance->issuedInvoiceTotal($quotationId, null);
    $numbered = CommercialInvoice::query()->whereNotNull('number')->count();
    $drafts = CommercialInvoice::query()->whereNull('number')->where('status', 'draft')->count();
    $last = DB::table('commercial_invoice_number_sequences')->where('year', $year)->value('last_number');
    $storedTotal = DB::table('commercial_invoices')->where('number', $expected)->value('total');

    if ($race['issued'] !== $expected
        || $numbered !== 1
        || $drafts !== 1
        || (int) $last !== 1
        || $quantity !== '2.0000'
        || $amount !== '20.00'
        || $total !== '20.00'
        || (string) $storedTotal !== '20.00') {
        fwrite(STDERR, "Invoice cap failed. Issued [{$race['issued']}], draft [{$race['draft']}], quantity [{$quantity}], amount [{$amount}], total [{$total}], stored [{$storedTotal}], numbered {$numbered}, drafts {$drafts}, last_number {$last}.\n");
        exit(1);
    }

    fwrite(STDOUT, "Invoice race: one issue returned {$race['issued']} for {$storedTotal}. The other stayed draft with no number ({$race['draft']}). {$race['overlap']} Invoiced quantity {$quantity} of delivered 2.0000. Invoiced amount {$amount} of accepted 20.00. Issued invoice total {$total}. Sequence last_number {$last}. The failed issue consumed no number.\n");
}

/**
 * @param  array<string, int>  $documents
 * @return array{issued: string, draft: string, overlap: string}
 */
function quantityLockRace(string $directory, string $series, array $documents, int $userId): array
{
    $go = $directory.'/go-'.$series;
    $workers = [];

    foreach ($documents as $name => $documentId) {
        $workers[] = quantityLockSpawn($directory, $series, $name, $documentId, $userId, $go);
    }

    $readyDeadline = microtime(true) + 30;

    while (microtime(true) < $readyDeadline) {
        quantityLockDrain($workers);
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
            quantityLockStop($workers, 'A worker did not become ready.');
        }
    }

    if (is_file($go)) {
        unlink($go);
    }

    file_put_contents($go, 'go');
    $finishedDeadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $finishedDeadline) {
        quantityLockDrain($workers);
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
        quantityLockStop($workers, 'A worker did not finish.');
    }

    foreach ($workers as $index => $worker) {
        $workers[$index]['code'] = proc_close($worker['process']);
    }

    $issued = '';
    $draft = '';
    $starts = [];
    $ends = [];

    foreach ($workers as $worker) {
        if ($worker['code'] !== 0) {
            fwrite(STDERR, "Worker {$worker['name']} exited {$worker['code']}.\n{$worker['output']}\n{$worker['error']}\n");
            exit(1);
        }

        $result = is_file($worker['result']) ? trim((string) file_get_contents($worker['result'])) : '';
        $start = (float) file_get_contents($worker['start']);
        $end = (float) file_get_contents($worker['end']);
        $starts[] = $start;
        $ends[] = $end;

        if ($start <= 0.0 || $end <= $start) {
            fwrite(STDERR, "Worker {$worker['name']} did not record an overlapping issue.\n");
            exit(1);
        }

        if (str_starts_with($result, 'issued|')) {
            $issued = substr($result, strlen('issued|'));
        } elseif (str_starts_with($result, 'draft|')) {
            $draft = substr($result, strlen('draft|'));
        }
    }

    if ($issued === '' || $draft === '' || ! ($starts[0] < $ends[1] && $starts[1] < $ends[0])) {
        fwrite(STDERR, "The two {$series} issues did not overlap with exactly one success. Issued [{$issued}], draft [{$draft}].\n");
        exit(1);
    }

    return [
        'issued' => $issued,
        'draft' => $draft,
        'overlap' => sprintf('Overlap confirmed (%.6f–%.6f and %.6f–%.6f).', $starts[0], $ends[0], $starts[1], $ends[1]),
    ];
}

/**
 * @param  list<string>  $argv
 */
function quantityLockWorker(array $argv): void
{
    quantityLockSetEnv('ASL_DOCUMENT_ALLOCATION_DELAY', '1');
    $series = quantityLockArgument($argv, 'series');
    $documentId = (int) quantityLockArgument($argv, 'document');
    $userId = (int) quantityLockArgument($argv, 'user');
    $ready = quantityLockArgument($argv, 'ready');
    $go = quantityLockArgument($argv, 'go');
    $start = quantityLockArgument($argv, 'start');
    $end = quantityLockArgument($argv, 'end');
    $result = quantityLockArgument($argv, 'result');
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
        if ($series === 'challan') {
            $challan = DeliveryChallan::query()->find($documentId);

            if (! $challan instanceof DeliveryChallan) {
                throw new RuntimeException("Challan {$documentId} was not found.");
            }

            $issued = app(ChallanWorkflow::class)->issue($challan, $user);
            $outcome = 'issued|'.$issued->stringAttribute('number');
        } elseif ($series === 'invoice') {
            $invoice = CommercialInvoice::query()->find($documentId);

            if (! $invoice instanceof CommercialInvoice) {
                throw new RuntimeException("Invoice {$documentId} was not found.");
            }

            $issued = app(InvoiceWorkflow::class)->issue($invoice, $user);
            $outcome = 'issued|'.$issued->stringAttribute('number');
        } else {
            throw new RuntimeException('Choose challan or invoice.');
        }
    } catch (DocumentRuleException $exception) {
        $outcome = 'draft|'.$exception->getMessage();
    } catch (Throwable $exception) {
        file_put_contents($end, (string) microtime(true));
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
        exit(1);
    }

    file_put_contents($end, (string) microtime(true));
    file_put_contents($result, $outcome);
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}
 */
function quantityLockSpawn(string $directory, string $series, string $name, int $documentId, int $userId, string $go): array
{
    $prefix = $directory.'/'.$series.'-'.$name;
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--series='.$series,
        '--document='.$documentId,
        '--user='.$userId,
        '--ready='.$prefix.'.ready',
        '--go='.$go,
        '--start='.$prefix.'.start',
        '--end='.$prefix.'.end',
        '--result='.$prefix.'.result',
    ];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, quantityLockChildEnvironment());

    if (! is_resource($process) || ! isset($pipes[1], $pipes[2]) || ! is_resource($pipes[1]) || ! is_resource($pipes[2])) {
        fwrite(STDERR, "Could not start worker {$name}.\n");
        exit(1);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return [
        'name' => $series.'-'.$name,
        'process' => $process,
        'stdout' => $pipes[1],
        'stderr' => $pipes[2],
        'ready' => $prefix.'.ready',
        'start' => $prefix.'.start',
        'end' => $prefix.'.end',
        'result' => $prefix.'.result',
        'code' => 1,
        'output' => '',
        'error' => '',
    ];
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function quantityLockDrain(array &$workers): void
{
    foreach ($workers as $index => $worker) {
        $output = stream_get_contents($worker['stdout']);
        $error = stream_get_contents($worker['stderr']);

        if (is_string($output)) {
            $workers[$index]['output'] .= $output;
        }

        if (is_string($error)) {
            $workers[$index]['error'] .= $error;
        }
    }
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function quantityLockStop(array $workers, string $message): void
{
    fwrite(STDERR, $message."\n");

    foreach ($workers as $worker) {
        $status = proc_get_status($worker['process']);

        if ($status['running']) {
            proc_terminate($worker['process']);
        }

        quantityLockDrain($workers);
        fwrite(STDERR, "{$worker['name']}: {$worker['output']} {$worker['error']}\n");
    }

    exit(1);
}

function quantityLockClean(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($directory);
}

/**
 * @param  list<string>  $argv
 */
function quantityLockArgument(array $argv, string $name): string
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

function quantityLockForceDatabase(string $database): void
{
    quantityLockSetEnv('DB_CONNECTION', 'mysql');
    quantityLockSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    putenv('ASL_DOCUMENT_ALLOCATION_DELAY');
    unset($_ENV['ASL_DOCUMENT_ALLOCATION_DELAY'], $_SERVER['ASL_DOCUMENT_ALLOCATION_DELAY']);
    putenv('ASL_QUOTATION_ALLOCATION_DELAY');
    unset($_ENV['ASL_QUOTATION_ALLOCATION_DELAY'], $_SERVER['ASL_QUOTATION_ALLOCATION_DELAY']);
}

function quantityLockSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function quantityLockBootstrap(string $database): void
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
function quantityLockChildEnvironment(): array
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
    $environment['ASL_DOCUMENT_ALLOCATION_DELAY'] = '1';
    unset($environment['DB_URL'], $environment['ASL_QUOTATION_ALLOCATION_DELAY']);

    return $environment;
}
