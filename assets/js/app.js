(() => {
  const metaScheme = document.querySelector('meta[name="color-scheme"]');
  const sysDark = window.matchMedia('(prefers-color-scheme: dark)');

  const aplicarTema = () => {
    const val = metaScheme?.content || 'light dark';
    const isDark = val === 'dark' || (val === 'light dark' && sysDark.matches);
    document.documentElement.dataset.bsTheme = isDark ? 'dark' : 'light';
  };

  const themeBtn = document.getElementById('btn-theme-toggle');
  if (themeBtn && metaScheme) {
    themeBtn.addEventListener('click', () => {
      const actualDark = metaScheme.content === 'dark' || (metaScheme.content === 'light dark' && sysDark.matches);
      const target = actualDark ? 'light' : 'dark';
      const osTarget = sysDark.matches ? 'dark' : 'light';
      try {
        if (target === osTarget) localStorage.removeItem('color-scheme');
        else localStorage.setItem('color-scheme', target);
      } catch (_) {}
      metaScheme.content = target === osTarget ? 'light dark' : target;
      aplicarTema();
    });

    window.addEventListener('storage', (e) => {
      if (e.key !== 'color-scheme') return;
      metaScheme.content = e.newValue || 'light dark';
      aplicarTema();
    });
    sysDark.addEventListener('change', aplicarTema);
  }

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
      authModal.querySelector(`[data-auth-tab="${tab}"]`)?.click();
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
      floorBtns.forEach((b) => b.setAttribute('aria-pressed', b.dataset.piso === piso ? 'true' : 'false'));
      floorCards.forEach((c) => { c.hidden = piso !== 'todos' && c.dataset.piso !== piso; });
    });
  });

  const selPisoAlq = document.getElementById('alq-piso');
  const selCasAlq = document.getElementById('alq-casilla');
  const filtrarPuestos = (preferId = '') => {
    if (!selPisoAlq || !selCasAlq) return;
    const p = selPisoAlq.value;
    let firstVisible = '';
    Array.from(selCasAlq.options).forEach((opt) => {
      const show = !p || opt.dataset.piso === p;
      opt.hidden = !show;
      if (show && firstVisible === '') firstVisible = opt.value;
    });
    selCasAlq.value = preferId || firstVisible;
  };
  if (selPisoAlq) {
    selPisoAlq.addEventListener('change', () => filtrarPuestos());
    filtrarPuestos();
  }

  document.querySelectorAll('[data-pick-casilla]').forEach((spot) => {
    spot.addEventListener('click', () => {
      if (selPisoAlq && selCasAlq) {
        selPisoAlq.value = spot.dataset.pickPiso;
        filtrarPuestos(spot.dataset.pickCasilla);
      }
      document.getElementById('seccion-alquiler')?.scrollIntoView({ behavior: 'smooth' });
    });
  });

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
      outEst.textContent = `${horas} h × ${tarifaHora.toFixed(2)} Bs = ${(horas * tarifaHora).toFixed(2)} Bs`;
    };
    inpIni.addEventListener('change', actualizarEstimado);
    inpFin.addEventListener('change', actualizarEstimado);
    actualizarEstimado();
  }

  const scanInput = document.getElementById('rfid-salida-scan');
  if (scanInput) {
    scanInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && scanInput.value.trim().length >= 3) {
        e.preventDefault();
        scanInput.form?.requestSubmit();
      }
    });
  }

  const qrModal = document.getElementById('modal-qr-salida');
  if (qrModal && qrModal.dataset.autoOpen === 'true' && typeof qrModal.showModal === 'function') {
    qrModal.showModal();
  }
})();
