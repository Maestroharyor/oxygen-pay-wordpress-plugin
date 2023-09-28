<?php
/*
 * Plugin Name: Oxygen Pay
 * Plugin URI: https://oxygenapp.co
 * Description: Pay with Oxygen and generate payment links for your orders.
 * Author: Ayomide Odewale
 * Author URI: http://oxygenapp.co
 * Version: 1.0
 */

/*
 * This action hook registers the PHP class as a WooCommerce payment gateway
 */

ob_clean();
ob_start();
require_once(plugin_dir_path(__FILE__) . 'utils.php');
add_filter('woocommerce_payment_gateways', 'oxygen_add_gateway_class');
function oxygen_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Oxygen_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'oxygen_init_gateway_class');
function oxygen_init_gateway_class()
{

    class WC_Oxygen_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'oxygen'; // payment gateway plugin ID
            $this->icon = 'https://oxygenapp.co/favicon.ico'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Pay With Oxygen';
            $this->method_description = 'Pay with Oxygen and generate payment links for your orders.';

            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->secret_key = $this->testmode ? $this->get_option('test_secret_key') : $this->get_option('secret_key');
            $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('api_key');
            $this->callback_url = $this->testmode ? $this->get_option('test_callback_url') : $this->get_option('callback_url');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Oxygen Pay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'Pay With Oxygen',
                    'default'     => 'Pay With Oxygen',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_api_key' => array(
                    'title'       => 'Test API Key',
                    'type'        => 'text'
                ),
                'test_secret_key' => array(
                    'title'       => 'Test Secret Key',
                    'type'        => 'text',
                ),
                'test_callback_url' => array(
                    'title'       => 'Test Callback URL',
                    'type'        => 'text',
                ),
                'api_key' => array(
                    'title'       => 'Live API Key',
                    'type'        => 'text'
                ),
                'secret_key' => array(
                    'title'       => 'Live Secret Key',
                    'type'        => 'text'
                ),
                'callback_url' => array(
                    'title'       => 'Live Callback URL',
                    'type'        => 'text'
                )
            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

            // ...

        }

        /*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
        public function payment_scripts()
        {

            // ...

        }

        /*
 		 * Fields validation, more in Step 5
		 */
        public function validate_fields()
        {

            if (empty($_POST['billing_first_name'])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            return true;
        }

        /*
		 * We're processing the payments here, everything about it is in Step 5
		 */


        public function generate_payment_link($order_id)
        {
            global $woocommerce;

            // we need it to get any order detailes
            $order = wc_get_order($order_id);

            // Retrieve API key and secret key from the database
            $api_key = $this->api_key;
            $secret_key = $this->secret_key;

            // API endpoints
            $token_endpoint = 'http://api.oxygenapp.co/api/payment/token';
            $initiate_endpoint = 'http://api.oxygenapp.co/api/payment/initiate';

            var_dump($secret_key, $api_key);

            // Data to send to obtain an access token
            $token_data = array(
                'api_key' => $api_key,
                'secret_key' => $secret_key,
                'type' => 'BUSINESS'
            );

            // Set up the request arguments for obtaining an access token

            $token_args = array(
                'body' => json_encode($token_data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            );


            // Send the POST request to obtain an access token
            $token_response = wp_safe_remote_post($token_endpoint, $token_args);

            var_dump($token_response);
            var_dump($token_response["body"]);

            // Check if the request for an access token was successful
            if (!is_wp_error($token_response)) {
                $token_response_body = wp_remote_retrieve_body($token_response);
                $token_data = json_decode($token_response_body);


                // Check the response for the access token
                if (isset($token_data->access_token)) {
                    // Access token retrieved successfully
                    $access_token = $token_data->access_token;

                    // Data to send for payment initiation
                    $callback_url = $this->callback_url ? $this->callback_url : "https://oxygenapp.co";
                    $payment_data = array(
                        "reference" => generate_unique_number(22),
                        "amount" => $order->get_total(),
                        "payer_last_name" => $order->get_billing_last_name(),
                        "payer_first_name" => $order->get_billing_first_name(),
                        "payer_email" => $order->get_billing_email(),
                        "call_back_url" => $callback_url,
                        "payer_phone_number" => $order->get_billing_phone(),
                        "payment_split" => "NO",
                        // "split_id" => "3445432",
                        "meta_data" => array(
                            array("merchant_code" => "test")
                        )
                    );



                    // Set up the request arguments for payment initiation
                    $initiate_args = array(
                        'body' => json_encode($payment_data),
                        'headers' => array(
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $access_token,
                        ),
                    );

                    // Send the POST request to initiate payment
                    $initiate_response = wp_safe_remote_post($initiate_endpoint, $initiate_args);
                    var_dump($initiate_response);

                    // Check if the payment initiation request was successful
                    if (!is_wp_error($initiate_response)) {
                        $initiate_response_body = wp_remote_retrieve_body($initiate_response);
                        $initiate_data = json_decode($initiate_response_body);

                        var_dump($initiate_data);

                        // Check the response for the payment link
                        if (isset($initiate_data->link)) {
                            // Payment link generated successfully
                            $payment_link = $initiate_data->link;
                            // var_dump($payment_link);
                            return $payment_link;
                        }
                    }
                }
            }

            // Handle errors or missing data in the response
            return false;
        }

        public function process_payment($order_id)
        {

            global $woocommerce;

            // we need it to get any order detailes
            $order = wc_get_order($order_id);


            /*
 	 * Array with parameters for API interaction
	 */
            $args = array(

                // ...

            );

            /*
	 * Your API interaction could be built with wp_remote_post()
 	 */
            // $response = wp_remote_post('{payment processor endpoint}', $args);
            $payment_link = $this->generate_payment_link($order_id);




            if (!is_wp_error($payment_link)) {

                // $body = json_decode($response['body'], true);

                // it could be different depending on your payment processor
                if ($payment_link) {

                    var_dump(esc_url($payment_link));
                    // wp_redirect("https://google.com", 302, "Wordpress");
                    // exit();

                    // header("Location: " . esc_url($payment_link));


                    // header("Location: " . esc_url($payment_link));


                    // exit;


                    // we received the payment
                    $order->payment_complete();


                    // // some notes to customer (replace true with false to make it private)
                    $order->add_order_note('Hey, your order is paid! Thank you!', true);

                    // // Empty cart
                    $woocommerce->cart->empty_cart();

                    // // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => esc_url($payment_link)
                    );
                } else {

                    wc_add_notice('Please try again.', 'error');
                    return;
                }
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }
        /*
		 * In case you need a webhook, like PayPal IPN etc
		 */
        public function webhook()
        {

            // ...

        }
    }
}
