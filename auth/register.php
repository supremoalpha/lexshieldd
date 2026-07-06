<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pdo = lex_pdo();
$error = '';
$success = '';
$fullName = '';
$email = '';
$contactNumber = '';
$address = '';
$city = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lex_csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $fullName = lex_sanitize_text($_POST['full_name'] ?? '');
        $email = lex_sanitize_email($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $contactNumber = lex_sanitize_text($_POST['contact_number'] ?? '');
        $city = lex_sanitize_text($_POST['city'] ?? '');
        $address = $city;

        if (strlen($password) < 10) {
            $error = 'Password must be at least 10 characters.';
        } elseif ($fullName === '' || $email === '' || $contactNumber === '' || $city === '') {
            $error = 'Please complete all required fields.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn()) {
                $error = 'An account already exists with that email.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active, created_at) VALUES (:full_name, :email, :password_hash, "client", 1, NOW())');
                    $stmt->execute([
                        'full_name' => $fullName,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    ]);
                    $userId = (int) $pdo->lastInsertId();
                    $stmt = $pdo->prepare('INSERT INTO clients (user_id, contact_number, address, risk_level) VALUES (:user_id, :contact_number, :address, "low")');
                    $stmt->execute([
                        'user_id' => $userId,
                        'contact_number' => $contactNumber,
                        'address' => $address,
                    ]);
                    lex_audit('register', 'users', (string) $userId, $userId);
                    $pdo->commit();
                    $success = 'Your client account has been created. You can now sign in.';
                    $fullName = '';
                    $email = '';
                    $contactNumber = '';
                    $address = '';
                    $city = '';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

lex_auth_page_header('Client Registration');
?>
<section class="auth-card auth-card-split">
  <div class="auth-copy auth-copy-dark">
    <a class="auth-home-link" href="<?= lex_e(lex_app_url('#home')) ?>" aria-label="Back to home" title="Back to home">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4.75 10.95 12 4.6l7.25 6.35v8.3a.75.75 0 0 1-.75.75h-4.25v-5.25h-4.5V20H5.5a.75.75 0 0 1-.75-.75v-8.3Z" fill="currentColor"/>
      </svg>
    </a>
    <h1>Secure access for legal teams and clients.</h1>
    <p>Session security, CSRF protection, and full audit logging on every action.</p>
    <ul class="feature-list">
      <li>Role-based portal access</li>
      <li>Encrypted case documents</li>
      <li>Full auditability</li>
    </ul>
  </div>
  <div class="auth-panel auth-panel-form">
    <div class="auth-switch" aria-label="Authentication pages">
      <a class="auth-switch__item" href="login.php">Sign in</a>
      <span class="auth-switch__item is-active">New client</span>
    </div>
    <h2>Create client account</h2>
    <p class="muted auth-subtitle">Fill out the form below to get started.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= lex_e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= lex_e($success) ?></div><?php endif; ?>
    <form method="post" class="form-grid" autocomplete="off">
      <?= lex_csrf_field() ?>
      <label class="full">Full name
        <input type="text" name="full_name" required placeholder="FirstName LastName" value="<?= lex_e($fullName) ?>">
      </label>
      <label class="full">Email
        <input type="email" name="email" required placeholder="user@example.com" value="<?= lex_e($email) ?>">
      </label>
      <label>Contact number
        <input type="text" name="contact_number" required placeholder="09xxxxxxxxx" value="<?= lex_e($contactNumber) ?>">
      </label>
      <label>City
        <input type="text" name="city" required placeholder="IloIlo City" value="<?= lex_e($city !== '' ? $city : $address) ?>">
      </label>
      <label class="full">Password
        <div class="password-field" data-password-toggle>
          <input type="password" name="password" required minlength="10" placeholder="Create a password">
          <button type="button" class="password-toggle" data-password-toggle-button aria-pressed="false" aria-label="Show password" title="Show password"><span class="sr-only">Show password</span></button>
        </div>
      </label>
      <button class="button button-primary auth-submit full" type="submit">Create account</button>
    </form>
    <p class="muted auth-footnote">Already have access? <a href="login.php">Sign in</a></p>
  </div>
</section>
<?php lex_auth_page_footer(); ?>
