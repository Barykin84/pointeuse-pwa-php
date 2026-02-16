<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/app/session.php';
require_once __DIR__ . '/app/auth.php';
require_login(); 
require_admin();
$appName = 'Pointeuse PWA';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> · Admin</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#0d6efd">
  <link rel="icon" href="/icons/icon-192.png" sizes="192x192">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/styles.css" rel="stylesheet">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
      });
    }
  </script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="/pointeuse.php">Pointeuse</a>
    <div class="d-flex ms-auto align-items-center gap-2">
      <a class="btn btn-sm btn-outline-light" href="/pointeuse.php">Retour</a>
      <button id="btn-logout" class="btn btn-sm btn-light">Déconnexion</button>
    </div>
  </div>
</nav>
<div class="container py-4">

<h1 class="h4 mb-3">Admin · Rapport mensuel</h1>
<div class="d-flex gap-2 mb-3 align-items-end">
  <div>
    <label class="form-label mb-0">Mois</label>
    <input type="month" id="month" class="form-control" style="width:180px;">
  </div>
  <button id="btn-export" class="btn btn-outline-secondary">Télécharger (CSV)</button>
</div>

<div id="report"></div>

</div>
<footer class="text-center text-muted py-4 small">
  <div>© <?= date('Y') ?> Pointeuse PWA · <a href="/offline.html">Mode hors-ligne</a></div>
</footer>

<script>
document.getElementById('btn-export').addEventListener('click', ()=>{
  const ym = document.getElementById('month').value;
  window.location.href = '/api/export_csv.php?month=' + encodeURIComponent(ym);
});
document.addEventListener('DOMContentLoaded',()=>{
  const b=document.getElementById('btn-logout');
  if(b){ b.addEventListener('click', async ()=>{
    try { await fetch('/api/logout.php', {method:'POST'}); location.href='/login.php'; } catch(e){ location.href='/login.php'; }
  });}
});

const monthInput = document.getElementById('month');
monthInput.value = new Date().toISOString().slice(0,7);
const fmt = secs => {
  const h = Math.floor(secs/3600);
  const m = Math.floor((secs%3600)/60);
  return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0');
};

async function loadReport(){
  const ym = monthInput.value;
  const r = await fetch('/api/report.php?month='+encodeURIComponent(ym));
  const j = await r.json();
  const wrap = document.getElementById('report');
  wrap.innerHTML = '';
  if (!j.ok || !(j.data||[]).length){ wrap.innerHTML = '<em>Aucune donnée.</em>'; return; }
  for (const u of j.data){
    const card = document.createElement('div');
    card.className = 'card mb-3';
    const total = fmt(u.total_seconds||0);
    let rows = '';
    let i=1;
    for (const e of u.entries){
      let dur = '—';
      if (e.ended_at){ dur = fmt(Math.max(0,(new Date(e.ended_at)-new Date(e.started_at))/1000)); }
      rows += `<tr><td>${i++}</td><td>${new Date(e.started_at).toLocaleString('fr-FR')}</td><td>${e.ended_at ? new Date(e.ended_at).toLocaleString('fr-FR') : '<span class="text-danger">Ouverte…</span>'}</td><td>${dur}</td></tr>`;
    }
    card.innerHTML = `<div class="card-header d-flex justify-content-between"><strong>${u.name} &lt;${u.email}&gt;</strong><span>Total: <span class="badge text-bg-dark">${total}</span></span></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle"><thead><tr><th>#</th><th>Début</th><th>Fin</th><th>Durée</th></tr></thead><tbody>${rows}</tbody></table>
      </div>
    </div>`;
    wrap.appendChild(card);
  }
}
monthInput.addEventListener('change', loadReport);
loadReport();
</script>
</body>
</html>
