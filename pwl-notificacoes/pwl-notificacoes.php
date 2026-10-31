<?php
/**
 * Plugin Name: PWL Notificações
 * Description: Central corporativa de notificações PWL com histórico, e-mail e Web Push para dispositivos autorizados.
 * Version: 1.10.3
 * Author: PWL
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PWL_Notificacoes {

    const VERSION    = '1.10.3';
    const DB_VERSION = '1.3.0';

    const META_PREF_BELL    = 'pwl_notif_pref_bell';
    const META_PREF_BELL_LEGACY    = 'cm_notif_pref_bell';
    const META_PREF_EMAIL   = 'pwl_notif_pref_email';
    const META_PREF_EMAIL_LEGACY   = 'cm_notif_pref_email';
    const META_PREF_DESKTOP = 'pwl_notif_pref_desktop';
    const META_PREF_DESKTOP_LEGACY = 'cm_notif_pref_desktop';

    const OPTION_EMAIL_BRAND_NAME  = 'pwl_notificacoes_email_brand_name';
    const OPTION_EMAIL_LOGO_URL    = 'pwl_notificacoes_email_logo_url';
    const OPTION_EMAIL_COLOR       = 'pwl_notificacoes_email_color';
    const OPTION_EMAIL_FOOTER      = 'pwl_notificacoes_email_footer';
    const OPTION_EMAIL_BUTTON_TEXT = 'pwl_notificacoes_email_button_text';
    const OPTION_EMAIL_SUBJECT     = 'pwl_notificacoes_email_subject_prefix';

    private static $instance = null;

    private $table_notifications;
    private $table_reads;
    private $table_recipients;
    private $table_email_queue;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        /*
         * Compatibilidade de dados:
         * instalações existentes continuam usando as tabelas legadas para
         * não perder notificações, leituras, destinatários ou fila de e-mail.
         * Instalações novas usam nomes PWL desde o início.
         */
        $legacy_notifications = $wpdb->prefix . 'cobremack_notificacoes';
        $legacy_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_notifications ) ) === $legacy_notifications );

        if ( $legacy_exists ) {
            $this->table_notifications = $legacy_notifications;
            $this->table_reads         = $wpdb->prefix . 'cobremack_notificacoes_lidas';
            $this->table_recipients    = $wpdb->prefix . 'cobremack_notificacoes_destinatarios';
            $this->table_email_queue   = $wpdb->prefix . 'cobremack_notificacoes_email_queue';
        } else {
            $this->table_notifications = $wpdb->prefix . 'pwl_notificacoes';
            $this->table_reads         = $wpdb->prefix . 'pwl_notificacoes_lidas';
            $this->table_recipients    = $wpdb->prefix . 'pwl_notificacoes_destinatarios';
            $this->table_email_queue   = $wpdb->prefix . 'pwl_notificacoes_email_queue';
        }

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
        add_action( 'plugins_loaded', array( $this, 'register_module' ), 20 );
        add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
        add_action( 'pwl_notificacoes_process_email_queue', array( $this, 'process_email_queue' ) );
        add_action( 'cm_notificacoes_process_email_queue', array( $this, 'process_email_queue' ) ); // Compatibilidade legada.

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_email_editor' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_design_system_admin' ), 5 );
        add_action( 'admin_post_pwl_criar_notificacao', array( $this, 'handle_create_notification' ) );
        add_action( 'admin_post_pwl_excluir_notificacao', array( $this, 'handle_delete_notification' ) );
        add_action( 'admin_post_pwl_notificacoes_save_email_module_template', array( $this, 'handle_save_email_module_template' ) );
        add_action( 'admin_post_cm_criar_notificacao', array( $this, 'handle_create_notification' ) ); // Compatibilidade legada.
        add_action( 'admin_post_cm_excluir_notificacao', array( $this, 'handle_delete_notification' ) ); // Compatibilidade legada.

        add_action( 'wp_ajax_pwl_notificacoes_listar', array( $this, 'ajax_list_notifications' ) );
        add_action( 'wp_ajax_pwl_notificacoes_marcar_lida', array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_pwl_notificacoes_marcar_todas', array( $this, 'ajax_mark_all_read' ) );
        add_action( 'wp_ajax_pwl_notificacoes_config', array( $this, 'ajax_config' ) );
        add_action( 'wp_ajax_pwl_notificacoes_salvar_preferencias', array( $this, 'ajax_save_preferences' ) );
        add_action( 'wp_ajax_pwl_notificacoes_workspace_criar', array( $this, 'ajax_workspace_create' ) );
        add_action( 'wp_ajax_pwl_notificacoes_workspace_excluir', array( $this, 'ajax_workspace_delete' ) );

        // Endpoints legados preservados para consumidores já implantados.
        add_action( 'wp_ajax_cm_notificacoes_listar', array( $this, 'ajax_list_notifications' ) );
        add_action( 'wp_ajax_cm_notificacoes_marcar_lida', array( $this, 'ajax_mark_read' ) );
        add_action( 'wp_ajax_cm_notificacoes_marcar_todas', array( $this, 'ajax_mark_all_read' ) );
        add_action( 'wp_ajax_cm_notificacoes_config', array( $this, 'ajax_config' ) );
        add_action( 'wp_ajax_cm_notificacoes_salvar_preferencias', array( $this, 'ajax_save_preferences' ) );

        add_action( 'wp_ajax_nopriv_pwl_notificacoes_listar', array( $this, 'ajax_guest_notifications' ) );
        add_action( 'wp_ajax_nopriv_pwl_notificacoes_config', array( $this, 'ajax_config_guest' ) );
        add_action( 'wp_ajax_nopriv_cm_notificacoes_listar', array( $this, 'ajax_guest_notifications' ) );
        add_action( 'wp_ajax_nopriv_cm_notificacoes_config', array( $this, 'ajax_config_guest' ) );

        add_shortcode( 'pwl_notificacoes_preferencias', array( $this, 'preferences_shortcode' ) );
        add_shortcode( 'pwl_notificacoes_workspace', array( $this, 'workspace_shortcode' ) );
        add_shortcode( 'cobremack_notificacoes_preferencias', array( $this, 'preferences_shortcode' ) ); // Compatibilidade legada.

        add_action( 'wp_footer', array( $this, 'frontend_desktop_script' ), 90 );
        add_action( 'init', array( $this, 'push_register' ) );
        add_action( 'init', array( $this, 'push_serve_worker' ), 0 );
        add_action( 'wp_ajax_pwl_push_subscribe', array( $this, 'push_subscribe' ) );
        add_action( 'wp_ajax_pwl_push_enable_desktop', array( $this, 'push_enable_desktop' ) );
        add_action( 'wp_ajax_pwl_push_unsubscribe', array( $this, 'push_unsubscribe' ) );
        add_action( 'wp_footer', array( $this, 'push_client' ), 95 );
        add_action( 'pwl_notificacoes_process_push_queue', array( $this, 'push_process_queue' ) );
        add_action( 'pwl_notificacoes_process_push_queue_once', array( $this, 'push_process_queue' ) );
        add_action( 'admin_post_nopriv_pwl_push_async_worker', array( $this, 'push_async_worker' ) );
        add_action( 'admin_post_pwl_push_async_worker', array( $this, 'push_async_worker' ) );
    }

    /* =========================================================
     * MODULO / PERMISSOES
     * ======================================================= */

    public function register_module() {
        if ( ! function_exists( 'pwl_register_module' ) ) {
            return;
        }

        pwl_register_module(
            'notificacoes',
            array(
                'name'         => 'Notificações',
                'version'      => self::VERSION,
                'area'         => 'Núcleo do Portal / Administração',
                'status'       => 'Em Testes',
                'description'  => 'Central corporativa de notificações da plataforma PWL.',
                'dependencies' => array(),
                'capabilities' => array(
                    'notificacoes.view'    => array( 'label' => 'Visualizar workspace de notificações', 'default' => false ),
                    'notificacoes.create'  => array( 'label' => 'Criar notificações', 'default' => false ),
                    'notificacoes.history' => array( 'label' => 'Consultar histórico de notificações', 'default' => false ),
                    'notificacoes.manage'  => array( 'label' => 'Gerenciar notificações', 'default' => false ),
                ),
            )
        );
    }

    private function workspace_user_can( $capability ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( function_exists( 'pwl_current_user_can' ) ) {
            return (bool) pwl_current_user_can( $capability );
        }

        /* Fallback seguro para instalações sem PWL Core. */
        if ( in_array( $capability, array( 'notificacoes.view', 'notificacoes.create', 'notificacoes.history' ), true ) ) {
            return current_user_can( 'edit_pages' );
        }

        return false;
    }

    /* =========================================================
     * CICLO DE VIDA / BANCO
     * ======================================================= */

    public function activate() {
        $this->create_tables();
        $this->migrate_legacy_notifications();
        update_option( 'pwl_notificacoes_db_version', self::DB_VERSION );
        $this->ensure_cron();
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'pwl_notificacoes_process_email_queue' );
        wp_clear_scheduled_hook( 'cm_notificacoes_process_email_queue' );
        wp_clear_scheduled_hook( 'pwl_notificacoes_process_push_queue' );
    }

    public function maybe_upgrade() {
        $current = get_option( 'pwl_notificacoes_db_version', get_option( 'cobremack_notificacoes_db_version', '0' ) );

        if ( version_compare( $current, self::DB_VERSION, '<' ) ) {
            $this->create_tables();
            $this->migrate_legacy_notifications();
            update_option( 'pwl_notificacoes_db_version', self::DB_VERSION );
        }

        $this->ensure_cron();
    }

    public function cron_schedules( $schedules ) {
        if ( ! isset( $schedules['pwl_every_minute'] ) ) {
            $schedules['pwl_every_minute'] = array(
                'interval' => 60,
                'display'  => 'A cada minuto - PWL Notificações',
            );
        }
        return $schedules;
    }

    private function ensure_cron() {
        if ( ! wp_next_scheduled( 'pwl_notificacoes_process_email_queue' ) ) {
            wp_schedule_event( time() + 60, 'pwl_every_minute', 'pwl_notificacoes_process_email_queue' );
        }

        if ( ! wp_next_scheduled( 'pwl_notificacoes_process_push_queue' ) ) {
            wp_schedule_event( time() + 65, 'pwl_every_minute', 'pwl_notificacoes_process_push_queue' );
        }

        // Remove agenda antiga para evitar processamento duplicado após a migração.
        wp_clear_scheduled_hook( 'cm_notificacoes_process_email_queue' );
    }

    private function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql_notifications = "
            CREATE TABLE {$this->table_notifications} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                target_type VARCHAR(20) NOT NULL DEFAULT 'all',
                title VARCHAR(255) NOT NULL,
                message LONGTEXT NULL,
                url TEXT NULL,
                notification_type VARCHAR(50) NOT NULL DEFAULT 'geral',
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                send_email TINYINT(1) NOT NULL DEFAULT 0,
                publish_at DATETIME NOT NULL,
                expires_at DATETIME NULL,
                created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY target_type (target_type),
                KEY notification_type (notification_type),
                KEY priority (priority),
                KEY publish_at (publish_at),
                KEY expires_at (expires_at),
                KEY created_at (created_at)
            ) {$charset_collate};
        ";

        $sql_reads = "
            CREATE TABLE {$this->table_reads} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                read_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY notification_user (notification_id, user_id),
                KEY user_id (user_id),
                KEY notification_id (notification_id)
            ) {$charset_collate};
        ";

        $sql_recipients = "
            CREATE TABLE {$this->table_recipients} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                source_type VARCHAR(20) NOT NULL DEFAULT 'user',
                source_key VARCHAR(191) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY notification_user (notification_id, user_id),
                KEY notification_id (notification_id),
                KEY user_id (user_id),
                KEY source_type (source_type)
            ) {$charset_collate};
        ";

        $sql_email_queue = "
            CREATE TABLE {$this->table_email_queue} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY notification_user (notification_id, user_id),
                KEY status_available (status, available_at),
                KEY notification_id (notification_id),
                KEY user_id (user_id)
            ) {$charset_collate};
        ";

        dbDelta( $sql_notifications );
        dbDelta( $sql_reads );
        dbDelta( $sql_recipients );
        dbDelta( $sql_email_queue );
    }

    private function migrate_legacy_notifications() {
        global $wpdb;

        $wpdb->query( "UPDATE {$this->table_notifications} SET target_type = 'selected' WHERE user_id > 0" );
        $wpdb->query( "UPDATE {$this->table_notifications} SET target_type = 'all' WHERE user_id = 0 AND (target_type IS NULL OR target_type = '')" );
        $wpdb->query( "UPDATE {$this->table_notifications} SET priority = 'normal' WHERE priority IS NULL OR priority = ''" );
        $wpdb->query( "UPDATE {$this->table_notifications} SET publish_at = created_at WHERE publish_at IS NULL OR publish_at = '0000-00-00 00:00:00'" );
    }

    /* =========================================================
     * ADMIN
     * ======================================================= */

    public function admin_menu() {
        /*
         * O PWL Core é o proprietário do menu raiz "Plataforma".
         * Por padrão, o slug esperado é "pwl-platform".
         * O filtro abaixo permite alterar o slug sem modificar este plugin
         * caso o Core utilize outro identificador.
         */
        $parent_slug = apply_filters(
            'pwl_notificacoes_parent_menu_slug',
            'pwl-platform'
        );

        add_submenu_page(
            $parent_slug,
            'Notificações',
            'Notificações',
            'manage_options',
            'pwl-notificacoes',
            array( $this, 'admin_page' )
        );
    }

    public function enqueue_email_editor( $hook ) {
        if ( false !== strpos( (string) $hook, 'pwl-notificacoes' ) && function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }
    }

    private function get_available_groups() {
        /*
         * Contrato de integração com o PWL Core.
         * O Core pode registrar grupos retornando:
         * array(
         *   'grupo-chave' => 'Nome do grupo',
         * )
         */
        $groups = apply_filters( 'pwl_notificacoes_grupos_disponiveis', array() );

        if ( empty( $groups ) ) {
            $groups = apply_filters( 'cobremack_notificacoes_grupos_disponiveis', array() ); // Compatibilidade legada.
        }

        return is_array( $groups ) ? $groups : array();
    }

    private function resolve_group_user_ids( $group_keys ) {
        $resolved = array();

        foreach ( $group_keys as $group_key ) {
            $group_key = sanitize_key( $group_key );
            if ( ! $group_key ) {
                continue;
            }

            /*
             * Contrato de integração com o PWL Core.
             * O Core deve retornar IDs WordPress dos membros do grupo.
             */
            $ids = apply_filters( 'pwl_notificacoes_usuarios_do_grupo', array(), $group_key );

            if ( empty( $ids ) ) {
                $ids = apply_filters( 'cobremack_notificacoes_usuarios_do_grupo', array(), $group_key ); // Compatibilidade legada.
            }

            if ( ! is_array( $ids ) ) {
                continue;
            }

            foreach ( $ids as $id ) {
                $id = absint( $id );
                if ( $id > 0 && get_userdata( $id ) ) {
                    $resolved[ $id ] = $group_key;
                }
            }
        }

        return $resolved;
    }

    /** Carrega o contrato oficial Admin UI somente na tela deste modulo. */
    public function enqueue_design_system_admin( $hook ) {
        if ( in_array( $hook, array( 'plataforma_page_pwl-notificacoes', 'pwl-platform_page_pwl-notificacoes' ), true ) && function_exists( 'pwl_core_admin_ui_enqueue' ) ) {
            pwl_core_admin_ui_enqueue();
        }
    }

    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        $notifications = $wpdb->get_results(
            "SELECT * FROM {$this->table_notifications} ORDER BY id DESC LIMIT 100"
        );

        $users = get_users(
            array(
                'orderby' => 'display_name',
                'order'   => 'ASC',
            )
        );

        $groups         = $this->get_available_groups();
        $created        = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
        $deleted        = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
        $initial_tab = $created ? 'history' : ( isset( $_GET['email_module'] ) ? 'email' : 'compose' );
        $email_modules = $this->get_email_modules();
        $email_module_key = isset( $_GET['email_module'] ) ? sanitize_key( wp_unslash( $_GET['email_module'] ) ) : 'caixinha';
        if ( ! isset( $email_modules[ $email_module_key ] ) ) { $email_module_key = 'caixinha'; }
        $email_module = $email_modules[ $email_module_key ];
        $email_templates = $this->get_email_module_templates();
        $email_template = isset( $email_templates[ $email_module_key ] ) ? $email_templates[ $email_module_key ] : $email_module;
        ?>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <div class="wrap pwln-admin pwl-ui" data-initial-tab="<?php echo esc_attr( $initial_tab ); ?>">

            <div class="pwln-header">
                <div>
                    <span class="pwln-eyebrow">NÚCLEO DO PORTAL / ADMINISTRAÇÃO</span>
                    <h1>Notificações</h1>
                    <p>Crie, distribua e acompanhe notificações corporativas com múltiplos destinatários, canais, prioridade, agendamento, validade e e-mail HTML.</p>
                </div>
                <div class="pwln-version"><span>Versão</span><strong><?php echo esc_html( self::VERSION ); ?></strong></div>
            </div>

            <nav class="pwln-tabs" aria-label="Navegação do módulo de notificações">
                <button type="button" class="pwln-tab <?php echo 'compose' === $initial_tab ? 'is-active' : ''; ?>" data-main-tab="compose">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <span class="pwln-tab-copy"><strong>Nova notificação</strong><small>Conteúdo, destinatários, canais e período</small></span>
                </button>
                <button type="button" class="pwln-tab <?php echo 'email' === $initial_tab ? 'is-active' : ''; ?>" data-main-tab="email">
                    <span class="dashicons dashicons-email-alt"></span>
                    <span class="pwln-tab-copy"><strong>Modelos de e-mail</strong><small>Edite o conteúdo por módulo</small></span>
                </button>
                <button type="button" class="pwln-tab <?php echo 'history' === $initial_tab ? 'is-active' : ''; ?>" data-main-tab="history">
                    <span class="dashicons dashicons-list-view"></span>
                    <span class="pwln-tab-copy"><strong>Histórico</strong><small>Leituras, entregas e notificações enviadas</small></span>
                </button>
                <button type="button" class="pwln-tab pwln-tab--secondary" data-main-tab="about">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span class="pwln-tab-copy"><strong>Sobre</strong><small>Versão, contratos e dependências</small></span>
                </button>
            </nav>

            <?php if ( $created ) : ?>
                <div class="notice notice-success is-dismissible"><p>Notificação publicada com sucesso.</p></div>
            <?php endif; ?>
            <?php if ( $deleted ) : ?>
                <div class="notice notice-success is-dismissible"><p>Notificação excluída com sucesso.</p></div>
            <?php endif; ?>
            <section class="pwln-main-panel <?php echo 'compose' === $initial_tab ? 'is-active' : ''; ?>" data-main-panel="compose">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pwlNotificationForm">
                    <input type="hidden" name="action" value="pwl_criar_notificacao">
                    <?php wp_nonce_field( 'pwl_criar_notificacao', 'pwl_notification_nonce' ); ?>

                    <div class="pwln-toolbar">
                        <div>
                            <span class="pwln-section-kicker">NOVA NOTIFICAÇÃO</span>
                            <h2>Criar notificação</h2>
                            <p>Configure a comunicação completa nesta tela sem alterar os contratos do módulo.</p>
                        </div>
                        <div class="pwln-toolbar-actions">
                            <button type="submit" class="button button-primary">Publicar notificação</button>
                        </div>
                    </div>

                    <div class="pwln-create-layout">
                        <div class="pwln-create-top">
                            <section class="pwln-panel pwln-content-panel">
                                <div class="pwln-panel-head">
                                    <span class="dashicons dashicons-edit"></span>
                                    <div><h3>Conteúdo</h3><p>Título, mensagem, link e classificação da notificação.</p></div>
                                </div>
                                <div class="pwln-panel-body">
                                    <label class="pwln-field"><span>Título</span><input id="cm_title" type="text" name="title" required></label>
                                    <label class="pwln-field"><span>Mensagem</span><textarea id="cm_message" name="message" rows="8"></textarea></label>
                                    <label class="pwln-field"><span>Link de destino</span><input id="cm_url" type="url" name="url" placeholder="https://portal.exemplo.com/..."></label>
                                    <div class="pwln-two">
                                        <label class="pwln-field"><span>Tipo</span><select id="cm_type" name="notification_type"><option value="geral">Geral</option><option value="comunicado">Comunicado</option><option value="campanha">Campanha</option><option value="rh">Recursos Humanos</option><option value="ti">Tecnologia da Informação</option><option value="contratacao">Nova contratação</option><option value="enquete">Enquete</option></select></label>
                                        <label class="pwln-field"><span>Prioridade</span><select id="cm_priority" name="priority"><option value="normal">Normal</option><option value="importante">Importante</option><option value="urgente">Urgente</option></select></label>
                                    </div>
                                </div>
                            </section>

                            <div class="pwln-side-stack">
                                <section class="pwln-panel">
                                    <div class="pwln-panel-head">
                                        <span class="dashicons dashicons-groups"></span>
                                        <div><h3>Destinatários</h3><p>Envie para todos, usuários específicos ou grupos.</p></div>
                                    </div>
                                    <div class="pwln-panel-body">
                                        <div class="pwln-choices">
                                            <label class="pwln-radiocard compact"><input type="radio" name="audience" value="all" checked><span><strong>Todos os usuários</strong><small>Entrega para toda a base elegível.</small></span></label>
                                            <label class="pwln-radiocard compact"><input type="radio" name="audience" value="selected"><span><strong>Selecionar usuários</strong><small>Escolha um ou vários usuários.</small></span></label>
                                            <?php if ( ! empty( $groups ) ) : ?>
                                                <label class="pwln-radiocard compact"><input type="radio" name="audience" value="groups"><span><strong>Selecionar grupos</strong><small>Resolve os membros via PWL Core.</small></span></label>
                                            <?php endif; ?>
                                        </div>

                                        <div class="pwln-recipient-selector" id="pwlRecipientSelector" style="display:none;">
                                            <div class="pwln-recipient-toolbar">
                                                <input type="search" id="pwlRecipientSearch" class="pwln-recipient-search" placeholder="Pesquisar usuário..." autocomplete="off">
                                                <div class="pwln-recipient-actions"><button type="button" id="pwlSelectVisible">Selecionar visíveis</button><button type="button" id="pwlClearSelection">Limpar</button></div>
                                            </div>
                                            <div class="pwln-recipient-list" id="pwlRecipientList">
                                                <?php foreach ( $users as $user ) : ?>
                                                    <?php $search = strtolower( $user->display_name . ' ' . $user->user_login . ' ' . $user->user_email ); ?>
                                                    <label class="pwln-recipient-item" data-search="<?php echo esc_attr( $search ); ?>">
                                                        <input type="checkbox" name="user_ids[]" value="<?php echo esc_attr( $user->ID ); ?>" class="pwl-recipient-checkbox">
                                                        <span><strong><?php echo esc_html( $user->display_name ); ?></strong><span class="pwln-recipient-login"> | <?php echo esc_html( $user->user_login ); ?> | <?php echo esc_html( $user->user_email ); ?></span></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="pwln-recipient-summary" id="pwlRecipientSummary">Nenhum usuário selecionado.</div>
                                        </div>

                                        <?php if ( ! empty( $groups ) ) : ?>
                                            <div id="pwlGroupSelector" class="pwln-group-selector" style="display:none;">
                                                <div class="pwln-group-list">
                                                    <?php foreach ( $groups as $group_key => $group_name ) : ?>
                                                        <label class="pwln-group-pill"><input type="checkbox" name="group_keys[]" value="<?php echo esc_attr( sanitize_key( $group_key ) ); ?>" class="pwl-group-checkbox"><?php echo esc_html( $group_name ); ?></label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <small>Os membros são resolvidos pelo contrato de integração do PWL Core no momento do envio.</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <section class="pwln-panel">
                                    <div class="pwln-panel-head">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <div><h3>Período</h3><p>Agendamento de publicação e validade.</p></div>
                                    </div>
                                    <div class="pwln-panel-body">
                                        <div class="pwln-two">
                                            <label class="pwln-field"><span>Publicar em</span><input id="cm_publish_at" type="datetime-local" name="publish_at"><small>Em branco publica imediatamente.</small></label>
                                            <label class="pwln-field"><span>Expira em</span><input id="cm_expires_at" type="datetime-local" name="expires_at"><small>Opcional. Após esse horário, deixa de aparecer no sino.</small></label>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div class="pwln-create-bottom">
                            <section class="pwln-panel">
                                <div class="pwln-panel-head">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    <div><h3>Canais</h3><p>Defina como a notificação será entregue.</p></div>
                                </div>
                                <div class="pwln-panel-body">
                                    <label class="pwln-switchrow"><input type="checkbox" checked disabled><span class="pwln-switch"></span><span><strong>Sino do Portal</strong><small>Canal principal e sempre ativo para a publicação.</small></span></label>
                                    <label class="pwln-switchrow"><input type="checkbox" name="send_email" value="1"><span class="pwln-switch"></span><span><strong>Enviar também por e-mail</strong><small>Processado em fila para não travar a publicação.</small></span></label>
                                </div>
                            </section>

                            <section class="pwln-panel">
                                <div class="pwln-panel-head">
                                    <span class="dashicons dashicons-shield"></span>
                                    <div><h3>Comportamento</h3><p>Resumo das regras já configuradas no módulo.</p></div>
                                </div>
                                <div class="pwln-panel-body">
                                    <div class="pwln-info"><span class="dashicons dashicons-info-outline"></span><p>As preferências individuais de sino, e-mail e desktop continuam sendo respeitadas. O envio não altera nenhuma configuração existente do usuário.</p></div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="pwln-bottom"><div><strong>Nova notificação</strong><span>Revise conteúdo, destinatários, canais e período antes de publicar.</span></div><div><button type="submit" class="button button-primary">Publicar notificação</button></div></div>
                </form>
            </section>

            <section class="pwln-main-panel <?php echo 'email' === $initial_tab ? 'is-active' : ''; ?>" data-main-panel="email">
                <div class="pwln-toolbar"><div><span class="pwln-section-kicker">EDITOR DE MODELOS</span><h2>Modelos por módulo</h2><p>Formate o conteúdo dos e-mails com um editor visual. A identidade global continua no PWL Core.</p></div></div>
                <?php if ( isset( $_GET['email_saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Modelo de e-mail salvo.</p></div><?php endif; ?>
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="pwln-panel" style="margin-bottom:16px;">
                    <input type="hidden" name="page" value="pwl-notificacoes">
                    <div class="pwln-panel-body pwln-email-grid">
                        <section>
                            <label class="pwln-field"><span>Módulo</span><select name="email_module" id="pwlnEmailModule" onchange="this.form.submit();">
                                <?php foreach ( $email_modules as $module_key => $module_config ) : ?><option value="<?php echo esc_attr( $module_key ); ?>" <?php selected( $email_module_key, $module_key ); ?>><?php echo esc_html( $module_config['label'] ); ?></option><?php endforeach; ?>
                            </select></label>
                            <p class="description"><?php echo esc_html( $email_module['description'] ); ?></p>
                            <div class="pwln-info"><span class="dashicons dashicons-info-outline"></span><p>Use o editor como em um documento: selecione o texto e aplique negrito, listas, links e alinhamento. Os campos entre chaves serão preenchidos automaticamente pelo módulo.</p></div>
                        </section>
                        <section class="pwln-panel pwln-email-preview-card">
                            <div class="pwln-panel-head"><span class="dashicons dashicons-visibility"></span><div><h3>Prévia</h3><p>Exemplo com dados fictícios. A identidade visual é carregada do PWL Core.</p></div></div>
                            <iframe id="pwlnEmailPreview" title="Prévia do modelo de e-mail" sandbox="" srcdoc="<?php echo esc_attr( $this->build_email_template_preview( $email_module_key, $email_module, $email_template ) ); ?>" style="width:100%;height:430px;border:0;background:#f4f6f8;"></iframe>
                        </section>
                    </div>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pwln-panel">
                    <input type="hidden" name="action" value="pwl_notificacoes_save_email_module_template">
                    <input type="hidden" name="module" value="<?php echo esc_attr( $email_module_key ); ?>">
                    <?php wp_nonce_field( 'pwl_notificacoes_save_email_module_template', 'pwl_email_module_nonce' ); ?>
                    <div class="pwln-panel-head"><span class="dashicons dashicons-edit"></span><div><h3>Conteúdo do e-mail</h3><p><?php echo esc_html( $email_module['label'] ); ?> · use os campos disponíveis para personalizar as informações.</p></div></div>
                    <div class="pwln-panel-body">
                        <label class="pwln-field"><span>Texto do botão</span><input type="text" id="pwlnEmailButtonText" name="button_text" value="<?php echo esc_attr( $email_template['button_text'] ); ?>" placeholder="Deixe vazio para não exibir botão"><small>O botão usa o link de destino gerado pelo módulo. O PWL Core controla a cor.</small></label>
                        <?php wp_editor( $email_template['content'], 'pwln_email_content_template', array( 'textarea_name' => 'content', 'textarea_rows' => 14, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true, 'tinymce' => array( 'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,undo,redo', 'toolbar2' => 'forecolor,backcolor,removeformat,code', 'height' => 320 ) ) ); ?>
                        <div class="pwln-email-tokens"><strong>Campos disponíveis neste módulo</strong><div><?php foreach ( $email_module['tokens'] as $token => $label ) : ?><code><?php echo esc_html( '{' . $token . '}' ); ?></code> <?php echo esc_html( $label ); ?> &nbsp; <?php endforeach; ?></div></div>
                    </div>
                    <div class="pwln-bottom"><div><strong>Modelo de <?php echo esc_html( $email_module['label'] ); ?></strong><span>O título, a saudação e a identidade visual vêm do sistema; este modelo controla o conteúdo e o botão.</span></div><div><button type="submit" class="button button-primary">Salvar modelo</button></div></div>
                </form>
                <section class="pwln-panel" style="margin-top:16px;"><div class="pwln-panel-body">
                    <div class="pwln-info"><span class="dashicons dashicons-admin-customizer"></span><p>Nome da marca, logotipo, cor e rodapé são configurações globais do PWL Core. <a href="<?php echo esc_url( admin_url( 'admin.php?page=pwl-platform-email' ) ); ?>">Abrir identidade global de e-mail no Core</a>.</p></div>
                </div></section>
                <?php $this->print_email_template_preview_script( $email_module_key, $email_template ); ?>
            </section>

            <section class="pwln-main-panel <?php echo 'history' === $initial_tab ? 'is-active' : ''; ?>" data-main-panel="history">
                <div class="pwln-toolbar"><div><span class="pwln-section-kicker">HISTÓRICO</span><h2>Notificações publicadas</h2><p>Consulte destinatários, leituras, status de e-mail e validade das últimas notificações.</p></div></div>
                <section class="pwln-panel">
                    <?php if ( ! $notifications ) : ?>
                        <div class="pwln-empty"><strong>Nenhuma notificação publicada</strong><p>As notificações enviadas aparecerão aqui.</p></div>
                    <?php else : ?>
                        <div class="pwln-tablewrap">
                            <table class="pwln-logtable">
                                <thead><tr><th>Data</th><th>Título</th><th>Tipo</th><th>Prioridade</th><th>Destinatários</th><th>Leitura</th><th>E-mail</th><th>Ação</th></tr></thead>
                                <tbody>
                                <?php foreach ( $notifications as $notification ) : ?>
                                    <?php
                                    $recipient_text = $this->get_admin_recipient_text( $notification );
                                    $report         = $this->get_notification_report( $notification );
                                    $email_report   = $this->get_email_report( $notification->id );
                                    $delete_url     = wp_nonce_url( admin_url( 'admin-post.php?action=pwl_excluir_notificacao&id=' . absint( $notification->id ) ), 'pwl_excluir_notificacao_' . absint( $notification->id ) );
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $notification->created_at ) ); ?></td>
                                        <td><strong><?php echo esc_html( $notification->title ); ?></strong><small>Publicação: <?php echo esc_html( mysql2date( 'd/m/Y H:i', $notification->publish_at ) ); ?><?php if ( ! empty( $notification->expires_at ) ) : ?><br>Expira: <?php echo esc_html( mysql2date( 'd/m/Y H:i', $notification->expires_at ) ); ?><?php endif; ?></small></td>
                                        <td><span class="pwln-badge"><?php echo esc_html( ucfirst( $notification->notification_type ) ); ?></span></td>
                                        <td><span class="pwln-badge pwln-priority-<?php echo esc_attr( $notification->priority ); ?>"><?php echo esc_html( ucfirst( $notification->priority ) ); ?></span></td>
                                        <td><?php echo esc_html( $recipient_text ); ?></td>
                                        <td><strong><?php echo esc_html( $report['read'] ); ?></strong> lidas<br><?php echo esc_html( $report['pending'] ); ?> pendentes</td>
                                        <td><?php if ( ! empty( $notification->send_email ) ) : ?><?php echo esc_html( $email_report['sent'] ); ?> enviados<br><?php echo esc_html( $email_report['pending'] ); ?> pendentes<br><?php echo esc_html( $email_report['failed'] ); ?> falhas<?php else : ?>Não solicitado<?php endif; ?></td>
                                        <td><a class="pwln-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Excluir esta notificação?');">Excluir</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="pwln-main-panel" data-main-panel="about">
                <div class="pwln-toolbar"><div><span class="pwln-section-kicker">SOBRE O MÓDULO</span><p>Central reutilizável para notificações corporativas, e-mail em fila, preferências e integração com módulos PWL.</p></div></div>
                <div class="pwln-created-grid pwln-about-grid">
                    <article class="pwln-created-card"><div class="pwln-created-head"><span class="dashicons dashicons-admin-plugins"></span></div><div class="pwln-created-body"><h3>Versão</h3><strong><?php echo esc_html( self::VERSION ); ?></strong></div></article>
                    <article class="pwln-created-card"><div class="pwln-created-head"><span class="dashicons dashicons-category"></span></div><div class="pwln-created-body"><h3>Área</h3><strong>Núcleo do Portal / Administração</strong></div></article>
                    <article class="pwln-created-card"><div class="pwln-created-head"><span class="dashicons dashicons-controls-play"></span></div><div class="pwln-created-body"><h3>Status</h3><strong>Em Testes</strong></div></article>
                    <article class="pwln-created-card"><div class="pwln-created-head"><span class="dashicons dashicons-shortcode"></span></div><div class="pwln-created-body"><h3>Shortcode</h3><strong><code>[pwl_notificacoes_preferencias]</code></strong></div></article>
                </div>
                <section class="pwln-panel pwln-about-notes"><div class="pwln-panel-body"><h3>Compatibilidade</h3><p>Contratos legados <code>cm_*</code>, shortcode anterior e tabelas existentes permanecem preservados para evitar quebra de consumidores implantados.</p><h3>Dependências</h3><p>WordPress e menu administrativo <strong>Plataforma</strong> fornecido pelo PWL Core. A integração com grupos depende dos filtros públicos já definidos pelo módulo.</p><details class="pwln-about-changelog"><summary>Changelog</summary><div class="pwln-about-changelog__body"><p><strong>1.10.3</strong> - Corrigido o preenchimento dos tokens de descrição e rastreio no modelo de e-mail de encomendas, com compatibilidade para os rótulos Descrição e Embalagem.</p><p><strong>1.10.2</strong> - Conversão visual Admin/Workspace para contratos oficiais do Design System, com referência Encomendas 1.0.0 e tokens dinâmicos; sem alteração funcional.</p><p><strong>1.10.1</strong> - API pública para módulos renderizarem e-mails diretos com seus modelos editáveis e o layout compartilhado do PWL Core.</p><p><strong>1.10.0</strong> - Modelos de e-mail gerenciados por módulo com editor visual, tokens e prévia ao vivo; identidade global preservada no PWL Core.</p><p><strong>1.9.2</strong> - Rótulo “Código interno da Encomenda” e ramal da Portaria adicionados ao formato do aviso.</p><p><strong>1.9.1</strong> - E-mails de encomendas formatados sem etiquetas, botão ou título duplicado; assunto sem prioridade.</p><p><strong>1.9.0</strong> - Layout global de e-mail e identidade visual passam a ser administrados pelo PWL Core; envio consome o renderizador compartilhado, mantendo fila, assunto e conteúdo do módulo.</p><p><strong>1.8.4</strong> - Processamento assíncrono por requisição interna autenticada com token de uso único, lote encadeado e fallback WP-Cron.</p><p><strong>1.8.3</strong> - Processamento pontual imediato da fila Web Push com disparo assíncrono do WP-Cron e manutenção da rotina recorrente de contingência.</p><p><strong>1.8.2</strong> - Correção de disponibilização da rota do Service Worker.</p><p><strong>1.8.1</strong> - Convite de ativação no Portal com autorização por clique, cadastro do dispositivo e preservação das preferências de sino/e-mail.</p><p><strong>1.8.0</strong> - Web Push: assinaturas por dispositivo, Service Worker isolado, envio criptografado, fila, agendamentos e preferências.</p><p><strong>1.7.5</strong> - Painel administrativo ampliado à largura útil, abas inativas sem borda, título principal mais leve, destinatários com seleção laranja sólida e changelog recolhido na aba Sobre. Sem alteração funcional.</p><p><strong>1.7.4</strong> - Aplicados ao Admin UI, Portal UI e preferências os padrões de interface do PWL Caixinha: paleta, tipografia, cartões Air Glass, abas laranja, campos e espaçamentos. Sem alteração funcional.</p><p><strong>1.7.3</strong> - Fundo claro da página do Workspace ajustado para cinza neutro <code>#F0F0F0</code>, preservando cards e painéis brancos.</p><p><strong>1.7.2</strong> - Corrigido o refinamento visual do Workspace: áreas originalmente brancas permanecem brancas e somente o tom auxiliar antes azulado passa a usar cinza neutro <code>#F7F7F7</code>.</p><p><strong>1.7.1</strong> - Ajustado o fundo claro do Workspace para cinza neutro <code>#F7F7F7</code>, preservando cards brancos e bordas existentes.</p><p><strong>1.7.0</strong> - Adicionado Workspace operacional via shortcode <code>[pwl_notificacoes_workspace]</code>, permissões PWL específicas e criação compartilhando a mesma lógica de negócio do painel técnico.</p></div></details><details class="pwln-about-changelog"><summary>Log de versões</summary><div class="pwln-about-changelog__body"><p><strong>1.10.3 (31/10/2026)</strong> - Parser dos modelos de encomenda compatível com os rótulos Descrição e Embalagem.</p><p><strong>1.10.2 (16/10/2026)</strong> - Integração visual aos tokens semânticos do Core e Design System 1.3.1, preservando recursos e dados.</p><p><strong>1.10.1 (15/10/2026)</strong> - API pública para módulos aplicarem os modelos editáveis também em envios diretos.</p><p><strong>1.10.0 (15/10/2026)</strong> - Editor visual e prévia ao vivo para modelos de e-mail por módulo.</p><p><strong>1.9.2 (15/10/2026)</strong> - Novo rótulo do código interno e ramal da Portaria formatados no aviso.</p><p><strong>1.9.1 (15/10/2026)</strong> - Formatação específica do e-mail de encomendas, sem etiquetas, botão ou prévia duplicada.</p><p><strong>1.9.0 (15/10/2026)</strong> - Integração ao serviço compartilhado de layout HTML do PWL Core e remoção da configuração visual duplicada no módulo.</p><p><strong>1.8.4 (15/10/2026)</strong> - Disparo Web Push imediato via loopback, sem bloquear publicação; registro da conclusão do lote e manutenção de contingência.</p><p><strong>1.8.3 (15/10/2026)</strong> - Adicionado agendamento pontual e tentativa de início imediato do cron após publicar, sem bloquear o formulário.</p><p><strong>1.8.2 (15/10/2026)</strong> - Corrigida rota do Service Worker.</p><p><strong>1.8.1 (15/10/2026)</strong> - Adicionado convite contextual no frontend, registro após consentimento e endpoint para ativar somente o canal desktop.</p><p><strong>1.8.0 (15/10/2026)</strong> - Implementada entrega Web Push com VAPID, criptografia AES-128-GCM e tentativas controladas, preservando o módulo 1.7.5.</p><p><strong>1.7.5</strong> - Ajustes de largura, navegação, destinatários e leitura do changelog na aba Sobre.</p><p><strong>1.7.4</strong> - Unificada a apresentação do módulo com a linguagem visual do Caixinha, preservando os fluxos atuais.</p><p><strong>1.7.3</strong> - Substituído o tom claro de fundo do Workspace por <code>#F0F0F0</code>, sem alterar áreas brancas, bordas, lógica ou modo escuro.</p><p><strong>1.7.2</strong> - Removida a aplicação de cinza no container principal e alterado apenas o token visual auxiliar do Workspace para <code>#F7F7F7</code>.</p><p><strong>1.7.1</strong> - Refinamento visual do fundo do Workspace, sem alteração funcional.</p><p><strong>1.7.0</strong> - Interface operacional adicionada sem alteração das tabelas, filas, endpoints legados ou configurações técnicas existentes.</p></div></details></div></section>
            </section>
        </div>

        <style>
        .pwln-admin{--o:#ff5a00;--os:#fff1e8;--ink:#1f2937;--text:#667085;--mut:#98a2b3;--line:#e4e7ec;--panel:#fff;--soft:#f8fafc;--danger:#b42318;font-family:"Plus Jakarta Sans",Arial,sans-serif}.pwln-admin *{box-sizing:border-box}.pwln-admin h1,.pwln-admin h2,.pwln-admin h3,.pwln-admin p,.pwln-admin span:not(.dashicons),.pwln-admin label,.pwln-admin input,.pwln-admin textarea,.pwln-admin select,.pwln-admin button,.pwln-admin .button,.pwln-admin a,.pwln-admin code,.pwln-admin strong,.pwln-admin small{font-family:"Plus Jakarta Sans",Arial,sans-serif!important}
        .pwln-header,.pwln-toolbar,.pwln-bottom{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.pwln-header{margin:18px 0}.pwln-eyebrow,.pwln-section-kicker{color:var(--o);font-size:9.9px;font-weight:800;line-height:1.2;letter-spacing:.12em}.pwln-header h1{margin:3px 0 0;color:var(--ink);font-size:29.7px!important;font-weight:600!important;line-height:1.2!important}.pwln-header p{margin:6px 0 0;color:var(--text);font-size:14.3px;font-weight:400;line-height:1.6}.pwln-toolbar h2{margin:3px 0 0;color:var(--ink);font-size:22px!important;font-weight:600!important;line-height:1.3!important}.pwln-toolbar p{margin:6px 0 0;color:var(--text)}.pwln-version{padding:10px 14px;border:1px solid var(--line);border-radius:12px;background:var(--panel)}.pwln-version span{display:block;font-size:10px;color:var(--mut)}
        .pwln-tabs{display:flex;align-items:stretch;justify-content:flex-start;gap:4px;margin:0 0 18px;padding:4px;border:1px solid var(--line);border-radius:13px;background:var(--panel);overflow-x:auto}.pwln-tab{position:relative;display:flex;align-items:center;gap:9px;min-width:180px;padding:10px 13px;border:1px solid transparent;border-radius:9px;color:var(--text);background:transparent;text-align:left;white-space:nowrap;cursor:pointer;transition:.16s}.pwln-tab>.dashicons{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex:0 0 28px;border-radius:7px;background:var(--soft);color:var(--mut);font-size:16px}.pwln-tab-copy{min-width:0}.pwln-tab strong{display:block;color:var(--ink);font-size:12px;line-height:1.25}.pwln-tab small{display:block;margin-top:2px;color:var(--mut);font-size:9.5px;line-height:1.2}.pwln-tab:hover{background:var(--soft);color:var(--o)}.pwln-tab:hover strong,.pwln-tab:hover>.dashicons{color:var(--o)}.pwln-tab.is-active{background:#f1f3f5}.pwln-tab.is-active>.dashicons{background:#e8ebef;color:var(--mut)}.pwln-tab--secondary{min-width:160px}
        .pwln-main-panel{display:none}.pwln-main-panel.is-active{display:block}.pwln-toolbar{margin:18px 0}.pwln-toolbar-actions{display:flex;gap:8px}.pwln-panel{overflow:hidden;background:var(--panel);border:1px solid var(--line);border-radius:14px}.pwln-panel-head{display:flex;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line)}.pwln-panel-head>.dashicons{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:var(--os);color:var(--o)}.pwln-panel-head h3{margin:0;color:var(--ink);font-size:15.4px!important;font-weight:600!important;line-height:1.35!important}.pwln-panel-head p{margin:3px 0 0;color:var(--text);font-size:11px}.pwln-panel-body{padding:18px}.pwln-content-panel{min-width:0}
        .pwln-create-layout{display:grid;gap:16px}.pwln-create-top{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(360px,.65fr);gap:16px;align-items:start}.pwln-side-stack{display:grid;gap:16px;align-content:start}.pwln-create-bottom{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}.pwln-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .pwln-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}.pwln-field>span{font-size:12px;font-weight:700;color:var(--ink)}.pwln-field small{font-size:10.5px;color:var(--mut)}.pwln-field input,.pwln-field select,.pwln-field textarea{width:100%;min-height:42px;border:1px solid var(--line);border-radius:9px;background:var(--panel);color:var(--ink)}.pwln-field textarea{padding:10px;resize:vertical}.pwln-field input:focus,.pwln-field textarea:focus,.pwln-field select:focus{border-color:var(--o);box-shadow:0 0 0 3px rgba(255,90,0,.10);outline:0}
        .pwln-choices{display:grid;grid-template-columns:1fr;gap:8px}.pwln-radiocard{position:relative;display:block}.pwln-radiocard input{position:absolute;opacity:0}.pwln-radiocard>span{display:flex;flex-direction:column;gap:4px;padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--panel);cursor:pointer}.pwln-radiocard input:checked+span{border-color:var(--o);background:var(--os);box-shadow:0 0 0 2px rgba(255,90,0,.08)}.pwln-radiocard strong{color:var(--ink);font-size:12px}.pwln-radiocard small{color:var(--text);font-size:10.5px;line-height:1.45}.pwln-recipient-selector{margin-top:14px;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--soft)}.pwln-recipient-toolbar{display:flex;gap:8px;padding:10px;border-bottom:1px solid var(--line)}.pwln-recipient-search{flex:1;min-height:38px;border:1px solid var(--line);border-radius:8px;background:var(--panel);color:var(--ink)}.pwln-recipient-actions{display:flex;gap:6px}.pwln-recipient-actions button{border:1px solid var(--line);border-radius:7px;background:var(--panel);color:var(--ink);cursor:pointer}.pwln-recipient-list{max-height:260px;overflow:auto}.pwln-recipient-item{display:flex;gap:8px;padding:9px 11px;border-bottom:1px solid var(--line);color:var(--ink);cursor:pointer}.pwln-recipient-item:hover{background:var(--panel)}.pwln-recipient-item.pwl-hidden{display:none}.pwln-recipient-login{color:var(--mut);font-size:10px}.pwln-recipient-summary{padding:9px 11px;color:var(--mut);font-size:10px}.pwln-group-selector{margin-top:14px}.pwln-group-list{display:flex;flex-wrap:wrap;gap:8px}.pwln-group-pill{display:inline-flex;gap:7px;align-items:center;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:var(--panel);color:var(--ink)}
        .pwln-switchrow{display:flex;gap:10px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--soft);cursor:pointer;margin-bottom:10px}.pwln-switchrow input{position:absolute;opacity:0}.pwln-switch{position:relative;width:38px;height:22px;flex:0 0 38px;border-radius:99px;background:#d0d5dd}.pwln-switch:after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:.18s}.pwln-switchrow input:checked+.pwln-switch{background:var(--o)}.pwln-switchrow input:checked+.pwln-switch:after{transform:translateX(16px)}.pwln-switchrow strong{display:block;color:var(--ink);font-size:12px}.pwln-switchrow small{display:block;color:var(--text);font-size:10px}.pwln-info{display:flex;gap:10px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:var(--soft);color:var(--text);font-size:11px;line-height:1.5}.pwln-info p{margin:0}
        .pwln-bottom{position:sticky;bottom:0;z-index:20;margin-top:18px;padding:14px 16px;background:rgba(255,255,255,.96);border:1px solid var(--line);border-radius:14px;box-shadow:0 -6px 24px rgba(15,23,42,.05)}.pwln-bottom strong{display:block;color:var(--ink)}.pwln-bottom span{display:block;color:var(--text);font-size:10px}.pwln-bottom>div:last-child{display:flex;gap:8px}
        .pwln-email-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(360px,.85fr);gap:16px}.pwln-email-preview-card{overflow:hidden;border:1px solid var(--line);border-radius:14px;background:#fff}.pwln-email-preview-card .pwln-panel-head{border-bottom:1px solid var(--line)}.pwln-email-preview-card iframe{display:block;min-height:430px}.pwln-email-tokens{margin-top:18px;padding:13px;border:1px solid var(--line);border-radius:10px;background:var(--soft);color:var(--text);font-size:12px;line-height:1.9}.pwln-email-tokens strong{display:block;margin-bottom:5px;color:var(--ink)}.pwln-email-tokens code{display:inline-block;padding:2px 6px;border-radius:5px;background:#fff;color:var(--o);font-weight:700}
        .pwln-tablewrap{overflow:auto}.pwln-logtable{width:100%;min-width:1050px;border-collapse:collapse}.pwln-logtable th,.pwln-logtable td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.pwln-logtable th{color:var(--mut);font-size:10px;text-transform:uppercase}.pwln-logtable td{color:var(--text);font-size:11px}.pwln-logtable td strong,.pwln-logtable td small{display:block}.pwln-badge{display:inline-flex;padding:5px 8px;border-radius:99px;background:var(--os);color:var(--o);font-size:10px;font-weight:700}.pwln-priority-importante{background:#fff7d6;color:#8a6100}.pwln-priority-urgente{background:#ffe3e3;color:#b42318}.pwln-delete{color:var(--danger)!important;font-weight:700}.pwln-empty{padding:40px;text-align:center;color:var(--text)}
        /* Aba Histórico: tipografia 10% maior, sem alterar demais módulos/abas */
        .pwln-main-panel[data-main-panel="history"]{font-size:110%}.pwln-main-panel[data-main-panel="history"] .pwln-section-kicker{font-size:9.9px}.pwln-main-panel[data-main-panel="history"] .pwln-logtable th{font-size:11px}.pwln-main-panel[data-main-panel="history"] .pwln-logtable td{font-size:12.1px}.pwln-main-panel[data-main-panel="history"] .pwln-badge{font-size:11px}.pwln-main-panel[data-main-panel="history"] .pwln-delete{font-size:12.1px}
        .pwln-created-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.pwln-created-card{display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden}.pwln-created-head{display:flex;justify-content:space-between;padding:14px;border-bottom:1px solid var(--line)}.pwln-created-head>.dashicons{color:var(--o)}.pwln-created-body{padding:15px;flex:1}.pwln-created-body h3{margin:0 0 12px;color:var(--ink);font-size:15.4px!important;font-weight:600!important;line-height:1.35!important}.pwln-about-notes{margin-top:16px}.pwln-about-notes h3{margin:0 0 6px;color:var(--ink);font-size:15.4px!important;font-weight:600!important;line-height:1.35!important}.pwln-about-notes p{margin:0 0 16px;color:var(--text)}
        .pwln-admin .button,.pwln-admin button.button{min-height:40px;padding:0 14px;border-radius:8px!important;font-size:13px!important;font-weight:600!important;box-shadow:none!important}.pwln-admin .button-primary,.pwln-admin button.button-primary{border-color:var(--o)!important;background:var(--o)!important;color:#fff!important}.pwln-admin .button-primary:hover,.pwln-admin button.button-primary:hover{border-color:#e84f00!important;background:#e84f00!important}
        body.cm-dark .pwln-admin,body.pwl-dark .pwln-admin,html[data-theme="dark"] .pwln-admin,body[data-theme="dark"] .pwln-admin{--ink:#f7f9fc;--text:#b8c4d3;--mut:#8fa0b4;--line:#354052;--panel:#111827;--soft:#172033;--os:#3a1d10}body.cm-dark .pwln-bottom,body.pwl-dark .pwln-bottom,html[data-theme="dark"] .pwln-bottom,body[data-theme="dark"] .pwln-bottom{background:rgba(17,24,39,.96)}body.cm-dark .pwln-tab.is-active,body.pwl-dark .pwln-tab.is-active,html[data-theme="dark"] .pwln-tab.is-active,body[data-theme="dark"] .pwln-tab.is-active{background:#202938}.pwln-admin .notice{margin:0 0 18px}
        @media(max-width:1180px){.pwln-create-top{grid-template-columns:minmax(0,1.15fr) minmax(330px,.85fr)}.pwln-side-stack .pwln-two{grid-template-columns:1fr}.pwln-email-grid{grid-template-columns:1fr}}
        @media(max-width:782px){.pwln-create-top,.pwln-create-bottom,.pwln-two{grid-template-columns:1fr}.pwln-header,.pwln-toolbar,.pwln-bottom{flex-direction:column;align-items:stretch}.pwln-tabs{gap:3px;padding:3px}.pwln-tab{min-width:155px}.pwln-recipient-toolbar{flex-direction:column}.pwln-recipient-actions{flex-wrap:wrap}.pwln-bottom>div:last-child{width:100%}.pwln-bottom .button{width:100%}}

        /* PWL Caixinha visual language: Design System colors, Air Glass surfaces and rounded cards. */
        .wrap.pwln-admin{max-width:1440px;margin:12px 18px 0 0;padding:16px 10px 32px}
        .pwln-admin{--o:#ff6a00;--os:#ffdbcc;--ink:#0b1c30;--text:#545f73;--mut:#7c8ca7;--line:rgba(148,163,184,.19);--panel:rgba(255,255,255,.76);--soft:#eff4ff;--danger:#ba1a1a;background:#f8f9ff;border-radius:18px;padding:14px 16px 28px;color:var(--ink);--glass-shadow:0 8px 24px rgba(15,23,42,.045)}
        .pwln-header{align-items:center;margin:4px 0 20px;padding:22px 26px;border:1px solid rgba(148,163,184,.14);border-radius:16px;background:rgba(255,255,255,.76);box-shadow:var(--glass-shadow);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
        .pwln-eyebrow,.pwln-section-kicker{font-size:10px;letter-spacing:.08em}.pwln-header h1{font-size:32px!important;font-weight:700!important;letter-spacing:-.025em}.pwln-header p{font-size:13px;color:var(--text)}
        .pwln-version{padding:11px 15px;border:1px solid rgba(148,163,184,.16);border-radius:11px;background:rgba(239,244,255,.82);text-align:right}.pwln-version span{color:var(--text)}.pwln-version strong{color:var(--ink);font-size:13px}
        .pwln-tabs{gap:8px;padding:9px 11px;border:1px solid rgba(148,163,184,.14);border-radius:14px;background:rgba(255,255,255,.74);box-shadow:var(--glass-shadow);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
        .pwln-tab{min-width:max-content;padding:10px 15px;border-color:rgba(148,163,184,.11);border-radius:12px;background:rgba(255,255,255,.54);box-shadow:0 2px 6px rgba(15,23,42,.035)}.pwln-tab>.dashicons{background:transparent;color:var(--text)}.pwln-tab strong{color:#3c475a;font-size:12px}.pwln-tab small{display:none}.pwln-tab:hover{background:#eff4ff}.pwln-tab.is-active{border-color:#ff6a00;background:#ff6a00;color:#fff;box-shadow:0 2px 5px rgba(255,106,0,.2)}.pwln-tab.is-active strong,.pwln-tab.is-active>.dashicons{color:#fff}.pwln-tab.is-active>.dashicons{background:transparent}
        .pwln-toolbar{align-items:center;margin:16px 0}.pwln-toolbar h2{font-size:22px!important;font-weight:700!important;letter-spacing:-.015em}.pwln-toolbar p{font-size:12px;color:var(--text)}
        .pwln-panel,.pwln-created-card,.pwln-email-preview-card{border:1px solid rgba(148,163,184,.15);border-radius:15px;background:var(--panel);box-shadow:0 3px 12px rgba(15,23,42,.035);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}.pwln-panel-head{align-items:center;padding:17px 20px;border-color:var(--line)}.pwln-panel-head>.dashicons{border-radius:9px;background:#ffdbcc;color:#a14000}.pwln-panel-head h3,.pwln-created-body h3,.pwln-about-notes h3{font-weight:700!important;color:var(--ink)}.pwln-panel-head p{color:var(--text)}.pwln-panel-body{padding:21px}
        .pwln-field>span{font-size:12px;color:var(--ink)}.pwln-field input,.pwln-field select,.pwln-field textarea,.pwln-recipient-search{min-height:44px;padding:9px 12px;border:1px solid rgba(148,163,184,.28);border-radius:9px;background:rgba(255,255,255,.82);box-shadow:0 1px 3px rgba(15,23,42,.035);color:var(--ink)}.pwln-field input:focus,.pwln-field textarea:focus,.pwln-field select:focus,.pwln-recipient-search:focus{border-color:#ff6a00;box-shadow:0 0 0 3px rgba(255,106,0,.13)}
        .pwln-radiocard>span,.pwln-switchrow,.pwln-info,.pwln-group-pill{border-color:rgba(148,163,184,.17);border-radius:11px;background:rgba(239,244,255,.65)}.pwln-radiocard input:checked+span{border-color:#ff6a00;background:rgba(255,219,204,.35);box-shadow:0 0 0 2px rgba(255,106,0,.08)}.pwln-switchrow input:checked+.pwln-switch{background:#ff6a00}.pwln-info{line-height:1.55}
        .pwln-recipient-selector{border-color:rgba(148,163,184,.2);border-radius:12px;background:rgba(239,244,255,.48)}.pwln-recipient-toolbar{padding:12px}.pwln-recipient-item{padding:10px 12px;border-color:rgba(148,163,184,.13)}.pwln-recipient-item:hover{background:rgba(255,255,255,.82)}
        .pwln-bottom{padding:16px 19px;border:1px solid rgba(148,163,184,.18);border-radius:15px;background:rgba(255,255,255,.88);box-shadow:0 8px 24px rgba(15,23,42,.06);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}.pwln-bottom strong{color:var(--ink)}.pwln-bottom span{color:var(--text)}
        .pwln-admin .button,.pwln-admin button.button{border-radius:9px!important}.pwln-admin .button-primary,.pwln-admin button.button-primary{border-color:#ff6a00!important;background:#ff6a00!important;box-shadow:0 2px 5px rgba(255,106,0,.18)!important}.pwln-admin .button-primary:hover,.pwln-admin button.button-primary:hover{border-color:#e85c00!important;background:#e85c00!important}
        .pwln-created-head{padding:16px 18px;border-color:var(--line)}.pwln-created-head>.dashicons{color:#ff6a00}.pwln-created-body{padding:18px}.pwln-about-notes{margin-top:16px}.pwln-tablewrap{border-radius:12px}.pwln-logtable th{color:var(--mut)}.pwln-logtable td{color:var(--text)}.pwln-logtable tr:hover td{background:rgba(239,244,255,.45)}.pwln-badge{background:#ffdbcc;color:#763300}.pwln-priority-importante{background:#fff1cc;color:#7b5700}.pwln-priority-urgente{background:#ffdad6;color:#93000a}.pwln-empty{padding:44px 20px}
        body.cm-dark .pwln-admin,body.pwl-dark .pwln-admin,html[data-theme="dark"] .pwln-admin,body[data-theme="dark"] .pwln-admin{--ink:#eaf1ff;--text:#bcc7de;--mut:#93a4bf;--line:rgba(255,255,255,.1);--panel:rgba(29,36,47,.82);--soft:#263244;--os:rgba(255,106,0,.18);background:#101823}
        body.cm-dark .pwln-header,body.pwl-dark .pwln-header,html[data-theme="dark"] .pwln-header,body[data-theme="dark"] .pwln-header,body.cm-dark .pwln-tabs,body.pwl-dark .pwln-tabs,html[data-theme="dark"] .pwln-tabs,body[data-theme="dark"] .pwln-tabs{background:rgba(29,36,47,.78)}body.cm-dark .pwln-version,body.pwl-dark .pwln-version,html[data-theme="dark"] .pwln-version,body[data-theme="dark"] .pwln-version{background:rgba(40,47,57,.76)}body.cm-dark .pwln-tab strong,body.pwl-dark .pwln-tab strong,html[data-theme="dark"] .pwln-tab strong,body[data-theme="dark"] .pwln-tab strong{color:#bcc7de}body.cm-dark .pwln-tab.is-active strong,body.pwl-dark .pwln-tab.is-active strong,html[data-theme="dark"] .pwln-tab.is-active strong,body[data-theme="dark"] .pwln-tab.is-active strong{color:#fff}body.cm-dark .pwln-field input,body.cm-dark .pwln-field select,body.cm-dark .pwln-field textarea,body.pwl-dark .pwln-field input,body.pwl-dark .pwln-field select,body.pwl-dark .pwln-field textarea,html[data-theme="dark"] .pwln-field input,html[data-theme="dark"] .pwln-field select,html[data-theme="dark"] .pwln-field textarea,body[data-theme="dark"] .pwln-field input,body[data-theme="dark"] .pwln-field select,body[data-theme="dark"] .pwln-field textarea{background:rgba(25,31,40,.62);color:#eaf1ff}
        @media(max-width:782px){.wrap.pwln-admin{margin-right:10px;padding:10px}.pwln-header{padding:19px}.pwln-tabs{gap:7px;padding:8px}.pwln-tab{min-width:max-content}.pwln-panel-body{padding:17px}.pwln-bottom .button{width:100%}}
        /* 1.7.5 refinements requested for the technical screen. */
        .wrap.pwln-admin{width:auto;max-width:none;margin:10px 20px 0 0;padding:8px 0 28px}
        .pwln-admin{padding:12px 18px 28px}
        .pwln-header h1{font-weight:600!important}
        .pwln-tabs .pwln-tab:not(.is-active){border-color:transparent;background:transparent;box-shadow:none}
        .pwln-tabs .pwln-tab:not(.is-active):hover{background:rgba(239,244,255,.78)}
        .pwln-tabs .pwln-tab.is-active{border-color:#ff6a00;background:#ff6a00}
        .pwln-radiocard input:checked+span{border-color:#ff6a00;background:#ff6a00;box-shadow:0 2px 5px rgba(255,106,0,.18)}
        .pwln-radiocard input:checked+span strong,.pwln-radiocard input:checked+span small{color:#fff}
        .pwln-group-pill input,.pwln-recipient-item input{accent-color:#ff6a00}
        .pwln-group-pill:has(input:checked){border-color:#ff6a00;background:#ff6a00;color:#fff;box-shadow:0 2px 5px rgba(255,106,0,.18)}
        .pwln-group-pill:has(input:checked) input{accent-color:#fff}
        .pwln-about-notes>.pwln-panel-body>h3:first-child{margin-top:0}
        .pwln-about-changelog{margin-top:12px;border-bottom:1px solid var(--line)}
        .pwln-about-changelog>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 2px;color:var(--ink);font-size:14px;font-weight:700;list-style:none;cursor:pointer}
        .pwln-about-changelog>summary::-webkit-details-marker{display:none}
        .pwln-about-changelog>summary:after{content:"";width:8px;height:8px;flex:0 0 8px;border-right:2px solid var(--mut);border-bottom:2px solid var(--mut);transform:rotate(45deg);transition:transform .15s}
        .pwln-about-changelog[open]>summary:after{transform:rotate(225deg)}
        .pwln-about-changelog__body{padding:0 12px 8px 18px}
        .pwln-about-changelog__body p{margin:0 0 12px;line-height:1.6}
        @media(max-width:782px){.wrap.pwln-admin{margin-right:10px;padding:8px 0 24px}.pwln-admin{padding:10px 12px 24px}}

        
/* 1.10.2 - Encomendas Admin UI / Core semantic contract. */
.pwl-ui.pwln-admin{--o:var(--pwl-ui-orange);--ink:var(--pwl-ui-ink);--text:var(--pwl-ui-text);--mut:var(--pwl-ui-muted);--line:var(--pwl-ui-line);--panel:var(--pwl-ui-panel);--soft:var(--pwl-ui-soft);--os:var(--pwl-ui-orange-soft);--danger:var(--pwl-ui-danger);font-family:var(--pwl-ui-font-family);color:var(--pwl-ui-ink);background:var(--pwl-ui-canvas);color-scheme:normal}
.pwl-ui.pwln-admin .pwln-header,.pwl-ui.pwln-admin .pwln-tabs,.pwl-ui.pwln-admin .pwln-panel,.pwl-ui.pwln-admin .pwln-card,.pwl-ui.pwln-admin .pwln-created-card,.pwl-ui.pwln-admin .pwln-bottom,.pwl-ui.pwln-admin .pwln-email-preview-card{background:var(--pwl-ui-panel);border-color:var(--pwl-ui-line);box-shadow:var(--pwl-ui-shadow-card)}
.pwl-ui.pwln-admin .pwln-header h1{font-family:var(--pwl-ui-font-family);color:var(--pwl-ui-ink)}
.pwl-ui.pwln-admin .pwln-header p,.pwl-ui.pwln-admin .pwln-tab small{color:var(--pwl-ui-text)}
.pwl-ui.pwln-admin .pwln-version{background:var(--pwl-ui-soft);border-color:var(--pwl-ui-line)}
.pwl-ui.pwln-admin .pwln-tab:not(.is-active){background:transparent;border-color:transparent;box-shadow:none}
.pwl-ui.pwln-admin .pwln-tab.is-active,.pwl-ui.pwln-admin .pwln-radiocard input:checked+span,.pwl-ui.pwln-admin .pwln-group-pill:has(input:checked){background:var(--pwl-ui-orange);border-color:var(--pwl-ui-orange);box-shadow:none}
.pwl-ui.pwln-admin .pwln-field input,.pwl-ui.pwln-admin .pwln-field select,.pwl-ui.pwln-admin .pwln-field textarea{background:var(--pwl-ui-field);color:var(--pwl-ui-ink);border-color:var(--pwl-ui-line);border-radius:var(--pwl-ui-control-radius);font-family:var(--pwl-ui-font-family)}
.pwl-ui.pwln-admin .pwln-field input:focus,.pwl-ui.pwln-admin .pwln-field select:focus,.pwl-ui.pwln-admin .pwln-field textarea:focus{border-color:var(--pwl-ui-orange);box-shadow:var(--pwl-ui-shadow-focus)}
.pwl-ui.pwln-admin .button-primary,.pwl-ui.pwln-admin button.button-primary{background:var(--pwl-ui-orange)!important;border-color:var(--pwl-ui-orange)!important;box-shadow:none!important}
.pwl-ui.pwln-admin .button-primary:hover,.pwl-ui.pwln-admin button.button-primary:hover{background:var(--pwl-ui-orange-hover)!important;border-color:var(--pwl-ui-orange-hover)!important}
.pwl-ui.pwln-admin .pwln-recipient-selector,.pwl-ui.pwln-admin .pwln-switchrow,.pwl-ui.pwln-admin .pwln-info,.pwl-ui.pwln-admin .pwln-email-tokens{background:var(--pwl-ui-soft);border-color:var(--pwl-ui-line)}
.pwl-ui.pwln-admin .pwln-recipient-item:hover,.pwl-ui.pwln-admin .pwln-logtable tr:hover td{background:var(--pwl-ui-soft)}
.pwl-ui.pwln-admin .pwln-group-pill input,.pwl-ui.pwln-admin .pwln-recipient-item input{accent-color:var(--pwl-ui-orange)}
.pwl-ui.pwln-admin .pwln-badge{background:var(--pwl-ui-orange-soft);color:var(--pwl-ui-orange)}
.pwl-ui.pwln-admin .pwln-priority-importante{background:var(--pwl-ui-soft);color:var(--pwl-ui-ink)}
.pwl-ui.pwln-admin .pwln-priority-urgente{background:var(--pwl-ui-danger-soft);color:var(--pwl-ui-danger)}
.pwl-ui.pwln-admin .pwln-bottom{backdrop-filter:none}
</style>

        <script>
        (function(){
            "use strict";
            const mainTabs=document.querySelectorAll('.pwln-tab');
            const mainPanels=document.querySelectorAll('.pwln-main-panel');
            const form=document.getElementById('pwlNotificationForm');
            const selector=document.getElementById('pwlRecipientSelector');
            const groupSelector=document.getElementById('pwlGroupSelector');
            const search=document.getElementById('pwlRecipientSearch');
            const list=document.getElementById('pwlRecipientList');
            const summary=document.getElementById('pwlRecipientSummary');
            const selectVisible=document.getElementById('pwlSelectVisible');
            const clearSelection=document.getElementById('pwlClearSelection');
            const audienceRadios=document.querySelectorAll('input[name="audience"]');
            mainTabs.forEach(function(tab){tab.addEventListener('click',function(){mainTabs.forEach(function(i){i.classList.remove('is-active')});mainPanels.forEach(function(p){p.classList.remove('is-active')});tab.classList.add('is-active');const panel=document.querySelector('[data-main-panel="'+tab.dataset.mainTab+'"]');if(panel)panel.classList.add('is-active')})});
            function selectedAudience(){const c=document.querySelector('input[name="audience"]:checked');return c?c.value:'all'}
            function updateAudience(){const a=selectedAudience();if(selector)selector.style.display=a==='selected'?'block':'none';if(groupSelector)groupSelector.style.display=a==='groups'?'block':'none'}
            function updateSummary(){if(!summary)return;const t=document.querySelectorAll('.pwl-recipient-checkbox:checked').length;summary.textContent=t===0?'Nenhum usuário selecionado.':(t===1?'1 usuário selecionado.':t+' usuários selecionados.')}
            function filterUsers(){if(!list)return;const t=(search?search.value:'').trim().toLowerCase();list.querySelectorAll('.pwln-recipient-item').forEach(function(item){const h=(item.dataset.search||'').toLowerCase();item.classList.toggle('pwl-hidden',!!t&&h.indexOf(t)===-1)})}
            audienceRadios.forEach(function(r){r.addEventListener('change',updateAudience)});document.querySelectorAll('.pwl-recipient-checkbox').forEach(function(cb){cb.addEventListener('change',updateSummary)});if(search)search.addEventListener('input',filterUsers);
            if(selectVisible&&list){selectVisible.addEventListener('click',function(){list.querySelectorAll('.pwln-recipient-item:not(.pwl-hidden) .pwl-recipient-checkbox').forEach(function(cb){cb.checked=true});updateSummary()})}
            if(clearSelection){clearSelection.addEventListener('click',function(){document.querySelectorAll('.pwl-recipient-checkbox').forEach(function(cb){cb.checked=false});updateSummary()})}
            if(form){form.addEventListener('submit',function(e){const a=selectedAudience();if(a==='selected'&&document.querySelectorAll('.pwl-recipient-checkbox:checked').length===0){e.preventDefault();alert('Selecione pelo menos um usuário.');return}if(a==='groups'&&document.querySelectorAll('.pwl-group-checkbox:checked').length===0){e.preventDefault();alert('Selecione pelo menos um grupo.')}})}
            updateAudience();updateSummary();
        })();
        </script>
        <?php
    }


    /* =========================================================
     * CRIAÇÃO / DESTINATÁRIOS
     * ======================================================= */

    public function handle_create_notification() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acesso não autorizado.' );
        }

        check_admin_referer( 'pwl_criar_notificacao', 'pwl_notification_nonce' );

        $result = $this->process_notification_payload( $_POST );

        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=pwl-notificacoes&created=1' ) );
        exit;
    }

    private function process_notification_payload( $payload ) {
        $payload = is_array( $payload ) ? $payload : array();

        $title   = isset( $payload['title'] ) ? sanitize_text_field( wp_unslash( $payload['title'] ) ) : '';
        $message = isset( $payload['message'] ) ? sanitize_textarea_field( wp_unslash( $payload['message'] ) ) : '';
        $url     = isset( $payload['url'] ) ? esc_url_raw( wp_unslash( $payload['url'] ) ) : '';

        if ( '' === $title ) {
            return new WP_Error( 'missing_title', 'Informe o título da notificação.' );
        }

        $allowed_types = array( 'geral', 'comunicado', 'campanha', 'rh', 'ti', 'contratacao', 'enquete' );
        $type = isset( $payload['notification_type'] ) ? sanitize_key( wp_unslash( $payload['notification_type'] ) ) : 'geral';
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'geral';
        }

        $allowed_priorities = array( 'normal', 'importante', 'urgente' );
        $priority = isset( $payload['priority'] ) ? sanitize_key( wp_unslash( $payload['priority'] ) ) : 'normal';
        if ( ! in_array( $priority, $allowed_priorities, true ) ) {
            $priority = 'normal';
        }

        $audience = isset( $payload['audience'] ) ? sanitize_key( wp_unslash( $payload['audience'] ) ) : 'all';
        if ( ! in_array( $audience, array( 'all', 'selected', 'groups' ), true ) ) {
            $audience = 'all';
        }

        $publish_at = $this->sanitize_local_datetime( isset( $payload['publish_at'] ) ? wp_unslash( $payload['publish_at'] ) : '' );
        $expires_at = $this->sanitize_local_datetime( isset( $payload['expires_at'] ) ? wp_unslash( $payload['expires_at'] ) : '', true );

        if ( ! $publish_at ) {
            $publish_at = current_time( 'mysql' );
        }

        if ( $expires_at && strtotime( $expires_at ) <= strtotime( $publish_at ) ) {
            return new WP_Error( 'invalid_expiration', 'A data de expiração deve ser posterior à publicação.' );
        }

        $send_email = ! empty( $payload['send_email'] ) ? 1 : 0;
        $user_ids   = array();
        $source_map = array();

        if ( 'selected' === $audience ) {
            $raw_ids = isset( $payload['user_ids'] ) && is_array( $payload['user_ids'] ) ? wp_unslash( $payload['user_ids'] ) : array();
            foreach ( $raw_ids as $raw_id ) {
                $id = absint( $raw_id );
                if ( $id > 0 && get_userdata( $id ) ) {
                    $user_ids[]      = $id;
                    $source_map[$id] = array( 'type' => 'user', 'key' => '' );
                }
            }
            $user_ids = array_values( array_unique( $user_ids ) );
            if ( empty( $user_ids ) ) {
                return new WP_Error( 'missing_users', 'Selecione pelo menos um usuário.' );
            }
        }

        if ( 'groups' === $audience ) {
            $raw_groups = isset( $payload['group_keys'] ) && is_array( $payload['group_keys'] ) ? wp_unslash( $payload['group_keys'] ) : array();
            $group_keys = array_values( array_filter( array_map( 'sanitize_key', $raw_groups ) ) );
            if ( empty( $group_keys ) ) {
                return new WP_Error( 'missing_groups', 'Selecione pelo menos um grupo.' );
            }

            $resolved = $this->resolve_group_user_ids( $group_keys );
            if ( empty( $resolved ) ) {
                return new WP_Error( 'empty_groups', 'Os grupos selecionados não retornaram usuários. Verifique a integração com o PWL Core.' );
            }

            $user_ids = array_keys( $resolved );
            foreach ( $resolved as $user_id => $group_key ) {
                $source_map[ $user_id ] = array( 'type' => 'group', 'key' => $group_key );
            }
            $audience = 'selected';
        }

        $notification_id = $this->insert_notification(
            $title,
            $message,
            $url,
            $type,
            $priority,
            $audience,
            $user_ids,
            $source_map,
            $send_email,
            $publish_at,
            $expires_at
        );

        if ( ! $notification_id ) {
            return new WP_Error( 'create_failed', 'Não foi possível publicar a notificação.' );
        }

        return absint( $notification_id );
    }

    private function sanitize_local_datetime( $value, $allow_empty = false ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            return $allow_empty ? null : false;
        }

        $timestamp = strtotime( $value );
        if ( ! $timestamp ) {
            return $allow_empty ? null : false;
        }

        return wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() );
    }

    private function insert_notification( $title, $message, $url, $type, $priority, $audience, $user_ids, $source_map, $send_email, $publish_at, $expires_at ) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_notifications,
            array(
                'user_id'           => 0,
                'target_type'        => $audience,
                'title'              => sanitize_text_field( $title ),
                'message'            => sanitize_textarea_field( $message ),
                'url'                => esc_url_raw( $url ),
                'notification_type'  => sanitize_key( $type ),
                'priority'           => sanitize_key( $priority ),
                'send_email'         => absint( $send_email ),
                'publish_at'         => $publish_at,
                'expires_at'         => $expires_at,
                'created_by'         => get_current_user_id(),
                'created_at'         => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
        );

        if ( false === $result ) {
            return false;
        }

        $notification_id = absint( $wpdb->insert_id );

        if ( 'selected' === $audience ) {
            foreach ( $user_ids as $user_id ) {
                $user_id = absint( $user_id );
                $source  = isset( $source_map[ $user_id ] ) ? $source_map[ $user_id ] : array( 'type' => 'user', 'key' => '' );

                $wpdb->insert(
                    $this->table_recipients,
                    array(
                        'notification_id' => $notification_id,
                        'user_id'         => $user_id,
                        'source_type'     => sanitize_key( $source['type'] ),
                        'source_key'      => sanitize_text_field( $source['key'] ),
                        'created_at'      => current_time( 'mysql' ),
                    ),
                    array( '%d', '%d', '%s', '%s', '%s' )
                );
            }
        }

        if ( $send_email ) {
            $this->queue_notification_emails( $notification_id, $audience, $user_ids, $publish_at );
        }

        $this->push_queue_notification( $notification_id, $audience, $user_ids, $publish_at );
        $this->push_kick_queue( $publish_at );

        do_action( 'pwl_notificacao_criada', $notification_id );
        do_action( 'cobremack_notificacao_criada', $notification_id ); // Compatibilidade legada.

        return $notification_id;
    }

    private function queue_notification_emails( $notification_id, $audience, $selected_user_ids, $available_at ) {
        global $wpdb;

        if ( 'all' === $audience ) {
            $user_ids = get_users( array( 'fields' => 'ID' ) );
        } else {
            $user_ids = $selected_user_ids;
        }

        foreach ( $user_ids as $user_id ) {
            $user_id = absint( $user_id );
            if ( ! $this->get_user_pref( $user_id, self::META_PREF_EMAIL, true ) ) {
                continue;
            }

            $user = get_userdata( $user_id );
            if ( ! $user || ! is_email( $user->user_email ) ) {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$this->table_email_queue}
                    (notification_id, user_id, email, status, attempts, available_at, created_at)
                    VALUES (%d, %d, %s, 'pending', 0, %s, %s)",
                    $notification_id,
                    $user_id,
                    $user->user_email,
                    $available_at,
                    current_time( 'mysql' )
                )
            );
        }
    }

    /* =========================================================
     * TEMPLATE HTML DE E-MAIL
     * ======================================================= */

    private function get_email_modules() {
        $modules = array(
            'caixinha' => array(
                'label' => 'Caixinha',
                'description' => 'E-mails de novas solicitações e complementos enviados ao Contas a Pagar.',
                'title' => 'Nova solicitação de Caixinha',
                'button_text' => 'Analisar solicitação',
                'content' => '<p><strong>Solicitante:</strong> {solicitante}</p><p><strong>Valor:</strong> {valor}</p><p><strong>Centro de custo:</strong> {centro_custo}</p><p><strong>Finalidade:</strong> {finalidade}</p>',
                'tokens' => array( 'solicitante' => 'Nome do colaborador', 'valor' => 'Valor solicitado em reais', 'centro_custo' => 'Centro de custo informado', 'finalidade' => 'Finalidade da solicitação' ),
                'match' => array( 'caixinha' ),
            ),
            'encomendas' => array(
                'label' => 'Encomendas',
                'description' => 'Aviso de encomenda recebida na Portaria.',
                'title' => 'Encomenda recebida na Portaria',
                'button_text' => '',
                'content' => '<p><strong>Remetente:</strong> {remetente}</p><p><strong>Código interno da Encomenda:</strong> {codigo_encomenda}</p><p><strong>Descrição:</strong> {descricao}</p><p><strong>Código de Rastreio:</strong> {codigo_rastreio}</p><p><strong>Procure a Portaria da unidade.</strong></p><p><strong>Ramal da Portaria:</strong> {ramal_portaria}</p>',
                'tokens' => array( 'remetente' => 'Nome do remetente', 'codigo_encomenda' => 'Código interno', 'descricao' => 'Descrição da encomenda', 'codigo_rastreio' => 'Código de rastreio', 'ramal_portaria' => 'Ramal da Portaria' ),
                'match' => array( 'encomenda recebida na portaria' ),
            ),
            'geral' => array(
                'label' => 'Outras notificações',
                'description' => 'Modelo padrão para notificações que ainda não possuem um modelo próprio registrado.',
                'title' => 'Atualização do Portal',
                'button_text' => '',
                'content' => '<p>{mensagem}</p><p style="font-size:12px;color:#667085;"><strong>Tipo:</strong> {tipo} &nbsp; <strong>Prioridade:</strong> {prioridade}</p>',
                'tokens' => array( 'mensagem' => 'Texto principal da notificação', 'tipo' => 'Classificação da notificação', 'prioridade' => 'Prioridade configurada' ),
                'match' => array(),
            ),
        );
        $modules = apply_filters( 'pwl_notificacoes_email_modules', $modules );
        return is_array( $modules ) ? $modules : array();
    }

    private function get_email_module_templates() {
        $modules = $this->get_email_modules();
        $saved = get_option( 'pwl_notificacoes_email_module_templates', array() );
        $saved = is_array( $saved ) ? $saved : array();
        $templates = array();
        foreach ( $modules as $key => $module ) {
            $key = sanitize_key( $key );
            if ( ! $key || ! is_array( $module ) ) { continue; }
            $custom = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
            $default_button = ( 'geral' === $key && ! isset( $custom['button_text'] ) ) ? (string) $this->get_email_settings()['button_text'] : (string) $module['button_text'];
            $templates[ $key ] = array(
                'content' => isset( $custom['content'] ) ? wp_kses_post( $custom['content'] ) : (string) $module['content'],
                'button_text' => isset( $custom['button_text'] ) ? sanitize_text_field( $custom['button_text'] ) : $default_button,
            );
        }
        return $templates;
    }

    public function handle_save_email_module_template() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acesso não autorizado.' ); }
        check_admin_referer( 'pwl_notificacoes_save_email_module_template', 'pwl_email_module_nonce' );
        $module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
        $modules = $this->get_email_modules();
        if ( ! $module || ! isset( $modules[ $module ] ) ) { wp_die( 'Módulo de e-mail inválido.' ); }
        $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
        $button_text = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
        if ( '' === trim( wp_strip_all_tags( $content ) ) ) { wp_die( 'O conteúdo do modelo não pode ficar vazio.' ); }
        $templates = get_option( 'pwl_notificacoes_email_module_templates', array() );
        $templates = is_array( $templates ) ? $templates : array();
        $templates[ $module ] = array( 'content' => $content, 'button_text' => $button_text );
        update_option( 'pwl_notificacoes_email_module_templates', $templates, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'pwl-notificacoes', 'email_module' => $module, 'email_saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function build_email_template_preview( $module_key, $module, $template ) {
        $sample_values = array(
            'solicitante' => 'Douglas Oliveira Rodrigues', 'valor' => 'R$ 66,00', 'centro_custo' => '04.10', 'finalidade' => 'Compra pontual para apoio à operação.', 'mensagem' => 'Conteúdo da notificação de exemplo.', 'tipo' => 'Geral', 'prioridade' => 'Normal',
            'remetente' => 'Loja Exemplo', 'codigo_encomenda' => 'ENC-2026-000034', 'descricao' => 'Pacote de demonstração', 'codigo_rastreio' => 'BR123456789', 'ramal_portaria' => '285 / 286',
        );
        $content = $this->apply_email_module_template( $template['content'], $sample_values );
        if ( function_exists( 'pwl_core_render_email' ) ) {
            return pwl_core_render_email( array(
                'title' => $module['title'], 'greeting' => 'Olá, Douglas Oliveira Rodrigues.',
                'content_html' => $content, 'button_url' => $template['button_text'] ? home_url( '/' ) : '',
                'button_text' => $template['button_text'], 'preheader' => '',
            ) );
        }
        return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f4f6f8;padding:20px"><main style="max-width:580px;margin:auto;background:#fff;padding:24px"><p>Olá, Douglas Oliveira Rodrigues.</p><h2>' . esc_html( $module['title'] ) . '</h2>' . $content . '</main></body></html>';
    }

    private function print_email_template_preview_script( $module_key, $template ) {
        if ( ! function_exists( 'pwl_core_render_email' ) ) { return; }
        $modules = $this->get_email_modules();
        $module = isset( $modules[ $module_key ] ) ? $modules[ $module_key ] : reset( $modules );
        $shell = pwl_core_render_email( array(
            'title' => $module['title'], 'greeting' => 'Olá, Douglas Oliveira Rodrigues.',
            'content_html' => '__PWL_EMAIL_CONTENT_PREVIEW__',
            'button_url' => home_url( '/' ),
            'button_text' => '__PWL_EMAIL_BUTTON_PREVIEW__', 'preheader' => '',
        ) );
        $samples = array( 'solicitante' => 'Douglas Oliveira Rodrigues', 'valor' => 'R$ 66,00', 'centro_custo' => '04.10', 'finalidade' => 'Compra pontual para apoio à operação.', 'mensagem' => 'Conteúdo da notificação de exemplo.', 'tipo' => 'Geral', 'prioridade' => 'Normal', 'remetente' => 'Loja Exemplo', 'codigo_encomenda' => 'ENC-2026-000034', 'descricao' => 'Pacote de demonstração', 'codigo_rastreio' => 'BR123456789', 'ramal_portaria' => '285 / 286' );
        ?>
        <script>
        (function(){var frame=document.getElementById('pwlnEmailPreview');if(!frame)return;var shell=<?php echo wp_json_encode( $shell ); ?>;var samples=<?php echo wp_json_encode( $samples ); ?>;var resizeGreeting=<?php echo wp_json_encode( 'caixinha' === $module_key ); ?>;var marker='__PWL_EMAIL_CONTENT_PREVIEW__';function escapeHtml(value){return String(value).replace(/[&<>"']/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];});}function getContent(){var editor=window.tinymce&&tinymce.get('pwln_email_content_template');var value=editor&&!editor.isHidden()?editor.getContent():((document.getElementById('pwln_email_content_template')||{}).value||'');Object.keys(samples).forEach(function(key){value=value.split('{'+key+'}').join(escapeHtml(samples[key]));});return value;}function update(){var html=shell.replace(marker,getContent());if(resizeGreeting){html=html.replace('font-size:13px;color:#667085;margin-bottom:10px;','font-size:15px;color:#667085;margin-bottom:10px;');}var button=document.getElementById('pwlnEmailButtonText');var label=button?button.value.trim():'';if(label){html=html.replace('__PWL_EMAIL_BUTTON_PREVIEW__',escapeHtml(label));html=html.replace(/<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:[^"]*">/,'<table role="presentation" align="center" cellspacing="0" cellpadding="0" border="0" style="margin:26px auto 4px;">').replace(/<td style="border-radius:9px;background:([^;]+);">/,'<td align="center" style="border-radius:9px!important;background:$1;padding:0;overflow:hidden;">').replace(/border-radius:9px;/g,'border-radius:9px!important;text-align:center;');}else{html=html.replace(/<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:[^"]*">.*?<\/table>/s,'');}frame.srcdoc=html;}document.addEventListener('DOMContentLoaded',function(){var textarea=document.getElementById('pwln_email_content_template');if(textarea){textarea.addEventListener('input',update);}var button=document.getElementById('pwlnEmailButtonText');if(button){button.addEventListener('input',update);}var tries=0;var timer=setInterval(function(){var editor=window.tinymce&&tinymce.get('pwln_email_content_template');if(editor){editor.on('keyup change input undo redo',update);clearInterval(timer);}if(++tries>30)clearInterval(timer);},250);update();});})();
        </script>
        <?php
    }

    private function identify_email_module( $notification ) {
        $title = is_object( $notification ) && isset( $notification->title ) ? strtolower( remove_accents( (string) $notification->title ) ) : '';
        $type = is_object( $notification ) && isset( $notification->notification_type ) ? sanitize_key( $notification->notification_type ) : '';
        $modules = $this->get_email_modules();
        foreach ( $modules as $key => $module ) {
            if ( $type === sanitize_key( $key ) ) { return $key; }
            foreach ( (array) ( $module['match'] ?? array() ) as $needle ) {
                if ( false !== strpos( $title, strtolower( remove_accents( (string) $needle ) ) ) ) { return $key; }
            }
        }
        return apply_filters( 'pwl_notificacoes_email_module_for_notification', 'geral', $notification );
    }

    private function get_email_module_values( $module_key, $notification, $user ) {
        $message = is_object( $notification ) && isset( $notification->message ) ? (string) $notification->message : '';
        $values = array( 'mensagem' => $message, 'titulo' => is_object( $notification ) ? (string) $notification->title : '', 'nome' => ( $user && ! empty( $user->display_name ) ) ? $user->display_name : 'Usuário', 'tipo' => is_object( $notification ) ? ucfirst( (string) $notification->notification_type ) : 'Geral', 'prioridade' => is_object( $notification ) ? $this->priority_label( $notification->priority ) : 'Normal' );
        if ( 'caixinha' === $module_key ) {
            if ( preg_match( '/^(?:A solicitação|O complemento)\s+CX-[A-Z0-9-]+\s+(?:foi enviada por\s+(.+?)|foi registrada por.+?em nome de\s+(.+?))\s+no valor de\s+(R\$\s*[\d.,]+)\.\s*Finalidade:\s*(.*)$/iu', trim( $message ), $legacy ) ) {
                $values += array( 'solicitante' => ! empty( $legacy[1] ) ? $legacy[1] : $legacy[2], 'valor' => $legacy[3], 'centro_custo' => 'Não informado', 'finalidade' => $legacy[4] );
            } else {
                $lines = preg_split( '/\r\n|\r|\n/', $message );
                $purpose = '';
                $in_purpose = false;
                foreach ( $lines as $line ) {
                    if ( preg_match( '/^Solicitante:\s*(.*)$/iu', trim( $line ), $m ) ) { $values['solicitante'] = $m[1]; $in_purpose = false; }
                    elseif ( preg_match( '/^Valor:\s*(.*)$/iu', trim( $line ), $m ) ) { $values['valor'] = $m[1]; $in_purpose = false; }
                    elseif ( preg_match( '/^Centro de custo:\s*(.*)$/iu', trim( $line ), $m ) ) { $values['centro_custo'] = $m[1]; $in_purpose = false; }
                    elseif ( preg_match( '/^Finalidade:\s*(.*)$/iu', trim( $line ), $m ) ) { $purpose = $m[1]; $in_purpose = true; }
                    elseif ( preg_match( '/^Solicitação vinculada:/iu', trim( $line ) ) ) { $in_purpose = false; }
                    elseif ( $in_purpose ) { $purpose .= "\n" . trim( $line ); }
                }
                if ( '' !== $purpose ) { $values['finalidade'] = $purpose; }
            }
        } elseif ( 'encomendas' === $module_key ) {
            $map = array(
                'remetente' => 'remetente',
                'codigo interno da encomenda' => 'codigo_encomenda',
                'descricao' => 'descricao',
                'embalagem' => 'descricao',
                'codigo de rastreio' => 'codigo_rastreio',
                'ramal da portaria' => 'ramal_portaria',
            );
            foreach ( preg_split( '/\r\n|\r|\n/', $message ) as $line ) {
                if ( preg_match( '/^([^:]+):\s*(.*)$/u', trim( $line ), $m ) ) {
                    $label = strtolower( remove_accents( trim( $m[1] ) ) );
                    if ( isset( $map[ $label ] ) ) { $values[ $map[ $label ] ] = $m[2]; }
                }
            }
        }
        return $values;
    }

    private function apply_email_module_template( $template, $values ) {
        $replacements = array();
        foreach ( $values as $key => $value ) { $replacements[ '{' . $key . '}' ] = nl2br( esc_html( (string) $value ) ); }
        return strtr( wp_kses_post( (string) $template ), $replacements );
    }

    /**
     * Renderiza um e-mail de módulo para um envio direto feito por outro plugin.
     * O template continua sendo administrado nesta central e a identidade visual
     * permanece sob responsabilidade do PWL Core.
     *
     * @param string $module_key Chave registrada em pwl_notificacoes_email_modules.
     * @param array  $args       title, greeting, values, button_url e preheader.
     * @return array|false Corpo HTML e configurações do Core, ou false se indisponível.
     */
    public function render_module_email( $module_key, $args = array() ) {
        $module_key = sanitize_key( $module_key );
        $modules = $this->get_email_modules();
        if ( ! isset( $modules[ $module_key ] ) || ! is_array( $args ) ) { return false; }
        if ( ! defined( 'PWL_CORE_VERSION' ) || version_compare( PWL_CORE_VERSION, '1.55.0', '<' ) || ! function_exists( 'pwl_core_get_email_settings' ) || ! function_exists( 'pwl_core_render_email' ) ) { return false; }

        $settings = pwl_core_get_email_settings();
        if ( ! is_array( $settings ) ) { return false; }
        $templates = $this->get_email_module_templates();
        if ( ! isset( $templates[ $module_key ] ) ) { return false; }
        $module = $modules[ $module_key ];
        $template = $templates[ $module_key ];
        $values = isset( $args['values'] ) && is_array( $args['values'] ) ? $args['values'] : array();
        $content = $this->apply_email_module_template( $template['content'], $values );
        $title = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : (string) $module['title'];
        $greeting = isset( $args['greeting'] ) ? sanitize_text_field( $args['greeting'] ) : 'Olá.';
        $button_text = (string) $template['button_text'];
        $html = pwl_core_render_email( array(
            'title' => $title,
            'greeting' => $greeting,
            'content_html' => $content,
            'button_url' => ( '' !== $button_text && ! empty( $args['button_url'] ) ) ? esc_url_raw( $args['button_url'] ) : '',
            'button_text' => $button_text,
            'preheader' => isset( $args['preheader'] ) ? sanitize_text_field( $args['preheader'] ) : '',
        ) );
        if ( ! is_string( $html ) || '' === trim( $html ) ) { return false; }

        // Mantém no envio externo os mesmos ajustes de leitura do modelo Caixinha.
        if ( 'caixinha' === $module_key ) {
            $html = preg_replace_callback( '/(<div style="font-size:)13px(;color:#667085;margin-bottom:10px;">Olá,.*?<\/div>)/', static function( $matches ) { return str_replace( 'font-size:13px', 'font-size:15px', $matches[0] ); }, $html, 1 );
        }
        if ( '' !== $button_text ) {
            $html = preg_replace_callback( '/<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:[^"]*">.*?<\/table>/is', static function( $matches ) {
                $button = preg_replace( '/<table\b/', '<table align="center"', $matches[0], 1 );
                $button = preg_replace( '/style="margin:[^"]*"/', 'style="margin:26px auto 4px;"', $button, 1 );
                $button = preg_replace( '/<td style="border-radius:9px;background:([^;]+);">/', '<td align="center" style="border-radius:9px !important;background:$1;padding:0;overflow:hidden;">', $button, 1 );
                return str_replace( 'border-radius:9px;', 'border-radius:9px !important;text-align:center;', $button );
            }, $html, 1 );
        }
        return array( 'html' => $html, 'title' => $title, 'button_text' => $button_text, 'settings' => $settings );
    }

    private function get_email_settings() {
        if ( function_exists( 'pwl_core_get_email_settings' ) ) {
            return pwl_core_get_email_settings();
        }
        $brand_name = sanitize_text_field( get_option( self::OPTION_EMAIL_BRAND_NAME, get_bloginfo( 'name' ) ) );
        if ( '' === $brand_name ) {
            $brand_name = 'Portal';
        }

        $primary_color = sanitize_hex_color( get_option( self::OPTION_EMAIL_COLOR, '#ff5a00' ) );
        if ( ! $primary_color ) {
            $primary_color = '#ff5a00';
        }

        $footer_default = '{{brand}} • Mensagem automática enviada pelo portal • {{year}}';

        return array(
            'brand_name'     => $brand_name,
            'logo_url'       => esc_url_raw( get_option( self::OPTION_EMAIL_LOGO_URL, apply_filters( 'pwl_notificacoes_default_email_logo_url', 'http://192.168.1.4/intranet/wp-content/uploads/2026/09/Logo-Oficial-Cobremack-preto-300x100.png' ) ) ),
            'primary_color'  => $primary_color,
            'footer'         => sanitize_textarea_field( get_option( self::OPTION_EMAIL_FOOTER, $footer_default ) ),
            'button_text'    => sanitize_text_field( get_option( self::OPTION_EMAIL_BUTTON_TEXT, 'Acessar conteúdo' ) ),
            'subject_prefix' => sanitize_text_field( get_option( self::OPTION_EMAIL_SUBJECT, $brand_name ) ),
        );
    }

    private function replace_email_placeholders( $text, $settings ) {
        return strtr(
            (string) $text,
            array(
                '{{brand}}' => isset( $settings['brand_name'] ) ? $settings['brand_name'] : '',
                '{{year}}'  => wp_date( 'Y' ),
            )
        );
    }

    public function handle_save_email_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acesso não autorizado.' );
        }

        check_admin_referer( 'pwl_salvar_email_template', 'pwl_email_template_nonce' );

        $brand_name     = isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '';
        $logo_url       = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
        $primary_color  = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : '';
        $footer         = isset( $_POST['footer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['footer'] ) ) : '';
        $button_text    = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
        $subject_prefix = isset( $_POST['subject_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_prefix'] ) ) : '';

        if ( '' === $brand_name ) {
            $brand_name = get_bloginfo( 'name' );
        }
        if ( ! $primary_color ) {
            $primary_color = '#ff5a00';
        }
        if ( '' === $button_text ) {
            $button_text = 'Acessar conteúdo';
        }
        if ( '' === $subject_prefix ) {
            $subject_prefix = $brand_name;
        }

        update_option( self::OPTION_EMAIL_BRAND_NAME, $brand_name );
        update_option( self::OPTION_EMAIL_LOGO_URL, $logo_url );
        update_option( self::OPTION_EMAIL_COLOR, $primary_color );
        update_option( self::OPTION_EMAIL_FOOTER, $footer );
        update_option( self::OPTION_EMAIL_BUTTON_TEXT, $button_text );
        update_option( self::OPTION_EMAIL_SUBJECT, $subject_prefix );

        wp_safe_redirect( admin_url( 'admin.php?page=pwl-notificacoes&email_saved=1' ) );
        exit;
    }

    private function render_email_html( $notification, $user, $settings ) {
        $is_package_notice = is_object( $notification ) && ! empty( $notification->title ) && false !== stripos( (string) $notification->title, 'Encomenda recebida na Portaria' );
        $email_module_key = $this->identify_email_module( $notification );
        $email_modules = $this->get_email_modules();
        $email_templates = $this->get_email_module_templates();
        $is_custom_module = isset( $email_modules[ $email_module_key ], $email_templates[ $email_module_key ] );
        $module_template = $is_custom_module ? $email_templates[ $email_module_key ] : array();
        $module_content = $is_custom_module ? $this->apply_email_module_template( $module_template['content'], $this->get_email_module_values( $email_module_key, $notification, $user ) ) : '';
        $module_button_text = $is_custom_module ? $module_template['button_text'] : $settings['button_text'];
        if ( function_exists( 'pwl_core_render_email' ) ) {
            $name = ( $user && ! empty( $user->display_name ) ) ? $user->display_name : 'Usuário';
            $message = nl2br( esc_html( (string) $notification->message ) );
            $type = esc_html( ucfirst( (string) $notification->notification_type ) );
            $priority = esc_html( $this->priority_label( $notification->priority ) );
            $content = $is_custom_module ? $module_content : ( $is_package_notice ? $this->format_package_notice_email( $notification->message ) : '<p>' . $message . '</p><p><span style="display:inline-block;padding:5px 9px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:11px;font-weight:700;margin-right:6px;">' . $type . '</span><span style="display:inline-block;padding:5px 9px;border-radius:999px;background:#fff1e8;color:' . esc_attr( $settings['primary_color'] ) . ';font-size:11px;font-weight:700;">' . $priority . '</span></p>' );
            $html = pwl_core_render_email( array(
                'title' => (string) $notification->title,
                'greeting' => 'Olá, ' . $name . '.',
                'content_html' => $content,
                'button_url' => ( $is_custom_module && '' === $module_button_text ) ? '' : ( ! empty( $notification->url ) ? $notification->url : '' ),
                'button_text' => $module_button_text,
                'preheader' => ( $is_package_notice || $is_custom_module ) ? '' : (string) $notification->title,
            ) );
            if ( $is_custom_module ) {
                if ( 'caixinha' === $email_module_key ) { $html = preg_replace_callback( '/(<div style="font-size:)13px(;color:#667085;margin-bottom:10px;">Olá,.*?<\/div>)/', static function( $matches ) { return str_replace( 'font-size:13px', 'font-size:15px', $matches[0] ); }, $html, 1 ); }
                if ( '' !== $module_button_text ) { $html = preg_replace_callback( '/<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:[^"]*">.*?<\/table>/is', static function( $matches ) {
                    $button = $matches[0];
                    $button = preg_replace( '/<table\b/', '<table align="center"', $button, 1 );
                    $button = preg_replace( '/style="margin:[^"]*"/', 'style="margin:26px auto 4px;"', $button, 1 );
                    $button = preg_replace( '/<td style="border-radius:9px;background:([^;]+);">/', '<td align="center" style="border-radius:9px !important;background:$1;padding:0;overflow:hidden;">', $button, 1 );
                    $button = str_replace( 'border-radius:9px;', 'border-radius:9px !important;text-align:center;', $button );
                    return $button;
                }, $html, 1 ); }
            }
            return apply_filters( 'pwl_notificacoes_email_html', $html, $notification, $user, $settings );
        }
        $brand       = $settings['brand_name'];
        $color       = $settings['primary_color'];
        $logo_url    = $settings['logo_url'];
        $button_text = $module_button_text;
        $footer      = $this->replace_email_placeholders( $settings['footer'], $settings );
        $name        = ( $user && ! empty( $user->display_name ) ) ? $user->display_name : 'Usuário';
        $message     = $is_custom_module ? $module_content : ( $is_package_notice ? $this->format_package_notice_email( $notification->message ) : nl2br( esc_html( (string) $notification->message ) ) );
        $title       = esc_html( (string) $notification->title );
        $priority    = esc_html( $this->priority_label( $notification->priority ) );
        $type        = esc_html( ucfirst( (string) $notification->notification_type ) );
        $url         = ! empty( $notification->url ) ? esc_url( $notification->url ) : '';

        $header_brand = '';
        if ( $logo_url ) {
            $header_brand = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $brand ) . '" style="display:block;max-width:190px;max-height:54px;width:auto;height:auto;border:0;">';
        } else {
            $header_brand = '<div style="font-size:20px;line-height:1.3;font-weight:800;color:#101828;">' . esc_html( $brand ) . '</div>';
        }

        $button = '';
        if ( $url && '' !== $button_text ) {
            $button = '<table role="presentation" align="center" cellspacing="0" cellpadding="0" border="0" style="margin:26px auto 4px;"><tr><td align="center" style="border-radius:9px !important;background:' . esc_attr( $color ) . ';padding:0;overflow:hidden;"><a href="' . $url . '" style="display:inline-block;padding:12px 20px;font-family:Arial,sans-serif;font-size:14px;line-height:1.2;font-weight:700;color:#ffffff;text-decoration:none;border-radius:9px !important;text-align:center;">' . esc_html( $button_text ) . '</a></td></tr></table>';
        }

        $html = '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#344054;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f6f8;padding:26px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #e6eaf0;border-radius:16px;overflow:hidden;">'
            . '<tr><td style="height:5px;background:' . esc_attr( $color ) . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:28px 32px 22px;">' . $header_brand . '</td></tr>'
            . '<tr><td style="padding:0 32px 8px;"><div style="font-size:13px;color:#667085;margin-bottom:10px;">Olá, ' . esc_html( $name ) . '.</div><h1 style="margin:0;font-size:24px;line-height:1.35;color:#101828;font-weight:800;">' . $title . '</h1></td></tr>'
            . ( ( $is_package_notice || $is_custom_module ) ? '' : '<tr><td style="padding:8px 32px 0;"><span style="display:inline-block;padding:5px 9px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:11px;font-weight:700;margin-right:6px;">' . $type . '</span><span style="display:inline-block;padding:5px 9px;border-radius:999px;background:#fff1e8;color:' . esc_attr( $color ) . ';font-size:11px;font-weight:700;">' . $priority . '</span></td></tr>' )
            . '<tr><td style="padding:26px 32px 30px;font-size:15px;line-height:1.75;color:#344054;">' . $message . $button . '</td></tr>'
            . '<tr><td style="padding:16px 32px;background:#fafbfc;border-top:1px solid #eef1f5;font-size:11px;line-height:1.55;color:#98a2b3;">' . nl2br( esc_html( $footer ) ) . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        return apply_filters( 'pwl_notificacoes_email_html', $html, $notification, $user, $settings );
    }

    private function format_cashbox_notice_email( $message ) {
        $message = trim( (string) $message );
        if ( preg_match( '/^(?:A solicitação|O complemento)\s+CX-[A-Z0-9-]+\s+(?:foi enviada por\s+(.+?)|foi registrada por.+?em nome de\s+(.+?))\s+no valor de\s+(R\$\s*[\d.,]+)\.\s*Finalidade:\s*(.*)$/iu', $message, $legacy ) ) {
            $message = 'Solicitante: ' . ( ! empty( $legacy[1] ) ? $legacy[1] : $legacy[2] ) . "\nValor: " . $legacy[3] . "\nCentro de custo: Não informado\nFinalidade: " . $legacy[4];
        }
        $html = '';
        $purpose = '';
        $in_purpose = false;
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $message ) as $line ) {
            $line = trim( $line );
            if ( '' === $line ) { continue; }
            if ( preg_match( '/^Solicitante:\s*(.*)$/iu', $line, $parts ) ) {
                $in_purpose = false;
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Solicitante:</strong> ' . esc_html( $parts[1] ) . '</p>';
            } elseif ( preg_match( '/^Valor:\s*(.*)$/iu', $line, $parts ) ) {
                $in_purpose = false;
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Valor:</strong> ' . esc_html( $parts[1] ) . '</p>';
            } elseif ( preg_match( '/^Centro de custo:\s*(.*)$/iu', $line, $parts ) ) {
                $in_purpose = false;
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Centro de custo:</strong> ' . esc_html( $parts[1] ) . '</p>';
            } elseif ( preg_match( '/^Finalidade:\s*(.*)$/iu', $line, $parts ) ) {
                $in_purpose = true;
                $purpose = $parts[1];
            } elseif ( preg_match( '/^Solicitação vinculada:/iu', $line ) ) {
                $in_purpose = false;
            } elseif ( $in_purpose ) {
                $purpose .= "\n" . $line;
            }
        }
        if ( '' !== $purpose ) { $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Finalidade:</strong> ' . nl2br( esc_html( $purpose ) ) . '</p>'; }
        return $html;
    }

    private function format_package_notice_email( $message ) {
        $html = '';
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $message ) as $line ) {
            $line = trim( $line );
            if ( '' === $line ) { continue; }
            if ( 0 === stripos( $line, 'Lembrete:' ) ) {
                $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#344054;"><strong>Lembrete:</strong></p>';
                continue;
            }
            if ( preg_match( '/^(Remetente|Código interno da Encomenda|Descrição|Embalagem|Código de Rastreio):\s*(.*)$/iu', $line, $parts ) ) {
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>' . esc_html( $parts[1] ) . ':</strong> ' . esc_html( $parts[2] ) . '</p>';
                continue;
            }
            if ( preg_match( '/^Procure a Portaria da unidade(?:.*)$/iu', $line ) ) {
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Procure a Portaria da unidade.</strong></p>';
                continue;
            }
            if ( preg_match( '/^Ramal da Portaria:\s*(.*)$/iu', $line, $parts ) ) {
                $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;"><strong>Ramal da Portaria:</strong> ' . esc_html( $parts[1] ) . '</p>';
                continue;
            }
            $html .= '<p style="margin:0 0 10px;font-size:15px;line-height:1.6;color:#344054;">' . esc_html( $line ) . '</p>';
        }
        return $html;
    }

    /* =========================================================
     * FILA DE E-MAIL
     * ======================================================= */

    public function process_email_queue() {
        global $wpdb;

        $now = current_time( 'mysql' );

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_email_queue}
                 WHERE status = 'pending' AND available_at <= %s
                 ORDER BY id ASC LIMIT 20",
                $now
            )
        );

        foreach ( $items as $item ) {
            $notification = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_notifications} WHERE id = %d LIMIT 1",
                    $item->notification_id
                )
            );

            if ( ! $notification ) {
                $wpdb->update( $this->table_email_queue, array( 'status' => 'failed', 'last_error' => 'Notificação inexistente.' ), array( 'id' => $item->id ), array( '%s', '%s' ), array( '%d' ) );
                continue;
            }

            if ( ! $this->get_user_pref( $item->user_id, self::META_PREF_EMAIL, true ) ) {
                $wpdb->update( $this->table_email_queue, array( 'status' => 'skipped', 'last_error' => 'Usuário desativou e-mail.' ), array( 'id' => $item->id ), array( '%s', '%s' ), array( '%d' ) );
                continue;
            }

            $user     = get_userdata( absint( $item->user_id ) );
            $settings = $this->get_email_settings();

            $prefix  = trim( (string) $settings['subject_prefix'] );
            $is_package_notice = ! empty( $notification->title ) && false !== stripos( (string) $notification->title, 'Encomenda recebida na Portaria' );
            $subject = ( $prefix ? '[' . $prefix . '] ' : '' ) . ( $is_package_notice ? '' : '[' . $this->priority_label( $notification->priority ) . '] ' ) . $notification->title;
            $subject = apply_filters( 'pwl_notificacoes_email_subject', $subject, $notification, $user, $settings );

            $body = $this->render_email_html( $notification, $user, $settings );

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
            );
            $headers = apply_filters( 'pwl_notificacoes_email_headers', $headers, $notification, $user, $settings );

            $sent = wp_mail( $item->email, $subject, $body, $headers );

            if ( $sent ) {
                $wpdb->update(
                    $this->table_email_queue,
                    array( 'status' => 'sent', 'attempts' => absint( $item->attempts ) + 1, 'sent_at' => current_time( 'mysql' ), 'last_error' => null ),
                    array( 'id' => $item->id ),
                    array( '%s', '%d', '%s', '%s' ),
                    array( '%d' )
                );
            } else {
                $attempts = absint( $item->attempts ) + 1;
                $status   = $attempts >= 3 ? 'failed' : 'pending';
                $wpdb->update(
                    $this->table_email_queue,
                    array( 'status' => $status, 'attempts' => $attempts, 'available_at' => wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 300, wp_timezone() ), 'last_error' => 'wp_mail retornou false.' ),
                    array( 'id' => $item->id ),
                    array( '%s', '%d', '%s', '%s' ),
                    array( '%d' )
                );
            }
        }
    }

    /* =========================================================
     * EXCLUSÃO
     * ======================================================= */

    public function handle_delete_notification() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acesso não autorizado.' );
        }

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) {
            wp_die( 'Notificação inválida.' );
        }

        check_admin_referer( 'pwl_excluir_notificacao_' . $id );

        global $wpdb;
        $wpdb->delete( $this->table_reads, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_recipients, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_email_queue, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_notifications, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=pwl-notificacoes&deleted=1' ) );
        exit;
    }

    /* =========================================================
     * WEB PUSH: entrega criptografada RFC 8291 e VAPID RFC 8292
     * ======================================================= */
    private function push_table() {
        global $wpdb;
        return $wpdb->prefix . 'pwl_notificacoes_push_subscriptions';
    }

    private function push_queue_table() {
        global $wpdb;
        return $wpdb->prefix . 'pwl_notificacoes_push_queue';
    }

    public function push_register() {
        if ( ! is_ssl() ) { return; }
        global $wpdb;
        $table = $this->push_table();
        if ( get_option( 'pwl_push_schema_version' ) !== '2' ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT NOT NULL,
                auth_secret TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY endpoint_hash (endpoint_hash),
                KEY user_id (user_id)
            ) {$wpdb->get_charset_collate()};" );
            dbDelta( "CREATE TABLE {$this->push_queue_table()} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_id BIGINT UNSIGNED NOT NULL,
                subscription_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL,
                last_error VARCHAR(190) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY notification_subscription (notification_id,subscription_id),
                KEY status_available (status,available_at),
                KEY subscription_id (subscription_id)
            ) {$wpdb->get_charset_collate()};" );
            update_option( 'pwl_push_schema_version', '2', false );
        }
        if ( ! get_option( 'pwl_push_vapid_public' ) && function_exists( 'openssl_pkey_new' ) ) {
            $key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
            if ( $key ) {
                $details = openssl_pkey_get_details( $key );
                if ( isset( $details['ec']['x'], $details['ec']['y'], $details['ec']['d'] ) ) {
                    $enc = static function( $raw ) { return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); };
                    add_option( 'pwl_push_vapid_public', $enc( "\x04" . $details['ec']['x'] . $details['ec']['y'] ), '', false );
                    add_option( 'pwl_push_vapid_private', $enc( $details['ec']['d'] ), '', false );
                }
            }
        }
    }

    /** Serve o Service Worker antes do roteamento/404 do WordPress, sem regras de rewrite. */
    public function push_serve_worker() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = wp_parse_url( $request_uri, PHP_URL_PATH );
        $expected = wp_parse_url( home_url( '/pwl-notificacoes-push/sw.js' ), PHP_URL_PATH );
        if ( ! is_string( $path ) || $path !== $expected ) {
            return;
        }

        $worker_file = plugin_dir_path( __FILE__ ) . 'pwl-notificacoes-sw.js';
        if ( ! is_readable( $worker_file ) ) {
            status_header( 503 );
            header( 'Content-Type: text/plain; charset=UTF-8' );
            header( 'Cache-Control: no-store' );
            echo 'Service Worker indisponivel.';
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: application/javascript; charset=UTF-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Service-Worker-Allowed: ' . wp_parse_url( home_url( '/pwl-notificacoes-push/' ), PHP_URL_PATH ) );
        readfile( $worker_file );
        exit;
    }

    private function push_verify() {
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => 'Não autenticado.' ), 401 ); }
        check_ajax_referer( 'pwl_notificacoes_nonce', 'nonce' );
        if ( ! is_ssl() ) { wp_send_json_error( array( 'message' => 'HTTPS obrigatório.' ), 400 ); }
    }

    // Ativa somente o canal desktop, sem modificar as preferências do sino/e-mail.
    public function push_enable_desktop() {
        $this->push_verify();
        update_user_meta( get_current_user_id(), self::META_PREF_DESKTOP, 1 );
        wp_send_json_success( array( 'enabled' => true ) );
    }

    public function push_subscribe() {
        $this->push_verify();
        if ( ! $this->get_user_pref( get_current_user_id(), self::META_PREF_DESKTOP, false ) ) {
            wp_send_json_error( array( 'message' => 'Ative e salve a preferência de notificações do Windows antes de registrar este dispositivo.' ), 403 );
        }
        $raw = isset( $_POST['subscription'] ) ? wp_unslash( $_POST['subscription'] ) : '';
        if ( ! is_string( $raw ) || strlen( $raw ) > 8192 ) { wp_send_json_error( array( 'message' => 'Assinatura inválida.' ), 400 ); }
        $sub = json_decode( $raw, true );
        $endpoint = isset( $sub['endpoint'] ) ? esc_url_raw( $sub['endpoint'] ) : '';
        $keys = isset( $sub['keys'] ) && is_array( $sub['keys'] ) ? $sub['keys'] : array();
        $p256dh = isset( $keys['p256dh'] ) ? $keys['p256dh'] : '';
        $auth = isset( $keys['auth'] ) ? $keys['auth'] : '';
        $host = wp_parse_url( $endpoint, PHP_URL_HOST );
        if ( ! $endpoint || ! $host || 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME ) || strlen( $endpoint ) > 2048 || ! preg_match( '/^[A-Za-z0-9_-]{80,120}$/', $p256dh ) || ! preg_match( '/^[A-Za-z0-9_-]{15,40}$/', $auth ) ) {
            wp_send_json_error( array( 'message' => 'Assinatura inválida.' ), 400 );
        }
        global $wpdb;
        $table = $this->push_table();
        $hash = hash( 'sha256', $endpoint );
        $wpdb->replace( $table, array( 'user_id' => get_current_user_id(), 'endpoint_hash' => $hash, 'endpoint' => $endpoint, 'p256dh' => sanitize_text_field( $p256dh ), 'auth_secret' => sanitize_text_field( $auth ), 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        wp_send_json_success( array( 'registered' => true ) );
    }

    public function push_unsubscribe() {
        $this->push_verify();
        $endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
        if ( $endpoint ) {
            global $wpdb;
            $wpdb->delete( $this->push_table(), array( 'user_id' => get_current_user_id(), 'endpoint_hash' => hash( 'sha256', $endpoint ) ), array( '%d', '%s' ) );
        }
        wp_send_json_success();
    }

    public function push_client() {
        if ( ! is_user_logged_in() || ! is_ssl() || is_admin() ) { return; }
        $public = get_option( 'pwl_push_vapid_public' );
        if ( ! $public ) { return; }
        $enabled = $this->get_user_pref( get_current_user_id(), self::META_PREF_DESKTOP, false );
        ?>
        <style id="pwl-push-consent-style">
        .pwl-push-consent{position:fixed;right:22px;bottom:22px;z-index:2147483000;width:min(390px,calc(100vw - 32px));box-sizing:border-box;padding:19px 20px;border:1px solid var(--pwl-border,#e4e7ec);border-radius:14px;background:var(--pwl-surface,#fff);color:var(--pwl-text,#263238);box-shadow:0 14px 42px rgba(0,0,0,.16);font-family:'Plus Jakarta Sans',sans-serif}
        .pwl-push-consent strong{display:block;font-size:14px;line-height:1.5;margin-bottom:6px}.pwl-push-consent p{font-size:12.5px;line-height:1.6;margin:0 0 14px}.pwl-push-consent-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.pwl-push-consent button{border:0;border-radius:9px;padding:10px 13px;font:600 12px 'Plus Jakarta Sans',sans-serif;cursor:pointer}.pwl-push-consent .pwl-push-activate{background:var(--pwl-primary,#ff5a00);color:white}.pwl-push-consent .pwl-push-later{background:transparent;color:inherit;border:1px solid var(--pwl-border,#d4d8df)}.pwl-push-consent .pwl-push-status{font-size:11px;margin-top:9px;line-height:1.5}
        body.cm-dark .pwl-push-consent,body.pwl-dark .pwl-push-consent,html[data-theme="dark"] .pwl-push-consent,body[data-theme="dark"] .pwl-push-consent{background:#20252d;color:#f3f4f6;border-color:#3b424c}
        @media(max-width:600px){.pwl-push-consent{right:16px;bottom:16px}}
        </style>
        <script>
        (function(){
            'use strict';
            const cfg=<?php echo wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'pwl_notificacoes_nonce' ), 'key' => $public, 'worker' => home_url( '/pwl-notificacoes-push/sw.js' ), 'scope' => home_url( '/pwl-notificacoes-push/' ), 'enabled' => (bool) $enabled, 'user' => get_current_user_id() ) ); ?>;
            if(!window.isSecureContext||!('serviceWorker' in navigator)||!('PushManager' in window)||!('Notification' in window))return;
            const decode=s=>Uint8Array.from(atob((s+'='.repeat((4-s.length%4)%4)).replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
            async function send(action,body){const f=new FormData();f.append('action',action);f.append('nonce',cfg.nonce);Object.keys(body||{}).forEach(k=>f.append(k,body[k]));const r=await fetch(cfg.url,{method:'POST',credentials:'same-origin',body:f,cache:'no-store'});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.data&&j.data.message||'Não foi possível concluir a operação.');return j;}
            async function register(){
                if(Notification.permission!=='granted')throw new Error('O navegador ainda não autorizou as notificações.');
                const r=await navigator.serviceWorker.register(cfg.worker,{scope:new URL(cfg.scope).pathname});
                let sub=await r.pushManager.getSubscription();
                if(!sub)sub=await r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:decode(cfg.key)});
                await send('pwl_push_subscribe',{subscription:JSON.stringify(sub.toJSON())});
                try{localStorage.setItem('pwl_push_registered','1');}catch(e){}
                return true;
            }
            window.pwlPushRegister=register;
            window.pwlPushUnregister=async function(){const r=await navigator.serviceWorker.getRegistration(new URL(cfg.scope).pathname);if(!r)return;const sub=await r.pushManager.getSubscription();if(sub){await send('pwl_push_unsubscribe',{endpoint:sub.endpoint});await sub.unsubscribe();try{localStorage.removeItem('pwl_push_registered');}catch(e){}}};
            // Permissão já concedida: restaura a assinatura sem mostrar o convite.
            if(Notification.permission==='granted'&&cfg.enabled){register().catch(()=>{});return;}
            if(Notification.permission==='denied'||cfg.enabled&&Notification.permission==='granted')return;
            const dismissKey='pwl_push_consent_later_'+cfg.user;
            try{if(sessionStorage.getItem(dismissKey)==='1')return;}catch(e){}
            function show(){
                if(document.getElementById('pwlPushConsent'))return;
                const panel=document.createElement('aside');panel.className='pwl-push-consent';panel.id='pwlPushConsent';panel.setAttribute('role','region');panel.setAttribute('aria-label','Ativar notificações do Portal');
                panel.innerHTML='<strong>Receba avisos do Portal no Windows</strong><p>Ative as notificações para receber comunicados mesmo quando a página do Portal estiver fechada.</p><div class="pwl-push-consent-actions"><button type="button" class="pwl-push-activate">ATIVAR NOTIFICAÇÕES</button><button type="button" class="pwl-push-later">Agora não</button></div><div class="pwl-push-status" role="status" aria-live="polite"></div>';
                document.body.appendChild(panel);
                const activate=panel.querySelector('.pwl-push-activate'),later=panel.querySelector('.pwl-push-later'),status=panel.querySelector('.pwl-push-status');
                later.addEventListener('click',()=>{try{sessionStorage.setItem(dismissKey,'1');}catch(e){}panel.remove();});
                activate.addEventListener('click',async()=>{
                    activate.disabled=true;status.textContent='Solicitando autorização…';
                    try{
                        const permission=await Notification.requestPermission();
                        if(permission!=='granted')throw new Error(permission==='denied'?'Permissão bloqueada. Libere as notificações nas configurações do navegador.':'Autorização não concedida.');
                        await send('pwl_push_enable_desktop',{});
                        cfg.enabled=true;
                        await register();
                        status.textContent='Notificações do Windows ativadas neste dispositivo.';
                        window.setTimeout(()=>panel.remove(),2200);
                    }catch(e){status.textContent=e.message||'Não foi possível ativar as notificações.';activate.disabled=false;}
                });
            }
            if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',show,{once:true});else show();
        })();
        </script>
        <?php
    }

    private function push_b64( $raw ) {
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    private function push_unb64( $value ) {
        return base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $value ) % 4 ) % 4 ), true );
    }

    private function push_queue_notification( $id, $audience, $users, $publish_at ) {
        global $wpdb;
        $table = $this->push_queue_table();
        if ( 'all' === $audience ) {
            $subs = $wpdb->get_results( "SELECT id,user_id FROM {$this->push_table()}" );
        } else {
            $users = array_values( array_filter( array_map( 'absint', (array) $users ) ) );
            if ( ! $users ) { return; }
            $holders = implode( ',', array_fill( 0, count( $users ), '%d' ) );
            $subs = $wpdb->get_results( $wpdb->prepare( "SELECT id,user_id FROM {$this->push_table()} WHERE user_id IN ({$holders})", $users ) );
        }
        foreach ( $subs as $sub ) {
            if ( ! $this->get_user_pref( $sub->user_id, self::META_PREF_DESKTOP, false ) ) { continue; }
            $wpdb->insert( $table, array(
                'notification_id' => $id, 'subscription_id' => $sub->id, 'user_id' => $sub->user_id,
                'status' => 'pending', 'available_at' => $publish_at, 'created_at' => current_time( 'mysql' ),
            ), array( '%d','%d','%d','%s','%s' ) );
        }
    }

    /**
     * Dispara processamento independente do WP-Cron para notificacoes imediatas.
     * O token de uso unico nunca e exposto ao cliente; o WP-Cron e contingencia.
     */
    private function push_kick_queue( $publish_at ) {
        $now = time();
        try {
            $due = ( new DateTimeImmutable( (string) $publish_at, wp_timezone() ) )->getTimestamp();
        } catch ( Exception $e ) {
            $due = $now;
        }
        $due = max( $now, (int) $due );
        $next = wp_next_scheduled( 'pwl_notificacoes_process_push_queue_once' );
        if ( ! $next || $next > $due + 5 ) {
            wp_schedule_single_event( $due, 'pwl_notificacoes_process_push_queue_once' );
        }
        if ( $due <= $now + 1 ) {
            $this->push_dispatch_async();
        }
    }

    private function push_dispatch_async() {
        // Limite curto para evitar disparos duplicados sob publicacoes simultaneas.
        if ( get_transient( 'pwl_push_async_dispatch_lock' ) ) { return; }
        set_transient( 'pwl_push_async_dispatch_lock', 1, 5 );
        $token = wp_generate_password( 48, false, false );
        $key = 'pwl_push_job_' . hash( 'sha256', $token );
        set_transient( $key, 1, 120 );
        $url = add_query_arg( array( 'action' => 'pwl_push_async_worker' ), admin_url( 'admin-post.php' ) );
        $args = array(
            'blocking' => false,
            'timeout' => 1,
            'redirection' => 0,
            'sslverify' => true,
            'body' => array( 'token' => $token ),
            'headers' => array( 'Cache-Control' => 'no-store' ),
        );
        $result = wp_remote_post( $url, $args );
        if ( is_wp_error( $result ) ) {
            delete_transient( 'pwl_push_async_dispatch_lock' );
            delete_transient( $key );
            // Evento ja agendado: fallback do WP-Cron permanece disponivel.
        }
    }

    public function push_async_worker() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { status_header( 405 ); exit; }
        $token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        if ( ! preg_match( '/^[A-Za-z0-9]{48}$/D', $token ) ) { status_header( 403 ); exit; }
        $key = 'pwl_push_job_' . hash( 'sha256', $token );
        if ( ! get_transient( $key ) ) { status_header( 403 ); exit; }
        delete_transient( $key );
        if ( function_exists( 'ignore_user_abort' ) ) { ignore_user_abort( true ); }
        if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 90 ); }
        $this->push_process_queue();
        status_header( 204 );
        exit;
    }

    private function push_der_len( $length ) {
        if ( $length < 128 ) { return chr( $length ); }
        $encoded = ltrim( pack( 'N', $length ), "\x00" );
        return chr( 0x80 | strlen( $encoded ) ) . $encoded;
    }

    private function push_public_pem( $raw ) {
        // SubjectPublicKeyInfo para prime256v1 (P-256), ponto nao comprimido de 65 bytes.
        if ( strlen( $raw ) !== 65 || ord( $raw[0] ) !== 4 ) { return false; }
        $prefix = hex2bin( '301306072a8648ce3d020106082a8648ce3d030107' );
        $bit = "\x03" . $this->push_der_len( 66 ) . "\x00" . $raw;
        $der = "\x30" . $this->push_der_len( strlen( $prefix ) + strlen( $bit ) ) . $prefix . $bit;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
    }

    private function push_hkdf( $ikm, $salt, $info, $length ) {
        $prk = hash_hmac( 'sha256', $ikm, $salt, true );
        $output = ''; $previous = '';
        for ( $i = 1; strlen( $output ) < $length; $i++ ) {
            $previous = hash_hmac( 'sha256', $previous . $info . chr( $i ), $prk, true );
            $output .= $previous;
        }
        return substr( $output, 0, $length );
    }

    private function push_encrypt( $subscription, $json ) {
        $client_public = $this->push_unb64( $subscription->p256dh );
        $auth = $this->push_unb64( $subscription->auth_secret );
        if ( strlen( $client_public ) !== 65 || strlen( $auth ) !== 16 ) { return new WP_Error( 'push_keys', 'Chaves da assinatura inválidas.' ); }
        $server_key = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
        $client_key = openssl_pkey_get_public( $this->push_public_pem( $client_public ) );
        if ( ! $server_key || ! $client_key || ! function_exists( 'openssl_pkey_derive' ) ) { return new WP_Error( 'push_crypto', 'ECDH indisponível.' ); }
        $details = openssl_pkey_get_details( $server_key );
        $server_public = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $shared = openssl_pkey_derive( $client_key, $server_key, 32 );
        if ( ! $shared ) { return new WP_Error( 'push_crypto', 'Não foi possível derivar a chave.' ); }
        // RFC 8291: IKM = HKDF(ECDH, auth, "WebPush: info\0" + ua + as, 32).
        $ikm = $this->push_hkdf( $shared, $auth, "WebPush: info\x00" . $client_public . $server_public, 32 );
        $salt = random_bytes( 16 );
        $key = $this->push_hkdf( $ikm, $salt, "Content-Encoding: aes128gcm\x00", 16 );
        $nonce = $this->push_hkdf( $ikm, $salt, "Content-Encoding: nonce\x00", 12 );
        $tag = '';
        $cipher = openssl_encrypt( $json . "\x02", 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
        if ( false === $cipher ) { return new WP_Error( 'push_crypto', 'Falha na criptografia.' ); }
        // salt(16), rs(4), idlen(1), keyid(65), ciphertext e tag.
        return $salt . pack( 'N', 4096 ) . chr( 65 ) . $server_public . $cipher . $tag;
    }

    private function push_vapid_jwt( $endpoint ) {
        $private = $this->push_unb64( (string) get_option( 'pwl_push_vapid_private' ) );
        $public = $this->push_unb64( (string) get_option( 'pwl_push_vapid_public' ) );
        if ( strlen( $private ) !== 32 || strlen( $public ) !== 65 ) { return new WP_Error( 'push_vapid', 'Chaves VAPID indisponíveis.' ); }
        // PKCS#8 não é necessário: estrutura ECPrivateKey SEC1 com parâmetros da curva.
        $inner = "\x02\x01\x01\x04\x20" . $private . hex2bin( 'a00a06082a8648ce3d030107' );
        $der = "\x30" . $this->push_der_len( strlen( $inner ) ) . $inner;
        $key = openssl_pkey_get_private( "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END EC PRIVATE KEY-----\n" );
        if ( ! $key ) { return new WP_Error( 'push_vapid', 'Chave VAPID inválida.' ); }
        $parts = wp_parse_url( $endpoint );
        $audience = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
        $header = $this->push_b64( wp_json_encode( array( 'typ'=>'JWT', 'alg'=>'ES256' ) ) );
        $claims = $this->push_b64( wp_json_encode( array( 'aud'=>$audience, 'exp'=>time()+3600, 'sub'=>'mailto:' . sanitize_email( get_option( 'admin_email' ) ) ) ) );
        $data = $header . '.' . $claims;
        $signature = '';
        if ( ! openssl_sign( $data, $signature, $key, OPENSSL_ALGO_SHA256 ) ) { return new WP_Error( 'push_vapid', 'Assinatura VAPID falhou.' ); }
        // Converter DER ECDSA para r||s (64 bytes), formato JOSE.
        $pos = 2;
        if ( ord( $signature[0] ) !== 0x30 ) { return new WP_Error( 'push_vapid', 'Assinatura inválida.' ); }
        if ( ord( $signature[1] ) & 0x80 ) { $pos += ord( $signature[1] ) & 0x7f; }
        $ints = array();
        for ( $i=0; $i<2; $i++ ) {
            if ( ord( $signature[$pos++] ) !== 2 ) { return new WP_Error( 'push_vapid', 'Formato ECDSA inválido.' ); }
            $len = ord( $signature[$pos++] );
            $ints[] = str_pad( substr( ltrim( substr( $signature, $pos, $len ), "\x00" ), -32 ), 32, "\x00", STR_PAD_LEFT );
            $pos += $len;
        }
        return array( 'jwt'=>$data . '.' . $this->push_b64( implode( '', $ints ) ), 'public'=>$this->push_b64( $public ) );
    }

    public function push_process_queue() {
        if ( ! is_ssl() || ! function_exists( 'openssl_pkey_derive' ) ) { return; }
        global $wpdb;
        $table = $this->push_queue_table();
        $now = current_time( 'mysql' );
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT q.*, s.endpoint,s.p256dh,s.auth_secret,n.title,n.message,n.url,n.priority,n.expires_at FROM {$table} q INNER JOIN {$this->push_table()} s ON s.id=q.subscription_id INNER JOIN {$this->table_notifications} n ON n.id=q.notification_id WHERE q.status='pending' AND q.available_at<=%s ORDER BY q.id ASC LIMIT 20", $now ) );
        foreach ( $items as $item ) {
            if ( ! $this->get_user_pref( $item->user_id, self::META_PREF_DESKTOP, false ) || ( $item->expires_at && $item->expires_at <= $now ) ) {
                $wpdb->update( $table, array( 'status'=>'skipped' ), array( 'id'=>$item->id ) ); continue;
            }
            $url = $item->url && wp_http_validate_url( $item->url ) && wp_parse_url( $item->url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? $item->url : home_url( '/' );
            $payload = wp_json_encode( array( 'id'=>(int)$item->notification_id, 'title'=>$item->title, 'body'=>$item->message, 'url'=>$url, 'priority'=>$item->priority ) );
            $encrypted = $this->push_encrypt( $item, $payload );
            $vapid = $this->push_vapid_jwt( $item->endpoint );
            $status = 0;
            if ( ! is_wp_error( $encrypted ) && ! is_wp_error( $vapid ) && strlen( $encrypted ) <= 4096 ) {
                $response = wp_safe_remote_post( $item->endpoint, array( 'timeout'=>12, 'redirection'=>0, 'limit_response_size'=>1024, 'headers'=>array(
                    'Content-Type'=>'application/octet-stream', 'Content-Encoding'=>'aes128gcm', 'TTL'=>'86400',
                    'Authorization'=>'vapid t=' . $vapid['jwt'] . ', k=' . $vapid['public'],
                ), 'body'=>$encrypted ) );
                $status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
            }
            if ( in_array( $status, array( 201,202,200 ), true ) ) {
                $wpdb->update( $table, array( 'status'=>'sent', 'attempts'=>(int)$item->attempts+1, 'last_error'=>null ), array( 'id'=>$item->id ) );
            } elseif ( in_array( $status, array( 404,410 ), true ) ) {
                $wpdb->delete( $this->push_table(), array( 'id'=>$item->subscription_id ), array( '%d' ) );
                $wpdb->update( $table, array( 'status'=>'invalid', 'last_error'=>'Assinatura expirada' ), array( 'id'=>$item->id ) );
            } else {
                $attempts = (int)$item->attempts + 1;
                $wpdb->update( $table, array( 'status'=>$attempts>=3?'failed':'pending', 'attempts'=>$attempts, 'available_at'=>wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + min( 3600, 300 * $attempts ), wp_timezone() ), 'last_error'=>'Falha no serviço push (HTTP ' . (int)$status . ')' ), array( 'id'=>$item->id ) );
            }
        }
        // Registra a conclusao do lote e inicia outro somente se houver itens vencidos.
        update_option( 'pwl_push_last_batch_at', current_time( 'mysql' ), false );
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE status='pending' AND available_at<=%s LIMIT 1", current_time( 'mysql' )
        ) );
        if ( $pending ) {
            // A fila recorrente permanece como contingencia se o loopback falhar.
            delete_transient( 'pwl_push_async_dispatch_lock' );
            $this->push_dispatch_async();
        }
    }

    /* =========================================================
     * VISIBILIDADE / RELATÓRIOS
     * ======================================================= */

    private function get_visibility_sql( $user_id, $respect_bell_pref = true ) {
        global $wpdb;

        if ( $respect_bell_pref && ! $this->get_user_pref( $user_id, self::META_PREF_BELL, true ) ) {
            return '1 = 0';
        }

        $now = current_time( 'mysql' );

        return $wpdb->prepare(
            "(
                (n.target_type = 'all' OR n.user_id = %d OR EXISTS (
                    SELECT 1 FROM {$this->table_recipients} nr
                    WHERE nr.notification_id = n.id AND nr.user_id = %d
                ))
                AND n.publish_at <= %s
                AND (n.expires_at IS NULL OR n.expires_at = '' OR n.expires_at > %s)
            )",
            $user_id,
            $user_id,
            $now,
            $now
        );
    }

    private function get_notification_recipient_ids( $notification ) {
        global $wpdb;

        if ( 'all' === $notification->target_type ) {
            return array_map( 'absint', get_users( array( 'fields' => 'ID' ) ) );
        }

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$this->table_recipients} WHERE notification_id = %d",
                $notification->id
            )
        );

        if ( empty( $ids ) && ! empty( $notification->user_id ) ) {
            $ids = array( absint( $notification->user_id ) );
        }

        return array_values( array_unique( array_map( 'absint', $ids ) ) );
    }

    private function get_notification_report( $notification ) {
        global $wpdb;

        $recipient_ids = $this->get_notification_recipient_ids( $notification );
        $total         = count( $recipient_ids );
        $read          = 0;

        if ( $total > 0 ) {
            $placeholders = implode( ',', array_fill( 0, $total, '%d' ) );
            $params       = array_merge( array( absint( $notification->id ) ), $recipient_ids );
            $sql          = $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$this->table_reads}
                 WHERE notification_id = %d AND user_id IN ({$placeholders})",
                $params
            );
            $read = absint( $wpdb->get_var( $sql ) );
        }

        return array(
            'total'   => $total,
            'read'    => $read,
            'pending' => max( 0, $total - $read ),
        );
    }

    private function get_email_report( $notification_id ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) total FROM {$this->table_email_queue}
                 WHERE notification_id = %d GROUP BY status",
                $notification_id
            )
        );

        $report = array( 'sent' => 0, 'pending' => 0, 'failed' => 0 );
        foreach ( $rows as $row ) {
            if ( 'sent' === $row->status ) {
                $report['sent'] += absint( $row->total );
            } elseif ( 'failed' === $row->status ) {
                $report['failed'] += absint( $row->total );
            } elseif ( 'pending' === $row->status ) {
                $report['pending'] += absint( $row->total );
            }
        }
        return $report;
    }

    private function get_admin_recipient_text( $notification ) {
        if ( 'all' === $notification->target_type ) {
            return 'Todos os usuários';
        }

        $ids = $this->get_notification_recipient_ids( $notification );
        if ( empty( $ids ) ) {
            return 'Nenhum destinatário';
        }

        $names = array();
        foreach ( $ids as $id ) {
            $user = get_userdata( $id );
            if ( $user ) {
                $names[] = $user->display_name;
            }
        }

        if ( count( $names ) <= 3 ) {
            return implode( ', ', $names );
        }

        return implode( ', ', array_slice( $names, 0, 3 ) ) . ' + ' . ( count( $names ) - 3 ) . ' usuário(s)';
    }

    /* =========================================================
     * AJAX
     * ======================================================= */

    private function verify_ajax() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Usuário não autenticado.' ), 401 );
        }
        check_ajax_referer( 'pwl_notificacoes_nonce', 'nonce' );
    }

    public function ajax_list_notifications() {
        $this->verify_ajax();
        global $wpdb;

        $user_id        = get_current_user_id();
        $visibility_sql = $this->get_visibility_sql( $user_id, true );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT n.id,n.title,n.message,n.url,n.notification_type,n.priority,n.publish_at,n.created_at,
                    CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
                 FROM {$this->table_notifications} n
                 LEFT JOIN {$this->table_reads} r ON r.notification_id = n.id AND r.user_id = %d
                 WHERE {$visibility_sql}
                 ORDER BY n.publish_at DESC,n.id DESC
                 LIMIT 30",
                $user_id
            )
        );

        $notifications = array();
        $unread_count  = 0;

        foreach ( $rows as $row ) {
            $is_read = (bool) $row->is_read;
            if ( ! $is_read ) {
                $unread_count++;
            }

            $notifications[] = array(
                'id'            => absint( $row->id ),
                'title'         => $row->title,
                'message'       => $row->message,
                'url'           => $row->url,
                'type'          => $row->notification_type,
                'priority'      => $row->priority,
                'priority_label'=> $this->priority_label( $row->priority ),
                'is_read'       => $is_read,
                'date'          => mysql2date( 'd/m/Y H:i', $row->publish_at ),
                'relative_date' => human_time_diff( strtotime( $row->publish_at ), current_time( 'timestamp' ) ) . ' atrás',
            );
        }

        wp_send_json_success(
            array(
                'notifications' => $notifications,
                'unread_count'  => $unread_count,
                'preferences'   => $this->get_preferences( $user_id ),
            )
        );
    }

    public function ajax_mark_read() {
        $this->verify_ajax();
        global $wpdb;

        $notification_id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
        if ( ! $notification_id ) {
            wp_send_json_error( array( 'message' => 'Notificação inválida.' ) );
        }

        $user_id        = get_current_user_id();
        $visibility_sql = $this->get_visibility_sql( $user_id, false );

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT n.id FROM {$this->table_notifications} n
                 WHERE n.id = %d AND {$visibility_sql} LIMIT 1",
                $notification_id
            )
        );

        if ( ! $exists ) {
            wp_send_json_error( array( 'message' => 'Notificação não encontrada.' ) );
        }

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->table_reads} (notification_id,user_id,read_at)
                 VALUES (%d,%d,%s)
                 ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)",
                $notification_id,
                $user_id,
                current_time( 'mysql' )
            )
        );

        wp_send_json_success();
    }

    public function ajax_mark_all_read() {
        $this->verify_ajax();
        global $wpdb;

        $user_id        = get_current_user_id();
        $visibility_sql = $this->get_visibility_sql( $user_id, false );
        $ids            = $wpdb->get_col( "SELECT n.id FROM {$this->table_notifications} n WHERE {$visibility_sql}" );

        foreach ( $ids as $notification_id ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$this->table_reads} (notification_id,user_id,read_at)
                     VALUES (%d,%d,%s)
                     ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)",
                    absint( $notification_id ),
                    $user_id,
                    current_time( 'mysql' )
                )
            );
        }

        wp_send_json_success( array( 'unread_count' => 0 ) );
    }

    public function ajax_config() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_success( array( 'logged_in' => false ) );
        }

        $user_id = get_current_user_id();

        wp_send_json_success(
            array(
                'logged_in'  => true,
                'nonce'      => wp_create_nonce( 'pwl_notificacoes_nonce' ),
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'preferences'=> $this->get_preferences( $user_id ),
            )
        );
    }

    public function ajax_config_guest() {
        wp_send_json_success( array( 'logged_in' => false ) );
    }

    public function ajax_guest_notifications() {
        wp_send_json_success( array( 'notifications' => array(), 'unread_count' => 0, 'logged_in' => false ) );
    }

    public function ajax_save_preferences() {
        $this->verify_ajax();

        $user_id = get_current_user_id();
        $bell     = ! empty( $_POST['bell'] ) ? 1 : 0;
        $email    = ! empty( $_POST['email'] ) ? 1 : 0;
        $desktop  = ! empty( $_POST['desktop'] ) ? 1 : 0;

        update_user_meta( $user_id, self::META_PREF_BELL, $bell );
        update_user_meta( $user_id, self::META_PREF_EMAIL, $email );
        update_user_meta( $user_id, self::META_PREF_DESKTOP, $desktop );
        if ( ! $desktop ) {
            global $wpdb;
            $wpdb->delete( $this->push_table(), array( 'user_id' => $user_id ), array( '%d' ) );
        }

        wp_send_json_success( array( 'preferences' => $this->get_preferences( $user_id ) ) );
    }

    /* =========================================================
     * WORKSPACE OPERACIONAL
     * ======================================================= */

    public function ajax_workspace_create() {
        if ( ! is_user_logged_in() || ! $this->workspace_user_can( 'notificacoes.create' ) ) {
            wp_send_json_error( array( 'message' => 'Você não possui permissão para criar notificações.' ), 403 );
        }

        check_ajax_referer( 'pwl_notificacoes_workspace', 'nonce' );

        $result = $this->process_notification_payload( $_POST );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        wp_send_json_success(
            array(
                'message'         => 'Notificação publicada com sucesso.',
                'notification_id' => absint( $result ),
            )
        );
    }

    public function ajax_workspace_delete() {
        if ( ! is_user_logged_in() || ! $this->workspace_user_can( 'notificacoes.manage' ) ) {
            wp_send_json_error( array( 'message' => 'Você não possui permissão para excluir notificações.' ), 403 );
        }

        check_ajax_referer( 'pwl_notificacoes_workspace', 'nonce' );
        $id = isset( $_POST['notification_id'] ) ? absint( $_POST['notification_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Notificação inválida.' ), 400 );
        }

        global $wpdb;
        $wpdb->delete( $this->table_reads, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_recipients, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_email_queue, array( 'notification_id' => $id ), array( '%d' ) );
        $wpdb->delete( $this->table_notifications, array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Notificação excluída com sucesso.' ) );
    }

    public function workspace_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="pwl-notif-workspace-message">Entre no Portal para acessar Notificações.</div>';
        }

        if ( ! $this->workspace_user_can( 'notificacoes.view' ) && ! $this->workspace_user_can( 'notificacoes.create' ) && ! $this->workspace_user_can( 'notificacoes.history' ) ) {
            return '<div class="pwl-notif-workspace-message">Você não possui permissão para acessar este módulo.</div>';
        }

        global $wpdb;
        $can_create  = $this->workspace_user_can( 'notificacoes.create' );
        $can_history = $this->workspace_user_can( 'notificacoes.history' );
        $can_manage  = $this->workspace_user_can( 'notificacoes.manage' );
        $users       = $can_create ? get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) ) : array();
        $groups      = $can_create ? $this->get_available_groups() : array();
        $history     = $can_history ? $wpdb->get_results( "SELECT * FROM {$this->table_notifications} ORDER BY id DESC LIMIT 100" ) : array();
        $nonce       = wp_create_nonce( 'pwl_notificacoes_workspace' );
        $ajax_url    = admin_url( 'admin-ajax.php' );

        if ( function_exists( 'pwl_ds_enqueue' ) ) {
            pwl_ds_enqueue( array( 'all' ), 'workspace' );
            if ( did_action( 'wp_head' ) && ! wp_style_is( 'pwl-ds-components', 'done' ) ) {
                wp_print_styles( array( 'pwl-ds-tokens', 'pwl-ds-components' ) );
            }
        }

        ob_start();
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <div class="pwl-nw pwl-ds pwl-portal-ui" id="pwlNotificationsWorkspace">
            <header class="pwl-nw-head">
                <div>
                    <span class="pwl-nw-kicker">PWL WORKSPACE</span>
                    <h1>Notificações</h1>
                    <p>Crie e acompanhe comunicações corporativas sem acessar a administração técnica do WordPress.</p>
                </div>
            </header>

            <nav class="pwl-nw-tabs" aria-label="Notificações">
                <?php if ( $can_create ) : ?><button type="button" class="pwl-nw-tab is-active" data-tab="create"><span>Nova notificação</span><small>Criar e programar envio</small></button><?php endif; ?>
                <?php if ( $can_history ) : ?><button type="button" class="pwl-nw-tab <?php echo $can_create ? '' : 'is-active'; ?>" data-tab="history"><span>Histórico</span><small>Leituras e entregas</small></button><?php endif; ?>
            </nav>

            <div class="pwl-nw-status" id="pwlNwStatus" hidden></div>

            <?php if ( $can_create ) : ?>
            <section class="pwl-nw-panel is-active" data-panel="create">
                <form id="pwlNwForm" class="pwl-nw-card">
                    <div class="pwl-nw-card-head"><div><h2>Nova notificação</h2><p>Defina conteúdo, destinatários, canais e período.</p></div><button type="submit" class="pwl-nw-primary">Publicar notificação</button></div>
                    <div class="pwl-nw-grid">
                        <div class="pwl-nw-section pwl-nw-main">
                            <h3>Conteúdo</h3>
                            <label>Título<input type="text" name="title" required></label>
                            <label>Mensagem<textarea name="message" rows="6"></textarea></label>
                            <label>Link de destino<input type="url" name="url" placeholder="https://..."></label>
                            <div class="pwl-nw-two">
                                <label>Tipo<select name="notification_type"><option value="geral">Geral</option><option value="comunicado">Comunicado</option><option value="campanha">Campanha</option><option value="rh">Recursos Humanos</option><option value="ti">Tecnologia da Informação</option><option value="contratacao">Nova contratação</option><option value="enquete">Enquete</option></select></label>
                                <label>Prioridade<select name="priority"><option value="normal">Normal</option><option value="importante">Importante</option><option value="urgente">Urgente</option></select></label>
                            </div>
                        </div>

                        <div class="pwl-nw-side">
                            <div class="pwl-nw-section">
                                <h3>Destinatários</h3>
                                <div class="pwl-nw-options">
                                    <label><input type="radio" name="audience" value="all" checked> Todos</label>
                                    <label><input type="radio" name="audience" value="selected"> Usuários</label>
                                    <?php if ( ! empty( $groups ) ) : ?><label><input type="radio" name="audience" value="groups"> Grupos</label><?php endif; ?>
                                </div>
                                <div class="pwl-nw-users" id="pwlNwUsers" hidden>
                                    <input type="search" id="pwlNwSearch" placeholder="Pesquisar usuário...">
                                    <div class="pwl-nw-userlist" id="pwlNwUserList">
                                        <?php foreach ( $users as $user ) : $search = strtolower( $user->display_name . ' ' . $user->user_login . ' ' . $user->user_email ); ?>
                                            <label data-search="<?php echo esc_attr( $search ); ?>"><input type="checkbox" name="user_ids[]" value="<?php echo esc_attr( $user->ID ); ?>"><span><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( $user->user_email ); ?></small></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php if ( ! empty( $groups ) ) : ?><div class="pwl-nw-groups" id="pwlNwGroups" hidden><?php foreach ( $groups as $key => $name ) : ?><label><input type="checkbox" name="group_keys[]" value="<?php echo esc_attr( sanitize_key( $key ) ); ?>"> <?php echo esc_html( $name ); ?></label><?php endforeach; ?></div><?php endif; ?>
                            </div>

                            <div class="pwl-nw-section">
                                <h3>Período</h3>
                                <label>Publicar em<input type="datetime-local" name="publish_at"><small>Em branco publica imediatamente.</small></label>
                                <label>Expira em<input type="datetime-local" name="expires_at"><small>Opcional.</small></label>
                            </div>
                        </div>
                    </div>

                    <div class="pwl-nw-bottom-grid">
                        <div class="pwl-nw-section"><h3>Canais</h3><label class="pwl-nw-switch"><input type="checkbox" checked disabled><span>Sino do Portal</span></label><label class="pwl-nw-switch"><input type="checkbox" name="send_email" value="1"><span>Enviar também por e-mail</span></label></div>
                        <div class="pwl-nw-section"><h3>Comportamento</h3><p>O envio utiliza as mesmas regras do painel técnico, incluindo preferências dos usuários, fila de e-mail e validade.</p></div>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <?php if ( $can_history ) : ?>
            <section class="pwl-nw-panel <?php echo $can_create ? '' : 'is-active'; ?>" data-panel="history">
                <div class="pwl-nw-history-card">
                    <div class="pwl-nw-history-head"><div><h2>Histórico</h2><p>Últimas notificações publicadas e respectivos indicadores.</p></div></div>
                    <div class="pwl-nw-table-wrap"><table><thead><tr><th>Data</th><th>Título</th><th>Prioridade</th><th>Destinatários</th><th>Leitura</th><th>E-mail</th><?php if ( $can_manage ) : ?><th>Ação</th><?php endif; ?></tr></thead><tbody>
                        <?php if ( empty( $history ) ) : ?><tr><td colspan="7">Nenhuma notificação publicada.</td></tr><?php else : foreach ( $history as $notification ) : $report=$this->get_notification_report($notification); $email_report=$this->get_email_report($notification->id); ?>
                        <tr data-notification-row="<?php echo esc_attr( $notification->id ); ?>"><td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $notification->created_at ) ); ?></td><td><strong><?php echo esc_html( $notification->title ); ?></strong><small><?php echo esc_html( ucfirst( $notification->notification_type ) ); ?></small></td><td><span class="pwl-nw-badge is-<?php echo esc_attr( $notification->priority ); ?>"><?php echo esc_html( $this->priority_label( $notification->priority ) ); ?></span></td><td><?php echo esc_html( $this->get_admin_recipient_text( $notification ) ); ?></td><td><?php echo esc_html( $report['read'] ); ?> lidas<br><?php echo esc_html( $report['pending'] ); ?> pendentes</td><td><?php echo ! empty( $notification->send_email ) ? esc_html( $email_report['sent'] . ' enviados / ' . $email_report['pending'] . ' pendentes / ' . $email_report['failed'] . ' falhas' ) : 'Não solicitado'; ?></td><?php if ( $can_manage ) : ?><td><button type="button" class="pwl-nw-delete" data-delete="<?php echo esc_attr( $notification->id ); ?>">Excluir</button></td><?php endif; ?></tr>
                        <?php endforeach; endif; ?>
                    </tbody></table></div>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <style>
        body:has(.pwl-nw){background:#f0f0f0}.pwl-nw{--o:#ff5a00;--ink:#101828;--text:#667085;--mut:#98a2b3;--line:#e4e7ec;--panel:#fff;--soft:#f0f0f0;font-family:"Plus Jakarta Sans",Arial,sans-serif;color:var(--ink);max-width:1440px;margin:0 auto;padding:28px 20px 44px}.pwl-nw *{box-sizing:border-box}.pwl-nw-head{margin-bottom:20px}.pwl-nw-kicker{display:block;color:var(--o);font-size:9.9px;font-weight:800;letter-spacing:.12em}.pwl-nw-head h1{margin:5px 0 7px;font-size:29.7px;line-height:1.2;font-weight:600}.pwl-nw-head p{margin:0;color:var(--text);font-size:14.3px;line-height:1.6}.pwl-nw-tabs{display:flex;gap:6px;margin-bottom:18px;padding:4px;border:1px solid var(--line);border-radius:13px;background:var(--panel);overflow:auto}.pwl-nw-tab{min-width:190px;padding:10px 13px;border:0;border-radius:9px;background:transparent;color:var(--text);text-align:left;cursor:pointer}.pwl-nw-tab span,.pwl-nw-tab small{display:block}.pwl-nw-tab span{font-size:13.2px;font-weight:600;color:var(--ink)}.pwl-nw-tab small{margin-top:2px;font-size:11px;color:var(--mut)}.pwl-nw-tab.is-active{background:#f1f3f5}.pwl-nw-panel{display:none}.pwl-nw-panel.is-active{display:block}.pwl-nw-card,.pwl-nw-history-card{background:var(--panel);border:1px solid var(--line);border-radius:16px;overflow:hidden}.pwl-nw-card-head,.pwl-nw-history-head{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:16px 18px;border-bottom:1px solid var(--line)}.pwl-nw-card-head h2,.pwl-nw-history-head h2{margin:0;font-size:22px;font-weight:600}.pwl-nw-card-head p,.pwl-nw-history-head p{margin:4px 0 0;color:var(--text);font-size:12.1px}.pwl-nw-primary{min-height:42px;padding:0 16px;border:0;border-radius:10px;background:var(--o);color:#fff;font-weight:700;cursor:pointer}.pwl-nw-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(340px,.65fr);gap:16px;padding:18px}.pwl-nw-side{display:grid;gap:16px}.pwl-nw-bottom-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:0 18px 18px}.pwl-nw-section{padding:16px;border:1px solid var(--line);border-radius:14px;background:var(--panel)}.pwl-nw-section h3{margin:0 0 14px;font-size:15.4px;font-weight:600}.pwl-nw label{display:flex;flex-direction:column;gap:6px;margin-bottom:13px;font-size:13.2px;font-weight:600}.pwl-nw input[type=text],.pwl-nw input[type=url],.pwl-nw input[type=datetime-local],.pwl-nw input[type=search],.pwl-nw textarea,.pwl-nw select{width:100%;min-height:42px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;background:var(--panel);color:var(--ink);font:500 13.2px/1.4 "Plus Jakarta Sans",Arial,sans-serif}.pwl-nw textarea{resize:vertical}.pwl-nw input:focus,.pwl-nw textarea:focus,.pwl-nw select:focus{outline:0;border-color:var(--o);box-shadow:0 0 0 3px rgba(255,90,0,.10)}.pwl-nw label small{font-size:11px;font-weight:400;color:var(--mut)}.pwl-nw-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pwl-nw-options{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.pwl-nw-options label,.pwl-nw-groups label{display:inline-flex;flex-direction:row;align-items:center;gap:7px;margin:0;padding:9px 10px;border:1px solid var(--line);border-radius:9px;background:var(--soft);font-size:12.1px;cursor:pointer}.pwl-nw-users{border:1px solid var(--line);border-radius:10px;overflow:hidden}.pwl-nw-users>input{border:0!important;border-bottom:1px solid var(--line)!important;border-radius:0!important}.pwl-nw-userlist{max-height:260px;overflow:auto}.pwl-nw-userlist label{display:flex;flex-direction:row;align-items:center;gap:9px;margin:0;padding:9px 10px;border-bottom:1px solid var(--line);font-size:12.1px}.pwl-nw-userlist label:last-child{border-bottom:0}.pwl-nw-userlist span{display:flex;flex-direction:column}.pwl-nw-userlist small{font-size:11px;color:var(--mut)}.pwl-nw-groups{display:flex;gap:8px;flex-wrap:wrap}.pwl-nw-switch{display:flex!important;flex-direction:row!important;align-items:center!important;gap:8px!important;margin-bottom:10px!important}.pwl-nw-section>p{margin:0;color:var(--text);font-size:13.2px;line-height:1.6}.pwl-nw-status{margin-bottom:14px;padding:11px 13px;border-radius:10px;background:#ecfdf3;color:#067647;font-size:12.1px;font-weight:600}.pwl-nw-status.is-error{background:#fef3f2;color:#b42318}.pwl-nw-table-wrap{overflow:auto}.pwl-nw table{width:100%;min-width:980px;border-collapse:collapse}.pwl-nw th,.pwl-nw td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:12.1px;color:var(--text)}.pwl-nw th{font-size:11px;text-transform:uppercase;color:var(--mut)}.pwl-nw td strong,.pwl-nw td small{display:block}.pwl-nw td strong{color:var(--ink)}.pwl-nw td small{margin-top:3px;color:var(--mut);font-size:11px}.pwl-nw-badge{display:inline-flex;padding:4px 8px;border-radius:999px;background:#f2f4f7;font-size:11px;font-weight:700}.pwl-nw-badge.is-importante{background:#fff7d6;color:#8a6100}.pwl-nw-badge.is-urgente{background:#ffe3e3;color:#b42318}.pwl-nw-delete{border:0;background:transparent;color:#b42318;font-weight:700;cursor:pointer}.pwl-notif-workspace-message{padding:18px;border:1px solid #e4e7ec;border-radius:12px;font-family:"Plus Jakarta Sans",Arial,sans-serif}
        body.cm-dark:has(.pwl-nw),body.pwl-dark:has(.pwl-nw),body[data-theme="dark"]:has(.pwl-nw){background:#0f1722}body.cm-dark .pwl-nw,body.pwl-dark .pwl-nw,html[data-theme="dark"] .pwl-nw,body[data-theme="dark"] .pwl-nw{--ink:#f7f9fc;--text:#b8c4d3;--mut:#8fa0b4;--line:#354052;--panel:#111827;--soft:#172033;background:#0f1722}body.cm-dark .pwl-nw-tab.is-active,body.pwl-dark .pwl-nw-tab.is-active,html[data-theme="dark"] .pwl-nw-tab.is-active,body[data-theme="dark"] .pwl-nw-tab.is-active{background:#202938}
        @media(max-width:980px){.pwl-nw-grid{grid-template-columns:1fr}.pwl-nw-bottom-grid{grid-template-columns:1fr}}@media(max-width:640px){.pwl-nw{padding:20px 12px 32px}.pwl-nw-card-head,.pwl-nw-history-head{align-items:stretch;flex-direction:column}.pwl-nw-primary{width:100%}.pwl-nw-two{grid-template-columns:1fr}.pwl-nw-tab{min-width:160px}}

        /* PWL Caixinha visual language for the employee Portal workspace. */
        body:has(.pwl-nw){background:#f8f9ff}.pwl-nw{--o:#ff6a00;--ink:#0b1c30;--text:#545f73;--mut:#7c8ca7;--line:rgba(148,163,184,.18);--panel:rgba(255,255,255,.76);--soft:#eff4ff;background:#f8f9ff;color:var(--ink);padding:24px 22px 40px}
        .pwl-nw-head{margin:0 0 18px;padding:21px 25px;border:1px solid rgba(148,163,184,.14);border-radius:16px;background:rgba(255,255,255,.76);box-shadow:0 4px 16px rgba(15,23,42,.04);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}.pwl-nw-kicker{font-size:10px;letter-spacing:.08em}.pwl-nw-head h1{font-size:30px;font-weight:700;letter-spacing:-.02em}.pwl-nw-head p{font-size:13px;color:var(--text)}
        .pwl-nw-tabs{gap:8px;margin-bottom:18px;padding:9px 11px;border-color:rgba(148,163,184,.14);border-radius:14px;background:rgba(255,255,255,.74);box-shadow:0 3px 12px rgba(15,23,42,.035);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}.pwl-nw-tab{min-width:max-content;padding:10px 15px;border:1px solid rgba(148,163,184,.11);border-radius:12px;background:rgba(255,255,255,.54);box-shadow:0 2px 6px rgba(15,23,42,.035)}.pwl-nw-tab span{font-size:12px;color:#3c475a}.pwl-nw-tab small{display:none}.pwl-nw-tab.is-active{border-color:#ff6a00;background:#ff6a00;box-shadow:0 2px 5px rgba(255,106,0,.2)}.pwl-nw-tab.is-active span{color:#fff}
        .pwl-nw-card,.pwl-nw-history-card,.pwl-nw-section{border-color:rgba(148,163,184,.15);border-radius:15px;background:var(--panel);box-shadow:0 3px 12px rgba(15,23,42,.035);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}.pwl-nw-card-head,.pwl-nw-history-head{padding:17px 20px;border-color:var(--line)}.pwl-nw-card-head h2,.pwl-nw-history-head h2{font-size:20px;font-weight:700}.pwl-nw-grid{padding:20px}.pwl-nw-bottom-grid{padding:0 20px 20px}.pwl-nw-section{padding:17px;background:rgba(239,244,255,.43)}
        .pwl-nw label{color:var(--ink)}.pwl-nw input[type=text],.pwl-nw input[type=url],.pwl-nw input[type=datetime-local],.pwl-nw input[type=search],.pwl-nw textarea,.pwl-nw select{border-color:rgba(148,163,184,.28);border-radius:9px;background:rgba(255,255,255,.84);color:var(--ink)}.pwl-nw input:focus,.pwl-nw textarea:focus,.pwl-nw select:focus{border-color:#ff6a00;box-shadow:0 0 0 3px rgba(255,106,0,.13)}.pwl-nw-options label,.pwl-nw-groups label{border-color:rgba(148,163,184,.18);border-radius:10px;background:rgba(239,244,255,.7)}.pwl-nw-primary{border-radius:9px;background:#ff6a00;box-shadow:0 2px 5px rgba(255,106,0,.18)}.pwl-nw-primary:hover{background:#e85c00}
        .pwl-nw-status{border:1px solid rgba(22,163,74,.18);border-radius:10px;background:#e8f7ee;color:#176b36}.pwl-nw-status.is-error{background:#ffdad6;color:#93000a}.pwl-nw th{color:var(--mut)}.pwl-nw td{color:var(--text)}.pwl-nw tr:hover td{background:rgba(239,244,255,.45)}.pwl-nw-badge{background:#eff4ff;color:#3c475a}.pwl-nw-badge.is-importante{background:#fff1cc;color:#7b5700}.pwl-nw-badge.is-urgente{background:#ffdad6;color:#93000a}
        body.cm-dark:has(.pwl-nw),body.pwl-dark:has(.pwl-nw),body[data-theme="dark"]:has(.pwl-nw){background:#101823}.pwl-nw{color-scheme:light}body.cm-dark .pwl-nw,body.pwl-dark .pwl-nw,html[data-theme="dark"] .pwl-nw,body[data-theme="dark"] .pwl-nw{--ink:#eaf1ff;--text:#bcc7de;--mut:#93a4bf;--line:rgba(255,255,255,.1);--panel:rgba(29,36,47,.82);--soft:#263244;background:#101823;color:#eaf1ff;color-scheme:dark}body.cm-dark .pwl-nw-tab span,body.pwl-dark .pwl-nw-tab span,html[data-theme="dark"] .pwl-nw-tab span,body[data-theme="dark"] .pwl-nw-tab span{color:#bcc7de}body.cm-dark .pwl-nw-tab.is-active span,body.pwl-dark .pwl-nw-tab.is-active span,html[data-theme="dark"] .pwl-nw-tab.is-active span,body[data-theme="dark"] .pwl-nw-tab.is-active span{color:#fff}body.cm-dark .pwl-nw input,body.cm-dark .pwl-nw textarea,body.cm-dark .pwl-nw select,body.pwl-dark .pwl-nw input,body.pwl-dark .pwl-nw textarea,body.pwl-dark .pwl-nw select,html[data-theme="dark"] .pwl-nw input,html[data-theme="dark"] .pwl-nw textarea,html[data-theme="dark"] .pwl-nw select,body[data-theme="dark"] .pwl-nw input,body[data-theme="dark"] .pwl-nw textarea,body[data-theme="dark"] .pwl-nw select{background:rgba(25,31,40,.68);color:#eaf1ff;border-color:rgba(255,255,255,.14)}
        @media(max-width:640px){.pwl-nw{padding:16px 12px 30px}.pwl-nw-head{padding:18px}.pwl-nw-tabs{gap:7px;padding:8px}.pwl-nw-tab{padding:9px 12px}.pwl-nw-grid{padding:15px}.pwl-nw-bottom-grid{padding:0 15px 15px}}
        
/* 1.10.2 - Encomendas Portal UI / DS semantic contract. */
body:has(.pwl-nw){background:var(--pwl-color-background,#f0f0f0)}
.pwl-ds.pwl-portal-ui.pwl-nw{--o:var(--pwl-color-primary);--ink:var(--pwl-color-text);--text:var(--pwl-color-text-secondary);--mut:var(--pwl-color-text-muted);--line:var(--pwl-color-border);--panel:var(--pwl-color-surface);--soft:var(--pwl-color-surface-subtle);background:var(--pwl-color-background);color:var(--pwl-color-text);font-family:var(--pwl-font-family);color-scheme:normal}
.pwl-nw .pwl-nw-head,.pwl-nw .pwl-nw-tabs,.pwl-nw .pwl-nw-card,.pwl-nw .pwl-nw-history-card,.pwl-nw .pwl-nw-section{background:var(--pwl-color-surface);border-color:var(--pwl-color-border);box-shadow:var(--pwl-shadow-card);backdrop-filter:none;-webkit-backdrop-filter:none}
.pwl-nw .pwl-nw-head h1{color:var(--pwl-color-text);font:var(--pwl-font-display)}
.pwl-nw .pwl-nw-head p{color:var(--pwl-color-text-secondary);font:var(--pwl-font-body)}
.pwl-nw .pwl-nw-tab{background:transparent;border-color:transparent;box-shadow:none;color:var(--pwl-color-text-secondary)}
.pwl-nw .pwl-nw-tab span{color:var(--pwl-color-text);font:var(--pwl-font-tab)}
.pwl-nw .pwl-nw-tab.is-active{background:var(--pwl-color-primary);border-color:var(--pwl-color-primary);box-shadow:none}
.pwl-nw .pwl-nw-tab.is-active span{color:#fff}
.pwl-nw .pwl-nw-section{background:var(--pwl-color-surface)}
.pwl-nw input[type=text],.pwl-nw input[type=url],.pwl-nw input[type=datetime-local],.pwl-nw input[type=search],.pwl-nw textarea,.pwl-nw select{background:var(--pwl-color-control-surface);border-color:var(--pwl-color-control-border);color:var(--pwl-color-text);border-radius:var(--pwl-radius-md);font:var(--pwl-font-control)}
.pwl-nw input:focus,.pwl-nw textarea:focus,.pwl-nw select:focus{border-color:var(--pwl-color-primary);box-shadow:var(--pwl-shadow-focus)}
.pwl-nw .pwl-nw-options label,.pwl-nw .pwl-nw-groups label{background:var(--pwl-color-surface-subtle);border-color:var(--pwl-color-border)}
.pwl-nw .pwl-nw-primary{background:var(--pwl-color-primary);border-radius:var(--pwl-radius-md);box-shadow:none}
.pwl-nw .pwl-nw-primary:hover{background:var(--pwl-color-primary-hover)}
.pwl-nw .pwl-nw-status{background:var(--pwl-color-success-soft);color:var(--pwl-color-success);border-color:var(--pwl-color-success-border)}
.pwl-nw .pwl-nw-status.is-error{background:var(--pwl-color-danger-soft);color:var(--pwl-color-danger)}
.pwl-nw .pwl-nw-badge{background:var(--pwl-color-neutral-soft);color:var(--pwl-color-neutral)}
.pwl-nw .pwl-nw-badge.is-importante{background:var(--pwl-color-warning-soft);color:var(--pwl-color-warning)}
.pwl-nw .pwl-nw-badge.is-urgente{background:var(--pwl-color-danger-soft);color:var(--pwl-color-danger)}
.pwl-nw tr:hover td{background:var(--pwl-color-surface-subtle)}
.pwl-nw .pwl-nw-delete{color:var(--pwl-color-danger)}
</style>

        <script>
        (function(){
            'use strict';
            const root=document.getElementById('pwlNotificationsWorkspace');if(!root)return;
            const ajaxUrl=<?php echo wp_json_encode( $ajax_url ); ?>,nonce=<?php echo wp_json_encode( $nonce ); ?>;
            const status=document.getElementById('pwlNwStatus'),form=document.getElementById('pwlNwForm'),users=document.getElementById('pwlNwUsers'),groups=document.getElementById('pwlNwGroups'),search=document.getElementById('pwlNwSearch'),userList=document.getElementById('pwlNwUserList');
            function msg(text,error){if(!status)return;status.hidden=false;status.textContent=text;status.classList.toggle('is-error',!!error);status.scrollIntoView({behavior:'smooth',block:'nearest'})}
            root.querySelectorAll('.pwl-nw-tab').forEach(b=>b.addEventListener('click',()=>{root.querySelectorAll('.pwl-nw-tab').forEach(x=>x.classList.remove('is-active'));root.querySelectorAll('.pwl-nw-panel').forEach(x=>x.classList.remove('is-active'));b.classList.add('is-active');const p=root.querySelector('[data-panel="'+b.dataset.tab+'"]');if(p)p.classList.add('is-active')}));
            function aud(){const c=root.querySelector('input[name="audience"]:checked');return c?c.value:'all'}function update(){const a=aud();if(users)users.hidden=a!=='selected';if(groups)groups.hidden=a!=='groups'}root.querySelectorAll('input[name="audience"]').forEach(r=>r.addEventListener('change',update));update();
            if(search&&userList)search.addEventListener('input',()=>{const t=search.value.trim().toLowerCase();userList.querySelectorAll('[data-search]').forEach(i=>i.hidden=!!t&&!(i.dataset.search||'').toLowerCase().includes(t))});
            if(form)form.addEventListener('submit',e=>{e.preventDefault();const a=aud();if(a==='selected'&&!form.querySelector('input[name="user_ids[]"]:checked') )return msg('Selecione pelo menos um usuário.',true);if(a==='groups'&&!form.querySelector('input[name="group_keys[]"]:checked'))return msg('Selecione pelo menos um grupo.',true);const fd=new FormData(form);fd.append('action','pwl_notificacoes_workspace_criar');fd.append('nonce',nonce);const btn=form.querySelector('[type="submit"]');btn.disabled=true;btn.textContent='Publicando...';fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json()).then(r=>{if(r&&r.success){msg(r.data.message||'Notificação publicada.');form.reset();update();setTimeout(()=>window.location.reload(),900)}else msg(r&&r.data&&r.data.message?r.data.message:'Não foi possível publicar.',true)}).catch(()=>msg('Falha de comunicação com o Portal.',true)).finally(()=>{btn.disabled=false;btn.textContent='Publicar notificação'})});
            root.querySelectorAll('[data-delete]').forEach(btn=>btn.addEventListener('click',()=>{if(!window.confirm('Excluir esta notificação?'))return;const fd=new FormData();fd.append('action','pwl_notificacoes_workspace_excluir');fd.append('nonce',nonce);fd.append('notification_id',btn.dataset.delete);fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json()).then(r=>{if(r&&r.success){const row=root.querySelector('[data-notification-row="'+btn.dataset.delete+'"]');if(row)row.remove();msg(r.data.message||'Notificação excluída.')}else msg(r&&r.data&&r.data.message?r.data.message:'Não foi possível excluir.',true)}).catch(()=>msg('Falha de comunicação com o Portal.',true))}));
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* =========================================================
     * PREFERÊNCIAS DO USUÁRIO
     * ======================================================= */

    private function get_user_pref( $user_id, $meta_key, $default ) {
        $value = get_user_meta( $user_id, $meta_key, true );

        if ( '' === $value ) {
            $legacy_map = array(
                self::META_PREF_BELL    => self::META_PREF_BELL_LEGACY,
                self::META_PREF_EMAIL   => self::META_PREF_EMAIL_LEGACY,
                self::META_PREF_DESKTOP => self::META_PREF_DESKTOP_LEGACY,
            );

            if ( isset( $legacy_map[ $meta_key ] ) ) {
                $legacy_value = get_user_meta( $user_id, $legacy_map[ $meta_key ], true );

                if ( '' !== $legacy_value ) {
                    update_user_meta( $user_id, $meta_key, absint( $legacy_value ) );
                    $value = $legacy_value;
                }
            }
        }

        if ( '' === $value ) {
            return (bool) $default;
        }

        return (bool) absint( $value );
    }

    private function get_preferences( $user_id ) {
        return array(
            'bell'    => $this->get_user_pref( $user_id, self::META_PREF_BELL, true ),
            'email'   => $this->get_user_pref( $user_id, self::META_PREF_EMAIL, true ),
            'desktop' => $this->get_user_pref( $user_id, self::META_PREF_DESKTOP, false ),
        );
    }

    public function preferences_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="pwl-notif-prefs">Entre no Portal para configurar suas notificações.</div>';
        }

        $prefs = $this->get_preferences( get_current_user_id() );
        ob_start();
        ?>
        <div class="pwl-notif-prefs" id="pwlNotifPrefs">
            <style>
                .pwl-notif-prefs{font-family:"Plus Jakarta Sans",Arial,sans-serif;background:#fff;border:1px solid #e7ebf3;border-radius:14px;padding:20px;max-width:680px}.pwl-notif-prefs h3{margin:0 0 16px}.pwl-notif-prefs label{display:flex;align-items:center;gap:10px;padding:10px 0;font-weight:600}.pwl-notif-prefs .pwl-note{font-size:12px;color:#667085;margin-top:8px}.pwl-notif-prefs button{margin-top:16px;background:#ff5a00;color:#fff;border:0;border-radius:9px;padding:10px 16px;font-weight:700;cursor:pointer}.pwl-notif-prefs .pwl-status{margin-top:12px;font-size:12px;color:#667085}
                body.pwl-dark .pwl-notif-prefs{background:#1d2228;border-color:#343a43;color:#f2f4f7}body.pwl-dark .pwl-notif-prefs .pwl-note,body.pwl-dark .pwl-notif-prefs .pwl-status{color:#98a2b3}

                .pwl-notif-prefs{--o:#ff6a00;--ink:#0b1c30;--text:#545f73;max-width:720px;padding:22px;border:1px solid rgba(148,163,184,.18);border-radius:15px;background:rgba(255,255,255,.78);box-shadow:0 6px 20px rgba(15,23,42,.045);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);color:var(--ink)}.pwl-notif-prefs h3{font-size:20px;font-weight:700}.pwl-notif-prefs label{padding:12px 0;color:var(--ink)}.pwl-notif-prefs .pwl-note,.pwl-notif-prefs .pwl-status{color:var(--text)}.pwl-notif-prefs button{background:#ff6a00;border-radius:9px}.pwl-notif-prefs button:hover{background:#e85c00}
            </style>

            <h3>Preferências de notificações</h3>
            <label><input type="checkbox" id="pwlPrefBell" <?php checked( $prefs['bell'] ); ?>> Receber no sino do Portal</label>
            <label><input type="checkbox" id="pwlPrefEmail" <?php checked( $prefs['email'] ); ?>> Receber por e-mail quando o remetente habilitar esse canal</label>
            <label><input type="checkbox" id="pwlPrefDesktop" <?php checked( $prefs['desktop'] ); ?>> Receber notificação do Windows/navegador enquanto o navegador estiver em execução</label>
            <div class="pwl-note">A notificação nativa depende da permissão do navegador. Para receber avisos com o Portal fechado, salve a preferência e autorize as notificações. O navegador precisa permitir execução em segundo plano.</div>
            <button type="button" id="pwlSaveNotifPrefs">Salvar preferências</button>
            <button type="button" id="pwlEnableDesktopNotif">Autorizar notificações do Windows</button>
            <div class="pwl-status" id="pwlNotifPrefsStatus"></div>
        </div>

        <script>
        (function(){
            const root=document.getElementById('pwlNotifPrefs');
            if(!root)return;
            const ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            const nonce=<?php echo wp_json_encode( wp_create_nonce( 'pwl_notificacoes_nonce' ) ); ?>;
            const status=document.getElementById('pwlNotifPrefsStatus');
            const save=document.getElementById('pwlSaveNotifPrefs');
            const enable=document.getElementById('pwlEnableDesktopNotif');
            const bell=document.getElementById('pwlPrefBell');
            const email=document.getElementById('pwlPrefEmail');
            const desktop=document.getElementById('pwlPrefDesktop');

            if(enable){enable.addEventListener('click',async function(){
                if(!('Notification' in window)){status.textContent='Este navegador não suporta notificações nativas.';return;}
                try{const permission=await Notification.requestPermission();status.textContent=permission==='granted'?'Permissão concedida. Salve suas preferências para registrar o dispositivo.':'Permissão não concedida.';if(permission==='granted')desktop.checked=true;}catch(e){status.textContent='Não foi possível solicitar a permissão.';}
            });}

            if(save){save.addEventListener('click',function(){
                const fd=new FormData();fd.append('action','pwl_notificacoes_salvar_preferencias');fd.append('nonce',nonce);fd.append('bell',bell.checked?'1':'');fd.append('email',email.checked?'1':'');fd.append('desktop',desktop.checked?'1':'');
                save.disabled=true;status.textContent='Salvando...';
                fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json()).then(r=>{status.textContent=r&&r.success?'Preferências salvas.':'Não foi possível salvar.';if(r&&r.success){if(desktop.checked&&Notification.permission==='granted'&&window.pwlPushRegister){window.pwlPushRegister().catch(()=>{status.textContent='Preferências salvas, mas não foi possível registrar o dispositivo.';});}else if(!desktop.checked&&window.pwlPushUnregister){window.pwlPushUnregister().catch(()=>{});}}}).catch(()=>{status.textContent='Falha ao salvar as preferências.';}).finally(()=>{save.disabled=false;});
            });}
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* =========================================================
     * NOTIFICAÇÃO NATIVA DO NAVEGADOR / WINDOWS
     * ======================================================= */

    public function frontend_desktop_script() {
        if ( ! is_user_logged_in() || is_admin() ) {
            return;
        }
        ?>
        <script>
        (function(){
            "use strict";
            if(!('Notification' in window) || !window.fetch)return;

            const ajaxUrl=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            const storageKey='pwl_notificacoes_desktop_v130';
            let nonce='';
            let enabled=false;
            let initialized=false;
            let knownIds={};

            function loadKnown(){try{const raw=localStorage.getItem(storageKey);knownIds=raw?JSON.parse(raw):{};}catch(e){knownIds={};}}
            function saveKnown(){try{localStorage.setItem(storageKey,JSON.stringify(knownIds));}catch(e){}}
            function remember(items){items.forEach(function(item){knownIds[String(item.id)]=1;});saveKnown();}

            function notify(item){
                if(!enabled || Notification.permission!=='granted')return;
                try{if(localStorage.getItem('pwl_push_registered')==='1')return;}catch(e){}
                const title=(item.priority==='urgente'?'URGENTE: ':item.priority==='importante'?'IMPORTANTE: ':'')+(item.title||'Nova notificação');
                const n=new Notification(title,{body:item.message||'Você recebeu uma nova notificação no Portal.',tag:'pwl-notif-'+item.id,renotify:false});
                n.onclick=function(){window.focus();if(item.url){window.location.href=item.url;}n.close();};
            }

            function poll(){
                if(!nonce)return;
                const fd=new FormData();fd.append('action','pwl_notificacoes_listar');fd.append('nonce',nonce);
                fetch(ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',body:fd}).then(r=>r.json()).then(function(r){
                    if(!r||!r.success||!r.data)return;
                    const prefs=r.data.preferences||{};enabled=!!prefs.desktop;
                    const items=Array.isArray(r.data.notifications)?r.data.notifications:[];
                    if(!initialized){remember(items);initialized=true;return;}
                    items.slice().reverse().forEach(function(item){const id=String(item.id);if(!knownIds[id] && !item.is_read){notify(item);}knownIds[id]=1;});saveKnown();
                }).catch(function(){});
            }

            loadKnown();
            const fd=new FormData();fd.append('action','pwl_notificacoes_config');
            fetch(ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',body:fd}).then(r=>r.json()).then(function(r){
                if(!r||!r.success||!r.data||!r.data.logged_in)return;nonce=r.data.nonce||'';enabled=!!(r.data.preferences&&r.data.preferences.desktop);poll();window.setInterval(poll,60000);
            }).catch(function(){});
        })();
        </script>
        <?php
    }

    /* =========================================================
     * API PÚBLICA / COMPATIBILIDADE
     * ======================================================= */

    private function priority_label( $priority ) {
        if ( 'urgente' === $priority ) {
            return 'Urgente';
        }
        if ( 'importante' === $priority ) {
            return 'Importante';
        }
        return 'Normal';
    }

    public function create_notification( $title, $message = '', $url = '', $recipients = 0, $type = 'geral', $args = array() ) {
        $title = sanitize_text_field( $title );
        if ( ! $title ) {
            return false;
        }

        $allowed_types      = array( 'geral', 'comunicado', 'campanha', 'rh', 'ti', 'contratacao', 'enquete' );
        $allowed_priorities = array( 'normal', 'importante', 'urgente' );

        $type = sanitize_key( $type );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'geral';
        }

        $priority = isset( $args['priority'] ) ? sanitize_key( $args['priority'] ) : 'normal';
        if ( ! in_array( $priority, $allowed_priorities, true ) ) {
            $priority = 'normal';
        }

        $send_email = ! empty( $args['send_email'] ) ? 1 : 0;
        $publish_at = ! empty( $args['publish_at'] ) ? $this->sanitize_local_datetime( $args['publish_at'] ) : current_time( 'mysql' );
        $expires_at = ! empty( $args['expires_at'] ) ? $this->sanitize_local_datetime( $args['expires_at'], true ) : null;

        $audience  = 'all';
        $user_ids  = array();
        $source_map = array();

        if ( is_array( $recipients ) ) {
            foreach ( $recipients as $recipient ) {
                $id = absint( $recipient );
                if ( $id > 0 && get_userdata( $id ) ) {
                    $user_ids[] = $id;
                    $source_map[$id] = array( 'type' => 'user', 'key' => '' );
                }
            }
            $user_ids = array_values( array_unique( $user_ids ) );
            if ( ! empty( $user_ids ) ) {
                $audience = 'selected';
            }
        } else {
            $single_id = absint( $recipients );
            if ( $single_id > 0 && get_userdata( $single_id ) ) {
                $audience = 'selected';
                $user_ids = array( $single_id );
                $source_map[$single_id] = array( 'type' => 'user', 'key' => '' );
            }
        }

        return $this->insert_notification(
            $title,
            sanitize_textarea_field( $message ),
            esc_url_raw( $url ),
            $type,
            $priority,
            $audience,
            $user_ids,
            $source_map,
            $send_email,
            $publish_at ?: current_time( 'mysql' ),
            $expires_at
        );
    }
}

function pwl_notificacoes() {
    return PWL_Notificacoes::instance();
}

pwl_notificacoes();

/**
 * Contrato público PWL.
 *
 * $recipients aceita:
 * 0 para todos os usuários, um ID WordPress ou array de IDs.
 *
 * $args aceita priority, send_email, publish_at e expires_at.
 */
function pwl_criar_notificacao( $title, $message = '', $url = '', $recipients = 0, $type = 'geral', $args = array() ) {
    return pwl_notificacoes()->create_notification( $title, $message, $url, $recipients, $type, $args );
}

/* =============================================================
 * COMPATIBILIDADE LEGADA
 * Preserva integrações implantadas sem expor a marca do cliente
 * na interface PWL.
 * ============================================================= */
if ( ! class_exists( 'Cobremack_Notificacoes', false ) ) {
    class_alias( 'PWL_Notificacoes', 'Cobremack_Notificacoes' );
}

if ( ! function_exists( 'cobremack_notificacoes' ) ) {
    function cobremack_notificacoes() {
        return pwl_notificacoes();
    }
}

if ( ! function_exists( 'cobremack_criar_notificacao' ) ) {
    function cobremack_criar_notificacao( $title, $message = '', $url = '', $recipients = 0, $type = 'geral', $args = array() ) {
        return pwl_criar_notificacao( $title, $message, $url, $recipients, $type, $args );
    }
}
