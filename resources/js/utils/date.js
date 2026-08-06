/**
 * Format a date string or object into a human-readable date string.
 * Example output: "Aug 7, 2026"
 *
 * @param {string|Date|null} value
 * @param {string} fallback
 * @returns {string}
 */
export function formatDate(value, fallback = '-') {
    if (!value) return fallback;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return fallback;
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
}

/**
 * Format an issue date into a human-readable string.
 * Example output: "Aug 7, 2026" or fallback string.
 *
 * @param {string|Date|null} value
 * @param {string} fallback
 * @returns {string}
 */
export function formatIssueDate(value, fallback = 'Unknown date') {
    return formatDate(value, fallback);
}
