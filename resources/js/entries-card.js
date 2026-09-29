export default () => ({
    busy: false,
    controller: null,

    init() {
        const { pageUrl, fragmentUrl } = this.$el.dataset;
        this.pageUrl = pageUrl;
        this.fragmentUrl = fragmentUrl;

        this.onClick = (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const group = event.target.closest('a[data-entries-group]');
            const link = event.target.closest('a[data-entries-link]');
            if (group) {
                event.preventDefault();
                this.setGroup(group.dataset.entriesGroup);
            } else if (link && this.$el.contains(link)) {
                event.preventDefault();
                this.load(link.href);
            }
        };
        this.onGroup = (event) => this.setGroup(event.detail.group);
        this.onPop = () => this.load(window.location.href, { push: false });

        document.addEventListener('click', this.onClick);
        window.addEventListener('entries:group', this.onGroup);
        window.addEventListener('popstate', this.onPop);
    },

    destroy() {
        document.removeEventListener('click', this.onClick);
        window.removeEventListener('entries:group', this.onGroup);
        window.removeEventListener('popstate', this.onPop);
        this.controller?.abort();
    },

    params() {
        return new URLSearchParams(window.location.search);
    },

    // Filters by group, keeping status and search; picking the active group only scrolls.
    setGroup(group) {
        const params = this.params();
        if (params.get('group') !== group) {
            params.set('group', group);
            params.delete('page');
            this.load(`${this.pageUrl}?${params}`);
        }
        this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    search() {
        const params = this.params();
        const q = this.$refs.search.value.trim();
        if ((params.get('q') ?? '') === q) return;
        q === '' ? params.delete('q') : params.set('q', q);
        params.delete('page');
        this.load(`${this.pageUrl}?${params}`);
    },

    // Swaps in the card body for the target URL; only the latest request wins, failures fall back to a full load.
    async load(url, { push = true } = {}) {
        const target = new URL(url, window.location.origin);
        this.controller?.abort();
        this.controller = new AbortController();
        this.busy = true;
        try {
            const response = await fetch(`${this.fragmentUrl}${target.search}`, {
                headers: { Accept: 'text/html' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.$refs.body.innerHTML = await response.text();
            const pageUrl = `${this.pageUrl}${new URL(response.url).search}`;
            if (push) history.pushState({ entries: true }, '', pageUrl);
            if (document.activeElement !== this.$refs.search) {
                this.$refs.search.value = new URL(pageUrl).searchParams.get('q') ?? '';
            }
            this.busy = false;
        } catch (error) {
            if (error.name === 'AbortError') return;
            window.location.href = target.href;
        }
    },
});
