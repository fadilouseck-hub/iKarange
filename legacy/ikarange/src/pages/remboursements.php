<?php
$user = requireAuth();
$pageTitle = 'Remboursements';
$activeTab = 'remboursements';
require basePath('src/views/layout.php');
?>

<div x-data="remboursementsPage()" x-init="load()">

  <!-- Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
      <div class="ik-search">
        <i class="bx bx-search"></i>
        <input type="text" placeholder="Rechercher par nom, matricule..."
               x-model="search" @input.debounce.350ms="filterRows()">
      </div>
      <select class="form-select form-select-sm" style="width:auto;" x-model="filterStatut" @change="load()">
        <option value="">Tous les statuts</option>
        <option value="soumis">Soumis</option>
        <option value="en_cours">En cours</option>
        <option value="valide">Valid&eacute;</option>
        <option value="rejete">Rejet&eacute;</option>
        <option value="paye">Pay&eacute;</option>
      </select>
    </div>
    <div style="font-size:0.82rem;color:#888;">
      <span x-text="filtered.length"></span> demande(s)
    </div>
  </div>

  <!-- Table -->
  <div class="ik-card" style="padding:0;overflow-x:auto;">
    <template x-if="!loading && filtered.length === 0">
      <div class="empty-state">
        <i class="bx bx-money"></i>
        <p>Aucune demande de remboursement</p>
      </div>
    </template>

    <template x-if="filtered.length > 0">
      <table class="ik-table">
        <thead>
          <tr>
            <th>N&deg;</th>
            <th>Adh&eacute;rent</th>
            <th>Date soins</th>
            <th>Acte</th>
            <th>Montant</th>
            <th>Rembours&eacute;</th>
            <th>Docs</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in filtered" :key="row.id">
            <tr>
              <td><span class="row-sub" x-text="row.numero"></span></td>
              <td>
                <div class="row-name" x-text="row.adherent_nom + ' ' + row.adherent_prenom"></div>
                <div class="row-sub" x-text="row.matricule + ' - ' + (row.entreprise_nom || '')"></div>
              </td>
              <td x-text="fmtDate(row.date_soins)"></td>
              <td x-text="row.type_acte"></td>
              <td style="font-weight:600;" x-text="fmtMoney(row.montant)"></td>
              <td style="color:var(--primary-dark);" x-text="fmtMoney(row.montant_rembourse)"></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:0.25rem;font-size:0.82rem;">
                  <i class="bx bx-paperclip"></i> <span x-text="row.nb_docs"></span>
                </span>
              </td>
              <td><span class="badge-status" :class="statutBadge(row.statut)" x-text="statutLabel(row.statut)"></span></td>
              <td>
                <button class="action-btn view" title="Voir" @click="openDetail(row)">
                  <i class="bx bx-show"></i>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>
  </div>

  <!-- Detail / Review Modal -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:650px;">
        <div class="ik-modal-header">
          <h3>Demande <span x-text="detail.numero" style="color:var(--primary);"></span></h3>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <div class="ik-modal-body">
          <!-- Adherent info -->
          <div style="background:#f8f9fa;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.82rem;">
            <strong x-text="detail.adherent_nom + ' ' + detail.adherent_prenom"></strong>
            <span style="color:#888;"> | </span>
            <span x-text="detail.matricule"></span>
            <span style="color:#888;"> | </span>
            <span x-text="detail.entreprise_nom"></span>
          </div>

          <!-- Claim details -->
          <div class="row g-3 mb-3" style="font-size:0.85rem;">
            <div class="col-sm-4">
              <span style="color:#888;">Type d'acte</span><br>
              <strong x-text="detail.type_acte"></strong>
            </div>
            <div class="col-sm-4">
              <span style="color:#888;">Date des soins</span><br>
              <strong x-text="fmtDate(detail.date_soins)"></strong>
            </div>
            <div class="col-sm-4">
              <span style="color:#888;">Montant demand&eacute;</span><br>
              <strong x-text="fmtMoney(detail.montant)"></strong>
            </div>
          </div>

          <template x-if="detail.motif">
            <div class="mb-3" style="font-size:0.85rem;">
              <span style="color:#888;">Motif :</span> <span x-text="detail.motif"></span>
            </div>
          </template>

          <!-- Documents -->
          <div class="mb-3">
            <label class="form-label" style="font-weight:600;">
              <i class="bx bx-paperclip"></i> Pi&egrave;ces justificatives (<span x-text="detail.nb_docs"></span>)
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
              <template x-for="doc in (detail.documents || [])" :key="doc.id">
                <a :href="'/api/portail-adherent/remboursement-doc/' + doc.id" target="_blank"
                   style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.35rem 0.65rem;background:#f0f0f0;border-radius:6px;font-size:0.78rem;color:#333;text-decoration:none;">
                  <i class="bx bx-file"></i>
                  <span x-text="doc.nom"></span>
                </a>
              </template>
            </div>
          </div>

          <hr style="border-color:#f0f0f0;">

          <!-- Admin action form -->
          <h6 style="font-weight:600;font-size:0.9rem;margin-bottom:0.75rem;">Traitement</h6>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="actionForm.statut">
                <option value="soumis">Soumis</option>
                <option value="en_cours">En cours de traitement</option>
                <option value="valide">Valid&eacute;</option>
                <option value="rejete">Rejet&eacute;</option>
                <option value="paye">Pay&eacute;</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Montant rembours&eacute;</label>
              <input type="number" class="form-control" x-model="actionForm.montant_rembourse" min="0">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Commentaire admin</label>
            <textarea class="form-control" rows="2" x-model="actionForm.commentaire_admin"
                      placeholder="Commentaire visible par l'adherent..."></textarea>
          </div>

          <template x-if="detail.commentaire_admin && detail.commentaire_admin !== actionForm.commentaire_admin">
            <div style="font-size:0.78rem;color:#888;margin-bottom:0.5rem;">
              Commentaire pr&eacute;c&eacute;dent : <em x-text="detail.commentaire_admin"></em>
            </div>
          </template>
        </div>
        <div class="ik-modal-footer">
          <button class="btn btn-light" @click="showModal = false">Fermer</button>
          <button class="btn btn-primary" @click="saveAction()" :disabled="saving">
            <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
            Enregistrer
          </button>
        </div>
      </div>
    </div>
  </template>
</div>

<script>
function remboursementsPage() {
  return {
    rows: [],
    filtered: [],
    loading: true,
    search: '',
    filterStatut: '',
    showModal: false,
    detail: null,
    saving: false,
    actionForm: { statut: '', montant_rembourse: 0, commentaire_admin: '' },

    async load() {
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.filterStatut) params.set('statut', this.filterStatut);
        const res = await api('/api/remboursements?' + params.toString());
        this.rows = res.data || [];
        this.filterRows();
      } catch(e) {
        toast('Erreur chargement', 'error');
      }
      this.loading = false;
    },

    filterRows() {
      const s = this.search.toLowerCase();
      this.filtered = s
        ? this.rows.filter(r =>
            (r.adherent_nom + ' ' + r.adherent_prenom).toLowerCase().includes(s) ||
            (r.matricule || '').toLowerCase().includes(s) ||
            (r.numero || '').toLowerCase().includes(s))
        : [...this.rows];
    },

    openDetail(row) {
      this.detail = row;
      this.actionForm = {
        statut: row.statut,
        montant_rembourse: row.montant_rembourse || 0,
        commentaire_admin: row.commentaire_admin || '',
      };
      this.showModal = true;
    },

    async saveAction() {
      this.saving = true;
      try {
        await api('/api/remboursements/' + this.detail.id, {
          method: 'POST',
          body: this.actionForm,
        });
        toast('Remboursement mis a jour', 'success');
        this.showModal = false;
        this.load();
      } catch(e) {
        toast(e.error || 'Erreur', 'error');
      }
      this.saving = false;
    },

    statutLabel(s) {
      const map = { soumis: 'Soumis', en_cours: 'En cours', valide: 'Valide', rejete: 'Rejete', paye: 'Paye' };
      return map[s] || s;
    },

    statutBadge(s) {
      const map = { soumis: 'badge-en_attente', en_cours: 'badge-facturee', valide: 'badge-approuvee', rejete: 'badge-rejetee', paye: 'badge-payee' };
      return map[s] || 'badge-en_attente';
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
