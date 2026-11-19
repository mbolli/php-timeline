// Timeline utility functions exposed to window for Datastar
// Functions return values; signal updates happen in data-on:* attributes

import '@mbolli/datastar-attribute-on-keys';

declare global {
    interface Window {
        timeline: typeof timeline;
    }
}

interface WheelResult {
    zoom: number;
    panX: number;
}

interface ResizeState {
    itemId: number;
    groupId: number;
    handle: 'start' | 'end';
    originalStartDate: string;
    originalEndDate: string;
    originalLeft: number;
    originalWidth: number;
    startX: number;
    itemElement: HTMLElement;
    tooltip: HTMLElement | null;
}

interface SendResult {
    ok: boolean;
    status: number;
    error: string;
    fields: Record<string, string>;
    data: Record<string, unknown>;
}

interface HistoryEntry {
    label: string;
    undone: string;
    undo: () => Promise<SendResult>;
}

interface ToastAction {
    label: string;
    run: () => void;
}

interface PendingConfirm {
    url: string;
    parent: HTMLDialogElement | null;
}

const MONTH_WIDTH = 20;
const LOCK_KEY = 'timeline.locked';
const HISTORY_LIMIT = 50;
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

let resizeState: ResizeState | null = null;
// Pans the app has started and not finished; scroll events in between are its own, not the user's
let pansInFlight = 0;
let justFinishedResize = false;
let draggedGroupId: string | null = null;
let pendingConfirm: PendingConfirm | null = null;
// Where focus goes when an edit dialog closes: the item it was opened from, else that item's group
let returnFocus: { itemId: string | null; trackId: string | null } = { itemId: null, trackId: null };
// The group the user last saw selected in the add-item form, to survive live updates of the list
let chosenGroup = '';
let locked = false;
// Changes this tab made, newest last, each with the command that reverts it
const history: HistoryEntry[] = [];
let undoQueue: Promise<unknown> = Promise.resolve();
// Commands still on their way: an undo waits for them, so it reverts the change the user last saw
const commandsInFlight = new Set<Promise<SendResult>>();
// What an edit dialog's form held when it opened: the state an undo of its save goes back to
const formSnapshots = new WeakMap<HTMLFormElement, FormData>();

const timeline = {
    /**
     * Handle wheel zoom - returns new zoom and panX values
     * Only zooms when Ctrl/Cmd is held, returns null if not zooming
     */
    handleWheel(e: WheelEvent, currentZoom: number, currentPanX: number): WheelResult | null {
        if (!e.ctrlKey && !e.metaKey) return null;

        e.preventDefault();

        const wrapper = e.currentTarget as HTMLElement;
        const mouseX = e.clientX - wrapper.getBoundingClientRect().left;
        // Follow the size of the gesture: a mouse notch is about 20%, a trackpad pinch sends many small steps
        const delta = e.deltaMode === 1 ? e.deltaY * 33 : e.deltaY;
        const factor = Math.exp(-Math.max(-150, Math.min(150, delta)) * 0.002);

        return this.zoomAround(mouseX, factor, currentZoom, currentPanX);
    },

    /**
     * Zoom by a factor around the middle of the visible timeline (toolbar buttons, keyboard)
     */
    zoomBy(factor: number, currentZoom: number, currentPanX: number): WheelResult {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement | null;
        const labelWidth = this.getLabelWidth();
        const width = wrapper?.clientWidth ?? 800;

        return this.zoomAround(labelWidth + (width - labelWidth) / 2, factor, currentZoom, currentPanX);
    },

    /**
     * Keep the timeline point under x (pixels from the wrapper's left edge) in place while zooming
     */
    zoomAround(x: number, factor: number, currentZoom: number, currentPanX: number): WheelResult {
        const newZoom = this.clampZoom(currentZoom * factor);
        const labelWidth = this.getLabelWidth();
        // panX is the negative scroll position, so this is the timeline position (unzoomed pixels) under x
        const point = (x - currentPanX - labelWidth) / currentZoom;

        return { zoom: newZoom, panX: Math.min(0, x - labelWidth - point * newZoom) };
    },

    /**
     * Scroll the timeline to a pan value, again on the next frame in case a zoom has not widened it yet
     */
    applyPan(wrapper: HTMLElement, panX: number): void {
        pansInFlight += 1;
        wrapper.scrollLeft = -panX;
        requestAnimationFrame(() => {
            wrapper.scrollLeft = -panX;
            pansInFlight -= 1;
        });
    },

    /**
     * The pan value of a scroll the user made (trackpad, scrollbar, focus), or null while the app itself
     * is scrolling: before a zoom has widened the timeline, the browser clamps the position for a frame
     */
    panFromScroll(wrapper: HTMLElement): number | null {
        return pansInFlight > 0 ? null : -wrapper.scrollLeft;
    },

    /**
     * Limit a pan value to the scrollable range (panX is the negative scroll position)
     */
    clampPan(panX: number): number {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement | null;
        const max = wrapper ? wrapper.scrollWidth - wrapper.clientWidth : 0;

        return Math.min(0, Math.max(-max, panX));
    },

    /**
     * Width of the group label column, which container queries may change
     */
    getLabelWidth(): number {
        return (document.querySelector('.ruler-corner') as HTMLElement | null)?.offsetWidth ?? 0;
    },

    /**
     * Width of the timeline area that is visible next to the group labels
     */
    getVisibleWidth(): number {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement | null;

        return Math.max(1, (wrapper?.clientWidth ?? 800) - this.getLabelWidth());
    },

    /**
     * Width of all years at zoom 1
     */
    getBaseWidth(): number {
        return document.querySelectorAll('.year').length * 12 * MONTH_WIDTH;
    },

    /**
     * Position of the current month at zoom 1, which the server renders as --now-position
     */
    getNowPosition(): number {
        const content = document.getElementById('timeline-content');

        return parseFloat(content?.style.getPropertyValue('--now-position') || '0');
    },

    /**
     * Pan value that puts a position (pixels at zoom 1) at a fraction of the visible width,
     * for a zoom the layout may not have yet
     */
    panTo(position: number, zoom: number, fraction: number): number {
        const visible = this.getVisibleWidth();
        const max = Math.max(0, this.getBaseWidth() * zoom - visible);

        return 0 - Math.min(max, Math.max(0, position * zoom - visible * fraction));
    },

    /**
     * Pan value that shows today at 70% of the visible width
     */
    todayPan(zoom: number): number {
        return this.panTo(this.getNowPosition(), zoom, 0.7);
    },

    /**
     * The view the page opens with and Reset returns to: zoom 1, scrolled to today
     */
    home(): WheelResult {
        return { zoom: 1, panX: this.todayPan(1) };
    },

    /**
     * Zoom out until every year is visible; where the minimum zoom is not enough, show the recent end
     */
    fit(): WheelResult {
        const base = Math.max(1, this.getBaseWidth());
        const zoom = this.clampZoom(this.getVisibleWidth() / base);

        return { zoom, panX: base * zoom <= this.getVisibleWidth() + 1 ? 0 : this.todayPan(zoom) };
    },

    /**
     * Keys that on-keys cannot bind because of their names: Ctrl/Cmd with + or - returns a zoom factor,
     * and / moves to the search field
     */
    zoomKeyFactor(e: KeyboardEvent): number | null {
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && this.canUseKeys()) {
            e.preventDefault();
            (document.getElementById('search') as HTMLInputElement | null)?.focus();

            return null;
        }
        if (!(e.ctrlKey || e.metaKey) || e.altKey) return null;

        const factor = e.key === '+' || e.key === '=' ? 1.2 : e.key === '-' || e.key === '_' ? 1 / 1.2 : null;
        if (factor) e.preventDefault();

        return factor;
    },

    /**
     * Single-key shortcuts work only when nobody is typing and no dialog is open
     */
    canUseKeys(): boolean {
        const active = document.activeElement as HTMLElement | null;
        if (active?.closest('input, textarea, select, [contenteditable]')) return false;

        return !document.querySelector('dialog[open]');
    },

    /**
     * Arrow keys pan unless they are moving the focus through the items of a group
     */
    canPan(): boolean {
        const active = document.activeElement as HTMLElement | null;

        return this.canUseKeys() && !active?.closest('.timeline-item, .track-info');
    },

    /**
     * Check if the event started on a control that should not start panning
     */
    isControl(e: MouseEvent | TouchEvent): boolean {
        return !!(e.target as HTMLElement).closest('button, a, input, select, textarea, .drag-handle');
    },

    /**
     * Calculate pan delta from drag
     */
    getDragDelta(e: MouseEvent, lastMouseX: number): number {
        return e.clientX - lastMouseX;
    },

    /**
     * Check if event target is a timeline item (should not drag)
     */
    isTimelineItem(e: MouseEvent | TouchEvent): boolean {
        return !!(e.target as HTMLElement).closest('.timeline-item');
    },

    // --- Feedback -------------------------------------------------------------------------------------

    /**
     * Show a short message at the bottom of the screen; the container is a live region
     */
    toast(message: string, kind: 'info' | 'error' = 'info', action?: ToastAction): void {
        const host = document.getElementById('toasts');
        if (!host) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${kind}`;
        const text = document.createElement('span');
        text.className = 'toast-message';
        text.textContent = message;
        toast.append(text);
        if (action) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'toast-action';
            button.textContent = action.label;
            button.addEventListener('click', () => {
                toast.remove();
                action.run();
            }, { once: true });
            toast.append(button);
        }
        host.replaceChildren(toast);
        // Re-open the popover so it sits above a dialog that was opened in the meantime
        if (host.matches(':popover-open')) host.hidePopover();
        host.showPopover();
        setTimeout(() => {
            toast.remove();
            if (host.childElementCount === 0 && host.matches(':popover-open')) host.hidePopover();
        }, kind === 'error' ? 8000 : action ? 6000 : 3000);
    },

    /**
     * Send a command and report how it went instead of throwing
     */
    send(method: string, url: string, body?: BodyInit, headers?: Record<string, string>): Promise<SendResult> {
        const request = this.request(method, url, body, headers);
        commandsInFlight.add(request);
        void request.finally(() => commandsInFlight.delete(request));

        return request;
    },

    async request(method: string, url: string, body?: BodyInit, headers?: Record<string, string>): Promise<SendResult> {
        try {
            const response = await fetch(url, { method, body, headers });
            if (response.ok) {
                const data = response.headers.get('Content-Type')?.includes('json')
                    ? await response.json().catch(() => ({})) as Record<string, unknown>
                    : {};

                return { ok: true, status: response.status, error: '', fields: {}, data };
            }

            const data = await response.json().catch(() => ({})) as { error?: string; fields?: Record<string, string> };

            return {
                ok: false,
                status: response.status,
                error: data.error ?? `The server answered with an error (${response.status}).`,
                fields: data.fields ?? {},
                data: {},
            };
        } catch {
            return { ok: false, status: 0, error: 'The server could not be reached.', fields: {}, data: {} };
        }
    },

    // --- Edit lock ------------------------------------------------------------------------------------

    /**
     * Without a stored choice, touch screens start locked: a stray drag there changes dates
     */
    storedLock(): boolean {
        try {
            const stored = localStorage.getItem(LOCK_KEY);
            if (stored === '1' || stored === '0') return stored === '1';
        } catch {
            // Storage is blocked: use the default
        }
        try {
            return matchMedia('(pointer: coarse)').matches;
        } catch {
            return false;
        }
    },

    isLocked(): boolean {
        return locked;
    },

    setLocked(value: boolean, remember = true): void {
        locked = value;
        document.documentElement.classList.toggle('is-locked', value);
        const button = document.getElementById('lock-toggle');
        button?.setAttribute('aria-pressed', String(value));
        button?.setAttribute('title', value ? 'Editing is locked. Unlock (L)' : 'Lock editing (L)');
        if (!remember) return;
        try {
            localStorage.setItem(LOCK_KEY, value ? '1' : '0');
        } catch {
            // Not remembered; the lock still holds for this page
        }
    },

    toggleLock(): void {
        this.setLocked(!locked);
    },

    /**
     * The L key, without modifiers, outside fields and dialogs
     */
    lockKey(e: KeyboardEvent): void {
        if (e.ctrlKey || e.metaKey || e.altKey || !this.canUseKeys()) return;
        e.preventDefault();
        this.toggleLock();
    },

    /**
     * True while locked, with a hint for the keyboard shortcut that tried to edit
     */
    blockedByLock(): boolean {
        if (locked) this.toast('Editing is locked. Press L to unlock.');

        return locked;
    },

    /**
     * Turn an edit dialog the server sent into a read-only view: the fields stay readable, nothing can be saved
     */
    makeReadOnly(dialog: HTMLDialogElement): void {
        const form = dialog.querySelector('form');
        if (!form) return;

        dialog.classList.add('is-readonly');
        form.dataset.readonly = '';
        const heading = dialog.querySelector('.modal-header h2');
        if (heading) heading.textContent = heading.textContent === 'Edit group' ? 'Group details' : 'Item details';

        form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[type="text"], input[type="month"], textarea').forEach(field => {
            field.readOnly = true;
            field.removeAttribute('autofocus');
        });
        form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLButtonElement>('select, input[type="color"], input[type="checkbox"], .swatch').forEach(field => {
            field.disabled = true;
        });
        form.querySelectorAll('[type="submit"], .btn-danger, .field:has(.btn-row)').forEach(element => element.remove());

        const close = form.querySelector('.modal-actions .btn') as HTMLButtonElement | null;
        if (close) {
            close.textContent = 'Close';
            close.autofocus = true;
        }
        const note = document.createElement('p');
        note.className = 'readonly-note';
        note.textContent = 'Editing is locked';
        form.querySelector('.modal-actions')?.prepend(note);
    },

    // --- Undo -----------------------------------------------------------------------------------------

    /**
     * Remember a change this tab made and offer to undo it
     */
    record(label: string, undo: () => Promise<SendResult>, undone = `Undone: ${label}`): void {
        const entry: HistoryEntry = { label, undone, undo };
        history.push(entry);
        if (history.length > HISTORY_LIMIT) history.shift();
        this.toast(label, 'info', { label: 'Undo', run: () => { void this.undo(entry); } });
    },

    /**
     * Revert the latest change, or the given one, through the normal commands, so every tab sees it.
     * The change leaves the history even when the server refuses, for example because it was deleted elsewhere.
     */
    undo(entry?: HistoryEntry): Promise<boolean> {
        // Quick presses queue up, each undoing one step
        const next = undoQueue.then(() => this.undoNow(entry));
        undoQueue = next.catch(() => false);

        return next;
    },

    async undoNow(entry?: HistoryEntry): Promise<boolean> {
        while (commandsInFlight.size > 0) {
            await Promise.allSettled([...commandsInFlight]);
            // Let the code that sent the command record it first
            await new Promise(resolve => setTimeout(resolve));
        }

        const target = entry ?? history.at(-1);
        const index = target ? history.indexOf(target) : -1;
        if (!target || index === -1) {
            // The toast's button of a change that Ctrl+Z has undone already
            if (!entry) this.toast('Nothing to undo');

            return false;
        }

        history.splice(index, 1);
        const result = await target.undo();

        if (result.ok) {
            this.toast(target.undone);
        } else {
            const reasons = Object.values(result.fields);
            const reason = result.status === 404
                ? 'It was deleted in the meantime.'
                : reasons.length > 0 ? reasons.join('. ') + '.' : result.error;
            this.toast(`Could not undo: ${target.label}. ${reason}`, 'error');
        }

        return result.ok;
    },

    /**
     * Ctrl/Cmd+Z, except in fields and dialogs, where it belongs to the field
     */
    undoKey(e: KeyboardEvent): void {
        if (e.shiftKey || e.altKey || !this.canUseKeys()) return;
        e.preventDefault();
        void this.undo();
    },

    // --- Forms and dialogs ----------------------------------------------------------------------------

    /**
     * Submit a dialog form. The dialog closes only once the server has accepted the input;
     * otherwise it stays open with the input intact and says what went wrong.
     */
    async submitForm(form: HTMLFormElement, method: string, url: string): Promise<boolean> {
        if (form.getAttribute('aria-busy') === 'true' || locked || form.hasAttribute('data-readonly')) return false;

        const submit = form.querySelector('[type="submit"]') as HTMLButtonElement | null;
        form.setAttribute('aria-busy', 'true');
        if (submit) submit.disabled = true;
        this.showFormError(form, null);

        const before = formSnapshots.get(form);
        const result = await this.send(method, url, new FormData(form));

        form.removeAttribute('aria-busy');
        if (submit) submit.disabled = false;

        if (!result.ok) {
            this.showFormError(form, result);

            return false;
        }

        this.recordForm(form, method, url, before, result);
        (form.closest('dialog') as HTMLDialogElement | null)?.close();
        if (form.hasAttribute('data-reset')) {
            form.reset();
            this.syncColorFields(form);
            chosenGroup = (document.getElementById('add-item-group') as HTMLSelectElement | null)?.value ?? '';
        }

        return true;
    },

    /**
     * Record a saved form: a create is undone by deleting the new id, an edit by saving the fields it opened with
     */
    recordForm(form: HTMLFormElement, method: string, url: string, before: FormData | undefined, result: SendResult): void {
        const data = new FormData(form);
        const isGroup = url.startsWith('/cmd/groups');
        const name = String(data.get(isGroup ? 'name' : 'title') ?? '').trim();
        const what = `${isGroup ? 'group ' : ''}“${name}”`;

        if (method === 'POST') {
            const id = Number(result.data.id);
            if (id > 0) this.record(`Added ${what}`, () => this.send('DELETE', `${url}/${id}`));
        } else if (method === 'PUT' && before) {
            this.record(`Changed ${what}`, () => this.send('PUT', url, before));
        }
    },

    /**
     * Put a failed request's reasons into the form's alert and mark the fields; null clears both
     */
    showFormError(form: HTMLElement, result: SendResult | null): void {
        const alert = form.querySelector('.form-error') as HTMLElement | null;
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        if (!alert) return;

        if (!result) {
            alert.hidden = true;
            alert.textContent = '';

            return;
        }

        const reasons = Object.values(result.fields);
        for (const name of Object.keys(result.fields)) {
            form.querySelector(`[name="${name}"]`)?.setAttribute('aria-invalid', 'true');
        }

        alert.textContent = result.status === 0
            ? 'The server could not be reached, so nothing was saved. Your input is still here; try again.'
            : `Not saved. ${reasons.length > 0 ? reasons.join('. ') + '.' : result.error}`;
        alert.hidden = false;
    },

    /**
     * The color input is off while "same as the group" is ticked, so no color is sent
     */
    syncColorFields(root: HTMLElement): void {
        root.querySelectorAll('.color-picker').forEach(picker => {
            const inherit = picker.querySelector('.color-inherit') as HTMLInputElement | null;
            const input = picker.querySelector('input[type="color"]') as HTMLInputElement | null;
            if (inherit && input) input.disabled = inherit.checked;
        });
    },

    /**
     * A preset swatch sets the color and switches off "same as the group"
     */
    pickColor(swatch: HTMLElement, color: string): void {
        const picker = swatch.closest('.color-picker');
        const inherit = picker?.querySelector('.color-inherit') as HTMLInputElement | null;
        const input = picker?.querySelector('input[type="color"]') as HTMLInputElement | null;
        if (inherit) inherit.checked = false;
        if (input) {
            input.disabled = false;
            input.value = color;
        }
    },

    /**
     * Open an edit dialog the server sent, remembering what had the focus
     */
    openDialog(dialog: HTMLDialogElement): void {
        const active = document.activeElement as HTMLElement | null;
        returnFocus = {
            itemId: active?.closest('.timeline-item')?.id ?? null,
            trackId: active?.closest('.track')?.id ?? null,
        };
        const form = dialog.querySelector('form');
        if (locked) {
            this.makeReadOnly(dialog);
        } else if (form) {
            formSnapshots.set(form, new FormData(form));
        }
        dialog.showModal();
    },

    /**
     * Remove a closed edit dialog and give the focus back to where it came from
     */
    closeDialog(dialog: HTMLDialogElement): void {
        dialog.remove();

        const { itemId, trackId } = returnFocus;
        const target = (itemId && document.getElementById(itemId))
            || (trackId && document.querySelector(`#${trackId} .track-info`));
        (target as HTMLElement | null)?.focus();
    },

    /**
     * Ask before deleting, in the page's own dialog on top of the edit dialog
     */
    confirmDelete(trigger: HTMLElement, url: string, message: string): void {
        const dialog = document.getElementById('confirm-dialog') as HTMLDialogElement | null;
        if (!dialog || locked) return;

        pendingConfirm = { url, parent: trigger.closest('dialog') };
        (dialog.querySelector('.confirm-message') as HTMLElement).textContent = message;
        this.showFormError(dialog, null);
        dialog.showModal();
    },

    /**
     * Carry out the confirmed delete; both dialogs close only when the server has done it
     */
    async runConfirmed(): Promise<void> {
        const dialog = document.getElementById('confirm-dialog') as HTMLDialogElement | null;
        if (!dialog || !pendingConfirm) return;

        const { url, parent } = pendingConfirm;
        const result = await this.send('DELETE', url);
        if (!result.ok) {
            this.showFormError(dialog, result);

            return;
        }

        pendingConfirm = null;
        // The deleted item cannot take the focus back, its group can
        returnFocus.itemId = null;
        dialog.close();
        parent?.close();
    },

    /**
     * Live updates re-render the group list of the add-item form. Keep the group the user had selected,
     * and say so when another window deleted it, instead of silently filing the item elsewhere.
     */
    guardGroupSelect(): void {
        const select = document.getElementById('add-item-group') as HTMLSelectElement | null;
        if (!select) return;

        const values = [...select.options].map(option => option.value);
        const dialog = select.closest('dialog') as HTMLDialogElement | null;

        if (chosenGroup !== '' && values.includes(chosenGroup)) {
            select.value = chosenGroup;
        } else if (chosenGroup !== '' && dialog?.open) {
            select.selectedIndex = -1;
            select.setAttribute('aria-invalid', 'true');
            const alert = dialog.querySelector('.form-error') as HTMLElement | null;
            if (alert) {
                alert.textContent = 'The group you had chosen was deleted in another window. Choose another group.';
                alert.hidden = false;
            }
            chosenGroup = '';
        } else {
            chosenGroup = select.value;
        }
    },

    // --- Dates ----------------------------------------------------------------------------------------

    /**
     * Months since year 0, for arithmetic on YYYY-MM strings
     */
    toMonths(date: string): number {
        const [year, month] = date.split('-').map(Number);

        return year * 12 + (month - 1);
    },

    fromMonths(months: number): string {
        return `${Math.floor(months / 12)}-${String((months % 12) + 1).padStart(2, '0')}`;
    },

    /**
     * "2020-03" as "Mar 2020"; an empty end date is an ongoing item
     */
    formatMonth(date: string): string {
        if (!date) return 'now';

        return `${MONTH_NAMES[this.toMonths(date) % 12]} ${date.slice(0, 4)}`;
    },

    /**
     * Give an item new dates on screen: position, width and data attributes
     */
    applyDates(item: HTMLElement, startDate: string, endDate: string): void {
        const first = this.getStartYear() * 12;
        const start = this.toMonths(startDate);
        const end = this.toMonths(endDate || this.getTodayDate());

        item.style.setProperty('--item-left', String((start - first) * MONTH_WIDTH));
        item.style.setProperty('--item-width', String(Math.max(1, end - start + 1) * MONTH_WIDTH));
        item.dataset.startDate = startDate;
        item.dataset.endDate = endDate;
    },

    /**
     * Save new dates for an item; on failure put the old ones back and say so
     */
    async saveDates(item: HTMLElement, startDate: string, endDate: string, oldStart: string, oldEnd: string): Promise<boolean> {
        const url = `/cmd/items/${item.dataset.itemId}/resize`;
        const dates = (start: string, end: string): FormData => {
            const formData = new FormData();
            formData.append('startDate', start);
            formData.append('endDate', end);
            formData.append('groupId', item.dataset.groupId ?? '');

            return formData;
        };

        item.classList.add('saving');
        const result = await this.send('PATCH', url, dates(startDate, endDate));
        item.classList.remove('saving');

        if (result.ok) {
            const title = item.querySelector('.item-title')?.textContent ?? 'item';
            const before = dates(oldStart, oldEnd);
            this.record(
                `Changed “${title}”: ${this.formatMonth(startDate)} → ${this.formatMonth(endDate)}`,
                () => this.send('PATCH', url, before),
                `Undone: “${title}” is back at ${this.formatMonth(oldStart)} → ${this.formatMonth(oldEnd)}`,
            );
        } else {
            this.applyDates(item, oldStart, oldEnd);
            const reasons = Object.values(result.fields);
            this.toast(`The new dates were not saved. ${reasons.length > 0 ? reasons.join('. ') + '.' : result.error}`, 'error');
        }

        return result.ok;
    },

    // --- Resizing -------------------------------------------------------------------------------------

    /**
     * Start resizing a timeline item - returns true if resize started
     */
    startResize(e: PointerEvent): boolean {
        const handle = e.target as HTMLElement;
        if (!handle.classList.contains('resize-handle') || locked) return false;

        e.stopPropagation();
        e.preventDefault();

        const item = handle.closest('.timeline-item') as HTMLElement;
        if (!item) return false;

        const handleType = handle.dataset.handle as 'start' | 'end';
        const itemId = parseInt(item.dataset.itemId || '0', 10);
        const groupId = parseInt(item.dataset.groupId || '0', 10);

        item.classList.add('resizing');

        // Set cursor on body to prevent flickering when mouse moves off handle
        document.body.style.cursor = 'ew-resize';

        const tooltip = document.createElement('div');
        tooltip.className = 'resize-tooltip';
        document.body.appendChild(tooltip);

        // Store original values for calculating deltas
        const originalLeft = parseFloat(item.style.getPropertyValue('--item-left') || '0');
        const originalWidth = parseFloat(item.style.getPropertyValue('--item-width') || '0');

        resizeState = {
            itemId,
            groupId,
            handle: handleType,
            originalStartDate: item.dataset.startDate || '',
            originalEndDate: item.dataset.endDate || '',
            originalLeft,
            originalWidth,
            startX: e.clientX,
            itemElement: item,
            tooltip,
        };

        this.updateResizeTooltip(e.clientX, e.clientY);
        return true;
    },

    /**
     * Handle resize pointer move
     */
    handleResizeMove(e: PointerEvent): void {
        if (!resizeState) return;

        e.preventDefault();

        const zoom = this.getCurrentZoom();
        const monthWidth = MONTH_WIDTH * zoom;
        const deltaX = e.clientX - resizeState.startX;
        const deltaMonths = Math.round(deltaX / monthWidth);

        const item = resizeState.itemElement;
        const { originalLeft, originalWidth } = resizeState;

        if (resizeState.handle === 'start') {
            const newLeft = Math.max(0, originalLeft + (deltaMonths * MONTH_WIDTH));
            const newWidth = Math.max(MONTH_WIDTH, originalWidth - (deltaMonths * MONTH_WIDTH));
            const newStartDate = this.calculateDateFromPosition(newLeft);

            // Only update if the width stays positive and dates are valid
            if (newWidth >= MONTH_WIDTH && this.isValidDateOrder(newStartDate, resizeState.originalEndDate || this.getTodayDate())) {
                item.style.setProperty('--item-left', String(newLeft));
                item.style.setProperty('--item-width', String(newWidth));
                item.dataset.startDate = newStartDate;
            }
        } else {
            const newWidth = Math.max(MONTH_WIDTH, originalWidth + (deltaMonths * MONTH_WIDTH));
            const newEndDate = this.calculateDateFromPosition(originalLeft + newWidth - MONTH_WIDTH);

            if (newWidth >= MONTH_WIDTH && this.isValidDateOrder(resizeState.originalStartDate, newEndDate)) {
                item.style.setProperty('--item-width', String(newWidth));
                item.dataset.endDate = newEndDate;
            }
        }

        this.updateResizeTooltip(e.clientX, e.clientY);
    },

    /**
     * Finish resizing and save - returns true if resize was active
     */
    async finishResize(): Promise<boolean> {
        if (!resizeState) return false;

        const { itemElement, tooltip, originalStartDate, originalEndDate } = resizeState;
        const newStartDate = itemElement.dataset.startDate || originalStartDate;
        const newEndDate = itemElement.dataset.endDate || originalEndDate;

        itemElement.classList.remove('resizing');
        tooltip?.remove();
        resizeState = null;
        document.body.style.cursor = '';

        // Keep the click that ends the drag from opening the item
        justFinishedResize = true;
        setTimeout(() => { justFinishedResize = false; }, 200);

        if (newStartDate !== originalStartDate || newEndDate !== originalEndDate) {
            await this.saveDates(itemElement, newStartDate, newEndDate, originalStartDate, originalEndDate);
        }

        return true;
    },

    /**
     * Cancel resize operation - returns true if resize was active
     */
    cancelResize(): boolean {
        if (!resizeState) return false;

        const { itemElement, tooltip, originalStartDate, originalEndDate, originalLeft, originalWidth } = resizeState;

        itemElement.style.setProperty('--item-left', String(originalLeft));
        itemElement.style.setProperty('--item-width', String(originalWidth));
        itemElement.dataset.startDate = originalStartDate;
        itemElement.dataset.endDate = originalEndDate;

        itemElement.classList.remove('resizing');
        tooltip?.remove();
        resizeState = null;
        document.body.style.cursor = '';

        justFinishedResize = true;
        setTimeout(() => { justFinishedResize = false; }, 200);

        return true;
    },

    /**
     * Check if we just finished resizing (to prevent click)
     */
    wasResizing(): boolean {
        return justFinishedResize;
    },

    /**
     * Update tooltip position and content, keeping it inside the window
     */
    updateResizeTooltip(x: number, y: number): void {
        if (!resizeState?.tooltip || !resizeState.itemElement) return;

        const { tooltip, itemElement } = resizeState;
        tooltip.textContent = `${this.formatMonth(itemElement.dataset.startDate || '')} → ${this.formatMonth(itemElement.dataset.endDate || '')}`;
        tooltip.style.left = `${Math.min(x + 12, window.innerWidth - tooltip.offsetWidth - 8)}px`;
        tooltip.style.top = `${Math.max(8, y - 34)}px`;
    },

    /**
     * Move the start or the end of an item by whole months from the keyboard
     */
    async nudgeItem(item: HTMLElement, edge: 'start' | 'end', delta: number): Promise<void> {
        const oldStart = item.dataset.startDate ?? '';
        const oldEnd = item.dataset.endDate ?? '';
        let start = oldStart;
        let end = oldEnd;

        if (edge === 'start') {
            start = this.fromMonths(this.toMonths(oldStart) + delta);
        } else if (oldEnd === '' && delta > 0) {
            return; // an ongoing item has no end to push out
        } else {
            end = this.fromMonths(this.toMonths(oldEnd || this.getTodayDate()) + delta);
        }

        if (!this.isValidDateOrder(start, end || this.getTodayDate()) || this.toMonths(start) < this.getStartYear() * 12) return;

        this.applyDates(item, start, end);
        await this.saveDates(item, start, end, oldStart, oldEnd);
    },

    /**
     * Calculate date from pixel position
     */
    calculateDateFromPosition(position: number): string {
        const totalMonths = Math.round(position / MONTH_WIDTH);

        return this.fromMonths(this.getStartYear() * 12 + totalMonths);
    },

    /**
     * Check if start date is before or equal to end date
     */
    isValidDateOrder(startDate: string, endDate: string): boolean {
        if (!endDate) return true; // No end date is valid (ongoing)
        return startDate <= endDate;
    },

    /**
     * Get current zoom level
     */
    getCurrentZoom(): number {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement;
        if (wrapper) {
            const zoomStr = getComputedStyle(wrapper).getPropertyValue('--zoom-level');
            return parseFloat(zoomStr) || 1;
        }
        return 1;
    },

    /**
     * Check if currently resizing
     */
    isResizing(): boolean {
        return resizeState !== null;
    },

    /**
     * Get today's date string
     */
    getTodayDate(): string {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    },

    /**
     * Get the start year from the timeline
     */
    getStartYear(): number {
        const yearMarker = document.querySelector('.year');
        if (yearMarker) {
            return parseInt(yearMarker.getAttribute('data-year') || '2000', 10);
        }
        return 2000;
    },

    /**
     * Clamp zoom value
     */
    clampZoom(zoom: number): number {
        return Math.max(0.1, Math.min(10, zoom));
    },

    // --- Keyboard navigation inside the timeline ------------------------------------------------------

    /**
     * One tab stop per group. From a group's button the arrow keys walk its items in date order and the
     * other groups; Shift or Alt with an arrow moves an item's end or start, Alt with up or down moves the group.
     */
    navKey(e: KeyboardEvent): void {
        const target = e.target as HTMLElement;
        const item = target.closest('.timeline-item') as HTMLElement | null;
        const button = target.closest('.track-info') as HTMLElement | null;
        const track = target.closest('.track') as HTMLElement | null;
        if (!track || (!item && !button) || e.ctrlKey || e.metaKey) return;

        const horizontal = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        const vertical = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
        if (horizontal === 0 && vertical === 0) return;

        e.preventDefault();

        if (item && horizontal !== 0 && (e.shiftKey || e.altKey)) {
            if (!this.blockedByLock()) void this.nudgeItem(item, e.altKey ? 'start' : 'end', horizontal);

            return;
        }

        if (vertical !== 0) {
            if (button && e.altKey) {
                if (this.blockedByLock()) return;
                const groupId = Number(track.dataset.groupId);
                void this.moveGroup(groupId, vertical as -1 | 1).then(() => {
                    (document.querySelector(`#track-${groupId} .track-info`) as HTMLElement | null)?.focus();
                });

                return;
            }

            const tracks = [...document.querySelectorAll('.track')];
            const next = tracks[tracks.indexOf(track) + vertical];
            (next?.querySelector('.track-info') as HTMLElement | null)?.focus();

            return;
        }

        const items = ([...track.querySelectorAll('.timeline-item')] as HTMLElement[])
            .sort((a, b) => parseFloat(a.style.getPropertyValue('--item-left')) - parseFloat(b.style.getPropertyValue('--item-left')));
        const index = item ? items.indexOf(item) + horizontal : horizontal === 1 ? 0 : -1;
        const next = index < 0 ? track.querySelector('.track-info') as HTMLElement | null : items[index];
        next?.focus();
    },

    // --- Finding things -------------------------------------------------------------------------------

    /**
     * Scroll so that an item starts near the left of the visible timeline, focus and flash it
     */
    goToItem(itemId: string): void {
        const item = document.getElementById(`item-${itemId}`);
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement | null;
        if (!item || !wrapper) return;

        const left = parseFloat(item.style.getPropertyValue('--item-left') || '0');
        wrapper.scrollLeft = -this.panTo(left, this.getCurrentZoom(), 0.15);
        item.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        item.focus({ preventScroll: true });
        item.classList.remove('flash');
        void item.offsetWidth;
        item.classList.add('flash');
    },

    /**
     * Scroll to the first month of a year; false when the timeline does not reach that year
     */
    goToYear(year: number): boolean {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement | null;
        const years = document.querySelectorAll('.year').length;
        const offset = year - this.getStartYear();
        if (!wrapper || offset < 0 || offset >= years) return false;

        wrapper.scrollLeft = -this.panTo(offset * 12 * MONTH_WIDTH, this.getCurrentZoom(), 0.05);

        return true;
    },

    /**
     * Fill the result list under the search field: a year to jump to, and the items whose title matches
     */
    search(query: string): void {
        const list = document.getElementById('search-results');
        if (!list) return;

        const text = query.trim().toLowerCase();
        list.replaceChildren();
        if (text === '') {
            list.hidden = true;

            return;
        }

        const addResult = (label: string, detail: string, run: () => void): void => {
            const entry = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-result';
            const title = document.createElement('span');
            title.className = 'search-result-title';
            title.textContent = label;
            const meta = document.createElement('span');
            meta.className = 'search-result-meta';
            meta.textContent = detail;
            button.append(title, meta);
            button.addEventListener('click', () => {
                this.closeSearch();
                run();
            });
            entry.append(button);
            list.append(entry);
        };

        if (/^\d{4}$/.test(text)) {
            const year = Number(text);
            const offset = year - this.getStartYear();
            if (offset >= 0 && offset < document.querySelectorAll('.year').length) {
                addResult(`Go to ${year}`, 'Year', () => { this.goToYear(year); });
            }
        }

        const matches = ([...document.querySelectorAll('.timeline-item')] as HTMLElement[])
            .filter(item => (item.querySelector('.item-title')?.textContent ?? '').toLowerCase().includes(text))
            .slice(0, 8);
        for (const item of matches) {
            const group = item.closest('.track')?.querySelector('.track-name')?.textContent ?? '';
            const range = `${this.formatMonth(item.dataset.startDate ?? '')} → ${this.formatMonth(item.dataset.endDate ?? '')}`;
            addResult(item.querySelector('.item-title')?.textContent ?? '', `${group} · ${range}`, () => {
                this.goToItem(item.dataset.itemId ?? '');
            });
        }

        if (list.children.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'search-empty';
            empty.textContent = 'Nothing found';
            list.append(empty);
        }
        list.hidden = false;
    },

    /**
     * Keys in the search field and its results: Enter takes the first result, arrows walk the list
     */
    searchKey(e: KeyboardEvent): void {
        const list = document.getElementById('search-results');
        const input = document.getElementById('search') as HTMLInputElement | null;
        if (!list || !input) return;

        const results = [...list.querySelectorAll('.search-result')] as HTMLElement[];
        const index = results.indexOf(document.activeElement as HTMLElement);

        if (e.key === 'Escape') {
            e.preventDefault();
            this.closeSearch();
            input.blur();
        } else if (e.key === 'Enter' && index === -1) {
            e.preventDefault();
            results[0]?.click();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            results[Math.min(index + 1, results.length - 1)]?.focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            (index <= 0 ? input : results[index - 1]).focus();
        }
    },

    closeSearch(): void {
        const list = document.getElementById('search-results');
        const input = document.getElementById('search') as HTMLInputElement | null;
        if (list) list.hidden = true;
        if (input) input.value = '';
    },

    // --- Reordering groups ----------------------------------------------------------------------------

    /**
     * Start dragging a group track
     */
    startDragGroup(e: DragEvent, groupId: number): void {
        if (locked) {
            e.preventDefault();

            return;
        }
        if (!e.dataTransfer) return;

        e.dataTransfer.setData('text/plain', String(groupId));
        e.dataTransfer.effectAllowed = 'move';
        draggedGroupId = String(groupId);

        const track = (e.target as HTMLElement).closest('.track') as HTMLElement | null;
        if (track) {
            track.classList.add('dragging');
            const label = track.querySelector('.track-label') as HTMLElement | null;
            if (label) {
                e.dataTransfer.setDragImage(label, 10, label.offsetHeight / 2);
            }
        }
    },

    /**
     * End dragging a group track
     */
    endDragGroup(e: DragEvent): void {
        draggedGroupId = null;
        (e.target as HTMLElement).closest('.track')?.classList.remove('dragging');
        this.clearDropMarkers();
    },

    clearDropMarkers(): void {
        document.querySelectorAll('.track.drop-before, .track.drop-after')
            .forEach(t => t.classList.remove('drop-before', 'drop-after'));
    },

    /**
     * Mark the edge of the hovered track where the dragged group will land
     */
    dragOverTrack(e: DragEvent): void {
        if (!e.dataTransfer || locked) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const track = e.currentTarget as HTMLElement;
        const ids = this.getGroupOrder();
        const from = draggedGroupId ? ids.indexOf(draggedGroupId) : -1;
        const to = ids.indexOf(track.dataset.groupId ?? '');
        if (from === to) return;

        track.classList.toggle('drop-after', from !== -1 && from < to);
        track.classList.toggle('drop-before', from === -1 || from > to);
    },

    /**
     * Handle dragleave on a track
     */
    dragLeaveTrack(e: DragEvent): void {
        (e.currentTarget as HTMLElement).classList.remove('drop-before', 'drop-after');
    },

    /**
     * Handle drop on a track: the dragged group takes the place of the target
     */
    async dropOnTrack(e: DragEvent, targetGroupId: number): Promise<void> {
        e.preventDefault();
        this.clearDropMarkers();

        const draggedId = e.dataTransfer?.getData('text/plain');
        if (locked || !draggedId || draggedId === String(targetGroupId)) return;

        const ids = this.getGroupOrder();
        const fromIdx = ids.indexOf(draggedId);
        const toIdx = ids.indexOf(String(targetGroupId));
        if (fromIdx === -1 || toIdx === -1) return;

        ids.splice(fromIdx, 1);
        ids.splice(toIdx, 0, draggedId);

        await this.saveGroupOrder(ids, `Moved “${this.groupName(draggedId)}” to position ${toIdx + 1} of ${ids.length}`);
    },

    /**
     * Move a group one place up (-1) or down (1), for touch screens and keyboards
     */
    async moveGroup(groupId: number, delta: -1 | 1): Promise<void> {
        const ids = this.getGroupOrder();
        const fromIdx = ids.indexOf(String(groupId));
        const toIdx = fromIdx + delta;
        if (locked || fromIdx === -1 || toIdx < 0 || toIdx >= ids.length) return;

        [ids[fromIdx], ids[toIdx]] = [ids[toIdx], ids[fromIdx]];

        await this.saveGroupOrder(ids, `Moved “${this.groupName(String(groupId))}” to position ${toIdx + 1} of ${ids.length}`);
    },

    groupName(groupId: string): string {
        return document.querySelector(`#track-${groupId} .track-name`)?.textContent ?? 'group';
    },

    getGroupOrder(): string[] {
        return ([...document.querySelectorAll('.track')] as HTMLElement[]).map(t => t.dataset.groupId ?? '');
    },

    async saveGroupOrder(ids: string[], label: string): Promise<boolean> {
        const put = (order: string[]): Promise<SendResult> => this.send(
            'PUT',
            '/cmd/groups/reorder',
            JSON.stringify({ orderedIds: order.map(Number) }),
            { 'Content-Type': 'application/json' },
        );
        const before = this.getGroupOrder();
        const result = await put(ids);
        if (result.ok) {
            this.record(label, () => put(before));
        } else {
            this.toast(`The new order was not saved. ${result.error}`, 'error');
        }

        return result.ok;
    },
};

window.timeline = timeline;
timeline.setLocked(timeline.storedLock(), false);

// Open on today instead of the empty first year; the scroll event carries the position into the signal
const initialWrapper = document.querySelector('.timeline-wrapper');
if (initialWrapper) {
    initialWrapper.scrollLeft = -timeline.todayPan(1);
}

// The group list of the add-item form is re-rendered by live updates; see guardGroupSelect
const groupField = document.getElementById('add-item-group')?.parentElement;
if (groupField) {
    chosenGroup = (document.getElementById('add-item-group') as HTMLSelectElement).value;
    groupField.addEventListener('change', () => {
        chosenGroup = (document.getElementById('add-item-group') as HTMLSelectElement | null)?.value ?? '';
    });
    new MutationObserver(() => timeline.guardGroupSelect()).observe(groupField, { childList: true, subtree: true });
}

export { timeline };
