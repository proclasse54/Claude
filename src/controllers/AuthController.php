<?php
// src/controllers/AuthController.php
// ============================================================
//  Gère : GET /login  POST /login  GET /logout  GET /install
// ============================================================
class AuthController
{
    // ── GET /login ───────────────────────────────────────────
    public function loginForm(): void
    {
        if (Auth::user()) {
            Response::redirect('/');
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        require ROOT . '/views/auth/login.php';
    }

    // ── POST /login ──────────────────────────────────────────
    public function loginSubmit(): void
    {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $_SESSION['login_error'] = 'Veuillez remplir tous les champs.';
            Response::redirect('/login');
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Logger::warning('auth', 'login_failed', ['email' => $email]);
            $_SESSION['login_error'] = 'Email ou mot de passe incorrect.';
            Response::redirect('/login');
        }

        // Succès : on ouvre la session
        Auth::login((int) $user['id'], $user['role']);
        Logger::info('auth', 'login_ok', ['email' => $email]);

        $redirect = $_SESSION['after_login'] ?? '/';
        unset($_SESSION['after_login']);
        Response::redirect($redirect);
    }

    // ── GET /logout ──────────────────────────────────────────
    public function logout(): void
    {
        Logger::info('auth', 'logout', ['user_id' => Auth::user()]);
        Auth::logout();
        Response::redirect('/login');
    }

    // ── GET /install ─────────────────────────────────────────
    // Crée le premier compte admin si aucun utilisateur n'existe encore.
    // À bloquer (ex : .htaccess ou config) une fois l'installation faite.
    public function install(): void
    {
        $pdo = Database::get();

        // Vérifie que la table users existe
        try {
            $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable $e) {
            $this->renderInstall('error', 'La table <code>users</code> n\'existe pas encore. Lancez d\'abord la migration SQL.');
            return;
        }

        if ((int) $count > 0) {
            $this->renderInstall('already', 'Un compte administrateur existe déjà. Cette page est désactivée.');
            return;
        }

        $message = null;
        $type    = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email']    ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm  = trim($_POST['confirm']  ?? '');

            if ($email === '' || $password === '' || $confirm === '') {
                $message = 'Tous les champs sont obligatoires.';
                $type    = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Adresse email invalide.';
                $type    = 'error';
            } elseif (strlen($password) < 8) {
                $message = 'Le mot de passe doit faire au moins 8 caractères.';
                $type    = 'error';
            } elseif ($password !== $confirm) {
                $message = 'Les mots de passe ne correspondent pas.';
                $type    = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare(
                    'INSERT INTO users (email, password_hash, role, is_active, created_at)
                     VALUES (?, ?, \'admin\', 1, NOW())'
                )->execute([$email, $hash]);
                Logger::info('install', 'admin_created', ['email' => $email]);
                $message = 'Compte admin créé ! Vous pouvez maintenant <a href="/login">vous connecter</a>.';
                $type    = 'success';
            }
        }

        $this->renderInstall($type, $message);
    }

    // ── Helpers privés ───────────────────────────────────────
    private function renderInstall(?string $type, ?string $message): void
    {
        require ROOT . '/views/auth/install.php';
    }
}
