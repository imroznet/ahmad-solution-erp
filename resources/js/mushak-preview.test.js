// Regression tests for the live VAT preview race (Codex P2 on PR #22).
//
// The preview controller is tested without a browser: the form is a plain
// object, the network is a controllable deferred promise, and the 250 ms
// debounce runs on node:test's fake timers. Each test drives the exact
// sequence a user would type, then proves which responses may — and may
// not — update the form.
//
// Run with: npm run test:js   (node --test resources/js/)
import { afterEach, beforeEach, describe, it, mock } from 'node:test';
import assert from 'node:assert/strict';
import { createPreviewController } from './mushak-preview.js';

const FORWARD_1500 = {
    vat_amount: '225.00',
    base_amount: '1500.00',
    final_amount: '1725.00',
    adjustment_amount: '0',
    vat_rate: '15',
    residual_applied: false,
};
const FORWARD_1600 = {
    vat_amount: '240.00',
    base_amount: '1600.00',
    final_amount: '1840.00',
    adjustment_amount: '0',
    vat_rate: '15',
    residual_applied: false,
};
const FORWARD_1700 = {
    vat_amount: '255.00',
    base_amount: '1700.00',
    final_amount: '1955.00',
    adjustment_amount: '0',
    vat_rate: '15',
    residual_applied: false,
};
const REVERSE_1500 = {
    vat_amount: '225.00',
    base_amount: '1500.00',
    final_amount: '1725.50',
    adjustment_amount: '0.50',
    vat_rate: '15',
    residual_applied: true,
};

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });
    return { promise, resolve, reject };
}

// Let the microtask queue drain (lets a resolved preview apply).
async function settle() {
    await new Promise((resolve) => setImmediate(resolve));
    await new Promise((resolve) => setImmediate(resolve));
}

function harness({ abortable = true } = {}) {
    const form = {
        driver: 'forward',
        base_amount: '1500.00',
        vat_rate: '15',
        adjustment_amount: '0',
        final_amount: '',
        vat_amount: '',
    };
    const sends = [];
    const applied = [];
    const notes = [];
    const controller = createPreviewController({
        read: () => ({
            driver: form.driver,
            base: form.base_amount.trim(),
            rate: form.vat_rate.trim(),
            adjustment: form.adjustment_amount.trim(),
            final: form.final_amount.trim(),
            precision: '2',
            method: 'half_up',
        }),
        send: (snapshot, signal) => {
            const d = deferred();
            // A real fetch rejects when its signal aborts; the mock
            // does the same so the abort path is exercised for real.
            // With abortable: false the response still resolves after
            // the abort, which is how the generation check — the
            // second line of defence — gets exercised.
            if (abortable) {
                signal.addEventListener(
                    'abort',
                    () => d.reject(new DOMException('The operation was aborted.', 'AbortError')),
                    { once: true },
                );
            }
            sends.push({ snapshot: { ...snapshot }, signal, deferred: d });
            return d.promise;
        },
        apply: (patch) => {
            applied.push({ ...patch });
            Object.assign(form, patch);
        },
        note: (text) => notes.push(text),
    });
    return {
        form,
        sends,
        applied,
        notes,
        controller,
        // Mirrors the form adapter: the driver follows the last-edited
        // field, then the edit is handed to the controller.
        edited(origin) {
            form.driver = origin === 'final' ? 'reverse' : 'forward';
            controller.edited();
        },
        async advance(ms) {
            mock.timers.tick(ms);
            await settle();
        },
    };
}

beforeEach(() => {
    mock.timers.enable({ apis: ['setTimeout'] });
});

afterEach(() => {
    mock.timers.reset();
});

describe('mushak live VAT preview race', () => {
    it('an older forward response cannot overwrite a newer final-amount edit', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);
        assert.equal(h.sends[0].snapshot.driver, 'forward');

        // The user now types a final amount, switching to reverse.
        h.form.final_amount = '1725.50';
        h.edited('final');
        await h.advance(250);
        assert.equal(h.sends.length, 2);
        assert.equal(h.sends[1].snapshot.driver, 'reverse');
        assert.equal(h.sends[0].signal.aborted, true);

        // The stale forward response arrives after the edit.
        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await settle();
        assert.equal(h.applied.length, 0);
        assert.equal(h.form.final_amount, '1725.50');
        assert.equal(h.form.driver, 'reverse');

        // The current reverse response applies.
        h.sends[1].deferred.resolve({ ok: true, payload: REVERSE_1500 });
        await settle();
        assert.equal(h.applied.length, 1);
        assert.deepEqual(h.applied[0], { vat_amount: '225.00', base_amount: '1500.00' });
        assert.equal(h.form.vat_amount, '225.00');
        assert.equal(h.form.base_amount, '1500.00');
    });

    it('a response arriving between an edit and the debounced preview is discarded', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);

        // The user types a final amount. The debounced reverse preview
        // has not fired yet — this is the window the old code left
        // open: the in-flight forward request was still current.
        h.form.final_amount = '1725.50';
        h.edited('final');
        assert.equal(h.sends.length, 1);

        // The stale forward response arrives inside that window.
        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await settle();
        assert.equal(h.applied.length, 0);
        assert.equal(h.form.final_amount, '1725.50');
        assert.equal(h.form.driver, 'reverse');

        // The debounced reverse preview then fires and applies.
        await h.advance(250);
        assert.equal(h.sends.length, 2);
        h.sends[1].deferred.resolve({ ok: true, payload: REVERSE_1500 });
        await settle();
        assert.equal(h.applied.length, 1);
        assert.deepEqual(h.applied[0], { vat_amount: '225.00', base_amount: '1500.00' });
    });

    it('a response that resolves after its request was aborted is still ignored', async () => {
        // The abort is not the authority: a response that was already
        // on its way can still resolve after the abort, so only the
        // generation check can stop it.
        const h = harness({ abortable: false });
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);

        h.form.final_amount = '1725.50';
        h.edited('final');
        await h.advance(250);
        assert.equal(h.sends.length, 2);
        assert.equal(h.sends[0].signal.aborted, true);

        // The aborted request's response resolves anyway.
        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await settle();
        assert.equal(h.applied.length, 0);
        assert.equal(h.form.final_amount, '1725.50');

        h.sends[1].deferred.resolve({ ok: true, payload: REVERSE_1500 });
        await settle();
        assert.equal(h.applied.length, 1);
    });

    it('an older reverse response cannot overwrite a newer base/rate edit', async () => {
        const h = harness();
        h.form.driver = 'reverse';
        h.form.final_amount = '1725.50';
        h.edited('final');
        await h.advance(250);
        assert.equal(h.sends.length, 1);
        assert.equal(h.sends[0].snapshot.driver, 'reverse');

        // The user now edits the base, switching back to forward.
        h.form.base_amount = '1600.00';
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 2);
        assert.equal(h.sends[1].snapshot.driver, 'forward');
        assert.equal(h.sends[1].snapshot.base, '1600.00');

        // The stale reverse response arrives after the edit.
        h.sends[0].deferred.resolve({ ok: true, payload: REVERSE_1500 });
        await settle();
        assert.equal(h.applied.length, 0);
        assert.equal(h.form.base_amount, '1600.00');
        assert.equal(h.form.driver, 'forward');

        // The current forward response applies.
        h.sends[1].deferred.resolve({ ok: true, payload: FORWARD_1600 });
        await settle();
        assert.equal(h.applied.length, 1);
        assert.deepEqual(h.applied[0], { vat_amount: '240.00', final_amount: '1840.00' });
    });

    it('rapid consecutive edits invalidate previous requests and send only once', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(100);
        assert.equal(h.sends.length, 0);

        h.form.base_amount = '1600.00';
        h.edited('base');
        await h.advance(100);
        assert.equal(h.sends.length, 0);

        h.form.base_amount = '1700.00';
        h.edited('base');
        await h.advance(249);
        assert.equal(h.sends.length, 0);
        await h.advance(1);
        assert.equal(h.sends.length, 1);
        assert.equal(h.sends[0].snapshot.base, '1700.00');
    });

    it('an edit while a preview is in flight aborts it and shows no error', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);

        h.form.base_amount = '1600.00';
        h.edited('base');
        h.edited('base');
        assert.equal(h.sends[0].signal.aborted, true);
        await settle();
        assert.deepEqual(h.notes, []);

        await h.advance(250);
        assert.equal(h.sends.length, 2);
        assert.equal(h.sends[1].snapshot.base, '1600.00');
    });

    it('a stale response cannot change the calculation driver', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends[0].snapshot.driver, 'forward');

        // The user switches to reverse by typing a final amount.
        h.form.final_amount = '1800.00';
        h.edited('final');
        assert.equal(h.form.driver, 'reverse');

        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await settle();
        assert.equal(h.form.driver, 'reverse');
        assert.equal(h.form.final_amount, '1800.00');
        assert.equal(h.applied.length, 0);
    });

    it('only the latest response updates derived values', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);

        // Blur sends a second preview for the same values.
        const blur = h.controller.request({ normalize: true });
        assert.equal(h.sends.length, 2);
        assert.equal(h.sends[0].signal.aborted, true);

        // The latest response applies, even though it resolves first.
        h.sends[1].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await blur;
        await settle();
        assert.equal(h.applied.length, 1);
        assert.deepEqual(h.applied[0], {
            vat_amount: '225.00',
            final_amount: '1725.00',
            base_amount: '1500.00',
            adjustment_amount: '0',
        });

        // The older response arrives afterwards and is ignored.
        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1600 });
        await settle();
        assert.equal(h.applied.length, 1);
        assert.equal(h.form.vat_amount, '225.00');
        assert.equal(h.form.final_amount, '1725.00');
    });

    it('submitting after rapid edits uses the latest valid calculation', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(100);
        h.form.base_amount = '1600.00';
        h.edited('base');
        await h.advance(100);
        h.form.base_amount = '1700.00';
        h.edited('base');
        await h.advance(250);

        assert.equal(h.sends.length, 1);
        assert.deepEqual(h.sends[0].snapshot, {
            driver: 'forward',
            base: '1700.00',
            rate: '15',
            adjustment: '0',
            final: '',
            precision: '2',
            method: 'half_up',
        });

        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1700 });
        await settle();

        // The values the form would submit: the latest typed inputs
        // plus the derived values from the latest valid preview.
        assert.equal(h.form.base_amount, '1700.00');
        assert.equal(h.form.vat_rate, '15');
        assert.equal(h.form.vat_amount, '255.00');
        assert.equal(h.form.final_amount, '1955.00');
        assert.equal(h.form.adjustment_amount, '0');
    });

    it('clearing the base while a preview is in flight cannot refill derived fields', async () => {
        const h = harness();
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);

        // The user clears the base; the debounced preview clears the
        // derived fields instead of sending.
        h.form.base_amount = '';
        h.edited('base');
        await h.advance(250);
        assert.equal(h.sends.length, 1);
        assert.equal(h.applied.length, 1);
        assert.deepEqual(h.applied[0], { vat_amount: '', final_amount: '' });
        assert.equal(h.form.vat_amount, '');
        assert.equal(h.form.final_amount, '');

        // The stale forward response arrives and must not refill them.
        h.sends[0].deferred.resolve({ ok: true, payload: FORWARD_1500 });
        await settle();
        assert.equal(h.applied.length, 1);
        assert.equal(h.form.vat_amount, '');
        assert.equal(h.form.final_amount, '');
    });
});
