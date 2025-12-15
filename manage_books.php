<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireLogin();

// Only staff and admin can access
if (!isStaff() && !isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle Add/Edit Book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category_id = $_POST['category_id'];
    $description = $_POST['description'];
    // Format price - remove dots and convert to number
    $price = str_replace('.', '', $_POST['price']);
    $price = preg_replace('/[^0-9]/', '', $price); // Remove non-numeric characters
    $stock = $_POST['stock'];
    
    // Validation
    if (empty($title) || empty($author) || empty($category_id) || empty($price) || $stock < 0) {
        $message = 'Semua field wajib diisi dan stok tidak boleh negatif!';
        $message_type = 'danger';
    } else {
        try {
            $image_path = null;
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['image']['type'];
                $file_size = $_FILES['image']['size'];
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.');
                }
                
                if ($file_size > 5 * 1024 * 1024) { // 5MB max
                    throw new Exception('Ukuran file terlalu besar. Maksimal 5MB.');
                }
                
                $upload_dir = 'uploads/books/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $file_name = 'book_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                    $image_path = $file_path;
                    
                    // Delete old image if updating
                    if (isset($_POST['book_id']) && $_POST['book_id']) {
                        $old_query = "SELECT image FROM books WHERE id = :id";
                        $old_stmt = $db->prepare($old_query);
                        $old_stmt->bindParam(':id', $_POST['book_id']);
                        $old_stmt->execute();
                        $old_book = $old_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old_book && $old_book['image'] && file_exists($old_book['image'])) {
                            unlink($old_book['image']);
                        }
                    }
                } else {
                    throw new Exception('Gagal mengupload gambar.');
                }
            } elseif (isset($_POST['book_id']) && $_POST['book_id']) {
                // Keep existing image if not uploading new one
                $old_query = "SELECT image FROM books WHERE id = :id";
                $old_stmt = $db->prepare($old_query);
                $old_stmt->bindParam(':id', $_POST['book_id']);
                $old_stmt->execute();
                $old_book = $old_stmt->fetch(PDO::FETCH_ASSOC);
                if ($old_book) {
                    $image_path = $old_book['image'];
                }
            }
            
            // Handle image from URL (Google Books)
            if (isset($_POST['book_image_url']) && !empty($_POST['book_image_url']) && !isset($_FILES['image'])) {
                $image_url = trim($_POST['book_image_url']);
                $upload_dir = 'uploads/books/';
                
                // Ensure directory exists and is writable
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        $message = 'Gagal membuat direktori upload.';
                        $message_type = 'danger';
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($upload_dir)) {
                    chmod($upload_dir, 0777);
                }
                
                // Function to download image with cURL fallback
                $image_data = false;
                $download_error = '';
                
                // Clean and validate URL
                $image_url = filter_var($image_url, FILTER_SANITIZE_URL);
                if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $download_error = 'URL gambar tidak valid.';
                } else {
                    // Try cURL first (more reliable)
                    if (function_exists('curl_init')) {
                        $ch = curl_init($image_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                        curl_setopt($ch, CURLOPT_REFERER, 'https://books.google.com/');
                        $image_data = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($http_code !== 200 || $image_data === false) {
                            $image_data = false;
                            $download_error = $curl_error ?: "HTTP Code: $http_code";
                        }
                    }
                    
                    // Fallback to file_get_contents if cURL failed
                    if ($image_data === false && ini_get('allow_url_fopen')) {
                        $context = stream_context_create([
                            'http' => [
                                'method' => 'GET',
                                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                                           "Referer: https://books.google.com/\r\n",
                                'timeout' => 30,
                                'follow_location' => true,
                                'ignore_errors' => true
                            ],
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false
                            ]
                        ]);
                        $image_data = @file_get_contents($image_url, false, $context);
                        if ($image_data === false) {
                            $download_error = 'file_get_contents gagal';
                        }
                    }
                }
                
                if ($image_data !== false && strlen($image_data) > 0) {
                    // Validate that it's actually an image
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $image_data);
                    finfo_close($finfo);
                    
                    $valid_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mime_type, $valid_mime_types)) {
                        $image_data = false;
                        $download_error = 'File yang diunduh bukan gambar yang valid.';
                    } else {
                        // Determine file extension from MIME type
                        $file_extension = 'jpg'; // default
                        switch ($mime_type) {
                            case 'image/jpeg':
                            case 'image/jpg':
                                $file_extension = 'jpg';
                                break;
                            case 'image/png':
                                $file_extension = 'png';
                                break;
                            case 'image/gif':
                                $file_extension = 'gif';
                                break;
                            case 'image/webp':
                                $file_extension = 'webp';
                                break;
                        }
                        
                        $file_name = 'book_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        if (file_put_contents($file_path, $image_data)) {
                            $image_path = $file_path;
                        } else {
                            // Log error but don't stop the process
                            error_log("Failed to save image to: " . $file_path . " - Directory writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
                            $message = 'Buku berhasil disimpan, namun gambar tidak berhasil disimpan ke server. Silakan upload gambar secara manual.';
                            $message_type = 'warning';
                        }
                    }
                }
                
                if ($image_data === false && empty($image_path)) {
                    // Log error but don't stop the process
                    error_log("Failed to download image from: " . $image_url . " - Error: " . $download_error);
                    $message = 'Buku berhasil disimpan, namun gambar tidak berhasil diunduh dari Google Books. Silakan upload gambar secara manual.';
                    $message_type = 'warning';
                }
            }
            
            $isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : null;
            if (empty($isbn)) {
                $isbn = null;
            }
            
            // Check if isbn column exists
            $has_isbn_column = false;
            try {
                $check_query = "SHOW COLUMNS FROM books LIKE 'isbn'";
                $check_stmt = $db->query($check_query);
                $has_isbn_column = $check_stmt->rowCount() > 0;
            } catch (Exception $e) {
                $has_isbn_column = false;
            }
            
            if (isset($_POST['book_id']) && !empty($_POST['book_id'])) {
                // Update existing book
                $book_id = $_POST['book_id'];
                if ($image_path) {
                    if ($has_isbn_column) {
                        $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock, image=:image, isbn=:isbn WHERE id=:id";
                    } else {
                        $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock, image=:image WHERE id=:id";
                    }
                } else {
                    if ($has_isbn_column) {
                        $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock, isbn=:isbn WHERE id=:id";
                    } else {
                        $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock WHERE id=:id";
                    }
                }
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $book_id);
                if ($image_path) {
                    $stmt->bindParam(':image', $image_path);
                }
                if ($has_isbn_column) {
                    $stmt->bindParam(':isbn', $isbn);
                }
            } else {
                // Insert new book
                if ($has_isbn_column) {
                    $query = "INSERT INTO books (title, author, category_id, description, price, stock, image, isbn, created_at) VALUES (:title, :author, :category_id, :description, :price, :stock, :image, :isbn, NOW())";
                } else {
                    $query = "INSERT INTO books (title, author, category_id, description, price, stock, image, created_at) VALUES (:title, :author, :category_id, :description, :price, :stock, :image, NOW())";
                }
                $stmt = $db->prepare($query);
                $stmt->bindParam(':image', $image_path);
                if ($has_isbn_column) {
                    $stmt->bindParam(':isbn', $isbn);
                }
            }
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->execute();
            
            // Redirect with appropriate message
            if ($message_type == 'warning') {
                header("Location: manage_books.php?warning=" . urlencode($message));
            } else {
                header("Location: manage_books.php?success=1");
            }
            exit();
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Handle Delete Book
if (isset($_GET['delete'])) {
    try {
        // Get book image path before deleting
        $query = "SELECT image FROM books WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['delete']);
        $stmt->execute();
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete book
        $query = "DELETE FROM books WHERE id = :id";
$stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['delete']);
$stmt->execute();
        
        // Delete image file if exists
        if ($book && $book['image'] && file_exists($book['image'])) {
            unlink($book['image']);
        }
        
        header("Location: manage_books.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get all books with category
$query = "SELECT b.*, c.name as category_name FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          ORDER BY b.created_at DESC";
$stmt = $db->query($query);

// Get all categories for dropdown
$query_cat = "SELECT * FROM categories ORDER BY name";
$stmt_cat = $db->query($query_cat);
$categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Get book for edit
$edit_book = null;
if (isset($_GET['edit'])) {
    $query = "SELECT * FROM books WHERE id = :id";
    $stmt_edit = $db->prepare($query);
    $stmt_edit->bindParam(':id', $_GET['edit']);
    $stmt_edit->execute();
    $edit_book = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku - ReyBookstore</title>
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
            <div class="ms-auto d-flex align-items-center">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                    <i class="bi bi-moon-fill" id="themeIcon"></i>
                </button>
                <a href="dashboard.php" class="btn btn-outline-light me-2 ms-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2><i class="bi bi-book"></i> Kelola Buku</h2>
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#bookModal" onclick="clearForm();">
            <i class="bi bi-plus-circle"></i> Tambah Buku
        </button>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <!-- <th>ID</th> -->
                        <th>Gambar</th>
                        <th>Judul</th>
                        <th>Penulis</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($book = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <!-- <td><?php echo $book['id']; ?></td> -->
                            <td>
                                <?php if ($book['image'] && file_exists($book['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" style="width: 60px; height: 80px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                        <i class="bi bi-image" style="font-size: 24px; color: #999;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($book['category_name'] ?: 'Tidak ada kategori'); ?></span>
                            </td>
                            <td>Rp <?php echo number_format($book['price'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if ($book['stock'] > 0): ?>
                                    <span class="badge bg-success"><?php echo $book['stock']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Habis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="manage_books.php?edit=<?php echo $book['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="manage_books.php?delete=<?php echo $book['id']; ?>" class="btn btn-sm btn-danger delete-book-btn" data-book-title="<?php echo htmlspecialchars($book['title']); ?>">
                                    <i class="bi bi-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="bookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="bookForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo $edit_book ? 'Edit' : 'Tambah'; ?> Buku</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="book_id" id="book_id" value="<?php echo $edit_book ? $edit_book['id'] : ''; ?>">
                        <input type="hidden" name="book_image_url" id="book_image_url" value="">
                        
                        <?php if (!$edit_book): ?>
                        <!-- Tabs for Search vs Manual Input -->
                        <ul class="nav nav-tabs mb-3" id="bookInputTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="search-tab" data-bs-toggle="tab" data-bs-target="#search-panel" type="button" role="tab">
                                    <i class="bi bi-search"></i> Cari dari Google Books
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-panel" type="button" role="tab">
                                    <i class="bi bi-pencil"></i> Input Manual
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="bookInputTabsContent">
                            <!-- Search Panel -->
                            <div class="tab-pane fade show active" id="search-panel" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label">Cari Buku</label>
                                    <div class="input-group">
                                        <input type="text" id="book_search" class="form-control" placeholder="Masukkan judul buku atau ISBN...">
                                        <button type="button" class="btn btn-primary" onclick="searchBook()">
                                            <i class="bi bi-search"></i> Cari
                                        </button>
                                    </div>
                                    <small class="text-muted">Cari buku dari Google Books API</small>
                                </div>
                                
                                <div id="search_results" class="mt-3" style="max-height: 400px; overflow-y: auto;"></div>
                            </div>
                            
                            <!-- Manual Input Panel -->
                            <div class="tab-pane fade" id="manual-panel" role="tabpanel">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Gambar Buku</label>
                            <div id="book_image_preview" class="mb-2"></div>
                            <?php if ($edit_book && $edit_book['image'] && file_exists($edit_book['image'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($edit_book['image']); ?>" alt="Current image" style="max-width: 200px; max-height: 250px; border-radius: 4px; border: 1px solid #ddd;">
                                    <p class="text-muted small mt-1">Gambar saat ini</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small class="text-muted">Format: JPG, PNG, GIF, WEBP. Maksimal 5MB</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Judul Buku <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control" value="<?php echo $edit_book ? htmlspecialchars($edit_book['title']) : ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Penulis <span class="text-danger">*</span></label>
                                <input type="text" name="author" id="author" class="form-control" value="<?php echo $edit_book ? htmlspecialchars($edit_book['author']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select name="category_id" id="category_id" class="form-select" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_book && $edit_book['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Harga <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" name="price" id="price" class="form-control" value="<?php echo $edit_book ? number_format($edit_book['price'], 0, ',', '.') : ''; ?>" placeholder="0" required>
                                </div>
                                <small class="text-muted">Format: 100000 atau 100.000</small>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Stok <span class="text-danger">*</span></label>
                                <input type="number" name="stock" id="stock" class="form-control" value="<?php echo $edit_book ? $edit_book['stock'] : ''; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" id="description" class="form-control" rows="4"><?php echo $edit_book ? htmlspecialchars($edit_book['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ISBN (Opsional)</label>
                            <input type="text" name="isbn" id="isbn" class="form-control" value="<?php echo $edit_book ? htmlspecialchars($edit_book['isbn'] ?? '') : ''; ?>" placeholder="ISBN buku">
                        </div>
                        
                        <?php if (!$edit_book): ?>
                            </div> <!-- End manual-panel -->
                        </div> <!-- End tab-content -->
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="save_book" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script>
        // Format Rupiah untuk input harga
        document.addEventListener('DOMContentLoaded', function() {
            const priceInput = document.getElementById('price');
            if (priceInput) {
                // Format saat input
                priceInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^0-9]/g, '');
                    if (value) {
                        value = parseInt(value).toLocaleString('id-ID');
                        e.target.value = value;
                    }
                });
                
                // Format saat blur (ketika keluar dari input)
                priceInput.addEventListener('blur', function(e) {
                    let value = e.target.value.replace(/[^0-9]/g, '');
                    if (value) {
                        value = parseInt(value).toLocaleString('id-ID');
                        e.target.value = value;
                    }
                });
                
                // Format saat focus (ketika masuk ke input)
                priceInput.addEventListener('focus', function(e) {
                    let value = e.target.value.replace(/[^0-9]/g, '');
                    if (value) {
                        e.target.value = value;
                    }
                });
            }
        });
        
        // Format price sebelum submit
        document.getElementById('bookForm')?.addEventListener('submit', function(e) {
            const priceInput = document.getElementById('price');
            if (priceInput) {
                // Remove dots before submit
                priceInput.value = priceInput.value.replace(/\./g, '');
            }
        });
    </script>
    <script>
        // Show alerts
        <?php if (isset($_GET['success'])): ?>
            showSuccess('Buku berhasil disimpan!');
        <?php endif; ?>
        
        <?php if (isset($_GET['warning'])): ?>
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: '<?php echo addslashes(urldecode($_GET['warning'])); ?>',
                confirmButtonColor: '#f0ad4e'
            });
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            showSuccess('Buku berhasil dihapus!');
        <?php endif; ?>
        
        <?php if ($message): ?>
            <?php if ($message_type == 'success'): ?>
                showSuccess('<?php echo addslashes($message); ?>');
            <?php elseif ($message_type == 'warning'): ?>
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: '<?php echo addslashes($message); ?>',
                    confirmButtonColor: '#f0ad4e'
                });
            <?php else: ?>
                showError('<?php echo addslashes($message); ?>');
            <?php endif; ?>
        <?php endif; ?>
        
        // Handle delete with SweetAlert
        document.querySelectorAll('.delete-book-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const bookTitle = this.getAttribute('data-book-title');
                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    text: 'Yakin hapus buku "' + bookTitle + '"?',
                    icon: 'warning',
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
        
        function clearForm() {
            // Only clear form when adding new book
            document.getElementById('book_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('author').value = '';
            document.getElementById('category_id').value = '';
            document.getElementById('price').value = '';
            document.getElementById('stock').value = '';
            document.getElementById('description').value = '';
            document.getElementById('image').value = '';
            document.getElementById('isbn').value = '';
            document.getElementById('book_image_url').value = '';
            document.getElementById('book_image_preview').innerHTML = '';
            document.getElementById('search_results').innerHTML = '';
            document.getElementById('book_search').value = '';
            // Clear image preview if exists
            var imgContainer = document.querySelector('#bookModal .mb-2');
            if (imgContainer && imgContainer.querySelector('img')) {
                imgContainer.remove();
            }
            // Update modal title
            document.querySelector('#bookModal .modal-title').textContent = 'Tambah Buku';
            // Switch to search tab
            if (document.getElementById('search-tab')) {
                document.getElementById('search-tab').click();
            }
        }
        
        // Search book from Google Books API
        function searchBook() {
            const searchTerm = document.getElementById('book_search').value.trim();
            const resultsDiv = document.getElementById('search_results');
            
            if (!searchTerm) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Masukkan judul buku atau ISBN untuk mencari'
                });
                return;
            }
            
            resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Mencari buku...</p></div>';
            
            // Google Books API
            const apiUrl = `https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(searchTerm)}&maxResults=10&langRestrict=id`;
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.items && data.items.length > 0) {
                        let html = '<div class="list-group">';
                        data.items.forEach((item, index) => {
                            const volumeInfo = item.volumeInfo;
                            const title = volumeInfo.title || 'Tidak ada judul';
                            const authors = volumeInfo.authors ? volumeInfo.authors.join(', ') : 'Tidak ada penulis';
                            const description = volumeInfo.description ? volumeInfo.description.substring(0, 150) + '...' : 'Tidak ada deskripsi';
                            const thumbnail = volumeInfo.imageLinks ? volumeInfo.imageLinks.thumbnail : '';
                            const isbn = volumeInfo.industryIdentifiers ? volumeInfo.industryIdentifiers.find(id => id.type === 'ISBN_13' || id.type === 'ISBN_10') : null;
                            const isbnValue = isbn ? isbn.identifier : '';
                            
                            html += `
                                <div class="list-group-item list-group-item-action" onclick="selectBook(${index})" style="cursor: pointer;">
                                    <div class="d-flex">
                                        ${thumbnail ? `<img src="${thumbnail}" alt="${title}" style="width: 80px; height: 120px; object-fit: cover; margin-right: 15px; border-radius: 4px;">` : '<div style="width: 80px; height: 120px; background: #f0f0f0; margin-right: 15px; border-radius: 4px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-image" style="font-size: 32px; color: #999;"></i></div>'}
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">${title}</h6>
                                            <p class="mb-1 text-muted"><small>oleh ${authors}</small></p>
                                            ${isbnValue ? `<p class="mb-1"><small class="badge bg-secondary">ISBN: ${isbnValue}</small></p>` : ''}
                                            <p class="mb-0 small">${description}</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Store book data
                            window.bookResults = window.bookResults || [];
                            let imageUrlLarge = '';
                            if (volumeInfo.imageLinks) {
                                // Try to get larger image - Google Books API provides different sizes
                                if (volumeInfo.imageLinks.large) {
                                    imageUrlLarge = volumeInfo.imageLinks.large.replace('http://', 'https://');
                                } else if (volumeInfo.imageLinks.medium) {
                                    imageUrlLarge = volumeInfo.imageLinks.medium.replace('http://', 'https://');
                                } else if (volumeInfo.imageLinks.small) {
                                    imageUrlLarge = volumeInfo.imageLinks.small.replace('http://', 'https://');
                                } else if (volumeInfo.imageLinks.thumbnail) {
                                    // For thumbnail, try to get better quality by modifying URL
                                    let thumbUrl = volumeInfo.imageLinks.thumbnail.replace('http://', 'https://');
                                    // Remove edge=curl parameter and set zoom=0 for better quality
                                    thumbUrl = thumbUrl.replace(/[?&]edge=curl/g, '');
                                    thumbUrl = thumbUrl.replace('zoom=1', 'zoom=0');
                                    // If no zoom parameter, add it
                                    if (!thumbUrl.includes('zoom=')) {
                                        thumbUrl += (thumbUrl.includes('?') ? '&' : '?') + 'zoom=0';
                                    }
                                    imageUrlLarge = thumbUrl;
                                } else if (volumeInfo.imageLinks.smallThumbnail) {
                                    let smallThumb = volumeInfo.imageLinks.smallThumbnail.replace('http://', 'https://');
                                    smallThumb = smallThumb.replace(/[?&]edge=curl/g, '');
                                    smallThumb = smallThumb.replace('zoom=1', 'zoom=0');
                                    if (!smallThumb.includes('zoom=')) {
                                        smallThumb += (smallThumb.includes('?') ? '&' : '?') + 'zoom=0';
                                    }
                                    imageUrlLarge = smallThumb;
                                }
                            }
                            
                            window.bookResults[index] = {
                                title: title,
                                author: authors,
                                description: volumeInfo.description || '',
                                isbn: isbnValue,
                                imageUrl: thumbnail ? thumbnail.replace('http://', 'https://') : '',
                                imageUrlLarge: imageUrlLarge
                            };
                        });
                        html += '</div>';
                        resultsDiv.innerHTML = html;
                    } else {
                        resultsDiv.innerHTML = '<div class="alert alert-warning">Tidak ada buku ditemukan. Coba dengan kata kunci lain.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsDiv.innerHTML = '<div class="alert alert-danger">Error saat mencari buku. Silakan coba lagi.</div>';
                });
        }
        
        // Select book from search results
        function selectBook(index) {
            const book = window.bookResults[index];
            if (!book) return;
            
            // Fill form
            document.getElementById('title').value = book.title;
            document.getElementById('author').value = book.author;
            document.getElementById('description').value = book.description;
            document.getElementById('isbn').value = book.isbn || '';
            
            // Set image URL
            if (book.imageUrlLarge) {
                document.getElementById('book_image_url').value = book.imageUrlLarge;
                // Show preview
                const previewDiv = document.getElementById('book_image_preview');
                previewDiv.innerHTML = `
                    <img src="${book.imageUrlLarge}" alt="${book.title}" style="max-width: 200px; max-height: 250px; border-radius: 4px; border: 1px solid #ddd;">
                    <p class="text-muted small mt-1">Gambar dari Google Books</p>
                `;
            }
            
            // Switch to manual tab to show filled form
            if (document.getElementById('manual-tab')) {
                document.getElementById('manual-tab').click();
            }
            
            // Focus on price field
            document.getElementById('price').focus();
            
            Swal.fire({
                icon: 'success',
                title: 'Buku Dipilih!',
                text: 'Data buku sudah terisi. Silakan tambahkan harga dan stok.',
                timer: 2000,
                showConfirmButton: false
            });
        }
        
        // Allow Enter key to search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('book_search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchBook();
                    }
                });
            }
        });
        
        // Handle modal events
        var bookModal = document.getElementById('bookModal');
        if (bookModal) {
            bookModal.addEventListener('hidden.bs.modal', function () {
                // Remove edit parameter from URL when modal is closed
                if (window.location.search.includes('edit=')) {
                    var url = new URL(window.location);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }
            });
        }
    </script>
    <script src="assets/js/dark-mode.js"></script>
    <?php if ($edit_book): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure form has correct values (they should already be set from PHP)
            var bookId = document.getElementById('book_id');
            if (bookId && bookId.value == '') {
                // If book_id is empty, set it from edit_book
                bookId.value = '<?php echo $edit_book['id']; ?>';
            }
            
            // Show modal with edit data (data already loaded from PHP)
            var modalElement = document.getElementById('bookModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
