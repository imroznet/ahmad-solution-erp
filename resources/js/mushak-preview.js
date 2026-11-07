// Live VAT preview controller for the draft Mushak form.
//
// The browser does no money arithmetic at all: every preview is the
// server's VatLineCalculator, the same authority that runs again on
// save. This module only owns the request protocol — debounce,
// invalidation, and which response may update the form — so the race
// between an in-flight preview and the user's next edit is decided
// here, in one place, and can be tested without a browser.
//
// Protocol:
// - Every edit invalidates immediately: the generation counter rises
//   and any in-flight request is aborted. A response that arrives
//   after that can never update the form.
// - Every preview send takes the current generation, so two sends
//   can never share one. Only the response whose generation is still
//   current may apply.
// - AbortController cancels the network request, but it is not the
//   authority: an aborted response that still resolves is ignored
//   by the generation check.
// - A response only ever writes derived fields; the field being
//   edited keeps the user's text and caret. The percentage is never
//   overwritten.
//
// The caller supplies:
// - read() -> { driver, base, rate, adjustment, final, precision, method }
//   (all values trimmed strings; base/rate/final may be '')
// - send(snapshot, signal) -> Promise<{ ok, payload }> (the fetch)
// - apply(patch) — writes the given derived fields (keys are the
//   form's data-field names: base_amount, vat_amount, final_amount,
//   adjustment_amount)
// - note(text) — shows the note under the driver, or clears it.
export function createPreviewController({ read, send, apply, note, debounceMs = 250 }) {
    let generation = 0;
    let aborter = null;
    let timer = null;

    function invalidate() {
        generation += 1;
        if (aborter !== null) {
            aborter.abort();
            aborter = null;
        }
    }

    function clearTimer() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    async function request({ normalize = false } = {}) {
        const snapshot = read();

        // Invalidate first: a request sent for the previous values
        // must not be able to refill fields the user just cleared,
        // and this send takes a generation no earlier send shares.
        invalidate();

        if (snapshot.driver === 'reverse' && snapshot.final === '') {
            apply({ base_amount: '', vat_amount: '' });
            note('');
            return;
        }
        if (snapshot.driver === 'forward' && snapshot.base === '') {
            apply({ vat_amount: '', final_amount: '' });
            note('');
            return;
        }

        const sentGeneration = generation;
        const current = new AbortController();
        aborter = current;

        let response;
        try {
            response = await send(snapshot, current.signal);
        } catch (error) {
            if (aborter === current) aborter = null;
            // An abort is an intentional supersede by a newer edit or
            // preview, not a failure: say nothing and let the newer
            // request populate the fields.
            if (error && error.name === 'AbortError') return;
            note('Could not calculate yet. Saving will calculate again.');
            return;
        }
        if (aborter === current) aborter = null;

        // Only the latest valid preview may update the form. A
        // response that arrives after any newer edit or preview is
        // discarded, even if its request was never aborted.
        if (generation !== sentGeneration) return;

        if (!response.ok) {
            note(response.payload?.message || 'The line could not be calculated.');
            return;
        }

        const payload = response.payload;

        // While typing, only derived fields change, so the field being
        // edited keeps the user's text and caret. On blur, every field
        // is normalised to the server's scaled values. The percentage
        // is never overwritten: it stays exactly as the user typed it.
        const patch = { vat_amount: payload.vat_amount ?? '' };
        if (snapshot.driver === 'reverse') {
            patch.base_amount = payload.base_amount;
            if (normalize) {
                patch.final_amount = payload.final_amount ?? '';
                patch.adjustment_amount = payload.adjustment_amount;
            }
        } else {
            patch.final_amount = payload.final_amount ?? '';
            if (normalize) {
                patch.base_amount = payload.base_amount;
                patch.adjustment_amount = payload.adjustment_amount;
            }
        }
        apply(patch);

        if (payload.vat_rate === null) {
            note('No VAT calculated — the percentage is blank.');
        } else if (payload.residual_applied) {
            note('VAT is the residual, so the final amount is kept exactly.');
        } else {
            note('');
        }
    }

    function edited() {
        // Invalidate at once, before the debounce: the request already
        // in flight must not survive the edit that supersedes it. The
        // debounced preview then reads the newest values when it fires.
        invalidate();
        clearTimer();
        timer = setTimeout(() => {
            timer = null;
            request();
        }, debounceMs);
    }

    return { edited, request, invalidate };
}
