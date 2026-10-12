<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = require_login();
if ($user['onboarding_status'] === 'completed') redirect('dashboard.php');
if ($user['role'] !== 'employer') redirect(next_screen($user));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $company = trim($_POST['company'] ?? '');
    $loc     = trim($_POST['location'] ?? '');
    $ind     = $_POST['industry'] ?? '';
    $pos     = trim($_POST['position'] ?? '');
    $type    = $_POST['employment_type'] ?? '';
    $salary  = trim($_POST['salary'] ?? '');
    $cur     = $_POST['salary_currency'] ?? 'INR';
    $detail  = trim($_POST['job_detail'] ?? '');

    if (mb_strlen($company) < 2 || mb_strlen($company) > 150) $errors['company'] = 'Enter your company name.';
    if (mb_strlen($loc) < 2 || mb_strlen($loc) > 150)         $errors['location'] = 'Enter the company location (City, Country).';
    if (!isset(INDUSTRIES[$ind]))                             $errors['industry'] = 'Choose an industry.';
    if (mb_strlen($pos) < 2 || mb_strlen($pos) > 150)         $errors['position'] = 'Enter the position you are hiring for.';
    if (!isset(EMPLOYMENT_TYPES[$type]))                      $errors['employment_type'] = 'Choose an employment type.';
    if (!valid_money($salary))                                $errors['salary'] = 'Enter a valid salary.';
    if (!isset(CURRENCIES[$cur]))                             $errors['salary'] = 'Choose a currency.';
    if (mb_strlen($detail) < 30)                              $errors['job_detail'] = 'Please describe the job in at least 30 characters.';
    elseif (mb_strlen($detail) > 3000)                        $errors['job_detail'] = 'Job details are too long (max 3000 characters).';

    [$logoErr, $logoMime] = check_upload('logo', IMAGE_TYPES, 2 * 1048576, false);
    if ($logoErr) $errors['logo'] = $logoErr;

    if (!$errors) {
        $saved = [];
        try {
            $logo = $logoMime ? $saved[] = store_upload('logo', $logoMime, IMAGE_TYPES, 'logos') : null;

            profile_save('employer', (int)$user['id'], [
                'company' => $company, 'location' => $loc, 'industry' => $ind, 'position' => $pos,
                'employment_type' => $type, 'salary' => $salary, 'salary_currency' => $cur,
                'job_detail' => $detail, 'logo_path' => $logo]);
            user_update((int)$user['id'], ['onboarding_status' => 'completed']);
            redirect('dashboard.php');
        } catch (Throwable $ex) {
            foreach ($saved as $f) @unlink(UPLOAD_DIR . '/' . $f);
            $errors['form'] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Something went wrong while saving. Please try again.';
        }
    }
}

page_start('Company setup', 3, true);
?>
  <h1>Set up your company</h1>
  <p class="sub">Tell candidates who you are and who you're looking for. <a href="role.php">Not hiring?</a></p>
  <?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate class="grid">
    <?= csrf_field() ?>
    <label>Company name
      <input type="text" name="company" value="<?= old('company') ?>" maxlength="150" required>
      <?= err($errors, 'company') ?>
    </label>
    <label>Company location
      <input type="text" name="location" value="<?= old('location') ?>" maxlength="150" placeholder="City, Country" required>
      <?= err($errors, 'location') ?>
    </label>
    <label>Industry
      <select name="industry" required>
        <option value="">Select…</option>
        <?php foreach (INDUSTRIES as $k => $l): ?><option value="<?= $k ?>" <?= selected('industry', $k) ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <?= err($errors, 'industry') ?>
    </label>
    <label>Position you're hiring for
      <input type="text" name="position" value="<?= old('position') ?>" maxlength="150" placeholder="e.g. Senior PHP Developer" required>
      <?= err($errors, 'position') ?>
    </label>
    <label>Employment type
      <select name="employment_type" required>
        <option value="">Select…</option>
        <?php foreach (EMPLOYMENT_TYPES as $k => $l): ?><option value="<?= $k ?>" <?= selected('employment_type', $k) ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <?= err($errors, 'employment_type') ?>
    </label>
    <label>Offered salary (per year)
      <div class="row">
        <select name="salary_currency" class="cc">
          <?php foreach (CURRENCIES as $c => $l): ?><option value="<?= $c ?>" <?= selected('salary_currency', $c, 'INR') ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="salary" value="<?= old('salary') ?>" min="1" step="any" required>
      </div>
      <?= err($errors, 'salary') ?>
    </label>
    <label class="full">Job details
      <textarea name="job_detail" rows="6" minlength="30" maxlength="3000" placeholder="Responsibilities, requirements, perks…" required><?= old('job_detail') ?></textarea>
      <?= err($errors, 'job_detail') ?>
    </label>
    <label class="full">Company logo <small>(optional · JPG, PNG, WebP · max 2 MB)</small>
      <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
      <?= err($errors, 'logo') ?>
    </label>
    <button class="btn full" type="submit">Finish setup</button>
  </form>
<?php page_end();
