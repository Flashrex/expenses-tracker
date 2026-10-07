import Alpine from 'alpinejs';

const TIMEOUT = 5000;

let nextId = 1;

/** The notifications shown bottom right; the layout renders them from this store. */
export const store = {
    items: [], // [{ id, type: 'success'|'error'|'info', message }]

    push({ type = 'success', message, timeout = TIMEOUT }) {
        const id = nextId++;
        this.items.push({ id, type, message });
        if (timeout > 0) setTimeout(() => this.dismiss(id), timeout);

        return id;
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
};

/** Show a notification from anywhere: notify({ type, message }) or notify('Saved'). */
export const notify = (notification) =>
    Alpine.store('notifications').push(typeof notification === 'string' ? { message: notification } : notification);
