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

    // Fade in cards — skip cards already in viewport to prevent layout flash / skeleton effect
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
        const r = el.getBoundingClientRect();
        // Card is already visible in the viewport — show immediately, no flash
        if (r.top < window.innerHeight && r.bottom > 0) return;
        // Card is below the fold — animate in on scroll
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

    // Property filter tag management
    const filterForm = document.getElementById('propertyFilterForm');
    const activeFiltersContainer = document.getElementById('activeFilters');

    if (filterForm && activeFiltersContainer) {
        const updateActiveFilters = () => {
            activeFiltersContainer.innerHTML = '';
            const formData = new FormData(filterForm);
            const tags = [];

            for (const [key, value] of formData.entries()) {
                if (!value) continue;
                let label = '';

                switch (key) {
                    case 'location': label = `Location: ${value}`; break;
                    case 'price': {
                        const [min, max] = value.split(',');
                        label = `Price: ${Number(min).toLocaleString()} - ${Number(max).toLocaleString()}`;
                    } break;
                    case 'type': label = `Type: ${value}`; break;
                    case 'status': label = `Status: ${value}`; break;
                    case 'room_count_min': label = `Rooms ≥ ${value}`; break;
                    case 'room_count_max': label = `Rooms ≤ ${value}`; break;
                    case 'sort': label = `Sort: ${value.replace('_', ' ')}`; break;
                    case 'price_min': label = `Price min: ${value}`; break;
                    case 'price_max': label = `Price max: ${value}`; break;
                    default: continue;
                }

                tags.push({key, label, value});
            }

            if (!tags.length) {
                activeFiltersContainer.innerHTML = '<small class="text-muted">Chưa có bộ lọc nào được áp dụng</small>';
                return;
            }

            tags.forEach(tag => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-primary btn-sm me-1 mb-1';
                btn.innerHTML = `${tag.label} <span aria-hidden="true">&times;</span>`;
                btn.addEventListener('click', () => {
                    const control = filterForm.querySelector(`[name="${tag.key}"]`);
                    if (control) {
                        if (control.type === 'checkbox' || control.type === 'radio') {
                            control.checked = false;
                        } else {
                            control.value = '';
                        }
                        filterForm.submit();
                    }
                });
                activeFiltersContainer.appendChild(btn);
            });
        };

        filterForm.querySelectorAll('select, input').forEach(el => {
            if (el.name === 'type' || el.name === 'status' || el.name === 'price') {
                el.addEventListener('change', () => filterForm.submit());
            }
            if (el.name === 'location' || el.name === 'room_count_min' || el.name === 'room_count_max') {
                el.addEventListener('change', updateActiveFilters);
            }
        });

        const resetFilters = document.getElementById('resetFilters');
        if (resetFilters) {
            resetFilters.addEventListener('click', () => {
                filterForm.reset();
                window.location.href = location.pathname;
            });
        }

        updateActiveFilters();
    }

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

// Block room selection helpers
function updateSelectedRoomCount() {
    const count = document.querySelectorAll('.room-card.selected').length;
    const countEl = document.getElementById('selectedRoomCount');
    if (countEl) countEl.innerText = count;
}

function toggleRoomCardSelection(card) {
    if (!card || !card.classList.contains('room-card')) return;
    const input = card.querySelector('.room-select-input');
    if (!input) return;

    input.checked = !input.checked;
    card.classList.toggle('selected', input.checked);
    updateSelectedRoomCount();
}

function resetSelectedRooms() {
    document.querySelectorAll('.room-card.selected').forEach(card => {
        const input = card.querySelector('.room-select-input');
        if (input) input.checked = false;
        card.classList.remove('selected');
    });
    updateSelectedRoomCount();
}

function selectAllRooms() {
    document.querySelectorAll('.room-card').forEach(card => {
        const input = card.querySelector('.room-select-input');
        if (input) input.checked = true;
        card.classList.add('selected');
    });
    updateSelectedRoomCount();
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

// AI Chatbot
/** Safely render a limited subset of Markdown in AI bubbles (XSS-safe) */
function renderChatMarkdown(text) {
    return text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') // escape HTML first
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')                    // **bold**
        .replace(/\*(.*?)\*/g, '<em>$1</em>')                                // *italic*
        .replace(/\n/g, '<br>');                                             // newlines
}

function appendChatBubble(text, type = 'ai') {
    const body = document.getElementById('aiChatbotBody');
    if (!body) return;
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${type}`;
    if (type === 'ai') {
        bubble.innerHTML = renderChatMarkdown(text);   // AI: render markdown
    } else {
        bubble.textContent = text;                     // User: plain text only (XSS safe)
    }
    body.appendChild(bubble);
    body.scrollTop = body.scrollHeight;
}

function appendChatTyping(show = true) {
    const body = document.getElementById('aiChatbotBody');
    if (!body) return;
    let el = document.getElementById('chatbotTyping');
    if (show) {
        if (!el) {
            el = document.createElement('div');
            el.id = 'chatbotTyping';
            el.className = 'chatbot-typing';
            el.innerHTML = '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>';
            body.appendChild(el);
        }
    } else {
        if (el) el.remove();
    }
    body.scrollTop = body.scrollHeight;
}

function renderRoomSuggestions(rooms) {
    const body = document.getElementById('aiChatbotBody');
    if (!body || !rooms || !rooms.length) return;
    rooms.forEach(room => {
        const card = document.createElement('a');
        card.className = 'chatbot-room-card';
        card.href = `${APP_BASE_URL || ''}${room.url}`;
        card.target = '_blank';
        card.innerHTML = `
            <img src="${room.image}" alt="${room.title}">
            <div class="chatbot-room-info">
                <div class="chatbot-room-title">${room.title}</div>
                <div class="chatbot-room-price">${room.price} đ/tháng</div>
                <div class="chatbot-room-address">${room.address}</div>
            </div>
        `;
        body.appendChild(card);
    });
    body.scrollTop = body.scrollHeight;
}

function renderQuickReplies(items) {
    const container = document.getElementById('chatbotQuickReplies');
    if (!container) return;
    container.innerHTML = '';
    if (!items || !items.length) return;
    items.forEach(item => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'quick-reply-btn';
        btn.innerText = item.label;
        btn.addEventListener('click', () => {
            document.getElementById('chatbotInput').value = item.value;
            sendChatbotMessage(item.value);
        });
        container.appendChild(btn);
    });
}

async function sendChatbotMessage(message) {
    if (!message || !message.trim()) return;
    appendChatBubble(message, 'user');
    document.getElementById('chatbotInput').value = '';
    appendChatTyping(true);

    try {
        const res = await fetch(`${APP_BASE_URL}/api/chatbot`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `message=${encodeURIComponent(message)}`
        });
        const data = await res.json();
        appendChatTyping(false);

        if (data.answer) appendChatBubble(data.answer, 'ai');
        if (Array.isArray(data.rooms) && data.rooms.length) {
            renderRoomSuggestions(data.rooms);
        }
        renderQuickReplies(data.quick_replies || []);

    } catch (error) {
        appendChatTyping(false);
        appendChatBubble('Xin lỗi, không thể kết nối lúc này. Vui lòng thử lại sau.', 'ai');
    }
}

function initChatbotWidget() {
    const widget = document.getElementById('aiChatbot');
    const toggle = document.getElementById('chatbotToggle');
    if (!widget || !toggle) return;

    widget.classList.add('closed');
    widget.setAttribute('aria-hidden', 'true');
    toggle.style.display = 'flex';

    const btnMinimize = document.getElementById('chatbotMinimize');
    const btnClose = document.getElementById('chatbotClose');
    const form = document.getElementById('chatbotForm');
    const input = document.getElementById('chatbotInput');

    const openChatbot = () => {
        widget.classList.add('open');
        widget.classList.remove('closed');
        widget.setAttribute('aria-hidden', 'false');
        toggle.style.display = 'none';
    };

    const closeChatbot = () => {
        widget.classList.remove('open');
        widget.classList.add('closed');
        widget.setAttribute('aria-hidden', 'true');
        toggle.style.display = 'flex';
    };

    toggle.addEventListener('click', openChatbot);

    if (btnMinimize) btnMinimize.addEventListener('click', closeChatbot);
    if (btnClose) btnClose.addEventListener('click', closeChatbot);

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = input.value.trim();
            if (text) sendChatbotMessage(text);
        });
    }

    // Suggestion defaults
    renderQuickReplies([
        { label: 'Dưới 2 triệu', value: 'phòng dưới 2 triệu' },
        { label: 'Có máy lạnh', value: 'phòng có máy lạnh' },
        { label: 'Gần trung tâm', value: 'phòng gần trung tâm' },
        { label: 'Ở ghép', value: 'ký túc xá ở ghép giá rẻ' }
    ]);
}

document.addEventListener('DOMContentLoaded', initChatbotWidget);
