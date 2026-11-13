import Alpine from 'alpinejs';

window.Alpine = Alpine;

// installable app: the service worker only adds an offline page
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));

// ── iPhone app (Capacitor shell) ────────────────────────────────────────────
// Inside the app the native plugins are reachable through Capacitor; in a normal browser none of this runs.
const cap = window.Capacitor;
const inApp = !!cap?.isNativePlatform?.();
const plugins = {};
const nativePlugin = (name) => (inApp ? (plugins[name] ??= cap.registerPlugin ? cap.registerPlugin(name) : cap.Plugins?.[name]) : null);
const signedIn = () => !!document.querySelector('meta[name="dems-auth"]');

// Push notifications: ask permission once, then tell the server this phone's address.
(async () => {
    const push = nativePlugin('PushNotifications');
    if (!push || !signedIn()) return;
    try {
        const perm = await push.requestPermissions();
        if (perm.receive !== 'granted') return;
        push.addListener('registration', (t) => api('POST', '/devices', { token: t.value, platform: 'ios' }).catch(() => {}));
        push.addListener('pushNotificationActionPerformed', (n) => { const u = n.notification?.data?.url; if (u && u.startsWith('/')) window.location.href = u; });
        await push.register();
    } catch (e) { /* not available: the site still works without push */ }
})();

// Face ID lock: when switched on (Account page), the app asks for Face ID when it opens and when it comes back after a minute away.
const FACE_KEY = 'dems.faceid';
const faceOn = () => { try { return localStorage.getItem(FACE_KEY) === '1'; } catch { return false; } };
const faceSet = (on) => { try { on ? localStorage.setItem(FACE_KEY, '1') : localStorage.removeItem(FACE_KEY); } catch { /* storage unavailable */ } };
const faceAuth = () => nativePlugin('BiometricAuth').authenticate({ reason: 'Unlock Moderation', cancelTitle: 'Cancel', allowDeviceCredential: true });
function showFaceLock() {
    if (document.getElementById('faceid-lock')) return;
    const el = document.createElement('div');
    el.id = 'faceid-lock';
    el.setAttribute('style', 'position:fixed;inset:0;z-index:9999;background:#fff;display:grid;place-items:center;text-align:center;padding:24px;font-family:system-ui,-apple-system,sans-serif');
    el.innerHTML = '<div><div style="width:64px;height:64px;border-radius:16px;background:#006699;margin:0 auto 16px"></div><h1 style="margin:0 0 6px;font-size:24px;color:#262626">Moderation is locked</h1><p id="faceid-msg" style="margin:0 0 20px;color:#595959">Use Face ID to continue.</p><button id="faceid-btn" style="background:#006699;color:#fff;border:0;border-radius:999px;padding:12px 28px;font-size:17px">Unlock</button></div>';
    document.body.appendChild(el);
    const unlock = async () => {
        try { await faceAuth(); el.remove(); } catch (e) { document.getElementById('faceid-msg').textContent = 'Could not verify you. Try again.'; }
    };
    document.getElementById('faceid-btn').addEventListener('click', unlock);
    unlock();
}
if (inApp && nativePlugin('BiometricAuth')) {
    let hiddenAt = 0;
    const start = () => { if (signedIn() && faceOn()) showFaceLock(); };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) hiddenAt = Date.now();
        else if (signedIn() && faceOn() && Date.now() - hiddenAt > 60000) showFaceLock();
    });
}

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
    docs: cfg.docs, // [{ id, label, filename }]
    newLabel: '',
    sig: null,
    busy: null,
    error: '',
    saved: '',
    get total() {
        return +Object.values(this.form.weights).reduce((s, v) => s + (Number(v) || 0), 0).toFixed(2);
    },
    get missing() {
        const f = this.form;
        return !(f.period && f.year && f.heqf_level && String(f.subject_level).trim() && f.qualification.trim() && f.qualification_code.trim() && f.assessment_date) || (f.subminimum === 'YES' && !(Number(f.subminimum_percent) > 0));
    },
    get ready() {
        return this.sig && !this.missing && this.total === 100 && this.docs.length > 0;
    },
    payload() {
        const f = this.form;
        return {
            ...f,
            year: Number(f.year) || null,
            heqf_level: Number(f.heqf_level) || null,
            subminimum_percent: f.subminimum === 'YES' ? Number(f.subminimum_percent) || null : null,
            weights: Object.fromEntries(Object.entries(f.weights).map(([k, v]) => [k, Number(v) || 0])),
        };
    },
    addDoc(file) {
        return run(this, 'doc', async () => {
            const label = this.newLabel.trim() || (this.docs.length ? file.name.replace(/\.[^.]+$/, '') : 'Assessment paper');
            const f = new FormData();
            f.set('kind', 'PAPER');
            f.set('label', label);
            f.set('file', file);
            const { attachment } = await api('POST', `/assessments/${this.id}/attachments`, f);
            const d = { id: attachment.id, label: attachment.label, filename: attachment.filename, version: attachment.version };
            const i = this.docs.findIndex((x) => x.label.toLowerCase() === d.label.toLowerCase());
            if (i >= 0) this.docs.splice(i, 1, d);
            else this.docs.push(d);
            this.newLabel = '';
        });
    },
    removeDoc(d) {
        if (!confirm(`Remove “${d.label}” from this submission?`)) return;
        return run(this, 'doc', async () => {
            await api('DELETE', `/assessments/${this.id}/attachments/${d.id}`);
            this.docs = this.docs.filter((x) => x.id !== d.id);
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
    linked: cfg.linkedHeaders && cfg.linkedHeaders.length ? cfg.number : null, // the assessment's own marks-sheet column
    linkedFound: false,
    autoPick() {
        this.column = 0;
        const only = this.cols.filter((c) => c.nonBlank > 0);
        const hit = this.linked ? this.cols.find((c) => cfg.linkedHeaders.includes(c.header.trim().toUpperCase()) && c.nonBlank > 0) : null;
        this.linkedFound = !!hit;
        this.column = hit ? hit.index : this.linked ? 0 : only.length === 1 ? only[0].index : 0;
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

const pendingLocked = {}; // encrypted bytes, kept out of Alpine's reactive state

// ── Word document review: render the .docx in the page and comment on selected passages ──
Alpine.data('wordReview', (cfg) => {
  let rootEl; // kept outside Alpine's state; the unlock form (inside an x-if) is removed after unlocking, so $refs/$root can't be used then
  return {
    url: cfg.url,
    api: cfg.comments,
    loading: true,
    error: '',
    comments: [],
    can: { comment: false, address: false, reply: false },
    draft: null,
    text: '',
    replying: null,
    replyText: '',
    busy: false,
    html: '',
    active: null,
    locked: false,
    password: '',
    unlocking: false,
    mammoth: null,
    purify: null,
    async init() {
        rootEl = this.$root;
        try {
            const [{ default: mammoth }, { default: DOMPurify }] = await Promise.all([import('mammoth/mammoth.browser'), import('dompurify')]);
            this.mammoth = mammoth;
            this.purify = DOMPurify;
            const buf = await (await fetch(this.url, { credentials: 'same-origin' })).arrayBuffer();
            const { isOle } = await import('./officecrypto.js');
            if (isOle(new Uint8Array(buf))) {
                // password-protected Word file: ask the reviewer for the password (it stays in this browser)
                pendingLocked[this.url] = buf;
                this.locked = true;
                return;
            }
            await this.show(buf);
        } catch (e) {
            this.error = 'This document could not be shown in the browser. Download it to read it.';
        } finally {
            this.loading = false;
        }
    },
    async show(buf) {
        const out = await this.mammoth.convertToHtml({ arrayBuffer: buf });
        this.html = this.purify.sanitize(out.value, { USE_PROFILES: { html: true } });
        await this.load(false);
        await this.$nextTick();
        this.paint();
        setTimeout(() => this.paint(), 50);
        this.$watch('comments', () => setTimeout(() => this.paint(), 0), { deep: false });
    },
    async unlock() {
        this.unlocking = true;
        this.error = '';
        try {
            const { decryptDocx, WrongPassword } = await import('./officecrypto.js');
            let plain;
            try {
                plain = await decryptDocx(pendingLocked[this.url], this.password);
            } catch (e) {
                this.error = e instanceof WrongPassword ? e.message : (e.message || 'Could not open the file.');
                return;
            }
            this.password = '';
            this.locked = false;
            delete pendingLocked[this.url];
            try {
                await this.show(plain);
            } catch (e) {
                this.error = 'Unlocked, but the document could not be shown: ' + (e.message || e);
            }
        } finally {
            this.unlocking = false;
        }
    },
    async load(repaint = true) {
        const r = await api('GET', this.api);
        this.comments = r.comments;
        this.can = r.can;
    },
    paint() {
        const root = rootEl.querySelector(".doc-body");
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
        rootEl.querySelector(`.doc-body [data-cid="${c.id}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    },
    select() {
        if (!this.can.comment) return;
        const sel = window.getSelection();
        const root = rootEl.querySelector(".doc-body");
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
    async sendReply(c) {
        if (this.replyText.trim().length < 2) return;
        this.busy = true;
        try {
            await api('POST', `/comments/${c.id}/replies`, { body: this.replyText });
            this.replying = null;
            this.replyText = '';
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
  };
});


// ── PDF review: pages are drawn in the browser (pdf.js) with a selectable text layer, so reviewers can comment on a passage ──
Alpine.data('pdfReview', (cfg) => {
  let rootEl, pdf, pdfjs, observer;
  const rendered = new Set();
  return {
    url: cfg.url,
    api: cfg.comments,
    loading: true,
    error: '',
    pages: 0,
    comments: [],
    can: { comment: false, address: false, reply: false },
    draft: null,
    text: '',
    replying: null,
    replyText: '',
    busy: false,
    active: null,
    async init() {
        rootEl = this.$root;
        try {
            await import('pdfjs-dist/web/pdf_viewer.css');
            pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
            const worker = (await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url')).default;
            pdfjs.GlobalWorkerOptions.workerSrc = worker;
            const data = new Uint8Array(await (await fetch(this.url, { credentials: 'same-origin' })).arrayBuffer());
            pdf = await pdfjs.getDocument({ data }).promise;
            this.pages = pdf.numPages;
            await this.load();
            await this.$nextTick();
            this.layout();
            this.$watch('comments', () => setTimeout(() => this.paintAll(), 0));
        } catch (e) {
            this.error = 'This PDF could not be shown in the browser. Download it to read it.';
        } finally {
            this.loading = false;
        }
    },
    async load() {
        const r = await api('GET', this.api);
        this.comments = r.comments;
        this.can = r.can;
    },
    layout() {
        const holder = rootEl.querySelector('.pdf-pages');
        holder.innerHTML = '';
        observer = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) this.renderPage(+en.target.dataset.page); }), { root: holder, rootMargin: '400px' });
        for (let n = 1; n <= this.pages; n++) {
            const div = document.createElement('div');
            div.className = 'pdf-page';
            div.dataset.page = n;
            div.style.minHeight = '480px';
            holder.appendChild(div);
            observer.observe(div);
        }
    },
    async renderPage(n) {
        if (rendered.has(n)) return;
        rendered.add(n);
        const div = rootEl.querySelector(`.pdf-page[data-page="${n}"]`);
        const page = await pdf.getPage(n);
        const base = page.getViewport({ scale: 1 });
        const width = Math.max(280, Math.min(div.parentElement.clientWidth - 24, 900));
        const viewport = page.getViewport({ scale: width / base.width });
        div.style.minHeight = '';
        div.style.width = viewport.width + 'px';
        div.style.height = viewport.height + 'px';
        div.style.setProperty('--scale-factor', viewport.scale);
        const canvas = document.createElement('canvas');
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(viewport.width * ratio);
        canvas.height = Math.floor(viewport.height * ratio);
        canvas.style.width = viewport.width + 'px';
        canvas.style.height = viewport.height + 'px';
        div.appendChild(canvas);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport, transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null }).promise;
        const layer = document.createElement('div');
        layer.className = 'textLayer';
        div.appendChild(layer);
        const tl = new pdfjs.TextLayer({ textContentSource: page.streamTextContent(), container: layer, viewport });
        await tl.render();
        const tag = document.createElement('span');
        tag.className = 'pdf-page-tag';
        tag.textContent = `Page ${n} of ${this.pages}`;
        div.appendChild(tag);
        if (this.can.comment) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pdf-page-btn';
            b.textContent = '+ Comment on page';
            b.addEventListener('click', () => { this.draft = { quote: null, offset: null, page: n }; this.text = ''; });
            div.appendChild(b);
        }
        this.paintPage(n);
    },
    pageString(layer) {
        return [...layer.querySelectorAll('span:not(.markedContent):not(.pdf-page-tag)')].map((s) => s.textContent).join('');
    },
    paintAll() {
        rendered.forEach((n) => this.paintPage(n));
    },
    paintPage(n) {
        const div = rootEl.querySelector(`.pdf-page[data-page="${n}"]`);
        const layer = div?.querySelector('.textLayer');
        if (!layer) return;
        layer.querySelectorAll('mark[data-cid]').forEach((m) => m.replaceWith(...m.childNodes));
        layer.normalize();
        const full = this.pageString(layer);
        this.comments.filter((c) => c.page === n && c.quote).forEach((c) => {
            const all = [];
            for (let i = full.indexOf(c.quote); i >= 0; i = full.indexOf(c.quote, i + 1)) all.push(i);
            const want = c.offset || 0;
            const at = c.offset !== null && full.substr(c.offset, c.quote.length) === c.quote ? c.offset : all.length ? all.reduce((b, i) => (Math.abs(i - want) < Math.abs(b - want) ? i : b)) : -1;
            c.anchored = at >= 0;
            if (at >= 0) this.mark(layer, at, at + c.quote.length, c);
        });
    },
    mark(layer, from, to, c) {
        let pos = 0;
        for (const sp of [...layer.querySelectorAll('span:not(.markedContent):not(.pdf-page-tag)')]) {
            const node = sp.firstChild && sp.firstChild.nodeType === 3 ? sp.firstChild : null;
            const len = sp.textContent.length;
            const s = Math.max(from, pos), e = Math.min(to, pos + len);
            if (node && s < e) {
                const r = document.createRange();
                r.setStart(node, s - pos);
                r.setEnd(node, e - pos);
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
        this.$nextTick(() => document.getElementById('pc-' + id)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
    },
    async goTo(c) {
        this.active = c.id;
        if (!c.page) return;
        const div = rootEl.querySelector(`.pdf-page[data-page="${c.page}"]`);
        const holder = rootEl.querySelector('.pdf-pages');
        if (div) holder.scrollTo({ top: div.offsetTop - holder.offsetTop - 8, behavior: 'smooth' });
    },
    select() {
        if (!this.can.comment) return;
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        const startEl = range.startContainer.nodeType === 3 ? range.startContainer.parentElement : range.startContainer;
        const layer = startEl?.closest?.('.textLayer');
        if (!layer || !rootEl.contains(layer)) return;
        const quote = range.toString().replace(/\s+/g, ' ').trim();
        if (!quote) return;
        const page = +layer.closest('.pdf-page').dataset.page;
        // offset of the selection start inside this page's text
        let offset = 0;
        for (const sp of layer.querySelectorAll('span:not(.markedContent):not(.pdf-page-tag)')) {
            if (sp.contains(range.startContainer)) {
                const pre = document.createRange();
                pre.selectNodeContents(sp);
                pre.setEnd(range.startContainer, range.startOffset);
                offset += pre.toString().length;
                break;
            }
            offset += sp.textContent.length;
        }
        this.draft = { quote: quote.slice(0, 600), offset, page };
        this.text = '';
    },
    general() {
        this.draft = { quote: null, offset: null, page: null };
        this.text = '';
    },
    async save() {
        this.busy = true;
        try {
            await api('POST', this.api, { body: this.text, quote: this.draft.quote, start_offset: this.draft.offset, page: this.draft.page });
            this.draft = null;
            window.getSelection()?.removeAllRanges();
            await this.load();
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },
    async sendReply(c) {
        if (this.replyText.trim().length < 2) return;
        this.busy = true;
        try {
            await api('POST', `/comments/${c.id}/replies`, { body: this.replyText });
            this.replying = null;
            this.replyText = '';
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
  };
});

// ── Account page: Face ID switch (only shown inside the iPhone app) ──────────
Alpine.data('faceId', () => ({
    native: inApp && !!nativePlugin('BiometricAuth'),
    available: false,
    on: faceOn(),
    msg: '',
    async init() {
        if (!this.native) return;
        try { this.available = !!(await nativePlugin('BiometricAuth').checkBiometry()).isAvailable; } catch { this.available = false; }
        if (!this.available) this.msg = 'Face ID is not set up on this iPhone (Settings → Face ID & Passcode).';
    },
    async toggle() {
        if (this.on) { faceSet(false); this.on = false; this.msg = 'Face ID lock is off.'; return; }
        try { await faceAuth(); faceSet(true); this.on = true; this.msg = 'Face ID lock is on.'; } catch (e) { this.msg = 'Face ID could not be verified, so the lock was not switched on.'; }
    },
}));

// ── Chrome ──────────────────────────────────────────────────────────────────
Alpine.data('docViewer', (first) => ({ tab: first }));

// Click-to-collapse boxes; each remembers its state on this device.
Alpine.data('fold', (key, open = true) => ({
    open,
    init() {
        try {
            const v = localStorage.getItem('dems.fold.' + key);
            if (v !== null) this.open = v === '1';
        } catch (e) {}
    },
    toggle() {
        this.open = !this.open;
        try { localStorage.setItem('dems.fold.' + key, this.open ? '1' : '0'); } catch (e) {}
    },
}));

Alpine.data('sidePanel', () => ({
    show: true,
    init() {
        try { this.show = localStorage.getItem('dems.side') !== '0'; } catch (e) {}
    },
    toggle() {
        this.show = !this.show;
        try { localStorage.setItem('dems.side', this.show ? '1' : '0'); } catch (e) {}
    },
}));

// Dean report: write / edit the narrative. The text lives in a shared store so the PDF / Word buttons always send what is on screen.
Alpine.store('dean', { sections: {} });
Alpine.data('deanNarrative', (cfg) => ({
    busy: '',
    msg: '',
    error: '',
    init() {
        Alpine.store('dean').sections = cfg.sections;
    },
    get sections() {
        return Alpine.store('dean').sections;
    },
    async generate(mode) {
        this.busy = mode;
        this.error = '';
        this.msg = '';
        try {
            const r = await api('POST', cfg.generate + location.search, { mode });
            Object.assign(Alpine.store('dean').sections, r.sections);
            await api('POST', cfg.save, { sections: Alpine.store('dean').sections }); // keep it for the preview and for the download buttons
            this.msg = mode === 'ai' ? 'Written by the AI from your figures. Read it and edit anything; the PDF and Word buttons include exactly this text.' : 'Draft written from your figures. Edit it; the PDF and Word buttons include exactly this text.';
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = '';
        }
    },
    async save(clear = false) {
        this.busy = 'save';
        this.error = '';
        try {
            await api('POST', cfg.save, { sections: clear ? {} : Alpine.store('dean').sections });
            location.reload();
        } catch (e) {
            this.error = e.message;
            this.busy = '';
        }
    },
}));

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
