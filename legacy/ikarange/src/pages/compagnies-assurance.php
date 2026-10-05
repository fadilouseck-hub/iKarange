<?php
$user = requireAuth();
$pageTitle = "Compagnies d'assurance";
$activeTab = 'compagnies';
require basePath('src/views/layout.php');
?>

<div x-data="compagniesPage()" x-init="load()">

  <!-- Toolbar: search + add button -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
    <div class="ik-search">
      <i class="bx bx-search"></i>
      <input type="text" placeholder="Rechercher une compagnie..."
             x-model="search"
             @input.debounce.350ms="load()">
    </div>
    <button class="btn btn-primary" @click="openCreate()" style="display:flex;align-items:center;gap:0.4rem;">
      <i class="bx bx-plus"></i> Nouvelle compagnie
    </button>
  </div>

  <!-- Table card -->
  <div class="ik-card" style="padding:0;overflow-x:auto;">

    <!-- Empty state -->
    <template x-if="!loading && rows.length === 0">
      <div class="empty-state">
        <i class="bx bx-shield-quarter"></i>
        <p>Aucune compagnie d'assurance trouv&eacute;e</p>
      </div>
    </template>

    <!-- Data table -->
    <template x-if="rows.length > 0">
      <table class="ik-table">
        <thead>
          <tr>
            <th>Compagnie</th>
            <th>Code</th>
            <th>Contact</th>
            <th>T&eacute;l&eacute;phone</th>
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
                    <i class="bx bx-shield-quarter" style="font-size:1.1rem;color:#1565c0;"></i>
                  </div>
                  <div>
                    <div class="row-name" x-text="row.nom"></div>
                    <div class="row-sub" x-text="row.email || ''"></div>
                  </div>
                </div>
              </td>
              <td x-text="row.code || '—'"></td>
              <td x-text="row.personne_contact || '—'"></td>
              <td x-text="row.telephone || '—'"></td>
              <td>
                <span class="badge-status"
                      :class="row.actif == 1 ? 'badge-actif' : 'badge-inactif'"
                      x-text="row.actif == 1 ? 'Active' : 'Inactive'"></span>
              </td>
              <td>
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
        <h3 x-text="editing ? 'Modifier compagnie d\'assurance' : 'Nouvelle compagnie d\'assurance'"></h3>
        <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
      </div>

      <form @submit.prevent="save()">
        <div class="ik-modal-body">

          <!-- Row 1: Nom -->
          <div class="mb-3">
            <label class="form-label">Nom *</label>
            <input type="text" class="form-control" x-model="form.nom" required>
          </div>

          <!-- Row 2: Code + Telephone -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Code</label>
              <input type="text" class="form-control" x-model="form.code">
            </div>
            <div class="col-md-6">
              <label class="form-label">T&eacute;l&eacute;phone</label>
              <input type="text" class="form-control" x-model="form.telephone">
            </div>
          </div>

          <!-- Row 3: Email -->
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" x-model="form.email">
          </div>

          <!-- Row 4: Adresse -->
          <div class="mb-3">
            <label class="form-label">Adresse</label>
            <input type="text" class="form-control" x-model="form.adresse">
          </div>

          <!-- Row 5: Personne de contact -->
          <div class="mb-3">
            <label class="form-label">Personne de contact</label>
            <input type="text" class="form-control" x-model="form.personne_contact">
          </div>

          <!-- Row 6: Toggle actif -->
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggleActif"
                     :checked="form.actif == 1"
                     @change="form.actif = $event.target.checked ? 1 : 0">
              <label class="form-check-label" for="toggleActif">Compagnie active</label>
            </div>
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

</div>

<script>
function compagniesPage() {
  return {
    loading: true,
    rows: [],
    search: '',
    showModal: false,
    editing: false,
    editId: null,
    saving: false,

    form: {
      nom: '',
      code: '',
      telephone: '',
      email: '',
      adresse: '',
      personne_contact: '',
      actif: 1,
    },

    resetForm() {
      this.form = {
        nom: '',
        code: '',
        telephone: '',
        email: '',
        adresse: '',
        personne_contact: '',
        actif: 1,
      };
    },

    async load() {
      try {
        const params = this.search ? '?search=' + encodeURIComponent(this.search) : '';
        const res = await api('/api/compagnies' + params);
        this.rows = res.data || [];
      } catch (e) {
        toast('Erreur chargement des compagnies', 'error');
      }
      this.loading = false;
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
        nom: row.nom || '',
        code: row.code || '',
        telephone: row.telephone || '',
        email: row.email || '',
        adresse: row.adresse || '',
        personne_contact: row.personne_contact || '',
        actif: parseInt(row.actif) ?? 1,
      };
      this.showModal = true;
    },

    async save() {
      if (!this.form.nom.trim()) {
        toast('Le nom de la compagnie est obligatoire', 'error');
        return;
      }

      this.saving = true;
      try {
        if (this.editing) {
          await api('/api/compagnies/' + this.editId, {
            method: 'POST',
            body: this.form,
          });
          toast('Compagnie mise à jour');
        } else {
          await api('/api/compagnies', {
            method: 'POST',
            body: this.form,
          });
          toast('Compagnie créée');
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de l’enregistrement', 'error');
      }
      this.saving = false;
    },

    async remove(row) {
      if (!window.confirm('Supprimer la compagnie « ' + row.nom + ' » ?')) return;

      try {
        await api('/api/compagnies/' + row.id, { method: 'DELETE' });
        toast('Compagnie supprimée');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
