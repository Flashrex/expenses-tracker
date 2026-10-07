export default ({ state, overrides, groups, assignUrl, alwaysUrl, overrideUrl, csrf }) => ({
    state, // { entries: { [index]: { group, always, chosen } } }; unchosen entries are in 'other'
    overrides, // { [index]: groupKey } for entries moved away from their rule's group
    groups, // { [key]: { name, color } } in the usual group order
    busy: false,
    error: false,

    pick(entry, group) {
        return this.send(assignUrl, { entry, group });
    },

    async toggleAlways(entry, event) {
        const ok = await this.send(alwaysUrl, { entry, always: event.target.checked });
        if (!ok) event.target.checked = this.state.entries[entry].always;
    },

    override(entry, group) {
        return this.send(overrideUrl, { entry, group }, (data) => {
            this.overrides = data.overrides;
        });
    },

    isOverridden(entry) {
        return Object.hasOwn(this.overrides, entry);
    },

    groupOf(entry, ruleGroup) {
        return this.overrides[entry] ?? ruleGroup;
    },

    // Grouped-by text of a To review entry.
    queuedBy(entry, merchant) {
        const { chosen, always } = this.state.entries[entry];
        if (!chosen) return 'No rule matched';
        return always ? `Always use for ${merchant}` : 'Picked manually';
    },

    // Clicks on controls inside a row never expand or collapse it.
    togglesRow(event) {
        return event.target.closest('button, input, label, a, [data-group-picker]') === null;
    },

    async send(url, body, apply = (data) => {
        this.state = data;
    }) {
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
            apply(await response.json());
            return true;
        } catch {
            this.error = true;
            return false;
        } finally {
            this.busy = false;
        }
    },
});
