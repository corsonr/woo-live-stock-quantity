<?php
/**
 * Plugin Name: WooCommerce - Live Stock Quantity
 * Plugin URI: http://www.remicorson.com/
 * Description: Adjusts Products Stock Quantity Live
 * Version: 1.0
 * Author: Remi Corson
 * Author URI: http://remicorson.com
 * Requires at least: 3.0
 * Tested up to: 3.6
 *
 * Text Domain: lsq
 * Domain Path: /languages/
 *
 */
 

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WooCommerce Live Stock Class
 *
 * Adds settings to WooCommerce inventory tab
 * and refresh product stock on product page using the HeartBeat API
 *
 * @since 1.0
 */
class WC_LSQ {

	/*
	|--------------------------------------------------------------------------
	| CONSTANTS
	|--------------------------------------------------------------------------
	*/
	
/*
	if( !defined( 'LSQ_BASE_FILE' ) )		define( 'LSQ_BASE_FILE', __FILE__ );
	if( !defined( 'LSQ_BASE_DIR' ) ) 		define( 'LSQ_BASE_DIR', dirname( LSQ_BASE_FILE ) );
	if( !defined( 'LSQ_PLUGIN_URL' ) ) 		define( 'LSQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	if( !defined( 'LSQ_PLUGIN_VERSION' ) ) 	define( 'LSQ_PLUGIN_VERSION', '1.0' );
*/
	
	/**
	 * Setup admin class
	 *
	 * @since 1.0
	 */
	public function __construct() {
	
		// Process only if WooCommerce is activated
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			
			/*
			|--------------------------------------------------------------------------
			| ACTIONS
			|--------------------------------------------------------------------------
			*/
			if( ! is_admin() ) {
				add_action( 'init', array( $this, 'woo_lsq_textdomain' ) );
				add_action( 'init', array( $this, 'woo_lsq_enqueue_scripts' ) );
			}
			
			/*
			|--------------------------------------------------------------------------
			| FILTERS
			|--------------------------------------------------------------------------
			*/
			if ( 'yes' === get_option( 'woocommerce_live_stock' ) ) {
				add_filter( 'heartbeat_nopriv_received', array( $this, 'woo_lsq_heartbeat_received' ), 5, 2 );
				add_filter( 'heartbeat_received', array( $this, 'woo_lsq_heartbeat_received' ), 5, 2 );
			}
			add_filter( 'woocommerce_inventory_settings', array( $this, 'woo_lsq_add_settings' ) );
		
		
		
		} // endif WooCommerce active
	
	}
	
	
	/*
	|--------------------------------------------------------------------------
	| START PLUGIN FUNCTIONS
	|--------------------------------------------------------------------------
	*/
	
	/*
	 * Load plugin text domain
	 *
	 * @since 1.0
	 */
	public function woo_lsq_textdomain() {
		load_plugin_textdomain( 'lsq', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	
	/**
	 * Enqueue Scripts if live stock enabled
	 *
	 * @since 1.0
	 */
	public function woo_lsq_enqueue_scripts(){
	
		// Check if live stock is enable in inventory settings
		if ( 'yes' === get_option( 'woocommerce_live_stock' ) ) {
			wp_enqueue_script( 'heartbeat' );
			add_action( 'print_footer_scripts', array( $this, 'woo_lsq_heartbeat_footer_js' ), 20 );
	    }
		
	}
	
	
	/**
	 * Scripts Injection to the footer, on the front end
	 *
	 * @since 1.0
	 * @return $response
	 */
	public function woo_lsq_heartbeat_footer_js() {
	    
	    // Only proceed if on a product page
	    if( is_product() ) {
	    
	    // Get heart beat period
	    $woo_lsq_period = get_option( 'woocommerce_live_stock_period' ) ? get_option( 'woocommerce_live_stock_period' ) : 'standard';
	    
		// Determinesperiod in seconds
		switch( $woo_lsq_period ) {
			case 'slow': 		$seconds = '60'; break;
			case 'standard': 	$seconds = '15'; break;
			case 'fast': 		$seconds = '5';  break;
			default : 		$seconds = '15';
		 }
	
	?>
	    <script>
	    (function($){
	    
	    	// Change default beat tick period
	    	wp.heartbeat.interval( '<?php echo $woo_lsq_period; ?>' );
	 
	        // Hook into the heartbeat-send
	        $(document).on('heartbeat-send', function(e, data) {
	            data['woo_lsq_heartbeat_action'] = 'get_stock_quantity';
	            data['woo_lsq_product_id'] = '<?php echo get_the_ID(); ?>';
	        });
	
	        // Listen for the custom event "heartbeat-tick" on $(document).
	        $(document).on( 'heartbeat-tick', function(e, data) {
	
	            // Only proceed if our woo_lsq_product_quantity data is present
	            if ( ! data['woo_lsq_product_quantity'] )
	                return;
	 
	            // Update product stock, hide add to cart button is no stock
	            if( data['woo_lsq_product_quantity'] > <?php echo get_option('woocommerce_notify_no_stock_amount'); ?> ) {
	            	$('.stock').text( data['woo_lsq_product_quantity'] + ' in stock (updated <?php echo $seconds; ?> seconds ago)' ).css( 'color', '<?php echo get_option( 'woocommerce_frontend_css_highlight' ); ?>' );
		           $('form.cart').show();
	            } else {
		           $('.stock').text( 'Out of stock (updated <?php echo $seconds; ?> seconds ago)').css( 'color', 'red' );
		           $('form.cart').hide(); 
	            }
	 
	        });
	    }(jQuery));
	    </script>
		<?php
		}
	}
	
	/**
	 * Heart Beat Data Reception
	 *
	 * @since 1.0
	 * @return $response
	 */
	public function woo_lsq_heartbeat_received( $response, $data ) {
	 
	    // Make sure we only run our query if the edd_heartbeat key is present
	    if( $data['woo_lsq_heartbeat_action'] == 'get_stock_quantity' ) {
	 
	        // Retrieve Stock Quantity
	        $in_stock = get_post_meta( $data['woo_lsq_product_id'], '_stock', true );
	 
	        // Send back stock quantity
	        $response['woo_lsq_product_quantity'] = $in_stock;
	 
	    }
	    return $response;
	}
	
	
	/**
	 * Inject global settings into the Settings > Inventory page, immediately after the 'Inventory Options' section
	 *
	 * @since 1.0
	 * @param array $settings associative array of WooCommerce settings
	 * @return array associative array of WooCommerce settings
	 */
	public function woo_lsq_add_settings( $settings ) {
	
		$updated_settings = array();
	
		foreach ( $settings as $setting ) {
	
			$updated_settings[] = $setting;
	
			if ( isset( $setting['id'] ) && 'inventory_options' === $setting['id']
			  && isset( $setting['type'] ) && 'sectionend' === $setting['type'] ) {
				$updated_settings = array_merge( $updated_settings, $this->woo_lsq_get_settings() );
			}
		}
	
		return $updated_settings;
	}
	
	
	/**
	 * Returns the global settings array for the plugin
	 *
	 * @since 1.0
	 * @return array the global settings
	 */
	public function woo_lsq_get_settings() {
	
		return apply_filters( 'woo_lsq_settings', array(
	
			// Section start
			array(
				'name' => __( 'Live Stock Settings', 'lsq' ),
				'type' => 'title',
				'id'   => 'woo_lsq_settings',
			),
	
			// Enable live stock
			array(
				'title' 	=> __( 'Enable live stock', 'lsq' ),
				'desc' 		=> __( 'Auto-refresh products stock', 'lsq' ),
				'id' 		=> 'woocommerce_live_stock',
				'default'	=> 'yes',
				'type' 		=> 'checkbox'
			),
			
			// Live stock period
			array(
				'title' => __( 'Refreshment', 'lsq' ),
				'desc' 		=> __( 'This controls how stock is refreshed.', 'lsq' ),
				'id' 		=> 'woocommerce_live_stock_period',
				'css' 		=> 'min-width:150px;',
				'default'	=> '',
				'type' 		=> 'select',
				'options' => array(
					'slow'  	=> __( 'Slow (every 60 seconds)', 'lsq' ),
					'standard'	=> __( 'Standard (every 15 seconds)', 'lsq' ),
					'fast' 		=> __( 'Fast (every 5 seconds)', 'lsq' ),
				),
				'desc_tip'	=>  true,
			),
	
			// section end
			array( 'type' => 'sectionend', 'id'   => 'woocommerce_lsq_end' ),
	
		) );
	}
	

} // end \WC_LSQ class

$woo_lsq = new WC_LSQ();