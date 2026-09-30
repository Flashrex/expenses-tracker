// One entry row of the Overview's entries card: expanding it and editing its description.
export default ({ description, url }) => ({
    open: false,
    saved: description ?? '',
    draft: description ?? '',
    busy: false,
    error: false,
    justSaved: false,
    savedTimer: null,

    get dirty() {
        return this.draft !== this.saved;
    },

    // Expanding shows the saved description again, dropping text that was never saved.
    toggle() {
        this.open = !this.open;
        if (this.open && !this.busy) {
            this.draft = this.saved;
            this.error = false;
        }
    },

    async save() {
        if (this.busy || !this.dirty) return;
        this.busy = true;
        this.error = false;
        try {
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.$root.closest('[data-csrf]').dataset.csrf,
                },
                body: JSON.stringify({ description: this.draft }),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            this.saved = this.draft = data.description ?? '';
            this.justSaved = true;
            clearTimeout(this.savedTimer);
            this.savedTimer = setTimeout(() => (this.justSaved = false), 2000);
        } catch {
            this.error = true;
        } finally {
            this.busy = false;
        }
    },

    destroy() {
        clearTimeout(this.savedTimer);
    },
});
