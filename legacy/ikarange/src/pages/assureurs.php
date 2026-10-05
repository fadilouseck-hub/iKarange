<?php
$user = requireSuperAdmin();
$pageTitle = 'Assureurs';
$activeTab = 'assureurs';
require basePath('src/views/layout.php');
?>

<div x-data="assureursPage()" x-init="load()">

  <!-- Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
    <div class="ik-search">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher un assureur..."
             x-model="search" @input.debounce.350ms="load()">
    </div>
    <button class="btn btn-primary" @click="openCreate()" style="display:flex;align-items:center;gap:0.4rem;">
      <i class="bx bx-plus"></i> Nouvel assureur
    </button>
  </div>

  <!-- Table card -->
  <div class="ik-card" style="padding:0;overflow-x:auto;">

    <template x-if="!loading && rows.length === 0">
      <div class="empty-state">
        <i class="bx bx-globe"></i>
        <p>Aucun assureur trouv&eacute;</p>
      </div>
    </template>

    <template x-if="rows.length > 0">
      <table class="ik-table">
        <thead>
          <tr>
            <th>Assureur</th>
            <th>Contact</th>
            <th>Entreprises</th>
            <th>Adh&eacute;rents</th>
            <th>PEC</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr>
              <td>
                <div class="row-name" x-text="row.name"></div>
                <div class="row-sub" x-text="row.contact_email || ''"></div>
              </td>
              <td>
                <span x-text="row.contact_name || '—'"></span><br>
                <span class="row-sub" x-text="row.contact_phone || ''"></span>
              </td>
              <td><span style="font-weight:600;" x-text="row.nb_entreprises"></span></td>
              <td><span style="font-weight:600;" x-text="row.nb_adherents"></span></td>
              <td><span x-text="row.nb_pec"></span></td>
              <td>
                <span class="badge-status" :class="row.is_active == 1 ? 'badge-actif' : 'badge-inactif'"
                      x-text="row.is_active == 1 ? 'Actif' : 'Inactif'"></span>
              </td>
              <td>
                <button class="action-btn edit" title="Modifier" @click="openEdit(row)">
                  <i class="bx bx-edit-alt"></i>
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>
  </div>

  <!-- Create/Edit Modal -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal" style="max-width:520px;">
        <div class="ik-modal-header">
          <h3 x-text="editing ? 'Modifier l\'assureur' : 'Nouvel assureur'"></h3>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <div class="ik-modal-body">
          <div class="mb-3">
            <label class="form-label">Nom de l'assureur <span style="color:red;">*</span></label>
            <input type="text" class="form-control" x-model="form.name" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label">Nom du contact</label>
              <input type="text" class="form-control" x-model="form.contact_name">
            </div>
            <div class="col-sm-6">
              <label class="form-label">T&eacute;l&eacute;phone</label>
              <input type="text" class="form-control" x-model="form.contact_phone">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email du contact</label>
            <input type="email" class="form-control" x-model="form.contact_email">
          </div>
          <div class="mb-3">
            <label class="form-label">Adresse</label>
            <textarea class="form-control" rows="2" x-model="form.address"></textarea>
          </div>
          <template x-if="editing">
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select class="form-select" x-model="form.is_active">
                <option value="1">Actif</option>
                <option value="0">Inactif</option>
              </select>
            </div>
          </template>
        </div>
        <div class="ik-modal-footer">
          <button class="btn btn-light" @click="showModal = false">Annuler</button>
          <button class="btn btn-primary" @click="save()" :disabled="saving">
            <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
            <span x-text="editing ? 'Enregistrer' : 'Cr&eacute;er'"></span>
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Success info after creation -->
  <template x-if="createdInfo">
    <div class="ik-modal-overlay" @click.self="createdInfo = null">
      <div class="ik-modal" style="max-width:420px;">
        <div class="ik-modal-header">
          <h3>Assureur cr&eacute;&eacute;</h3>
          <button class="close-btn" @click="createdInfo = null">&times;</button>
        </div>
        <div class="ik-modal-body" style="text-align:center;">
          <i class="bx bx-check-circle" style="font-size:3rem;color:var(--primary);"></i>
          <p style="margin:1rem 0 0.5rem;">L'assureur a &eacute;t&eacute; cr&eacute;&eacute; avec un compte administrateur :</p>
          <div style="background:#f5f5f9;border-radius:8px;padding:1rem;text-align:left;font-size:0.85rem;">
            <div><strong>Login :</strong> <code x-text="createdInfo.admin_login"></code></div>
            <div><strong>Mot de passe :</strong> <code x-text="createdInfo.admin_password"></code></div>
          </div>
          <p style="font-size:0.75rem;color:#999;margin-top:0.75rem;">Transmettez ces identifiants &agrave; l'administrateur de l'assureur.</p>
        </div>
        <div class="ik-modal-footer">
          <button class="btn btn-primary" @click="createdInfo = null">Fermer</button>
        </div>
      </div>
    </div>
  </template>

</div>

<script>
function assureursPage() {
  return {
    rows: [],
    loading: true,
    search: '',
    showModal: false,
    editing: false,
    editId: null,
    saving: false,
    createdInfo: null,
    form: { name: '', contact_name: '', contact_email: '', contact_phone: '', address: '', is_active: '1' },

    async load() {
      this.loading = true;
      try {
        const res = await api('/api/assureurs');
        let data = res.data || [];
        if (this.search) {
          const s = this.search.toLowerCase();
          data = data.filter(r => (r.name || '').toLowerCase().includes(s) || (r.contact_name || '').toLowerCase().includes(s));
        }
        this.rows = data;
      } catch(e) {
        toast('Erreur chargement', 'error');
      }
      this.loading = false;
    },

    resetForm() {
      this.form = { name: '', contact_name: '', contact_email: '', contact_phone: '', address: '', is_active: '1' };
    },

    openCreate() {
      this.resetForm();
      this.editing = false;
      this.editId = null;
      this.showModal = true;
    },

    openEdit(row) {
      this.form = {
        name: row.name || '',
        contact_name: row.contact_name || '',
        contact_email: row.contact_email || '',
        contact_phone: row.contact_phone || '',
        address: row.address || '',
        is_active: String(row.is_active ?? 1),
      };
      this.editing = true;
      this.editId = row.id;
      this.showModal = true;
    },

    async save() {
      if (!this.form.name.trim()) { toast('Le nom est requis', 'error'); return; }
      this.saving = true;
      try {
        const url = this.editing ? '/api/assureurs/' + this.editId : '/api/assureurs';
        const res = await api(url, { method: 'POST', body: this.form });
        this.showModal = false;
        if (!this.editing && res.admin_login) {
          this.createdInfo = res;
        } else {
          toast(this.editing ? 'Assureur mis a jour' : 'Assureur cree', 'success');
        }
        this.load();
      } catch(e) {
        toast(e.error || 'Erreur', 'error');
      }
      this.saving = false;
    }
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
