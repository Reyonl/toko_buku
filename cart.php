<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireLogin();

// Only customers can access cart
if (!isCustomer()) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle add to cart
if (isset($_POST['add_to_cart']) && isset($_POST['book_id'])) {
    $book_id = $_POST['book_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Validate quantity
    if ($quantity < 1) {
        $message = 'Jumlah harus lebih dari 0';
        $message_type = 'danger';
    } else {
        // Get book details
        $query = "SELECT * FROM books WHERE id = :id AND stock > 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $book_id);
        $stmt->execute();
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book) {
            $message = 'Buku tidak ditemukan atau stok habis';
            $message_type = 'danger';
        } elseif ($quantity > $book['stock']) {
            $message = 'Jumlah melebihi stok yang tersedia';
            $message_type = 'danger';
        } else {
            try {
                $db->beginTransaction();
                
                // Get or create pending order
                $query = "SELECT id FROM orders WHERE user_id = :user_id AND status = 'pending' LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    // Create new pending order
                    $query = "INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (:user_id, 0, 'pending', NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();
                    $order_id = $db->lastInsertId();
                } else {
                    $order_id = $order['id'];
                }
                
                // Check if book already in order
                $query = "SELECT id, quantity FROM order_items WHERE order_id = :order_id AND book_id = :book_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':book_id', $book_id);
                $stmt->execute();
                $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_item) {
                    // Update quantity
                    $new_quantity = $existing_item['quantity'] + $quantity;
                    if ($new_quantity > $book['stock']) {
                        throw new Exception('Jumlah melebihi stok yang tersedia');
                    }
                    $query = "UPDATE order_items SET quantity = :quantity WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':quantity', $new_quantity);
                    $stmt->bindParam(':id', $existing_item['id']);
                    $stmt->execute();
                } else {
                    // Add new item
                    $query = "INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (:order_id, :book_id, :quantity, :price)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':order_id', $order_id);
                    $stmt->bindParam(':book_id', $book_id);
                    $stmt->bindParam(':quantity', $quantity);
                    $stmt->bindParam(':price', $book['price']);
                    $stmt->execute();
                }
                
                // Update order total
                $query = "SELECT SUM(price * quantity) as total FROM order_items WHERE order_id = :order_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->execute();
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $query = "UPDATE orders SET total_amount = :total WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':total', $total);
                $stmt->bindParam(':id', $order_id);
                $stmt->execute();
                
                $db->commit();
                header("Location: cart.php?swal=success&message=" . urlencode('Buku berhasil ditambahkan ke pesanan!'));
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Show success message if redirected
if (isset($_GET['success'])) {
    $message = 'Buku berhasil ditambahkan ke pesanan!';
    $message_type = 'success';
}

// Handle remove item
if (isset($_POST['remove_item']) && isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];
    
    try {
        $db->beginTransaction();
        
        // Get order_id from item
        $query = "SELECT order_id FROM order_items WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $item_id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Delete item
            $query = "DELETE FROM order_items WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $item_id);
            $stmt->execute();
            
            // Update order total
            $query = "SELECT SUM(price * quantity) as total FROM order_items WHERE order_id = :order_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':order_id', $item['order_id']);
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($total === null) {
                $total = 0;
            }
            
            $query = "UPDATE orders SET total_amount = :total WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':id', $item['order_id']);
            $stmt->execute();
            
            // If no items left, delete order
            $query = "SELECT COUNT(*) as count FROM order_items WHERE order_id = :order_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':order_id', $item['order_id']);
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count == 0) {
                $query = "DELETE FROM orders WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $item['order_id']);
                $stmt->execute();
            }
        }
        
        $db->commit();
        $message = 'Item berhasil dihapus';
        $message_type = 'success';
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle checkout
if (isset($_POST['checkout']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    try {
        // Update order status to processing
        $query = "UPDATE orders SET status = 'processing' WHERE id = :id AND user_id = :user_id AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $order_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update book stock
            $query = "SELECT book_id, quantity FROM order_items WHERE order_id = :order_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $update_query = "UPDATE books SET stock = stock - :quantity WHERE id = :book_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':quantity', $item['quantity']);
                $update_stmt->bindParam(':book_id', $item['book_id']);
                $update_stmt->execute();
            }
            
            header("Location: orders.php?success=1");
            exit();
        } else {
            $message = 'Pesanan tidak ditemukan atau sudah diproses';
            $message_type = 'danger';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get pending order and items
$query = "SELECT * FROM orders WHERE user_id = :user_id AND status = 'pending' LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$pending_order = $stmt->fetch(PDO::FETCH_ASSOC);

$cart_items = [];
if ($pending_order) {
    $query = "SELECT oi.*, b.title, b.author, b.stock, b.image FROM order_items oi 
              JOIN books b ON oi.book_id = b.id 
              WHERE oi.order_id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $pending_order['id']);
    $stmt->execute();
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Keranjang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Toko Buku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> BookStore</a>
            <span class="navbar-text text-white me-3">
                Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 bg-light p-4" style="min-height: 100vh;">
                <h5>Menu</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                    </li>
                    <?php if (isCustomer()): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="wishlist.php"><i class="bi bi-heart"></i> Wishlist</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php"><i class="bi bi-bag"></i> Pesanan Saya</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isStaff() || isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_books.php"><i class="bi bi-book"></i> Kelola Buku</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_categories.php"><i class="bi bi-tags"></i> Kelola Kategori</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php"><i class="bi bi-people"></i> Kelola User</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_orders.php"><i class="bi bi-box"></i> Kelola Pesanan</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-md-10 p-4">
                <h2><i class="bi bi-cart"></i> Keranjang</h2>
                
                <?php if (empty($cart_items)): ?>
                    <div class="text-center py-5 mt-4">
                        <i class="bi bi-cart-x" style="font-size: 64px; color: #5bc0de;"></i>
                        <h5 class="mt-3">Keranjang Kosong</h5>
                        <p class="text-muted">Anda belum menambahkan buku ke keranjang. <a href="index.php">Kunjungi halaman utama</a> untuk melihat koleksi buku.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Gambar</th>
                                    <th>Buku</th>
                                    <th>Penulis</th>
                                    <th>Harga</th>
                                    <th>Jumlah</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['image'] && file_exists($item['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 60px; height: 80px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                    <i class="bi bi-image" style="font-size: 24px; color: #999;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo htmlspecialchars($item['author']); ?></td>
                                        <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;" class="remove-item-form">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="remove_item" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-active">
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>Rp <?php echo number_format($pending_order['total_amount'], 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $pending_order['id']; ?>">
                                            <button type="submit" name="checkout" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Checkout
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Lanjut Belanja</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script>
        // Handle remove item with SweetAlert
        document.querySelectorAll('.remove-item-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Konfirmasi',
                    text: 'Yakin ingin menghapus item ini?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        });
        
        // Show message from URL
        <?php if ($message): ?>
            <?php if ($message_type == 'success'): ?>
                showSuccess('<?php echo addslashes($message); ?>');
            <?php elseif ($message_type == 'danger'): ?>
                showError('<?php echo addslashes($message); ?>');
            <?php else: ?>
                showInfo('<?php echo addslashes($message); ?>');
            <?php endif; ?>
        <?php endif; ?>
        
        // Handle checkout with SweetAlert
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            if (form.querySelector('button[name="checkout"]')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Checkout Pesanan?',
                        text: 'Apakah Anda yakin ingin melakukan checkout?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, Checkout',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
