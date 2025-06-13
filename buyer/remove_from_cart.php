<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['cart_id'])) {
    header("Location: cart.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$cart_id = $_POST['cart_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get product name before deleting
    $query = "SELECT p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$cart_id, $user_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = "Cart item not found!";
        header("Location: cart.php");
        exit();
    }
    
    // Delete cart item
    $delete_query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
    $delete_stmt = $db->prepare($delete_query);
    
    if ($delete_stmt->execute([$cart_id, $user_id])) {
        $_SESSION['success'] = "Removed " . $product['name'] . " from cart";
    } else {
        $_SESSION['error'] = "Failed to remove item from cart!";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: cart.php");
exit();
?>
