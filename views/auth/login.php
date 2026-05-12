<?php
// views/auth/login.php
$pageTitle = 'Connexion — ProClasse';
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
  <link rel="stylesheet" href="/css/login.css">
</head>
<body>
  <div class="login-card">
    <div class="login-logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-label="ProClasse">
        <rect width="32" height="32" rx="8" fill="currentColor" opacity=".12"/>
        <path d="M8 22 L16 10 L24 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M11 18 L21 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>ProClasse</span>
    </div>

    <p class="login-title">Connexion à votre espace</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif ?>

    <form method="POST" action="/login" autocomplete="on">
      <div class="form-group">
        <label for="email">Adresse email</label>
        <input
          type="email"
          id="email"
          name="email"
          autocomplete="email"
          placeholder="vous@etablissement.fr"
          required
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
        >
      </div>

      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          placeholder="••••••••"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        Se connecter
      </button>
    </form>

    <p class="login-footer">ProClasse &mdash; gestion de salle de classe</p>
  </div>
  <script src="/js/login-theme.js"></script>
</body>
</html>
