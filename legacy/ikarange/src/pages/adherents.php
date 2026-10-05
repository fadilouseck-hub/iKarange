<?php
$user = requireAuth();
$pageTitle = 'Adhérents';
$activeTab = 'adherents';
require basePath('src/views/layout.php');
?>

<div x-data="adherentsPage()" x-init="load()">

  <!-- Toolbar -->
  <div class="ik-card mb-4">
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;">
      <div class="ik-search" style="flex:1;min-width:220px;">
        <i class="bx bx-search"></i>
        <input type="text"
               x-model.debounce.300ms="search"
               @input="load()"
               placeholder="Rechercher par nom, pr&eacute;nom ou matricule...">
      </div>
      <div style="display:flex;gap:0.5rem;flex-shrink:0;">
        <button class="btn btn-outline-primary btn-sm" @click="importExcel()">
          <i class="bx bx-upload"></i> Importer Excel/CSV
        </button>
        <button class="btn btn-primary btn-sm" @click="openCreate()">
          <i class="bx bx-plus"></i> Nouvel adh&eacute;rent
        </button>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="ik-card">

    <!-- Loading skeleton -->
    <template x-if="loading">
      <div>
        <template x-for="i in 5" :key="i">
          <div style="display:flex;align-items:center;gap:1rem;padding:0.75rem 1rem;border-bottom:1px solid #f0f0f0;">
            <div class="skeleton" style="width:36px;height:36px;border-radius:50%;"></div>
            <div style="flex:1;">
              <div class="skeleton" style="width:60%;height:14px;margin-bottom:6px;"></div>
              <div class="skeleton" style="width:40%;height:10px;"></div>
            </div>
          </div>
        </template>
      </div>
    </template>

    <!-- Empty state -->
    <template x-if="!loading && items.length === 0">
      <div class="empty-state">
        <i class="bx bx-group"></i>
        <p>Aucun adh&eacute;rent trouv&eacute;</p>
      </div>
    </template>

    <!-- Data table -->
    <div x-show="!loading && items.length > 0" style="overflow-x:auto;">
      <table class="ik-table">
        <thead>
          <tr>
            <th>Adh&eacute;rent</th>
            <th>Matricule</th>
            <th>Entreprise</th>
            <th>Cat&eacute;gorie</th>
            <th>Statut</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="item in items" :key="item.id">
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                  <template x-if="item.photo">
                    <img :src="'/api/adherents/' + item.id + '/photo'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                  </template>
                  <template x-if="!item.photo">
                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.85rem;color:#fff;flex-shrink:0;"
                         :style="'background:' + avatarColor(item.nom)">
                      <span x-text="(item.nom || '').charAt(0).toUpperCase()"></span>
                    </div>
                  </template>
                  <div>
                    <div class="row-name" x-text="item.nom + ' ' + item.prenom"></div>
                    <div class="row-sub" x-text="item.telephone || ''"></div>
                  </div>
                </div>
              </td>
              <td x-text="item.matricule"></td>
              <td x-text="item.entreprise_nom || '—'"></td>
              <td>
                <span x-text="formatCategorie(item.categorie)"></span>
              </td>
              <td>
                <span class="badge-status"
                      :class="'badge-' + item.statut"
                      x-text="formatStatut(item.statut)"></span>
              </td>
              <td style="text-align:right;white-space:nowrap;">
                <button class="action-btn view" title="Voir la carte" @click="viewCard(item)">
                  <i class="bx bx-id-card"></i>
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

    <!-- Total count -->
    <div x-show="!loading && items.length > 0" style="padding:0.75rem 1rem;font-size:0.8rem;color:#888;border-top:1px solid #f0f0f0;">
      <span x-text="total + ' adhérent(s)'"></span>
    </div>
  </div>

  <!-- ═══════════════ Modal: Create / Edit ═══════════════ -->
  <div class="ik-modal-overlay" x-show="showModal" x-cloak
       @click.self="confirmCloseModal('showModal', $data)"
       style="overflow-y:auto;">
    <div class="ik-modal" style="max-width:620px;" @click.stop>
      <div class="ik-modal-header">
        <h3 x-text="editId ? 'Modifier adhérent' : 'Nouvel adhérent'"></h3>
        <button class="close-btn" @click="showModal = false"><i class="bx bx-x"></i></button>
      </div>
      <div class="ik-modal-body">

        <!-- Photo upload area -->
        <div style="display:flex;justify-content:center;margin-bottom:1.25rem;">
          <div @click="$refs.photoInput.click()"
               style="width:80px;height:80px;border-radius:50%;background:#f0f0f0;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;"
               title="Cliquez pour choisir une photo">
            <template x-if="photoPreview">
              <img :src="photoPreview" style="width:100%;height:100%;object-fit:cover;">
            </template>
            <template x-if="!photoPreview">
              <i class="bx bx-camera" style="font-size:1.5rem;color:#aaa;"></i>
            </template>
            <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.5);color:#fff;font-size:0.55rem;text-align:center;padding:2px 0;">
              <i class="bx bx-camera"></i>
            </div>
          </div>
          <input type="file" x-ref="photoInput" accept="image/jpeg,image/png,image/webp"
                 @change="handlePhotoSelect($event)" style="display:none;">
        </div>

        <!-- Nom + Prenom -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Nom <span style="color:#dc3545;">*</span></label>
            <input type="text" class="form-control" x-model="form.nom" placeholder="Nom de famille">
          </div>
          <div class="col-md-6">
            <label class="form-label">Pr&eacute;nom <span style="color:#dc3545;">*</span></label>
            <input type="text" class="form-control" x-model="form.prenom" placeholder="Pr&eacute;nom">
          </div>
        </div>

        <!-- Matricule + Sexe -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Matricule</label>
            <input type="text" class="form-control" x-model="form.matricule" disabled placeholder="Auto-g&eacute;n&eacute;r&eacute;">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sexe</label>
            <select class="form-select" x-model="form.sexe">
              <option value="masculin">Masculin</option>
              <option value="feminin">F&eacute;minin</option>
            </select>
          </div>
        </div>

        <!-- Date de naissance + Telephone -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Date de naissance</label>
            <input type="date" class="form-control" x-model="form.date_naissance">
          </div>
          <div class="col-md-6">
            <label class="form-label">T&eacute;l&eacute;phone</label>
            <input type="text" class="form-control" x-model="form.telephone" placeholder="77 000 00 00">
          </div>
        </div>

        <!-- Entreprise -->
        <div class="mb-3">
          <label class="form-label">Entreprise <span style="color:#dc3545;">*</span></label>
          <select class="form-select" x-model="form.entreprise_id">
            <option value="">— S&eacute;lectionner —</option>
            <template x-for="ent in entreprises" :key="ent.id">
              <option :value="ent.id" x-text="ent.raison_sociale"></option>
            </template>
          </select>
        </div>

        <!-- Categorie + Plafond -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Cat&eacute;gorie</label>
            <select class="form-select" x-model="form.categorie">
              <option value="titulaire">Titulaire</option>
              <option value="conjoint">Conjoint</option>
              <option value="enfant">Enfant</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Plafond annuel F CFA</label>
            <input type="number" class="form-control" x-model.number="form.plafond_annuel" placeholder="0">
          </div>
        </div>

        <!-- Date adhesion + Statut -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Date d'adh&eacute;sion</label>
            <input type="date" class="form-control" x-model="form.date_adhesion">
          </div>
          <div class="col-md-6">
            <label class="form-label">Statut</label>
            <select class="form-select" x-model="form.statut">
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
              <option value="suspendu">Suspendu</option>
            </select>
          </div>
        </div>

      </div>
      <div class="ik-modal-footer">
        <button class="btn btn-light btn-sm" @click="showModal = false">Annuler</button>
        <button class="btn btn-primary btn-sm" @click="save()" :disabled="saving">
          <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
          <span x-text="editId ? 'Mettre à jour' : 'Créer'"></span>
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ Modal: Carte d'adhérent ═══════════════ -->
  <div class="ik-modal-overlay" x-show="showCardModal" x-cloak
       @click.self="showCardModal = false">
    <div class="ik-modal" style="max-width:520px;" @click.stop>
      <div class="ik-modal-header">
        <h3>Carte d'adh&eacute;rent</h3>
        <button class="close-btn" @click="showCardModal = false"><i class="bx bx-x"></i></button>
      </div>
      <div class="ik-modal-body" style="text-align:center;">

        <!-- ── Styled Card ── -->
        <div id="adherent-card" style="border-radius:16px;overflow:hidden;background:linear-gradient(135deg,#1a3a3a 0%,#1e6b6b 40%,#2ab5b5 70%,#5ed8d8 100%);color:#fff;box-shadow:0 8px 32px rgba(0,0,0,0.18);max-width:440px;margin:0 auto;text-align:left;">

          <!-- Card Header -->
          <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 10px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                <img src="/images/logo-icon.png" alt="" style="width:36px;height:36px;object-fit:contain;">
              </div>
              <div>
                <div style="font-weight:700;font-size:0.95rem;letter-spacing:0.5px;">I'KARANG&Eacute;</div>
                <div style="font-size:0.6rem;opacity:0.8;">Gestion Assurance &amp; Mutuelle</div>
              </div>
            </div>
            <div style="background:rgba(255,255,255,0.2);border-radius:6px;padding:4px 10px;font-size:0.65rem;font-weight:600;letter-spacing:0.5px;">
              CARTE D'ADH&Eacute;RENT
            </div>
          </div>

          <!-- Card Body - Member Info -->
          <div style="display:flex;align-items:center;gap:16px;padding:14px 20px 16px;">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);overflow:hidden;">
              <template x-if="cardItem.photo">
                <img :src="'/api/adherents/' + cardItem.id + '/photo'" style="width:100%;height:100%;object-fit:cover;">
              </template>
              <template x-if="!cardItem.photo">
                <i class="bx bx-user" style="font-size:1.8rem;color:rgba(255,255,255,0.7);"></i>
              </template>
            </div>
            <div style="min-width:0;">
              <div style="font-size:1.2rem;font-weight:700;line-height:1.2;" x-text="(cardItem.nom || '').toUpperCase() + ' ' + (cardItem.prenom || '')"></div>
              <div style="font-size:0.85rem;font-weight:600;opacity:0.9;margin-top:2px;" x-text="cardItem.matricule || ''"></div>
              <div style="font-size:0.78rem;opacity:0.8;margin-top:1px;" x-text="cardItem.entreprise_nom || ''"></div>
              <div style="font-size:0.72rem;opacity:0.7;margin-top:2px;" x-text="cardItem.date_naissance ? 'N&eacute;(e) le: ' + fmtDate(cardItem.date_naissance) : ''"></div>
            </div>
          </div>

          <!-- Card Footer -->
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;background:rgba(0,0,0,0.15);">
            <span style="background:rgba(255,255,255,0.2);border-radius:4px;padding:3px 10px;font-size:0.7rem;font-weight:600;letter-spacing:0.3px;" x-text="formatCategorie(cardItem.categorie || '').toUpperCase()"></span>
            <span style="font-size:0.72rem;opacity:0.8;" x-text="cardItem.date_adhesion ? 'Adh&eacute;sion: ' + fmtDate(cardItem.date_adhesion) : ''"></span>
            <span style="border-radius:4px;padding:3px 10px;font-size:0.7rem;font-weight:600;letter-spacing:0.3px;"
                  :style="cardItem.statut === 'actif' ? 'background:rgba(45,212,168,0.4);' : cardItem.statut === 'suspendu' ? 'background:rgba(255,152,0,0.4);' : 'background:rgba(255,82,82,0.4);'"
                  x-text="formatStatut(cardItem.statut || '').toUpperCase()"></span>
          </div>
        </div>

        <p style="margin-top:1rem;font-size:0.8rem;color:#888;">
          Aper&ccedil;u de la carte. Cliquez sur Imprimer pour l'&eacute;diter au format carte.
        </p>
      </div>
      <div class="ik-modal-footer">
        <button class="btn btn-light btn-sm" @click="showCardModal = false">Fermer</button>
        <button class="btn btn-outline-dark btn-sm" @click="addToAppleWallet()" :disabled="walletLoading">
          <span x-show="walletLoading" class="spinner-border spinner-border-sm me-1"></span>
          <i x-show="!walletLoading" class="bx bx-wallet" style="vertical-align:middle;"></i>
          Apple Wallet
        </button>
        <button class="btn btn-primary btn-sm" @click="printCard()">
          <i class="bx bx-printer"></i> Imprimer la carte
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ Modal: Import Excel/CSV ═══════════════ -->
  <div class="ik-modal-overlay" x-show="showImportModal" x-cloak
       @click.self="confirmCloseModal('showImportModal', $data)"
       style="overflow-y:auto;">
    <div class="ik-modal" style="max-width:560px;" @click.stop>
      <div class="ik-modal-header">
        <h3>Importer des adh&eacute;rents</h3>
        <button class="close-btn" @click="showImportModal = false"><i class="bx bx-x"></i></button>
      </div>
      <div class="ik-modal-body">

        <!-- Step 1: Download template -->
        <div style="background:#f0f9f4;border-radius:8px;padding:1rem;margin-bottom:1rem;">
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <i class="bx bx-spreadsheet" style="font-size:1.8rem;color:var(--primary);"></i>
            <div>
              <div style="font-weight:600;font-size:0.9rem;color:#333;">&Eacute;tape 1 : T&eacute;l&eacute;charger le mod&egrave;le</div>
              <div style="font-size:0.8rem;color:#666;">Remplissez le fichier CSV avec vos donn&eacute;es</div>
            </div>
          </div>
          <a href="/api/adherents/template" download
             class="btn btn-outline-primary btn-sm mt-2" style="display:inline-flex;align-items:center;gap:0.25rem;">
            <i class="bx bx-download"></i> T&eacute;l&eacute;charger le mod&egrave;le CSV
          </a>
        </div>

        <!-- Step 2: Upload file -->
        <div style="background:#f5f5f9;border-radius:8px;padding:1rem;">
          <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
            <i class="bx bx-upload" style="font-size:1.8rem;color:var(--primary);"></i>
            <div>
              <div style="font-weight:600;font-size:0.9rem;color:#333;">&Eacute;tape 2 : Importer le fichier</div>
              <div style="font-size:0.8rem;color:#666;">Formats accept&eacute;s : CSV (.csv) ou Excel (.xlsx)</div>
            </div>
          </div>

          <div @click="$refs.importFileInput.click()"
               @dragover.prevent="importDragOver = true"
               @dragleave.prevent="importDragOver = false"
               @drop.prevent="handleImportDrop($event)"
               :style="importDragOver ? 'border-color:var(--primary);background:#e8f5e9;' : ''"
               style="border:2px dashed #ccc;border-radius:8px;padding:1.5rem;text-align:center;cursor:pointer;transition:all 0.2s;">
            <template x-if="!importFile">
              <div>
                <i class="bx bx-cloud-upload" style="font-size:2rem;color:#aaa;"></i>
                <div style="font-size:0.85rem;color:#666;margin-top:0.5rem;">
                  Cliquez ou glissez un fichier ici
                </div>
              </div>
            </template>
            <template x-if="importFile">
              <div style="display:flex;align-items:center;gap:0.75rem;justify-content:center;">
                <i class="bx bx-file" style="font-size:1.5rem;color:var(--primary);"></i>
                <div style="text-align:left;">
                  <div style="font-weight:600;font-size:0.85rem;" x-text="importFile.name"></div>
                  <div style="font-size:0.75rem;color:#888;" x-text="formatFileSize(importFile.size)"></div>
                </div>
                <button @click.stop="importFile = null" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.2rem;">
                  <i class="bx bx-x"></i>
                </button>
              </div>
            </template>
          </div>
          <input type="file" x-ref="importFileInput" accept=".csv,.xlsx,.txt"
                 @change="importFile = $event.target.files[0] || null" style="display:none;">
        </div>

        <!-- Import result -->
        <template x-if="importResult">
          <div style="margin-top:1rem;padding:1rem;border-radius:8px;"
               :style="importResult.created > 0 ? 'background:#e8f5e9;' : 'background:#fff3e0;'">
            <div style="font-weight:600;font-size:0.9rem;margin-bottom:0.5rem;">
              <i class="bx" :class="importResult.created > 0 ? 'bx-check-circle' : 'bx-error'" style="vertical-align:middle;"></i>
              R&eacute;sultat de l'import
            </div>
            <div style="font-size:0.85rem;color:#333;">
              <span style="color:#2e7d32;font-weight:600;" x-text="importResult.created"></span> adh&eacute;rent(s) cr&eacute;&eacute;(s)
              <template x-if="importResult.skipped > 0">
                <span> &mdash; <span style="color:#e65100;font-weight:600;" x-text="importResult.skipped"></span> ignor&eacute;(s)</span>
              </template>
            </div>
            <template x-if="importResult.errors && importResult.errors.length > 0">
              <div style="margin-top:0.5rem;max-height:120px;overflow-y:auto;">
                <template x-for="err in importResult.errors" :key="err">
                  <div style="font-size:0.75rem;color:#c62828;padding:2px 0;" x-text="err"></div>
                </template>
              </div>
            </template>
          </div>
        </template>

      </div>
      <div class="ik-modal-footer">
        <button class="btn btn-light btn-sm" @click="showImportModal = false">Fermer</button>
        <button class="btn btn-primary btn-sm" @click="doImport()" :disabled="!importFile || importing">
          <span x-show="importing" class="spinner-border spinner-border-sm me-1"></span>
          <i x-show="!importing" class="bx bx-upload" style="vertical-align:middle;"></i>
          Importer
        </button>
      </div>
    </div>
  </div>

  <!-- Print-only styles -->
  <style>
    @media print {
      body * { visibility: hidden !important; }
      #adherent-card,
      #adherent-card * {
        visibility: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      #adherent-card {
        position: fixed !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 400px !important;
        box-shadow: none !important;
      }
      @page {
        size: landscape;
        margin: 0;
      }
    }
  </style>

</div>

<script>
function adherentsPage() {
  return {
    loading: true,
    saving: false,
    search: '',
    items: [],
    total: 0,
    entreprises: [],

    // Modal state
    showModal: false,
    showCardModal: false,
    walletLoading: false,
    editId: null,
    cardItem: {},

    // Photo state
    photoFile: null,
    photoPreview: '',

    // Import state
    showImportModal: false,
    importFile: null,
    importing: false,
    importResult: null,
    importDragOver: false,

    form: {
      nom: '',
      prenom: '',
      matricule: '',
      sexe: 'masculin',
      date_naissance: '',
      telephone: '',
      entreprise_id: '',
      categorie: 'titulaire',
      plafond_annuel: 0,
      date_adhesion: '',
      statut: 'actif',
    },

    async load() {
      this.loading = true;
      try {
        let url = '/api/adherents';
        const params = [];
        if (this.search) params.push('search=' + encodeURIComponent(this.search));
        if (params.length) url += '?' + params.join('&');

        const res = await api(url);
        this.items = res.data || [];
        this.total = res.total || 0;
      } catch(e) {
        toast('Erreur chargement des adhérents', 'error');
      }
      this.loading = false;
    },

    async loadEntreprises() {
      try {
        const res = await api('/api/entreprises');
        this.entreprises = res.data || [];
      } catch(e) {
        // silently fail
      }
    },

    resetForm() {
      this.form = {
        nom: '',
        prenom: '',
        matricule: '',
        sexe: 'masculin',
        date_naissance: '',
        telephone: '',
        entreprise_id: '',
        categorie: 'titulaire',
        plafond_annuel: 0,
        date_adhesion: '',
        statut: 'actif',
      };
      this.photoFile = null;
      this.photoPreview = '';
    },

    handlePhotoSelect(event) {
      const file = event.target.files[0];
      if (!file) return;
      const allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        toast('Format accepté : JPEG, PNG ou WebP', 'error');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        toast('La photo ne doit pas dépasser 5 Mo', 'error');
        return;
      }
      this.photoFile = file;
      this.photoPreview = URL.createObjectURL(file);
    },

    async openCreate() {
      this.editId = null;
      this.resetForm();
      await this.loadEntreprises();
      this.showModal = true;
    },

    async openEdit(item) {
      this.editId = item.id;
      this.photoFile = null;
      this.photoPreview = item.photo ? '/api/adherents/' + item.id + '/photo' : '';
      await this.loadEntreprises();
      this.form = {
        nom: item.nom || '',
        prenom: item.prenom || '',
        matricule: item.matricule || '',
        sexe: item.sexe || 'masculin',
        date_naissance: item.date_naissance || '',
        telephone: item.telephone || '',
        entreprise_id: item.entreprise_id || '',
        categorie: item.categorie || 'titulaire',
        plafond_annuel: item.plafond_annuel || 0,
        date_adhesion: item.date_adhesion || '',
        statut: item.statut || 'actif',
      };
      this.showModal = true;
    },

    async save() {
      // Validate
      if (!this.form.nom.trim() || !this.form.prenom.trim()) {
        toast('Nom et prénom sont requis', 'error');
        return;
      }
      if (!this.form.entreprise_id) {
        toast('Veuillez sélectionner une entreprise', 'error');
        return;
      }

      this.saving = true;
      try {
        const url = this.editId
          ? '/api/adherents/' + this.editId
          : '/api/adherents';

        const fd = new FormData();
        fd.append('nom', this.form.nom);
        fd.append('prenom', this.form.prenom);
        fd.append('entreprise_id', this.form.entreprise_id);
        fd.append('sexe', this.form.sexe);
        fd.append('date_naissance', this.form.date_naissance || '');
        fd.append('telephone', this.form.telephone || '');
        fd.append('categorie', this.form.categorie);
        fd.append('plafond_annuel', this.form.plafond_annuel || 0);
        fd.append('date_adhesion', this.form.date_adhesion || '');
        fd.append('statut', this.form.statut);
        if (this.photoFile) {
          fd.append('photo', this.photoFile);
        }

        await api(url, { method: 'POST', body: fd });

        toast(this.editId ? 'Adhérent mis à jour' : 'Adhérent créé');
        this.showModal = false;
        this.load();
      } catch(e) {
        toast(e.error || "Erreur lors de l'enregistrement", 'error');
      }
      this.saving = false;
    },

    async remove(item) {
      if (!confirm('Supprimer ' + item.nom + ' ' + item.prenom + ' ?')) return;
      try {
        await api('/api/adherents/' + item.id, { method: 'DELETE' });
        toast('Adhérent supprimé');
        this.load();
      } catch(e) {
        toast(e.error || 'Erreur lors de la suppression', 'error');
      }
    },

    viewCard(item) {
      this.cardItem = { ...item };
      this.showCardModal = true;
    },

    printCard() {
      window.print();
    },

    async addToAppleWallet() {
      if (!this.cardItem || !this.cardItem.id) return;
      this.walletLoading = true;
      try {
        const res = await fetch('/api/adherents/' + this.cardItem.id + '/wallet-pass', {
          headers: { 'X-CSRF-Token': CSRF },
        });
        if (!res.ok) {
          const err = await res.json().catch(() => ({}));
          throw new Error(err.error || 'Erreur lors du téléchargement');
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'ikarange-' + (this.cardItem.matricule || 'carte') + '.pkpass';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        toast('Pass Apple Wallet téléchargé');
      } catch (e) {
        toast(e.message || 'Erreur Apple Wallet', 'error');
      }
      this.walletLoading = false;
    },

    importExcel() {
      this.importFile = null;
      this.importResult = null;
      this.importing = false;
      this.importDragOver = false;
      this.showImportModal = true;
    },

    handleImportDrop(event) {
      this.importDragOver = false;
      var files = event.dataTransfer.files;
      if (files.length > 0) {
        this.importFile = files[0];
      }
    },

    formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' o';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
      return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
    },

    async doImport() {
      if (!this.importFile) return;
      this.importing = true;
      this.importResult = null;
      try {
        var fd = new FormData();
        fd.append('file', this.importFile);
        var res = await api('/api/adherents/import', { method: 'POST', body: fd });
        this.importResult = res;
        if (res.created > 0) {
          toast(res.created + ' adherent(s) importe(s)', 'success');
          this.load();
        }
      } catch(e) {
        this.importResult = { created: 0, skipped: 0, errors: [e.error || "Erreur lors de l'import"] };
        toast(e.error || "Erreur lors de l'import", 'error');
      }
      this.importing = false;
    },

    formatStatut(s) {
      const map = { actif: 'Actif', inactif: 'Inactif', suspendu: 'Suspendu' };
      return map[s] || s;
    },

    formatCategorie(c) {
      const map = { titulaire: 'Titulaire', conjoint: 'Conjoint', enfant: 'Enfant' };
      return map[c] || c;
    },

    avatarColor(name) {
      const colors = ['#1565c0','#2e7d32','#e65100','#7b1fa2','#c62828','#00838f','#4527a0','#ad1457'];
      let hash = 0;
      for (let i = 0; i < (name || '').length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      return colors[Math.abs(hash) % colors.length];
    },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
