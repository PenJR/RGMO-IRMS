/**
 * Serialize draft saves so each request uses the last acknowledged revision.
 * The form adapter owns serialization, transport and receipt presentation.
 */
export class PurchaseDraftAutosaver {
    constructor({
        id,
        revision = 0,
        save,
        onState = () => {},
        delay = 900,
        setTimeout: schedule = (callback, timeout) => globalThis.setTimeout(callback, timeout),
        clearTimeout: unschedule = (handle) => globalThis.clearTimeout(handle),
    }) {
        if (typeof id !== 'string' || !id || typeof save !== 'function') {
            throw new TypeError('A draft identifier and save function are required.');
        }
        if (!Number.isInteger(revision) || revision < 0) {
            throw new TypeError('The draft revision must be a nonnegative integer.');
        }

        this.id = id;
        this._revision = revision;
        this._save = save;
        this._onState = onState;
        this._delay = delay;
        this._schedule = schedule;
        this._unschedule = unschedule;
        this._snapshot = null;
        this._version = 0;
        this._savedVersion = 0;
        this._timer = null;
        this._running = null;
        this._lastDraft = null;
        this._conflict = null;
        this._keepalive = false;
        this._stopped = false;
    }

    get dirty() {
        return this._version > this._savedVersion;
    }

    get busy() {
        return this._running !== null;
    }

    get revision() {
        return this._revision;
    }

    get conflicted() {
        return this._conflict !== null;
    }

    update(snapshot) {
        if (this._stopped || this.conflicted) return false;

        this._snapshot = snapshot;
        this._version += 1;
        this._cancelTimer();
        this._timer = this._schedule(() => {
            this._timer = null;
            // Failures are reported through onState and remain available to retry.
            void this.flush().catch(() => {});
        }, this._delay);

        return true;
    }

    async flush({ keepalive = false } = {}) {
        this._cancelTimer();
        if (this._stopped) return this._lastDraft;
        if (this.conflicted) throw this._conflict;

        while (!this._stopped && this.dirty) {
            this._keepalive ||= keepalive;
            this._cancelTimer();
            if (!this._running) {
                // Assign the promise before callbacks can request another flush.
                this._running = Promise.resolve().then(() => this._drain()).finally(() => {
                    this._running = null;
                    this._keepalive = false;
                });
            }
            await this._running;
            // An edit can arrive between the drain finishing and its promise's
            // callbacks. Check again before reporting a successful final flush.
        }

        return this._lastDraft;
    }

    stop() {
        this._stopped = true;
        this._cancelTimer();
    }

    async _drain() {
        while (!this._stopped && this.dirty) {
            this._cancelTimer();
            const version = this._version;
            const snapshot = this._snapshot;
            this._emit('saving', { snapshot, revision: this._revision });

            try {
                const draft = await this._save({
                    ...snapshot,
                    id: this.id,
                    expected_revision: this._revision,
                }, { keepalive: this._keepalive });

                if (!draft || !Number.isInteger(draft.revision) || draft.revision <= this._revision) {
                    throw new Error('The server did not acknowledge a valid draft revision.');
                }
                if (draft.id !== this.id) {
                    throw new Error('The server acknowledged a different draft.');
                }

                this._revision = draft.revision;
                this._savedVersion = version;
                this._lastDraft = draft;

                // A later edit may have queued the same upload while it was in
                // flight. It is already stored; do not upload or remove it twice.
                if (this._snapshot !== snapshot) {
                    const queued = { ...this._snapshot };
                    if (snapshot.receipt && queued.receipt === snapshot.receipt) queued.receipt = null;
                    if (snapshot.removeReceipt && queued.removeReceipt) queued.removeReceipt = false;
                    this._snapshot = queued;
                }

                this._emit('saved', { draft, snapshot, dirty: this.dirty });
            } catch (error) {
                if (error?.status === 409) {
                    this._conflict = error;
                    this._cancelTimer();
                    this._emit('conflict', { error, snapshot, draft: error.draft ?? null });
                } else {
                    this._emit('error', { error, snapshot, draft: null });
                }
                throw error;
            }
        }

        return this._lastDraft;
    }

    _cancelTimer() {
        if (this._timer !== null) {
            this._unschedule(this._timer);
            this._timer = null;
        }
    }

    _emit(status, details) {
        if (!this._stopped) this._onState(status, details);
    }
}
