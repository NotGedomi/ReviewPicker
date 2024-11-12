# Review Picker Plugin

Plugin para WordPress que permite seleccionar y mostrar reseñas específicas de productos WooCommerce.

## Descripción

Review Picker te permite:
- Filtrar reseñas por categoría y producto
- Seleccionar reseñas específicas para mostrar
- Gestionar una colección única de reseñas
- Mostrar las reseñas usando un shortcode
- Obtener los datos en formato JSON para personalizar la visualización

## Instalación

1. Descargar el plugin
2. Subir a wp-content/plugins/
3. Activar el plugin en WordPress

## Uso básico

### En el panel de administración

1. Ve a "Gestor de Reseñas" en el menú lateral
2. Selecciona una categoría y producto
3. Elige las reseñas que quieras mostrar
4. Haz clic en "Actualizar Colección"

### Mostrar las reseñas

Usa el shortcode en cualquier página o post:

```
[custom_reviews]
```

### Obtener datos JSON

El shortcode puede devolver los datos en formato JSON para procesarlos:

```php
<?php
// Obtener datos
$reviews_data = json_decode(do_shortcode('[custom_reviews]'), true);

// Estructura del JSON
{
    "success": true,
    "summary": {
        "total_reviews": 10,
        "average_rating": 4.5
    },
    "reviews": [
        {
            "id": 1,
            "product_id": 123,
            "product_name": "Nombre del producto",
            "rating": 5,
            "author": "Nombre del autor",
            "author_image": "URL de la imagen",
            "date": "01/01/2024",
            "content": "Contenido de la reseña",
            "verified": true
        }
        // ... más reseñas
    ]
}
```

### Ejemplos de uso

#### PHP Básico
```php
<?php
$reviews_data = json_decode(do_shortcode('[custom_reviews]'), true);

if ($reviews_data['success']): 
    foreach($reviews_data['reviews'] as $review): ?>
        <div class="review">
            <h3><?php echo $review['author']; ?></h3>
            <div class="rating"><?php echo str_repeat('★', $review['rating']); ?></div>
            <p><?php echo $review['content']; ?></p>
        </div>
    <?php endforeach;
endif;
?>
```

#### JavaScript
```javascript
// En tu template
<div id="reviews-container"></div>

<script>
const reviewsData = JSON.parse(`<?php echo do_shortcode('[custom_reviews]'); ?>`);

if (reviewsData.success) {
    const container = document.getElementById('reviews-container');
    const html = reviewsData.reviews.map(review => `
        <div class="review">
            <img src="${review.author_image}" alt="${review.author}">
            <h3>${review.author}</h3>
            <div class="stars">${'★'.repeat(review.rating)}</div>
            <p>${review.content}</p>
        </div>
    `).join('');
    
    container.innerHTML = html;
}
</script>
```

## Notas adicionales

- Las reseñas se guardan como una única colección
- Se actualizan cada vez que modificas la selección
- Puedes personalizar completamente el diseño
- Los datos incluyen toda la información de la reseña original de WooCommerce

## Requerimientos

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+

## Soporte

Para reportar bugs o solicitar funcionalidades, por favor abre un issue en este repositorio.