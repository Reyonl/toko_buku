<?php
require_once 'includes/session.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get categories
$query_cat = "SELECT * FROM categories ORDER BY name";
$stmt_cat = $db->prepare($query_cat);
$stmt_cat->execute();

// Get books with pagination and filters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$query = "SELECT b.*, c.name as category_name FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE b.stock > 0";

if ($category_filter) {
    $query .= " AND b.category_id = :category";
}
if ($search) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search)";
}

// Sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY b.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY b.price DESC";
        break;
    case 'title':
        $query .= " ORDER BY b.title ASC";
        break;
    default:
        $query .= " ORDER BY b.created_at DESC";
}

// Get total count for pagination
$count_query = $query;
$count_stmt = $db->prepare($count_query);
if ($category_filter) {
    $count_stmt->bindParam(':category', $category_filter);
}
if ($search) {
    $search_param = "%$search%";
    $count_stmt->bindParam(':search', $search_param);
}
$count_stmt->execute();
$total_books = $count_stmt->rowCount();
$total_pages = ceil($total_books / $per_page);

// Add pagination
$query .= " LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
if ($category_filter) {
    $stmt->bindParam(':category', $category_filter);
}
if ($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toko Buku Online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .book-card { transition: transform 0.3s; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 0; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> BookStore</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a></li>
                        <?php if (isCustomer()): ?>
                            <li class="nav-item"><a class="nav-link" href="wishlist.php"><i class="bi bi-heart"></i> Wishlist</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero text-center">
        <div class="container">
            <h1 class="display-4">Selamat Datang di Toko Buku Online</h1>
            <p class="lead">Temukan buku favorit Anda dengan harga terbaik</p>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-3">
                <h5>Kategori</h5>
                <div class="list-group">
                    <a href="index.php" class="list-group-item list-group-item-action <?php echo !$category_filter ? 'active' : ''; ?>">Semua Kategori</a>
                    <?php while ($cat = $stmt_cat->fetch(PDO::FETCH_ASSOC)): ?>
                        <a href="index.php?category=<?php echo $cat['id']; ?>" class="list-group-item list-group-item-action <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="col-md-9">
                <form method="GET" class="mb-4">
                    <?php if ($category_filter): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <?php endif; ?>
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <input type="text" name="search" class="form-control" placeholder="Cari buku..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Harga: Rendah ke Tinggi</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Harga: Tinggi ke Rendah</option>
                                <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Judul A-Z</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Cari</button>
                </form>

                <?php if ($total_books > 0): ?>
                    <p class="text-muted mb-3">Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_books); ?> dari <?php echo $total_books; ?> buku</p>
                <?php endif; ?>

                <?php if ($total_books > 0): ?>
                    <div class="row">
                        <?php while ($book = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card book-card h-100">
                                    <a href="book_detail.php?id=<?php echo $book['id']; ?>">
                                        <?php if ($book['image'] && file_exists($book['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($book['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($book['title']); ?>" style="height: 300px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                                                <i class="bi bi-image" style="font-size: 64px; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="card-body">
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="text-decoration-none text-dark">
                                            <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        </a>
                                        <p class="card-text text-muted">oleh <?php echo htmlspecialchars($book['author']); ?></p>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($book['category_name']); ?></span>
                                        <p class="card-text mt-2"><?php echo substr(htmlspecialchars($book['description']), 0, 100); ?>...</p>
                                        <h4 class="text-primary">Rp <?php echo number_format($book['price'], 0, ',', '.'); ?></h4>
                                        <p class="text-muted">Stok: <?php echo $book['stock']; ?></p>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Lihat Detail
                                            </a>
                                            <?php if (isLoggedIn()): ?>
                                                <form method="POST" action="cart.php" class="mt-2">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $book['stock']; ?>" class="form-control mb-2">
                                                    <button type="submit" name="add_to_cart" class="btn btn-primary w-100"><i class="bi bi-cart-plus"></i> Tambah ke Keranjang</button>
                                                </form>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-secondary w-100">Login untuk Membeli</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-info-circle" style="font-size: 48px; color: #5bc0de;"></i>
                        <p class="mt-3 text-muted">Tidak ada buku yang ditemukan.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-5">
        <p>by Reyonl.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
</body>
</html>