<?php
/**
 * Plugin Name: CDW Plugin Base
 * Plugin URI:  https://congdongweb.com/
 * Description: A base plugin for CDW with license management.
 * Version:     1.0.0
 * Author:      CongDongWeb
 * Author URI:  https://congdongweb.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cdw-plugin-base
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CDW_PLUGIN_BASE_VERSION', '1.0.0' );
define( 'CDW_PLUGIN_BASE_FILE',  __FILE__ );
define( 'CDW_PLUGIN_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CDW_PLUGIN_BASE_URL', plugin_dir_url( __FILE__ ) );
define( 'CDW_PLUGIN_BASE_ID', 2861 );
define( 'CDW_LICENSE_SERVER_URL', 'https://dev2.congdongweb.com/wp-json/cdw/v1/' );
define( 'CDW_UPDATE_SERVER_URL', 'https://dev2.congdongweb.com/wp-json/cdw/v1/' );

require_once CDW_PLUGIN_BASE_DIR . 'includes/class-cdw-license-manager.php';

class CDW_Plugin_Base {

    private $license_manager;

    public function __construct() {
        $this->license_manager = new CDW_License_Manager();

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'check_license_status_for_notice' ) );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'cdw_plugin_base_daily_license_check', array( $this->license_manager, 'perform_remote_license_check' ) );

        add_action( 'wp_ajax_cdw_plugin_base_cancel_license', array( $this, 'handle_ajax_cancel_license' ) );
        add_filter( 'admin_footer_text', array( $this, 'custom_admin_footer_text' ) ,999);

        add_filter( 'pre_set_site_transient_update_plugins', array( $this->license_manager, 'check_for_plugin_updates' ) );
        
    }

    public function activate() {
        if ( ! wp_next_scheduled( 'cdw_plugin_base_daily_license_check' ) ) {
            wp_schedule_event( time(), 'daily', 'cdw_plugin_base_daily_license_check' );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'cdw_plugin_base_daily_license_check' );
    }

    public function handle_ajax_cancel_license() {
        check_ajax_referer( 'cdw_plugin_base_cancel_license_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Bạn không có đủ quyền hạn.', 'cdw-plugin-base' ) ) );
        }

        $this->license_manager->clear_local_license_data();

        wp_send_json_success( array(
            'message' => __( 'Giấy phép đã được hủy thành công.', 'cdw-plugin-base' ),
        ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Plugin Cơ Sở CDW', 'cdw-plugin-base' ),
            __( 'Plugin Cơ Sở CDW', 'cdw-plugin-base' ),
            'manage_options',
            'cdw-plugin-base',
            array( $this, 'main_page_content' ),
            'dashicons-admin-generic',
            6
        );

        add_submenu_page(
            'cdw-plugin-base',
            __( 'Giấy phép', 'cdw-plugin-base' ),
            __( 'Giấy phép', 'cdw-plugin-base' ),
            'manage_options',
            'cdw-plugin-base-license',
            array( $this, 'license_page_content' )
        );
    }

    public function main_page_content() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php if ( ! $this->license_manager->is_license_active() ) : ?>
                <div class="notice notice-error">
                    <p><strong><?php esc_html_e( 'Plugin Cơ Sở CDW:', 'cdw-plugin-base' ); ?></strong> <?php esc_html_e( 'Plugin hiện đang không hoạt động. Vui lòng kích hoạt giấy phép của bạn để sử dụng đầy đủ các tính năng.', 'cdw-plugin-base' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=cdw-plugin-base-license' ) ); ?>"><?php esc_html_e( 'Đi đến trang Giấy phép', 'cdw-plugin-base' ); ?></a></p>
                </div>
            <?php else : ?>
                <p><?php esc_html_e( 'Chào mừng đến với Plugin Cơ Sở CDW!', 'cdw-plugin-base' ); ?></p>
                <!-- Thêm nội dung chính của plugin tại đây khi giấy phép hoạt động -->
                <p><?php esc_html_e( 'Đây là nội dung chính của plugin khi giấy phép đang hoạt động.', 'cdw-plugin-base' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function license_page_content() {
        do_action('cdw_plugin_base_daily_license_check');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php settings_errors('cdw-plugin-base-license-notices'); ?>

            <?php if ( ! $this->license_manager->is_license_active() ) : ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cdw_plugin_base_license_group' );
                do_settings_sections( 'cdw-plugin-base-license' );
                submit_button( __( 'Lưu & Kích hoạt Giấy phép', 'cdw-plugin-base' ) );
                ?>
            </form>
            <?php else : ?>
            <?php $this->license_manager->display_license_status(); ?>
            <hr>
            <h2><?php esc_html_e( 'Hủy Giấy phép', 'cdw-plugin-base' ); ?></h2>
            <p><?php esc_html_e( 'Nhấp vào nút bên dưới để xóa giấy phép hiện tại và nhập một giấy phép mới.', 'cdw-plugin-base' ); ?></p>
            <button type="button" class="button button-delete" id="cdw-plugin-base-cancel-button"><?php esc_html_e( 'Hủy Giấy phép', 'cdw-plugin-base' ); ?></button>
            <?php endif; ?>
        </div>
        <?php
    }

    public function check_license_status_for_notice() {
        if ( ! $this->license_manager->is_license_active() ) {
            add_action( 'admin_notices', array( $this, 'license_not_connected_notice' ) );
        }
    }

    public function license_not_connected_notice() {
        $last_error = get_option( 'cdw_plugin_base_last_error', '' );
        $message = sprintf( __( 'Giấy phép của bạn chưa được kết nối. Vui lòng truy cập <a href="%s">trang Giấy phép</a> để kích hoạt plugin.', 'cdw-plugin-base' ), esc_url( admin_url( 'admin.php?page=cdw-plugin-base-license' ) ) );

        if ( ! empty( $last_error ) ) {
            $message .= '<br><strong>' . esc_html__( 'Lỗi cuối cùng:', 'cdw-plugin-base' ) . '</strong> <span style="color: red;">' . esc_html( $last_error ) . '</span>';
        }
        ?>
        <div class="notice notice-warning is-dismissible cdw-plugin-base-admin-notice">
            <p><strong><?php esc_html_e( 'Plugin Cơ Sở CDW:', 'cdw-plugin-base' ); ?></strong> <?php echo $message; ?></p>
        </div>
        <?php
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'cdw-plugin-base-admin-style',
            CDW_PLUGIN_BASE_URL . 'assets/css/cdw-plugin-base-admin.css',
            array(),
            CDW_PLUGIN_BASE_VERSION
        );

        wp_enqueue_script(
            'cdw-plugin-base-admin-script',
            CDW_PLUGIN_BASE_URL . 'assets/js/cdw-plugin-base-admin.js',
            array( 'jquery' ),
            CDW_PLUGIN_BASE_VERSION,
            true
        );

        wp_enqueue_script(
            'cdw-plugin-base-ajax-script',
            CDW_PLUGIN_BASE_URL . 'assets/js/cdw-plugin-base-ajax.js',
            array( 'jquery' ),
            CDW_PLUGIN_BASE_VERSION,
            true
        );

        wp_localize_script(
            'cdw-plugin-base-ajax-script',
            'cdw_plugin_base_ajax',
            array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'cancel_nonce' => wp_create_nonce( 'cdw_plugin_base_cancel_license_nonce' ),
            )
        );
    }

    public function custom_admin_footer_text( $text ) {
        $screen = get_current_screen();
        if ( strpos( $screen->id, 'cdw-plugin-base' ) !== false ) {
            return ' Cảm ơn bạn đã sử dụng <a href="https://congdongweb.com/" target="_blank">CDW Plugin Base</a>. Được phát triển bởi <a href="https://congdongweb.com/" target="_blank">CongDongWeb</a>.';
        }
        return $text;
    }
}

new CDW_Plugin_Base();
