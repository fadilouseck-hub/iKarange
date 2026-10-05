<?php
/**
 * Tiers Payant — Prestataire Portal
 * Standalone page with its own layout (no admin sidebar).
 * Shows login form if not authenticated, portal dashboard if authenticated.
 */

$prestaUser = prestaUser();
$pageTitle  = 'Tiers payant';
$activeTab  = 'tiers-payant';

// If admin is logged in, show with sidebar; if prestataire is logged in, custom layout; otherwise login
if (authUser()) {
    // Admin viewing the page — show with sidebar layout
    $showNav = true;
    require basePath('src/views/layout.php');
    if (!$prestaUser) {
        // Admin not logged in as prestataire — redirect to unified login
        echo '<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;">';
        echo '<div class="ik-card" style="padding:2rem;text-align:center;max-width:400px;">';
        echo '<i class="bx bx-heart" style="font-size:3rem;color:var(--primary);"></i>';
        echo '<h5 style="margin:1rem 0 0.5rem;">Portail Tiers Payant</h5>';
        echo '<p style="color:#888;font-size:0.85rem;">Pour acceder au portail prestataire, connectez-vous avec un compte prestataire via la page de connexion.</p>';
        echo '<a href="/login" class="btn btn-primary" style="margin-top:1rem;">Se connecter</a>';
        echo '</div></div>';
        require basePath('src/views/layout-bottom.php');
        return;
    }
    // Admin + prestataire logged in: show portal inside admin layout
    renderPortal($prestaUser);
    require basePath('src/views/layout-bottom.php');
    return;
}

// ── No admin session → standalone prestataire portal ──
if (!$prestaUser) {
    // Redirect to unified login
    redirect('/login');
}

// Prestataire is logged in → standalone portal
$showNav = false;
require basePath('src/views/layout.php');
renderStandalonePortal($prestaUser);
require basePath('src/views/layout-bottom.php');
return;


// ════════════════════════════════════════════════════════
// RENDER FUNCTIONS
// ════════════════════════════════════════════════════════

function renderLoginPage(): void {
?>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-body);padding:1rem;">
  <div x-data="tiersPayantLogin()" style="width:100%;max-width:420px;">

    <div class="text-center mb-4">
      <img src="/images/logo-full.png" alt="I'KARANGÉ" style="width:120px;height:auto;margin-bottom:0.75rem;">
      <h2 style="font-weight:700;color:#1a3a3a;margin-bottom:0.25rem;">I'KARANG&Eacute;</h2>
      <p style="color:#888;font-size:0.85rem;">Portail Tiers Payant</p>
    </div>

    <div class="ik-card" style="padding:2rem;">
      <h4 style="text-align:center;font-weight:600;margin-bottom:0.25rem;">Connexion Prestataire</h4>
      <p style="text-align:center;color:#888;font-size:0.82rem;margin-bottom:1.5rem;">
        Acc&egrave;s au syst&egrave;me de tiers payant
      </p>

      <form @submit.prevent="submit()">
        <div class="mb-3">
          <label class="form-label">Login</label>
          <input type="text" class="form-control" x-model="login" required autofocus placeholder="Votre identifiant">
        </div>
        <div class="mb-4">
          <label class="form-label">Mot de passe</label>
          <div style="position:relative;">
            <input :type="showPwd ? 'text' : 'password'" class="form-control" x-model="password" required placeholder="Votre mot de passe">
            <button type="button" @click="showPwd = !showPwd"
                    style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;">
              <i class="bx" :class="showPwd ? 'bx-hide' : 'bx-show'"></i>
            </button>
          </div>
        </div>
        <div x-show="error" x-cloak class="alert alert-danger py-2 px-3 small" x-text="error"></div>
        <button type="submit" class="btn btn-primary w-100" :disabled="loading" style="padding:0.6rem;">
          <span x-show="!loading">Se connecter</span>
          <span x-show="loading"><i class="bx bx-loader-alt bx-spin"></i> Connexion...</span>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function tiersPayantLogin() {
  return {
    login: '', password: '', showPwd: false, loading: false, error: '',
    async submit() {
      this.error = '';
      this.loading = true;
      try {
        await api('/api/prestataire-login', { method: 'POST', body: { login: this.login, password: this.password } });
        window.location.reload();
      } catch (e) { this.error = e.error || 'Identifiants incorrects'; }
      this.loading = false;
    }
  };
}
</script>
<?php
}


function renderStandalonePortal(array $presta): void {
    $prestaName = htmlspecialchars($presta['prestataire_nom'] ?? $presta['nom_complet'] ?? 'Prestataire');
    $userName   = htmlspecialchars($presta['nom_complet'] ?? $presta['login']);
?>
<!-- Standalone Portal Header -->
<style>
  .tp-topbar {
    background: #1a3a3a;
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 1000;
  }
  .tp-topbar .tp-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
  }
  .tp-topbar .tp-brand-icon {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
  }
  .tp-topbar .tp-brand-icon img { width: 100%; height: 100%; object-fit: contain; }
  .tp-topbar .tp-brand-text { color: #fff; font-weight: 700; font-size: 1.1rem; }
  .tp-topbar .tp-brand-sub { color: #b0c4c4; font-size: 0.7rem; font-weight: 400; }
  .tp-topbar .tp-user {
    display: flex; align-items: center; gap: 0.75rem; color: #fff;
  }
  .tp-topbar .tp-user-info { text-align: right; }
  .tp-topbar .tp-user-name { font-size: 0.85rem; font-weight: 600; }
  .tp-topbar .tp-user-role { font-size: 0.72rem; color: #b0c4c4; }
  .tp-topbar .tp-logout {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex; align-items: center; gap: 0.3rem;
    transition: background .15s;
  }
  .tp-topbar .tp-logout:hover { background: rgba(255,255,255,0.2); }

  .tp-body { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
</style>

<div class="tp-topbar">
  <div class="tp-brand">
    <span class="tp-brand-icon"><img src="/images/logo-icon.png" alt="I'KARANGÉ"></span>
    <span>
      <span class="tp-brand-text">I'KARANG&Eacute;</span><br>
      <span class="tp-brand-sub">Portail Tiers Payant</span>
    </span>
  </div>
  <div class="tp-user">
    <div class="tp-user-info">
      <div class="tp-user-name"><?= $userName ?></div>
      <div class="tp-user-role"><?= $prestaName ?></div>
    </div>
    <button class="tp-logout" onclick="fetch('/api/prestataire-logout',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF}}).then(()=>location.reload())">
      <i class="bx bx-log-out"></i> D&eacute;connexion
    </button>
  </div>
</div>

<div class="tp-body">
  <?php renderPortalContent($presta); ?>
</div>
<?php
}


function renderPortal(array $presta): void {
    renderPortalContent($presta);
}


function renderPortalContent(array $presta): void {
    $prestaName = htmlspecialchars($presta['prestataire_nom'] ?? 'Prestataire');
?>
<div x-data="tiersPayantPortal()" x-init="init()">

  <!-- Module tabs -->
  <div style="display:flex;gap:0;border-bottom:2px solid #e9ecef;margin-bottom:1.25rem;">
    <button @click="module = 'tiers-payant'" :style="module === 'tiers-payant' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
            style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.9rem;cursor:pointer;margin-bottom:-2px;">
      <i class="bx bx-heart"></i> Tiers Payant
    </button>
    <button @click="module = 'facturation'; loadFacturation()" :style="module === 'facturation' ? 'border-bottom:2px solid var(--primary);color:var(--primary-dark);font-weight:600;' : 'color:#888;'"
            style="padding:0.75rem 1.25rem;background:none;border:none;font-size:0.9rem;cursor:pointer;margin-bottom:-2px;">
      <i class="bx bx-receipt"></i> Facturation
    </button>
  </div>

  <!-- ══════ Module: Tiers Payant ══════ -->
  <div x-show="module === 'tiers-payant'">

  <!-- ── Search Section ── -->
  <div class="ik-card mb-4">
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
      <div style="width:40px;height:40px;background:#e6f9f1;border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <i class="bx bx-search" style="font-size:1.2rem;color:#15803d;"></i>
      </div>
      <div>
        <h5 style="font-weight:600;margin:0;color:#1a3a3a;">Identifiez l'adh&eacute;rent</h5>
        <p style="font-size:0.78rem;color:#888;margin:0;">Recherchez par matricule, nom ou pr&eacute;nom</p>
      </div>
    </div>

    <div style="position:relative;">
      <div style="position:relative;">
        <i class="bx bx-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#aaa;font-size:1.1rem;"></i>
        <input type="text"
               class="form-control"
               style="padding-left:2.5rem;border-radius:8px;height:44px;"
               placeholder="Saisissez le matricule ou le nom de l'adh&eacute;rent..."
               x-model="searchQuery"
               @input.debounce.400ms="doSearch()"
               @focus="showDropdown = searchResults.length > 0"
               @keydown.escape="showDropdown = false">
      </div>

      <!-- Search results dropdown -->
      <div x-show="showDropdown && searchResults.length > 0" x-cloak
           @click.outside="showDropdown = false"
           style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e0e0e0;border-radius:8px;margin-top:4px;max-height:280px;overflow-y:auto;z-index:100;box-shadow:0 8px 24px rgba(0,0,0,0.12);">
        <template x-for="r in searchResults" :key="r.id">
          <div @click="selectAdherent(r)"
               style="padding:0.75rem 1rem;cursor:pointer;border-bottom:1px solid #f5f5f5;display:flex;align-items:center;gap:0.75rem;transition:background .1s;"
               onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
            <div style="width:36px;height:36px;background:#e6f9f1;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="bx bx-user" style="color:#15803d;"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:0.875rem;color:#1a3a3a;" x-text="r.nom.toUpperCase() + ' ' + r.prenom"></div>
              <div style="font-size:0.75rem;color:#888;">
                <span x-text="r.matricule" style="font-weight:600;"></span>
                <span> &bull; </span>
                <span x-text="r.entreprise_nom || '—'"></span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- No results -->
      <div x-show="showDropdown && searchResults.length === 0 && searchQuery.length >= 2 && !searching" x-cloak
           style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e0e0e0;border-radius:8px;margin-top:4px;padding:1.5rem;text-align:center;color:#888;box-shadow:0 8px 24px rgba(0,0,0,0.12);">
        <i class="bx bx-search-alt" style="font-size:1.5rem;color:#ddd;display:block;margin-bottom:0.5rem;"></i>
        Aucun adh&eacute;rent trouv&eacute;
      </div>
    </div>
  </div>

  <!-- ── Selected Adherent Section ── -->
  <template x-if="selectedAdherent">
    <div>
      <!-- Adherent Info + Coverage Row -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

        <!-- Adherent Info Card -->
        <div class="ik-card">
          <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
            <div style="width:48px;height:48px;background:linear-gradient(135deg, #1a3a3a, #2d5454);border-radius:50%;display:flex;align-items:center;justify-content:center;">
              <i class="bx bx-user" style="font-size:1.3rem;color:#fff;"></i>
            </div>
            <div>
              <div style="font-weight:700;font-size:1rem;color:#1a3a3a;" x-text="selectedAdherent.nom.toUpperCase() + ' ' + selectedAdherent.prenom"></div>
              <div style="font-size:0.78rem;color:#888;">Adh&eacute;rent</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:auto 1fr;gap:0.25rem 1rem;font-size:0.85rem;">
            <span style="color:#888;">Matricule:</span>
            <span style="font-weight:600;color:#1a3a3a;" x-text="selectedAdherent.matricule"></span>

            <span style="color:#888;">Entreprise:</span>
            <span x-text="selectedAdherent.entreprise_nom || '—'"></span>

            <span style="color:#888;">Cat&eacute;gorie:</span>
            <span x-text="formatCategorie(selectedAdherent.categorie)"></span>

            <span style="color:#888;">Statut:</span>
            <span>
              <span class="badge-status badge-actif" x-text="selectedAdherent.statut === 'actif' ? 'Actif' : selectedAdherent.statut"></span>
            </span>
          </div>
        </div>

        <!-- Coverage Card -->
        <div class="ik-card">
          <h6 style="font-weight:600;color:#1a3a3a;margin-bottom:1rem;">
            <i class="bx bx-shield-quarter" style="color:#2dd4a8;margin-right:0.3rem;"></i>
            Couverture
          </h6>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
            <div style="text-align:center;padding:0.75rem;background:#f8f9fa;border-radius:8px;">
              <div style="font-size:0.72rem;color:#888;margin-bottom:0.25rem;">Plafond annuel</div>
              <div style="font-weight:700;font-size:0.95rem;color:#1a3a3a;" x-text="fmtMoney(coverage.plafond)"></div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:#fff3e0;border-radius:8px;">
              <div style="font-size:0.72rem;color:#888;margin-bottom:0.25rem;">Consomm&eacute;</div>
              <div style="font-weight:700;font-size:0.95rem;color:#e65100;" x-text="fmtMoney(coverage.consomme)"></div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:#e6f9f1;border-radius:8px;">
              <div style="font-size:0.72rem;color:#888;margin-bottom:0.25rem;">Disponible</div>
              <div style="font-weight:700;font-size:0.95rem;color:#15803d;" x-text="fmtMoney(coverage.disponible)"></div>
            </div>
          </div>

          <!-- Progress bar -->
          <div style="margin-bottom:0.5rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:#888;margin-bottom:0.25rem;">
              <span>Consommation</span>
              <span x-text="coveragePercent + '%'"></span>
            </div>
            <div style="background:#e9ecef;border-radius:20px;height:8px;overflow:hidden;">
              <div style="height:100%;border-radius:20px;transition:width .5s ease;"
                   :style="'width:' + coveragePercent + '%;background:' + (coveragePercent > 80 ? '#dc3545' : coveragePercent > 50 ? '#ffc107' : '#2dd4a8')">
              </div>
            </div>
          </div>

          <div style="font-size:0.78rem;color:#888;">
            Taux de couverture: <strong style="color:#1a3a3a;" x-text="coverage.taux + '%'"></strong>
          </div>
        </div>
      </div>

      <!-- ── PEC Creation Form ── -->
      <div class="ik-card mb-4">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;">
          <div style="width:40px;height:40px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bx bx-file-blank" style="font-size:1.2rem;color:#1565c0;"></i>
          </div>
          <div>
            <h5 style="font-weight:600;margin:0;color:#1a3a3a;">Nouvelle prise en charge</h5>
            <p style="font-size:0.78rem;color:#888;margin:0;">Cr&eacute;er une demande de prise en charge pour cet adh&eacute;rent</p>
          </div>
        </div>

        <form @submit.prevent="submitPec()">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <!-- Type d'acte -->
            <div class="mb-3">
              <label class="form-label">Type d'acte</label>
              <select class="form-select" x-model="pecForm.type_acte" required>
                <option value="consultation">Consultation</option>
                <option value="hospitalisation">Hospitalisation</option>
                <option value="pharmacie">Pharmacie</option>
                <option value="analyse">Analyse</option>
                <option value="imagerie">Imagerie</option>
                <option value="chirurgie">Chirurgie</option>
                <option value="dentaire">Dentaire</option>
                <option value="optique">Optique</option>
                <option value="maternite">Maternit&eacute;</option>
                <option value="autre">Autre</option>
              </select>
            </div>

            <!-- Date des soins -->
            <div class="mb-3">
              <label class="form-label">Date des soins</label>
              <input type="date" class="form-control" x-model="pecForm.date_soins" required>
            </div>

            <!-- Motif -->
            <div class="mb-3">
              <label class="form-label">Motif</label>
              <input type="text" class="form-control" x-model="pecForm.motif" placeholder="Motif de la consultation">
            </div>

            <!-- Montant total -->
            <div class="mb-3">
              <label class="form-label">Montant total (FCFA)</label>
              <input type="number" class="form-control" x-model.number="pecForm.montant_total" min="1" required placeholder="0">
            </div>
          </div>

          <!-- Amount preview -->
          <div x-show="pecForm.montant_total > 0" x-cloak
               style="background:#f8f9fa;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;display:flex;gap:1.5rem;font-size:0.85rem;">
            <div>
              <span style="color:#888;">Part IPM (<?= htmlspecialchars($presta['prestataire_nom'] ?? '') ?>):</span>
              <strong style="color:#1565c0;" x-text="fmtMoney(Math.round(pecForm.montant_total * coverage.taux / 100))"></strong>
            </div>
            <div>
              <span style="color:#888;">Part adh&eacute;rent:</span>
              <strong style="color:#e65100;" x-text="fmtMoney(pecForm.montant_total - Math.round(pecForm.montant_total * coverage.taux / 100))"></strong>
            </div>
          </div>

          <!-- Warning if exceeds disponible -->
          <div x-show="pecForm.montant_total > 0 && Math.round(pecForm.montant_total * coverage.taux / 100) > coverage.disponible" x-cloak
               class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bx bx-error-circle"></i>
            La part IPM d&eacute;passe le plafond disponible. Elle sera plafonn&eacute;e &agrave;
            <strong x-text="fmtMoney(coverage.disponible)"></strong>.
          </div>

          <div x-show="pecError" x-cloak class="alert alert-danger py-2 px-3 small mb-3" x-text="pecError"></div>
          <div x-show="pecSuccess" x-cloak class="alert alert-success py-2 px-3 small mb-3" x-text="pecSuccess"></div>

          <button type="submit" class="btn btn-primary" :disabled="pecLoading" style="padding:0.5rem 2rem;">
            <span x-show="!pecLoading"><i class="bx bx-check"></i> Cr&eacute;er la prise en charge</span>
            <span x-show="pecLoading"><i class="bx bx-loader-alt bx-spin"></i> Cr&eacute;ation...</span>
          </button>
        </form>
      </div>

      <!-- ── Historique des PEC ── -->
      <div class="ik-card" x-show="historique.length > 0" x-cloak>
        <h6 style="font-weight:600;color:#1a3a3a;margin-bottom:1rem;">
          <i class="bx bx-history" style="color:#7b1fa2;margin-right:0.3rem;"></i>
          Historique des prises en charge
        </h6>

        <table class="ik-table">
          <thead>
            <tr>
              <th>N&deg;</th>
              <th>Date soins</th>
              <th>Type acte</th>
              <th>Montant</th>
              <th>Part IPM</th>
              <th>Part Adh.</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="h in historique" :key="h.id">
              <tr>
                <td style="font-weight:600;font-size:0.8rem;" x-text="h.numero"></td>
                <td x-text="fmtDate(h.date_soins)"></td>
                <td>
                  <span class="badge-type badge-clinique" x-text="formatTypeActe(h.type_acte)"></span>
                </td>
                <td x-text="fmtMoney(h.montant_total)"></td>
                <td x-text="fmtMoney(h.part_ipm)"></td>
                <td x-text="fmtMoney(h.part_adherent)"></td>
                <td>
                  <span class="badge-status" :class="'badge-' + h.statut" x-text="formatStatut(h.statut)"></span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

    </div>
  </template>

  <!-- ── Empty State (no adherent selected) ── -->
  <div x-show="!selectedAdherent && !searching" x-cloak class="text-center" style="padding:4rem 1rem;color:#999;">
    <i class="bx bx-search-alt-2" style="font-size:4rem;color:#ddd;display:block;margin-bottom:1rem;"></i>
    <h5 style="color:#888;font-weight:600;">Recherchez un adh&eacute;rent</h5>
    <p style="font-size:0.85rem;">Utilisez la barre de recherche ci-dessus pour identifier un adh&eacute;rent et cr&eacute;er une prise en charge.</p>
  </div>

  </div><!-- /module tiers-payant -->

  <!-- ══════ Module: Facturation ══════ -->
  <div x-show="module === 'facturation'" x-cloak>
    <div class="ik-card mb-4">
      <h5 style="font-weight:600;margin-bottom:1rem;">
        <i class="bx bx-receipt" style="color:var(--primary);"></i> G&eacute;n&eacute;rer une facture
      </h5>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <label class="form-label" style="font-size:0.75rem;">P&eacute;riode du</label>
          <input type="date" class="form-control form-control-sm" x-model="factDateDebut">
        </div>
        <div>
          <label class="form-label" style="font-size:0.75rem;">Au</label>
          <input type="date" class="form-control form-control-sm" x-model="factDateFin">
        </div>
        <button class="btn btn-primary btn-sm" @click="loadFacturation()" :disabled="factLoading"
                style="display:flex;align-items:center;gap:0.3rem;">
          <i class="bx bx-search"></i> Rechercher
        </button>
        <template x-if="factData && factData.nb_pec_total > 0">
          <button class="btn btn-outline-primary btn-sm" @click="printFacture()"
                  style="display:flex;align-items:center;gap:0.3rem;">
            <i class="bx bx-printer"></i> Imprimer
          </button>
        </template>
        <template x-if="factData && factData.nb_pec_total > 0">
          <button class="btn btn-primary btn-sm" @click="openSubmitModal()"
                  style="display:flex;align-items:center;gap:0.3rem;">
            <i class="bx bx-send"></i> Soumettre la facture
          </button>
        </template>
      </div>
      <div style="margin-top:0.5rem;font-size:0.72rem;color:#888;">
        <i class="bx bx-info-circle"></i> Seules les prestations <strong>approuv&eacute;es</strong> peuvent &ecirc;tre factur&eacute;es. Une facture sera cr&eacute;&eacute;e par entreprise.
      </div>
    </div>

    <!-- Submit facture modal with file upload -->
    <template x-if="showSubmitModal">
      <div class="ik-modal-overlay" @click.self="confirmCloseModal('showSubmitModal', $data)">
        <div class="ik-modal" style="max-width:600px;">
          <div class="ik-modal-header">
            <h3>Soumettre la facture</h3>
            <button class="close-btn" @click="showSubmitModal = false">&times;</button>
          </div>
          <div class="ik-modal-body">
            <div style="background:#e3f2fd;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.82rem;">
              <i class="bx bx-info-circle" style="color:#1565c0;"></i>
              Une facture sera cr&eacute;&eacute;e <strong>pour chaque entreprise</strong>. Les prestations associ&eacute;es seront marqu&eacute;es comme &laquo; factur&eacute;es &raquo;.
            </div>

            <!-- Enterprise checklist -->
            <label class="form-label" style="font-weight:600;">Entreprises &agrave; facturer</label>
            <div style="border:1px solid #e0e0e0;border-radius:8px;max-height:200px;overflow-y:auto;margin-bottom:1rem;">
              <template x-for="ent in (factData ? factData.entreprises : [])" :key="ent.entreprise_id">
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:0.85rem;">
                  <input type="checkbox" :value="ent.entreprise_id" x-model="submitForm.entreprises" :checked="true">
                  <span style="flex:1;font-weight:500;" x-text="ent.entreprise_nom"></span>
                  <span style="color:var(--primary-dark);font-weight:600;" x-text="fmtMoney(ent.total_ipm)"></span>
                </label>
              </template>
            </div>

            <!-- File upload zone -->
            <label class="form-label" style="font-weight:600;">Pi&egrave;ces justificatives <span style="color:#888;font-weight:400;">(factures originales, ordonnances, bulletins...)</span></label>
            <div style="border:2px dashed #d5d9dd;border-radius:8px;padding:1.5rem;text-align:center;cursor:pointer;"
                 @click="$refs.factFiles.click()"
                 @dragover.prevent="$el.style.borderColor='var(--primary)'"
                 @dragleave="$el.style.borderColor='#d5d9dd'"
                 @drop.prevent="handleFactDrop($event); $el.style.borderColor='#d5d9dd'">
              <i class="bx bx-cloud-upload" style="font-size:2rem;color:#aaa;"></i>
              <p style="margin:0.5rem 0 0;font-size:0.82rem;color:#888;">
                Cliquez ou glissez vos fichiers ici<br>
                <span style="font-size:0.72rem;">JPEG, PNG, PDF &mdash; max 5 Mo par fichier</span>
              </p>
            </div>
            <input type="file" x-ref="factFiles" multiple accept="image/jpeg,image/png,application/pdf"
                   @change="submitForm.files = Array.from($event.target.files)" style="display:none;">

            <template x-if="submitForm.files.length > 0">
              <div style="margin-top:0.75rem;">
                <template x-for="(f, idx) in submitForm.files" :key="idx">
                  <div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0.5rem;background:#f8f9fa;border-radius:4px;margin-bottom:0.25rem;font-size:0.78rem;">
                    <span><i class="bx bx-file" style="color:var(--primary);"></i> <span x-text="f.name"></span> <span style="color:#888;" x-text="'(' + (f.size / 1024).toFixed(0) + ' Ko)'"></span></span>
                    <button type="button" @click="submitForm.files.splice(idx, 1)" style="background:none;border:none;color:#c62828;cursor:pointer;font-size:1rem;">&times;</button>
                  </div>
                </template>
              </div>
            </template>
          </div>
          <div class="ik-modal-footer">
            <button class="btn btn-light" @click="showSubmitModal = false">Annuler</button>
            <button class="btn btn-primary" @click="submitFacture()" :disabled="submittingFact">
              <span x-show="submittingFact" class="spinner-border spinner-border-sm me-1"></span>
              Soumettre <span x-show="submitForm.entreprises.length > 0" x-text="'(' + submitForm.entreprises.length + ' facture' + (submitForm.entreprises.length > 1 ? 's' : '') + ')'"></span>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Facturation results -->
    <template x-if="factLoading">
      <div style="text-align:center;padding:3rem;color:#888;">
        <i class="bx bx-loader-alt bx-spin" style="font-size:2rem;"></i>
      </div>
    </template>

    <template x-if="!factLoading && factData && factData.nb_pec_total === 0">
      <div class="ik-card empty-state">
        <i class="bx bx-receipt"></i>
        <p>Aucune prestation pour cette p&eacute;riode</p>
      </div>
    </template>

    <template x-if="!factLoading && factData && factData.nb_pec_total > 0">
      <div>
        <!-- Summary -->
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
          <div class="stat-card" style="flex:1;min-width:140px;">
            <div class="stat-label">Entreprises</div>
            <div class="stat-value" x-text="factData.entreprises.length"></div>
          </div>
          <div class="stat-card" style="flex:1;min-width:140px;">
            <div class="stat-label">Total PEC</div>
            <div class="stat-value" x-text="factData.nb_pec_total"></div>
          </div>
          <div class="stat-card" style="flex:1;min-width:140px;">
            <div class="stat-label">Montant total</div>
            <div class="stat-value" x-text="fmtMoney(factData.grand_total)"></div>
          </div>
          <div class="stat-card" style="flex:1;min-width:140px;">
            <div class="stat-label">Part IPM (ch&egrave;ques)</div>
            <div class="stat-value" style="color:var(--primary-dark);" x-text="fmtMoney(factData.grand_total_ipm)"></div>
          </div>
        </div>

        <!-- Per-enterprise breakdown -->
        <template x-for="ent in factData.entreprises" :key="ent.entreprise_id">
          <div class="ik-card mb-3" style="padding:0;overflow:hidden;">
            <div style="background:#1a3a3a;color:#fff;padding:0.6rem 1rem;display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;" x-text="ent.entreprise_nom"></span>
              <span style="font-size:0.78rem;" x-text="ent.nb_pec + ' prestation(s) — ' + fmtMoney(ent.total_ipm) + ' (cheque)'"></span>
            </div>
            <table class="ik-table" style="margin:0;">
              <thead><tr>
                <th>N&deg;</th><th>Date</th><th>Adh&eacute;rent</th><th>Acte</th>
                <th style="text-align:right;">Montant</th><th style="text-align:right;">Part IPM</th>
              </tr></thead>
              <tbody>
                <template x-for="p in ent.pecs" :key="p.id">
                  <tr>
                    <td><span class="row-sub" x-text="p.numero"></span></td>
                    <td x-text="fmtDate(p.date_soins)"></td>
                    <td class="row-name" x-text="p.adherent_nom + ' ' + p.adherent_prenom"></td>
                    <td x-text="p.type_acte"></td>
                    <td style="text-align:right;" x-text="fmtMoney(p.montant_total)"></td>
                    <td style="text-align:right;color:var(--primary-dark);font-weight:600;" x-text="fmtMoney(p.part_ipm)"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </template>
  </div><!-- /module facturation -->

</div>

<script>
function tiersPayantPortal() {
  return {
    // Module tab
    module: 'tiers-payant',

    // Search
    searchQuery: '',
    searchResults: [],
    showDropdown: false,
    searching: false,

    // Facturation
    factDateDebut: '',
    factDateFin: '',
    factLoading: false,
    factData: null,
    showSubmitModal: false,
    submittingFact: false,
    submitForm: { entreprises: [], files: [] },

    // Selected adherent
    selectedAdherent: null,
    coverage: { plafond: 0, consomme: 0, disponible: 0, taux: 70 },
    historique: [],
    loadingCoverage: false,

    // PEC form
    pecForm: {
      type_acte: 'consultation',
      date_soins: new Date().toISOString().split('T')[0],
      motif: '',
      montant_total: 0
    },
    pecLoading: false,
    pecError: '',
    pecSuccess: '',

    get coveragePercent() {
      if (!this.coverage.plafond) return 0;
      return Math.min(100, Math.round(this.coverage.consomme / this.coverage.plafond * 100));
    },

    init() {},

    async doSearch() {
      const q = this.searchQuery.trim();
      if (q.length < 2) {
        this.searchResults = [];
        this.showDropdown = false;
        return;
      }
      this.searching = true;
      try {
        const res = await api('/api/tiers-payant/search?q=' + encodeURIComponent(q));
        this.searchResults = res.data || [];
        this.showDropdown = true;
      } catch (e) {
        this.searchResults = [];
      }
      this.searching = false;
    },

    async selectAdherent(a) {
      this.showDropdown = false;
      this.searchQuery = a.nom.toUpperCase() + ' ' + a.prenom + ' (' + a.matricule + ')';
      this.selectedAdherent = a;
      this.loadingCoverage = true;
      this.pecError = '';
      this.pecSuccess = '';

      // Reset form
      this.pecForm = {
        type_acte: 'consultation',
        date_soins: new Date().toISOString().split('T')[0],
        motif: '',
        montant_total: 0
      };

      try {
        const res = await api('/api/tiers-payant/couverture/' + a.id);
        this.coverage = {
          plafond: res.plafond || 0,
          consomme: res.consomme || 0,
          disponible: res.disponible || 0,
          taux: res.taux || 70
        };
        this.historique = res.historique || [];
      } catch (e) {
        toast(e.error || 'Erreur lors du chargement', 'error');
      }
      this.loadingCoverage = false;
    },

    async submitPec() {
      this.pecError = '';
      this.pecSuccess = '';

      if (!this.selectedAdherent) {
        this.pecError = "Veuillez d'abord sélectionner un adhérent";
        return;
      }
      if (this.pecForm.montant_total <= 0) {
        this.pecError = 'Le montant doit être supérieur à 0';
        return;
      }

      this.pecLoading = true;
      try {
        const res = await api('/api/tiers-payant/pec', {
          method: 'POST',
          body: {
            adherent_id: this.selectedAdherent.id,
            type_acte: this.pecForm.type_acte,
            date_soins: this.pecForm.date_soins,
            montant_total: this.pecForm.montant_total,
            motif: this.pecForm.motif
          }
        });
        this.pecSuccess = 'Prise en charge ' + res.numero + ' créée avec succès!';
        toast(this.pecSuccess, 'success');

        // Open receipt for signature in new window
        if (res.id) {
          window.open('/api/tiers-payant/pec-receipt/' + res.id, '_blank');
        }

        // Reset form
        this.pecForm = {
          type_acte: 'consultation',
          date_soins: new Date().toISOString().split('T')[0],
          motif: '',
          montant_total: 0
        };

        // Refresh coverage
        await this.selectAdherent(this.selectedAdherent);
      } catch (e) {
        this.pecError = e.error || 'Erreur lors de la création';
      }
      this.pecLoading = false;
    },

    formatCategorie(c) {
      const map = { titulaire: 'Titulaire', conjoint: 'Conjoint', enfant: 'Enfant' };
      return map[c] || c;
    },

    formatTypeActe(t) {
      const map = {
        consultation: 'Consultation', analyse: 'Analyse', pharmacie: 'Pharmacie',
        hospitalisation: 'Hospitalisation', imagerie: 'Imagerie', dentaire: 'Dentaire',
        chirurgie: 'Chirurgie', optique: 'Optique', maternite: 'Maternité', autre: 'Autre'
      };
      return map[t] || t;
    },

    formatStatut(s) {
      const map = {
        en_attente: 'En attente', approuvee: 'Approuvée', reglee: 'Réglée',
        rejetee: 'Rejetée', facturee: 'Facturée'
      };
      return map[s] || s;
    },

    fmtMoney(n) { return window.fmtMoney ? fmtMoney(n) : (n || 0).toLocaleString('fr-FR') + ' F'; },
    fmtDate(d) { return window.fmtDate ? fmtDate(d) : d; },

    // ── Facturation ──
    async loadFacturation() {
      this.factLoading = true;
      this.factData = null;
      try {
        const params = new URLSearchParams();
        if (this.factDateDebut) params.set('date_debut', this.factDateDebut);
        if (this.factDateFin) params.set('date_fin', this.factDateFin);
        this.factData = await api('/api/tiers-payant/facturation?' + params.toString());
      } catch(e) {
        toast('Erreur chargement facturation', 'error');
      }
      this.factLoading = false;
    },

    printFacture() {
      const params = new URLSearchParams();
      if (this.factDateDebut) params.set('date_debut', this.factDateDebut);
      if (this.factDateFin) params.set('date_fin', this.factDateFin);
      params.set('format', 'print');
      window.open('/api/tiers-payant/facturation?' + params.toString(), '_blank');
    },

    openSubmitModal() {
      // Pre-select all enterprises by default
      this.submitForm = {
        entreprises: (this.factData?.entreprises || []).map(e => e.entreprise_id),
        files: [],
      };
      this.showSubmitModal = true;
    },

    handleFactDrop(e) {
      const files = Array.from(e.dataTransfer.files);
      this.submitForm.files = [...this.submitForm.files, ...files];
    },

    async submitFacture() {
      if (this.submitForm.entreprises.length === 0) {
        toast('Selectionnez au moins une entreprise', 'error');
        return;
      }

      if (!confirm('Confirmer la creation de ' + this.submitForm.entreprises.length + ' facture(s) ? Les prestations seront marquees comme facturees.')) {
        return;
      }

      this.submittingFact = true;
      try {
        const fd = new FormData();
        if (this.factDateDebut) fd.append('date_debut', this.factDateDebut);
        if (this.factDateFin) fd.append('date_fin', this.factDateFin);
        for (const eid of this.submitForm.entreprises) {
          fd.append('entreprises[]', eid);
        }
        for (const f of this.submitForm.files) {
          fd.append('documents[]', f);
        }

        const res = await api('/api/tiers-payant/facture-submit', {
          method: 'POST',
          body: fd,
        });

        toast(res.total_factures + ' facture(s) cree(s) avec ' + res.total_documents + ' document(s)', 'success');
        this.showSubmitModal = false;
        this.submitForm = { entreprises: [], files: [] };
        // Refresh facturation data
        this.loadFacturation();
      } catch(e) {
        toast(e.error || 'Erreur', 'error');
      }
      this.submittingFact = false;
    }
  };
}
</script>
<?php
}
