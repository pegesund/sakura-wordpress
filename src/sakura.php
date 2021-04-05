<?php
/**
 * Plugin Name: Sakura Network
 * Plugin URI: https://www.sakura.eco
 * Description: An eCommerce toolkit that helps you show articles in a Sakura network.
 * Version: 1.0.5
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

if ( ! defined( 'SAKURA_PLUGIN_FILE' ) ) {
	define( 'SAKURA_PLUGIN_FILE', __FILE__ );
}

if (! ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )) {
	define( 'SAKURA_SUPPORT_WOO', false );
} else {
    define( 'SAKURA_SUPPORT_WOO', false );
}

/**
 * Main Sakura Class.
 *
 * @class Sakura
 */
final class Sakura {

  public $current_action = null;
  public $current_action_params = null;
  public $stock_status_array = null;
  /**
   * Sakura version.
   *
   * @var string
   */
  public $version = '1.0.5';
  
  /**
   * Sakura Constructor.
   */
  public function __construct() {
  	$this->define( 'SAKURA_PLUGIN_BASENAME', plugin_basename( SAKURA_PLUGIN_FILE ) );
  	$this->init_hooks();
  }
  
         /**
  	* Hook into actions and filters.
  	*
  	* @since 2.3
  	*/
         private function init_hooks() {
       add_action( 'init', array( $this, 'init' ), 999 );
       add_action( 'init', array( $this, 'init_block' ));
       add_action( 'shutdown', array( $this, 'execute_delayed_queue' ), 0 );
  		 add_filter( 'plugin_action_links_' . SAKURA_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
       add_action('wp_enqueue_scripts', array( $this, 'enqueue_scripts'), 0);
       add_action('enqueue_block_editor_assets', array( $this, 'setup_block_options'), 0);
       // we have to add it before default actions. @see https://github.com/woocommerce/woocommerce/blob/trunk/includes/class-wc-emails.php#L194
  		 add_action( 'woocommerce_email_footer' , array( $this, 'append_widget_in_email_receipt' ), 9);
  		 add_action( 'woocommerce_after_cart' , array( $this, 'render_widget_in_cart' ));
  		 add_action( 'woocommerce_cart_is_empty' , array( $this, 'render_widget_in_cart' ));
  		 add_action( 'woocommerce_thankyou' , array( $this, 'render_widget_in_thank_you' ));
  		 add_action( 'after_woocommerce_pay' , array( $this, 'render_widget_in_receipt_page' ));
  		 add_action( 'woocommerce_product_set_stock_status' , function ($product_id) {
           do_action('sakura_record_activity', "try to sync product data");
           return $this->enqueue_action('update_stock_status', $product_id);
       });
  
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
                  $history = $history . "," . sanitize_text_field($value);
              } else {
                  $history = sanitize_text_field($value);
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
          if (isset($_GET["sakura_network"])) {
            $history = $history . "-" . rawurlencode($_GET["sakura_network"]);
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
      case 'update_stock_status':
          $this->update_stock_status($this->current_action_params);
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
                      'pid' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
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
  * fetch articles from Sakura server
  */
  public function articles($source) {
      $query_args = array();
  
      $sakura_network_options = get_option( 'sakura_network_option' ); // Array of All Options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $url = $sakura_server . '/api/widget/articles/' . $sakura_widget_key;
  
      $history = SC()->sakura_history_in_cookie();
      if (isset($history)) {
          $query_args['history'] = $history;
      }
      if (isset($source)) {
          $query_args['source'] = $source;
      }
  
      if (SAKURA_SUPPORT_WOO) {
          $product = wc_get_product();
          if ($product) {
              $query_args['pid'] = $product->get_id();
              $query_args['sku'] = $product->get_sku();
          }
      }
      if (sizeof($query_args) > 0) {
          $url = $url . '?' . http_build_query($query_args);
      }
      $http_args = array(
          'method'      => 'GET',
          'timeout'     => MINUTE_IN_SECONDS,
          'redirection' => 0,
          'httpversion' => '1.0',
          'blocking'    => true,
          'user-agent'  => sprintf('WooCommerce Hookshot (WordPress/%s)', $GLOBALS['wp_version']),
          'headers'     => array(
              'Content-Type' => 'application/json',
          ));
      $response = wp_safe_remote_request($url);
      do_action('sakura_record_activity', $response);
      if ($response instanceof WP_Error) {
          return (object)array('status' => 'error',
                               'message' => 'Failed to get articles');
      }
      return json_decode($response['body']);
  }
  /**
  * apend widget in email receipt
  */
  public function append_widget_in_email_receipt($email) {
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
      if (!isset ($sakura_network_options['sakura_email_receipt']) ||
          !$sakura_network_options['sakura_email_receipt']) {
          return;
      }
      do_action('sakura_record_activity', 'append_widget_in_email_receipt');
  
      $articles = $this->articles('email');
      if ($articles->{'status'} != 'success' ||
          empty($articles->{'articles'}))
      {
          return;
      }
      $fromSite = $articles->{'fromSite'};
      $fromArticle = $articles->{'fromArticle'};
      $sakura_from = '';
      if (!empty($fromSite)) {
          $sakura_from = $fromSite . ":";
      }
      if (!empty($fromArticle)) {
          if (empty($sakura_from)) {
              $sakura_from = ':';
          }
          $sakura_from .= $fromArticle;
      }
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      ?>
          <br>
          <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap&subset=latin-ext" rel="stylesheet">
          <b style='display: block; font-family: Montserrat, "Helvetica Neue", Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 5px; text-align: left;'>OTHER CUSTOMERS ALSO LIKE</b>
          <span style='font-family: Montserrat;'>
          Discovery Name is a digital cooperation of online offering made for you to give you an even more relevant and exciting discovery online. </span>
          <br>
          <span style='font-family: Montserrat;'>
          Below you will find even more products that global customers also views, visits and purchases. On behalf of Discovery Name, we thank You for your purchase and invite you to discover even more products by clicking on one of the assets below. </span>
  
          <br>
          <div style="background:#f6f6f4;background-color:#f6f6f4; padding: 5px; width:100%">
              <table style="border-collapse: collapse; width: 100%; height: 36px; background-color: #f6f6f4; " border="0">
              <tbody>
                  <tr style="width:100%; height: 18px;">
              <td style="padding-top: 5px;padding-bottom: 5px;width: 100%; height: 18px;font-family: Montserrat;">&nbsp; &nbsp; DISCOVERY IN THE BLUE</td>
                  </tr>
                  <tr style="width:100%; height: 18px;">
              <td style="width: 100%; height: 18px;">
                  <div style="position: relative; width: 100%; overflow-x: scroll; overflow-y: hidden; height: 280px;">
                  <table style="border-collapse: collapse; margin-left: auto; margin-right: auto; position: absolute; top: 0; left: 0; right: 0; bottom: 0; " border="0">
                      <tbody>
                  <tr>
                  <?php
                      foreach( $articles->{'articles'} as $article_obj ) {
                          $title = esc_attr($article_obj->{'title_i18n'}->{'en'});
                          $desc = esc_attr($article_obj->{'description_i18n'}->{'en'});
                          $price = esc_attr($article_obj->{'price'});
                          $currency = esc_attr($article_obj->{'currency'});
                          $id = esc_attr($article_obj->{'id'});
  
                          $linkKey = $article_obj->{'link_key'};
                          $url = $sakura_server . '/api/widget/tracking/' . $linkKey . '/click';
                          $img = esc_attr($article_obj->{'photo'});
                          $from_network = $article_obj->{'from_network'};
                          $query_args = array();
                          if (!empty($sakura_from)) {
                              $query_args['sakura_from'] = $sakura_from;
                          }
                          $query_args['sakura_to'] = $id;
                          if (!empty($from_network)) {
                              $query_args['sakura_network'] = $from_network;
                          }
  
                          $trackImgURL = $sakura_server . '/api/widget/tracking/' . $linkKey . '/view';
  
                          ?>
                          <td>
                          <a target="_blank" href="<?php echo $url; ?>" title="<?php echo $desc ?>">
                          <img src="<?php echo $img ?>" style="max-height: 192px; max-width: 192px;"/></a>
                          <div style="text-align: center;font-family: Montserrat;" title="<?php echo $desc ?>"><b><?php echo $title ?></b></div>
                          <div style="text-align: center;" title="<?php echo $desc ?>">
                              <div data-column="1" data-groupkey="0">
                          <div style="font-family: Montserrat;"><?php echo $price ?>&nbsp;<?php echo $currency ?></div>
                              </div>
                          </div>
                          <div style="text-align: center;">
                              <img src="<?php echo $trackImgURL; ?>">
                          </div>
                          </td>
                          <?php
                      }
                  ?>
                  </tr>
                      </tbody>
                  </table>
                  </div>
              </td>
                  </tr>
              </tbody>
              </table>
              <div style="margin: 0; float: right;">
                  <span style="color: rgb(12, 46, 24); font-family: Montserrat; height:100%;">Networked by</span>
                  <a href="http://sakura.eco" target="_blank">
                  <img style="height: 15px; vertical-align: top;" src="https://www.sakura.eco/img/logo-2021-1.png"/>
                  </a>
              </div>
          </div>
      <?php
  }
  /**
  * render widget in cart
  */
  public function render_widget_in_cart() {
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
      if (!isset ($sakura_network_options['sakura_include_in_cart']) ||
          !$sakura_network_options['sakura_include_in_cart']) {
          return;
      }
      $this->render_widget_in_place();
  }
  /**
  * render widget in receipt page
  */
  public function render_widget_in_receipt_page() {
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
      if (!isset ($sakura_network_options['sakura_include_in_receipt']) ||
          !$sakura_network_options['sakura_include_in_receipt']) {
          return;
      }
      $this->render_widget_in_place();
  }
  /**
  * render widget in thank you page
  */
  public function render_widget_in_thank_you() {
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
      if (!isset ($sakura_network_options['sakura_include_in_thank_you']) ||
          !$sakura_network_options['sakura_include_in_thank_you']) {
          return;
      }
      $this->render_widget_in_place();
  }
  /**
  * render widget in place
  */
  public function render_widget_in_place() {
      do_action('sakura_record_activity', 'render_widget_in_place');
      $sakura_network_options = get_option( 'sakura_network_option' ); // Array of All Options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $url = $sakura_server . '/widget/' . $sakura_widget_key;
  
      $history = SC()->sakura_history_in_cookie();
      if (isset($history)) {
          $query_args['history'] = $history;
      }
  
      if (SAKURA_SUPPORT_WOO) {
          $product = wc_get_product();
          if ($product) {
              $query_args['pid'] = $product->get_id();
              $query_args['sku'] = $product->get_sku();
          }
      }
      if (sizeof($query_args) > 0) {
          $url = $url . '?' . http_build_query($query_args);
      }
  
      ?>
          <h3> OTHER CUSTOMERS ALSO LIKE </h3>
          <iframe class="sakura" style="width: 100%; height: 433px; border: 0" src="<?php echo $url; ?>" title="Sakura Transparency Widget"></iframe>
      <?php
  
  }
  
  /**
  * render widget in place
  */
  public function update_stock_status($product_id) {
      $product = wc_get_product( $product_id );
      $status = $product->get_stock_status();
      if (isset($stock_status_array[$product_id]) &&
          // If stock status keep unchanged, just return.
          $stock_status_array[$product_id] == $status) {
          return;
      }
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
  
      $sakura_secret_key = $sakura_network_options['sakura_secret_key']; // Sakura Secret key
      if (!isset ($sakura_secret_key)) {
          do_action('sakura_record_activity', 'Failed to update stock status becuase of empty secret key');
          return;
      }
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $http_args = array(
          'method'      => 'GET',
          'timeout'     => MINUTE_IN_SECONDS,
          'redirection' => 0,
          'httpversion' => '1.0',
          'blocking'    => true,
          'user-agent'  => sprintf('WooCommerce Hookshot (WordPress/%s)', $GLOBALS['wp_version']),
          'headers'     => array(
              'Content-Type' => 'application/json',
          ));
      $response = wp_safe_remote_request(sprintf('%s/api/widget/updateStockStatus?secretKey=%s&pid=%s&sku=%s&stock_status=%s',
                                                 $sakura_server,
                                                 $sakura_secret_key,
                                                 $product_id,
                                                 $product->get_sku(),
                                                 $status), $http_args);
      do_action('sakura_record_activity', $response);
      $stock_status_array[$product->get_id()] = $status;
  }
  
  /**
  * Initialize networks data for current site.
  */
  public function setup_block_options() {
      do_action('sakura_record_activity', 'setup_block_options');
      // wp_enqueue_script( 'sakura-network-data');
      wp_add_inline_script('wp-editor',
                           sprintf('var _sakura_networks = %s;',
                                   wp_json_encode($this->networks())));
  }
  /**
  * Get a list of owned Sakura networks.
  */
  public function networks() {
      $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
  
      $sakura_secret_key = $sakura_network_options['sakura_secret_key']; // Sakura Secret key
      if (!isset ($sakura_secret_key)) {
          return (object)array('status' => 'error',
                               'message' => 'Please setup secret Key for Sakura network.');
      }
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $http_args = array(
          'method'      => 'GET',
          'timeout'     => MINUTE_IN_SECONDS,
          'redirection' => 0,
          'httpversion' => '1.0',
          'blocking'    => true,
          'user-agent'  => sprintf('WooCommerce Hookshot (WordPress/%s)', $GLOBALS['wp_version']),
          'headers'     => array(
              'Content-Type' => 'application/json',
          ));
      $response = wp_safe_remote_request(sprintf('%s/api/widget/networks?secretKey=%s', $sakura_server, $sakura_secret_key), $http_args);
      do_action('sakura_record_activity', $response);
      if ($response instanceof WP_Error) {
          return (object)array('status' => 'error',
                               'message' => 'Failed to get networks');
      }
      return json_decode($response['body']);
  }
  /**
  * enqueue js files.
  */
  public function enqueue_scripts() {
      wp_enqueue_script( 'iframeResizer', plugins_url( '/js/iframeResizer.min.js', __FILE__ ));
      wp_enqueue_script( 'sakura', plugins_url( '/js/sakura.js', __FILE__), array(), false, true);
  }
  /**
   * Show action links on the plugin screen.
   *
   * @param mixed $links Plugin Action links.
   *
   * @return array
   */
  public static function plugin_action_links( $links ) {
  	$action_links = array(
  		'settings' => '<a href="' . admin_url( 'admin.php?page=sakura-network' ) . '" aria-label="' . esc_attr__( 'View Sakura network settings', 'sakura' ) . '">' . esc_html__( 'Settings', 'sakura' ) . '</a>',
  	);
  
  	return array_merge( $action_links, $links );
  }
  
  /**
   * Define constant if not already set.
   *
   * @param string      $name  Constant name.
   * @param string|bool $value Constant value.
   */
  private function define( $name, $value ) {
  	if ( ! defined( $name ) ) {
  		define( $name, $value );
  	}
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
  
  /**
  * Registers all block assets so that they can be enqueued through the block editor
  * in the corresponding context.
  */
  public function init_block() {
      $dir = plugin_dir_path( SAKURA_PLUGIN_FILE );
  
      $script_asset_path = "$dir/build/index.asset.php";
      $index_js     = 'build/index.js';
      $script_asset = require( $script_asset_path );
      wp_register_script(
          'sakura-network-block-editor',
          plugins_url( $index_js, SAKURA_PLUGIN_FILE),
          $script_asset['dependencies'],
          $script_asset['version']
      );
      wp_set_script_translations( 'sakura-network-block-editor', 'sakura-network' );
  
      $editor_css = 'build/index.css';
      wp_register_style(
          'sakura-network-block-editor',
          plugins_url( $editor_css, __FILE__ ),
          array(),
          filemtime( "$dir/$editor_css" )
      );
  
      $style_css = 'build/style-index.css';
      wp_register_style(
          'sakura-network-block',
          plugins_url( $style_css, __FILE__ ),
          array(),
          filemtime( "$dir/$style_css" )
      );
  
      register_block_type(
          'sakura-network/sakura-network',
          array(
              'render_callback' => array( $this, 'block_render_callback' ),
              'editor_script' => 'sakura-network-block-editor',
              'attributes'      => [
                  'network' => [
                  'default' => '0',
                  'type'    => 'string'
              ],
                  'bgcolor' => [
                  'default' => '',
                  'type'    => 'string'
              ],
                  'font' => [
                  'default' => '',
                  'type'    => 'string'
              ]
  ],
              'editor_style'  => 'sakura-network-block-editor',
              'style'         => 'sakura-network-block'
          )
      );
  }
  /**
  * The render callback for block Sakura network.
  */
  public function block_render_callback($attributes, $content) {
      $network = $attributes['network'];
      do_action('sakura_record_activity', sprintf('block_render_callback, network:%s', $network));
      do_action('sakura_record_activity', sprintf('block_render_callback, content:%s', $content));
      $query_args = array();
  
      if ($network != 0) {
          $query_args['network'] = $network;
      }
      $bgcolor = $attributes['bgcolor'];
      if (! empty($bgcolor)) {
          $query_args['bgcolor'] = $bgcolor;
      }
      $font = $attributes['font'];
      if (! empty($font)) {
          $query_args['font'] = $font;
      }
  
      $sakura_network_options = get_option( 'sakura_network_option' ); // Array of All Options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $url = $sakura_server . '/widget/' . $sakura_widget_key;
  
      $history = SC()->sakura_history_in_cookie();
      if (isset($history)) {
          $query_args['history'] = $history;
      }
      if (SAKURA_SUPPORT_WOO) {
          $product = wc_get_product();
          if ($product) {
              $query_args['pid'] = $product->get_id();
              $query_args['sku'] = $product->get_sku();
          }
      }
  
      if (sizeof($query_args) > 0) {
          $url = $url . '?' . http_build_query($query_args);
      }
  
      return '<iframe class="sakura" style="width: 100%; height: 433px; border: 0" src="'
              . $url . '" title="Sakura Transparency Widget"></iframe>';
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
  		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
  		add_action( 'admin_footer-widgets.php', array( $this, 'print_scripts' ), 9999 );
    }
  /**
  * enqueue js files.
  */
  public function enqueue_scripts($hook_suffix) {
      if ( 'widgets.php' !== $hook_suffix ) {
          return;
      }
  
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_script( 'wp-color-picker' );
      wp_enqueue_script( 'underscore' );
  }
  /**
   * Print scripts.
   *
   * @since 1.0
   */
  public function print_scripts() {
  	?>
  	<script>
  		( function( $ ){
  			function initColorPicker( widget ) {
  				widget.find( '.sakura-color-field' ).wpColorPicker( {
            defaultColor: "#f6f6f4",
          palettes: ['#f7edec', '#97a7a9', '#f6f6f4'],
      change: function(e, ui) {
              $('.sakura-color-field').val(ui.color.toString());
              $('.sakura-color-field').trigger('change');
          },
      clear: function(e, ui) {
          $(e.target).trigger('change')}
  				});
  			}
  
  			function onFormUpdate( event, widget ) {
  				initColorPicker( widget );
  			}
  
  			$( document ).on( 'widget-added widget-updated', onFormUpdate );
  
  			$( document ).ready( function() {
  				$( '#widgets-right .widget:has(.sakura-color-field)' ).each( function () {
  					initColorPicker( $( this ) );
  				} );
  			} );
  		}( jQuery ) );
  	</script>
  	<?php
  }
  // Creating widget front-end
  public function widget( $args, $instance ) {
      $query_args = array();
  
      if ( !empty( $instance[ 'network' ] ) ) {
          $query_args['network'] = $instance['network'];
      }
      if ( !empty( $instance[ 'bgcolor' ] ) ) {
          $query_args['bgcolor'] = $instance['bgcolor'];
      }
      if ( !empty( $instance[ 'font' ] ) ) {
          $query_args['font'] = $instance['font'];
      }
  
      $sakura_network_options = get_option( 'sakura_network_option' ); // Array of All Options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // Sakura Widget key
  
      $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
      $url = $sakura_server . '/widget/' . $sakura_widget_key;
  
      $history = SC()->sakura_history_in_cookie();
      if (isset($history)) {
          $query_args['history'] = $history;
      }
  
      if (SAKURA_SUPPORT_WOO) {
          $product = wc_get_product();
          if ($product) {
              $query_args['pid'] = $product->get_id();
              $query_args['sku'] = $product->get_sku();
          }
      }
      if (sizeof($query_args) > 0) {
          $url = $url . '?' . http_build_query($query_args);
      }
  
      // before and after widget arguments are defined by themes
      echo $args['before_widget'];
      // if ( ! empty( $title ) )
      //     echo $args['before_title'] . $title . $args['after_title'];
  
          // This is where you run the code and display the output
          ?>
          <iframe class="sakura" style="width: 100%; height: 433px; border: 0" src="<?php echo $url; ?>" title="Sakura Transparency Widget"></iframe>
      <?php
          echo $args['after_widget'];
      }
  
  // Widget Backend
  public function form( $instance ) {
      do_action('sakura_record_activity', sprintf('form instance: %s', json_encode($instance)));
      if ( !empty( $instance[ 'network' ] ) ) {
          $network = (int)$instance['network'];
      } else {
          $network = 0;
      }
      $bgcolor = (!empty($instance['bgcolor'] ) ) ? $instance['bgcolor'] : '#f6f6f4';
      $font = (!empty($instance['font'] ) ) ? $instance['font'] : '';
  
      $sakura_network_options = get_option('sakura_network_option'); // array of all options
      $sakura_widget_key = $sakura_network_options['sakura_widget_key']; // sakura widget key
      if ( !isset ($sakura_widget_key)) {
          ?>
          <p>
          please setup widget key via <a href="/wp-admin/admin.php?page=sakura-network">sakura network menu</a>.
          </p>
          <?php
      }
      $networks = SC()->networks();
      if ($networks->{'status'} != 'success') {
          echo '<h3>';
          echo $networks->{'message'};
          echo '</h3>';
      } else {
          ?>
          <p>
          <label for="<?php echo $this->get_field_id('network'); ?>">Network: </label>
          <select class='widefat' id="<?php echo $this->get_field_id('network'); ?>"
                      name="<?php echo $this->get_field_name('network'); ?>" type="text">
              <option value=''<?php echo ($network==0)?'selected':''; ?>>
                  All networks
              </option>
              <?php
                  foreach( $networks->{'networks'} as $network_obj ) {
                  $id = $network_obj->{'id'};
                  $name = $network_obj->{'name'}->{'en'};
                  ?>
                      <option value='<?php echo $id ?>'<?php echo ($network==$id)?'selected':''; ?>>
                          <?php echo $name ?>
                      </option>
                  <?php
                  }
              ?>
              </select>
              </p>
          <p>
          <label for="<?php echo $this->get_field_id('bgcolor'); ?>">Background color:</label>
          <input class="widefat sakura-color-field" id="<?php echo $this->get_field_id('bgcolor'); ?>"
                  name="<?php echo $this->get_field_name('bgcolor'); ?>"
                  value="<?php echo $bgcolor; ?>" type="text" />
              </p>
          <p>
          <label for="<?php echo $this->get_field_id('font'); ?>">Font: </label>
          <select class='widefat' id="<?php echo $this->get_field_id('font'); ?>"
                      name="<?php echo $this->get_field_name('font'); ?>" type="text">
              <option value=''<?php echo ($font=='')?'selected':''; ?>>
                  Default
              </option>
              <option value='Montserrat'<?php echo ($font=='Montserrat')?'selected':''; ?>>
                  Montserrat
              </option>
              <option value='Avenir LT W04_65 Medium1475536'<?php echo ($font=='Avenir LT W04_65 Medium1475536')?'selected':''; ?>>
                  Avenir
              </option>
              <option value='Vesper Libre'<?php echo ($font=='Vesper Libre')?'selected':''; ?>>
                  Vesper Libre
              </option>
              <option value='IBM Plex Sans'<?php echo ($font=='IBM Plex Sans')?'selected':''; ?>>
                  IBM Plex Sans
              </option>
              </select>
              </p>
          <?php
      }
      // widget admin form
  }
  // Updating widget replacing old instances with new
      public function update( $new_instance, $old_instance ) {
          $instance = array();
          $instance['network'] = ( ! empty( $new_instance['network'] ) ) ? strip_tags( $new_instance['network'] ) : '';
          $instance['bgcolor'] = ( ! empty( $new_instance['bgcolor'] ) ) ? strip_tags( $new_instance['bgcolor'] ) : '';
          $instance['font'] = ( ! empty( $new_instance['font'] ) ) ? strip_tags( $new_instance['font'] ) : '';
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
  		    'Sakura Widget Key', // title
  		    array( $this, 'sakura_widget_key_callback' ), // callback
  		    'sakura-network-admin', // page
  		    'sakura_network_setting_section' // section
  	    );
  
  add_settings_field(
      'sakura_secret_key', // id
      'Sakura Secret Key', // title
      array( $this, 'sakura_secret_key_callback' ), // callback
      'sakura-network-admin', // page
      'sakura_network_setting_section' // section
  );
  
  	    add_settings_field(
  		    'sakura_email_receipt', // id
  		    'Include widget in new order email receipt', // title
  		    array( $this, 'sakura_email_receipt_callback' ), // callback
  		    'sakura-network-admin', // page
  		    'sakura_network_setting_section' // section
  	    );
  
  	    add_settings_field(
  		    'sakura_include_in_cart', // id
  		    'Include widget in cart page', // title
  		    array( $this, 'sakura_include_in_cart_callback' ), // callback
  		    'sakura-network-admin', // page
  		    'sakura_network_setting_section' // section
  	    );
  
  	    add_settings_field(
  		    'sakura_include_in_receipt', // id
  		    'Include widget in receipt page', // title
  		    array( $this, 'sakura_include_in_receipt_callback' ), // callback
  		    'sakura-network-admin', // page
  		    'sakura_network_setting_section' // section
  	    );
  
  	    add_settings_field(
  		    'sakura_include_in_thank_you', // id
  		    'Include widget in thank you page', // title
  		    array( $this, 'sakura_include_in_thank_you_callback' ), // callback
  		    'sakura-network-admin', // page
  		    'sakura_network_setting_section' // section
  	    );
      }
      public function sakura_network_sanitize($input) {
  	    $sanitary_values = array();
  	    if ( isset( $input['sakura_widget_key'] ) ) {
  		    $sanitary_values['sakura_widget_key'] = sanitize_text_field( $input['sakura_widget_key'] );
  	    }
  
  if ( isset( $input['sakura_secret_key'] ) ) {
    	    $sanitary_values['sakura_secret_key'] = sanitize_text_field( $input['sakura_secret_key'] );
      }
  
  	    if ( isset( $input['sakura_email_receipt'] ) ) {
  		    $sanitary_values['sakura_email_receipt'] = sanitize_text_field( $input['sakura_email_receipt'] );
  	    }
  
  	    if ( isset( $input['sakura_include_in_cart'] ) ) {
  		    $sanitary_values['sakura_include_in_cart'] = sanitize_text_field( $input['sakura_include_in_cart'] );
  	    }
  
  	    if ( isset( $input['sakura_include_in_receipt'] ) ) {
  		    $sanitary_values['sakura_include_in_receipt'] = sanitize_text_field( $input['sakura_include_in_receipt'] );
  	    }
  
  	    if ( isset( $input['sakura_include_in_thank_you'] ) ) {
  		    $sanitary_values['sakura_include_in_thank_you'] = sanitize_text_field( $input['sakura_include_in_thank_you'] );
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
  
  public function sakura_secret_key_callback() {
  	printf(
  		'<input class="regular-text" type="text" name="sakura_network_option[sakura_secret_key]" id="sakura_secret_key" value="%s">',
  		isset( $this->sakura_network_options['sakura_secret_key'] ) ? esc_attr( $this->sakura_network_options['sakura_secret_key']) : ''
  	);
  }
  
  public function sakura_email_receipt_callback() {
      $sakura_email_receipt = false;
      if (isset( $this->sakura_network_options['sakura_email_receipt'] )) {
          $sakura_email_receipt = $this->sakura_network_options['sakura_email_receipt'];
      }
      $html = '<input type="checkbox" id="sakura_email_receipt" name="sakura_network_option[sakura_email_receipt]" value="1"'
          . checked( 1, $sakura_email_receipt, false ) . '/>';
      $html .= '<label for="sakura_email_receipt_key">Include widget in new order email receipt</label>';
  
      printf($html);
  }
  public function sakura_include_in_cart_callback() {
      $sakura_include_in_cart = false;
      if (isset( $this->sakura_network_options['sakura_include_in_cart'] )) {
          $sakura_include_in_cart = $this->sakura_network_options['sakura_include_in_cart'];
      }
      $html = '<input type="checkbox" id="sakura_include_in_cart" name="sakura_network_option[sakura_include_in_cart]" value="1"'
          . checked( 1, $sakura_include_in_cart, false ) . '/>';
      $html .= '<label for="sakura_include_in_cart_key">Include widget in cart page</label>';
  
      printf($html);
  }
  public function sakura_include_in_receipt_callback() {
      $sakura_include_in_receipt = false;
      if (isset( $this->sakura_network_options['sakura_include_in_receipt'] )) {
          $sakura_include_in_receipt = $this->sakura_network_options['sakura_include_in_receipt'];
      }
      $html = '<input type="checkbox" id="sakura_include_in_receipt" name="sakura_network_option[sakura_include_in_receipt]" value="1"'
          . checked( 1, $sakura_include_in_receipt, false ) . '/>';
      $html .= '<label for="sakura_include_in_receipt_key">Include widget in receipt page</label>';
  
      printf($html);
  }
  public function sakura_include_in_thank_you_callback() {
      $sakura_include_in_thank_you = false;
      if (isset( $this->sakura_network_options['sakura_include_in_thank_you'] )) {
          $sakura_include_in_thank_you = $this->sakura_network_options['sakura_include_in_thank_you'];
      }
      $html = '<input type="checkbox" id="sakura_include_in_thank_you" name="sakura_network_option[sakura_include_in_thank_you]" value="1"'
          . checked( 1, $sakura_include_in_thank_you, false ) . '/>';
      $html .= '<label for="sakura_include_in_thank_you_key">Include widget in thank you page</label>';
  
      printf($html);
  }
}

if ( is_admin() )
	$sakura_network = new SakuraNetwork();

class BulkExport {
  public function __construct() {
      add_filter( 'bulk_actions-edit-product', array( $this, 'register_my_bulk_actions' ));
      add_filter( 'handle_bulk_actions-edit-product', array( $this, 'my_bulk_action_handler'), 10, 3 );
      add_action( 'admin_notices', array($this, 'my_bulk_action_admin_notice' ));
  }
  function register_my_bulk_actions($bulk_actions) {
    $bulk_actions['export_to_sakura'] = __( 'Export to Sakura', 'export_to_sakura');
    return $bulk_actions;
  }
  function my_bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
  
    if ( $doaction !== 'export_to_sakura' ) {
      return $redirect_to;
    }
  
    $sakura_network_options = get_option('sakura_network_option'); // Array of All Options
  
    $allProducts = array();
    $payload = array();
    $payload['sakura_widget_key'] = $sakura_network_options['sakura_widget_key'];;
    $payload['sakura_secret_key'] = $sakura_network_options['sakura_secret_key'];
    $payload['currency'] = get_woocommerce_currency();
  
    foreach ( $post_ids as $post_id ) {
      $prod = wc_get_product( $post_id );
  
      // tags
      $terms = get_the_terms( $post_id, 'product_tag' );
      $termsString = '';
  
      if (is_array($terms))
      {
        foreach ($terms as $tag) {
          $termsString = $termsString . $tag->to_array()['name'] . ', ';
        }
        $termsString = substr($termsString, 0, strlen($termsString) - 2);
      }
  
      $image_url = wp_get_attachment_image_src(
        get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' );
      $permalink = $prod->get_permalink();
  
      $prod_m = $prod->get_data();
  
      if (is_array($image_url))
        $prod_m['img_url'] = $image_url[0];
      else
        $prod_m['img_url'] = '';
      $prod_m['permalink'] = $permalink;
      $prod_m['tags'] = $termsString;
  
      array_push($allProducts, $prod_m);
    }
    $payload['all_products'] = $allProducts;
  
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
  
    $sakura_server = apply_filters('sakura_update_server_address', 'https://www.sakura.eco');
    $response = wp_safe_remote_request(sprintf('%s/api/addWCProducts', $sakura_server), $http_args);
  
    $countPosts = 0;
    // sanity check
    if ($response) {
      $countPosts = count( $post_ids );
    }
  
    $redirect_to = add_query_arg( 'bulk_export_posts', $countPosts, $redirect_to );
  
    return $redirect_to;
  }
  function my_bulk_action_admin_notice() {
    if ( ! empty( $_REQUEST['bulk_export_posts'] ) ) {
      $export_count = intval( $_REQUEST['bulk_export_posts'] );
      if ($export_count > 0) {
      printf( '<div id="message" class="updated fade">' .
        _n( 'Exported %s post to Sakura',
          'Exported %s posts to Sakura',
          $export_count,
          'export_to_sakura'
        ) . '</div>', $export_count );
      }
    }
  }
}

if ( is_admin() )
  $bulk_export = new BulkExport();
