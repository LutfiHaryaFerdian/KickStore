<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $cart_id = $_POST['cart_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify cart item belongs to user
    $query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$cart_id, $user_id])) {
        $_SESSION['message'] = "Item removed from cart!";
    } else {
        $_SESSION['error'] = "Failed to remove item!";
    }
}

header("Location: cart.php");
exit();
?>