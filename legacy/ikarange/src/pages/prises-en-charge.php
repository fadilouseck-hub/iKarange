<?php
$user = requireAuth();
$pageTitle = 'Prises en charge';
$activeTab = 'pec';
require basePath('src/views/layout.php');
?>

<div x-data="pecPage()" x-init="load()">

  <!-- Header: Search + Filter + Button -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center gap-2 flex-wrap" style="flex:1;">
      <div class="ik-search">
        <i class="bx bx-search"></i>
        <input type="text" placeholder="Rechercher par nom, n&deg; PEC, prestataire..."
               x-model="search" @input.debounce.400ms="load()">
      </div>
      <select class="form-select form-select-sm" style="width:auto;min-width:150px;"
              x-model="filterStatut" @change="load()">
        <option value="">Tous les statuts</option>
        <option value="en_attente">En attente</option>
        <option value="approuvee">Approuv&eacute;e</option>
        <option value="reglee">R&eacute;gl&eacute;e</option>
        <option value="rejetee">Rejet&eacute;e</option>
        <option value="facturee">Factur&eacute;e</option>
      </select>
    </div>
    <button class="btn btn-primary btn-sm" @click="openCreate()">
      <i class="bx bx-plus"></i> Nouvelle PEC
    </button>
  </div>

  <!-- Table -->
  <div class="ik-card">
    <template x-if="loading">
      <div class="text-center py-4"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#aaa;"></i></div>
    </template>

    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-file"></i>
        <p>Aucune prise en charge</p>
      </div>
    </template>

    <template x-if="!loading && items.length > 0">
      <div style="overflow-x:auto;">
        <table class="ik-table">
          <thead>
            <tr>
              <th>N&deg; PEC</th>
              <th>Adh&eacute;rent</th>
              <th>Prestataire</th>
              <th>Type</th>
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
                  <div class="row-name" x-text="(item.adherent_nom || '') + ' ' + (item.adherent_prenom || '')"></div>
                  <div class="row-sub" x-text="item.entreprise_nom || ''"></div>
                </td>
                <td x-text="item.prestataire_nom || '—'"></td>
                <td x-text="formatTypeActe(item.type_acte)"></td>
                <td>
                  <div style="font-weight:600;" x-text="fmtMoney(item.montant_total)"></div>
                  <div class="row-sub" x-text="'IPM: ' + fmtMoney(item.part_ipm)"></div>
                </td>
                <td>
                  <span class="badge-status"
                        :class="'badge-' + item.statut"
                        x-text="formatStatut(item.statut)"></span>
                </td>
                <td>
                  <a :href="'/prises-en-charge/' + item.id" class="action-btn view" title="D&eacute;tails">
                    <i class="bx bx-show"></i>
                  </a>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </div>

  <!-- Modal: Nouvelle PEC -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:640px;">
        <div class="ik-modal-header">
          <h3>Nouvelle prise en charge</h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

            <!-- Adh&eacute;rent -->
            <div class="mb-3">
              <label class="form-label">Adh&eacute;rent <span style="color:#dc3545;">*</span></label>
              <select class="form-select" x-model="form.adherent_id" required @change="lookupBareme()">
                <option value="">-- S&eacute;lectionner --</option>
                <template x-for="a in adherents" :key="a.id">
                  <option :value="a.id" x-text="a.nom + ' ' + a.prenom + ' (' + a.matricule + ')'"></option>
                </template>
              </select>
            </div>

            <!-- Prestataire -->
            <div class="mb-3">
              <label class="form-label">Prestataire <span style="color:#dc3545;">*</span></label>
              <select class="form-select" x-model="form.prestataire_id" required>
                <option value="">-- S&eacute;lectionner --</option>
                <template x-for="p in prestataires" :key="p.id">
                  <option :value="p.id" x-text="p.nom"></option>
                </template>
              </select>
            </div>

            <!-- Type d'acte + Sous-type + Date des soins -->
            <div class="row g-3 mb-3">
              <div :class="form.type_acte === 'maternite' ? 'col-md-4' : 'col-md-6'">
                <label class="form-label">Type d'acte</label>
                <select class="form-select" x-model="form.type_acte" @change="onActTypeChange()">
                  <option value="consultation">Consultation</option>
                  <option value="analyse">Analyse</option>
                  <option value="pharmacie">Pharmacie</option>
                  <option value="hospitalisation">Hospitalisation</option>
                  <option value="imagerie">Imagerie</option>
                  <option value="chirurgie">Chirurgie</option>
                  <option value="dentaire">Dentaire</option>
                  <option value="optique">Optique</option>
                  <option value="maternite">Maternit&eacute;</option>
                  <option value="autre">Autre</option>
                </select>
              </div>
              <div class="col-md-4" x-show="form.type_acte === 'maternite'" x-cloak>
                <label class="form-label">Sous-type</label>
                <select class="form-select" x-model="form.sous_type_acte" @change="onActTypeChange()">
                  <option value="maternite_simple">Accouchement simple</option>
                  <option value="maternite_multiple">Accouchement multiple</option>
                  <option value="maternite_chirurgicale">Accouchement chirurgical</option>
                </select>
              </div>
              <div :class="form.type_acte === 'maternite' ? 'col-md-4' : 'col-md-6'">
                <label class="form-label">Date des soins</label>
                <input type="date" class="form-control" x-model="form.date_soins">
              </div>
            </div>

            <!-- Bareme info banner -->
            <template x-if="baremeInfo && baremeInfo.has_bareme && baremeInfo.plafond_acte">
              <div style="background:#fff3e0;border:1px solid #ffe0b2;border-radius:8px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;font-size:0.78rem;color:#e65100;">
                <i class="bx bx-info-circle"></i>
                Plafond <strong x-text="baremeInfo.libelle || ''"></strong> :
                <strong x-text="fmtMoney(baremeInfo.plafond_acte)"></strong>
                <span x-show="baremeInfo.periode_plafond === 'par_an'"> / an</span>
                <span x-show="baremeInfo.periode_plafond === 'par_2_ans'"> / 2 ans</span>
                <span x-show="baremeInfo.periode_plafond === 'par_evenement'"> / acte</span>
                <template x-if="baremeInfo.disponible_plafond !== null && baremeInfo.periode_plafond !== 'par_evenement'">
                  <span> &mdash; Disponible : <strong x-text="fmtMoney(baremeInfo.disponible_plafond)"></strong></span>
                </template>
              </div>
            </template>

            <!-- Montant total + Taux couverture -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Montant total F CFA</label>
                <input type="number" class="form-control" x-model.number="form.montant_total" min="0"
                       @input="calcParts()">
              </div>
              <div class="col-md-6">
                <label class="form-label">Taux de couverture %</label>
                <input type="number" class="form-control" x-model.number="form.taux_couverture" min="0" max="100"
                       @input="calcParts()">
              </div>
            </div>

            <!-- Part IPM + Part adh&eacute;rent (readonly) -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Part IPM</label>
                <input type="text" class="form-control" :value="fmtMoney(form.part_ipm)" readonly
                       style="background:#f8f9fa;">
              </div>
              <div class="col-md-6">
                <label class="form-label">Part adh&eacute;rent</label>
                <input type="text" class="form-control" :value="fmtMoney(form.part_adherent)" readonly
                       style="background:#f8f9fa;">
              </div>
            </div>

            <!-- Motif -->
            <div class="mb-3">
              <label class="form-label">Motif</label>
              <input type="text" class="form-control" x-model="form.motif">
            </div>

            <!-- Statut -->
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="form.statut">
                <option value="en_attente">En attente</option>
                <option value="approuvee">Approuv&eacute;e</option>
                <option value="reglee">R&eacute;gl&eacute;e</option>
                <option value="rejetee">Rejet&eacute;e</option>
                <option value="facturee">Factur&eacute;e</option>
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
              <span x-show="!saving">Cr&eacute;er</span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>

<script>
function pecPage() {
  return {
    loading: true,
    items: [],
    adherents: [],
    prestataires: [],
    showModal: false,
    saving: false,
    search: '',
    filterStatut: '',
    baremeInfo: null,

    form: {
      adherent_id: '',
      prestataire_id: '',
      type_acte: 'consultation',
      sous_type_acte: '',
      date_soins: '',
      montant_total: 0,
      taux_couverture: 80,
      part_ipm: 0,
      part_adherent: 0,
      motif: '',
      statut: 'en_attente',
      observations: '',
    },

    async load() {
      this.loading = true;
      try {
        let url = '/api/pec?';
        if (this.search) url += 'search=' + encodeURIComponent(this.search) + '&';
        if (this.filterStatut) url += 'statut=' + encodeURIComponent(this.filterStatut) + '&';

        const res = await api(url);
        this.items = res.data || [];
      } catch (e) {
        toast('Erreur chargement des PEC', 'error');
      }
      this.loading = false;
    },

    async loadRefs() {
      try {
        const [aRes, pRes] = await Promise.all([
          api('/api/adherents'),
          api('/api/prestataires'),
        ]);
        this.adherents = aRes.data || [];
        this.prestataires = pRes.data || [];
      } catch (e) {
        // silent
      }
    },

    calcParts() {
      const mt = this.form.montant_total || 0;
      const tc = this.form.taux_couverture || 0;
      this.form.part_ipm = Math.round(mt * tc / 100);
      this.form.part_adherent = mt - this.form.part_ipm;
    },

    async onActTypeChange() {
      if (this.form.type_acte !== 'maternite') {
        this.form.sous_type_acte = '';
      } else if (!this.form.sous_type_acte) {
        this.form.sous_type_acte = 'maternite_simple';
      }
      await this.lookupBareme();
    },

    async lookupBareme() {
      if (!this.form.adherent_id || !this.form.type_acte) {
        this.baremeInfo = null;
        return;
      }
      try {
        let url = '/api/baremes/lookup?adherent_id=' + this.form.adherent_id + '&type_acte=' + this.form.type_acte;
        if (this.form.sous_type_acte) url += '&sous_type_acte=' + this.form.sous_type_acte;
        const res = await api(url);
        this.baremeInfo = res;
        if (res.has_bareme) {
          this.form.taux_couverture = res.taux_couverture;
          this.calcParts();
        }
      } catch (e) {
        this.baremeInfo = null;
      }
    },

    openCreate() {
      this.baremeInfo = null;
      this.form = {
        adherent_id: '',
        prestataire_id: '',
        type_acte: 'consultation',
        sous_type_acte: '',
        date_soins: '',
        montant_total: 0,
        taux_couverture: 80,
        part_ipm: 0,
        part_adherent: 0,
        motif: '',
        statut: 'en_attente',
        observations: '',
      };
      this.loadRefs();
      this.showModal = true;
    },

    async save() {
      this.saving = true;
      try {
        const body = {
          adherent_id: this.form.adherent_id,
          prestataire_id: this.form.prestataire_id,
          type_acte: this.form.type_acte,
          sous_type_acte: this.form.sous_type_acte || '',
          date_soins: this.form.date_soins,
          montant_total: this.form.montant_total,
          taux_couverture: this.form.taux_couverture,
          motif: this.form.motif,
          statut: this.form.statut,
          observations: this.form.observations,
        };
        await api('/api/pec', { method: 'POST', body });
        toast('Prise en charge créée');
        this.showModal = false;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la création', 'error');
      }
      this.saving = false;
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

    formatTypeActe(t) {
      const map = {
        consultation: 'Consultation',
        analyse: 'Analyse',
        pharmacie: 'Pharmacie',
        hospitalisation: 'Hospitalisation',
        imagerie: 'Imagerie',
        chirurgie: 'Chirurgie',
        dentaire: 'Dentaire',
        optique: 'Optique',
        maternite: 'Maternite',
        autre: 'Autre',
      };
      return map[t] || t;
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
