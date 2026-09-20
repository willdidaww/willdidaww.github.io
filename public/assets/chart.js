/**
 * Chart bar interaktif sederhana via Canvas (tanpa library eksternal).
 * Mendukung hover tooltip. Dipakai dashboard: pendapatan vs biaya.
 */
(function () {
  const data = window.CHART_DATA || [];
  const canvas = document.getElementById('chartPB');
  if (!canvas || !data.length) return;

  const dpr = window.devicePixelRatio || 1;
  function fit() {
    const w = canvas.clientWidth || 500;
    const h = 220;
    canvas.width = w * dpr; canvas.height = h * dpr;
    canvas.style.height = h + 'px';
    return { w, h };
  }

  const fmt = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');
  let bars = [];

  function draw() {
    const { w, h } = fit();
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);

    const pad = { l: 8, r: 8, t: 14, b: 26 };
    const chartW = w - pad.l - pad.r;
    const chartH = h - pad.t - pad.b;
    const max = Math.max(1, ...data.map(d => Math.max(d.pendapatan, d.biaya)));
    const groupW = chartW / data.length;
    const barW = Math.min(26, groupW / 3);
    bars = [];

    // gridlines
    ctx.strokeStyle = '#eef2f7'; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = pad.t + chartH * (i / 4);
      ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke();
    }

    data.forEach((d, i) => {
      const cx = pad.l + groupW * i + groupW / 2;
      const hP = (d.pendapatan / max) * chartH;
      const hB = (d.biaya / max) * chartH;
      const xP = cx - barW - 2, xB = cx + 2;
      const yP = pad.t + chartH - hP, yB = pad.t + chartH - hB;

      ctx.fillStyle = '#1d4ed8'; ctx.fillRect(xP, yP, barW, hP);
      ctx.fillStyle = '#f59e0b'; ctx.fillRect(xB, yB, barW, hB);
      bars.push({ x: xP, y: yP, w: barW, h: hP, label: 'Pendapatan ' + d.bulan, val: d.pendapatan });
      bars.push({ x: xB, y: yB, w: barW, h: hB, label: 'Biaya ' + d.bulan, val: d.biaya });

      ctx.fillStyle = '#64748b'; ctx.font = '11px system-ui'; ctx.textAlign = 'center';
      ctx.fillText(d.bulan, cx, h - 8);
    });

    // legend
    ctx.textAlign = 'left'; ctx.font = '11px system-ui';
    ctx.fillStyle = '#1d4ed8'; ctx.fillRect(pad.l, 2, 10, 10);
    ctx.fillStyle = '#64748b'; ctx.fillText('Pendapatan', pad.l + 14, 11);
    ctx.fillStyle = '#f59e0b'; ctx.fillRect(pad.l + 90, 2, 10, 10);
    ctx.fillStyle = '#64748b'; ctx.fillText('Biaya', pad.l + 104, 11);
  }

  let tip = document.createElement('div');
  tip.style.cssText = 'position:fixed;pointer-events:none;background:#0f172a;color:#fff;padding:5px 9px;border-radius:6px;font-size:12px;display:none;z-index:99';
  document.body.appendChild(tip);

  canvas.addEventListener('mousemove', (e) => {
    const rect = canvas.getBoundingClientRect();
    const mx = e.clientX - rect.left, my = e.clientY - rect.top;
    const hit = bars.find(b => mx >= b.x && mx <= b.x + b.w && my >= b.y && my <= b.y + b.h);
    if (hit) {
      tip.style.display = 'block';
      tip.style.left = (e.clientX + 12) + 'px';
      tip.style.top = (e.clientY - 10) + 'px';
      tip.innerHTML = hit.label + '<br><b>' + fmt(hit.val) + '</b>';
    } else { tip.style.display = 'none'; }
  });
  canvas.addEventListener('mouseleave', () => tip.style.display = 'none');

  draw();
  window.addEventListener('resize', draw);
})();
