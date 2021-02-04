<?php
/**
 * Plugin Name: Sakura Network in WooCommerce
 * Plugin URI: https://sakura.eco
 * Description: An eCommerce toolkit that helps you show articles in a sakura.eco network.
 * Version: 1.0.0
 * Author: Sakura.eco
 * Author URI: https://sakura.eco/
 * Developer: Sakura.eco
 * Developer URI: https://sakura.eco/
 * Text Domain: sakura
 * Domain Path: /languages
 *
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * Requires at least: 5.3
 * Requires PHP: 7.0
 *
 * @package SakuraEco
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if (! ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )) {
    exit;
}

/**
 * Main Sakura Class.
 *
 * @class Sakrua
 */
final class Sakrua {

  /**
   * Sakrua version.
   *
   * @var string
   */
  public $version = '1.0.2';
  
  /**
   * Sakura Constructor.
   */
  public function __construct() {
  	$this->init_hooks();
  }
  
  
  /**
   * The single instance of the class.
   *
   * @var Sakrua
   * @since 1.0
   */
  protected static $_instance = null;
  
  /**
   * Main Sakura Instance.
   *
   * Ensures only one instance of Sakura is loaded or can be loaded.
   *
   * @since 2.1
   * @static
   * @see SK()
   * @return Sakura - Main instance.
   */
  public static function instance() {
  	if ( is_null( self::$_instance ) ) {
  		self::$_instance = new self();
  	}
  	return self::$_instance;
  }
  
    /**
     * Hook into actions and filters.
     *
     * @since 2.3
     */
    private function init_hooks() {
  add_action('wp_head', array( $this, 'setup_widget'));
    }
  /**
   * Setup widget.
   */
  public function setup_widget() {
  }
  
  /**
   * Init Sakura when WooCommerce Initialises.
   */
  public function init() {
  }
}
