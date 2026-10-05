<?php
$user = requireAuth();
$pageTitle = 'Tableau de bord';
$activeTab = 'dashboard';
require basePath('src/views/layout.php');
?>

<div x-data="dashboardPage()" x-init="load()">

  <!-- Stat cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Entreprises</div>
        <div class="stat-value" x-text="data.entreprises ?? '—'"></div>
        <div class="stat-sub">Entreprises actives</div>
        <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;">
          <i class="bx bx-buildings"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Adh&eacute;rents</div>
        <div class="stat-value" x-text="data.adherents ?? '—'"></div>
        <div class="stat-sub" x-text="data.adherents + ' titulaires'"></div>
        <div class="stat-icon" style="background:#fff3e0;color:#e65100;">
          <i class="bx bx-group"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Prises en charge</div>
        <div class="stat-value" x-text="data.pec_total ?? '—'"></div>
        <div class="stat-sub" x-text="(data.pec_en_attente || 0) + ' en attente'"></div>
        <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;">
          <i class="bx bx-file"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">D&eacute;penses IPM</div>
        <div class="stat-value" x-text="fmtMoney(data.depenses_ipm)"></div>
        <div class="stat-sub">Total des prises en charge</div>
        <div class="stat-icon" style="background:#f3e5f5;color:#7b1fa2;">
          <i class="bx bx-trending-up"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Stat cards row 2 -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Factures en attente</div>
        <div class="stat-value" x-text="data.factures_attente_count ?? '—'"></div>
        <div class="stat-sub" x-text="fmtMoney(data.factures_attente_montant) + ' impay&eacute;'"></div>
        <div class="stat-icon" style="background:#fff3e0;color:#ff9800;">
          <i class="bx bx-receipt"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Primes collect&eacute;es</div>
        <div class="stat-value" x-text="fmtMoney(data.primes_collectees)"></div>
        <div class="stat-sub">Ann&eacute;e en cours</div>
        <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;">
          <i class="bx bx-wallet"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Taux de sinistralit&eacute;</div>
        <div class="stat-value" x-text="(data.taux_sinistralite ?? 0) + ' %'"></div>
        <div class="stat-sub">D&eacute;penses / Primes</div>
        <div class="stat-icon" :style="'background:' + sinistraliteColor(data.taux_sinistralite).bg + ';color:' + sinistraliteColor(data.taux_sinistralite).fg">
          <i class="bx bx-line-chart"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="stat-label">Prestataires agr&eacute;&eacute;s</div>
        <div class="stat-value" x-text="data.prestataires_agrees ?? '—'"></div>
        <div class="stat-sub">Actifs</div>
        <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;">
          <i class="bx bx-plus-medical"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">D&eacute;penses par type d'acte</h6>
        <div style="position:relative;height:280px;">
          <canvas id="chartDepensesType"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="ik-card" style="height:100%;">
        <h6 style="font-weight:600;margin-bottom:1rem;">R&eacute;partition des PEC</h6>
        <div style="position:relative;height:280px;display:flex;justify-content:center;">
          <canvas id="chartPecStatut"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Line chart - Volume mensuel -->
  <div class="ik-card mb-4">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
      <h6 style="font-weight:600;margin:0;">
        <i class="bx bx-trending-up" style="color:var(--primary);"></i>
        &Eacute;volution des actes (12 mois)
      </h6>
      <select class="form-select" style="width:auto;font-size:0.82rem;" x-model="selectedActe" @change="loadVolume()">
        <option value="tous">Tous les actes</option>
        <option value="consultation">Consultation</option>
        <option value="hospitalisation">Hospitalisation</option>
        <option value="imagerie">Imagerie</option>
        <option value="dentaire">Dentaire</option>
        <option value="analyse">Analyse</option>
        <option value="pharmacie">Pharmacie</option>
        <option value="chirurgie">Chirurgie</option>
      </select>
    </div>
    <div style="position:relative;height:280px;">
      <canvas id="chartVolumeMensuel"></canvas>
    </div>
  </div>

  <!-- Recent PEC -->
  <div class="ik-card">
    <h6 style="font-weight:600;margin-bottom:1rem;">
      <i class="bx bx-bolt" style="color:var(--primary);"></i>
      Derni&egrave;res prises en charge
    </h6>

    <template x-if="data.recent_pec && data.recent_pec.length === 0">
      <div class="empty-state">
        <i class="bx bx-file"></i>
        <p>Aucune prise en charge</p>
      </div>
    </template>

    <template x-for="pec in (data.recent_pec || [])" :key="pec.id">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid #f0f0f0;">
        <div>
          <div style="font-weight:600;color:#333;" x-text="(pec.adherent_nom || '') + ' ' + (pec.adherent_prenom || '')"></div>
          <div style="font-size:0.75rem;color:#999;" x-text="(pec.prestataire_nom || '') + ' — ' + (pec.type_acte || '')"></div>
        </div>
        <div style="text-align:right;">
          <div style="font-weight:600;" x-text="fmtMoney(pec.montant_total)"></div>
          <span class="badge-status"
                :class="'badge-' + pec.statut"
                x-text="formatStatut(pec.statut)"></span>
        </div>
      </div>
    </template>
  </div>
</div>

<script>
function dashboardPage() {
  return {
    loading: true,
    data: {},
    selectedActe: 'tous',
    volumeChart: null,

    async load() {
      try {
        this.data = await api('/api/dashboard');
      } catch(e) {
        toast('Erreur chargement tableau de bord', 'error');
      }
      this.loading = false;
      this.$nextTick(() => {
        this.renderCharts();
        this.renderVolumeChart();
      });
    },

    async loadVolume() {
      try {
        const d = await api('/api/dashboard?type_acte=' + this.selectedActe);
        this.data.volume_mensuel = d.volume_mensuel;
        this.renderVolumeChart();
      } catch(e) {}
    },

    sinistraliteColor(val) {
      if (!val || val < 70) return { bg: '#e8f5e9', fg: '#2e7d32' };
      if (val < 90) return { bg: '#fff3e0', fg: '#e65100' };
      return { bg: '#fce4ec', fg: '#c62828' };
    },

    formatStatut(s) {
      const map = {
        en_attente: 'En attente',
        approuvee: 'Approuvée',
        reglee: 'Réglée',
        rejetee: 'Rejetée',
        facturee: 'Facturée',
      };
      return map[s] || s;
    },

    renderCharts() {
      // Bar chart - Depenses par type
      const types = (this.data.depenses_par_type || []);
      const typeLabels = {
        consultation: 'Consultation',
        analyse: 'Analyse',
        pharmacie: 'Pharmacie',
        hospitalisation: 'Hospitalisation',
        imagerie: 'Imagerie',
        dentaire: 'Dentaire',
      };

      const barCtx = document.getElementById('chartDepensesType');
      if (barCtx) {
        new Chart(barCtx, {
          type: 'bar',
          data: {
            labels: types.map(t => typeLabels[t.type_acte] || t.type_acte),
            datasets: [{
              data: types.map(t => t.total),
              backgroundColor: '#2196F3',
              borderRadius: 4,
              maxBarThickness: 50,
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
      }

      // Donut chart - PEC par statut
      const statuts = (this.data.pec_par_statut || []);
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

      const donutCtx = document.getElementById('chartPecStatut');
      if (donutCtx) {
        new Chart(donutCtx, {
          type: 'doughnut',
          data: {
            labels: statuts.map(s => (statutLabels[s.statut] || s.statut) + ' (' + s.count + ')'),
            datasets: [{
              data: statuts.map(s => s.count),
              backgroundColor: statuts.map(s => statutColors[s.statut] || '#999'),
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
      }
    },

    renderVolumeChart() {
      const ctx = document.getElementById('chartVolumeMensuel');
      if (!ctx) return;

      const volume = this.data.volume_mensuel || [];
      const monthNames = ['Jan','F\u00e9v','Mar','Avr','Mai','Juin','Juil','Ao\u00fbt','Sep','Oct','Nov','D\u00e9c'];

      // Build full 12-month array
      const now = new Date();
      const months = [];
      for (let i = 11; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        const label = monthNames[d.getMonth()] + ' ' + d.getFullYear();
        const found = volume.find(v => v.mois === key);
        months.push({ label, nb: found ? parseInt(found.nb) : 0, montant: found ? parseInt(found.montant) : 0 });
      }

      if (this.volumeChart) this.volumeChart.destroy();

      this.volumeChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: months.map(m => m.label),
          datasets: [{
            label: 'Nombre de PEC',
            data: months.map(m => m.nb),
            borderColor: '#2dd4a8',
            backgroundColor: 'rgba(45,212,168,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#2dd4a8',
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                afterLabel: (ctx) => 'Montant: ' + new Intl.NumberFormat('fr-FR').format(months[ctx.dataIndex].montant) + ' F'
              }
            }
          },
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { ticks: { font: { size: 10 } } }
          }
        }
      });
    }
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
