<?php ?>

  <?php if ($showNav): ?>
    </div><!-- /.ik-content -->

    <!-- Footer -->
    <footer class="text-center py-3 text-muted small border-top" style="background:#fff;">
      Powered by MCE Group &copy; <?= date('Y') ?> I'KARANG&Eacute; &mdash; Gestion Assurance &amp; Mutuelle
    </footer>

  </div><!-- /.ik-main -->
  <?php endif; ?>

  <!-- Global JS -->
  <script>
  // CSRF helper
  window.CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // Toast helper
  window.toast = function(msg, type = 'success') {
    const area = document.getElementById('toast-area');
    const el = document.createElement('div');
    const colors = { success:'#15803d', error:'#dc3545', info:'#1565c0', warning:'#e65100' };
    const bgs = { success:'#e6f9f1', error:'#fce4ec', info:'#e3f2fd', warning:'#fff3e0' };
    el.style.cssText = `
      padding:0.6rem 1.25rem;border-radius:8px;margin-bottom:0.5rem;font-size:0.85rem;font-weight:500;
      min-width:280px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);
      animation:slideDown .25s ease;
      background:${bgs[type]||bgs.info};color:${colors[type]||colors.info};
    `;
    el.textContent = msg;
    area.appendChild(el);
    setTimeout(() => {
      el.style.opacity='0';
      el.style.transition='opacity .3s';
      setTimeout(()=>el.remove(), 300);
    }, 3500);
  };

  // Fetch wrapper
  window.api = async function(url, opts = {}) {
    const defaults = {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF,
      },
    };
    if (opts.body && !(opts.body instanceof FormData)) {
      defaults.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const merged = { ...defaults, ...opts, headers: { ...defaults.headers, ...(opts.headers||{}) } };
    const res = await fetch(url, merged);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw { status: res.status, ...data };
    return data;
  };

  // Format money (F CFA)
  window.fmtMoney = function(n) {
    if (!n && n !== 0) return '0 F';
    return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' F';
  };

  // Format date
  window.fmtDate = function(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('fr-FR');
  };

  // ── Modal safe close (prevents accidental data loss) ──
  // Used on overlay click: warns if form has data, otherwise closes silently
  window.confirmCloseModal = function(modalFlag, ctx) {
    const modal = event?.target?.querySelector('.ik-modal') || document.querySelector('.ik-modal');
    if (!modal) { ctx[modalFlag] = false; return; }
    const inputs = modal.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea');
    let dirty = false;
    for (const el of inputs) {
      if (el.value && el.value.trim() && el.placeholder !== el.value) { dirty = true; break; }
    }
    if (!dirty || confirm('Vous avez des donnees non sauvegardees. Fermer quand meme ?')) {
      ctx[modalFlag] = false;
    }
  };

  // Slide down animation
  const styleSheet = document.createElement('style');
  styleSheet.textContent = '@keyframes slideDown{from{transform:translateY(-10px);opacity:0}to{transform:translateY(0);opacity:1}}';
  document.head.appendChild(styleSheet);

  // ── Assistant widget ──
  window.assistantWidget = function() {
    return {
      query: '',
      open: false,
      loading: false,
      result: null,
      _debounceTimer: null,

      onFocus() {
        if (this.result) {
          this.open = true;
        } else {
          // Show greeting
          this.result = {
            type: 'greeting',
            message: 'Bonjour ! Posez-moi une question sur vos donnees.',
            suggestions: [
              "Combien d'adherents ?",
              'Factures impayees',
              'Page statistiques',
              'Total des primes'
            ]
          };
          this.open = true;
        }
      },

      clear() {
        this.query = '';
        this.result = null;
        this.open = false;
      },

      autoAsk() {
        if (this.query.trim().length >= 2) {
          this.ask();
        } else if (this.query.trim().length === 0) {
          this.result = null;
        }
      },

      async ask() {
        const q = this.query.trim();
        if (!q) return;

        this.loading = true;
        this.open = true;

        try {
          const data = await api('/api/assistant', {
            method: 'POST',
            body: { query: q }
          });
          this.result = data;
        } catch (e) {
          this.result = {
            type: 'error',
            message: 'Erreur lors de la recherche. Veuillez reessayer.',
            suggestions: [
              "Combien d'adherents ?",
              'Page entreprises'
            ]
          };
        } finally {
          this.loading = false;
        }
      },

      useSuggestion(s) {
        this.query = s;
        this.ask();
      }
    };
  };
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
