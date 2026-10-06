// Set ikon SVG inline (tanpa dependensi/CDN). Stroke mengikuti currentColor.
// Pakai: icon('bus') -> string <svg>. Ukuran default 1em agar ikut font-size.

const P = {
  dashboard: '<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>',
  route: '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M18 7v4a4 4 0 0 1-4 4H9a3 3 0 0 0 0 6h0"/>',
  receipt: '<path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  checkCircle: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5L16 9"/>',
  bus: '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16M8 17v2M16 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/>',
  map: '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
  users: '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 6a3 3 0 0 1 0 6M21 20a6 6 0 0 0-5-5.9"/>',
  compass: '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
  folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
  idcard: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16a3 3 0 0 1 6 0M14 9h4M14 13h4"/>',
  wave: '<path d="M4 12a8 8 0 0 1 16 0M8 12a4 4 0 0 1 8 0M12 12v0"/>',
  logout: '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17 5 12l5-5M5 12h11"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  paperclip: '<path d="M21 11.5 12 20a4 4 0 0 1-6-6l8.5-8.5a2.5 2.5 0 0 1 4 4L10 17.5a1 1 0 0 1-1.5-1.5L16 8.5"/>',
  party: '<path d="M4 20 9 7l8 8-13 5Z"/><path d="M14 6a2 2 0 0 1 2-2M18 10a2 2 0 0 0 2-2M15 3l.5 1M20 7l1 .5"/>',
  arrowRight: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  arrowLeft: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  chevronRight: '<path d="m9 6 6 6-6 6"/>',
};

export function icon(name, opts = {}) {
  const d = P[name];
  if (!d) return '';
  const size = opts.size || '1em';
  const sw = opts.stroke || 1.8;
  const cls = opts.class ? ` class="${opts.class}"` : '';
  const style = opts.style ? ` style="${opts.style}"` : '';
  return `<svg${cls}${style} width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" ` +
    `stroke="currentColor" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round" ` +
    `aria-hidden="true" focusable="false" style="vertical-align:-0.15em;${opts.style || ''}">${d}</svg>`;
}
