<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = require_login();
if ($user['onboarding_status'] !== 'completed') redirect(next_screen($user));

$table = $user['role'] === 'employer' ? 'employer' : 'employee';
$p = profile_get($table, (int)$user['id']) ?? [];

page_start('Dashboard');
?>
  <h1>You're all set, <?= e(explode(' ', $user['name'])[0]) ?> 🎉</h1>
  <p class="sub">Your <?= $user['role'] === 'employer' ? 'company' : 'profile' ?> is ready. Swiping comes next.</p>

  <?php if ($user['role'] === 'employee' && $p): ?>
    <?php $age = (new DateTime($p['dob']))->diff(new DateTime('today'))->y; ?>
    <dl class="summary">
      <dt>Name</dt><dd><?= e($p['name']) ?>, <?= $age ?></dd>
      <dt>Location</dt><dd><?= e($p['location']) ?></dd>
      <dt>Qualification</dt><dd><?= e($p['qualification']) ?></dd>
      <dt>Experience</dt><dd><?= e((string)$p['experience_years']) ?> years</dd>
      <dt>Expected salary</dt><dd><?= e($p['salary_currency']) ?> <?= number_format((float)$p['expected_salary']) ?></dd>
      <dt>Industries</dt><dd><?= e(implode(', ', array_map(fn($k) => INDUSTRIES[$k] ?? $k, json_decode($p['industries'] ?? '[]', true)))) ?></dd>
    </dl>
  <?php elseif ($p): ?>
    <dl class="summary">
      <dt>Company</dt><dd><?= e($p['company']) ?></dd>
      <dt>Location</dt><dd><?= e($p['location']) ?></dd>
      <dt>Hiring for</dt><dd><?= e($p['position']) ?> (<?= e(EMPLOYER_EMPLOYMENT_TYPES[$p['employment_type']] ?? '') ?>)</dd>
      <dt>Salary</dt><dd><?= e($p['salary_currency']) ?> <?= number_format((float)$p['salary']) ?></dd>
    </dl>
  <?php endif; ?>

  <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn ghost" type="submit">Sign out</button></form>
<?php page_end();
