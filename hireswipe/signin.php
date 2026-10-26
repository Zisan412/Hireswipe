<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/demo_data.php';   // demo accounts (prototype only)

if ($u = current_user()) redirect(next_screen($u));

try { demo_seed(); } catch (Throwable $ex) { /* store not writable: ignore here, real pages will show the message */ }

$errors = [];
$notice = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // One-click demo buttons: sign in straight to the Home screen
    if (in_array($_POST['demo'] ?? '', ['employee', 'employer'], true)) demo_login_as($_POST['demo']);

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

  <style>

.demo2{margin-top:22px;padding:14px;border-radius:12px;background:var(--amber-t);display:grid;gap:8px}
.demo2 p{margin:0 0 2px;font-size:13.5px;color:#6B4A00}.demo2 code{background:#fff;padding:1px 6px;border-radius:6px}
.demo2 button{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 14px;border:0;border-radius:10px;background:#fff;color:var(--ink);font:inherit;cursor:pointer;box-shadow:0 0 0 1.5px rgba(154,91,0,.3);text-align:left}
.demo2 button:hover{background:#FFF9EC}.demo2 button span{color:var(--mute);font-size:13px}
</style>
  <form method="post" class="demo2">
    <?= csrf_field() ?>
    <p><b>Quick demo</b> · every profile field is pre-filled · password <code>Demo@1234</code></p>
    <button type="submit" name="demo" value="employee"><b>Job seeker</b><span>employee@hireswipe.com</span></button>
    <button type="submit" name="demo" value="employer"><b>Employer</b><span>employer@hireswipe.com</span></button>
  </form>

  <p class="foot">New to HireSwipe? <a href="signup.php">Create an account</a></p>
<?php page_end();
