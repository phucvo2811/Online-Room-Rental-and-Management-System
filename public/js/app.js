const APP_BASE_URL = window.APP_URL || '';

document.addEventListener('DOMContentLoaded', () => {

    // Navbar scroll
    const navbar = document.getElementById('mainNavbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        }, { passive: true });
    }

    // Back to top
    const btnTop = document.getElementById('btnBackTop');
    if (btnTop) {
        window.addEventListener('scroll', () => {
            btnTop.classList.toggle('show', window.scrollY > 400);
        }, { passive: true });
        btnTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // Auto dismiss alerts
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(el => {
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(el)?.close(), 4000);
    });

    // Fade in cards
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                fadeObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.room-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        fadeObserver.observe(el);
    });

    // Form submit loading
    document.querySelectorAll('form').forEach(form => {
        const btn = form.querySelector('[type=submit]');
        if (btn) {
            btn.dataset.origText = btn.innerHTML;
            form.addEventListener('submit', () => {
                if (btn.dataset.noLoading) return;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
                setTimeout(() => { btn.disabled = false; btn.innerHTML = btn.dataset.origText; }, 8000);
            });
        }
    });

    // Gallery keyboard nav
    document.addEventListener('keydown', e => {
        const thumbs = [...document.querySelectorAll('.gallery-thumb')];
        if (!thumbs.length) return;
        const idx = thumbs.findIndex(t => t.classList.contains('active'));
        if (e.key === 'ArrowRight' && idx < thumbs.length - 1) thumbs[idx + 1].click();
        if (e.key === 'ArrowLeft'  && idx > 0)                 thumbs[idx - 1].click();
    });
});

// Toggle Favorite
async function toggleFav(btn, roomId) {
    try {
        const res = await fetch(`${APP_BASE_URL}/favorites/toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `room_id=${roomId}`
        });
        const data = await res.json();
        const icon = btn.querySelector('i');
        if (data.status === 'added') {
            icon.className = 'bi bi-heart-fill';
            btn.classList.add('active');
            showToast('Đã thêm vào yêu thích ❤️', 'success');
        } else {
            icon.className = 'bi bi-heart';
            btn.classList.remove('active');
            showToast('Đã xóa khỏi yêu thích', 'info');
        }
    } catch {
        showToast('Vui lòng đăng nhập để dùng tính năng này', 'warning');
    }
}

// Toast
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    const id = 'toast_' + Date.now();
    const icons = {
        success: 'check-circle-fill text-success',
        danger:  'exclamation-triangle-fill text-danger',
        warning: 'exclamation-circle-fill text-warning',
        info:    'info-circle-fill text-primary'
    };
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center border-0 shadow" role="alert">
            <div class="d-flex align-items-center gap-2 p-3">
                <i class="bi bi-${icons[type] || icons.info} fs-5"></i>
                <div class="me-auto fw-500 small">${message}</div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    const toastEl = document.getElementById(id);
    new bootstrap.Toast(toastEl, { delay: 3500 }).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}