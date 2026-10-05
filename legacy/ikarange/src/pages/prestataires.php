<?php
$user = requireAuth();
$pageTitle = 'Prestataires';
$activeTab = 'prestataires';
require basePath('src/views/layout.php');
?>

<div x-data="prestatairesPage()" x-init="load()">

  <!-- Toolbar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="ik-search">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher un prestataire..." x-model="search" @input.debounce.350ms="load()">
    </div>
    <button class="btn btn-primary btn-sm" @click="openCreate()">
      <i class="bx bx-plus"></i> Nouveau prestataire
    </button>
  </div>

  <!-- Table card -->
  <div class="ik-card" style="overflow-x:auto;">

    <!-- Loading skeleton -->
    <template x-if="loading">
      <div>
        <div class="skeleton" style="height:18px;width:60%;margin-bottom:1rem;"></div>
        <div class="skeleton" style="height:14px;width:90%;margin-bottom:0.75rem;"></div>
        <div class="skeleton" style="height:14px;width:80%;margin-bottom:0.75rem;"></div>
        <div class="skeleton" style="height:14px;width:85%;margin-bottom:0.75rem;"></div>
      </div>
    </template>

    <!-- Empty state -->
    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-plus-medical"></i>
        <p>Aucun prestataire trouv&eacute;</p>
      </div>
    </template>

    <!-- Data table -->
    <template x-if="!loading && items.length > 0">
      <table class="ik-table">
        <thead>
          <tr>
            <th>Prestataire</th>
            <th>Type</th>
            <th>Ville</th>
            <th>T&eacute;l&eacute;phone</th>
            <th>Statut</th>
            <th style="width:90px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="item in items" :key="item.id">
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:0.6rem;">
                  <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <img src="/images/logo-icon.png" alt="" style="width:34px;height:34px;object-fit:contain;">
                  </div>
                  <div>
                    <div class="row-name" x-text="item.nom"></div>
                    <div class="row-sub" x-text="item.specialites || '—'"></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge-type" :class="'badge-' + item.type" x-text="typeLabel(item.type)"></span>
              </td>
              <td x-text="item.ville || '—'"></td>
              <td x-text="item.telephone || '—'"></td>
              <td>
                <span class="badge-status" :class="'badge-' + item.statut" x-text="statutLabel(item.statut)"></span>
              </td>
              <td>
                <button class="action-btn edit" title="Modifier" @click="openEdit(item)">
                  <i class="bx bx-edit-alt"></i>
                </button>
                <button class="action-btn delete" title="Supprimer" @click="remove(item)">
                  <i class="bx bx-trash"></i>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>
  </div>

  <!-- ── Modal Nouveau / Modifier prestataire ── -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal">
        <div class="ik-modal-header">
          <h3 x-text="editing ? 'Modifier le prestataire' : 'Nouveau prestataire'"></h3>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <div class="ik-modal-body">

          <!-- Nom -->
          <div class="mb-3">
            <label class="form-label">Nom *</label>
            <input type="text" class="form-control" x-model="form.nom" placeholder="Nom du prestataire">
          </div>

          <!-- Type + Ville -->
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label">Type *</label>
              <select class="form-select" x-model="form.type">
                <option value="clinique">Clinique</option>
                <option value="hopital">H&ocirc;pital</option>
                <option value="pharmacie">Pharmacie</option>
                <option value="laboratoire">Laboratoire</option>
                <option value="centre_imagerie">Centre d'imagerie</option>
                <option value="dentiste">Dentiste</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Ville</label>
              <input type="text" class="form-control" x-model="form.ville" placeholder="Ville">
            </div>
          </div>

          <!-- Adresse -->
          <div class="mb-3">
            <label class="form-label">Adresse</label>
            <input type="text" class="form-control" x-model="form.adresse" placeholder="Adresse compl&egrave;te">
          </div>

          <!-- Téléphone + Email -->
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label">T&eacute;l&eacute;phone</label>
              <input type="text" class="form-control" x-model="form.telephone" placeholder="T&eacute;l&eacute;phone">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" x-model="form.email" placeholder="Email">
            </div>
          </div>

          <!-- Spécialités -->
          <div class="mb-3">
            <label class="form-label">Sp&eacute;cialit&eacute;s</label>
            <input type="text" class="form-control" x-model="form.specialites" placeholder="Ex: Cardiologie, P&eacute;diatrie...">
          </div>

          <!-- Statut -->
          <div class="mb-3">
            <label class="form-label">Statut</label>
            <select class="form-select" x-model="form.statut">
              <option value="agree">Agr&eacute;&eacute;</option>
              <option value="suspendu">Suspendu</option>
              <option value="resilie">R&eacute;sili&eacute;</option>
            </select>
          </div>

        </div>
        <div class="ik-modal-footer">
          <button class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
          <button class="btn btn-primary btn-sm" @click="save()" :disabled="saving">
            <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
            <span x-text="editing ? 'Mettre &agrave; jour' : 'Cr&eacute;er'"></span>
          </button>
        </div>
      </div>
    </div>
  </template>

</div>

<script>
function prestatairesPage() {
  return {
    loading: true,
    saving: false,
    items: [],
    total: 0,
    search: '',

    showModal: false,
    editing: null,
    form: {},

    typeLabel(t) {
      const map = {
        clinique: 'Clinique',
        hopital: 'Hôpital',
        pharmacie: 'Pharmacie',
        laboratoire: 'Laboratoire',
        centre_imagerie: "Centre d'imagerie",
        dentiste: 'Dentiste',
      };
      return map[t] || t;
    },

    statutLabel(s) {
      const map = {
        agree: 'Agréé',
        suspendu: 'Suspendu',
        resilie: 'Résilié',
      };
      return map[s] || s;
    },

    async load() {
      this.loading = true;
      try {
        const params = new URLSearchParams();
        if (this.search) params.set('q', this.search);
        const res = await api('/api/prestataires?' + params.toString());
        this.items = res.data || [];
        this.total = res.total || 0;
      } catch (e) {
        toast('Erreur chargement des prestataires', 'error');
      }
      this.loading = false;
    },

    resetForm() {
      this.form = {
        nom: '',
        type: 'clinique',
        ville: '',
        adresse: '',
        telephone: '',
        email: '',
        specialites: '',
        statut: 'agree',
      };
    },

    openCreate() {
      this.editing = null;
      this.resetForm();
      this.showModal = true;
    },

    openEdit(item) {
      this.editing = item.id;
      this.form = {
        nom: item.nom || '',
        type: item.type || 'clinique',
        ville: item.ville || '',
        adresse: item.adresse || '',
        telephone: item.telephone || '',
        email: item.email || '',
        specialites: item.specialites || '',
        statut: item.statut || 'agree',
      };
      this.showModal = true;
    },

    async save() {
      if (!this.form.nom || !this.form.type) {
        toast('Le nom et le type sont requis', 'error');
        return;
      }
      this.saving = true;
      try {
        const url = this.editing
          ? '/api/prestataires/' + this.editing
          : '/api/prestataires';
        await api(url, { method: 'POST', body: this.form });
        toast(this.editing ? 'Prestataire mis à jour' : 'Prestataire créé', 'success');
        this.showModal = false;
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de l’enregistrement', 'error');
      }
      this.saving = false;
    },

    async remove(item) {
      if (!confirm('Supprimer le prestataire « ' + item.nom + ' » ?')) return;
      try {
        await api('/api/prestataires/' + item.id, { method: 'DELETE' });
        toast('Prestataire supprimé', 'success');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
