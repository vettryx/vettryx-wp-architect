<?php
/**
 * Vettryx WP Architect Dynamic Engine
 * * Gerencia o registro dinâmico de CPTs e Taxonomias com base nas configurações do usuário.
 * * @package Vettryx_WP_Architect
 * @since 1.0.0
 */

// Segurança: Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal do motor de entidades dinâmicas
 */
class Vettryx_WP_Architect_Engine {

    public function __construct() {
        add_action('init', [$this, 'register_dynamic_entities']);
        add_action('update_option_vtx_dynamic_entities', 'flush_rewrite_rules');
        add_action('admin_head', [$this, 'inject_vettryx_clean_ui']);
        
        // Filtros para limpar e customizar os Títulos e Descrições de Arquivo
        add_filter('get_the_archive_title', [$this, 'filter_archive_title']);
        add_filter('get_the_archive_description', [$this, 'filter_archive_description']);
        
        // Hook para desativar o Gutenberg condicionalmente
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg_conditionally'], 10, 2);
    }

    /**
     * Intercepta o carregamento do editor e desativa o Gutenberg se a opção estiver marcada
     */
    public function disable_gutenberg_conditionally($current_status, $post_type) {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return $current_status;

        foreach ($entities as $e) {
            if ($e['cpt_slug'] === $post_type && !empty($e['disable_gutenberg'])) {
                return false; // Força o editor clássico para este CPT
            }
        }
        return $current_status;
    }

    /**
     * Filtra o título da página de arquivo
     * * @param string $title Título original da página de arquivo
     * @return string Título customizado
     */
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

    /**
     * Filtra a descrição da página de arquivo
     * * @param string $description Descrição original da página de arquivo
     * @return string Descrição customizada
     */
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

    /**
     * Registra as entidades dinâmicas (CPTs e Taxonomias)
     * * Executado no hook 'init' para registrar todos os tipos de conteúdo dinâmicos
     */
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

            // Define os suportes dinamicamente com base no toggle do Gutenberg
            $supports = ['title', 'thumbnail', 'excerpt'];
            if (empty($e['disable_gutenberg'])) {
                $supports[] = 'editor';
            }

            $args = [
                'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true,
                'menu_icon' => !empty($e['icon']) ? sanitize_html_class($e['icon']) : 'dashicons-admin-post',
                'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 5,
                'supports' => $supports, 'show_in_rest' => true, 
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

    /**
     * Injeta CSS no painel para mascarar o WordPress e dar visual de sistema SaaS
     */
    public function inject_vettryx_clean_ui() {
        global $typenow;
        if (empty($typenow)) return;

        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;

        $is_clean_ui = false;
        foreach ($entities as $e) {
            if ($e['cpt_slug'] === $typenow && !empty($e['disable_gutenberg'])) {
                $is_clean_ui = true;
                break;
            }
        }

        if (!$is_clean_ui) return;

        // O CSS que transforma o painel
        ?>
        <style>
            /* Fundo da tela mais limpo e esconde lixos do WP */
            body { background-color: #f4f6f8; }
            #post-status-info, #post-body-content .wp-heading-inline { display: none !important; }
            
            /* Campo de Título Moderno */
            #titlediv #title {
                padding: 15px 20px;
                font-size: 24px;
                font-weight: 600;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            }

            /* Cards de Meta Boxes (Visual VETTRYX) */
            .postbox {
                border: 1px solid #ccd0d4 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 6px rgba(0,0,0,0.03) !important;
                margin-bottom: 20px !important;
            }
            .postbox .hndle {
                background: #fff;
                border-bottom: 1px solid #eee;
                border-radius: 8px 8px 0 0;
                padding: 15px 20px;
                font-size: 16px;
                font-weight: 600;
                color: #023047; /* Azul VETTRYX */
            }
            .postbox .inside { padding: 20px; }

            /* Truque CSS: Renomeia "Resumo" para "Descrição" */
            #postexcerpt .hndle span { font-size: 0; }
            #postexcerpt .hndle span:before {
                content: "Descrição (Aparece na tela do projeto)";
                font-size: 16px;
            }
            #postexcerpt p { display: none; } /* Esconde o texto inútil de ajuda */
            #postexcerpt textarea { height: 120px; border-radius: 6px; }
            
            /* Botão de Publicar Bombado */
            #publish {
                background: #0073aa;
                border-color: #0073aa;
                color: #fff;
                font-size: 16px;
                padding: 10px 20px;
                height: auto;
                border-radius: 6px;
                width: 100%;
                text-transform: uppercase;
                font-weight: bold;
                transition: all 0.3s;
            }
            #publish:hover { background: #005177; transform: scale(1.02); }
        </style>
        <?php
    }
}
