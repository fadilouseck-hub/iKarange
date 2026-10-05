<?php
// Redirect if already logged in (any user type)
if (authUser()) {
    redirect('/');
}
if (prestaUser()) {
    redirect('/tiers-payant');
}
if (adherentUser()) {
    redirect('/portail-adherent');
}

$pageTitle = 'Connexion';
$activeTab = '';
$showNav   = false;
require basePath('src/views/layout.php');
?>

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(to bottom, #fff, #f5f5f9);padding:1rem;">
  <div x-data="loginPage()" style="width:100%;max-width:420px;">

    <div class="text-center mb-4">
      <img src="/images/logo-full.png" alt="I'KARANGÉ" style="width:120px;height:auto;margin-bottom:0.75rem;">
      <h2 style="font-weight:700;color:#1a3a3a;margin-bottom:0.25rem;">I'KARANG&Eacute;</h2>
      <p style="color:#888;font-size:0.85rem;">Gestion Assurance &amp; Mutuelle</p>
    </div>

    <div class="ik-card" style="padding:2rem;">
      <h4 style="text-align:center;font-weight:600;margin-bottom:0.25rem;">Connexion</h4>
      <p style="text-align:center;color:#888;font-size:0.82rem;margin-bottom:1.5rem;">
        Gestionnaire, prestataire ou adh&eacute;rent
      </p>

      <form @submit.prevent="submit()">
        <div class="mb-3">
          <label class="form-label">Identifiant</label>
          <input type="text" class="form-control" x-model="login" required autofocus placeholder="Votre login">
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
function loginPage() {
  return {
    login: '',
    password: '',
    showPwd: false,
    loading: false,
    error: '',

    async submit() {
      this.error = '';
      this.loading = true;
      try {
        const res = await api('/api/login', {
          method: 'POST',
          body: { login: this.login, password: this.password }
        });
        window.location.href = res.redirect || '/';
      } catch (e) {
        this.error = e.error || 'Identifiants incorrects';
      }
      this.loading = false;
    }
  };
}
</script>

<?php require basePath('src/views/layout-bottom.php'); ?>
