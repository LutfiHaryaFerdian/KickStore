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
    // Handle different types of updates
    if (isset($_POST['cart_id']) && isset($_POST['action'])) {
        // Single item quantity update (increase/decrease)
        $cart_id = $_POST['cart_id'];
        $action = $_POST['action'];
        
        // Get current cart item with product info
        $query = "SELECT c.quantity, c.product_id, p.name, p.stock 
                  FROM cart c 
                  JOIN products p ON c.product_id = p.id 
                  WHERE c.id = ? AND c.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$cart_id, $user_id]);
        $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cart_item) {
            $_SESSION['error'] = "Item keranjang tidak ditemukan!";
            header("Location: cart.php");
            exit();
        }
        
        $current_quantity = $cart_item['quantity'];
        $product_stock = $cart_item['stock'];
        $product_name = $cart_item['name'];
        
        if ($action == 'increase') {
            if ($current_quantity >= $product_stock) {
                $_SESSION['error'] = "Tidak dapat menambah item. Maksimal stok tersedia: " . $product_stock;
            } else {
                $new_quantity = $current_quantity + 1;
                $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                    $_SESSION['success'] = "Kuantitas ditambah untuk " . $product_name;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui keranjang!";
                }
            }
        } elseif ($action == 'decrease') {
            if ($current_quantity <= 1) {
                $_SESSION['error'] = "Kuantitas minimal adalah 1. Gunakan tombol hapus untuk menghapus item.";
            } else {
                $new_quantity = $current_quantity - 1;
                $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                    $_SESSION['success'] = "Kuantitas dikurangi untuk " . $product_name;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui keranjang!";
                }
            }
        } else {
            $_SESSION['error'] = "Aksi tidak valid!";
        }
        
    } elseif (isset($_POST['product_id']) && isset($_POST['action'])) {
        // Product-based quantity update (for compatibility)
        $product_id = $_POST['product_id'];
        $action = $_POST['action'];
        
        // Get current cart item
        $query = "SELECT c.id, c.quantity, p.name, p.stock 
                  FROM cart c 
                  JOIN products p ON c.product_id = p.id 
                  WHERE c.product_id = ? AND c.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$product_id, $user_id]);
        $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cart_item) {
            $_SESSION['error'] = "Item tidak ditemukan di keranjang!";
            header("Location: cart.php");
            exit();
        }
        
        $cart_id = $cart_item['id'];
        $current_quantity = $cart_item['quantity'];
        $product_stock = $cart_item['stock'];
        $product_name = $cart_item['name'];
        
        if ($action == 'increase') {
            if ($current_quantity >= $product_stock) {
                $_SESSION['error'] = "Tidak dapat menambah item. Maksimal stok tersedia: " . $product_stock;
            } else {
                $new_quantity = $current_quantity + 1;
                $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                    $_SESSION['success'] = "Kuantitas ditambah untuk " . $product_name;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui keranjang!";
                }
            }
        } elseif ($action == 'decrease') {
            if ($current_quantity <= 1) {
                $_SESSION['error'] = "Kuantitas minimal adalah 1. Gunakan tombol hapus untuk menghapus item.";
            } else {
                $new_quantity = $current_quantity - 1;
                $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                    $_SESSION['success'] = "Kuantitas dikurangi untuk " . $product_name;
                } else {
                    $_SESSION['error'] = "Gagal memperbarui keranjang!";
                }
            }
        }
        
    } elseif (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        // Bulk quantity update
        $quantities = $_POST['quantities'];
        $updated_count = 0;
        
        foreach ($quantities as $cart_id => $new_quantity) {
            $new_quantity = (int)$new_quantity;
            
            if ($new_quantity < 1) {
                continue; // Skip invalid quantities
            }
            
            // Get cart item and product info
            $query = "SELECT c.quantity, c.product_id, p.name, p.stock 
                      FROM cart c 
                      JOIN products p ON c.product_id = p.id 
                      WHERE c.id = ? AND c.user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$cart_id, $user_id]);
            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cart_item) {
                continue; // Skip if item not found
            }
            
            // Check stock availability
            if ($new_quantity > $cart_item['stock']) {
                $_SESSION['error'] = "Kuantitas untuk " . $cart_item['name'] . " melebihi stok yang tersedia (" . $cart_item['stock'] . ")";
                continue;
            }
            
            // Update quantity
            $update_query = "UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$new_quantity, $cart_id, $user_id])) {
                $updated_count++;
            }
        }
        
        if ($updated_count > 0) {
            $_SESSION['success'] = "Berhasil memperbarui " . $updated_count . " item di keranjang.";
        } else {
            $_SESSION['error'] = "Tidak ada item yang diperbarui.";
        }
        
    } else {
        $_SESSION['error'] = "Data tidak valid!";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: cart.php");
exit();
?>
