<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Engine {

    public function __construct() {
        add_action('init', [$this, 'register_dynamic_entities']);
        add_action('update_option_vtx_dynamic_entities', 'flush_rewrite_rules');
        
        // Filtros para limpar e customizar os Títulos e Descrições de Arquivo
        add_filter('get_the_archive_title', [$this, 'filter_archive_title']);
        add_filter('get_the_archive_description', [$this, 'filter_archive_description']);
    }

    public function filter_archive_title($title) {
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
            if (is_array($entities)) {
                foreach ($entities as $e) {
                    if ($e['cpt_slug'] === $post_type) {
                        return !empty($e['archive_title']) ? esc_html($e['archive_title']) : esc_html($e['cpt_name_plural']);
                    }
                }
            }
            // Remove o prefixo "Archives:" nativamente caso o usuário não tenha preenchido
            return post_type_archive_title('', false);
        }
        return $title;
    }

    public function filter_archive_description($description) {
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
            if (is_array($entities)) {
                foreach ($entities as $e) {
                    if ($e['cpt_slug'] === $post_type && !empty($e['archive_desc'])) {
                        return wpautop(wp_kses_post($e['archive_desc']));
                    }
                }
            }
        }
        return $description;
    }

    public function register_dynamic_entities() {
        $saved_json = get_option('vtx_dynamic_entities', '[]');
        $entities = json_decode($saved_json, true);
        if (!is_array($entities) || empty($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug']) || empty($e['cpt_name_plural'])) continue;
            $slug = sanitize_title($e['cpt_slug']);
            
            $labels = [
                'name' => esc_html($e['cpt_name_plural']), 'singular_name' => esc_html($e['cpt_name_singular']),
                'menu_name' => esc_html($e['cpt_name_plural']), 'add_new' => 'Adicionar Novo',
                'add_new_item' => 'Adicionar ' . esc_html($e['cpt_name_singular']),
                'edit_item' => 'Editar ' . esc_html($e['cpt_name_singular']), 'all_items' => 'Todos os ' . esc_html($e['cpt_name_plural']),
            ];

            $args = [
                'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true,
                'menu_icon' => !empty($e['icon']) ? sanitize_html_class($e['icon']) : 'dashicons-admin-post',
                'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 5,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'], 'show_in_rest' => true, 
                'rewrite' => ['slug' => $slug, 'with_front' => false],
            ];
            register_post_type($slug, $args);

            if (!empty($e['cat_slug']) && !empty($e['cat_name'])) {
                $cat_slug = sanitize_title($e['cat_slug']);
                register_taxonomy($slug . '_category', [$slug], ['hierarchical' => true, 'labels' => ['name' => esc_html($e['cat_name']), 'singular_name' => esc_html($e['cat_name']), 'menu_name' => esc_html($e['cat_name'])], 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true, 'rewrite' => ['slug' => $cat_slug, 'with_front' => false], 'show_in_rest' => true]);
            }

            if (!empty($e['tag_slug']) && !empty($e['tag_name'])) {
                $tag_slug = sanitize_title($e['tag_slug']);
                register_taxonomy($slug . '_tag', [$slug], ['hierarchical' => false, 'labels' => ['name' => esc_html($e['tag_name']), 'singular_name' => esc_html($e['tag_name']), 'menu_name' => esc_html($e['tag_name'])], 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true, 'rewrite' => ['slug' => $tag_slug, 'with_front' => false], 'show_in_rest' => true]);
            }
        }
    }
}
