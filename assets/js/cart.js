// JavaScript для корзины и быстрого просмотра товаров

document.addEventListener('DOMContentLoaded', function() {
    // CSRF токен из meta тега
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || getCsrfFromCookie();
    
    // Функция получения CSRF токена из cookie
    function getCsrfFromCookie() {
        const name = 'csrf_token=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i].trim();
            if (c.indexOf(name) === 0) {
                return c.substring(name.length);
            }
        }
        return '';
    }
    
    // Обновление счетчика корзины
    function updateCartCount(count) {
        const cartCountEl = document.querySelector('.cart-count');
        if (cartCountEl) {
            cartCountEl.textContent = count;
            if (count > 0) {
                cartCountEl.style.display = 'inline-block';
            } else {
                cartCountEl.style.display = 'none';
            }
        }
    }
    
    // Показ уведомления
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('toast-show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Анимация корзины
    function animateCart() {
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            cartIcon.classList.add('cart-shake');
            setTimeout(() => cartIcon.classList.remove('cart-shake'), 500);
        }
    }
    
    // Добавление в корзину через AJAX
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const productId = this.dataset.productId;
            const quantity = 1;
            
            fetch('ajax_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cart_count);
                    animateCart();
                    showToast(data.message);
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Ошибка при добавлении в корзину', 'error');
            });
        });
    });
    
    // Управление корзиной на странице cart.php
    const cartContainer = document.querySelector('.cart-items');
    if (cartContainer) {
        // Изменение количества
        cartContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('qty-plus') || e.target.classList.contains('qty-minus')) {
                const row = e.target.closest('.cart-item');
                const productId = row.dataset.productId;
                const qtyInput = row.querySelector('.qty-input');
                let quantity = parseInt(qtyInput.value);
                
                if (e.target.classList.contains('qty-plus')) {
                    quantity++;
                } else if (e.target.classList.contains('qty-minus') && quantity > 1) {
                    quantity--;
                }
                
                updateCartItem(productId, quantity, row);
            }
            
            // Удаление товара
            if (e.target.classList.contains('remove-item')) {
                const row = e.target.closest('.cart-item');
                const productId = row.dataset.productId;
                
                if (confirm('Удалить этот товар из корзины?')) {
                    updateCartItem(productId, 0, row);
                }
            }
        });
        
        // Очистка корзины
        const clearCartBtn = document.querySelector('.clear-cart-btn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (confirm('Вы уверены, что хотите очистить корзину?')) {
                    fetch('ajax_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=clear&csrf_token=${encodeURIComponent(csrfToken)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateCartCount(0);
                            window.location.reload();
                        }
                    });
                }
            });
        }
    }
    
    // Обновление элемента корзины
    function updateCartItem(productId, quantity, row) {
        fetch('ajax_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update&product_id=${productId}&quantity=${quantity}&csrf_token=${encodeURIComponent(csrfToken)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (quantity === 0) {
                    row.remove();
                    const remaining = document.querySelectorAll('.cart-item');
                    if (remaining.length === 0) {
                        window.location.reload();
                    }
                } else {
                    // Обновляем сумму позиции
                    const price = parseFloat(row.dataset.price);
                    const totalEl = row.querySelector('.item-total');
                    if (totalEl) {
                        totalEl.textContent = (price * quantity).toFixed(2) + ' ₽';
                    }
                    
                    // Обновляем общую сумму
                    updateCartTotal();
                }
                
                updateCartCount(data.cart_count);
                if (data.message !== 'Товар удален') {
                    showToast(data.message);
                }
            } else {
                showToast(data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Ошибка при обновлении корзины', 'error');
        });
    }
    
    // Обновление общей суммы корзины
    function updateCartTotal() {
        let total = 0;
        document.querySelectorAll('.cart-item').forEach(row => {
            const price = parseFloat(row.dataset.price);
            const quantity = parseInt(row.querySelector('.qty-input').value);
            total += price * quantity;
        });
        
        const totalEl = document.querySelector('.cart-total-amount');
        if (totalEl) {
            totalEl.textContent = total.toFixed(2) + ' ₽';
        }
    }
    
    // Быстрый просмотр товара (модальное окно)
    const quickViewModal = document.getElementById('quickViewModal');
    if (quickViewModal) {
        document.querySelectorAll('.quick-view-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const productId = this.dataset.productId;
                
                fetch(`catalog.php?quick_view=${productId}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    const modalContent = quickViewModal.querySelector('.modal-content-inner');
                    if (modalContent) {
                        modalContent.innerHTML = html;
                        quickViewModal.classList.add('modal-active');
                        document.body.style.overflow = 'hidden';
                    }
                });
            });
        });
        
        // Закрытие модального окна
        quickViewModal.querySelector('.modal-close')?.addEventListener('click', closeModal);
        quickViewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        function closeModal() {
            quickViewModal.classList.remove('modal-active');
            document.body.style.overflow = '';
        }
    }
    
    // Поиск и фильтрация с AJAX
    const searchInput = document.getElementById('search-products');
    const priceMinInput = document.getElementById('price-min');
    const priceMaxInput = document.getElementById('price-max');
    const categorySelect = document.getElementById('category-filter');
    
    if (searchInput || priceMinInput || priceMaxInput || categorySelect) {
        let debounceTimer;
        
        [searchInput, priceMinInput, priceMaxInput, categorySelect].forEach(input => {
            if (input) {
                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(applyFilters, 500);
                });
            }
        });
        
        function applyFilters() {
            const search = searchInput?.value || '';
            const priceMin = priceMinInput?.value || '';
            const priceMax = priceMaxInput?.value || '';
            const category = categorySelect?.value || '';
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (priceMin) params.append('min_price', priceMin);
            if (priceMax) params.append('max_price', priceMax);
            if (category) params.append('category', category);
            
            fetch(`catalog.php?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                const productsGrid = document.querySelector('.collection-grid');
                if (productsGrid) {
                    // Извлекаем только сетку товаров из ответа
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newGrid = doc.querySelector('.collection-grid');
                    if (newGrid) {
                        productsGrid.innerHTML = newGrid.innerHTML;
                        
                        // Переназначаем обработчики кнопок "В корзину"
                        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                
                                const productId = this.dataset.productId;
                                const quantity = 1;
                                
                                fetch('ajax_cart.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `action=add&product_id=${productId}&quantity=${quantity}&csrf_token=${encodeURIComponent(csrfToken)}`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        updateCartCount(data.cart_count);
                                        animateCart();
                                        showToast(data.message);
                                    } else {
                                        showToast(data.error, 'error');
                                    }
                                });
                            });
                        });
                    }
                }
            });
        }
    }
    
    // Проверка доступности товаров в корзине при загрузке
    if (document.querySelector('.cart-page')) {
        fetch('ajax_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=check&csrf_token=${encodeURIComponent(csrfToken)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.removed && data.removed.length > 0) {
                showToast(data.message, 'warning');
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    }
});
