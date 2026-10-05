<?php
$au = adherentUser();
if (!$au) {
    redirect('/login');
}
$showNav = false;
$pageTitle = 'Portail Adherent';
require basePath('src/views/layout.php');
?>

<?php /* Login is now handled by unified /login page */ ?>
<?php if (false): ?>
<!-- ── Old login form (disabled) ── -->
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(to bottom,#fff,#f5f5f9);padding:1rem;"
     x-data="{ login:'', password:'', loading:false, error:'' }">
  <div style="width:100%;max-width:400px;">
    <div style="text-align:center;margin-bottom:2rem;">
      <img src="/images/logo.png" alt="I'KARANGE" style="height:80px;margin-bottom:1rem;">
      <h2 style="font-size:1.3rem;font-weight:700;color:#1a3a3a;">Portail Adh&eacute;rent</h2>
      <p style="color:#888;font-size:0.85rem;">Acc&eacute;dez &agrave; votre espace personnel</p>
    </div>

    <div class="ik-card" style="padding:2rem;">
      <div class="mb-3">
        <label class="form-label">Identifiant</label>
        <input type="text" class="form-control" x-model="login" placeholder="Votre login" @keydown.enter="submit()">
      </div>
      <div class="mb-3">
        <label class="form-label">Mot de passe</label>
        <input type="password" class="form-control" x-model="password" placeholder="Votre mot de passe" @keydown.enter="submit()">
      </div>
      <template x-if="error">
        <div style="background:#fce4ec;color:#c62828;padding:0.5rem 0.75rem;border-radius:6px;font-size:0.82rem;margin-bottom:1rem;" x-text="error"></div>
      </template>
      <button class="btn btn-primary w-100" @click="submit()" :disabled="loading" style="padding:0.6rem;">
        <span x-show="loading" class="spinner-border spinner-border-sm me-1"></span>
        Se connecter
      </button>
    </div>

    <div style="text-align:center;margin-top:1rem;">
      <a href="/login" style="color:var(--primary);font-size:0.8rem;text-decoration:none;">Acc&egrave;s gestionnaire &rarr;</a>
    </div>
  </div>
</div>

<script>
function submit() {
  // Alpine scoped
}
document.addEventListener('alpine:init', () => {
  // The submit is handled inline
});
</script>

<!-- Inline submit handler via Alpine -->
<div x-data x-init="
  document.querySelectorAll('[\\@click=\\'submit()\\']').forEach(btn => {
    const scope = Alpine.$data(btn.closest('[x-data]'));
    btn.addEventListener('click', async () => {
      if (!scope.login || !scope.password) { scope.error = 'Veuillez remplir tous les champs'; return; }
      scope.loading = true; scope.error = '';
      try {
        const res = await fetch('/api/adherent-login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
          body: JSON.stringify({ login: scope.login, password: scope.password })
        });
        const data = await res.json();
        if (data.ok) { location.reload(); } else { scope.error = data.error || 'Erreur'; }
      } catch(e) { scope.error = 'Erreur de connexion'; }
      scope.loading = false;
    });
  });
" style="display:none;"></div>

<?php else: ?>
<!-- ── Authenticated Portal ── -->
<div style="min-height:100vh;background:var(--bg-body);">
  <!-- Header bar -->
  <div style="background:#1a3a3a;color:#fff;padding:0.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <img src="/images/logo-icon.png" alt="" style="height:32px;">
      <span style="font-weight:600;">Portail Adh&eacute;rent</span>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <span style="font-size:0.85rem;"><?= e($au['prenom'] . ' ' . $au['nom']) ?></span>
      <span style="font-size:0.72rem;background:rgba(255,255,255,0.15);padding:0.2rem 0.5rem;border-radius:12px;"><?= e($au['matricule']) ?></span>
      <a href="javascript:void(0);" style="color:var(--primary);font-size:0.82rem;text-decoration:none;"
         onclick="fetch('/api/adherent-logout',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF}}).then(()=>location.reload())">
        <i class="bx bx-log-out"></i> D&eacute;connexion
      </a>
    </div>
  </div>

  <!-- Portal content -->
  <div style="max-width:900px;margin:0 auto;padding:1.5rem;" x-data="adherentPortal()" x-init="load()">

    <!-- Tab navigation -->
    <div style="display:flex;gap:0;border-bottom:2px solid #e9ecef;margin-bottom:1.5rem;">
      <button @click="tab = 'profil'" :style="tab === 'profil' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
              style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.85rem;cursor:pointer;margin-bottom:-2px;">
        <i class="bx bx-user"></i> Mon Profil
      </button>
      <button @click="tab = 'couverture'" :style="tab === 'couverture' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
              style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.85rem;cursor:pointer;margin-bottom:-2px;">
        <i class="bx bx-shield-quarter"></i> Ma Couverture
      </button>
      <button @click="tab = 'pec'" :style="tab === 'pec' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
              style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.85rem;cursor:pointer;margin-bottom:-2px;">
        <i class="bx bx-file"></i> Mes Prises en Charge
      </button>
      <button @click="tab = 'remboursements'; loadRemboursements()"
              :style="tab === 'remboursements' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
              style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.85rem;cursor:pointer;margin-bottom:-2px;">
        <i class="bx bx-money"></i> Mes Remboursements
      </button>
      <button @click="tab = 'rapports'" :style="tab === 'rapports' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
              style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.85rem;cursor:pointer;margin-bottom:-2px;">
        <i class="bx bx-printer"></i> Mes Rapports
      </button>
    </div>

    <!-- Tab: Mon Profil -->
    <div x-show="tab === 'profil'" x-cloak>
      <div class="row g-4">
        <!-- Profile info -->
        <div class="col-md-7">
          <div class="ik-card">
            <h6 style="font-weight:600;margin-bottom:1rem;"><i class="bx bx-id-card" style="color:var(--primary);"></i> Informations personnelles</h6>
            <template x-if="data.profile">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;font-size:0.85rem;">
                <div><span style="color:#888;">Nom</span><br><strong x-text="data.profile.nom"></strong></div>
                <div><span style="color:#888;">Pr&eacute;nom</span><br><strong x-text="data.profile.prenom"></strong></div>
                <div><span style="color:#888;">Matricule</span><br><strong x-text="data.profile.matricule"></strong></div>
                <div><span style="color:#888;">Sexe</span><br><strong x-text="data.profile.sexe === 'M' ? 'Masculin' : 'Feminin'"></strong></div>
                <div><span style="color:#888;">Date de naissance</span><br><strong x-text="fmtDate(data.profile.date_naissance)"></strong></div>
                <div><span style="color:#888;">Cat&eacute;gorie</span><br><strong x-text="data.profile.categorie || '—'"></strong></div>
                <div><span style="color:#888;">Entreprise</span><br><strong x-text="data.profile.entreprise_nom || '—'"></strong></div>
                <div><span style="color:#888;">Date d'adh&eacute;sion</span><br><strong x-text="fmtDate(data.profile.date_adhesion)"></strong></div>
                <div style="grid-column:span 2;">
                  <span style="color:#888;">T&eacute;l&eacute;phone</span><br>
                  <div style="display:flex;gap:0.5rem;align-items:center;">
                    <input type="text" class="form-control form-control-sm" style="max-width:200px;" x-model="telephone">
                    <button class="btn btn-sm btn-outline-primary" @click="updatePhone()" :disabled="savingPhone">
                      <span x-show="savingPhone" class="spinner-border spinner-border-sm"></span>
                      <span x-show="!savingPhone">Modifier</span>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Stats + password -->
        <div class="col-md-5">
          <div class="ik-card mb-3">
            <h6 style="font-weight:600;margin-bottom:1rem;"><i class="bx bx-bar-chart" style="color:var(--primary);"></i> R&eacute;sum&eacute;</h6>
            <div style="display:grid;gap:0.75rem;">
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                <span style="color:#888;">PEC total</span>
                <strong x-text="data.pec_total ?? 0"></strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                <span style="color:#888;">En attente</span>
                <strong style="color:#e65100;" x-text="data.pec_en_attente ?? 0"></strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                <span style="color:#888;">Consomm&eacute; (ann&eacute;e)</span>
                <strong x-text="fmtMoney(data.consomme_annee)"></strong>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                <span style="color:#888;">Plafond annuel</span>
                <strong x-text="fmtMoney(data.plafond_annuel)"></strong>
              </div>
            </div>
          </div>

          <div class="ik-card">
            <h6 style="font-weight:600;margin-bottom:1rem;"><i class="bx bx-lock" style="color:var(--primary);"></i> Changer le mot de passe</h6>
            <div class="mb-2">
              <input type="password" class="form-control form-control-sm" placeholder="Mot de passe actuel" x-model="pwCurrent">
            </div>
            <div class="mb-2">
              <input type="password" class="form-control form-control-sm" placeholder="Nouveau mot de passe (6+ car.)" x-model="pwNew">
            </div>
            <button class="btn btn-sm btn-primary w-100" @click="changePassword()" :disabled="savingPw">Modifier</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Ma Couverture -->
    <div x-show="tab === 'couverture'" x-cloak>
      <div class="ik-card">
        <h6 style="font-weight:600;margin-bottom:1rem;"><i class="bx bx-list-check" style="color:var(--primary);"></i> Bar&egrave;me de prestations</h6>

        <template x-if="baremes.length === 0">
          <div class="empty-state">
            <i class="bx bx-info-circle"></i>
            <p>Aucun bar&egrave;me d&eacute;fini pour votre entreprise</p>
          </div>
        </template>

        <template x-if="baremes.length > 0">
          <div style="overflow-x:auto;">
            <table class="ik-table">
              <thead>
                <tr>
                  <th>Prestation</th>
                  <th>Taux</th>
                  <th>Plafond</th>
                  <th>P&eacute;riode</th>
                  <th>Consomm&eacute;</th>
                  <th>Disponible</th>
                </tr>
              </thead>
              <tbody>
                <template x-for="b in baremes" :key="b.id">
                  <tr>
                    <td><strong x-text="b.libelle"></strong><br><span class="row-sub" x-text="b.type_acte"></span></td>
                    <td x-text="b.taux_couverture + '%'"></td>
                    <td x-text="b.plafond_acte ? fmtMoney(b.plafond_acte) : 'Frais r\u00e9els'"></td>
                    <td x-text="formatPeriode(b.periode_plafond)"></td>
                    <td x-text="b.plafond_acte ? fmtMoney(b.consomme) : '—'"></td>
                    <td>
                      <template x-if="b.disponible !== null && b.disponible !== undefined">
                        <span :style="b.disponible < (b.plafond_acte * 0.2) ? 'color:#c62828;font-weight:600;' : 'color:#2e7d32;font-weight:600;'"
                              x-text="fmtMoney(b.disponible)"></span>
                      </template>
                      <template x-if="b.disponible === null || b.disponible === undefined">
                        <span style="color:#888;">&infin;</span>
                      </template>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </div>

    <!-- Tab: Mes PEC -->
    <div x-show="tab === 'pec'" x-cloak>
      <div class="ik-card" style="padding:0;overflow-x:auto;">
        <template x-if="pecList.length === 0">
          <div class="empty-state">
            <i class="bx bx-file"></i>
            <p>Aucune prise en charge</p>
          </div>
        </template>

        <template x-if="pecList.length > 0">
          <table class="ik-table">
            <thead>
              <tr>
                <th>N&deg;</th>
                <th>Date</th>
                <th>Acte</th>
                <th>Prestataire</th>
                <th>Montant</th>
                <th>Part IPM</th>
                <th>Part Adh.</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="p in pecList" :key="p.id">
                <tr>
                  <td><span class="row-sub" x-text="p.numero"></span></td>
                  <td x-text="fmtDate(p.date_soins)"></td>
                  <td x-text="p.type_acte"></td>
                  <td x-text="p.prestataire_nom || '—'"></td>
                  <td x-text="fmtMoney(p.montant_total)"></td>
                  <td style="color:var(--primary-dark);font-weight:600;" x-text="fmtMoney(p.part_ipm)"></td>
                  <td x-text="fmtMoney(p.part_adherent)"></td>
                  <td>
                    <span class="badge-status" :class="'badge-' + p.statut" x-text="formatStatut(p.statut)"></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </template>
      </div>
    </div>

    <!-- Tab: Mes Remboursements -->
    <div x-show="tab === 'remboursements'" x-cloak>
      <!-- New claim button -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h6 style="font-weight:600;margin:0;"><i class="bx bx-money" style="color:var(--primary);"></i> Mes demandes de remboursement</h6>
        <button class="btn btn-primary btn-sm" @click="showNewClaim = true" style="display:flex;align-items:center;gap:0.3rem;">
          <i class="bx bx-plus"></i> Nouvelle demande
        </button>
      </div>

      <!-- Claims list -->
      <div class="ik-card" style="padding:0;overflow-x:auto;">
        <template x-if="rembList.length === 0">
          <div class="empty-state">
            <i class="bx bx-money"></i>
            <p>Aucune demande de remboursement</p>
          </div>
        </template>

        <template x-if="rembList.length > 0">
          <table class="ik-table">
            <thead><tr>
              <th>N&deg;</th><th>Date soins</th><th>Acte</th><th>Montant</th><th>Rembours&eacute;</th><th>Docs</th><th>Statut</th><th>Commentaire</th>
            </tr></thead>
            <tbody>
              <template x-for="r in rembList" :key="r.id">
                <tr>
                  <td><span class="row-sub" x-text="r.numero"></span></td>
                  <td x-text="fmtDate(r.date_soins)"></td>
                  <td x-text="r.type_acte"></td>
                  <td style="font-weight:600;" x-text="fmtMoney(r.montant)"></td>
                  <td style="color:var(--primary-dark);" x-text="fmtMoney(r.montant_rembourse)"></td>
                  <td><span x-text="r.nb_docs"></span> <i class="bx bx-paperclip" style="color:#888;"></i></td>
                  <td><span class="badge-status" :class="rembBadge(r.statut)" x-text="rembLabel(r.statut)"></span></td>
                  <td style="font-size:0.75rem;color:#888;max-width:150px;" x-text="r.commentaire_admin || ''"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </template>
      </div>

      <!-- New claim modal -->
      <template x-if="showNewClaim">
        <div class="ik-modal-overlay" @click.self="confirmCloseModal('showNewClaim', $data)">
          <div class="ik-modal" style="max-width:560px;">
            <div class="ik-modal-header">
              <h3>Nouvelle demande de remboursement</h3>
              <button class="close-btn" @click="showNewClaim = false">&times;</button>
            </div>
            <div class="ik-modal-body">
              <div class="row g-3 mb-3">
                <div class="col-sm-6">
                  <label class="form-label">Type d'acte *</label>
                  <select class="form-select" x-model="claimForm.type_acte" required>
                    <option value="">-- Choisir --</option>
                    <option value="consultation">Consultation</option>
                    <option value="pharmacie">Pharmacie</option>
                    <option value="analyse">Analyse</option>
                    <option value="imagerie">Imagerie</option>
                    <option value="hospitalisation">Hospitalisation</option>
                    <option value="chirurgie">Chirurgie</option>
                    <option value="dentaire">Dentaire</option>
                    <option value="optique">Optique</option>
                    <option value="maternite">Maternit&eacute;</option>
                    <option value="autre">Autre</option>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Date des soins *</label>
                  <input type="date" class="form-control" x-model="claimForm.date_soins" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Montant d&eacute;bours&eacute; (F CFA) *</label>
                <input type="number" class="form-control" x-model="claimForm.montant" min="1" required placeholder="Ex: 25000">
              </div>

              <div class="mb-3">
                <label class="form-label">Motif / Description</label>
                <textarea class="form-control" rows="2" x-model="claimForm.motif" placeholder="Decrivez les soins effectues..."></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Pi&egrave;ces justificatives * <span style="color:#888;font-weight:400;">(factures, ordonnances, bulletins)</span></label>
                <div style="border:2px dashed #d5d9dd;border-radius:8px;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .2s;"
                     @click="$refs.claimFiles.click()"
                     @dragover.prevent="$el.style.borderColor='var(--primary)'"
                     @dragleave="$el.style.borderColor='#d5d9dd'"
                     @drop.prevent="handleClaimDrop($event); $el.style.borderColor='#d5d9dd'">
                  <i class="bx bx-cloud-upload" style="font-size:2rem;color:#aaa;"></i>
                  <p style="margin:0.5rem 0 0;font-size:0.82rem;color:#888;">
                    Cliquez ou glissez vos fichiers ici<br>
                    <span style="font-size:0.72rem;">JPEG, PNG, PDF &mdash; max 5 Mo par fichier</span>
                  </p>
                </div>
                <input type="file" x-ref="claimFiles" multiple accept="image/jpeg,image/png,application/pdf"
                       @change="claimFiles = Array.from($event.target.files)" style="display:none;">

                <!-- Selected files -->
                <template x-if="claimFiles.length > 0">
                  <div style="margin-top:0.75rem;">
                    <template x-for="(f, idx) in claimFiles" :key="idx">
                      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0.5rem;background:#f8f9fa;border-radius:4px;margin-bottom:0.25rem;font-size:0.78rem;">
                        <span><i class="bx bx-file" style="color:var(--primary);"></i> <span x-text="f.name"></span> <span style="color:#888;" x-text="'(' + (f.size / 1024).toFixed(0) + ' Ko)'"></span></span>
                        <button type="button" @click="claimFiles.splice(idx, 1)" style="background:none;border:none;color:#c62828;cursor:pointer;font-size:1rem;">&times;</button>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </div>
            <div class="ik-modal-footer">
              <button class="btn btn-light" @click="showNewClaim = false">Annuler</button>
              <button class="btn btn-primary" @click="submitClaim()" :disabled="submittingClaim">
                <span x-show="submittingClaim" class="spinner-border spinner-border-sm me-1"></span>
                Soumettre la demande
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Tab: Mes Rapports -->
    <div x-show="tab === 'rapports'" x-cloak>
      <div class="row g-3 mb-4">
        <!-- Report: Mes Prestations -->
        <div class="col-md-6">
          <div class="ik-card" style="text-align:center;padding:1.5rem;">
            <i class="bx bx-file" style="font-size:2.5rem;color:#1565c0;"></i>
            <h6 style="font-weight:600;margin:0.75rem 0 0.25rem;">Historique des Prestations</h6>
            <p style="font-size:0.75rem;color:#888;margin-bottom:1rem;">Actes m&eacute;dicaux, co&ucirc;ts et prises en charge</p>
            <div style="display:flex;gap:0.5rem;justify-content:center;">
              <button class="btn btn-sm btn-primary" @click="exportMyReport('prestations', 'csv')" style="display:flex;align-items:center;gap:0.3rem;">
                <i class="bx bx-spreadsheet"></i> Excel
              </button>
              <button class="btn btn-sm btn-outline-primary" @click="exportMyReport('prestations', 'pdf')" style="display:flex;align-items:center;gap:0.3rem;">
                <i class="bx bx-file-blank"></i> PDF
              </button>
            </div>
          </div>
        </div>

        <!-- Report: Ma Couverture -->
        <div class="col-md-6">
          <div class="ik-card" style="text-align:center;padding:1.5rem;">
            <i class="bx bx-shield-quarter" style="font-size:2.5rem;color:var(--primary);"></i>
            <h6 style="font-weight:600;margin:0.75rem 0 0.25rem;">Ma Couverture</h6>
            <p style="font-size:0.75rem;color:#888;margin-bottom:1rem;">Bar&egrave;me, plafonds et consommation par acte</p>
            <div style="display:flex;gap:0.5rem;justify-content:center;">
              <button class="btn btn-sm btn-primary" @click="exportMyBaremes('csv')" style="display:flex;align-items:center;gap:0.3rem;">
                <i class="bx bx-spreadsheet"></i> Excel
              </button>
              <button class="btn btn-sm btn-outline-primary" @click="exportMyBaremes('pdf')" style="display:flex;align-items:center;gap:0.3rem;">
                <i class="bx bx-file-blank"></i> PDF
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="ik-card" style="background:var(--bg-body);border-style:dashed;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <i class="bx bx-info-circle" style="font-size:1.25rem;color:var(--primary);"></i>
          <div style="font-size:0.82rem;color:#666;">
            Vos rapports contiennent uniquement vos donn&eacute;es personnelles.
            Les fichiers Excel peuvent &ecirc;tre ouverts avec Microsoft Excel ou Google Sheets.
            Le format PDF s'ouvre dans votre navigateur.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function adherentPortal() {
  return {
    tab: 'profil',
    data: {},
    baremes: [],
    pecList: [],
    rembList: [],
    telephone: '',
    savingPhone: false,
    pwCurrent: '',
    pwNew: '',
    savingPw: false,
    showNewClaim: false,
    submittingClaim: false,
    claimFiles: [],
    claimForm: { type_acte: '', date_soins: '', montant: '', motif: '' },

    async load() {
      try {
        this.data = await api('/api/portail-adherent/dashboard');
        this.telephone = this.data.profile?.telephone || '';
      } catch(e) {
        toast('Erreur chargement', 'error');
      }
      // Load baremes and PEC in parallel
      this.loadBaremes();
      this.loadPec();
    },

    async loadBaremes() {
      try {
        const res = await api('/api/portail-adherent/baremes');
        this.baremes = res.data || [];
      } catch(e) {}
    },

    async loadPec() {
      try {
        const res = await api('/api/portail-adherent/pec');
        this.pecList = res.data || [];
      } catch(e) {}
    },

    async updatePhone() {
      this.savingPhone = true;
      try {
        await api('/api/portail-adherent/profile', { method: 'POST', body: { telephone: this.telephone } });
        toast('Telephone mis a jour', 'success');
      } catch(e) { toast(e.error || 'Erreur', 'error'); }
      this.savingPhone = false;
    },

    async changePassword() {
      if (!this.pwCurrent || !this.pwNew) { toast('Remplissez les deux champs', 'error'); return; }
      this.savingPw = true;
      try {
        await api('/api/portail-adherent/password', { method: 'POST', body: { current_password: this.pwCurrent, new_password: this.pwNew } });
        toast('Mot de passe modifie', 'success');
        this.pwCurrent = '';
        this.pwNew = '';
      } catch(e) { toast(e.error || 'Erreur', 'error'); }
      this.savingPw = false;
    },

    formatStatut(s) {
      const map = { en_attente:'En attente', approuvee:'Approuvee', reglee:'Reglee', rejetee:'Rejetee', facturee:'Facturee' };
      return map[s] || s;
    },

    formatPeriode(p) {
      if (!p) return '—';
      const map = { par_evenement:'Par evenement', par_an:'Par an', par_2_ans:'Par 2 ans' };
      return map[p] || p;
    },

    // ── Remboursements ──
    async loadRemboursements() {
      try {
        const res = await api('/api/portail-adherent/remboursements');
        this.rembList = res.data || [];
      } catch(e) {}
    },

    rembLabel(s) {
      const map = { soumis:'Soumis', en_cours:'En cours', valide:'Valide', rejete:'Rejete', paye:'Paye' };
      return map[s] || s;
    },

    rembBadge(s) {
      const map = { soumis:'badge-en_attente', en_cours:'badge-facturee', valide:'badge-approuvee', rejete:'badge-rejetee', paye:'badge-payee' };
      return map[s] || 'badge-en_attente';
    },

    handleClaimDrop(e) {
      const files = Array.from(e.dataTransfer.files);
      this.claimFiles = [...this.claimFiles, ...files];
    },

    async submitClaim() {
      if (!this.claimForm.type_acte || !this.claimForm.date_soins || !this.claimForm.montant) {
        toast('Remplissez tous les champs obligatoires', 'error');
        return;
      }
      if (this.claimFiles.length === 0) {
        toast('Ajoutez au moins un justificatif', 'error');
        return;
      }

      this.submittingClaim = true;
      try {
        const fd = new FormData();
        fd.append('type_acte', this.claimForm.type_acte);
        fd.append('date_soins', this.claimForm.date_soins);
        fd.append('montant', this.claimForm.montant);
        fd.append('motif', this.claimForm.motif);
        for (const f of this.claimFiles) {
          fd.append('documents[]', f);
        }

        const res = await api('/api/portail-adherent/remboursement-create', {
          method: 'POST',
          body: fd,
        });

        toast('Demande ' + res.numero + ' soumise avec ' + res.docs + ' document(s)', 'success');
        this.showNewClaim = false;
        this.claimForm = { type_acte: '', date_soins: '', montant: '', motif: '' };
        this.claimFiles = [];
        this.loadRemboursements();
      } catch(e) {
        toast(e.error || 'Erreur', 'error');
      }
      this.submittingClaim = false;
    },

    // ── Report exports ──
    exportMyReport(type, format) {
      if (type === 'prestations') {
        if (format === 'csv') {
          this._downloadPecCSV();
        } else {
          this._printPecPDF();
        }
      }
    },

    _downloadPecCSV() {
      const rows = this.pecList || [];
      if (!rows.length) { toast('Aucune prestation', 'info'); return; }
      const bom = '\uFEFF';
      const header = 'Numero;Date;Acte;Prestataire;Motif;Montant total;Taux;Part IPM;Part Adherent;Statut\n';
      const csv = rows.map(r =>
        [r.numero, r.date_soins, r.type_acte, r.prestataire_nom || '', r.motif || '',
         r.montant_total, r.taux_couverture, r.part_ipm, r.part_adherent, r.statut].join(';')
      ).join('\n');
      const blob = new Blob([bom + header + csv], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'mes-prestations-' + new Date().toISOString().split('T')[0] + '.csv';
      a.click();
    },

    _printPecPDF() {
      const rows = this.pecList || [];
      if (!rows.length) { toast('Aucune prestation', 'info'); return; }
      const p = this.data.profile || {};
      const trs = rows.map(r =>
        '<tr><td>' + [r.numero, fmtDate(r.date_soins), r.type_acte, r.prestataire_nom || '', fmtMoney(r.montant_total), fmtMoney(r.part_ipm), fmtMoney(r.part_adherent), this.formatStatut(r.statut)].join('</td><td>') + '</td></tr>'
      ).join('');
      this._openPrintWindow('Historique des Prestations',
        '<p style="font-size:11px;"><strong>Adherent:</strong> ' + (p.prenom || '') + ' ' + (p.nom || '') + ' | <strong>Matricule:</strong> ' + (p.matricule || '') + ' | <strong>Entreprise:</strong> ' + (p.entreprise_nom || '') + '</p>',
        '<th>N\u00b0</th><th>Date</th><th>Acte</th><th>Prestataire</th><th>Montant</th><th>Part IPM</th><th>Part Adh.</th><th>Statut</th>',
        trs
      );
    },

    exportMyBaremes(format) {
      const rows = this.baremes || [];
      if (!rows.length) { toast('Aucun bareme', 'info'); return; }
      if (format === 'csv') {
        const bom = '\uFEFF';
        const header = 'Prestation;Code;Taux %;Plafond;Periode;Consomme;Disponible\n';
        const csv = rows.map(r =>
          [r.libelle, r.type_acte, r.taux_couverture, r.plafond_acte || 'Frais reels',
           r.periode_plafond || '', r.consomme || 0, r.disponible ?? 'Illimite'].join(';')
        ).join('\n');
        const blob = new Blob([bom + header + csv], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'ma-couverture-' + new Date().toISOString().split('T')[0] + '.csv';
        a.click();
      } else {
        const p = this.data.profile || {};
        const trs = rows.map(r =>
          '<tr><td>' + [r.libelle, r.taux_couverture + '%', r.plafond_acte ? fmtMoney(r.plafond_acte) : 'Frais reels', this.formatPeriode(r.periode_plafond), r.plafond_acte ? fmtMoney(r.consomme || 0) : '—', r.disponible != null ? fmtMoney(r.disponible) : '\u221e'].join('</td><td>') + '</td></tr>'
        ).join('');
        this._openPrintWindow('Ma Couverture - Bareme de Prestations',
          '<p style="font-size:11px;"><strong>Adherent:</strong> ' + (p.prenom || '') + ' ' + (p.nom || '') + ' | <strong>Matricule:</strong> ' + (p.matricule || '') + ' | <strong>Entreprise:</strong> ' + (p.entreprise_nom || '') + ' | <strong>Plafond annuel:</strong> ' + fmtMoney(p.plafond_annuel || 0) + '</p>',
          '<th>Prestation</th><th>Taux</th><th>Plafond</th><th>Periode</th><th>Consomme</th><th>Disponible</th>',
          trs
        );
      }
    },

    _openPrintWindow(title, subtitle, ths, trs) {
      const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
        '<style>body{font-family:Arial,sans-serif;margin:20px;color:#333;}' +
        '.header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1a3a3a;padding-bottom:10px;margin-bottom:10px;}' +
        '.header h1{font-size:16px;color:#1a3a3a;margin:0;}.header .date{font-size:11px;color:#888;}.header .logo{font-weight:700;color:#2dd4a8;font-size:14px;}' +
        'table{width:100%;border-collapse:collapse;font-size:10px;margin-top:8px;}' +
        'th{background:#1a3a3a;color:#fff;padding:6px 8px;text-align:left;font-size:9px;text-transform:uppercase;}' +
        'td{padding:5px 8px;border-bottom:1px solid #eee;}tr:nth-child(even){background:#f9f9f9;}' +
        '.footer{margin-top:20px;text-align:center;font-size:9px;color:#aaa;border-top:1px solid #ddd;padding-top:10px;}' +
        '@media print{body{margin:10px;}@page{size:landscape;margin:10mm;}}</style></head><body>' +
        '<div class="header"><div><span class="logo">I\'KARANGE</span><h1>' + title + '</h1></div><div class="date">Genere le ' + new Date().toLocaleDateString('fr-FR') + '</div></div>' +
        subtitle +
        '<table><thead><tr>' + ths + '</tr></thead><tbody>' + trs + '</tbody></table>' +
        '<div class="footer">I\'KARANGE - Portail Adherent | Powered by MCE Group</div>' +
        '<script>window.onload=function(){window.print();}<\/script></body></html>';
      const win = window.open('', '_blank');
      win.document.write(html);
      win.document.close();
    }
  };
}
</script>
<?php endif; ?>

<?php require basePath('src/views/layout-bottom.php'); ?>
