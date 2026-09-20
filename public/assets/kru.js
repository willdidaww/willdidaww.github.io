/**
 * PWA Kru: input pengeluaran dengan kompresi foto sisi klien,
 * idempotency (client_uuid), dan antrian offline via IndexedDB.
 */
(function () {
  const API = '/api/kru/pengeluaran';

  // --- indikator jaringan ---
  const netEl = document.getElementById('netStatus');
  function setNet() {
    if (!netEl) return;
    if (navigator.onLine) { netEl.textContent = 'online'; netEl.classList.remove('off'); }
    else { netEl.textContent = 'offline'; netEl.classList.add('off'); }
  }
  window.addEventListener('online', () => { setNet(); flushQueue(); });
  window.addEventListener('offline', setNet);
  setNet();

  // --- IndexedDB antrian ---
  let dbp = new Promise((resolve, reject) => {
    const req = indexedDB.open('kru_akap', 1);
    req.onupgradeneeded = () => req.result.createObjectStore('queue', { keyPath: 'client_uuid' });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
  async function qAdd(item) {
    const db = await dbp;
    return new Promise((res, rej) => {
      const tx = db.transaction('queue', 'readwrite');
      tx.objectStore('queue').put(item);
      tx.oncomplete = res; tx.onerror = () => rej(tx.error);
    });
  }
  async function qAll() {
    const db = await dbp;
    return new Promise((res) => {
      const out = [];
      const cur = db.transaction('queue').objectStore('queue').openCursor();
      cur.onsuccess = (e) => { const c = e.target.result; if (c) { out.push(c.value); c.continue(); } else res(out); };
    });
  }
  async function qDel(uuid) {
    const db = await dbp;
    return new Promise((res) => {
      const tx = db.transaction('queue', 'readwrite');
      tx.objectStore('queue').delete(uuid);
      tx.oncomplete = res;
    });
  }

  function uuid() {
    return (crypto.randomUUID) ? crypto.randomUUID()
      : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
      });
  }

  // --- kompresi foto (target ~200-400KB) ---
  function compress(file) {
    return new Promise((resolve) => {
      if (!file) return resolve(null);
      const img = new Image();
      const reader = new FileReader();
      reader.onload = () => { img.src = reader.result; };
      img.onload = () => {
        const max = 1280;
        let { width: w, height: h } = img;
        if (w > h && w > max) { h = h * max / w; w = max; }
        else if (h > max) { w = w * max / h; h = max; }
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        // turunkan kualitas hingga < 400KB
        let q = 0.8, data = c.toDataURL('image/jpeg', q);
        while (data.length > 400 * 1024 * 1.37 && q > 0.3) { q -= 0.1; data = c.toDataURL('image/jpeg', q); }
        resolve(data);
      };
      reader.readAsDataURL(file);
    });
  }

  async function send(payload) {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || 'Gagal kirim');
    return res.json();
  }

  async function flushQueue() {
    const items = await qAll();
    const banner = document.getElementById('queueBanner');
    if (items.length && banner) { banner.style.display = 'block'; banner.textContent = `${items.length} data menunggu sinkron...`; }
    for (const it of items) {
      try { await send(it); await qDel(it.client_uuid); } catch (e) { /* biarkan di antrian */ }
    }
    const left = await qAll();
    if (banner) {
      if (left.length) banner.textContent = `${left.length} data belum tersinkron (menunggu online).`;
      else banner.style.display = 'none';
    }
    if (!left.length && items.length) location.reload();
  }

  // --- toggle field BBM ---
  document.querySelectorAll('.pengForm select[name="kategori_id"]').forEach(sel => {
    const bbm = sel.closest('form').querySelector('.bbmFields');
    const upd = () => { bbm.style.display = sel.options[sel.selectedIndex].dataset.tipe === 'bbm' ? 'block' : 'none'; };
    sel.addEventListener('change', upd); upd();
  });

  // --- submit ---
  document.querySelectorAll('.pengForm').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const status = form.querySelector('.status');
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true; status.textContent = 'Memproses foto...';
      const fd = new FormData(form);
      const fileInput = form.querySelector('[name="foto"]');
      const opt = form.querySelector('select[name="kategori_id"]').selectedOptions[0];
      const foto = await compress(fileInput.files[0]);
      if (opt.dataset.bukti === '1' && !foto) { status.textContent = 'Kategori ini wajib foto nota.'; btn.disabled = false; return; }

      const payload = {
        client_uuid: uuid(),
        rit_id: +form.dataset.rit,
        kategori_id: +fd.get('kategori_id'),
        nominal: +fd.get('nominal'),
        tanggal: new Date().toISOString().slice(0, 10),
        keterangan: fd.get('keterangan') || '',
        odometer: fd.get('odometer') ? +fd.get('odometer') : null,
        liter: fd.get('liter') ? +fd.get('liter') : null,
        foto: foto,
      };

      status.textContent = 'Menyimpan lokal...';
      await qAdd(payload); // simpan dulu (idempotency + offline safe)

      if (navigator.onLine) {
        try {
          const r = await send(payload);
          await qDel(payload.client_uuid);
          status.textContent = r.duplikat ? '✓ Sudah terkirim sebelumnya' : '✓ Terkirim, menunggu approval';
          setTimeout(() => location.reload(), 800);
        } catch (err) {
          status.textContent = '⚠ Tersimpan lokal (gagal kirim): ' + err.message + ' — akan disinkron otomatis.';
          btn.disabled = false;
        }
      } else {
        status.textContent = '📴 Offline — tersimpan lokal, akan dikirim saat online.';
        btn.disabled = false;
      }
    });
  });

  // flush saat load
  flushQueue();
})();
