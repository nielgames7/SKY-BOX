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

  const activarPiso = (piso) => {
    floorBtns.forEach((b) => b.setAttribute('aria-pressed', b.dataset.piso === piso ? 'true' : 'false'));
    floorCards.forEach((c) => { c.hidden = c.dataset.piso !== piso; });
  };

  floorBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const piso = btn.dataset.piso;
      activarPiso(piso);
      try { sessionStorage.setItem('skybox_piso', piso); } catch (_) {}
    });
  });

  try {
    const pisoGuardado = sessionStorage.getItem('skybox_piso');
    if (pisoGuardado && document.querySelector(`.floor-pill[data-piso="${pisoGuardado}"]`)) {
      activarPiso(pisoGuardado);
    }
  } catch (_) {}

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

  // Actualización automática en tiempo real del croquis para sensores IoT y RFID
  if (floorCards.length > 0) {
    const SVG_AUTO = '<svg class="auto" viewBox="0 0 40 70" aria-hidden="true">'
      + '<rect x="3" y="2" width="34" height="66" rx="9" class="auto-carroceria"/>'
      + '<rect x="7" y="13" width="26" height="12" rx="3" class="auto-vidrio"/>'
      + '<rect x="7" y="46" width="26" height="9" rx="3" class="auto-vidrio"/>'
      + '<rect x="9" y="27" width="22" height="17" rx="2" class="auto-techo"/>'
      + '</svg>';
    const ETIQUETAS = { libre: 'Libre', ocupado: 'Ocupado', reservado: 'Reservado' };
    const ringFill = document.querySelector('.ring-fill');
    const ringNum = document.querySelector('.ring-num');
    const cntOcup = document.querySelector('.resumen-aside .text-danger.fs-5');
    const cntRes = document.querySelector('.resumen-aside .text-warning.fs-5');
    let lastHash = '';
    let lastPagoEvt = null;
    let lastFirmaReg = null;

    const refrescarTablasAdmin = async () => {
      try {
        const htmlResp = await fetch(window.location.href, { cache: 'no-store' });
        if (!htmlResp.ok) return;
        const doc = new DOMParser().parseFromString(await htmlResp.text(), 'text/html');
        ['seccion-operacion', 'seccion-historial'].forEach((id) => {
          const nuevo = doc.getElementById(id);
          const actual = document.getElementById(id);
          if (nuevo && actual) actual.innerHTML = nuevo.innerHTML;
        });
        const recNuevo = doc.querySelector('.resumen-aside .text-primary.fs-5');
        const recActual = document.querySelector('.resumen-aside .text-primary.fs-5');
        if (recNuevo && recActual) recActual.textContent = recNuevo.textContent;
      } catch (_) {}
    };

    const actualizarCroquisVivo = async () => {
      try {
        const resp = await fetch('controllers/api_iot.php?accion=estado', { cache: 'no-store' });
        if (!resp.ok) return;
        const data = await resp.json();
        if (!data || !data.ok || !Array.isArray(data.casillas)) return;

        const firmaReg = data.firma_registros || '';
        if (lastFirmaReg === null) {
          lastFirmaReg = firmaReg;
        } else if (firmaReg !== lastFirmaReg) {
          lastFirmaReg = firmaReg;
          await refrescarTablasAdmin();
        }

        const evtPago = data.ultimo_pago?.evento || '';
        if (lastPagoEvt === null) {
          lastPagoEvt = evtPago;
        } else if (evtPago !== lastPagoEvt) {
          lastPagoEvt = evtPago;
          if (qrModal && typeof qrModal.showModal === 'function') {
            if (evtPago !== '') {
              const p = data.ultimo_pago;
              const img = document.getElementById('qr-modal-img');
              if (img) img.src = `${p.qr_imagen || 'assets/qr/qr_pago.png'}?t=${Date.now()}`;
              const m = document.getElementById('qr-modal-monto');
              if (m) m.textContent = `${p.total_bs || '5.00'} Bs`;
              const meta = document.getElementById('qr-modal-meta');
              if (meta) meta.innerHTML = `Estadía: <strong>${p.tiempo || '0m'}</strong> &middot; Puesto: <strong>${p.casilla || '-'}</strong>`;
              const tk = document.getElementById('qr-modal-ticket');
              if (tk) tk.textContent = p.ticket || '-';
              const rf = document.getElementById('qr-modal-rfid');
              if (rf) rf.textContent = p.uid_rfid || '-';
              const ref = document.getElementById('qr-modal-ref');
              if (ref) ref.textContent = evtPago;
              if (!qrModal.open) qrModal.showModal();
            } else if (qrModal.open) {
              qrModal.close();
            }
          }
        }

        const hash = data.casillas.map((c) => `${c.id_casilla}:${c.estado}:${c.uid_rfid || ''}`).join('|');
        if (hash === lastHash) return;
        lastHash = hash;

        const libresPorPiso = {};
        data.casillas.forEach((cas) => {
          libresPorPiso[cas.piso] = (libresPorPiso[cas.piso] || 0) + (cas.estado === 'libre' ? 1 : 0);
          const wrap = document.querySelector(`[data-casilla-wrap="${cas.id_casilla}"]`);
          if (!wrap) return;
          const esForm = wrap.tagName === 'FORM';
          const btn = esForm ? wrap.querySelector('button') : wrap;
          if (!btn) return;

          const numStr = String(wrap.dataset.casillaNum || '1').padStart(2, '0');
          const orient = btn.classList.contains('puesto-abajo') ? 'abajo' : 'arriba';
          btn.className = `puesto puesto-${cas.estado} puesto-${orient}`;
          btn.title = `${cas.codigo}: ${ETIQUETAS[cas.estado] || cas.estado}`;

          let extra = '';
          if (esForm) {
            if (cas.estado === 'ocupado' && cas.uid_rfid) {
              extra = `<span class="puesto-extra font-monospace">${cas.uid_rfid}</span>`;
            } else if (cas.estado === 'reservado' && cas.codigo_reserva) {
              extra = `<span class="puesto-extra">${cas.codigo_reserva}</span>`;
            }
          } else {
            if (cas.estado === 'libre') {
              btn.dataset.pickCasilla = String(cas.id_casilla);
              btn.dataset.pickPiso = String(cas.piso);
            } else {
              delete btn.dataset.pickCasilla;
              delete btn.dataset.pickPiso;
            }
          }

          let icono = '';
          if (cas.estado === 'ocupado') icono = SVG_AUTO;
          else if (cas.estado === 'reservado') icono = '<span class="puesto-icono">R</span>';

          btn.innerHTML = `<span class="puesto-numero">${numStr}</span>${icono}<span class="puesto-codigo">${cas.codigo}</span><span class="puesto-estado">${ETIQUETAS[cas.estado] || cas.estado}</span>${extra}`;
        });

        Object.entries(libresPorPiso).forEach(([piso, libres]) => {
          const pill = document.querySelector(`.floor-pill[data-piso="${piso}"]`);
          if (pill) pill.textContent = `P${piso} (${libres}/6)`;
          const badge = document.querySelector(`.piso-card[data-piso="${piso}"] .piso-titulo .badge`);
          if (badge) {
            badge.className = `badge rounded-pill ${libres > 0 ? 'text-bg-success' : 'text-bg-danger'}`;
            badge.textContent = `${libres}/6 libres`;
          }
        });

        const total = data.conteo.total || 60;
        const pctOcup = total > 0 ? (total - data.conteo.libre) / total : 0;
        if (ringFill) ringFill.setAttribute('stroke-dashoffset', (282.7 * pctOcup).toFixed(1));
        if (ringNum) ringNum.textContent = String(data.conteo.libre);
        if (cntOcup) cntOcup.textContent = String(data.conteo.ocupado);
        if (cntRes) cntRes.textContent = String(data.conteo.reservado);
      } catch (_) {}
    };

    setInterval(actualizarCroquisVivo, 1500);
    setInterval(() => {
      const ahoraSeg = Math.floor(Date.now() / 1000);
      document.querySelectorAll('[data-ingreso-ts]').forEach((el) => {
        const ts = parseInt(el.dataset.ingresoTs || '0', 10);
        if (ts > 0) {
          const seg = Math.max(1, ahoraSeg - ts);
          const m = Math.floor(seg / 60);
          const s = seg % 60;
          el.textContent = (m > 0 ? `${m}m ` : '') + `${s}s`;
        }
      });
    }, 1000);
  }
})();
