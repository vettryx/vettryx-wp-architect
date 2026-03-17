<?php
/**
 * Plugin Name: VETTRYX WP Architect
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-architect
 * Description: Motor dinâmico da VETTRYX para criação de Custom Post Types (CPTs), Taxonomias, Meta Boxes e Shortcodes sem plugins de terceiros.
 * Version:     1.0.0
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * License:     Proprietária (Uso Comercial Exclusivo)
 * Vettryx Icon: dashicons-layout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vettryx_WP_Architect {

    public function __construct() {
        // Fase 1: Interface e Armazenamento
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Fase 2: Motor de Ignição (Registro no WP)
        add_action('init', [$this, 'register_dynamic_entities']);
        
        // Flush de permalinks automático ao salvar as configurações
        add_action('update_option_vtx_dynamic_entities', 'flush_rewrite_rules');
    }

    // ==========================================
    // 1. MOTOR DE IGNIÇÃO (FASE 2)
    // ==========================================
    
    public function register_dynamic_entities() {
        $saved_json = get_option('vtx_dynamic_entities', '[]');
        $entities = json_decode($saved_json, true);

        if (!is_array($entities) || empty($entities)) return;

        foreach ($entities as $e) {
            if (empty($e['cpt_slug']) || empty($e['cpt_name_plural'])) continue;

            $slug = sanitize_title($e['cpt_slug']);
            
            // 1.1. Registra o Custom Post Type
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
                'show_in_rest'       => true, // Essencial para Elementor e Gutenberg
                'rewrite'            => ['slug' => $slug, 'with_front' => false],
            ];

            register_post_type($slug, $args);

            // 1.2. Registra Taxonomia (Categorias)
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
                // Cria a taxonomia amarrando ao CPT gerado
                register_taxonomy($slug . '_category', [$slug], $cat_args);
            }

            // 1.3. Registra Taxonomia (Tags)
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

    // ==========================================
    // 2. INTERFACE ADMIN E ARMAZENAMENTO (FASE 1)
    // ==========================================

    public function add_admin_menu() {
        add_menu_page('VETTRYX Architect', 'Architect', 'manage_options', 'vtx-architect', [$this, 'render_admin_page'], 'dashicons-layout', 80);
    }

    public function register_settings() {
        register_setting('vtx_architect_settings_group', 'vtx_dynamic_entities');
    }

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
            .vtx-field-row { display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .vtx-field-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .vtx-input { width: 100%; }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('vtx-entities-container');
            const btnAddEntity = document.getElementById('btn-add-entity');
            const hiddenInput = document.getElementById('vtx_dynamic_entities');
            const form = document.getElementById('vtx-architect-form');

            let entities = [];
            try { entities = JSON.parse(hiddenInput.value || '[]'); } catch (e) { entities = []; }

            const fieldTemplate = (f = {}) => `
                <div class="vtx-field-row">
                    <div>
                        <label>Label do Campo <small>(Ex: Link do Projeto)</small></label>
                        <input type="text" class="regular-text vtx-input f-label" value="${f.label || ''}" placeholder="Nome visual">
                    </div>
                    <div>
                        <label>ID do Campo <small>(Ex: link_projeto)</small></label>
                        <input type="text" class="regular-text vtx-input f-id" value="${f.id || ''}" placeholder="slug_do_campo">
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
                        <a href="#" class="vtx-btn-danger btn-remove-field" title="Remover Campo">🗑️</a>
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
                            <label>Nome: </label> <input type="text" class="vtx-input e-cat-name" value="${e.cat_name || ''}" placeholder="Ex: Tipos de Projeto">
                        </div>
                        <div style="background:#f0f0f1; padding:10px; border-radius:4px;">
                            <strong>Tags (Micro-segmentação)</strong><br><br>
                            <label>Slug: </label> <input type="text" class="vtx-input e-tag-slug" value="${e.tag_slug || ''}" placeholder="Ex: detalhe-projeto"><br><br>
                            <label>Nome: </label> <input type="text" class="vtx-input e-tag-name" value="${e.tag_name || ''}" placeholder="Ex: Detalhes do Projeto">
                        </div>
                    </div>

                    <div class="vtx-section-title">Campos Personalizados (Meta Boxes)</div>
                    <div class="vtx-fields-container">
                        <div class="fields-wrapper">
                            ${(e.fields || []).map(f => fieldTemplate(f)).join('')}
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
                    e.target.previousElementSibling.insertAdjacentHTML('beforeend', fieldTemplate());
                }
                if (e.target.classList.contains('btn-remove-field')) {
                    e.preventDefault();
                    e.target.closest('.vtx-field-row').remove();
                }
            });

            container.addEventListener('input', function(e) {
                if (e.target.classList.contains('e-cpt-plural')) {
                    e.target.closest('.vtx-entity-card').querySelector('.vtx-title-preview').innerText = e.target.value || 'Nova';
                }
            });

            form.addEventListener('submit', function() {
                const compiledEntities = [];
                container.querySelectorAll('.vtx-entity-card').forEach(card => {
                    const entity = {
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
                        const label = row.querySelector('.f-label').value.trim();
                        if (id && label) {
                            entity.fields.push({ id: id, label: label, type: row.querySelector('.f-type').value });
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

// Inicia o plugin
new Vettryx_WP_Architect();
