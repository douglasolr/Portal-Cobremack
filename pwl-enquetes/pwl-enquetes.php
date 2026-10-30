<?php
/**
 * Plugin Name: PWL Enquetes
 * Description: Crie enquetes internas e publique-as em qualquer pagina usando shortcode.
 * Version: 2.16.9
 * Author: PWL
 * Text Domain: pwl-enquetes
 *
 * Changelog:
 * 2.16.1 - Votacao em tela unica com confirmacao modal; remove etapa de revisao e progresso.
 * 2.16.0 - Editor experimental React/Vite/TypeScript com shadcn/ui e tokens PWL; fallback PHP preservado.
 * 2.15.0 - Adiciona confirmacao React acessivel antes de remover alternativas, preservando gravacao PHP.
 * 2.13.0 - Reformula a navegacao e ferramentas administrativas em React com tokens oficiais; preserva conteudo PHP e contratos.
 * 2.12.1 - Corrige duplicidade das abas administrativas com fallback PHP preservado.
 * 2.12.0 - Renderiza as abas administrativas em React com componentes e tokens PWL, mantendo fallback PHP e filtro de registros.
 * 2.11.1 - Corrige duplicacao de abas e aplica navegacao compacta com icones, conforme referencia shadcn/ui.
 * 2.11.0 - Consolida controles React de alternativas e configuracoes, com persistencia PHP original e fallback.
 * 2.9.0 - Editor React progressivo para titulo e alternativas, integrado aos campos originais e aos tokens oficiais do Design System.
 * 2.7.0 - Navegacao administrativa React progressiva e filtro interativo de tabelas; formularios e backend preservados.
 * 2.4.1 - Aplica estilo Questionnaire as enquetes publicas sem alterar o fluxo de votos.
 * 2.3.4 - Alinha Admin UI e Portal UI ao PWL Design System 1.2.2, preservando o layout adaptativo no popup.
 * 2.3.3 - Adapta automaticamente a enquete ao container do popup, com layout responsivo por largura disponivel.
 * 2.3.0 - Adiciona exclusao individual de votos com justificativa, dupla confirmacao e auditoria permanente.
 * 2.2.9 - Restaura a tipografia compacta das alternativas antes do voto na Home e preserva a ampliacao dos resultados.
 * 2.6.0 - Revisao e confirmacao do voto em React, indicador de progresso e melhorias de acessibilidade.
 * 2.5.0 - Frontend React progressivo para votacao, mantendo backend e contratos.
 * 2.4.0 - Admin UI registrada como submenu de Plataforma; slug, shortcodes, dados e capacidades preservados.
 * 2.2.8 - Aumenta a tipografia das alternativas exibidas nos resultados do widget da Home.
 * 2.2.7 - Corrige a cascata tipografica das alternativas no widget da Home e fixa o rotulo em 14.3px.
 * 2.2.6 - Aumenta a legibilidade do texto das alternativas no widget da Home para 13.2px.
 * 2.2.5 - Normaliza a tipografia do widget de enquete na Home, preservando a escala ampliada da pagina publicada.
 *
 * Log:
 * 2.16.0 | 2026-10-27 | Build estatico, dez componentes shadcn/ui e alternancia para editor PHP.
 * 2.15.0 | 2026-10-27 | Dialogo de confirmacao no editor React, sem alterar contratos ou persistencia.
 * 2.13.0 | 2026-10-27 | Primeira etapa da nova interface administrativa, navegacao React, busca e visual aprimorado.
 * 2.12.1 | 2026-10-27 | Corrige exibicao simultanea das abas PHP e React.
 * 2.12.0 | 2026-10-27 | Abas React reais com icones, navegacao original preservada e fallback PHP.
 * 2.11.1 | 2026-10-27 | Navegacao administrativa unica, abas compactas com icones e filtro React preservado.
 * 2.11.0 | 2026-10-27 | Controles de tipo, resultados, persistencia e encerramento integrados ao editor React.
 * 2.9.0 | 2026-10-27 | Controles React sincronizados com o editor PHP; preservados biblioteca, emojis, imagens e persistencia.
 * 2.8.0 | 2026-10-27 | Prévia interativa React no editor, sem alterar persistência ou contratos.
 * 2.7.0 | 2026-10-27 | React na navegacao da gestao, filtro local e fallback PHP sem alterar contratos.
 * 2.4.1 | 2026-10-27 | Aparencia Questionnaire com tokens do Design System; preservados contratos e dados.
 * 2.4.0 | 2026-10-09 | Menu administrativo integrado a Plataforma; dados, capacidades e shortcodes preservados.
 * 2.3.4 | 2026-09-29 | Design System 1.2.2 aplicado aos contextos administrativos e publicos, sem alterar regras de voto ou responsividade do popup.
 * 2.3.3 | 2026-09-29 | Layout adaptativo no PWL Popups com uso integral da largura e composicao em duas colunas quando houver espaco.
 * 2.3.0 | 2026-09-29 | Exclusao auditada de votos com copia integral do registro removido.
 * 2.2.9 | 2026-09-22 | Reversao do aumento das alternativas antes do voto; resultados permanecem com tipografia ampliada.
 * 2.2.8 | 2026-09-22 | Ajuste de legibilidade das alternativas no bloco de resultados do widget da Home.
 * 2.2.7 | 2026-09-22 | Correcao de especificidade CSS e aumento das alternativas para 14.3px no contexto Home.
 * 2.2.6 | 2026-09-22 | Ajuste tipografico das alternativas no contexto Home.
 * 2.2.5 | 2026-09-22 | Ajuste visual exclusivo do contexto Home.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PWL_Enquetes {
    const VERSION = '2.16.9';
    const DB_VERSION = '1.1.0';
    const DB_VERSION_OPTION = 'pwl_enquetes_db_version';
    const POST_TYPE = 'pwl_enquete';
    const TABLE_SUFFIX = 'pwl_enquete_votos';
    const AUDIT_TABLE_SUFFIX = 'pwl_enquete_votos_log';
    const LEGACY_POST_TYPE = 'cm_enquete';
    const LEGACY_TABLE_SUFFIX = 'cm_enquete_votos';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'maybe_upgrade'));
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'register_admin_pages'), 99);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_pwl_salvar_enquete_admin', array($this, 'handle_admin_save_poll'));
        add_action('admin_post_pwl_excluir_enquete_admin', array($this, 'handle_admin_delete_poll'));
        add_shortcode('pwl_enquete', array($this, 'shortcode'));
        add_shortcode('pwl_poll', array($this, 'shortcode'));
        add_shortcode('cobre' . 'mack_enquete', array($this, 'shortcode'));
        add_shortcode('cobre' . 'mack_poll', array($this, 'shortcode'));
        add_action('wp_ajax_pwl_registrar_voto', array($this, 'register_vote'));
        add_action('wp_ajax_' . 'cm_registrar_voto', array($this, 'register_vote'));
        add_action('admin_post_pwl_exportar_relatorio_enquete', array($this, 'export_poll_report'));
        add_action('admin_post_pwl_excluir_voto_enquete', array($this, 'handle_delete_vote'));
        add_action('admin_footer', array($this, 'render_pwl_media_picker_modal'), 40);
    }

    public static function activate() {
        $instance = self::instance();
        $instance->install_schema();
        $instance->migrate_legacy_data();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        $instance->register_post_type();
        flush_rewrite_rules();
    }

    public function maybe_upgrade() {
        $installed = get_option(self::DB_VERSION_OPTION, '0');
        if (version_compare($installed, self::DB_VERSION, '>=')) {
            return;
        }

        $this->install_schema();
        $this->migrate_legacy_data();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    private function install_schema() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $audit_table = $wpdb->prefix . self::AUDIT_TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            choices longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY poll_user (poll_id,user_id),
            KEY poll_id (poll_id)
        ) {$charset_collate};";

        $audit_sql = "CREATE TABLE {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(30) NOT NULL DEFAULT 'delete',
            vote_id bigint(20) unsigned NOT NULL,
            poll_id bigint(20) unsigned NOT NULL,
            voter_user_id bigint(20) unsigned NOT NULL,
            voter_display_name varchar(191) NOT NULL DEFAULT '',
            voter_login varchar(191) NOT NULL DEFAULT '',
            voter_email varchar(191) NOT NULL DEFAULT '',
            choices longtext NOT NULL,
            choice_labels longtext NOT NULL,
            vote_created_at datetime NOT NULL,
            reason text NOT NULL,
            performed_by bigint(20) unsigned NOT NULL,
            performed_by_name varchar(191) NOT NULL DEFAULT '',
            ip_address varchar(100) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            performed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY poll_id (poll_id),
            KEY vote_id (vote_id),
            KEY voter_user_id (voter_user_id),
            KEY performed_by (performed_by),
            KEY performed_at (performed_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        dbDelta($audit_sql);
    }

    private function migrate_legacy_data() {
        global $wpdb;

        $legacy_table = $wpdb->prefix . self::LEGACY_TABLE_SUFFIX;
        $current_table = $wpdb->prefix . self::TABLE_SUFFIX;
        $legacy_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_table)));

        if ($legacy_table === $legacy_exists) {
            $wpdb->query(
                "INSERT IGNORE INTO {$current_table} (id, poll_id, user_id, choices, created_at)
                 SELECT id, poll_id, user_id, choices, created_at FROM {$legacy_table}"
            );
        }

        $wpdb->update(
            $wpdb->posts,
            array('post_type' => self::POST_TYPE),
            array('post_type' => self::LEGACY_POST_TYPE),
            array('%s'),
            array('%s')
        );

        $meta_map = array(
            '_cm_options' => '_pwl_options',
            '_cm_type' => '_pwl_type',
            '_cm_results_visibility' => '_pwl_results_visibility',
            '_cm_status' => '_pwl_status',
            '_cm_closes_at' => '_pwl_closes_at',
        );

        foreach ($meta_map as $legacy_key => $current_key) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                     SELECT source.post_id, %s, source.meta_value
                     FROM {$wpdb->postmeta} source
                     INNER JOIN {$wpdb->posts} posts ON posts.ID = source.post_id
                     WHERE source.meta_key = %s
                       AND posts.post_type = %s
                       AND NOT EXISTS (
                           SELECT 1 FROM {$wpdb->postmeta} target
                           WHERE target.post_id = source.post_id AND target.meta_key = %s
                       )",
                    $current_key,
                    $legacy_key,
                    self::POST_TYPE,
                    $current_key
                )
            );
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Enquetes',
                'singular_name' => 'Enquete',
                'menu_name' => 'Enquetes',
                'add_new' => 'Adicionar nova',
                'add_new_item' => 'Adicionar nova enquete',
                'edit_item' => 'Editar enquete',
                'new_item' => 'Nova enquete',
                'view_item' => 'Visualizar enquete',
                'search_items' => 'Pesquisar enquetes',
                'not_found' => 'Nenhuma enquete encontrada',
                'all_items' => 'Enquetes',
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-chart-bar',
            'supports' => array('title', 'editor'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
    }

    public function register_admin_pages() {
        $capability = $this->admin_capability();

        add_submenu_page(
            'pwl-platform',
            'Enquetes',
            'Enquetes',
            $capability,
            'pwl-enquetes',
            array($this, 'render_workspace_page')
        );

        add_submenu_page(
            null,
            'Relatórios de Enquetes',
            'Relatórios de Enquetes',
            $capability,
            'pwl-enquetes-relatorios',
            array($this, 'redirect_legacy_reports_page')
        );

        add_submenu_page(
            null,
            'Sobre Enquetes',
            'Sobre Enquetes',
            $capability,
            'pwl-enquetes-sobre',
            array($this, 'redirect_legacy_about_page')
        );
    }

    private function admin_capability() {
        return (string) apply_filters('pwl_enquetes_admin_capability', 'read');
    }

    private function user_can_manage_enquetes() {
        return current_user_can($this->admin_capability());
    }

    public function redirect_legacy_reports_page() {
        wp_safe_redirect($this->workspace_url('reports'));
        exit;
    }

    public function redirect_legacy_about_page() {
        wp_safe_redirect($this->workspace_url('about'));
        exit;
    }

    private function workspace_url($tab = 'editor', $args = array()) {
        $url = admin_url('admin.php?page=pwl-enquetes&tab=' . sanitize_key($tab));

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    private function get_workspace_polls() {
        return get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 300,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
    }

    private function get_poll_options($poll_id = 0) {
        $options = $poll_id ? get_post_meta($poll_id, '_pwl_options', true) : array();

        if (!is_array($options) || count($options) < 2) {
            $options = array(
                array('key' => wp_generate_uuid4(), 'label' => '', 'emoji' => '', 'image_id' => 0),
                array('key' => wp_generate_uuid4(), 'label' => '', 'emoji' => '', 'image_id' => 0),
            );
        }

        foreach ($options as &$option) {
            if (!is_array($option)) {
                $option = array();
            }
            $option = wp_parse_args($option, array(
                'key' => wp_generate_uuid4(),
                'label' => '',
                'emoji' => '',
                'image_id' => 0,
            ));
            $option['image_id'] = absint($option['image_id']);
        }
        unset($option);

        return $options;
    }

    private function encode_emoji_for_storage($emoji) {
        $emoji = trim((string) $emoji);
        if ('' === $emoji) {
            return '';
        }
        if (function_exists('mb_substr')) {
            $emoji = mb_substr($emoji, 0, 12, 'UTF-8');
        }
        return 'pwl64:' . base64_encode($emoji);
    }

    private function decode_option_emoji($option) {
        $value = is_array($option) && isset($option['emoji']) ? (string) $option['emoji'] : '';
        if (0 === strpos($value, 'pwl64:')) {
            $decoded = base64_decode(substr($value, 6), true);
            return false === $decoded ? '' : $decoded;
        }
        return $value;
    }

    private function results_admin_capability() {
        return (string) apply_filters('pwl_enquetes_results_admin_capability', 'manage_options');
    }

    private function user_can_view_admin_results() {
        return current_user_can($this->results_admin_capability());
    }

    private function sanitize_admin_datetime($value) {
        $value = sanitize_text_field((string) $value);
        if ('' === $value) {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();

        if (!$date || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            return '';
        }

        return $date->format('Y-m-d\TH:i');
    }

    public function handle_admin_save_poll() {
        if (!$this->user_can_manage_enquetes()) {
            wp_die('Acesso não autorizado.');
        }

        check_admin_referer('pwl_salvar_enquete_admin', 'pwl_enquete_admin_nonce');

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $keys = isset($_POST['pwl_option_keys']) ? (array) wp_unslash($_POST['pwl_option_keys']) : array();
        $labels = isset($_POST['pwl_option_labels']) ? (array) wp_unslash($_POST['pwl_option_labels']) : array();
        $emojis = isset($_POST['pwl_option_emojis']) ? (array) wp_unslash($_POST['pwl_option_emojis']) : array();
        $option_image_ids = isset($_POST['pwl_option_image_ids']) ? (array) wp_unslash($_POST['pwl_option_image_ids']) : array();
        $image_id = isset($_POST['pwl_image_id']) ? absint($_POST['pwl_image_id']) : 0;
        $persistent_until_vote = !empty($_POST['pwl_persistent_until_vote']) ? '1' : '0';

        if ($poll_id && self::POST_TYPE !== get_post_type($poll_id)) {
            wp_safe_redirect($this->workspace_url('polls', array('pwl_error' => 'invalid')));
            exit;
        }

        $options = array();
        foreach ($labels as $index => $label) {
            $label = sanitize_text_field($label);
            if ('' === $label) {
                continue;
            }

            $key = isset($keys[$index]) ? sanitize_text_field($keys[$index]) : '';
            if ('' === $key) {
                $key = wp_generate_uuid4();
            }

            $emoji = isset($emojis[$index]) ? $this->encode_emoji_for_storage($emojis[$index]) : '';

            $option_image_id = isset($option_image_ids[$index]) ? absint($option_image_ids[$index]) : 0;
            $options[] = array('key' => $key, 'label' => $label, 'emoji' => $emoji, 'image_id' => $option_image_id);
        }

        if ('' === $title || count($options) < 2) {
            wp_safe_redirect($this->workspace_url('editor', array(
                'edit' => $poll_id,
                'pwl_error' => '' === $title ? 'title' : 'options',
            )));
            exit;
        }

        $type = isset($_POST['pwl_type']) && 'multiple' === sanitize_key(wp_unslash($_POST['pwl_type'])) ? 'multiple' : 'single';
        $visibility = isset($_POST['pwl_results_visibility']) ? sanitize_key(wp_unslash($_POST['pwl_results_visibility'])) : 'after_vote';
        if (!in_array($visibility, array('after_vote', 'always', 'admin'), true)) {
            $visibility = 'after_vote';
        }

        $status = isset($_POST['pwl_status']) && 'closed' === sanitize_key(wp_unslash($_POST['pwl_status'])) ? 'closed' : 'active';
        $closes_at = isset($_POST['pwl_closes_at']) ? $this->sanitize_admin_datetime(wp_unslash($_POST['pwl_closes_at'])) : '';

        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $description,
        );

        if ($poll_id) {
            $post_data['ID'] = $poll_id;
            $saved_id = wp_update_post(wp_slash($post_data), true);
        } else {
            $saved_id = wp_insert_post(wp_slash($post_data), true);
        }

        if (is_wp_error($saved_id) || !$saved_id) {
            wp_safe_redirect($this->workspace_url('editor', array(
                'edit' => $poll_id,
                'pwl_error' => 'save',
            )));
            exit;
        }

        update_post_meta($saved_id, '_pwl_options', $options);
        update_post_meta($saved_id, '_pwl_type', $type);
        update_post_meta($saved_id, '_pwl_results_visibility', $visibility);
        update_post_meta($saved_id, '_pwl_status', $status);
        update_post_meta($saved_id, '_pwl_closes_at', $closes_at);
        update_post_meta($saved_id, '_pwl_image_id', $image_id);
        update_post_meta($saved_id, '_pwl_persistent_until_vote', $persistent_until_vote);

        wp_safe_redirect($this->workspace_url('polls', array('pwl_saved' => 1, 'poll_id' => absint($saved_id))));
        exit;
    }

    public function handle_admin_delete_poll() {
        if (!$this->user_can_manage_enquetes()) {
            wp_die('Acesso não autorizado.');
        }

        $poll_id = isset($_GET['poll_id']) ? absint($_GET['poll_id']) : 0;
        if (!$poll_id || self::POST_TYPE !== get_post_type($poll_id)) {
            wp_safe_redirect($this->workspace_url('polls', array('pwl_error' => 'invalid')));
            exit;
        }

        check_admin_referer('pwl_excluir_enquete_' . $poll_id);
        wp_trash_post($poll_id);

        wp_safe_redirect($this->workspace_url('polls', array('pwl_deleted' => 1)));
        exit;
    }

    private function vote_delete_capability() {
        return (string) apply_filters('pwl_enquetes_delete_votes_capability', 'manage_options');
    }

    private function user_can_delete_votes() {
        return current_user_can($this->vote_delete_capability());
    }

    private function text_length($value) {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    public function handle_delete_vote() {
        if (!$this->user_can_delete_votes()) {
            wp_die('Você não possui permissão para excluir votos.', 'Acesso negado', array('response' => 403));
        }

        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))) {
            wp_die('Método de requisição inválido.', 'Exclusão de voto', array('response' => 405));
        }

        $vote_id = isset($_POST['vote_id']) ? absint($_POST['vote_id']) : 0;
        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
        $confirmation = isset($_POST['confirmation']) ? sanitize_text_field(wp_unslash($_POST['confirmation'])) : '';

        if (!$vote_id || !$poll_id || self::POST_TYPE !== get_post_type($poll_id)) {
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'invalid_vote')));
            exit;
        }

        check_admin_referer('pwl_excluir_voto_' . $vote_id, 'pwl_delete_vote_nonce');

        if ($this->text_length($reason) < 15) {
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'vote_reason')));
            exit;
        }

        if ('EXCLUIR' !== $confirmation) {
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'vote_confirmation')));
            exit;
        }

        global $wpdb;
        $votes_table = $wpdb->prefix . self::TABLE_SUFFIX;
        $audit_table = $wpdb->prefix . self::AUDIT_TABLE_SUFFIX;
        $vote = $wpdb->get_row($wpdb->prepare(
            "SELECT id, poll_id, user_id, choices, created_at FROM {$votes_table} WHERE id = %d AND poll_id = %d LIMIT 1",
            $vote_id,
            $poll_id
        ));

        if (!$vote) {
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'invalid_vote')));
            exit;
        }

        $voter = get_userdata($vote->user_id);
        $actor = wp_get_current_user();
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $user_agent = substr($user_agent, 0, 255);

        $wpdb->query('START TRANSACTION');

        $logged = $wpdb->insert(
            $audit_table,
            array(
                'action' => 'delete',
                'vote_id' => absint($vote->id),
                'poll_id' => absint($vote->poll_id),
                'voter_user_id' => absint($vote->user_id),
                'voter_display_name' => $voter ? sanitize_text_field($voter->display_name) : 'Usuário removido',
                'voter_login' => $voter ? sanitize_user($voter->user_login, true) : '',
                'voter_email' => $voter ? sanitize_email($voter->user_email) : '',
                'choices' => (string) $vote->choices,
                'choice_labels' => $this->get_choice_labels($vote->choices, $this->get_option_labels($poll_id)),
                'vote_created_at' => $vote->created_at,
                'reason' => $reason,
                'performed_by' => absint($actor->ID),
                'performed_by_name' => sanitize_text_field($actor->display_name),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'performed_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if (false === $logged) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'vote_delete_failed')));
            exit;
        }

        $deleted = $wpdb->delete(
            $votes_table,
            array('id' => $vote_id, 'poll_id' => $poll_id),
            array('%d', '%d')
        );

        if (1 !== $deleted) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_error' => 'vote_delete_failed')));
            exit;
        }

        $wpdb->query('COMMIT');
        do_action('pwl_enquetes_vote_deleted', $vote_id, $poll_id, absint($vote->user_id), absint($actor->ID), $reason);

        wp_safe_redirect($this->workspace_url('reports', array('poll_id' => $poll_id, 'pwl_vote_deleted' => 1)));
        exit;
    }

    public function render_workspace_page() {
        if (!$this->user_can_manage_enquetes()) {
            wp_die('Acesso não autorizado.');
        }

        $allowed_tabs = array('editor', 'polls', 'reports', 'about');
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'editor';
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'editor';
        }

        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        if ($edit_id) {
            $active_tab = 'editor';
        }

        $polls = $this->get_workspace_polls();
        $total_votes = 0;
        $active_count = 0;

        foreach ($polls as $poll) {
            $total_votes += $this->get_vote_count($poll->ID);
            if ('publish' === $poll->post_status && $this->is_open($poll->ID)) {
                $active_count++;
            }
        }
        ?>
        <div class="wrap pwle-workspace pwl-ds pwl-page">
            <header class="pwl-page-header pwle-workspace__header">
                <div>
                    <span class="pwl-page-header__eyebrow">RECURSOS CORPORATIVOS · ADMINISTRAÇÃO</span>
                    <h1 class="pwl-page-header__title">Enquetes</h1>
                    <p class="pwl-page-header__description">Crie enquetes, acompanhe participações e gerencie pesquisas internas.</p>
                </div>
                <div class="pwl-page-header__actions">
                    <a class="pwl-button pwl-button--secondary" href="<?php echo esc_url($this->workspace_url('polls')); ?>">
                        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                        Ver enquetes
                    </a>
                    <a class="pwl-button pwl-button--primary" href="<?php echo esc_url($this->workspace_url('editor')); ?>">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        Nova enquete
                    </a>
                    <div class="pwle-version-card"><span>Status do módulo</span><strong>Em produção</strong></div>
                </div>
            </header>

            <?php $this->render_workspace_notice(); ?>

            <nav class="pwl-tabs pwle-workspace__tabs" aria-label="Seções do módulo">
                <?php
                $tabs = array(
                    'editor' => array('dashicons-edit', $edit_id ? 'Editar enquete' : 'Nova enquete', 'Pergunta, alternativas e publicação'),
                    'polls' => array('dashicons-list-view', 'Enquetes', 'Cadastros, status e shortcodes'),
                    'reports' => array('dashicons-chart-bar', 'Relatórios', 'Participações e exportações'),
                    'about' => array('dashicons-info-outline', 'Sobre', 'Versão, contratos e dependências'),
                );
                foreach ($tabs as $tab_key => $tab_data) :
                    $tab_args = ('editor' === $tab_key && $edit_id) ? array('edit' => $edit_id) : array();
                    ?>
                    <a class="pwl-tabs__item pwle-workspace__tab <?php echo $active_tab === $tab_key ? 'is-selected' : ''; ?>" data-pwle-tab="<?php echo esc_attr($tab_key); ?>" href="<?php echo esc_url($this->workspace_url($tab_key, $tab_args)); ?>" <?php echo $active_tab === $tab_key ? 'aria-current="page"' : ''; ?>>
                        <span class="dashicons <?php echo esc_attr($tab_data[0]); ?>" aria-hidden="true"></span>
                        <span><strong><?php echo esc_html($tab_data[1]); ?></strong></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <main class="pwle-workspace__content">
                <section class="pwle-workspace__panel" data-pwle-panel="editor" <?php echo 'editor' !== $active_tab ? 'hidden' : ''; ?>><?php $this->render_workspace_editor($edit_id); ?></section>
                <section class="pwle-workspace__panel" data-pwle-panel="polls" <?php echo 'polls' !== $active_tab ? 'hidden' : ''; ?>><?php $this->render_workspace_polls($polls); ?></section>
                <section class="pwle-workspace__panel" data-pwle-panel="reports" <?php echo 'reports' !== $active_tab ? 'hidden' : ''; ?>><?php $this->render_workspace_reports($polls, $active_count, $total_votes); ?></section>
                <section class="pwle-workspace__panel" data-pwle-panel="about" <?php echo 'about' !== $active_tab ? 'hidden' : ''; ?>><?php $this->render_workspace_about(); ?></section>
            </main>
        </div>
        <?php
    }

    private function render_workspace_notice() {
        $error = isset($_GET['pwl_error']) ? sanitize_key(wp_unslash($_GET['pwl_error'])) : '';

        if (!empty($_GET['pwl_saved'])) {
            echo '<div class="pwl-alert pwl-alert--success pwle-notice"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div class="pwl-alert__content"><strong class="pwl-alert__title">Enquete salva e publicada.</strong>O cadastro já está disponível para uso pelo shortcode.</div></div>';
        } elseif (!empty($_GET['pwl_deleted'])) {
            echo '<div class="pwl-alert pwl-alert--success pwle-notice"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div class="pwl-alert__content"><strong class="pwl-alert__title">Enquete movida para a lixeira.</strong>Os votos foram preservados para permitir recuperação.</div></div>';
        } elseif (!empty($_GET['pwl_vote_deleted'])) {
            echo '<div class="pwl-alert pwl-alert--success pwle-notice"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div class="pwl-alert__content"><strong class="pwl-alert__title">Voto excluído com sucesso.</strong>A participação foi removida e a ação permaneceu registrada no histórico de auditoria.</div></div>';
        } elseif ($error) {
            $messages = array(
                'title' => 'Informe a pergunta da enquete.',
                'options' => 'Cadastre pelo menos duas alternativas válidas.',
                'save' => 'Não foi possível salvar a enquete. Tente novamente.',
                'invalid' => 'A enquete informada não foi encontrada.',
                'invalid_vote' => 'O voto informado não foi encontrado ou já havia sido excluído.',
                'vote_reason' => 'Informe uma justificativa com pelo menos 15 caracteres.',
                'vote_confirmation' => 'A segunda confirmação da exclusão não foi concluída.',
                'vote_delete_failed' => 'Não foi possível excluir o voto. Nenhum dado foi removido.',
            );
            $message = isset($messages[$error]) ? $messages[$error] : 'Revise os dados informados e tente novamente.';
            echo '<div class="pwl-alert pwl-alert--danger pwle-notice"><span class="dashicons dashicons-warning" aria-hidden="true"></span><div class="pwl-alert__content"><strong class="pwl-alert__title">Não foi possível concluir.</strong>' . esc_html($message) . '</div></div>';
        }
    }

    private function render_workspace_editor($edit_id = 0) {
        $poll = $edit_id ? get_post($edit_id) : null;
        if ($poll && self::POST_TYPE !== $poll->post_type) {
            $poll = null;
            $edit_id = 0;
        }

        $options = $this->get_poll_options($edit_id);
        $type = $edit_id ? (get_post_meta($edit_id, '_pwl_type', true) ?: 'single') : 'single';
        $visibility = $edit_id ? (get_post_meta($edit_id, '_pwl_results_visibility', true) ?: 'after_vote') : 'after_vote';
        $status = $edit_id ? (get_post_meta($edit_id, '_pwl_status', true) ?: 'active') : 'active';
        $closes_at = $edit_id ? get_post_meta($edit_id, '_pwl_closes_at', true) : '';
        $title = $poll ? $poll->post_title : '';
        $description = $poll ? $poll->post_content : '';
        $image_id = $edit_id ? absint(get_post_meta($edit_id, '_pwl_image_id', true)) : 0;
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium_large') : '';
        $persistent_until_vote = $edit_id ? ('1' === (string) get_post_meta($edit_id, '_pwl_persistent_until_vote', true)) : false;
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pwle-editor" id="pwlPollWorkspaceForm">
            <input type="hidden" name="action" value="pwl_salvar_enquete_admin">
            <input type="hidden" name="poll_id" value="<?php echo esc_attr($edit_id); ?>">
            <?php wp_nonce_field('pwl_salvar_enquete_admin', 'pwl_enquete_admin_nonce'); ?>

            <div class="pwle-editor__grid">
                <div class="pwle-editor__main">
                    <section class="pwl-card pwle-card">
                        <div class="pwl-card__header pwle-card__header">
                            <span class="pwle-card__icon dashicons dashicons-format-status" aria-hidden="true"></span>
                            <div>
                                <h3 class="pwl-card__title">Pergunta e contexto</h3>
                                <p class="pwl-card__description">Escreva uma pergunta direta e, se necessário, explique o objetivo da consulta.</p>
                            </div>
                        </div>
                        <div class="pwl-card__body">
                            <label class="pwl-field">
                                <span class="pwl-field__label">Pergunta da enquete</span>
                                <input type="text" class="pwl-input" name="title" value="<?php echo esc_attr($title); ?>" placeholder="Ex.: Como você avalia a qualidade da internet na empresa?" maxlength="180" required>
                                <span class="pwl-field__helper">Use uma única pergunta, com linguagem simples e sem induzir a resposta.</span>
                            </label>

                            <div class="pwl-field pwle-rich-editor">
                                <span class="pwl-field__label">Descrição <small>opcional</small></span>
                                <?php
                                wp_editor(
                                    $description,
                                    'pwl_enquete_description',
                                    array(
                                        'textarea_name' => 'description',
                                        'textarea_rows' => 7,
                                        'media_buttons' => false,
                                        'teeny' => true,
                                        'quicktags' => true,
                                        'tinymce' => array(
                                            'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                                            'toolbar2' => '',
                                        ),
                                    )
                                );
                                ?>
                                <span class="pwl-field__helper">Use negrito, itálico, listas e links para contextualizar a pesquisa.</span>
                            </div>

                            <div class="pwl-field pwle-field--last pwle-image-field">
                                <span class="pwl-field__label">Imagem da enquete <small>opcional</small></span>
                                <input type="hidden" name="pwl_image_id" id="pwlPollImageId" value="<?php echo esc_attr($image_id); ?>">
                                <div class="pwle-image-picker <?php echo $image_url ? 'has-image' : ''; ?>" id="pwlPollImagePicker">
                                    <div class="pwle-image-preview" id="pwlPollImagePreview">
                                        <?php if ($image_url) : ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="Prévia da imagem da enquete">
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                            <strong>Nenhuma imagem selecionada</strong>
                                            <small>PNG, JPG ou WebP da Biblioteca de Mídia PWL.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pwle-image-actions">
                                        <button type="button" class="pwl-button pwl-button--secondary" id="pwlSelectPollImage">Selecionar imagem</button>
                                        <button type="button" class="pwl-button pwl-button--ghost" id="pwlRemovePollImage" <?php echo $image_url ? '' : 'hidden'; ?>>Remover</button>
                                    </div>
                                </div>
                                <span class="pwl-field__helper">A seleção abre a Biblioteca de Mídia PWL completa, com pastas, filtros, pesquisa e favoritos.</span>
                            </div>
                        </div>
                    </section>

                    <section class="pwl-card pwle-card">
                        <div class="pwl-card__header pwle-card__header">
                            <span class="pwle-card__icon dashicons dashicons-list-view" aria-hidden="true"></span>
                            <div>
                                <h3 class="pwl-card__title">Alternativas</h3>
                                <p class="pwl-card__description">Cadastre no mínimo duas opções. Arraste para reorganizar a ordem de exibição.</p>
                            </div>
                        </div>
                        <div class="pwl-card__body">
                            <div id="pwl-options-list" class="pwle-options-list">
                                <?php foreach ($options as $index => $option) : ?>
                                    <?php
                                    $option_image_id = !empty($option['image_id']) ? absint($option['image_id']) : 0;
                                    $option_image_url = $option_image_id ? wp_get_attachment_image_url($option_image_id, 'thumbnail') : '';
                                    ?>
                                    <div class="pwl-option-row pwle-option-row">
                                        <span class="pwle-option-number"><?php echo esc_html($index + 1); ?></span>
                                        <span class="dashicons dashicons-menu pwl-drag" aria-hidden="true"></span>
                                        <input type="hidden" name="pwl_option_keys[]" value="<?php echo esc_attr($option['key']); ?>">
                                        <div class="pwle-option-emoji-wrap">
                                            <input type="text" class="pwl-input pwle-option-emoji" name="pwl_option_emojis[]" value="<?php echo esc_attr($this->decode_option_emoji($option)); ?>" placeholder="😀" maxlength="12" aria-label="Emoji da alternativa">
                                            <button type="button" class="pwl-button pwl-button--ghost pwle-emoji-open" aria-label="Escolher emoji" title="Escolher emoji">☺</button>
                                        </div>
                                        <input type="text" class="pwl-input pwle-option-label" name="pwl_option_labels[]" value="<?php echo esc_attr($option['label']); ?>" placeholder="Digite a alternativa" maxlength="180" required>
                                        <div class="pwle-option-image">
                                            <input type="hidden" name="pwl_option_image_ids[]" value="<?php echo esc_attr($option_image_id); ?>" class="pwle-option-image-id">
                                            <div class="pwle-option-image-preview <?php echo $option_image_url ? 'has-image' : ''; ?>">
                                                <?php if ($option_image_url) : ?><img src="<?php echo esc_url($option_image_url); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php endif; ?>
                                            </div>
                                            <button type="button" class="pwl-button pwl-button--ghost pwle-option-image-select" title="Selecionar imagem na Biblioteca PWL"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></button>
                                            <button type="button" class="pwl-button pwl-button--ghost pwle-option-image-remove" title="Remover imagem" <?php echo $option_image_url ? '' : 'hidden'; ?>><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                                        </div>
                                        <button type="button" class="pwl-button pwl-button--ghost pwl-remove-option" aria-label="Remover alternativa">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="pwl-button pwl-button--secondary pwle-add-option" id="pwl-add-option">
                                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                                Adicionar alternativa
                            </button>
                        </div>
                    </section>
                </div>

                <aside class="pwle-editor__aside">
                    <section class="pwl-card pwle-card">
                        <div class="pwl-card__header pwle-card__header">
                            <span class="pwle-card__icon dashicons dashicons-admin-settings" aria-hidden="true"></span>
                            <div>
                                <h3 class="pwl-card__title">Configuração</h3>
                                <p class="pwl-card__description">Defina a regra de resposta e a visibilidade dos resultados.</p>
                            </div>
                        </div>
                        <div class="pwl-card__body">
                            <fieldset class="pwle-fieldset">
                                <legend>Quantas alternativas cada pessoa pode escolher?</legend>
                                <label class="pwle-choice-card <?php echo 'single' === $type ? 'is-selected' : ''; ?>">
                                    <input type="radio" name="pwl_type" value="single" <?php checked($type, 'single'); ?>>
                                    <span class="dashicons dashicons-marker" aria-hidden="true"></span>
                                    <span><strong>Escolha única</strong><small>O colaborador seleciona apenas uma alternativa.</small></span>
                                </label>
                                <label class="pwle-choice-card <?php echo 'multiple' === $type ? 'is-selected' : ''; ?>">
                                    <input type="radio" name="pwl_type" value="multiple" <?php checked($type, 'multiple'); ?>>
                                    <span class="dashicons dashicons-forms" aria-hidden="true"></span>
                                    <span><strong>Múltipla escolha</strong><small>Permite selecionar mais de uma alternativa.</small></span>
                                </label>
                            </fieldset>

                            <fieldset class="pwle-fieldset">
                                <legend>Quando os resultados serão exibidos?</legend>
                                <label class="pwle-radio-row"><input type="radio" name="pwl_results_visibility" value="after_vote" <?php checked($visibility, 'after_vote'); ?>><span><strong>Depois do voto</strong><small>Evita que o resultado influencie a resposta.</small></span></label>
                                <label class="pwle-radio-row"><input type="radio" name="pwl_results_visibility" value="always" <?php checked($visibility, 'always'); ?>><span><strong>Sempre visíveis</strong><small>Todos podem acompanhar o resultado.</small></span></label>
                                <label class="pwle-radio-row"><input type="radio" name="pwl_results_visibility" value="admin" <?php checked($visibility, 'admin'); ?>><span><strong>Somente administradores</strong><small>O resultado fica restrito à gestão.</small></span></label>
                            </fieldset>

                            <fieldset class="pwle-fieldset pwle-persistence-fieldset">
                                <legend>Persistência da enquete</legend>
                                <label class="pwl-switch pwle-persistence-switch">
                                    <input type="checkbox" name="pwl_persistent_until_vote" value="1" <?php checked($persistent_until_vote); ?>>
                                    <span class="pwl-switch__track" aria-hidden="true"></span>
                                    <span><strong>Manter visível até o colaborador responder</strong><small>Enquanto não houver voto registrado para o usuário autenticado, a enquete continuará aparecendo sempre que a página for carregada. Depois do voto, deixa de ser exibida.</small></span>
                                </label>
                            </fieldset>
                        </div>
                    </section>

                    <section class="pwl-card pwle-card">
                        <div class="pwl-card__header pwle-card__header">
                            <span class="pwle-card__icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                            <div>
                                <h3 class="pwl-card__title">Disponibilidade</h3>
                                <p class="pwl-card__description">Controle a abertura e o encerramento da votação.</p>
                            </div>
                        </div>
                        <div class="pwl-card__body">
                            <label class="pwl-switch pwle-status-switch">
                                <input type="checkbox" id="pwlPollStatusToggle" <?php checked($status, 'active'); ?>>
                                <span class="pwl-switch__track" aria-hidden="true"></span>
                                <span><strong>Enquete ativa</strong><small>Desative para encerrar imediatamente.</small></span>
                            </label>
                            <input type="hidden" name="pwl_status" id="pwlPollStatusValue" value="<?php echo esc_attr($status); ?>">

                            <label class="pwl-field pwle-field--last">
                                <span class="pwl-field__label">Encerramento automático <small>opcional</small></span>
                                <input type="datetime-local" class="pwl-input" name="pwl_closes_at" value="<?php echo esc_attr($closes_at); ?>">
                                <span class="pwl-field__helper">A enquete será encerrada automaticamente nesta data e horário.</span>
                            </label>
                        </div>
                    </section>

                    <section class="pwl-card pwl-card--subtle pwle-card pwle-publication-card">
                        <div class="pwl-card__body">
                            <span class="pwle-section-kicker">PUBLICAÇÃO</span>
                            <?php if ($edit_id) : ?>
                                <h3>Shortcode da enquete</h3>
                                <p>Copie o código e insira na página ou widget desejado.</p>
                                <div class="pwle-shortcode-copy">
                                    <input type="text" class="pwl-input" id="pwlPollShortcode" readonly value="[pwl_enquete id=&quot;<?php echo esc_attr($edit_id); ?>&quot;]">
                                    <button type="button" class="pwl-button pwl-button--secondary" data-copy-target="pwlPollShortcode">Copiar</button>
                                </div>
                            <?php else : ?>
                                <h3>Publicação automática</h3>
                                <p>Ao salvar, a enquete será publicada e o shortcode será gerado automaticamente.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="pwle-savebar">
                <div>
                    <strong><?php echo $edit_id ? 'Atualizar enquete' : 'Nova enquete'; ?></strong>
                    <span>Revise a pergunta, as alternativas e as regras antes de publicar.</span>
                </div>
                <div class="pwle-savebar__actions">
                    <?php if ($edit_id) : ?>
                        <a class="pwl-button pwl-button--secondary" href="<?php echo esc_url($this->workspace_url('editor')); ?>">Cancelar edição</a>
                    <?php endif; ?>
                    <button type="submit" class="pwl-button pwl-button--primary">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php echo $edit_id ? 'Salvar alterações' : 'Salvar e publicar enquete'; ?>
                    </button>
                </div>
            </div>
        </form>
        <?php
    }

    private function render_workspace_polls($polls) {
        ?>
        <section class="pwl-section-header pwle-section-header">
            <div>
                <span class="pwle-section-kicker">ENQUETES CADASTRADAS</span>
                <h2 class="pwl-section-header__title">Gerenciar enquetes</h2>
                <p class="pwl-section-header__description">Consulte status, participação e shortcode. Edite uma enquete sem sair desta interface.</p>
            </div>
            <a class="pwl-button pwl-button--primary" href="<?php echo esc_url($this->workspace_url('editor')); ?>">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                Criar enquete
            </a>
        </section>

        <section class="pwl-card pwle-card">
            <div class="pwl-card__header pwle-card__header">
                <span class="pwle-card__icon dashicons dashicons-list-view" aria-hidden="true"></span>
                <div>
                    <h3 class="pwl-card__title">Enquetes</h3>
                    <p class="pwl-card__description"><?php echo esc_html(number_format_i18n(count($polls))); ?> cadastro(s) encontrado(s).</p>
                </div>
            </div>

            <?php if (empty($polls)) : ?>
                <div class="pwle-empty-state">
                    <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                    <strong>Nenhuma enquete cadastrada</strong>
                    <p>Crie a primeira enquete para começar a coletar a opinião dos colaboradores.</p>
                    <a class="pwl-button pwl-button--primary" href="<?php echo esc_url($this->workspace_url('editor')); ?>">Criar primeira enquete</a>
                </div>
            <?php else : ?>
                <div class="pwl-table-wrap">
                    <table class="pwl-table pwle-table">
                        <thead>
                            <tr>
                                <th>Enquete</th>
                                <th>Status</th>
                                <th>Resposta</th>
                                <th>Participações</th>
                                <th>Encerramento</th>
                                <th>Shortcode</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($polls as $poll) :
                            $is_active = 'publish' === $poll->post_status && $this->is_open($poll->ID);
                            $type = get_post_meta($poll->ID, '_pwl_type', true) ?: 'single';
                            $closes_at = get_post_meta($poll->ID, '_pwl_closes_at', true);
                            $delete_url = wp_nonce_url(
                                admin_url('admin-post.php?action=pwl_excluir_enquete_admin&poll_id=' . absint($poll->ID)),
                                'pwl_excluir_enquete_' . absint($poll->ID)
                            );
                            ?>
                            <tr>
                                <td class="pwle-table__title"><strong><?php echo esc_html(get_the_title($poll)); ?></strong><small>Criada em <?php echo esc_html(mysql2date('d/m/Y H:i', $poll->post_date)); ?> • ID <?php echo esc_html($poll->ID); ?></small></td>
                                <td><span class="pwl-badge <?php echo $is_active ? 'pwl-badge--success' : 'pwl-badge--danger'; ?>"><?php echo $is_active ? 'Ativa' : 'Encerrada'; ?></span></td>
                                <td><?php echo 'multiple' === $type ? 'Múltipla escolha' : 'Escolha única'; ?></td>
                                <td><strong><?php echo esc_html(number_format_i18n($this->get_vote_count($poll->ID))); ?></strong></td>
                                <td><?php echo $closes_at ? esc_html(mysql2date('d/m/Y H:i', str_replace('T', ' ', $closes_at))) : '<span class="pwle-muted">Sem prazo</span>'; ?></td>
                                <td>
                                    <div class="pwle-inline-code">
                                        <code id="pwlShortcode<?php echo esc_attr($poll->ID); ?>">[pwl_enquete id="<?php echo esc_html($poll->ID); ?>"]</code>
                                        <button type="button" class="pwle-copy-icon" data-copy-target="pwlShortcode<?php echo esc_attr($poll->ID); ?>" aria-label="Copiar shortcode"><span class="dashicons dashicons-admin-page"></span></button>
                                    </div>
                                </td>
                                <td>
                                    <div class="pwle-row-actions">
                                        <a href="<?php echo esc_url($this->workspace_url('editor', array('edit' => $poll->ID))); ?>">Editar</a>
                                        <a href="<?php echo esc_url($this->workspace_url('reports', array('poll_id' => $poll->ID))); ?>">Relatório</a>
                                        <a class="is-danger" href="<?php echo esc_url($delete_url); ?>" data-confirm="Mover esta enquete para a lixeira?">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_workspace_reports($polls, $active_count, $total_votes) {
        $selected_id = isset($_GET['poll_id']) ? absint($_GET['poll_id']) : 0;
        $selected_poll = $selected_id ? get_post($selected_id) : null;
        if ($selected_poll && self::POST_TYPE !== $selected_poll->post_type) {
            $selected_poll = null;
        }
        ?>
        <section class="pwl-section-header pwle-section-header">
            <div>
                <span class="pwle-section-kicker">RELATÓRIOS</span>
                <h2 class="pwl-section-header__title">Resultados das enquetes</h2>
                <p class="pwl-section-header__description">Acompanhe a participação, consulte as respostas identificadas e exporte os dados em CSV.</p>
            </div>
        </section>

        <div class="pwle-metrics">
            <article class="pwl-card"><span>Total de enquetes</span><strong><?php echo esc_html(number_format_i18n(count($polls))); ?></strong><small>Cadastros disponíveis</small></article>
            <article class="pwl-card"><span>Enquetes ativas</span><strong><?php echo esc_html(number_format_i18n($active_count)); ?></strong><small>Recebendo respostas</small></article>
            <article class="pwl-card"><span>Participações</span><strong><?php echo esc_html(number_format_i18n($total_votes)); ?></strong><small>Votos registrados</small></article>
        </div>

        <?php if ($selected_poll) : ?>
            <section class="pwl-card pwle-card pwle-report-detail">
                <div class="pwl-card__header pwle-card__header pwle-card__header--between">
                    <div class="pwle-card__identity">
                        <span class="pwle-card__icon dashicons dashicons-chart-bar" aria-hidden="true"></span>
                        <div>
                            <h3 class="pwl-card__title"><?php echo esc_html(get_the_title($selected_poll)); ?></h3>
                            <p class="pwl-card__description">Relatório detalhado da enquete selecionada.</p>
                        </div>
                    </div>
                    <a class="pwl-button pwl-button--secondary pwl-button--compact" href="<?php echo esc_url($this->workspace_url('reports')); ?>">Fechar relatório</a>
                </div>
                <div class="pwl-card__body">
                    <?php echo $this->get_results_html($selected_poll->ID, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->get_admin_report_html($selected_poll->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="pwl-card pwle-card">
            <div class="pwl-card__header pwle-card__header">
                <span class="pwle-card__icon dashicons dashicons-chart-pie" aria-hidden="true"></span>
                <div>
                    <h3 class="pwl-card__title">Relatórios por enquete</h3>
                    <p class="pwl-card__description">Selecione uma enquete para visualizar as respostas ou exportar o relatório completo.</p>
                </div>
            </div>
            <?php if (empty($polls)) : ?>
                <div class="pwle-empty-state"><strong>Nenhum relatório disponível</strong><p>Os relatórios serão exibidos depois que uma enquete for criada.</p></div>
            <?php else : ?>
                <div class="pwl-table-wrap">
                    <table class="pwl-table pwle-table">
                        <thead><tr><th>Enquete</th><th>Status</th><th>Participações</th><th>Publicação</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($polls as $poll) :
                            $is_active = 'publish' === $poll->post_status && $this->is_open($poll->ID);
                            $export_url = wp_nonce_url(
                                admin_url('admin-post.php?action=pwl_exportar_relatorio_enquete&poll_id=' . absint($poll->ID)),
                                'pwl_exportar_relatorio_' . absint($poll->ID)
                            );
                            ?>
                            <tr>
                                <td class="pwle-table__title"><strong><?php echo esc_html(get_the_title($poll)); ?></strong><small>ID <?php echo esc_html($poll->ID); ?></small></td>
                                <td><span class="pwl-badge <?php echo $is_active ? 'pwl-badge--success' : 'pwl-badge--danger'; ?>"><?php echo $is_active ? 'Ativa' : 'Encerrada'; ?></span></td>
                                <td><strong><?php echo esc_html(number_format_i18n($this->get_vote_count($poll->ID))); ?></strong></td>
                                <td><?php echo esc_html(mysql2date('d/m/Y H:i', $poll->post_date)); ?></td>
                                <td><div class="pwle-row-actions"><a href="<?php echo esc_url($this->workspace_url('reports', array('poll_id' => $poll->ID))); ?>">Abrir relatório</a><a href="<?php echo esc_url($export_url); ?>">Exportar CSV</a></div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_workspace_about() {
        ?>
        <section class="pwl-section-header pwle-section-header">
            <div>
                <span class="pwle-section-kicker">SOBRE O MÓDULO</span>
                <h2 class="pwl-section-header__title">PWL Enquetes</h2>
                <p class="pwl-section-header__description">Módulo corporativo para pesquisas internas autenticadas, publicação por shortcode e relatórios de participação.</p>
            </div>
        </section>

        <div class="pwle-about-grid">
            <article class="pwl-card"><span>Versão</span><strong><?php echo esc_html(self::VERSION); ?></strong></article>
            <article class="pwl-card"><span>Área</span><strong>Recursos Corporativos</strong></article>
            <article class="pwl-card"><span>Controle</span><strong>Um voto por usuário</strong></article>
            <article class="pwl-card"><span>Shortcode</span><strong><code>[pwl_enquete]</code></strong></article>
        </div>

        <section class="pwl-card pwle-card">
            <div class="pwl-card__header pwle-card__header">
                <span class="pwle-card__icon dashicons dashicons-info-outline" aria-hidden="true"></span>
                <div><h3 class="pwl-card__title">Compatibilidade e dependências</h3><p class="pwl-card__description">Informações técnicas desta versão.</p></div>
            </div>
            <div class="pwl-card__body pwle-about-notes">
                <h3>Versão 2.16.0 — Em Testes</h3>
                <p>Editor React experimental com componentes shadcn/ui e Design System 1.3.1. Use Experimentar editor React; o formulário PHP permanece disponível. Build estático incluído, sem Node.js no servidor.</p>
                <h3>Versão 2.4.0</h3>
                <p>Administração integrada ao menu Plataforma. A capacidade configurável de gerenciamento, os relatórios e os contratos existentes foram mantidos.</p>
                <h3>Administração própria</h3>
                <p>A criação, edição, publicação e consulta de enquetes agora ocorrem em uma interface exclusiva. O armazenamento interno permanece compatível com os dados já existentes.</p>
                <h3>Dados preservados</h3>
                <p>Enquetes, alternativas, votos, usuários, datas e metadados existentes permanecem disponíveis. Nenhuma base paralela foi criada.</p>
                <h3>Shortcodes</h3>
                <p>Utilize <code>[pwl_enquete id="ID"]</code> ou <code>[pwl_poll id="ID"]</code>. Os aliases anteriores continuam aceitos internamente para evitar quebra de páginas existentes.</p>
                <h3>Design System</h3>
                <p>A Admin UI consome o PWL Design System v1.3.1 pelo contrato público <code>pwl_ds_enqueue()</code>, com fallback visual local e suporte ao modo escuro global.</p>
                <h3>Versão 2.2.1</h3>
                <p>Corrige a exibição antecipada de resultados na opção Depois do voto e torna o armazenamento de emojis compatível com bancos que não preservam caracteres de quatro bytes, mantendo leitura das alternativas antigas.</p>
                <h3>Versão 2.2.0</h3>
                <p>Adiciona persistência configurável por enquete, imagem individual por alternativa, biblioteca visual de emojis e integração exclusiva com a PWL Biblioteca de Mídia pelo contrato público de picker, preservando IDs, votos, shortcodes e dados existentes.</p>
                <h3>Versão 2.1.0</h3>
                <p>Adiciona descrição formatada, imagem opcional da enquete, emoji por alternativa, detalhamento de colaboradores por alternativa nos relatórios e reforça a persistência do formulário enquanto o usuário autenticado ainda não possui voto registrado.</p>
                <h3>Versão 2.0.0</h3>
                <p>Nova experiência administrativa inspirada no Cardápio Semanal, sem exposição da tela de posts do WordPress, com fluxo unificado de cadastro, gestão e relatórios.</p>
            </div>
        </section>
        <?php
    }

    public function add_meta_boxes() {
        add_meta_box('pwl_enquete_config', 'Configuracao da enquete', array($this, 'render_config_box'), self::POST_TYPE, 'normal', 'high');
        add_meta_box('pwl_enquete_shortcode', 'Publicar na pagina', array($this, 'render_shortcode_box'), self::POST_TYPE, 'side', 'high');
        add_meta_box('pwl_enquete_results', 'Resultados', array($this, 'render_results_box'), self::POST_TYPE, 'normal', 'default');
    }

    public function render_config_box($post) {
        wp_nonce_field('pwl_salvar_enquete', 'pwl_enquete_nonce');
        $options = get_post_meta($post->ID, '_pwl_options', true);
        if (!is_array($options) || empty($options)) {
            $options = array(
                array('key' => wp_generate_uuid4(), 'label' => ''),
                array('key' => wp_generate_uuid4(), 'label' => ''),
            );
        }
        $type = get_post_meta($post->ID, '_pwl_type', true) ?: 'single';
        $visibility = get_post_meta($post->ID, '_pwl_results_visibility', true) ?: 'after_vote';
        $status = get_post_meta($post->ID, '_pwl_status', true) ?: 'active';
        $closes_at = get_post_meta($post->ID, '_pwl_closes_at', true);
        ?>
        <div class="pwl-admin-field">
            <label><strong>Alternativas</strong></label>
            <p class="description">Adicione pelo menos duas alternativas. Arraste para reorganizar.</p>
            <div id="pwl-options-list">
                <?php foreach ($options as $option) : ?>
                    <div class="pwl-option-row">
                        <span class="dashicons dashicons-menu pwl-drag" aria-hidden="true"></span>
                        <input type="hidden" name="pwl_option_keys[]" value="<?php echo esc_attr($option['key']); ?>">
                        <input type="text" name="pwl_option_labels[]" value="<?php echo esc_attr($option['label']); ?>" placeholder="Digite uma alternativa" class="widefat">
                        <button type="button" class="button-link-delete pwl-remove-option">Remover</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="pwl-add-option"><span class="dashicons dashicons-plus-alt2"></span> Adicionar alternativa</button>
        </div>

        <div class="pwl-admin-grid">
            <div class="pwl-admin-field">
                <label for="pwl_type"><strong>Tipo de resposta</strong></label>
                <select id="pwl_type" name="pwl_type" class="widefat">
                    <option value="single" <?php selected($type, 'single'); ?>>Uma alternativa</option>
                    <option value="multiple" <?php selected($type, 'multiple'); ?>>Multiplas alternativas</option>
                </select>
            </div>
            <div class="pwl-admin-field">
                <label for="pwl_results_visibility"><strong>Exibicao dos resultados</strong></label>
                <select id="pwl_results_visibility" name="pwl_results_visibility" class="widefat">
                    <option value="after_vote" <?php selected($visibility, 'after_vote'); ?>>Depois de votar</option>
                    <option value="always" <?php selected($visibility, 'always'); ?>>Sempre visiveis</option>
                    <option value="admin" <?php selected($visibility, 'admin'); ?>>Somente administradores</option>
                </select>
            </div>
            <div class="pwl-admin-field">
                <label for="pwl_status"><strong>Status</strong></label>
                <select id="pwl_status" name="pwl_status" class="widefat">
                    <option value="active" <?php selected($status, 'active'); ?>>Ativa</option>
                    <option value="closed" <?php selected($status, 'closed'); ?>>Encerrada</option>
                </select>
            </div>
            <div class="pwl-admin-field">
                <label for="pwl_closes_at"><strong>Encerramento automatico</strong></label>
                <input type="datetime-local" id="pwl_closes_at" name="pwl_closes_at" value="<?php echo esc_attr($closes_at); ?>" class="widefat">
                <p class="description">Opcional. Utiliza o fuso horario do WordPress.</p>
            </div>
        </div>
        <?php
    }

    public function render_shortcode_box($post) {
        if ('auto-draft' === $post->post_status) {
            echo '<p>Salve a enquete para gerar o shortcode.</p>';
            return;
        }
        $shortcode = '[pwl_enquete id="' . absint($post->ID) . '"]';
        echo '<p>Copie e cole em um widget de Shortcode do Elementor ou no editor da pagina.</p>';
        echo '<input type="text" class="widefat pwl-shortcode-field" readonly value="' . esc_attr($shortcode) . '" onclick="this.select();">';
    }

    public function render_results_box($post) {
        if ('auto-draft' === $post->post_status) {
            echo '<p>Os resultados aparecerao depois que a enquete for publicada.</p>';
            return;
        }
        echo $this->get_results_html($post->ID, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get_admin_report_html($post->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function save_poll($post_id) {
        if (!isset($_POST['pwl_enquete_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwl_enquete_nonce'])), 'pwl_salvar_enquete')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $keys = isset($_POST['pwl_option_keys']) ? (array) wp_unslash($_POST['pwl_option_keys']) : array();
        $labels = isset($_POST['pwl_option_labels']) ? (array) wp_unslash($_POST['pwl_option_labels']) : array();
        $emojis = isset($_POST['pwl_option_emojis']) ? (array) wp_unslash($_POST['pwl_option_emojis']) : array();
        $option_image_ids = isset($_POST['pwl_option_image_ids']) ? (array) wp_unslash($_POST['pwl_option_image_ids']) : array();
        $options = array();
        foreach ($labels as $index => $label) {
            $label = sanitize_text_field($label);
            if ('' === $label) {
                continue;
            }
            $key = isset($keys[$index]) ? sanitize_text_field($keys[$index]) : '';
            if ('' === $key) {
                $key = wp_generate_uuid4();
            }
            $emoji = isset($emojis[$index]) ? $this->encode_emoji_for_storage($emojis[$index]) : '';
            $option_image_id = isset($option_image_ids[$index]) ? absint($option_image_ids[$index]) : 0;
            $options[] = array('key' => $key, 'label' => $label, 'emoji' => $emoji, 'image_id' => $option_image_id);
        }
        update_post_meta($post_id, '_pwl_options', $options);

        $type = isset($_POST['pwl_type']) && 'multiple' === $_POST['pwl_type'] ? 'multiple' : 'single';
        $allowed_visibility = array('after_vote', 'always', 'admin');
        $visibility = isset($_POST['pwl_results_visibility']) ? sanitize_key($_POST['pwl_results_visibility']) : 'after_vote';
        $status = isset($_POST['pwl_status']) && 'closed' === $_POST['pwl_status'] ? 'closed' : 'active';
        $closes_at = isset($_POST['pwl_closes_at']) ? sanitize_text_field($_POST['pwl_closes_at']) : '';

        update_post_meta($post_id, '_pwl_type', $type);
        update_post_meta($post_id, '_pwl_results_visibility', in_array($visibility, $allowed_visibility, true) ? $visibility : 'after_vote');
        update_post_meta($post_id, '_pwl_status', $status);
        update_post_meta($post_id, '_pwl_closes_at', $closes_at);
        update_post_meta($post_id, '_pwl_persistent_until_vote', !empty($_POST['pwl_persistent_until_vote']) ? '1' : '0');
    }

    public function render_pwl_media_picker_modal() {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('pwl-enquetes' !== $page || !function_exists('pwl_media_render_picker_modal')) {
            return;
        }
        pwl_media_render_picker_modal();
    }

    public function admin_assets($hook) {
        $screen = get_current_screen();
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $is_poll_screen = $screen && self::POST_TYPE === $screen->post_type;
        $is_workspace = 'pwl-enquetes' === $page;
        $is_custom_page = in_array($page, array('pwl-enquetes', 'pwl-enquetes-relatorios', 'pwl-enquetes-sobre'), true);

        if (!$is_poll_screen && !$is_custom_page) {
            return;
        }

        if (function_exists('pwl_ds_enqueue')) {
            pwl_ds_enqueue(array('all'), 'admin');
        } else {
            wp_enqueue_style(
                'pwl-enquetes-font',
                'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
                array(),
                null
            );
        }

        wp_enqueue_style('pwl-enquetes-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), self::VERSION);
        wp_add_inline_style(
            'pwl-enquetes-admin',
            $is_workspace
                ? $this->admin_report_styles() . $this->admin_workspace_styles()
                : $this->admin_report_styles() . $this->admin_interface_styles()
        );

        if ($is_workspace) {
            // The workspace styles are already included in the stylesheet payload above.
        }

        if ($is_poll_screen || $is_workspace) {
            wp_enqueue_media();
            wp_enqueue_editor();
            wp_enqueue_script('jquery-ui-sortable');

            if ($is_workspace) {
                wp_add_inline_script('jquery-ui-sortable', $this->admin_workspace_script());
            } else {
                wp_enqueue_script('pwl-enquetes-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery', 'jquery-ui-sortable'), self::VERSION, true);
                wp_add_inline_script('pwl-enquetes-admin', $this->admin_interface_script());
            }
        }
    }

    public function render_edit_header($post) {
        if (!$post || self::POST_TYPE !== $post->post_type) {
            return;
        }

        $this->render_admin_header('config', $post);
    }

    public function render_list_header() {
        $screen = get_current_screen();

        if (!$screen || 'edit-' . self::POST_TYPE !== $screen->id) {
            return;
        }

        $this->render_admin_header('list', null, true);
    }

    private function render_admin_header($active = 'list', $post = null, $standalone = false) {
        $all_url = $this->workspace_url('polls');
        $new_url = $this->workspace_url('editor');
        $reports_url = $this->workspace_url('reports');
        $about_url = $this->workspace_url('about');
        $is_existing = $post && !empty($post->ID) && 'auto-draft' !== $post->post_status;
        $config_title = $is_existing ? 'Editar enquete' : 'Nova enquete';
        $title = 'Enquetes';
        $description = 'Crie, publique e acompanhe pesquisas internas com controle por usuário e relatórios completos.';

        if ('config' === $active) {
            $title = $config_title;
            $description = 'Configure pergunta, conteúdo, alternativas, publicação, prazo e visibilidade dos resultados.';
        } elseif ('reports' === $active) {
            $title = 'Relatórios de enquetes';
            $description = 'Acompanhe participação, status e exporte os registros detalhados de cada enquete.';
        } elseif ('about' === $active) {
            $title = 'Sobre o módulo';
            $description = 'Versão, contratos públicos, compatibilidade e dependências do módulo de enquetes.';
        }
        ?>
        <div class="pwle-admin-shell <?php echo $standalone ? 'pwle-admin-shell--standalone' : ''; ?>" <?php echo $standalone ? 'data-pwle-move-to-wrap="1"' : ''; ?>>
            <div class="pwle-header">
                <div>
                    <span class="pwle-eyebrow">RECURSOS CORPORATIVOS · ADMINISTRAÇÃO</span>
                    <h1><?php echo esc_html($title); ?></h1>
                    <p><?php echo esc_html($description); ?></p>
                </div>
                <div class="pwle-version"><span>Versão</span><strong><?php echo esc_html(self::VERSION); ?></strong></div>
            </div>

            <nav class="pwle-tabs" aria-label="Navegação do módulo de enquetes">
                <a class="pwle-tab <?php echo 'list' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($all_url); ?>">
                    <span class="dashicons dashicons-list-view"></span>
                    <span class="pwle-tab-copy"><strong>Enquetes</strong><small>Cadastros, status e shortcodes</small></span>
                </a>
                <a class="pwle-tab <?php echo 'config' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($is_existing ? $this->workspace_url('editor', array('edit' => absint($post->ID))) : $new_url); ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <span class="pwle-tab-copy"><strong><?php echo esc_html($config_title); ?></strong><small>Conteúdo, alternativas e período</small></span>
                </a>
                <a class="pwle-tab <?php echo 'reports' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($reports_url); ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <span class="pwle-tab-copy"><strong>Relatórios</strong><small>Participações e exportações</small></span>
                </a>
                <a class="pwle-tab <?php echo 'about' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($about_url); ?>">
                    <span class="dashicons dashicons-info-outline"></span>
                    <span class="pwle-tab-copy"><strong>Sobre</strong><small>Versão, contratos e dependências</small></span>
                </a>
            </nav>
        </div>
        <?php
    }

    public function render_reports_page() {
        if (!$this->user_can_manage_enquetes()) {
            wp_die('Acesso não autorizado.');
        }

        $polls = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $total_votes = 0;
        $active_count = 0;
        foreach ($polls as $poll) {
            $total_votes += $this->get_vote_count($poll->ID);
            if ($this->is_open($poll->ID) && 'publish' === $poll->post_status) {
                $active_count++;
            }
        }
        ?>
        <div class="wrap pwle-admin-page">
            <?php $this->render_admin_header('reports'); ?>

            <div class="pwle-toolbar">
                <div><span class="pwle-section-kicker">RELATÓRIOS</span><h2>Visão consolidada</h2><p>Consulte o desempenho das enquetes e acesse os relatórios individuais.</p></div>
                <a class="button button-primary" href="<?php echo esc_url($this->workspace_url('editor')); ?>">Criar enquete</a>
            </div>

            <div class="pwle-metrics">
                <article><span>Total de enquetes</span><strong><?php echo esc_html(number_format_i18n(count($polls))); ?></strong></article>
                <article><span>Enquetes ativas</span><strong><?php echo esc_html(number_format_i18n($active_count)); ?></strong></article>
                <article><span>Participações registradas</span><strong><?php echo esc_html(number_format_i18n($total_votes)); ?></strong></article>
            </div>

            <section class="pwle-panel">
                <div class="pwle-panel-head"><span class="dashicons dashicons-chart-bar"></span><div><h3>Relatórios por enquete</h3><p>Abra a edição para visualizar respostas individuais ou exporte diretamente em CSV.</p></div></div>
                <?php if (empty($polls)) : ?>
                    <div class="pwle-empty"><strong>Nenhuma enquete cadastrada</strong><p>Crie a primeira enquete para iniciar a coleta de respostas.</p></div>
                <?php else : ?>
                    <div class="pwle-tablewrap">
                        <table class="pwle-table">
                            <thead><tr><th>Enquete</th><th>Publicação</th><th>Status</th><th>Participações</th><th>Shortcode</th><th>Ações</th></tr></thead>
                            <tbody>
                            <?php foreach ($polls as $poll) :
                                $votes = $this->get_vote_count($poll->ID);
                                $is_active = $this->is_open($poll->ID) && 'publish' === $poll->post_status;
                                $export_url = wp_nonce_url(
                                    admin_url('admin-post.php?action=pwl_exportar_relatorio_enquete&poll_id=' . absint($poll->ID)),
                                    'pwl_exportar_relatorio_' . $poll->ID
                                );
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html(get_the_title($poll)); ?></strong><small>ID <?php echo esc_html($poll->ID); ?></small></td>
                                    <td><?php echo esc_html(mysql2date('d/m/Y H:i', $poll->post_date)); ?></td>
                                    <td><span class="pwle-badge <?php echo $is_active ? 'is-active' : 'is-closed'; ?>"><?php echo $is_active ? 'Ativa' : 'Encerrada'; ?></span></td>
                                    <td><strong><?php echo esc_html(number_format_i18n($votes)); ?></strong></td>
                                    <td><code>[pwl_enquete id=&quot;<?php echo esc_html($poll->ID); ?>&quot;]</code></td>
                                    <td><div class="pwle-row-actions"><a href="<?php echo esc_url($this->workspace_url('reports', array('poll_id' => absint($poll->ID)))); ?>">Abrir relatório</a><a href="<?php echo esc_url($export_url); ?>">Exportar CSV</a></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public function render_about_page() {
        if (!$this->user_can_manage_enquetes()) {
            wp_die('Acesso não autorizado.');
        }
        ?>
        <div class="wrap pwle-admin-page">
            <?php $this->render_admin_header('about'); ?>

            <div class="pwle-toolbar"><div><span class="pwle-section-kicker">SOBRE O MÓDULO</span><h2>Enquetes</h2><p>Módulo corporativo para pesquisas internas autenticadas e relatórios de participação.</p></div></div>

            <div class="pwle-about-grid">
                <article class="pwle-about-card"><span class="dashicons dashicons-admin-plugins"></span><div><h3>Versão</h3><strong><?php echo esc_html(self::VERSION); ?></strong></div></article>
                <article class="pwle-about-card"><span class="dashicons dashicons-category"></span><div><h3>Área</h3><strong>Recursos Corporativos</strong></div></article>
                <article class="pwle-about-card"><span class="dashicons dashicons-shield"></span><div><h3>Controle</h3><strong>Um voto por usuário</strong></div></article>
                <article class="pwle-about-card"><span class="dashicons dashicons-shortcode"></span><div><h3>Shortcode</h3><strong><code>[pwl_enquete]</code></strong></div></article>
            </div>

            <section class="pwle-panel pwle-about-notes">
                <div class="pwle-panel-head"><span class="dashicons dashicons-info-outline"></span><div><h3>Compatibilidade e contratos</h3><p>Informações técnicas preservadas nesta evolução visual.</p></div></div>
                <div class="pwle-panel-body">
                    <h3>Dados preservados</h3><p>Enquetes, votos e metadados existentes são migrados automaticamente para os identificadores PWL.</p>
                    <h3>Shortcodes</h3><p>Utilize <code>[pwl_enquete id="ID"]</code> ou <code>[pwl_poll id="ID"]</code>. Publicações existentes continuam funcionando durante a transição.</p>
                    <h3>Dependências</h3><p>WordPress 6.2 ou superior, PHP 7.4 ou superior e menu administrativo <strong>Plataforma</strong> fornecido pelo PWL Core.</p>
                    <h3>Versão 1.4.0</h3><p>Conversão integral para PWL, com novos identificadores técnicos, ativos, ações, shortcodes e migração automática dos dados existentes.</p>
                    <h3>Versão 1.3.0</h3><p>Padronização da interface administrativa com o PWL Notificações, integração ao menu Plataforma, modo claro e escuro, navegação unificada e painel consolidado de relatórios.</p>
                </div>
            </section>
        </div>
        <?php
    }

    public function admin_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['pwl_shortcode'] = 'Shortcode';
                $new['pwl_votes'] = 'Participacoes';
                $new['pwl_status'] = 'Status';
            }
        }
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        if ('pwl_shortcode' === $column) {
            echo '<code>[pwl_enquete id=&quot;' . absint($post_id) . '&quot;]</code>';
        } elseif ('pwl_votes' === $column) {
            echo absint($this->get_vote_count($post_id));
        } elseif ('pwl_status' === $column) {
            echo $this->is_open($post_id) ? '<span class="pwl-status pwl-status-active">Ativa</span>' : '<span class="pwl-status pwl-status-closed">Encerrada</span>';
        }
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'pwl_enquete');
        $poll_id = absint($atts['id']);
        $post = get_post($poll_id);
        if (!$post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status) {
            return current_user_can('edit_posts') ? '<div class="pwl-poll-notice">Enquete nao encontrada ou ainda nao publicada.</div>' : '';
        }

        if (function_exists('pwl_ds_enqueue')) {
            pwl_ds_enqueue(array('all'), 'portal');
        }

        wp_enqueue_style('pwl-enquetes-front', plugin_dir_url(__FILE__) . 'assets/frontend.css', array(), self::VERSION);
        wp_enqueue_script('pwl-enquetes-front', plugin_dir_url(__FILE__) . 'assets/frontend.js', array(), self::VERSION, true);
        wp_enqueue_script('pwl-enquetes-react', plugin_dir_url(__FILE__) . 'assets/react-frontend.js', array('wp-element', 'pwl-enquetes-front'), self::VERSION, true);
        wp_localize_script('pwl-enquetes-front', 'PWLEnquetes', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'sending' => 'Registrando seu voto...',
            'error' => 'Nao foi possivel registrar o voto. Tente novamente.',
        ));

        $user_id = get_current_user_id();
        $has_voted = $user_id ? $this->has_voted($poll_id, $user_id) : false;
        $persistent_until_vote = '1' === (string) get_post_meta($poll_id, '_pwl_persistent_until_vote', true);
        if ($persistent_until_vote && $user_id && $has_voted) {
            return '';
        }
        $is_open = $this->is_open($poll_id);
        $visibility = get_post_meta($poll_id, '_pwl_results_visibility', true) ?: 'after_vote';
        $show_results = 'always' === $visibility || ('after_vote' === $visibility && $has_voted) || ('admin' === $visibility && $this->user_can_view_admin_results());

        static $frontend_styles_output = false;
        $frontend_style_tag = '';
        if (!$frontend_styles_output) {
            $frontend_style_tag = '<style id="pwl-enquetes-front-inline">' . $this->frontend_styles() . $this->questionnaire_styles() . '</style>';
            $frontend_styles_output = true;
        }

        $is_home_context = is_front_page() || is_home();

        ob_start();
        echo $frontend_style_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <section class="pwl-poll pwl-poll--intranet pwl-ds<?php echo $is_home_context ? ' pwl-poll--home' : ''; ?>" data-poll-id="<?php echo esc_attr($poll_id); ?>">
            <div class="pwl-poll__header">
                <div class="pwl-poll__section-title">
                    <span class="pwl-poll__section-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img" focusable="false">
                            <path d="M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z"></path>
                        </svg>
                    </span>
                    <span>Enquete</span>
                </div>
                <span class="pwl-poll__brand">ENQUETE INTERNA</span>
            </div>
            <div class="pwl-poll__body">
                <div class="pwl-poll__question">
                    <div class="pwl-poll__question-meta">
                        <span class="pwl-poll__eyebrow">PARTICIPE</span>
                        <span class="pwl-poll__status <?php echo $is_open ? 'pwl-poll__status--open' : 'pwl-poll__status--closed'; ?>">
                            <span aria-hidden="true"></span>
                            <?php echo $is_open ? 'Votacao aberta' : 'Encerrada'; ?>
                        </span>
                    </div>
                    <h2 class="pwl-poll__title"><?php echo esc_html(get_the_title($poll_id)); ?></h2>
                <?php if (trim($post->post_content)) : ?>
                    <div class="pwl-poll__description"><?php echo wp_kses_post(wpautop($post->post_content)); ?></div>
                <?php endif; ?>
                <?php $poll_image_id = absint(get_post_meta($poll_id, '_pwl_image_id', true)); ?>
                <?php if ($poll_image_id) : ?>
                    <div class="pwl-poll__media">
                        <?php echo wp_get_attachment_image($poll_image_id, 'large', false, array('class' => 'pwl-poll__image', 'loading' => 'lazy')); ?>
                    </div>
                <?php endif; ?>
                </div>
                <?php if (!is_user_logged_in()) : ?>
                    <div class="pwl-poll__message">Entre na intranet para participar desta enquete.</div>
                <?php elseif ($has_voted) : ?>
                    <div class="pwl-poll__message pwl-poll__message--success">Seu voto foi registrado com sucesso.</div>
                <?php elseif (!$is_open) : ?>
                    <div class="pwl-poll__message">Esta enquete esta encerrada.</div>
                <?php else : ?>
                    <?php echo $this->get_form_html($poll_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>

                <div class="pwl-poll__feedback" role="status" aria-live="polite"></div>
                <div class="pwl-poll__results" <?php echo $show_results ? '' : 'hidden'; ?>>
                    <?php if ($show_results) { echo $this->get_results_html($poll_id, false); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function questionnaire_styles() {
        return <<<'CSS'
/* Questionnaire presentation: limited to the public poll surface. */
.pwl-poll.pwl-poll--intranet {
    border-color: var(--pwl-color-border);
    border-radius: var(--pwl-radius-lg);
    background: var(--pwl-color-surface);
    box-shadow: var(--pwl-shadow-sm);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__header {
    border-color: var(--pwl-color-border);
    background: var(--pwl-color-surface);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__question {
    max-width: 100%;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__title {
    color: var(--pwl-color-text);
    font-weight: 700;
    letter-spacing: normal;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__description {
    color: var(--pwl-color-text-secondary);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__options {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--pwl-space-3);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option {
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    padding: var(--pwl-space-4);
    border: 1px solid var(--pwl-color-border);
    border-radius: var(--pwl-radius-md);
    background: var(--pwl-color-surface);
    color: var(--pwl-color-text);
    box-shadow: none;
    cursor: pointer;
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option:hover {
    border-color: var(--pwl-color-primary);
    background: var(--pwl-color-primary-soft);
    transform: translateY(-1px);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option:has(input:checked) {
    border-color: var(--pwl-color-primary);
    background: var(--pwl-color-primary-soft);
    box-shadow: inset 0 0 0 1px var(--pwl-color-primary);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option:has(input:focus-visible) {
    outline: 2px solid var(--pwl-color-primary);
    outline-offset: 2px;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option-label {
    color: var(--pwl-color-text);
    font-weight: 500;
    line-height: 1.5;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__submit {
    border-radius: var(--pwl-radius-md);
    background: var(--pwl-color-primary);
    color: var(--pwl-color-on-primary, white);
    font-weight: 700;
    transition: background-color .18s ease, transform .18s ease;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__submit:hover {
    background: var(--pwl-color-primary-hover);
    transform: translateY(-1px);
}
@media (prefers-reduced-motion: reduce) {
    .pwl-poll.pwl-poll--intranet .pwl-poll__option,
    .pwl-poll.pwl-poll--intranet .pwl-poll__submit { transition: none; transform: none; }
}
CSS;
    }

    private function frontend_styles() {
        return <<<'CSS'
.pwl-poll.pwl-poll--intranet,
.pwl-poll.pwl-poll--intranet * {
    box-sizing: border-box;
}

.pwl-poll.pwl-poll--intranet {
    --pwl-orange: var(--pwl-color-primary, #ff5a00);
    --pwl-orange-dark: var(--pwl-color-primary-hover, #e84f00);
    --pwl-orange-soft: var(--pwl-color-primary-soft, #fff1e8);
    --pwl-navy: var(--pwl-color-text, #1f2937);
    --pwl-text: var(--pwl-color-text, #1f2937);
    --pwl-muted: var(--pwl-color-text-secondary, #667085);
    --pwl-line: var(--pwl-color-border, #e4e7ec);
    --pwl-surface: var(--pwl-color-surface-subtle, #f8fafc);
    width: 100%;
    max-width: none;
    margin: 0;
    overflow: hidden;
    border: 1px solid var(--pwl-line);
    border-radius: 18px;
    background: var(--pwl-color-surface, #ffffff);
    box-shadow: 0 4px 14px rgba(20, 32, 51, 0.055);
    color: var(--pwl-text);
    font-family: var(--pwl-font-family, "Plus Jakarta Sans", Arial, sans-serif);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin: 0 clamp(18px, 2vw, 24px);
    padding: clamp(17px, 1.5vw, 21px) 0 13px;
    border-bottom: 1px solid var(--pwl-line);
    background: var(--pwl-color-surface, #ffffff);
    color: var(--pwl-navy);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    color: var(--pwl-navy);
    font-size: clamp(22.99px, 1.485vw, 27.83px);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.025em;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__section-icon {
    display: inline-flex;
    flex: 0 0 22px;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    color: var(--pwl-orange);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__section-icon svg {
    display: block;
    width: 100%;
    height: 100%;
    fill: currentColor;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__brand {
    flex: 0 0 auto;
    color: var(--pwl-muted);
    font-size: 10.89px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0.03em;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__body {
    padding: clamp(17px, 1.8vw, 24px);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__question {
    margin-bottom: 17px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__question-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__eyebrow {
    display: inline-block;
    margin: 0;
    color: var(--pwl-orange);
    font-size: 13.31px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0.11em;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 13.31px;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__status > span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__status--open {
    background: var(--pwl-color-success-soft, #ecfdf3);
    color: var(--pwl-color-success, #067647);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__status--closed {
    background: var(--pwl-color-surface-subtle, #f8fafc);
    color: var(--pwl-color-text-muted, #98a2b3);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__title {
    margin: 0;
    color: var(--pwl-navy);
    font-size: clamp(21.78px, 1.375vw, 26.62px);
    font-weight: 800;
    line-height: 1.3;
    letter-spacing: -0.025em;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__description {
    margin-top: 7px;
    color: var(--pwl-muted);
    font-size: 14.52px;
    font-weight: 500;
    line-height: 1.55;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__description p {
    margin: 0;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__media {
    margin-top: 14px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__image {
    display: block;
    width: 100%;
    max-height: 420px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid var(--pwl-line);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__emoji,
.pwl-poll.pwl-poll--intranet .pwl-result__emoji {
    flex: 0 0 auto;
    font-size: 21.78px;
    line-height: 1;
}


.pwl-poll.pwl-poll--intranet fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__options {
    display: grid;
    gap: 9px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 46px;
    margin: 0;
    padding: 11px 13px;
    border: 1px solid var(--pwl-line);
    border-radius: 11px;
    background: var(--pwl-surface);
    color: var(--pwl-text);
    cursor: pointer;
    font-size: 14.52px;
    font-weight: 650;
    line-height: 1.35;
    transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option:hover {
    border-color: var(--pwl-orange);
    background: var(--pwl-orange-soft);
    box-shadow: var(--pwl-shadow-focus, 0 0 0 3px rgba(255, 90, 0, .10));
    transform: none;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option:has(input:checked) {
    border-color: var(--pwl-orange);
    background: var(--pwl-orange-soft);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__control {
    position: relative;
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    border: 2px solid #b7bfcc;
    border-radius: 50%;
    background: var(--pwl-color-surface, #ffffff);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option input[type="checkbox"] + .pwl-poll__control {
    border-radius: 5px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option input:checked + .pwl-poll__control {
    border-color: var(--pwl-orange);
    background: var(--pwl-orange);
    box-shadow: inset 0 0 0 4px var(--pwl-color-surface, #ffffff);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__option input:focus-visible + .pwl-poll__control {
    outline: 3px solid rgba(255, 103, 0, 0.18);
    outline-offset: 2px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__submit {
    width: 100%;
    min-height: 44px;
    margin: 13px 0 0;
    padding: 11px 18px;
    border: 0;
    border-radius: 10px;
    background: var(--pwl-orange);
    color: #ffffff;
    box-shadow: 0 7px 16px rgba(255, 103, 0, 0.18);
    cursor: pointer;
    font-family: inherit;
    font-size: 14.52px;
    font-weight: 800;
    line-height: 1.2;
    transition: background-color 0.18s ease, box-shadow 0.18s ease;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__submit:hover {
    background: var(--pwl-orange-dark);
    box-shadow: 0 9px 19px rgba(255, 103, 0, 0.23);
    transform: none;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__submit:disabled {
    opacity: 0.65;
    cursor: wait;
    box-shadow: none;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__message,
.pwl-poll.pwl-poll--intranet .pwl-poll__feedback {
    margin: 0;
    padding: 11px 13px;
    border: 1px solid var(--pwl-line);
    border-radius: 10px;
    background: var(--pwl-surface);
    color: var(--pwl-muted);
    font-size: 13.31px;
    font-weight: 600;
    line-height: 1.45;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__feedback:empty {
    display: none;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__feedback {
    margin-top: 12px;
}

.pwl-poll.pwl-poll--intranet .pwl-poll__message--success,
.pwl-poll.pwl-poll--intranet .pwl-poll__feedback--success {
    border-color: var(--pwl-color-success, #067647);
    background: var(--pwl-color-success-soft, #ecfdf3);
    color: var(--pwl-color-success, #067647);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__feedback--error {
    border-color: var(--pwl-color-danger, #b42318);
    background: var(--pwl-color-danger-soft, #fef3f2);
    color: var(--pwl-color-danger, #b42318);
}

.pwl-poll.pwl-poll--intranet .pwl-poll__results:not([hidden]) {
    margin-top: 18px;
    padding-top: 17px;
    border-top: 1px solid var(--pwl-line);
}

.pwl-poll.pwl-poll--intranet .pwl-results__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.pwl-poll.pwl-poll--intranet .pwl-results__heading strong {
    color: var(--pwl-navy);
    font-size: 16.94px;
    font-weight: 800;
}

.pwl-poll.pwl-poll--intranet .pwl-results__heading span {
    color: var(--pwl-muted);
    font-size: 13.31px;
    font-weight: 600;
}

.pwl-poll.pwl-poll--intranet .pwl-results__list {
    display: grid;
    gap: 14px;
}

.pwl-poll.pwl-poll--intranet .pwl-result__labels {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 6px;
}

.pwl-poll.pwl-poll--intranet .pwl-result__labels span {
    color: var(--pwl-text);
    font-size: 13.31px;
    font-weight: 650;
    line-height: 1.35;
}

.pwl-poll.pwl-poll--intranet .pwl-result__labels strong {
    flex: 0 0 auto;
    color: var(--pwl-orange-dark);
    font-size: 13.31px;
    font-weight: 800;
}

.pwl-poll.pwl-poll--intranet .pwl-result__track {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--pwl-color-surface-subtle, #f8fafc);
}

.pwl-poll.pwl-poll--intranet .pwl-result__track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--pwl-orange);
    transition: width 0.45s ease;
}

.pwl-poll.pwl-poll--intranet .pwl-result small {
    display: block;
    margin-top: 5px;
    color: var(--pwl-muted);
    font-size: 10.89px;
    font-weight: 600;
}

.pwl-poll.pwl-poll--intranet .screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}


.pwl-poll.pwl-poll--intranet .pwl-poll__option-image {
    display: inline-flex;
    flex: 0 0 56px;
    width: 56px;
    height: 56px;
    overflow: hidden;
    border: 1px solid var(--pwl-line);
    border-radius: 12px;
    background: var(--pwl-surface);
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pwl-poll.pwl-poll--intranet .pwl-poll__option-label { min-width: 0; }


/* =========================================================
POPUP | AJUSTE RESPONSIVO AO CONTAINER
Quando a enquete for renderizada dentro do PWL Popups, sua largura
passa a ser determinada exclusivamente pela largura util do popup.
========================================================= */
.pwl-popup-content .pwl-poll.pwl-poll--intranet,
.pwl-preview-body .pwl-poll.pwl-poll--intranet,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup {
    display: block !important;
    width: 100% !important;
    inline-size: 100% !important;
    max-width: 100% !important;
    max-inline-size: 100% !important;
    min-width: 0 !important;
    min-inline-size: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}

.pwl-popup-content .pwl-poll.pwl-poll--intranet > *,
.pwl-preview-body .pwl-poll.pwl-poll--intranet > *,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup > * {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}

.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__header,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__body,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__question,
.pwl-popup-content .pwl-poll.pwl-poll--intranet fieldset,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__options,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__option,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__results,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-results__list,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__header,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__body,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__question,
.pwl-preview-body .pwl-poll.pwl-poll--intranet fieldset,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__options,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__option,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__results,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-results__list,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__header,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__body,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__question,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup fieldset,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__options,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__option,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__results,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-results__list {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    max-height: none !important;
    box-sizing: border-box !important;
}

.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__media,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__media,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__media {
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden !important;
}

.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__image,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__image,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__image {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    max-height: min(38vh, 360px) !important;
    object-fit: contain !important;
}


.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__submit,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__submit,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__submit {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__option-label,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__option-label,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__option-label,
.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__description,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__description,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__description {
    min-width: 0 !important;
    max-width: 100% !important;
    overflow-wrap: anywhere !important;
}

/* 2.3.3 | layout verdadeiramente adaptativo ao container do popup */
.pwl-popup-content .pwl-poll.pwl-poll--intranet,
.pwl-preview-body .pwl-poll.pwl-poll--intranet,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup {
    container-type: inline-size;
    container-name: pwl-poll-popup;
}

.pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
.pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
.pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__body > .pwl-poll__form {
    width:100% !important;
    max-width:100% !important;
    min-width:0 !important;
}

@container pwl-poll-popup (min-width: 900px) {
    .pwl-poll.pwl-poll--intranet .pwl-poll__body {
        display:grid !important;
        grid-template-columns:minmax(0, 1.12fr) minmax(320px, .88fr) !important;
        column-gap:clamp(20px, 2.5vw, 34px) !important;
        row-gap:18px !important;
        align-items:start !important;
    }
    .pwl-poll.pwl-poll--intranet .pwl-poll__question {
        grid-column:1 !important;
        grid-row:1 / span 2 !important;
        margin-bottom:0 !important;
    }
    .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
    .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__message {
        grid-column:2 !important;
        grid-row:1 !important;
        align-self:start !important;
    }
    .pwl-poll.pwl-poll--intranet .pwl-poll__feedback {
        grid-column:2 !important;
        grid-row:2 !important;
        margin-top:0 !important;
    }
    .pwl-poll.pwl-poll--intranet .pwl-poll__results {
        grid-column:1 / -1 !important;
    }
    .pwl-poll.pwl-poll--intranet .pwl-poll__image {
        max-height:clamp(260px, 34vh, 390px) !important;
    }
}

@media (max-width: 782px) {
    .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__header,
    .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__header,
    .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__header {
        flex-wrap: wrap !important;
    }
    .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__question-meta,
    .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__question-meta,
    .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__question-meta {
        flex-wrap: wrap !important;
        align-items: flex-start !important;
    }
}

/* =========================================================
HOME | ESCALA COMPACTA
Mantem a pagina publicada com a escala ampliada e normaliza
somente a enquete renderizada na Home do Portal.
========================================================= */
.pwl-poll.pwl-poll--intranet.pwl-poll--home {
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__header {
    gap: 12px;
    margin: 0 clamp(16px, 1.2vw, 22px);
    padding: clamp(16px, 1.2vw, 22px) 0 12px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__section-title {
    gap: 8px;
    font-size: clamp(15px, 1vw, 18px);
    line-height: 1.3;
    letter-spacing: 0;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__section-icon {
    flex-basis: 22px;
    width: 22px;
    height: 22px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__brand {
    font-size: 9px;
    letter-spacing: .3px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__body {
    padding: clamp(16px, 1.2vw, 22px);
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__question {
    margin-bottom: 14px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__question-meta {
    gap: 10px;
    margin-bottom: 7px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__eyebrow,
.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__status {
    font-size: 9.9px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__status {
    gap: 5px;
    padding: 4px 7px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__title {
    font-size: 14.3px;
    line-height: 1.35;
    letter-spacing: 0;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__description {
    margin-top: 6px;
    font-size: 11px;
    line-height: 1.45;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__media {
    margin-top: 11px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__image {
    border-radius: 9px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__options {
    gap: 8px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__option {
    gap: 9px;
    min-height: 42px;
    padding: 9px 10px;
    border-radius: 9px;
    font-size: 11px !important;
    line-height: 1.4;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__option .pwl-poll__option-label {
    font-size: 11px !important;
    font-weight: 600;
    line-height: 1.4;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__control {
    flex-basis: 16px;
    width: 16px;
    height: 16px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__emoji,
.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-result__emoji {
    font-size: 18px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__option-image {
    flex-basis: 48px;
    width: 48px;
    height: 48px;
    border-radius: 9px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__submit {
    min-height: 42px;
    margin-top: 11px;
    padding: 9px 12px;
    border-radius: 8px;
    font-size: 10px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__message,
.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__feedback {
    padding: 10px;
    border-radius: 9px;
    font-size: 10px;
    line-height: 1.45;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-poll__results:not([hidden]) {
    margin-top: 14px;
    padding-top: 13px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-results__heading {
    margin-bottom: 10px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-results__heading strong {
    font-size: 12.1px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-results__heading span {
    font-size: 10px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-result__labels > span {
    font-size: 14.3px !important;
    font-weight: 600;
    line-height: 1.4;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-result__labels > strong {
    font-size: 13.2px !important;
    font-weight: 700;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-results__list {
    gap: 10px;
}

.pwl-poll.pwl-poll--intranet.pwl-poll--home .pwl-result small {
    font-size: 10px;
}

@media (max-width: 600px) {
    .pwl-poll.pwl-poll--intranet {
        border-radius: 16px;
    }

    .pwl-poll.pwl-poll--intranet .pwl-poll__header {
        margin-right: 18px;
        margin-left: 18px;
    }

    .pwl-poll.pwl-poll--intranet .pwl-poll__body {
        padding: 18px;
    }
}
CSS;
    }

    private function get_form_html($poll_id) {
        $options = get_post_meta($poll_id, '_pwl_options', true);
        if (!is_array($options) || count($options) < 2) {
            return '<div class="pwl-poll__message">Esta enquete ainda nao possui alternativas suficientes.</div>';
        }
        $type = get_post_meta($poll_id, '_pwl_type', true) ?: 'single';
        $input_type = 'multiple' === $type ? 'checkbox' : 'radio';
        $input_name = 'multiple' === $type ? 'choices[]' : 'choices';
        ob_start();
        ?>
        <form class="pwl-poll__form">
            <input type="hidden" name="action" value="pwl_registrar_voto">
            <input type="hidden" name="poll_id" value="<?php echo esc_attr($poll_id); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('pwl_vote_' . $poll_id)); ?>">
            <fieldset>
                <legend class="screen-reader-text">Escolha sua resposta</legend>
                <div class="pwl-poll__options">
                    <?php foreach ($options as $option) : ?>
                        <label class="pwl-poll__option">
                            <input type="<?php echo esc_attr($input_type); ?>" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($option['key']); ?>">
                            <span class="pwl-poll__control" aria-hidden="true"></span>
                            <?php if (!empty($option['image_id'])) : ?>
                                <span class="pwl-poll__option-image"><?php echo wp_get_attachment_image(absint($option['image_id']), 'thumbnail', false, array('loading' => 'lazy')); ?></span>
                            <?php endif; ?>
                            <?php $option_emoji = $this->decode_option_emoji($option); if ('' !== $option_emoji) : ?><span class="pwl-poll__emoji" aria-hidden="true"><?php echo esc_html($option_emoji); ?></span><?php endif; ?>
                            <span class="pwl-poll__option-label"><?php echo esc_html($option['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="pwl-poll__submit">Registrar voto</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function register_vote() {
        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$poll_id || !wp_verify_nonce($nonce, 'pwl_vote_' . $poll_id)) {
            wp_send_json_error(array('message' => 'Solicitacao invalida. Atualize a pagina e tente novamente.'), 403);
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Voce precisa entrar na intranet para votar.'), 401);
        }
        $post = get_post($poll_id);
        if (!$post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status) {
            wp_send_json_error(array('message' => 'Enquete indisponivel.'), 404);
        }
        if (!$this->is_open($poll_id)) {
            wp_send_json_error(array('message' => 'Esta enquete esta encerrada.'), 400);
        }
        $user_id = get_current_user_id();
        if ($this->has_voted($poll_id, $user_id)) {
            wp_send_json_error(array('message' => 'Seu voto ja foi registrado nesta enquete.'), 409);
        }

        $submitted = isset($_POST['choices']) ? (array) wp_unslash($_POST['choices']) : array();
        $submitted = array_values(array_unique(array_filter(array_map('sanitize_text_field', $submitted))));
        $options = get_post_meta($poll_id, '_pwl_options', true);
        $valid_keys = is_array($options) ? wp_list_pluck($options, 'key') : array();
        $choices = array_values(array_intersect($submitted, $valid_keys));
        $type = get_post_meta($poll_id, '_pwl_type', true) ?: 'single';
        if (empty($choices)) {
            wp_send_json_error(array('message' => 'Selecione pelo menos uma alternativa.'), 400);
        }
        if ('single' === $type && 1 !== count($choices)) {
            wp_send_json_error(array('message' => 'Selecione apenas uma alternativa.'), 400);
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . self::TABLE_SUFFIX,
            array(
                'poll_id' => $poll_id,
                'user_id' => $user_id,
                'choices' => wp_json_encode($choices),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );
        if (false === $inserted) {
            wp_send_json_error(array('message' => 'Seu voto ja foi registrado ou ocorreu um erro. Atualize a pagina.'), 409);
        }

        $visibility = get_post_meta($poll_id, '_pwl_results_visibility', true) ?: 'after_vote';
        $show_results = 'always' === $visibility || 'after_vote' === $visibility || ('admin' === $visibility && $this->user_can_view_admin_results());
        wp_send_json_success(array(
            'message' => 'Seu voto foi registrado com sucesso.',
            'showResults' => $show_results,
            'resultsHtml' => $show_results ? $this->get_results_html($poll_id, false) : '',
        ));
    }

    private function get_results_data($poll_id) {
        global $wpdb;
        $options = get_post_meta($poll_id, '_pwl_options', true);
        $options = is_array($options) ? $options : array();
        $counts = array();
        foreach ($options as $option) {
            $counts[$option['key']] = 0;
        }
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT choices FROM {$wpdb->prefix}" . self::TABLE_SUFFIX . ' WHERE poll_id = %d',
            $poll_id
        ));
        foreach ($rows as $row) {
            $choices = json_decode($row, true);
            if (!is_array($choices)) {
                continue;
            }
            foreach (array_unique($choices) as $choice) {
                if (isset($counts[$choice])) {
                    $counts[$choice]++;
                }
            }
        }
        return array('options' => $options, 'counts' => $counts, 'voters' => count($rows));
    }

    private function get_vote_rows($poll_id, $limit = 0) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT id, poll_id, user_id, choices, created_at
             FROM {$wpdb->prefix}" . self::TABLE_SUFFIX . '
             WHERE poll_id = %d
             ORDER BY created_at DESC, id DESC',
            $poll_id
        );

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        return $wpdb->get_results($sql);
    }

    private function get_option_labels($poll_id) {
        $options = get_post_meta($poll_id, '_pwl_options', true);
        $labels = array();

        if (!is_array($options)) {
            return $labels;
        }

        foreach ($options as $option) {
            if (!empty($option['key'])) {
                $labels[$option['key']] = isset($option['label']) ? $option['label'] : $option['key'];
            }
        }

        return $labels;
    }

    private function get_choice_labels($encoded_choices, $option_labels) {
        $choices = json_decode($encoded_choices, true);

        if (!is_array($choices)) {
            return 'Resposta indisponivel';
        }

        $labels = array();
        foreach ($choices as $choice) {
            $labels[] = isset($option_labels[$choice]) ? $option_labels[$choice] : 'Alternativa removida';
        }

        return empty($labels) ? 'Sem resposta' : implode(', ', $labels);
    }

    private function format_vote_date($date) {
        try {
            $vote_date = new DateTimeImmutable($date, wp_timezone());
            return $vote_date->format('d/m/Y H:i:s');
        } catch (Exception $e) {
            return $date;
        }
    }

    private function get_option_voters($poll_id) {
        $rows = $this->get_vote_rows($poll_id);
        $options = get_post_meta($poll_id, '_pwl_options', true);
        $map = array();

        if (is_array($options)) {
            foreach ($options as $option) {
                if (!empty($option['key'])) {
                    $map[$option['key']] = array();
                }
            }
        }

        foreach ($rows as $row) {
            $choices = json_decode($row->choices, true);
            if (!is_array($choices)) {
                continue;
            }
            $user = get_userdata($row->user_id);
            $name = $user ? $user->display_name : 'Usuário removido';
            foreach (array_unique($choices) as $choice) {
                if (isset($map[$choice])) {
                    $map[$choice][] = array(
                        'user_id' => absint($row->user_id),
                        'name' => $name,
                        'login' => $user ? $user->user_login : '',
                    );
                }
            }
        }

        return $map;
    }

    private function get_vote_deletion_logs($poll_id, $limit = 200) {
        global $wpdb;
        $audit_table = $wpdb->prefix . self::AUDIT_TABLE_SUFFIX;

        $sql = $wpdb->prepare(
            "SELECT id, action, vote_id, poll_id, voter_user_id, voter_display_name, voter_login,
                    voter_email, choices, choice_labels, vote_created_at, reason, performed_by,
                    performed_by_name, ip_address, user_agent, performed_at
             FROM {$audit_table}
             WHERE poll_id = %d
             ORDER BY performed_at DESC, id DESC",
            $poll_id
        );

        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        return $wpdb->get_results($sql);
    }

    private function get_admin_report_html($poll_id) {
        if (!$this->user_can_manage_enquetes()) {
            return '';
        }

        $total = $this->get_vote_count($poll_id);
        $rows = $this->get_vote_rows($poll_id, 200);
        $deletion_logs = $this->get_vote_deletion_logs($poll_id, 200);
        $can_delete_votes = $this->user_can_delete_votes();
        $option_labels = $this->get_option_labels($poll_id);
        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=pwl_exportar_relatorio_enquete&poll_id=' . absint($poll_id)),
            'pwl_exportar_relatorio_' . $poll_id
        );

        ob_start();
        ?>
        <div class="pwl-admin-report">
            <div class="pwl-admin-report__header">
                <div>
                    <h3>Relatorio de participacoes</h3>
                    <p>Identificação dos usuários, respostas enviadas e ações de auditoria.</p>
                </div>
                <a class="button button-primary pwl-admin-report__export" href="<?php echo esc_url($export_url); ?>">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    Exportar CSV
                </a>
            </div>

            <div class="pwl-admin-report__summary">
                <div class="pwl-admin-report__metric">
                    <span>Total de participacoes</span>
                    <strong><?php echo esc_html(number_format_i18n($total)); ?></strong>
                </div>
                <div class="pwl-admin-report__metric">
                    <span>Tipo de resposta</span>
                    <strong><?php echo 'multiple' === get_post_meta($poll_id, '_pwl_type', true) ? 'Multipla' : 'Unica'; ?></strong>
                </div>
                <div class="pwl-admin-report__metric">
                    <span>Status atual</span>
                    <strong class="<?php echo $this->is_open($poll_id) ? 'is-open' : 'is-closed'; ?>">
                        <?php echo $this->is_open($poll_id) ? 'Ativa' : 'Encerrada'; ?>
                    </strong>
                </div>
            </div>

            <?php
            $poll_options = get_post_meta($poll_id, '_pwl_options', true);
            $option_voters = $this->get_option_voters($poll_id);
            if (is_array($poll_options) && !empty($poll_options)) :
            ?>
                <div class="pwl-admin-report__breakdown">
                    <h4>Colaboradores por alternativa</h4>
                    <p>Em respostas de múltipla escolha, o mesmo colaborador pode aparecer em mais de uma alternativa.</p>
                    <div class="pwl-admin-report__option-grid">
                        <?php foreach ($poll_options as $option) :
                            $key = isset($option['key']) ? $option['key'] : '';
                            $voters = isset($option_voters[$key]) ? $option_voters[$key] : array();
                            ?>
                            <article class="pwl-admin-report__option-card">
                                <div class="pwl-admin-report__option-title">
                                    <strong><?php $option_emoji = $this->decode_option_emoji($option); echo '' !== $option_emoji ? esc_html($option_emoji) . ' ' : ''; ?><?php echo esc_html(isset($option['label']) ? $option['label'] : 'Alternativa'); ?></strong>
                                    <span><?php echo esc_html(number_format_i18n(count($voters))); ?></span>
                                </div>
                                <?php if (empty($voters)) : ?>
                                    <small>Nenhum colaborador selecionou esta alternativa.</small>
                                <?php else : ?>
                                    <ul>
                                        <?php foreach ($voters as $voter) : ?>
                                            <li><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php echo esc_html($voter['name']); ?><?php echo $voter['login'] ? ' <small>(' . esc_html($voter['login']) . ')</small>' : ''; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($rows)) : ?>
                <div class="pwl-admin-report__empty">
                    Nenhuma participacao registrada nesta enquete.
                </div>
            <?php else : ?>
                <div class="pwl-admin-report__table-wrap">
                    <table class="widefat striped pwl-admin-report__table">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Usuario</th>
                                <th>E-mail</th>
                                <th>Perfil</th>
                                <th>Resposta</th>
                                <th>Data e horario</th>
                                <?php if ($can_delete_votes) : ?><th>Ação</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) :
                                $user = get_userdata($row->user_id);
                                $display_name = $user ? $user->display_name : 'Usuario removido';
                                $user_login = $user ? $user->user_login : 'ID ' . absint($row->user_id);
                                $user_email = $user ? $user->user_email : '';
                                $roles = $user && !empty($user->roles) ? implode(', ', array_map(array($this, 'translate_role'), $user->roles)) : 'Indisponivel';
                                $answer = $this->get_choice_labels($row->choices, $option_labels);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($display_name); ?></strong></td>
                                    <td><?php echo esc_html($user_login); ?></td>
                                    <td><?php echo esc_html($user_email ?: 'Nao informado'); ?></td>
                                    <td><?php echo esc_html($roles); ?></td>
                                    <td><span class="pwl-admin-report__answer"><?php echo esc_html($answer); ?></span></td>
                                    <td><?php echo esc_html($this->format_vote_date($row->created_at)); ?></td>
                                    <?php if ($can_delete_votes) : ?>
                                        <td>
                                            <button
                                                type="button"
                                                class="button button-link-delete pwl-admin-report__delete-vote"
                                                data-vote-id="<?php echo esc_attr(absint($row->id)); ?>"
                                                data-poll-id="<?php echo esc_attr(absint($poll_id)); ?>"
                                                data-voter="<?php echo esc_attr($display_name); ?>"
                                                data-answer="<?php echo esc_attr($answer); ?>"
                                                data-nonce="<?php echo esc_attr(wp_create_nonce('pwl_excluir_voto_' . absint($row->id))); ?>"
                                            >Excluir voto</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total > 200) : ?>
                    <p class="description pwl-admin-report__limit">
                        A tela apresenta as 200 participacoes mais recentes. O arquivo CSV inclui o relatorio completo.
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="pwl-admin-audit">
                <div class="pwl-admin-audit__header">
                    <div>
                        <h3>Histórico de exclusões</h3>
                        <p>Registro permanente das remoções realizadas neste relatório.</p>
                    </div>
                    <span class="pwl-badge pwl-badge--neutral"><?php echo esc_html(number_format_i18n(count($deletion_logs))); ?> registro(s)</span>
                </div>

                <?php if (empty($deletion_logs)) : ?>
                    <div class="pwl-admin-report__empty">Nenhum voto foi excluído desta enquete.</div>
                <?php else : ?>
                    <div class="pwl-admin-report__table-wrap">
                        <table class="widefat striped pwl-admin-report__table pwl-admin-audit__table">
                            <thead>
                                <tr>
                                    <th>Voto</th>
                                    <th>Colaborador</th>
                                    <th>Resposta excluída</th>
                                    <th>Justificativa</th>
                                    <th>Excluído por</th>
                                    <th>Data da ação</th>
                                    <th>Origem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletion_logs as $log) : ?>
                                    <tr>
                                        <td>#<?php echo esc_html(absint($log->vote_id)); ?></td>
                                        <td><strong><?php echo esc_html($log->voter_display_name ?: 'Usuário removido'); ?></strong><small><?php echo esc_html($log->voter_login ?: 'ID ' . absint($log->voter_user_id)); ?></small></td>
                                        <td><span class="pwl-admin-report__answer"><?php echo esc_html($log->choice_labels ?: 'Resposta indisponível'); ?></span><small>Votado em <?php echo esc_html($this->format_vote_date($log->vote_created_at)); ?></small></td>
                                        <td class="pwl-admin-audit__reason"><?php echo esc_html($log->reason); ?></td>
                                        <td><strong><?php echo esc_html($log->performed_by_name ?: 'Usuário ID ' . absint($log->performed_by)); ?></strong><small>ID <?php echo esc_html(absint($log->performed_by)); ?></small></td>
                                        <td><?php echo esc_html($this->format_vote_date($log->performed_at)); ?></td>
                                        <td><?php echo esc_html($log->ip_address ?: 'IP não registrado'); ?><small title="<?php echo esc_attr($log->user_agent); ?>"><?php echo esc_html($log->user_agent ?: 'Navegador não registrado'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($can_delete_votes && !empty($rows)) : ?>
                <div class="pwl-vote-delete-modal" id="pwlVoteDeleteModal" hidden>
                    <div class="pwl-vote-delete-modal__backdrop" data-pwl-close-delete-modal></div>
                    <div class="pwl-vote-delete-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pwlVoteDeleteTitle">
                        <button type="button" class="pwl-vote-delete-modal__close" data-pwl-close-delete-modal aria-label="Fechar">&times;</button>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="pwlVoteDeleteForm">
                            <input type="hidden" name="action" value="pwl_excluir_voto_enquete">
                            <input type="hidden" name="vote_id" id="pwlDeleteVoteId" value="">
                            <input type="hidden" name="poll_id" id="pwlDeletePollId" value="">
                            <input type="hidden" name="pwl_delete_vote_nonce" id="pwlDeleteVoteNonce" value="">
                            <input type="hidden" name="confirmation" id="pwlDeleteConfirmation" value="">

                            <div class="pwl-vote-delete-step" data-delete-step="1">
                                <span class="pwl-vote-delete-modal__icon dashicons dashicons-trash" aria-hidden="true"></span>
                                <span class="pwl-vote-delete-modal__eyebrow">PRIMEIRA CONFIRMAÇÃO</span>
                                <h3 id="pwlVoteDeleteTitle">Justificar exclusão do voto</h3>
                                <p>Você está prestes a excluir o voto de <strong id="pwlDeleteVoter"></strong>. A resposta registrada foi: <strong id="pwlDeleteAnswer"></strong>.</p>
                                <label for="pwlDeleteReason">Motivo da exclusão <span aria-hidden="true">*</span></label>
                                <textarea name="reason" id="pwlDeleteReason" rows="4" minlength="15" maxlength="1000" required placeholder="Informe o motivo com no mínimo 15 caracteres."></textarea>
                                <div class="pwl-vote-delete-modal__counter"><span id="pwlDeleteReasonCount">0</span>/15 caracteres mínimos</div>
                                <div class="pwl-vote-delete-modal__actions">
                                    <button type="button" class="button" data-pwl-close-delete-modal>Cancelar</button>
                                    <button type="button" class="button button-primary" id="pwlDeleteContinue" disabled>Continuar</button>
                                </div>
                            </div>

                            <div class="pwl-vote-delete-step" data-delete-step="2" hidden>
                                <span class="pwl-vote-delete-modal__icon pwl-vote-delete-modal__icon--danger dashicons dashicons-warning" aria-hidden="true"></span>
                                <span class="pwl-vote-delete-modal__eyebrow">SEGUNDA CONFIRMAÇÃO</span>
                                <h3>Confirmar exclusão definitiva</h3>
                                <p>O voto será removido do resultado e o colaborador poderá votar novamente. A justificativa e todos os dados da ação permanecerão no histórico.</p>
                                <div class="pwl-vote-delete-modal__summary">
                                    <span>Justificativa registrada</span>
                                    <strong id="pwlDeleteReasonPreview"></strong>
                                </div>
                                <div class="pwl-vote-delete-modal__actions">
                                    <button type="button" class="button" id="pwlDeleteBack">Voltar</button>
                                    <button type="submit" class="button pwl-vote-delete-modal__confirm" id="pwlDeleteSubmit">Excluir voto definitivamente</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                (function(){
                    'use strict';
                    var modal=document.getElementById('pwlVoteDeleteModal');
                    if(!modal){return;}
                    var form=document.getElementById('pwlVoteDeleteForm');
                    var reason=document.getElementById('pwlDeleteReason');
                    var count=document.getElementById('pwlDeleteReasonCount');
                    var continueButton=document.getElementById('pwlDeleteContinue');
                    var backButton=document.getElementById('pwlDeleteBack');
                    var submitButton=document.getElementById('pwlDeleteSubmit');
                    var steps=modal.querySelectorAll('[data-delete-step]');
                    function length(value){return Array.from((value||'').trim()).length;}
                    function showStep(step){steps.forEach(function(item){item.hidden=item.getAttribute('data-delete-step')!==String(step);});}
                    function closeModal(){modal.hidden=true;document.body.classList.remove('pwl-vote-delete-open');form.reset();document.getElementById('pwlDeleteConfirmation').value='';showStep(1);count.textContent='0';continueButton.disabled=true;submitButton.disabled=false;submitButton.textContent='Excluir voto definitivamente';}
                    document.querySelectorAll('.pwl-admin-report__delete-vote').forEach(function(button){button.addEventListener('click',function(){
                        document.getElementById('pwlDeleteVoteId').value=button.getAttribute('data-vote-id')||'';
                        document.getElementById('pwlDeletePollId').value=button.getAttribute('data-poll-id')||'';
                        document.getElementById('pwlDeleteVoteNonce').value=button.getAttribute('data-nonce')||'';
                        document.getElementById('pwlDeleteVoter').textContent=button.getAttribute('data-voter')||'';
                        document.getElementById('pwlDeleteAnswer').textContent=button.getAttribute('data-answer')||'';
                        modal.hidden=false;document.body.classList.add('pwl-vote-delete-open');showStep(1);window.setTimeout(function(){reason.focus();},50);
                    });});
                    modal.querySelectorAll('[data-pwl-close-delete-modal]').forEach(function(button){button.addEventListener('click',closeModal);});
                    reason.addEventListener('input',function(){var total=length(reason.value);count.textContent=String(total);continueButton.disabled=total<15;});
                    continueButton.addEventListener('click',function(){if(length(reason.value)<15){reason.focus();return;}document.getElementById('pwlDeleteReasonPreview').textContent=reason.value.trim();showStep(2);});
                    backButton.addEventListener('click',function(){showStep(1);reason.focus();});
                    form.addEventListener('submit',function(event){if(length(reason.value)<15){event.preventDefault();showStep(1);reason.focus();return;}document.getElementById('pwlDeleteConfirmation').value='EXCLUIR';submitButton.disabled=true;submitButton.textContent='Excluindo...';});
                    document.addEventListener('keydown',function(event){if('Escape'===event.key&&!modal.hidden){closeModal();}});
                })();
                </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function translate_role($role) {
        $roles = wp_roles();
        return isset($roles->role_names[$role]) ? translate_user_role($roles->role_names[$role]) : $role;
    }

    public function export_poll_report() {
        $poll_id = isset($_GET['poll_id']) ? absint($_GET['poll_id']) : 0;

        if (!$poll_id || self::POST_TYPE !== get_post_type($poll_id)) {
            wp_die('Enquete invalida.', 'Relatorio de enquete', array('response' => 400));
        }

        if (!current_user_can('edit_post', $poll_id)) {
            wp_die('Voce nao possui permissao para exportar este relatorio.', 'Acesso negado', array('response' => 403));
        }

        check_admin_referer('pwl_exportar_relatorio_' . $poll_id);

        $rows = $this->get_vote_rows($poll_id);
        $option_labels = $this->get_option_labels($poll_id);
        $filename = 'relatorio-enquete-' . $poll_id . '-' . wp_date('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        if (false === $output) {
            wp_die('Nao foi possivel gerar o arquivo do relatorio.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array(
            'ID do voto',
            'ID do usuario',
            'Nome do colaborador',
            'Usuario',
            'E-mail',
            'Perfil',
            'Resposta',
            'Data e horario',
        ), ';');

        foreach ($rows as $row) {
            $user = get_userdata($row->user_id);
            $roles = $user && !empty($user->roles) ? implode(', ', array_map(array($this, 'translate_role'), $user->roles)) : 'Indisponivel';

            fputcsv($output, array(
                absint($row->id),
                absint($row->user_id),
                $this->csv_safe($user ? $user->display_name : 'Usuario removido'),
                $this->csv_safe($user ? $user->user_login : ''),
                $this->csv_safe($user ? $user->user_email : ''),
                $this->csv_safe($roles),
                $this->csv_safe($this->get_choice_labels($row->choices, $option_labels)),
                $this->format_vote_date($row->created_at),
            ), ';');
        }

        fclose($output);
        exit;
    }

    private function csv_safe($value) {
        $value = (string) $value;
        if (preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function admin_report_styles() {
        return <<<'CSS'
.pwl-admin-report {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid var(--pwl-color-border,#e4e7ec);
}

.pwl-admin-report__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 18px;
}

.pwl-admin-report__header h3 {
    margin: 0 0 4px;
    color: var(--pwl-color-text,#1f2937);
    font-size: 18px;
}

.pwl-admin-report__header p {
    margin: 0;
    color: var(--pwl-color-text-secondary,#667085);
}

.pwl-admin-report__export {
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
}

.pwl-admin-report__export .dashicons {
    width: 17px;
    height: 17px;
    font-size: 17px;
}

.pwl-admin-report__summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.pwl-admin-report__metric {
    padding: 14px 16px;
    border: 1px solid var(--pwl-color-border,#e4e7ec);
    border-radius: 8px;
    background: var(--pwl-color-surface-subtle,#f8fafc);
}

.pwl-admin-report__metric span {
    display: block;
    margin-bottom: 5px;
    color: var(--pwl-color-text-secondary,#667085);
    font-size: 12px;
}

.pwl-admin-report__metric strong {
    color: var(--pwl-color-text,#1f2937);
    font-size: 17px;
}

.pwl-admin-report__metric strong.is-open {
    color: var(--pwl-color-success,#067647);
}

.pwl-admin-report__metric strong.is-closed {
    color: var(--pwl-color-danger,#b42318);
}

.pwl-admin-report__table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--pwl-color-border,#e4e7ec);
    border-radius: 7px;
}

.pwl-admin-report__table {
    min-width: 980px;
    border: 0 !important;
    box-shadow: none !important;
}

.pwl-admin-report__table th {
    padding: 11px 10px;
    color: var(--pwl-color-text,#1f2937);
    font-weight: 700;
    white-space: nowrap;
}

.pwl-admin-report__table td {
    padding: 11px 10px;
    vertical-align: middle;
}

.pwl-admin-report__answer {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--pwl-color-primary-soft,#fff1e8);
    color: var(--pwl-color-primary-hover,#e84f00);
    font-weight: 600;
    white-space: nowrap;
}

.pwl-admin-report__empty {
    padding: 22px;
    border: 1px dashed var(--pwl-color-border,#e4e7ec);
    border-radius: 7px;
    background: var(--pwl-color-surface-subtle,#f8fafc);
    color: var(--pwl-color-text-secondary,#667085);
    text-align: center;
}

.pwl-admin-report__limit {
    margin-top: 10px !important;
}

.pwl-admin-report__delete-vote{padding:0!important;border:0!important;background:transparent!important;color:#b42318!important;font-weight:700;cursor:pointer}
.pwl-admin-audit{margin-top:26px;padding-top:22px;border-top:1px solid var(--pwl-color-border,#e2e6ea)}
.pwl-admin-audit__header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
.pwl-admin-audit__header h3{margin:0 0 4px;color:var(--pwl-color-text,#172033);font-size:17px}
.pwl-admin-audit__header p{margin:0;color:var(--pwl-color-text-secondary,#646970)}
.pwl-admin-audit__table{min-width:1220px}
.pwl-admin-audit__table small{display:block;margin-top:4px;color:var(--pwl-color-text-muted,#7b8494);font-size:10px}
.pwl-admin-audit__reason{min-width:240px;white-space:normal}
body.pwl-vote-delete-open{overflow:hidden}
.pwl-vote-delete-modal[hidden],.pwl-vote-delete-step[hidden]{display:none!important}
.pwl-vote-delete-modal{position:fixed;inset:0;z-index:100100;display:flex;align-items:center;justify-content:center;padding:20px}
.pwl-vote-delete-modal__backdrop{position:absolute;inset:0;background:rgba(16,24,40,.66);backdrop-filter:blur(3px)}
.pwl-vote-delete-modal__dialog{position:relative;z-index:1;width:min(100%,560px);max-height:calc(100vh - 40px);overflow:auto;padding:28px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:16px;background:var(--pwl-color-surface,#fff);box-shadow:0 24px 70px rgba(16,24,40,.28);color:var(--pwl-color-text,#1f2937)}
.pwl-vote-delete-modal__close{position:absolute;top:14px;right:16px;width:34px;height:34px;padding:0;border:0;border-radius:8px;background:var(--pwl-color-surface-subtle,#f8fafc);color:var(--pwl-color-text-secondary,#667085);font-size:24px;line-height:1;cursor:pointer}
.pwl-vote-delete-modal__icon{width:46px;height:46px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;border-radius:12px;background:var(--pwl-color-primary-soft,#fff1e8);color:var(--pwl-color-primary,#ff5a00);font-size:22px}
.pwl-vote-delete-modal__icon--danger{background:#fee4e2;color:#b42318}
.pwl-vote-delete-modal__eyebrow{display:block;margin-bottom:5px;color:#b42318;font-size:10px;font-weight:800;letter-spacing:.12em}
.pwl-vote-delete-modal h3{margin:0 42px 8px 0;color:var(--pwl-color-text,#1f2937);font-size:21px}
.pwl-vote-delete-modal p{margin:0 0 18px;color:var(--pwl-color-text-secondary,#667085);line-height:1.6}
.pwl-vote-delete-modal label{display:block;margin-bottom:7px;color:var(--pwl-color-text,#1f2937);font-size:12px;font-weight:700}
.pwl-vote-delete-modal textarea{width:100%;min-height:112px;padding:11px 12px;border:1px solid var(--pwl-color-border,#d0d5dd);border-radius:9px;background:var(--pwl-color-surface,#fff);color:var(--pwl-color-text,#1f2937);resize:vertical}
.pwl-vote-delete-modal textarea:focus{border-color:var(--pwl-color-primary,#ff5a00);box-shadow:var(--pwl-shadow-focus,0 0 0 3px rgba(255,90,0,.10));outline:0}
.pwl-vote-delete-modal__counter{margin-top:6px;color:var(--pwl-color-text-muted,#98a2b3);font-size:10px;text-align:right}
.pwl-vote-delete-modal__summary{margin:0 0 20px;padding:14px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:10px;background:var(--pwl-color-surface-subtle,#f8fafc)}
.pwl-vote-delete-modal__summary span,.pwl-vote-delete-modal__summary strong{display:block}
.pwl-vote-delete-modal__summary span{margin-bottom:5px;color:var(--pwl-color-text-muted,#98a2b3);font-size:10px;font-weight:700;text-transform:uppercase}
.pwl-vote-delete-modal__summary strong{color:var(--pwl-color-text,#1f2937);line-height:1.55;white-space:pre-wrap}
.pwl-vote-delete-modal__actions{display:flex;justify-content:flex-end;gap:9px;margin-top:20px}
.pwl-vote-delete-modal__confirm{min-height:40px;padding:0 15px;border:1px solid #b42318;border-radius:8px;background:#b42318;color:#fff;font-weight:700;cursor:pointer}
.pwl-vote-delete-modal__confirm:hover,.pwl-vote-delete-modal__confirm:focus{border-color:#912018;background:#912018;color:#fff}

@media (max-width: 782px) {
    .pwl-admin-report__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .pwl-admin-report__summary {
        grid-template-columns: 1fr;
    }

    .pwl-admin-audit__header,.pwl-vote-delete-modal__actions{align-items:stretch;flex-direction:column}
    .pwl-vote-delete-modal__dialog{padding:22px}
}
CSS;
    }

    private function admin_workspace_styles() {
        return <<<'CSS'
.pwle-workspace{max-width:none;margin:0 20px 0 0;padding-top:6px;color:var(--pwl-color-text,#1f2937);font-family:var(--pwl-font-family,"Plus Jakarta Sans",Arial,sans-serif)}
.pwle-workspace *{box-sizing:border-box}.pwle-workspace a{text-decoration:none}.pwle-workspace__header{align-items:center}.pwle-workspace__header .pwl-page-header__actions{align-items:center}.pwle-version-card{min-width:78px;padding:8px 12px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:var(--pwl-radius-md,9px);background:var(--pwl-color-surface,#fff);text-align:center}.pwle-version-card span{display:block;color:var(--pwl-color-text-muted,#98a2b3);font-size:9.9px;font-weight:700;text-transform:uppercase}.pwle-version-card strong{display:block;margin-top:2px;color:var(--pwl-color-text,#1f2937);font-size:13px}
.pwle-notice{margin:0 0 16px}.pwle-workspace__tabs{margin:0 0 20px}.pwle-workspace__tab{display:flex;align-items:center;gap:10px;min-width:210px}.pwle-workspace__tab>.dashicons{width:30px;height:30px;display:flex;align-items:center;justify-content:center;flex:0 0 30px;border-radius:8px;background:var(--pwl-color-surface-subtle,#f8fafc);color:var(--pwl-color-text-muted,#98a2b3);font-size:17px}.pwle-workspace__tab>span:last-child{display:block;min-width:0}.pwle-workspace__tab strong{display:block;color:var(--pwl-color-text,#1f2937);font-size:12.1px;line-height:1.3}.pwle-workspace__tab small{display:block;margin-top:2px;color:var(--pwl-color-text-muted,#98a2b3);font-size:9.9px;line-height:1.3}.pwle-workspace__tab:hover>.dashicons,.pwle-workspace__tab:hover strong{color:var(--pwl-color-primary,#ff5a00)}
.pwle-workspace__content{display:grid;gap:16px}.pwle-section-header{margin:0}.pwle-section-kicker{display:block;margin-bottom:4px;color:var(--pwl-color-primary,#ff5a00);font-size:9.9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.pwle-workspace{background:#f0f0f1;padding:12px 14px 28px;margin-left:-20px;margin-right:0}.pwle-workspace__header,.pwle-workspace__tabs,.pwle-workspace__panel>.pwle-editor,.pwle-workspace__panel>.pwle-card,.pwle-workspace__panel>.pwle-reports,.pwle-workspace__panel>.pwle-about{background:#fff;border:1px solid #dfe5ee;border-radius:14px;box-shadow:0 1px 2px rgba(16,24,40,.04)}.pwle-workspace__header{padding:20px;margin-bottom:18px}.pwle-workspace__header .pwl-page-header__eyebrow{color:#ff5a00}.pwle-workspace__tabs{display:flex;gap:4px;padding:8px;margin-bottom:18px;overflow-x:auto}.pwle-workspace__tab{min-width:0;padding:10px 13px;border-radius:9px;white-space:nowrap}.pwle-workspace__tab>.dashicons{display:none}.pwle-workspace__tab strong{font-size:13px;font-weight:500}.pwle-workspace__tab.is-selected,.pwle-workspace__tab.is-selected:hover{background:#ff6900;color:#fff}.pwle-workspace__tab.is-selected strong,.pwle-workspace__tab.is-selected:hover strong{color:#fff}.pwle-workspace__panel[hidden]{display:none!important}.pwle-workspace__panel>.pwle-editor,.pwle-workspace__panel>.pwle-card,.pwle-workspace__panel>.pwle-reports,.pwle-workspace__panel>.pwle-about{padding:20px}.pwle-workspace__panel .pwle-card{border-radius:12px}.pwle-workspace__panel .pwl-input,.pwle-workspace__panel input[type=text],.pwle-workspace__panel input[type=search],.pwle-workspace__panel select,.pwle-workspace__panel textarea{border-radius:8px}.pwle-workspace__panel .pwl-button--primary{background:#ff6900;border-color:#ff6900}.pwle-workspace__panel .pwl-button--primary:hover{background:#e95f00;border-color:#e95f00}@media(max-width:782px){.pwle-workspace{margin-left:-10px;padding:10px}.pwle-workspace__header{padding:16px}.pwle-workspace__tabs{margin-bottom:12px}.pwle-workspace__tab{padding:9px 11px}}
.pwle-editor{display:grid;gap:18px}.pwle-editor__grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(350px,.8fr);gap:16px;align-items:start}.pwle-editor__main,.pwle-editor__aside{display:grid;gap:16px;align-content:start}.pwle-card{box-shadow:none}.pwle-card__header{align-items:flex-start;justify-content:flex-start}.pwle-card__header--between{justify-content:space-between;align-items:center}.pwle-card__identity{display:flex;align-items:flex-start;gap:12px}.pwle-card__icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;flex:0 0 36px;border-radius:9px;background:var(--pwl-color-primary-soft,#fff1e8);color:var(--pwl-color-primary,#ff5a00);font-size:18px}.pwle-field--last{margin-bottom:0}.pwle-field--last small,.pwl-field__label small{color:var(--pwl-color-text-muted,#98a2b3);font-size:10px;font-weight:500}
.pwle-options-list{display:grid;gap:10px}.pwle-option-row,.pwle-workspace .pwl-option-row{display:grid;grid-template-columns:28px 24px 58px minmax(0,1fr) 42px;gap:8px;align-items:center;padding:9px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:var(--pwl-radius-md,9px);background:var(--pwl-color-surface-subtle,#f8fafc)}.pwle-option-number{width:26px;height:26px;display:flex;align-items:center;justify-content:center;border-radius:7px;background:var(--pwl-color-primary-soft,#fff1e8);color:var(--pwl-color-primary,#ff5a00);font-size:11px;font-weight:800}.pwl-drag{color:var(--pwl-color-text-muted,#98a2b3);cursor:grab}.pwle-option-row .pwl-input,.pwle-workspace .pwl-option-row input[type="text"]{margin:0}.pwle-option-row .pwl-remove-option,.pwle-workspace .pwl-option-row .pwl-remove-option{min-width:40px;padding:0;color:var(--pwl-color-danger,#b42318)}.pwle-workspace .pwl-option-row:not(.pwle-option-row){grid-template-columns:24px minmax(0,1fr) 72px}.pwle-workspace .pwl-option-row:not(.pwle-option-row) .button-link-delete{border:0;background:transparent;color:var(--pwl-color-danger,#b42318);font-size:11px;font-weight:700;cursor:pointer}.pwle-add-option{margin-top:12px}.pwle-option-emoji{text-align:center;font-size:20px!important;padding-left:6px!important;padding-right:6px!important}.pwle-rich-editor .wp-editor-wrap{margin-top:7px}.pwle-rich-editor .wp-editor-container{border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:10px;overflow:hidden}.pwle-rich-editor .mce-toolbar-grp,.pwle-rich-editor .quicktags-toolbar{background:var(--pwl-color-surface-subtle,#f8fafc);border-color:var(--pwl-color-border,#e4e7ec)}.pwle-image-picker{display:grid;gap:10px;margin-top:7px}.pwle-image-preview{min-height:130px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:14px;border:1px dashed var(--pwl-color-border,#d0d5dd);border-radius:10px;background:var(--pwl-color-surface-subtle,#f8fafc);color:var(--pwl-color-text-secondary,#667085);text-align:center}.pwle-image-preview img{display:block;width:100%;max-height:260px;object-fit:cover;border-radius:8px}.pwle-image-preview .dashicons{width:30px;height:30px;font-size:30px}.pwle-image-preview strong{font-size:12px}.pwle-image-preview small{font-size:10px}.pwle-image-actions{display:flex;gap:8px;flex-wrap:wrap}.pwl-admin-report__breakdown{margin:18px 0;padding:16px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:12px;background:var(--pwl-color-surface-subtle,#f8fafc)}.pwl-admin-report__breakdown>h4{margin:0 0 4px;font-size:13.2px}.pwl-admin-report__breakdown>p{margin:0 0 12px;color:var(--pwl-color-text-secondary,#667085);font-size:11px}.pwl-admin-report__option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.pwl-admin-report__option-card{padding:12px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:10px;background:var(--pwl-color-surface,#fff)}.pwl-admin-report__option-title{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.pwl-admin-report__option-title strong{font-size:12.1px}.pwl-admin-report__option-title span{min-width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:var(--pwl-color-primary-soft,#fff1e8);color:var(--pwl-color-primary,#ff5a00);font-size:11px;font-weight:800}.pwl-admin-report__option-card ul{display:grid;gap:5px;margin:10px 0 0;padding:0;list-style:none}.pwl-admin-report__option-card li{display:flex;align-items:center;gap:6px;font-size:11px}.pwl-admin-report__option-card li .dashicons{width:14px;height:14px;font-size:14px;color:var(--pwl-color-text-muted,#98a2b3)}.pwl-admin-report__option-card>small{display:block;margin-top:8px;color:var(--pwl-color-text-muted,#98a2b3);font-size:10px}
.pwle-fieldset{min-width:0;margin:0 0 20px;padding:0;border:0}.pwle-fieldset:last-child{margin-bottom:0}.pwle-fieldset legend{margin-bottom:9px;color:var(--pwl-color-text,#1f2937);font-size:12.1px;font-weight:700;line-height:1.45}.pwle-choice-card{position:relative;display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:12px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:10px;background:var(--pwl-color-surface,#fff);cursor:pointer;transition:.16s}.pwle-choice-card:hover{border-color:var(--pwl-color-text-muted,#98a2b3)}.pwle-choice-card.is-selected,.pwle-choice-card:has(input:checked){border-color:var(--pwl-color-primary,#ff5a00);background:var(--pwl-color-primary-soft,#fff1e8);box-shadow:var(--pwl-shadow-focus,0 0 0 3px rgba(255,90,0,.10))}.pwle-choice-card input{position:absolute;opacity:0}.pwle-choice-card>.dashicons{color:var(--pwl-color-primary,#ff5a00)}.pwle-choice-card strong,.pwle-radio-row strong{display:block;color:var(--pwl-color-text,#1f2937);font-size:12px}.pwle-choice-card small,.pwle-radio-row small{display:block;margin-top:2px;color:var(--pwl-color-text-secondary,#667085);font-size:10.5px;line-height:1.4}.pwle-radio-row{display:flex;align-items:flex-start;gap:9px;padding:9px 0;border-bottom:1px solid var(--pwl-color-border,#e4e7ec);cursor:pointer}.pwle-radio-row:last-child{border-bottom:0}.pwle-radio-row input{margin-top:2px;accent-color:var(--pwl-color-primary,#ff5a00)}
.pwle-status-switch{width:100%;align-items:flex-start;margin-bottom:18px;padding:12px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:10px;background:var(--pwl-color-surface-subtle,#f8fafc)}.pwle-status-switch strong{display:block;color:var(--pwl-color-text,#1f2937);font-size:12px}.pwle-status-switch small{display:block;margin-top:2px;color:var(--pwl-color-text-secondary,#667085);font-size:10.5px;line-height:1.4}.pwle-publication-card h3{margin:8px 0 5px;color:var(--pwl-color-text,#1f2937);font-size:14px}.pwle-publication-card p{margin:0;color:var(--pwl-color-text-secondary,#667085);font-size:11px;line-height:1.5}.pwle-shortcode-copy{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin-top:12px}
.pwle-savebar{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 16px;border:1px solid var(--pwl-color-border,#e4e7ec);border-radius:14px 14px 0 0;background:var(--pwl-color-surface,#fff);box-shadow:0 -8px 24px rgba(15,23,42,.06)}.pwle-savebar strong{display:block;color:var(--pwl-color-text,#1f2937);font-size:13px}.pwle-savebar span{display:block;margin-top:2px;color:var(--pwl-color-text-secondary,#667085);font-size:10.5px}.pwle-savebar__actions{display:flex;gap:8px;align-items:center}
.pwle-empty-state{display:flex;align-items:center;flex-direction:column;padding:46px 20px;text-align:center}.pwle-empty-state>.dashicons{width:48px;height:48px;margin-bottom:10px;color:var(--pwl-color-text-muted,#98a2b3);font-size:48px}.pwle-empty-state strong{color:var(--pwl-color-text,#1f2937);font-size:15px}.pwle-empty-state p{max-width:480px;margin:6px 0 16px;color:var(--pwl-color-text-secondary,#667085);font-size:12px}.pwle-table{min-width:1050px}.pwle-table__title strong,.pwle-table__title small{display:block}.pwle-table__title strong{color:var(--pwl-color-text,#1f2937)}.pwle-table__title small{margin-top:3px;color:var(--pwl-color-text-muted,#98a2b3);font-size:10px}.pwle-muted{color:var(--pwl-color-text-muted,#98a2b3)}.pwle-inline-code{display:flex;align-items:center;gap:5px}.pwle-inline-code code{white-space:nowrap;color:var(--pwl-color-text-secondary,#667085);font-size:10.5px}.pwle-copy-icon{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:7px;background:transparent;color:var(--pwl-color-text-muted,#98a2b3);cursor:pointer}.pwle-copy-icon:hover{background:var(--pwl-color-primary-soft,#fff1e8);color:var(--pwl-color-primary,#ff5a00)}.pwle-copy-icon .dashicons{width:15px;height:15px;font-size:15px}.pwle-row-actions{display:flex;flex-wrap:wrap;gap:9px}.pwle-row-actions a{color:var(--pwl-color-primary,#ff5a00);font-size:11px;font-weight:700}.pwle-row-actions a.is-danger{color:var(--pwl-color-danger,#b42318)}
.pwle-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.pwle-metrics article{padding:17px}.pwle-metrics span{display:block;color:var(--pwl-color-text-secondary,#667085);font-size:10.5px;font-weight:600;text-transform:uppercase}.pwle-metrics strong{display:block;margin:7px 0 2px;color:var(--pwl-color-text,#1f2937);font-size:25px}.pwle-metrics small{color:var(--pwl-color-text-muted,#98a2b3);font-size:10px}.pwle-report-detail .pwl-results{margin-bottom:20px}.pwle-workspace .pwl-results__heading,.pwle-workspace .pwl-result__labels{display:flex;align-items:center;justify-content:space-between;gap:12px}.pwle-workspace .pwl-results__heading{margin-bottom:14px}.pwle-workspace .pwl-results__list{display:grid;gap:14px}.pwle-workspace .pwl-result__labels{margin-bottom:6px}.pwle-workspace .pwl-result__track{height:8px;overflow:hidden;border-radius:999px;background:var(--pwl-color-surface-subtle,#f8fafc)}.pwle-workspace .pwl-result__track span{display:block;height:100%;border-radius:inherit;background:var(--pwl-color-primary,#ff5a00)}.pwle-workspace .pwl-result small{display:block;margin-top:5px}.pwle-workspace .pwl-admin-report{border-top-color:var(--pwl-color-border,#e4e7ec)}.pwle-workspace .pwl-admin-report__header h3,.pwle-workspace .pwl-admin-report__metric strong,.pwle-workspace .pwl-results__heading strong,.pwle-workspace .pwl-result__labels span{color:var(--pwl-color-text,#1f2937)}.pwle-workspace .pwl-admin-report__header p,.pwle-workspace .pwl-admin-report__metric span,.pwle-workspace .pwl-results__heading span,.pwle-workspace .pwl-result small{color:var(--pwl-color-text-secondary,#667085)}.pwle-workspace .pwl-admin-report__metric,.pwle-workspace .pwl-admin-report__empty{border-color:var(--pwl-color-border,#e4e7ec);background:var(--pwl-color-surface-subtle,#f8fafc)}.pwle-workspace .pwl-admin-report__export{min-height:40px;padding:0 14px;border-color:var(--pwl-color-primary,#ff5a00);border-radius:8px;background:var(--pwl-color-primary,#ff5a00);color:#fff}.pwle-workspace .pwl-admin-report__table-wrap{border-color:var(--pwl-color-border,#e4e7ec)}
.pwle-about-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.pwle-about-grid article{padding:17px}.pwle-about-grid span{display:block;margin-bottom:8px;color:var(--pwl-color-text-secondary,#667085);font-size:10px;text-transform:uppercase}.pwle-about-grid strong{color:var(--pwl-color-text,#1f2937);font-size:13px}.pwle-about-grid code{color:inherit}.pwle-about-notes h3{margin:0 0 5px;color:var(--pwl-color-text,#1f2937);font-size:13px}.pwle-about-notes p{margin:0 0 16px;color:var(--pwl-color-text-secondary,#667085);font-size:12px;line-height:1.6}.pwle-about-notes p:last-child{margin-bottom:0}
body.cm-dark .pwle-savebar,body.pwl-dark .pwle-savebar,html[data-theme="dark"] .pwle-savebar,body[data-theme="dark"] .pwle-savebar{background:rgba(34,40,49,.96)}

.pwle-workspace .pwle-option-row {
    grid-template-columns: 28px 22px 118px minmax(220px, 1fr) auto 42px;
    align-items: center;
}
.pwle-workspace .pwle-option-emoji-wrap { display:flex; gap:6px; align-items:center; min-width:0; }
.pwle-workspace .pwle-option-emoji { width:64px; text-align:center; font-size:20px; }
.pwle-workspace .pwle-emoji-open { width:42px; min-width:42px; padding:0; font-size:20px; }
.pwle-workspace .pwle-option-image { display:flex; gap:5px; align-items:center; }
.pwle-workspace .pwle-option-image-preview { display:flex; align-items:center; justify-content:center; width:42px; height:42px; overflow:hidden; border:1px solid var(--pwl-color-border,#e4e7ec); border-radius:10px; background:var(--pwl-color-surface-subtle,#f8fafc); color:var(--pwl-color-text-secondary,#667085); }
.pwle-workspace .pwle-option-image-preview img { width:100%; height:100%; object-fit:cover; }
.pwle-workspace .pwle-option-image .pwl-button { width:34px; min-width:34px; height:34px; padding:0; }
.pwle-workspace .pwle-persistence-fieldset { border-top:1px solid var(--pwl-color-border,#e4e7ec); padding-top:18px; }
.pwle-workspace .pwle-persistence-switch { align-items:flex-start; }
.pwle-emoji-library { position:fixed; z-index:100200; width:min(420px,calc(100vw - 32px)); max-height:min(520px,calc(100vh - 32px)); overflow:hidden; border:1px solid var(--pwl-color-border,#dfe3e8); border-radius:16px; background:var(--pwl-color-surface,#fff); box-shadow:0 24px 70px rgba(15,23,42,.22); color:var(--pwl-color-text,#1f2937); font-family:var(--pwl-font-family,"Plus Jakarta Sans",Arial,sans-serif); }
.pwle-emoji-library[hidden] { display:none!important; }
.pwle-emoji-library__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--pwl-color-border,#e4e7ec); }
.pwle-emoji-library__header strong { font-size:13.2px; font-weight:800; }
.pwle-emoji-library__close { border:0; background:transparent; cursor:pointer; font-size:22px; color:inherit; }
.pwle-emoji-library__tabs { display:flex; gap:4px; overflow:auto; padding:8px 10px; border-bottom:1px solid var(--pwl-color-border,#e4e7ec); }
.pwle-emoji-library__tab { border:0; border-radius:8px; padding:7px 9px; background:transparent; cursor:pointer; font-size:16px; }
.pwle-emoji-library__tab.is-active { background:var(--pwl-color-primary-soft,#fff1e8); }
.pwle-emoji-library__grid { display:grid; grid-template-columns:repeat(8,1fr); gap:4px; overflow:auto; max-height:340px; padding:12px; }
.pwle-emoji-library__emoji { display:flex; align-items:center; justify-content:center; aspect-ratio:1; border:0; border-radius:9px; background:transparent; cursor:pointer; font-size:23px; }
.pwle-emoji-library__emoji:hover { background:var(--pwl-color-surface-subtle,#f4f6f8); }
body.cm-dark .pwle-emoji-library, .cm-dark .pwle-emoji-library { background:#151a21; border-color:#303844; color:#f4f6f8; }
@media (max-width: 900px) { .pwle-workspace .pwle-option-row { grid-template-columns:28px 22px minmax(0,1fr) 42px; } .pwle-workspace .pwle-option-emoji-wrap,.pwle-workspace .pwle-option-image { grid-column:3 / 4; } .pwle-workspace .pwle-option-label { grid-column:3 / 4; } }

@media(max-width:1180px){.pwle-editor__grid{grid-template-columns:minmax(0,1fr) minmax(320px,.75fr)}.pwle-workspace__tab{min-width:185px}.pwle-about-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.pwle-editor__grid{grid-template-columns:1fr}.pwle-metrics{grid-template-columns:1fr}.pwle-editor__aside{grid-template-columns:repeat(2,minmax(0,1fr))}.pwle-publication-card{grid-column:1/-1}}
@media(max-width:782px){.pwl-admin-report__option-grid{grid-template-columns:1fr}.pwle-workspace{margin-right:10px}.pwle-workspace__header,.pwle-savebar{align-items:stretch;flex-direction:column}.pwle-workspace__header .pwl-page-header__actions{width:100%}.pwle-workspace__header .pwl-button{flex:1}.pwle-version-card{flex:0 0 auto}.pwle-workspace__tab{min-width:165px}.pwle-editor__aside,.pwle-about-grid{grid-template-columns:1fr}.pwle-option-row,.pwle-workspace .pwl-option-row{grid-template-columns:24px 54px minmax(0,1fr) 42px}.pwle-option-number{display:none}.pwle-savebar__actions{width:100%;flex-direction:column-reverse}.pwle-savebar__actions .pwl-button{width:100%}.pwle-card__header--between{align-items:stretch;flex-direction:column}.pwle-shortcode-copy{grid-template-columns:1fr}.pwle-shortcode-copy .pwl-button{width:100%}}
CSS;
    }

    private function admin_interface_styles() {
        return <<<'CSS'
body.post-type-pwl_enquete,
.pwle-admin-page,
.pwle-admin-shell {
    --pwle-o: #ff5a00;
    --pwle-os: #fff1e8;
    --pwle-ink: #1f2937;
    --pwle-text: #667085;
    --pwle-mut: #98a2b3;
    --pwle-line: #e4e7ec;
    --pwle-panel: #ffffff;
    --pwle-soft: #f8fafc;
    --pwle-danger: #b42318;
}

body.post-type-pwl_enquete #wpcontent,
body.post-type-pwl_enquete #wpbody-content,
.pwle-admin-page {
    font-family: "Plus Jakarta Sans", Arial, sans-serif;
}

body.post-type-pwl_enquete #wpbody-content *,
.pwle-admin-page *,
.pwle-admin-shell * {
    box-sizing: border-box;
}

body.post-type-pwl_enquete #wpbody-content input,
body.post-type-pwl_enquete #wpbody-content textarea,
body.post-type-pwl_enquete #wpbody-content select,
body.post-type-pwl_enquete #wpbody-content button,
body.post-type-pwl_enquete #wpbody-content .button,
.pwle-admin-page input,
.pwle-admin-page textarea,
.pwle-admin-page select,
.pwle-admin-page button,
.pwle-admin-page .button {
    font-family: "Plus Jakarta Sans", Arial, sans-serif !important;
}

.pwle-admin-shell {
    margin: 18px 0;
    font-family: "Plus Jakarta Sans", Arial, sans-serif;
}

.pwle-admin-shell--standalone {
    margin-right: 20px;
    margin-left: 2px;
}

.pwle-header,
.pwle-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.pwle-header {
    margin: 18px 0;
}

.pwle-eyebrow,
.pwle-section-kicker {
    color: var(--pwle-o);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.12em;
}

.pwle-header h1,
.pwle-toolbar h2 {
    margin: 3px 0 0;
    color: var(--pwle-ink);
    font-family: "Plus Jakarta Sans", Arial, sans-serif;
    font-weight: 800;
}

.pwle-header h1 {
    font-size: 30px;
    line-height: 1.25;
}

.pwle-toolbar h2 {
    font-size: 23px;
    line-height: 1.3;
}

.pwle-header p,
.pwle-toolbar p {
    margin: 6px 0 0;
    color: var(--pwle-text);
    font-size: 13px;
    line-height: 1.55;
}

.pwle-version {
    flex: 0 0 auto;
    padding: 10px 14px;
    border: 1px solid var(--pwle-line);
    border-radius: 12px;
    background: var(--pwle-panel);
}

.pwle-version span {
    display: block;
    color: var(--pwle-mut);
    font-size: 10px;
}

.pwle-version strong {
    color: var(--pwle-ink);
    font-size: 14px;
}

.pwle-tabs {
    display: flex;
    align-items: stretch;
    justify-content: flex-start;
    gap: 4px;
    margin: 0 0 18px;
    padding: 4px;
    overflow-x: auto;
    border: 1px solid var(--pwle-line);
    border-radius: 13px;
    background: var(--pwle-panel);
}

.pwle-tab {
    position: relative;
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 190px;
    padding: 10px 13px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--pwle-text);
    text-align: left;
    text-decoration: none;
    white-space: nowrap;
    transition: 0.16s ease;
}

.pwle-tab:focus {
    color: var(--pwle-text);
    box-shadow: 0 0 0 2px rgba(255, 90, 0, 0.14);
    outline: 0;
}

.pwle-tab > .dashicons {
    display: flex;
    flex: 0 0 28px;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: var(--pwle-soft);
    color: var(--pwle-mut);
    font-size: 16px;
}

.pwle-tab-copy {
    min-width: 0;
}

.pwle-tab strong,
.pwle-tab small {
    display: block;
}

.pwle-tab strong {
    color: var(--pwle-ink);
    font-size: 12px;
    line-height: 1.25;
}

.pwle-tab small {
    margin-top: 2px;
    color: var(--pwle-mut);
    font-size: 9.5px;
    line-height: 1.2;
}

.pwle-tab:hover {
    background: var(--pwle-soft);
    color: var(--pwle-o);
}

.pwle-tab:hover strong,
.pwle-tab:hover > .dashicons {
    color: var(--pwle-o);
}

.pwle-tab.is-active {
    background: #f1f3f5;
}

.pwle-tab.is-active > .dashicons {
    background: #e8ebef;
    color: var(--pwle-mut);
}

.pwle-admin-page {
    color: var(--pwle-text);
}

.pwle-toolbar {
    align-items: center;
    margin: 18px 0;
}

.pwle-admin-page .button,
body.post-type-pwl_enquete #wpbody-content .button,
body.post-type-pwl_enquete #wpbody-content button.button {
    min-height: 40px;
    padding: 0 14px;
    border-radius: 8px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    box-shadow: none !important;
}

.pwle-admin-page .button-primary,
body.post-type-pwl_enquete #wpbody-content .button-primary,
body.post-type-pwl_enquete #wpbody-content button.button-primary {
    border-color: var(--pwle-o) !important;
    background: var(--pwle-o) !important;
    color: #ffffff !important;
}

.pwle-admin-page .button-primary:hover,
body.post-type-pwl_enquete #wpbody-content .button-primary:hover,
body.post-type-pwl_enquete #wpbody-content button.button-primary:hover {
    border-color: #e84f00 !important;
    background: #e84f00 !important;
}

.pwle-panel,
body.post-type-pwl_enquete .postbox {
    overflow: hidden;
    border: 1px solid var(--pwle-line) !important;
    border-radius: 14px !important;
    background: var(--pwle-panel) !important;
    box-shadow: none !important;
}

.pwle-panel-head {
    display: flex;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--pwle-line);
}

.pwle-panel-head > .dashicons {
    display: flex;
    flex: 0 0 34px;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--pwle-os);
    color: var(--pwle-o);
}

.pwle-panel-head h3,
.pwle-panel-body h3 {
    margin: 0;
    color: var(--pwle-ink);
}

.pwle-panel-head p {
    margin: 3px 0 0;
    color: var(--pwle-text);
    font-size: 11px;
}

.pwle-panel-body {
    padding: 18px;
}

.pwle-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.pwle-metrics article {
    padding: 17px 18px;
    border: 1px solid var(--pwle-line);
    border-radius: 14px;
    background: var(--pwle-panel);
}

.pwle-metrics span {
    display: block;
    color: var(--pwle-mut);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.pwle-metrics strong {
    display: block;
    margin-top: 7px;
    color: var(--pwle-ink);
    font-size: 24px;
}

.pwle-tablewrap {
    overflow: auto;
}

.pwle-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
}

.pwle-table th,
.pwle-table td {
    padding: 12px;
    border-bottom: 1px solid var(--pwle-line);
    text-align: left;
    vertical-align: top;
}

.pwle-table th {
    color: var(--pwle-mut);
    font-size: 10px;
    text-transform: uppercase;
}

.pwle-table td {
    color: var(--pwle-text);
    font-size: 11px;
}

.pwle-table td > strong,
.pwle-table td > small {
    display: block;
}

.pwle-table td > strong {
    color: var(--pwle-ink);
}

.pwle-table td > small {
    margin-top: 3px;
    color: var(--pwle-mut);
}

.pwle-table code {
    white-space: nowrap;
}

.pwle-badge {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 99px;
    background: var(--pwle-os);
    color: var(--pwle-o);
    font-size: 10px;
    font-weight: 700;
}

.pwle-badge.is-active {
    background: #eaf8f2;
    color: var(--pwl-color-success,#067647);
}

.pwle-badge.is-closed {
    background: #f2f4f7;
    color: #667085;
}

.pwle-row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.pwle-row-actions a {
    color: var(--pwle-o);
    font-weight: 700;
    text-decoration: none;
}

.pwle-empty {
    padding: 40px;
    color: var(--pwle-text);
    text-align: center;
}

.pwle-empty strong {
    color: var(--pwle-ink);
}

.pwle-empty p {
    margin: 5px 0 0;
}

.pwle-about-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.pwle-about-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    border: 1px solid var(--pwle-line);
    border-radius: 14px;
    background: var(--pwle-panel);
}

.pwle-about-card > .dashicons {
    display: flex;
    flex: 0 0 34px;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--pwle-os);
    color: var(--pwle-o);
}

.pwle-about-card h3 {
    margin: 0 0 8px;
    color: var(--pwle-ink);
}

.pwle-about-card strong {
    color: var(--pwle-text);
}

.pwle-about-notes {
    margin-top: 16px;
}

.pwle-about-notes .pwle-panel-body h3 {
    margin: 0 0 6px;
}

.pwle-about-notes .pwle-panel-body p {
    margin: 0 0 16px;
    color: var(--pwle-text);
}

body.edit-php.post-type-pwl_enquete .wrap > .wp-heading-inline,
body.edit-php.post-type-pwl_enquete .wrap > .page-title-action {
    display: none !important;
}

body.post-type-pwl_enquete #poststuff {
    padding-top: 0;
}

body.post-type-pwl_enquete #titlediv #title {
    min-height: 50px;
    padding: 8px 13px;
    border: 1px solid var(--pwle-line);
    border-radius: 10px;
    background: var(--pwle-panel);
    color: var(--pwle-ink);
    font-size: 20px;
    font-weight: 700;
    box-shadow: none;
}

body.post-type-pwl_enquete #titlediv #title:focus,
body.post-type-pwl_enquete input:focus,
body.post-type-pwl_enquete textarea:focus,
body.post-type-pwl_enquete select:focus {
    border-color: var(--pwle-o) !important;
    box-shadow: 0 0 0 3px rgba(255, 90, 0, 0.1) !important;
    outline: 0 !important;
}

body.post-type-pwl_enquete #post-body.columns-2 {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 16px;
    margin-right: 0;
}

body.post-type-pwl_enquete #post-body-content,
body.post-type-pwl_enquete #postbox-container-1,
body.post-type-pwl_enquete #postbox-container-2 {
    float: none;
    width: auto;
    min-width: 0;
    margin: 0;
}

body.post-type-pwl_enquete #post-body-content {
    grid-column: 1;
}

body.post-type-pwl_enquete #postbox-container-1 {
    grid-column: 2;
    grid-row: 1 / span 2;
}

body.post-type-pwl_enquete #postbox-container-2 {
    grid-column: 1;
}

body.post-type-pwl_enquete #poststuff .postbox-header {
    border-bottom: 1px solid var(--pwle-line);
}

body.post-type-pwl_enquete #poststuff .postbox-header h2,
body.post-type-pwl_enquete #poststuff h2.hndle {
    color: var(--pwle-ink);
    font-family: "Plus Jakarta Sans", Arial, sans-serif;
    font-size: 13px;
    font-weight: 800;
}

body.post-type-pwl_enquete #poststuff .inside {
    margin-top: 0;
    padding: 18px;
}

body.post-type-pwl_enquete #postdivrich {
    overflow: hidden;
    border: 1px solid var(--pwle-line);
    border-radius: 14px;
    background: var(--pwle-panel);
}

body.post-type-pwl_enquete #wp-content-editor-tools {
    background: var(--pwle-soft);
}

body.post-type-pwl_enquete .pwl-admin-grid {
    gap: 14px;
}

body.post-type-pwl_enquete .pwl-admin-field > label strong {
    color: var(--pwle-ink);
    font-size: 12px;
}

body.post-type-pwl_enquete .pwl-admin-field .description {
    color: var(--pwle-mut);
    font-size: 10.5px;
}

body.post-type-pwl_enquete .pwl-option-row {
    padding: 7px;
    border: 1px solid var(--pwle-line);
    border-radius: 10px;
    background: var(--pwle-soft);
}

body.post-type-pwl_enquete .pwl-option-row input[type="text"],
body.post-type-pwl_enquete .pwl-admin-field select,
body.post-type-pwl_enquete .pwl-admin-field input[type="datetime-local"],
body.post-type-pwl_enquete .pwl-shortcode-field {
    min-height: 42px;
    border: 1px solid var(--pwle-line);
    border-radius: 9px;
    background: var(--pwle-panel);
    color: var(--pwle-ink);
}

body.post-type-pwl_enquete .wp-list-table {
    overflow: hidden;
    border: 1px solid var(--pwle-line);
    border-radius: 14px;
    background: var(--pwle-panel);
    box-shadow: none;
}

body.post-type-pwl_enquete .wp-list-table th,
body.post-type-pwl_enquete .wp-list-table td {
    padding-top: 11px;
    padding-bottom: 11px;
}

body.post-type-pwl_enquete .wp-list-table thead th,
body.post-type-pwl_enquete .wp-list-table tfoot th {
    color: var(--pwle-mut);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

body.post-type-pwl_enquete .subsubsub a,
body.post-type-pwl_enquete .row-actions a {
    color: var(--pwle-o);
}

body.post-type-pwl_enquete .tablenav .button,
body.post-type-pwl_enquete .search-box .button {
    min-height: 34px;
}

body.post-type-pwl_enquete .pwl-admin-report {
    border-top-color: var(--pwle-line);
}

body.post-type-pwl_enquete .pwl-admin-report__header h3,
body.post-type-pwl_enquete .pwl-results__heading strong,
body.post-type-pwl_enquete .pwl-admin-report__metric strong,
body.post-type-pwl_enquete .pwl-result__labels span {
    color: var(--pwle-ink);
}

body.post-type-pwl_enquete .pwl-admin-report__header p,
body.post-type-pwl_enquete .pwl-results__heading span,
body.post-type-pwl_enquete .pwl-admin-report__metric span,
body.post-type-pwl_enquete .pwl-result small {
    color: var(--pwle-text);
}

body.post-type-pwl_enquete .pwl-admin-report__metric,
body.post-type-pwl_enquete .pwl-admin-report__empty {
    border-color: var(--pwle-line);
    background: var(--pwle-soft);
}

body.post-type-pwl_enquete .pwl-admin-report__table-wrap {
    border-color: var(--pwle-line);
}

body.cm-dark.post-type-pwl_enquete,
body.pwl-dark.post-type-pwl_enquete,
body.cm-dark .pwle-admin-page,
body.pwl-dark .pwle-admin-page,
html[data-theme="dark"] .pwle-admin-page,
body[data-theme="dark"] .pwle-admin-page,
body.cm-dark .pwle-admin-shell,
body.pwl-dark .pwle-admin-shell,
html[data-theme="dark"] .pwle-admin-shell,
body[data-theme="dark"] .pwle-admin-shell {
    --pwle-ink: #f7f9fc;
    --pwle-text: #b8c4d3;
    --pwle-mut: #8fa0b4;
    --pwle-line: #354052;
    --pwle-panel: #111827;
    --pwle-soft: #172033;
    --pwle-os: #3a1d10;
}

body.cm-dark .pwle-tab.is-active,
body.pwl-dark .pwle-tab.is-active,
html[data-theme="dark"] .pwle-tab.is-active,
body[data-theme="dark"] .pwle-tab.is-active {
    background: #202938;
}

body.cm-dark.post-type-pwl_enquete #wpbody-content,
body.pwl-dark.post-type-pwl_enquete #wpbody-content,
body.cm-dark.post-type-pwl_enquete #wpcontent,
body.pwl-dark.post-type-pwl_enquete #wpcontent {
    background: #0f1724;
}

body.cm-dark.post-type-pwl_enquete .wp-list-table,
body.pwl-dark.post-type-pwl_enquete .wp-list-table,
body.cm-dark.post-type-pwl_enquete .wp-list-table th,
body.cm-dark.post-type-pwl_enquete .wp-list-table td,
body.pwl-dark.post-type-pwl_enquete .wp-list-table th,
body.pwl-dark.post-type-pwl_enquete .wp-list-table td {
    border-color: var(--pwle-line);
    background: var(--pwle-panel);
    color: var(--pwle-text);
}

@media (max-width: 1180px) {
    body.post-type-pwl_enquete #post-body.columns-2 {
        grid-template-columns: minmax(0, 1fr) 290px;
    }

    .pwle-tab {
        min-width: 175px;
    }
}

@media (max-width: 960px) {
    body.post-type-pwl_enquete #post-body.columns-2 {
        display: block;
    }

    body.post-type-pwl_enquete #postbox-container-1,
    body.post-type-pwl_enquete #postbox-container-2 {
        width: 100%;
    }
}

@media (max-width: 782px) {
    .pwle-header,
    .pwle-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .pwle-tabs {
        gap: 3px;
        padding: 3px;
    }

    .pwle-tab {
        min-width: 155px;
    }

    .pwle-metrics {
        grid-template-columns: 1fr;
    }

    .pwle-admin-shell--standalone {
        margin-right: 10px;
        margin-left: 0;
    }
}
CSS;
    }

    private function admin_workspace_script() {
        return <<<'JS'
(function(){
    "use strict";
    const root=document.querySelector('.pwle-workspace');
    if(!root)return;

    const tabLinks=Array.from(root.querySelectorAll('[data-pwle-tab]'));
    const panels=Array.from(root.querySelectorAll('[data-pwle-panel]'));
    function showTab(tab,updateHistory){
        if(!panels.some(function(panel){return panel.dataset.pwlePanel===tab;}))return;
        panels.forEach(function(panel){panel.hidden=panel.dataset.pwlePanel!==tab;});
        tabLinks.forEach(function(link){const active=link.dataset.pwleTab===tab;link.classList.toggle('is-selected',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
        if(updateHistory){const url=new URL(window.location.href);url.searchParams.set('tab',tab);if(tab!=='editor')url.searchParams.delete('edit');window.history.pushState({pwleTab:tab},'',url);}
    }
    tabLinks.forEach(function(link){link.addEventListener('click',function(event){if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;event.preventDefault();const url=new URL(link.href);const edit=url.searchParams.get('edit');showTab(link.dataset.pwleTab,true);if(edit){url.searchParams.set('tab','editor');window.history.replaceState({pwleTab:'editor'},'',url);}});});
    window.addEventListener('popstate',function(){showTab(new URL(window.location.href).searchParams.get('tab')||'editor',false);});

    const optionsList=document.getElementById('pwl-options-list');
    const form=document.getElementById('pwlPollWorkspaceForm');
    const statusToggle=document.getElementById('pwlPollStatusToggle');
    const statusValue=document.getElementById('pwlPollStatusValue');
    const imageId=document.getElementById('pwlPollImageId');
    const imagePreview=document.getElementById('pwlPollImagePreview');
    const removeImageButton=document.getElementById('pwlRemovePollImage');
    let mediaTarget=null;
    let emojiTarget=null;

    const emojiGroups={
        '😀':['😀','😃','😄','😁','😆','😅','😂','🙂','🙃','😉','😊','😍','🥰','😘','😎','🤩','🥳','🤔','🤗','🤭','😐','😕','😢','😭','😡','🤯','😴','🤓','🫡','🫠'],
        '👍':['👍','👎','👏','🙌','👌','✌️','🤞','🤝','🙏','💪','👀','💡','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💯','✅','❌','⭐','🔥','✨','⚡','🎯','🏆','📌'],
        '🏢':['🏢','🏭','💻','🖥️','⌨️','📱','📞','📧','📊','📈','📉','🗂️','📁','📋','📝','🔧','🛠️','⚙️','🔒','🔑','🌐','📶','🚀','🧩','👥','👤','🕒','📅','🔔','💬'],
        '🍽️':['☕','🥤','🍽️','🍕','🍔','🥗','🍎','🍊','🍌','🍰','🎂','🍫','🌞','🌧️','⛅','🌙','🌱','🌳','🚗','🚌','🏠','📍','🎉','🎁','🎵','⚽','🏃','🚶','♻️','🧹']
    };

    function ensureEmojiLibrary(){
        let lib=document.getElementById('pwleEmojiLibrary');
        if(lib)return lib;
        lib=document.createElement('div');
        lib.id='pwleEmojiLibrary';
        lib.className='pwle-emoji-library';
        lib.hidden=true;
        lib.innerHTML='<div class="pwle-emoji-library__header"><strong>Escolher emoji</strong><button type="button" class="pwle-emoji-library__close" aria-label="Fechar">×</button></div><div class="pwle-emoji-library__tabs"></div><div class="pwle-emoji-library__grid"></div>';
        document.body.appendChild(lib);
        const tabs=lib.querySelector('.pwle-emoji-library__tabs');
        Object.keys(emojiGroups).forEach(function(group,index){
            const b=document.createElement('button'); b.type='button'; b.className='pwle-emoji-library__tab'+(index===0?' is-active':''); b.textContent=group; b.dataset.group=group; tabs.appendChild(b);
        });
        function render(group){
            const grid=lib.querySelector('.pwle-emoji-library__grid'); grid.innerHTML='';
            emojiGroups[group].forEach(function(emoji){const b=document.createElement('button');b.type='button';b.className='pwle-emoji-library__emoji';b.textContent=emoji;b.dataset.emoji=emoji;grid.appendChild(b);});
            lib.querySelectorAll('.pwle-emoji-library__tab').forEach(function(t){t.classList.toggle('is-active',t.dataset.group===group);});
        }
        render(Object.keys(emojiGroups)[0]);
        lib.addEventListener('click',function(e){
            const tab=e.target.closest('.pwle-emoji-library__tab'); if(tab){render(tab.dataset.group);return;}
            const em=e.target.closest('.pwle-emoji-library__emoji'); if(em&&emojiTarget){emojiTarget.value=em.dataset.emoji;emojiTarget.dispatchEvent(new Event('change',{bubbles:true}));lib.hidden=true;emojiTarget=null;return;}
            if(e.target.closest('.pwle-emoji-library__close')){lib.hidden=true;emojiTarget=null;}
        });
        return lib;
    }

    function openEmoji(button){
        const row=button.closest('.pwle-option-row'); if(!row)return;
        emojiTarget=row.querySelector('input[name="pwl_option_emojis[]"]');
        const lib=ensureEmojiLibrary();
        const rect=button.getBoundingClientRect();
        lib.hidden=false;
        const w=Math.min(420,window.innerWidth-32), h=Math.min(520,window.innerHeight-32);
        let left=Math.min(rect.left,window.innerWidth-w-16); left=Math.max(16,left);
        let top=rect.bottom+8; if(top+h>window.innerHeight)top=Math.max(16,rect.top-h-8);
        lib.style.left=left+'px'; lib.style.top=top+'px';
    }

    function updateOptionNumbers(){
        if(!optionsList)return;
        optionsList.querySelectorAll('.pwl-option-row').forEach(function(row,index){
            row.classList.add('pwle-option-row');
            let number=row.querySelector('.pwle-option-number');
            if(!number){number=document.createElement('span');number.className='pwle-option-number';row.insertBefore(number,row.firstChild);}
            number.textContent=String(index+1);
        });
    }

    function updateChoiceCards(name){root.querySelectorAll('input[name="'+name+'"]').forEach(function(input){const card=input.closest('.pwle-choice-card');if(card)card.classList.toggle('is-selected',input.checked);});}

    function openPwlMedia(target,title){
        if(typeof window.CustomEvent!=='function')return;
        mediaTarget=target;
        if(typeof window.pwlMediaPicker==='undefined' && !document.querySelector('[data-pwl-media-picker],.pwl-media-picker,.pwl-media-modal')){
            // The public picker can still be event-driven without a global marker.
        }
        window.dispatchEvent(new CustomEvent('pwl:media-picker:open',{detail:{source:'pwl-enquetes',type:'image',title:title||'Selecionar imagem'}}));
    }

    window.addEventListener('pwl:media-picker:selected',function(event){
        const item=event.detail||{}; if(!mediaTarget||!item.id)return;
        const id=Number(item.id)||0;
        const url=item.url||item.thumbnail||item.thumb||'';
        if(mediaTarget.kind==='poll'){
            if(imageId)imageId.value=id;
            if(imagePreview)imagePreview.innerHTML=url?'<img src="'+String(url).replace(/"/g,'&quot;')+'" alt="Prévia da imagem da enquete">':'<span class="dashicons dashicons-format-image"></span><strong>Imagem selecionada</strong>';
            if(removeImageButton)removeImageButton.hidden=false;
        }else if(mediaTarget.kind==='option'&&mediaTarget.row){
            const row=mediaTarget.row, input=row.querySelector('.pwle-option-image-id'), preview=row.querySelector('.pwle-option-image-preview'), remove=row.querySelector('.pwle-option-image-remove');
            if(input)input.value=id;
            if(preview){preview.classList.add('has-image');preview.innerHTML=url?'<img src="'+String(url).replace(/"/g,'&quot;')+'" alt="">':'<span class="dashicons dashicons-format-image"></span>';}
            if(remove)remove.hidden=false;
        }
        mediaTarget=null;
    });

    root.addEventListener('change',function(event){
        if(event.target.matches('input[name="pwl_type"]'))updateChoiceCards('pwl_type');
        if(event.target===statusToggle&&statusValue)statusValue.value=statusToggle.checked?'active':'closed';
    });

    root.addEventListener('click',function(event){
        const addOption=event.target.closest('#pwl-add-option');
        if(addOption&&optionsList){
            event.preventDefault();
            const key=(window.crypto&&crypto.randomUUID)?crypto.randomUUID():'pwl-'+Date.now()+'-'+Math.random().toString(16).slice(2);
            const row=document.createElement('div'); row.className='pwl-option-row pwle-option-row';
            row.innerHTML='<span class="pwle-option-number"></span><span class="dashicons dashicons-menu pwl-drag" aria-hidden="true"></span><input type="hidden" name="pwl_option_keys[]" value="'+key+'"><div class="pwle-option-emoji-wrap"><input type="text" class="pwl-input pwle-option-emoji" name="pwl_option_emojis[]" placeholder="😀" maxlength="12" aria-label="Emoji da alternativa"><button type="button" class="pwl-button pwl-button--ghost pwle-emoji-open" aria-label="Escolher emoji" title="Escolher emoji">☺</button></div><input type="text" class="pwl-input pwle-option-label" name="pwl_option_labels[]" placeholder="Digite a alternativa" maxlength="180" required><div class="pwle-option-image"><input type="hidden" name="pwl_option_image_ids[]" value="" class="pwle-option-image-id"><div class="pwle-option-image-preview"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div><button type="button" class="pwl-button pwl-button--ghost pwle-option-image-select" title="Selecionar imagem na Biblioteca PWL"><span class="dashicons dashicons-format-image"></span></button><button type="button" class="pwl-button pwl-button--ghost pwle-option-image-remove" title="Remover imagem" hidden><span class="dashicons dashicons-no-alt"></span></button></div><button type="button" class="pwl-button pwl-button--ghost pwl-remove-option" aria-label="Remover alternativa"><span class="dashicons dashicons-trash"></span></button>';
            optionsList.appendChild(row); updateOptionNumbers(); const label=row.querySelector('input[name="pwl_option_labels[]"]');if(label)label.focus(); return;
        }
        const removeOption=event.target.closest('.pwl-remove-option');
        if(removeOption&&optionsList){event.preventDefault();const rows=optionsList.querySelectorAll('.pwl-option-row');if(rows.length<=2){window.alert('A enquete precisa manter pelo menos duas alternativas.');return;}const row=removeOption.closest('.pwl-option-row');if(row)row.remove();updateOptionNumbers();return;}
        const emojiButton=event.target.closest('.pwle-emoji-open'); if(emojiButton){event.preventDefault();openEmoji(emojiButton);return;}
        const optionImage=event.target.closest('.pwle-option-image-select'); if(optionImage){event.preventDefault();openPwlMedia({kind:'option',row:optionImage.closest('.pwle-option-row')},'Selecionar imagem da alternativa');return;}
        const optionImageRemove=event.target.closest('.pwle-option-image-remove'); if(optionImageRemove){event.preventDefault();const row=optionImageRemove.closest('.pwle-option-row');const input=row.querySelector('.pwle-option-image-id');const preview=row.querySelector('.pwle-option-image-preview');if(input)input.value='';if(preview){preview.classList.remove('has-image');preview.innerHTML='<span class="dashicons dashicons-format-image"></span>';}optionImageRemove.hidden=true;return;}
        if(event.target.closest('#pwlSelectPollImage')){event.preventDefault();openPwlMedia({kind:'poll'},'Selecionar imagem da enquete');return;}
        if(event.target.closest('#pwlRemovePollImage')){event.preventDefault();if(imageId)imageId.value='';if(imagePreview)imagePreview.innerHTML='<span class="dashicons dashicons-format-image" aria-hidden="true"></span><strong>Nenhuma imagem selecionada</strong><small>PNG, JPG ou WebP da Biblioteca de Mídia PWL.</small>';if(removeImageButton)removeImageButton.hidden=true;return;}
        const copyButton=event.target.closest('[data-copy-target]'); if(copyButton){const target=document.getElementById(copyButton.dataset.copyTarget);const value=target?(typeof target.value==='string'?target.value:target.textContent):'';if(value&&navigator.clipboard){navigator.clipboard.writeText(value).then(function(){const original=copyButton.textContent;copyButton.textContent='Copiado';window.setTimeout(function(){copyButton.textContent=original;},1400);});}else if(target&&target.select){target.select();document.execCommand('copy');}}
        const confirmLink=event.target.closest('[data-confirm]'); if(confirmLink&&!window.confirm(confirmLink.dataset.confirm||'Confirmar esta ação?'))event.preventDefault();
    });

    document.addEventListener('click',function(event){const lib=document.getElementById('pwleEmojiLibrary');if(lib&&!lib.hidden&&!event.target.closest('#pwleEmojiLibrary')&&!event.target.closest('.pwle-emoji-open')){lib.hidden=true;emojiTarget=null;}});
    if(optionsList&&window.MutationObserver)new MutationObserver(updateOptionNumbers).observe(optionsList,{childList:true});
    if(form)form.addEventListener('submit',function(event){const labels=Array.from(form.querySelectorAll('input[name="pwl_option_labels[]"]')).filter(function(input){return input.value.trim()!=='';});if(labels.length<2){event.preventDefault();window.alert('Cadastre pelo menos duas alternativas válidas.');}});
    if(optionsList&&window.jQuery&&jQuery.fn.sortable)jQuery(optionsList).sortable({handle:'.pwl-drag',items:'.pwl-option-row',update:updateOptionNumbers});
    updateOptionNumbers(); updateChoiceCards('pwl_type'); if(statusToggle&&statusValue)statusValue.value=statusToggle.checked?'active':'closed';
})();
JS;
    }

    private function admin_interface_script() {
        return <<<'JS'
(function(){
    "use strict";
    function init(){
        document.body.classList.add("pwle-admin-active");
        var shell=document.querySelector('.pwle-admin-shell[data-pwle-move-to-wrap="1"]');
        var wrap=document.querySelector('#wpbody-content .wrap');
        if(shell&&wrap&&shell.parentNode!==wrap){wrap.insertBefore(shell,wrap.firstChild);}
        var titlePrompt=document.getElementById('title-prompt-text');
        if(titlePrompt){titlePrompt.textContent='Digite a pergunta da enquete';}
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
JS;
    }

    private function get_results_html($poll_id, $admin = false) {
        $data = $this->get_results_data($poll_id);
        ob_start();
        ?>
        <div class="pwl-results <?php echo $admin ? 'pwl-results--admin' : ''; ?>">
            <div class="pwl-results__heading">
                <strong>Resultado</strong>
                <span><?php echo esc_html(sprintf(_n('%d participacao', '%d participacoes', $data['voters'], 'pwl-enquetes'), $data['voters'])); ?></span>
            </div>
            <?php if (empty($data['options'])) : ?>
                <p>Nenhuma alternativa cadastrada.</p>
            <?php else : ?>
                <div class="pwl-results__list">
                    <?php foreach ($data['options'] as $option) :
                        $count = isset($data['counts'][$option['key']]) ? $data['counts'][$option['key']] : 0;
                        $percent = $data['voters'] > 0 ? round(($count / $data['voters']) * 100, 1) : 0;
                        ?>
                        <div class="pwl-result">
                            <div class="pwl-result__labels">
                                <span><?php if (!empty($option['image_id'])) : ?><span class="pwl-result__thumb"><?php echo wp_get_attachment_image(absint($option['image_id']), 'thumbnail'); ?></span><?php endif; ?><?php $option_emoji = $this->decode_option_emoji($option); echo '' !== $option_emoji ? '<span class="pwl-result__emoji">' . esc_html($option_emoji) . '</span> ' : ''; ?><?php echo esc_html($option['label']); ?></span>
                                <strong><?php echo esc_html(number_format_i18n($percent, 1)); ?>%</strong>
                            </div>
                            <div class="pwl-result__track"><span style="width: <?php echo esc_attr($percent); ?>%"></span></div>
                            <small><?php echo esc_html(sprintf(_n('%d voto', '%d votos', $count, 'pwl-enquetes'), $count)); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_vote_count($poll_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE_SUFFIX . ' WHERE poll_id = %d',
            $poll_id
        ));
    }

    private function has_voted($poll_id, $user_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::TABLE_SUFFIX . ' WHERE poll_id = %d AND user_id = %d LIMIT 1',
            $poll_id,
            $user_id
        ));
    }

    private function is_open($poll_id) {
        if ('closed' === get_post_meta($poll_id, '_pwl_status', true)) {
            return false;
        }
        $closes_at = get_post_meta($poll_id, '_pwl_closes_at', true);
        if ($closes_at) {
            try {
                $close = new DateTimeImmutable($closes_at, wp_timezone());
                $now = new DateTimeImmutable('now', wp_timezone());
                if ($now >= $close) {
                    return false;
                }
            } catch (Exception $e) {
                return true;
            }
        }
        return true;
    }
}

register_activation_hook(__FILE__, array('PWL_Enquetes', 'activate'));
register_deactivation_hook(__FILE__, array('PWL_Enquetes', 'deactivate'));
PWL_Enquetes::instance();



/* =========================================================
 * 2.3.5 - modo embedded para PWL Popups
 * Remove somente a superficie externa da enquete quando
 * renderizada dentro de popup. Fora do popup nada muda.
 * ========================================================= */
add_action( 'wp_footer', 'pwl_enquetes_embedded_popup_styles', 110 );
add_action( 'admin_footer', 'pwl_enquetes_embedded_popup_styles', 110 );

function pwl_enquetes_embedded_popup_styles() {
    ?>
    <style id="pwl-enquetes-embedded-popup-styles">
        /* O popup externo e o unico card/superficie visual. */
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded.pwl-card,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-card,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-card {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        /* Remove apenas o respiro que fazia parecer card dentro de card. */
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-card,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-card {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }

        /* Preserva a estrutura interna da enquete. */
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-content,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-content,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded form {
            width: 100% !important;
            max-width: none !important;
        }
    </style>
    <?php
}




/* =========================================================
 * 2.3.6 - refinamento do modo embedded
 * Preenche melhor a area util do popup mantendo um unico card.
 * ========================================================= */
add_action( 'wp_footer', 'pwl_enquetes_embedded_layout_refine', 130 );
add_action( 'admin_footer', 'pwl_enquetes_embedded_layout_refine', 130 );

function pwl_enquetes_embedded_layout_refine() {
    ?>
    <style id="pwl-enquetes-embedded-layout-refine">
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded {
            display: block !important;
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded > *,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-card,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-shell,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-card {
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-header,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-header {
            padding: 0 0 14px !important;
            margin: 0 0 16px !important;
            border-bottom: 1px solid var(--pwl-color-border, #e4e7ec) !important;
            border-radius: 0 !important;
            background: transparent !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-content,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-content,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded form {
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-grid,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-grid,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="enquete-grid"],
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="poll-grid"] {
            display: grid !important;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .95fr) !important;
            gap: 28px !important;
            align-items: start !important;
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded img {
            width: 100% !important;
            max-width: none !important;
            height: auto !important;
            display: block !important;
            object-fit: contain !important;
            border-radius: var(--pwl-radius-lg, 14px) !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-options,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-options,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="options"] {
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-has-embedded-poll .pwl-enquete--embedded button[type="submit"],
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-button,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="vote-button"] {
            width: 100% !important;
            min-height: 42px !important;
            border-radius: 10px !important;
        }

        @media (max-width: 980px) {
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-grid,
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-grid,
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="enquete-grid"],
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="poll-grid"] {
                grid-template-columns: 1fr !important;
                gap: 18px !important;
            }
        }

        @media (max-width: 620px) {
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-header,
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-poll-header {
                margin-bottom: 12px !important;
                padding-bottom: 12px !important;
            }
        }
    </style>
    <?php
}



/* =========================================================
 * 2.3.7 - refinamento real do modo embedded
 * Usa as classes reais .pwl-poll e .pwl-poll--embedded-popup.
 * ========================================================= */
add_action( 'wp_footer', 'pwl_enquetes_embedded_real_layout_fix', 1000 );
add_action( 'admin_footer', 'pwl_enquetes_embedded_real_layout_fix', 1000 );
function pwl_enquetes_embedded_real_layout_fix() {
    ?>
    <style id="pwl-enquetes-embedded-real-layout-fix">
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__header,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__header,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__header {
            margin: 0 !important;
            padding: 0 0 16px !important;
            background: transparent !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__body,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__body,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__body {
            padding: 18px 0 0 !important;
            width: 100% !important;
            max-width: none !important;
        }

        @container pwl-poll-popup (min-width: 900px) {
            .pwl-poll.pwl-poll--intranet .pwl-poll__body {
                grid-template-columns: minmax(0, 1.18fr) minmax(340px, .82fr) !important;
                column-gap: 32px !important;
            }
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__media,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__media,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__media {
            margin-top: 14px !important;
            border-radius: var(--pwl-radius-lg, 14px) !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__image,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__image,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__image {
            width: 100% !important;
            max-width: none !important;
            max-height: 420px !important;
            object-fit: contain !important;
            border-radius: inherit !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__options,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__options,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__options {
            gap: 10px !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__option,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__option,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__option {
            border-radius: var(--pwl-radius-md, 9px) !important;
        }

        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__submit,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__submit,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__submit {
            margin-top: 12px !important;
            border-radius: 10px !important;
        }
    </style>
    <?php
}



/* =========================================================
 * 2.3.8 - modo embedded compacto
 * Reduz tipografia, controles, imagem e espacamentos.
 * ========================================================= */
add_action( 'wp_footer', 'pwl_enquetes_compact_embedded_popup', 150 );
add_action( 'admin_footer', 'pwl_enquetes_compact_embedded_popup', 150 );

function pwl_enquetes_compact_embedded_popup() {
    ?>
    <style id="pwl-enquetes-compact-embedded-popup">
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup,
        .pwl-preview-card--adaptive-poll .pwl-enquete--embedded {
            width: 100% !important;
            max-width: none !important;
            font-size: 12px !important;
            line-height: 1.45 !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__header,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-header,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__header,
        .pwl-preview-card--adaptive-poll .pwl-enquete--embedded .pwl-enquete-header {
            padding: 0 0 10px !important;
            margin: 0 0 12px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup h1,
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup h2,
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup h3,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded h1,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded h2,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded h3,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup h1,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup h2,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup h3 {
            font-size: 20px !important;
            line-height: 1.2 !important;
            margin-bottom: 6px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup p,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded p,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup p {
            font-size: 11.5px !important;
            line-height: 1.45 !important;
            margin-top: 2px !important;
            margin-bottom: 4px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__eyebrow,
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__status,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="eyebrow"],
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="status"],
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup [class*="eyebrow"],
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup [class*="status"] {
            font-size: 10px !important;
            line-height: 1.2 !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__layout,
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__grid,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-grid,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__layout,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__grid {
            display: grid !important;
            grid-template-columns: minmax(0, 1.08fr) minmax(300px, .92fr) !important;
            gap: 18px !important;
            align-items: start !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup img,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded img,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup img {
            width: 100% !important;
            max-height: 300px !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 10px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup label,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded label,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup label {
            font-size: 11.5px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup input[type="radio"] + *,
        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup input[type="checkbox"] + *,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded input[type="radio"] + *,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup input[type="radio"] + * {
            font-size: 11.5px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__option,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded [class*="option"],
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__option {
            min-height: 36px !important;
            padding: 8px 10px !important;
            border-radius: 8px !important;
            margin-bottom: 8px !important;
        }

        .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup button,
        .pwl-popup-has-embedded-poll .pwl-enquete--embedded button,
        .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup button {
            min-height: 38px !important;
            padding: 0 14px !important;
            font-size: 11.5px !important;
            border-radius: 8px !important;
        }

        @media (max-width: 760px) {
            .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__layout,
            .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup .pwl-poll__grid,
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded .pwl-enquete-grid,
            .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__layout,
            .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup .pwl-poll__grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .pwl-popup-has-embedded-poll .pwl-poll--embedded-popup img,
            .pwl-popup-has-embedded-poll .pwl-enquete--embedded img,
            .pwl-preview-card--adaptive-poll .pwl-poll--embedded-popup img {
                max-height: 240px !important;
            }
        }
    </style>
    <?php
}




/* =========================================================
 * 2.3.9 - layout vertical no modo popup
 * Cabeçalho, pergunta, mídia, opções, botão, mensagens e
 * resultados ficam sempre em uma única coluna.
 * ========================================================= */
add_action( 'wp_footer', 'pwl_enquetes_vertical_popup_layout', 170 );
add_action( 'admin_footer', 'pwl_enquetes_vertical_popup_layout', 170 );

function pwl_enquetes_vertical_popup_layout() {
    ?>
    <style id="pwl-enquetes-vertical-popup-layout">
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__body,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__body,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__body,
        .pwl-popup-has-embedded-poll .pwl-poll.pwl-poll--intranet .pwl-poll__body {
            display: block !important;
            grid-template-columns: none !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__question,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__question,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__question,
        .pwl-popup-has-embedded-poll .pwl-poll.pwl-poll--intranet .pwl-poll__question {
            display: block !important;
            grid-column: auto !important;
            grid-row: auto !important;
            width: 100% !important;
            margin: 0 0 14px !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__body > .pwl-poll__form,
        .pwl-popup-has-embedded-poll .pwl-poll.pwl-poll--intranet .pwl-poll__body > .pwl-poll__form,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__feedback,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__feedback,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__feedback,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__results,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__results,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__results {
            grid-column: auto !important;
            grid-row: auto !important;
            width: 100% !important;
            max-width: none !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__media,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__media,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__media {
            width: 100% !important;
            margin: 12px 0 14px !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__image,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__image,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__image {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            max-height: 260px !important;
            height: auto !important;
            object-fit: contain !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__options,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__options,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__options {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 8px !important;
            width: 100% !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__option,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__option,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__option {
            width: 100% !important;
            min-height: 38px !important;
            margin: 0 !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__submit,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__submit,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__submit {
            width: 100% !important;
            margin-top: 10px !important;
        }

        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__message,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__message,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__message,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__feedback,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__feedback,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__feedback,
        .pwl-popup-content .pwl-poll.pwl-poll--intranet .pwl-poll__results,
        .pwl-preview-body .pwl-poll.pwl-poll--intranet .pwl-poll__results,
        .pwl-poll.pwl-poll--intranet.pwl-poll--embedded-popup .pwl-poll__results {
            margin-top: 14px !important;
        }
    </style>
    <?php
}

