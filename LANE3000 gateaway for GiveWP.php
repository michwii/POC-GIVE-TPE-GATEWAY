<?php
/**
 * LANE 3000 Gateway for GiveWP
 *
 * @package     HelloWorld
 * @author      Elyes HAHCEM
 * @copyright   2022 Elyes HACHEM
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: LANE3000 gateway for GiveWP
 * Plugin URI:  https://seriouscompany.fr
 * Description: This plugin integrate with the LANE3000 paiement terminal
 * Version:     1.0.0
 * Author:      Elyes HACHEM
 * Author URI:  https://seriouscompany.fr
 * Text Domain: Serious Company
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

    // change the prefix lan3000_for_give here to avoid collisions with other functions
    function lane3000_for_give_register_payment_method( $gateways ) {
    
        // Duplicate this section to add support for multiple payment method from a custom payment gateway.
        $gateways['lane3000'] = array(
        'admin_label'    => __( 'lane3000 - Credit Card', 'lane3000-for-give' ), // This label will be displayed under Give settings in admin.
        'checkout_label' => __( 'Payment à la borne', 'lane3000-for-give' ), // This label will be displayed on donation form in frontend.
        );
        
        return $gateways;
    }
  

    // change the lane3000_for_give prefix to avoid collisions with other functions.
    function lane3000_for_give_register_payment_gateway_sections( $sections ) {
        
        // `lane3000-settings` is the name/slug of the payment gateway section.
        $sections['lane3000-settings'] = __( 'lane3000', 'lane3000-for-give' );

        return $sections;
    }


    /**
     * Register Admin Settings.
     *
     * @param array $settings List of admin settings.
     *
     * @since 1.0.0
     *
     * @return array
     */
    // change the lane3000_for_give prefix to avoid collisions with other functions.
    function lane3000_for_give_register_payment_gateway_setting_fields( $settings ) {

        switch ( give_get_current_setting_section() ) {

            case 'lane3000-settings':
                $settings = array(
                    array(
                        'id'   => 'give_title_lane3000',
                        'type' => 'title',
                    ),
                );

                $settings[] = array(
                    'name' => __( 'LANE3000 ID', 'give-square' ),
                    'desc' => __( 'Enter your LANE3000 ID.', 'lane3000-for-give' ),
                    'id'   => 'lane3000_for_give_lane3000_api_key',
                    'type' => 'text',
                );

                $settings[] = array(
                    'id'   => 'give_title_lane3000',
                    'type' => 'sectionend',
                );

                break;

        } // End switch().

        return $settings;
    }

    function give_lane3000_standard_billing_fields( $form_id ) {
    
        printf(
            '
            <fieldset class="no-fields">                    
                <p style="text-align: center;"><b>%1$s</b></p>
                <p style="text-align: center;">
                    <b>%2$s</b> %3$s
                </p>
            </fieldset>
        ',
            __( 'Donner de manière sécurisé avec cette borne de paiement', 'give' ),
            __( 'How it works:', 'give' ),
            __( 'Une fois que vous aurez cliqué sur le bouton valider, suivez les instructions du terminal de paiement. A la fin de votre don, vous recevrez par email votre justificatif de paiement pour déduction aux impôts', 'give' )
        );
    
        return true;
    
    }

    



    // change the lane3000_for_give prefix to avoid collisions with other functions.
    function lane3000_for_give_process_lane3000TPE_donation( $posted_data ) {

        // Make sure we don't have any left over errors present.
        give_clear_errors();

        // Any errors?
        $errors = give_get_errors();

        // No errors, proceed.
        if ( ! $errors ) {

            $form_id         = intval( $posted_data['post_data']['give-form-id'] );
            $price_id        = ! empty( $posted_data['post_data']['give-price-id'] ) ? $posted_data['post_data']['give-price-id'] : 0;
            $donation_amount = ! empty( $posted_data['price'] ) ? $posted_data['price'] : 0;

            // Setup the payment details.
            $donation_data = array(
                'price'           => $donation_amount,
                'give_form_title' => $posted_data['post_data']['give-form-title'],
                'give_form_id'    => $form_id,
                'give_price_id'   => $price_id,
                'date'            => $posted_data['date'],
                'user_email'      => $posted_data['user_email'],
                'purchase_key'    => $posted_data['purchase_key'],
                'currency'        => give_get_currency( $form_id ),
                'user_info'       => $posted_data['user_info'],
                'status'          => 'pending',
                'gateway'         => 'lane3000',
            );

            // Record the pending donation.
            $donation_id = give_insert_payment( $donation_data );

            if ( ! $donation_id ) {

                // Record Gateway Error as Pending Donation in Give is not created.
                give_record_gateway_error(
                    __( 'lane3000 Error', 'lane3000-for-give' ),
                    sprintf(
                    /* translators: %s Exception error message. */
                        __( 'Unable to create a pending donation with Give.', 'lane3000-for-give' )
                    )
                );

                // Send user back to checkout.
                give_send_back_to_checkout( '?payment-mode=lane3000' );
                return;
            }

            // Do the actual payment processing using the custom payment gateway API. To access the GiveWP settings, use give_get_option() 
            // as a reference, this pulls the API key entered above: give_get_option('lane3000_for_give_lane3000TPE_api_key')

            function getSuccessPageURL() {
                $url = get_permalink(absint(give_get_option('success_page')));
                return str_replace( 'http:', 'https:', $url );
            }
            function getFailurePageURL() {
                $url = get_permalink(absint(give_get_option('failure_page')));
                return str_replace( 'http:', 'https:', $url );
            }

            $successPage = urlencode(getSuccessPageURL() . "?donationId=$donation_id");
            $failurePage = urlencode(getFailurePageURL() . "?donationId=$donation_id");

            //$test = give_get_success_page_uri();
            //$test = give_get_failed_transaction_uri();
            //$test = add_query_arg('give-listener', 'IPN', home_url('index.php'));

            wp_redirect("http://localhost:8080?successPageURL=$successPage&failurePageURL=$failurePage&donationId=$donation_id");

        } else {

            // Send user back to checkout.
            give_send_back_to_checkout( '?payment-mode=lane3000' );
        } // End if().
    }


    function give_listen_for_lane3000_ipn() {

        
        if ( isset( $_GET['transactionStatus'] ) && 'success' === $_GET['transactionStatus'] && isset($_GET['transactionId']) && isset($_GET['donationId']) ){    
        
            /*
            $url = 'http://localhost:8080/verify';
            $response = wp_remote_post( $url, array(
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'body'        => array(
                    'transactionId' => $_GET['transactionId']
                )
                )
            );
            */

            $donationId = $_GET['donationId'];

            give_update_payment_status( $donationId, 'publish' );
            wp_send_json_success('{"titi":"successssssssssssss"}');
        
        }

        if ( isset( $_GET['transactionStatus'] ) && 'failed' === $_GET['transactionStatus'] && isset($_GET['donationId']) ){    
            //faire un truc
            $donationId = $_GET['donationId'];
            give_update_payment_status( $donationId, 'failed' );
            wp_send_json_success('{"toto":"faileddddddddddd"}');
        }
        
    }

    function give_process_lane3000_ipn() {

        /*
        // Check the request method is POST.
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        wp_send_json_success('{"toto":"XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXx"}');
        */

        return true;
    }
    
    //Allow to have CORS requests coming from the LAN application
    function initCors( $value ) {
        $origin_url = '*';
      
        // Check if production environment or not
        if (ENVIRONMENT === 'production') {
          $origin_url = 'http://localhost:8080';
        }
      
        header( 'Access-Control-Allow-Origin: ' . $origin_url );
        header( 'Access-Control-Allow-Methods: GET' );
        header( 'Access-Control-Allow-Credentials: true' );
        return $value;
    }
    add_action( 'rest_api_init', function() {
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
        add_filter( 'rest_pre_serve_request', initCors);
    }, 15 );
    

    
    add_filter( 'give_payment_gateways', 'lane3000_for_give_register_payment_method' );
    add_filter( 'give_get_sections_gateways', 'lane3000_for_give_register_payment_gateway_sections' );
    add_filter( 'give_get_settings_gateways', 'lane3000_for_give_register_payment_gateway_setting_fields' );
    add_action( 'give_lane3000_cc_form', 'give_lane3000_standard_billing_fields' );
    // change the lane3000_for_give prefix to avoid collisions with other functions.
    add_action( 'give_gateway_lane3000', 'lane3000_for_give_process_lane3000TPE_donation' );
    add_action( 'init', 'give_listen_for_lane3000_ipn' );
    //add_action( 'give_verify_lane3000_ipn', 'give_process_lane3000_ipn' );




    

 ?>