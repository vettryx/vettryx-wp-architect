<?php
/**
 * Arquivo: modules/architect/includes/admin-interface.php
 * 
 * Responsável pela interface administrativa do módulo Architect.
 * 
 * @package VETTRYX_WP_Core
 * @subpackage Architect
 * @author André Ventura
 * @since 1.0.0
 */

// Segurança: Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Classe principal do módulo Architect
class Vettryx_WP_Architect_Admin {

    // Nome da opção no banco de dados onde as entidades dinâmicas serão salvas
    private $option_name = 'vtx_dynamic_entities';

    // Construtor
    public function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_action( 'update_option_' . $this->option_name, [ $this, 'migrate_database_entities' ], 10, 2 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        }
    }

    /**
     * Adiciona a página de submenu no painel de administração
     */
    public function add_submenu_page() {
        add_submenu_page(
            'vettryx-core-modules',
            'Architect',
            'Architect',
            'manage_options',
            'vtx-architect',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Registra as configurações no WordPress
     */
    public function register_settings() { register_setting( 'vtx_architect_settings_group', $this->option_name ); }

    /**
     * Enfileira os assets necessários para a página de administração
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'vtx-architect' ) === false ) return;
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        wp_enqueue_script( 'vtx-dashicons-list', $plugin_url . 'assets/js/vtx-dashicons.js', [], '1.0.0', true );
    }

    /**
     * Migra as entidades dinâmicas no banco de dados
     */
    public function migrate_database_entities( $old_value, $new_value ) {
        global $wpdb;
        $entities = json_decode( $new_value, true );
        if ( ! is_array( $entities ) ) return;

        foreach ( $entities as $e ) {
            $old_cpt = ! empty( $e['old_cpt_slug'] ) ? sanitize_title( $e['old_cpt_slug'] ) : '';
            $new_cpt = ! empty( $e['cpt_slug'] ) ? sanitize_title( $e['cpt_slug'] ) : '';

            if ( $old_cpt && $new_cpt && $old_cpt !== $new_cpt ) {
                $wpdb->update( $wpdb->posts, [ 'post_type' => $new_cpt ], [ 'post_type' => $old_cpt ] );
                $wpdb->update( $wpdb->term_taxonomy, [ 'taxonomy' => $new_cpt . '_category' ], [ 'taxonomy' => $old_cpt . '_category' ] );
                $wpdb->update( $wpdb->term_taxonomy, [ 'taxonomy' => $new_cpt . '_tag' ], [ 'taxonomy' => $old_cpt . '_tag' ] );
            }

            if ( ! empty( $e['fields'] ) ) {
                foreach ( $e['fields'] as $f ) {
                    $old_id = ! empty( $f['old_id'] ) ? sanitize_text_field( $f['old_id'] ) : '';
                    $new_id = ! empty( $f['id'] ) ? sanitize_text_field( $f['id'] ) : '';
                    if ( $old_id && $new_id && $old_id !== $new_id ) {
                        $wpdb->update( $wpdb->postmeta, [ 'meta_key' => $new_id ], [ 'meta_key' => $old_id ] );
                    }
                }
            }
        }
    }

    /**
     * Renderiza a página de administração
     */
    public function render_admin_page() {
        $saved_json = get_option( $this->option_name, '[]' );
        if ( empty( $saved_json ) ) $saved_json = '[]';
        global $wpdb;
        $post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $available_imports = [];

        foreach ( $post_types as $pt ) {
            $slug = $pt->name;
            $taxonomies = get_object_taxonomies( $slug, 'objects' );
            $cat_slug = ''; $cat_name = ''; $tag_slug = ''; $tag_name = '';
            foreach ( $taxonomies as $tax ) {
                if ( $tax->hierarchical && empty( $cat_slug ) ) { $cat_slug = $tax->name; $cat_name = $tax->labels->name; }
                elseif ( ! $tax->hierarchical && empty( $tag_slug ) ) { $tag_slug = $tax->name; $tag_name = $tax->labels->name; }
            }
            $meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key NOT LIKE '\_%' LIMIT 50", $slug ) );
            $fields = [];
            if ( $meta_keys ) {
                foreach ( $meta_keys as $key ) { $fields[] = [ 'old_id' => $key, 'id' => $key, 'label' => ucwords( str_replace( [ '_', '-' ], ' ', $key ) ), 'type' => 'text' ]; }
            }
            $available_imports[ $slug ] = [ 'old_cpt_slug' => $slug, 'cpt_slug' => $slug, 'cpt_name_plural' => $pt->labels->name, 'cpt_name_singular' => $pt->labels->singular_name, 'icon' => $pt->menu_icon ? $pt->menu_icon : 'dashicons-admin-post', 'cat_slug' => $cat_slug, 'cat_name' => $cat_name, 'tag_slug' => $tag_slug, 'tag_name' => $tag_name, 'fields' => $fields ];
        }
        ?>
        <div class="wrap">
            <h1><?php _e( 'VETTRYX Architect 🏗️', 'vettryx-wp-core' ); ?></h1>
            <p><?php _e( 'Construa Entidades (CPTs, Taxonomias e Campos) de forma visual e dinâmica.', 'vettryx-wp-core' ); ?></p>

            <form method="post" action="options.php" id="vtx-architect-form">
                <?php settings_fields( 'vtx_architect_settings_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>" id="vtx_dynamic_entities" value="<?php echo esc_attr( $saved_json ); ?>" />

                <div id="vtx-entities-container"></div>

                <div style="margin-top: 20px; display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 15px; border-left: 4px solid #023047; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="button button-secondary" id="btn-add-entity">+ Adicionar Nova Entidade</button>
                        <span style="color: #ccc;">|</span>
                        <select id="import-cpt-select" style="max-width: 250px;"><option value="">Buscar de outro plugin/tema...</option>
                            <?php foreach( $available_imports as $slug => $data ): ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $data['cpt_name_plural'] ); ?> (<?php echo esc_html( $slug ); ?>)</option><?php endforeach; ?>
                        </select>
                        <button type="button" class="button" id="btn-import-dynamic" style="color: #0073aa; border-color: #0073aa;">⚡ Importar</button>
                    </div>
                    <?php submit_button( 'Salvar Estruturas', 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>

        <div id="vtx-icon-picker-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
           <div style="background:#fff; width:600px; max-width:90%; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.2); display:flex; flex-direction:column; max-height:80vh;">
               <div style="padding:15px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                   <h3 style="margin:0;">Selecione o Ícone</h3>
                   <button type="button" id="btn-close-icon-picker" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
               </div>
               <div id="vtx-icon-grid" style="padding:20px; display:grid; grid-template-columns:repeat(auto-fill, minmax(45px, 1fr)); gap:15px; overflow-y:auto;"></div>
           </div>
        </div>

        <style>
            .vtx-entity-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-left: 4px solid #023047; margin-bottom: 20px; }
            .vtx-entity-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
            .vtx-btn-danger { color: #d63638; cursor: pointer; text-decoration: none; font-weight: bold; }
            .vtx-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .vtx-section-title { font-weight: 600; margin: 20px 0 10px; padding-top: 15px; border-top: 1px dashed #ccc; }
            .vtx-fields-container { background: #f9f9f9; border: 1px solid #e2e4e7; padding: 15px; border-radius: 4px; }
            .vtx-field-row { display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 10px; align-items: start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .vtx-input { width: 100%; }
            .vtx-sc-hint-input { margin-top:5px; width:100%; background:transparent; border:none; color:#d63638; font-family:monospace; font-weight:bold; cursor:pointer; box-shadow:none !important; }
            .vtx-icon-item { font-size: 24px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; cursor: pointer; }
            .vtx-icon-item:hover { background: #0073aa; color: #fff; }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('vtx-entities-container');
            const hiddenInput = document.getElementById('vtx_dynamic_entities');
            const availableImports = <?php echo json_encode( $available_imports ); ?>;
            const iconModal = document.getElementById('vtx-icon-picker-modal');
            const iconGrid = document.getElementById('vtx-icon-grid');
            let currentIconTarget = null; 

            const dashiconsList = window.vtxDashiconsList || ['dashicons-admin-post'];
            iconGrid.innerHTML = dashiconsList.map(icon => `<div class="vtx-icon-item" data-icon="${icon}" title="${icon}"><span class="dashicons ${icon}"></span></div>`).join('');
            document.getElementById('btn-close-icon-picker').addEventListener('click', () => iconModal.style.display = 'none');
            
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
                        <input type="text" class="regular-text vtx-input f-label" value="${f.label || ''}">
                    </div>
                    <div>
                        <label>ID do Campo</label>
                        <input type="hidden" class="f-old-id" value="${f.id || ''}">
                        <input type="text" class="regular-text vtx-input f-id" value="${f.id || ''}">
                        <input type="text" readonly class="vtx-sc-hint-input vtx-sc-hint" value="[vtx_${cptSlug}_${f.id || 'id'}]" onfocus="this.select();">
                    </div>
                    <div>
                        <label>Tipo</label>
                        <select class="vtx-input f-type">
                            <option value="text" ${f.type === 'text' ? 'selected' : ''}>Texto Curto</option>
                            <option value="textarea" ${f.type === 'textarea' ? 'selected' : ''}>Texto Longo</option>
                            <option value="url" ${f.type === 'url' ? 'selected' : ''}>URL</option>
                            <option value="date" ${f.type === 'date' ? 'selected' : ''}>Data (Dia/Mês/Ano)</option>
                            <option value="image" ${f.type === 'image' ? 'selected' : ''}>Imagem Única</option>
                            <option value="gallery" ${f.type === 'gallery' ? 'selected' : ''}>Galeria de Fotos</option>
                        </select>
                        <div class="vtx-date-slug-opt" style="display: ${f.type === 'date' ? 'block' : 'none'}; margin-top: 10px; background: #fff; padding: 10px; border-left: 3px solid #0073aa;">
                            <label><input type="checkbox" class="f-use-slug" ${f.use_as_slug ? 'checked' : ''}> 🔗 <strong>Usar data na URL</strong> (Ex: 20261201-post)</label>
                        </div>
                    </div>
                    <div><a href="#" class="vtx-btn-danger btn-remove-field" style="display:block; margin-top:25px;">🗑️</a></div>
                </div>
            `;

            const entityTemplate = (e = {}, index) => `
                <div class="vtx-entity-card" data-index="${index}">
                    <div class="vtx-entity-header">
                        <h2>Entidade #${index + 1}: <span class="vtx-title-preview">${e.cpt_name_plural || 'Nova'}</span></h2>
                        <a href="#" class="vtx-btn-danger btn-remove-entity">Remover</a>
                    </div>
                    <div class="vtx-grid-4">
                        <div><label>Slug</label><input type="hidden" class="e-old-cpt-slug" value="${e.cpt_slug || ''}"><input type="text" class="vtx-input e-cpt-slug" value="${e.cpt_slug || ''}" required></div>
                        <div><label>Plural</label><input type="text" class="vtx-input e-cpt-plural" value="${e.cpt_name_plural || ''}" required></div>
                        <div><label>Singular</label><input type="text" class="vtx-input e-cpt-singular" value="${e.cpt_name_singular || ''}" required></div>
                        <div>
                            <label>Ícone</label>
                            <div style="display:flex; gap:10px; align-items:center; margin-top:2px;">
                                <span class="vtx-icon-preview dashicons ${e.icon || 'dashicons-admin-post'}"></span>
                                <input type="text" class="vtx-input e-icon" value="${e.icon || 'dashicons-admin-post'}" readonly style="width:calc(100% - 90px);">
                                <button type="button" class="button btn-open-icon-picker">Escolher</button>
                            </div>
                        </div>
                    </div>
                    <div class="vtx-section-title">Página de Arquivo (Global)</div>
                    <div class="vtx-grid-2">
                        <div><label>Título</label><input type="text" class="vtx-input e-archive-title" value="${e.archive_title || ''}"></div>
                        <div><label>Descrição</label><textarea class="vtx-input e-archive-desc" rows="2">${e.archive_desc || ''}</textarea></div>
                    </div>
                    <div class="vtx-section-title">Taxonomias</div>
                    <div class="vtx-grid-2">
                        <div style="background:#f0f0f1; padding:10px;"><strong>Categorias</strong><br><label>Slug: </label> <input type="text" class="vtx-input e-cat-slug" value="${e.cat_slug || ''}"><br><label>Nome: </label> <input type="text" class="vtx-input e-cat-name" value="${e.cat_name || ''}"></div>
                        <div style="background:#f0f0f1; padding:10px;"><strong>Tags</strong><br><label>Slug: </label> <input type="text" class="vtx-input e-tag-slug" value="${e.tag_slug || ''}"><br><label>Nome: </label> <input type="text" class="vtx-input e-tag-name" value="${e.tag_name || ''}"></div>
                    </div>
                    <div class="vtx-section-title">Campos (Meta Boxes)</div>
                    <div class="vtx-fields-container">
                        <div class="fields-wrapper">${(e.fields || []).map(f => fieldTemplate(f, e.cpt_slug)).join('')}</div>
                        <button type="button" class="button btn-add-field" style="margin-top: 15px;">+ Adicionar Campo</button>
                    </div>
                </div>
            `;

            function render() { container.innerHTML = entities.map((e, i) => entityTemplate(e, i)).join(''); }
            document.getElementById('btn-add-entity').addEventListener('click', () => { entities.push({ fields: [] }); render(); });
            document.getElementById('btn-import-dynamic').addEventListener('click', () => {
                const sel = document.getElementById('import-cpt-select').value;
                if (!sel) return;
                entities.push(availableImports[sel]); render(); alert('Importado!');
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-open-icon-picker')) { e.preventDefault(); currentIconTarget = e.target.closest('.vtx-grid-4'); iconModal.style.display = 'flex'; }
                if (e.target.classList.contains('btn-remove-entity')) { e.preventDefault(); if(confirm('Remover?')) { entities.splice(e.target.closest('.vtx-entity-card').dataset.index, 1); render(); } }
                if (e.target.classList.contains('btn-add-field')) { e.preventDefault(); e.target.previousElementSibling.insertAdjacentHTML('beforeend', fieldTemplate({}, e.target.closest('.vtx-entity-card').querySelector('.e-cpt-slug').value.trim() || 'slug')); }
                if (e.target.classList.contains('btn-remove-field')) { e.preventDefault(); e.target.closest('.vtx-field-row').remove(); }
            });

            container.addEventListener('change', function(e) {
                if (e.target.classList.contains('f-type')) {
                    const dateOpt = e.target.closest('.vtx-field-row').querySelector('.vtx-date-slug-opt');
                    if(dateOpt) dateOpt.style.display = e.target.value === 'date' ? 'block' : 'none';
                }
            });

            document.getElementById('vtx-architect-form').addEventListener('submit', function() {
                const compiledEntities = [];
                container.querySelectorAll('.vtx-entity-card').forEach(card => {
                    const entity = {
                        old_cpt_slug: card.querySelector('.e-old-cpt-slug').value.trim(), cpt_slug: card.querySelector('.e-cpt-slug').value.trim(),
                        cpt_name_plural: card.querySelector('.e-cpt-plural').value.trim(), cpt_name_singular: card.querySelector('.e-cpt-singular').value.trim(),
                        icon: card.querySelector('.e-icon').value.trim(), archive_title: card.querySelector('.e-archive-title').value.trim(),
                        archive_desc: card.querySelector('.e-archive-desc').value.trim(), cat_slug: card.querySelector('.e-cat-slug').value.trim(),
                        cat_name: card.querySelector('.e-cat-name').value.trim(), tag_slug: card.querySelector('.e-tag-slug').value.trim(),
                        tag_name: card.querySelector('.e-tag-name').value.trim(), fields: []
                    };
                    card.querySelectorAll('.vtx-field-row').forEach(row => {
                        const id = row.querySelector('.f-id').value.trim();
                        if (id && row.querySelector('.f-label').value.trim()) {
                            entity.fields.push({ 
                                old_id: row.querySelector('.f-old-id').value.trim(), id: id, label: row.querySelector('.f-label').value.trim(), 
                                type: row.querySelector('.f-type').value, 
                                use_as_slug: row.querySelector('.f-use-slug') ? row.querySelector('.f-use-slug').checked : false 
                            });
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
