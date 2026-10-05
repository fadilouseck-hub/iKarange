<?php
$user = requireAuth();
$pageTitle = 'Primes & Budget';
$activeTab = 'primes';
require basePath('src/views/layout.php');
?>

<div x-data="primesBudgetPage()" x-init="load()">

  <!-- Summary stat cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="stat-card">
        <div class="stat-label">Total cotisations</div>
        <div class="stat-value" x-text="fmtMoney(summary.total_cotisations)"></div>
        <div class="stat-sub">Ensemble des cotisations</div>
        <div class="stat-icon" style="background:#f5f5f5;color:#333;">
          <i class="bx bx-wallet"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="stat-card">
        <div class="stat-label">Cotisations pay&eacute;es</div>
        <div class="stat-value" style="color:#15803d;" x-text="fmtMoney(summary.cotisations_payees)"></div>
        <div class="stat-sub">Montant encaiss&eacute;</div>
        <div class="stat-icon" style="background:#e6f9f1;color:#15803d;">
          <i class="bx bx-check-circle"></i>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="stat-card">
        <div class="stat-label">Reste &agrave; encaisser</div>
        <div class="stat-value" style="color:#e65100;" x-text="fmtMoney(summary.reste_encaisser)"></div>
        <div class="stat-sub">Cotisations en attente</div>
        <div class="stat-icon" style="background:#fff3e0;color:#e65100;">
          <i class="bx bx-time-five"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Header: Search + Button -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="ik-search" style="flex:1;max-width:380px;">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher par entreprise..."
             x-model="search" @input.debounce.400ms="load()">
    </div>
    <button class="btn btn-primary btn-sm" @click="openCreate()">
      <i class="bx bx-plus"></i> Nouvelle cotisation
    </button>
  </div>

  <!-- Table -->
  <div class="ik-card">
    <template x-if="loading">
      <div class="text-center py-4"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#aaa;"></i></div>
    </template>

    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-wallet"></i>
        <p>Aucune cotisation</p>
      </div>
    </template>

    <template x-if="!loading && items.length > 0">
      <div style="overflow-x:auto;">
        <table class="ik-table">
          <thead>
            <tr>
              <th>Entreprise</th>
              <th>Mois</th>
              <th>Adh&eacute;rents</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="item in items" :key="item.id">
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:36px;height:36px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <i class="bx bx-buildings" style="font-size:1.1rem;color:#1565c0;"></i>
                    </div>
                    <span style="font-weight:600;" x-text="item.entreprise_nom || '—'"></span>
                  </div>
                </td>
                <td x-text="formatMois(item.mois)"></td>
                <td x-text="item.nombre_adherents || '—'"></td>
                <td>
                  <span style="font-weight:600;" x-text="fmtMoney(item.montant)"></span>
                </td>
                <td>
                  <span class="badge-status"
                        :class="'badge-' + item.statut"
                        x-text="formatStatut(item.statut)"></span>
                </td>
                <td>
                  <button class="action-btn edit" title="Modifier" @click="openEdit(item)">
                    <i class="bx bx-pencil"></i>
                  </button>
                  <button class="action-btn delete" title="Supprimer" @click="remove(item)">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </div>

  <!-- Modal: Nouvelle Prime -->
  <template x-if="showModal && !editing">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:520px;">
        <div class="ik-modal-header">
          <h3>Nouvelle Prime</h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

            <!-- Entreprise -->
            <div class="mb-3">
              <label class="form-label">Entreprise <span style="color:#dc3545;">*</span></label>
              <select class="form-select" x-model="form.entreprise_id" required>
                <option value="">-- S&eacute;lectionner --</option>
                <template x-for="e in entreprises" :key="e.id">
                  <option :value="e.id" x-text="e.raison_sociale"></option>
                </template>
              </select>
            </div>

            <!-- Mois -->
            <div class="mb-3">
              <label class="form-label">Mois (YYYY-MM) <span style="color:#dc3545;">*</span></label>
              <input type="month" class="form-control" x-model="form.mois" required>
            </div>

            <!-- Montant -->
            <div class="mb-3">
              <label class="form-label">Montant F CFA <span style="color:#dc3545;">*</span></label>
              <input type="number" class="form-control" x-model.number="form.montant" min="0" required>
            </div>

            <!-- Nombre d'adhérents -->
            <div class="mb-3">
              <label class="form-label">Nombre d'adh&eacute;rents</label>
              <input type="number" class="form-control" x-model.number="form.nombre_adherents" min="0">
            </div>

            <!-- Statut -->
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="form.statut">
                <option value="a_facturer">&Agrave; facturer</option>
                <option value="facturee">Factur&eacute;e</option>
                <option value="payee">Pay&eacute;e</option>
              </select>
            </div>

          </div>
          <div class="ik-modal-footer">
            <button type="button" class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
              <span x-show="!saving">Cr&eacute;er</span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

  <!-- Modal: Modification Prime -->
  <template x-if="showModal && editing">
    <div class="ik-modal-overlay" @click.self="showModal = false">
      <div class="ik-modal" style="max-width:520px;">
        <div class="ik-modal-header">
          <h3>Modification Prime</h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

            <!-- Entreprise -->
            <div class="mb-3">
              <label class="form-label">Entreprise <span style="color:#dc3545;">*</span></label>
              <select class="form-select" x-model="form.entreprise_id" required>
                <option value="">-- S&eacute;lectionner --</option>
                <template x-for="e in entreprises" :key="e.id">
                  <option :value="e.id" x-text="e.raison_sociale"></option>
                </template>
              </select>
            </div>

            <!-- Mois -->
            <div class="mb-3">
              <label class="form-label">Mois (YYYY-MM) <span style="color:#dc3545;">*</span></label>
              <input type="month" class="form-control" x-model="form.mois" required>
            </div>

            <!-- Montant -->
            <div class="mb-3">
              <label class="form-label">Montant F CFA <span style="color:#dc3545;">*</span></label>
              <input type="number" class="form-control" x-model.number="form.montant" min="0" required>
            </div>

            <!-- Nombre d'adhérents -->
            <div class="mb-3">
              <label class="form-label">Nombre d'adh&eacute;rents</label>
              <input type="number" class="form-control" x-model.number="form.nombre_adherents" min="0">
            </div>

            <!-- Statut -->
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="form.statut">
                <option value="a_facturer">&Agrave; facturer</option>
                <option value="facturee">Factur&eacute;e</option>
                <option value="payee">Pay&eacute;e</option>
              </select>
            </div>

            <!-- Date de paiement -->
            <div class="mb-3">
              <label class="form-label">Date de paiement</label>
              <input type="date" class="form-control" x-model="form.date_paiement">
            </div>

          </div>
          <div class="ik-modal-footer">
            <button type="button" class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
              <span x-show="!saving">Mettre &agrave; jour</span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>

<script>
function primesBudgetPage() {
  return {
    loading: true,
    items: [],
    entreprises: [],
    summary: {
      total_cotisations: 0,
      cotisations_payees: 0,
      reste_encaisser: 0,
    },
    showModal: false,
    editing: false,
    editId: null,
    saving: false,
    search: '',

    form: {
      entreprise_id: '',
      mois: '',
      montant: 0,
      nombre_adherents: 0,
      statut: 'a_facturer',
      date_paiement: '',
    },

    resetForm() {
      this.form = {
        entreprise_id: '',
        mois: '',
        montant: 0,
        nombre_adherents: 0,
        statut: 'a_facturer',
        date_paiement: '',
      };
    },

    async load() {
      this.loading = true;
      try {
        let url = '/api/primes?';
        if (this.search) url += 'search=' + encodeURIComponent(this.search) + '&';

        const res = await api(url);
        this.items = res.data || [];
        if (res.summary) {
          this.summary = res.summary;
        }
      } catch (e) {
        toast('Erreur chargement des cotisations', 'error');
      }
      this.loading = false;
    },

    async loadEntreprises() {
      try {
        const res = await api('/api/entreprises');
        this.entreprises = res.data || [];
      } catch (e) {
        // silent
      }
    },

    openCreate() {
      this.editing = false;
      this.editId = null;
      this.resetForm();
      this.loadEntreprises();
      this.showModal = true;
    },

    openEdit(item) {
      this.editing = true;
      this.editId = item.id;
      this.form = {
        entreprise_id: item.entreprise_id || '',
        mois: item.mois || '',
        montant: parseInt(item.montant) || 0,
        nombre_adherents: parseInt(item.nombre_adherents) || 0,
        statut: item.statut || 'a_facturer',
        date_paiement: item.date_paiement || '',
      };
      this.loadEntreprises();
      this.showModal = true;
    },

    async save() {
      this.saving = true;
      try {
        const body = {
          entreprise_id: this.form.entreprise_id,
          mois: this.form.mois,
          montant: this.form.montant,
          nombre_adherents: this.form.nombre_adherents,
          statut: this.form.statut,
        };

        if (this.editing) {
          body.date_paiement = this.form.date_paiement;
          await api('/api/primes/' + this.editId, { method: 'POST', body });
          toast('Cotisation mise à jour');
        } else {
          await api('/api/primes', { method: 'POST', body });
          toast('Cotisation créée');
        }
        this.showModal = false;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de l’enregistrement', 'error');
      }
      this.saving = false;
    },

    async remove(item) {
      const label = (item.entreprise_nom || '') + ' - ' + (item.mois || '');
      if (!window.confirm('Supprimer la cotisation « ' + label + ' » ?')) return;
      try {
        await api('/api/primes/' + item.id, { method: 'DELETE' });
        toast('Cotisation supprimée');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },

    formatStatut(s) {
      const map = {
        a_facturer: 'À facturer',
        facturee: 'Facturée',
        payee: 'Payée',
      };
      return map[s] || s;
    },

    formatMois(m) {
      if (!m) return '—';
      const parts = m.split('-');
      if (parts.length < 2) return m;
      const moisNoms = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
      const idx = parseInt(parts[1], 10) - 1;
      return (moisNoms[idx] || parts[1]) + ' ' + parts[0];
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
