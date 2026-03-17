<?php
/**
 * Vettryx WP Architect Shortcodes
 * 
 * Gerencia os shortcodes dinâmicos para CPTs e Taxonomias
 * 
 * @package Vettryx_WP_Architect
 * @since 1.0.0
 */

// Segurança: Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Classe principal dos shortcodes dinâmicos
class Vettryx_WP_Architect_Shortcodes {

    // Construtor da classe
    public function __construct() {
        add_action('init', [$this, 'register_dynamic_shortcodes']);
    }

    // Método para registrar os shortcodes dinâmicos
    public function register_dynamic_shortcodes() {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug'])) continue;
            $cpt_slug = sanitize_title($e['cpt_slug']);

            // Campos Personalizados
            if (!empty($e['fields'])) {
                foreach ($e['fields'] as $field) {
                    $field_id = sanitize_text_field($field['id']);
                    $field_type = sanitize_text_field($field['type']);
                    
                    add_shortcode("vtx_{$cpt_slug}_{$field_id}", function($atts) use ($field_id, $field_type) {
                        $a = shortcode_atts(['id' => ''], $atts);
                        $post_id = !empty($a['id']) ? intval($a['id']) : get_the_ID();
                        if (!$post_id) return '';
                        
                        $value = get_post_meta($post_id, $field_id, true);

                        if ($field_type === 'date' && !is_array($value)) {
                            $value = ['day' => get_post_meta($post_id, $field_id . '_day', true), 'month' => get_post_meta($post_id, $field_id . '_month', true), 'year' => get_post_meta($post_id, $field_id . '_year', true)];
                        }

                        if (empty($value) && $field_type !== 'date') return '';
                        if ($field_type === 'date' && empty($value['year'])) return '';

                        return Vettryx_WP_Architect_Shortcodes::format_output($value, $field_type);
                    });
                }
            }

            // Taxonomias
            if (!empty($e['cat_slug'])) {
                $cat_tax = $cpt_slug . '_category';
                add_shortcode("vtx_{$cpt_slug}_categorias", function($atts) use ($cat_tax) {
                    $a = shortcode_atts(['id' => ''], $atts);
                    return Vettryx_WP_Architect_Shortcodes::format_taxonomy(!empty($a['id']) ? intval($a['id']) : get_the_ID(), $cat_tax, 'category');
                });
                
                add_shortcode("vtx_{$cpt_slug}_categoria_capa", function($atts) use ($cat_tax) {
                    $a = shortcode_atts(['id' => ''], $atts);
                    $post_id = !empty($a['id']) ? intval($a['id']) : get_the_ID();
                    if (!$post_id) return '';
                    
                    $terms = get_the_terms($post_id, $cat_tax);
                    if ($terms && !is_wp_error($terms)) {
                        $term = $terms[0]; 
                        $image_id = get_term_meta($term->term_id, 'vtx_tax_image', true);
                        if ($image_id) {
                            $img_url = wp_get_attachment_image_url($image_id, 'large');
                            return '<img src="'.esc_url($img_url).'" alt="' . esc_attr($term->name) . '" class="vtx-sc-cat-image" style="width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px; display: block;">';
                        }
                    }
                    return '';
                });
            }

            // Tags
            if (!empty($e['tag_slug'])) {
                $tag_tax = $cpt_slug . '_tag';
                add_shortcode("vtx_{$cpt_slug}_tags", function($atts) use ($tag_tax) {
                    $a = shortcode_atts(['id' => ''], $atts);
                    return Vettryx_WP_Architect_Shortcodes::format_taxonomy(!empty($a['id']) ? intval($a['id']) : get_the_ID(), $tag_tax, 'tag');
                });
            }

            // Página de Arquivo
            add_shortcode("vtx_{$cpt_slug}_arquivo_titulo", function() use ($e, $cpt_slug) {
                if (is_tax($cpt_slug . '_category') || is_tax($cpt_slug . '_tag')) {
                    $term = get_queried_object();
                    return ($term && isset($term->name)) ? $term->name : '';
                }
                return !empty($e['archive_title']) ? esc_html($e['archive_title']) : esc_html($e['cpt_name_plural']);
            });

            // Descrição da Página de Arquivo
            add_shortcode("vtx_{$cpt_slug}_arquivo_descricao", function() use ($e, $cpt_slug) {
                if (is_tax($cpt_slug . '_category') || is_tax($cpt_slug . '_tag')) {
                    $term = get_queried_object();
                    return ($term && isset($term->description)) ? wpautop($term->description) : '';
                }
                return !empty($e['archive_desc']) ? wpautop(wp_kses_post($e['archive_desc'])) : '';
            });
        }
    }

    // Método para formatar o valor de um campo baseado no tipo
    public static function format_output($value, $type) {
        switch ($type) {
            case 'url': return '<a href="' . esc_url($value) . '" class="vtx-sc-url" target="_blank" rel="noopener">' . esc_url($value) . '</a>';
            case 'textarea': return wpautop(esc_html($value));
            case 'image': 
                $img_url = wp_get_attachment_image_url($value, 'large'); 
                return $img_url ? '<img src="' . esc_url($img_url) . '" class="vtx-sc-image" style="max-width:100%; height:auto; border-radius:8px; display:block;">' : '';
            
            case 'date':
                if (!is_array($value)) return esc_html($value);
                $day = $value['day'] ?? ''; $month = $value['month'] ?? ''; $year = $value['year'] ?? '';
                if (!$year) return '';
                $meses = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'];
                $month_text = ($month && isset($meses[$month])) ? $meses[$month] : '';
                if ($day && $month_text) return "$day de $month_text de $year";
                elseif ($month_text) return "$month_text de $year";
                else return $year;

            case 'gallery':
                $ids = explode(',', $value);
                // ID único para agrupar as fotos na navegação do lightbox
                $gal_id = uniqid('vtx_gal_');
                $html = '<div class="vtx-sc-gallery" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:15px;">';
                foreach ($ids as $id) {
                    $thumb_url = wp_get_attachment_image_url($id, 'large');
                    $full_url = wp_get_attachment_image_url($id, 'full');
                    if ($thumb_url && $full_url) {
                        // Atributos data-elementor ativam o lightbox do construtor automaticamente
                        $html .= '<a href="' . esc_url($full_url) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="' . $gal_id . '" style="display:block; overflow:hidden; border-radius:5px;"><img src="' . esc_url($thumb_url) . '" style="width:100%; aspect-ratio:1; object-fit:cover; transition:transform 0.3s ease;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'"></a>';
                    }
                }
                $html .= '</div>';
                return $html;
                
            default: return esc_html($value);
        }
    }

    // Método para formatar a taxonomia de um post
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
