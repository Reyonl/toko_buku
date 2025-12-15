<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$query = "SELECT o.*, COUNT(oi.id) as total_items 
          FROM orders o 
          LEFT JOIN order_items oi ON o.id = oi.order_id 
          WHERE o.user_id = :user_id 
          GROUP BY o.id 
          ORDER BY o.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - ReyBookstore</title>
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
        <h2><i class="bi bi-bag"></i> Pesanan Saya</h2>
        
        <div class="table-responsive mt-4">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Tanggal</th>
                        <th>Total Item</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td><?php echo $order['total_items']; ?> item</td>
                            <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
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
                            <td>
                                <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Detail</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script>
        <?php if (isset($_GET['success'])): ?>
            showSuccess('Pesanan berhasil dibuat!');
        <?php endif; ?>
    </script>
</body>
</html>