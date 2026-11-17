(() => {
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    const outside = (e, dlg) => {
      const r = dlg.getBoundingClientRect();
      return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
    };
    document.querySelectorAll('dialog[closedby="any"]').forEach((dlg) => {
      let downOut = false;
      dlg.addEventListener('pointerdown', (e) => { downOut = outside(e, dlg); });
      dlg.addEventListener('pointerup', (e) => { if (downOut && outside(e, dlg)) dlg.close(); });
    });
  }

  const authModal = document.getElementById('modal-auth');
  document.querySelectorAll('[data-open-auth]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!authModal) return;
      const tab = btn.dataset.openAuth === 'register' ? 'register' : 'login';
      const tabBtn = authModal.querySelector(`[data-auth-tab="${tab}"]`);
      if (tabBtn) tabBtn.click();
      authModal.showModal();
    });
  });

  if (authModal) {
    const tabs = authModal.querySelectorAll('[data-auth-tab]');
    const panels = authModal.querySelectorAll('[data-auth-panel]');
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.authTab;
        tabs.forEach((t) => {
          const on = t === tab;
          t.setAttribute('aria-selected', on ? 'true' : 'false');
          t.classList.toggle('active', on);
        });
        panels.forEach((p) => { p.hidden = p.dataset.authPanel !== target; });
      });
    });
  }

  document.querySelectorAll('[data-toggle-pass]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePass);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Ocultar' : 'Ver';
    });
  });

  const floorBtns = document.querySelectorAll('.floor-pill[data-piso]');
  const floorCards = document.querySelectorAll('.piso-card[data-piso]');
  floorBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const piso = btn.dataset.piso;
      floorBtns.forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
      floorCards.forEach((c) => { c.hidden = piso !== 'todos' && c.dataset.piso !== piso; });
    });
  });

  const selPisoAlq = document.getElementById('alq-piso');
  const selCasAlq = document.getElementById('alq-casilla');
  if (selPisoAlq && selCasAlq) {
    const filtrarPuestos = () => {
      const p = selPisoAlq.value;
      let firstVisible = null;
      Array.from(selCasAlq.options).forEach((opt) => {
        const show = !p || opt.dataset.piso === p;
        opt.hidden = !show;
        if (show && firstVisible === null) firstVisible = opt;
      });
      if (firstVisible) selCasAlq.value = firstVisible.value;
    };
    selPisoAlq.addEventListener('change', filtrarPuestos);
    filtrarPuestos();
  }

  const inpIni = document.getElementById('alq-inicio');
  const inpFin = document.getElementById('alq-fin');
  const outEst = document.getElementById('alq-estimado');
  if (inpIni && inpFin && outEst) {
    const tarifaHora = parseFloat(outEst.dataset.precioHora || '5');
    const actualizarEstimado = () => {
      const t1 = Date.parse(inpIni.value);
      const t2 = Date.parse(inpFin.value);
      if (!t1 || !t2 || t2 <= t1) {
        outEst.textContent = 'Selecciona un rango válido';
        return;
      }
      const horas = Math.max(1, Math.ceil((t2 - t1) / 3600000));
      const total = (horas * tarifaHora).toFixed(2);
      outEst.textContent = `${horas} h × ${tarifaHora.toFixed(2)} Bs = ${total} Bs`;
    };
    inpIni.addEventListener('change', actualizarEstimado);
    inpFin.addEventListener('change', actualizarEstimado);
    actualizarEstimado();
  }

  const qrBox = document.getElementById('qr-salida-svg');
  if (qrBox && qrBox.dataset.qrTexto) {
    const texto = qrBox.dataset.qrTexto;
    const n = 21;
    const grid = Array.from({ length: n }, () => Array(n).fill(false));
    const res = Array.from({ length: n }, () => Array(n).fill(false));
    const finder = (r0, c0) => {
      for (let r = -1; r <= 7; r++) {
        for (let c = -1; c <= 7; c++) {
          const rr = r0 + r, cc = c0 + c;
          if (rr < 0 || rr >= n || cc < 0 || cc >= n) continue;
          res[rr][cc] = true;
          if (r === -1 || r === 7 || c === -1 || c === 7) grid[rr][cc] = false;
          else if (r === 0 || r === 6 || c === 0 || c === 6) grid[rr][cc] = true;
          else grid[rr][cc] = r >= 2 && r <= 4 && c >= 2 && c <= 4;
        }
      }
    };
    finder(0, 0);
    finder(0, n - 7);
    finder(n - 7, 0);
    for (let i = 8; i < n - 8; i++) {
      res[6][i] = true; res[i][6] = true;
      grid[6][i] = i % 2 === 0; grid[i][6] = i % 2 === 0;
    }
    let h = 2166136261;
    for (let i = 0; i < texto.length; i++) { h ^= texto.charCodeAt(i); h = Math.imul(h, 16777619); }
    let seed = h >>> 0;
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (res[r][c]) continue;
        seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
        grid[r][c] = (seed & 1) === 1;
      }
    }
    let rects = '';
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (grid[r][c]) rects += `<rect x="${c + 2}" y="${r + 2}" width="1" height="1"/>`;
      }
    }
    const vb = n + 4;
    qrBox.innerHTML = `<svg class="qr-svg" viewBox="0 0 ${vb} ${vb}" width="144" height="144" role="img" aria-label="QR de pago simulado"><rect width="${vb}" height="${vb}" fill="#fff"/><g fill="#0f172a">${rects}</g></svg>`;
  }
})();
