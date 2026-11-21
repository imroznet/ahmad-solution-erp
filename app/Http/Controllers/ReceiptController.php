<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\ReceiptStatus;
use App\Accounting\ReceiptWriter;
use App\Documents\DocumentStatus;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SaveReceiptRequest;
use App\Models\Account;
use App\Models\CommercialInvoice;
use App\Models\Party;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(): View
    {
        $actor = $this->actor(request()->user());

        return view('accounting.receipts.index', [
            'receipts' => Receipt::query()->with('party')->orderByDesc('receipt_date')->orderByDesc('id')->paginate(20),
            'canCreate' => $actor->hasPermission('receipts.create'),
        ]);
    }

    public function create(): View
    {
        return view('accounting.receipts.form', $this->formData());
    }

    public function store(SaveReceiptRequest $request, ReceiptWriter $writer): RedirectResponse
    {
        try {
            $receipt = $writer->record($request->validated(), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'receipt');
        }

        return redirect()->route('receipts.show', $receipt);
    }

    public function show(Receipt $receipt): View
    {
        $actor = $this->actor(request()->user());
        $receipt->load(['party', 'account', 'allocations.invoice', 'creator', 'voidedBy']);

        return view('accounting.receipts.show', [
            'receipt' => $receipt,
            'canVoid' => $receipt->status === ReceiptStatus::Recorded && $actor->hasPermission('receipts.create'),
        ]);
    }

    public function void(Request $request, Receipt $receipt, ReceiptWriter $writer): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $reason = $validated['void_reason'];

        try {
            $writer->void($receipt, $this->actor($request->user()), is_string($reason) ? $reason : '');
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'receipt');
        }

        return redirect()->route('receipts.show', $receipt);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $partyId = old('party_id');
        $invoices = CommercialInvoice::query()
            ->where('status', DocumentStatus::ISSUED)
            ->with('quotation.party')
            ->orderBy('invoice_date')
            ->orderBy('id');

        if (is_numeric($partyId)) {
            $invoices->whereHas('quotation', fn ($query) => $query->where('party_id', (int) $partyId));
        }

        return [
            'parties' => Party::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('role', 'customer'))->orderBy('legal_name')->get(),
            'accounts' => Account::query()->forSettlement()->get(),
            'invoices' => $invoices->get(),
            'modes' => [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank transfer',
                'cheque' => 'Cheque',
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
            ],
            'today' => now()->timezone('Asia/Dhaka')->toDateString(),
        ];
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
