<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Shortcodes {

    public function __construct() {
        add_action('init', [$this, 'register_dynamic_shortcodes']);
    }

    public function register_dynamic_shortcodes() {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug'])) continue;

            $cpt_slug = sanitize_title($e['cpt_slug']);

            // 1. Gera Shortcodes para os Campos Personalizados
            if (!empty($e['fields'])) {
                foreach ($e['fields'] as $field) {
                    $field_id = sanitize_text_field($field['id']);
                    $field_type = sanitize_text_field($field['type']);
                    
                    $shortcode_tag = "vtx_{$cpt_slug}_{$field_id}";

                    add_shortcode($shortcode_tag, function($atts) use ($field_id, $field_type) {
                        // Aceita o parâmetro ID, senão cai pro fallback da página atual
                        $a = shortcode_atts(['id' => ''], $atts);
                        $post_id = !empty($a['id']) ? intval($a['id']) : get_the_ID();

                        if (!$post_id) return '';

                        $value = get_post_meta($post_id, $field_id, true);
                        if (empty($value)) return '';

                        return Vettryx_WP_Architect_Shortcodes::format_output($value, $field_type);
                    });
                }
            }

            // 2. Gera Shortcode para Categorias
            if (!empty($e['cat_slug'])) {
                $cat_tax = $cpt_slug . '_category';
                add_shortcode("vtx_{$cpt_slug}_categorias", function($atts) use ($cat_tax) {
                    $a = shortcode_atts(['id' => ''], $atts);
                    $post_id = !empty($a['id']) ? intval($a['id']) : get_the_ID();
                    return Vettryx_WP_Architect_Shortcodes::format_taxonomy($post_id, $cat_tax, 'category');
                });
            }

            // 3. Gera Shortcode para Tags
            if (!empty($e['tag_slug'])) {
                $tag_tax = $cpt_slug . '_tag';
                add_shortcode("vtx_{$cpt_slug}_tags", function($atts) use ($tag_tax) {
                    $a = shortcode_atts(['id' => ''], $atts);
                    $post_id = !empty($a['id']) ? intval($a['id']) : get_the_ID();
                    return Vettryx_WP_Architect_Shortcodes::format_taxonomy($post_id, $tag_tax, 'tag');
                });
            }
        }
    }

    // --- MÉTODOS ESTÁTICOS DE FORMATAÇÃO HTML ---

    public static function format_output($value, $type) {
        switch ($type) {
            case 'url':
                return '<a href="' . esc_url($value) . '" class="vtx-sc-url" target="_blank" rel="noopener">' . esc_url($value) . '</a>';
            
            case 'textarea':
                return wpautop(esc_html($value));
            
            case 'image':
                $img_url = wp_get_attachment_image_url($value, 'large');
                return $img_url ? '<img src="' . esc_url($img_url) . '" class="vtx-sc-image" style="max-width:100%; height:auto; border-radius:8px; display:block;">' : '';
            
            case 'gallery':
                $ids = explode(',', $value);
                $html = '<div class="vtx-sc-gallery" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:15px;">';
                foreach ($ids as $id) {
                    $img_url = wp_get_attachment_image_url($id, 'large');
                    if ($img_url) {
                        $html .= '<img src="' . esc_url($img_url) . '" style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:5px;">';
                    }
                }
                $html .= '</div>';
                return $html;
            
            default: // text
                return esc_html($value);
        }
    }

    public static function format_taxonomy($post_id, $taxonomy, $type) {
        if (!$post_id) return '';
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) return '';

        $html = [];
        foreach ($terms as $term) {
            $link = get_term_link($term);
            if ($type === 'category') {
                $html[] = '<a href="' . esc_url($link) . '" class="vtx-sc-cat" style="font-weight:600; color:inherit; text-decoration:none;">' . esc_html($term->name) . '</a>';
            } else {
                $html[] = '<a href="' . esc_url($link) . '" class="vtx-sc-tag" style="display:inline-block; background:#e2e8f0; color:inherit; padding:4px 10px; border-radius:4px; font-size:13px; text-decoration:none; margin:0 5px 5px 0;">' . esc_html($term->name) . '</a>';
            }
        }
        return '<div class="vtx-sc-tax-wrapper">' . implode($type === 'category' ? ', ' : '', $html) . '</div>';
    }
}
