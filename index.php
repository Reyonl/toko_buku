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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>ReyBookstore - Toko Buku Online</title>
    <link rel="shortcut icon" href="assets/images/bookshop.gif" type="image/gif">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Navbar Enhancement */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .nav-link {
            transition: all 0.3s;
            border-radius: 5px;
            margin: 0 5px;
        }
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        .hero-content {
            position: relative;
            z-index: 1;
        }
        .hero h1 {
            font-size: 3rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin-bottom: 1rem;
        }
        .hero .lead {
            font-size: 1.3rem;
            opacity: 0.95;
        }

        /* Sidebar Enhancement */
        .sidebar-category {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px var(--card-shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .sidebar-category h5 {
            color: #2a5298;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .list-group-item {
            border: none;
            border-radius: 8px !important;
            margin-bottom: 5px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .list-group-item:hover {
            background-color: #f8f9fa;
            border-left-color: #667eea;
            transform: translateX(5px);
        }
        .list-group-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-left-color: #764ba2;
        }

        /* Search Bar Enhancement */
        .search-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px var(--card-shadow);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        /* Book Card Enhancement */
        .book-card {
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px var(--card-shadow);
            background: var(--card-bg);
        }
        .book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .book-card .card-img-top {
            transition: transform 0.5s;
            border-radius: 0;
        }
        .book-card:hover .card-img-top {
            transform: scale(1.05);
        }
        .book-card .card-body {
            padding: 20px;
        }
        .book-card .card-title {
            color: #2a5298;
            font-size: 1.1rem;
            margin-bottom: 10px;
            min-height: 50px;
        }
        .book-card .card-title:hover {
            color: #667eea;
        }
        .book-card .badge {
            border-radius: 20px;
            padding: 5px 12px;
            font-weight: 500;
        }
        .book-card .card-text {
            color: #6c757d;
            font-size: 0.9rem;
            min-height: 40px;
        }
        .book-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            margin: 15px 0;
        }
        .stock-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            background: #d4edda;
            color: #155724;
        }
        .book-card .card-footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            transform: translateY(-2px);
        }

        /* Pagination Enhancement */
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            border: 2px solid #e9ecef;
            color: #667eea;
            transition: all 0.3s;
        }
        .pagination .page-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 2px 10px var(--card-shadow);
            border: 1px solid var(--border-color);
        }
        .empty-state i {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }

        /* Footer Enhancement */
        footer {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
            margin-top: 60px;
            padding: 30px 0;
        }
        footer p {
            margin: 0;
            opacity: 0.9;
        }

        /* Stats Section */
        .stats-info {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px var(--card-shadow);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            .hero .lead {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> ReyBookstore</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isCustomer()): ?>
                            <li class="nav-item"><a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a></li>
                            <li class="nav-item"><a class="nav-link" href="wishlist.php"><i class="bi bi-heart"></i> Wishlist</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                            <i class="bi bi-moon-fill" id="themeIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero text-center">
        <div class="container hero-content">
            <h1 class="display-4"><i class="bi bi-book-heart"></i> Selamat Datang di ReyBookstore</h1>
            <p class="lead">Temukan ribuan buku favorit Anda dengan harga terbaik dan kualitas terjamin</p>
            <div class="mt-4">
                <a href="#books" class="btn btn-light btn-lg">
                    <i class="bi bi-arrow-down-circle"></i> Jelajahi Koleksi
                </a>
            </div>
        </div>
    </div>

    <div class="container my-5" id="books">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar-category">
                    <h5><i class="bi bi-grid-3x3-gap"></i> Kategori</h5>
                    <div class="list-group">
                        <a href="index.php" class="list-group-item list-group-item-action <?php echo !$category_filter ? 'active' : ''; ?>">
                            <i class="bi bi-collection"></i> Semua Kategori
                        </a>
                        <?php 
                        // Reset pointer untuk fetch ulang
                        $stmt_cat->execute();
                        while ($cat = $stmt_cat->fetch(PDO::FETCH_ASSOC)): 
                        ?>
                            <a href="index.php?category=<?php echo $cat['id']; ?>" class="list-group-item list-group-item-action <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                                <i class="bi bi-bookmark"></i> <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="search-section">
                    <form method="GET">
                        <?php if ($category_filter): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari buku berdasarkan judul atau penulis..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="sort" class="form-select" onchange="this.form.submit()">
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>><i class="bi bi-clock"></i> Terbaru</option>
                                    <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Harga: Rendah ke Tinggi</option>
                                    <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Harga: Tinggi ke Rendah</option>
                                    <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Judul A-Z</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($total_books > 0): ?>
                    <div class="stats-info">
                        <i class="bi bi-info-circle text-primary"></i> 
                        Menampilkan <strong><?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_books); ?></strong> dari <strong><?php echo $total_books; ?></strong> buku
                    </div>
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
                                    <div class="card-body d-flex flex-column">
                                        <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="text-decoration-none text-dark">
                                            <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        </a>
                                        <p class="card-text text-muted small mb-2">
                                            <i class="bi bi-person"></i> oleh <strong><?php echo htmlspecialchars($book['author']); ?></strong>
                                        </p>
                                        <span class="badge bg-info mb-2" style="width: fit-content;"><?php echo htmlspecialchars($book['category_name'] ?: 'Tidak ada kategori'); ?></span>
                                        <p class="card-text small text-muted flex-grow-1"><?php echo substr(htmlspecialchars($book['description'] ?: 'Tidak ada deskripsi'), 0, 100); ?><?php echo strlen($book['description'] ?: '') > 100 ? '...' : ''; ?></p>
                                        <div class="book-price">Rp <?php echo number_format($book['price'], 0, ',', '.'); ?></div>
                                        <span class="stock-badge">
                                            <i class="bi bi-check-circle"></i> Stok: <?php echo $book['stock']; ?> unit
                                        </span>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0">
                                        <div class="d-grid gap-2">
                                            <a href="book_detail.php?id=<?php echo $book['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Lihat Detail
                                            </a>
                                            <?php if (isLoggedIn() && isCustomer()): ?>
                                                <form method="POST" action="cart.php" class="mt-2">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <div class="input-group mb-2">
                                                        <span class="input-group-text"><i class="bi bi-123"></i></span>
                                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $book['stock']; ?>" class="form-control" placeholder="Jumlah">
                                                    </div>
                                                    <button type="submit" name="add_to_cart" class="btn btn-primary w-100">
                                                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                                    </button>
                                                </form>
                                            <?php elseif (!isLoggedIn()): ?>
                                                <a href="login.php" class="btn btn-secondary w-100">
                                                    <i class="bi bi-box-arrow-in-right"></i> Login untuk Membeli
                                                </a>
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
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h4 class="mt-3">Tidak ada buku yang ditemukan</h4>
                        <p class="text-muted">Coba ubah kata kunci pencarian atau pilih kategori lain</p>
                        <a href="index.php" class="btn btn-primary mt-3">
                            <i class="bi bi-arrow-left"></i> Kembali ke Semua Buku
                        </a>
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
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
