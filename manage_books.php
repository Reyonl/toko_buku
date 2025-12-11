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
    $price = $_POST['price'];
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
            
            if (isset($_POST['book_id']) && !empty($_POST['book_id'])) {
                // Update existing book
                $book_id = $_POST['book_id'];
                if ($image_path) {
                    $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock, image=:image WHERE id=:id";
                } else {
                    $query = "UPDATE books SET title=:title, author=:author, category_id=:category_id, description=:description, price=:price, stock=:stock WHERE id=:id";
                }
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $book_id);
                if ($image_path) {
                    $stmt->bindParam(':image', $image_path);
                }
            } else {
                // Insert new book
                $query = "INSERT INTO books (title, author, category_id, description, price, stock, image, created_at) VALUES (:title, :author, :category_id, :description, :price, :stock, :image, NOW())";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':image', $image_path);
            }
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->execute();
            
            header("Location: manage_books.php?success=1");
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
    <title>Kelola Buku - Toko Buku</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Arvo:ital,wght@0,400;0,700;1,400;1,700&family=Elms+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book"></i> BookStore</a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2><i class="bi bi-book"></i> Kelola Buku</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Buku berhasil disimpan!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Buku berhasil dihapus!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#bookModal" onclick="clearForm();">
            <i class="bi bi-plus-circle"></i> Tambah Buku
        </button>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
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
                            <td><?php echo $book['id']; ?></td>
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
                                <a href="manage_books.php?delete=<?php echo $book['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus buku ini?')">
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
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo $edit_book ? 'Edit' : 'Tambah'; ?> Buku</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="book_id" id="book_id" value="<?php echo $edit_book ? $edit_book['id'] : ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Gambar Buku</label>
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
                                <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="price" id="price" class="form-control" value="<?php echo $edit_book ? $edit_book['price'] : ''; ?>" min="0" step="100" required>
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
    <script>
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
            // Clear image preview if exists
            var imgContainer = document.querySelector('#bookModal .mb-2');
            if (imgContainer && imgContainer.querySelector('img')) {
                imgContainer.remove();
            }
            // Update modal title
            document.querySelector('#bookModal .modal-title').textContent = 'Tambah Buku';
        }
        
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
