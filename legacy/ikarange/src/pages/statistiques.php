<?php
$user = requireAuth();
$pageTitle = 'Statistiques';
$activeTab = 'statistiques';
require basePath('src/views/layout.php');
?>

<div x-data="statistiquesPage()" x-init="load()">

  <!-- Stat cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Total d&eacute;penses IPM</div>
        <div class="stat-value" x-text="fmtMoney(data.depenses_ipm)"></div>
        <div class="stat-sub">Cumul des prises en charge</div>
        <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;">
          <i class="bx bx-trending-up"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Moyenne par PEC</div>
        <div class="stat-value" x-text="fmtMoney(data.moyenne_pec)"></div>
        <div class="stat-sub">Co&ucirc;t moyen par dossier</div>
        <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;">
          <i class="bx bx-calculator"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Total factures</div>
        <div class="stat-value" x-text="data.total_factures ?? '—'"></div>
        <div class="stat-sub">PEC factur&eacute;es</div>
        <div class="stat-icon" style="background:#fff3e0;color:#e65100;">
          <i class="bx bx-receipt"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Entreprises clientes</div>
        <div class="stat-value" x-text="data.entreprises_clientes ?? '—'"></div>
        <div class="stat-sub">Entreprises actives</div>
        <div class="stat-icon" style="background:#f3e5f5;color:#7b1fa2;">
          <i class="bx bx-buildings"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts 2x2 grid -->
  <div class="row g-3 mb-4">
    <!-- Top left: Depenses par entreprise (horizontal bar) -->
    <div class="col-lg-6">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">
          <i class="bx bx-bar-chart" style="color:var(--primary);"></i>
          D&eacute;penses par entreprise
        </h6>
        <div style="position:relative;height:280px;">
          <canvas id="chartDepEntreprise"></canvas>
        </div>
      </div>
    </div>
    <!-- Top right: PEC par statut (doughnut) -->
    <div class="col-lg-6">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">
          <i class="bx bx-pie-chart-alt-2" style="color:var(--primary);"></i>
          PEC par statut
        </h6>
        <div style="display:flex;justify-content:center;height:280px;">
          <canvas id="chartPecStatut"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <!-- Bottom left: Evolution des depenses (line) -->
    <div class="col-lg-6">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">
          <i class="bx bx-line-chart" style="color:var(--primary);"></i>
          &Eacute;volution des d&eacute;penses
        </h6>
        <div style="position:relative;height:280px;">
          <canvas id="chartEvolution"></canvas>
        </div>
      </div>
    </div>
    <!-- Bottom right: Top prestataires (horizontal bar colored) -->
    <div class="col-lg-6">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">
          <i class="bx bx-medal" style="color:var(--primary);"></i>
          Top prestataires
        </h6>
        <div style="position:relative;height:280px;">
          <canvas id="chartTopPrestataires"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
function statistiquesPage() {
  return {
    loading: true,
    data: {},

    async load() {
      try {
        this.data = await api('/api/statistiques');
      } catch (e) {
        toast('Erreur chargement des statistiques', 'error');
      }
      this.loading = false;
      this.$nextTick(() => this.renderCharts());
    },

    renderCharts() {
      this.renderDepEntreprise();
      this.renderPecStatut();
      this.renderEvolution();
      this.renderTopPrestataires();
    },

    renderDepEntreprise() {
      const items = this.data.depenses_par_entreprise || [];
      const ctx = document.getElementById('chartDepEntreprise');
      if (!ctx) return;

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: items.map(i => i.name),
          datasets: [{
            data: items.map(i => i.total),
            backgroundColor: '#2196F3',
            borderRadius: 4,
            maxBarThickness: 28,
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { callback: v => new Intl.NumberFormat('fr-FR').format(v) }
            },
            y: {
              ticks: { font: { size: 11 } }
            }
          }
        }
      });
    },

    renderPecStatut() {
      const items = this.data.pec_par_statut || [];
      const ctx = document.getElementById('chartPecStatut');
      if (!ctx) return;

      const statutLabels = {
        en_attente: 'En attente',
        approuvee: 'Approuvée',
        reglee: 'Réglée',
        rejetee: 'Rejetée',
        facturee: 'Facturée',
      };
      const statutColors = {
        en_attente: '#ff9800',
        approuvee: '#1565c0',
        reglee: '#2e7d32',
        rejetee: '#c62828',
        facturee: '#7b1fa2',
      };

      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: items.map(s => (statutLabels[s.statut] || s.statut) + ' (' + s.count + ')'),
          datasets: [{
            data: items.map(s => s.count),
            backgroundColor: items.map(s => statutColors[s.statut] || '#999'),
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '60%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } }
          }
        }
      });
    },

    renderEvolution() {
      const items = this.data.evolution_depenses || [];
      const ctx = document.getElementById('chartEvolution');
      if (!ctx) return;

      const monthNames = {
        '01': 'Jan', '02': 'Fév', '03': 'Mar', '04': 'Avr',
        '05': 'Mai', '06': 'Jun', '07': 'Jul', '08': 'Aoû',
        '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Déc'
      };

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: items.map(i => {
            const parts = i.month.split('-');
            return (monthNames[parts[1]] || parts[1]) + ' ' + parts[0].slice(2);
          }),
          datasets: [{
            data: items.map(i => i.total),
            borderColor: '#2dd4a8',
            backgroundColor: 'rgba(45, 212, 168, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#2dd4a8',
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { callback: v => new Intl.NumberFormat('fr-FR').format(v) }
            }
          }
        }
      });
    },

    renderTopPrestataires() {
      const items = this.data.top_prestataires || [];
      const ctx = document.getElementById('chartTopPrestataires');
      if (!ctx) return;

      const colors = ['#2196F3', '#2dd4a8', '#ff9800', '#7b1fa2', '#c62828', '#1565c0'];

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: items.map(i => i.name),
          datasets: [{
            data: items.map(i => i.total),
            backgroundColor: items.map((_, idx) => colors[idx % colors.length]),
            borderRadius: 4,
            maxBarThickness: 28,
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { callback: v => new Intl.NumberFormat('fr-FR').format(v) }
            },
            y: {
              ticks: { font: { size: 11 } }
            }
          }
        }
      });
    }
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
