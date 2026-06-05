<?php
/**
 * Plugin Name: Appsmith Secure Embed
 * Description: Securely embed Appsmith apps inside WordPress using signed short-lived tokens and per-user Appsmith URLs.
 * Version: 1.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();

    if (in_array('b2b_customer', (array) $user->roles, true)) {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_redirect(site_url('/customer-reporting'));
            exit;
        }
    }
});

add_filter('show_admin_bar', function ($show) {
    if (!is_user_logged_in()) {
        return $show;
    }

    $user = wp_get_current_user();

    if (in_array('b2b_customer', (array) $user->roles, true)) {
        return false;
    }

    return $show;
});


class Appsmith_Secure_Embed {

    private const TOKEN_TTL = 18000; // 1 hour

    public function __construct() {
        add_shortcode('appsmith_embed', [$this, 'render_appsmith_embed']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // User role + meta UI
        add_action('show_user_profile', [$this, 'render_appsmith_url_field']);
        add_action('edit_user_profile', [$this, 'render_appsmith_url_field']);
        add_action('user_new_form', [$this, 'render_appsmith_url_field_new']);

        add_action('personal_options_update', [$this, 'save_appsmith_url_field']);
        add_action('edit_user_profile_update', [$this, 'save_appsmith_url_field']);
        add_action('user_register', [$this, 'save_new_user_appsmith_url']);

        // Login redirect for B2B customers
        add_filter('login_redirect', [$this, 'b2b_login_redirect'], 10, 3);
    }

    /**
     * Activation: create B2B Customer role
     */
    public static function activate() {
        add_role(
            'b2b_customer',
            'B2B Customer',
            ['read' => true]
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('appsmith/v1', '/verify', [
            'methods'  => 'POST',
            'callback' => [$this, 'verify_token_endpoint'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Render field when creating NEW user
     */
    public function render_appsmith_url_field_new($operation) {

        // Only show for adding users
        if ($operation !== 'add-new-user') {
            return;
        }

        ?>
        <h3>Appsmith</h3>

        <table class="form-table">
            <tr>
                <th>
                    <label for="appsmith_url">Appsmith Embed SRC URL</label>
                </th>
                <td>
                    <input
                        type="url"
                        name="appsmith_url"
                        id="appsmith_url"
                        class="regular-text"
                        placeholder="https://appsmith.example.com/embed/..."
                    />

                    <p class="description">
                        Assign the Appsmith application URL for this B2B customer.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Appsmith URL when NEW user is created
     */
    public function save_new_user_appsmith_url($user_id) {

        if (isset($_POST['appsmith_url'])) {

            update_user_meta(
                $user_id,
                'appsmith_url',
                esc_url_raw($_POST['appsmith_url'])
            );
        }
    }

    /**
     * Generate signed token
     */
    private function generate_token() {
        if (!is_user_logged_in() || !defined('APPSMITH_SHARED_SECRET')) {
            return null;
        }

        $user = wp_get_current_user();

        $payload = [
            'user_id' => $user->ID,
            'email'   => $user->user_email,
            'name'    => $user->display_name,
            'iss'     => get_site_url(),
            'exp'     => time() + self::TOKEN_TTL,
        ];

        $payload_b64 = base64_encode(wp_json_encode($payload));
        $signature   = hash_hmac('sha256', $payload_b64, APPSMITH_SHARED_SECRET);

        return $payload_b64 . '.' . $signature;
    }

    /**
     * REST endpoint: verify token
     */
    public function verify_token_endpoint($request) {
        $token = $request->get_param('token');

        if (!$token || !defined('APPSMITH_SHARED_SECRET')) {
            return new WP_Error('unauthorized', 'Invalid token', ['status' => 401]);
        }

        try {
            [$payloadB64, $signature] = explode('.', $token, 2);

            $expected = hash_hmac('sha256', $payloadB64, APPSMITH_SHARED_SECRET);

            if (!hash_equals($expected, $signature)) {
                return new WP_Error('unauthorized', 'Invalid signature', ['status' => 401]);
            }

            $payload = json_decode(base64_decode($payloadB64), true);

            if (!$payload || $payload['exp'] < time()) {
                return new WP_Error('unauthorized', 'Token expired', ['status' => 401]);
            }

            return [
                'valid' => true,
                'user'  => $payload,
            ];
        } catch (Throwable $e) {
            return new WP_Error('unauthorized', 'Token error', ['status' => 401]);
        }
    }

    /**
     * User profile field: Appsmith URL
     */
    public function render_appsmith_url_field($user) {
        if (!in_array('b2b_customer', (array) $user->roles, true)) {
            return;
        }
        ?>
        <h3>Appsmith</h3>
        <table class="form-table">
            <tr>
                <th><label for="appsmith_url">Appsmith URL</label></th>
                <td>
                    <input
                        type="url"
                        name="appsmith_url"
                        id="appsmith_url"
                        value="<?php echo esc_attr(get_user_meta($user->ID, 'appsmith_url', true)); ?>"
                        class="regular-text"
                    />
                    <p class="description">
                        Paste the Appsmith <strong>embed URL</strong> for this customer.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Appsmith URL
     */
    public function save_appsmith_url_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['appsmith_url'])) {
            update_user_meta(
                $user_id,
                'appsmith_url',
                esc_url_raw($_POST['appsmith_url'])
            );
        }
    }

    /**
     * Login redirect for B2B customers
     */
    public function b2b_login_redirect($redirect_to, $requested, $user) {
        if ($user instanceof WP_User) {
            if (in_array('b2b_customer', (array) $user->roles, true)) {
                return '/customer-reporting';
            }
        }
        return $redirect_to;
    }

    /**
     * Shortcode renderer
     *
     * Usage:
     * [appsmith_embed height="900"]
     */
    public function render_appsmith_embed($atts) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url('/customer-reporting'));
            exit;
        }

        $user = wp_get_current_user();

        if (!in_array('b2b_customer', (array) $user->roles, true)) {
            return '<p>You do not have access to this application.</p>';
        }

        $appsmith_url = get_user_meta($user->ID, 'appsmith_url', true);

        if (!$appsmith_url) {
            return '<p>No Appsmith application assigned to your account.</p>';
        }

        $atts = shortcode_atts([
            'height' => '900',
        ], $atts);

        $token = $this->generate_token();

        if (!$token) {
            return '<p>Authentication error.</p>';
        }

        $src = esc_url(add_query_arg('token', $token, $appsmith_url));

        return sprintf(
            '<iframe src="%s" style="width:100%%; height:%s; border:none;"></iframe>',
            $src,
            // intval($atts['height'])
            esc_attr($atts['height'])
        );
    }
}

// Activation hook
register_activation_hook(__FILE__, ['Appsmith_Secure_Embed', 'activate']);

new Appsmith_Secure_Embed();