<?php
$user = requireAuth();
$pageTitle = 'Entreprises';
$activeTab = 'entreprises';
require basePath('src/views/layout.php');
?>

<div x-data="entreprisesPage()" x-init="load()">

  <!-- Toolbar: search + add button -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
    <div class="ik-search">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher une entreprise..."
             x-model="search"
             @input.debounce.350ms="load()">
    </div>
    <button class="btn btn-primary" @click="openCreate()" style="display:flex;align-items:center;gap:0.4rem;">
      <i class="bx bx-plus"></i> Nouvelle entreprise
    </button>
  </div>

  <!-- Table card -->
  <div class="ik-card" style="padding:0;overflow-x:auto;">

    <!-- Empty state -->
    <template x-if="!loading && rows.length === 0">
      <div class="empty-state">
        <i class="bx bx-buildings"></i>
        <p>Aucune entreprise trouv&eacute;e</p>
      </div>
    </template>

    <!-- Data table -->
    <template x-if="rows.length > 0">
      <table class="ik-table">
        <thead>
          <tr>
            <th>Raison sociale</th>
            <th>NINEA</th>
            <th>T&eacute;l&eacute;phone</th>
            <th>Employ&eacute;s</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                  <div style="width:36px;height:36px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bx bx-buildings" style="font-size:1.1rem;color:#1565c0;"></i>
                  </div>
                  <div>
                    <div class="row-name" x-text="row.raison_sociale"></div>
                    <div class="row-sub" x-text="row.secteur_activite || ''"></div>
                  </div>
                </div>
              </td>
              <td x-text="row.ninea || '—'"></td>
              <td x-text="row.telephone || '—'"></td>
              <td x-text="row.nombre_employes || 0"></td>
              <td>
                <span class="badge-status"
                      :class="'badge-' + row.statut"
                      x-text="formatStatut(row.statut)"></span>
              </td>
              <td>
                <button class="action-btn view" title="Bar&egrave;me" @click="openBareme(row)">
                  <i class="bx bx-list-check"></i>
                </button>
                <button class="action-btn edit" title="Modifier" @click="openEdit(row)">
                  <i class="bx bx-pencil"></i>
                </button>
                <button class="action-btn delete" title="Supprimer" @click="remove(row)">
                  <i class="bx bx-trash"></i>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>
  </div>

  <!-- ── Modal Create / Edit ── -->
  <div x-show="showModal" x-cloak class="ik-modal-overlay"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click.self="confirmCloseModal('showModal', $data)">

    <div class="ik-modal" @click.stop>
      <div class="ik-modal-header">
        <h3 x-text="editing ? 'Modifier entreprise' : 'Nouvelle entreprise'"></h3>
        <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
      </div>

      <form @submit.prevent="save()">
        <div class="ik-modal-body">

          <!-- Row 1: Raison sociale + NINEA + Telephone -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Raison sociale *</label>
              <input type="text" class="form-control" x-model="form.raison_sociale" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">NINEA</label>
              <input type="text" class="form-control" x-model="form.ninea">
            </div>
            <div class="col-md-3">
              <label class="form-label">T&eacute;l&eacute;phone</label>
              <input type="text" class="form-control" x-model="form.telephone">
            </div>
          </div>

          <!-- Row 2: Email + Secteur -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" x-model="form.email">
            </div>
            <div class="col-md-6">
              <label class="form-label">Secteur d'activit&eacute;</label>
              <input type="text" class="form-control" x-model="form.secteur_activite">
            </div>
          </div>

          <!-- Row 3: Adresse -->
          <div class="mb-3">
            <label class="form-label">Adresse</label>
            <input type="text" class="form-control" x-model="form.adresse">
          </div>

          <!-- Row 4: Nombre employes + Taux cotisation -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Nombre d'employ&eacute;s</label>
              <input type="number" class="form-control" x-model.number="form.nombre_employes" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Taux de cotisation %</label>
              <input type="number" class="form-control" x-model.number="form.taux_cotisation" min="0" max="100" step="0.01">
            </div>
          </div>

          <!-- Row 5: Taux prise en charge + Date adhesion -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Taux de prise en charge %</label>
              <input type="number" class="form-control" x-model.number="form.taux_prise_en_charge" min="0" max="100" step="0.01">
            </div>
            <div class="col-md-6">
              <label class="form-label">Date d'adh&eacute;sion</label>
              <input type="date" class="form-control" x-model="form.date_adhesion">
            </div>
          </div>

          <!-- Row 6: Statut -->
          <div class="mb-3">
            <label class="form-label">Statut</label>
            <select class="form-select" x-model="form.statut">
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
              <option value="suspendu">Suspendu</option>
            </select>
          </div>

        </div>

        <div class="ik-modal-footer">
          <button type="button" class="btn btn-light" @click="showModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            <span x-show="!saving" x-text="editing ? 'Mettre à jour' : 'Créer'"></span>
            <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Modal Bareme ── -->
  <div x-show="showBaremeModal" x-cloak class="ik-modal-overlay"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click.self="confirmCloseModal('showBaremeModal', $data)">

    <div class="ik-modal" style="max-width:920px;" @click.stop>
      <div class="ik-modal-header">
        <h3>
          <i class="bx bx-list-check" style="color:var(--primary);"></i>
          Bar&egrave;me &mdash; <span x-text="baremeEntreprise?.raison_sociale || ''"></span>
        </h3>
        <button class="close-btn" @click="showBaremeModal = false"><i class="bx bx-x"></i></button>
      </div>

      <div class="ik-modal-body" style="padding:0.75rem 1rem;">

        <template x-if="baremeLoading">
          <div style="text-align:center;padding:2rem;color:#999;">
            <i class="bx bx-loader-alt bx-spin" style="font-size:1.5rem;"></i>
            <p style="margin-top:0.5rem;font-size:0.85rem;">Chargement...</p>
          </div>
        </template>

        <template x-if="!baremeLoading">
          <div style="overflow-x:auto;">
            <table class="ik-table" style="font-size:0.82rem;">
              <thead>
                <tr>
                  <th style="min-width:180px;">Prestation</th>
                  <th style="width:90px;">Taux %</th>
                  <th style="width:140px;">Plafond F CFA</th>
                  <th style="width:130px;">P&eacute;riode</th>
                  <th style="width:130px;">Appliquer par</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="(row, idx) in baremeRows" :key="row.type_acte">
                  <tr>
                    <td>
                      <span style="font-weight:500;" x-text="row.libelle"></span>
                      <div style="font-size:0.7rem;color:#aaa;" x-text="row.type_acte"></div>
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm"
                             x-model.number="row.taux_couverture" min="0" max="100" step="0.5"
                             style="width:70px;">
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm"
                             x-model.number="row.plafond_acte" min="0" step="1000"
                             placeholder="Frais r&eacute;els"
                             style="width:120px;">
                    </td>
                    <td>
                      <select class="form-select form-select-sm" x-model="row.periode_plafond"
                              :disabled="!row.plafond_acte"
                              style="width:120px;">
                        <option value="">—</option>
                        <option value="par_evenement">Par &eacute;v&egrave;nement</option>
                        <option value="par_an">Par an</option>
                        <option value="par_2_ans">Par 2 ans</option>
                      </select>
                    </td>
                    <td>
                      <select class="form-select form-select-sm" x-model="row.plafond_par"
                              :disabled="!row.plafond_acte"
                              style="width:120px;">
                        <option value="beneficiaire">B&eacute;n&eacute;ficiaire</option>
                        <option value="titulaire">Titulaire</option>
                      </select>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </template>

        <div style="margin-top:0.5rem;font-size:0.72rem;color:#999;">
          <i class="bx bx-info-circle"></i>
          Laissez le plafond vide pour &laquo; Frais r&eacute;els &raquo; (pas de plafond).
        </div>
      </div>

      <div class="ik-modal-footer">
        <button type="button" class="btn btn-light" @click="showBaremeModal = false">Fermer</button>
        <button type="button" class="btn btn-primary" @click="saveBareme()" :disabled="baremeSaving">
          <span x-show="!baremeSaving"><i class="bx bx-save"></i> Enregistrer</span>
          <span x-show="baremeSaving"><i class="bx bx-loader-alt bx-spin"></i></span>
        </button>
      </div>
    </div>
  </div>

</div>

<script>
function entreprisesPage() {
  return {
    loading: true,
    rows: [],
    search: '',
    showModal: false,
    editing: false,
    editId: null,
    saving: false,

    // Bareme state
    showBaremeModal: false,
    baremeEntreprise: null,
    baremeRows: [],
    baremeLoading: false,
    baremeSaving: false,

    defaultBaremeRows() {
      return [
        { type_acte: 'consultation', libelle: 'Consultation', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'pharmacie', libelle: 'Pharmacie', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'imagerie', libelle: 'Imagerie', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'analyse', libelle: 'Analyses / Biologie', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'hospitalisation', libelle: 'Hospitalisation', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'chirurgie', libelle: 'Chirurgie', taux_couverture: 80, plafond_acte: '', periode_plafond: '', plafond_par: 'beneficiaire' },
        { type_acte: 'maternite_simple', libelle: 'Accouchement simple', taux_couverture: 80, plafond_acte: 400000, periode_plafond: 'par_evenement', plafond_par: 'beneficiaire' },
        { type_acte: 'maternite_multiple', libelle: 'Accouchement multiple', taux_couverture: 80, plafond_acte: 500000, periode_plafond: 'par_evenement', plafond_par: 'beneficiaire' },
        { type_acte: 'maternite_chirurgicale', libelle: 'Accouchement chirurgical', taux_couverture: 80, plafond_acte: 700000, periode_plafond: 'par_evenement', plafond_par: 'beneficiaire' },
        { type_acte: 'dentaire', libelle: 'Dentaire', taux_couverture: 80, plafond_acte: 300000, periode_plafond: 'par_an', plafond_par: 'beneficiaire' },
        { type_acte: 'optique', libelle: 'Optique', taux_couverture: 80, plafond_acte: 250000, periode_plafond: 'par_2_ans', plafond_par: 'titulaire' },
        { type_acte: 'autre', libelle: 'Autre / Transport', taux_couverture: 80, plafond_acte: 100000, periode_plafond: 'par_an', plafond_par: 'beneficiaire' },
      ];
    },

    form: {
      raison_sociale: '',
      ninea: '',
      telephone: '',
      email: '',
      secteur_activite: '',
      adresse: '',
      nombre_employes: 0,
      taux_cotisation: 0,
      taux_prise_en_charge: 80,
      date_adhesion: '',
      statut: 'actif',
    },

    resetForm() {
      this.form = {
        raison_sociale: '',
        ninea: '',
        telephone: '',
        email: '',
        secteur_activite: '',
        adresse: '',
        nombre_employes: 0,
        taux_cotisation: 0,
        taux_prise_en_charge: 80,
        date_adhesion: '',
        statut: 'actif',
      };
    },

    async load() {
      try {
        const params = this.search ? '?search=' + encodeURIComponent(this.search) : '';
        const res = await api('/api/entreprises' + params);
        this.rows = res.data || [];
      } catch (e) {
        toast('Erreur chargement des entreprises', 'error');
      }
      this.loading = false;
    },

    formatStatut(s) {
      const map = {
        actif: 'Actif',
        inactif: 'Inactif',
        suspendu: 'Suspendu',
      };
      return map[s] || s;
    },

    openCreate() {
      this.editing = false;
      this.editId = null;
      this.resetForm();
      this.showModal = true;
    },

    openEdit(row) {
      this.editing = true;
      this.editId = row.id;
      this.form = {
        raison_sociale: row.raison_sociale || '',
        ninea: row.ninea || '',
        telephone: row.telephone || '',
        email: row.email || '',
        secteur_activite: row.secteur_activite || '',
        adresse: row.adresse || '',
        nombre_employes: parseInt(row.nombre_employes) || 0,
        taux_cotisation: parseFloat(row.taux_cotisation) || 0,
        taux_prise_en_charge: parseFloat(row.taux_prise_en_charge) || 80,
        date_adhesion: row.date_adhesion || '',
        statut: row.statut || 'actif',
      };
      this.showModal = true;
    },

    async save() {
      if (!this.form.raison_sociale.trim()) {
        toast('La raison sociale est obligatoire', 'error');
        return;
      }

      this.saving = true;
      try {
        if (this.editing) {
          await api('/api/entreprises/' + this.editId, {
            method: 'POST',
            body: this.form,
          });
          toast('Entreprise mise à jour');
        } else {
          await api('/api/entreprises', {
            method: 'POST',
            body: this.form,
          });
          toast('Entreprise créée');
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        toast(e.error || "Erreur lors de l'enregistrement", 'error');
      }
      this.saving = false;
    },

    async remove(row) {
      if (!window.confirm("Supprimer l'entreprise « " + row.raison_sociale + " » ?")) return;

      try {
        await api('/api/entreprises/' + row.id, { method: 'DELETE' });
        toast('Entreprise supprimée');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },

    // ── Bareme methods ──

    async openBareme(row) {
      this.baremeEntreprise = row;
      this.baremeLoading = true;
      this.showBaremeModal = true;

      try {
        const res = await api('/api/baremes?entreprise_id=' + row.id);
        if (res.data && res.data.length > 0) {
          this.baremeRows = res.data.map(r => ({
            ...r,
            taux_couverture: parseFloat(r.taux_couverture) || 80,
            plafond_acte: r.plafond_acte !== null ? parseInt(r.plafond_acte) : '',
            periode_plafond: r.periode_plafond || '',
            plafond_par: r.plafond_par || 'beneficiaire',
          }));
        } else {
          this.baremeRows = this.defaultBaremeRows();
        }
      } catch (e) {
        this.baremeRows = this.defaultBaremeRows();
      }
      this.baremeLoading = false;
    },

    async saveBareme() {
      this.baremeSaving = true;
      try {
        const lignes = this.baremeRows.map(r => ({
          type_acte: r.type_acte,
          libelle: r.libelle,
          taux_couverture: r.taux_couverture || 80,
          plafond_acte: r.plafond_acte === '' || r.plafond_acte === null || r.plafond_acte === 0 ? null : parseInt(r.plafond_acte),
          periode_plafond: (r.plafond_acte && r.plafond_acte !== '' && r.plafond_acte !== 0) ? (r.periode_plafond || 'par_an') : null,
          plafond_par: r.plafond_par || 'beneficiaire',
        }));

        await api('/api/baremes', {
          method: 'POST',
          body: { entreprise_id: this.baremeEntreprise.id, lignes }
        });
        toast("Bareme enregistre avec succes");
        this.showBaremeModal = false;
      } catch (e) {
        toast(e.error || "Erreur lors de la sauvegarde", 'error');
      }
      this.baremeSaving = false;
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
