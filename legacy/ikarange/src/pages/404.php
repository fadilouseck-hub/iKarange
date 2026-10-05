<?php
$pageTitle = 'Page introuvable';
$activeTab = '';
$showNav   = (bool)authUser();
require basePath('src/views/layout.php');
?>

<div style="text-align:center;padding:4rem 1rem;">
  <div style="font-size:5rem;margin-bottom:1rem;">&#x1F50D;</div>
  <h2 style="font-weight:700;color:#333;">Page introuvable</h2>
  <p style="color:#888;margin-bottom:2rem;">La page que vous recherchez n'existe pas ou a &eacute;t&eacute; d&eacute;plac&eacute;e.</p>
  <a href="/" class="btn btn-primary">
    <i class="bx bx-home me-1"></i> Retour au tableau de bord
  </a>
</div>

<?php require basePath('src/views/layout-bottom.php'); ?>
