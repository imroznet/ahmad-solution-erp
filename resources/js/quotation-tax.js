// Live quotation tax preview.
//
// The browser does no money arithmetic. It sends the typed lines, the
// VAT percentage, the generic Tax percentage, and the adjustment, then
// writes the server's display strings into the preview. It never writes
// a percentage back into an input, and it never invents 15%.
//
// A newer edit invalidates an in-flight response. The generation check
// is the authority; AbortController only cancels the request.

export function createQuotationTaxPreview({ read, send, apply, note, debounceMs = 250 }) {
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

    async function request() {
        const snapshot = read();
        invalidate();

        if (snapshot.skip) {
            apply({
                base_display: '—',
                vat_display: '—',
                tax_display: '—',
                adjustment_display: '—',
                total_display: '—',
            });
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
            if (aborter === current) {
                aborter = null;
            }
            if (sentGeneration !== generation) {
                return;
            }
            if (error && error.name === 'AbortError') {
                return;
            }
            note('The amounts could not be calculated. They will be calculated again when you save.');
            return;
        }

        if (aborter === current) {
            aborter = null;
        }
        if (sentGeneration !== generation) {
            return;
        }

        const payload = response.payload || {};
        if (!response.ok) {
            note(payload.message || 'The amounts could not be calculated.');
            apply({
                base_display: '',
                vat_display: '',
                tax_display: '',
                adjustment_display: '',
                total_display: '',
            });
            return;
        }

        apply({
            base_display: payload.base_display ?? '',
            vat_display: payload.vat_display ?? '',
            tax_display: payload.tax_display ?? '',
            adjustment_display: payload.adjustment_display ?? '',
            total_display: payload.total_display ?? '',
        });
        note(payload.notice ?? '');
    }

    return {
        schedule() {
            clearTimer();
            timer = setTimeout(() => {
                timer = null;
                request();
            }, debounceMs);
        },
        flush() {
            clearTimer();
            return request();
        },
    };
}
