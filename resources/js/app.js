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
    form: cfg.form,
    files: cfg.files, // { PAPER: {filename,...}|null, MEMO: ... }
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get total() {
        return +Object.values(this.form.weights).reduce((s, v) => s + (Number(v) || 0), 0).toFixed(2);
    },
    get missing() {
        const f = this.form;
        return !(f.period && f.year && f.heqf_level && String(f.subject_level).trim() && f.qualification.trim() && f.qualification_code.trim() && f.assessment_date);
    },
    get ready() {
        return this.sig && !this.missing && this.total === 100 && this.files.PAPER && this.files.MEMO;
    },
    payload() {
        const f = this.form;
        return {
            ...f,
            year: Number(f.year) || null,
            heqf_level: Number(f.heqf_level) || null,
            weights: Object.fromEntries(Object.entries(f.weights).map(([k, v]) => [k, Number(v) || 0])),
        };
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
            await api('PUT', `/assessments/${this.id}/section1`, { s1: this.payload() });
            this.saved = 'Draft saved.';
        });
    },
    submit() {
        return run(this, 'submit', async () => {
            go(await api('POST', `/assessments/${this.id}/submit-pre`, { s1: this.payload(), signature: this.sig }));
        });
    },
}));

// ── Gate 1 ──────────────────────────────────────────────────────────────────
Alpine.data('preReview', (cfg) => ({
    id: cfg.id,
    ratings: cfg.ratings,
    questions: cfg.questions,
    form: {
        ratings: Object.fromEntries(cfg.ratings.map((r) => [r.key, null])),
        questions: Object.fromEntries(cfg.questions.map((q) => [q.key, { answer: null, comment: '' }])),
    },
    approving: false,
    sheet: false,
    comments: '',
    consensus: false,
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get formComplete() {
        return (
            this.ratings.every((r) => this.form.ratings[r.key] !== null) &&
            this.questions.every((q) => this.form.questions[q.key].answer && (this.form.questions[q.key].answer !== 'NO' || this.form.questions[q.key].comment.trim()))
        );
    },
    approve() {
        return run(this, 'approve', async () => {
            go(await api('POST', `/assessments/${this.id}/pre-review`, { decision: 'APPROVED', consensus: this.consensus, comments: this.comments || null, s1_moderator: this.form, signature: this.sig }));
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
    form: cfg.form, // { registered, type_of_assessment, answers: {q1..q5} }
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
    get current() {
        return this.sheets.find((s) => s.name === this.sheet) ?? null;
    },
    get cols() {
        return this.current?.columns ?? [];
    },
    /** Pre-select the test that matches the assessment number ("Test 2" → T2) when it has marks. */
    autoPick() {
        this.column = 0;
        const n = (cfg.number.match(/\d+/) ?? [])[0];
        const hit = n ? this.cols.find((c) => c.header.toUpperCase() === `T${n}` && c.nonBlank > 0) : null;
        const only = this.cols.filter((c) => c.nonBlank > 0);
        this.column = hit ? hit.index : only.length === 1 ? only[0].index : 0;
    },
    get source() {
        if (this.mode === 'excel') return this.attId && this.sheet && Number(this.column) ? { type: 'excel', attachment_id: this.attId, sheet: this.sheet, column: Number(this.column) } : null;
        return this.manual.trim() ? { type: 'manual', text: this.manual } : null;
    },
    get sourceKey() {
        return JSON.stringify(this.source);
    },
    get absent() {
        const n = this.preview?.stats?.candidate_count;
        return n === undefined || this.form.registered === null || this.form.registered === '' ? null : Math.max(0, this.form.registered - n);
    },
    get ready() {
        const f = this.form;
        return (
            this.preview &&
            this.sig &&
            f.registered !== null && f.registered !== '' && f.registered >= this.preview.stats.candidate_count &&
            f.type_of_assessment.trim() &&
            Object.values(f.answers).every((v) => v.trim())
        );
    },
    async loadSheets() {
        try {
            const r = await api('GET', `/assessments/${this.id}/marks?attachment=${this.attId}`);
            this.sheets = r.sheets;
            this.sheet = r.sheets[0]?.name ?? '';
            this.autoPick();
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
                if (this.form.registered === null || this.form.registered === '') this.form.registered = this.preview.info?.enrolled ?? this.preview.stats.candidate_count;
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
            go(await api('POST', `/assessments/${this.id}/submit-post`, { source: this.source, total_marks: Number(this.total), s2: { ...this.form, registered: Number(this.form.registered) }, signature: this.sig }));
        });
    },
}));

// ── Gate 2 (Section 2, questions 6–8) / Gate 3 (Section 3) ───────────────────
Alpine.data('finalReview', (cfg) => ({
    id: cfg.id,
    mode: cfg.mode,
    answers: cfg.answers,
    items: cfg.items,
    adjustments: cfg.adjustments,
    form: {
        answers: Object.fromEntries(cfg.answers.map((q) => [q.key, ''])),
        items: Object.fromEntries(cfg.items.map((i) => [i.key, ''])),
        adjustments: { recommended: null, specify: '' },
    },
    comments: '',
    consensus: false,
    sheet: false,
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get complete() {
        const f = this.form;
        return (
            this.answers.every((q) => f.answers[q.key].trim()) &&
            this.items.every((i) => f.items[i.key].trim()) &&
            f.adjustments.recommended &&
            (f.adjustments.recommended !== 'YES' || f.adjustments.specify.trim())
        );
    },
    approve() {
        return run(this, 'approve', async () => {
            const body = { decision: 'APPROVED', consensus: this.consensus, comments: this.comments || null, signature: this.sig };
            if (this.mode === 'external') body.s3_external = { items: this.form.items, adjustments: this.form.adjustments };
            else body.s2_moderator = this.form;
            go(await api('POST', `/assessments/${this.id}/final-review`, body));
        });
    },
    revise() {
        return run(this, 'revise', async () => {
            go(await api('POST', `/assessments/${this.id}/final-review`, { decision: 'REVISION_REQUESTED', comments: this.comments }));
        });
    },
}));

// ── Section 3 sign-off (examiner and Head of Department) ────────────────────
Alpine.data('section3', (cfg) => ({
    id: cfg.id,
    open: cfg.open || [],
    sig: null,
    busy: null,
    error: '',
    saved: '',
    sign() {
        return run(this, 'sign', async () => {
            go(await api('POST', `/assessments/${this.id}/section3-sign`, { signature: this.sig, as: this.open[0] }));
        });
    },
}));

// ── Word document review: render the .docx in the page and comment on selected passages ──
Alpine.data('wordReview', (cfg) => ({
    url: cfg.url,
    api: cfg.comments,
    loading: true,
    error: '',
    comments: [],
    can: { comment: false, address: false },
    draft: null,
    text: '',
    busy: false,
    html: '',
    active: null,
    async init() {
        try {
            const [{ default: mammoth }, { default: DOMPurify }] = await Promise.all([import('mammoth/mammoth.browser'), import('dompurify')]);
            const buf = await (await fetch(this.url, { credentials: 'same-origin' })).arrayBuffer();
            const out = await mammoth.convertToHtml({ arrayBuffer: buf });
            this.html = DOMPurify.sanitize(out.value, { USE_PROFILES: { html: true } });
            await this.load(false);
            this.paint();
            this.$watch('comments', () => setTimeout(() => this.paint(), 0), { deep: false });
        } catch (e) {
            this.error = 'This document could not be shown in the browser. Download it to read it.';
        } finally {
            this.loading = false;
        }
    },
    async load(repaint = true) {
        const r = await api('GET', this.api);
        this.comments = r.comments;
        this.can = r.can;
    },
    paint() {
        const root = this.$refs.body;
        if (!root) return;
        root.innerHTML = this.html;
        const full = root.textContent;
        this.comments.filter((c) => c.quote).forEach((c) => {
            let at = c.offset !== null && full.substr(c.offset, c.quote.length) === c.quote ? c.offset : -1;
            if (at < 0) {
                const all = [];
                for (let i = full.indexOf(c.quote); i >= 0; i = full.indexOf(c.quote, i + 1)) all.push(i);
                at = all.length ? all.reduce((b, i) => (Math.abs(i - (c.offset || 0)) < Math.abs(b - (c.offset || 0)) ? i : b)) : -1;
            }
            c.anchored = at >= 0;
            if (at >= 0) this.mark(root, at, at + c.quote.length, c);
        });
    },
    mark(root, from, to, c) {
        const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        for (let n = w.nextNode(); n; n = w.nextNode()) nodes.push(n);
        let pos = 0;
        for (const n of nodes) {
            const len = n.nodeValue.length, s = Math.max(from, pos), e = Math.min(to, pos + len);
            if (s < e) {
                const r = document.createRange();
                r.setStart(n, s - pos); r.setEnd(n, e - pos);
                const m = document.createElement('mark');
                m.dataset.cid = c.id;
                m.className = c.addressed ? 'doc-mark doc-mark-done' : 'doc-mark';
                m.addEventListener('click', () => this.focus(c.id));
                r.surroundContents(m);
            }
            pos += len;
        }
    },
    focus(id) {
        this.active = id;
        this.$nextTick(() => document.getElementById('c-' + id)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
    },
    goTo(c) {
        this.active = c.id;
        this.$refs.body.querySelector(`[data-cid="${c.id}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    },
    select() {
        if (!this.can.comment) return;
        const sel = window.getSelection();
        const root = this.$refs.body;
        if (!sel || sel.isCollapsed || !root.contains(sel.anchorNode) || !root.contains(sel.focusNode)) return;
        const range = sel.getRangeAt(0);
        const quote = range.toString().trim();
        if (!quote) return;
        const pre = document.createRange();
        pre.selectNodeContents(root);
        pre.setEnd(range.startContainer, range.startOffset);
        const lead = range.toString().length - range.toString().trimStart().length;
        this.draft = { quote: quote.slice(0, 600), offset: pre.toString().length + lead };
        this.text = '';
    },
    general() {
        this.draft = { quote: null, offset: null };
        this.text = '';
    },
    async save() {
        this.busy = true;
        try {
            await api('POST', this.api, { body: this.text, quote: this.draft.quote, start_offset: this.draft.offset });
            this.draft = null;
            window.getSelection()?.removeAllRanges();
            await this.load();
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },
    async remove(c) {
        await api('DELETE', `/comments/${c.id}`);
        await this.load();
    },
    async toggle(c) {
        await api('PATCH', `/comments/${c.id}`, { addressed: !c.addressed });
        await this.load();
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
        const ok = f === 'ALL' || (f === 'ACTION' ? action : f === 'FINAL' ? ['PENDING_FINAL_MODERATION', 'PENDING_EXTERNAL_MODERATION', 'PENDING_SECTION3_SIGNOFF'].includes(status) : status === f);
        return ok && text.toLowerCase().includes(this.q.toLowerCase());
    },
    get none() {
        return this.meta.length > 0 && !this.meta.some((_, i) => this.show(i));
    },
}));

Alpine.start();
