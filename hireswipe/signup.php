<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

if ($u = current_user()) redirect(next_screen($u));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $cc      = $_POST['country_code'] ?? '+91';
    $mobile  = preg_replace('/[\s-]/', '', $_POST['mobile'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[\p{L} .\'-]{2,100}$/u', $name))          $errors['name'] = 'Enter your full name (letters only, 2–100 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) $errors['email'] = 'Enter a valid email address.';
    if (!isset(COUNTRY_CODES[$cc]))                              $errors['mobile'] = 'Choose a country code.';
    elseif (!valid_mobile($cc, $mobile))                         $errors['mobile'] = $cc === '+91' ? 'Enter a valid 10-digit mobile number.' : 'Enter a valid mobile number.';
    if ($p = password_problem($pass))                            $errors['password'] = $p;
    if ($confirm !== $pass)                                      $errors['confirm_password'] = 'Passwords do not match.';
    if (empty($_POST['terms']))                                  $errors['terms'] = 'Please accept the terms to continue.';

    if (!isset($errors['email'])) {
        if (user_by_email($email)) $errors['email'] = 'This email is already registered. Try signing in.';
    }
    if (!isset($errors['mobile'])) {
        if (user_by_mobile($cc, $mobile)) $errors['mobile'] = 'This mobile number is already registered.';
    }

    if (!$errors) {
        try {
            $new = user_create(['name' => $name, 'email' => $email, 'country_code' => $cc, 'mobile' => $mobile,
                                'password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$new['id'];
            redirect('role.php');
        } catch (RuntimeException $ex) {
            $errors['form'] = $ex->getMessage();
        }
    }
}

page_start('Create account', 1);
?>
  <h1>Create your account</h1>
  <p class="sub">It takes less than a minute.</p>
  <?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Full name
      <input type="text" name="name" value="<?= old('name') ?>" maxlength="100" autocomplete="name" required>
      <?= err($errors, 'name') ?>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= old('email') ?>" maxlength="255" autocomplete="email" required>
      <?= err($errors, 'email') ?>
    </label>
    <label>Mobile number
      <div class="row">
        <select name="country_code" class="cc">
          <?php foreach (COUNTRY_CODES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= selected('country_code', $code, '+91') ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="tel" name="mobile" value="<?= old('mobile') ?>" inputmode="numeric" autocomplete="tel-national" required>
      </div>
      <?= err($errors, 'mobile') ?>
    </label>
    <label>New password
      <div class="pw"><input type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        <button type="button" data-toggle-pass>Show</button></div>
      <ul id="pw-rules" class="rules">
        <li data-rule="len">8+ characters</li><li data-rule="case">Upper &amp; lower case</li>
        <li data-rule="num">A number</li><li data-rule="sym">A special character</li>
      </ul>
      <?= err($errors, 'password') ?>
    </label>
    <label>Confirm password
      <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
      <?= err($errors, 'confirm_password') ?>
    </label>
    <label class="check">
      <input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
      <span>I agree to the Terms of Service and Privacy Policy</span>
    </label>
    <?= err($errors, 'terms') ?>
    <button class="btn" type="submit">Create account</button>
  </form>

  <p class="foot">Already have an account? <a href="signin.php">Sign in</a></p>
<?php page_end();
