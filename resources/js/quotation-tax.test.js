import { afterEach, beforeEach, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { createQuotationTaxPreview } from './quotation-tax.js';

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });
    return { promise, resolve, reject };
}

async function settle() {
    await new Promise((resolve) => setImmediate(resolve));
    await new Promise((resolve) => setImmediate(resolve));
}

describe('quotation tax preview', () => {
    beforeEach(() => {
        // node:test fake timers are not required; debounce is set to 0.
    });

    afterEach(() => {
    });

    it('shows a dash and does not call the server when no line is described', async () => {
        const applied = [];
        let sent = 0;
        const controller = createQuotationTaxPreview({
            read: () => ({ skip: true }),
            send: async () => {
                sent += 1;
                return { ok: true, payload: {} };
            },
            apply: (patch) => applied.push(patch),
            note: () => {},
            debounceMs: 0,
        });

        await controller.flush();

        assert.equal(sent, 0);
        assert.equal(applied[0].total_display, '—');
    });

    it('writes the server display strings and does not pass a percentage through', async () => {
        const applied = [];
        const notes = [];
        const controller = createQuotationTaxPreview({
            read: () => ({ skip: false }),
            send: async () => ({
                ok: true,
                payload: {
                    base_display: '৳1,000.00',
                    vat_display: '৳150.00',
                    tax_display: 'Not calculated',
                    adjustment_display: '৳0.00',
                    total_display: '৳1,150.00',
                    notice: 'typed',
                    vat_rate_percent: '15',
                },
            }),
            apply: (patch) => applied.push(patch),
            note: (text) => notes.push(text),
            debounceMs: 0,
        });

        await controller.flush();

        assert.deepEqual(applied, [{
            base_display: '৳1,000.00',
            vat_display: '৳150.00',
            tax_display: 'Not calculated',
            adjustment_display: '৳0.00',
            total_display: '৳1,150.00',
        }]);
        assert.equal(Object.hasOwn(applied[0], 'vat_rate_percent'), false);
        assert.deepEqual(notes, ['typed']);
    });

    it('ignores a stale response after a newer edit', async () => {
        const first = deferred();
        const applied = [];
        let generation = 0;
        const controller = createQuotationTaxPreview({
            read: () => ({ skip: false, generation: generation }),
            send: () => {
                generation += 1;
                return generation === 1 ? first.promise : Promise.resolve({
                    ok: true,
                    payload: { total_display: 'newer' },
                });
            },
            apply: (patch) => applied.push(patch),
            note: () => {},
            debounceMs: 0,
        });

        const older = controller.flush();
        await controller.flush();
        first.resolve({
            ok: true,
            payload: { total_display: 'stale', vat_rate_percent: '15' },
        });
        await older;
        await settle();

        assert.deepEqual(applied.map((patch) => patch.total_display), ['newer']);
    });
});
