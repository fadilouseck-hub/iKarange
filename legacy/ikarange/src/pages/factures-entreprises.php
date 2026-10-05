<?php
$user = requireAuth();
$pageTitle = 'Factures entreprises';
$activeTab = 'factures-ent';
require basePath('src/views/layout.php');
?>

<div x-data="facturesEntPage()" x-init="load()">

  <!-- Header: Search + Buttons -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="ik-search" style="flex:1;max-width:380px;">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher par n&deg; facture, entreprise..."
             x-model="search" @input.debounce.400ms="load()">
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" @click="openEtatGlobal()">
        <i class="bx bx-printer"></i> Imprimer tout
      </button>
      <button class="btn btn-outline-primary btn-sm" @click="openGenerate()">
        <i class="bx bx-cog"></i> G&eacute;n&eacute;rer factures
      </button>
      <button class="btn btn-primary btn-sm" @click="openCreate()">
        <i class="bx bx-plus"></i> Nouvelle facture
      </button>
    </div>
  </div>

  <!-- Table -->
  <div class="ik-card">
    <template x-if="loading">
      <div class="text-center py-4"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#aaa;"></i></div>
    </template>

    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-spreadsheet"></i>
        <p>Aucune facture entreprise</p>
      </div>
    </template>

    <template x-if="!loading && items.length > 0">
      <div style="overflow-x:auto;">
        <table class="ik-table">
          <thead>
            <tr>
              <th>N&deg; Facture</th>
              <th>Entreprise</th>
              <th>Date</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="item in items" :key="item.id">
              <tr>
                <td>
                  <span class="row-name" x-text="item.numero"></span>
                </td>
                <td>
                  <span style="font-weight:600;" x-text="item.entreprise_nom || '—'"></span>
                </td>
                <td x-text="fmtDate(item.date_facture)"></td>
                <td>
                  <div style="font-weight:600;" x-text="fmtMoney(item.montant_total)"></div>
                  <template x-if="item.montant_paye > 0">
                    <div class="row-sub" x-text="'Pay&eacute;: ' + fmtMoney(item.montant_paye)"></div>
                  </template>
                </td>
                <td>
                  <span class="badge-status"
                        :class="'badge-' + item.statut"
                        x-text="formatStatut(item.statut)"></span>
                </td>
                <td>
                  <button class="action-btn download" title="T&eacute;l&eacute;charger" @click="openEtat(item)">
                    <i class="bx bx-download"></i>
                  </button>
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

  <!-- Modal: Nouvelle / Modifier facture entreprise -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:600px;">
        <div class="ik-modal-header">
          <h3 x-text="editing ? 'Modifier facture entreprise' : 'Nouvelle facture entreprise'"></h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

            <!-- N° Facture (disabled) -->
            <div class="mb-3">
              <label class="form-label">N&deg; Facture</label>
              <input type="text" class="form-control" value="Auto-g&eacute;n&eacute;r&eacute;" disabled
                     style="background:#f8f9fa;" x-show="!editing">
              <input type="text" class="form-control" :value="form.numero" disabled
                     style="background:#f8f9fa;" x-show="editing">
            </div>

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

            <!-- Montant total + Montant payé -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Montant total F CFA <span style="color:#dc3545;">*</span></label>
                <input type="number" class="form-control" x-model.number="form.montant_total" min="0" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Montant pay&eacute;</label>
                <input type="number" class="form-control" x-model.number="form.montant_paye" min="0">
              </div>
            </div>

            <!-- Date facture + Date échéance -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Date facture</label>
                <input type="date" class="form-control" x-model="form.date_facture">
              </div>
              <div class="col-md-6">
                <label class="form-label">Date &eacute;ch&eacute;ance</label>
                <input type="date" class="form-control" x-model="form.date_echeance">
              </div>
            </div>

            <!-- Statut -->
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="form.statut">
                <option value="en_attente">En attente</option>
                <option value="validee">Valid&eacute;e</option>
                <option value="partiel">Partiel</option>
                <option value="payee">Pay&eacute;e</option>
              </select>
            </div>

            <!-- Observations -->
            <div class="mb-3">
              <label class="form-label">Observations</label>
              <textarea class="form-control" rows="3" x-model="form.observations"></textarea>
            </div>

          </div>
          <div class="ik-modal-footer">
            <button type="button" class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
              <span x-show="!saving" x-text="editing ? 'Mettre &agrave; jour' : 'Cr&eacute;er'"></span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

  <!-- Modal: Générer les factures entreprises -->
  <template x-if="showGenerateModal">
    <div class="ik-modal-overlay" @click.self="showGenerateModal = false">
      <div class="ik-modal" style="max-width:520px;">
        <div class="ik-modal-header">
          <h3>G&eacute;n&eacute;rer les factures entreprises</h3>
          <button class="close-btn" @click="showGenerateModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="generate()">
          <div class="ik-modal-body">

            <!-- Date de début + Date de fin -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Date de d&eacute;but <span style="color:#dc3545;">*</span></label>
                <input type="date" class="form-control" x-model="genForm.date_debut" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Date de fin <span style="color:#dc3545;">*</span></label>
                <input type="date" class="form-control" x-model="genForm.date_fin" required>
              </div>
            </div>

            <!-- Entreprise (optionnel) -->
            <div class="mb-3">
              <label class="form-label">Entreprise (optionnel)</label>
              <select class="form-select" x-model="genForm.entreprise_id">
                <option value="">Toutes les entreprises</option>
                <template x-for="e in entreprises" :key="e.id">
                  <option :value="e.id" x-text="e.raison_sociale"></option>
                </template>
              </select>
            </div>

            <!-- Info box -->
            <div style="background:#e3f2fd;border-radius:8px;padding:0.85rem 1rem;font-size:0.82rem;color:#1565c0;">
              <i class="bx bx-info-circle" style="margin-right:0.25rem;"></i>
              Les factures seront g&eacute;n&eacute;r&eacute;es automatiquement &agrave; partir des prises en charge approuv&eacute;es dans la p&eacute;riode s&eacute;lectionn&eacute;e. Une facture par entreprise sera cr&eacute;&eacute;e avec le total des parts IPM.
            </div>

          </div>
          <div class="ik-modal-footer">
            <button type="button" class="btn btn-light btn-sm" @click="showGenerateModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="generating">
              <span x-show="!generating">G&eacute;n&eacute;rer</span>
              <span x-show="generating"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>

<script>
function facturesEntPage() {
  return {
    loading: true,
    items: [],
    entreprises: [],
    showModal: false,
    showGenerateModal: false,
    editing: false,
    editId: null,
    saving: false,
    generating: false,
    search: '',

    form: {
      numero: '',
      entreprise_id: '',
      montant_total: 0,
      montant_paye: 0,
      date_facture: '',
      date_echeance: '',
      statut: 'en_attente',
      observations: '',
    },

    genForm: {
      date_debut: '',
      date_fin: '',
      entreprise_id: '',
    },

    resetForm() {
      this.form = {
        numero: '',
        entreprise_id: '',
        montant_total: 0,
        montant_paye: 0,
        date_facture: '',
        date_echeance: '',
        statut: 'en_attente',
        observations: '',
      };
    },

    async load() {
      this.loading = true;
      try {
        let url = '/api/factures-entreprises?';
        if (this.search) url += 'search=' + encodeURIComponent(this.search) + '&';

        const res = await api(url);
        this.items = res.data || [];
      } catch (e) {
        toast('Erreur chargement des factures', 'error');
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
        numero: item.numero || '',
        entreprise_id: item.entreprise_id || '',
        montant_total: parseInt(item.montant_total) || 0,
        montant_paye: parseInt(item.montant_paye) || 0,
        date_facture: item.date_facture || '',
        date_echeance: item.date_echeance || '',
        statut: item.statut || 'en_attente',
        observations: item.observations || '',
      };
      this.loadEntreprises();
      this.showModal = true;
    },

    openGenerate() {
      this.genForm = { date_debut: '', date_fin: '', entreprise_id: '' };
      this.loadEntreprises();
      this.showGenerateModal = true;
    },

    async save() {
      this.saving = true;
      try {
        const body = {
          entreprise_id: this.form.entreprise_id,
          montant_total: this.form.montant_total,
          montant_paye: this.form.montant_paye,
          date_facture: this.form.date_facture,
          date_echeance: this.form.date_echeance,
          statut: this.form.statut,
          observations: this.form.observations,
        };

        if (this.editing) {
          await api('/api/factures-entreprises/' + this.editId, { method: 'POST', body });
          toast('Facture mise à jour');
        } else {
          await api('/api/factures-entreprises', { method: 'POST', body });
          toast('Facture créée');
        }
        this.showModal = false;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de l’enregistrement', 'error');
      }
      this.saving = false;
    },

    async generate() {
      if (!this.genForm.date_debut || !this.genForm.date_fin) {
        toast('Veuillez renseigner les dates', 'error');
        return;
      }
      this.generating = true;
      try {
        const body = {
          date_debut: this.genForm.date_debut,
          date_fin: this.genForm.date_fin,
        };
        if (this.genForm.entreprise_id) {
          body.entreprise_id = this.genForm.entreprise_id;
        }
        const res = await api('/api/factures-entreprises/generate', { method: 'POST', body });
        toast(res.count + ' facture(s) générée(s)');
        this.showGenerateModal = false;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la génération', 'error');
      }
      this.generating = false;
    },

    async remove(item) {
      if (!window.confirm('Supprimer la facture « ' + item.numero + ' » ?')) return;
      try {
        await api('/api/factures-entreprises/' + item.id, { method: 'DELETE' });
        toast('Facture supprimée');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },

    openEtat(item) {
      window.open('/api/factures-entreprises/' + item.id + '/etat', '_blank');
    },

    openEtatGlobal() {
      // Build URL with IDs of all currently displayed factures
      const ids = this.items.map(i => i.id).join(',');
      window.open('/api/factures-entreprises/etat-global' + (ids ? '?ids=' + ids : ''), '_blank');
    },

    formatStatut(s) {
      const map = {
        en_attente: 'En attente',
        validee: 'Validée',
        partiel: 'Partiel',
        payee: 'Payée',
      };
      return map[s] || s;
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
