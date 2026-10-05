<?php
$user = requireAuth();
$pageTitle = 'Utilisateurs prestataires';
$activeTab = 'utilisateurs-prestataires';
require basePath('src/views/layout.php');
?>

<div x-data="utilisateursPrestPage()" x-init="load()">

  <!-- Title section -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
      <h4 style="font-weight:700;color:#1a3a3a;margin:0;">Utilisateurs Prestataires</h4>
      <p style="color:#888;font-size:0.85rem;margin:0;">Gestion des comptes d'acc&egrave;s pour les prestataires</p>
    </div>
    <button class="btn btn-primary btn-sm" @click="openCreate()">
      <i class="bx bx-plus"></i> Nouvel utilisateur
    </button>
  </div>

  <!-- Table -->
  <div class="ik-card">
    <template x-if="loading">
      <div class="text-center py-4"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#aaa;"></i></div>
    </template>

    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-user-check"></i>
        <p>Aucun utilisateur prestataire</p>
      </div>
    </template>

    <template x-if="!loading && items.length > 0">
      <div style="overflow-x:auto;">
        <table class="ik-table">
          <thead>
            <tr>
              <th>Login</th>
              <th>Nom complet</th>
              <th>Prestataire</th>
              <th>Statut</th>
              <th>Date cr&eacute;ation</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="item in items" :key="item.id">
              <tr>
                <td>
                  <span class="row-name" x-text="item.login"></span>
                </td>
                <td x-text="item.nom_complet || '—'"></td>
                <td x-text="item.prestataire_nom || '—'"></td>
                <td>
                  <span class="badge-status"
                        :class="item.is_active == 1 ? 'badge-actif' : 'badge-inactif'"
                        x-text="item.is_active == 1 ? 'Actif' : 'Inactif'"></span>
                </td>
                <td x-text="fmtDate(item.created_at)"></td>
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
      </div>
    </template>
  </div>

  <!-- Modal -->
  <template x-if="showModal">
    <div class="ik-modal-overlay" @click.self="confirmCloseModal('showModal', $data)">
      <div class="ik-modal">
        <div class="ik-modal-header">
          <h3 x-text="editId ? 'Modifier utilisateur prestataire' : 'Nouvel utilisateur prestataire'"></h3>
          <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
        </div>
        <form @submit.prevent="save()">
          <div class="ik-modal-body">

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

            <!-- Login + Mot de passe -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Login <span style="color:#dc3545;">*</span></label>
                <input type="text" class="form-control" x-model="form.login" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Mot de passe <span x-show="!editId" style="color:#dc3545;">*</span></label>
                <div style="position:relative;">
                  <input :type="showPwd ? 'text' : 'password'" class="form-control" x-model="form.password"
                         :required="!editId" :placeholder="editId ? 'Laisser vide pour ne pas changer' : ''">
                  <button type="button" @click="showPwd = !showPwd"
                          style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;">
                    <i class="bx" :class="showPwd ? 'bx-hide' : 'bx-show'"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Nom complet -->
            <div class="mb-3">
              <label class="form-label">Nom complet</label>
              <input type="text" class="form-control" x-model="form.nom_complet">
            </div>

            <!-- Compte actif -->
            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="switchActive" x-model="form.is_active">
                <label class="form-check-label" for="switchActive" style="font-size:0.85rem;">Compte actif</label>
              </div>
            </div>

          </div>
          <div class="ik-modal-footer">
            <button type="button" class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
              <span x-show="!saving" x-text="editId ? 'Mettre à jour' : 'Créer'"></span>
              <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>

<script>
function utilisateursPrestPage() {
  return {
    loading: true,
    items: [],
    prestataires: [],
    showModal: false,
    showPwd: false,
    saving: false,
    editId: null,
    form: {
      prestataire_id: '',
      login: '',
      password: '',
      nom_complet: '',
      is_active: true,
    },

    async load() {
      try {
        const [res, pRes] = await Promise.all([
          api('/api/utilisateurs-prestataires'),
          api('/api/prestataires'),
        ]);
        this.items = res.data || [];
        this.prestataires = pRes.data || [];
      } catch (e) {
        toast('Erreur chargement', 'error');
      }
      this.loading = false;
    },

    resetForm() {
      this.form = {
        prestataire_id: '',
        login: '',
        password: '',
        nom_complet: '',
        is_active: true,
      };
      this.showPwd = false;
      this.editId = null;
    },

    openCreate() {
      this.resetForm();
      this.showModal = true;
    },

    openEdit(item) {
      this.editId = item.id;
      this.form = {
        prestataire_id: item.prestataire_id || '',
        login: item.login || '',
        password: '',
        nom_complet: item.nom_complet || '',
        is_active: item.is_active == 1,
      };
      this.showPwd = false;
      this.showModal = true;
    },

    async save() {
      this.saving = true;
      try {
        const body = {
          prestataire_id: this.form.prestataire_id,
          login: this.form.login,
          nom_complet: this.form.nom_complet,
          is_active: this.form.is_active ? 1 : 0,
        };
        if (this.form.password) {
          body.password = this.form.password;
        }

        if (this.editId) {
          await api('/api/utilisateurs-prestataires/' + this.editId, { method: 'POST', body });
          toast('Utilisateur mis à jour');
        } else {
          body.password = this.form.password;
          await api('/api/utilisateurs-prestataires', { method: 'POST', body });
          toast('Utilisateur créé');
        }
        this.showModal = false;
        this.loading = true;
        await this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de l\'enregistrement', 'error');
      }
      this.saving = false;
    },

    async remove(item) {
      if (!confirm('Supprimer l\'utilisateur "' + (item.login || '') + '" ?')) return;
      try {
        await api('/api/utilisateurs-prestataires/' + item.id, { method: 'DELETE' });
        toast('Utilisateur supprimé');
        this.items = this.items.filter(i => i.id !== item.id);
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
