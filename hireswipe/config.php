<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

/* ---------- Settings ---------- */

const UPLOAD_DIR       = __DIR__ . '/uploads';
const MAX_LOGIN_TRIES  = 5;    // wrong passwords before lock
const LOCK_MINUTES     = 15;   // lock duration

// Employment types differ per role (requirements 9.2)
const EMPLOYER_EMPLOYMENT_TYPES = ['full_time' => 'Full time', 'part_time' => 'Part time', 'internship' => 'Internship'];
const SEEKER_EMPLOYMENT_TYPES   = ['full_time' => 'Full time', 'half_time' => 'Half time', 'casual' => 'Casual'];

// Industry reference list (requirements 9.1)
const INDUSTRIES = [
    'consulting' => 'Consulting & Strategy', 'design' => 'Design & Architecture', 'education' => 'Education & Training',
    'engineering' => 'Engineering', 'farming' => 'Farming, Animal & Conservation', 'government' => 'Government & Defence',
    'healthcare' => 'Healthcare & Medical', 'hospitality' => 'Hospitality & Tourism', 'hr' => 'Human Resources & Recruitment',
    'ict' => 'Information & Communication Technology', 'insurance' => 'Insurance & Superannuation', 'legal' => 'Legal',
    'manufacturing' => 'Manufacturing, Transport & Logistics', 'marketing' => 'Marketing & Communications',
    'mining' => 'Mining, Resources & Energy', 'real_estate' => 'Real Estate & Property',
    'retail' => 'Retail & Consumer Products', 'sales' => 'Sales',
];
const CURRENCIES = ['INR' => 'INR ₹', 'USD' => 'USD $', 'EUR' => 'EUR €', 'GBP' => 'GBP £', 'AED' => 'AED'];
const COUNTRY_CODES = [
    '+91' => 'India (+91)', '+1' => 'US/Canada (+1)', '+44' => 'UK (+44)',
    '+971' => 'UAE (+971)', '+65' => 'Singapore (+65)', '+61' => 'Australia (+61)',
];
const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];   // JPG or PNG only (C7)
const CV_TYPES    = ['application/pdf' => 'pdf'];   // CV must be a PDF (C8)

/* ---------- Session ---------- */
ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));   // stay signed in until sign out (A3)
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

const DEMO_EMAIL = 'demo@hireswipe.com';   // prototype only - remove before launch
const DEMO_PASS  = 'Demo@1234';

/* ---------- Prototype storage (JSON file; replaces the database for now) ---------- */
/** Uses data/store.json; if that folder is not writable, falls back to the system temp folder. */
function store_path(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/store.json';
    if (is_dir($dir) && (is_file($file) ? is_writable($file) : is_writable($dir))) return $file;
    return sys_get_temp_dir() . '/hireswipe_store.json';
}
function store_load(): array
{
    $blank = ['next_id' => 1, 'users' => [], 'employee' => [], 'employer' => []];
    $file = store_path();
    if (!is_file($file)) return $blank;
    return json_decode((string)file_get_contents($file), true) ?: $blank;
}
function store_save(array $s): void
{
    $ok = @file_put_contents(store_path(), json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($ok === false) throw new RuntimeException('We could not save your data. Please make the data/ folder writable and try again.');
}
function user_by_id(int $id): ?array { return store_load()['users'][$id] ?? null; }
function user_by_email(string $email): ?array
{
    foreach (store_load()['users'] as $u) if ($u['email'] === $email) return $u;
    return null;
}
function user_by_mobile(string $cc, string $m): ?array
{
    foreach (store_load()['users'] as $u) if ($u['country_code'] === $cc && $u['mobile'] === $m) return $u;
    return null;
}
function user_create(array $f): array
{
    $s = store_load(); $id = $s['next_id']++;
    $u = $f + ['id' => $id, 'role' => null, 'onboarding_status' => 'pending_role', 'status' => 'active',
               'failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => null, 'created_at' => date('Y-m-d H:i:s')];
    $s['users'][$id] = $u; store_save($s);
    return $u;
}
function user_update(int $id, array $f): void
{
    $s = store_load();
    if (isset($s['users'][$id])) { $s['users'][$id] = array_merge($s['users'][$id], $f); store_save($s); }
}
/** Dummy login: always restarts onboarding so the whole flow can be walked through again. */
function demo_login(): never
{
    $u = user_by_email(DEMO_EMAIL) ?? user_create([
        'name' => 'Demo User', 'email' => DEMO_EMAIL, 'country_code' => '+91', 'mobile' => '9000000000',
        'password_hash' => password_hash(DEMO_PASS, PASSWORD_DEFAULT)]);
    $s = store_load();
    unset($s['employee'][$u['id']], $s['employer'][$u['id']]);
    store_save($s);
    user_update((int)$u['id'], ['role' => null, 'onboarding_status' => 'pending_role', 'failed_login_count' => 0, 'locked_until' => null]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    redirect('role.php');
}
function profile_get(string $table, int $uid): ?array { return store_load()[$table][$uid] ?? null; }
function profile_save(string $table, int $uid, array $d): void
{
    $s = store_load(); $s[$table][$uid] = $d + ['user_id' => $uid]; store_save($s);
}

/* ---------- Helpers ---------- */
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old(string $k, string $default = ''): string { return e($_POST[$k] ?? $default); }
function selected(string $k, string $v, string $default = ''): string { return ($_POST[$k] ?? $default) === $v ? 'selected' : ''; }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function err(array $errors, string $k): string { return isset($errors[$k]) ? '<p class="err">' . e($errors[$k]) . '</p>' : ''; }

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_verify(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Auth ---------- */
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) return null;
    $u = user_by_id((int)$_SESSION['uid']);
    if (!$u || $u['status'] !== 'active') { $_SESSION = []; return null; }
    return $u;
}
function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('signin.php');
    return $u;
}
/** Decide which screen the user belongs on, based on onboarding progress. */
function next_screen(array $u): string
{
    if ($u['onboarding_status'] === 'completed') return 'dashboard.php';
    if ($u['role'] === null) return 'role.php';
    return $u['role'] === 'employee' ? 'employee_setup.php' : 'employer_setup.php';
}

/* ---------- Validation ---------- */
function password_problem(string $p): ?string
{
    if (strlen($p) < 8)  return 'Password must be at least 8 characters.';
    if (strlen($p) > 72) return 'Password must be 72 characters or fewer.';
    if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p)) return 'Use both uppercase and lowercase letters.';
    if (!preg_match('/\d/', $p))        return 'Include at least one number.';
    if (!preg_match('/[^a-zA-Z0-9]/', $p)) return 'Include at least one special character.';
    return null;
}
function valid_mobile(string $cc, string $m): bool
{
    if (!ctype_digit($m)) return false;
    if ($cc === '+91') return (bool)preg_match('/^[6-9]\d{9}$/', $m);
    return strlen($m) >= 6 && strlen($m) <= 14;
}
function valid_money(string $v): bool
{
    return is_numeric($v) && (float)$v > 0 && (float)$v <= 999999999;
}

/* ---------- File uploads ---------- */
/** @return array{0:?string,1:?string} [error, detected mime] */
function check_upload(string $field, array $allowed, int $maxBytes, bool $required, string $requiredMsg = 'This file is required.'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return [$required ? $requiredMsg : null, null];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) return ['File is too large.', null];
    if ($f['error'] !== UPLOAD_ERR_OK) return ['Upload failed. Please try again.', null];
    if ($f['size'] > $maxBytes) return ['File is too large (max ' . round($maxBytes / 1048576) . ' MB).', null];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) return ['Unsupported file type. Allowed: ' . implode(', ', array_unique($allowed)) . '.', null];
    if (str_starts_with($mime, 'image/') && @getimagesize($f['tmp_name']) === false) return ['That image looks corrupted.', null];
    return [null, $mime];
}
function store_upload(string $field, string $mime, array $allowed, string $subdir): string
{
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];   // never trust the client filename
    if (!is_dir($dir) || !@move_uploaded_file($_FILES[$field]['tmp_name'], "$dir/$name"))
        throw new RuntimeException('We could not save your file. Please make the uploads/ folder writable and try again.');
    return $subdir . '/' . $name;
}
