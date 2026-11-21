<?php

namespace App\Http\Controllers;

use App\Accounting\AccountingException;
use App\Accounting\CashFlowCategory;
use App\Accounting\JournalSource;
use App\Accounting\JournalStatus;
use App\Accounting\PaymentStatus;
use App\Accounting\PaymentWriter;
use App\Http\Controllers\Concerns\RedirectsAccountingErrors;
use App\Http\Requests\SavePaymentRequest;
use App\Models\Account;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use RedirectsAccountingErrors;

    public function index(): View
    {
        $actor = $this->actor(request()->user());

        return view('accounting.payments.index', [
            'payments' => Payment::query()->with('party')->orderByDesc('payment_date')->orderByDesc('id')->paginate(20),
            'canCreate' => $actor->hasPermission('payments.create'),
        ]);
    }

    public function create(): View
    {
        return view('accounting.payments.form', $this->formData());
    }

    public function store(SavePaymentRequest $request, PaymentWriter $writer): RedirectResponse
    {
        try {
            $payment = $writer->record($request->validated(), $this->actor($request->user()));
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'payment');
        }

        return redirect()->route('payments.show', $payment);
    }

    public function show(Payment $payment): View
    {
        $actor = $this->actor(request()->user());
        $payment->load(['party', 'account', 'allocations.journal', 'creator', 'voidedBy']);

        return view('accounting.payments.show', [
            'payment' => $payment,
            'canVoid' => $payment->status === PaymentStatus::Recorded && $actor->hasPermission('payments.create'),
        ]);
    }

    public function void(Request $request, Payment $payment, PaymentWriter $writer): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $reason = $validated['void_reason'];

        try {
            $writer->void($payment, $this->actor($request->user()), is_string($reason) ? $reason : '');
        } catch (AccountingException $exception) {
            return $this->accountingError($exception, 'payment');
        }

        return redirect()->route('payments.show', $payment);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $partyId = old('party_id');
        $payableId = Account::query()->where('system_key', 'accounts_payable')->value('id');
        $journals = Journal::query()
            ->where('status', JournalStatus::Posted->value)
            ->where('source', JournalSource::Manual->value)
            ->whereHas('lines', function ($query) use ($payableId, $partyId): void {
                $query->where('account_id', $payableId)->whereNotNull('party_id');

                if (is_numeric($partyId)) {
                    $query->where('party_id', (int) $partyId);
                }
            })
            ->whereDoesntHave('reversal')
            ->with('lines.party')
            ->orderBy('journal_date')
            ->orderBy('id');

        return [
            'parties' => Party::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('role', 'supplier'))->orderBy('legal_name')->get(),
            'accounts' => Account::query()->forSettlement()->get(),
            'journals' => $journals->get(),
            'modes' => [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank transfer',
                'cheque' => 'Cheque',
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
            ],
            'categories' => CashFlowCategory::ordered(),
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
