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

/**
 * Format string tanggal YYYY-MM-DD menjadi format Hari-Bulan-Tahun (DD-MM-YYYY).
 */
export function formatDate(dateStr) {
  if (!dateStr) return '—';
  const clean = String(dateStr).split('T')[0];
  const parts = clean.split('-');
  if (parts.length === 3) {
    const [y, m, d] = parts;
    return `${d}-${m}-${y}`;
  }
  return dateStr;
}

/**
 * Format rentang tanggal cuti menjadi format Hari-Bulan-Tahun (contoh: 07-09-2026 s/d 10-09-2026).
 */
export function formatDateRange(start, end) {
  if (!start && !end) return '—';
  const s = formatDate(start);
  const e = formatDate(end);
  if (!end || s === e) return s;
  return `${s} s/d ${e}`;
}
