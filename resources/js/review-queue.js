export default ({ state, assignUrl, alwaysUrl, csrf }) => ({
    state, // { entries: { [index]: { group, always } }, open }
    busy: false,
    error: false,

    pick(entry, group) {
        return this.send(assignUrl, { entry, group });
    },

    async toggleAlways(entry, event) {
        const ok = await this.send(alwaysUrl, { entry, always: event.target.checked });
        if (!ok) event.target.checked = this.state.entries[entry].always;
    },

    async send(url, body) {
        if (this.busy) return false;
        this.busy = true;
        this.error = false;
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            if (response.status === 409) {
                window.location.href = (await response.json()).redirect;
                return false;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.state = await response.json();
            return true;
        } catch {
            this.error = true;
            return false;
        } finally {
            this.busy = false;
        }
    },
});
