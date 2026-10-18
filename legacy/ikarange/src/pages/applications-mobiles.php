<?php
$user = requireAuth();
if (!hasPermission('mobile-apps')) {
    redirect('/');
}
$pageTitle = 'Applications mobiles';
$activeTab = 'mobile-apps';
require basePath('src/views/layout.php');
?>

<div x-data="mobileAppsPage()" x-init="loadAll()">

  <!-- Summary -->
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="stat-card" style="cursor:pointer;" @click="tab = 'feedback'">
        <div class="stat-label">Avis &agrave; lire</div>
        <div class="stat-value" x-text="feedback.filter(f => f.status === 'new').length"></div>
        <div class="stat-sub"><span x-text="feedback.length"></span> avis au total</div>
        <div class="stat-icon" style="background:#e3f2fd;color:#1565c0;"><i class="bx bx-message-dots"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card" style="cursor:pointer;" @click="tab = 'souscriptions'">
        <div class="stat-label">Contrats souscrits en ligne</div>
        <div class="stat-value" x-text="policies.filter(p => p.status === 'active').length"></div>
        <div class="stat-sub"><span x-text="policies.length"></span> contrat(s) cr&eacute;&eacute;(s) &middot; mode test</div>
        <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;"><i class="bx bx-cart"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card" style="cursor:pointer;" @click="tab = 'suppressions'">
        <div class="stat-label">Suppressions de compte en attente</div>
        <div class="stat-value" x-text="deletions.filter(d => d.status === 'requested').length"
             :style="deletions.some(d => d.status === 'requested') ? 'color:#c62828' : ''"></div>
        <div class="stat-sub"><span x-text="deletions.length"></span> demande(s) au total</div>
        <div class="stat-icon" style="background:#fce4ec;color:#c62828;"><i class="bx bx-user-x"></i></div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <template x-for="t in tabs" :key="t.key">
      <button class="btn btn-sm" :class="tab === t.key ? 'btn-primary' : 'btn-light'" @click="tab = t.key">
        <i :class="t.icon"></i> <span x-text="t.label"></span>
      </button>
    </template>
    <div style="margin-left:auto;font-size:0.8rem;color:#888;align-self:center;">
      Donn&eacute;es envoy&eacute;es par les adh&eacute;rents depuis les applications iOS et Android
    </div>
  </div>

  <!-- Feedback -->
  <div x-show="tab === 'feedback'">
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.75rem;">
      <select class="form-select form-select-sm" style="width:auto;" x-model="feedbackStatus">
        <option value="">Tous les statuts</option>
        <option value="new">Nouveau</option>
        <option value="read">Lu</option>
        <option value="done">Trait&eacute;</option>
      </select>
      <select class="form-select form-select-sm" style="width:auto;" x-model="feedbackCategory">
        <option value="">Toutes les cat&eacute;gories</option>
        <template x-for="(label, key) in categoryLabels" :key="key"><option :value="key" x-text="label"></option></template>
      </select>
    </div>
    <div class="ik-card" style="padding:0;overflow-x:auto;">
      <template x-if="!loading && feedbackRows().length === 0">
        <div class="empty-state"><i class="bx bx-message-dots"></i><p>Aucun avis</p></div>
      </template>
      <template x-if="feedbackRows().length > 0">
        <table class="ik-table">
          <thead><tr><th>Date</th><th>Adh&eacute;rent</th><th>Cat&eacute;gorie</th><th>Message</th><th>Appareil</th><th>Statut</th></tr></thead>
          <tbody>
            <template x-for="f in feedbackRows()" :key="f.id">
              <tr>
                <td style="white-space:nowrap;" x-text="fmtDateTime(f.created_at)"></td>
                <td>
                  <div class="row-name" x-text="f.prenom + ' ' + f.nom"></div>
                  <div class="row-sub" x-text="f.matricule"></div>
                </td>
                <td><span class="badge-type" x-text="categoryLabels[f.category] || f.category"></span></td>
                <td style="max-width:420px;white-space:pre-wrap;" x-text="f.message"></td>
                <td class="row-sub" style="white-space:nowrap;">
                  <span x-text="platformLabel(f.platform)"></span> <span x-text="f.app_version || ''"></span><br>
                  <span x-text="[f.device_model, f.os_version].filter(Boolean).join(' · ')"></span>
                </td>
                <td>
                  <select class="form-select form-select-sm" style="width:auto;" :value="f.status"
                          @change="setFeedbackStatus(f, $event.target.value)">
                    <option value="new">Nouveau</option>
                    <option value="read">Lu</option>
                    <option value="done">Trait&eacute;</option>
                  </select>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </template>
    </div>
  </div>

  <!-- Online subscriptions -->
  <div x-show="tab === 'souscriptions'">
    <div class="ik-card mb-3" style="background:#fff8e1;border-color:#ffe082;font-size:0.85rem;">
      <i class="bx bx-info-circle"></i>
      <strong>Mode test.</strong> Les formules, tarifs et paiements de la souscription en ligne sont fictifs : aucun
      prestataire de paiement n'est appel&eacute; et aucun argent n'est encaiss&eacute;.
      <span x-text="onlineMembers"></span> adh&eacute;rent(s) ont acc&egrave;s &agrave; ce parcours.
    </div>
    <div class="ik-card" style="padding:0;overflow-x:auto;">
      <template x-if="!loading && policies.length === 0">
        <div class="empty-state"><i class="bx bx-cart"></i><p>Aucun contrat souscrit en ligne</p></div>
      </template>
      <template x-if="policies.length > 0">
        <table class="ik-table">
          <thead><tr><th>Contrat</th><th>Adh&eacute;rent</th><th>Formule</th><th>Prime</th><th>P&eacute;riode</th><th>Paiement</th><th>Statut</th></tr></thead>
          <tbody>
            <template x-for="p in policies" :key="p.id">
              <tr>
                <td>
                  <div class="row-name" style="white-space:nowrap;" x-text="p.number"></div>
                  <div class="row-sub" x-text="'Créé le ' + fmtDate(p.created_at)"></div>
                </td>
                <td>
                  <div class="row-name" x-text="p.prenom + ' ' + p.nom"></div>
                  <div class="row-sub" x-text="[p.matricule, p.telephone].filter(Boolean).join(' · ')"></div>
                </td>
                <td>
                  <div x-text="p.product_name"></div>
                  <div class="row-sub" x-text="p.coverage_rate + ' % · ' + (p.members || 1) + ' personne(s)'"></div>
                </td>
                <td style="font-weight:600;" x-text="fmtMoney(p.total_premium)"></td>
                <td class="row-sub" x-text="p.start_date ? fmtDate(p.start_date) + ' → ' + fmtDate(p.end_date) : '—'"></td>
                <td>
                  <template x-if="p.payments.length === 0"><span class="row-sub">Aucun</span></template>
                  <template x-for="pay in p.payments" :key="pay.reference">
                    <div style="font-size:0.8rem;white-space:nowrap;">
                      <span class="badge-status" :class="paymentBadge(pay.status)" x-text="paymentLabel(pay.status)"></span>
                      <span x-text="methodLabel(pay.method)"></span>
                      <span class="row-sub" x-text="pay.reference"></span>
                    </div>
                  </template>
                </td>
                <td><span class="badge-status" :class="policyBadge(p.status)" x-text="policyLabel(p.status)"></span></td>
              </tr>
            </template>
          </tbody>
        </table>
      </template>
    </div>
  </div>

  <!-- Account deletion requests -->
  <div x-show="tab === 'suppressions'">
    <div class="ik-card" style="padding:0;overflow-x:auto;">
      <template x-if="!loading && deletions.length === 0">
        <div class="empty-state"><i class="bx bx-user-x"></i><p>Aucune demande de suppression</p></div>
      </template>
      <template x-if="deletions.length > 0">
        <table class="ik-table">
          <thead><tr><th>Date</th><th>Adh&eacute;rent</th><th>Motif</th><th>Acc&egrave;s</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
            <template x-for="d in deletions" :key="d.id">
              <tr>
                <td style="white-space:nowrap;" x-text="fmtDateTime(d.created_at)"></td>
                <td>
                  <div class="row-name" x-text="d.prenom + ' ' + d.nom"></div>
                  <div class="row-sub" x-text="[d.matricule, d.telephone].filter(Boolean).join(' · ')"></div>
                </td>
                <td style="max-width:360px;white-space:pre-wrap;" x-text="d.reason || '—'"></td>
                <td class="row-sub" x-text="Number(d.has_access) ? 'Actif' : 'Supprimé'"></td>
                <td><span class="badge-status" :class="deletionBadge(d.status)" x-text="deletionLabel(d.status)"></span></td>
                <td>
                  <button class="btn btn-sm btn-outline-danger" x-show="d.status === 'requested'" @click="openDeletion(d)">
                    Traiter
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </template>
    </div>
  </div>

  <!-- Deletion modal -->
  <template x-if="deletion">
    <div class="ik-modal-overlay" @click.self="deletion = null">
      <div class="ik-modal" style="max-width:600px;">
        <div class="ik-modal-header">
          <h3>Suppression du compte de <span x-text="deletion.prenom + ' ' + deletion.nom" style="color:var(--primary);"></span></h3>
          <button class="close-btn" @click="deletion = null">&times;</button>
        </div>
        <div class="ik-modal-body" style="font-size:0.85rem;">
          <p>V&eacute;rifiez l'identit&eacute; de l'adh&eacute;rent avant de traiter la demande (par exemple en le rappelant au
            <strong x-text="deletion.telephone || 'numéro du dossier'"></strong>).</p>
          <template x-if="!preview"><div class="spinner-border spinner-border-sm"></div></template>
          <template x-if="preview">
            <div>
              <h6 style="font-weight:600;">Sera supprim&eacute;</h6>
              <ul style="margin-bottom:0.75rem;">
                <template x-for="(count, table) in preview.tables" :key="table">
                  <li x-show="count > 0"><span x-text="tableLabels[table] || table"></span> : <strong x-text="count"></strong></li>
                </template>
                <li x-show="preview.files > 0">Fichiers (dossier m&eacute;dical, envois) : <strong x-text="preview.files"></strong></li>
                <li>Acc&egrave;s &agrave; l'application et au portail : <strong>mot de passe effac&eacute;</strong></li>
              </ul>
              <h6 style="font-weight:600;">Sera conserv&eacute; (dur&eacute;e l&eacute;gale)</h6>
              <p class="row-sub">Identit&eacute; de l'adh&eacute;rent, contrat, sinistres et remboursements, paiements, prises en charge.</p>
              <div style="background:#fce4ec;border-radius:8px;padding:0.6rem 0.8rem;color:#c62828;">
                <i class="bx bx-error"></i> Cette suppression est d&eacute;finitive.
              </div>
            </div>
          </template>
        </div>
        <div class="ik-modal-footer">
          <button class="btn btn-light" @click="deletion = null">Annuler</button>
          <button class="btn btn-outline-secondary" @click="actOnDeletion('reject')" :disabled="saving">Refuser la demande</button>
          <button class="btn btn-danger" @click="actOnDeletion('process')" :disabled="saving || !preview">
            <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
            Supprimer les donn&eacute;es
          </button>
        </div>
      </div>
    </div>
  </template>
</div>

<script>
function mobileAppsPage() {
  return {
    tab: 'feedback',
    tabs: [
      { key: 'feedback',      icon: 'bx bx-message-dots', label: 'Avis' },
      { key: 'souscriptions', icon: 'bx bx-cart',         label: 'Souscriptions en ligne' },
      { key: 'suppressions',  icon: 'bx bx-user-x',       label: 'Suppressions de compte' },
    ],
    loading: true,
    saving: false,
    feedback: [],
    feedbackStatus: '',
    feedbackCategory: '',
    policies: [],
    onlineMembers: 0,
    deletions: [],
    deletion: null,
    preview: null,
    categoryLabels: { information: 'Information', suggestion: 'Suggestion', bug: 'Problème', other: 'Autre' },
    tableLabels: {
      mobile_tokens: 'Sessions de connexion', mobile_qr_tokens: 'QR codes de la carte', mobile_devices: 'Appareils',
      mobile_preferences: 'Préférences', mobile_claim_drafts: 'Brouillons de sinistre', mobile_feedback: 'Avis envoyés',
      mobile_assistant_usage: "Utilisation de l'assistant", mobile_quotes: 'Devis', mobile_uploads: 'Envois inachevés',
      mobile_vault_documents: 'Documents du dossier médical',
    },

    async loadAll() {
      this.loading = true;
      const results = await Promise.allSettled([
        api('/api/applications-mobiles/feedback'),
        api('/api/applications-mobiles/souscriptions'),
        api('/api/applications-mobiles/suppressions'),
      ]);
      if (results[0].status === 'fulfilled') this.feedback = results[0].value.data || [];
      if (results[1].status === 'fulfilled') {
        this.policies = results[1].value.data || [];
        this.onlineMembers = results[1].value.online_members || 0;
      }
      if (results[2].status === 'fulfilled') this.deletions = results[2].value.data || [];
      if (results.some(r => r.status === 'rejected')) toast('Erreur chargement', 'error');
      if (this.deletions.some(d => d.status === 'requested')) this.tab = 'suppressions';
      this.loading = false;
    },

    feedbackRows() {
      return this.feedback.filter(f =>
        (!this.feedbackStatus || f.status === this.feedbackStatus) &&
        (!this.feedbackCategory || f.category === this.feedbackCategory));
    },

    async setFeedbackStatus(f, status) {
      const previous = f.status;
      f.status = status;
      try {
        await api('/api/applications-mobiles/feedback/' + f.id, { method: 'POST', body: { status } });
      } catch (e) {
        f.status = previous;
        toast(e.error || 'Erreur', 'error');
      }
    },

    async openDeletion(d) {
      this.deletion = d;
      this.preview = null;
      try {
        const res = await api('/api/applications-mobiles/suppressions/' + d.id);
        this.preview = res.data.preview;
      } catch (e) {
        toast(e.error || 'Erreur', 'error');
        this.deletion = null;
      }
    },

    async actOnDeletion(action) {
      const name = this.deletion.prenom + ' ' + this.deletion.nom;
      const question = action === 'process'
        ? 'Supprimer définitivement les données d\'application de ' + name + ' ?'
        : 'Refuser la demande de ' + name + ' ? Rien ne sera supprimé.';
      if (!confirm(question)) return;
      this.saving = true;
      try {
        await api('/api/applications-mobiles/suppressions/' + this.deletion.id, { method: 'POST', body: { action } });
        toast(action === 'process' ? 'Données supprimées' : 'Demande refusée', 'success');
        this.deletion = null;
        this.loadAll();
      } catch (e) {
        toast(e.error || 'Erreur', 'error');
      }
      this.saving = false;
    },

    fmtDateTime(d) {
      if (!d) return '';
      const date = new Date(d.replace(' ', 'T'));
      return isNaN(date) ? d : date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    },
    platformLabel(p) { return { ios: 'iOS', android: 'Android' }[p] || p || ''; },
    methodLabel(m) { return { wave: 'Wave', orange_money: 'Orange Money', card: 'Carte' }[m] || m; },
    policyLabel(s) { return { pending_payment: 'Paiement attendu', active: 'Actif', cancelled: 'Annulé', expired: 'Expiré' }[s] || s; },
    policyBadge(s) { return { active: 'badge-active', cancelled: 'badge-rejetee', expired: 'badge-inactif' }[s] || 'badge-en_attente'; },
    paymentLabel(s) { return { pending: 'En attente', succeeded: 'Payé', failed: 'Échoué', cancelled: 'Annulé', expired: 'Expiré' }[s] || s; },
    paymentBadge(s) { return { succeeded: 'badge-payee', failed: 'badge-rejetee', cancelled: 'badge-rejetee', expired: 'badge-inactif' }[s] || 'badge-en_attente'; },
    deletionLabel(s) { return { requested: 'À traiter', processed: 'Supprimé', rejected: 'Refusé' }[s] || s; },
    deletionBadge(s) { return { requested: 'badge-en_attente', processed: 'badge-validee', rejected: 'badge-rejetee' }[s] || ''; },
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
