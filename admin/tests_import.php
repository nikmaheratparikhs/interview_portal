<?php
$title = 'Import Tests';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
$pdo = getPDO();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf_or_fail();
  if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please upload a CSV file.';
  } else {
    $tmp = $_FILES['file']['tmp_name'];
    $f = fopen($tmp, 'r');
    if ($f === false) {
      $errors[] = 'Cannot open uploaded file.';
    } else {
      // ✅ expected columns — corrected (removed 'correct_text_answer' since your sample doesn't have it)
      $expected = [
        'test_title','test_description','test_category','test_difficulty','test_time_limit_minutes',
        'question_text','question_type','question_points',
        'choice_1','choice_1_correct','choice_2','choice_2_correct',
        'choice_3','choice_3_correct','choice_4','choice_4_correct'
      ];

      $normalize = function ($s) {
        $s = (string)$s;
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s); // strip BOM
        return strtolower(trim($s));
      };

      // ✅ Try comma, semicolon, or tab
      $delimiters = [',', ';', "\t"];
      $delimiterUsed = null;
      $header = null;

      foreach ($delimiters as $delim) {
        rewind($f);
        $header = fgetcsv($f, 0, $delim, '"', '\\');
        if (!$header) continue;
        $normalizedHeader = array_map($normalize, $header);
        if ($normalizedHeader === $expected) {
          $delimiterUsed = $delim;
          break;
        }
      }

      if ($delimiterUsed === null) {
        $errors[] = 'Invalid header or delimiter. Please use the sample CSV (comma, semicolon, or tab-separated).';
      } else {
        // ✅ Read and import
        $pdo->beginTransaction();
        try {
          $testIdMap = [];
          $createdTests = 0; $createdQuestions = 0;

          while (($row = fgetcsv($f, 0, $delimiterUsed, '"', '\\')) !== false) {
            if ($row === null) continue;

            $row = array_map(fn($v) => trim((string)$v), $row);

            // Skip empty lines
            if (implode('', $row) === '') continue;

            // Normalize length
            $row = array_slice($row, 0, count($expected));
            if (count($row) < count($expected)) {
              $row = array_pad($row, count($expected), '');
            }

            // Build associative data
            $data = [];
            foreach ($expected as $i => $key) {
              $data[$key] = $row[$i] ?? '';
            }

            $key = trim($data['test_title']);
            if ($key === '') continue;

            if (!isset($testIdMap[$key])) {
              // Create test
              $stmt = $pdo->prepare('INSERT INTO tests (title, description, category, difficulty, time_limit_minutes, is_active, created_by) VALUES (?,?,?,?,?,1,?)');
              $stmt->execute([
                $data['test_title'],
                $data['test_description'] ?: null,
                $data['test_category'] ?: null,
                in_array(strtolower($data['test_difficulty']), ['beginner','intermediate','advanced'], true)
                  ? strtolower($data['test_difficulty'])
                  : 'beginner',
                is_numeric($data['test_time_limit_minutes'])
                  ? (int)$data['test_time_limit_minutes']
                  : null,
                $_SESSION['user']['id']
              ]);
              $testIdMap[$key] = (int)$pdo->lastInsertId();
              $createdTests++;
            }

            $testId = $testIdMap[$key];

            // Create question
            $qType = in_array(strtolower($data['question_type']), ['single','multiple','text'], true)
              ? strtolower($data['question_type'])
              : 'single';
            $qPoints = is_numeric($data['question_points']) ? (float)$data['question_points'] : 1;

            $pdo->prepare('INSERT INTO questions (test_id, question_text, question_type, points) VALUES (?,?,?,?)')
                ->execute([$testId, $data['question_text'], $qType, $qPoints]);
            $qid = (int)$pdo->lastInsertId();
            $createdQuestions++;

            if ($qType !== 'text') {
              for ($i = 1; $i <= 4; $i++) {
                $ct = trim((string)$data['choice_'.$i]);
                if ($ct === '') continue;
                $flag = strtolower(trim((string)$data['choice_'.$i.'_correct']));
                $isCorrect = in_array($flag, ['true','1','yes'], true) ? 1 : 0;
                $pdo->prepare('INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?,?,?)')
                    ->execute([$qid, $ct, $isCorrect]);
              }
            }
          }

          $pdo->commit();
          $success[] = 'Import completed successfully. Created ' . $createdTests . ' test(s) and ' . $createdQuestions . ' question(s).';
        } catch (Throwable $e) {
          $pdo->rollBack();
          $errors[] = 'Import failed: ' . $e->getMessage();
        }
      }
      fclose($f);
    }
  }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Import Tests</h1>
    <a href="<?= base_url('admin/tests.php') ?>" class="text-sm text-primary-700 hover:underline">Back</a>
  </div>

  <?php if ($errors): ?>
    <ul class="mb-4 text-red-700 bg-red-50 border border-red-200 rounded p-3 text-sm list-disc list-inside">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($success): ?>
    <ul class="mb-4 text-green-700 bg-green-50 border border-green-200 rounded p-3 text-sm list-disc list-inside">
      <?php foreach ($success as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded p-6">
    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div>
        <label class="block text-sm text-slate-600 mb-1">CSV file</label>
        <input type="file" name="file" accept=".csv,.tsv" class="block w-full text-sm" required />
      </div>
      <div class="flex items-center gap-3">
        <button class="px-4 py-2 rounded bg-primary-600 text-white" type="submit">Import</button>
        <a class="text-sm text-primary-700 hover:underline" href="<?= base_url('assets/sample_tests.csv') ?>">Download sample CSV</a>
      </div>
    </form>
  </div>
</div>
