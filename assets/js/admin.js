jQuery(document).ready(function($) {
    let selectedReviews = [];

    // Cargar reseñas guardadas al iniciar
    loadSavedReviews();

    function loadSavedReviews() {
        $.ajax({
            url: reviewsManager.ajax_url,
            type: 'POST',
            data: {
                action: 'get_saved_reviews',
                nonce: reviewsManager.nonce
            },
            success: function(response) {
                if (response.success && response.data.reviews) {
                    selectedReviews = response.data.reviews;
                    updateSelectedReviewsList();
                } else {
                    showNotice('Error al cargar las reseñas guardadas.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar reseñas guardadas:', error);
                showNotice('Error al cargar las reseñas guardadas.', 'error');
            }
        });
    }

    // Cargar categorías al iniciar
    loadCategories();

    function loadCategories() {
        $.ajax({
            url: reviewsManager.ajax_url,
            type: 'POST',
            data: {
                action: 'get_categories',
                nonce: reviewsManager.nonce
            },
            success: function(response) {
                if (response.success && response.data.categories) {
                    const $categorySelect = $('#category-filter');
                    response.data.categories.forEach(category => {
                        $categorySelect.append(`<option value="${category.id}">${category.name}</option>`);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar categorías:', error);
            }
        });
    }

    // Evento cambio de categoría
    $('#category-filter').on('change', function() {
        const categoryId = $(this).val();
        const $productSelect = $('#product-filter');
        
        $productSelect.prop('disabled', true)
            .empty()
            .append('<option value="">Seleccionar Producto</option>');
        
        if (!categoryId) {
            return;
        }

        $.ajax({
            url: reviewsManager.ajax_url,
            type: 'POST',
            data: {
                action: 'get_products',
                nonce: reviewsManager.nonce,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success && response.data.products) {
                    response.data.products.forEach(product => {
                        $productSelect.append(`<option value="${product.id}">${product.name}</option>`);
                    });
                    $productSelect.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar productos:', error);
            }
        });
    });

    // Evento cambio de producto
    $('#product-filter').on('change', function() {
        const productId = $(this).val();
        $('#product-reviews-container').addClass('hidden');
        $('#product-reviews-list').empty();
        
        if (!productId) {
            return;
        }

        $.ajax({
            url: reviewsManager.ajax_url,
            type: 'POST',
            data: {
                action: 'get_product_reviews',
                nonce: reviewsManager.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success && response.data.reviews) {
                    renderProductReviews(response.data.reviews);
                    $('#product-reviews-container').removeClass('hidden');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar reseñas:', error);
            }
        });
    });

    // Renderizar reseñas disponibles
    function renderProductReviews(reviews) {
        const $reviewsList = $('#product-reviews-list');
        $reviewsList.empty();

        if (reviews.length === 0) {
            $reviewsList.append('<p>No hay reseñas disponibles para este producto.</p>');
            return;
        }

        reviews.forEach(review => {
            const isSelected = selectedReviews.some(r => r.id === review.id);
            const $review = $(`
                <div class="review-item ${isSelected ? 'selected' : ''}" data-review-id="${review.id}">
                    <div class="review-header">
                        <img src="${review.author_image}" alt="${review.display_name}" class="author-image">
                        <div class="review-meta">
                            <span class="product-name">${review.product_name}</span>
                            <div class="author-info">
                                <span class="author-name">${review.display_name}</span>
                                ${review.verified ? '<span class="verified-badge">Compra verificada</span>' : ''}
                            </div>
                            <div class="review-details">
                                <span class="review-date">${review.date}</span>
                                <div class="star-rating">${getStarRating(review.rating)}</div>
                            </div>
                        </div>
                        <button class="select-review-btn ${isSelected ? 'selected' : ''}" type="button">
                            ${isSelected ? 'Deseleccionar' : 'Seleccionar'}
                        </button>
                    </div>
                    <div class="review-content">${review.content}</div>
                </div>
            `);

            $review.find('.select-review-btn').on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const $reviewItem = $btn.closest('.review-item');
                
                if ($btn.hasClass('selected')) {
                    selectedReviews = selectedReviews.filter(r => r.id !== review.id);
                    $btn.removeClass('selected').text('Seleccionar');
                    $reviewItem.removeClass('selected');
                } else {
                    selectedReviews.push(review);
                    $btn.addClass('selected').text('Deseleccionar');
                    $reviewItem.addClass('selected');
                }
                
                updateSelectedReviewsList();
            });

            $reviewsList.append($review);
        });
    }

    // Actualizar lista de reseñas seleccionadas
    function updateSelectedReviewsList() {
        const $selectedList = $('#selected-reviews-list');
        $selectedList.empty();

        if (selectedReviews.length === 0) {
            $selectedList.append('<p class="no-reviews">No hay reseñas seleccionadas</p>');
            return;
        }

        selectedReviews.forEach(review => {
            const $selectedReview = $(`
                <div class="selected-review-item" data-review-id="${review.id}">
                    <div class="review-header">
                        <img src="${review.author_image}" alt="${review.display_name}" class="author-image">
                        <div class="review-meta">
                            <span class="product-name">${review.product_name}</span>
                            <div class="author-info">
                                <span class="author-name">${review.display_name}</span>
                                ${review.verified ? '<span class="verified-badge">Compra verificada</span>' : ''}
                            </div>
                            <div class="review-details">
                                <span class="review-date">${review.date}</span>
                                <div class="star-rating">${getStarRating(review.rating)}</div>
                            </div>
                        </div>
                        <button class="remove-review-btn" type="button" title="Eliminar reseña">&times;</button>
                    </div>
                    <div class="review-content">${review.content}</div>
                </div>
            `);

            // Manejar eliminación de reseña
            $selectedReview.find('.remove-review-btn').on('click', function(e) {
                e.preventDefault();
                const reviewId = review.id;
                
                // Remover de selectedReviews
                selectedReviews = selectedReviews.filter(r => r.id !== reviewId);
                
                // Actualizar UI
                updateSelectedReviewsList();
                
                // Actualizar estado en la lista de reseñas disponibles
                const $availableReview = $(`.review-item[data-review-id="${reviewId}"]`);
                if ($availableReview.length) {
                    $availableReview.removeClass('selected')
                        .find('.select-review-btn')
                        .removeClass('selected')
                        .text('Seleccionar');
                }
            
                showNotice('Reseña eliminada de la selección.');
            });

            $selectedList.append($selectedReview);
        });
    }

    // Guardar colección
    $('#save-reviews').on('click', function() {
        if (selectedReviews.length === 0) {
            showNotice('Por favor, selecciona al menos una reseña.', 'error');
            return;
        }
    
        $.ajax({
            url: reviewsManager.ajax_url,
            type: 'POST',
            data: {
                action: 'save_reviews_collection',
                nonce: reviewsManager.nonce,
                reviews: selectedReviews
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Colección actualizada exitosamente.');
                    loadSavedReviews();
                } else {
                    showNotice(response.data || 'Error al actualizar la colección.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al guardar:', error);
                showNotice('Error al guardar la colección. Por favor, intenta de nuevo.', 'error');
            }
        });
    });

    // Función auxiliar para mostrar estrellas
    function getStarRating(rating) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += i <= rating ? '★' : '☆';
        }
        return stars;
    }

    function showNotice(message, type = 'success') {
        // Remover notificaciones anteriores
        $('.notice').remove();
        
        // Crear nueva notificación
        const $notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Descartar este aviso.</span>
                </button>
            </div>
        `);
    
        // Insertar notificación al principio de .wrap
        $('.wrap').prepend($notice);
    
        // Manejar el botón de cerrar
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        });
    
        // Auto ocultar después de 3 segundos
        setTimeout(() => {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }
});