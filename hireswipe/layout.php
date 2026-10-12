<?php
function page_start(string $title, ?int $step = null, bool $wide = false): void
{
    $labels = [1 => 'Create your account', 2 => 'Choose your role', 3 => 'Set up your profile']; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · HireSwipe</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=DM+Sans:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="shell">
  <aside class="promo">
    <a class="brand" href="index.php">Hire<b>Swipe</b></a>
    <div class="promo-copy">
      <h2>Swipe right on the job that fits.</h2>
      <p>Employers post a role. Job seekers build a profile. Both sides only see matches worth a conversation.</p>
    </div>
    <div class="deck" aria-hidden="true">
      <div class="pcard job c3"><small>Hiring · Pune</small><h3>Frontend engineer</h3><p>₹12–16 L · Full-time</p></div>
      <div class="pcard job c2"><small>Hiring · Bengaluru</small><h3>Product designer</h3><p>₹14–18 L · Full-time</p></div>
      <div class="pcard c1"><small>Looking for work · Ahmedabad</small><h3>Priya Shah</h3><p>UI designer · 4 years</p>
        <div class="tags"><span>Figma</span><span>Design systems</span><span>Fintech</span></div></div>
    </div>
  </aside>
  <main class="pane">
    <div class="pane-in <?= $wide ? 'wide' : '' ?>">
      <?php if ($step): ?>
        <div class="progress" role="progressbar" aria-valuemin="1" aria-valuemax="3" aria-valuenow="<?= $step ?>">
          <div class="bar"><i style="width:<?= round($step / 3 * 100) ?>%"></i></div>
          <p>Step <?= $step ?> of 3 · <?= $labels[$step] ?></p>
        </div>
      <?php endif; ?>
<?php }

function page_end(): void { ?>
    </div>
  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
<?php }
