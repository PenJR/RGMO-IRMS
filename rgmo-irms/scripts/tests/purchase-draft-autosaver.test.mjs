import assert from 'node:assert/strict';
import test from 'node:test';
import { PurchaseDraftAutosaver } from '../../resources/js/purchase-draft-autosaver.js';

class Clock {
    now = 0;
    nextId = 1;
    timers = new Map();

    setTimeout = (callback, delay) => {
        const id = this.nextId++;
        this.timers.set(id, { callback, at: this.now + delay });
        return id;
    };

    clearTimeout = (id) => this.timers.delete(id);

    advance(ms) {
        const end = this.now + ms;
        while (true) {
            const next = [...this.timers].filter(([, timer]) => timer.at <= end)
                .sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            this.now = next[1].at;
            this.timers.delete(next[0]);
            next[1].callback();
        }
        this.now = end;
    }
}

const settle = async () => {
    for (let index = 0; index < 12; index += 1) await Promise.resolve();
};

const deferred = () => {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};

const snapshot = (notes, receipt = null, removeReceipt = false) => ({
    payload: { notes, items: [{ inventory_item_id: '', ordered_quantity: '', unit_cost: '' }] },
    receipt,
    removeReceipt,
});

const makeQueue = (options = {}) => {
    const clock = new Clock();
    const states = [];
    const calls = [];
    const queue = new PurchaseDraftAutosaver({
        id: 'draft-a',
        save: async (request, transport) => {
            calls.push({ request, transport });
            return { id: request.id, revision: request.expected_revision + 1 };
        },
        onState: (status, details) => states.push({ status, details }),
        setTimeout: clock.setTimeout,
        clearTimeout: clock.clearTimeout,
        ...options,
    });
    return { queue, clock, states, calls };
};

test('typing is debounced and incomplete fields survive in the latest snapshot', async () => {
    const { queue, clock, states, calls } = makeQueue();
    queue.update(snapshot('first'));
    clock.advance(600);
    queue.update(snapshot('unfinished newer entry'));
    clock.advance(899);
    await settle();
    assert.equal(calls.length, 0);
    assert.equal(queue.dirty, true);
    clock.advance(1);
    await settle();
    assert.equal(calls.length, 1);
    assert.deepEqual(calls[0].request.payload, snapshot('unfinished newer entry').payload);
    assert.equal(calls[0].request.expected_revision, 0);
    assert.equal(queue.revision, 1);
    assert.equal(queue.dirty, false);
    assert.equal(queue.busy, false);
    assert.deepEqual(states.map((entry) => entry.status), ['saving', 'saved']);
});

test('edits during an in-flight save wait and use the newly acknowledged revision', async () => {
    const first = deferred();
    const second = deferred();
    const requests = [];
    let concurrent = 0;
    let maximumConcurrent = 0;
    const { queue } = makeQueue({
        revision: 4,
        save: async (request) => {
            requests.push(request);
            concurrent += 1;
            maximumConcurrent = Math.max(maximumConcurrent, concurrent);
            const result = await (requests.length === 1 ? first.promise : second.promise);
            concurrent -= 1;
            return result;
        },
    });
    queue.update(snapshot('before'));
    const flushing = queue.flush();
    await settle();
    queue.update(snapshot('during save'));
    const alsoFlushing = queue.flush();
    await settle();
    assert.equal(requests.length, 1);
    assert.equal(queue.busy, true);
    first.resolve({ id: 'draft-a', revision: 5 });
    await settle();
    assert.equal(requests.length, 2);
    assert.equal(requests[1].expected_revision, 5);
    assert.equal(requests[1].payload.notes, 'during save');
    assert.equal(queue.dirty, true);
    second.resolve({ id: 'draft-a', revision: 6 });
    assert.deepEqual(await flushing, { id: 'draft-a', revision: 6 });
    await alsoFlushing;
    assert.equal(maximumConcurrent, 1);
    assert.equal(queue.revision, 6);
    assert.equal(queue.dirty, false);
});

test('a keepalive flush joining an active save upgrades the next queued request', async () => {
    const first = deferred();
    const calls = [];
    const { queue } = makeQueue({
        save: async (request, options) => {
            calls.push({ request, options });
            return calls.length === 1 ? first.promise : { id: request.id, revision: 2 };
        },
    });
    queue.update(snapshot('normal request'));
    const normal = queue.flush();
    await settle();
    queue.update(snapshot('leaving page'));
    const leaving = queue.flush({ keepalive: true });
    first.resolve({ id: 'draft-a', revision: 1 });
    await Promise.all([normal, leaving]);
    assert.equal(calls.length, 2);
    assert.equal(calls[0].options.keepalive, false);
    assert.equal(calls[1].options.keepalive, true);
});

test('network failures retain the latest unsaved work for an explicit retry', async () => {
    const failure = new Error('offline');
    const requests = [];
    const { queue, states } = makeQueue({
        revision: 2,
        save: async (request) => {
            requests.push(request);
            if (requests.length === 1) throw failure;
            return { id: request.id, revision: 3 };
        },
    });
    queue.update(snapshot('keep this'));
    await assert.rejects(queue.flush(), (error) => error === failure);
    assert.equal(queue.dirty, true);
    assert.equal(queue.busy, false);
    assert.equal(queue.revision, 2);
    queue.update(snapshot('latest retry data'));
    await queue.flush();
    assert.equal(requests[1].payload.notes, 'latest retry data');
    assert.equal(requests[1].expected_revision, 2);
    assert.equal(queue.dirty, false);
    assert.deepEqual(states.map((entry) => entry.status), ['saving', 'error', 'saving', 'saved']);
});

test('conflicts latch and never silently overwrite another tab or completed draft', async () => {
    const serverDraft = { id: 'draft-a', revision: 9, payload: { notes: 'other tab' } };
    const conflict = Object.assign(new Error('changed elsewhere'), { status: 409, draft: serverDraft });
    let calls = 0;
    const { queue, states, clock } = makeQueue({
        revision: 5,
        save: async () => { calls += 1; throw conflict; },
    });
    queue.update(snapshot('local changes'));
    await assert.rejects(queue.flush(), (error) => error === conflict);
    assert.equal(queue.conflicted, true);
    assert.equal(queue.dirty, true);
    assert.equal(queue.revision, 5);
    assert.equal(queue.update(snapshot('must not overwrite')), false);
    clock.advance(5000);
    await settle();
    await assert.rejects(queue.flush(), (error) => error === conflict);
    assert.equal(calls, 1);
    assert.equal(states.at(-1).status, 'conflict');
    assert.equal(states.at(-1).details.draft, serverDraft);
});

test('stopping cancels a pending debounce without saving', async () => {
    const { queue, clock, calls, states } = makeQueue();
    queue.update(snapshot('discarded'));
    queue.stop();
    clock.advance(5000);
    await settle();
    assert.equal(queue.update(snapshot('ignored')), false);
    assert.equal(await queue.flush(), null);
    assert.equal(calls.length, 0);
    assert.equal(states.length, 0);
});

test('stopping suppresses late callbacks and queued writes without aborting the current save', async () => {
    const pending = deferred();
    let calls = 0;
    const { queue, states } = makeQueue({ save: async () => { calls += 1; return pending.promise; } });
    queue.update(snapshot('already sent'));
    const flushing = queue.flush();
    await settle();
    queue.update(snapshot('not sent after stop'));
    queue.stop();
    pending.resolve({ id: 'draft-a', revision: 1 });
    await flushing;
    assert.equal(calls, 1);
    assert.equal(queue.revision, 1);
    assert.equal(queue.dirty, true);
    assert.deepEqual(states.map((entry) => entry.status), ['saving']);
});

test('an acknowledged upload is not repeated by newer payload-only edits', async () => {
    const file = new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' });
    const first = deferred();
    const requests = [];
    const { queue, states } = makeQueue({
        save: async (request) => {
            requests.push(request);
            return requests.length === 1 ? first.promise : { id: request.id, revision: 2 };
        },
    });
    queue.update(snapshot('with receipt', file));
    const flushing = queue.flush();
    await settle();
    queue.update(snapshot('new notes with same receipt', file));
    first.resolve({ id: 'draft-a', revision: 1 });
    await flushing;
    assert.equal(requests[0].receipt, file);
    assert.equal(requests[1].receipt, null);
    assert.equal(requests[1].payload.notes, 'new notes with same receipt');
    assert.equal(states.find((entry) => entry.status === 'saved').details.snapshot.receipt, file);
});

test('a replacement selected while uploading stays queued as a distinct file', async () => {
    const original = new File(['first'], 'first.pdf');
    const replacement = new File(['second'], 'second.pdf');
    const first = deferred();
    const requests = [];
    const { queue } = makeQueue({
        save: async (request) => {
            requests.push(request);
            return requests.length === 1 ? first.promise : { id: request.id, revision: 2 };
        },
    });
    queue.update(snapshot('first file', original));
    const flushing = queue.flush();
    await settle();
    queue.update(snapshot('replacement file', replacement));
    first.resolve({ id: 'draft-a', revision: 1 });
    await flushing;
    assert.equal(requests[1].receipt, replacement);
});

test('an acknowledged receipt removal is not repeated by queued edits', async () => {
    const first = deferred();
    const requests = [];
    const { queue } = makeQueue({
        save: async (request) => {
            requests.push(request);
            return requests.length === 1 ? first.promise : { id: request.id, revision: 2 };
        },
    });
    queue.update(snapshot('remove file', null, true));
    const flushing = queue.flush();
    await settle();
    queue.update(snapshot('more changes', null, true));
    first.resolve({ id: 'draft-a', revision: 1 });
    await flushing;
    assert.equal(requests[0].removeReceipt, true);
    assert.equal(requests[1].removeReceipt, false);
});

test('a final flush includes edits arriving between acknowledgement and promise completion', async () => {
    const requests = [];
    let queue;
    ({ queue } = makeQueue({
        save: async (request) => {
            requests.push(request);
            return { id: request.id, revision: request.expected_revision + 1 };
        },
        onState: (status, details) => {
            if (status === 'saved' && details.draft.revision === 1) {
                queueMicrotask(() => queue.update(snapshot('late microtask edit')));
            }
        },
    }));
    queue.update(snapshot('first'));
    const result = await queue.flush();
    assert.equal(requests.length, 2);
    assert.equal(requests[1].payload.notes, 'late microtask edit');
    assert.equal(result.revision, 2);
    assert.equal(queue.dirty, false);
});

test('a missing, unchanged or backward revision does not mark work as saved', async () => {
    for (const reply of [{}, { id: 'draft-a', revision: 2 }, { id: 'draft-a', revision: 3 }]) {
        const { queue, states } = makeQueue({ revision: 3, save: async () => reply });
        queue.update(snapshot('still unsaved'));
        await assert.rejects(queue.flush(), /valid draft revision/);
        assert.equal(queue.revision, 3);
        assert.equal(queue.dirty, true);
        assert.equal(states.at(-1).status, 'error');
    }
});

test('a response for a different draft is not adopted', async () => {
    const { queue } = makeQueue({ save: async () => ({ id: 'draft-b', revision: 1 }) });
    queue.update(snapshot('local data'));
    await assert.rejects(queue.flush(), /different draft/);
    assert.equal(queue.dirty, true);
    assert.equal(queue.revision, 0);
});

test('a response without a draft identifier does not mark work as saved', async () => {
    const { queue } = makeQueue({ save: async () => ({ revision: 1 }) });
    queue.update(snapshot('local data'));
    await assert.rejects(queue.flush(), /different draft/);
    assert.equal(queue.dirty, true);
    assert.equal(queue.revision, 0);
});

test('flushing a clean queue creates no request or save notification', async () => {
    const { queue, calls, states } = makeQueue({ revision: 8 });
    assert.equal(await queue.flush({ keepalive: true }), null);
    assert.equal(queue.revision, 8);
    assert.equal(queue.busy, false);
    assert.equal(calls.length, 0);
    assert.equal(states.length, 0);
});
