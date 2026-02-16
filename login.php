
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/app/session.php';
$appName = 'Pointeuse PWA';
if (!empty($_SESSION['user_id'])) { header('Location: /pointeuse.php'); exit; }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> · Connexion</title>
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
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4 mb-3">Connexion</h1>
          <form id="login-form" class="vstack gap-2">
            <input class="form-control" type="email" name="email" placeholder="Email" required>
            <input class="form-control" type="password" name="password" placeholder="Mot de passe" required>
            <button class="btn btn-primary w-100">Se connecter</button>
          </form>
          <div class="text-center mt-3">
            <a href="/register.php">Créer un compte</a>
          </div>
          <div id="login-msg" class="text-danger small mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('login-form').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const f = e.currentTarget;
  const data = { email: f.email.value.trim(), password: f.password.value };
  try {
    const r = await fetch('/api/login.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
    const j = await r.json();
    if (j.ok) { location.href='/pointeuse.php'; } else { document.getElementById('login-msg').textContent = j.error || 'Erreur'; }
  } catch(err) { document.getElementById('login-msg').textContent = 'Erreur réseau'; }
});
</script>
</body>
</html>
