export function formatNepal(value) {
  if (!value) return '';
  const date = new Date(value);
  return date.toLocaleString('en-IN', {
    timeZone: 'Asia/Kathmandu',
    hour12: true
  });
}

export function formatNepalFromMs(ms, fallbackMs = Date.now()) {
  if (!ms && ms !== 0) return formatNepal(fallbackMs);
  let num = Number(ms);
  if (Number.isNaN(num)) return formatNepal(fallbackMs);
  // If device sends uptime ms (very small), fallback to current epoch ms
  if (num < 100000000000) {
    num = fallbackMs;
  }
  return formatNepal(num);
}
