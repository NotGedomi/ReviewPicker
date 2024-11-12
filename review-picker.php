<?php
/**
 * Plugin Name: Review Picker
 * Description: Permite seleccionar y mostrar reseñas específicas mediante shortcode
 * Version: 1.0
 * Author: Gedomi
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Reviews_Manager
{
    private static $instance = null;
    private $table_name;

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'custom_reviews_collection';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_get_products', array($this, 'ajax_get_products'));
        add_action('wp_ajax_get_product_reviews', array($this, 'ajax_get_product_reviews'));
        add_action('wp_ajax_save_reviews_collection', array($this, 'ajax_save_reviews_collection'));
        add_action('wp_ajax_get_saved_reviews', array($this, 'ajax_get_saved_reviews'));
        add_action('wp_ajax_remove_review', array($this, 'ajax_remove_review'));
        add_shortcode('custom_reviews', array($this, 'render_reviews_shortcode'));

        register_activation_hook(__FILE__, array($this, 'create_plugin_table'));
    }

    public function create_plugin_table()
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            review_data longtext NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        if ($wpdb->last_error) {
            error_log('Error al crear la tabla de reseñas: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Review Picker',
            'Review Picker',
            'manage_options',
            'custom-reviews-manager',
            array($this, 'render_admin_page'),
            'dashicons-star-filled',
            30
        );
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Review Picker</h1>
            
            <div class="reviews-manager-container">
                <div class="filters-section">
                    <h2>Buscar Productos</h2>
                    <div class="filters-grid">
                        <select id="category-filter" class="filter-select">
                            <option value="">Seleccionar Categoría</option>
                        </select>
                        
                        <select id="product-filter" class="filter-select" disabled>
                            <option value="">Seleccionar Producto</option>
                        </select>
                    </div>
                </div>

                <div id="product-reviews-container" class="reviews-section hidden">
                    <h3>Reseñas Disponibles</h3>
                    <div id="product-reviews-list"></div>
                </div>

                <div class="selected-reviews-section">
                    <h3>Reseñas Seleccionadas</h3>
                    <div id="selected-reviews-list">
                        <p class="no-reviews">No hay reseñas seleccionadas</p>
                    </div>
                </div>

                <div class="save-section">
                    <button id="save-reviews" class="button button-primary">Actualizar Colección</button>
                </div>

                <div class="shortcode-info">
                    <h3>Shortcode</h3>
                    <code>[custom_reviews]</code>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_custom-reviews-manager' !== $hook) {
            return;
        }
    
        // Añadir wp-admin CSS para notificaciones
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('custom-reviews-manager-css', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('custom-reviews-manager-js', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0', true);
        
        wp_localize_script('custom-reviews-manager-js', 'reviewsManager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reviews_manager_nonce')
        ));
    }

    public function ajax_get_saved_reviews()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');
        
        $reviews = $this->get_collection();
        
        if ($reviews === false) {
            wp_send_json_success(array('reviews' => array()));
            return;
        }
        
        wp_send_json_success(array('reviews' => $reviews));
    }

    public function ajax_remove_review()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');
        
        if (!isset($_POST['review_id'])) {
            wp_send_json_error('ID de reseña no proporcionado');
            return;
        }

        $review_id = intval($_POST['review_id']);
        $current_reviews = $this->get_collection();

        if (!is_array($current_reviews)) {
            wp_send_json_error('No hay reseñas para actualizar');
            return;
        }

        // Filtrar la reseña a eliminar
        $updated_reviews = array_filter($current_reviews, function($review) use ($review_id) {
            return $review['id'] !== $review_id;
        });

        // Guardar la colección actualizada
        $saved = $this->save_collection(array_values($updated_reviews));

        if ($saved) {
            wp_send_json_success(array(
                'message' => 'Reseña eliminada exitosamente',
                'reviews' => array_values($updated_reviews)
            ));
        } else {
            wp_send_json_error('Error al eliminar la reseña');
        }
    }
    public function ajax_get_categories()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');

        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));

        if (is_wp_error($categories)) {
            wp_send_json_error('Error al obtener categorías');
            return;
        }

        $formatted_categories = array();
        foreach ($categories as $cat) {
            $formatted_categories[] = array(
                'id' => $cat->term_id,
                'name' => $cat->name
            );
        }

        wp_send_json_success(array('categories' => $formatted_categories));
    }

    public function ajax_get_products()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        if ($category_id > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            );
        }

        $products = get_posts($args);
        $formatted_products = array();

        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product) {
                $formatted_products[] = array(
                    'id' => $product->ID,
                    'name' => $product->post_title,
                    'rating' => $wc_product->get_average_rating()
                );
            }
        }

        wp_send_json_success(array('products' => $formatted_products));
    }

    public function ajax_get_product_reviews()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');

        $product_id = intval($_POST['product_id']);
        $reviews = $this->get_formatted_product_reviews($product_id);

        wp_send_json_success(array('reviews' => $reviews));
    }

    private function get_formatted_product_reviews($product_id) {
        $product = wc_get_product($product_id);
        $reviews = get_comments(array(
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ));
    
        $formatted_reviews = array();
        
        foreach ($reviews as $review) {
            // Obtener datos del usuario de forma segura
            $user_data = null;
            $first_name = '';
            $last_name = '';
            $display_name = $review->comment_author;
    
            if ($review->user_id) {
                $user_data = get_userdata($review->user_id);
                if ($user_data) {
                    $first_name = get_user_meta($review->user_id, 'first_name', true);
                    $last_name = get_user_meta($review->user_id, 'last_name', true);
                    
                    if (!empty($first_name) && !empty($last_name)) {
                        $display_name = $first_name . ' ' . $last_name;
                    } elseif (!empty($first_name)) {
                        $display_name = $first_name;
                    } elseif (!empty($last_name)) {
                        $display_name = $last_name;
                    } elseif (!empty($user_data->display_name)) {
                        $display_name = $user_data->display_name;
                    }
                }
            }
    
            // Obtener avatar personalizado o por defecto
            $avatar_url = '';
            if ($review->user_id) {
                $custom_image_id = get_user_meta($review->user_id, 'instructor_profile_image_id', true);
                if ($custom_image_id) {
                    $image_url = wp_get_attachment_image_src($custom_image_id, 'thumbnail');
                    if ($image_url) {
                        $avatar_url = $image_url[0];
                    }
                }
            }
            
            if (empty($avatar_url)) {
                $avatar_url = get_avatar_url($review->comment_author_email, array('size' => 96));
            }
    
            $rating = get_comment_meta($review->comment_ID, 'rating', true);
            $formatted_rating = number_format((float)$rating, 1, '.', '');
            $fecha_formateada = date_i18n('j F, Y', strtotime($review->comment_date));
    
            $formatted_reviews[] = array(
                'id' => $review->comment_ID,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'rating' => $formatted_rating,
                'author' => $review->comment_author,
                'display_name' => $display_name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'user_id' => $review->user_id,
                'author_image' => $avatar_url,
                'date' => $fecha_formateada,
                'content' => $review->comment_content,
                'verified' => wc_review_is_from_verified_owner($review->comment_ID)
            );
        }
    
        return $formatted_reviews;
    }

    public function ajax_save_reviews_collection()
    {
        check_ajax_referer('reviews_manager_nonce', 'nonce');

        if (!isset($_POST['reviews']) || !is_array($_POST['reviews'])) {
            wp_send_json_error('No se recibieron reseñas para guardar');
            return;
        }

        $review_data = array();
        foreach ($_POST['reviews'] as $review) {
            $review_data[] = array(
                'id' => intval($review['id']),
                'product_id' => intval($review['product_id']),
                'product_name' => sanitize_text_field($review['product_name']),
                'rating' => number_format((float)$review['rating'], 1, '.', ''),
                'author' => sanitize_text_field($review['author']),
                'display_name' => sanitize_text_field($review['display_name']),
                'user_id' => intval($review['user_id']),
                'author_image' => esc_url_raw($review['author_image']),
                'date' => sanitize_text_field($review['date']),
                'content' => wp_kses_post($review['content']),
                'verified' => (bool)$review['verified']
            );
        }

        $saved = $this->save_collection($review_data);

        if ($saved) {
            wp_send_json_success(array(
                'message' => 'Colección actualizada exitosamente',
                'reviews' => $review_data
            ));
        } else {
            wp_send_json_error('Error al actualizar la colección');
        }
    }

    private function save_collection($review_data)
    {
        global $wpdb;

        try {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
                $this->create_plugin_table();
            }

            $wpdb->query('START TRANSACTION');

            $wpdb->query("DELETE FROM {$this->table_name}");

            $success = $wpdb->insert(
                $this->table_name,
                array(
                    'review_data' => json_encode($review_data, JSON_UNESCAPED_UNICODE),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s')
            );

            if ($success === false) {
                $wpdb->query('ROLLBACK');
                error_log('Error al guardar la colección: ' . $wpdb->last_error);
                return false;
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Excepción al guardar la colección: ' . $e->getMessage());
            return false;
        }
    }

    private function get_collection()
    {
        global $wpdb;
        
        try {
            $collection = $wpdb->get_var("SELECT review_data FROM {$this->table_name} LIMIT 1");
            
            if ($collection === null) {
                return array();
            }
            
            return json_decode($collection, true) ?: array();
            
        } catch (Exception $e) {
            error_log('Error al obtener la colección: ' . $e->getMessage());
            return array();
        }
    }

    public function render_reviews_shortcode($atts)
    {
        $reviews = $this->get_collection();
        
        if (empty($reviews)) {
            return json_encode([
                'success' => false,
                'message' => 'No hay reseñas disponibles'
            ]);
        }
        
        $total_reviews = count($reviews);
        $average_rating = 0;
        
        if ($total_reviews > 0) {
            $total_rating = array_sum(array_column($reviews, 'rating'));
            $average_rating = number_format($total_rating / $total_reviews, 1);
        }

        return json_encode([
            'success' => true,
            'summary' => [
                'total_reviews' => $total_reviews,
                'average_rating' => $average_rating
            ],
            'reviews' => $reviews
        ]);
    }
}

// Inicializar el plugin
WC_Custom_Reviews_Manager::getInstance();