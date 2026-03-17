<?php
/**
 * Vettryx WP Architect Meta Boxes
 * 
 * Gerencia os meta boxes dinâmicos para CPTs e Taxonomias.
 * 
 * @package Vettryx_WP_Architect
 * @since 1.0.0
 */

// Segurança: Evita acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Classe responsável por gerenciar os meta boxes dinâmicos
class Vettryx_WP_Architect_Meta_Boxes {

    // Construtor: Adiciona os hooks
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_dynamic_meta_boxes']);
        add_action('save_post', [$this, 'save_dynamic_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);
        add_action('admin_footer', [$this, 'render_media_uploader_js']);
        add_filter('wp_insert_post_data', [$this, 'force_dynamic_date_slug'], 10, 2);
        $this->hook_taxonomy_image_fields();
    }

    // Método para forçar o slug dinâmico
    public function force_dynamic_date_slug($data, $postarr) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $data;
        if (in_array($data['post_status'], ['auto-draft', 'trash'])) return $data;

        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return $data;

        foreach ($entities as $e) {
            if ($e['cpt_slug'] === $data['post_type'] && !empty($e['fields'])) {
                foreach ($e['fields'] as $field) {
                    if ($field['type'] === 'date' && !empty($field['use_as_slug'])) {
                        $id = $field['id'];
                        $year = isset($_POST[$id.'_year']) ? sanitize_text_field($_POST[$id.'_year']) : get_post_meta($postarr['ID'], $id.'_year', true);
                        $month = isset($_POST[$id.'_month']) ? sanitize_text_field($_POST[$id.'_month']) : get_post_meta($postarr['ID'], $id.'_month', true);
                        $day = isset($_POST[$id.'_day']) ? sanitize_text_field($_POST[$id.'_day']) : get_post_meta($postarr['ID'], $id.'_day', true);

                        if (empty($year)) continue;

                        $date_prefix = $year . str_pad($month ?: '01', 2, '0', STR_PAD_LEFT) . str_pad($day ?: '01', 2, '0', STR_PAD_LEFT);
                        $clean_slug = preg_replace('/^[0-9]{8}-/', '', $data['post_name'] ?: sanitize_title($data['post_title']));
                        $desired_slug = $date_prefix . '-' . $clean_slug;

                        if ($data['post_name'] !== $desired_slug) {
                            $data['post_name'] = wp_unique_post_slug($desired_slug, $postarr['ID'], $data['post_status'], $data['post_type'], $data['post_parent']);
                        }
                        break 2;
                    }
                }
            }
        }
        return $data;
    }

    // Método para adicionar os campos de imagem nas categorias
    public function hook_taxonomy_image_fields() {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;
        foreach ($entities as $e) {
            if (!empty($e['cpt_slug']) && !empty($e['cat_slug'])) {
                $tax_slug = sanitize_title($e['cpt_slug']) . '_category';
                add_action("{$tax_slug}_add_form_fields", [$this, 'add_category_image_field']);
                add_action("{$tax_slug}_edit_form_fields", [$this, 'edit_category_image_field']);
                add_action("created_{$tax_slug}", [$this, 'save_category_image']);
                add_action("edited_{$tax_slug}", [$this, 'save_category_image']);
            }
        }
    }

    // Método para adicionar o campo de imagem no formulário de criação de categoria
    public function add_category_image_field() {
        ?>
        <div class="form-field term-group">
            <label for="vtx_tax_image">Imagem Representativa</label>
            <input type="hidden" id="vtx_tax_image" name="vtx_tax_image" value="">
            <div id="preview_vtx_tax_image" style="margin-top:10px;"></div>
            <p><button type="button" class="button vtx-upload-single-img" data-target="vtx_tax_image">Selecionar Imagem</button></p>
        </div>
        <?php
    }

    // Método para editar o campo de imagem no formulário de edição de categoria
    public function edit_category_image_field($term) {
        $image_id = get_term_meta($term->term_id, 'vtx_tax_image', true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="vtx_tax_image">Imagem da Categoria</label></th>
            <td>
                <input type="hidden" id="vtx_tax_image" name="vtx_tax_image" value="<?php echo esc_attr($image_id); ?>">
                <div id="preview_vtx_tax_image" style="margin-top:10px;">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" style="max-width:150px; display:block; margin-bottom:10px; border-radius:4px;">
                        <a href="#" class="vtx-remove-single-img" data-target="vtx_tax_image" style="color:#d63638; text-decoration:none; font-weight:bold;">Remover Imagem</a>
                    <?php endif; ?>
                </div>
                <p><button type="button" class="button vtx-upload-single-img" data-target="vtx_tax_image" style="margin-top:10px;">Alterar Imagem</button></p>
            </td>
        </tr>
        <?php
    }

    // Método para salvar o campo de imagem da categoria
    public function save_category_image($term_id) {
        if (isset($_POST['vtx_tax_image'])) update_term_meta($term_id, 'vtx_tax_image', sanitize_text_field($_POST['vtx_tax_image']));
    }

    // Método para enfileirar o media uploader
    public function enqueue_media_uploader() { wp_enqueue_media(); }

    public function add_dynamic_meta_boxes() {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;
        foreach ($entities as $e) {
            if (empty($e['cpt_slug']) || empty($e['fields'])) continue;
            add_meta_box('vtx_dynamic_fields_' . $e['cpt_slug'], 'Dados Adicionais', [$this, 'render_meta_box_content'], $e['cpt_slug'], 'normal', 'high', ['fields' => $e['fields']]);
        }
    }

    // Método para renderizar o conteúdo do meta box
    public function render_meta_box_content($post, $metabox) {
        wp_nonce_field('vtx_architect_save_data', 'vtx_architect_nonce');
        $fields = $metabox['args']['fields'];
        echo '<div style="display: grid; gap: 20px;">';
        foreach ($fields as $field) {
            $id = esc_attr($field['id']);
            $value = get_post_meta($post->ID, $id, true);
            $label = esc_html($field['label']);
            echo '<div><label for="'.$id.'"><strong>'.$label.'</strong></label><br>';
            
            switch ($field['type']) {
                case 'text':
                case 'url':
                    $type = $field['type'] === 'url' ? 'url' : 'text';
                    echo "<input type='{$type}' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' style='width: 100%;' />"; break;
                
                case 'textarea':
                    echo "<textarea id='{$id}' name='{$id}' rows='4' style='width: 100%;'>" . esc_textarea($value) . "</textarea>"; break;
                
                case 'date':
                    $val = is_array($value) ? $value : ['day' => get_post_meta($post->ID, $id . '_day', true), 'month' => get_post_meta($post->ID, $id . '_month', true), 'year' => get_post_meta($post->ID, $id . '_year', true)];
                    $d = $val['day'] ?? ''; $m = $val['month'] ?? ''; $y = $val['year'] ?? '';
                    $current_year = date('Y');

                    echo "<div style='display:flex; gap:10px; margin-top:5px;'>";
                    echo "<select name='{$id}_day' style='width:auto;'><option value=''>Dia (Opcional)</option>";
                    for($i=1; $i<=31; $i++) { $val_d = str_pad($i, 2, '0', STR_PAD_LEFT); echo "<option value='{$val_d}' ".selected($d, $val_d, false).">{$val_d}</option>"; }
                    echo "</select>";
                    echo "<select name='{$id}_month' style='width:auto;'><option value=''>Mês (Opcional)</option>";
                    $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                    foreach($meses as $num => $nome) { $val_m = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<option value='{$val_m}' ".selected($m, $val_m, false).">{$nome}</option>"; }
                    echo "</select>";
                    echo "<select name='{$id}_year' style='width:auto;' required><option value=''>Ano (Obrigatório)</option>";
                    for($i = $current_year + 2; $i >= $current_year - 15; $i--) { echo "<option value='{$i}' ".selected($y, $i, false).">{$i}</option>"; }
                    echo "</select></div>";
                    break;

                case 'image':
                    $img_url = $value ? wp_get_attachment_image_url($value, 'thumbnail') : '';
                    echo "<input type='hidden' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' /><div id='preview_{$id}' style='margin-top:10px;'>";
                    if ($img_url) { echo "<img src='{$img_url}' style='max-width:150px; display:block; margin-bottom:10px; border-radius:4px;'><a href='#' class='vtx-remove-single-img vtx-btn-danger' data-target='{$id}'>Remover Imagem</a>"; }
                    echo "</div><button type='button' class='button vtx-upload-single-img' data-target='{$id}' style='margin-top:10px;'>Selecionar Imagem</button>"; break;
                
                case 'gallery':
                    echo "<input type='hidden' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' /><button type='button' class='button vtx-upload-gallery-img' data-target='{$id}' style='margin-top:10px;'>Adicionar Fotos na Galeria</button><div id='preview_{$id}' style='display:flex; flex-wrap:wrap; gap:10px; margin-top:15px;'>";
                    if (!empty($value)) {
                        foreach (explode(',', $value) as $img_id) {
                            if ($img_url = wp_get_attachment_image_url($img_id, 'thumbnail')) {
                                echo "<div class='vtx-img-wrap' data-id='{$img_id}' style='position:relative; width:80px; height:80px; border:1px solid #ddd;'><img src='{$img_url}' style='width:100%; height:100%; object-fit:cover;'><a href='#' class='vtx-remove-gallery-img' style='position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; line-height:18px; text-decoration:none;'>&times;</a></div>";
                            }
                        }
                    }
                    echo "</div>"; break;
            }
            echo '</div>';
        }
        echo '</div><style>.vtx-btn-danger { color: #d63638; text-decoration: none; font-weight: bold; }</style>';
    }

    // Método para salvar os meta boxes dinâmicos
    public function save_dynamic_meta_boxes($post_id) {
        if (!isset($_POST['vtx_architect_nonce']) || !wp_verify_nonce($_POST['vtx_architect_nonce'], 'vtx_architect_save_data') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
        $post_type = get_post_type($post_id);
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        
        foreach ($entities as $e) {
            if ($e['cpt_slug'] === $post_type && !empty($e['fields'])) {
                foreach ($e['fields'] as $field) {
                    $id = $field['id'];
                    if ($field['type'] === 'date') {
                        $val = ['day' => isset($_POST[$id.'_day']) ? sanitize_text_field($_POST[$id.'_day']) : '', 'month' => isset($_POST[$id.'_month']) ? sanitize_text_field($_POST[$id.'_month']) : '', 'year' => isset($_POST[$id.'_year']) ? sanitize_text_field($_POST[$id.'_year']) : ''];
                        update_post_meta($post_id, $id, $val);
                        update_post_meta($post_id, $id . '_day', $val['day']); update_post_meta($post_id, $id . '_month', $val['month']); update_post_meta($post_id, $id . '_year', $val['year']);
                    } else {
                        if (isset($_POST[$id])) { update_post_meta($post_id, $id, $field['type'] === 'textarea' ? sanitize_textarea_field($_POST[$id]) : ($field['type'] === 'url' ? esc_url_raw($_POST[$id]) : sanitize_text_field($_POST[$id]))); } else { delete_post_meta($post_id, $id); }
                    }
                }
                break;
            }
        }
    }

    // Método para renderizar o JavaScript do media uploader
    public function render_media_uploader_js() {
        $screen = get_current_screen();
        if (!$screen || ($screen->base !== 'post' && $screen->base !== 'term')) return; 
        ?>
        <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            $(document).on('click', '.vtx-upload-single-img', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var inputField = $('#' + targetId);
                var previewArea = $('#preview_' + targetId);
                mediaUploader = wp.media({ title: 'Selecionar Imagem', multiple: false });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    inputField.val(attachment.id);
                    var imgUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                    previewArea.html('<img src="'+imgUrl+'" style="max-width:150px; display:block; margin-bottom:10px; border-radius:4px;"><a href="#" class="vtx-remove-single-img" data-target="'+targetId+'" style="color:#d63638; text-decoration:none; font-weight:bold;">Remover Imagem</a>');
                });
                mediaUploader.open();
            });

            $(document).on('click', '.vtx-remove-single-img', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).val(''); $('#preview_' + targetId).html('');
            });

            $(document).on('click', '.vtx-upload-gallery-img', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var inputField = $('#' + targetId);
                var previewArea = $('#preview_' + targetId);
                mediaUploader = wp.media({ title: 'Adicionar Fotos', multiple: true });
                mediaUploader.on('select', function() {
                    var attachments = mediaUploader.state().get('selection').map(function(a) { return a.toJSON(); });
                    var currentIds = inputField.val() ? inputField.val().split(',') : [];
                    attachments.forEach(function(attachment) {
                        var id = attachment.id.toString();
                        var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                        if($.inArray(id, currentIds) === -1) {
                            currentIds.push(id);
                            previewArea.append('<div class="vtx-img-wrap" data-id="'+id+'" style="position:relative; width:80px; height:80px; border:1px solid #ddd;"><img src="'+url+'" style="width:100%; height:100%; object-fit:cover;"><a href="#" class="vtx-remove-gallery-img" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; line-height:18px; text-decoration:none;">&times;</a></div>');
                        }
                    });
                    inputField.val(currentIds.join(','));
                });
                mediaUploader.open();
            });

            $(document).on('click', '.vtx-remove-gallery-img', function(e) {
                e.preventDefault();
                var wrap = $(this).closest('.vtx-img-wrap');
                var idToRemove = wrap.data('id').toString();
                var containerId = wrap.closest('div[id^="preview_"]').attr('id');
                var targetId = containerId.replace('preview_', '');
                var inputField = $('#' + targetId);
                inputField.val($.grep(inputField.val().split(','), function(value) { return value !== idToRemove; }).join(','));
                wrap.remove();
            });
        });
        </script>
        <?php
    }
}
