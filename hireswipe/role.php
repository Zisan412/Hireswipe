<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = require_login();
if ($user['onboarding_status'] === 'completed') redirect('dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $role = $_POST['role'] ?? '';
    if (!in_array($role, ['employee', 'employer'], true)) {
        $error = 'Please choose one option to continue.';
    } else {
        user_update((int)$user['id'], ['role' => $role, 'onboarding_status' => 'pending_profile']);
        redirect($role === 'employee' ? 'employee_setup.php' : 'employer_setup.php');
    }
}
$current = $user['role'] ?? '';

page_start('Choose your role', 2);
?>
  <h1>Hi <?= e(explode(' ', $user['name'])[0]) ?>, what brings you here?</h1>
  <p class="sub">Pick one. You can change this until you finish your profile.</p>
  <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <div class="choices">
      <label class="choice choice-employee">
        <input type="radio" name="role" value="employee" <?= $current === 'employee' ? 'checked' : '' ?> required>
        <span class="ico" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></span><span><b>I'm looking for work</b><small>Build your profile, upload your CV and get matched with jobs.</small></span>
      </label>
      <label class="choice choice-employer">
        <input type="radio" name="role" value="employer" <?= $current === 'employer' ? 'checked' : '' ?> required>
        <span class="ico" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5l9-2v18M13 9h7v12M8 8h1M8 12h1M8 16h1M16 13h1M16 17h1M2 21h20"/></svg></span><span><b>I'm hiring</b><small>Set up your company and post a role to find candidates.</small></span>
      </label>
    </div>
    <button class="btn" type="submit">Continue</button>
  </form>
  <form method="post" action="logout.php" class="foot"><?= csrf_field() ?><button class="link" type="submit">Sign out</button></form>
<?php page_end();
