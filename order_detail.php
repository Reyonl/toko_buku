<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'];

// Get order details
$query = "SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $order_id);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Check access
if (!$order || (!isAdmin() && $order['user_id'] != $_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get order items
$query = "SELECT oi.*, b.title, b.author, b.image FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = :order_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order_id; ?> - ReyBookstore</title>
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
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> ReyBookstore</a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt"></i> Detail Pesanan #<?php echo $order_id; ?></h2>
            <a href="<?php echo isAdmin() ? 'manage_orders.php' : 'orders.php'; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="bi bi-info-circle"></i> Informasi Pesanan</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>No. Pesanan:</strong></td>
                                <td>#<?php echo $order['id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tanggal:</strong></td>
                                <td><?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <?php
                                    $badge_class = 'secondary';
                                    switch ($order['status']) {
                                        case 'pending': $badge_class = 'warning'; break;
                                        case 'processing': $badge_class = 'info'; break;
                                        case 'completed': $badge_class = 'success'; break;
                                        case 'cancelled': $badge_class = 'danger'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo strtoupper($order['status']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total:</strong></td>
                                <td><h4 class="text-primary">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></h4></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5><i class="bi bi-person"></i> Informasi Customer</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nama:</strong></td>
                                <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($order['email']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5><i class="bi bi-list"></i> Item Pesanan</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Gambar</th>
                                <th>Buku</th>
                                <th>Penulis</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
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
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                <td><strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
</body>
</html>