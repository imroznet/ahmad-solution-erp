<x-layouts.app :title="$quotation->number">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $quotation->number }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $revision->party_legal_name }} · {{ $quotation->statusLabel() }} · Revision {{ $revision->revision_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('quotations.pdf', [$quotation, $revision->revision_number]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">PDF</a>
            @if ($canCreateChallan ?? false)
                <a href="{{ route('challans.create', ['quotation' => $quotation->id]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Delivery challan</a>
            @endif
            @if ($canCreateInvoice ?? false)
                <a href="{{ route('invoices.create', ['quotation' => $quotation->id]) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Commercial invoice</a>
            @endif
            @if ($isCurrent && $canUpdate)
                <a href="{{ route('quotations.edit', $quotation) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Edit draft</a>
            @elseif ($isCurrent && $canRevise)
                <a href="{{ route('quotations.edit', $quotation) }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">New revision</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($shareUrl)
        <div class="mt-4 rounded border border-line bg-white p-3 text-sm" role="status">
            <p class="font-medium">Copy this link now. It is not stored and will not be shown again.</p>
            <input readonly value="{{ $shareUrl }}" class="mt-2 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
    @endif

    @if (! $isCurrent)
        <p class="mt-4 rounded border border-line bg-white px-3 py-2 text-sm">You are viewing revision {{ $revision->revision_number }}. The current revision is {{ $quotation->currentRevision?->revision_number }}.</p>
    @endif

    <nav class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="Revisions">
        @foreach ($quotation->revisions as $historical)
            <a href="{{ route('quotations.revisions.show', [$quotation, $historical->revision_number]) }}" @class(['rounded border px-2 py-1', 'border-brand text-brand' => (int) $historical->id === (int) $revision->id, 'border-line' => (int) $historical->id !== (int) $revision->id])>
                R{{ $historical->revision_number }} · {{ $historical->statusLabel() }}
            </a>
        @endforeach
    </nav>

    <div class="mt-4">
        @include('quotations.partials.document', ['quotation' => $quotation, 'revision' => $revision])
    </div>

    @if ($revision->internal_notes)
        <section class="mt-4 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">Internal notes</h2>
            <p class="mt-1 whitespace-pre-wrap">{{ $revision->internal_notes }}</p>
        </section>
    @endif

    @if ($isCurrent)
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            @if ($canSubmit)
                <form method="POST" action="{{ route('quotations.submit', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Submit for approval</h2>
                    <label for="submit-remarks" class="mt-3 block text-sm">Remarks <span class="text-muted">(optional)</span></label>
                    <textarea id="submit-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Submit</button>
                </form>
            @endif
            @if ($canApprove)
                <form method="POST" action="{{ route('quotations.approve', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Approve</h2>
                    <p class="mt-1 text-sm text-muted">You cannot approve a revision you created or submitted.</p>
                    <label for="approve-remarks" class="mt-3 block text-sm">Remarks <span class="text-muted">(optional)</span></label>
                    <textarea id="approve-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Approve</button>
                </form>
            @endif
            @if ($canReturn)
                <form method="POST" action="{{ route('quotations.return', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Return to draft</h2>
                    <label for="return-remarks" class="mt-3 block text-sm">Reason</label>
                    <textarea id="return-remarks" name="remarks" required rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Return</button>
                </form>
            @endif
            @if ($canSend)
                <form method="POST" action="{{ route('quotations.send', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Record send</h2>
                    <p class="mt-1 text-sm text-muted">Email is not sent. This records that the quotation was issued to the customer.</p>
                    <label for="recipient_label" class="mt-3 block text-sm">Recipient <span class="text-muted">(optional)</span></label>
                    <input id="recipient_label" name="recipient_label" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <label for="send-remarks" class="mt-3 block text-sm">Remarks <span class="text-muted">(optional)</span></label>
                    <textarea id="send-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Mark as sent</button>
                </form>
            @endif
            @if ($canShare)
                <form method="POST" action="{{ route('quotations.shares.store', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Customer link</h2>
                    <p class="mt-1 text-sm text-muted">The raw token is shown once. Only its hash is stored.</p>
                    <label for="days" class="mt-3 block text-sm">Expires in days</label>
                    <input id="days" name="days" type="number" min="1" max="{{ $shareMaxDays }}" value="{{ $shareDays }}" class="mt-1 w-32 rounded border border-line px-3 py-2 text-sm">
                    <button type="submit" class="mt-3 rounded bg-brand px-3 py-2 text-sm font-medium text-white">Create link</button>
                </form>
            @endif
            @if ($canRespond)
                <form method="POST" action="{{ route('quotations.respond', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Record the customer's response</h2>
                    <label for="respondent_name" class="mt-3 block text-sm">Name</label>
                    <input id="respondent_name" name="respondent_name" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <label for="respondent_designation" class="mt-3 block text-sm">Designation <span class="text-muted">(optional)</span></label>
                    <input id="respondent_designation" name="respondent_designation" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <label for="response-remarks" class="mt-3 block text-sm">Remarks <span class="text-muted">(optional)</span></label>
                    <textarea id="response-remarks" name="remarks" rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <label class="mt-3 flex items-start gap-2 text-sm">
                        <input type="checkbox" name="confirmed" value="1" class="mt-1">
                        <span>I confirm this response is for revision {{ $revision->revision_number }}.</span>
                    </label>
                    <div class="mt-3 flex gap-2">
                        <button type="submit" name="decision" value="accepted" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white">Accept</button>
                        <button type="submit" name="decision" value="rejected" class="rounded border border-line px-3 py-2 text-sm">Reject</button>
                    </div>
                </form>
            @endif
            @if ($canExpire)
                <form method="POST" action="{{ route('quotations.expire', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Mark expired</h2>
                    <p class="mt-1 text-sm text-muted">The validity date has passed.</p>
                    <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Mark expired</button>
                </form>
            @endif
            @if ($canCancel)
                <form method="POST" action="{{ route('quotations.cancel', $quotation) }}" class="rounded border border-line bg-white p-4">
                    @csrf
                    <h2 class="text-sm font-medium">Cancel</h2>
                    <label for="cancel_reason" class="mt-3 block text-sm">Reason</label>
                    <textarea id="cancel_reason" name="cancel_reason" required rows="2" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-3 rounded border border-line px-3 py-2 text-sm">Cancel quotation</button>
                </form>
            @endif
        </div>
    @endif

    @if ($revision->sends->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-base font-semibold">Send record</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($revision->sends as $send)
                    <li class="rounded border border-line bg-white px-3 py-2">
                        {{ $send->created_at?->timezone('Asia/Dhaka')->format('d M Y H:i') }}
                        · {{ $send->sender?->name }}
                        · Email was not sent
                        @if ($send->recipient_label) · {{ $send->recipient_label }} @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($revision->approvals->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-base font-semibold">Approval record</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($revision->approvals as $approval)
                    <li class="rounded border border-line bg-white px-3 py-2">
                        {{ $approval->actionLabel() }} · {{ $approval->user?->name }} · {{ $approval->created_at?->timezone('Asia/Dhaka')->format('d M Y H:i') }}
                        @if ($approval->remarks)<span class="block text-muted">{{ $approval->remarks }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($revision->response)
        <section class="mt-6 rounded border border-line bg-white p-4 text-sm">
            <h2 class="font-medium">{{ $revision->response->decisionLabel() }}</h2>
            <p class="mt-1">{{ $revision->response->respondent_name }}@if ($revision->response->respondent_designation), {{ $revision->response->respondent_designation }}@endif</p>
            @if ($revision->response->remarks)
                <p class="mt-1 whitespace-pre-wrap">{{ $revision->response->remarks }}</p>
            @endif
        </section>
    @endif

    @if ($revision->cancel_reason)
        <p class="mt-4 text-sm">Cancellation reason: {{ $revision->cancel_reason }}</p>
    @endif

    @if ($canRevokeShare && $revision->shareTokens->isNotEmpty())
        <section class="mt-6">
            <h2 class="text-base font-semibold">Share links</h2>
            <p class="mt-1 text-sm text-muted">The token itself is not stored and is not listed here.</p>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($revision->shareTokens as $share)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded border border-line bg-white px-3 py-2">
                        <span>
                            Created {{ $share->created_at?->timezone('Asia/Dhaka')->format('d M Y H:i') }}
                            by {{ $share->createdBy?->name }}
                            · expires {{ $share->expires_at?->timezone('Asia/Dhaka')->format('d M Y H:i') }}
                            · {{ $share->revoked_at ? 'Revoked' : ($share->isUsable() ? 'Active' : 'Expired') }}
                        </span>
                        @if ($share->revoked_at === null)
                            <form method="POST" action="{{ route('quotations.shares.revoke', [$quotation, $share]) }}">
                                @csrf
                                <button type="submit" class="text-sm font-medium text-brand">Revoke</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
