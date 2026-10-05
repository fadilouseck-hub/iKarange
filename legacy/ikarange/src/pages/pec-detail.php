<?php
$user = requireAuth();
$pageTitle = 'Détails PEC';
$activeTab = 'pec';
require basePath('src/views/layout.php');
?>

<div x-data="pecDetailPage()" x-init="load()">

  <!-- Back button -->
  <div class="mb-3">
    <a href="/prises-en-charge" style="color:var(--primary);text-decoration:none;font-size:0.9rem;">
      <i class="bx bx-arrow-back"></i> Retour aux prises en charge
    </a>
  </div>

  <template x-if="loading">
    <div class="text-center py-5"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#aaa;"></i></div>
  </template>

  <template x-if="!loading && !pec.id">
    <div class="empty-state">
      <i class="bx bx-error"></i>
      <p>Prise en charge introuvable</p>
    </div>
  </template>

  <template x-if="!loading && pec.id">
    <div>
      <!-- Header card -->
      <div class="ik-card mb-4">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
          <div>
            <h5 style="font-weight:700;margin:0;" x-text="'PEC ' + pec.numero"></h5>
            <div style="font-size:0.85rem;color:#888;margin-top:0.25rem;"
                 x-text="'Cr&eacute;&eacute;e le ' + fmtDate(pec.created_at)"></div>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <span class="badge-status"
                  :class="'badge-' + pec.statut"
                  x-text="formatStatut(pec.statut)"
                  style="font-size:0.85rem;padding:0.4rem 1rem;"></span>
            <button class="btn btn-primary btn-sm" @click="openEdit()">
              <i class="bx bx-edit"></i> Modifier
            </button>
          </div>
        </div>
      </div>

      <!-- Details grid -->
      <div class="row g-3 mb-4">
        <!-- Adh&eacute;rent info -->
        <div class="col-md-6">
          <div class="ik-card" style="height:100%;">
            <h6 style="font-weight:600;margin-bottom:1rem;color:var(--primary);">
              <i class="bx bx-user"></i> Adh&eacute;rent
            </h6>
            <div class="detail-row">
              <span class="detail-label">Nom complet</span>
              <span class="detail-value" x-text="(pec.adherent_nom || '') + ' ' + (pec.adherent_prenom || '')"></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Matricule</span>
              <span class="detail-value" x-text="pec.adherent_matricule || '—'"></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Entreprise</span>
              <span class="detail-value" x-text="pec.entreprise_nom || '—'"></span>
            </div>
          </div>
        </div>

        <!-- Prestataire info -->
        <div class="col-md-6">
          <div class="ik-card" style="height:100%;">
            <h6 style="font-weight:600;margin-bottom:1rem;color:var(--primary);">
              <i class="bx bx-plus-medical"></i> Prestataire
            </h6>
            <div class="detail-row">
              <span class="detail-label">Nom</span>
              <span class="detail-value" x-text="pec.prestataire_nom || '—'"></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Type d'acte</span>
              <span class="detail-value" x-text="formatTypeActe(pec.type_acte)"></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Date des soins</span>
              <span class="detail-value" x-text="fmtDate(pec.date_soins)"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial details -->
      <div class="ik-card mb-4">
        <h6 style="font-weight:600;margin-bottom:1rem;color:var(--primary);">
          <i class="bx bx-money"></i> D&eacute;tails financiers
        </h6>
        <div class="row g-3">
          <div class="col-sm-6 col-md-3">
            <div class="stat-card" style="text-align:center;">
              <div class="stat-label">Montant total</div>
              <div class="stat-value" style="font-size:1.2rem;" x-text="fmtMoney(pec.montant_total)"></div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="stat-card" style="text-align:center;">
              <div class="stat-label">Taux couverture</div>
              <div class="stat-value" style="font-size:1.2rem;" x-text="(pec.taux_couverture || 0) + '%'"></div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="stat-card" style="text-align:center;">
              <div class="stat-label">Part IPM</div>
              <div class="stat-value" style="font-size:1.2rem;color:#2e7d32;" x-text="fmtMoney(pec.part_ipm)"></div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="stat-card" style="text-align:center;">
              <div class="stat-label">Part adh&eacute;rent</div>
              <div class="stat-value" style="font-size:1.2rem;color:#e65100;" x-text="fmtMoney(pec.part_adherent)"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Motif & Observations -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="ik-card" style="height:100%;">
            <h6 style="font-weight:600;margin-bottom:0.75rem;">Motif</h6>
            <p style="color:#555;margin:0;" x-text="pec.motif || 'Aucun motif renseign&eacute;'"></p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="ik-card" style="height:100%;">
            <h6 style="font-weight:600;margin-bottom:0.75rem;">Observations</h6>
            <p style="color:#555;margin:0;" x-text="pec.observations || 'Aucune observation'"></p>
          </div>
        </div>
      </div>
    </div>
  </template>

  <!-- Modal: Modifier PEC -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:640px;">
        <div class="ik-modal-header">
          <h3>Modifier la prise en charge</h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

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

            <!-- Motif -->
            <div class="mb-3">
              <label class="form-label">Motif</label>
              <input type="text" class="form-control" x-model="form.motif">
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
              <span x-show="!saving">Mettre &agrave; jour</span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>

<style>
.detail-row { display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid #f0f0f0; }
.detail-row:last-child { border-bottom:none; }
.detail-label { color:#888; font-size:0.85rem; }
.detail-value { font-weight:600; color:#333; font-size:0.85rem; }
</style>

<script>
function pecDetailPage() {
  const pecId = window.location.pathname.split('/').pop();
  return {
    loading: true,
    pec: {},
    showModal: false,
    saving: false,
    form: {},

    async load() {
      this.loading = true;
      try {
        this.pec = await api('/api/pec/' + pecId);
      } catch (e) {
        toast('Erreur chargement de la PEC', 'error');
      }
      this.loading = false;
    },

    openEdit() {
      this.form = {
        montant_total: this.pec.montant_total || 0,
        taux_couverture: this.pec.taux_couverture || 80,
        part_ipm: this.pec.part_ipm || 0,
        part_adherent: this.pec.part_adherent || 0,
        statut: this.pec.statut || 'en_attente',
        motif: this.pec.motif || '',
        observations: this.pec.observations || '',
      };
      this.showModal = true;
    },

    calcParts() {
      const mt = this.form.montant_total || 0;
      const tc = this.form.taux_couverture || 0;
      this.form.part_ipm = Math.round(mt * tc / 100);
      this.form.part_adherent = mt - this.form.part_ipm;
    },

    async save() {
      this.saving = true;
      try {
        await api('/api/pec/' + pecId, { method: 'POST', body: this.form });
        toast('Prise en charge mise à jour');
        this.showModal = false;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la mise à jour', 'error');
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
        dentaire: 'Dentaire',
      };
      return map[t] || t;
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
