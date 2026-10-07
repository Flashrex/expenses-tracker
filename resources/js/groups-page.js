import { notify } from './notifications';

const uid = () => crypto.randomUUID();

export default ({ state, swatches, fields, directions, urls, csrf }) => ({
    cards: [], // { uid, key, isOther, name, color, rules: [{ uid, id, direction, share, conditions: [{ uid, field, operator, value }] }], always: [{ id, text }], snapshot, errors, saving }
    ignored: null, // same shape with key 'ignored' and no name, colour, share or always rules
    swatches,
    fields, // { [field]: { label, operators: [[value, label], …] } }
    directions, // [[value, label], …]
    dropTarget: null,
    draggingField: null,
    deleting: null,
    moveTo: 'other',
    deleteBusy: false,

    init() {
        this.cards = state.cards.map((card) => this.fromServer(card));
        this.ignored = this.fromServer({ ...state.ignored, key: 'ignored' });

        window.addEventListener('beforeunload', (event) => {
            if (this.anyDirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    },

    fromServer(card, previous = {}) {
        const built = {
            uid: previous.uid ?? uid(),
            key: card.key,
            isOther: card.isOther ?? false,
            name: card.name ?? '',
            color: card.color ?? '',
            always: card.always ?? [],
            rules: card.rules.map((rule) => ({
                uid: uid(),
                id: rule.id,
                direction: rule.direction,
                share: rule.share ?? 1,
                conditions: rule.conditions.map((condition) => ({ uid: uid(), ...condition })),
            })),
            errors: {},
            saving: false,
        };
        built.snapshot = this.serialize(built);

        return built;
    },

    get anyDirty() {
        return this.cards.some((card) => this.isDirty(card)) || (this.ignored !== null && this.isDirty(this.ignored));
    },

    isDirty(card) {
        return card.key === null || this.serialize(card) !== card.snapshot;
    },

    serialize(card) {
        return JSON.stringify({
            name: card.name,
            color: card.color,
            rules: card.rules.map((rule) => ({
                id: rule.id,
                direction: rule.direction,
                share: String(rule.share),
                conditions: rule.conditions.map(({ field, operator, value }) => ({ field, operator, value })),
            })),
        });
    },

    payload(card) {
        const rules = card.rules.map((rule) => ({
            id: rule.id,
            direction: rule.direction,
            ...(card === this.ignored ? {} : { share: String(rule.share) }),
            conditions: rule.conditions.map(({ field, operator, value }) => ({ field, operator, value })),
        }));

        return card === this.ignored ? { rules } : { name: card.name, color: card.color, rules };
    },

    error(card, path) {
        return card.errors[path]?.[0] ?? '';
    },

    isCustomColor(card) {
        return !this.swatches.includes(card.color);
    },

    defaultColor() {
        return this.swatches.find((swatch) => !this.cards.some((card) => card.color === swatch)) ?? '#cd6666';
    },

    savedGroups(except = null) {
        return this.cards.filter((card) => card.key !== null && card !== except);
    },

    addGroup() {
        const card = { uid: uid(), key: null, isOther: false, name: '', color: this.defaultColor(), rules: [], always: [], errors: {}, saving: false };
        card.snapshot = this.serialize(card);
        const other = this.cards.findIndex((existing) => existing.isOther);
        this.cards.splice(other === -1 ? this.cards.length : other, 0, card);
        this.$nextTick(() => document.querySelector(`[data-card-uid="${card.uid}"] [data-name]`)?.focus());
    },

    newCondition(field) {
        return { uid: uid(), field, operator: this.fields[field].operators[0][0], value: '' };
    },

    addRule(card, field) {
        card.rules.push({
            uid: uid(),
            id: null,
            direction: card === this.ignored ? 'in' : 'out',
            share: 1,
            conditions: [this.newCondition(field)],
        });
    },

    addCondition(rule, field) {
        rule.conditions.push(this.newCondition(field));
    },

    removeCondition(rule, conditionUid) {
        rule.conditions = rule.conditions.filter((condition) => condition.uid !== conditionUid);
    },

    removeRule(card, ruleUid) {
        card.rules = card.rules.filter((rule) => rule.uid !== ruleUid);
    },

    startBlock(event, field) {
        event.dataTransfer.setData('application/x-rule-field', field);
        event.dataTransfer.effectAllowed = 'copy';
        this.draggingField = field;
    },

    endBlock() {
        this.draggingField = null;
        this.dropTarget = null;
    },

    acceptBlock(event, target) {
        if (this.draggingField === null) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        this.dropTarget = target;
    },

    leaveBlock(event, target) {
        if (this.dropTarget === target && !event.currentTarget.contains(event.relatedTarget)) {
            this.dropTarget = null;
        }
    },

    droppedField(event) {
        return event.dataTransfer.getData('application/x-rule-field') || this.draggingField;
    },

    dropOnRule(rule, event) {
        const field = this.droppedField(event);
        if (!field || !this.fields[field]) return;
        event.preventDefault();
        this.addCondition(rule, field);
        this.endBlock();
    },

    dropOnAddRule(card, event) {
        const field = this.droppedField(event);
        if (!field || !this.fields[field]) return;
        event.preventDefault();
        this.addRule(card, field);
        this.endBlock();
    },

    async moveCard(cardUid, position) {
        const before = [...this.cards];
        const from = this.cards.findIndex((card) => card.uid === cardUid);
        if (from === -1 || from === position) return;

        const [card] = this.cards.splice(from, 1);
        this.cards.splice(position, 0, card);

        // "Other" always stays last.
        const other = this.cards.findIndex((existing) => existing.isOther);
        if (other !== -1 && other !== this.cards.length - 1) {
            this.cards = before;
            return;
        }

        const savedKeys = (cards) => cards.filter((existing) => existing.key !== null).map((existing) => existing.key);
        const keys = savedKeys(this.cards);
        if (JSON.stringify(keys) === JSON.stringify(savedKeys(before))) return;

        const { ok } = await this.request('PUT', urls.order, { groups: keys });
        if (!ok) {
            this.cards = before;
            notify({ type: 'error', message: "Couldn't save the group order. Please try again." });
            return;
        }

        notify('Group order saved');
    },

    moveRule(card, ruleUid, position) {
        const from = card.rules.findIndex((rule) => rule.uid === ruleUid);
        if (from === -1 || from === position) return;

        const [rule] = card.rules.splice(from, 1);
        card.rules.splice(position, 0, rule);
    },

    async save(card) {
        if (card.saving) return;
        card.saving = true;

        let method = 'PUT';
        let url = card === this.ignored ? urls.ignored : urls.update.replace('__KEY__', card.key);
        let body = this.payload(card);

        const created = card.key === null;
        const label = card === this.ignored ? 'Ignored rules' : `"${card.name.trim() || 'Group'}"`;

        if (created) {
            const index = this.cards.indexOf(card);
            method = 'POST';
            url = urls.store;
            body = { ...body, before: this.cards.slice(index + 1).find((below) => below.key !== null)?.key ?? null };
        }

        const { ok, status, json } = await this.request(method, url, body);
        card.saving = false;

        if (status === 422) {
            card.errors = json?.errors ?? {};
            notify({ type: 'error', message: `Couldn't save ${label}. Check the highlighted fields.` });
            return;
        }

        if (!ok || !json?.card) {
            notify({ type: 'error', message: `Couldn't save ${label}. Please try again.` });
            return;
        }

        const fresh = this.fromServer(card === this.ignored ? { ...json.card, key: 'ignored' } : json.card, card);
        Object.assign(card, fresh);
        notify(created ? `Created ${label}` : `Saved ${label}`);
    },

    askDelete(card) {
        if (card.key === null) {
            this.cards = this.cards.filter((existing) => existing !== card);
            return;
        }

        this.deleting = card;
        this.moveTo = 'other';
    },

    async confirmDelete() {
        if (this.deleting === null || this.deleteBusy) return;
        this.deleteBusy = true;

        const card = this.deleting;
        const { ok } = await this.request('DELETE', urls.destroy.replace('__KEY__', card.key), { move_to: this.moveTo });
        this.deleteBusy = false;

        if (!ok) {
            notify({ type: 'error', message: `Couldn't delete "${card.name}". Please try again.` });
            return;
        }

        this.cards = this.cards.filter((existing) => existing !== card);
        this.deleting = null;
        notify(`Deleted "${card.name}"`);
    },

    async deleteAlways(card, rule) {
        if (!window.confirm(`Delete the "Always use" rule ${rule.text}?`)) return;

        const { ok } = await this.request('DELETE', urls.rule.replace('__ID__', rule.id));
        if (!ok) {
            notify({ type: 'error', message: "Couldn't delete the \"Always use\" rule. Please try again." });
            return;
        }

        card.always = card.always.filter((existing) => existing.id !== rule.id);
        notify(`Deleted "Always use" rule ${rule.text}`);
    },

    async request(method, url, body = undefined) {
        try {
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            const json = response.status === 204 ? null : await response.json().catch(() => null);

            return { ok: response.ok, status: response.status, json };
        } catch {
            return { ok: false, status: 0, json: null };
        }
    },
});
