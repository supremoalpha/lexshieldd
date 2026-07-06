<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pdo = lex_pdo();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lex_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = lex_sanitize_email($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid credentials.';
        } elseif ((int) $user['is_active'] !== 1) {
            $error = 'Account is inactive.';
        } elseif (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $error = 'Account locked due to failed attempts. Please try again later.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            lex_record_login_attempt($pdo, (int) $user['id'], false);
            lex_audit('failed_login', 'users', (string) $user['id'], (int) $user['id']);
            $error = 'Invalid credentials.';
        } else {
            lex_record_login_attempt($pdo, (int) $user['id'], true);
            lex_login_user($user);
            lex_audit('login', 'users', (string) $user['id'], (int) $user['id']);
            lex_notify((int) $user['id'], 'security', 'You have successfully logged in to LEXSHIELD.');
            header('Location: ../' . $user['role'] . '/index.php');
            exit;
        }
    }
}

lex_auth_page_header('Secure Login');
?>
<section class="auth-card auth-card-split">
  <div class="auth-copy auth-copy-dark">
    <a class="auth-home-link" href="<?= lex_e(lex_app_url('#home')) ?>" aria-label="Back to home" title="Back to home">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4.75 10.95 12 4.6l7.25 6.35v8.3a.75.75 0 0 1-.75.75h-4.25v-5.25h-4.5V20H5.5a.75.75 0 0 1-.75-.75v-8.3Z" fill="currentColor"/>
      </svg>
    </a>
    <h1>Secure access for legal teams and clients.</h1>
    <p>Sign in with session security, CSRF protection, and audit logging.</p>
    <ul class="feature-list">
      <li>Role-based portal access</li>
      <li>Encrypted case documents</li>
      <li>Full auditability</li>
    </ul>
  </div>
  <div class="auth-panel auth-panel-form">
    <div class="auth-switch" aria-label="Authentication pages">
      <span class="auth-switch__item is-active">Sign in</span>
      <a class="auth-switch__item" href="register.php">New client</a>
    </div>
    <h2>Sign in</h2>
    <p class="muted auth-subtitle">Use your approved account to continue.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= lex_e($error) ?></div><?php endif; ?>
    <form method="post" class="stack-form" autocomplete="off">
      <?= lex_csrf_field() ?>
      <label>Email
        <input type="email" name="email" required placeholder="user@gmail.com">
      </label>
      <label>Password
        <div class="password-field" data-password-toggle>
          <input type="password" name="password" required placeholder="Enter your password">
          <button type="button" class="password-toggle" data-password-toggle-button aria-pressed="false" aria-label="Show password" title="Show password"><span class="sr-only">Show password</span></button>
        </div>
      </label>
      <button class="button button-primary auth-submit" type="submit">Login</button>
    </form>
    <p class="muted auth-footnote">New client? <a href="register.php">Create an account</a></p>
  </div>
</section>
<?php lex_auth_page_footer(); ?>
