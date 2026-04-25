<?php
// views/auth/install.php
$pageTitle = 'Installation — ProClasse';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/app.css">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100dvh;
      background: var(--bg);
    }
    .install-card {
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-md);
      padding: var(--space-10) var(--space-8);
      width: 100%;
      max-width: 440px;
    }
    .install-header {
      text-align: center;
      margin-bottom: var(--space-8);
    }
    .install-header svg { color: var(--primary); margin: 0 auto var(--space-3); }
    .install-header h1 { font-size: var(--text-lg); font-weight: 700; }
    .install-header p  { font-size: var(--text-sm); color: var(--text-muted); margin-top: var(--space-1); }
    .alert {
      padding: var(--space-3) var(--space-4);
      border-radius: var(--radius-md);
      font-size: var(--text-sm);
      margin-bottom: var(--space-5);
      line-height: 1.5;
    }
    .alert a { color: inherit; font-weight: 600; }
    .alert-error   { background: var(--danger-light);  color: var(--danger);  }
    .alert-success { background: var(--primary-light); color: var(--primary); }
    .alert-warning {
      background: oklch(from var(--danger) l c h / .08);
      color: var(--text-muted);
    }
    .install-hint {
      margin-top: var(--space-6);
      padding: var(--space-3) var(--space-4);
      background: var(--primary-light);
      border-radius: var(--radius-md);
      font-size: var(--text-xs);
      color: var(--primary);
      line-height: 1.6;
    }
  </style>
</head>
<body>
  <div class="install-card">
    <div class="install-header">
      <svg width="40" height="40" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="currentColor" opacity=".12"/>
        <path d="M8 22 L16 10 L24 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M11 18 L21 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h1>Installation de ProClasse</h1>
      <p>Créez votre compte administrateur pour commencer.</p>
    </div>

    <?php if ($type === 'already'): ?>
      <div class="alert alert-warning"><?= $message ?></div>

    <?php elseif ($type === 'error' && str_contains((string)$message, 'table')): ?>
      <div class="alert alert-error"><?= $message ?></div>

    <?php elseif ($type === 'success'): ?>
      <div class="alert alert-success"><?= $message ?></div>

    <?php else: ?>

      <?php if ($type === 'error'): ?>
        <div class="alert alert-error"><?= htmlspecialchars($message ?? '') ?></div>
      <?php endif ?>

      <form method="POST" action="/install">
        <div class="form-group">
          <label for="email">Adresse email admin</label>
          <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
            placeholder="admin@etablissement.fr"
            required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label for="password">Mot de passe <span class="form-hint">(8 caractères min.)</span></label>
          <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
            placeholder="••••••••"
            required
            minlength="8"
          >
        </div>

        <div class="form-group">
          <label for="confirm">Confirmer le mot de passe</label>
          <input
            type="password"
            id="confirm"
            name="confirm"
            autocomplete="new-password"
            placeholder="••••••••"
            required
            minlength="8"
          >
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:var(--space-2)">
          Créer le compte admin
        </button>
      </form>

      <div class="install-hint">
        ⚠️ Pensez à désactiver cette page après l'installation.<br>
        Ajoutez dans <code>.htaccess</code> : <code>RedirectMatch 403 ^/install$</code>
      </div>

    <?php endif ?>
  </div>

  <script>
    (function(){
      if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
</body>
</html>
