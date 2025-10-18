<?php
$title = 'Edit Employee';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$pdo = getPDO();

$id = (int)(get('id') ?? 0);
$emp = pdo_fetch_one($pdo, 'SELECT * FROM users WHERE id = ? AND role = "employee"', [$id]);
if (!$emp) {
    flash_set('error', 'Employee not found.');
    redirect('admin/employees.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_fail();

    $name = trim((string)post('name'));
    $email = trim((string)post('email'));
    $mobile = trim((string)post('mobile'));
    $interview_date = trim((string)post('interview_date'));
    $is_active = (int)(post('is_active') ? 1 : 0);

    // ✅ Basic validation
    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    // ✅ Email validation only if entered
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    // ✅ Mobile validation only if entered
    if ($mobile !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $mobile)) {
        $errors[] = 'Invalid mobile number format.';
    }

    // ✅ Duplicate email check (ignore current employee)
    if (!$errors && $email !== '') {
        $exists = pdo_fetch_one($pdo, 'SELECT id FROM users WHERE email = ? AND id != ?', [$email, $id]);
        if ($exists) {
            $errors[] = 'Email already exists.';
        }
    }

    // ✅ Duplicate mobile check (ignore current employee)
    if (!$errors && $mobile !== '') {
        $existsMobile = pdo_fetch_one($pdo, 'SELECT id FROM users WHERE mobile = ? AND id != ?', [$mobile, $id]);
        if ($existsMobile) {
            $errors[] = 'Mobile number already exists.';
        }
    }

    if (!$errors) {
        // ✅ Convert blanks to NULL
        $emailToStore = $email !== '' ? $email : null;
        $mobileToStore = $mobile !== '' ? $mobile : null;

        $stmt = $pdo->prepare('UPDATE users 
            SET name = ?, email = ?, mobile = ?, interview_date = ?, is_active = ? 
            WHERE id = ? AND role = "employee"');

        $stmt->execute([
            $name,
            $emailToStore,
            $mobileToStore,
            $interview_date ?: null,
            $is_active,
            $id
        ]);

        flash_set('success', 'Employee updated successfully.');
        redirect('admin/employees.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Edit Employee</h1>
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
      <input class="w-full border rounded px-3 py-2 focus-ring" name="name" value="<?= e($emp['name']) ?>" required />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Email (optional)</label>
      <input type="email" class="w-full border rounded px-3 py-2 focus-ring" name="email" value="<?= e($emp['email']) ?>" />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Mobile (optional)</label>
      <input type="text" class="w-full border rounded px-3 py-2 focus-ring" name="mobile" value="<?= e($emp['mobile']) ?>" placeholder="" />
    </div>

    <div>
      <label class="block text-sm text-slate-600 mb-1">Interview Date</label>
      <input type="datetime-local" class="w-full border rounded px-3 py-2 focus-ring"
             name="interview_date"
             value="<?= e(str_replace(' ', 'T', (string)$emp['interview_date'])) ?>" />
    </div>

    <label class="flex items-center gap-2">
      <input type="checkbox" name="is_active" class="border rounded" <?= $emp['is_active'] ? 'checked' : '' ?> /> Active
    </label>

    <div>
      <button class="px-4 py-2 rounded bg-primary-600 text-white hover:bg-primary-700" type="submit">Save</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
