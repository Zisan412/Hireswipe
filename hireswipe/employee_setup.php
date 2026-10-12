<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

$user = require_login();
if ($user['onboarding_status'] === 'completed') redirect('dashboard.php');
if ($user['role'] !== 'employee') redirect(next_screen($user));

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name   = trim($_POST['name'] ?? '');
    $dob    = $_POST['dob'] ?? '';
    $loc    = trim($_POST['location'] ?? '');
    $nat    = trim($_POST['nationality'] ?? '');
    $qual   = trim($_POST['qualification'] ?? '');
    $exp    = $_POST['experience_years'] ?? '';
    $salary = trim($_POST['expected_salary'] ?? '');
    $cur    = $_POST['salary_currency'] ?? 'INR';
    $type   = $_POST['employment_type'] ?? '';
    $age    = null;
    $inds   = array_values(array_intersect((array)($_POST['industries'] ?? []), array_keys(INDUSTRIES)));

    if (!preg_match('/^[\p{L} .\'-]{2,100}$/u', $name)) $errors['name'] = 'Enter your full name.';

    $d = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob)              $errors['dob'] = 'Enter a valid date of birth.';
    else {
        $age = $d->diff(new DateTime('today'))->y;
        if ($d > new DateTime('today') || $age < 16)      { $errors['dob'] = 'You must be at least 16 years old.'; $age = null; }
        elseif ($age > 80)                                { $errors['dob'] = 'Please check your date of birth.'; $age = null; }
    }
    if (mb_strlen($loc) < 2 || mb_strlen($loc) > 150)     $errors['location'] = 'Enter your city and country (e.g. Ahmedabad, India).';
    if (!preg_match('/^[\p{L} .\'-]{2,80}$/u', $nat))     $errors['nationality'] = 'Enter your nationality.';
    if (mb_strlen($qual) < 2 || mb_strlen($qual) > 150)   $errors['qualification'] = 'Enter your highest qualification.';
    if (!ctype_digit((string)$exp) || (int)$exp > 60)     $errors['experience_years'] = 'Enter valid experience (whole years).';
    elseif ($age !== null && (int)$exp >= $age)           $errors['experience_years'] = 'Experience should be less than age.';
    if (!valid_money($salary))                            $errors['expected_salary'] = 'Enter a valid expected salary.';
    if (!isset(CURRENCIES[$cur]))                         $errors['expected_salary'] = 'Choose a currency.';
    if (!isset(SEEKER_EMPLOYMENT_TYPES[$type]))                  $errors['employment_type'] = 'Choose an employment type.';
    if (count($inds) < 1)                                 $errors['industries'] = 'Select at least one industry.';

    [$picErr, $picMime] = check_upload('picture', IMAGE_TYPES, 2 * 1048576, true, 'Upload your picture.');
    [$cvErr,  $cvMime ] = check_upload('cv', CV_TYPES, 5 * 1048576, true, 'Upload your CV.');
    if ($picErr) $errors['picture'] = $picErr;
    if ($cvErr)  $errors['cv'] = $cvErr;

    if (!$errors) {
        $saved = [];
        try {
            $pic = $saved[] = store_upload('picture', $picMime, IMAGE_TYPES, 'pictures');
            $cv  = $saved[] = store_upload('cv', $cvMime, CV_TYPES, 'cv');
            $cvName = mb_substr(basename($_FILES['cv']['name']), 0, 255);

            profile_save('employee', (int)$user['id'], [
                'name' => $name, 'dob' => $dob, 'location' => $loc, 'nationality' => $nat, 'qualification' => $qual,
                'experience_years' => $exp, 'expected_salary' => $salary, 'salary_currency' => $cur,
                'employment_type' => $type, 'industries' => json_encode($inds),
                'picture_path' => $pic, 'cv_path' => $cv, 'cv_original_name' => $cvName]);
            user_update((int)$user['id'], ['onboarding_status' => 'completed']);
            redirect('dashboard.php');
        } catch (Throwable $ex) {
            foreach ($saved as $f) @unlink(UPLOAD_DIR . '/' . $f);
            $errors['form'] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Something went wrong while saving. Please try again.';
        }
    }
}
$postedInds = (array)($_POST['industries'] ?? []);

page_start('Your profile', 3, true);
?>
  <h1>Tell us about yourself</h1>
  <p class="sub">This helps employers find you. <a href="role.php">Not a job seeker?</a></p>
  <?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" novalidate class="grid">
    <?= csrf_field() ?>
    <label>Full name
      <input type="text" name="name" value="<?= old('name', $user['name']) ?>" maxlength="100" required>
      <?= err($errors, 'name') ?>
    </label>
    <label>Date of birth
      <input type="date" name="dob" value="<?= old('dob') ?>" max="<?= date('Y-m-d', strtotime('-16 years')) ?>" required>
      <?= err($errors, 'dob') ?>
    </label>
    <label>Current location
      <input type="text" name="location" value="<?= old('location') ?>" maxlength="150" placeholder="City, Country" required>
      <?= err($errors, 'location') ?>
    </label>
    <label>Nationality
      <input type="text" name="nationality" value="<?= old('nationality') ?>" maxlength="80" required>
      <?= err($errors, 'nationality') ?>
    </label>
    <label>Highest qualification
      <input type="text" name="qualification" value="<?= old('qualification') ?>" maxlength="150" placeholder="e.g. B.Tech Computer Science" required>
      <?= err($errors, 'qualification') ?>
    </label>
    <label>Experience (years)
      <input type="number" name="experience_years" value="<?= old('experience_years') ?>" min="0" max="60" step="1" required>
      <?= err($errors, 'experience_years') ?>
    </label>
    <label>Expected salary (per year)
      <div class="row">
        <select name="salary_currency" class="cc">
          <?php foreach (CURRENCIES as $c => $l): ?><option value="<?= $c ?>" <?= selected('salary_currency', $c, 'INR') ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="expected_salary" value="<?= old('expected_salary') ?>" min="1" step="any" required>
      </div>
      <?= err($errors, 'expected_salary') ?>
    </label>
    <label>Employment type
      <select name="employment_type" required>
        <option value="">Select…</option>
        <?php foreach (SEEKER_EMPLOYMENT_TYPES as $k => $l): ?><option value="<?= $k ?>" <?= selected('employment_type', $k) ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <?= err($errors, 'employment_type') ?>
    </label>

    <fieldset class="full">
      <legend>Preferred industries <small>(choose one or more)</small></legend>
      <div class="chips">
        <?php foreach (INDUSTRIES as $k => $l): ?>
          <label class="chip"><input type="checkbox" name="industries[]" value="<?= $k ?>" <?= in_array($k, $postedInds, true) ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?= err($errors, 'industries') ?>
    </fieldset>

    <label>Profile picture <small>(JPG or PNG · max 2 MB)</small>
      <input type="file" name="picture" accept="image/jpeg,image/png" required>
      <?= err($errors, 'picture') ?>
    </label>
    <label>CV / Resume <small>(PDF only · max 5 MB)</small>
      <input type="file" name="cv" accept=".pdf,application/pdf" required>
      <?= err($errors, 'cv') ?>
    </label>

    <button class="btn full" type="submit">Finish setup</button>
  </form>
<?php page_end();
