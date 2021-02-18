<?php
/**
 * Plugin Name: Sakura Network in WooCommerce
 * Plugin URI: https://www.sakura.eco
 * Description: An eCommerce toolkit that helps you show articles in a Sakura network.
 * Version: 1.0.0
 * Author: Sakura.eco
 * Author URI: https://www.sakura.eco/
 * Developer: Sakura.eco
 * Developer URI: https://www.sakura.eco/
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
       add_action( 'init', array( $this, 'init' ), 999 );
       add_action( 'shutdown', array( $this, 'execute_delayed_queue' ), 0 );
  
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
  
      $this->store_sakura_history_in_cookie();
  }
      }
  
  /**
  * Store site/articles into a local cookie.
  */
  public function do_store_sakura_history_in_cookie($history) {
          $history_cookie_id = 0;
          do {
              $history_cookie_id++;
              $history_cookie_name = sprintf('sakura_history_%d', $history_cookie_id);
          } while (isset($_COOKIE[$history_cookie_name]));
  
          wc_setcookie($history_cookie_name, $history, time() + MONTH_IN_SECONDS);
          $_COOKIE[$history_cookie_name] = $history;
  }
  /**
  * fetch site/articles in a local cookie.
  */
  public function sakura_history_in_cookie() {
      $history = NULL;
      foreach($_COOKIE as $key => $value) {
          if (strpos($key, 'sakura_history_', 0) === 0) {
              if (isset($history)) {
                  $history = $history . "," . $value;
              } else {
                  $history = $value;
              }
          }
      }
      return $history;
  }
  /**
  * Store site/articles from sakura networks.
  */
  public function store_sakura_history_in_cookie() {
      if (isset($_GET["sakura_from"])) {
          $article = rawurlencode($_GET["sakura_from"]);
          $history = sprintf('%s', $article);
          if (isset($_GET["sakura_to"])) {
            $history = $history . "-" . rawurlencode($_GET["sakura_to"]);
          }
          $this->do_store_sakura_history_in_cookie ($history);
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
      do_action('sakura_record_activity', sprintf('new order: #%d', $order_id));
      $history = $this->sakura_history_in_cookie();
      if (isset($history)) {
              $order = wc_get_order($order_id);
              $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
              $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
              do_action('sakura_record_activity', sprintf('notify sakura for new order: #%d', $order_id));
              foreach ($order->get_items() as $item_id => $item) {
                  $product    = $item->get_product();
                  $payload = array(
                      'event' => 'purchase',
                      'product-id' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
                      'sakura-widget-key' => $sakura_widget_key,
                      'sku' => $product->get_sku(),
                      'amount' => $item->get_quantity(),
                      'id' => $order_id,
                  );
                  $payload['history'] = $history;
  
                  $http_args = array(
                      'method'      => 'POST',
                      'timeout'     => MINUTE_IN_SECONDS,
                      'redirection' => 0,
                      'httpversion' => '1.0',
                      'blocking'    => true,
                      'user-agent'  => sprintf('WooCommerce Hookshot (WordPress/%s)', $GLOBALS['wp_version']),
                      'body'        => trim(wp_json_encode($payload)),
                      'headers'     => array(
                          'Content-Type' => 'application/json',
                      ),
                      'cookies'     => array(),
                  );
                  // Add custom headers.
                  $http_args['headers']['X-WC-Webhook-Source']      = home_url('/'); // Since 2.6.0.
  
                  $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
                  $response = wp_safe_remote_request(sprintf('%s/api/widget/event', $sakura_server), $http_args);
                  do_action('sakura_record_activity', $response);
              };
          }
  }
  /**
  * add to cart
  */
  public function add_to_cart($arg ) {
      do_action('sakura_record_activity', sprintf('notify sakura for add to cart: #%s', $arg));
  }
  /**
  * payment complete
  */
      public function payment_complete($order_id)
      {
          do_action('sakura_record_activity', sprintf('notify sakura for payment complete: #%d', $order_id));
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
  		global $post;
      if ( isset( $instance[ 'title' ] ) ) {
  
          $title = $instance['title'];
      } else {
          $title = __('Sakura Network', 'wpb_widget_domain');
      }
  
      $query_args = array();
      $sakura_network_options = get_option( 'sakura_network_option' ); // Array of All Options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $url = $sakura_server . '/widget/' . $sakura_widget_key;
  
      $history = SC()->sakura_history_in_cookie();
      if (isset($history)) {
          $query_args['history'] = $history;
      }
      $product = wc_get_product();
      if ($product) {
          $query_args['id'] = $product->get_id();
          $query_args['sku'] = $product->get_sku();
      }
      if (sizeof($query_args) > 0) {
          $url = $url . '?' . http_build_query($query_args);
      }
  
      // before and after widget arguments are defined by themes
      echo $args['before_widget'];
      if ( ! empty( $title ) )
          echo $args['before_title'] . $title . $args['after_title'];
  
      // This is where you run the code and display the output
      ?>
      <iframe class="sakura" width="100%" height="433" src="<?php echo $url; ?>" title="Sakura Transparency Widget"></iframe>
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
           Please setup this widget via <a href="/wp-admin/admin.php?page=sakura-network">Sakura Network menu</a>.
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

class SakuraNetwork {
  private $sakura_network_options;
  public function __construct() {
  	add_action( 'admin_menu', array( $this, 'sakura_network_add_plugin_page' ) );
  	add_action( 'admin_init', array( $this, 'sakura_network_page_init' ) );
  }
  public function sakura_network_add_plugin_page() {
  	add_menu_page(
  		'Sakura Network', // page_title
  		'Sakura Network', // menu_title
  		'manage_options', // capability
  		'sakura-network', // menu_slug
  		array( $this, 'sakura_network_create_admin_page' ), // function
  		'dashicons-admin-settings', // icon_url
  		2 // position
  	);
  }
  public function sakura_network_create_admin_page() {
  	$this->sakura_network_options = get_option( 'sakura_network_option' ); ?>
  
  	<div class="wrap">
  		<h2>Sakura Network</h2>
  		<p>Sakura Network Options</p>
  		<?php settings_errors(); ?>
  
  		<form method="post" action="options.php">
  			<?php
  				settings_fields( 'sakura_network_option_group' );
  				do_settings_sections( 'sakura-network-admin' );
  				submit_button();
  			?>
  		</form>
  	</div>
  <?php }
  public function sakura_network_page_init() {
  	register_setting(
  		'sakura_network_option_group', // option_group
  		'sakura_network_option', // option_name
  		array( $this, 'sakura_network_sanitize' ) // sanitize_callback
  	);
  
  	add_settings_section(
  		'sakura_network_setting_section', // id
  		'Settings', // title
  		array( $this, 'sakura_network_section_info' ), // callback
  		'sakura-network-admin' // page
  	);
  
  	add_settings_field(
  		'sakura_widget_key', // id
  		'Sakura Widget key', // title
  		array( $this, 'sakura_widget_key_callback' ), // callback
  		'sakura-network-admin', // page
  		'sakura_network_setting_section' // section
  	);
  }
  public function sakura_network_sanitize($input) {
  	$sanitary_values = array();
  	if ( isset( $input['sakura_company_id'] ) ) {
  		$sanitary_values['sakura_company_id'] = sanitize_text_field( $input['sakura_company_id'] );
  	}
  
  	if ( isset( $input['sakura_widget_key'] ) ) {
  		$sanitary_values['sakura_widget_key'] = sanitize_text_field( $input['sakura_widget_key'] );
  	}
  
  	return $sanitary_values;
  }
  public function sakura_network_section_info() {
  
  }
  public function sakura_widget_key_callback() {
  	printf(
  		'<input class="regular-text" type="text" name="sakura_network_option[sakura_widget_key]" id="sakura_widget_key" value="%s">',
  		isset( $this->sakura_network_options['sakura_widget_key'] ) ? esc_attr( $this->sakura_network_options['sakura_widget_key']) : ''
  	);
  }
  
}

if ( is_admin() )
	$sakura_network = new SakuraNetwork();
