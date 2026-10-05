<?php
$user = requireAuth();
$pageTitle = "Gestion des accès";
$activeTab = 'acces';
require basePath('src/views/layout.php');
?>

<div x-data="gestionAccesPage()" x-init="load()">

  <!-- Tabs -->
  <div style="display:flex;gap:0;margin-bottom:1.5rem;border-bottom:2px solid #e9ecef;">
    <button @click="activeTab = 'users'; load()"
            :style="activeTab === 'users'
              ? 'border-bottom:2px solid var(--primary);color:#1a3a3a;font-weight:600;'
              : 'border-bottom:2px solid transparent;color:#888;'"
            style="background:none;border:none;border-left:none;border-right:none;border-top:none;padding:0.75rem 1.25rem;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;margin-bottom:-2px;">
      <i class="bx bx-group"></i> Utilisateurs
    </button>
    <button @click="activeTab = 'profiles'; load()"
            :style="activeTab === 'profiles'
              ? 'border-bottom:2px solid var(--primary);color:#1a3a3a;font-weight:600;'
              : 'border-bottom:2px solid transparent;color:#888;'"
            style="background:none;border:none;border-left:none;border-right:none;border-top:none;padding:0.75rem 1.25rem;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;gap:0.4rem;margin-bottom:-2px;">
      <i class="bx bx-shield"></i> Profils d'acc&egrave;s
    </button>
  </div>

  <!-- ════════════════════════════════════════════ -->
  <!--               UTILISATEURS TAB              -->
  <!-- ════════════════════════════════════════════ -->
  <template x-if="activeTab === 'users'">
    <div>
      <!-- Toolbar -->
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <div style="font-size:0.85rem;color:#666;">
          <span x-text="users.length"></span> utilisateur(s) enregistr&eacute;(s)
        </div>
        <button class="btn btn-primary" @click="openCreateUser()" style="display:flex;align-items:center;gap:0.4rem;">
          <i class="bx bx-plus"></i> Nouvel utilisateur
        </button>
      </div>

      <!-- Empty state -->
      <template x-if="!loading && users.length === 0">
        <div class="ik-card">
          <div class="empty-state">
            <i class="bx bx-user"></i>
            <p>Aucun utilisateur trouv&eacute;</p>
          </div>
        </div>
      </template>

      <!-- User cards -->
      <div style="display:flex;flex-direction:column;gap:0.75rem;">
        <template x-for="u in users" :key="u.id">
          <div class="ik-card" style="padding:1rem 1.25rem;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
              <!-- Left: avatar + info -->
              <div style="display:flex;align-items:flex-start;gap:1rem;flex:1;min-width:0;">
                <!-- Avatar icon -->
                <div style="width:44px;height:44px;border-radius:50%;background:#e3f2fd;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="bx bx-group" style="font-size:1.2rem;color:#1565c0;"></i>
                </div>

                <div style="min-width:0;flex:1;">
                  <!-- Name + Active badge -->
                  <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.2rem;">
                    <span style="font-weight:600;color:#333;font-size:0.95rem;" x-text="u.full_name || u.login"></span>
                    <span class="badge-status"
                          :class="u.is_active == 1 ? 'badge-actif' : 'badge-inactif'"
                          x-text="u.is_active == 1 ? 'Actif' : 'Inactif'"></span>
                  </div>

                  <!-- Login + email -->
                  <div style="font-size:0.8rem;color:#888;margin-bottom:0.1rem;">
                    Login : <span x-text="u.login"></span>
                  </div>
                  <template x-if="u.email">
                    <div style="font-size:0.8rem;color:#888;margin-bottom:0.4rem;" x-text="u.email"></div>
                  </template>

                  <!-- Profile badge -->
                  <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                    <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.65rem;border-radius:20px;font-size:0.72rem;font-weight:600;border:1px solid;"
                          :style="profileBadgeStyle(u.profile_name || u.role)">
                      <span style="width:8px;height:8px;border-radius:50%;border:1.5px solid currentColor;display:inline-block;"></span>
                      <span x-text="u.profile_name || roleLabel(u.role)"></span>
                    </span>
                  </div>

                  <!-- Permission tags (first 5 + "+N autres") -->
                  <div style="display:flex;flex-wrap:wrap;gap:0.3rem;" x-show="u.profile_permissions && u.profile_permissions.length > 0">
                    <template x-for="(perm, idx) in (u.profile_permissions || []).slice(0, 5)" :key="perm">
                      <span style="display:inline-block;padding:0.15rem 0.5rem;background:#f0f0f0;border-radius:12px;font-size:0.68rem;color:#666;white-space:nowrap;"
                            x-text="permissionLabel(perm)"></span>
                    </template>
                    <template x-if="(u.profile_permissions || []).length > 5">
                      <span style="display:inline-block;padding:0.15rem 0.5rem;background:#f0f0f0;border-radius:12px;font-size:0.68rem;color:#888;white-space:nowrap;"
                            x-text="'+' + ((u.profile_permissions || []).length - 5) + ' autres'"></span>
                    </template>
                  </div>
                </div>
              </div>

              <!-- Right: actions -->
              <div style="display:flex;gap:0.25rem;flex-shrink:0;">
                <button class="action-btn edit" title="Modifier" @click="openEditUser(u)">
                  <i class="bx bx-pencil"></i>
                </button>
                <button class="action-btn delete" title="Supprimer" @click="removeUser(u)">
                  <i class="bx bx-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  <!-- ════════════════════════════════════════════ -->
  <!--             PROFILS D'ACCES TAB             -->
  <!-- ════════════════════════════════════════════ -->
  <template x-if="activeTab === 'profiles'">
    <div>
      <!-- Toolbar -->
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <div style="font-size:0.85rem;color:#666;">
          <span x-text="profiles.length"></span> profil(s) configur&eacute;(s)
        </div>
        <button class="btn btn-primary" @click="openCreateProfile()" style="display:flex;align-items:center;gap:0.4rem;">
          <i class="bx bx-plus"></i> Nouveau profil
        </button>
      </div>

      <!-- Empty state -->
      <template x-if="!loading && profiles.length === 0">
        <div class="ik-card">
          <div class="empty-state">
            <i class="bx bx-shield"></i>
            <p>Aucun profil d'acc&egrave;s trouv&eacute;</p>
          </div>
        </div>
      </template>

      <!-- Profile cards - 2-column grid -->
      <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:1rem;">
        <template x-for="p in profiles" :key="p.id">
          <div class="ik-card" style="padding:1.25rem;">
            <!-- Header: name + actions -->
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.3rem;">
              <div style="font-weight:700;font-size:1rem;color:#1a3a3a;" x-text="p.name"></div>
              <div style="display:flex;gap:0.25rem;flex-shrink:0;">
                <button class="action-btn edit" title="Modifier" @click="openEditProfile(p)">
                  <i class="bx bx-pencil"></i>
                </button>
                <button class="action-btn delete" title="Supprimer" @click="removeProfile(p)">
                  <i class="bx bx-trash"></i>
                </button>
              </div>
            </div>

            <!-- Description -->
            <div style="font-size:0.8rem;color:#888;margin-bottom:0.5rem;"
                 x-text="p.description || 'Aucune description'"></div>

            <!-- User count + module count -->
            <div style="font-size:0.75rem;color:#666;margin-bottom:0.75rem;">
              <span x-text="(p.user_count || 0) + ' utilisateur(s)'"></span> &mdash;
              <span x-text="(p.permissions || []).length + ' module(s)'"></span>
            </div>

            <!-- Module badges: all modules, green if enabled, gray if not -->
            <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
              <template x-for="page in availablePages" :key="page.key">
                <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;border-radius:12px;font-size:0.68rem;font-weight:500;white-space:nowrap;"
                      :style="(p.permissions || []).includes(page.key)
                        ? 'background:#e6f9f1;color:#15803d;'
                        : 'background:#f5f5f5;color:#ccc;'">
                  <span style="width:6px;height:6px;border-radius:50;display:inline-block;"
                        :style="(p.permissions || []).includes(page.key)
                          ? 'background:#15803d;border-radius:50%;'
                          : 'background:#ccc;border-radius:50%;'"></span>
                  <span x-text="page.label"></span>
                </span>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  <!-- ════════════════════════════════════════════ -->
  <!--          MODAL: UTILISATEUR                 -->
  <!-- ════════════════════════════════════════════ -->
  <div x-show="showUserModal" x-cloak class="ik-modal-overlay"
       @click.self="confirmCloseModal('showUserModal', $data)">

    <div class="ik-modal" @click.stop>
      <div class="ik-modal-header">
        <h3 x-text="editingUser ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'"></h3>
        <button class="close-btn" @click="showUserModal = false"><i class="bx bx-x"></i></button>
      </div>

      <form @submit.prevent="saveUser()">
        <div class="ik-modal-body">

          <!-- Nom complet -->
          <div class="mb-3">
            <label class="form-label">Nom complet *</label>
            <input type="text" class="form-control" x-model="userForm.full_name" required>
          </div>

          <!-- Identifiant + Mot de passe -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Identifiant (login) *</label>
              <input type="text" class="form-control" x-model="userForm.login" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" x-text="editingUser ? 'Mot de passe *' : 'Mot de passe *'"></label>
              <div style="position:relative;">
                <input :type="showPassword ? 'text' : 'password'" class="form-control" x-model="userForm.password"
                       :required="!editingUser" style="padding-right:2.5rem;">
                <button type="button" @click="showPassword = !showPassword"
                        style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;padding:0.25rem;">
                  <i class="bx" :class="showPassword ? 'bx-hide' : 'bx-show'"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" x-model="userForm.email">
          </div>

          <!-- T&eacute;l&eacute;phone -->
          <div class="mb-3">
            <label class="form-label">T&eacute;l&eacute;phone</label>
            <input type="text" class="form-control" x-model="userForm.phone">
          </div>

          <!-- R&ocirc;le -->
          <div class="mb-3">
            <label class="form-label">R&ocirc;le *</label>
            <select class="form-select" x-model="userForm.role">
              <option value="gestionnaire">Gestionnaire</option>
              <option value="administrateur">Administrateur</option>
              <option value="prestataire">Prestataire</option>
              <option value="entreprise">Entreprise</option>
              <option value="adherent">Adh&eacute;rent</option>
            </select>
          </div>

          <!-- Profil d'acc&egrave;s -->
          <div class="mb-3">
            <label class="form-label">Profil d'acc&egrave;s</label>
            <select class="form-select" x-model="userForm.profile_id">
              <option value="">-- Aucun profil (par d&eacute;faut) --</option>
              <template x-for="p in profiles" :key="p.id">
                <option :value="p.id" x-text="p.name"></option>
              </template>
            </select>
          </div>

          <!-- Notes -->
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" x-model="userForm.notes" rows="2"></textarea>
          </div>

          <!-- Toggle actif -->
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggleUserActif"
                     :checked="userForm.is_active == 1"
                     @change="userForm.is_active = $event.target.checked ? 1 : 0">
              <label class="form-check-label" for="toggleUserActif">Compte actif</label>
            </div>
          </div>

        </div>

        <div class="ik-modal-footer">
          <button type="button" class="btn btn-light" @click="showUserModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            <span x-show="!saving" x-text="editingUser ? 'Mettre à jour' : 'Créer'"></span>
            <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ════════════════════════════════════════════ -->
  <!--          MODAL: PROFIL D'ACCES              -->
  <!-- ════════════════════════════════════════════ -->
  <div x-show="showProfileModal" x-cloak class="ik-modal-overlay"
       @click.self="confirmCloseModal('showProfileModal', $data)">

    <div class="ik-modal" style="max-width:620px;" @click.stop>
      <div class="ik-modal-header">
        <h3 x-text="editingProfile ? 'Modifier le profil' : 'Nouveau profil d\'accès'"></h3>
        <button class="close-btn" @click="showProfileModal = false"><i class="bx bx-x"></i></button>
      </div>

      <form @submit.prevent="saveProfile()">
        <div class="ik-modal-body">

          <!-- Nom du profil -->
          <div class="mb-3">
            <label class="form-label">Nom du profil *</label>
            <input type="text" class="form-control" x-model="profileForm.name" required>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" x-model="profileForm.description" rows="2"
                      placeholder="Description du profil..."></textarea>
          </div>

          <!-- Modules accessibles -->
          <div class="mb-3">
            <label class="form-label">Modules accessibles</label>

            <!-- Select all / Deselect all buttons -->
            <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="selectAllPermissions()">
                Tout s&eacute;lectionner
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="deselectAllPermissions()">
                Tout d&eacute;s&eacute;lectionner
              </button>
            </div>

            <div style="display:flex;flex-direction:column;gap:0;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
              <template x-for="(page, idx) in availablePages" :key="page.key">
                <div :style="'display:flex;align-items:center;justify-content:space-between;padding:0.6rem 1rem;cursor:pointer;font-size:0.85rem;' + (idx > 0 ? 'border-top:1px solid #f0f0f0;' : '')"
                     @click="togglePermission(page.key)">
                  <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;margin:0;">
                    <input type="checkbox" :value="page.key"
                           :checked="profileForm.permissions.includes(page.key)"
                           @click.stop="togglePermission(page.key)"
                           style="accent-color:var(--primary);width:16px;height:16px;">
                    <span x-text="page.label" style="color:#333;"></span>
                  </label>
                  <span :style="'width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;' + (profileForm.permissions.includes(page.key) ? 'background:#e6f9f1;color:#15803d;' : 'background:#fce4ec;color:#c62828;')">
                    <i class="bx" :class="profileForm.permissions.includes(page.key) ? 'bx-check' : 'bx-x'"
                       style="font-size:0.85rem;"></i>
                  </span>
                </div>
              </template>
            </div>

            <!-- Selected count -->
            <div style="font-size:0.78rem;color:var(--primary);margin-top:0.5rem;font-weight:500;">
              <span x-text="profileForm.permissions.length"></span> module(s) s&eacute;lectionn&eacute;(s)
            </div>
          </div>

          <!-- Toggle profil actif -->
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggleProfileActif"
                     :checked="profileForm.is_active == 1"
                     @change="profileForm.is_active = $event.target.checked ? 1 : 0">
              <label class="form-check-label" for="toggleProfileActif">Profil actif</label>
            </div>
          </div>

        </div>

        <div class="ik-modal-footer">
          <button type="button" class="btn btn-light" @click="showProfileModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            <span x-show="!saving" x-text="editingProfile ? 'Mettre à jour' : 'Créer'"></span>
            <span x-show="saving"><i class="bx bx-loader-alt bx-spin"></i></span>
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
function gestionAccesPage() {
  return {
    loading: true,
    activeTab: 'users',
    users: [],
    profiles: [],
    saving: false,
    showPassword: false,

    // User modal
    showUserModal: false,
    editingUser: false,
    editUserId: null,
    userForm: {
      full_name: '',
      login: '',
      password: '',
      email: '',
      phone: '',
      role: 'gestionnaire',
      profile_id: '',
      notes: '',
      is_active: 1,
    },

    // Profile modal
    showProfileModal: false,
    editingProfile: false,
    editProfileId: null,
    profileForm: {
      name: '',
      description: '',
      permissions: [],
      is_active: 1,
    },

    // Available pages for permissions
    availablePages: [
      { key: 'dashboard',       icon: 'bx bx-grid-alt',       label: 'Tableau de bord' },
      { key: 'tiers-payant',    icon: 'bx bx-heart',          label: 'Tiers payant' },
      { key: 'entreprises',     icon: 'bx bx-buildings',      label: 'Entreprises' },
      { key: 'adherents',       icon: 'bx bx-group',          label: 'Adhérents' },
      { key: 'prestataires',    icon: 'bx bx-plus-medical',   label: 'Prestataires' },
      { key: 'utilisateurs-prestataires', icon: 'bx bx-user-check', label: 'Utilisateurs prestataires' },
      { key: 'pec',             icon: 'bx bx-file',           label: 'Prises en charge' },
      { key: 'factures-prest',  icon: 'bx bx-receipt',        label: 'Factures prestataires' },
      { key: 'factures-ent',    icon: 'bx bx-spreadsheet',    label: 'Factures entreprises' },
      { key: 'primes',          icon: 'bx bx-wallet',         label: 'Primes & Budget' },
      { key: 'statistiques',    icon: 'bx bx-bar-chart-alt-2', label: 'Statistiques' },
      { key: 'rapports',        icon: 'bx bx-printer',         label: 'Rapports' },
      { key: 'remboursements', icon: 'bx bx-money',           label: 'Remboursements' },
      { key: 'compagnies',      icon: 'bx bx-shield-quarter', label: "Compagnies d'assurance" },
      { key: 'acces',           icon: 'bx bx-lock-open-alt',  label: "Gestion des acc\u00e8s" },
      { key: 'assureurs',       icon: 'bx bx-globe',          label: 'Assureurs' },
    ],

    permissionLabels: null,

    init() {
      this.permissionLabels = {};
      this.availablePages.forEach(p => {
        this.permissionLabels[p.key] = p.label;
      });
    },

    async load() {
      this.loading = true;
      try {
        const profRes = await api('/api/acces/profiles');
        this.profiles = profRes.data || [];

        if (this.activeTab === 'users') {
          const userRes = await api('/api/acces/users');
          this.users = userRes.data || [];
        }
      } catch (e) {
        toast('Erreur chargement des données', 'error');
      }
      this.loading = false;
    },

    // ── Permission helpers ──
    permissionLabel(key) {
      if (!this.permissionLabels) this.init();
      return this.permissionLabels[key] || key;
    },

    togglePermission(key) {
      const idx = this.profileForm.permissions.indexOf(key);
      if (idx === -1) {
        this.profileForm.permissions.push(key);
      } else {
        this.profileForm.permissions.splice(idx, 1);
      }
    },

    selectAllPermissions() {
      this.profileForm.permissions = this.availablePages.map(p => p.key);
    },

    deselectAllPermissions() {
      this.profileForm.permissions = [];
    },

    // ── Role / Profile helpers ──
    roleLabel(role) {
      const map = {
        administrateur: 'Administrateur',
        gestionnaire: 'Gestionnaire',
        prestataire: 'Prestataire',
        entreprise: 'Entreprise',
        adherent: 'Adherent',
      };
      return map[role] || role;
    },

    profileBadgeStyle(name) {
      if (!name) return 'background:#f0f0f0;color:#666;border-color:#ddd;';
      const n = (name || '').toLowerCase();
      if (n.includes('admin'))       return 'background:#e6f9f1;color:#15803d;border-color:#c3e6cb;';
      if (n.includes('gestionnaire'))return 'background:#e3f2fd;color:#1565c0;border-color:#b8daff;';
      if (n.includes('prestataire')) return 'background:#e3f2fd;color:#1565c0;border-color:#b8daff;';
      if (n.includes('consultant'))  return 'background:#fff3e0;color:#e65100;border-color:#ffcc80;';
      return 'background:#e3f2fd;color:#1565c0;border-color:#b8daff;';
    },

    // ── User CRUD ──
    resetUserForm() {
      this.userForm = {
        full_name: '',
        login: '',
        password: '',
        email: '',
        phone: '',
        role: 'gestionnaire',
        profile_id: '',
        notes: '',
        is_active: 1,
      };
      this.showPassword = false;
    },

    openCreateUser() {
      this.editingUser = false;
      this.editUserId = null;
      this.resetUserForm();
      this.showUserModal = true;
    },

    openEditUser(u) {
      this.editingUser = true;
      this.editUserId = u.id;
      this.userForm = {
        full_name: u.full_name || '',
        login: u.login || '',
        password: '',
        email: u.email || '',
        phone: u.phone || '',
        role: u.role || 'gestionnaire',
        profile_id: u.profile_id || '',
        notes: u.notes || '',
        is_active: parseInt(u.is_active) ?? 1,
      };
      this.showPassword = false;
      this.showUserModal = true;
    },

    async saveUser() {
      if (!this.userForm.full_name.trim()) {
        toast('Le nom complet est obligatoire', 'error');
        return;
      }
      if (!this.userForm.login.trim()) {
        toast("L'identifiant est obligatoire", 'error');
        return;
      }
      if (!this.editingUser && !this.userForm.password) {
        toast('Le mot de passe est obligatoire', 'error');
        return;
      }

      this.saving = true;
      try {
        const body = { ...this.userForm };
        if (!this.editingUser && !body.password) {
          delete body.password;
        }
        if (this.editingUser && !body.password) {
          delete body.password;
        }

        if (this.editingUser) {
          await api('/api/acces/users/' + this.editUserId, {
            method: 'POST',
            body: body,
          });
          toast('Utilisateur mis à jour');
        } else {
          await api('/api/acces/users', {
            method: 'POST',
            body: body,
          });
          toast('Utilisateur créé');
        }
        this.showUserModal = false;
        this.load();
      } catch (e) {
        toast(e.error || "Erreur lors de l'enregistrement", 'error');
      }
      this.saving = false;
    },

    async removeUser(u) {
      if (!window.confirm('Supprimer l\'utilisateur « ' + (u.full_name || u.login) + ' » ?')) return;

      try {
        await api('/api/acces/users/' + u.id, { method: 'DELETE' });
        toast('Utilisateur supprimé');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },

    // ── Profile CRUD ──
    resetProfileForm() {
      this.profileForm = {
        name: '',
        description: '',
        permissions: [],
        is_active: 1,
      };
    },

    openCreateProfile() {
      this.editingProfile = false;
      this.editProfileId = null;
      this.resetProfileForm();
      this.showProfileModal = true;
    },

    openEditProfile(p) {
      this.editingProfile = true;
      this.editProfileId = p.id;
      this.profileForm = {
        name: p.name || '',
        description: p.description || '',
        permissions: Array.isArray(p.permissions) ? [...p.permissions] : [],
        is_active: parseInt(p.is_active) ?? 1,
      };
      this.showProfileModal = true;
    },

    async saveProfile() {
      if (!this.profileForm.name.trim()) {
        toast('Le nom du profil est obligatoire', 'error');
        return;
      }

      this.saving = true;
      try {
        if (this.editingProfile) {
          await api('/api/acces/profiles/' + this.editProfileId, {
            method: 'POST',
            body: this.profileForm,
          });
          toast('Profil mis à jour');
        } else {
          await api('/api/acces/profiles', {
            method: 'POST',
            body: this.profileForm,
          });
          toast('Profil créé');
        }
        this.showProfileModal = false;
        this.load();
      } catch (e) {
        toast(e.error || "Erreur lors de l'enregistrement", 'error');
      }
      this.saving = false;
    },

    async removeProfile(p) {
      if (!window.confirm('Supprimer le profil « ' + p.name + ' » ?')) return;

      try {
        await api('/api/acces/profiles/' + p.id, { method: 'DELETE' });
        toast('Profil supprimé');
        this.load();
      } catch (e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
