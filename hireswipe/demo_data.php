<?php
declare(strict_types=1);

/**
 * Demo accounts + dummy Home-screen data (prototype only - delete this file before launch).
 * Needs config.php to be loaded first (uses its store functions).
 *
 *   Employee login:  employee@hireswipe.com / Demo@1234   (Priya Shah, UI designer, profile complete)
 *   Employer login:  employer@hireswipe.com / Demo@1234   (BrightPath Technologies, role posted, profile complete)
 */

const DEMO_PASSWORD       = 'Demo@1234';
const DEMO_EMPLOYEE_EMAIL = 'employee@hireswipe.com';
const DEMO_EMPLOYER_EMAIL = 'employer@hireswipe.com';

/** Creates the two demo accounts (with every profile field filled) the first time it runs. */
function demo_seed(): void
{
    if (!user_by_email(DEMO_EMPLOYEE_EMAIL)) {
        $u = user_create([
            'name' => 'Priya Shah', 'email' => DEMO_EMPLOYEE_EMAIL, 'country_code' => '+91', 'mobile' => '9876543210',
            'password_hash' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
            'role' => 'employee', 'onboarding_status' => 'completed',
        ]);
        profile_save('employee', (int)$u['id'], [
            'name' => 'Priya Shah', 'dob' => '1998-04-12', 'location' => 'Ahmedabad, India', 'nationality' => 'Indian',
            'qualification' => 'B.Des Interaction Design', 'experience_years' => '4',
            'expected_salary' => '1400000', 'salary_currency' => 'INR', 'employment_type' => 'full_time',
            'industries' => json_encode(['design', 'ict', 'marketing']),
            'picture_path' => null, 'cv_path' => null, 'cv_original_name' => 'Priya_Shah_CV.pdf',
        ]);
    }
    if (!user_by_email(DEMO_EMPLOYER_EMAIL)) {
        $u = user_create([
            'name' => 'Rahul Verma', 'email' => DEMO_EMPLOYER_EMAIL, 'country_code' => '+91', 'mobile' => '9876543211',
            'password_hash' => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
            'role' => 'employer', 'onboarding_status' => 'completed',
        ]);
        profile_save('employer', (int)$u['id'], [
            'company' => 'BrightPath Technologies', 'location' => 'Pune, India', 'industry' => 'ict',
            'position' => 'Senior PHP Developer', 'employment_type' => 'full_time',
            'salary' => '1800000', 'salary_currency' => 'INR',
            'job_detail' => 'We are looking for a Senior PHP Developer to build and scale our hiring platform. '
                          . 'You will design APIs, own the MySQL schema, review code and mentor two junior developers. '
                          . 'Perks: hybrid work, health cover, learning budget.',
            'logo_path' => null,
        ]);
    }
}

/** One-click sign in used by the demo buttons on signin.php. */
function demo_login_as(string $role): never
{
    demo_seed();
    $u = user_by_email($role === 'employer' ? DEMO_EMPLOYER_EMAIL : DEMO_EMPLOYEE_EMAIL);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    redirect('home.php');
}

/* ---------- Dummy cards for the Home screen ---------- */

/** Job cards shown to the job seeker (spec 6.4: logo, company, title, type, experience, salary + details). */
function demo_jobs(): array
{
    $j = fn(string $id, string $tile, string $color, string $company, string $title, string $type, string $exp,
            string $salary, string $desc, string $addr, string $workplace, string $jobType) => [
        'id' => $id, 'tile' => $tile, 'color' => $color, 'kicker' => $company, 'title' => $title,
        'facts' => [['Type', $type], ['Experience', $exp], ['Salary', $salary]],
        'details' => [['Description', $desc], ['Address', $addr], ['Workplace', $workplace], ['Job type', $jobType]],
    ];
    $jobs = [
        $j('j1', 'AT', '#2F5BFF', 'Acme Technologies', 'Frontend Engineer', 'Full time', '3–5 years', '₹12–16 L / year',
           'Build fast, accessible interfaces for our SaaS dashboard with React and a shared design system.', 'Baner, Pune, India', 'Hybrid', 'Permanent'),
        $j('j2', 'ND', '#7A3FF2', 'Nimbus Design Studio', 'Product Designer', 'Full time', '2–4 years', '₹9–13 L / year',
           'Own end-to-end product design for fintech clients: research, flows, prototypes and handoff.', 'Indiranagar, Bengaluru, India', 'Remote', 'Permanent'),
        $j('j3', 'GF', '#12805C', 'GreenLeaf Foods', 'Marketing Executive', 'Full time', '1–3 years', '₹5–7 L / year',
           'Plan campaigns, manage social channels and work with retail partners across Gujarat.', 'Satellite, Ahmedabad, India', 'On-site', 'Permanent'),
        $j('j4', 'OF', '#C62839', 'Orbit FinServe', 'PHP Backend Developer', 'Full time', '3–6 years', '₹14–18 L / year',
           'Design REST APIs and optimise MySQL queries for a high-volume payments platform.', 'Andheri East, Mumbai, India', 'Hybrid', 'Permanent'),
        $j('j5', 'SH', '#0E7490', 'Sunrise Hospital', 'HR Coordinator', 'Part time', '2–3 years', '₹4–6 L / year',
           'Support hiring, onboarding and staff records for a 200-bed hospital.', 'Adajan, Surat, India', 'On-site', 'Permanent'),
        $j('j6', 'PP', '#F5A524', 'Pixel Pulse Media', 'UI/UX Intern', 'Internship', '0–1 year', '₹1.8–2.4 L / year',
           'Learn by shipping: help the design team with wireframes, icons and usability tests.', 'Remote, India', 'Remote', 'Internship (6 months)'),
        $j('j7', 'KL', '#9A5B00', 'Kite Logistics', 'Operations Analyst', 'Full time', '2–4 years', '₹8–11 L / year',
           'Analyse fleet and warehouse data, build dashboards and recommend cost savings.', 'Sector 44, Gurugram, India', 'On-site', 'Permanent'),
        $j('j8', 'ES', '#2246D6', 'EduSpark Learning', 'Content Strategist', 'Part time', '3–5 years', '₹6–8 L / year',
           'Shape course content and the editorial calendar for our online learning platform.', 'Remote, India', 'Remote', 'Contract (12 months)'),
    ];
    // location + industry per job (used by the Search filters)
    $meta = [
        'j1' => ['Pune, India', 'Information & Communication Technology'], 'j2' => ['Bengaluru, India', 'Design & Architecture'],
        'j3' => ['Ahmedabad, India', 'Retail & Consumer Products'],        'j4' => ['Mumbai, India', 'Information & Communication Technology'],
        'j5' => ['Surat, India', 'Healthcare & Medical'],                   'j6' => ['Remote, India', 'Marketing & Communications'],
        'j7' => ['Gurugram, India', 'Manufacturing, Transport & Logistics'], 'j8' => ['Remote, India', 'Education & Training'],
    ];
    foreach ($jobs as &$job) { [$job['loc'], $job['ind']] = $meta[$job['id']]; }
    unset($job);
    return $jobs;
}

/** Candidate cards shown to the employer: photo, name, experience, industry, expected salary.
 *  'loc' and 'ind' are not printed on the card; the Search filters use them. */
function demo_candidates(): array
{
    $c = fn(string $id, string $initials, string $color, string $name, string $loc, string $exp, string $industry, string $salary) => [
        'id' => $id, 'round' => true, 'photo' => null,   // photo: put the uploaded picture URL here later
        'tile' => $initials, 'color' => $color, 'kicker' => '', 'title' => $name,
        'loc' => $loc, 'ind' => $industry,
        'facts' => [['Experience', $exp], ['Industry', $industry], ['Expected salary', $salary]],
        'details' => [],
    ];
    return [
        $c('c1', 'AM', '#2F5BFF', 'Aarav Mehta',  'Ahmedabad, India', '6 years', 'Information & Communication Technology', '₹17 L / year'),
        $c('c2', 'NK', '#7A3FF2', 'Neha Kapoor',  'Pune, India',      '4 years', 'Information & Communication Technology', '₹15 L / year'),
        $c('c3', 'RI', '#12805C', 'Rohan Iyer',   'Bengaluru, India', '5 years', 'Engineering',                            '₹18.5 L / year'),
        $c('c4', 'SQ', '#C62839', 'Sana Qureshi', 'Hyderabad, India', '3 years', 'Information & Communication Technology', '₹12 L / year'),
        $c('c5', 'VR', '#0E7490', 'Vikram Rao',   'Mumbai, India',    '8 years', 'Consulting & Strategy',                  '₹24 L / year'),
        $c('c6', 'ID', '#F5A524', 'Ishita Desai', 'Surat, India',     '2 years', 'Education & Training',                   '₹8 L / year'),
        $c('c7', 'KP', '#9A5B00', 'Karan Patel',  'Ahmedabad, India', '5 years', 'Engineering',                            '₹14 L / year'),
        $c('c8', 'MS', '#2246D6', 'Meera Shah',   'Pune, India',      '7 years', 'Consulting & Strategy',                  '₹21 L / year'),
    ];
}

/** Notification list opened by the bell icon. */
function demo_notifications(bool $employer): array
{
    return $employer ? [
        ['title' => 'New applicant', 'text' => 'Aarav Mehta applied for Senior PHP Developer.', 'time' => '5 min ago', 'unread' => true],
        ['title' => 'Profile viewed', 'text' => 'Your job post was viewed 24 times today.', 'time' => '1 hour ago', 'unread' => true],
        ['title' => 'Match', 'text' => 'Neha Kapoor accepted your shortlist.', 'time' => 'Yesterday', 'unread' => false],
        ['title' => 'Welcome', 'text' => 'Your company profile is live on HireSwipe.', 'time' => '2 days ago', 'unread' => false],
    ] : [
        ['title' => 'New match', 'text' => 'Acme Technologies is interested in your profile.', 'time' => '10 min ago', 'unread' => true],
        ['title' => 'New jobs', 'text' => '8 new jobs match your industries today.', 'time' => '2 hours ago', 'unread' => true],
        ['title' => 'Profile viewed', 'text' => 'Nimbus Design Studio viewed your CV.', 'time' => 'Yesterday', 'unread' => false],
        ['title' => 'Welcome', 'text' => 'Your profile is complete. Start swiping!', 'time' => '2 days ago', 'unread' => false],
    ];
}