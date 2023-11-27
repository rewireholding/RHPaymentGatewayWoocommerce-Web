<?php

/**
 * Plugin Name: Mi Método de Pago
 * Plugin URI: http://mitienda.com
 * Description: Un método de pago personalizado para WooCommerce.
 * Version: 1.0
 * Author: Tu Nombre
 * Author URI: http://tusitio.com
 */

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente.
}

function init_mi_metodo_pago()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Mi_Metodo_Pago extends WC_Payment_Gateway
    {
        public function __construct()
        {
            // Configuraciones iniciales del método de pago aquí...
            $this->id                 = 'mi_pasarela_de_pagos';
            $this->icon               = apply_filters('woocommerce_mi_pasarela_de_pagos_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('Rewire Holding Payment Gateway', 'woocommerce');
            $this->method_description = __('Allows card payments through the Rewire Holding Payment Gateway, with Visa & Mastercard, Debid and Prepaid Cards', 'text-domain');

            $this->init_form_fields();

            $this->init_settings();
            $this->description = $this->get_option('description');
            $this->title              = $this->get_option('title');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Cargar configuraciones, acciones, etc.
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

            // Preparar la URL de redirección con los parámetros necesarios
            $response = $this->api_call($order);

            if (is_wp_error($response)) {
                return array(
                    'result'   => 'failure',
                    'message' => 'hola'
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);


            if (!empty($data->url)) {
		$order->update_meta_data('payment_api_id',$data->guid);
		$order->save();
                // Redirigir a la URL proporcionada por la API
                return array(
                    'result'   => 'success',
                    'redirect' => $data->url
                );
            } else {
                // Manejar casos donde no se recibe una URL
                return array(
                    'result'   => 'failure',
                    'message' => 'Error al obtener la URL de redirección.'
                );
            }
        }

        private function api_call($order)
        {
            // Configura los parámetros de la API aquí
            $username = $this->get_option('api_key');
            $password = $this->get_option('api_secret');
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
                    'callback_url' => 'https://britscope.com/?verificar_pago=1&order=' . $order->get_id()
                    // Otros datos necesarios para tu API de pago
                )),
                'timeout' => 45,
            );

            // Realiza la llamada a la API
            $response = wp_remote_post($api_url, $api_args);
            return $response;
        }
        // Otros métodos necesarios aquí...
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
                    'title'       => 'Habilitar/Deshabilitar',
                    'label'       => 'Habilitar Mi Método de Pago',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'text-domain'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'text-domain'),
                    'default' => __('Mi Método de Pago', 'text-domain'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title'       => __('Descripción', 'text-domain'),
                    'type'        => 'textarea',
                    'description' => __('Esto controla la descripción que el usuario ve durante el checkout.', 'text-domain'),
                    'default'     => __('Pago a través de Mi Método de Pago.', 'text-domain'),
                    'desc_tip'    => true,
                ),
                'api_key' => array(
                    'title'       => 'Clave API',
                    'type'        => 'text',
                    'description' => 'Ingresa tu clave de API proporcionada por el servicio de pagos.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'api_secret' => array(
                    'title'       => 'Secreto API',
                    'type'        => 'password',
                    'description' => 'Ingresa tu secreto de API proporcionado por el servicio de pagos.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                // Puedes agregar más campos aquí según sea necesario
            );
        }
    }

    function agregar_mi_metodo_pago($methods)
    {
        $methods[] = 'WC_Mi_Metodo_Pago';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'agregar_mi_metodo_pago');
}

add_action('plugins_loaded', 'init_mi_metodo_pago');

// Manejar la verificación de pago
function verificar_pago()
{
    if (!isset($_GET['verificar_pago'])) {
        return;
    }

    $order_id = $_GET['order']; // Validar y limpiar esto adecuadamente
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


    // Comprobar el código de estado HTTP de la respuesta

    if ($response_code == 200) {
        // Si el código es 200, el pago fue exitoso
        $order->payment_complete();
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    } else {
	    error_log("error");

        // Si el código no es 200, el pago falló
        $order->update_status('failed', 'The payment was not successful');
        wc_add_notice(__('The payment was not successful, please contact with your bank', 'text-domain'), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }

    exit;
}
function verificar_estado_pago_api($tx_id)
{
    // Aquí deberías configurar la llamada a tu API para verificar el estado del pag
    //password


    $api_url = 'https://api.saurus.com/data/api/v1.0/merchant/topup/paymentgateway/api/polling/'.$tx_id;
    error_log($api_url);
    $headers = array(
        'Content-Type' => 'application/json'
    );

    $api_args = array(
        'method' => 'GET',
        'headers' => $headers,
        'timeout' => 45,
    );

    // Realiza la llamada a la API
    $response = wp_remote_post($api_url, $api_args);
    return $response;
}


add_action('init', 'verificar_pago');
