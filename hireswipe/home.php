<?php
require __DIR__ . '/config.php';
require __DIR__ . '/demo_data.php';

$user = require_login();
if ($user['onboarding_status'] !== 'completed') redirect(next_screen($user));

$uid        = (int)$user['id'];
$isEmployer = $user['role'] === 'employer';
$p          = profile_get($isEmployer ? 'employer' : 'employee', $uid) ?? [];
$first      = explode(' ', trim($user['name']))[0];
csrf_field();   // makes sure $_SESSION['csrf'] exists

$all = $isEmployer ? demo_candidates() : demo_jobs();
$ids = array_column($all, 'id');

/* Swipe decisions are kept in the session for now (no database yet). */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'reset') { unset($_SESSION['swipes'][$uid]); redirect('home.php'); }
    if ($action === 'swipe') {
        $id  = (string)($_POST['id'] ?? '');
        $dir = (string)($_POST['dir'] ?? '');
        if (in_array($id, $ids, true) && in_array($dir, ['accept', 'reject'], true)) $_SESSION['swipes'][$uid][$id] = $dir;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$done     = $_SESSION['swipes'][$uid] ?? [];
$cards    = array_values(array_filter($all, fn($c) => !isset($done[$c['id']])));   // rejected/accepted never come back
$accepted = count(array_filter($done, fn($d) => $d === 'accept'));
$rejected = count($done) - $accepted;
$notes    = demo_notifications($isEmployer);
$unread   = count(array_filter($notes, fn($n) => $n['unread']));

$boot = [
    'csrf'   => $_SESSION['csrf'],
    'cards'  => $cards,
    'stats'  => ['accepted' => $accepted, 'rejected' => $rejected],
    'labels' => $isEmployer ? ['accept' => 'Shortlist', 'reject' => 'Pass'] : ['accept' => 'Accept', 'reject' => 'Reject'],
];

/* Profile rows for the Profile tab */
if ($isEmployer) {
    $rows = [
        'Company' => $p['company'] ?? '', 'Location' => $p['location'] ?? '',
        'Industry' => INDUSTRIES[$p['industry'] ?? ''] ?? '', 'Hiring for' => $p['position'] ?? '',
        'Employment type' => EMPLOYER_EMPLOYMENT_TYPES[$p['employment_type'] ?? ''] ?? '',
        'Salary' => ($p['salary_currency'] ?? '') . ' ' . number_format((float)($p['salary'] ?? 0)),
        'Job details' => $p['job_detail'] ?? '',
    ];
} else {
    $age  = isset($p['dob']) ? (new DateTime($p['dob']))->diff(new DateTime('today'))->y : '';
    $inds = array_map(fn($k) => INDUSTRIES[$k] ?? $k, json_decode($p['industries'] ?? '[]', true) ?: []);
    $rows = [
        'Name' => ($p['name'] ?? '') . ($age !== '' ? ", $age" : ''), 'Location' => $p['location'] ?? '',
        'Nationality' => $p['nationality'] ?? '', 'Qualification' => $p['qualification'] ?? '',
        'Experience' => ($p['experience_years'] ?? '0') . ' years',
        'Expected salary' => ($p['salary_currency'] ?? '') . ' ' . number_format((float)($p['expected_salary'] ?? 0)),
        'Employment type' => SEEKER_EMPLOYMENT_TYPES[$p['employment_type'] ?? ''] ?? '',
        'Industries' => implode(', ', $inds), 'CV' => $p['cv_original_name'] ?? '',
    ];
}
$ic = fn(string $d) => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$filterLocs = array_values(array_unique(array_filter(array_column($all, 'loc')))); sort($filterLocs);
$filterInds = array_values(array_unique(array_filter(array_column($all, 'ind')))); sort($filterInds);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home · HireSwipe</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=DM+Sans:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/home.css?v=<?= filemtime(__DIR__ . '/assets/home.css') ?>">
</head>
<body class="home">
<div class="app">
  <header class="top">
    <a class="brand" href="home.php">Hire<b>Swipe</b></a>
    <nav class="topnav" aria-label="Main">
      <button class="nl" type="button" data-tab="search"><?= $ic('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>') ?>Search</button>
      <div class="nwrap">
        <button class="nl" type="button" id="bell" aria-haspopup="true" aria-expanded="false"><?= $ic('<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>') ?>Notifications
          <?php if ($unread): ?><span class="badge" id="badge"><?= $unread ?></span><?php endif; ?></button>
        <div class="drawer" id="notif" aria-label="Notifications">
          <h2>Notifications</h2>
          <?php foreach ($notes as $n): ?>
            <div class="note <?= $n['unread'] ? 'unread' : '' ?>"><b><?= e($n['title']) ?></b><p><?= e($n['text']) ?></p><small><?= e($n['time']) ?></small></div>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="nl" type="button" data-tab="profile"><?= $ic('<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>') ?>Profile</button>
    </nav>
  </header>

  <main>
    <!-- HOME -->
    <section class="tab active" id="tab-home">
      <div class="swipe" id="deck"></div>
      <div class="actions" id="actions">
        <button class="act no" type="button" id="btn-reject"><span><?= $ic('<path d="M6 6l12 12M18 6L6 18"/>') ?></span><?= e($boot['labels']['reject']) ?></button>
        <button class="act yes" type="button" id="btn-accept"><span><?= $ic('<path d="M5 12.5l4.5 4.5L19 7.5"/>') ?></span><?= e($boot['labels']['accept']) ?></button>
      </div>
    </section>

    <!-- SEARCH / APPLY -->
    <section class="tab" id="tab-search">
      <h1><?= $isEmployer ? 'Search candidates' : 'Search jobs' ?></h1>
      <div class="filters">
        <div class="field grow"><span class="lbl">Search</span>
          <input type="text" id="q" placeholder="<?= $isEmployer ? 'Search by name or location…' : 'Search by job title, company or location…' ?>" autocomplete="off"></div>
        <div class="field"><span class="lbl">Location</span>
          <select id="f-loc" aria-label="Filter by location"><option value="">All locations</option>
            <?php foreach ($filterLocs as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="field"><span class="lbl">Industry</span>
          <select id="f-ind" aria-label="Filter by industry"><option value="">All industries</option>
            <?php foreach ($filterInds as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
        <button type="button" id="f-clear" class="clear">Clear</button>
      </div>
      <p class="count" id="count"></p>
      <div class="list" id="results"></div>
    </section>

    <!-- PROFILE -->
    <section class="tab" id="tab-profile">
      <h1><?= $isEmployer ? 'Company profile' : 'My profile' ?></h1>
      <dl class="summary">
        <?php foreach ($rows as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e((string)$v) ?></dd><?php endforeach; ?>
      </dl>
      <form method="post" action="home.php"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><button class="btn ghost" type="submit">Reset swiped cards (demo)</button></form>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn ghost" type="submit">Sign out</button></form>
    </section>
  </main>

  <div class="toast" id="toast" role="status"></div>
</div>
<script>window.HS = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/home.js?v=<?= filemtime(__DIR__ . '/assets/home.js') ?>"></script>
</body>
</html>