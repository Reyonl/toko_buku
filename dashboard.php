<?php
require_once 'includes/session.php';
requireLogin();

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $page_title; ?> - ReyBookstore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .badge {
            font-weight: 500;
        }
        .alert {
            border-left: 4px solid;
        }
        .alert-warning {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> ReyBookstore</a>
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
                            <a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a>
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
                <h2>Dashboard</h2>
                
                <?php 
                require_once 'config/database.php';
                $database = new Database();
                $db = $database->getConnection();
                ?>
                
                <div class="row mt-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-person"></i> Role</h5>
                                <p class="card-text display-6"><?php echo strtoupper($_SESSION['role']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isAdmin() || isStaff()): 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM books");
                        $total_books = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM books WHERE stock > 0");
                        $available_books = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Stok rendah (kurang dari 10)
                        $stmt = $db->query("SELECT COUNT(*) as total FROM books WHERE stock > 0 AND stock < 10");
                        $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Stok habis
                        $stmt = $db->query("SELECT COUNT(*) as total FROM books WHERE stock = 0");
                        $out_of_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Total stok semua buku
                        $stmt = $db->query("SELECT SUM(stock) as total FROM books");
                        $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
                    ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-book"></i> Total Buku</h5>
                                    <p class="card-text display-6"><?php echo $total_books; ?></p>
                                    <small>Tersedia: <?php echo $available_books; ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-box-seam"></i> Total Stok</h5>
                                    <p class="card-text display-6"><?php echo number_format($total_stock, 0, ',', '.'); ?></p>
                                    <small>Unit tersedia</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card text-white bg-warning mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-exclamation-triangle"></i> Stok Rendah</h5>
                                    <p class="card-text display-6"><?php echo $low_stock; ?></p>
                                    <small>Buku dengan stok &lt; 10</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card text-white bg-danger mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-x-circle"></i> Stok Habis</h5>
                                    <p class="card-text display-6"><?php echo $out_of_stock; ?></p>
                                    <small>Buku perlu restock</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role='customer'");
                        $total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status != 'cancelled'");
                        $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
                        $revenue_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        $total_revenue = $revenue_data['total'] ? $revenue_data['total'] : 0;
                    ?>
                        <div class="col-md-3">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-people"></i> Customer</h5>
                                    <p class="card-text display-6"><?php echo $total_customers; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card text-white bg-warning mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-bag"></i> Total Pesanan</h5>
                                    <p class="card-text display-6"><?php echo $total_orders; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (isAdmin()): ?>
                    <!-- Revenue Card -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-currency-dollar"></i> Total Pendapatan</h5>
                                    <p class="card-text display-5">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></p>
                                    <small>Dari pesanan yang selesai</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <?php
                            $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
                            $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            
                            $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'processing'");
                            $processing_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-clock-history"></i> Pesanan Pending</h5>
                                    <p class="card-text display-6 text-warning"><?php echo $pending_orders; ?></p>
                                    <small class="text-muted">Processing: <?php echo $processing_orders; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Best Selling Books -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Buku Terlaris</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $best_seller_query = "SELECT b.*, SUM(oi.quantity) as total_sold, c.name as category_name 
                                                          FROM books b 
                                                          JOIN order_items oi ON b.id = oi.book_id 
                                                          JOIN orders o ON oi.order_id = o.id 
                                                          LEFT JOIN categories c ON b.category_id = c.id
                                                          WHERE o.status = 'completed' 
                                                          GROUP BY b.id 
                                                          ORDER BY total_sold DESC 
                                                          LIMIT 5";
                                    $best_seller_stmt = $db->query($best_seller_query);
                                    $best_sellers = $best_seller_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($best_sellers) > 0):
                                    ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Buku</th>
                                                        <th>Kategori</th>
                                                        <th>Terjual</th>
                                                        <th>Harga</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($best_sellers as $bs): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($bs['title']); ?></strong><br>
                                                                <small class="text-muted">oleh <?php echo htmlspecialchars($bs['author']); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($bs['category_name']); ?></td>
                                                            <td><span class="badge bg-success"><?php echo $bs['total_sold']; ?> unit</span></td>
                                                            <td>Rp <?php echo number_format($bs['price'], 0, ',', '.'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">Belum ada data penjualan.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isAdmin() || isStaff()): ?>
                    <!-- Stock Management Section -->
                    <div class="row mt-4">
                        <!-- Low Stock Books -->
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Buku dengan Stok Rendah</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $low_stock_query = "SELECT b.*, c.name as category_name 
                                                        FROM books b 
                                                        LEFT JOIN categories c ON b.category_id = c.id 
                                                        WHERE b.stock > 0 AND b.stock < 10 
                                                        ORDER BY b.stock ASC 
                                                        LIMIT 10";
                                    $low_stock_stmt = $db->query($low_stock_query);
                                    $low_stock_books = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($low_stock_books) > 0):
                                    ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Judul Buku</th>
                                                        <th>Kategori</th>
                                                        <th>Stok</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($low_stock_books as $book): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($book['author']); ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($book['category_name'] ?: 'Tidak ada'); ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-warning text-dark">
                                                                    <?php echo $book['stock']; ?> unit
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="manage_books.php?edit=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary" title="Edit Stok">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if ($low_stock > 10): ?>
                                            <div class="text-center mt-2">
                                                <a href="manage_books.php" class="btn btn-sm btn-outline-warning">
                                                    Lihat Semua (<?php echo $low_stock; ?> buku)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="bi bi-check-circle text-success" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-2">Tidak ada buku dengan stok rendah</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Out of Stock Books -->
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="bi bi-x-circle-fill"></i> Buku Stok Habis</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $out_stock_query = "SELECT b.*, c.name as category_name 
                                                        FROM books b 
                                                        LEFT JOIN categories c ON b.category_id = c.id 
                                                        WHERE b.stock = 0 
                                                        ORDER BY b.title ASC 
                                                        LIMIT 10";
                                    $out_stock_stmt = $db->query($out_stock_query);
                                    $out_stock_books = $out_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($out_stock_books) > 0):
                                    ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Judul Buku</th>
                                                        <th>Kategori</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($out_stock_books as $book): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($book['author']); ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($book['category_name'] ?: 'Tidak ada'); ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-danger">Habis</span>
                                                            </td>
                                                            <td>
                                                                <a href="manage_books.php?edit=<?php echo $book['id']; ?>" class="btn btn-sm btn-primary" title="Tambah Stok">
                                                                    <i class="bi bi-plus-circle"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if ($out_of_stock > 10): ?>
                                            <div class="text-center mt-2">
                                                <a href="manage_books.php" class="btn btn-sm btn-outline-danger">
                                                    Lihat Semua (<?php echo $out_of_stock; ?> buku)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <i class="bi bi-check-circle text-success" style="font-size: 48px;"></i>
                                            <p class="text-muted mt-2">Semua buku memiliki stok</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Alert Banner -->
                    <?php if ($low_stock > 0 || $out_of_stock > 0): ?>
                        <div class="alert alert-warning alert-dismissible fade show mt-4" role="alert">
                            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Peringatan Stok!</h5>
                            <p class="mb-0">
                                <?php if ($out_of_stock > 0): ?>
                                    <strong><?php echo $out_of_stock; ?> buku</strong> dengan stok habis dan 
                                <?php endif; ?>
                                <?php if ($low_stock > 0): ?>
                                    <strong><?php echo $low_stock; ?> buku</strong> dengan stok rendah perlu segera ditangani.
                                <?php endif; ?>
                                <a href="manage_books.php" class="alert-link">Kelola stok sekarang</a>
                            </p>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="card mt-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <h5><i class="bi bi-hand-thumbs-up"></i> Selamat Datang!</h5>
                        <p class="mb-1">Anda login sebagai <strong><?php echo strtoupper($_SESSION['role']); ?></strong></p>
                        <p class="mb-0">Silakan gunakan menu di samping untuk mengakses fitur yang tersedia.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
</body>
</html>
