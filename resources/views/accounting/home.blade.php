<x-layouts.app title="Accounting">
    <h1 class="text-xl font-semibold">Accounting</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Chart of accounts, opening balances, journals, customer receipts, and receivables. Amounts are BDT. An issued commercial invoice posts revenue. VAT posts when a Mushak 6.3 is issued. Supplier payments, profit and loss, the balance sheet, cash flow, and period close are not in this release. A supplier bill is a journal to an expense account. The ledger does not accrue interest. A Shariah-compliant profit or markup is recorded only when the bank or the owner instructs, and a treatment a government rule requires is entered the same way.</p>
    <ul class="mt-6 max-w-md space-y-2 text-sm">
        @if ($canChart)
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('accounts.index') }}">Chart of accounts</a></li>
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('cash-accounts.index') }}">Bank and MFS accounts</a></li>
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('financial-years.index') }}">Financial years</a></li>
        @endif
        @if ($canJournals)
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('journals.index') }}">Journals</a></li>
        @endif
        <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('receipts.index') }}">Receipts</a></li>
        @if ($canOpening)
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('opening-balances.edit') }}">Opening balances</a></li>
        @endif
        @if ($canReports)
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('reports.trial-balance') }}">Trial balance</a></li>
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('reports.general-ledger') }}">General ledger</a></li>
            <li><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('reports.receivables-aging') }}">Receivables aging</a></li>
        @endif
    </ul>
</x-layouts.app>
