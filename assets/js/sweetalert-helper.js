// SweetAlert Helper Functions

// Show success alert
function showSuccess(message, title = 'Berhasil!') {
    Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'OK'
    });
}

// Show error alert
function showError(message, title = 'Error!') {
    Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonColor: '#d33',
        confirmButtonText: 'OK'
    });
}

// Show warning alert
function showWarning(message, title = 'Peringatan!') {
    Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        confirmButtonColor: '#f0ad4e',
        confirmButtonText: 'OK'
    });
}

// Show info alert
function showInfo(message, title = 'Informasi') {
    Swal.fire({
        icon: 'info',
        title: title,
        text: message,
        confirmButtonColor: '#5bc0de',
        confirmButtonText: 'OK'
    });
}

// Show confirm dialog
function showConfirm(message, title = 'Konfirmasi', callback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya',
        cancelButtonText: 'Tidak'
    }).then((result) => {
        if (result.isConfirmed && callback) {
            callback();
        }
    });
}

// Auto show alert from URL parameters (only if not handled by page-specific scripts)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Only auto-show if page doesn't have custom handling
    if (urlParams.get('success') && !document.querySelector('script').textContent.includes('showSuccess')) {
        showSuccess('Operasi berhasil dilakukan!');
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]success=1/, '');
        window.history.replaceState({}, document.title, newUrl || window.location.pathname);
    }
    
    if (urlParams.get('deleted') && !document.querySelector('script').textContent.includes('showSuccess')) {
        showSuccess('Data berhasil dihapus!');
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]deleted=1/, '');
        window.history.replaceState({}, document.title, newUrl || window.location.pathname);
    }
    
    if (urlParams.get('removed') && !document.querySelector('script').textContent.includes('showSuccess')) {
        showSuccess('Berhasil dihapus dari wishlist!');
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]removed=1/, '');
        window.history.replaceState({}, document.title, newUrl || window.location.pathname);
    }
    
    if (urlParams.get('error') && !document.querySelector('script').textContent.includes('showError')) {
        showError(decodeURIComponent(urlParams.get('error')));
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]error=[^&]*/, '');
        window.history.replaceState({}, document.title, newUrl || window.location.pathname);
    }
});

