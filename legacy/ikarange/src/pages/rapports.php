<?php
$user = requireAuth();
$pageTitle = 'Rapports';
$activeTab = 'rapports';
require basePath('src/views/layout.php');
?>

<div x-data="rapportsPage()" x-init="init()">

  <!-- Report selector cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ik-card" style="cursor:pointer;text-align:center;padding:1.5rem 1rem;transition:all .15s;"
           :style="tab === 'beneficiaires' ? 'border-color:var(--primary);box-shadow:0 0 0 2px rgba(45,212,168,0.2);' : ''"
           @click="tab = 'beneficiaires'; loadReport()">
        <i class="bx bx-group" style="font-size:2rem;color:var(--primary);"></i>
        <div style="font-weight:600;margin-top:0.5rem;">B&eacute;n&eacute;ficiaires</div>
        <div style="font-size:0.72rem;color:#888;">Assur&eacute;s et ayants droits</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ik-card" style="cursor:pointer;text-align:center;padding:1.5rem 1rem;transition:all .15s;"
           :style="tab === 'prestations' ? 'border-color:var(--primary);box-shadow:0 0 0 2px rgba(45,212,168,0.2);' : ''"
           @click="tab = 'prestations'; loadReport()">
        <i class="bx bx-file" style="font-size:2rem;color:#1565c0;"></i>
        <div style="font-weight:600;margin-top:0.5rem;">Prestations</div>
        <div style="font-size:0.72rem;color:#888;">Historique des consommations</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ik-card" style="cursor:pointer;text-align:center;padding:1.5rem 1rem;transition:all .15s;"
           :style="tab === 'remboursements' ? 'border-color:var(--primary);box-shadow:0 0 0 2px rgba(45,212,168,0.2);' : ''"
           @click="tab = 'remboursements'; loadReport()">
        <i class="bx bx-money" style="font-size:2rem;color:#e65100;"></i>
        <div style="font-weight:600;margin-top:0.5rem;">Remboursements</div>
        <div style="font-size:0.72rem;color:#888;">Factures et paiements</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ik-card" style="cursor:pointer;text-align:center;padding:1.5rem 1rem;transition:all .15s;"
           :style="tab === 'prestataires' ? 'border-color:var(--primary);box-shadow:0 0 0 2px rgba(45,212,168,0.2);' : ''"
           @click="tab = 'prestataires'; loadReport()">
        <i class="bx bx-plus-medical" style="font-size:2rem;color:#7b1fa2;"></i>
        <div style="font-weight:600;margin-top:0.5rem;">R&eacute;seau de soins</div>
        <div style="font-size:0.72rem;color:#888;">Prestataires agr&eacute;&eacute;s</div>
      </div>
    </div>
  </div>

  <!-- Filters + Actions bar -->
  <div class="ik-card mb-4" x-show="tab" x-cloak>
    <div style="display:flex;align-items:flex-end;flex-wrap:wrap;gap:0.75rem;">

      <!-- Beneficiaires filters -->
      <template x-if="tab === 'beneficiaires'">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;flex:1;">
          <div>
            <label class="form-label" style="font-size:0.75rem;">Entreprise</label>
            <select class="form-select form-select-sm" x-model="filters.entreprise_id" @change="loadReport()" style="min-width:180px;">
              <option value="">Toutes</option>
              <template x-for="e in entreprises" :key="e.id">
                <option :value="e.id" x-text="e.raison_sociale"></option>
              </template>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Statut</label>
            <select class="form-select form-select-sm" x-model="filters.statut" @change="loadReport()">
              <option value="">Tous</option>
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
              <option value="suspendu">Suspendu</option>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Cat&eacute;gorie</label>
            <select class="form-select form-select-sm" x-model="filters.categorie" @change="loadReport()">
              <option value="">Toutes</option>
              <option value="titulaire">Titulaire</option>
              <option value="conjoint">Conjoint</option>
              <option value="enfant">Enfant</option>
            </select>
          </div>
        </div>
      </template>

      <!-- Prestations filters -->
      <template x-if="tab === 'prestations'">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;flex:1;">
          <div>
            <label class="form-label" style="font-size:0.75rem;">Entreprise</label>
            <select class="form-select form-select-sm" x-model="filters.entreprise_id" @change="loadReport()" style="min-width:180px;">
              <option value="">Toutes</option>
              <template x-for="e in entreprises" :key="e.id">
                <option :value="e.id" x-text="e.raison_sociale"></option>
              </template>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Type d'acte</label>
            <select class="form-select form-select-sm" x-model="filters.type_acte" @change="loadReport()">
              <option value="">Tous</option>
              <option value="consultation">Consultation</option>
              <option value="hospitalisation">Hospitalisation</option>
              <option value="pharmacie">Pharmacie</option>
              <option value="analyse">Analyse</option>
              <option value="imagerie">Imagerie</option>
              <option value="dentaire">Dentaire</option>
              <option value="chirurgie">Chirurgie</option>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Du</label>
            <input type="date" class="form-control form-control-sm" x-model="filters.date_debut" @change="loadReport()">
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Au</label>
            <input type="date" class="form-control form-control-sm" x-model="filters.date_fin" @change="loadReport()">
          </div>
        </div>
      </template>

      <!-- Remboursements filters -->
      <template x-if="tab === 'remboursements'">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;flex:1;">
          <div>
            <label class="form-label" style="font-size:0.75rem;">Prestataire</label>
            <select class="form-select form-select-sm" x-model="filters.prestataire_id" @change="loadReport()" style="min-width:180px;">
              <option value="">Tous</option>
              <template x-for="p in prestatairesLst" :key="p.id">
                <option :value="p.id" x-text="p.nom"></option>
              </template>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Statut</label>
            <select class="form-select form-select-sm" x-model="filters.statut" @change="loadReport()">
              <option value="">Tous</option>
              <option value="en_attente">En attente</option>
              <option value="validee">Valid&eacute;e</option>
              <option value="payee">Pay&eacute;e</option>
              <option value="partiel">Partiel</option>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Du</label>
            <input type="date" class="form-control form-control-sm" x-model="filters.date_debut" @change="loadReport()">
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Au</label>
            <input type="date" class="form-control form-control-sm" x-model="filters.date_fin" @change="loadReport()">
          </div>
        </div>
      </template>

      <!-- Prestataires filters -->
      <template x-if="tab === 'prestataires'">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;flex:1;">
          <div>
            <label class="form-label" style="font-size:0.75rem;">Type</label>
            <select class="form-select form-select-sm" x-model="filters.type" @change="loadReport()">
              <option value="">Tous</option>
              <option value="hopital">H&ocirc;pital</option>
              <option value="clinique">Clinique</option>
              <option value="pharmacie">Pharmacie</option>
              <option value="laboratoire">Laboratoire</option>
              <option value="centre_imagerie">Imagerie</option>
              <option value="dentiste">Dentiste</option>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:0.75rem;">Statut</label>
            <select class="form-select form-select-sm" x-model="filters.statut" @change="loadReport()">
              <option value="">Tous</option>
              <option value="agree">Agr&eacute;&eacute;</option>
              <option value="suspendu">Suspendu</option>
              <option value="resilie">R&eacute;sili&eacute;</option>
            </select>
          </div>
        </div>
      </template>

      <!-- Export buttons -->
      <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-primary btn-sm" @click="exportCSV()" :disabled="!reportData || loading"
                style="display:flex;align-items:center;gap:0.3rem;white-space:nowrap;">
          <i class="bx bx-spreadsheet"></i> Excel/CSV
        </button>
        <button class="btn btn-outline-primary btn-sm" @click="exportPDF()" :disabled="!reportData || loading"
                style="display:flex;align-items:center;gap:0.3rem;white-space:nowrap;">
          <i class="bx bx-file-blank"></i> PDF
        </button>
      </div>
    </div>
  </div>

  <!-- Summary cards -->
  <template x-if="summary && tab">
    <div class="row g-3 mb-4">
      <template x-if="tab === 'beneficiaires'">
        <div class="col-12">
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Total</div>
              <div class="stat-value" x-text="summary.total"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Actifs</div>
              <div class="stat-value" style="color:#2e7d32;" x-text="summary.actifs"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Titulaires</div>
              <div class="stat-value" x-text="summary.titulaires"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Ayants droits</div>
              <div class="stat-value" x-text="summary.ayants_droits"></div>
            </div>
          </div>
        </div>
      </template>
      <template x-if="tab === 'prestations'">
        <div class="col-12">
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Total PEC</div>
              <div class="stat-value" x-text="summary.total_pec"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Montant total</div>
              <div class="stat-value" x-text="fmtMoney(summary.montant_total)"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Part IPM</div>
              <div class="stat-value" style="color:var(--primary-dark);" x-text="fmtMoney(summary.total_ipm)"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Part Adh&eacute;rent</div>
              <div class="stat-value" x-text="fmtMoney(summary.total_adherent)"></div>
            </div>
          </div>
        </div>
      </template>
      <template x-if="tab === 'remboursements'">
        <div class="col-12">
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Factures</div>
              <div class="stat-value" x-text="summary.total_factures"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Montant total</div>
              <div class="stat-value" x-text="fmtMoney(summary.montant_total)"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Pay&eacute;</div>
              <div class="stat-value" style="color:#2e7d32;" x-text="fmtMoney(summary.montant_paye)"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Reste &agrave; payer</div>
              <div class="stat-value" style="color:#c62828;" x-text="fmtMoney(summary.reste_a_payer)"></div>
            </div>
          </div>
        </div>
      </template>
      <template x-if="tab === 'prestataires'">
        <div class="col-12">
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Total</div>
              <div class="stat-value" x-text="summary.total"></div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
              <div class="stat-label">Agr&eacute;&eacute;s</div>
              <div class="stat-value" style="color:#2e7d32;" x-text="summary.agrees"></div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </template>

  <!-- Data preview table -->
  <div class="ik-card" style="padding:0;overflow-x:auto;" x-show="tab" x-cloak>
    <template x-if="loading">
      <div style="padding:3rem;text-align:center;color:#888;">
        <i class="bx bx-loader-alt bx-spin" style="font-size:2rem;"></i>
        <p style="margin-top:0.5rem;">Chargement...</p>
      </div>
    </template>

    <template x-if="!loading && reportData && reportData.length === 0">
      <div class="empty-state">
        <i class="bx bx-search"></i>
        <p>Aucune donn&eacute;e pour ces filtres</p>
      </div>
    </template>

    <!-- Beneficiaires table -->
    <template x-if="!loading && tab === 'beneficiaires' && reportData && reportData.length > 0">
      <table class="ik-table">
        <thead><tr>
          <th>Matricule</th><th>Nom</th><th>Pr&eacute;nom</th><th>Sexe</th>
          <th>Cat&eacute;gorie</th><th>Entreprise</th><th>Plafond</th><th>Statut</th>
        </tr></thead>
        <tbody>
          <template x-for="r in reportData.slice(0, 100)" :key="r.matricule">
            <tr>
              <td><span class="row-sub" x-text="r.matricule"></span></td>
              <td class="row-name" x-text="r.nom"></td>
              <td x-text="r.prenom"></td>
              <td x-text="r.sexe"></td>
              <td><span class="badge-status badge-actif" x-text="r.categorie"></span></td>
              <td x-text="r.entreprise"></td>
              <td x-text="fmtMoney(r.plafond_annuel)"></td>
              <td><span class="badge-status" :class="'badge-' + r.statut" x-text="r.statut"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>

    <!-- Prestations table -->
    <template x-if="!loading && tab === 'prestations' && reportData && reportData.length > 0">
      <table class="ik-table">
        <thead><tr>
          <th>N&deg;</th><th>Date</th><th>Adh&eacute;rent</th><th>Prestataire</th>
          <th>Acte</th><th>Montant</th><th>Part IPM</th><th>Statut</th>
        </tr></thead>
        <tbody>
          <template x-for="r in reportData.slice(0, 100)" :key="r.numero">
            <tr>
              <td><span class="row-sub" x-text="r.numero"></span></td>
              <td x-text="fmtDate(r.date_soins)"></td>
              <td class="row-name" x-text="r.adherent_nom + ' ' + r.adherent_prenom"></td>
              <td x-text="r.prestataire_nom"></td>
              <td x-text="r.type_acte"></td>
              <td x-text="fmtMoney(r.montant_total)"></td>
              <td style="color:var(--primary-dark);font-weight:600;" x-text="fmtMoney(r.part_ipm)"></td>
              <td><span class="badge-status" :class="'badge-' + r.statut" x-text="r.statut"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>

    <!-- Remboursements table -->
    <template x-if="!loading && tab === 'remboursements' && reportData && reportData.length > 0">
      <table class="ik-table">
        <thead><tr>
          <th>N&deg;</th><th>Prestataire</th><th>Date</th>
          <th>Montant</th><th>Pay&eacute;</th><th>Reste</th><th>Statut</th>
        </tr></thead>
        <tbody>
          <template x-for="r in reportData.slice(0, 100)" :key="r.numero">
            <tr>
              <td><span class="row-sub" x-text="r.numero"></span></td>
              <td class="row-name" x-text="r.prestataire_nom"></td>
              <td x-text="fmtDate(r.date_facture)"></td>
              <td x-text="fmtMoney(r.montant_total)"></td>
              <td style="color:#2e7d32;" x-text="fmtMoney(r.montant_paye)"></td>
              <td style="color:#c62828;font-weight:600;" x-text="fmtMoney(r.reste_a_payer)"></td>
              <td><span class="badge-status" :class="'badge-' + r.statut" x-text="r.statut"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>

    <!-- Prestataires table -->
    <template x-if="!loading && tab === 'prestataires' && reportData && reportData.length > 0">
      <table class="ik-table">
        <thead><tr>
          <th>Nom</th><th>Type</th><th>Ville</th><th>T&eacute;l&eacute;phone</th>
          <th>Nb PEC</th><th>Total IPM</th><th>Statut</th>
        </tr></thead>
        <tbody>
          <template x-for="r in reportData.slice(0, 100)" :key="r.nom">
            <tr>
              <td class="row-name" x-text="r.nom"></td>
              <td><span class="badge-type" :class="'badge-' + r.type" x-text="r.type"></span></td>
              <td x-text="r.ville || '—'"></td>
              <td x-text="r.telephone || '—'"></td>
              <td style="font-weight:600;" x-text="r.nb_pec"></td>
              <td x-text="fmtMoney(r.total_ipm)"></td>
              <td><span class="badge-status" :class="'badge-' + r.statut" x-text="r.statut"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </template>

    <!-- Row count -->
    <template x-if="!loading && reportData && reportData.length > 0">
      <div style="padding:0.75rem 1rem;border-top:1px solid #f0f0f0;font-size:0.75rem;color:#888;">
        <span x-text="reportData.length"></span> ligne(s)
        <template x-if="reportData.length > 100">
          <span> &mdash; 100 premi&egrave;res affich&eacute;es, exportez pour voir tout</span>
        </template>
      </div>
    </template>
  </div>
</div>

<script>
function rapportsPage() {
  return {
    tab: '',
    loading: false,
    reportData: null,
    summary: null,
    filters: {},
    entreprises: [],
    prestatairesLst: [],

    init() {
      this.loadEntreprises();
      this.loadPrestataires();
      // Auto-open tab from URL: /rapports?tab=remboursements
      const urlTab = new URLSearchParams(window.location.search).get('tab');
      if (urlTab && ['beneficiaires','prestations','remboursements','prestataires'].includes(urlTab)) {
        this.tab = urlTab;
        this.$nextTick(() => this.loadReport());
      }
    },

    async loadEntreprises() {
      try {
        const res = await api('/api/entreprises');
        this.entreprises = res.data || [];
      } catch(e) {}
    },

    async loadPrestataires() {
      try {
        const res = await api('/api/prestataires');
        this.prestatairesLst = res.data || [];
      } catch(e) {}
    },

    async loadReport() {
      if (!this.tab) return;
      this.loading = true;
      this.reportData = null;
      this.summary = null;

      // Update URL without reload
      const url = new URL(window.location);
      url.searchParams.set('tab', this.tab);
      history.replaceState(null, '', url);

      const params = new URLSearchParams();
      for (const [k, v] of Object.entries(this.filters)) {
        if (v) params.set(k, v);
      }

      try {
        const res = await api('/api/rapports/' + this.tab + '?' + params.toString());
        this.reportData = res.data || [];
        this.summary = res.summary || null;
      } catch(e) {
        toast('Erreur chargement du rapport', 'error');
      }
      this.loading = false;
    },

    exportCSV() {
      const params = new URLSearchParams();
      for (const [k, v] of Object.entries(this.filters)) {
        if (v) params.set(k, v);
      }
      params.set('format', 'csv');
      window.location.href = '/api/rapports/' + this.tab + '?' + params.toString();
    },

    exportPDF() {
      if (!this.reportData || !this.reportData.length) return;

      const titles = {
        beneficiaires: 'Etat des Beneficiaires',
        prestations: 'Historique des Prestations',
        remboursements: 'Etat des Remboursements',
        prestataires: 'Reseau de Soins - Prestataires Agrees'
      };

      // Build table HTML based on report type
      let headers = [];
      let rowsFn;

      if (this.tab === 'beneficiaires') {
        headers = ['Matricule', 'Nom', 'Prenom', 'Sexe', 'Categorie', 'Entreprise', 'Plafond', 'Statut'];
        rowsFn = r => [r.matricule, r.nom, r.prenom, r.sexe, r.categorie, r.entreprise, fmtMoney(r.plafond_annuel), r.statut];
      } else if (this.tab === 'prestations') {
        headers = ['N\u00b0', 'Date', 'Adherent', 'Prestataire', 'Acte', 'Montant', 'Part IPM', 'Part Adh.', 'Statut'];
        rowsFn = r => [r.numero, fmtDate(r.date_soins), r.adherent_nom + ' ' + r.adherent_prenom, r.prestataire_nom, r.type_acte, fmtMoney(r.montant_total), fmtMoney(r.part_ipm), fmtMoney(r.part_adherent), r.statut];
      } else if (this.tab === 'remboursements') {
        headers = ['N\u00b0', 'Prestataire', 'Type', 'Date', 'Montant', 'Paye', 'Reste', 'Statut'];
        rowsFn = r => [r.numero, r.prestataire_nom, r.prestataire_type, fmtDate(r.date_facture), fmtMoney(r.montant_total), fmtMoney(r.montant_paye), fmtMoney(r.reste_a_payer), r.statut];
      } else if (this.tab === 'prestataires') {
        headers = ['Nom', 'Type', 'Ville', 'Telephone', 'Specialites', 'Nb PEC', 'Total IPM', 'Statut'];
        rowsFn = r => [r.nom, r.type, r.ville || '', r.telephone || '', r.specialites || '', r.nb_pec, fmtMoney(r.total_ipm), r.statut];
      }

      // Summary line
      let summaryHtml = '';
      if (this.summary) {
        const items = [];
        for (const [k, v] of Object.entries(this.summary)) {
          if (typeof v === 'object') continue;
          const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
          items.push('<span><strong>' + label + ':</strong> ' + (typeof v === 'number' && v > 999 ? fmtMoney(v) : v) + '</span>');
        }
        summaryHtml = '<div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-bottom:1rem;font-size:11px;">' + items.join('') + '</div>';
      }

      // Build table rows
      const trs = this.reportData.map(r => {
        const cells = rowsFn(r).map(c => '<td>' + (c ?? '') + '</td>').join('');
        return '<tr>' + cells + '</tr>';
      }).join('');

      const ths = headers.map(h => '<th>' + h + '</th>').join('');

      const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + titles[this.tab] + '</title>' +
        '<style>' +
        'body{font-family:Arial,sans-serif;margin:20px;color:#333;}' +
        '.header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1a3a3a;padding-bottom:10px;margin-bottom:15px;}' +
        '.header h1{font-size:16px;color:#1a3a3a;margin:0;}' +
        '.header .date{font-size:11px;color:#888;}' +
        '.header .logo{font-weight:700;color:#2dd4a8;font-size:14px;}' +
        'table{width:100%;border-collapse:collapse;font-size:10px;margin-top:10px;}' +
        'th{background:#1a3a3a;color:#fff;padding:6px 8px;text-align:left;font-size:9px;text-transform:uppercase;}' +
        'td{padding:5px 8px;border-bottom:1px solid #eee;}' +
        'tr:nth-child(even){background:#f9f9f9;}' +
        '.footer{margin-top:20px;text-align:center;font-size:9px;color:#aaa;border-top:1px solid #ddd;padding-top:10px;}' +
        '@media print{body{margin:10px;}@page{size:landscape;margin:10mm;}}' +
        '</style></head><body>' +
        '<div class="header"><div><span class="logo">I\'KARANGE</span><h1>' + titles[this.tab] + '</h1></div><div class="date">Genere le ' + new Date().toLocaleDateString('fr-FR') + '</div></div>' +
        summaryHtml +
        '<table><thead><tr>' + ths + '</tr></thead><tbody>' + trs + '</tbody></table>' +
        '<div class="footer">I\'KARANGE - Gestion Assurance & Mutuelle | Powered by MCE Group</div>' +
        '<script>window.onload=function(){window.print();}<\/script>' +
        '</body></html>';

      const win = window.open('', '_blank');
      win.document.write(html);
      win.document.close();
    }
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
