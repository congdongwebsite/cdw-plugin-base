<?php

if (!defined('ABSPATH')) {
    exit;
}

class CDW_License_Manager
{
    private $license_key_option_name = 'cdw_plugin_base_license_key';
    private $license_status_option_name = 'cdw_plugin_base_license_status';
    private $license_data_option_name = 'cdw_plugin_base_license_data';
    private $last_error_option_name = 'cdw_plugin_base_last_error';

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_cdw_plugin_base_deactivate_license', array($this, 'handle_license_deactivation'));
        add_action('update_option_' . $this->license_key_option_name, array($this, 'after_license_update'), 10, 2);
        add_filter('plugins_api', array($this, 'cdw_plugins_api_callback'), 10, 3);
    }

    public function after_license_update($old_value, $value)
    {
        static $has_run = false;
        if (! $has_run && $old_value !== $value) {
            $has_run = true;
            $this->activate_license_api($value);
        }
    }

    public function activate_license_api($license_key)
    {
        if (empty($license_key)) {
            $this->clear_local_license_data();
            add_settings_error('cdw-plugin-base-license-notices', 'license_deactivated', __('Khóa giấy phép đã được xóa. Plugin đã bị vô hiệu hóa.', 'cdw-plugin-base'), 'updated');
            return;
        }

        $validation_result = $this->validate_license($license_key);

        if (is_wp_error($validation_result)) {
            $this->clear_local_license_data($validation_result->get_error_message());
            add_settings_error('cdw-plugin-base-license-notices', 'license_error', $validation_result->get_error_message(), 'error');
            return;
        }

        if ($validation_result === true) {
            $connection_result = $this->connect_plugin($license_key);

            if (is_wp_error($connection_result)) {
                $this->clear_local_license_data($connection_result->get_error_message());
                add_settings_error('cdw-plugin-base-license-notices', 'plugin_connect_error', $connection_result->get_error_message(), 'error');
            } else {
                update_option($this->license_status_option_name, 'active');
                update_option($this->license_data_option_name, $connection_result);
                update_option($this->last_error_option_name, '');
                add_settings_error('cdw-plugin-base-license-notices', 'license_activated', __('Giấy phép đã được kích hoạt và plugin đã kết nối thành công.', 'cdw-plugin-base'), 'updated');
            }
        } else {
            $this->clear_local_license_data(__('Khóa giấy phép không hợp lệ.', 'cdw-plugin-base'));
            add_settings_error('cdw-plugin-base-license-notices', 'license_invalid', __('Khóa giấy phép không hợp lệ. Vui lòng thử lại.', 'cdw-plugin-base'), 'error');
        }
    }

    public function register_settings()
    {
        register_setting(
            'cdw_plugin_base_license_group',
            $this->license_key_option_name,
            array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_license_key'),
                'default'           => '',
            )
        );

        add_settings_section(
            'cdw_plugin_base_license_section',
            __('Kích hoạt Giấy phép', 'cdw-plugin-base'),
            array($this, 'license_section_callback'),
            'cdw-plugin-base-license'
        );

        add_settings_field(
            'cdw_plugin_base_license_key_field',
            __('Khóa Giấy phép', 'cdw-plugin-base'),
            array($this, 'license_key_field_callback'),
            'cdw-plugin-base-license',
            'cdw_plugin_base_license_section'
        );
    }

    public function sanitize_license_key($new_license_key)
    {
        $new_license_key = sanitize_text_field($new_license_key);

        if (empty($new_license_key)) {
            $this->clear_local_license_data();
            add_settings_error('cdw-plugin-base-license-notices', 'license_deactivated', __('Khóa giấy phép đã được xóa. Plugin đã bị vô hiệu hóa.', 'cdw-plugin-base'), 'updated');
            return '';
        }

        $validation_result = $this->validate_license($new_license_key);

        if (is_wp_error($validation_result)) {
            $this->clear_local_license_data($validation_result->get_error_message());
            add_settings_error('cdw-plugin-base-license-notices', 'license_error', $validation_result->get_error_message(), 'error');
            return '';
        }

        if ($validation_result === true) {
            $connection_result = $this->connect_plugin($new_license_key);
            if (is_wp_error($connection_result)) {
                $this->clear_local_license_data($connection_result->get_error_message());
                add_settings_error('cdw-plugin-base-license-notices', 'plugin_connect_error', $connection_result->get_error_message(), 'error');
                return '';
            } else {
                update_option($this->license_status_option_name, 'active');
                update_option($this->license_data_option_name, $connection_result);
                update_option($this->last_error_option_name, '');
                add_settings_error('cdw-plugin-base-license-notices', 'license_activated', __('Giấy phép đã được kích hoạt và plugin đã kết nối thành công.', 'cdw-plugin-base'), 'updated');
                return $new_license_key;
            }
        }

        $this->clear_local_license_data(__('Khóa giấy phép không hợp lệ.', 'cdw-plugin-base'));
        add_settings_error('cdw-plugin-base-license-notices', 'license_invalid', __('Khóa giấy phép không hợp lệ. Vui lòng thử lại.', 'cdw-plugin-base'), 'error');
        return '';
    }

    public function license_section_callback()
    {
        echo '<p>' . esc_html__('Nhập khóa giấy phép của bạn vào đây để kích hoạt plugin.', 'cdw-plugin-base') . '</p>';
    }

    public function license_key_field_callback()
    {
        $license_key = get_option($this->license_key_option_name);
?>
        <input type="password" name="<?php echo esc_attr($this->license_key_option_name); ?>" value="<?php echo esc_attr($this->mask_license($license_key)); ?>" class="regular-text" />
<?php
    }

    public function is_license_active()
    {
        return get_option($this->license_status_option_name) === 'active';
    }

    public function display_license_status()
    {
        $license_data = get_option($this->license_data_option_name, array());

        $current_license_status = get_option($this->license_status_option_name, 'inactive');
        $status_text = '';
        $status_class = 'cdw-license-status-inactive'; // Default to inactive

        switch ($current_license_status) {
            case 'active':
                $status_text = esc_html__('Hoạt động', 'cdw-plugin-base');
                $status_class = 'cdw-license-status-active';
                break;
            case 'inactive':
                $status_text = esc_html__('Inactive', 'cdw-plugin-base');
                $status_class = 'cdw-license-status-inactive';
                break;
            case 'expired':
                $status_text = esc_html__('Expired', 'cdw-plugin-base');
                $status_class = 'cdw-license-status-expired';
                break;
            default:
                $status_text = esc_html($current_license_status);
                break;
        }

        echo '<p class="cdw-license-status-wrapper"><strong>' . esc_html__('Trạng thái Giấy phép:', 'cdw-plugin-base') . '</strong> <span class="cdw-license-status-value ' . esc_attr($status_class) . '">' . $status_text . '</span></p>';
        if (!empty($license_data)) {
            echo '<h3>' . esc_html__('Chi tiết Giấy phép:', 'cdw-plugin-base') . '</h3>';
            echo '<table class="form-table cdw-license-details-table">';

            $display_fields = array(
                'title'       => __('Tên gói', 'cdw-plugin-base'),
                'key'         => __('Mã kích hoạt', 'cdw-plugin-base'),
                'plugin_name' => __('Tên Plugin', 'cdw-plugin-base'),
                'type'        => __('Loại giấy phép', 'cdw-plugin-base'),
                'starts_at'   => __('Ngày bắt đầu', 'cdw-plugin-base'),
                'expires_at'  => __('Ngày hết hạn', 'cdw-plugin-base'),
                'status'      => __('Trạng thái', 'cdw-plugin-base'),
                'version'     => __('Phiên bản', 'cdw-plugin-base'),
            );

            foreach ($display_fields as $key => $label) {
                if (isset($license_data[$key])) {
                    $value = $license_data[$key];
                    if ('status' === $key) {
                        switch ($value) {
                            case 'active':
                                $value = __('Hoạt động', 'cdw-plugin-base');
                                break;
                            case 'inactive':
                                $value = __('Không hoạt động', 'cdw-plugin-base');
                                break;
                            case 'expired':
                                $value = __('Hết hạn', 'cdw-plugin-base');
                                break;
                            default:
                                $value = esc_html($value);
                                break;
                        }
                    } elseif ('key' === $key) {
                        $value = $this->mask_license($value);
                    } else {
                        $value = esc_html($value);
                    }
                    echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $value . '</td></tr>';
                }
            }
            echo '</table>';
        }
    }

    private function validate_license($license_key)
    {
        if (empty($license_key)) {
            return new WP_Error('license_key_empty', __('License Key không được để trống.', 'cdw-plugin-base'));
        }

        $api_url = trailingslashit(CDW_LICENSE_SERVER_URL) . 'license/verify';
        $response = wp_remote_post($api_url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'body'      => json_encode(array('license_key' => $license_key, 'plugin_id' => CDW_PLUGIN_BASE_ID)),
            'headers'   => array('Content-Type' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('license_verify_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['status'])) {
            return new WP_Error('license_verify_invalid_response', __($data['message'] ?? 'Phản hồi không hợp lệ từ máy chủ giấy phép.', 'cdw-plugin-base'));
        }

        if ($data['status'] === 'valid') {
            return true;
        } else {
            return new WP_Error('license_verify_failed', isset($data['message']) ? $data['message'] : __('Xác minh giấy phép thất bại.', 'cdw-plugin-base'));
        }
    }

    private function connect_plugin($license_key)
    {
        $api_url = trailingslashit(CDW_LICENSE_SERVER_URL) . 'plugin/connect';
        $response = wp_remote_post($api_url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'body'      => json_encode(array(
                'license_key' => $license_key,
                'site_url'    => home_url(),
                'plugin_name' => 'CDW Plugin Base',
                'version'     => CDW_PLUGIN_BASE_VERSION,
                'plugin_id'   => CDW_PLUGIN_BASE_ID,
            )),
            'headers'   => array('Content-Type' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('plugin_connect_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['status'])) {
            return new WP_Error('plugin_connect_invalid_response', __('Phản hồi không hợp lệ từ máy chủ giấy phép trong quá trình kết nối.', 'cdw-plugin-base'));
        }
        if ($data['status'] === 'connected' && isset($data['license_info'])) {
            $license_info = $data['license_info'];
            return $license_info;
        } else {
            return new WP_Error('plugin_connect_failed', isset($data['message']) ? $data['message'] : __('Kết nối plugin thất bại.', 'cdw-plugin-base'));
        }
    }

    public function perform_remote_license_check()
    {
        $license_key = get_option($this->license_key_option_name);

        if (empty($license_key)) {
            if ($this->is_license_active()) {
                $this->clear_local_license_data();
            }
            return;
        }

        $validation_result = $this->validate_license($license_key);

        if ($validation_result !== true) {
            $error_message = is_wp_error($validation_result) ? $validation_result->get_error_message() : __('Giấy phép không còn hiệu lực.', 'cdw-plugin-base');
            $this->clear_local_license_data($error_message);
        } else {
            $connection_result = $this->connect_plugin($license_key);
            if (is_wp_error($connection_result)) {
                update_option($this->last_error_option_name, $connection_result->get_error_message());
            } else {
                update_option($this->license_data_option_name, $connection_result);
                update_option($this->last_error_option_name, '');
            }
        }
    }

    public function check_for_plugin_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $license_key = get_option($this->license_key_option_name);

        if (empty($license_key)) {
            return new WP_Error('license_key_empty', __('License Key không được để trống.', 'cdw-plugin-base'));
        }

        $plugin_slug = basename(CDW_PLUGIN_BASE_DIR);

        $api_url = trailingslashit(CDW_LICENSE_SERVER_URL) . 'plugin/update-check';
        $response = wp_remote_post($api_url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'body'      => json_encode(array(
                'slug'    => $plugin_slug,
                'license_key' => $license_key,
                'plugin_id' => CDW_PLUGIN_BASE_ID,
                'version' => CDW_PLUGIN_BASE_VERSION,
            )),
            'headers'   => array('Content-Type' => 'application/json'),
        ));

        if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $api_response = json_decode($body, true);

            if (! empty($api_response) && isset($api_response['status']) && $api_response['status'] === 'update_available') {
                $new_version = $api_response['new_version'];
                $download_url = $api_response['download_url'];

                $update = new stdClass();
                $update->id = 0;
                $update->slug = $plugin_slug;
                $update->plugin = plugin_basename(CDW_PLUGIN_BASE_FILE);
                $update->new_version = $new_version;
                $update->package = $download_url;
                $update->upgrade_notice = sprintf(__('A new version (%s) of CDW Plugin Base is available. Please update to get the latest features and bug fixes.', 'cdw-plugin-base'), $new_version);

                $transient->response[$update->plugin] = $update;
            }
        }
        return $transient;
    }

    public function cdw_plugins_api_callback($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== basename(CDW_PLUGIN_BASE_DIR)) {
            return $result;
        }

        $license_key = get_option($this->license_key_option_name);

        if (empty($license_key)) {
            return $result;
        }

        $api_url = trailingslashit(CDW_LICENSE_SERVER_URL) . 'plugin/info';
        $response = wp_remote_post($api_url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'body'      => json_encode(array(
                'license_key' => $license_key,
                'plugin_id' => CDW_PLUGIN_BASE_ID,
                'slug'      => $args->slug,
            )),
            'headers'   => array('Content-Type' => 'application/json'),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $result;
        }

        $body = wp_remote_retrieve_body($response);
        $plugin_info = json_decode($body, true);

        if (empty($plugin_info) || !isset($plugin_info['name'])) {
            return $result;
        }

        $obj = new stdClass();
        $obj->name = $plugin_info['name'];
        $obj->slug = $plugin_info['slug'];
        $obj->version = $plugin_info['version'];
        $obj->author = isset($plugin_info['author']) ? $plugin_info['author'] : 'N/A';
        $obj->requires = isset($plugin_info['requires']) ? $plugin_info['requires'] : '';
        $obj->tested = isset($plugin_info['tested']) ? $plugin_info['tested'] : '';
        $obj->last_updated = isset($plugin_info['last_updated']) ? $plugin_info['last_updated'] : '';
        $obj->homepage = isset($plugin_info['homepage']) ? $plugin_info['homepage'] : '';
        $obj->download_link = isset($plugin_info['download_link']) ? $plugin_info['download_link'] : '';
        $obj->sections = isset($plugin_info['sections']) ? $plugin_info['sections'] : array();
        $obj->banners = isset($plugin_info['banners']) ? $plugin_info['banners'] : array();
        $obj->external = true;

        return $obj;
    }

    public function clear_local_license_data($error_message = '')
    {
        delete_option($this->license_key_option_name);
        delete_option($this->license_status_option_name);
        delete_option($this->license_data_option_name);
        update_option($this->last_error_option_name, sanitize_text_field($error_message));
    }

    public function mask_license($license)
    {
        $length = strlen($license);

        if ($length >= 7) {
            $firstThree = substr($license, 0, 3);
            $lastFour = substr($license, -4);
            $middleLength = $length - 7;
            $mask = str_repeat('x', $middleLength);
            return $firstThree . $mask . $lastFour;
        }
        return $license;
    }
}
