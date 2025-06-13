<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['cart_id']) || !isset($_POST['action'])) {
    header("Location: cart.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$cart_id = $_POST['cart_id'];
$action = $_POST['action'];
$user_id = $_SESSION['user_id'];

try {
    // Get current cart item with product info
    $query = "SELECT c.quantity, c.product_id, p.name, p.stock 
              FROM cart c 
              JOIN products p ON c.product_id = p.id 
              WHERE c.id = ? AND c.user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$cart_id, $user_id]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart_item) {
        $_SESSION['error'] = "Cart item not found!";
        header("Location: cart.php");
        exit();
    }
    
    $current_quantity = $cart_item['quantity'];
    $product_stock = $cart_item['stock'];
    $product_name = $cart_item['name'];
    
    if ($action == 'increase') {
        if ($current_quantity >= $product_stock) {
            $_SESSION['error'] = "Cannot add more items. Maximum stock available: " . $product_stock;
        } else {
            $new_quantity = $current_quantity + 1;
            $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                $_SESSION['success'] = "Quantity increased for " . $product_name;
            } else {
                $_SESSION['error'] = "Failed to update cart!";
            }
        }
    } elseif ($action == 'decrease') {
        if ($current_quantity <= 1) {
            $_SESSION['error'] = "Minimum quantity is 1. Use remove button to delete item.";
        } else {
            $new_quantity = $current_quantity - 1;
            $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                $_SESSION['success'] = "Quantity decreased for " . $product_name;
            } else {
                $_SESSION['error'] = "Failed to update cart!";
            }
        }
    } else {
        $_SESSION['error'] = "Invalid action!";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: cart.php");
exit();
?>
