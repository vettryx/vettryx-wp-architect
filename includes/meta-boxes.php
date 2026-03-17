<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Meta_Boxes {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_dynamic_meta_boxes']);
        add_action('save_post', [$this, 'save_dynamic_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);
        add_action('admin_footer', [$this, 'render_media_uploader_js']);
    }

    // 1. Enfileira o uploader do WP
    public function enqueue_media_uploader() {
        wp_enqueue_media();
    }

    // 2. Registra os Meta Boxes
    public function add_dynamic_meta_boxes() {
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);
        if (!is_array($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug']) || empty($e['fields'])) continue;

            add_meta_box(
                'vtx_dynamic_fields_' . $e['cpt_slug'],
                'Dados Adicionais',
                [$this, 'render_meta_box_content'],
                $e['cpt_slug'],
                'normal',
                'high',
                ['fields' => $e['fields']] // Passa os campos como argumento
            );
        }
    }

    // 3. Renderiza o HTML dos campos
    public function render_meta_box_content($post, $metabox) {
        wp_nonce_field('vtx_architect_save_data', 'vtx_architect_nonce');
        $fields = $metabox['args']['fields'];

        echo '<div style="display: grid; gap: 20px;">';

        foreach ($fields as $field) {
            $id = esc_attr($field['id']);
            $value = get_post_meta($post->ID, $id, true);
            $label = esc_html($field['label']);

            echo '<div>';
            echo "<label for='{$id}'><strong>{$label}</strong></label><br>";

            switch ($field['type']) {
                case 'text':
                case 'url':
                    $type = $field['type'] === 'url' ? 'url' : 'text';
                    echo "<input type='{$type}' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' style='width: 100%;' />";
                    break;

                case 'textarea':
                    echo "<textarea id='{$id}' name='{$id}' rows='4' style='width: 100%;'>" . esc_textarea($value) . "</textarea>";
                    break;

                case 'image':
                    $img_url = $value ? wp_get_attachment_image_url($value, 'thumbnail') : '';
                    echo "<input type='hidden' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' />";
                    echo "<div id='preview_{$id}' style='margin-top:10px;'>";
                    if ($img_url) {
                        echo "<img src='{$img_url}' style='max-width:150px; display:block; margin-bottom:10px; border-radius:4px;'>";
                        echo "<a href='#' class='vtx-remove-single-img vtx-btn-danger' data-target='{$id}'>Remover Imagem</a>";
                    }
                    echo "</div>";
                    echo "<button type='button' class='button vtx-upload-single-img' data-target='{$id}'>Selecionar Imagem</button>";
                    break;

                case 'gallery':
                    echo "<input type='hidden' id='{$id}' name='{$id}' value='" . esc_attr($value) . "' />";
                    echo "<button type='button' class='button vtx-upload-gallery-img' data-target='{$id}'>Adicionar Fotos na Galeria</button>";
                    echo "<div id='preview_{$id}' style='display:flex; flex-wrap:wrap; gap:10px; margin-top:15px;'>";
                    if (!empty($value)) {
                        $ids = explode(',', $value);
                        foreach ($ids as $img_id) {
                            $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
                            if ($img_url) {
                                echo "<div class='vtx-img-wrap' data-id='{$img_id}' style='position:relative; width:80px; height:80px; border:1px solid #ddd;'>";
                                echo "<img src='{$img_url}' style='width:100%; height:100%; object-fit:cover;'>";
                                echo "<a href='#' class='vtx-remove-gallery-img' style='position:absolute; top:-5px; right:-5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; line-height:18px; text-decoration:none;'>&times;</a>";
                                echo "</div>";
                            }
                        }
                    }
                    echo "</div>";
                    break;
            }
            echo '</div>';
        }
        echo '</div>';

        // Estilo básico para a lixeira vermelha que reaproveitamos da Fase 1
        echo "<style>.vtx-btn-danger { color: #d63638; text-decoration: none; font-weight: bold; }</style>";
    }

    // 4. Salva os dados no banco
    public function save_dynamic_meta_boxes($post_id) {
        if (!isset($_POST['vtx_architect_nonce']) || !wp_verify_nonce($_POST['vtx_architect_nonce'], 'vtx_architect_save_data')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $post_type = get_post_type($post_id);
        $entities = json_decode(get_option('vtx_dynamic_entities', '[]'), true);

        foreach ($entities as $e) {
            if ($e['cpt_slug'] === $post_type && !empty($e['fields'])) {
                foreach ($e['fields'] as $field) {
                    $id = $field['id'];
                    if (isset($_POST[$id])) {
                        // Sanitiza conforme o tipo
                        if ($field['type'] === 'textarea') {
                            $sanitized = sanitize_textarea_field($_POST[$id]);
                        } elseif ($field['type'] === 'url') {
                            $sanitized = esc_url_raw($_POST[$id]);
                        } else {
                            $sanitized = sanitize_text_field($_POST[$id]);
                        }
                        update_post_meta($post_id, $id, $sanitized);
                    } else {
                        delete_post_meta($post_id, $id);
                    }
                }
                break; // Achou o CPT, não precisa ler o resto
            }
        }
    }

    // 5. Motor JavaScript do Uploader de Mídia
    public function render_media_uploader_js() {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') return; // Roda apenas em telas de edição
        ?>
        <script>
        jQuery(document).ready(function($){
            var mediaUploader;

            // --- IMAGEM ÚNICA ---
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
                    previewArea.html('<img src="'+imgUrl+'" style="max-width:150px; display:block; margin-bottom:10px; border-radius:4px;"><a href="#" class="vtx-remove-single-img vtx-btn-danger" data-target="'+targetId+'">Remover Imagem</a>');
                });
                mediaUploader.open();
            });

            $(document).on('click', '.vtx-remove-single-img', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                $('#preview_' + targetId).html('');
            });

            // --- GALERIA MULTIPLA ---
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

                var currentIds = inputField.val().split(',');
                var newIds = $.grep(currentIds, function(value) { return value !== idToRemove; });

                inputField.val(newIds.join(','));
                wrap.remove();
            });
        });
        </script>
        <?php
    }
}
