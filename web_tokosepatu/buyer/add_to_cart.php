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
    
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // Check if product exists and has enough stock
    $product_query = "SELECT stock FROM products WHERE id = ?";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product && $product['stock'] >= $quantity) {
        // Check if item already in cart
        $check_query = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$user_id, $product_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // Update quantity
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $new_quantity = $existing['quantity'] + $quantity;
            
            if ($new_quantity <= $product['stock']) {
                $update_query = "UPDATE cart SET quantity = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$new_quantity, $existing['id']]);
                $_SESSION['message'] = "Cart updated successfully!";
            } else {
                $_SESSION['error'] = "Not enough stock available!";
            }
        } else {
            // Add new item to cart
            $insert_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->execute([$user_id, $product_id, $quantity]);
            $_SESSION['message'] = "Item added to cart successfully!";
        }
    } else {
        $_SESSION['error'] = "Product not available or insufficient stock!";
    }
}

header("Location: index.php");
exit();
?>