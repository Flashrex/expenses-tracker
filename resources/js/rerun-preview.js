export default ({ decisions, ids, decideUrl, csrf }) => ({
    decisions, // { [transactionId]: 'accept' | 'decline' }
    ids, // every proposed transaction id
    busy: false,
    error: false,

    get undecided() {
        return this.ids.filter((id) => !this.decisions[id]).length;
    },

    // Clicks on controls inside a row never expand or collapse it.
    togglesRow(event) {
        return event.target.closest('button, input, label, a') === null;
    },

    async decide(entries, decision) {
        if (this.busy) return;
        this.busy = true;
        this.error = false;
        try {
            const response = await fetch(decideUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ entries, decision }),
            });
            if (response.status === 409) {
                window.location.href = (await response.json()).redirect;
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.decisions = (await response.json()).decisions;
        } catch {
            this.error = true;
        } finally {
            this.busy = false;
        }
    },
});
