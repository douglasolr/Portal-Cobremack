<?php
/**
 * Plugin Name: PWL Encomendas
 * Description: Registro, avisos e controle de retirada de encomendas recebidas na Portaria.
 * Version: 1.5.11
 * Author: PWL
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: pwl-encomendas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PWL_Encomendas {
    const VERSION    = '1.5.11';
    const DB_VERSION = '1.9.0';
    const MODULE_ID  = 'encomendas';
    const CAP_VIEW   = 'encomendas.view';
    const CAP_MANAGE = 'encomendas.manage';
    const CAP_ADMIN  = 'encomendas.admin';
    const CAP_HYBRID = 'encomendas.hybrid';

    private static $instance = null;
    private static $shortcode_rendered = false;
    private $table;
    private $events;
    private $carriers;
    private $packagings;
    private $groups;
    private $group_members;
    private $package_recipients;
    private $sequences;
    private $notices;
    private $active_recipient_snapshot_id = 0;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table  = $wpdb->prefix . 'pwl_encomendas';
        $this->events = $wpdb->prefix . 'pwl_encomendas_events';
        $this->carriers = $wpdb->prefix . 'pwl_encomendas_carriers';
        $this->packagings = $wpdb->prefix . 'pwl_encomendas_packagings';
        $this->groups = $wpdb->prefix . 'pwl_encomendas_groups';
        $this->group_members = $wpdb->prefix . 'pwl_encomendas_group_members';
        $this->package_recipients = $wpdb->prefix . 'pwl_encomendas_package_recipients';
        $this->sequences = $wpdb->prefix . 'pwl_encomendas_sequences';
        $this->notices = $wpdb->prefix . 'pwl_encomendas_notices';

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
        add_action( 'plugins_loaded', array( $this, 'register_module' ), 30 );
        add_shortcode( 'pwl_encomendas', array( $this, 'shortcode' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_post_pwl_encomendas_create', array( $this, 'handle_create' ) );
        add_action( 'admin_post_pwl_encomendas_notice_create', array( $this, 'handle_notice_create' ) );
        add_action( 'admin_post_pwl_encomendas_notice_cancel', array( $this, 'handle_notice_cancel' ) );
        add_action( 'admin_post_pwl_encomendas_pickup', array( $this, 'handle_pickup' ) );
        add_action( 'admin_post_pwl_encomendas_retry', array( $this, 'handle_retry' ) );
        add_action( 'admin_post_pwl_encomendas_delete_bulk', array( $this, 'handle_delete_bulk' ) );
        add_action( 'admin_post_pwl_encomendas_carrier_save', array( $this, 'handle_carrier_save' ) );
        add_action( 'admin_post_pwl_encomendas_carrier_toggle', array( $this, 'handle_carrier_toggle' ) );
        add_action( 'admin_post_pwl_encomendas_packaging_save', array( $this, 'handle_packaging_save' ) );
        add_action( 'admin_post_pwl_encomendas_packaging_toggle', array( $this, 'handle_packaging_toggle' ) );
        add_action( 'admin_post_pwl_encomendas_group_save', array( $this, 'handle_group_save' ) );
        add_action( 'admin_post_pwl_encomendas_group_toggle', array( $this, 'handle_group_toggle' ) );
        add_action( 'admin_post_pwl_encomendas_meta_save', array( $this, 'handle_meta_save' ) );
        add_action( 'admin_post_pwl_encomendas_teams_save', array( $this, 'handle_teams_save' ) );
        add_action( 'wp_ajax_pwl_encomendas_meta_signup_event', array( $this, 'handle_meta_signup_event' ) );
        add_filter( 'pwl_notificacoes_email_html', array( $this, 'limit_notification_email_logo' ), 20 );
    }

    public function activate() {
        $this->install_schema();
    }

    public function deactivate() {}

    public function maybe_upgrade() {
        if ( version_compare( get_option( 'pwl_encomendas_db_version', '0' ), self::DB_VERSION, '<' ) ) {
            $this->install_schema();
        }
    }

    private function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recipient_type varchar(16) NOT NULL DEFAULT 'person',
            recipient_group_id bigint(20) unsigned NOT NULL DEFAULT 0,
            recipient_person_id bigint(20) unsigned NOT NULL,
            recipient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            recipient_name varchar(190) NOT NULL,
            recipient_email varchar(190) NOT NULL DEFAULT '',
            recipient_mobile varchar(40) NOT NULL DEFAULT '',
            recipient_unit varchar(80) NOT NULL DEFAULT '',
            sender varchar(190) NOT NULL DEFAULT '',
            package_code varchar(40) NULL DEFAULT NULL,
            tracking_code varchar(120) NOT NULL DEFAULT '',
            description varchar(500) NOT NULL DEFAULT '',
            notes text NULL,
            status varchar(24) NOT NULL DEFAULT 'awaiting_pickup',
            whatsapp_optin_confirmed tinyint(1) unsigned NOT NULL DEFAULT 0,
            portal_status varchar(24) NOT NULL DEFAULT 'pending',
            email_status varchar(24) NOT NULL DEFAULT 'pending',
            whatsapp_status varchar(24) NOT NULL DEFAULT 'pending',
            teams_status varchar(24) NOT NULL DEFAULT 'pending',
            received_by bigint(20) unsigned NOT NULL DEFAULT 0,
            received_at datetime NOT NULL,
            picked_up_by_person_id bigint(20) unsigned NOT NULL DEFAULT 0,
            picked_up_by_name varchar(190) NOT NULL DEFAULT '',
            picked_up_by_user bigint(20) unsigned NOT NULL DEFAULT 0,
            picked_up_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY package_code_unique (package_code),
            KEY status_received (status,received_at),
            KEY recipient_person_id (recipient_person_id),
            KEY recipient_user_id (recipient_user_id),
            KEY tracking_code (tracking_code),
            KEY received_at (received_at)
        ) {$collate};" );
        $legacy_packages = $wpdb->get_results( "SELECT id, received_at FROM {$this->table} WHERE package_code IS NULL OR package_code = '' ORDER BY id ASC" );
        foreach ( $legacy_packages as $legacy_package ) {
            $wpdb->update(
                $this->table,
                array( 'package_code' => $this->build_package_code( $legacy_package->id, $legacy_package->received_at ) ),
                array( 'id' => absint( $legacy_package->id ) ),
                array( '%s' ),
                array( '%d' )
            );
        }
        dbDelta( "CREATE TABLE {$this->events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            package_id bigint(20) unsigned NOT NULL,
            event_type varchar(32) NOT NULL,
            channel varchar(24) NOT NULL DEFAULT '',
            result varchar(24) NOT NULL DEFAULT '',
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            detail varchar(500) NOT NULL DEFAULT '',
            external_ref varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY package_id_created (package_id,created_at),
            KEY channel_result (channel,result)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->carriers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            logo_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            active tinyint(1) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name_unique (name),
            KEY active_name (active,name)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->packagings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            active tinyint(1) unsigned NOT NULL DEFAULT 1,
            sort_order bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name_unique (name),
            KEY active_name (active,name),
            KEY active_order (active,sort_order)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->groups} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            active tinyint(1) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY name_unique (name),
            KEY active_name (active,name)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->group_members} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            person_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_person (group_id,person_id),
            KEY person_id (person_id)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->package_recipients} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            package_id bigint(20) unsigned NOT NULL,
            person_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            person_name varchar(190) NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            mobile varchar(40) NOT NULL DEFAULT '',
            portal_status varchar(24) NOT NULL DEFAULT 'pending',
            email_status varchar(24) NOT NULL DEFAULT 'pending',
            whatsapp_status varchar(24) NOT NULL DEFAULT 'pending',
            teams_status varchar(24) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY package_person (package_id,person_id),
            KEY person_id (person_id),
            KEY user_id (user_id)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->sequences} (
            sequence_year smallint(4) unsigned NOT NULL,
            last_number bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (sequence_year)
        ) {$collate};" );
        dbDelta( "CREATE TABLE {$this->notices} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            person_id bigint(20) unsigned NOT NULL,
            author_name varchar(190) NOT NULL,
            sender varchar(190) NOT NULL DEFAULT '',
            expected_at date DEFAULT NULL,
            tracking_code varchar(120) NOT NULL DEFAULT '',
            description varchar(500) NOT NULL DEFAULT '',
            authorized_person varchar(190) NOT NULL DEFAULT '',
            passphrase_cipher text NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'pending',
            package_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            linked_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY user_status_created (user_id,status,created_at),
            KEY person_status (person_id,status),
            KEY package_id (package_id)
        ) {$collate};" );
        $wpdb->query( "UPDATE {$this->table} SET teams_status = 'not_configured' WHERE teams_status = 'pending' AND created_at < NOW()" );
        $wpdb->query( "UPDATE {$this->package_recipients} SET teams_status = 'not_configured' WHERE teams_status = 'pending' AND created_at < NOW()" );
        $wpdb->query( "INSERT IGNORE INTO {$this->carriers} (name, active, created_at, updated_at) SELECT DISTINCT sender, 1, NOW(), NOW() FROM {$this->table} WHERE sender <> ''" );
        $wpdb->query( "INSERT IGNORE INTO {$this->packagings} (name, active, created_at, updated_at) VALUES ('Caixa Pequena',1,NOW(),NOW()), ('Caixa Média',1,NOW(),NOW()), ('Caixa Grande',1,NOW(),NOW()), ('Sacola Pequena',1,NOW(),NOW()), ('Sacola Média',1,NOW(),NOW()), ('Sacola Grande',1,NOW(),NOW()), ('Outros',1,NOW(),NOW())" );
        $wpdb->query( "UPDATE {$this->packagings} SET sort_order = id WHERE sort_order = 0" );
        update_option( 'pwl_encomendas_db_version', self::DB_VERSION, false );
    }

    public function register_module() {
        if ( ! function_exists( 'pwl_register_module' ) ) {
            return;
        }
        pwl_register_module(
            self::MODULE_ID,
            array(
                'name'         => 'Encomendas',
                'version'      => self::VERSION,
                'area'         => 'Recursos Corporativos',
                'status'       => 'Em produção',
                'description'  => 'Registro de encomendas, avisos e controle de retirada com interfaces Portal UI e Admin UI integradas aos tokens oficiais.',
                'dependencies' => array( 'PWL Core', 'PWL Notificações', 'PWL Design System', 'WhatsApp Business Platform Cloud API (opcional até configuração)' ),
                'capabilities' => array(
                    self::CAP_VIEW   => array( 'label' => 'Visualizar Encomendas', 'default' => false ),
                    self::CAP_MANAGE => array( 'label' => 'Registrar e gerenciar Encomendas na Portal UI', 'default' => false ),
                    self::CAP_ADMIN  => array( 'label' => 'Acessar a Admin UI de Encomendas', 'default' => false ),
                    self::CAP_HYBRID => array( 'label' => 'Alternar entre registrar encomenda e avisar a Portaria na Portal UI', 'default' => false ),
                ),
            )
        );
    }

    private function can( $capability ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        return function_exists( 'pwl_current_user_can' ) && pwl_current_user_can( $capability );
    }

    private function current_person_for_user( $user_id ) {
        if ( ! function_exists( 'pwl_get_people' ) ) {
            return array();
        }
        $match = array();
        foreach ( (array) pwl_get_people( array( 'limit' => 1000 ) ) as $person ) {
            if ( absint( $person['wp_user_id'] ?? 0 ) !== absint( $user_id ) ) {
                continue;
            }
            if ( 'active' === ( $person['person_status'] ?? '' ) ) {
                return $person;
            }
            $match = $person;
        }
        return $match;
    }

    private function encrypt_notice_passphrase( $passphrase ) {
        if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_random_pseudo_bytes' ) ) {
            return '';
        }
        $iv = openssl_random_pseudo_bytes( 16, $strong );
        if ( ! $strong || false === $iv ) {
            return '';
        }
        $keys = $this->notice_crypto_keys();
        $ciphertext = openssl_encrypt( $passphrase, 'aes-256-cbc', $keys['encryption'], OPENSSL_RAW_DATA, $iv );
        if ( false === $ciphertext ) {
            return '';
        }
        $mac = hash_hmac( 'sha256', $iv . $ciphertext, $keys['authentication'], true );
        return 'v1:' . base64_encode( $iv ) . ':' . base64_encode( $mac ) . ':' . base64_encode( $ciphertext );
    }

    private function notice_crypto_keys() {
        $master_key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
        return array(
            'encryption' => hash_hmac( 'sha256', 'pwl-encomendas-passphrase-encryption', $master_key, true ),
            'authentication' => hash_hmac( 'sha256', 'pwl-encomendas-passphrase-authentication', $master_key, true ),
        );
    }

    private function decrypt_notice_passphrase( $encoded ) {
        if ( ! function_exists( 'openssl_decrypt' ) || ! is_string( $encoded ) || ! preg_match( '/^v1:([^:]+):([^:]+):([^:]+)$/', $encoded, $parts ) ) {
            return '';
        }
        $iv = base64_decode( $parts[1], true );
        $mac = base64_decode( $parts[2], true );
        $ciphertext = base64_decode( $parts[3], true );
        if ( false === $iv || false === $mac || false === $ciphertext || 16 !== strlen( $iv ) ) {
            return '';
        }
        $keys = $this->notice_crypto_keys();
        $expected_mac = hash_hmac( 'sha256', $iv . $ciphertext, $keys['authentication'], true );
        if ( ! hash_equals( $expected_mac, $mac ) ) {
            return '';
        }
        $plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $keys['encryption'], OPENSSL_RAW_DATA, $iv );
        return false === $plaintext ? '' : $plaintext;
    }

    /**
     * Resolve a module capability without granting the WordPress administrator bypass.
     * Notifications must follow the explicit PWL Core permission configuration.
     */
    private function user_has_module_capability( $user_id, $capability ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! get_userdata( $user_id ) ) {
            return false;
        }
        if ( function_exists( 'pwl_user_has_platform_access' ) && ! pwl_user_has_platform_access( $user_id ) ) {
            return false;
        }

        $overrides = get_option( 'pwl_user_permission_overrides', array() );
        if ( is_array( $overrides ) ) {
            $legacy_key = sanitize_key( $capability );
            $override = $overrides[ $user_id ][ $capability ] ?? $overrides[ $user_id ][ $legacy_key ] ?? 'inherit';
            if ( 'allow' === $override ) {
                return true;
            }
            if ( 'deny' === $override ) {
                return false;
            }
        }

        $group_allow = false;
        $group_ids = function_exists( 'pwl_get_user_groups' ) ? pwl_get_user_groups( $user_id ) : array();
        foreach ( (array) $group_ids as $group_id ) {
            $decision = function_exists( 'pwl_group_can' ) ? pwl_group_can( $group_id, $capability ) : null;
            if ( false === $decision ) {
                return false;
            }
            if ( true === $decision ) {
                $group_allow = true;
            }
        }
        if ( $group_allow ) {
            return true;
        }

        return function_exists( 'pwl_get_capability_default' ) && pwl_get_capability_default( $capability );
    }

    private function management_user_ids() {
        $user_ids = array();
        foreach ( get_users( array( 'fields' => 'ID', 'number' => -1 ) ) as $user_id ) {
            $user_id = absint( $user_id );
            if ( $this->user_has_module_capability( $user_id, self::CAP_MANAGE ) ) {
                $user_ids[] = $user_id;
            }
        }
        return array_values( array_unique( $user_ids ) );
    }

    private function notify_management_of_advance_notice( $author_name, $sender ) {
        if ( ! function_exists( 'pwl_criar_notificacao' ) ) {
            return;
        }
        $recipient_ids = apply_filters( 'pwl_encomendas_management_notification_user_ids', $this->management_user_ids() );
        $recipient_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $recipient_ids ) ) ) );
        if ( ! $recipient_ids ) {
            return;
        }
        $title = 'Novo aviso antecipado de encomenda';
        $message = sanitize_text_field( $author_name ) . ' informou uma encomenda a caminho pela ' . sanitize_text_field( $sender ) . '. Consulte o módulo Encomendas no Portal.';
        foreach ( $recipient_ids as $recipient_id ) {
            pwl_criar_notificacao( $title, $message, $this->portal_url(), $recipient_id, 'geral', array( 'priority' => 'importante', 'send_email' => false ) );
        }
    }

    private function can_delete_packages() {
        return is_admin() && is_user_logged_in() && current_user_can( 'manage_options' ) && is_super_admin();
    }

    public function enqueue_portal_assets() {
        if ( function_exists( 'pwl_ds_enqueue' ) ) {
            pwl_ds_enqueue( array( 'all' ), 'portal' );
        }
        $layout_handle = 'pwl-encomendas-layout';
        wp_enqueue_style( $layout_handle, plugins_url( 'assets/css/encomendas-layout.css', __FILE__ ), array( 'pwl-ds-components' ), self::VERSION );
        wp_enqueue_script( 'pwl-encomendas-table', plugins_url( 'assets/js/encomendas.js', __FILE__ ), array(), self::VERSION, true );
        if ( did_action( 'wp_head' ) && ! wp_style_is( $layout_handle, 'done' ) ) {
            wp_print_styles( array( $layout_handle ) );
        }
    }

    public function admin_menu() {
        $allowed = $this->can( self::CAP_ADMIN );
        if ( ! $allowed ) {
            return;
        }
        add_submenu_page(
            'pwl-platform',
            'Encomendas',
            'Encomendas',
            'read',
            'pwl-encomendas',
            array( $this, 'admin_page' )
        );

    }

    public function admin_assets( $hook ) {
        $plugin_pages = array( 'pwl-encomendas', 'pwl-encomendas-relatorios', 'pwl-encomendas-transportadoras', 'pwl-encomendas-groups', 'pwl-encomendas-whatsapp', 'pwl-encomendas-teams' );
        $requested_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $known_hook = in_array( $hook, array( 'plataforma_page_pwl-encomendas', 'plataforma_page_pwl-encomendas-relatorios', 'plataforma_page_pwl-encomendas-transportadoras', 'plataforma_page_pwl-encomendas-groups', 'plataforma_page_pwl-encomendas-whatsapp', 'plataforma_page_pwl-encomendas-teams', 'pwl-platform_page_pwl-encomendas', 'pwl-platform_page_pwl-encomendas-relatorios', 'pwl-platform_page_pwl-encomendas-transportadoras', 'pwl-platform_page_pwl-encomendas-groups', 'pwl-platform_page_pwl-encomendas-whatsapp', 'pwl-platform_page_pwl-encomendas-teams' ), true );
        $module_shell = in_array( $requested_page, array( 'pwl-platform', 'plataforma' ), true );
        if ( ! $known_hook && ! in_array( $requested_page, $plugin_pages, true ) && ! $module_shell ) {
            return;
        }
        if ( function_exists( 'pwl_core_admin_ui_enqueue' ) ) {
            pwl_core_admin_ui_enqueue();
        }
        wp_enqueue_style( 'pwl-encomendas-admin', plugins_url( 'assets/css/encomendas-admin.css', __FILE__ ), array(), self::VERSION );
        wp_enqueue_script( 'pwl-encomendas-table', plugins_url( 'assets/js/encomendas.js', __FILE__ ), array(), self::VERSION, true );
        wp_enqueue_media();
        wp_enqueue_script( 'pwl-encomendas-carrier-media', plugins_url( 'assets/js/carrier-media.js', __FILE__ ), array( 'media-views', 'media-editor' ), self::VERSION, true );
        wp_enqueue_script( 'pwl-encomendas-admin-tabs', plugins_url( 'assets/js/admin-tabs.js', __FILE__ ), array(), self::VERSION, true );
        wp_enqueue_script( 'pwl-encomendas-meta-signup', plugins_url( 'assets/js/meta-signup.js', __FILE__ ), array(), self::VERSION, true );
        wp_localize_script( 'pwl-encomendas-meta-signup', 'PWLWhatsAppSignup', array(
            'appId' => get_option( 'pwl_encomendas_meta_app_id', '' ),
            'configId' => get_option( 'pwl_encomendas_meta_config_id', '' ),
            'nonce' => wp_create_nonce( 'pwl_encomendas_meta_signup' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'redirectUri' => home_url( '/' ),
        ) );
    }

    public function admin_page() {
        if ( ! $this->can( self::CAP_ADMIN ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar o módulo de Encomendas.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $view = isset( $_GET['encomendas_view'] ) ? sanitize_key( wp_unslash( $_GET['encomendas_view'] ) ) : 'packages';
        switch ( $view ) {
            case 'register_notice': $this->register_notice_page(); return;
            case 'reports': $this->reports_page(); return;
            case 'packagings': $this->packagings_page(); return;
            case 'groups': $this->groups_page(); return;
            case 'carriers': $this->carriers_page(); return;
            case 'whatsapp': $this->whatsapp_page(); return;
            case 'teams': $this->teams_page(); return;
            case 'about': $this->about_page(); return;
            default: $this->render_workspace( true ); return;
        }
    }

    public function reports_page() {
        if ( ! $this->can( self::CAP_ADMIN ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar os relatórios de Encomendas.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $this->render_reports();
    }

    public function register_notice_page() {
        if ( ! $this->can( self::CAP_ADMIN ) ) {
            wp_die( 'Voc&ecirc; n&atilde;o possui permiss&atilde;o para registrar avisos de encomendas.', '', array( 'response' => 403 ) );
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $person = $this->current_person_for_user( $user_id );
        $carriers = $wpdb->get_results( "SELECT name FROM {$this->carriers} WHERE active = 1 ORDER BY name ASC" );
        $feedback = isset( $_GET['pwl_encomendas_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_encomendas_result'] ) ) : '';

        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS &middot; PORTARIA</span><h1 class="pwl-ui-title">Registrar aviso</h1><p class="pwl-ui-description">Informe uma encomenda a caminho e cadastre a palavra-passe para a retirada.</p></div></header>';
        $this->render_admin_tabs( 'register_notice' );

        if ( 'notice_created' === $feedback ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Aviso antecipado enviado para a Portaria.</strong></div></div></div>';
        } elseif ( 'error' === $feedback ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>N&atilde;o foi poss&iacute;vel registrar o aviso. Confira os dados e tente novamente.</strong></div></div></div>';
        }

        $this->section_open( true, 'Avisar encomenda', 'Informe os dados da encomenda e a palavra-passe que a Portaria dever&aacute; solicitar na retirada.' );
        if ( ! function_exists( 'pwl_get_people' ) || ! function_exists( 'pwl_criar_notificacao' ) ) {
            echo '<div class="pwl-ui-notice"><strong>Depend&ecirc;ncia indispon&iacute;vel.</strong> Ative PWL Core e PWL Notifica&ccedil;&otilde;es para usar este recurso.</div>';
        } elseif ( ! empty( $person ) && 'active' === ( $person['person_status'] ?? '' ) ) {
            $this->render_advance_notice_form( $person, $carriers, true );
        } else {
            echo '<div class="pwl-ui-notice"><strong>Cadastro do colaborador n&atilde;o vinculado.</strong> Pe&ccedil;a &agrave; equipe do Portal para associar seu usu&aacute;rio &agrave; pessoa cadastrada no PWL Core.</div>';
        }
        $this->section_close( true );
        echo '</main></div>';
    }

    public function carriers_page() {
        if ( ! $this->can( self::CAP_ADMIN ) || ! $this->can( self::CAP_MANAGE ) ) {
            wp_die( esc_html__( 'Você não possui permissão para gerenciar transportadoras.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        // Enfileira também no callback da tela para cobrir shells administrativos
        // que chamam a página fora do hook padrão de submenu do WordPress.
        wp_enqueue_media();
        wp_enqueue_script( 'pwl-encomendas-carrier-media', plugins_url( 'assets/js/carrier-media.js', __FILE__ ), array( 'media-views', 'media-editor' ), self::VERSION, true );
        $this->render_carriers();
    }

    public function groups_page() {
        if ( ! $this->can( self::CAP_ADMIN ) || ! $this->can( self::CAP_MANAGE ) ) {
            wp_die( esc_html__( 'Você não possui permissão para gerenciar grupos de recebimento.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $this->render_groups();
    }

    public function whatsapp_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Apenas administradores podem configurar o WhatsApp.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $saved = isset( $_GET['pwl_wa_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['pwl_wa_saved'] ) );
        $app_id = get_option( 'pwl_encomendas_meta_app_id', '' );
        $config_id = get_option( 'pwl_encomendas_meta_config_id', '' );
        $phone_id = defined( 'PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID' ) ? PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID : '';
        $ready = $app_id && $config_id;
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">WhatsApp da Portaria</h1><p class="pwl-ui-description">Preparação da conexão Meta para avisos de encomendas.</p></div></header>';
        $this->render_admin_tabs( 'whatsapp' );
        echo '<section class="pwl-ui-card"><header class="pwl-ui-card-header"><h2>Configuração do Embedded Signup</h2><p>O número de produção já conectado (final 0042) não será alterado por esta configuração.</p></header><div class="pwl-ui-card-body">';
        if ( $saved ) {
            echo '<div class="pwl-ui-notice"><strong>Identificadores salvos.</strong> Revise agora a configuração do Embedded Signup no painel Meta antes de testar.</div>';
        }
        echo '<p><strong>Domínio do portal:</strong> ' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</p>';
        echo '<p><strong>Status:</strong> ' . ( $ready ? 'App ID e Configuration ID informados.' : 'Aguardando App ID e Configuration ID do Meta for Developers.' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_meta_save">';
        wp_nonce_field( 'pwl_encomendas_meta_save' );
        echo '<div class="pwl-ui-layout--form"><label class="pwl-ui-field"><span class="pwl-ui-label">Meta App ID</span><input class="pwl-ui-control" name="meta_app_id" inputmode="numeric" value="' . esc_attr( $app_id ) . '" required></label><label class="pwl-ui-field"><span class="pwl-ui-label">Embedded Signup Configuration ID</span><input class="pwl-ui-control" name="meta_config_id" inputmode="numeric" value="' . esc_attr( $config_id ) . '" required></label></div><button class="pwl-ui-button pwl-ui-button--primary" type="submit">Salvar identificadores</button></form>';
        echo '<div class="pwl-ui-notice pwl-encomendas-meta-guidance"><strong>Etapa de validação:</strong> o app Meta precisa ter uma configuração Embedded Signup compatível com onboarding do WhatsApp Business App. O botão abaixo apenas abre o fluxo e registra o retorno técnico. Ele não troca tokens, registra o número nem conclui a conexão de produção. Não cole token de acesso nesta tela.</div>';
        echo '<p><button class="pwl-ui-button pwl-ui-button--primary" type="button" id="pwl-encomendas-start-meta-signup" ' . ( $ready ? '' : 'disabled' ) . '>Testar fluxo de coexistência</button></p><pre class="pwl-encomendas-meta-output" id="pwl-encomendas-meta-output" aria-live="polite">' . ( $ready ? 'Pronto para abrir o Embedded Signup.' : 'Informe e salve o Meta App ID e o Configuration ID para habilitar.' ) . '</pre>';
        echo '<p>O ID do número atualmente usado pelo envio está ' . ( $phone_id ? 'configurado no wp-config.php.' : 'pendente no wp-config.php.' ) . ' O Embedded Signup ainda não foi executado.</p>';
        echo '</div></section></main></div>';
    }

    public function teams_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Apenas administradores podem configurar o Microsoft Teams.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $configured = (bool) get_option( 'pwl_encomendas_teams_webhook_url', '' );
        $result = isset( $_GET['pwl_teams_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_teams_result'] ) ) : '';
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Microsoft Teams</h1><p class="pwl-ui-description">Envie um aviso privado em conversa individual com cada colaborador.</p></div></header>';
        $this->render_admin_tabs( 'teams' );
        if ( 'saved' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Configuração do Teams salva.</strong></div></div></div>';
        } elseif ( 'error' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>URL inválida.</strong> Use o endereço HTTPS do webhook gerado pelo fluxo da Microsoft.</div></div></div>';
        }
        $this->section_open( true, 'Conexão com Power Automate', 'O fluxo recebe os dados da encomenda e envia uma mensagem privada pelo Flow bot.' );
        echo '<p><strong>Status:</strong> ' . ( $configured ? 'Webhook configurado.' : 'Aguardando URL do webhook.' ) . '</p>';
        echo '<p>Configure no fluxo a ação Teams <strong>Postar uma mensagem em um chat ou canal</strong>, como <strong>Flow bot</strong>, em <strong>Chat com Flow bot</strong>, usando <code>recipient_email</code> como destinatário. Para destinatários em grupo, o módulo chama o fluxo separadamente para cada membro.</p>';
        echo '<p>No gatilho <strong>Quando uma solicitação de webhook do Teams é recebida</strong>, use este esquema do corpo:</p><pre>{"type":"object","properties":{"recipient_email":{"type":"string"},"recipient_name":{"type":"string"},"title":{"type":"string"},"package_code":{"type":"string"},"message":{"type":"string"}},"required":["recipient_email","recipient_name","title","package_code","message"]}</pre>';
        echo '<p>O fluxo recebe destinatário, nome, título, código interno e texto do aviso. Cada colaborador recebe uma conversa individual; colegas não são incluídos. A URL é um segredo de integração e não será exibida após ser salva. Restrinja seu acesso e não a compartilhe.</p>';
        echo '<p>O histórico de execuções do Power Automate pode reter o conteúdo recebido e fica acessível aos proprietários do fluxo e administradores autorizados do Microsoft 365. Restrinja os responsáveis pelo fluxo e aplique a retenção definida pela Cobremack.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_teams_save">';
        wp_nonce_field( 'pwl_encomendas_teams_save' );
        echo '<label class="pwl-ui-field"><span class="pwl-ui-label">URL do webhook do fluxo</span><input class="pwl-ui-control" type="url" name="teams_webhook_url" autocomplete="off" placeholder="' . ( $configured ? 'Configurada. Informe outra somente para substituir.' : 'Cole a URL HTTPS do webhook do Power Automate.' ) . '"></label>';
        if ( $configured ) {
            echo '<label class="pwl-ui-field"><span><input type="checkbox" name="clear_teams_webhook" value="1"> Remover a configuração atual</span></label>';
        }
        echo '<button class="pwl-ui-button pwl-ui-button--primary" type="submit">Salvar configuração</button></form>';
        $this->section_close( true );
        echo '</main></div>';
    }

    public function handle_teams_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Apenas administradores podem salvar a configuração do Teams.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pwl_encomendas_teams_save' );
        if ( ! empty( $_POST['clear_teams_webhook'] ) ) {
            delete_option( 'pwl_encomendas_teams_webhook_url' );
        } else {
            $submitted = isset( $_POST['teams_webhook_url'] ) ? trim( (string) wp_unslash( $_POST['teams_webhook_url'] ) ) : '';
            if ( '' !== $submitted ) {
                $url = esc_url_raw( $submitted, array( 'https' ) );
                if ( ! $url || ! $this->valid_teams_webhook_url( $url ) ) {
                    wp_safe_redirect( add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'teams', 'pwl_teams_result' => 'error' ), admin_url( 'admin.php' ) ) );
                    exit;
                }
                update_option( 'pwl_encomendas_teams_webhook_url', $url, false );
            }
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'teams', 'pwl_teams_result' => 'saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function valid_teams_webhook_url( $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return false;
        }
        $host = strtolower( $parts['host'] );
        return (bool) preg_match( '/(?:^|\.)logic\.azure\.com$/', $host ) || (bool) preg_match( '/(?:^|\.)api\.powerplatform\.com$/', $host );
    }

    public function handle_meta_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Apenas administradores podem salvar esta configuração.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pwl_encomendas_meta_save' );
        $app_id = isset( $_POST['meta_app_id'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['meta_app_id'] ) ) : '';
        $config_id = isset( $_POST['meta_config_id'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['meta_config_id'] ) ) : '';
        if ( ! $app_id || ! $config_id ) {
            wp_die( esc_html__( 'Informe os dois identificadores numéricos da Meta.', 'pwl-encomendas' ), '', array( 'response' => 400 ) );
        }
        update_option( 'pwl_encomendas_meta_app_id', $app_id, false );
        update_option( 'pwl_encomendas_meta_config_id', $config_id, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'whatsapp', 'pwl_wa_saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_meta_signup_event() {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'pwl_encomendas_meta_signup', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }
        $raw = isset( $_POST['event'] ) ? wp_unslash( $_POST['event'] ) : '';
        if ( ! is_string( $raw ) || strlen( $raw ) > 2000 ) {
            wp_send_json_error( array( 'message' => 'Retorno inválido.' ), 400 );
        }
        $event = json_decode( $raw, true );
        if ( ! is_array( $event ) ) {
            wp_send_json_error( array( 'message' => 'Não foi possível interpretar o retorno da Meta.' ), 400 );
        }
        update_option( 'pwl_encomendas_meta_signup_last_event', array(
            'event' => sanitize_key( $event['event'] ?? '' ),
            'data_keys' => array_slice( array_map( 'sanitize_key', (array) ( $event['data_keys'] ?? array() ) ), 0, 20 ),
            'code_received' => ! empty( $event['code_received'] ),
            'status' => sanitize_key( $event['status'] ?? '' ),
            'received_at' => current_time( 'mysql' ),
        ), false );
        $event_name = sanitize_key( $event['event'] ?? '' );
        $status = sanitize_key( $event['status'] ?? '' );
        if ( 'wa_embedded_signup' === $event_name ) {
            $signup_event = sanitize_key( $event['signup_event'] ?? '' );
            if ( in_array( $signup_event, array( 'finish', 'finish_whatsapp_business_app_onboarding' ), true ) ) {
                $message = 'A Meta concluiu o Embedded Signup e retornou os identificadores do onboarding. A conexão ainda não está ativa: o servidor precisa trocar o código por token, registrar o número e configurar o webhook.';
            } elseif ( 'cancel' === $signup_event ) {
                $message = 'O fluxo foi cancelado antes da conclusão. A Meta não conectou o número.';
            } else {
                $message = 'A Meta retornou o evento ' . strtoupper( $signup_event ?: 'desconhecido' ) . '. A conexão não foi concluída.';
            }
        } elseif ( 'fb_login_callback' === $event_name ) {
            if ( ! empty( $event['code_received'] ) ) {
                $message = 'A Meta autorizou o login e retornou um código, mas não enviou o evento WA_EMBEDDED_SIGNUP. O cadastro incorporado não foi confirmado. Confira se o Configuration ID salvo pertence à variação “WhatsApp Embedded Signup” e se o onboarding do WhatsApp Business App está habilitado. O código não foi trocado por token e o número não está conectado.';
            } else {
                $message = 'A janela fechou sem autorização. Status retornado pela Meta: ' . ( $status ?: 'não informado' ) . '. Nenhum número foi conectado.';
            }
        } else {
            $message = 'A Meta retornou o evento ' . strtoupper( $event_name ?: 'desconhecido' ) . '. A conexão de produção não foi concluída.';
        }
        wp_send_json_success( array( 'message' => $message ) );
    }

    private function render_admin_tabs( $active ) {
        $base = admin_url( 'admin.php' );
        $url = function ( $view ) use ( $base ) {
            return add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => $view ), $base );
        };
        $primary = 'packages';
        if ( 'register_notice' === $active ) { $primary = 'register_notice'; }
        elseif ( 'reports' === $active ) { $primary = 'reports'; }
        elseif ( in_array( $active, array( 'whatsapp', 'teams' ), true ) ) { $primary = 'integrations'; }
        elseif ( in_array( $active, array( 'packagings', 'groups', 'carriers' ), true ) ) { $primary = 'catalogs'; }
        elseif ( 'about' === $active ) { $primary = 'about'; }

        $tabs = array( 'packages' => array( 'Encomendas', 'packages' ) );
        if ( $this->can( self::CAP_ADMIN ) ) {
            $tabs['register_notice'] = array( 'Registrar aviso', 'register_notice' );
        }
        if ( $this->can( self::CAP_ADMIN ) ) {
            $tabs['reports'] = array( 'Relatórios', 'reports' );
        }
        if ( current_user_can( 'manage_options' ) ) {
            $tabs['integrations'] = array( 'Integrações', 'whatsapp' );
        }
        if ( $this->can( self::CAP_ADMIN ) && $this->can( self::CAP_MANAGE ) ) {
            $tabs['catalogs'] = array( 'Cadastros', 'packagings' );
        }
        $tabs['about'] = array( 'Sobre', 'about' );

        echo '<nav class="pwl-ui-tabs pwl-encomendas-main-tabs" aria-label="Seções de Encomendas">';
        foreach ( $tabs as $key => $tab ) {
            echo '<a class="pwl-ui-tab' . ( $primary === $key ? ' is-active' : '' ) . '" data-pwl-encomendas-tab href="' . esc_url( $url( $tab[1] ) ) . '"' . ( $primary === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab[0] ) . '</a>';
        }
        echo '</nav>';

        if ( in_array( $active, array( 'whatsapp', 'teams' ), true ) && current_user_can( 'manage_options' ) ) {
            $this->render_admin_subtabs( array( 'WhatsApp' => 'whatsapp', 'Teams' => 'teams' ), $active, $url, 'Integrações' );
        } elseif ( in_array( $active, array( 'packagings', 'groups', 'carriers' ), true ) && $this->can( self::CAP_ADMIN ) && $this->can( self::CAP_MANAGE ) ) {
            $this->render_admin_subtabs( array( 'Embalagem' => 'packagings', 'Grupos de Recebimento' => 'groups', 'Transportadoras' => 'carriers' ), $active, $url, 'Cadastros' );
        }
    }

    private function render_admin_subtabs( $tabs, $active, $url, $label ) {
        echo '<nav class="pwl-ui-tabs pwl-encomendas-subtabs" aria-label="' . esc_attr( $label ) . '">';
        foreach ( $tabs as $title => $view ) {
            echo '<a class="pwl-ui-tab' . ( $active === $view ? ' is-active' : '' ) . '" data-pwl-encomendas-tab href="' . esc_url( $url( $view ) ) . '"' . ( $active === $view ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
        }
        echo '</nav>';
    }

    private function render_reports() {
        global $wpdb;
        $search = isset( $_GET['pwl_report_q'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_report_q'] ) ) : '';
        $from = isset( $_GET['pwl_report_from'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_report_from'] ) ) : '';
        $to = isset( $_GET['pwl_report_to'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_report_to'] ) ) : '';
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || ! strtotime( $from ) ) { $from = ''; }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) || ! strtotime( $to ) ) { $to = ''; }
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 100;
        $offset = ( $paged - 1 ) * $per_page;

        $where = array( '1=1' );
        $params = array();
        if ( $from ) {
            $where[] = 'e.created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ( $to ) {
            $where[] = 'e.created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }
        if ( '' !== $search ) {
            $fields = array( 'CAST(e.package_id AS CHAR)', 'e.event_type', 'e.channel', 'e.result', 'e.detail', 'p.package_code', 'p.recipient_name', 'p.sender', 'p.tracking_code', 'p.description' );
            foreach ( preg_split( '/\s+/', trim( $search ) ) as $term ) {
                if ( '' === $term ) { continue; }
                $like = '%' . $wpdb->esc_like( $term ) . '%';
                $parts = array();
                foreach ( $fields as $field ) {
                    $parts[] = $field . ' LIKE %s';
                    $params[] = $like;
                }
                $where[] = '(' . implode( ' OR ', $parts ) . ')';
            }
        }
        $from_sql = " FROM {$this->events} e LEFT JOIN {$this->table} p ON p.id = e.package_id WHERE " . implode( ' AND ', $where );
        $total = (int) $wpdb->get_var( $params ? $wpdb->prepare( 'SELECT COUNT(*)' . $from_sql, $params ) : 'SELECT COUNT(*)' . $from_sql );
        $sql = 'SELECT e.*, p.package_code, p.recipient_name, p.sender, p.tracking_code' . $from_sql . ' ORDER BY e.created_at DESC, e.id DESC LIMIT %d OFFSET %d';
        $query_params = array_merge( $params, array( $per_page, $offset ) );
        $events = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Relatórios de Encomendas</h1><p class="pwl-ui-description">Consulte os registros de recebimento, avisos, reenvios e retiradas.</p></div></header>';
        $this->render_admin_tabs( 'reports' );
        $this->section_open( true, 'Eventos registrados', 'Os eventos são armazenados automaticamente para auditoria.' );
        echo '<p>Exibindo ' . esc_html( number_format_i18n( count( (array) $events ) ) ) . ' de ' . esc_html( number_format_i18n( $total ) ) . ' eventos.</p>';
        echo '<form method="get"><input type="hidden" name="page" value="pwl-encomendas"><input type="hidden" name="encomendas_view" value="reports"><div class="pwl-ui-layout--form">';
        echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Buscar</span><input class="pwl-ui-control" type="search" name="pwl_report_q" value="' . esc_attr( $search ) . '" placeholder="Colaborador, transportadora, rastreio ou evento"></label>';
        echo '<label class="pwl-ui-field"><span class="pwl-ui-label">De</span><input class="pwl-ui-control" type="date" name="pwl_report_from" value="' . esc_attr( $from ) . '"></label>';
        echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Até</span><input class="pwl-ui-control" type="date" name="pwl_report_to" value="' . esc_attr( $to ) . '"></label></div><button class="pwl-ui-button pwl-ui-button--primary" type="submit">Filtrar relatório</button></form>';
        echo '<div class="pwl-ui-table-wrap"><table class="pwl-ui-table"><thead><tr><th>Data e hora</th><th>Encomenda</th><th>Colaborador</th><th>Evento</th><th>Canal</th><th>Resultado</th><th>Responsável</th><th>Detalhe</th><th>Referência externa</th></tr></thead><tbody>';
        if ( $events ) {
            foreach ( $events as $event ) {
                $actor = $event->actor_user_id ? get_userdata( absint( $event->actor_user_id ) ) : false;
                echo '<tr><td>' . esc_html( mysql2date( 'd/m/Y H:i', $event->created_at ) ) . '</td><td><strong>' . esc_html( $event->package_code ?: $this->build_package_code( $event->package_id, $event->created_at ) ) . '</strong><br>#' . esc_html( absint( $event->package_id ) ) . ' · ' . esc_html( $event->sender ?: $event->tracking_code ) . '</td><td>' . esc_html( $event->recipient_name ?: '—' ) . '</td><td>' . esc_html( $this->event_label( $event->event_type ) ) . '</td><td>' . esc_html( $this->event_channel_label( $event->channel ) ) . '</td><td>' . esc_html( $this->event_result_label( $event->result ) ) . '</td><td>' . esc_html( $actor ? $actor->display_name : 'Sistema' ) . '</td><td>' . esc_html( $event->detail ) . '</td><td>' . esc_html( $event->external_ref ?: '—' ) . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="9">Nenhum evento encontrado para os filtros informados.</td></tr>';
        }
        echo '</tbody></table></div>';
        $total_pages = (int) ceil( $total / $per_page );
        if ( $total_pages > 1 ) {
            $base_url = remove_query_arg( 'paged' );
            echo '<div class="pwl-ui-report-pagination">';
            if ( $paged > 1 ) {
                echo '<a class="pwl-ui-button" href="' . esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ) . '">Anterior</a>';
            }
            echo '<span>Página ' . esc_html( number_format_i18n( $paged ) ) . ' de ' . esc_html( number_format_i18n( $total_pages ) ) . '</span>';
            if ( $paged < $total_pages ) {
                echo '<a class="pwl-ui-button" href="' . esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ) . '">Próxima</a>';
            }
            echo '</div>';
        }
        $this->section_close( true );
        echo '</main></div>';
    }

    public function packagings_page() {
        if ( ! $this->can( self::CAP_ADMIN ) || ! $this->can( self::CAP_MANAGE ) ) {
            wp_die( esc_html__( 'Você não possui permissão para gerenciar embalagens.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        $this->render_packagings();
    }

    private function render_packagings() {
        global $wpdb;
        $items = $wpdb->get_results( "SELECT * FROM {$this->packagings} ORDER BY active DESC, id ASC" );
        $edit_id = isset( $_GET['edit_packaging'] ) ? absint( $_GET['edit_packaging'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->packagings} WHERE id = %d", $edit_id ) ) : null;
        $result = isset( $_GET['pwl_packaging_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_packaging_result'] ) ) : '';
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Embalagem</h1><p class="pwl-ui-description">Cadastre as opções disponíveis no campo Embalagem ao registrar uma encomenda.</p></div></header>';
        $this->render_admin_tabs( 'packagings' );
        if ( in_array( $result, array( 'saved', 'updated' ), true ) ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>' . ( 'saved' === $result ? 'Embalagem salva com sucesso.' : 'Status da embalagem atualizado.' ) . '</strong></div></div></div>';
        } elseif ( 'error' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Não foi possível salvar. Confira o nome e verifique se ele já está cadastrado.</strong></div></div></div>';
        }
        $this->section_open( true, $editing ? 'Editar embalagem' : 'Cadastrar embalagem', 'As embalagens ativas aparecem no formulário de registro de encomendas.' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_packaging_save"><input type="hidden" name="packaging_id" value="' . esc_attr( $editing ? absint( $editing->id ) : 0 ) . '">';
        wp_nonce_field( 'pwl_encomendas_packaging_save' );
        echo '<div class="pwl-ui-layout--form"><label class="pwl-ui-field"><span class="pwl-ui-label">Nome da embalagem</span><input class="pwl-ui-control" name="packaging_name" maxlength="190" value="' . esc_attr( $editing ? $editing->name : '' ) . '" placeholder="Ex.: Caixa Pequena" required></label></div><p><button class="pwl-ui-button pwl-ui-button--primary" type="submit">' . ( $editing ? 'Salvar alterações' : 'Cadastrar embalagem' ) . '</button> ' . ( $editing ? '<a class="pwl-ui-button" href="' . esc_url( admin_url( 'admin.php?page=pwl-encomendas&encomendas_view=packagings' ) ) . '">Cancelar</a>' : '' ) . '</p></form>';
        $this->section_close( true );
        $this->section_open( true, 'Embalagens cadastradas', 'Embalagens inativas deixam de aparecer para novos registros.');
        echo '<div class="pwl-ui-table-wrap"><table class="pwl-ui-table"><thead><tr><th>Embalagem</th><th>Status</th><th>Ação</th></tr></thead><tbody>';
        foreach ( (array) $items as $item ) {
            $item_id = absint( $item->id );
            echo '<tr><td>' . esc_html( $item->name ) . '</td><td>' . ( $item->active ? 'Ativa' : 'Inativa' ) . '</td><td><a class="pwl-ui-button" href="' . esc_url( add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'packagings', 'edit_packaging' => $item_id ), admin_url( 'admin.php' ) ) ) . '">Editar</a> <form class="pwl-encomendas-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_packaging_toggle"><input type="hidden" name="packaging_id" value="' . esc_attr( $item_id ) . '"><input type="hidden" name="active" value="' . esc_attr( $item->active ? 0 : 1 ) . '">';
            wp_nonce_field( 'pwl_encomendas_packaging_toggle_' . $item_id );
            echo '<button class="pwl-ui-button" type="submit">' . ( $item->active ? 'Desativar' : 'Ativar' ) . '</button></form></td></tr>';
        }
        if ( ! $items ) { echo '<tr><td colspan="3">Nenhuma embalagem cadastrada.</td></tr>'; }
        echo '</tbody></table></div>';
        $this->section_close( true );
        echo '</main></div>';
    }

    public function handle_packaging_save() {
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_packaging_save' );
        global $wpdb;
        $id = absint( $_POST['packaging_id'] ?? 0 );
        $name = trim( sanitize_text_field( wp_unslash( $_POST['packaging_name'] ?? '' ) ) );
        if ( '' === $name ) { $this->redirect_packagings( 'error', $id ); }
        if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->packagings} WHERE id = %d", $id ) ) ) { $this->redirect_packagings( 'error', $id ); }
        $duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->packagings} WHERE name = %s AND id <> %d LIMIT 1", $name, $id ) );
        if ( $duplicate ) { $this->redirect_packagings( 'error', $id ); }
        $now = current_time( 'mysql' );
        if ( $id ) { $saved = $wpdb->update( $this->packagings, array( 'name' => $name, 'updated_at' => $now ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) ); }
        else { $next_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$this->packagings}" ); $saved = $wpdb->insert( $this->packagings, array( 'name' => $name, 'active' => 1, 'sort_order' => $next_order, 'created_at' => $now, 'updated_at' => $now ), array( '%s','%d','%d','%s','%s' ) ); }
        if ( false === $saved ) { $this->redirect_packagings( 'error', $id ); }
        $this->redirect_packagings( 'saved' );
    }

    public function handle_packaging_toggle() {
        $id = absint( $_POST['packaging_id'] ?? 0 );
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_packaging_toggle_' . $id );
        global $wpdb;
        $wpdb->update( $this->packagings, array( 'active' => empty( $_POST['active'] ) ? 0 : 1, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%d','%s' ), array( '%d' ) );
        $this->redirect_packagings( 'updated' );
    }

    private function redirect_packagings( $result, $edit_id = 0 ) {
        $args = array( 'encomendas_view' => 'packagings', 'pwl_packaging_result' => sanitize_key( $result ) );
        if ( $edit_id ) { $args['edit_packaging'] = absint( $edit_id ); }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=pwl-encomendas' ) ) );
        exit;
    }

    private function render_groups() {
        global $wpdb;
        $people = function_exists( 'pwl_get_people' ) ? pwl_get_people( array( 'limit' => 1000 ) ) : array();
        $groups = $wpdb->get_results( "SELECT * FROM {$this->groups} ORDER BY name ASC" );
        $edit_id = isset( $_GET['edit_group'] ) ? absint( $_GET['edit_group'] ) : 0;
        $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->groups} WHERE id = %d", $edit_id ) ) : null;
        $selected_ids = $editing ? array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT person_id FROM {$this->group_members} WHERE group_id = %d", $edit_id ) ) ) : array();
        $result = isset( $_GET['pwl_group_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_group_result'] ) ) : '';
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Grupos de Recebimento</h1><p class="pwl-ui-description">Cadastre destinatários coletivos para situações em que várias pessoas precisam receber o mesmo aviso.</p></div></header>';
        $this->render_admin_tabs( 'groups' );
        if ( in_array( $result, array( 'saved', 'updated' ), true ) ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>' . ( 'saved' === $result ? 'Grupo salvo com sucesso.' : 'Status do grupo atualizado.' ) . '</strong></div></div></div>';
        } elseif ( 'error' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Não foi possível salvar. Informe um nome e selecione pelo menos uma pessoa ativa.</strong></div></div></div>';
        }
        echo '<div class="pwl-encomendas-groups-grid">';
        $this->section_open( true, $editing ? 'Editar grupo' : 'Criar grupo de recebimento', 'Membros com conta no Portal recebem a notificação, incluindo o aviso do navegador/Windows quando autorizado. Todos os membros com e-mail cadastrado recebem o e-mail.' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_group_save"><input type="hidden" name="group_id" value="' . esc_attr( $editing ? absint( $editing->id ) : 0 ) . '">';
        wp_nonce_field( 'pwl_encomendas_group_save' );
        echo '<div class="pwl-ui-layout--form pwl-encomendas-group-form"><label class="pwl-ui-field"><span class="pwl-ui-label">Nome do grupo</span><input class="pwl-ui-control" name="group_name" maxlength="190" value="' . esc_attr( $editing ? $editing->name : '' ) . '" placeholder="Ex.: Compras" required></label><fieldset class="pwl-ui-field"><legend class="pwl-ui-label">Colaboradores membros</legend><label class="pwl-ui-field pwl-encomendas-group-search"><span class="pwl-ui-label">Buscar colaborador</span><input class="pwl-ui-control" type="search" data-pwl-group-search placeholder="Digite o nome do colaborador"></label><div class="pwl-encomendas-group-members">';
        $active_people = array_filter( (array) $people, function ( $person ) { return 'active' === ( $person['person_status'] ?? '' ); } );
        usort( $active_people, function ( $a, $b ) { return strnatcasecmp( remove_accents( $a['display_name'] ?: $a['full_name'] ), remove_accents( $b['display_name'] ?: $b['full_name'] ) ); } );
        foreach ( $active_people as $person ) {
            $person_id = absint( $person['id'] );
            $person_name = $person['display_name'] ?: $person['full_name'];
            echo '<label data-pwl-group-member><input type="checkbox" name="member_ids[]" value="' . esc_attr( $person_id ) . '" ' . checked( in_array( $person_id, $selected_ids, true ), true, false ) . '> <span>' . esc_html( $person_name ) . '</span></label>';
        }
        echo '</div></fieldset></div><p><button class="pwl-ui-button pwl-ui-button--primary" type="submit">' . ( $editing ? 'Salvar alterações' : 'Criar grupo' ) . '</button>' . ( $editing ? ' <a class="pwl-ui-button" href="' . esc_url( admin_url( 'admin.php?page=pwl-encomendas&encomendas_view=groups' ) ) . '">Cancelar edição</a>' : '' ) . '</p></form>';
        $this->section_close( true );
        $this->section_open( true, 'Grupos cadastrados', 'Grupos ativos aparecem no campo Colaborador destinatário, antes da lista de colaboradores.' );
        echo '<div class="pwl-ui-table-wrap"><table class="pwl-ui-table"><thead><tr><th>Grupo</th><th>Membros</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
        if ( $groups ) {
            foreach ( $groups as $group ) {
                $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->group_members} WHERE group_id = %d", absint( $group->id ) ) );
                $edit_url = add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'groups', 'edit_group' => absint( $group->id ) ), admin_url( 'admin.php' ) );
                echo '<tr><td><strong>' . esc_html( $group->name ) . '</strong></td><td>' . esc_html( number_format_i18n( $count ) ) . '</td><td>' . ( $group->active ? 'Ativo' : 'Inativo' ) . '</td><td><a class="pwl-ui-button" href="' . esc_url( $edit_url ) . '">Editar</a> <form class="pwl-encomendas-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_group_toggle"><input type="hidden" name="group_id" value="' . esc_attr( absint( $group->id ) ) . '"><input type="hidden" name="active" value="' . esc_attr( $group->active ? 0 : 1 ) . '">';
                wp_nonce_field( 'pwl_encomendas_group_toggle_' . absint( $group->id ) );
                echo '<button class="pwl-ui-button" type="submit">' . ( $group->active ? 'Desativar' : 'Ativar' ) . '</button></form></td></tr>';
            }
        } else {
            echo '<tr><td colspan="4">Nenhum grupo cadastrado.</td></tr>';
        }
        echo '</tbody></table></div>';
        $this->section_close( true );
        echo '</div>';
        echo '</main></div>';
    }

    public function handle_group_save() {
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_group_save' );
        global $wpdb;
        $id = absint( $_POST['group_id'] ?? 0 );
        $name = trim( sanitize_text_field( wp_unslash( $_POST['group_name'] ?? '' ) ) );
        $raw_ids = wp_unslash( $_POST['member_ids'] ?? array() );
        $ids = is_array( $raw_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) ) : array();
        $active_people = function_exists( 'pwl_get_people' ) ? pwl_get_people( array( 'status' => 'active', 'limit' => 1000 ) ) : array();
        $valid_ids = array();
        foreach ( (array) $active_people as $person ) { $valid_ids[] = absint( $person['id'] ); }
        $ids = array_values( array_intersect( $ids, $valid_ids ) );
        if ( '' === $name || ! $ids ) { $this->redirect_groups( 'error' ); }
        if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->groups} WHERE id = %d", $id ) ) ) { $this->redirect_groups( 'error' ); }
        $duplicate = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->groups} WHERE name = %s AND id <> %d LIMIT 1", $name, $id ) ) );
        if ( $duplicate ) { $this->redirect_groups( 'error' ); }
        $now = current_time( 'mysql' );
        $wpdb->query( 'START TRANSACTION' );
        if ( $id ) {
            $wpdb->update( $this->groups, array( 'name' => $name, 'updated_at' => $now ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) );
        } else {
            if ( false === $wpdb->insert( $this->groups, array( 'name' => $name, 'active' => 1, 'created_at' => $now, 'updated_at' => $now ), array( '%s','%d','%s','%s' ) ) ) {
                $wpdb->query( 'ROLLBACK' );
                $this->redirect_groups( 'error' );
            }
            $id = absint( $wpdb->insert_id );
        }
        if ( ! $id || $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); $this->redirect_groups( 'error' ); }
        $wpdb->delete( $this->group_members, array( 'group_id' => $id ), array( '%d' ) );
        foreach ( $ids as $person_id ) {
            if ( false === $wpdb->insert( $this->group_members, array( 'group_id' => $id, 'person_id' => $person_id, 'created_at' => $now ), array( '%d','%d','%s' ) ) ) {
                $wpdb->query( 'ROLLBACK' );
                $this->redirect_groups( 'error' );
            }
        }
        $wpdb->query( 'COMMIT' );
        $this->redirect_groups( 'saved' );
    }

    public function handle_group_toggle() {
        $id = absint( $_POST['group_id'] ?? 0 );
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_group_toggle_' . $id );
        global $wpdb;
        $wpdb->update( $this->groups, array( 'active' => empty( $_POST['active'] ) ? 0 : 1, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%d','%s' ), array( '%d' ) );
        $this->redirect_groups( 'updated' );
    }

    private function redirect_groups( $result ) {
        wp_safe_redirect( add_query_arg( array( 'encomendas_view' => 'groups', 'pwl_group_result' => sanitize_key( $result ) ), admin_url( 'admin.php?page=pwl-encomendas' ) ) );
        exit;
    }

    private function render_carriers() {
        global $wpdb;
        $carriers = $wpdb->get_results( "SELECT id, name, logo_attachment_id, active, created_at FROM {$this->carriers} ORDER BY active DESC, name ASC" );
        $edit_id = isset( $_GET['carrier_id'] ) ? absint( $_GET['carrier_id'] ) : 0;
        $editing_carrier = null;
        foreach ( (array) $carriers as $carrier_row ) {
            if ( $edit_id && absint( $carrier_row->id ) === $edit_id ) {
                $editing_carrier = $carrier_row;
                break;
            }
        }
        if ( $edit_id && ! $editing_carrier ) {
            $edit_id = 0;
        }
        $result = isset( $_GET['pwl_carrier_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_carrier_result'] ) ) : '';
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Transportadoras</h1><p class="pwl-ui-description">Cadastre as opções disponíveis no campo de remetente da Portaria.</p></div></header>';
        $this->render_admin_tabs( 'carriers' );
        if ( 'saved' === $result || 'updated' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>' . ( 'saved' === $result ? 'Dados da transportadora salvos.' : 'Situação da transportadora atualizada.' ) . '</strong></div></div></div>';
        } elseif ( 'error' === $result ) {
            echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Não foi possível salvar. Confira o nome e selecione um arquivo de imagem válido.</strong></div></div></div>';
        }
        echo '<div class="pwl-encomendas-carriers-grid">';
        $this->section_open( true, $editing_carrier ? 'Editar transportadora' : 'Cadastrar transportadora', 'Escolha uma imagem da Biblioteca de Mídia para exibir nos cards de encomenda.' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_carrier_save">';
        wp_nonce_field( 'pwl_encomendas_carrier_save' );
        echo '<input type="hidden" name="carrier_id" value="' . esc_attr( $editing_carrier ? absint( $editing_carrier->id ) : 0 ) . '">';
        $current_logo_id = $editing_carrier ? absint( $editing_carrier->logo_attachment_id ) : 0;
        $current_logo_url = $current_logo_id && wp_attachment_is_image( $current_logo_id ) ? wp_get_attachment_image_url( $current_logo_id, 'medium' ) : '';
        $open_media_handler = "var picker=this.closest('[data-pwl-carrier-picker]');if(!picker||!window.wp||typeof window.wp.media!=='function'){window.alert('A Biblioteca de Mídia do WordPress não foi carregada. Atualize a página e tente novamente.');return false;}var frame=window.wp.media({title:'Escolha o logo da transportadora',button:{text:'Usar esta imagem'},library:{type:'image'},multiple:false});frame.on('select',function(){var selected=frame.state().get('selection').first();if(!selected)return;var image=selected.toJSON();if(!image.id||!image.url)return;var idField=picker.querySelector('[data-pwl-carrier-image-id]');var previewWrap=picker.querySelector('[data-pwl-carrier-preview-wrap]');var preview=picker.querySelector('[data-pwl-carrier-preview]');var remove=picker.querySelector('[data-pwl-carrier-image-remove]');if(idField)idField.value=String(image.id);if(preview)preview.src=image.sizes&&image.sizes.medium?image.sizes.medium.url:(image.sizes&&image.sizes.thumbnail?image.sizes.thumbnail.url:image.url);if(previewWrap)previewWrap.hidden=false;if(remove)remove.hidden=false;});frame.open();return false;";
        echo '<div class="pwl-ui-layout--form pwl-encomendas-carrier-form"><label class="pwl-ui-field pwl-encomendas-carrier-name-field"><span class="pwl-ui-label">Nome da transportadora</span><input class="pwl-ui-control" name="carrier_name" maxlength="190" value="' . esc_attr( $editing_carrier ? $editing_carrier->name : '' ) . '" required></label>';
        echo '<div class="pwl-ui-field pwl-encomendas-carrier-logo-field"><span class="pwl-ui-label">Imagem / logotipo</span><div class="pwl-encomendas-carrier-logo-picker" data-pwl-carrier-picker><input type="hidden" name="carrier_logo_attachment_id" value="' . esc_attr( $current_logo_id ) . '" data-pwl-carrier-image-id><span class="pwl-encomendas-carrier-admin-preview" data-pwl-carrier-preview-wrap' . ( $current_logo_url ? '' : ' hidden' ) . '><img src="' . esc_url( $current_logo_url ?: '' ) . '" alt="" data-pwl-carrier-preview></span><button type="button" class="pwl-ui-button" data-pwl-carrier-image-open onclick="' . esc_attr( $open_media_handler ) . '">Escolher imagem</button><button type="button" class="pwl-ui-button" data-pwl-carrier-image-remove' . ( $current_logo_url ? '' : ' hidden' ) . '>Remover imagem</button></div><small>Se não escolher uma imagem, o card usa o logo automático quando houver correspondência.</small></div>';
        echo '<div class="pwl-encomendas-carrier-form-actions"><button class="pwl-ui-button pwl-ui-button--primary pwl-encomendas-carrier-submit" type="submit">' . ( $editing_carrier ? 'Salvar alterações' : 'Cadastrar' ) . '</button>';
        if ( $editing_carrier ) {
            echo '<a class="pwl-ui-button" href="' . esc_url( admin_url( 'admin.php?page=pwl-encomendas&encomendas_view=carriers' ) ) . '">Cancelar</a>';
        }
        echo '</div></div></form>';
        $this->section_close( true );
        $this->section_open( true, 'Transportadoras cadastradas', 'Transportadoras desativadas deixam de aparecer para novos registros.' );
        echo '<div class="pwl-ui-table-wrap"><table class="pwl-ui-table"><thead><tr><th>Transportadora</th><th>Logo</th><th>Status</th><th>Ação</th></tr></thead><tbody>';
        if ( $carriers ) {
            foreach ( $carriers as $carrier ) {
                $carrier_logo_url = absint( $carrier->logo_attachment_id ) && wp_attachment_is_image( absint( $carrier->logo_attachment_id ) ) ? wp_get_attachment_image_url( absint( $carrier->logo_attachment_id ), 'medium' ) : '';
                echo '<tr><td>' . esc_html( $carrier->name ) . '</td><td>' . ( $carrier_logo_url ? '<img class="pwl-encomendas-carrier-admin-table-logo" src="' . esc_url( $carrier_logo_url ) . '" alt="" loading="lazy">' : 'Automático' ) . '</td><td>' . ( $carrier->active ? 'Ativa' : 'Inativa' ) . '</td><td><a class="pwl-ui-button" href="' . esc_url( add_query_arg( array( 'page' => 'pwl-encomendas', 'encomendas_view' => 'carriers', 'carrier_id' => absint( $carrier->id ) ), admin_url( 'admin.php' ) ) ) . '">Editar</a> <form class="pwl-encomendas-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_carrier_toggle"><input type="hidden" name="carrier_id" value="' . esc_attr( absint( $carrier->id ) ) . '"><input type="hidden" name="active" value="' . esc_attr( $carrier->active ? 0 : 1 ) . '">';
                wp_nonce_field( 'pwl_encomendas_carrier_toggle_' . absint( $carrier->id ) );
                echo '<button class="pwl-ui-button" type="submit">' . ( $carrier->active ? 'Desativar' : 'Ativar' ) . '</button></form></td></tr>';
            }
        } else {
            echo '<tr><td colspan="4">Nenhuma transportadora cadastrada.</td></tr>';
        }
        echo '</tbody></table></div>';
        $this->section_close( true );
        echo '</div>';
        echo '</main></div>';
    }

    private function event_label( $event ) {
        $labels = array( 'received' => 'Recebimento', 'notice' => 'Aviso', 'retry' => 'Reenvio', 'picked_up' => 'Retirada', 'pickup_notice' => 'Aviso de retirada' );
        return $labels[ $event ] ?? sanitize_text_field( $event );
    }

    private function event_channel_label( $channel ) {
        $labels = array( 'portal' => 'Portal', 'email' => 'E-mail', 'windows' => 'Windows', 'whatsapp' => 'WhatsApp', 'teams' => 'Microsoft Teams' );
        return $labels[ $channel ] ?? ( $channel ? sanitize_text_field( $channel ) : '—' );
    }

    private function event_result_label( $result ) {
        $labels = array( 'success' => 'Concluído', 'created' => 'Criado', 'accepted' => 'Aceito pela API', 'sent' => 'Enviado', 'failed' => 'Falhou', 'skipped' => 'Ignorado', 'not_configured' => 'Não configurado', 'pending' => 'Pendente' );
        return $labels[ $result ] ?? sanitize_text_field( $result );
    }

    public function shortcode() {
        if ( self::$shortcode_rendered ) {
            return '';
        }
        if ( ! is_user_logged_in() ) {
            self::$shortcode_rendered = true;
            return '<div id="pwl_encomendas_root"><div class="pwl-encomendas-access-message">Entre no Portal para acessar o módulo de Encomendas.</div></div>';
        }
        if ( ! $this->can( self::CAP_VIEW ) && ! $this->can( self::CAP_MANAGE ) && ! $this->can( self::CAP_HYBRID ) ) {
            self::$shortcode_rendered = true;
            return '<div id="pwl_encomendas_root"><div class="pwl-encomendas-access-message">Você não possui permissão para acessar o módulo de Encomendas.</div></div>';
        }
        self::$shortcode_rendered = true;
        $this->enqueue_portal_assets();
        ob_start();
        $this->render_workspace( false );
        return ob_get_clean();
    }

    private function render_portal_stats( $metrics ) {
        echo '<div class="pwl-encomendas-stats">';
        foreach ( (array) $metrics as $metric ) {
            $metric_name = (string) $metric[0];
            $metric_value = (int) $metric[1];
            $tone = '';
            if ( in_array( $metric_name, array( 'Aguardando retirada', 'Avisos pendentes', 'Notificações de colaboradores' ), true ) ) {
                $tone = $metric_value >= 10 ? 'danger' : ( $metric_value >= 5 ? 'warning' : 'success' );
            } elseif ( in_array( $metric_name, array( 'Recebidas hoje', 'Retiradas hoje' ), true ) ) {
                $tone = 'success';
            }
            $tone_class = $tone ? ' cm-encomendas-stat--' . $tone : '';
            echo '<section class="pwl-card' . esc_attr( $tone_class ) . '"><div class="pwl-card__body"><strong class="pwl-encomendas-stat-value">' . esc_html( number_format_i18n( $metric_value ) ) . '</strong><span class="pwl-encomendas-stat-label">' . esc_html( $metric_name ) . '</span></div></section>';
        }
        echo '</div>';
    }

    private function render_workspace( $admin = false ) {
        global $wpdb;
        $can_manage = $this->can( self::CAP_MANAGE );
        $can_hybrid = $can_manage && $this->can( self::CAP_HYBRID );
        $current_user_id = get_current_user_id();
        $current_person = $this->current_person_for_user( $current_user_id );
        $search = isset( $_GET['pwl_encomendas_q'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_encomendas_q'] ) ) : '';
        $status = isset( $_GET['pwl_encomendas_status'] ) ? sanitize_key( wp_unslash( $_GET['pwl_encomendas_status'] ) ) : ( $admin ? '' : 'awaiting_pickup' );
        $filter_person_id = isset( $_GET['pwl_encomendas_person'] ) ? absint( wp_unslash( $_GET['pwl_encomendas_person'] ) ) : 0;
        $filter_carrier = isset( $_GET['pwl_encomendas_carrier'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_encomendas_carrier'] ) ) : '';
        $filter_period = $admin && isset( $_GET['pwl_encomendas_period'] ) ? sanitize_key( wp_unslash( $_GET['pwl_encomendas_period'] ) ) : '';
        $filter_date = $admin && isset( $_GET['pwl_encomendas_date'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_encomendas_date'] ) ) : '';
        $filter_month = ! $admin && ! $can_manage && isset( $_GET['pwl_encomendas_month'] ) ? sanitize_text_field( wp_unslash( $_GET['pwl_encomendas_month'] ) ) : '';
        $default_sort = 'received';
        $default_direction = 'desc';
        $where = array( '1=1' );
        $params = array();
        if ( ! $admin && ! $can_manage ) {
            $where[] = "(recipient_user_id = %d OR EXISTS (SELECT 1 FROM {$this->package_recipients} prs_scope WHERE prs_scope.package_id = {$this->table}.id AND prs_scope.user_id = %d))";
            $params[] = $current_user_id;
            $params[] = $current_user_id;
            if ( preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $filter_month ) ) {
                $month_start = DateTimeImmutable::createFromFormat( '!Y-m-d', $filter_month . '-01', wp_timezone() );
                if ( $month_start && $month_start->format( 'Y-m' ) === $filter_month ) {
                    $where[] = 'received_at >= %s AND received_at < %s';
                    $params[] = $month_start->format( 'Y-m-d H:i:s' );
                    $params[] = $month_start->modify( '+1 month' )->format( 'Y-m-d H:i:s' );
                } else {
                    $filter_month = '';
                }
            } else {
                $filter_month = '';
            }
        }
        if ( in_array( $status, array( 'awaiting_pickup', 'picked_up' ), true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( $filter_person_id ) {
            $where[] = "(recipient_person_id = %d OR EXISTS (SELECT 1 FROM {$this->package_recipients} prf WHERE prf.package_id = {$this->table}.id AND prf.person_id = %d))";
            $params[] = $filter_person_id;
            $params[] = $filter_person_id;
        }
        if ( '' !== $filter_carrier ) {
            $where[] = 'sender = %s';
            $params[] = $filter_carrier;
        }
        if ( $admin && $filter_period ) {
            $period_start = null;
            $period_end = null;
            $now = current_datetime();
            $today_start = $now->setTime( 0, 0, 0 );
            $tomorrow_start = $today_start->modify( '+1 day' );
            switch ( $filter_period ) {
                case 'today':
                    $period_start = $today_start;
                    $period_end = $tomorrow_start;
                    break;
                case 'yesterday':
                    $period_start = $today_start->modify( '-1 day' );
                    $period_end = $today_start;
                    break;
                case 'this_week':
                    $week_start = (int) get_option( 'start_of_week', 1 );
                    $days_since_week_start = ( (int) $today_start->format( 'w' ) - $week_start + 7 ) % 7;
                    $period_start = $today_start->modify( '-' . $days_since_week_start . ' days' );
                    $period_end = $period_start->modify( '+7 days' );
                    break;
                case 'this_month':
                    $period_start = $today_start->modify( 'first day of this month' );
                    $period_end = $period_start->modify( '+1 month' );
                    break;
                case 'last_month':
                    $period_end = $today_start->modify( 'first day of this month' );
                    $period_start = $period_end->modify( '-1 month' );
                    break;
                case 'last_6_months':
                    $period_start = $today_start->modify( '-6 months' );
                    $period_end = $tomorrow_start;
                    break;
                case 'last_year':
                    $period_start = $today_start->modify( '-1 year' );
                    $period_end = $tomorrow_start;
                    break;
                case 'custom':
                    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter_date ) ) {
                        $selected_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $filter_date, wp_timezone() );
                        if ( $selected_date && $selected_date->format( 'Y-m-d' ) === $filter_date ) {
                            $period_start = $selected_date;
                            $period_end = $selected_date->modify( '+1 day' );
                        }
                    }
                    break;
            }
            if ( $period_start && $period_end ) {
                $where[] = 'received_at >= %s AND received_at < %s';
                $params[] = $period_start->format( 'Y-m-d H:i:s' );
                $params[] = $period_end->format( 'Y-m-d H:i:s' );
            } elseif ( 'custom' === $filter_period ) {
                $where[] = '1 = 0';
            }
        }
        if ( '' !== $search ) {
            $search_fields = array(
                'CAST(`recipient_name` AS CHAR)',
                'CAST(`recipient_email` AS CHAR)',
                'CAST(`recipient_mobile` AS CHAR)',
                'CAST(`recipient_unit` AS CHAR)',
                'CAST(`sender` AS CHAR)',
                'CAST(`package_code` AS CHAR)',
                'CAST(`tracking_code` AS CHAR)',
                'CAST(`description` AS CHAR)',
                'CAST(`notes` AS CHAR)',
                'CAST(`picked_up_by_name` AS CHAR)',
                "DATE_FORMAT(`received_at`, '%%d/%%m/%%Y %%H:%%i')",
                "DATE_FORMAT(`picked_up_at`, '%%d/%%m/%%Y %%H:%%i')",
                "CASE `status` WHEN 'awaiting_pickup' THEN 'Aguardando retirada' WHEN 'picked_up' THEN 'Retirada' ELSE `status` END",
                "CASE `portal_status` WHEN 'created' THEN 'Criado' WHEN 'accepted' THEN 'Aceito' WHEN 'sent' THEN 'Enviado' WHEN 'failed' THEN 'Falhou' WHEN 'skipped' THEN 'Ignorado' WHEN 'not_configured' THEN 'Não configurado' ELSE `portal_status` END",
                "CASE `email_status` WHEN 'created' THEN 'Criado' WHEN 'accepted' THEN 'Aceito' WHEN 'sent' THEN 'Enviado' WHEN 'failed' THEN 'Falhou' WHEN 'skipped' THEN 'Ignorado' WHEN 'not_configured' THEN 'Não configurado' ELSE `email_status` END",
                "CASE `whatsapp_status` WHEN 'created' THEN 'Criado' WHEN 'accepted' THEN 'Aceito' WHEN 'sent' THEN 'Enviado' WHEN 'failed' THEN 'Falhou' WHEN 'skipped' THEN 'Ignorado' WHEN 'not_configured' THEN 'Não configurado' ELSE `whatsapp_status` END",
                "CASE `teams_status` WHEN 'created' THEN 'Criado' WHEN 'accepted' THEN 'Aceito' WHEN 'sent' THEN 'Enviado' WHEN 'failed' THEN 'Falhou' WHEN 'skipped' THEN 'Ignorado' WHEN 'not_configured' THEN 'Não configurado' ELSE `teams_status` END",
            );
            $terms = preg_split( '/\s+/', trim( $search ) );
            foreach ( $terms as $term ) {
                if ( '' === $term ) {
                    continue;
                }
                $like = '%' . $wpdb->esc_like( $term ) . '%';
                $term_where = array();
                foreach ( $search_fields as $field_expression ) {
                    $term_where[] = $field_expression . ' LIKE %s';
                    $params[] = $like;
                }
                $term_where[] = "EXISTS (SELECT 1 FROM {$this->package_recipients} prs WHERE prs.package_id = {$this->table}.id AND prs.person_name LIKE %s)";
                $params[] = $like;
                $where[] = '(' . implode( ' OR ', $term_where ) . ')';
            }
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY received_at DESC, id DESC';
        $query = $params ? $wpdb->prepare( $sql, $params ) : $sql;
        $rows = $wpdb->get_results( $query );
        $people = function_exists( 'pwl_get_people' ) ? pwl_get_people( array( 'status' => 'active', 'limit' => 1000 ) ) : array();
        $filter_people = function_exists( 'pwl_get_people' ) ? pwl_get_people( array( 'limit' => 1000 ) ) : array();
        $carriers = $wpdb->get_results( "SELECT id, name FROM {$this->carriers} WHERE active = 1 ORDER BY name ASC" );
        $recipient_groups = $wpdb->get_results( "SELECT id, name FROM {$this->groups} WHERE active = 1 ORDER BY name ASC" );
        $filter_carriers = $wpdb->get_results( "SELECT id, name FROM {$this->carriers} ORDER BY name ASC" );
        $notice_scope = ( ! $admin && ! $can_manage ) ? $wpdb->prepare( ' WHERE user_id = %d', $current_user_id ) : '';
        if ( ! $admin && ! $can_manage ) {
            $notice_scope .= $notice_scope ? " AND status <> 'linked'" : " WHERE status <> 'linked'";
        }
        $notices = $wpdb->get_results( "SELECT * FROM {$this->notices}{$notice_scope} ORDER BY created_at DESC LIMIT 200" );
        $notice_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->notices} WHERE status = 'pending'" );
        $package_scope = '';
        if ( ! $admin && ! $can_manage ) {
            $package_scope = $wpdb->prepare( " AND (recipient_user_id = %d OR EXISTS (SELECT 1 FROM {$this->package_recipients} prs_scope WHERE prs_scope.package_id = {$this->table}.id AND prs_scope.user_id = %d))", $current_user_id, $current_user_id );
        }
        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'awaiting_pickup'{$package_scope}" );
        $today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE received_at >= %s{$package_scope}", current_time( 'Y-m-d 00:00:00' ) ) );
        $picked_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'picked_up' AND picked_up_at >= %s{$package_scope}", current_time( 'Y-m-d 00:00:00' ) ) );
        $notices_attention = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'awaiting_pickup' AND (portal_status IN ('pending','failed','not_configured') OR email_status IN ('pending','failed','not_configured') OR whatsapp_status IN ('pending','failed','not_configured') OR teams_status IN ('pending','failed','not_configured')){$package_scope}" );
        if ( ! $admin && ! $can_manage ) {
            $notices_attention = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->notices} WHERE user_id = %d AND status = 'pending'", $current_user_id ) );
        } elseif ( ! $admin ) {
            $notices_attention = $notice_pending;
        }
        $wa_ready = $this->whatsapp_configured();
        $feedback = isset( $_GET['pwl_encomendas_result'] ) ? sanitize_key( wp_unslash( $_GET['pwl_encomendas_result'] ) ) : '';

        if ( $admin ) {
            echo '<div class="pwl-ui"><main class="pwl-ui-page">';
            echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Controle de Encomendas</h1><p class="pwl-ui-description">Registre recebimentos, acompanhe avisos e controle as retiradas.</p></div><div class="pwl-ui-version"><span>Status do módulo</span><strong>Em produção</strong></div></header>';
            $this->render_admin_tabs( 'packages' );
        } else {
            echo '<div class="pwl-ds pwl-portal-ui pwl-encomendas-portal' . ( $can_manage ? ' cm-stitch-gestao' : '' ) . '"><main class="pwl-portal-page pwl-page">';
            $portal_title = $can_manage ? 'Controle de Encomendas' : 'Minhas Encomendas';
            $portal_description = $can_manage ? 'Registre recebimentos, acompanhe avisos e controle as retiradas.' : 'Acompanhe o que chegou para você e avise a Portaria sobre o que está a caminho.';
            echo '<header class="pwl-portal-page-header"><div class="pwl-portal-page-header__identity"><div class="pwl-portal-page-header__content"><div class="pwl-portal-page-header__context"><span class="pwl-portal-page-header__eyebrow">Recursos Corporativos · Portaria</span></div><h1 class="pwl-portal-page-header__title">' . esc_html( $portal_title ) . '</h1><p class="pwl-portal-page-header__description">' . esc_html( $portal_description ) . '</p></div></div>' . ( $can_manage ? '<div class="pwl-portal-page-header__aside"><span class="pwl-portal-page-header__status pwl-portal-page-header__status--' . ( $wa_ready ? 'success' : 'warning' ) . '">' . ( $wa_ready ? 'WhatsApp configurado' : 'WhatsApp aguardando configuração' ) . '</span></div>' : '' ) . '</header>';
        }

        if ( $feedback ) {
            $messages = array(
                'created' => array( 'success', 'Encomenda registrada. Os avisos e eventos foram salvos para consulta em Relatórios.' ),
                'picked' => array( 'success', 'Retirada registrada.' ),
                'error' => array( 'danger', 'Não foi possível concluir a operação. Verifique os dados e tente novamente.' ),
                'deleted' => array( 'success', 'As encomendas selecionadas e seus eventos relacionados foram excluídos.' ),
                'notice_created' => array( 'success', 'Aviso antecipado enviado para a Portaria.' ),
                'notice_cancelled' => array( 'info', 'Aviso antecipado cancelado.' ),
                'duplicate' => array( 'info', 'Envio repetido bloqueado. Atualize a tela e confira a lista antes de tentar novamente.' ),
            );
            if ( isset( $messages[ $feedback ] ) ) {
                if ( $admin ) {
                    echo '<div class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>' . esc_html( $messages[ $feedback ][1] ) . '</strong></div></div></div>';
                } else {
                    echo '<div class="pwl-alert pwl-alert--' . esc_attr( $messages[ $feedback ][0] ) . ' pwl-encomendas-feedback"><div class="pwl-alert__content">' . esc_html( $messages[ $feedback ][1] ) . '</div></div>';
                }
            }
        }

        if ( ! function_exists( 'pwl_get_people' ) || ! function_exists( 'pwl_criar_notificacao' ) ) {
            if ( $admin ) {
                echo '<section class="pwl-ui-card"><div class="pwl-ui-card-body"><div class="pwl-ui-notice"><strong>Dependência indisponível.</strong> Ative PWL Core e PWL Notificações para usar o módulo.</div></div></section>';
            } else {
                echo '<div class="pwl-alert pwl-alert--warning"><div class="pwl-alert__content"><strong class="pwl-alert__title">Dependência indisponível</strong>Ative PWL Core e PWL Notificações para usar o módulo.</div></div>';
            }
            echo '</main></div>';
            return;
        }

        $metrics = array(
            array( 'Aguardando retirada', $pending ),
            array( 'Recebidas hoje', $today ),
            array( 'Retiradas hoje', $picked_today ),
            array( 'Avisos pendentes', $notices_attention ),
        );
        if ( ! $admin && ! $can_manage ) {
            $employee_period_scope = $package_scope;
            if ( $filter_month ) {
                $employee_month_start = DateTimeImmutable::createFromFormat( '!Y-m-d', $filter_month . '-01', wp_timezone() );
                $employee_period_scope .= $wpdb->prepare( ' AND received_at >= %s AND received_at < %s', $employee_month_start->format( 'Y-m-d H:i:s' ), $employee_month_start->modify( '+1 month' )->format( 'Y-m-d H:i:s' ) );
            }
            $employee_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE 1=1{$employee_period_scope}" );
            $employee_picked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE status = 'picked_up'{$employee_period_scope}" );
            $employee_notice_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->notices} WHERE user_id = %d AND status <> 'cancelled'", $current_user_id ) );
            $metrics = array(
                array( 'Aguardando retirada', $pending ),
                array( 'Retiradas no período', $employee_picked ),
                array( 'Total no período', $employee_total ),
                array( 'Avisos enviados', $employee_notice_count ),
            );
        }
        if ( $can_manage && ! $admin ) {
            echo '<div class="cm-stitch-actions"><div class="cm-stitch-identity"><span class="cm-stitch-package-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 19 29 5l29 13-23 16L5 19Z"/><path d="M5 19v29l30 13V34M58 18v30L35 61"/><path d="M29 5v23M5 19l-3 20 25 11 8-16M35 34l8 17 19-22-4-11"/></svg></span><div class="cm-stitch-heading"><h2>Controle de Encomendas &amp; Portaria</h2><p>Triagem centralizada, registro de correspondências e avisos aos colaboradores.</p></div></div><button type="button" class="pwl-button pwl-button--primary cm-stitch-open" data-cm-open-registration>Registrar Encomenda</button>';
            if ( $can_hybrid ) {
                echo '<button type="button" class="pwl-button pwl-button--secondary" data-pwl-open-notice>Avisar a Portaria</button>';
            }
            echo '</div>';
            if ( $can_hybrid ) {
                echo '<div class="pwl-encomendas-hybrid-notice">';
                $this->section_open( false, 'Avisar a Portaria', 'Informe uma encomenda própria a caminho, incluindo palavra-passe e observações.', 'pwl-encomendas-notice-form-card pwl-encomendas-registration-card' );
                if ( ! empty( $current_person ) && 'active' === ( $current_person['person_status'] ?? '' ) ) {
                    $this->render_advance_notice_form( $current_person, $carriers );
                } else {
                    echo '<div class="pwl-alert pwl-alert--warning"><div class="pwl-alert__content">Cadastro do colaborador não vinculado ao PWL Core.</div></div>';
                }
                echo '<div class="pwl-encomendas-own-notices"><h3>Meus avisos enviados</h3>';
                $own_notices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->notices} WHERE user_id = %d AND status <> 'linked' ORDER BY created_at DESC LIMIT 200", $current_user_id ) );
                $this->render_advance_notice_cards( $own_notices, false );
                echo '</div>';
                $this->section_close( false );
                echo '</div>'; 
            }
            echo '<div class="pwl-encomendas-management-top"><div class="pwl-encomendas-management-main">';
            // Indicador visual com contagem real de avisos pendentes.
            $management_metrics = $metrics;
            $management_metrics[] = array( 'Notificações de colaboradores', $notice_pending );
            $this->render_portal_stats( $management_metrics );
            $this->section_open( false, 'Registrar Encomenda', 'Preencha os dados de entrega. O colaborador será notificado em tempo real.', 'pwl-encomendas-registration-card' );
            $pending_advance_notices = array_values( array_filter( (array) $notices, function ( $notice ) { return 'pending' === $notice->status; } ) );
            $this->render_create_form( $people, false, $carriers, $recipient_groups );
            $this->section_close( false );
            echo '</div>';
            $this->section_open( false, 'Notificações de colaboradores', number_format_i18n( $notice_pending ) . ' aguardando chegada', 'pwl-encomendas-management-notifications' );
            $this->render_advance_notice_queue( $notices );
            $this->render_advance_notice_promotion_modal();
            $this->section_close( false );
            echo '</div>';
            
        } else {
            if ( $admin ) {
                echo '<div class="pwl-ui-grid pwl-encomendas-admin-stats">';
                foreach ( $metrics as $metric ) {
                    echo '<section class="pwl-ui-card"><div class="pwl-ui-card-body"><span class="pwl-ui-label">' . esc_html( $metric[0] ) . '</span><p><strong>' . esc_html( number_format_i18n( $metric[1] ) ) . '</strong></p></div></section>';
                }
                echo '</div>';
            } else {
                $this->render_portal_stats( $metrics );
            }
        }

        if ( ! $admin && ! $can_manage ) {
            $employee_months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(received_at, '%Y-%m') FROM {$this->table} WHERE 1=1{$package_scope} ORDER BY 1 DESC" );
            echo '<div class="pwl-encomendas-employee-layout">';
            $this->section_open( false, 'Encomendas recebidas', 'Consulte os volumes registrados para você.' );
            echo '<form class="pwl-encomendas-employee-filters" method="get"><label class="pwl-field"><span class="pwl-field__label">Mês</span><select class="pwl-select" name="pwl_encomendas_month"><option value="">Todos os meses</option>';
            foreach ( (array) $employee_months as $month_value ) {
                $month_timestamp = strtotime( $month_value . '-01' );
                echo '<option value="' . esc_attr( $month_value ) . '" ' . selected( $filter_month, $month_value, false ) . '>' . esc_html( $month_timestamp ? wp_date( 'F Y', $month_timestamp ) : $month_value ) . '</option>';
            }
            echo '</select></label><label class="pwl-field"><span class="pwl-field__label">Status</span><select class="pwl-select" name="pwl_encomendas_status"><option value="">Todos</option><option value="awaiting_pickup" ' . selected( $status, 'awaiting_pickup', false ) . '>Aguardando retirada</option><option value="picked_up" ' . selected( $status, 'picked_up', false ) . '>Retirada</option></select></label><label class="pwl-field"><span class="pwl-field__label">Buscar em suas encomendas</span><input class="pwl-input" type="search" name="pwl_encomendas_q" value="' . esc_attr( $search ) . '" placeholder="Remetente, código ou descrição"></label><button class="pwl-button pwl-button--secondary" type="submit">Filtrar</button></form>';
            $this->render_package_cards( $rows, $people, false );
            $this->section_close( false );
            $this->section_open( false, 'Avisar encomenda', 'Informe o que está a caminho e cadastre a palavra-passe que a Portaria deverá solicitar na retirada.', 'pwl-encomendas-notice-form-card pwl-encomendas-registration-card' );
            if ( ! empty( $current_person ) && 'active' === ( $current_person['person_status'] ?? '' ) ) {
                $this->render_advance_notice_form( $current_person, $carriers );
            } else {
                echo '<div class="pwl-alert pwl-alert--warning"><div class="pwl-alert__content"><strong class="pwl-alert__title">Cadastro do colaborador não vinculado</strong>Peça à equipe do Portal para associar seu usuário à pessoa cadastrada no PWL Core.</div></div>';
            }
            echo '<div class="pwl-encomendas-own-notices"><h3>Avisos enviados</h3>';
            $this->render_advance_notice_cards( $notices, false );
            echo '</div>';
            $this->section_close( false );
            echo '</div></main></div>';
            return;
        }

        echo $admin ? '<div class="pwl-encomendas-admin-list">' : '<div class="pwl-encomendas-layout">';

        $this->section_open( $admin, 'Encomendas registradas', 'Consulte o status dos avisos e das retiradas.' );
        echo '<form method="get">';
        if ( $admin ) {
            echo '<input type="hidden" name="page" value="pwl-encomendas"><input type="hidden" name="encomendas_view" value="packages">';
            echo '<div class="pwl-encomendas-admin-filters">';
            echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Buscar encomendas</span><input class="pwl-ui-control" type="search" name="pwl_encomendas_q" value="' . esc_attr( $search ) . '" placeholder="Colaborador, remetente, descrição ou rastreio"></label>';
            echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Status</span><select class="pwl-ui-control" name="pwl_encomendas_status"><option value="">Todos</option><option value="awaiting_pickup" ' . selected( $status, 'awaiting_pickup', false ) . '>Aguardando retirada</option><option value="picked_up" ' . selected( $status, 'picked_up', false ) . '>Retirada</option></select></label>';
            echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Período de recebimento</span><select class="pwl-ui-control" name="pwl_encomendas_period" data-pwl-period-filter><option value="">Todos os períodos</option><option value="today" ' . selected( $filter_period, 'today', false ) . '>Hoje</option><option value="yesterday" ' . selected( $filter_period, 'yesterday', false ) . '>Ontem</option><option value="this_week" ' . selected( $filter_period, 'this_week', false ) . '>Esta semana</option><option value="this_month" ' . selected( $filter_period, 'this_month', false ) . '>Este mês</option><option value="last_month" ' . selected( $filter_period, 'last_month', false ) . '>Mês passado</option><option value="last_6_months" ' . selected( $filter_period, 'last_6_months', false ) . '>Últimos 6 meses</option><option value="last_year" ' . selected( $filter_period, 'last_year', false ) . '>Último ano</option><option value="custom" ' . selected( $filter_period, 'custom', false ) . '>Personalizado</option></select></label>';
            echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Transportadora</span><select class="pwl-ui-control" name="pwl_encomendas_carrier"><option value="">Todas</option>';
            foreach ( $filter_carriers as $filter_carrier_option ) {
                echo '<option value="' . esc_attr( $filter_carrier_option->name ) . '" ' . selected( $filter_carrier, $filter_carrier_option->name, false ) . '>' . esc_html( $filter_carrier_option->name ) . '</option>';
            }
            echo '</select></label><label class="pwl-ui-field pwl-encomendas-admin-date" data-pwl-custom-date' . ( 'custom' === $filter_period ? '' : ' hidden' ) . '><span class="pwl-ui-label">Selecionar a data</span><input class="pwl-ui-control" type="date" name="pwl_encomendas_date" value="' . esc_attr( $filter_date ) . '"' . ( 'custom' === $filter_period ? ' required' : '' ) . '></label></div>';
            echo '<button class="pwl-ui-button pwl-ui-button--primary" type="submit">Aplicar filtros</button></form>';
        } else {
            echo '<div class="pwl-encomendas-filter-grid"><label class="pwl-field"><span class="pwl-field__label">Buscar em todas as colunas</span><input class="pwl-input" type="search" name="pwl_encomendas_q" value="' . esc_attr( $search ) . '" placeholder="Digite nome, unidade, remetente, código, status ou qualquer termo"></label>';
            echo '<label class="pwl-field"><span class="pwl-field__label">Status</span><select class="pwl-select" name="pwl_encomendas_status"><option value="">Todos</option><option value="awaiting_pickup" ' . selected( $status, 'awaiting_pickup', false ) . '>Aguardando retirada</option><option value="picked_up" ' . selected( $status, 'picked_up', false ) . '>Retirada</option></select></label>';
            echo '<label class="pwl-field"><span class="pwl-field__label">Usuário</span><select class="pwl-select" name="pwl_encomendas_person"><option value="">Todos</option>';
            foreach ( $filter_people as $filter_person ) {
                $filter_person_name = $filter_person['display_name'] ?: $filter_person['full_name'];
                echo '<option value="' . esc_attr( absint( $filter_person['id'] ) ) . '" ' . selected( $filter_person_id, absint( $filter_person['id'] ), false ) . '>' . esc_html( $filter_person_name ) . '</option>';
            }
            echo '</select></label><label class="pwl-field"><span class="pwl-field__label">Transportadora</span><select class="pwl-select" name="pwl_encomendas_carrier"><option value="">Todas</option>';
            foreach ( $filter_carriers as $filter_carrier_option ) {
                echo '<option value="' . esc_attr( $filter_carrier_option->name ) . '" ' . selected( $filter_carrier, $filter_carrier_option->name, false ) . '>' . esc_html( $filter_carrier_option->name ) . '</option>';
            }
            echo '</select></label>';
            echo '<button class="pwl-button pwl-button--secondary pwl-encomendas-filter-button" type="submit">Filtrar encomendas</button></div></form>';
        }
        if ( ! $admin ) {
            $this->render_package_cards( $rows, $people, $can_manage );
        } else {
            $this->render_admin_table( $rows, $can_manage, $default_sort, $default_direction, $people );
        }
        $this->section_close( $admin );
        echo '</div>';

        echo '</main></div>';
    }

    public function about_page() {
        echo '<div class="pwl-ui"><main class="pwl-ui-page">';
        echo '<header class="pwl-ui-header"><div><span class="pwl-ui-eyebrow">RECURSOS CORPORATIVOS · PORTARIA</span><h1 class="pwl-ui-title">Sobre Encomendas</h1><p class="pwl-ui-description">Informações do módulo e histórico de atualizações.</p></div></header>';
        $this->render_admin_tabs( 'about' );
        $this->section_open( true, 'PWL Encomendas', 'Registro, avisos e controle de retirada de encomendas recebidas na Portaria.' );
        echo '<div class="pwl-encomendas-about-grid"><div><span class="pwl-ui-label">Versão</span><strong>' . esc_html( self::VERSION ) . '</strong></div><div><span class="pwl-ui-label">Área</span><strong>Recursos Corporativos · Portaria</strong></div><div><span class="pwl-ui-label">Status</span><strong>Em produção</strong></div></div>';
        echo '<p>Colaboradores, e-mail e celular são consultados na Base Universal do PWL Core. O módulo armazena encomendas e eventos de auditoria.</p>';
        echo '<details class="pwl-encomendas-changelog"><summary>Changelog · histórico de mudanças</summary><div class="pwl-encomendas-changelog-content">';
        $changelog_path = plugin_dir_path( __FILE__ ) . 'CHANGELOG.md';
        $changelog = is_readable( $changelog_path ) ? file_get_contents( $changelog_path ) : '';
        $in_version = false;
        $list_open = false;
        foreach ( preg_split( '/\r?\n/', (string) $changelog ) as $line ) {
            if ( preg_match( '/^##\s+(.+)$/', trim( $line ), $match ) ) {
                if ( $list_open ) { echo '</ul>'; $list_open = false; }
                if ( $in_version ) { echo '</details>'; }
                echo '<details><summary>Versão ' . esc_html( trim( $match[1] ) ) . '</summary><ul>';
                $in_version = true;
                $list_open = true;
            } elseif ( $in_version && preg_match( '/^\s*-\s+(.+)$/', $line, $match ) ) {
                $entry = esc_html( trim( $match[1] ) );
                $entry = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $entry );
                $entry = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $entry );
                echo '<li>' . $entry . '</li>';
            }
        }
        if ( $list_open ) { echo '</ul>'; }
        if ( $in_version ) { echo '</details>'; }
        echo '</div></details>';
        $this->section_close( true );
        echo '</main></div>';
    }

    private function render_admin_table( $rows, $can_manage, $default_sort, $default_direction, $people ) {
        $can_delete = $this->can_delete_packages();
        if ( $can_delete ) {
            echo '<form id="pwl-encomendas-delete-form" class="pwl-encomendas-bulk-delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="pwl_encomendas_delete_bulk">';
            wp_nonce_field( 'pwl_encomendas_delete_bulk' );
            echo '<button class="pwl-ui-button pwl-ui-button--danger" type="button" data-pwl-delete-open disabled>Excluir selecionadas</button>';
            echo '<span data-pwl-delete-count aria-live="polite">Nenhuma encomenda selecionada.</span>';
            echo '<div class="pwl-encomendas-delete-confirm" data-pwl-delete-confirm hidden><label class="pwl-ui-field"><span class="pwl-ui-label">Digite EXCLUIR para confirmar</span><input class="pwl-ui-control" type="text" name="delete_confirmation" autocomplete="off" pattern="EXCLUIR" required disabled></label><button class="pwl-ui-button pwl-ui-button--danger" type="submit">Confirmar exclusão</button><button class="pwl-ui-button" type="button" data-pwl-delete-cancel>Cancelar</button></div>';
            echo '</form>';
        }
        echo '<div class="pwl-ui-table-wrap"><table class="pwl-ui-table pwl-encomendas-table' . ( $can_delete ? ' pwl-encomendas-table--deletable' : '' ) . '" data-sort-key="' . esc_attr( $default_sort ) . '" data-sort-direction="' . esc_attr( $default_direction ) . '"><thead><tr>';
        if ( $can_delete ) {
            echo '<th><input type="checkbox" data-pwl-delete-all aria-label="Selecionar todas as encomendas desta lista"></th>';
        }
        $columns = array(
            array( 'Código interno', 'package_code' ),
            array( 'Data recebida', 'received' ),
            array( 'Colaborador', 'recipient' ),
            array( 'Remetente', 'sender' ),
            array( 'Rastreio', 'tracking' ),
            array( 'Embalagem', 'description' ),
            array( 'Status', 'status' ),
            array( 'Retirada em', 'pickup_at' ),
            array( 'Retirada por', 'pickup_by' ),
        );
        foreach ( $columns as $column ) {
            $current   = $default_sort === $column[1] ? 'descending' : 'none';
            $indicator = $default_sort === $column[1] ? '↓' : '↕';
            echo '<th aria-sort="' . esc_attr( $current ) . '"><button class="pwl-encomendas-sort" type="button" data-sort="' . esc_attr( $column[1] ) . '" aria-label="Ordenar por ' . esc_attr( $column[0] ) . '">' . esc_html( $column[0] ) . ' <span aria-hidden="true">' . esc_html( $indicator ) . '</span></button></th>';
        }
        echo '<th>Ações</th></tr></thead><tbody>';
        if ( $rows ) {
            foreach ( $rows as $row ) {
                if ( $can_delete ) {
                    echo '<tr><td><input type="checkbox" name="package_ids[]" value="' . esc_attr( absint( $row->id ) ) . '" form="pwl-encomendas-delete-form" data-pwl-delete-item aria-label="Selecionar encomenda de ' . esc_attr( $row->recipient_name ) . '"></td>';
                } else {
                    echo '<tr>';
                }
                $package_code = $row->package_code ?: $this->build_package_code( $row->id, $row->received_at );
                echo '<td data-sort-value="' . esc_attr( $package_code ) . '"><strong>' . esc_html( $package_code ) . '</strong></td>';
                echo '<td data-sort-value="' . esc_attr( $row->received_at ) . '">' . esc_html( mysql2date( 'd/m/Y H:i', $row->received_at ) ) . '</td>';
                echo '<td data-sort-value="' . esc_attr( $row->recipient_name ) . '"><strong>' . esc_html( $row->recipient_name ) . '</strong></td>';
                echo '<td data-sort-value="' . esc_attr( $row->sender ) . '">' . esc_html( $row->sender ?: '—' ) . '</td>';
                echo '<td data-sort-value="' . esc_attr( $row->tracking_code ) . '">' . esc_html( $row->tracking_code ?: '—' ) . '</td>';
                echo '<td data-sort-value="' . esc_attr( $row->description ) . '">' . esc_html( $row->description ?: '—' ) . '</td><td data-sort-value="' . esc_attr( $row->status ) . '">';
                echo $this->status_badge( $row->status, 'awaiting_pickup' === $row->status ? 'Aguardando retirada' : 'Retirada', true );
                echo '</td><td data-sort-value="' . esc_attr( $row->picked_up_at ) . '">' . ( $row->picked_up_at ? esc_html( mysql2date( 'd/m/Y H:i', $row->picked_up_at ) ) : '—' ) . '</td>';
                echo '<td data-sort-value="' . esc_attr( $row->picked_up_by_name ) . '">' . esc_html( $row->picked_up_by_name ?: '—' ) . '</td><td><div class="pwl-encomendas-table-actions">';
                if ( $can_manage && 'awaiting_pickup' === $row->status ) {
                    $this->render_action_form( 'pwl_encomendas_pickup', absint( $row->id ), 'Registrar retirada', true, $people );
                }
                if ( $can_manage && 'awaiting_pickup' === $row->status ) {
                    $this->render_action_form( 'pwl_encomendas_retry', absint( $row->id ), 'Reenviar avisos', true );
                }
                echo '</div></td></tr>';
            }
        } else {
            echo '<tr><td colspan="' . esc_attr( $can_delete ? 11 : 10 ) . '">Nenhuma encomenda encontrada.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function render_package_cards( $rows, $people, $can_manage ) {
        $people_by_id = array();
        foreach ( $people as $person ) {
            if ( isset( $person['id'] ) ) {
                $people_by_id[ absint( $person['id'] ) ] = $person;
            }
        }

        if ( ! $rows ) {
            echo '<div class="pwl-alert pwl-alert--info"><div class="pwl-alert__content">Nenhuma encomenda encontrada.</div></div>';
            return;
        }

        global $wpdb;
        $carrier_logo_urls = array();
        $carrier_logo_records = $wpdb->get_results( "SELECT name, logo_attachment_id FROM {$this->carriers}" );
        foreach ( (array) $carrier_logo_records as $carrier_record ) {
            $attachment_id = absint( $carrier_record->logo_attachment_id );
            if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
                $attachment_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
                if ( $attachment_url ) {
                    $carrier_logo_urls[ sanitize_title( $carrier_record->name ) ] = $attachment_url;
                }
            }
        }

        $notice_by_package = array();
        $package_ids = array_values( array_filter( array_map( function ( $row ) { return absint( $row->id ); }, (array) $rows ) ) );
        if ( $can_manage && $package_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $package_ids ), '%d' ) );
            $linked_notices = $wpdb->get_results( $wpdb->prepare( "SELECT package_id, passphrase_cipher, authorized_person, author_name, created_at, status FROM {$this->notices} WHERE package_id IN ({$placeholders})", $package_ids ) );
            foreach ( (array) $linked_notices as $linked_notice ) {
                $notice_by_package[ absint( $linked_notice->package_id ) ] = $linked_notice;
            }
        }

        echo '<div class="pwl-encomendas-card-grid">';
        foreach ( $rows as $row ) {
            $person       = $people_by_id[ absint( $row->recipient_person_id ) ] ?? array();
            $initials     = '';
            $name_parts   = preg_split( '/\s+/u', trim( $row->recipient_name ) );
            if ( ! empty( $name_parts[0] ) ) {
                $initials .= function_exists( 'mb_substr' ) ? mb_substr( $name_parts[0], 0, 1 ) : substr( $name_parts[0], 0, 1 );
            }
            if ( count( $name_parts ) > 1 ) {
                $initials .= function_exists( 'mb_substr' ) ? mb_substr( end( $name_parts ), 0, 1 ) : substr( end( $name_parts ), 0, 1 );
            }
            if ( function_exists( 'mb_strtoupper' ) ) {
                $initials = mb_strtoupper( $initials );
            } else {
                $initials = strtoupper( $initials );
            }

            $badge_class = 'awaiting_pickup' === $row->status ? 'warning' : 'success';
            $status_text = 'awaiting_pickup' === $row->status ? 'Aguardando retirada' : 'Retirada';
            $extension = sanitize_text_field( $person['extension'] ?? '' );
            $photo_url = ! empty( $row->recipient_person_id ) && function_exists( 'pwl_get_person_photo_url' )
                ? pwl_get_person_photo_url( absint( $row->recipient_person_id ), 'thumbnail' )
                : '';

            $carrier_logo_url = $carrier_logo_urls[ sanitize_title( (string) $row->sender ) ] ?? '';
            if ( ! $carrier_logo_url ) {
                $carrier_logo_file = $this->carrier_logo_file( (string) $row->sender );
                $carrier_logo_url = $carrier_logo_file ? plugins_url( 'assets/images/carriers/' . $carrier_logo_file, __FILE__ ) : '';
            }

            echo '<article class="pwl-card pwl-encomendas-package-card" data-package-id="' . esc_attr( absint( $row->id ) ) . '"><div class="pwl-card__body">';
            echo '<div class="pwl-encomendas-card-top"><span class="pwl-badge pwl-badge--' . esc_attr( $badge_class ) . '">' . esc_html( $status_text ) . '</span>';
            if ( $can_manage && 'awaiting_pickup' === $row->status ) {
                $this->render_action_form( 'pwl_encomendas_retry', absint( $row->id ), 'Reenviar avisos' );
            }
            echo '</div>';
            echo '<div class="pwl-encomendas-recipient"><span class="pwl-encomendas-avatar" aria-hidden="true">';
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="" loading="lazy" decoding="async">';
            } else {
                echo esc_html( $initials );
            }
            echo '</span><div class="pwl-encomendas-recipient-copy"><strong>' . esc_html( $row->recipient_name ) . '</strong><span>Colaborador' . ( $extension ? ': Ramal ' . esc_html( $extension ) : '' ) . '</span></div>';
            if ( $carrier_logo_url ) {
                echo '<span class="pwl-encomendas-carrier-badge" aria-hidden="true"><img src="' . esc_url( $carrier_logo_url ) . '" alt="" loading="lazy" decoding="async"></span>';
            }
            echo '</div>';
            echo '<div class="pwl-encomendas-package-detail"><div class="pwl-encomendas-detail-line pwl-encomendas-detail-line--received"><span>Recebido em:</span><strong>' . esc_html( mysql2date( 'd/m/Y H:i', $row->received_at ) ) . '</strong></div><div class="pwl-encomendas-detail-line pwl-encomendas-detail-line--sender"><span>Remetente: <strong>' . esc_html( $row->sender ?: 'Não informado' ) . '</strong></span></div>';
            echo '<div class="pwl-encomendas-detail-line"><span>Código interno da Encomenda: <strong class="pwl-encomendas-tracking">' . esc_html( $row->package_code ?: $this->build_package_code( $row->id, $row->received_at ) ) . '</strong></span></div>';
            if ( $row->tracking_code ) {
                echo '<div class="pwl-encomendas-detail-line"><span>Código de rastreio: <strong class="pwl-encomendas-tracking">' . esc_html( $row->tracking_code ) . '</strong></span></div>';
            }
            if ( $row->description ) {
                $description = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $row->description, 'UTF-8' ) : strtoupper( $row->description );
                echo '<div class="pwl-encomendas-detail-line"><span>Embalagem: <strong class="pwl-encomendas-package-description">' . esc_html( $description ) . '</strong></span></div>';
            }
            $linked_notice = $notice_by_package[ absint( $row->id ) ] ?? null;
            if ( $can_manage && $linked_notice ) {
                echo '<div class="pwl-encomendas-detail-line"><span>Aviso antecipado registrado por: <strong>' . esc_html( $linked_notice->author_name ) . '</strong></span></div>';
                if ( 'completed' === $linked_notice->status ) {
                    echo '<div class="pwl-encomendas-notice-secret-used">Palavra-passe removida após a retirada.</div>';
                } else {
                    $package_passphrase = $this->decrypt_notice_passphrase( $linked_notice->passphrase_cipher );
                    if ( $package_passphrase ) {
                        echo '<details class="pwl-encomendas-notice-secret"><summary>Palavra-passe informada</summary><code>' . esc_html( $package_passphrase ) . '</code></details>';
                    }
                }
                if ( $linked_notice->authorized_person ) {
                    echo '<div class="pwl-encomendas-detail-line"><span>Autorizado a retirar: <strong>' . esc_html( $linked_notice->authorized_person ) . '</strong></span></div>';
                }
            }
            echo '</div><div class="pwl-encomendas-channels"><span>Portal: ' . esc_html( $this->channel_label( $row->portal_status ) ) . '</span><span>E-mail: ' . esc_html( $this->channel_label( $row->email_status ) ) . '</span><span>WhatsApp: ' . esc_html( $this->channel_label( $row->whatsapp_status ) ) . '</span><span>Teams: ' . esc_html( $this->channel_label( $row->teams_status ) ) . '</span></div>';
            $operator = ! empty( $row->received_by ) ? get_userdata( absint( $row->received_by ) ) : false;
            $operator_name = $operator && $operator->display_name ? $operator->display_name : 'Usuário não identificado';
            echo '<div class="pwl-encomendas-operator"><strong>Operador:</strong> ' . esc_html( $operator_name ) . '</div>';
            if ( 'picked_up' === $row->status ) {
                $delivery_operator = ! empty( $row->picked_up_by_user ) ? get_userdata( absint( $row->picked_up_by_user ) ) : false;
                $delivery_operator_name = $delivery_operator && $delivery_operator->display_name ? $delivery_operator->display_name : 'Usuário não identificado';
                echo '<div class="pwl-encomendas-pickup-note">Retirada em ' . esc_html( $row->picked_up_at ? mysql2date( 'd/m/Y H:i', $row->picked_up_at ) : '—' ) . '<br>Entregue por: <strong>' . esc_html( $delivery_operator_name ) . '</strong><br>Retirado por: ' . esc_html( $row->picked_up_by_name ?: 'Não informado' ) . '</div>';
            }
            echo '<div class="pwl-encomendas-card-actions">';
            if ( $can_manage && 'awaiting_pickup' === $row->status ) {
                $this->render_action_form( 'pwl_encomendas_pickup', absint( $row->id ), 'Registrar retirada', false, $people );
            }
            echo '</div>';
            echo '</div></article>';
        }
        echo '</div>';
    }

    private function section_open( $admin, $title, $description, $class = '' ) {
        $section_class = $class ? ' ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', trim( $class ) ) ) ) : '';
        if ( $admin ) {
            echo '<section class="pwl-ui-card' . esc_attr( $section_class ) . '"><header class="pwl-ui-card-header"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $description ) . '</p></header><div class="pwl-ui-card-body">';
        } else {
            $registration_icon = in_array( 'pwl-encomendas-registration-card', preg_split( '/\s+/', trim( $class ) ), true ) ? '<span class="cm-stitch-modal-package-icon" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 19 29 5l29 13-23 16L5 19Z"/><path d="M5 19v29l30 13V34M58 18v30L35 61"/><path d="M29 5v23M5 19l-3 20 25 11 8-16M35 34l8 17 19-22-4-11"/></svg></span>' : '';
            if ( in_array( 'pwl-encomendas-notice-form-card', preg_split( '/\s+/', trim( $class ) ), true ) ) {
                $registration_icon = '<span class="cm-stitch-modal-package-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg></span>';
            }
            echo '<section class="pwl-card' . esc_attr( $section_class ) . '"><header class="pwl-card__header">' . $registration_icon . '<div><h2 class="pwl-card__title">' . esc_html( $title ) . '</h2><p class="pwl-card__description">' . esc_html( $description ) . '</p></div></header><div class="pwl-card__body">';
        }
    }

    private function carrier_logo_file( $carrier_name ) {
        $carrier_name = trim( sanitize_text_field( $carrier_name ) );
        if ( '' === $carrier_name ) {
            return '';
        }

        $logos = array(
            array( 'pattern' => '/aliexpress|ali\s*express/i', 'file' => 'aliexpress.svg' ),
            array( 'pattern' => '/shopee/i', 'file' => 'shopee.svg' ),
            array( 'pattern' => '/mercado\s*livre|mercado\s*libre|mercadolivre|\bmeli\b/i', 'file' => 'mercado-livre.svg' ),
            array( 'pattern' => '/amazon/i', 'file' => 'amazon.svg' ),
            array( 'pattern' => '/magalu|magazine\s*luiza/i', 'file' => 'magalu.svg' ),
            array( 'pattern' => '/shein/i', 'file' => 'shein.svg' ),
            array( 'pattern' => '/dhl/i', 'file' => 'dhl.svg' ),
            array( 'pattern' => '/correios/i', 'file' => 'correios.svg' ),
            array( 'pattern' => '/jadlog/i', 'file' => 'jadlog.svg' ),
        );

        foreach ( $logos as $logo ) {
            if ( preg_match( $logo['pattern'], $carrier_name ) ) {
                return $logo['file'];
            }
        }

        return '';
    }

    private function section_close( $admin ) {
        echo '</div></section>';
    }

    private function render_advance_notice_form( $person, $carriers, $admin = false ) {
        echo '<form class="pwl-encomendas-advance-notice-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_notice_create">';
        if ( $admin ) {
            echo '<input type="hidden" name="pwl_encomendas_return" value="admin_register_notice">';
        }
        wp_nonce_field( 'pwl_encomendas_notice_create' );
        echo '<div class="pwl-encomendas-advance-notice-grid">';
        echo '<label class="pwl-field pwl-ui-field"><span class="pwl-field__label pwl-ui-label">Remetente ou transportadora</span><select class="pwl-select pwl-ui-control" name="sender" required><option value="">Selecione</option>';
        foreach ( $carriers as $carrier ) {
            echo '<option value="' . esc_attr( $carrier->name ) . '">' . esc_html( $carrier->name ) . '</option>';
        }
        if ( ! $carriers ) {
            echo '<option value="" disabled>Nenhuma transportadora ativa cadastrada</option>';
        }
        echo '</select></label>';
        echo '<label class="pwl-field pwl-ui-field"><span class="pwl-field__label pwl-ui-label">Previsão de chegada</span><input class="pwl-input pwl-ui-control" type="date" name="expected_at" min="' . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
        echo '<label class="pwl-field pwl-ui-field"><span class="pwl-field__label pwl-ui-label">Código de rastreio (opcional)</span><input class="pwl-input pwl-ui-control" name="tracking_code" maxlength="120" placeholder="Ex.: BR123456789BR"></label>';
        echo '<label class="pwl-field pwl-ui-field"><span class="pwl-field__label pwl-ui-label">Quem poderá retirar (opcional)</span><input class="pwl-input pwl-ui-control" name="authorized_person" maxlength="190" placeholder="Nome de outro colaborador autorizado"></label>';
        echo '<label class="pwl-field pwl-ui-field pwl-encomendas-advance-notice-description"><span class="pwl-field__label pwl-ui-label">Detalhes da encomenda</span><textarea class="pwl-input pwl-ui-control" name="description" maxlength="500" rows="3" required placeholder="Ex.: caixa pequena, envelope ou material de escritório"></textarea></label>';
        echo '<label class="pwl-field pwl-ui-field pwl-encomendas-advance-notice-password"><span class="pwl-field__label pwl-ui-label">Palavra-passe</span><span class="pwl-encomendas-password-control"><input class="pwl-input pwl-ui-control" type="password" name="passphrase" minlength="4" maxlength="80" autocomplete="new-password" required placeholder="Mínimo de 4 caracteres"><button class="pwl-button pwl-button--secondary pwl-ui-button pwl-ui-button--secondary" type="button" data-pwl-password-generate>Gerar</button><button class="pwl-button pwl-button--secondary pwl-ui-button pwl-ui-button--secondary" type="button" data-pwl-password-toggle>Mostrar</button></span><small class="pwl-field__hint pwl-ui-hint">A palavra-passe será exibida somente à Portaria e a você. Não será enviada em e-mail ou notificação.</small></label>';
        echo '<div class="pwl-encomendas-advance-notice-submit"><button class="pwl-button pwl-button--primary pwl-ui-button pwl-ui-button--primary" type="submit">Enviar aviso para a Portaria</button></div>';
        echo '</div></form>';
    }

    private function render_advance_notice_cards( $notices, $management = false ) {
        $notices = (array) $notices;
        if ( ! $notices ) {
            echo '<div class="pwl-alert pwl-alert--info"><div class="pwl-alert__content">Você ainda não enviou avisos antecipados.</div></div>';
            return;
        }
        global $wpdb;
        $notice_package_ids = array_values( array_filter( array_map( function ( $notice ) { return absint( $notice->package_id ); }, $notices ) ) );
        $notice_packages = array();
        if ( $notice_package_ids ) {
            $notice_package_placeholders = implode( ',', array_fill( 0, count( $notice_package_ids ), '%d' ) );
            $package_records = $wpdb->get_results( $wpdb->prepare( "SELECT id, package_code, received_at FROM {$this->table} WHERE id IN ({$notice_package_placeholders})", $notice_package_ids ) );
            foreach ( (array) $package_records as $package_record ) {
                $notice_packages[ absint( $package_record->id ) ] = $package_record;
            }
        }
        $labels = array( 'pending' => 'Aguardando chegada', 'linked' => 'Encomenda recebida', 'completed' => 'Retirada concluída', 'cancelled' => 'Cancelado' );
        echo '<div class="pwl-encomendas-advance-notice-list">';
        foreach ( $notices as $notice ) {
            $status_label = $labels[ $notice->status ] ?? 'Em acompanhamento';
            $passphrase = 'completed' === $notice->status ? '' : $this->decrypt_notice_passphrase( $notice->passphrase_cipher );
            echo '<article class="pwl-encomendas-advance-notice-card"><div class="pwl-encomendas-advance-notice-card__top"><strong>' . esc_html( $notice->sender ) . '</strong><span class="pwl-badge' . ( 'completed' === $notice->status ? ' pwl-badge--success' : ' pwl-badge--warning' ) . '">' . esc_html( $status_label ) . '</span></div>';
            echo '<p>' . esc_html( $notice->description ) . '</p>';
            if ( $notice->expected_at ) {
                echo '<div class="pwl-encomendas-advance-notice-meta"><span>Previsão: <strong>' . esc_html( mysql2date( 'd/m/Y', $notice->expected_at ) ) . '</strong></span></div>';
            }
            if ( $notice->tracking_code ) {
                echo '<div class="pwl-encomendas-advance-notice-meta"><span>Rastreio: <strong>' . esc_html( $notice->tracking_code ) . '</strong></span></div>';
            }
            if ( $notice->authorized_person ) {
                echo '<div class="pwl-encomendas-advance-notice-meta"><span>Autorizado a retirar: <strong>' . esc_html( $notice->authorized_person ) . '</strong></span></div>';
            }
            if ( $notice->package_id ) {
                $package = $notice_packages[ absint( $notice->package_id ) ] ?? null;
                if ( $package ) {
                    echo '<div class="pwl-encomendas-advance-notice-meta"><span>Código interno: <strong>' . esc_html( $package->package_code ?: $this->build_package_code( $package->id, $package->received_at ) ) . '</strong></span></div>';
                }
            }
            if ( $passphrase ) {
                echo '<details class="pwl-encomendas-notice-secret"><summary>Ver palavra-passe</summary><code>' . esc_html( $passphrase ) . '</code></details>';
            } elseif ( 'completed' === $notice->status ) {
                echo '<div class="pwl-encomendas-notice-secret-used">Palavra-passe removida após a retirada.</div>';
            }
            echo '<div class="pwl-encomendas-advance-notice-meta"><span>Registrado por: <strong>' . esc_html( $notice->author_name ) . '</strong></span><span>' . esc_html( mysql2date( 'd/m/Y H:i', $notice->created_at ) ) . '</span></div>';
            if ( ! $management && 'pending' === $notice->status ) {
                echo '<form class="pwl-encomendas-notice-cancel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pwl_encomendas_notice_cancel"><input type="hidden" name="notice_id" value="' . esc_attr( absint( $notice->id ) ) . '">';
                wp_nonce_field( 'pwl_encomendas_notice_cancel_' . absint( $notice->id ) );
                echo '<button class="pwl-button pwl-button--secondary" type="submit">Cancelar aviso</button></form>';
            }
            echo '</article>';
        }
        echo '</div>';
    }

    private function render_advance_notice_queue( $notices ) {
        global $wpdb;
        $pending = array_values( array_filter( (array) $notices, function ( $notice ) { return 'pending' === $notice->status; } ) );
        if ( ! $pending ) {
            echo '<p class="pwl-encomendas-empty-management-text">Não há avisos antecipados aguardando encomenda.</p>';
            return;
        }
        echo '<div class="pwl-encomendas-advance-notice-list pwl-encomendas-advance-notice-list--management">';
        foreach ( $pending as $notice ) {
            $passphrase = $this->decrypt_notice_passphrase( $notice->passphrase_cipher );
            $person = function_exists( 'pwl_get_person' ) ? pwl_get_person( absint( $notice->person_id ) ) : array();
            $person_name = ! empty( $person['display_name'] ) ? $person['display_name'] : ( $person['full_name'] ?? $notice->author_name );
            $photo_url = function_exists( 'pwl_get_person_photo_url' ) ? pwl_get_person_photo_url( absint( $notice->person_id ), 'thumbnail' ) : '';
            $person_initials = '';
            foreach ( preg_split( '/\s+/', trim( (string) $person_name ) ) as $name_part ) {
                if ( '' !== $name_part ) {
                    $person_initials .= function_exists( 'mb_substr' ) ? mb_substr( $name_part, 0, 1 ) : substr( $name_part, 0, 1 );
                }
                if ( strlen( $person_initials ) >= 2 ) {
                    break;
                }
            }
            $carrier = $wpdb->get_row( $wpdb->prepare( "SELECT logo_attachment_id FROM {$this->carriers} WHERE name = %s LIMIT 1", $notice->sender ) );
            $carrier_logo_url = $carrier && absint( $carrier->logo_attachment_id ) && wp_attachment_is_image( absint( $carrier->logo_attachment_id ) ) ? wp_get_attachment_image_url( absint( $carrier->logo_attachment_id ), 'medium' ) : '';
            if ( ! $carrier_logo_url ) {
                $carrier_logo_file = $this->carrier_logo_file( $notice->sender );
                $carrier_logo_url = $carrier_logo_file ? plugins_url( 'assets/images/carriers/' . $carrier_logo_file, __FILE__ ) : '';
            }
            echo '<article class="pwl-encomendas-advance-notice-card" data-pwl-advance-notice="' . esc_attr( absint( $notice->id ) ) . '" data-person-id="' . esc_attr( absint( $notice->person_id ) ) . '" data-sender="' . esc_attr( $notice->sender ) . '" data-carrier-logo="' . esc_url( $carrier_logo_url ) . '" data-tracking="' . esc_attr( $notice->tracking_code ) . '" data-description="' . esc_attr( $notice->description ) . '" data-expected="' . esc_attr( $notice->expected_at ) . '" data-authorized="' . esc_attr( $notice->authorized_person ) . '" data-passphrase="' . esc_attr( $passphrase ) . '"><div class="pwl-encomendas-notice-layout"><span class="pwl-encomendas-notice-avatar">';
            if ( $photo_url ) {
                echo '<img src="' . esc_url( $photo_url ) . '" alt="" loading="lazy" decoding="async">';
            } else {
                echo esc_html( strtoupper( $person_initials ?: '?' ) );
            }
            echo '</span><div class="pwl-encomendas-notice-content"><strong class="pwl-encomendas-notice-person">' . esc_html( $person_name ) . '</strong><div class="pwl-encomendas-notice-data"><div class="pwl-encomendas-notice-line"><span><strong>Transportadora:</strong> ' . esc_html( $notice->sender ) . '</span><span><strong>Código:</strong> ' . esc_html( $notice->tracking_code ?: 'Não informado' ) . '</span></div><div class="pwl-encomendas-notice-line"><span><strong>Descrição:</strong> ' . esc_html( $notice->description ) . '</span><span><strong>Palavra passe:</strong> ' . esc_html( $passphrase ?: 'Não informada' ) . '</span></div></div></div><button class="pwl-button pwl-button--primary" type="button" data-pwl-promote-notice>Promover encomenda</button></div></article>';
        }
        echo '</div>';
    }

    private function render_advance_notice_promotion_modal() {
        global $wpdb;
        $packagings = $wpdb->get_results( "SELECT name FROM {$this->packagings} WHERE active = 1 ORDER BY CASE WHEN name LIKE '% Pequena' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Pequena')) WHEN name LIKE '% Pequeno' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Pequeno')) WHEN name LIKE '% Média' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Média')) WHEN name LIKE '% Médio' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Médio')) WHEN name LIKE '% Grande' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Grande')) ELSE name END ASC, CASE WHEN name LIKE '% Pequena' OR name LIKE '% Pequeno' THEN 1 WHEN name LIKE '% Média' OR name LIKE '% Médio' THEN 2 WHEN name LIKE '% Grande' THEN 3 ELSE 4 END ASC, name ASC, id ASC" );
        echo '<div class="pwl-encomendas-promote-modal" data-pwl-promote-modal hidden><div class="pwl-encomendas-promote-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pwl-promote-title"><h2 id="pwl-promote-title">Promover aviso em encomenda</h2><p>Confira os dados e selecione a embalagem:</p><div class="pwl-encomendas-promote-carrier"><img src="" alt="" data-pwl-promote-logo hidden></div><dl><dt>Colaborador</dt><dd data-pwl-promote-person></dd><dt>Remetente</dt><dd data-pwl-promote-sender></dd><dt>Descrição informada</dt><dd data-pwl-promote-description></dd><dt>Código de rastreio</dt><dd data-pwl-promote-tracking></dd><dt>Previsão</dt><dd data-pwl-promote-expected></dd><dt>Autorizado a retirar</dt><dd data-pwl-promote-authorized></dd><dt>Palavra-passe</dt><dd data-pwl-promote-passphrase></dd></dl><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-pwl-package-create><input type="hidden" name="action" value="pwl_encomendas_create"><input type="hidden" name="submission_token" value="' . esc_attr( wp_generate_uuid4() ) . '"><input type="hidden" name="recipient_person_id" data-pwl-promote-recipient><input type="hidden" name="sender" data-pwl-promote-sender-input><input type="hidden" name="tracking_code" data-pwl-promote-tracking-input><input type="hidden" name="notes" data-pwl-promote-notes-input><input type="hidden" name="advance_notice_id" data-pwl-promote-notice-id>';
        wp_nonce_field( 'pwl_encomendas_create' );
        echo '<label class="pwl-field"><span class="pwl-field__label">Embalagem</span><select class="pwl-select" name="description" data-pwl-promote-packaging required><option value="">Selecione uma embalagem</option>';
        foreach ( (array) $packagings as $packaging ) { echo '<option value="' . esc_attr( $packaging->name ) . '">' . esc_html( $packaging->name ) . '</option>'; }
        echo '</select></label><p class="pwl-encomendas-promote-modal__question">Tem certeza que deseja promover o aviso em Encomenda?</p><div class="pwl-encomendas-promote-modal__actions"><button class="pwl-button pwl-button--primary" type="submit">SIM, PROMOVER</button><button class="pwl-button pwl-button--secondary" type="button" data-pwl-promote-cancel>NÃO, CANCELAR</button></div></form></div></div>';
    }

    public function handle_notice_create() {
        $admin_register_notice = isset( $_POST['pwl_encomendas_return'] ) && 'admin_register_notice' === sanitize_key( wp_unslash( $_POST['pwl_encomendas_return'] ) );
        if ( ! is_user_logged_in() || ( ! $this->can( self::CAP_VIEW ) && ! $this->can( self::CAP_MANAGE ) && ! $this->can( self::CAP_HYBRID ) && ( ! $admin_register_notice || ! $this->can( self::CAP_ADMIN ) ) ) ) {
            wp_die( esc_html__( 'Você não possui permissão para enviar avisos à Portaria.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pwl_encomendas_notice_create' );
        global $wpdb;
        $user_id = get_current_user_id();
        $person = $this->current_person_for_user( $user_id );
        $sender = sanitize_text_field( wp_unslash( $_POST['sender'] ?? '' ) );
        $description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
        $passphrase = sanitize_text_field( wp_unslash( $_POST['passphrase'] ?? '' ) );
        $expected_at = sanitize_text_field( wp_unslash( $_POST['expected_at'] ?? '' ) );
        $tracking = sanitize_text_field( wp_unslash( $_POST['tracking_code'] ?? '' ) );
        $authorized = sanitize_text_field( wp_unslash( $_POST['authorized_person'] ?? '' ) );
        $carrier_exists = $sender ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->carriers} WHERE name = %s AND active = 1 LIMIT 1", $sender ) ) : false;
        $valid_date = '' === $expected_at;
        if ( ! $valid_date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expected_at ) && $expected_at >= current_time( 'Y-m-d' ) ) {
            $date_object = DateTimeImmutable::createFromFormat( '!Y-m-d', $expected_at, wp_timezone() );
            $valid_date = $date_object && $date_object->format( 'Y-m-d' ) === $expected_at;
        }
        $cipher = $this->encrypt_notice_passphrase( $passphrase );
        if ( empty( $person ) || 'active' !== ( $person['person_status'] ?? '' ) || ! $carrier_exists || '' === $description || strlen( $passphrase ) < 4 || strlen( $passphrase ) > 80 || ! $valid_date || ! $cipher ) {
            $this->redirect_notice_create_result( 'error' );
        }
        $wp_user = get_userdata( $user_id );
        $author_name = $wp_user && $wp_user->display_name ? sanitize_text_field( $wp_user->display_name ) : sanitize_text_field( $person['display_name'] ?: $person['full_name'] );
        $now = current_time( 'mysql' );
        $ok = $wpdb->insert( $this->notices, array(
            'user_id' => $user_id,
            'person_id' => absint( $person['id'] ),
            'author_name' => $author_name,
            'sender' => $sender,
            'expected_at' => $expected_at ?: null,
            'tracking_code' => $tracking,
            'description' => $description,
            'authorized_person' => $authorized,
            'passphrase_cipher' => $cipher,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ), array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ) );
        if ( $ok ) {
            $this->notify_management_of_advance_notice( $author_name, $sender );
        }
        $this->redirect_notice_create_result( $ok ? 'notice_created' : 'error' );
    }

    public function handle_notice_cancel() {
        $id = absint( $_POST['notice_id'] ?? 0 );
        if ( ! is_user_logged_in() || ( ! $this->can( self::CAP_VIEW ) && ! $this->can( self::CAP_MANAGE ) && ! $this->can( self::CAP_HYBRID ) ) ) {
            wp_die( esc_html__( 'Você não possui permissão para cancelar este aviso.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pwl_encomendas_notice_cancel_' . $id );
        global $wpdb;
        $where = array( 'id' => $id, 'status' => 'pending' );
        $formats = array( '%d', '%s' );
        if ( ! $this->can( self::CAP_MANAGE ) ) {
            $where['user_id'] = get_current_user_id();
            $formats[] = '%d';
        }
        $updated = $wpdb->update( $this->notices, array( 'status' => 'cancelled', 'passphrase_cipher' => '', 'updated_at' => current_time( 'mysql' ) ), $where, array( '%s','%s','%s' ), $formats );
        $this->redirect( 1 === $updated ? 'notice_cancelled' : 'error' );
    }

    private function render_create_form( $people, $admin = false, $carriers = array(), $recipient_groups = array(), $advance_notices = array() ) {
        $field = $admin ? 'pwl-ui-field' : 'pwl-field';
        $label = $admin ? 'pwl-ui-label' : 'pwl-field__label';
        $control = $admin ? 'pwl-ui-control' : 'pwl-input';
        $select = $admin ? 'pwl-ui-control' : 'pwl-select';
        $textarea = 'pwl-ui-control';
        $form_layout = $admin ? ' class="pwl-ui-layout--form"' : ' class="pwl-encomendas-create-grid cm-stitch-form"';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-pwl-package-create><input type="hidden" name="action" value="pwl_encomendas_create"><input type="hidden" name="submission_token" value="' . esc_attr( wp_generate_uuid4() ) . '">';
        wp_nonce_field( 'pwl_encomendas_create' );
        echo '<div' . $form_layout . '>';
        echo '<label class="' . esc_attr( $field ) . ' cm-stitch-recipient"><span class="' . esc_attr( $label ) . '">Colaborador Destinatário * <small>Pesquise por nome ou ramal</small></span><select class="' . esc_attr( $select ) . '" name="recipient_person_id" required><option value="">Selecione um colaborador</option>';
        foreach ( $recipient_groups as $recipient_group ) {
            echo '<option value="group:' . esc_attr( absint( $recipient_group->id ) ) . '">' . esc_html( $recipient_group->name . ' · Grupo' ) . '</option>';
        }
        if ( $recipient_groups ) { echo '<option disabled>──────────</option>'; }
        $people = array_values( (array) $people );
        usort( $people, function ( $a, $b ) { return strnatcasecmp( remove_accents( $a['display_name'] ?: $a['full_name'] ), remove_accents( $b['display_name'] ?: $b['full_name'] ) ); } );
        foreach ( $people as $person ) {
            $linked = absint( $person['wp_user_id'] ?? 0 );
            if ( 'active' !== ( $person['person_status'] ?? '' ) ) { continue; }
            $name = $person['display_name'] ?: $person['full_name'];
            echo '<option value="person:' . esc_attr( absint( $person['id'] ) ) . '">' . esc_html( $name . ( ! $linked ? ' · sem acesso ao Portal' : '' ) ) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="' . esc_attr( $field ) . '"><span class="' . esc_attr( $label ) . '">Remetente ou Transportadora *</span><select class="' . esc_attr( $select ) . '" name="sender" required><option value="">Selecione uma transportadora</option>';
        foreach ( $carriers as $carrier ) {
            echo '<option value="' . esc_attr( $carrier->name ) . '">' . esc_html( $carrier->name ) . '</option>';
        }
        if ( ! $carriers ) {
            echo '<option value="" disabled>Nenhuma transportadora ativa cadastrada</option>';
        }
        echo '</select></label>';
        echo '<label class="' . esc_attr( $field ) . '"><span class="' . esc_attr( $label ) . '">Código de Rastreio / Identificador</span><input class="' . esc_attr( $control ) . '" name="tracking_code" maxlength="120"></label>';
        global $wpdb;
        $packagings = $wpdb->get_results( "SELECT name FROM {$this->packagings} WHERE active = 1 ORDER BY CASE WHEN name LIKE '% Pequena' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Pequena')) WHEN name LIKE '% Pequeno' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Pequeno')) WHEN name LIKE '% Média' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Média')) WHEN name LIKE '% Médio' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Médio')) WHEN name LIKE '% Grande' THEN LEFT(name, CHAR_LENGTH(name) - CHAR_LENGTH(' Grande')) ELSE name END ASC, CASE WHEN name LIKE '% Pequena' OR name LIKE '% Pequeno' THEN 1 WHEN name LIKE '% Média' OR name LIKE '% Médio' THEN 2 WHEN name LIKE '% Grande' THEN 3 ELSE 4 END ASC, name ASC, id ASC" );
        echo '<label class="' . esc_attr( $field ) . '"><span class="' . esc_attr( $label ) . '">Tipo de Embalagem *</span><select class="' . esc_attr( $select ) . '" name="description" required><option value="">Selecione uma embalagem</option>';
        foreach ( (array) $packagings as $packaging ) { echo '<option value="' . esc_attr( $packaging->name ) . '">' . esc_html( $packaging->name ) . '</option>'; }
        echo '</select></label>'; 
        if ( $admin ) {
            echo '<label class="' . esc_attr( $field ) . '"><span class="' . esc_attr( $label ) . '">Observação</span><textarea class="' . esc_attr( $textarea ) . '" name="notes" rows="3"></textarea></label>';
        } else {
            echo '<div class="pwl-encomendas-form-submit"><button class="pwl-button pwl-button--secondary cm-stitch-cancel" type="button" data-cm-cancel-registration>Cancelar</button><button class="pwl-button pwl-button--primary" type="submit">➤ &nbsp; Registrar e Enviar Avisos</button></div>';
        }
        echo '</div>';
        if ( $admin ) {
            echo '<p><button class="pwl-ui-button pwl-ui-button--primary" type="submit">Registrar e enviar avisos</button></p></form>';
        } else {
            echo '</form>';
        }
    }

    private function render_action_form( $action, $id, $label, $admin = false, $people = array() ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="package_id" value="' . esc_attr( $id ) . '">';
        wp_nonce_field( $action . '_' . $id );
        $primary_action = 'pwl_encomendas_pickup' === $action;
        $button_class = $admin
            ? 'pwl-ui-button' . ( $primary_action ? ' pwl-ui-button--primary' : '' )
            : 'pwl-button ' . ( $primary_action ? 'pwl-button--primary' : 'pwl-button--secondary' );
        $button_class .= ' pwl-encomendas-action-button';
        if ( 'pwl_encomendas_pickup' === $action ) {
            echo '<button class="' . esc_attr( $button_class ) . '" type="button" data-pwl-pickup-open>' . esc_html( strtoupper( $label ) ) . '</button><div class="pwl-encomendas-pickup-fields" data-pwl-pickup-fields hidden>';
            echo '<button class="' . esc_attr( $button_class ) . '" type="submit">Confirmar retirada</button>';
            if ( $admin ) {
                echo '<label class="pwl-ui-field"><span class="pwl-ui-label">Quem retirou</span><select class="pwl-ui-control" name="picked_up_person_id" data-pwl-pickup-person required><option value="" selected disabled hidden>Selecione quem retirou</option><option value="other">Outra pessoa</option>';
            } else {
                echo '<label class="pwl-field"><span class="pwl-field__label">Quem retirou</span><select class="pwl-select" name="picked_up_person_id" data-pwl-pickup-person required><option value="" selected disabled hidden>Selecione quem retirou</option><option value="other">Outra pessoa</option>';
            }
            foreach ( $people as $person ) {
                if ( 'active' !== ( $person['person_status'] ?? '' ) ) {
                    continue;
                }
                $person_name = $person['display_name'] ?: $person['full_name'];
                echo '<option value="' . esc_attr( absint( $person['id'] ) ) . '">' . esc_html( $person_name ) . '</option>';
            }
            echo '</select></label>';
            if ( $admin ) {
                echo '<label class="pwl-ui-field pwl-encomendas-pickup-other" hidden><span class="pwl-ui-label">Nome de quem retirou</span><input class="pwl-ui-control" name="picked_up_by_name" maxlength="190" disabled></label>';
            } else {
                echo '<label class="pwl-field pwl-encomendas-pickup-other" hidden><span class="pwl-field__label">Nome de quem retirou</span><input class="pwl-input" name="picked_up_by_name" maxlength="190" disabled></label>';
            }
            echo '</div></form>';
            return;
        }
        echo '<button class="' . esc_attr( $button_class ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
    }

    public function handle_create() {
        $this->guard_post( self::CAP_MANAGE, 'pwl_encomendas_create' );
        global $wpdb;
        $submission_token = sanitize_text_field( wp_unslash( $_POST['submission_token'] ?? '' ) );
        if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $submission_token ) ) {
            $this->redirect( 'error' );
        }
        $request_option = 'pwl_encomendas_req_' . hash( 'sha256', $submission_token );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $wpdb->esc_like( 'pwl_encomendas_req_' ) . '%', time() - DAY_IN_SECONDS ) );
        if ( ! add_option( $request_option, (string) time(), '', false ) ) {
            $this->redirect( 'duplicate' );
        }
        $recipient_value = sanitize_text_field( wp_unslash( $_POST['recipient_person_id'] ?? '' ) );
        $recipient_type = 'person';
        $group_id = 0;
        $person_id = 0;
        $person = null;
        $advance_notice_id = absint( $_POST['advance_notice_id'] ?? 0 );
        $advance_notice = null;
        if ( preg_match( '/^group:(\d+)$/', $recipient_value, $match ) ) {
            $recipient_type = 'group';
            $group_id = absint( $match[1] );
            $group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->groups} WHERE id = %d AND active = 1", $group_id ) );
            if ( ! $group ) { $this->redirect( 'error' ); }
            $recipient_name = sanitize_text_field( $group->name );
        } elseif ( preg_match( '/^person:(\d+)$/', $recipient_value, $match ) ) {
            $person_id = absint( $match[1] );
            $person = function_exists( 'pwl_get_person' ) ? pwl_get_person( $person_id ) : null;
            if ( ! $person || 'active' !== ( $person['person_status'] ?? '' ) ) { $this->redirect( 'error' ); }
            $recipient_name = sanitize_text_field( $person['display_name'] ?: $person['full_name'] );
        } else {
            $this->redirect( 'error' );
        }
        if ( $advance_notice_id ) {
            $advance_notice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->notices} WHERE id = %d AND status = 'pending'", $advance_notice_id ) );
            if ( ! $advance_notice || 'person' !== $recipient_type || absint( $advance_notice->person_id ) !== $person_id ) {
                $this->redirect( 'error' );
            }
        }
        $sender = sanitize_text_field( wp_unslash( $_POST['sender'] ?? '' ) );
        $packaging = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
        $carrier_exists = $sender ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->carriers} WHERE name = %s AND active = 1 LIMIT 1", $sender ) ) : false;
        $packaging_exists = $packaging ? $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->packagings} WHERE name = %s AND active = 1 LIMIT 1", $packaging ) ) : false;
        if ( ! $carrier_exists || ! $packaging_exists ) {
            $this->redirect( 'error' );
        }
        $user_id = $person ? absint( $person['wp_user_id'] ?? 0 ) : 0;
        $user = $user_id ? get_userdata( $user_id ) : false;
        $email = $person ? sanitize_email( $person['corporate_email'] ?? '' ) : '';
        $mobile = $person ? sanitize_text_field( $person['corporate_mobile'] ?? '' ) : '';
        $now = current_time( 'mysql' );
        $data = array(
            'recipient_type'      => $recipient_type,
            'recipient_group_id'  => $group_id,
            'recipient_person_id' => $person_id,
            'recipient_user_id'   => $user ? $user_id : 0,
            'recipient_name'      => $recipient_name,
            'recipient_email'     => is_email( $email ) ? $email : '',
            'recipient_mobile'    => $mobile,
            'recipient_unit'      => $person ? sanitize_text_field( $person['unit_id'] ?? '' ) : '',
            'sender'              => $sender,
            'tracking_code'       => sanitize_text_field( wp_unslash( $_POST['tracking_code'] ?? '' ) ),
            'description'         => $packaging,
            'notes'               => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
            'status'              => 'awaiting_pickup',
            'received_by'         => get_current_user_id(),
            'received_at'         => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        );
        $ok = $wpdb->insert( $this->table, $data, array( '%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s' ) );
        if ( ! $ok ) {
            $this->redirect( 'error' );
        }
        $package_id = absint( $wpdb->insert_id );
        if ( 'group' === $recipient_type ) {
            $group_people = $wpdb->get_col( $wpdb->prepare( "SELECT person_id FROM {$this->group_members} WHERE group_id = %d", $group_id ) );
            $members = array();
            foreach ( (array) $group_people as $member_person_id ) {
                $member = function_exists( 'pwl_get_person' ) ? pwl_get_person( absint( $member_person_id ) ) : null;
                if ( ! $member || 'active' !== ( $member['person_status'] ?? '' ) ) { continue; }
                $member_user_id = absint( $member['wp_user_id'] ?? 0 );
                $member_user = $member_user_id ? get_userdata( $member_user_id ) : false;
                $member_email = sanitize_email( $member['corporate_email'] ?? '' );
                $members[] = array(
                    'package_id' => $package_id,
                    'person_id' => absint( $member['id'] ),
                    'user_id' => $member_user ? $member_user_id : 0,
                    'person_name' => sanitize_text_field( $member['display_name'] ?: $member['full_name'] ),
                    'email' => is_email( $member_email ) ? $member_email : '',
                    'mobile' => sanitize_text_field( $member['corporate_mobile'] ?? '' ),
                    'portal_status' => 'pending', 'email_status' => 'pending', 'whatsapp_status' => 'pending', 'teams_status' => 'pending',
                    'created_at' => $now,
                );
            }
            if ( ! $members ) {
                $wpdb->delete( $this->table, array( 'id' => $package_id ), array( '%d' ) );
                $this->redirect( 'error' );
            }
            $snapshot_failed = false;
            foreach ( $members as $member_data ) {
                if ( false === $wpdb->insert( $this->package_recipients, $member_data, array( '%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s' ) ) ) {
                    $snapshot_failed = true;
                    break;
                }
            }
            if ( $snapshot_failed ) {
                $wpdb->delete( $this->package_recipients, array( 'package_id' => $package_id ), array( '%d' ) );
                $wpdb->delete( $this->table, array( 'id' => $package_id ), array( '%d' ) );
                $this->redirect( 'error' );
            }
        }
        $package_code = $this->next_package_code( $now );
        if ( ! $package_code || false === $wpdb->update( $this->table, array( 'package_code' => $package_code ), array( 'id' => $package_id ), array( '%s' ), array( '%d' ) ) ) {
            $wpdb->delete( $this->package_recipients, array( 'package_id' => $package_id ), array( '%d' ) );
            $wpdb->delete( $this->table, array( 'id' => $package_id ), array( '%d' ) );
            $this->redirect( 'error' );
        }
        if ( $advance_notice_id ) {
            $linked = $wpdb->update( $this->notices, array( 'status' => 'linked', 'package_id' => $package_id, 'linked_by' => get_current_user_id(), 'updated_at' => $now ), array( 'id' => $advance_notice_id, 'status' => 'pending', 'person_id' => $person_id ), array( '%s','%d','%d','%s' ), array( '%d','%s','%d' ) );
            if ( 1 !== $linked ) {
                $wpdb->delete( $this->package_recipients, array( 'package_id' => $package_id ), array( '%d' ) );
                $wpdb->delete( $this->table, array( 'id' => $package_id ), array( '%d' ) );
                $this->redirect( 'error' );
            }
        }
        $this->log_event( $package_id, 'received', '', 'success', 'Encomenda registrada na Portaria. Código: ' . $package_code . '.' );
        $this->send_pending_channels( $package_id );
        $this->redirect( 'created' );
    }

    public function handle_pickup() {
        $id = absint( $_POST['package_id'] ?? 0 );
        $this->guard_post( self::CAP_MANAGE, 'pwl_encomendas_pickup_' . $id );
        global $wpdb;
        $row = $this->get_package( $id );
        $person_choice = sanitize_text_field( wp_unslash( $_POST['picked_up_person_id'] ?? 'other' ) );
        $person_id = 0;
        if ( 'other' === $person_choice ) {
            $name = sanitize_text_field( wp_unslash( $_POST['picked_up_by_name'] ?? '' ) );
        } else {
            $person_id = absint( $person_choice );
            $person = function_exists( 'pwl_get_person' ) ? pwl_get_person( $person_id ) : false;
            if ( ! $person || 'active' !== ( $person['person_status'] ?? '' ) ) {
                $this->redirect( 'error' );
            }
            $name = sanitize_text_field( $person['display_name'] ?: $person['full_name'] );
        }
        if ( ! $row || 'awaiting_pickup' !== $row->status || '' === $name ) {
            $this->redirect( 'error' );
        }
        $pickup_at = current_time( 'mysql' );
        $updated = $wpdb->update( $this->table, array( 'status' => 'picked_up', 'picked_up_by_name' => $name, 'picked_up_by_person_id' => $person_id, 'picked_up_by_user' => get_current_user_id(), 'picked_up_at' => $pickup_at, 'updated_at' => $pickup_at ), array( 'id' => $id ), array( '%s','%s','%d','%d','%s','%s' ), array( '%d' ) );
        if ( false === $updated ) { $this->redirect( 'error' ); }
        $wpdb->update( $this->notices, array( 'status' => 'completed', 'passphrase_cipher' => '', 'updated_at' => $pickup_at ), array( 'package_id' => $id, 'status' => 'linked' ), array( '%s','%s','%s' ), array( '%d','%s' ) );
        $delivery_operator = get_userdata( get_current_user_id() );
        $delivery_operator_name = $delivery_operator && $delivery_operator->display_name ? $delivery_operator->display_name : 'Usuário não identificado';
        $this->log_event( $id, 'picked_up', '', 'success', 'Entregue por ' . $delivery_operator_name . '; retirado por ' . $name . '.' );
        $this->notify_pickup_recipients( $id );
        $this->redirect( 'picked' );
    }

    private function notify_pickup_recipients( $package_id ) {
        global $wpdb;
        $package = $this->get_package( $package_id );
        if ( ! $package || 'picked_up' !== $package->status ) { return; }
        $targets = array();
        if ( 'group' === ( $package->recipient_type ?? 'person' ) ) {
            $snapshots = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->package_recipients} WHERE package_id = %d ORDER BY person_name ASC", absint( $package_id ) ) );
            foreach ( (array) $snapshots as $snapshot ) {
                $target = clone $package;
                $target->recipient_user_id = absint( $snapshot->user_id );
                $target->recipient_person_id = absint( $snapshot->person_id );
                $target->recipient_name = $snapshot->person_name;
                $target->recipient_email = $snapshot->email;
                $target->recipient_mobile = $snapshot->mobile;
                $target->recipient_snapshot_id = absint( $snapshot->id );
                $targets[] = $target;
            }
        } else {
            $targets[] = $package;
        }
        if ( ! $targets ) { return; }
        $operator = ! empty( $package->picked_up_by_user ) ? get_userdata( absint( $package->picked_up_by_user ) ) : false;
        $operator_name = $operator && $operator->display_name ? $operator->display_name : 'Usuário não identificado';
        $pickup_at = $package->picked_up_at ? $package->picked_up_at : current_time( 'mysql' );
        $title = 'Encomenda retirada na Portaria';
        $package_code = $package->package_code ?: $this->build_package_code( $package->id, $package->received_at );
        foreach ( $targets as $target ) {
            $recipient_name = sanitize_text_field( $target->recipient_name ?: 'Colaborador' );
            $message = 'Olá ' . $recipient_name . ', sua encomenda foi retirada na Portaria.';
            $message .= "\nCódigo interno da Encomenda: " . $package_code;
            $message .= "\nData da retirada: " . mysql2date( 'd/m/Y', $pickup_at );
            $message .= "\nHora da retirada: " . mysql2date( 'H:i', $pickup_at );
            $message .= "\nQuem retirou: " . sanitize_text_field( $package->picked_up_by_name ?: 'Não informado' );
            $message .= "\nQuem entregou: " . $operator_name;
            $this->active_recipient_snapshot_id = absint( $target->recipient_snapshot_id ?? 0 );
            $user_id = absint( $target->recipient_user_id );
            $notification_id = false;
            if ( $user_id && function_exists( 'pwl_criar_notificacao' ) ) {
                $notification_id = pwl_criar_notificacao( $title, $message, $this->portal_url(), $user_id, 'geral', array( 'priority' => 'normal', 'send_email' => true ) );
            }
            $this->log_event( $package_id, 'pickup_notice', 'portal', $notification_id ? 'created' : 'skipped', $notification_id ? 'Notificação de retirada criada no PWL Notificações.' : 'Sem conta vinculada ou serviço PWL Notificações indisponível.' );
            $this->log_event( $package_id, 'pickup_notice', 'windows', $notification_id ? 'accepted' : 'skipped', $notification_id ? 'Solicitação enviada ao PWL Notificações; entrega depende da preferência Windows e de dispositivo inscrito.' : 'Canal Windows indisponível sem conta vinculada e notificação central.' );
            if ( $notification_id ) {
                $this->log_event( $package_id, 'pickup_notice', 'email', 'accepted', 'Solicitação enviada à fila do PWL Notificações; a preferência de e-mail do usuário é respeitada.' );
            } elseif ( ! $user_id && is_email( $target->recipient_email ) ) {
                $sent = $this->send_formatted_email_fallback( $target, $title, $message );
                $this->log_event( $package_id, 'pickup_notice', 'email', $sent ? 'accepted' : 'failed', $sent ? 'E-mail de retirada enviado ao endereço cadastrado.' : 'O serviço de e-mail não aceitou o aviso de retirada.' );
            } else {
                $this->log_event( $package_id, 'pickup_notice', 'email', 'skipped', 'Sem conta vinculada ou endereço de e-mail válido para envio.' );
            }
            if ( ! is_email( $target->recipient_email ) ) {
                $this->log_event( $package_id, 'pickup_notice', 'teams', 'skipped', 'E-mail corporativo ausente ou inválido para localizar a conta do Teams.' );
            } elseif ( ! get_option( 'pwl_encomendas_teams_webhook_url', '' ) ) {
                $this->log_event( $package_id, 'pickup_notice', 'teams', 'not_configured', 'Webhook do Microsoft Teams ainda não configurado.' );
            } else {
                $response = $this->send_teams_flow_message( $target, $title, $message );
                $this->log_event( $package_id, 'pickup_notice', 'teams', $response['ok'] ? 'accepted' : 'failed', $response['detail'], $response['id'] );
            }
        }
        $this->active_recipient_snapshot_id = 0;
    }

    public function handle_retry() {
        $id = absint( $_POST['package_id'] ?? 0 );
        $this->guard_post( self::CAP_MANAGE, 'pwl_encomendas_retry_' . $id );
        $row = $this->get_package( $id );
        if ( ! $row || 'awaiting_pickup' !== $row->status ) {
            $this->redirect( 'error' );
        }
        $this->send_pending_channels( $id, true );
        $this->log_event( $id, 'retry', '', 'success', 'Nova tentativa solicitada pela Portaria.' );
        $this->redirect( 'retried' );
    }

    public function handle_delete_bulk() {
        if ( ! $this->can_delete_packages() ) {
            wp_die( esc_html__( 'Somente super administradores do WordPress podem excluir encomendas pela Admin UI.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'pwl_encomendas_delete_bulk' );

        $confirmation = sanitize_text_field( wp_unslash( $_POST['delete_confirmation'] ?? '' ) );
        $ids_raw = wp_unslash( $_POST['package_ids'] ?? array() );
        $ids = is_array( $ids_raw ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids_raw ) ) ) ) : array();
        if ( 'EXCLUIR' !== $confirmation || ! $ids ) {
            wp_safe_redirect( add_query_arg( 'pwl_encomendas_result', 'error', admin_url( 'admin.php?page=pwl-encomendas' ) ) );
            exit;
        }

        global $wpdb;
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query( 'START TRANSACTION' );
        $events_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->events} WHERE package_id IN ({$placeholders})", $ids ) );
        $recipients_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->package_recipients} WHERE package_id IN ({$placeholders})", $ids ) );
        $notices_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->notices} WHERE package_id IN ({$placeholders})", $ids ) );
        $packages_deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE id IN ({$placeholders})", $ids ) );
        if ( false === $events_deleted || false === $recipients_deleted || false === $notices_deleted || false === $packages_deleted ) {
            $wpdb->query( 'ROLLBACK' );
            wp_safe_redirect( add_query_arg( 'pwl_encomendas_result', 'error', admin_url( 'admin.php?page=pwl-encomendas' ) ) );
            exit;
        }
        $wpdb->query( 'COMMIT' );
        wp_safe_redirect( add_query_arg( 'pwl_encomendas_result', 'deleted', admin_url( 'admin.php?page=pwl-encomendas' ) ) );
        exit;
    }

    public function handle_carrier_save() {
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_carrier_save' );
        global $wpdb;
        $id = absint( $_POST['carrier_id'] ?? 0 );
        $name = sanitize_text_field( wp_unslash( $_POST['carrier_name'] ?? '' ) );
        $name = trim( $name );
        $logo_id = absint( $_POST['carrier_logo_attachment_id'] ?? 0 );
        if ( '' === $name || ( $logo_id && ! wp_attachment_is_image( $logo_id ) ) ) {
            $this->redirect_carriers( 'error', $id );
        }
        if ( $id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->carriers} WHERE id = %d LIMIT 1", $id ) ) ) {
            $this->redirect_carriers( 'error', $id );
        }
        $duplicate_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->carriers} WHERE name = %s AND id <> %d LIMIT 1", $name, $id ) ) );
        if ( $id && $duplicate_id ) {
            $this->redirect_carriers( 'error', $id );
        }
        $now = current_time( 'mysql' );
        if ( $id ) {
            $saved = $wpdb->update( $this->carriers, array( 'name' => $name, 'logo_attachment_id' => $logo_id, 'updated_at' => $now ), array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );
        } else {
            $existing_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->carriers} WHERE name = %s LIMIT 1", $name ) ) );
            if ( $existing_id ) {
                $saved = $wpdb->update( $this->carriers, array( 'logo_attachment_id' => $logo_id, 'active' => 1, 'updated_at' => $now ), array( 'id' => $existing_id ), array( '%d', '%d', '%s' ), array( '%d' ) );
            } else {
                $saved = $wpdb->insert( $this->carriers, array( 'name' => $name, 'logo_attachment_id' => $logo_id, 'active' => 1, 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%d', '%d', '%s', '%s' ) );
            }
        }
        if ( false === $saved ) {
            $this->redirect_carriers( 'error', $id );
        }
        $this->redirect_carriers( 'saved' );
    }

    public function handle_carrier_toggle() {
        $id = absint( $_POST['carrier_id'] ?? 0 );
        $this->guard_admin_post( self::CAP_MANAGE, 'pwl_encomendas_carrier_toggle_' . $id );
        global $wpdb;
        $active = empty( $_POST['active'] ) ? 0 : 1;
        $wpdb->update( $this->carriers, array( 'active' => $active, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) );
        $this->redirect_carriers( 'updated' );
    }

    private function redirect_carriers( $result, $edit_id = 0 ) {
        $args = array( 'pwl_carrier_result' => sanitize_key( $result ) );
        if ( $edit_id ) {
            $args['carrier_id'] = absint( $edit_id );
        }
        $url = add_query_arg( array_merge( array( 'encomendas_view' => 'carriers' ), $args ), admin_url( 'admin.php?page=pwl-encomendas' ) );
        wp_safe_redirect( $url );
        exit;
    }

    private function send_pending_channels( $id, $force = false ) {
        $row = $this->get_package( $id );
        if ( ! $row ) { return; }
        if ( 'group' === ( $row->recipient_type ?? 'person' ) ) {
            global $wpdb;
            $recipients = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->package_recipients} WHERE package_id = %d ORDER BY person_name ASC", absint( $row->id ) ) );
            foreach ( (array) $recipients as $recipient ) {
                $recipient_row = clone $row;
                $recipient_row->recipient_person_id = absint( $recipient->person_id );
                $recipient_row->recipient_user_id = absint( $recipient->user_id );
                $recipient_row->recipient_name = $recipient->person_name;
                $recipient_row->recipient_email = $recipient->email;
                $recipient_row->recipient_mobile = $recipient->mobile;
                $recipient_row->recipient_snapshot_id = absint( $recipient->id );
                $recipient_row->portal_status = $recipient->portal_status;
                $recipient_row->email_status = $recipient->email_status;
                $recipient_row->whatsapp_status = $recipient->whatsapp_status;
                $recipient_row->teams_status = $recipient->teams_status;
                $this->active_recipient_snapshot_id = absint( $recipient->id );
                $this->send_pending_channels_for_row( $recipient_row, $force );
            }
            $this->active_recipient_snapshot_id = 0;
            foreach ( array( 'portal_status', 'email_status', 'whatsapp_status', 'teams_status' ) as $column ) {
                $statuses = $wpdb->get_col( $wpdb->prepare( "SELECT {$column} FROM {$this->package_recipients} WHERE package_id = %d", absint( $row->id ) ) );
                $this->update_channel( $row->id, $column, $this->aggregate_channel_status( $statuses ) );
            }
            return;
        }
        $this->send_pending_channels_for_row( $row, $force );
    }

    private function send_pending_channels_for_row( $row, $force = false ) {
        $id = absint( $row->id );
        $is_group_recipient = ! empty( $row->recipient_snapshot_id );
        $email_queued = false;
        $email_disabled = false;
        $message = $force ? 'Lembrete:' : '';
        if ( $row->sender ) { $message .= ( $message ? "\n" : '' ) . 'Remetente: ' . $row->sender; }
        $message .= ( $message ? "\n" : '' ) . 'Código interno da Encomenda: ' . ( $row->package_code ?: $this->build_package_code( $row->id, $row->received_at ) );
        if ( $row->description ) { $message .= ( $message ? "\n" : '' ) . 'Descrição: ' . $row->description; }
        if ( $row->tracking_code ) { $message .= ( $message ? "\n" : '' ) . 'Código de Rastreio: ' . $row->tracking_code; }
        $message .= ( $message ? "\n" : '' ) . 'Procure a Portaria da unidade.';
        $message .= "\nRamal da Portaria: 285 / 286";
        $notice_title = $force ? 'Lembrete: Encomenda recebida na Portaria' : 'Encomenda recebida na Portaria';

        if ( $force || ! in_array( $row->portal_status, array( 'created', 'accepted' ), true ) ) {
            $result = false;
            if ( $row->recipient_user_id && function_exists( 'pwl_criar_notificacao' ) ) {
                $email_preference = get_user_meta( absint( $row->recipient_user_id ), 'pwl_notif_pref_email', true );
                if ( '' === $email_preference ) { $email_preference = get_user_meta( absint( $row->recipient_user_id ), 'cm_notif_pref_email', true ); }
                $email_enabled = $is_group_recipient || '' === $email_preference || ! in_array( (string) $email_preference, array( '0', 'false', 'off' ), true );
                $email_disabled = ! $email_enabled;
                // For group recipients, send email directly below so every snapshot member
                // receives it even if the generic notification email preference is off.
                $email_queued = ! $is_group_recipient && is_email( $row->recipient_email ) && $email_enabled;
                $result = pwl_criar_notificacao( $notice_title, $message, $this->portal_url(), absint( $row->recipient_user_id ), 'geral', array( 'priority' => 'importante', 'send_email' => $email_queued ) );
                if ( ! $result ) { $email_queued = false; }
            }
            $status = $result ? 'created' : 'skipped';
            $detail = $result ? 'Notificação criada para a conta vinculada.' : 'Colaborador sem conta vinculada ou módulo de notificações indisponível.';
            $this->update_channel( $id, 'portal_status', $status );
            $this->log_event( $id, 'notice', 'portal', $status, $detail );
        }

        if ( $force || ! in_array( $row->email_status, array( 'accepted', 'sent' ), true ) ) {
            $status = 'skipped';
            $detail = 'E-mail corporativo não cadastrado ou inválido.';
            if ( is_email( $row->recipient_email ) ) {
                if ( $is_group_recipient && is_email( $row->recipient_email ) ) {
                    $sent = $this->send_formatted_email_fallback( $row, $notice_title, $message );
                    $status = $sent ? 'accepted' : 'failed';
                    $detail = $sent ? 'E-mail HTML enviado ao membro do grupo.' : 'O serviço de e-mail não aceitou o envio ao membro do grupo.';
                } elseif ( $email_queued ) {
                    $sent = true;
                    $status = 'accepted';
                    $detail = 'E-mail incluído na fila do módulo Notificações com o template HTML centralizado.';
                } elseif ( $email_disabled ) {
                    $status = 'skipped';
                    $detail = 'E-mail não enviado: preferência de e-mail desativada pelo colaborador.';
                } elseif ( ! $row->recipient_user_id || ! function_exists( 'pwl_criar_notificacao' ) ) {
                    $sent = $this->send_formatted_email_fallback( $row, $notice_title, $message );
                    $status = $sent ? 'accepted' : 'failed';
                    $detail = $sent ? 'E-mail HTML enviado ao endereço cadastrado.' : 'O serviço de e-mail não aceitou o envio.';
                } else {
                    $sent = false;
                    $status = 'failed';
                    $detail = 'E-mail não enviado: a preferência de e-mail está desativada ou a notificação não foi criada.';
                }
            }
            $this->update_channel( $id, 'email_status', $status );
            $this->log_event( $id, 'notice', 'email', $status, $detail );
        }

        if ( $force || ! in_array( $row->whatsapp_status, array( 'accepted', 'sent' ), true ) ) {
            $status = 'not_configured';
            $detail = 'Cloud API, modelo ou credenciais ainda não configurados.';
            if ( ! $this->phone_for_whatsapp( $row->recipient_mobile ) ) {
                $status = 'skipped';
                $detail = 'Celular corporativo ausente ou inválido no Core.';
            } elseif ( $this->whatsapp_configured() ) {
                $response = $this->send_whatsapp_template( $row );
                $status = $response['ok'] ? 'accepted' : 'failed';
                $detail = $response['detail'];
            }
            $this->update_channel( $id, 'whatsapp_status', $status );
            $this->log_event( $id, 'notice', 'whatsapp', $status, $detail );
        }

        if ( $force || ! in_array( $row->teams_status, array( 'accepted', 'sent' ), true ) ) {
            $status = 'not_configured';
            $detail = 'Webhook do Microsoft Teams ainda não configurado.';
            if ( ! is_email( $row->recipient_email ) ) {
                $status = 'skipped';
                $detail = 'E-mail corporativo ausente ou inválido para localizar a conta do Teams.';
            } elseif ( get_option( 'pwl_encomendas_teams_webhook_url', '' ) ) {
                $response = $this->send_teams_flow_message( $row, $notice_title );
                $status = $response['ok'] ? 'accepted' : 'failed';
                $detail = $response['detail'];
                $this->update_channel( $id, 'teams_status', $status );
                $this->log_event( $id, 'notice', 'teams', $status, $detail, $response['id'] );
            } else {
                $this->update_channel( $id, 'teams_status', $status );
                $this->log_event( $id, 'notice', 'teams', $status, $detail );
            }
        }
    }

    private function send_formatted_email_fallback( $row, $subject, $message ) {
        $brand = sanitize_text_field( get_option( 'pwl_notificacoes_email_brand_name', get_bloginfo( 'name' ) ) );
        if ( '' === $brand ) { $brand = get_bloginfo( 'name' ); }
        $color = sanitize_hex_color( get_option( 'pwl_notificacoes_email_color', '#ff5a00' ) );
        if ( ! $color ) { $color = '#ff5a00'; }
        $logo = esc_url_raw( get_option( 'pwl_notificacoes_email_logo_url', apply_filters( 'pwl_notificacoes_default_email_logo_url', '' ) ) );
        $footer = sanitize_textarea_field( get_option( 'pwl_notificacoes_email_footer', '{{brand}} • Mensagem automática enviada pelo portal • {{year}}' ) );
        $footer = strtr( $footer, array( '{{brand}}' => $brand, '{{year}}' => wp_date( 'Y' ) ) );
        $brand_markup = $logo
            ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $brand ) . '" width="300" style="display:block;width:100%;max-width:300px;max-height:90px;height:auto;border:0;">'
            : '<div style="font-size:20px;line-height:1.3;font-weight:800;color:#101828;">' . esc_html( $brand ) . '</div>';
        $html = '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#344054;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f6f8;padding:26px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e6eaf0;border-collapse:separate;border-spacing:0;border-radius:16px!important;overflow:hidden;">'
            . '<tr><td style="height:5px;background:' . esc_attr( $color ) . ';font-size:0;line-height:0;border-radius:16px 16px 0 0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 32px 22px;">' . $brand_markup . '</td></tr>'
            . '<tr><td style="padding:0 32px 8px;"><div style="font-size:13px;color:#667085;margin-bottom:10px;">Olá, ' . esc_html( $row->recipient_name ) . '.</div><h1 style="margin:0;font-size:24px;line-height:1.35;color:#101828;font-weight:800;">' . esc_html( $subject ) . '</h1></td></tr>'
            . '<tr><td style="padding:26px 32px 30px;font-size:15px;line-height:1.75;color:#344054;">' . $this->format_package_email_message( $message ) . '</td></tr>'
            . '<tr><td style="padding:16px 32px;background:#fafbfc;border-top:1px solid #eef1f5;font-size:11px;line-height:1.55;color:#667085;border-radius:0 0 16px 16px;">' . nl2br( esc_html( $footer ) ) . '</td></tr>'
            . '</table></td></tr></table></body></html>';
        $prefix = sanitize_text_field( get_option( 'pwl_notificacoes_email_subject_prefix', $brand ) );
        $email_subject = ( $prefix ? '[' . $prefix . '] ' : '' ) . $subject;
        return wp_mail( $row->recipient_email, $email_subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    private function format_package_email_message( $message ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string) $message );
        $html = '';
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) { continue; }
            if ( 0 === stripos( $line, 'Lembrete:' ) ) {
                $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#344054;"><strong>Lembrete:</strong></p>';
                continue;
            }
            if ( preg_match( '/^(Remetente|Código interno da Encomenda|Descrição|Embalagem|Código de Rastreio|Data da retirada|Hora da retirada|Quem retirou|Quem entregou):\s*(.*)$/iu', $line, $parts ) ) {
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>' . esc_html( $parts[1] ) . ':</strong> ' . esc_html( $parts[2] ) . '</p>';
                continue;
            }
            if ( preg_match( '/^(Procure a Portaria da unidade)(.*)$/iu', $line, $parts ) ) {
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>' . esc_html( $parts[1] ) . '</strong>' . esc_html( $parts[2] ) . '</p>';
                continue;
            }
            $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;">' . esc_html( $line ) . '</p>';
        }
        return $html;
    }

    private function round_package_email_card( $html ) {
        return preg_replace_callback( '/<table\b(?=[^>]*\bwidth=["\']?640(?:["\']|\s|>))[^>]*>/i', function( $matches ) {
            $tag = $matches[0];
            $style = '';
            if ( preg_match( '/\sstyle=(["\'])(.*?)\1/i', $tag, $style_match ) ) { $style = $style_match[2]; }
            $rules = array(
                'border-collapse' => 'separate',
                'border-spacing'  => '0',
                'border-radius'   => '16px!important',
                'overflow'        => 'hidden',
            );
            foreach ( $rules as $property => $value ) {
                $pattern = '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:[^;]*/i';
                if ( preg_match( $pattern, $style ) ) {
                    $style = preg_replace( $pattern, ';' . $property . ':' . $value, $style, 1 );
                } else {
                    $style .= ';' . $property . ':' . $value;
                }
            }
            $style = trim( $style, '; ' );
            if ( preg_match( '/\sstyle=(["\'])(.*?)\1/i', $tag ) ) {
                return preg_replace( '/\sstyle=(["\'])(.*?)\1/i', ' style="' . esc_attr( $style ) . '"', $tag, 1 );
            }
            return preg_replace( '/<table\b/i', '<table style="' . esc_attr( $style ) . '"', $tag, 1 );
        }, (string) $html, 1 );
    }

    public function limit_notification_email_logo( $html, $notification = null, $user = null, $settings = array() ) {
        if ( is_object( $notification ) && ! empty( $notification->title ) && ( false !== stripos( $notification->title, 'Encomenda recebida na Portaria' ) || false !== stripos( $notification->title, 'Encomenda retirada na Portaria' ) ) ) {
            $html = $this->round_package_email_card( $html );
            $title = esc_html( (string) $notification->title );
            $html = preg_replace_callback( '/(<body\b[^>]*>)(.*?)(<table\b)/is', function( $body_parts ) use ( $title ) {
                $outside_title = '~<(p|div|span)\b[^>]*>\s*' . preg_quote( $title, '~' ) . '\.?\s*</\1>|' . preg_quote( $title, '~' ) . '\.?~i';
                $before_card = preg_replace( $outside_title, '', $body_parts[2], 1 );
                return $body_parts[1] . $before_card . $body_parts[3];
            }, $html, 1 );
            $plain_message = nl2br( esc_html( (string) $notification->message ) );
            $formatted_message = $this->format_package_email_message( $notification->message );
            if ( '' !== $plain_message && false !== strpos( $html, $plain_message ) ) {
                $html = str_replace( $plain_message, $formatted_message, $html );
            }
            $html = preg_replace_callback( '/<tr\b[^>]*>.*?<\/tr>/is', function( $row_parts ) {
                $row_text = preg_replace( '/\s+/', '', wp_strip_all_tags( $row_parts[0] ) );
                return false !== stripos( $row_text, 'Geral' ) && false !== stripos( $row_text, 'Importante' ) ? '' : $row_parts[0];
            }, $html );
            $html = preg_replace( '/<table\s+role="presentation"\s+cellspacing="0"\s+cellpadding="0"\s+border="0"\s+style="margin:[^"]*">.*?<\/table>/is', '', $html, 1 );
        }

        return preg_replace_callback( '/<img\b[^>]*>/i', function( $matches ) {
            $tag = $matches[0];
            $style = '';
            if ( preg_match( '/\sstyle=("|\')(.*?)\1/i', $tag, $style_match ) ) {
                $style = $style_match[2];
            }
            $rules = array(
                'display'    => 'block',
                'max-width'  => '300px',
                'max-height' => '90px',
                'width'      => '100%',
                'height'     => 'auto',
                'border'     => '0',
            );
            foreach ( $rules as $property => $value ) {
                $pattern = '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:[^;]*/i';
                if ( preg_match( $pattern, $style ) ) {
                    $style = preg_replace( $pattern, ';' . $property . ':' . $value, $style, 1 );
                } else {
                    $style .= ';' . $property . ':' . $value;
                }
            }
            $style = trim( $style, '; ' );
            if ( preg_match( '/\swidth=("|\')(.*?)\1/i', $tag ) ) {
                $tag = preg_replace( '/\swidth=("|\')(.*?)\1/i', ' width="300"', $tag, 1 );
            } else {
                $tag = preg_replace( '/<img\b/i', '<img width="300"', $tag, 1 );
            }
            if ( preg_match( '/\sstyle=("|\')(.*?)\1/i', $tag ) ) {
                return preg_replace( '/\sstyle=("|\')(.*?)\1/i', ' style="' . esc_attr( $style ) . '"', $tag, 1 );
            }
            return preg_replace( '/\s*\/?>$/', ' style="' . esc_attr( $style ) . '"$0', $tag, 1 );
        }, (string) $html );
    }

    private function send_whatsapp_template( $row ) {
        $phone_id = defined( 'PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID' ) ? trim( (string) PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID ) : '';
        $token = defined( 'PWL_ENCOMENDAS_WA_ACCESS_TOKEN' ) ? trim( (string) PWL_ENCOMENDAS_WA_ACCESS_TOKEN ) : '';
        $version = defined( 'PWL_ENCOMENDAS_WA_GRAPH_VERSION' ) ? trim( (string) PWL_ENCOMENDAS_WA_GRAPH_VERSION ) : '';
        $template = defined( 'PWL_ENCOMENDAS_WA_TEMPLATE_NAME' ) ? trim( (string) PWL_ENCOMENDAS_WA_TEMPLATE_NAME ) : 'aviso_encomenda';
        $language = defined( 'PWL_ENCOMENDAS_WA_TEMPLATE_LANGUAGE' ) ? trim( (string) PWL_ENCOMENDAS_WA_TEMPLATE_LANGUAGE ) : 'pt_BR';
        $phone = $this->phone_for_whatsapp( $row->recipient_mobile );
        $pickup_details = ( $row->tracking_code ? 'Código de rastreio: ' . $row->tracking_code . '. ' : '' ) . 'Retire na Portaria da unidade. Ramal da Portaria: 285 / 286.';
        $params = array( $row->recipient_name, $row->sender ?: 'Não informado', $row->description ?: 'Encomenda', $pickup_details );
        $components = array( array( 'type' => 'body', 'parameters' => array() ) );
        foreach ( $params as $param ) {
            $components[0]['parameters'][] = array( 'type' => 'text', 'text' => function_exists( 'mb_substr' ) ? mb_substr( $param, 0, 200 ) : substr( $param, 0, 200 ) );
        }
        $payload = array( 'messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'template', 'template' => array( 'name' => $template, 'language' => array( 'code' => $language ), 'components' => $components ) );
        $response = wp_remote_post( 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $phone_id ) . '/messages', array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( $payload ),
        ) );
        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'detail' => 'Falha de conexão com a API oficial do WhatsApp.', 'id' => '' );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code >= 200 && $code < 300 && ! empty( $body['messages'][0]['id'] ) ) {
            return array( 'ok' => true, 'detail' => 'API aceitou a mensagem para processamento.', 'id' => sanitize_text_field( $body['messages'][0]['id'] ) );
        }
        return array( 'ok' => false, 'detail' => 'A API recusou o envio. Confira a configuração e o modelo aprovado.', 'id' => '' );
    }

    private function send_teams_flow_message( $row, $title ) {
        $url = (string) get_option( 'pwl_encomendas_teams_webhook_url', '' );
        if ( ! $url || ! $this->valid_teams_webhook_url( $url ) ) {
            return array( 'ok' => false, 'detail' => 'Webhook do Microsoft Teams inválido ou não configurado.', 'id' => '' );
        }
        $teams_heading = trim( (string) $title );
        if ( '' === $teams_heading ) { $teams_heading = 'Encomenda recebida na Portaria'; }
        if ( '!' !== substr( $teams_heading, -1 ) ) { $teams_heading .= '!'; }
        $teams_lines = array(
            '<strong>' . esc_html( sanitize_text_field( $teams_heading ) ) . '</strong>',
            '<strong>Remetente:</strong> ' . esc_html( sanitize_text_field( (string) $row->sender ) ),
            '<strong>Código interno da Encomenda:</strong> ' . esc_html( sanitize_text_field( $row->package_code ?: $this->build_package_code( $row->id, $row->received_at ) ) ),
        );
        if ( $row->description ) { $teams_lines[] = '<strong>Descrição:</strong> ' . esc_html( sanitize_text_field( (string) $row->description ) ); }
        if ( $row->tracking_code ) { $teams_lines[] = '<strong>Código de Rastreio:</strong> ' . esc_html( sanitize_text_field( (string) $row->tracking_code ) ); }
        $teams_lines[] = '<strong>Procure a Portaria da unidade.</strong>';
        $teams_lines[] = '<strong>Ramal da Portaria:</strong> 285 / 286';
        $teams_message = wp_kses( implode( '<br>', $teams_lines ), array( 'br' => array(), 'strong' => array() ) );
        $payload = array(
            'recipient_email' => sanitize_email( $row->recipient_email ),
            'recipient_name' => sanitize_text_field( $row->recipient_name ),
            'title' => sanitize_text_field( $title ),
            'package_code' => sanitize_text_field( $row->package_code ?: $this->build_package_code( $row->id, $row->received_at ) ),
            'message' => $teams_message,
        );
        $response = wp_remote_post( $url, array(
            'timeout' => 15,
            'redirection' => 0,
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body' => wp_json_encode( $payload ),
        ) );
        if ( is_wp_error( $response ) ) {
            return array( 'ok' => false, 'detail' => 'Não foi possível conectar ao fluxo do Microsoft Teams.', 'id' => '' );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return array( 'ok' => false, 'detail' => 'O fluxo do Microsoft Teams recusou a solicitação (HTTP ' . $code . ').', 'id' => '' );
        }
        $headers = wp_remote_retrieve_headers( $response );
        $run_id = isset( $headers['x-ms-workflow-run-id'] ) ? sanitize_text_field( (string) $headers['x-ms-workflow-run-id'] ) : '';
        return array( 'ok' => true, 'detail' => 'Fluxo aceitou o aviso privado. Confirme a entrega no histórico de execuções do Power Automate.', 'id' => substr( $run_id, 0, 190 ) );
    }

    private function whatsapp_configured() {
        return defined( 'PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID' ) && defined( 'PWL_ENCOMENDAS_WA_ACCESS_TOKEN' ) && defined( 'PWL_ENCOMENDAS_WA_GRAPH_VERSION' ) && defined( 'PWL_ENCOMENDAS_WA_TEMPLATE_NAME' ) && defined( 'PWL_ENCOMENDAS_WA_TEMPLATE_LANGUAGE' ) && '' !== trim( (string) PWL_ENCOMENDAS_WA_PHONE_NUMBER_ID ) && '' !== trim( (string) PWL_ENCOMENDAS_WA_ACCESS_TOKEN ) && '' !== trim( (string) PWL_ENCOMENDAS_WA_GRAPH_VERSION );
    }

    private function phone_for_whatsapp( $value ) {
        $digits = preg_replace( '/\D+/', '', (string) $value );
        if ( strlen( $digits ) < 12 || strlen( $digits ) > 15 ) { return ''; }
        return $digits;
    }

    private function build_package_code( $id, $received_at = '' ) {
        $year = $received_at ? mysql2date( 'Y', $received_at ) : wp_date( 'Y' );
        return 'ENC-' . $year . '-' . str_pad( (string) absint( $id ), 6, '0', STR_PAD_LEFT );
    }

    private function next_package_code( $received_at = '' ) {
        global $wpdb;
        $year = absint( $received_at ? mysql2date( 'Y', $received_at ) : wp_date( 'Y' ) );
        for ( $attempt = 0; $attempt < 1000; $attempt++ ) {
            $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->sequences} (sequence_year, last_number) VALUES (%d, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1", $year ) );
            if ( false === $result ) { return ''; }
            $number = absint( $wpdb->get_var( $wpdb->prepare( "SELECT last_number FROM {$this->sequences} WHERE sequence_year = %d", $year ) ) );
            if ( ! $number ) { return ''; }
            $code = 'ENC-' . $year . '-' . str_pad( (string) $number, 6, '0', STR_PAD_LEFT );
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE package_code = %s LIMIT 1", $code ) );
            if ( ! $existing ) { return $code; }
        }
        return '';
    }

    private function get_package( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", absint( $id ) ) );
    }

    private function update_channel( $id, $column, $status ) {
        global $wpdb;
        $allowed = array( 'portal_status', 'email_status', 'whatsapp_status', 'teams_status' );
        if ( ! in_array( $column, $allowed, true ) ) { return; }
        if ( $this->active_recipient_snapshot_id ) {
            $snapshot_id = absint( $this->active_recipient_snapshot_id );
            $wpdb->update( $this->package_recipients, array( $column => sanitize_key( $status ) ), array( 'id' => $snapshot_id ), array( '%s' ), array( '%d' ) );
            return;
        }
        $wpdb->update( $this->table, array( $column => sanitize_key( $status ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ), array( '%s','%s' ), array( '%d' ) );
    }

    private function aggregate_channel_status( $statuses ) {
        $statuses = array_values( (array) $statuses );
        if ( ! $statuses ) { return 'skipped'; }
        if ( in_array( 'pending', $statuses, true ) ) { return 'pending'; }
        if ( in_array( 'failed', $statuses, true ) ) { return 'failed'; }
        foreach ( array( 'created', 'accepted', 'sent' ) as $success ) {
            if ( in_array( $success, $statuses, true ) ) { return $success; }
        }
        if ( in_array( 'not_configured', $statuses, true ) ) { return 'not_configured'; }
        return 'skipped';
    }

    private function log_event( $package_id, $event_type, $channel, $result, $detail, $external_ref = '' ) {
        global $wpdb;
        if ( $this->active_recipient_snapshot_id ) {
            $recipient_name = $wpdb->get_var( $wpdb->prepare( "SELECT person_name FROM {$this->package_recipients} WHERE id = %d", absint( $this->active_recipient_snapshot_id ) ) );
            if ( $recipient_name ) { $detail .= ' Destinatário: ' . $recipient_name . '.'; }
        }
        $wpdb->insert( $this->events, array(
            'package_id' => absint( $package_id ), 'event_type' => sanitize_key( $event_type ), 'channel' => sanitize_key( $channel ),
            'result' => sanitize_key( $result ), 'actor_user_id' => get_current_user_id(), 'detail' => sanitize_text_field( $detail ),
            'external_ref' => sanitize_text_field( $external_ref ), 'created_at' => current_time( 'mysql' ),
        ), array( '%d','%s','%s','%s','%d','%s','%s','%s' ) );
    }

    private function status_badge( $status, $label, $admin = false ) {
        if ( $admin ) {
            return '<span class="pwl-ui-badge">' . esc_html( $label ) . '</span>';
        }
        $classes = array( 'created' => 'success', 'accepted' => 'success', 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'neutral', 'not_configured' => 'warning', 'pending' => 'warning', 'awaiting_pickup' => 'warning', 'picked_up' => 'success' );
        $class = $classes[ $status ] ?? '';
        $modifier = $class && 'neutral' !== $class ? ' pwl-badge--' . $class : '';
        return '<span class="pwl-badge' . esc_attr( $modifier ) . '">' . esc_html( $label ) . '</span>';
    }

    private function channel_label( $status ) {
        $labels = array( 'created' => 'Criado', 'accepted' => 'Aceito', 'sent' => 'Enviado', 'failed' => 'Falhou', 'skipped' => 'Ignorado', 'not_configured' => 'Não configurado', 'pending' => 'Pendente' );
        return $labels[ $status ] ?? 'Pendente';
    }

    private function guard_post( $capability, $nonce_action ) {
        if ( ! is_user_logged_in() || ! $this->can( $capability ) ) {
            wp_die( esc_html__( 'Você não possui permissão para esta operação.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( $nonce_action );
    }

    private function guard_admin_post( $capability, $nonce_action ) {
        if ( ! is_user_logged_in() || ! $this->can( self::CAP_ADMIN ) || ! $this->can( $capability ) ) {
            wp_die( esc_html__( 'Você não possui permissão para acessar a Admin UI de Encomendas.', 'pwl-encomendas' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( $nonce_action );
    }

    private function redirect( $result ) {
        $referer = wp_get_referer();
        $target = $referer ? $referer : home_url( '/' );
        wp_safe_redirect( add_query_arg( 'pwl_encomendas_result', sanitize_key( $result ), $target ) );
        exit;
    }

    private function redirect_notice_create_result( $result ) {
        $return_to = isset( $_POST['pwl_encomendas_return'] ) ? sanitize_key( wp_unslash( $_POST['pwl_encomendas_return'] ) ) : '';
        if ( 'admin_register_notice' === $return_to ) {
            $target = add_query_arg( array(
                'page' => 'pwl-encomendas',
                'encomendas_view' => 'register_notice',
                'pwl_encomendas_result' => sanitize_key( $result ),
            ), admin_url( 'admin.php' ) );
            wp_safe_redirect( $target );
            exit;
        }
        $this->redirect( $result );
    }

    private function portal_url() {
        return wp_get_referer() ? esc_url_raw( wp_get_referer() ) : home_url( '/' );
    }
}

PWL_Encomendas::instance();


