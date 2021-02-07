<?php
/**
 * Plugin Name: Sakura Network in WooCommerce
 * Plugin URI: https://sakura.eco
 * Description: An eCommerce toolkit that helps you show articles in a Sakura network.
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
 * @class Sakura
 */
final class Sakura {

  public $current_action = null;
  public $current_action_params = null;
  /**
   * Sakura version.
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
  	* Hook into actions and filters.
  	*
  	* @since 2.3
  	*/
         private function init_hooks() {
       add_action( 'init', array( $this, 'init' ), 0 );
       add_action( 'shutdown', array( $this, 'execute_delayed_queue' ), 0 );
       <<sakura-dev-hooks>>
  
       // a uniform interface to woocommerce events.
       add_action( 'woocommerce_new_order', function ($order_id) {
         return $this->enqueue_action('woocommerce_new_order', $order_id);
  }, 0 );
       // add_action( 'woocommerce_update_order', array( $this, 'update_order' ), 0 );
       // add_action( 'woocommerce_order_refunded', array( $this, 'refund_order' ), 0 );
       add_action( 'woocommerce_add_to_cart',
  function ($hash_id) {
  return $this->enqueue_action('woocommerce_add_to_cart', $hash_id);
  }, 0 );
       add_action( 'woocommerce_pre_payment_complete',
  function ($order_id) {
  return $this->enqueue_action('woocommerce_pre_payment_complete', $order_id);
  }, 0 );
         }
      /**
       * Init Sakura when Wordpress Initialises.
       */
      public function init() {
    // Classes/actions loaded for the frontend and for ajax requests.
  if (( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' )) {
      $this->store_sakura_from_in_cookie();
  }
      }
  
  /**
  * Store site/articles from sakura networks.
  */
  public function store_sakura_from_in_cookie() {
      if (isset($_GET["sakura_from"])) {
          $article = rawurlencode($_GET["sakura_from"]);
          if (isset( $_COOKIE["sakura_from"] )) {
              $articles = $_COOKIE["sakura_from"] . "," . $article;
          } else {
              $articles = $article;
          }
          wc_setcookie("sakura_from", $articles, time() - MONTH_IN_SECONDS);
          $_COOKIE["sakura_from"] = $articles;
      }
  }
  /**
  * enqueue action
  */
  public function enqueue_action($action, $arg) {
          $this->current_action = $action;
          $this->current_action_params = $arg;
  }
  /**
  * Process action
  */
  public function execute_delayed_queue() {
        switch ($this->current_action)
  {
                  case 'woocommerce_new_order':
      $this->new_order($this->current_action_params);
    break;
  case 'woocommerce_add_to_cart':
      $this->add_to_cart($this->current_action_params);
  break;
  case 'woocommerce_pre_payment_complete':
      $this->payment_complete = $this->current_action_params;
      break;
  }
                       }
  
  /**
  * New order
  */
  public function new_order($order_id) {
  			    $order = wc_get_order( $order_id );
  
          error_log(sprintf('notify sakura for new order: #%d', $order_id));
      foreach ( $order->get_items() as $item_id => $item ) {
          $product    = $item->get_product();
          $payload = array(
                      'event' => 'purchase',
                      'to_article' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
                      'amount' => $item->get_quantity(),
                      'id' => $order_id,
                                      );
  
          $http_args = array(
          'method'      => 'POST',
          'timeout'     => MINUTE_IN_SECONDS,
          'redirection' => 0,
          'httpversion' => '1.0',
          'blocking'    => true,
          'user-agent'  => sprintf( 'WooCommerce Hookshot (WordPress/%s)', $GLOBALS['wp_version'] ),
          'body'        => trim( wp_json_encode( $payload ) ),
          'headers'     => array(
              'Content-Type' => 'application/json',
          ),
          'cookies'     => array(),
          );
          // Add custom headers.
          $http_args['headers']['X-WC-Webhook-Source']      = home_url( '/' ); // Since 2.6.0.
  
          // Webhook away!
          $response = wp_safe_remote_request( 'http://sakura/api/widget/event', $http_args );
              if($response instanceof WP_Error) {
                  error_log(sprintf('response:#%s', json_encode($response->get_error_messages())));
              } else {
                  error_log(sprintf('response:#%s', json_encode($response)));
              }
  
          };
  }
  /**
  * add to cart
  */
  public function add_to_cart($arg ) {
          $logger = wc_get_logger();
          error_log(sprintf('notify sakura for add to cart: #%s', $arg));
  }
  /**
  * payment complete
  */
      public function payment_complete($order_id)
      {
          $logger = wc_get_logger();
          error_log(sprintf('notify sakura for payment complete: #%d', $order_id));
      }
  
  
  /**
   * The single instance of the class.
   *
   * @var Sakura
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
   * @see SC()
   * @return Sakura - Main instance.
   */
  public static function instance() {
  	if ( is_null( self::$_instance ) ) {
  		self::$_instance = new self();
  	}
  	return self::$_instance;
  }
  
}

/**
 * Returns the main instance of SC.
 *
 * @since  1.0
 * @return Sakura
 */
function SC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Sakura::instance();
}
// Global for backwards compatibility.
$GLOBALS['sakura'] = SC();

class Sakura_widget extends WP_Widget {
  // Creating the widget
  function __construct() {
      parent::__construct(
  
          // Base ID of your widget
          'Sakura_widget',
  
          // Widget name will appear in UI
          __('Sakura Network', 'sakura_widget_domain'),
  
          // Widget description
          array( 'description' => __('A widget for your Sakura network', 'sakura_widget_domain' ), )
      );
    }
  // Creating widget front-end
  public function widget( $args, $instance ) {
      if ( isset( $instance[ 'title' ] ) ) {
  
          $title = $instance['title'];
      } else {
          $title = __('Sakura Network', 'wpb_widget_domain');
      }
      $url = apply_filters( 'widget_url', $instance['url'] );
      if (isset( $_COOKIE["sakura_from"] )) {
          $url = $url . "?from=" .  $_COOKIE["sakura_from"];
      } else {
          $articles = '';
      }
  
      // before and after widget arguments are defined by themes
      echo $args['before_widget'];
      if ( ! empty( $title ) )
          echo $args['before_title'] . $title . $args['after_title'];
  
      // This is where you run the code and display the output
      ?>
      <iframe class="sakura" width="450" height="433" src="<?php echo $url; ?>" title="Sakura Transparency Widget"></iframe>
  <?php
      echo $args['after_widget'];
  }
  
  // Widget Backend
      public function form( $instance ) {
          if ( isset( $instance[ 'title' ] ) ) {
              $title = $instance['title'];
          } else {
              $title = __('Sakura Network', 'wpb_widget_domain' );
          }
          $url = ! empty( $instance['url'] ) ? $instance['url'] : esc_html__( 'Please input the widget URL', 'text_domain' );
          // Widget admin form
  
  
          ?>
          <p>
          <label for="<?php echo $this->get_field_id( 'url' ); ?>"><?php _e( 'Url:' ); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id( 'url' ); ?>"
          name="<?php echo $this->get_field_name( 'url' ); ?>" type="text" value="<?php echo esc_attr( $url ); ?>" />
          </p>
  <?php
      }
  // Updating widget replacing old instances with new
      public function update( $new_instance, $old_instance ) {
          $instance = array();
          $instance['url'] = ( ! empty( $new_instance['url'] ) ) ? strip_tags( $new_instance['url'] ) : '';
          $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
          return $instance;
      }
  
  // Class sakura_widget ends here
}

// Register and load the widget
function sakura_load_widget() {
    register_widget( 'Sakura_widget' );
}
add_action( 'widgets_init', 'sakura_load_widget' );

if(isset($_SERVER['SAKURA_DEV'])){
    add_filter( 'http_request_args', function ( $args ) {

        $args['reject_unsafe_urls'] = false;

        return $args;
    }, 999 );
 }

fastcgi_param SAKURA_DEV true;
