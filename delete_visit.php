<?php
// delete_visit.php - Delete a visit
session_start();
require_once 'config.php';
require_once 'database.php';

if (!isset($_GET['id'])) {
    header("Location: visit.php");
    exit();
}

$visit_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("DELETE FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    
    $_SESSION['message'] = "Visit deleted successfully!";
} catch(PDOException $e) {
    $_SESSION['error'] = "Error deleting visit: " . $e->getMessage();
}

header("Location: visit.php");
exit();
?>