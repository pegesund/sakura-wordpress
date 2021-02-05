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
 * @class Sakura
 */
final class Sakura {

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
          array( 'description' => __('A Widget for your Sakura network', 'sakura_widget_domain' ), )
      );
    }
  	    // Creating widget front-end
  public function widget( $args, $instance ) {
      $title = apply_filters( 'widget_title', $instance['title'] );
  
      // before and after widget arguments are defined by themes
      echo $args['before_widget'];
      if ( ! empty( $title ) )
          echo $args['before_title'] . $title . $args['after_title'];
  
      // This is where you run the code and display the output
      echo __( '<iframe width="450" height="433" src="https://sakura/widget/34653862a2760d00b676b5d10c654542" title="Sakura Transparency Widget"></iframe>', 'sakura_widget_domain' );
      echo $args['after_widget'];
  }
  
      // Widget Backend
      public function form( $instance ) {
          if ( isset( $instance[ 'title' ] ) ) {
              $title = $instance[ 'title' ];
          }
          else {
              $title = __( 'New title', 'wpb_widget_domain' );
          }
          // Widget admin form
          ?>
          <p>
          <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
          name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
          </p>
  <?php
      }
  // Updating widget replacing old instances with new
  public function update( $new_instance, $old_instance ) {
      $instance = array();
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
