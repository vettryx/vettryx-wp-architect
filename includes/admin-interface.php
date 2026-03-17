<?php
if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Hook que dispara logo após a opção ser salva no banco para fazer a migração dos dados
        add_action('update_option_vtx_dynamic_entities', [$this, 'migrate_database_entities'], 10, 2);
    }

    public function add_admin_menu() {
        add_menu_page('VETTRYX Architect', 'Architect', 'manage_options', 'vtx-architect', [$this, 'render_admin_page'], 'dashicons-layout', 80);
    }

    public function register_settings() {
        register_setting('vtx_architect_settings_group', 'vtx_dynamic_entities');
    }

    // ==========================================
    // MOTOR DE MIGRAÇÃO AUTOMÁTICA DE BANCO
    // ==========================================
    public function migrate_database_entities($old_value, $new_value) {
        global $wpdb;
        $entities = json_decode($new_value, true);
        
        if (!is_array($entities)) return;

        foreach ($entities as $e) {
            $old_cpt = !empty($e['old_cpt_slug']) ? sanitize_title($e['old_cpt_slug']) : '';
            $new_cpt = !empty($e['cpt_slug']) ? sanitize_title($e['cpt_slug']) : '';

            // 1. Migração do Post Type e Taxonomias (Se o slug principal mudou)
            if ($old_cpt && $new_cpt && $old_cpt !== $new_cpt) {
                // Atualiza a tabela wp_posts
                $wpdb->update($wpdb->posts, ['post_type' => $new_cpt], ['post_type' => $old_cpt]);
                
                // Atualiza a tabela wp_term_taxonomy (Categorias e Tags atreladas)
                $wpdb->update($wpdb->term_taxonomy, ['taxonomy' => $new_cpt . '_category'], ['taxonomy' => $old_cpt . '_category']);
                $wpdb->update($wpdb->term_taxonomy, ['taxonomy' => $new_cpt . '_tag'], ['taxonomy' => $old_cpt . '_tag']);
            }

            // 2. Migração dos Meta Boxes / Campos Personalizados
            if (!empty($e['fields'])) {
                foreach ($e['fields'] as $f) {
                    $old_id = !empty($f['old_id']) ? sanitize_text_field($f['old_id']) : '';
                    $new_id = !empty($f['id']) ? sanitize_text_field($f['id']) : '';

                    if ($old_id && $new_id && $old_id !== $new_id) {
                        // Atualiza a tabela wp_postmeta
                        $wpdb->update($wpdb->postmeta, ['meta_key' => $new_id], ['meta_key' => $old_id]);
                    }
                }
            }
        }
    }

    // ==========================================
    // INTERFACE VISUAL
    // ==========================================
    public function render_admin_page() {
        $saved_json = get_option('vtx_dynamic_entities', '[]');
        if (empty($saved_json)) $saved_json = '[]';
        ?>
        <div class="wrap">
            <h1>VETTRYX WP Architect 🏗️</h1>
            <p>Construa Entidades (CPTs, Taxonomias e Campos) de forma visual e dinâmica.</p>

            <form method="post" action="options.php" id="vtx-architect-form">
                <?php settings_fields('vtx_architect_settings_group'); ?>
                <input type="hidden" name="vtx_dynamic_entities" id="vtx_dynamic_entities" value="<?php echo esc_attr($saved_json); ?>" />

                <div id="vtx-entities-container"></div>

                <div style="margin-top: 20px;">
                    <button type="button" class="button button-secondary" id="btn-add-entity">+ Adicionar Nova Entidade (CPT)</button>
                    <?php submit_button('Salvar Estruturas', 'primary', 'submit', false, ['style' => 'float: right;']); ?>
                </div>
            </form>
        </div>

        <style>
            .vtx-entity-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-left: 4px solid #023047; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .vtx-entity-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
            .vtx-entity-header h2 { margin: 0; font-size: 1.2em; }
            .vtx-btn-danger { color: #d63638; cursor: pointer; text-decoration: none; font-weight: bold; }
            .vtx-btn-danger:hover { color: #a10000; }
            .vtx-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-section-title { font-weight: 600; margin: 20px 0 10px; padding-top: 15px; border-top: 1px dashed #ccc; color: #444; }
            .vtx-fields-container { background: #f9f9f9; border: 1px solid #e2e4e7; padding: 15px; border-radius: 4px; }
            .vtx-field-row { display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 10px; align-items: start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .vtx-field-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .vtx-input { width: 100%; }
            .vtx-sc-hint-input { margin-top:5px; width:100%; background:transparent; border:none; color:#d63638; font-family:monospace; font-weight:bold; cursor:pointer; padding:0; box-shadow:none !important; }
            .vtx-sc-hint-input:focus { outline:none; }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('vtx-entities-container');
            const btnAddEntity = document.getElementById('btn-add-entity');
            const hiddenInput = document.getElementById('vtx_dynamic_entities');
            const form = document.getElementById('vtx-architect-form');

            let entities = [];
            try { entities = JSON.parse(hiddenInput.value || '[]'); } catch (e) { entities = []; }

            const fieldTemplate = (f = {}, cptSlug = 'slug') => `
                <div class="vtx-field-row">
                    <div>
                        <label>Label do Campo <small>(Ex: Link do Projeto)</small></label>
                        <input type="text" class="regular-text vtx-input f-label" value="${f.label || ''}" placeholder="Nome visual">
                    </div>
                    <div>
                        <label>ID do Campo <small>(Ex: link_projeto)</small></label>
                        <input type="hidden" class="f-old-id" value="${f.id || ''}">
                        <input type="text" class="regular-text vtx-input f-id" value="${f.id || ''}" placeholder="slug_do_campo">
                        <input type="text" readonly class="vtx-sc-hint-input vtx-sc-hint" value="[vtx_${cptSlug}_${f.id || 'id'}]" onfocus="this.select();" title="Clique para copiar">
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
                    <div>
                        <a href="#" class="vtx-btn-danger btn-remove-field" style="display:block; margin-top:25px;" title="Remover Campo">🗑️</a>
                    </div>
                </div>
            `;

            const entityTemplate = (e = {}, index) => `
                <div class="vtx-entity-card" data-index="${index}">
                    <div class="vtx-entity-header">
                        <h2>Entidade #${index + 1}: <span class="vtx-title-preview">${e.cpt_name_plural || 'Nova'}</span></h2>
                        <a href="#" class="vtx-btn-danger btn-remove-entity">Remover Entidade</a>
                    </div>

                    <div class="vtx-grid-4">
                        <div>
                            <label>Slug do CPT <small>(Ex: projetos)</small></label>
                            <input type="hidden" class="e-old-cpt-slug" value="${e.cpt_slug || ''}">
                            <input type="text" class="vtx-input e-cpt-slug" value="${e.cpt_slug || ''}" required>
                        </div>
                        <div>
                            <label>Nome Plural <small>(Ex: Projetos)</small></label>
                            <input type="text" class="vtx-input e-cpt-plural" value="${e.cpt_name_plural || ''}" required>
                        </div>
                        <div>
                            <label>Nome Singular <small>(Ex: Projeto)</small></label>
                            <input type="text" class="vtx-input e-cpt-singular" value="${e.cpt_name_singular || ''}" required>
                        </div>
                        <div>
                            <label>Dashicon <small>(Ex: dashicons-portfolio)</small></label>
                            <input type="text" class="vtx-input e-icon" value="${e.icon || 'dashicons-admin-post'}">
                        </div>
                    </div>

                    <div class="vtx-section-title">Taxonomias (Opcional)</div>
                    <div class="vtx-grid-2">
                        <div style="background:#f0f0f1; padding:10px; border-radius:4px;">
                            <strong>Categorias</strong><br><br>
                            <label>Slug: </label> <input type="text" class="vtx-input e-cat-slug" value="${e.cat_slug || ''}" placeholder="Ex: tipo-projeto"><br><br>
                            <label>Nome: </label> <input type="text" class="vtx-input e-cat-name" value="${e.cat_name || ''}" placeholder="Ex: Tipos de Projeto"><br><br>
                            <label style="font-size:12px; color:#666;">Shortcode da Categoria:</label>
                            <input type="text" readonly class="vtx-sc-hint-input vtx-sc-cat-hint" value="[vtx_${e.cpt_slug || 'slug'}_categorias]" onfocus="this.select();" title="Clique para copiar">
                        </div>
                        <div style="background:#f0f0f1; padding:10px; border-radius:4px;">
                            <strong>Tags (Micro-segmentação)</strong><br><br>
                            <label>Slug: </label> <input type="text" class="vtx-input e-tag-slug" value="${e.tag_slug || ''}" placeholder="Ex: detalhe-projeto"><br><br>
                            <label>Nome: </label> <input type="text" class="vtx-input e-tag-name" value="${e.tag_name || ''}" placeholder="Ex: Detalhes do Projeto"><br><br>
                            <label style="font-size:12px; color:#666;">Shortcode da Tag:</label>
                            <input type="text" readonly class="vtx-sc-hint-input vtx-sc-tag-hint" value="[vtx_${e.cpt_slug || 'slug'}_tags]" onfocus="this.select();" title="Clique para copiar">
                        </div>
                    </div>

                    <div class="vtx-section-title">Campos Personalizados (Meta Boxes)</div>
                    <div class="vtx-fields-container">
                        <div class="fields-wrapper">
                            ${(e.fields || []).map(f => fieldTemplate(f, e.cpt_slug)).join('')}
                        </div>
                        <button type="button" class="button btn-add-field" style="margin-top: 15px;">+ Adicionar Campo</button>
                    </div>
                </div>
            `;

            function render() { container.innerHTML = entities.map((e, i) => entityTemplate(e, i)).join(''); }

            btnAddEntity.addEventListener('click', () => { entities.push({ fields: [] }); render(); });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-remove-entity')) {
                    e.preventDefault();
                    if(confirm('Tem certeza que deseja remover esta entidade inteira?')) {
                        const index = e.target.closest('.vtx-entity-card').dataset.index;
                        entities.splice(index, 1);
                        render();
                    }
                }
                if (e.target.classList.contains('btn-add-field')) {
                    e.preventDefault();
                    const card = e.target.closest('.vtx-entity-card');
                    const cptSlug = card.querySelector('.e-cpt-slug').value.trim() || 'slug';
                    e.target.previousElementSibling.insertAdjacentHTML('beforeend', fieldTemplate({}, cptSlug));
                }
                if (e.target.classList.contains('btn-remove-field')) {
                    e.preventDefault();
                    e.target.closest('.vtx-field-row').remove();
                }
            });

            container.addEventListener('input', function(e) {
                const card = e.target.closest('.vtx-entity-card');
                if (!card) return;

                if (e.target.classList.contains('e-cpt-plural')) {
                    card.querySelector('.vtx-title-preview').innerText = e.target.value || 'Nova';
                }

                if (e.target.classList.contains('e-cpt-slug') || e.target.classList.contains('f-id')) {
                    const cptSlug = card.querySelector('.e-cpt-slug').value.trim() || 'slug';
                    
                    card.querySelectorAll('.vtx-field-row').forEach(row => {
                        const fId = row.querySelector('.f-id').value.trim() || 'id';
                        const hint = row.querySelector('.vtx-sc-hint');
                        if (hint) hint.value = `[vtx_${cptSlug}_${fId}]`;
                    });

                    const catHint = card.querySelector('.vtx-sc-cat-hint');
                    if (catHint) catHint.value = `[vtx_${cptSlug}_categorias]`;

                    const tagHint = card.querySelector('.vtx-sc-tag-hint');
                    if (tagHint) tagHint.value = `[vtx_${cptSlug}_tags]`;
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
                        cat_slug: card.querySelector('.e-cat-slug').value.trim(),
                        cat_name: card.querySelector('.e-cat-name').value.trim(),
                        tag_slug: card.querySelector('.e-tag-slug').value.trim(),
                        tag_name: card.querySelector('.e-tag-name').value.trim(),
                        fields: []
                    };

                    card.querySelectorAll('.vtx-field-row').forEach(row => {
                        const id = row.querySelector('.f-id').value.trim();
                        const old_id = row.querySelector('.f-old-id').value.trim();
                        const label = row.querySelector('.f-label').value.trim();
                        if (id && label) {
                            entity.fields.push({ old_id: old_id, id: id, label: label, type: row.querySelector('.f-type').value });
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
