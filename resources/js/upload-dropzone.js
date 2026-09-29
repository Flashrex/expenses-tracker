export default ({ maxFiles, maxBytes }) => ({
    dragging: false,
    busy: false,
    count: 0,
    error: null,

    choose(files, dropped = false) {
        if (!files || files.length === 0) return;

        if (files.length > maxFiles) return this.reject(`You can upload up to ${maxFiles} statements at once.`);

        const total = Array.from(files).reduce((sum, file) => sum + file.size, 0);
        if (total > maxBytes) return this.reject('Upload too large, try fewer files.');

        this.error = null;
        if (dropped) this.$refs.input.files = files;
        this.count = files.length;
        this.busy = true;
        this.$refs.form.requestSubmit();
    },

    reject(message) {
        this.error = message;
        this.$refs.input.value = '';
    },
});
