<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireLogin();

if (!isCustomer()) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle remove from wishlist
if (isset($_GET['remove'])) {
    $book_id = $_GET['remove'];
    try {
        $query = "DELETE FROM wishlist WHERE user_id = :user_id AND book_id = :book_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        
        header("Location: wishlist.php?removed=1");
        exit();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get wishlist items
$query = "SELECT w.*, b.*, c.name as category_name FROM wishlist w 
          JOIN books b ON w.book_id = b.id 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE w.user_id = :user_id 
          ORDER BY w.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - ReyBookstore</title>
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
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> ReyBookstore</a>
            <span class="navbar-text text-white me-3">
                Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                <i class="bi bi-moon-fill" id="themeIcon"></i>
            </button>
            <a href="logout.php" class="btn btn-outline-light ms-2">Logout</a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 bg-light p-4 sidebar-menu" style="min-height: 100vh;">
                <h5>Menu</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                    </li>
                    <?php if (isCustomer()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="wishlist.php"><i class="bi bi-heart"></i> Wishlist</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php"><i class="bi bi-bag"></i> Pesanan Saya</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-md-10 p-4">
                <h2><i class="bi bi-heart"></i> Wishlist Saya</h2>
                
                <?php if (empty($wishlist_items)): ?>
                    <div class="text-center py-5 mt-4">
                        <i class="bi bi-heart" style="font-size: 64px; color: #dc3545;"></i>
                        <h5 class="mt-3">Wishlist Kosong</h5>
                        <p class="text-muted">Anda belum menambahkan buku ke wishlist. <a href="index.php">Kunjungi halaman utama</a> untuk melihat koleksi buku.</p>
                    </div>
                <?php else: ?>
                    <div class="row mt-4">
                        <?php foreach ($wishlist_items as $item): ?>
                            <div class="col-md-3 mb-4">
                                <div class="card h-100">
                                    <a href="book_detail.php?id=<?php echo $item['book_id']; ?>">
                                        <?php if ($item['image'] && file_exists($item['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>" style="height: 300px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                                                <i class="bi bi-image" style="font-size: 64px; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="book_detail.php?id=<?php echo $item['book_id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="card-text text-muted">oleh <?php echo htmlspecialchars($item['author']); ?></p>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <h4 class="text-primary mt-2">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></h4>
                                        <p class="text-muted">Stok: <?php echo $item['stock']; ?></p>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <a href="book_detail.php?id=<?php echo $item['book_id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Lihat Detail
                                            </a>
                                            <?php if ($item['stock'] > 0): ?>
                                                <form method="POST" action="cart.php">
                                                    <input type="hidden" name="book_id" value="<?php echo $item['book_id']; ?>">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit" name="add_to_cart" class="btn btn-primary w-100">
                                                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="wishlist.php?remove=<?php echo $item['book_id']; ?>" class="btn btn-outline-danger remove-wishlist-btn" data-book-title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <i class="bi bi-heart-fill"></i> Hapus dari Wishlist
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script src="assets/js/dark-mode.js"></script>
    <script>
        <?php if (isset($_GET['removed'])): ?>
            showSuccess('Buku berhasil dihapus dari wishlist!');
        <?php endif; ?>
        
        // Handle remove from wishlist with SweetAlert
        document.querySelectorAll('.remove-wishlist-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const bookTitle = this.getAttribute('data-book-title');
                Swal.fire({
                    title: 'Konfirmasi',
                    text: 'Yakin hapus "' + bookTitle + '" dari wishlist?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = this.href;
                    }
                });
            });
        });
    </script>
</body>
</html>

