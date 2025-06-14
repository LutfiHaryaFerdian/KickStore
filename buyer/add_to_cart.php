<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['product_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$quantity = isset($_POST['quantity']) && $_POST['quantity'] > 0 ? (int)$_POST['quantity'] : 1;

try {
    
    $product_query = "SELECT id, name, price, stock FROM products WHERE id = ? AND status = 'active'";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = "Product not found or unavailable!";
        header("Location: index.php");
        exit();
    }
    
    if ($product['stock'] < $quantity) {
        $_SESSION['error'] = "Insufficient stock! Only " . $product['stock'] . " items available.";
        header("Location: index.php");
        exit();
    }
    
    
    $check_query = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$user_id, $product_id]);
    $existing_item = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_item) {
        
        $new_quantity = $existing_item['quantity'] + $quantity;
        
        
        if ($new_quantity > $product['stock']) {
            $_SESSION['error'] = "Cannot add more items. Total quantity would exceed available stock (" . $product['stock'] . " items).";
            header("Location: index.php");
            exit();
        }
        
        $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $result = $update_stmt->execute([$new_quantity, $existing_item['id']]);
        
        if ($result) {
            $_SESSION['success'] = "Cart updated! " . $product['name'] . " quantity increased to " . $new_quantity . ".";
        } else {
            $_SESSION['error'] = "Failed to update cart!";
        }
    } else {
        
        $insert_query = "INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        $insert_stmt = $db->prepare($insert_query);
        $result = $insert_stmt->execute([$user_id, $product_id, $quantity]);
        
        if ($result) {
            $_SESSION['success'] = "Product added to cart! " . $product['name'] . " (" . $quantity . " item" . ($quantity > 1 ? "s" : "") . ")";
        } else {
            $_SESSION['error'] = "Failed to add product to cart!";
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}


$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'index.php';
header("Location: " . $redirect_url);
exit();
?>
