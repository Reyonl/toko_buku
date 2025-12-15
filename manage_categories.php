<?php
require_once 'includes/session.php';
require_once 'config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    if (isset($_POST['category_id']) && $_POST['category_id']) {
        $category_id = $_POST['category_id'];
        $query = "UPDATE categories SET name=:name, description=:description WHERE id=:id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $category_id);
    } else {
        $query = "INSERT INTO categories (name, description) VALUES (:name, :description)";
        $stmt = $db->prepare($query);
    }
    
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->execute();
    
    header("Location: manage_categories.php?success=1");
    exit();
}

// Delete Category
if (isset($_GET['delete'])) {
    $query = "DELETE FROM categories WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['delete']);
    $stmt->execute();
    
    header("Location: manage_categories.php?deleted=1");
    exit();
}

// Get all categories
$query = "SELECT c.*, COUNT(b.id) as total_books FROM categories c LEFT JOIN books b ON c.id = b.category_id GROUP BY c.id ORDER BY c.name";
$stmt = $db->query($query);

// Get category for edit
$edit_category = null;
if (isset($_GET['edit'])) {
    $query = "SELECT * FROM categories WHERE id = :id";
    $stmt_edit = $db->prepare($query);
    $stmt_edit->bindParam(':id', $_GET['edit']);
    $stmt_edit->execute();
    $edit_category = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - ReyBookstore</title>
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
        <h2><i class="bi bi-tags"></i> Kelola Kategori</h2>
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="clearForm()">
            <i class="bi bi-plus-circle"></i> Tambah Kategori
        </button>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Total Buku</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description']); ?></td>
                            <td><?php echo $category['total_books']; ?> buku</td>
                            <td>
                                <a href="manage_categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($category['total_books'] == 0): ?>
                                    <a href="manage_categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger delete-category-btn" data-category-name="<?php echo htmlspecialchars($category['name']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo $edit_category ? 'Edit' : 'Tambah'; ?> Kategori</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="category_id" id="category_id" value="<?php echo $edit_category ? $edit_category['id'] : ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Kategori</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $edit_category ? htmlspecialchars($edit_category['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="save_category" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert-helper.js"></script>
    <script src="assets/js/dark-mode.js"></script>
    <script>
        // Show alerts
        <?php if (isset($_GET['success'])): ?>
            showSuccess('Kategori berhasil disimpan!');
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            showSuccess('Kategori berhasil dihapus!');
        <?php endif; ?>
        
        // Handle delete with SweetAlert
        document.querySelectorAll('.delete-category-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const categoryName = this.getAttribute('data-category-name');
                Swal.fire({
                    title: 'Konfirmasi Hapus',
                    text: 'Yakin hapus kategori "' + categoryName + '"?',
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
            document.querySelector('input[name="category_id"]').value = '';
            document.querySelector('input[name="name"]').value = '';
            document.querySelector('textarea[name="description"]').value = '';
        }
    </script>
    <?php if ($edit_category): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            modal.show();
        });
    </script>
    <?php endif; ?>
</body>
</html>