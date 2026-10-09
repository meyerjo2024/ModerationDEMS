import Alpine from 'alpinejs';

window.Alpine = Alpine;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** JSON/multipart request to the app. Throws Error(message) with the server's friendly text. */
async function api(method, url, body) {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(data.error || first || data.message || 'Something went wrong. Please try again.');
    }
    return data;
}

/** Run an async action with busy/error state handling on an Alpine component. */
async function run(self, key, fn) {
    self.busy = key;
    self.error = '';
    self.saved = '';
    try {
        return await fn();
    } catch (e) {
        self.error = e.message;
    } finally {
        self.busy = null;
    }
}

const go = (data) => {
    window.scrollTo({ top: 0 });
    window.location.href = data.redirect;
};

// ── Signature pad + password re-confirmation ────────────────────────────────
Alpine.data('signoff', () => ({
    password: '',
    image: null,
    drawing: false,
    last: null,
    length: 0,
    empty: true,
    init() {
        this.setup();
        // The pad may start hidden (zero width) inside a collapsed panel — size it whenever it becomes visible.
        new ResizeObserver(() => {
            if (this.empty && !this.drawing) this.setup();
        }).observe(this.$refs.pad);
    },
    setup() {
        const c = this.$refs.pad;
        const { width } = c.getBoundingClientRect();
        if (!width) return;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        c.width = Math.round(width * dpr);
        c.height = Math.round(150 * dpr);
        const ctx = c.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2.4;
        ctx.strokeStyle = '#1d1d1f';
    },
    pos(e) {
        const r = this.$refs.pad.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    },
    down(e) {
        e.target.setPointerCapture(e.pointerId);
        this.drawing = true;
        this.last = this.pos(e);
        const ctx = this.$refs.pad.getContext('2d');
        ctx.beginPath();
        ctx.arc(this.last.x, this.last.y, 0.6, 0, Math.PI * 2);
        ctx.stroke();
    },
    move(e) {
        if (!this.drawing || !this.last) return;
        const p = this.pos(e);
        const ctx = this.$refs.pad.getContext('2d');
        const mid = { x: (this.last.x + p.x) / 2, y: (this.last.y + p.y) / 2 };
        ctx.beginPath();
        ctx.moveTo(this.last.x, this.last.y);
        ctx.quadraticCurveTo(this.last.x, this.last.y, mid.x, mid.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        this.length += Math.hypot(p.x - this.last.x, p.y - this.last.y);
        this.last = p;
    },
    up() {
        if (!this.drawing) return;
        this.drawing = false;
        this.last = null;
        if (this.length > 40) {
            this.empty = false;
            this.image = this.$refs.pad.toDataURL('image/png');
            this.emit();
        }
    },
    clear() {
        const c = this.$refs.pad;
        c.getContext('2d').clearRect(0, 0, c.width, c.height);
        this.length = 0;
        this.empty = true;
        this.image = null;
        this.emit();
    },
    emit() {
        this.$dispatch('signed', this.image && this.password ? { image: this.image, password: this.password } : null);
    },
}));

// ── Phase 1: Section 1 ──────────────────────────────────────────────────────
Alpine.data('section1', (cfg) => ({
    id: cfg.id,
    rows: cfg.rows.length ? cfg.rows : [{ type: '', weighting: '', heqf_level: 6, aligned: true, comment: '' }],
    files: cfg.files, // { PAPER: {filename,...}|null, MEMO: ... }
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get total() {
        return +this.rows.reduce((s, r) => s + (Number(r.weighting) || 0), 0).toFixed(2);
    },
    get ready() {
        return this.sig && this.total === 100 && this.files.PAPER && this.files.MEMO;
    },
    add() {
        if (this.rows.length < 15) this.rows.push({ type: '', weighting: '', heqf_level: 6, aligned: true, comment: '' });
    },
    remove(i) {
        if (this.rows.length > 1) this.rows.splice(i, 1);
    },
    payload() {
        return this.rows.map((r) => ({ ...r, weighting: Number(r.weighting) || 0, heqf_level: Number(r.heqf_level) }));
    },
    upload(kind, file) {
        return run(this, kind, async () => {
            const f = new FormData();
            f.set('kind', kind);
            f.set('file', file);
            const { attachment } = await api('POST', `/assessments/${this.id}/attachments`, f);
            this.files[kind] = { id: attachment.id, filename: attachment.filename, fresh: true };
        });
    },
    saveDraft() {
        return run(this, 'save', async () => {
            await api('PUT', `/assessments/${this.id}/section1`, { question_types: this.payload() });
            this.saved = 'Draft saved.';
        });
    },
    submit() {
        return run(this, 'submit', async () => {
            go(await api('POST', `/assessments/${this.id}/submit-pre`, { question_types: this.payload(), signature: this.sig }));
        });
    },
}));

// ── Gate 1 ──────────────────────────────────────────────────────────────────
Alpine.data('preReview', (cfg) => ({
    id: cfg.id,
    approving: false,
    sheet: false,
    comments: '',
    consensus: false,
    sig: null,
    busy: null,
    error: '',
    saved: '',
    approve() {
        return run(this, 'approve', async () => {
            go(await api('POST', `/assessments/${this.id}/pre-review`, { decision: 'APPROVED', consensus: this.consensus, comments: this.comments || null, signature: this.sig }));
        });
    },
    revise() {
        return run(this, 'revise', async () => {
            go(await api('POST', `/assessments/${this.id}/pre-review`, { decision: 'REVISION_REQUESTED', comments: this.comments }));
        });
    },
}));

// ── Phase 3: Section 2 ──────────────────────────────────────────────────────
Alpine.data('section2', (cfg) => ({
    id: cfg.id,
    mode: 'excel',
    attId: cfg.marksId || '',
    marksName: cfg.marksName || '',
    samples: cfg.samples,
    sheets: [],
    sheet: '',
    column: 0,
    manual: '',
    total: 100,
    preview: null,
    calcError: '',
    calculating: false,
    commentary: '',
    sig: null,
    busy: null,
    error: '',
    saved: '',
    _t: null,
    init() {
        if (this.attId) this.loadSheets();
        this.$watch('sourceKey', () => this.schedule());
        this.$watch('total', () => this.schedule());
    },
    get cols() {
        return this.sheets.find((s) => s.name === this.sheet)?.columns ?? [];
    },
    get source() {
        if (this.mode === 'excel') return this.attId && this.sheet && Number(this.column) ? { type: 'excel', attachment_id: this.attId, sheet: this.sheet, column: Number(this.column) } : null;
        return this.manual.trim() ? { type: 'manual', text: this.manual } : null;
    },
    get sourceKey() {
        return JSON.stringify(this.source);
    },
    get ready() {
        return this.preview && this.commentary.trim().length >= 10 && this.sig;
    },
    async loadSheets() {
        try {
            const r = await api('GET', `/assessments/${this.id}/marks?attachment=${this.attId}`);
            this.sheets = r.sheets;
            this.sheet = r.sheets[0]?.name ?? '';
            this.column = 0;
            this.preview = null;
        } catch (e) {
            this.calcError = e.message;
        }
    },
    schedule() {
        clearTimeout(this._t);
        this.calcError = '';
        if (!this.source || !(Number(this.total) > 0)) {
            this.preview = null;
            return;
        }
        this.calculating = true;
        this._t = setTimeout(async () => {
            try {
                this.preview = await api('POST', `/assessments/${this.id}/calculate`, { source: this.source, total_marks: Number(this.total) });
            } catch (e) {
                this.preview = null;
                this.calcError = e.message;
            } finally {
                this.calculating = false;
            }
        }, 350);
    },
    upload(kind, file) {
        return run(this, kind, async () => {
            const f = new FormData();
            f.set('kind', kind);
            f.set('file', file);
            const { attachment } = await api('POST', `/assessments/${this.id}/attachments`, f);
            if (kind === 'MARKS') {
                this.attId = attachment.id;
                this.marksName = attachment.filename;
                await this.loadSheets();
            } else {
                this.samples.push({ id: attachment.id, filename: attachment.filename });
            }
        });
    },
    submit() {
        return run(this, 'submit', async () => {
            go(await api('POST', `/assessments/${this.id}/submit-post`, { source: this.source, total_marks: Number(this.total), commentary: this.commentary, signature: this.sig }));
        });
    },
}));

// ── Gate 2 / Gate 3 ─────────────────────────────────────────────────────────
Alpine.data('finalReview', (cfg) => ({
    id: cfg.id,
    checks: cfg.checks, // [{id, question}]
    answers: Object.fromEntries(cfg.checks.map((c) => [c.id, { answer: null, comment: '' }])),
    scripts: '',
    comments: '',
    consensus: false,
    sheet: false,
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get complete() {
        return this.checks.every((c) => this.answers[c.id].answer && (this.answers[c.id].answer !== 'NO' || this.answers[c.id].comment.trim()));
    },
    approve() {
        return run(this, 'approve', async () => {
            go(await api('POST', `/assessments/${this.id}/final-review`, {
                decision: 'APPROVED',
                consensus: this.consensus,
                scripts_sampled: Number(this.scripts),
                comments: this.comments || null,
                checklist: this.checks.map((c) => ({ id: c.id, answer: this.answers[c.id].answer, comment: this.answers[c.id].comment })),
                signature: this.sig,
            }));
        });
    },
    revise() {
        return run(this, 'revise', async () => {
            go(await api('POST', `/assessments/${this.id}/final-review`, { decision: 'REVISION_REQUESTED', comments: this.comments }));
        });
    },
}));

// ── Chrome ──────────────────────────────────────────────────────────────────
Alpine.data('docViewer', (first) => ({ tab: first }));

Alpine.data('notifs', () => ({
    items: [],
    unread: 0,
    open: false,
    init() {
        this.load();
        setInterval(() => this.load(), 60000);
    },
    async load() {
        try {
            const r = await api('GET', '/notifications');
            this.items = r.notifications;
            this.unread = r.unread;
        } catch {
            /* ignore */
        }
    },
    toggle() {
        this.open = !this.open;
        if (this.open && this.unread) api('POST', '/notifications/read').then(() => setTimeout(() => this.load(), 4000)).catch(() => {});
    },
}));

Alpine.data('dashboard', (meta) => ({
    meta,
    filter: 'ALL',
    q: '',
    show(i) {
        const { status, action, text } = this.meta[i];
        const f = this.filter;
        const ok = f === 'ALL' || (f === 'ACTION' ? action : f === 'FINAL' ? ['PENDING_FINAL_MODERATION', 'PENDING_EXTERNAL_MODERATION'].includes(status) : status === f);
        return ok && text.toLowerCase().includes(this.q.toLowerCase());
    },
    get none() {
        return this.meta.length > 0 && !this.meta.some((_, i) => this.show(i));
    },
}));

Alpine.start();
