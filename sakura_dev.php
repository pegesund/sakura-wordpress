<?php
/**
 * @package The development plugin for Sakura Network.
 * @version 1.0.0
 */
/*
Plugin Name: Sakura network internal development
Plugin URI: https://sakura.eco/
Description: This is just a plugin for development use only, to make us local development easy.
Author: Sakura.eco
Version: 1.0.0
Author URI: https://sakura.eco/
*/

add_filter( 'http_request_args', function ( $args ) {

    $args['reject_unsafe_urls'] = false;

    return $args;
}, 999 );

function read_sakura_server_for_dev ($arg) {
  return file_get_contents( get_home_path() . 'sakura_address.txt');
}
add_filter( 'sakura_update_server_address', 'read_sakura_server_for_dev', 999 );
