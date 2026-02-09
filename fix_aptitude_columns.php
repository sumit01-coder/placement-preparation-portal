<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

$db = Database::getInstance();

echo "<h2>Fixing Schema Issues...</h2>";

// 1. Check aptitude_questions for test_id
try {
    $columns = $db->fetchAll("DESCRIBE aptitude_questions");
    $hasTestId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'test_id') {
            $hasTestId = true;
            break;
        }
    }

    if (!$hasTestId) {
        echo "Adding missing column 'test_id' to 'aptitude_questions'...<br>";
        $sql = "ALTER TABLE aptitude_questions ADD COLUMN test_id INT NOT NULL AFTER question_id";
        $db->query($sql);
        echo "<span style='color:green'>Success! Added test_id.</span><br>";
        
        // Add Foreign Key
        echo "Adding FK constraint...<br>";
        $sql = "ALTER TABLE aptitude_questions ADD CONSTRAINT fk_aq_test FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id) ON DELETE CASCADE";
        $db->query($sql);
        echo "<span style='color:green'>Success! Added Foreign Key.</span><br>";
        
    } else {
        echo "<span style='color:blue'>Column 'test_id' already exists in 'aptitude_questions'.</span><br>";
    }

} catch (Exception $e) {
    echo "<span style='color:red'>Error checking/fixing aptitude_questions: " . $e->getMessage() . "</span><br>";
}

// 2. Check if table 'test_questions' exists (The old pivot table) and DROP it if we are using direct relation
// Actually, let's keep it safe. If the User has data in pivot table, we might need to migrate it.
// For now, let's just make sure the column exists.

echo "<h3>Done!</h3>";
?>
