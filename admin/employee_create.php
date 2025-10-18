<?php
$title = 'Create Employee';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$pdo = getPDO();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

    $name = trim((string)post('name'));
    $email = trim((string)post('email'));
    $mobile = trim((string)post('mobile'));
    $password = (string)post('password');
    $role = (string)(post('role') ?? 'employee');
    $interview_date = trim((string)post('interview_date'));

    // ✅ Basic validation
    if ($name === '' || strlen($password) < 6) {
        $errors[] = 'Provide valid name and password (at least 6 characters).';
    }

    // ✅ Email validation only if entered
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    // ✅ Mobile validation (optional but must be numeric)
    if ($mobile !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) {
        $errors[] = 'Invalid mobile number format.';
    }

    // ✅ Check duplicates only if entered
    if (!$errors) {
        if ($email !== '') {
            $exists = pdo_fetch_one($pdo, 'SELECT id FROM users WHERE email = ?', [$email]);
            if ($exists) {
                $errors[] = 'Email already exists.';
            }
        }
        if ($mobile !== '') {
            $existsMobile = pdo_fetch_one($pdo, 'SELECT id FROM users WHERE mobile = ?', [$mobile]);
            if ($existsMobile) {
                $errors[] = 'Mobile number already exists.';
            }
        }
    }

    if (!$errors) {
        if (!in_array($role, ['admin', 'employee'], true)) {
            $role = 'employee';
        }

        // ✅ Store NULL when blank
        $emailToStore = $email !== '' ? $email : null;
        $mobileToStore = $mobile !== '' ? $mobile : null;

        $stmt = $pdo->prepare('INSERT INTO users (name, email, mobile, password_hash, role, interview_date) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $name,
            $emailToStore,
            $mobileToStore,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $interview_date ?: null
        ]);

        flash_set('success', 'Employee created successfully.');
        redirect('admin/employees.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Create Employee</h1>
    <a href="<?= base_url('admin/employees.php') ?>" class="text-sm text-primary-700 hover:underline">Back</a>
  </div>
  <form method="post" class="bg-white border border-slate-200 rounded p-6 grid grid-cols-1 gap-4">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <?php if ($errors): ?>
      <ul class="text-red-700 bg-red-50 border border-red-200 rounded p-3 text-sm list-disc list-inside">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Name</label>
      <input class="w-full border rounded px-3 py-2 focus-ring" name="name" required />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Email (optional)</label>
      <input type="email" class="w-full border rounded px-3 py-2 focus-ring" name="email" />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Mobile (optional)</label>
      <input type="text" class="w-full border rounded px-3 py-2 focus-ring" name="mobile" placeholder="" />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Role</label>
      <select class="w-full border rounded px-3 py-2" name="role">
        <option value="employee">Employee</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Password</label>
      <input type="password" class="w-full border rounded px-3 py-2 focus-ring" name="password" required />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Interview Date</label>
      <input type="datetime-local" class="w-full border rounded px-3 py-2 focus-ring" name="interview_date" />
    </div>

    <div>
      <button class="px-4 py-2 rounded bg-primary-600 text-white hover:bg-primary-700" type="submit">
        Create
      </button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
