<?php

$page = (string)file_get_contents(dirname(__DIR__) . '/admin/hr-assessment-tests.php');
$module = (string)file_get_contents(dirname(__DIR__) . '/core/HrModule.php');

foreach (['name="options_json"', 'JSON گزینه‌ها', 'invalid_options'] as $forbidden) {
    if (str_contains($page, $forbidden)) {
        throw new RuntimeException('Raw assessment JSON UI remains: ' . $forbidden);
    }
}

foreach (['name="options_text"', 'assessment_options_json', 'حداقل دو گزینه', 'طیف ۱ تا ۵'] as $required) {
    if (!str_contains($page, $required)) {
        throw new RuntimeException('Assessment option builder contract missing: ' . $required);
    }
}

if (preg_match('/DELETE\s+FROM\s+hr_assessment_(?:tests|questions|packages)/i', $module)) {
    throw new RuntimeException('Assessment seed must archive historical seeded data instead of deleting it.');
}
foreach (['archivePreviousSeededAssessmentData', 'is_archived=1', 'q.active=0'] as $required) {
    if (!str_contains($module, $required)) {
        throw new RuntimeException('Assessment archival contract missing: ' . $required);
    }
}

echo "HR assessment builder contract: PASS\n";
