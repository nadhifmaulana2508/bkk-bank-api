<?php
$u=current_user();
$currentPage=basename(parse_url($_SERVER['REQUEST_URI']??'', PHP_URL_PATH) ?: 'dashboard.php');
$initial=strtoupper(substr((string)($u['full_name']??'P'),0,1));
?>
<header class="topbar">
  <a class="brand" href="<?=e(base_url('dashboard.php'))?>" aria-label="TTD IN BKK - Dashboard">
    <span class="brandmark"><img src="<?=e(base_url('img/logo.png'))?>" alt="Logo BKK"></span>
    <span class="brand-name">TTD IN BKK<small>TTD Digital Terpusat</small></span>
  </a>
  <button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="mainNav">
    <span></span><span></span><span></span><span class="sr-only">Buka menu</span>
  </button>
  <nav class="navlinks" id="mainNav" aria-label="Navigasi utama">
    <a class="<?= $currentPage==='dashboard.php'?'active':'' ?>" href="<?=e(base_url('dashboard.php'))?>">Dashboard</a>
    <a class="<?= $currentPage==='signature.php'?'active':'' ?>" href="<?=e(base_url('signature.php'))?>">TTD Saya</a>
    <a class="nav-cta <?= $currentPage==='documents.php'?'active':'' ?>" href="<?=e(base_url('documents.php'))?>">Coba TTD Surat</a>
    <a class="<?= $currentPage==='activity.php'?'active':'' ?>" href="<?=e(base_url('activity.php'))?>">Aktivitas</a>
  </nav>
  <div class="user">
    <div class="avatar"><?=e($initial)?></div>
    <div class="user-details"><b><?=e($u['full_name']??'-')?></b><div class="muted user-role"><?=e($u['job_position']??'-')?></div></div>
    <a class="btn btn-soft btn-logout" href="<?=e(base_url('logout.php'))?>">Keluar</a>
  </div>
</header>
<script>
(() => {
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('mainNav');
  if (!toggle || !nav) return;
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  nav.addEventListener('click', (event) => {
    if (event.target.closest('a')) {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
