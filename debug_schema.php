<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

$db = Database::getInstance();

try {
    echo "<h1>Debug: aptitude_questions Columns</h1>";
    $columns = $db->fetchAll("DESCRIBE aptitude_questions");
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

try {
    echo "<h1>Debug: coding_problems Columns</h1>";
    $columns = $db->fetchAll("DESCRIBE coding_problems");
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
