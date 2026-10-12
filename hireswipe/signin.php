<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

if ($u = current_user()) redirect(next_screen($u));

$errors = [];
$notice = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
    if ($pass === '')                               $errors['password'] = 'Enter your password.';

    if (!$errors) {
        if ($email === DEMO_EMAIL && hash_equals(DEMO_PASS, $pass)) demo_login();

        $user = user_by_email($email);

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($user['locked_until']) - time()) / 60);
            $errors['form'] = "Too many failed attempts. Try again in $mins minute(s).";
        } else {
            // Always run a hash check so timing doesn't reveal whether the email exists
            $ok = password_verify($pass, $user['password_hash'] ?? password_hash('x', PASSWORD_DEFAULT));

            if ($user && $ok && $user['status'] === 'active') {
                user_update((int)$user['id'], ['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => date('Y-m-d H:i:s')]);
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$user['id'];
                redirect(next_screen($user));
            }

            if ($user) {
                $fails = $user['failed_login_count'] + 1;
                $lock  = $fails >= MAX_LOGIN_TRIES ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
                user_update((int)$user['id'], ['failed_login_count' => $lock ? 0 : $fails, 'locked_until' => $lock]);
            }
            $errors['form'] = 'Incorrect email or password.';   // same message either way
        }
    }
}

page_start('Sign in');
?>
  <h1>Welcome back</h1>
  <p class="sub">Sign in to continue to HireSwipe.</p>

  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>
  <?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

  <div class="demo">
    <div><b>Demo login</b><span>demo@hireswipe.com &nbsp;·&nbsp; Demo@1234</span></div>
    <button type="button" id="fill-demo">Fill in</button>
  </div>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" value="<?= old('email') ?>" autocomplete="email" required autofocus>
      <?= err($errors, 'email') ?>
    </label>
    <label>Password
      <div class="pw"><input type="password" name="password" autocomplete="current-password" required>
        <button type="button" data-toggle-pass>Show</button></div>
      <?= err($errors, 'password') ?>
    </label>
    <button class="btn" type="submit">Sign in</button>
  </form>

  <p class="foot">New to HireSwipe? <a href="signup.php">Create an account</a></p>
<?php page_end();
