<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Engine {

    public function __construct() {
        // Roda o registro dinâmico no init do WordPress
        add_action('init', [$this, 'register_dynamic_entities']);
        
        // Limpa os links automagicamente ao salvar as configs no admin
        add_action('update_option_vtx_dynamic_entities', 'flush_rewrite_rules');
    }

    public function register_dynamic_entities() {
        $saved_json = get_option('vtx_dynamic_entities', '[]');
        $entities = json_decode($saved_json, true);

        if (!is_array($entities) || empty($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug']) || empty($e['cpt_name_plural'])) continue;

            $slug = sanitize_title($e['cpt_slug']);
            
            // Registra o Custom Post Type
            $labels = [
                'name'               => esc_html($e['cpt_name_plural']),
                'singular_name'      => esc_html($e['cpt_name_singular']),
                'menu_name'          => esc_html($e['cpt_name_plural']),
                'add_new'            => 'Adicionar Novo',
                'add_new_item'       => 'Adicionar ' . esc_html($e['cpt_name_singular']),
                'edit_item'          => 'Editar ' . esc_html($e['cpt_name_singular']),
                'all_items'          => 'Todos os ' . esc_html($e['cpt_name_plural']),
            ];

            $args = [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_icon'          => !empty($e['icon']) ? sanitize_html_class($e['icon']) : 'dashicons-admin-post',
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 5,
                'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'], 
                'show_in_rest'       => true, 
                'rewrite'            => ['slug' => $slug, 'with_front' => false],
            ];

            register_post_type($slug, $args);

            // Registra Taxonomia (Categorias)
            if (!empty($e['cat_slug']) && !empty($e['cat_name'])) {
                $cat_slug = sanitize_title($e['cat_slug']);
                $cat_args = [
                    'hierarchical'      => true,
                    'labels'            => [
                        'name'          => esc_html($e['cat_name']),
                        'singular_name' => esc_html($e['cat_name']),
                        'menu_name'     => esc_html($e['cat_name']),
                    ],
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'query_var'         => true,
                    'rewrite'           => ['slug' => $cat_slug, 'with_front' => false],
                    'show_in_rest'      => true,
                ];
                register_taxonomy($slug . '_category', [$slug], $cat_args);
            }

            // Registra Taxonomia (Tags)
            if (!empty($e['tag_slug']) && !empty($e['tag_name'])) {
                $tag_slug = sanitize_title($e['tag_slug']);
                $tag_args = [
                    'hierarchical'      => false,
                    'labels'            => [
                        'name'          => esc_html($e['tag_name']),
                        'singular_name' => esc_html($e['tag_name']),
                        'menu_name'     => esc_html($e['tag_name']),
                    ],
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'query_var'         => true,
                    'rewrite'           => ['slug' => $tag_slug, 'with_front' => false],
                    'show_in_rest'      => true, 
                ];
                register_taxonomy($slug . '_tag', [$slug], $tag_args);
            }
        }
    }
}
