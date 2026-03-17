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

// Segurança: Impede o acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal do plugin
 */
class Vettryx_WP_Architect {

    public function __construct() {
        // 1. Interface Admin e Armazenamento (Fase 1)
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Hooks das Fases 2, 3 e 4 (Motor, Meta Boxes, Shortcodes) entrarão aqui depois.
    }

    // ==========================================
    // 1. INTERFACE ADMIN E ARMAZENAMENTO
    // ==========================================

    // 1.1. Adiciona o Menu Principal do Architect
    public function add_admin_menu() {
        // Como é um módulo de construção, criei como menu principal para facilitar o desenvolvimento.
        // Depois podemos jogar como submenu do VETTRYX Core.
        add_menu_page(
            'VETTRYX Architect',
            'Architect',
            'manage_options',
            'vtx-architect',
            [$this, 'render_admin_page'],
            'dashicons-layout',
            80
        );
    }

    // 1.2. Registra a option que salvará a matriz de dados
    public function register_settings() {
        // Esta é a variável global no banco de dados que vai armazenar nosso array de CPTs/Campos
        register_setting('vtx_architect_settings_group', 'vtx_dynamic_entities');
    }

    // 1.3. Enfileira scripts específicos apenas na tela do Architect
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_vtx-architect') {
            return;
        }
        
        // Espaço reservado para carregarmos o CSS/JS do formulário repeater na próxima etapa
    }

    // 1.4. Renderiza a estrutura da página visual
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>VETTRYX WP Architect 🏗️</h1>
            <p>Construa Entidades (CPTs, Taxonomias e Campos) de forma visual e dinâmica.</p>

            <form method="post" action="options.php">
                <?php settings_fields('vtx_architect_settings_group'); ?>
                
                <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-left: 4px solid #023047; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2 style="margin-top: 0;">Construtor de Entidades</h2>
                    <p class="description">A interface de adição de CPTs e campos entrará aqui.</p>
                    
                    <input type="hidden" name="vtx_dynamic_entities" id="vtx_dynamic_entities" value="" />
                </div>

                <?php submit_button('Salvar Estruturas', 'primary', 'submit', true, ['style' => 'margin-top: 20px;']); ?>
            </form>
        </div>
        <?php
    }

}

// Inicializa o plugin
new Vettryx_WP_Architect();
