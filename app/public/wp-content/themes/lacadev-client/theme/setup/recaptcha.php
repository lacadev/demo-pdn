<?php
/**
 * Google reCAPTCHA v3 Integration
 *
 * Handles invisible reCAPTCHA for Login, Register, and Comments.
 *
 * @package LaCaDev
 */

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Recaptcha {

    private $site_key;
    private $secret_key;
    private $score_threshold;

    public function __construct() {
        $this->site_key = getOption('recaptcha_site_key');
        $this->secret_key = getOption('recaptcha_secret_key');
        $this->score_threshold = (float) getOption('recaptcha_score') ?: 0.5;

        // Skip if keys are missing
        if (empty($this->site_key) || empty($this->secret_key)) {
            return;
        }

        // Frontend Scripts
        add_action('login_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Login Integration
        if (getOption('enable_recaptcha_login')) {
            add_action('login_form', [$this, 'print_hidden_field']);
            add_filter('authenticate', [$this, 'verify_login'], 30, 3);
        }

        // Register Integration
        if (getOption('enable_recaptcha_register')) {
            add_action('register_form', [$this, 'print_hidden_field']);
            add_filter('registration_errors', [$this, 'verify_registration'], 10, 3);
        }

        // Comment Integration
        if (getOption('enable_recaptcha_comment')) {
            add_action('comment_form', [$this, 'print_hidden_field']);
            add_filter('preprocess_comment', [$this, 'verify_comment']);
        }
    }

    /**
     * Enqueue Google reCAPTCHA Script
     */
    public function enqueue_scripts() {
        wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $this->site_key, [], null, false);
        
        $script = "
            document.addEventListener('DOMContentLoaded', function() {
                var forms = document.querySelectorAll('#loginform, #registerform, #commentform');
                
                grecaptcha.ready(function() {
                    grecaptcha.execute('" . $this->site_key . "', {action: 'submit'}).then(function(token) {
                        var inputs = document.querySelectorAll('.laca-recaptcha-response');
                        inputs.forEach(function(input) {
                            input.value = token;
                        });
                    });
                });
                
                // Refresh token every 90s to avoid expiration
                setInterval(function(){
                    grecaptcha.ready(function() {
                        grecaptcha.execute('" . $this->site_key . "', {action: 'submit'}).then(function(token) {
                            var inputs = document.querySelectorAll('.laca-recaptcha-response');
                            inputs.forEach(function(input) {
                                input.value = token;
                            });
                        });
                    });
                }, 90000);
            });
        ";

        wp_add_inline_script('google-recaptcha', $script);
        
        // Ensure standard WP handles the nonce for this script via a filter if needed, 
        // but since we are in a custom theme setup, we can also manually add it to the tag.
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'google-recaptcha' && defined('LACA_CSP_NONCE')) {
                return str_replace('<script ', '<script nonce="' . LACA_CSP_NONCE . '" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Print hidden input field
     */
    public function print_hidden_field() {
        echo '<input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">';
    }

    /**
     * Verify Token Logic
     */
    private function verify_token($token) {
        if (empty($token)) {
            return new WP_Error('recaptcha_error', __('Vui lòng tải lại trang để xác thực reCAPTCHA.', 'laca'));
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $this->secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_error', __('Lỗi kết nối đến máy chủ xác thực.', 'laca'));
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (empty($result['success']) || !$result['success']) {
            return new WP_Error('recaptcha_error', __('Xác thực reCAPTCHA thất bại. Bạn có phải là robot?', 'laca'));
        }

        if ($result['score'] < $this->score_threshold) {
             return new WP_Error('recaptcha_low_score', __('Hệ thống nghi ngờ bạn là robot. Điểm tín nhiệm thấp.', 'laca'));
        }

        return true;
    }

    /**
     * Validate Login
     */
    public function verify_login($user, $username, $password) {
        if (isset($_POST['laca_recaptcha_response'])) {
            $verify = $this->verify_token($_POST['laca_recaptcha_response']);
            if (is_wp_error($verify)) {
                return $verify;
            }
        }
        return $user;
    }

    /**
     * Validate Registration
     */
    public function verify_registration($errors, $sanitized_user_login, $user_email) {
        if (isset($_POST['laca_recaptcha_response'])) {
            $verify = $this->verify_token($_POST['laca_recaptcha_response']);
            if (is_wp_error($verify)) {
                $errors->add('recaptcha_error', $verify->get_error_message());
            }
        }
        return $errors;
    }

    /**
     * Validate Comment
     */
    public function verify_comment($commentdata) {
        if (!is_user_logged_in() && isset($_POST['laca_recaptcha_response'])) {
            $verify = $this->verify_token($_POST['laca_recaptcha_response']);
            if (is_wp_error($verify)) {
                wp_die($verify->get_error_message());
            }
        }
        return $commentdata;
    }
}



add_action('init', function() {
    new Laca_Recaptcha();
});
