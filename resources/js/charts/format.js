const de = (value, digits) =>
    value.toLocaleString('de-DE', { minimumFractionDigits: digits, maximumFractionDigits: digits, useGrouping: true });

/** 168628 → "1.686,28 €" */
export const euros = (cents) => `${de(cents / 100, 2)} €`;

/** 168628 → "1.686 €" */
export const wholeEuros = (cents) => `${de(Math.round(cents / 100), 0)} €`;
