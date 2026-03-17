<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('update_option_vtx_dynamic_entities', [$this, 'migrate_database_entities'], 10, 2);
    }

    public function add_admin_menu() {
        add_menu_page('VETTRYX Architect', 'Architect', 'manage_options', 'vtx-architect', [$this, 'render_admin_page'], 'dashicons-layout', 80);
    }

    public function register_settings() {
        register_setting('vtx_architect_settings_group', 'vtx_dynamic_entities');
    }

    public function migrate_database_entities($old_value, $new_value) {
        global $wpdb;
        $entities = json_decode($new_value, true);
        if (!is_array($entities)) return;

        foreach ($entities as $e) {
            $old_cpt = !empty($e['old_cpt_slug']) ? sanitize_title($e['old_cpt_slug']) : '';
            $new_cpt = !empty($e['cpt_slug']) ? sanitize_title($e['cpt_slug']) : '';

            if ($old_cpt && $new_cpt && $old_cpt !== $new_cpt) {
                $wpdb->update($wpdb->posts, ['post_type' => $new_cpt], ['post_type' => $old_cpt]);
                $wpdb->update($wpdb->term_taxonomy, ['taxonomy' => $new_cpt . '_category'], ['taxonomy' => $old_cpt . '_category']);
                $wpdb->update($wpdb->term_taxonomy, ['taxonomy' => $new_cpt . '_tag'], ['taxonomy' => $old_cpt . '_tag']);
            }

            if (!empty($e['fields'])) {
                foreach ($e['fields'] as $f) {
                    $old_id = !empty($f['old_id']) ? sanitize_text_field($f['old_id']) : '';
                    $new_id = !empty($f['id']) ? sanitize_text_field($f['id']) : '';
                    if ($old_id && $new_id && $old_id !== $new_id) {
                        $wpdb->update($wpdb->postmeta, ['meta_key' => $new_id], ['meta_key' => $old_id]);
                    }
                }
            }
        }
    }

    public function render_admin_page() {
        $saved_json = get_option('vtx_dynamic_entities', '[]');
        if (empty($saved_json)) $saved_json = '[]';

        global $wpdb;
        $post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
        $available_imports = [];

        foreach ($post_types as $pt) {
            $slug = $pt->name;
            $taxonomies = get_object_taxonomies($slug, 'objects');
            $cat_slug = ''; $cat_name = ''; $tag_slug = ''; $tag_name = '';
            foreach ($taxonomies as $tax) {
                if ($tax->hierarchical && empty($cat_slug)) { $cat_slug = $tax->name; $cat_name = $tax->labels->name; }
                elseif (!$tax->hierarchical && empty($tag_slug)) { $tag_slug = $tax->name; $tag_name = $tax->labels->name; }
            }

            $meta_keys = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key NOT LIKE '\_%' LIMIT 50", $slug));
            $fields = [];
            if ($meta_keys) {
                foreach ($meta_keys as $key) { $fields[] = ['old_id' => $key, 'id' => $key, 'label' => ucwords(str_replace(['_', '-'], ' ', $key)), 'type' => 'text']; }
            }

            $available_imports[$slug] = [
                'old_cpt_slug' => $slug, 'cpt_slug' => $slug, 'cpt_name_plural' => $pt->labels->name, 'cpt_name_singular' => $pt->labels->singular_name,
                'icon' => $pt->menu_icon ? $pt->menu_icon : 'dashicons-admin-post',
                'cat_slug' => $cat_slug, 'cat_name' => $cat_name, 'tag_slug' => $tag_slug, 'tag_name' => $tag_name, 'fields' => $fields
            ];
        }
        ?>
        <div class="wrap">
            <h1>VETTRYX WP Architect 🏗️</h1>
            <p>Construa Entidades (CPTs, Taxonomias e Campos) de forma visual e dinâmica.</p>

            <form method="post" action="options.php" id="vtx-architect-form">
                <?php settings_fields('vtx_architect_settings_group'); ?>
                <input type="hidden" name="vtx_dynamic_entities" id="vtx_dynamic_entities" value="<?php echo esc_attr($saved_json); ?>" />

                <div id="vtx-entities-container"></div>

                <div style="margin-top: 20px; display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 15px; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="button button-secondary" id="btn-add-entity">+ Adicionar Nova Entidade</button>
                        <span style="color: #ccc;">|</span>
                        <select id="import-cpt-select" style="max-width: 250px;">
                            <option value="">Buscar de outro plugin/tema...</option>
                            <?php foreach($available_imports as $slug => $data): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($data['cpt_name_plural']); ?> (<?php echo esc_html($slug); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button" id="btn-import-dynamic" style="color: #0073aa; border-color: #0073aa;">⚡ Importar</button>
                    </div>
                    <?php submit_button('Salvar Estruturas', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>

        <div id="vtx-icon-picker-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
           <div style="background:#fff; width:600px; max-width:90%; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.2); display:flex; flex-direction:column; max-height:80vh;">
               <div style="padding:15px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                   <h3 style="margin:0;">Selecione o Ícone para o Menu</h3>
                   <button type="button" id="btn-close-icon-picker" style="background:none; border:none; font-size:24px; cursor:pointer; color:#666;">&times;</button>
               </div>
               <div id="vtx-icon-grid" style="padding:20px; display:grid; grid-template-columns:repeat(auto-fill, minmax(45px, 1fr)); gap:15px; overflow-y:auto;">
                  </div>
           </div>
        </div>

        <style>
            .vtx-entity-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-left: 4px solid #023047; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .vtx-entity-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
            .vtx-entity-header h2 { margin: 0; font-size: 1.2em; }
            .vtx-btn-danger { color: #d63638; cursor: pointer; text-decoration: none; font-weight: bold; }
            .vtx-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-section-title { font-weight: 600; margin: 20px 0 10px; padding-top: 15px; border-top: 1px dashed #ccc; color: #444; }
            .vtx-fields-container { background: #f9f9f9; border: 1px solid #e2e4e7; padding: 15px; border-radius: 4px; }
            .vtx-field-row { display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 10px; align-items: start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .vtx-field-row:last-child { border-bottom: none; margin-bottom: 0; }
            .vtx-input { width: 100%; }
            .vtx-sc-hint-input { margin-top:5px; width:100%; background:transparent; border:none; color:#d63638; font-family:monospace; font-weight:bold; cursor:pointer; padding:0; box-shadow:none !important; }
            .vtx-sc-hint-input:focus { outline:none; }
            .vtx-icon-item { font-size: 24px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; color: #555; transition: all 0.2s; }
            .vtx-icon-item:hover { background: #0073aa; color: #fff; border-color: #0073aa; transform: scale(1.1); }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('vtx-entities-container');
            const btnAddEntity = document.getElementById('btn-add-entity');
            const btnImportDynamic = document.getElementById('btn-import-dynamic');
            const importSelect = document.getElementById('import-cpt-select');
            const hiddenInput = document.getElementById('vtx_dynamic_entities');
            const form = document.getElementById('vtx-architect-form');
            const availableImports = <?php echo json_encode($available_imports); ?>;

            // Modal Icon Picker Logic
            const iconModal = document.getElementById('vtx-icon-picker-modal');
            const iconGrid = document.getElementById('vtx-icon-grid');
            let currentIconTarget = null; // Armazena qual card abriu o modal

            const dashiconsList = [
                'dashicons-admin-post', 'dashicons-portfolio', 'dashicons-store', 'dashicons-welcome-learn-more', 
                'dashicons-building', 'dashicons-businessman', 'dashicons-hammer', 'dashicons-art', 
                'dashicons-camera', 'dashicons-video-alt3', 'dashicons-format-gallery', 'dashicons-desktop', 
                'dashicons-smartphone', 'dashicons-megaphone', 'dashicons-calendar-alt', 'dashicons-chart-bar', 
                'dashicons-groups', 'dashicons-awards', 'dashicons-location', 'dashicons-cart', 'dashicons-heart', 
                'dashicons-star-filled', 'dashicons-lightbulb', 'dashicons-money-alt', 'dashicons-shield', 
                'dashicons-database', 'dashicons-cloud', 'dashicons-airplane', 'dashicons-clipboard', 'dashicons-testimonial'
            ];

            // Preenche o modal de ícones
            iconGrid.innerHTML = dashiconsList.map(icon => `<div class="vtx-icon-item" data-icon="${icon}"><span class="dashicons ${icon}"></span></div>`).join('');

            // Fecha o Modal
            document.getElementById('btn-close-icon-picker').addEventListener('click', () => iconModal.style.display = 'none');
            
            // Seleciona o ícone
            iconGrid.addEventListener('click', function(e) {
                const item = e.target.closest('.vtx-icon-item');
                if (item && currentIconTarget) {
                    const selectedIcon = item.getAttribute('data-icon');
                    currentIconTarget.querySelector('.e-icon').value = selectedIcon;
                    currentIconTarget.querySelector('.vtx-icon-preview').className = `vtx-icon-preview dashicons ${selectedIcon}`;
                    iconModal.style.display = 'none';
                }
            });

            let entities = [];
            try { entities = JSON.parse(hiddenInput.value || '[]'); } catch (e) { entities = []; }

            const fieldTemplate = (f = {}, cptSlug = 'slug') => `
                <div class="vtx-field-row">
                    <div>
                        <label>Label do Campo</label>
                        <input type="text" class="regular-text vtx-input f-label" value="${f.label || ''}" placeholder="Nome visual">
                    </div>
                    <div>
                        <label>ID do Campo</label>
                        <input type="hidden" class="f-old-id" value="${f.id || ''}">
                        <input type="text" class="regular-text vtx-input f-id" value="${f.id || ''}" placeholder="slug_do_campo">
                        <input type="text" readonly class="vtx-sc-hint-input vtx-sc-hint" value="[vtx_${cptSlug}_${f.id || 'id'}]" onfocus="this.select();">
                    </div>
                    <div>
                        <label>Tipo</label>
                        <select class="vtx-input f-type">
                            <option value="text" ${f.type === 'text' ? 'selected' : ''}>Texto Curto</option>
                            <option value="textarea" ${f.type === 'textarea' ? 'selected' : ''}>Texto Longo</option>
                            <option value="url" ${f.type === 'url' ? 'selected' : ''}>URL</option>
                            <option value="image" ${f.type === 'image' ? 'selected' : ''}>Imagem Única</option>
                            <option value="gallery" ${f.type === 'gallery' ? 'selected' : ''}>Galeria de Fotos</option>
                        </select>
                    </div>
                    <div><a href="#" class="vtx-btn-danger btn-remove-field" style="display:block; margin-top:25px;">🗑️</a></div>
                </div>
            `;

            const entityTemplate = (e = {}, index) => `
                <div class="vtx-entity-card" data-index="${index}">
                    <div class="vtx-entity-header">
                        <h2>Entidade #${index + 1}: <span class="vtx-title-preview">${e.cpt_name_plural || 'Nova'}</span></h2>
                        <a href="#" class="vtx-btn-danger btn-remove-entity">Remover Entidade</a>
                    </div>

                    <div class="vtx-grid-4">
                        <div><label>Slug do CPT</label><input type="hidden" class="e-old-cpt-slug" value="${e.cpt_slug || ''}"><input type="text" class="vtx-input e-cpt-slug" value="${e.cpt_slug || ''}" required></div>
                        <div><label>Nome Plural</label><input type="text" class="vtx-input e-cpt-plural" value="${e.cpt_name_plural || ''}" required></div>
                        <div><label>Nome Singular</label><input type="text" class="vtx-input e-cpt-singular" value="${e.cpt_name_singular || ''}" required></div>
                        <div>
                            <label>Ícone do Menu</label>
                            <div style="display:flex; gap:10px; align-items:center; margin-top:2px;">
                                <span class="vtx-icon-preview dashicons ${e.icon || 'dashicons-admin-post'}" style="font-size:24px; width:24px; height:24px; color:#555;"></span>
                                <input type="text" class="vtx-input e-icon" value="${e.icon || 'dashicons-admin-post'}" readonly style="background:#f0f0f1; width:calc(100% - 100px);">
                                <button type="button" class="button btn-open-icon-picker">Escolher</button>
                            </div>
                        </div>
                    </div>

                    <div class="vtx-section-title">Página de Arquivo (Global)</div>
                    <div class="vtx-grid-2">
                        <div>
                            <label>Título da Página de Arquivo</label>
                            <input type="text" class="vtx-input e-archive-title" value="${e.archive_title || ''}" placeholder="Ex: Nossos Trabalhos">
                            <label style="font-size:12px; color:#666; display:block; margin-top:5px;">Shortcode: <input type="text" readonly class="vtx-sc-hint-input" value="[vtx_${e.cpt_slug || 'slug'}_arquivo_titulo]" onfocus="this.select();"></label>
                        </div>
                        <div>
                            <label>Descrição da Página de Arquivo</label>
                            <textarea class="vtx-input e-archive-desc" rows="3" placeholder="Descrição para SEO e cabeçalho...">${e.archive_desc || ''}</textarea>
                            <label style="font-size:12px; color:#666; display:block; margin-top:5px;">Shortcode: <input type="text" readonly class="vtx-sc-hint-input" value="[vtx_${e.cpt_slug || 'slug'}_arquivo_descricao]" onfocus="this.select();"></label>
                        </div>
                    </div>

                    <div class="vtx-section-title">Taxonomias (Opcional)</div>
                    <div class="vtx-grid-2">
                        <div style="background:#f0f0f1; padding:10px; border-radius:4px;">
                            <strong>Categorias</strong><br><br>
                            <label>Slug: </label> <input type="text" class="vtx-input e-cat-slug" value="${e.cat_slug || ''}"><br><br>
                            <label>Nome: </label> <input type="text" class="vtx-input e-cat-name" value="${e.cat_name || ''}"><br><br>
                            <label style="font-size:12px; color:#666;">Shortcode: <input type="text" readonly class="vtx-sc-hint-input vtx-sc-cat-hint" value="[vtx_${e.cpt_slug || 'slug'}_categorias]" onfocus="this.select();"></label>
                        </div>
                        <div style="background:#f0f0f1; padding:10px; border-radius:4px;">
                            <strong>Tags</strong><br><br>
                            <label>Slug: </label> <input type="text" class="vtx-input e-tag-slug" value="${e.tag_slug || ''}"><br><br>
                            <label>Nome: </label> <input type="text" class="vtx-input e-tag-name" value="${e.tag_name || ''}"><br><br>
                            <label style="font-size:12px; color:#666;">Shortcode: <input type="text" readonly class="vtx-sc-hint-input vtx-sc-tag-hint" value="[vtx_${e.cpt_slug || 'slug'}_tags]" onfocus="this.select();"></label>
                        </div>
                    </div>

                    <div class="vtx-section-title">Campos Personalizados (Meta Boxes)</div>
                    <div class="vtx-fields-container">
                        <div class="fields-wrapper">${(e.fields || []).map(f => fieldTemplate(f, e.cpt_slug)).join('')}</div>
                        <button type="button" class="button btn-add-field" style="margin-top: 15px;">+ Adicionar Campo</button>
                    </div>
                </div>
            `;

            function render() { container.innerHTML = entities.map((e, i) => entityTemplate(e, i)).join(''); }

            btnAddEntity.addEventListener('click', () => { entities.push({ fields: [] }); render(); });

            btnImportDynamic.addEventListener('click', () => {
                const selectedSlug = importSelect.value;
                if (!selectedSlug) return alert('Selecione um Post Type.');
                const importData = availableImports[selectedSlug];
                if(confirm(`Deseja importar "${importData.cpt_name_plural}"?`)) {
                    entities.push(importData); render(); alert('Importado com sucesso!');
                }
            });

            container.addEventListener('click', function(e) {
                // Abre o picker de ícones
                if (e.target.classList.contains('btn-open-icon-picker')) {
                    e.preventDefault();
                    currentIconTarget = e.target.closest('.vtx-grid-4');
                    iconModal.style.display = 'flex';
                }
                if (e.target.classList.contains('btn-remove-entity')) {
                    e.preventDefault();
                    if(confirm('Remover entidade?')) { entities.splice(e.target.closest('.vtx-entity-card').dataset.index, 1); render(); }
                }
                if (e.target.classList.contains('btn-add-field')) {
                    e.preventDefault();
                    e.target.previousElementSibling.insertAdjacentHTML('beforeend', fieldTemplate({}, e.target.closest('.vtx-entity-card').querySelector('.e-cpt-slug').value.trim() || 'slug'));
                }
                if (e.target.classList.contains('btn-remove-field')) {
                    e.preventDefault(); e.target.closest('.vtx-field-row').remove();
                }
            });

            form.addEventListener('submit', function() {
                const compiledEntities = [];
                container.querySelectorAll('.vtx-entity-card').forEach(card => {
                    const entity = {
                        old_cpt_slug: card.querySelector('.e-old-cpt-slug').value.trim(),
                        cpt_slug: card.querySelector('.e-cpt-slug').value.trim(),
                        cpt_name_plural: card.querySelector('.e-cpt-plural').value.trim(),
                        cpt_name_singular: card.querySelector('.e-cpt-singular').value.trim(),
                        icon: card.querySelector('.e-icon').value.trim(),
                        archive_title: card.querySelector('.e-archive-title').value.trim(),
                        archive_desc: card.querySelector('.e-archive-desc').value.trim(),
                        cat_slug: card.querySelector('.e-cat-slug').value.trim(),
                        cat_name: card.querySelector('.e-cat-name').value.trim(),
                        tag_slug: card.querySelector('.e-tag-slug').value.trim(),
                        tag_name: card.querySelector('.e-tag-name').value.trim(),
                        fields: []
                    };
                    card.querySelectorAll('.vtx-field-row').forEach(row => {
                        const id = row.querySelector('.f-id').value.trim();
                        if (id && row.querySelector('.f-label').value.trim()) {
                            entity.fields.push({ old_id: row.querySelector('.f-old-id').value.trim(), id: id, label: row.querySelector('.f-label').value.trim(), type: row.querySelector('.f-type').value });
                        }
                    });
                    if (entity.cpt_slug && entity.cpt_name_plural) compiledEntities.push(entity);
                });
                hiddenInput.value = JSON.stringify(compiledEntities);
            });
            render();
        });
        </script>
        <?php
    }
}
