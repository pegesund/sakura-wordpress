<?php
/**
 * @package The development plugin for Sakura Network.
 * @version 1.0.2
 */
/*
Plugin Name: Sakura network internal development
Plugin URI: https://www.sakura.eco/
Description: This is just a plugin for development use only, to make us local development easy.
Author: Sakura.eco
Version: 1.0.2
Author URI: https://www.sakura.eco/
*/

add_filter( 'http_request_args', function ( $args ) {

    $args['reject_unsafe_urls'] = false;
    $args['sslverify'] = false;

    return $args;
}, 999 );

// Ensure get_home_path() is declared.
require_once ABSPATH . 'wp-admin/includes/file.php';

function read_sakura_server_for_dev ($arg) {
  return trim(file_get_contents( get_home_path() . 'sakura_address.txt'));
}
add_filter( 'sakura_update_server_address', 'read_sakura_server_for_dev', 999 );

function log_sakura_plugin_activity ($message) {
    do_action( 'qm/notice', $message );
    if (is_string($message)) {
        error_log($message);
    } else if ($message instanceof WP_Error) {
        error_log(sprintf('WP_Error:#%s', json_encode($message->get_error_messages())));
    } else {
        error_log(json_encode($message));
    }
}
add_action( 'sakura_record_activity', 'log_sakura_plugin_activity');

function log_sakura_receipt ($message) {
    file_put_contents(get_home_path() . 'wc-mail.html', $message);
    return $message;
}
add_filter( 'woocommerce_mail_content', 'log_sakura_receipt', 999 );
