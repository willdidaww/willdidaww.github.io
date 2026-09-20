// Modal dialog sederhana untuk form create/edit.
import { $, formData } from './lib.js';

let overlay;

/**
 * Tampilkan modal dengan form.
 * @param {string} title
 * @param {string} bodyHtml  isi form (tanpa <form>)
 * @param {(data:object, closeFn:Function)=>Promise<void>} onSubmit
 */
export function openModal(title, bodyHtml, onSubmit) {
  closeModal();
  overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal-card">
      <div class="modal-head"><b>${title}</b><button class="btn ghost sm" id="mClose">✕</button></div>
      <form id="modalForm" class="modal-body">${bodyHtml}
        <div class="btn-row" style="margin-top:16px;justify-content:flex-end">
          <button type="button" class="btn" id="mCancel">Batal</button>
          <button type="submit" class="btn primary" id="mSave">Simpan</button>
        </div>
      </form>
    </div>`;
  document.body.appendChild(overlay);
  const close = () => closeModal();
  $('#mClose').addEventListener('click', close);
  $('#mCancel').addEventListener('click', close);
  overlay.addEventListener('click', (ev) => { if (ev.target === overlay) close(); });

  $('#modalForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const save = $('#mSave');
    save.disabled = true; save.textContent = 'Menyimpan…';
    try {
      await onSubmit(formData(ev.target), close);
    } catch (err) {
      const { toast } = await import('./lib.js');
      toast(err.message || 'Gagal menyimpan', 'error');
      save.disabled = false; save.textContent = 'Simpan';
    }
  });
}

export function closeModal() {
  if (overlay) { overlay.remove(); overlay = null; }
}
