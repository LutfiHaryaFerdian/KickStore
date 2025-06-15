<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: cart.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

try {
    if (isset($_POST['cart_id'])) {
        // Remove single item
        $cart_id = $_POST['cart_id'];
        
        // Get product name for confirmation message
        $product_query = "SELECT p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?";
        $product_stmt = $db->prepare($product_query);
        $product_stmt->execute([$cart_id, $user_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $delete_query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
            $delete_stmt = $db->prepare($delete_query);
            
            if ($delete_stmt->execute([$cart_id, $user_id])) {
                $_SESSION['success'] = "Item " . $product['name'] . " berhasil dihapus dari keranjang.";
            } else {
                $_SESSION['error'] = "Gagal menghapus item dari keranjang.";
            }
        } else {
            $_SESSION['error'] = "Item tidak ditemukan di keranjang.";
        }
        
    } elseif (isset($_POST['clear_cart'])) {
        // Clear entire cart
        $clear_query = "DELETE FROM cart WHERE user_id = ?";
        $clear_stmt = $db->prepare($clear_query);
        
        if ($clear_stmt->execute([$user_id])) {
            $_SESSION['success'] = "Keranjang berhasil dikosongkan.";
        } else {
            $_SESSION['error'] = "Gagal mengosongkan keranjang.";
        }
        
    } else {
        $_SESSION['error'] = "Data tidak valid.";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: cart.php");
exit();
?>
