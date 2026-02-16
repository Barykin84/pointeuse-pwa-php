<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/app/session.php';
require_once __DIR__ . '/app/auth.php';
require_login();

$appName = 'Pointeuse PWA';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appName) ?></title>
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
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span class="navbar-text text-white small me-2">
          <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>
        </span>
        <?php if (($_SESSION['role'] ?? 'user') === 'admin'): ?>
          <a class="btn btn-sm btn-outline-light" href="/admin.php">Admin</a>
        <?php endif; ?>
        <button id="btn-logout" class="btn btn-sm btn-light">Déconnexion</button>
        <script>
          document.addEventListener('DOMContentLoaded',()=>{
            const b=document.getElementById('btn-logout');
            if(b){
              b.addEventListener('click', async ()=>{
                try {
                  await fetch('/api/logout.php', {method:'POST'});
                  location.href='/login.php';
                } catch(e){
                  location.href='/login.php';
                }
              });
            }
          });
        </script>
      <?php else: ?>
        <a class="btn btn-sm btn-light" href="/login.php">Connexion</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container py-4">

  <!-- Statut en cours -->
  <div class="status-card d-none" id="statusCard">
    <span class="me-2">⏱ En cours depuis <strong id="since"></strong></span>
    <button class="btn btn-sm btn-danger" id="statusStop">Départ</button>
  </div>

  <h1 class="h4 mb-3">Pointeuse</h1>

  <!-- Boutons desktop -->
  <div class="d-none d-sm-flex gap-2 mb-3">
    <button id="btn-arrive" class="btn btn-success">Arrivée</button>
    <button id="btn-depart" class="btn btn-danger">Départ</button>

    <div class="ms-auto d-flex align-items-center gap-2">
      <label class="form-label mb-0">Mois</label>
      <input type="month" id="month" class="form-control" style="width:180px;">
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>Heures du mois</span>
      <span>Total: <span id="total" class="badge text-bg-success">00:00</span></span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle" id="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>Début</th>
              <th>Fin</th>
              <th>Durée</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Boutons mobile -->
<div class="action-footer-mobile d-sm-none">
  <button class="btn btn-success btn-lg w-100 tap-big" id="btn-arrive-footer">Arrivée</button>
  <button class="btn btn-danger  btn-lg w-100 tap-big" id="btn-depart-footer">Départ</button>
</div>

<footer class="text-center text-muted py-4 small">
  <div>© <?= date('Y') ?> Pointeuse PWA · <a href="/offline.html">Mode hors-ligne</a></div>
</footer>

<script>
const IS_ADMIN = <?= (($_SESSION['role'] ?? 'user') === 'admin') ? 'true' : 'false' ?>;

/* ----------- UTILITAIRES DATES (sans new Date sur du FR) ---------- */

// db: "2025-11-14 09:30:00" -> "14/11/2025 09:30"
function dbToFr(dbStr) {
  if (!dbStr) return '';
  const [datePart, timePart] = dbStr.split(' ');
  if (!datePart || !timePart) return dbStr;
  const [y,m,d] = datePart.split('-');
  const [hh,mm] = timePart.split(':');
  return `${d}/${m}/${y} ${hh}:${mm}`;
}

// FR: "14/11/2025 09:30" -> "2025-11-14 09:30"
function parseFrenchDate(str) {
  const m = str.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/);
  if (!m) return null;
  let [_, d, mo, y, h, mi] = m;
  d  = d.padStart(2,'0');
  mo = mo.padStart(2,'0');
  h  = h.padStart(2,'0');
  return `${y}-${mo}-${d} ${h}:${mi}`;
}

// Calcul durée en secondes à partir de strings MySQL
function diffSeconds(startDb, endDb) {
  if (!startDb || !endDb) return 0;
  const s = Date.parse(startDb.replace(' ','T'));
  const e = Date.parse(endDb.replace(' ','T'));
  if (isNaN(s) || isNaN(e)) return 0;
  return Math.max(0, (e - s) / 1000);
}

/* ------------------------ VARIABLES UI ----------------------------- */
const monthInput = document.getElementById('month');
monthInput.value = new Date().toISOString().slice(0,7);

const statusCard = document.getElementById('statusCard');
const sinceEl = document.getElementById('since');
const statusStopBtn = document.getElementById('statusStop');

const btnArrive = document.getElementById('btn-arrive');
const btnDepart = document.getElementById('btn-depart');
const btnArriveFooter = document.getElementById('btn-arrive-footer');
const btnDepartFooter = document.getElementById('btn-depart-footer');

/* --------------------------- FONCTIONS ---------------------------- */
function guard(btn) {
  if (!btn || btn.disabled) return false;
  btn.disabled = true;
  setTimeout(()=> btn.disabled = false, 1200);
  return true;
}

function fmt(secs) {
  const h = Math.floor(secs/3600);
  const m = Math.floor((secs%3600)/60);
  return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
}

let openStartedAt = null;

function updateStatusBanner() {
  if (!openStartedAt) {
    statusCard.classList.add('d-none');
    return;
  }
  statusCard.classList.remove('d-none');
  // openStartedAt est au format DB, on affiche juste l'heure de début
  const [datePart, timePart] = openStartedAt.split(' ');
  const [hh,mm] = timePart.split(':');
  sinceEl.textContent = `${hh}:${mm}`;
}

/* ------------------------ CHARGEMENT MOIS ------------------------- */
async function loadMonth() {
  const ym = monthInput.value;
  const r = await fetch('/api/time.php?month='+encodeURIComponent(ym));
  const j = await r.json();

  const tb = document.querySelector('#tbl tbody');
  tb.innerHTML = '';

  let i=1;
  let latestOpen = null;
  let total = 0;

  (j.entries || []).forEach(row => {
    const startRaw = row.started_at;
    const endRaw   = row.ended_at;

    const startFr = dbToFr(startRaw);
    const endFr   = endRaw ? dbToFr(endRaw) : '';

    let dur = '—';
    if (endRaw) {
      const secs = diffSeconds(startRaw, endRaw);
      total += secs;
      dur = fmt(secs);
    } else {
      if (!latestOpen || startRaw > latestOpen.started_at) {
        latestOpen = row;
      }
    }

    const canEdit = endRaw && (row.editable || IS_ADMIN);
    const actionHtml = canEdit
      ? `<button class="btn btn-sm btn-outline-primary btn-edit"
                 data-id="${row.id}"
                 data-start-raw="${startRaw}"
                 data-end-raw="${endRaw || ''}">
            ✎
          </button>`
      : '';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i++}</td>
      <td>${startFr}</td>
      <td>${endFr || '<span class="text-danger">Ouverte…</span>'}</td>
      <td>${dur}</td>
      <td>${actionHtml}</td>
    `;
    tb.appendChild(tr);
  });

  document.getElementById('total').textContent = fmt(total);
  openStartedAt = latestOpen ? latestOpen.started_at : null;
  updateStatusBanner();

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => openEditDialog(btn.dataset.id, btn.dataset.startRaw, btn.dataset.endRaw));
  });
}

/* --------------------------- ACTIONS ------------------------------ */
async function actionArrive(btnRef) {
  if (!guard(btnRef)) return;
  const r = await fetch('/api/time.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'arrive'})
  });
  const j = await r.json();
  if (!j.ok) alert(j.error || 'Erreur');
  await loadMonth();
}

async function actionDepart(btnRef) {
  if (!guard(btnRef)) return;
  const r = await fetch('/api/time.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'depart'})
  });
  const j = await r.json();
  if (!j.ok) alert(j.error || 'Erreur');
  await loadMonth();
}

/* -------------------------- ÉDITION ------------------------------- */
async function openEditDialog(entryId, startRaw, endRaw) {
  if (!entryId || !startRaw || !endRaw) return;

  const startFrDefault = dbToFr(startRaw);  // JJ/MM/AAAA HH:MM
  const endFrDefault   = dbToFr(endRaw);

  const newStartFr = prompt('Nouveau début (JJ/MM/AAAA HH:MM)', startFrDefault);
  if (!newStartFr) return;

  const newEndFr = prompt('Nouvelle fin (JJ/MM/AAAA HH:MM)', endFrDefault);
  if (!newEndFr) return;

  const reason = prompt('Raison de la modification (obligatoire) :', '');
  if (!reason) {
    alert('La raison est obligatoire.');
    return;
  }

  const newStart = parseFrenchDate(newStartFr);
  const newEnd   = parseFrenchDate(newEndFr);

  if (!newStart || !newEnd) {
    alert("Format incorrect. Exemple attendu : 14/11/2025 09:30");
    return;
  }

  const r = await fetch('/api/time.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'update',
      entry_id: entryId,
      started_at: newStart,
      ended_at: newEnd,
      reason: reason
    })
  });
  const j = await r.json();
  if (!j.ok) {
    alert(j.error || 'Erreur lors de la mise à jour');
    return;
  }
  await loadMonth();
}

/* ------------------------- ÉVÈNEMENTS ----------------------------- */
if (btnArrive)       btnArrive.onclick       = ()=> actionArrive(btnArrive);
if (btnDepart)       btnDepart.onclick       = ()=> actionDepart(btnDepart);
if (btnArriveFooter) btnArriveFooter.onclick = ()=> actionArrive(btnArriveFooter);
if (btnDepartFooter) btnDepartFooter.onclick = ()=> actionDepart(btnDepartFooter);
if (statusStopBtn)   statusStopBtn.onclick   = ()=> actionDepart(statusStopBtn);

monthInput.addEventListener('change', loadMonth);
loadMonth();
</script>

</body>
</html>
