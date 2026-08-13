/* CampusMart - lightweight canvas charts (no external libraries) */
(function () {
  'use strict';

  function barChart(canvas, labels, values, color) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    ctx.scale(dpr, dpr);

    ctx.clearRect(0, 0, w, h);
    const pad = { top: 16, right: 12, bottom: 34, left: 44 };
    const chartW = w - pad.left - pad.right;
    const chartH = h - pad.top - pad.bottom;
    const max = Math.max.apply(null, values.concat([1]));

    // Grid
    ctx.strokeStyle = '#e2e8f0';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '11px Segoe UI, Arial, sans-serif';
    ctx.lineWidth = 1;
    const steps = 4;
    for (let s = 0; s <= steps; s++) {
      const y = pad.top + chartH - (chartH * s / steps);
      ctx.beginPath();
      ctx.moveTo(pad.left, y);
      ctx.lineTo(w - pad.right, y);
      ctx.stroke();
      const val = Math.round(max * s / steps);
      ctx.textAlign = 'right';
      ctx.fillText(String(val), pad.left - 6, y + 4);
    }

    // Bars
    const slot = chartW / values.length;
    const barW = Math.max(8, slot * 0.55);
    for (let i = 0; i < values.length; i++) {
      const x = pad.left + slot * i + (slot - barW) / 2;
      const bh = Math.max(1, chartH * values[i] / max);
      const y = pad.top + chartH - bh;
      const grad = ctx.createLinearGradient(0, y, 0, y + bh);
      grad.addColorStop(0, '#7c3aed');
      grad.addColorStop(1, color || '#4f46e5');
      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.roundRect(x, y, barW, bh, 4);
      ctx.fill();
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      ctx.fillText(labels[i], x + barW / 2, h - 10);
    }
  }

  function donutChart(canvas, slices, colors) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    ctx.scale(dpr, dpr);

    ctx.clearRect(0, 0, w, h);
    const total = slices.reduce(function (a, b) { return a + b; }, 0);
    if (!total) return;
    const cx = w / 2, cy = h / 2;
    const radius = Math.min(w, h) / 2 - 8;
    const inner = radius * 0.6;
    let start = -Math.PI / 2;

    slices.forEach(function (val, i) {
      const angle = (val / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, radius, start, start + angle);
      ctx.closePath();
      ctx.fillStyle = colors[i % colors.length];
      ctx.fill();
      ctx.beginPath();
      ctx.arc(cx, cy, inner, start, start + angle);
      ctx.closePath();
      ctx.fillStyle = '#ffffff';
      ctx.fill();
      start += angle;
    });

    ctx.fillStyle = '#0f172a';
    ctx.font = '700 18px Segoe UI, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(String(total), cx, cy + 6);
  }

  function lineChart(canvas, labels, series) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    ctx.scale(dpr, dpr);

    ctx.clearRect(0, 0, w, h);
    const pad = { top: 16, right: 12, bottom: 30, left: 44 };
    const chartW = w - pad.left - pad.right;
    const chartH = h - pad.top - pad.bottom;
    const all = series.reduce(function (acc, s) { return acc.concat(s.values); }, []);
    const max = Math.max.apply(null, all.concat([1]));

    ctx.strokeStyle = '#e2e8f0';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '11px Segoe UI, Arial, sans-serif';
    const steps = 4;
    for (let s = 0; s <= steps; s++) {
      const y = pad.top + chartH - (chartH * s / steps);
      ctx.beginPath();
      ctx.moveTo(pad.left, y);
      ctx.lineTo(w - pad.right, y);
      ctx.stroke();
      ctx.textAlign = 'right';
      ctx.fillText(String(Math.round(max * s / steps)), pad.left - 6, y + 4);
    }

    const colors = ['#4f46e5', '#16a34a'];
    series.forEach(function (s, si) {
      const stepX = chartW / Math.max(1, s.values.length - 1);
      ctx.beginPath();
      s.values.forEach(function (v, i) {
        const x = pad.left + i * stepX;
        const y = pad.top + chartH - (chartH * v / max);
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.strokeStyle = colors[si % colors.length];
      ctx.lineWidth = 2.5;
      ctx.lineJoin = 'round';
      ctx.stroke();
      s.values.forEach(function (v, i) {
        const x = pad.left + i * stepX;
        const y = pad.top + chartH - (chartH * v / max);
        ctx.beginPath();
        ctx.arc(x, y, 3.5, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.strokeStyle = colors[si % colors.length];
        ctx.lineWidth = 2;
        ctx.stroke();
      });
    });

    if (series.length && series[0].values.length > 1) {
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      const stepX = chartW / (series[0].values.length - 1);
      series[0].values.forEach(function (_, i) {
        ctx.fillText(labels[i], pad.left + i * stepX, h - 8);
      });
    }
  }

  document.querySelectorAll('[data-chart]').forEach(function (canvas) {
    try {
      const type = canvas.getAttribute('data-chart');
      const data = JSON.parse(canvas.textContent || '{}');
      if (type === 'bar') barChart(canvas, data.labels, data.values, data.color);
      else if (type === 'donut') donutChart(canvas, data.values, data.colors);
      else if (type === 'line') lineChart(canvas, data.labels, data.series);
    } catch (err) {
      /* leave chart empty rather than breaking the page */
    }
  });
})();
