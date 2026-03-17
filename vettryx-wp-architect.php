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

// Carrega os módulos
require_once plugin_dir_path(__FILE__) . 'includes/admin-interface.php';
require_once plugin_dir_path(__FILE__) . 'includes/dynamic-engine.php';
require_once plugin_dir_path(__FILE__) . 'includes/meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';

/**
 * Classe principal de inicialização
 */
class Vettryx_WP_Architect {
    public function __construct() {
        new Vettryx_WP_Architect_Admin();
        new Vettryx_WP_Architect_Engine();
        new Vettryx_WP_Architect_Meta_Boxes();
        new Vettryx_WP_Architect_Shortcodes();
    }
}

// Inicializa o plugin
new Vettryx_WP_Architect();
