/**
 * Format a Date as YYYY-MM-DD in the user's local time.
 *
 * `Date#toISOString()` always uses UTC. In time zones ahead of UTC (such as
 * Indonesia and Thailand), that turns local midnight into the previous date.
 */
export function localDateISO(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}
