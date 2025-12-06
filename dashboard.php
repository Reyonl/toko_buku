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
    <title><?php echo $page_title; ?> - Toko Buku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                            <a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a>
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
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-person"></i> Role</h5>
                                <p class="card-text display-6"><?php echo strtoupper($_SESSION['role']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isAdmin() || isStaff()): 
                        require_once 'config/database.php';
                        $database = new Database();
                        $db = $database->getConnection();
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM books");
                        $total_books = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                        <div class="col-md-4">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-book"></i> Total Buku</h5>
                                    <p class="card-text display-6"><?php echo $total_books; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): 
                        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role='customer'");
                        $total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                        <div class="col-md-4">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-people"></i> Total Customer</h5>
                                    <p class="card-text display-6"><?php echo $total_customers; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="alert alert-info mt-4">
                    <h5>Selamat Datang!</h5>
                    <p>Anda login sebagai <strong><?php echo strtoupper($_SESSION['role']); ?></strong></p>
                    <p>Silakan gunakan menu di samping untuk mengakses fitur yang tersedia.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>