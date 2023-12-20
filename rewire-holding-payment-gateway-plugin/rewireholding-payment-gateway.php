<?php

/**
 * Plugin Name: Rewire Holding Payment Gateway
 * Plugin URI: https://rewireholding.com/
 * Description: Rewire Holding WooCommerce Payment Gateway
 * Version: 1.0
 * Author: Rafael Moreno
 */

if (!defined('ABSPATH')) {
    exit; 
}

function init_rewire_holding_payment_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_RewireHolding_Payment_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id                 = 'rewireholding_payment_gateway';
            $this->icon               = apply_filters('woocommerce_payment_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('Rewire Holding Payment Gateway', 'woocommerce');
            $this->method_description = __('Allows card payments through the Rewire Holding Payment Gateway, with Visa & Mastercard, Debid and Prepaid Cards', 'text-domain');

            $this->init_form_fields();

            $this->init_settings();
            $this->description = $this->get_option('description');
            $this->title              = $this->get_option('title');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        }

        public function get_option($key, $empty_value = null)
        {
            if (empty($this->settings[$key])) {
                return $empty_value;
            }
            return $this->settings[$key];
        }
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $response = $this->api_call($order);

            if (is_wp_error($response)) {
                return array(
                    'result'   => 'failure',
                    'message' => ''
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);


            if (!empty($data->url)) {
                $order->update_meta_data('payment_api_id', $data->guid);
                $order->save();
                return array(
                    'result'   => 'success',
                    'redirect' => $data->url
                );
            } else {
                return array(
                    'result'   => 'failure',
                    'message' => 'Error getting redirection URL.'
                );
            }
        }

        private function api_call($order)
        {
            $username = $this->get_option('api_key');
            $password = $this->get_option('api_secret');
            $domain = $this->get_option('domain');
            $api_url = 'https://api.saurus.com/data/api/v1.0/merchant/topup/paymentgateway/api/draft';
            $headers = array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json'
            );

            $api_args = array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode(array(
                    'asset_order_id' => $order->get_id(),
                    'amount'   => $order->get_total(),
                    'callback_url' => $domain.'/?verificar_pago=1&order=' . $order->get_id()
                )),
                'timeout' => 45,
            );

            $response = wp_remote_post($api_url, $api_args);
            return $response;
        }
        public function admin_options()
        {
            echo '<h2>' . esc_html($this->method_title) . '</h2>';
            echo wp_kses_post(wpautop($this->method_description));
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Rewire Holding Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'text-domain'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'text-domain'),
                    'default' => __('Rewire Holding Payment Gateway', 'text-domain'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title'       => __('Descripción', 'text-domain'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description the user sees during checkout.', 'text-domain'),
                    'default'     => __('Payment via Rewire Holding Payment Gateway.', 'text-domain'),
                    'desc_tip'    => true,
                ),
                'api_key' => array(
                    'title'       => 'Key API',
                    'type'        => 'text',
                    'description' => 'Enter your API key provided by the payment service.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'api_secret' => array(
                    'title'       => 'Secret API',
                    'type'        => 'password',
                    'description' => 'Enter your API secret provided by the payment service.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'domain' => array(
                    'title'       => 'Domain URL',
                    'type'        => 'text',
                    'description' => 'Enter the domain name of your website.',
                    'default'     => 'http://example.com',
                    'desc_tip'    => true,
                ),
                // Puedes agregar más campos aquí según sea necesario
            );
        }
    }

    function add_rewire_holding_payment_gateway($methods)
    {
        $methods[] = 'WC_RewireHolding_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_rewire_holding_payment_gateway');
}

add_action('plugins_loaded', 'init_rewire_holding_payment_gateway');

function verificar_pago()
{
    if (!isset($_GET['verificar_pago'])) {
        return;
    }

    $order_id = $_GET['order']; 
    $order = wc_get_order($order_id);
    $payment_id =

        $api_response = verificar_estado_pago_api($order->get_meta('payment_api_id'));

    if (is_wp_error($api_response)) {
        error_log("error");
        $order->update_status('failed', 'Error in payment API.');
        wc_add_notice(__('The payment was not successful, please contact with your bank', 'text-domain'), 'error');
        wp_redirect(wc_get_checkout_url());

        exit;
    }

    $response_code = wp_remote_retrieve_response_code($api_response);



    if ($response_code == 200) {
        $order->payment_complete();
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    } else {
        error_log("error");

        $order->update_status('failed', 'The payment was not successful');
        wc_add_notice(__('The payment was not successful, please contact with your bank', 'text-domain'), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }

    exit;
}
function verificar_estado_pago_api($tx_id)
{

    $api_url = 'https://api.saurus.com/data/api/v1.0/merchant/topup/paymentgateway/api/polling/' . $tx_id;
    error_log($api_url);
    $headers = array(
        'Content-Type' => 'application/json'
    );

    $api_args = array(
        'method' => 'GET',
        'headers' => $headers,
        'timeout' => 45,
    );

    $response = wp_remote_post($api_url, $api_args);
    return $response;
}


add_action('init', 'verificar_pago');
