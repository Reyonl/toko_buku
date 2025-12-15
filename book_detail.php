<?php
require_once 'includes/session.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$book_id = isset($_GET['id']) ? $_GET['id'] : 0;
$message = '';
$message_type = '';

// Handle add review
if (isset($_POST['add_review']) && isLoggedIn()) {
    $rating = $_POST['rating'];
    $review = $_POST['review'];
    
    try {
        $query = "INSERT INTO book_reviews (book_id, user_id, rating, review, created_at) 
                  VALUES (:book_id, :user_id, :rating, :review, NOW())
                  ON DUPLICATE KEY UPDATE rating = :rating, review = :review, created_at = NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':review', $review);
        $stmt->execute();
        
        $message = 'Review berhasil ditambahkan!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle add to wishlist
if (isset($_GET['add_wishlist']) && isLoggedIn()) {
    try {
        $query = "INSERT INTO wishlist (user_id, book_id, created_at) VALUES (:user_id, :book_id, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        
        $message = 'Buku berhasil ditambahkan ke wishlist!';
        $message_type = 'success';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            $message = 'Buku sudah ada di wishlist Anda!';
            $message_type = 'warning';
        } else {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Handle remove from wishlist
if (isset($_GET['remove_wishlist']) && isLoggedIn()) {
    try {
        $query = "DELETE FROM wishlist WHERE user_id = :user_id AND book_id = :book_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        
        $message = 'Buku berhasil dihapus dari wishlist!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Check if book is in wishlist
$in_wishlist = false;
if (isLoggedIn()) {
    try {
        $wishlist_query = "SELECT id FROM wishlist WHERE user_id = :user_id AND book_id = :book_id";
        $wishlist_stmt = $db->prepare($wishlist_query);
        $wishlist_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $wishlist_stmt->bindParam(':book_id', $book_id);
        $wishlist_stmt->execute();
        $in_wishlist = $wishlist_stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Table doesn't exist yet
    }
}

// Get user's review for this book
$user_review = null;
if (isLoggedIn()) {
    try {
        $user_review_query = "SELECT * FROM book_reviews WHERE book_id = :book_id AND user_id = :user_id";
        $user_review_stmt = $db->prepare($user_review_query);
        $user_review_stmt->bindParam(':book_id', $book_id);
        $user_review_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $user_review_stmt->execute();
        $user_review = $user_review_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist yet
    }
}

// Get all reviews
$reviews = [];
try {
    $reviews_query = "SELECT br.*, u.full_name, u.username FROM book_reviews br 
                       JOIN users u ON br.user_id = u.id 
                       WHERE br.book_id = :book_id 
                       ORDER BY br.created_at DESC";
    $reviews_stmt = $db->prepare($reviews_query);
    $reviews_stmt->bindParam(':book_id', $book_id);
    $reviews_stmt->execute();
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist yet
}

// Get book details
$query = "SELECT b.*, c.name as category_name FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE b.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $book_id);
$stmt->execute();
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header("Location: index.php");
    exit();
}

// Get average rating (if reviews table exists)
$avg_rating = 0;
$total_reviews = 0;
try {
    $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM book_reviews WHERE book_id = :book_id";
    $rating_stmt = $db->prepare($rating_query);
    $rating_stmt->bindParam(':book_id', $book_id);
    $rating_stmt->execute();
    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
    if ($rating_data && $rating_data['avg_rating'] !== null) {
        $avg_rating = round((float)$rating_data['avg_rating'], 1);
        $total_reviews = (int)$rating_data['total'];
    }
} catch (Exception $e) {
    // Table doesn't exist yet, will be created later
}

// Get related books (same category)
$related_query = "SELECT b.*, c.name as category_name FROM books b 
                  LEFT JOIN categories c ON b.category_id = c.id 
                  WHERE b.category_id = :category_id AND b.id != :book_id AND b.stock > 0 
                  LIMIT 4";
$related_stmt = $db->prepare($related_query);
$related_stmt->bindParam(':category_id', $book['category_id']);
$related_stmt->bindParam(':book_id', $book_id);
$related_stmt->execute();
$related_books = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - ReyBookstore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .book-image { max-height: 600px; object-fit: cover; border-radius: 8px; }
        .rating-stars { color: #ffc107; font-size: 1.5rem; }
        .related-book-card { transition: transform 0.3s; }
        .related-book-card:hover { transform: translateY(-5px); }
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Keranjang</a></li>
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

    <div class="container my-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php?category=<?php echo $book['category_id']; ?>"><?php echo htmlspecialchars($book['category_name']); ?></a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($book['title']); ?></li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-4 mb-4">
                <?php if ($book['image'] && file_exists($book['image'])): ?>
                    <img src="<?php echo htmlspecialchars($book['image']); ?>" class="img-fluid book-image w-100" alt="<?php echo htmlspecialchars($book['title']); ?>">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center book-image w-100">
                        <i class="bi bi-image" style="font-size: 128px; color: #ccc;"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <h1 class="mb-3"><?php echo htmlspecialchars($book['title']); ?></h1>
                <p class="text-muted fs-5 mb-2">oleh <strong><?php echo htmlspecialchars($book['author']); ?></strong></p>
                
                <div class="mb-3">
                    <span class="badge bg-info fs-6"><?php echo htmlspecialchars($book['category_name']); ?></span>
                    <?php if ($book['isbn']): ?>
                        <span class="badge bg-secondary fs-6">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($avg_rating > 0): ?>
                    <div class="mb-3">
                        <div class="rating-stars">
                            <?php 
                            $rounded_rating = round((float)$avg_rating);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="bi bi-star<?php echo $i <= $rounded_rating ? '-fill' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="ms-2"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> review<?php echo $total_reviews > 1 ? 's' : ''; ?>)</span>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <h2 class="text-primary mb-0">Rp <?php echo number_format($book['price'], 0, ',', '.'); ?></h2>
                    <p class="text-muted mb-0">
                        <?php if ($book['stock'] > 0): ?>
                            <span class="badge bg-success">Stok Tersedia: <?php echo $book['stock']; ?> unit</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Stok Habis</span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Deskripsi</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                    </div>
                </div>

                <?php if ($book['stock'] > 0): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Beli Buku Ini</h5>
                            <?php if (isLoggedIn()): ?>
                                <form method="POST" action="cart.php" class="d-flex gap-2 mb-2">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $book['stock']; ?>" class="form-control" style="max-width: 100px;">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                    </button>
                                </form>
                                <div class="d-grid">
                                    <?php if ($in_wishlist): ?>
                                        <a href="book_detail.php?id=<?php echo $book_id; ?>&remove_wishlist=1" class="btn btn-outline-danger">
                                            <i class="bi bi-heart-fill"></i> Hapus dari Wishlist
                                        </a>
                                    <?php else: ?>
                                        <a href="book_detail.php?id=<?php echo $book_id; ?>&add_wishlist=1" class="btn btn-outline-danger">
                                            <i class="bi bi-heart"></i> Tambah ke Wishlist
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary w-100">
                                    <i class="bi bi-box-arrow-in-right"></i> Login untuk Membeli
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #f0ad4e;"></i>
                        <p class="mt-3 text-muted">Buku ini sedang tidak tersedia.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="mt-5">
            <h3 class="mb-4">Review & Rating</h3>
            
            <?php if (isLoggedIn() && !$user_review): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Tulis Review</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-select" required>
                                    <option value="">Pilih Rating</option>
                                    <option value="5">5 - Sangat Baik</option>
                                    <option value="4">4 - Baik</option>
                                    <option value="3">3 - Cukup</option>
                                    <option value="2">2 - Kurang</option>
                                    <option value="1">1 - Sangat Kurang</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Review</label>
                                <textarea name="review" class="form-control" rows="4" placeholder="Tulis review Anda..."></textarea>
                            </div>
                            <button type="submit" name="add_review" class="btn btn-primary">Kirim Review</button>
                        </form>
                    </div>
                </div>
            <?php elseif (isLoggedIn() && $user_review): ?>
                <div class="text-center py-3 mb-4" style="background: #d1ecf1; border-radius: 8px;">
                    <i class="bi bi-info-circle" style="font-size: 24px; color: #5bc0de;"></i>
                    <p class="mb-0 mt-2">Anda sudah memberikan review untuk buku ini.</p>
                </div>
            <?php else: ?>
                <div class="text-center py-3 mb-4" style="background: #fff3cd; border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 24px; color: #f0ad4e;"></i>
                    <p class="mb-0 mt-2"><a href="login.php">Login</a> untuk menulis review.</p>
                </div>
            <?php endif; ?>
            
            <?php if (count($reviews) > 0): ?>
                <div class="row">
                    <?php foreach ($reviews as $review): ?>
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($review['full_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($review['username']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="rating-stars" style="font-size: 1rem;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    <?php if ($review['review']): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-info-circle" style="font-size: 48px; color: #5bc0de;"></i>
                    <p class="mt-3 text-muted">Belum ada review untuk buku ini.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($related_books) > 0): ?>
            <div class="mt-5">
                <h3 class="mb-4">Buku Terkait</h3>
                <div class="row">
                    <?php foreach ($related_books as $related): ?>
                        <div class="col-md-3 mb-4">
                            <div class="card related-book-card h-100">
                                <a href="book_detail.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                    <?php if ($related['image'] && file_exists($related['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($related['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($related['title']); ?>" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="bi bi-image" style="font-size: 48px; color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($related['title']); ?></h6>
                                        <p class="card-text text-muted small">oleh <?php echo htmlspecialchars($related['author']); ?></p>
                                        <p class="card-text text-primary fw-bold">Rp <?php echo number_format($related['price'], 0, ',', '.'); ?></p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-5">
        <p>&copy; 2024 ReyBookstore. All rights reserved.</p>
        <p>by Reyonl.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script>
        <?php if ($message): ?>
            <?php if ($message_type == 'success'): ?>
                showSuccess('<?php echo addslashes($message); ?>');
            <?php elseif ($message_type == 'danger'): ?>
                showError('<?php echo addslashes($message); ?>');
            <?php elseif ($message_type == 'warning'): ?>
                showWarning('<?php echo addslashes($message); ?>');
            <?php else: ?>
                showInfo('<?php echo addslashes($message); ?>');
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>

