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
    $quantity = $_POST['quantity'];
    $user_id = $_SESSION['user_id'];
    
    // Verify cart item belongs to user and get product info
    $query = "SELECT c.*, p.stock FROM cart c 
              JOIN products p ON c.product_id = p.id 
              WHERE c.id = ? AND c.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$cart_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($quantity <= $cart_item['stock']) {
            $update_query = "UPDATE cart SET quantity = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$quantity, $cart_id]);
            $_SESSION['message'] = "Cart updated successfully!";
        } else {
            $_SESSION['error'] = "Not enough stock available!";
        }
    } else {
        $_SESSION['error'] = "Invalid cart item!";
    }
}

header("Location: cart.php");
exit();
?>