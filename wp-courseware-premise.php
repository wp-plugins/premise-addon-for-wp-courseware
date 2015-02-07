<?php
/*
 * Plugin Name: WP Courseware - Premise Membership Add On
 * Version: 1.3
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add support for the Premise Membership membership plugin for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */


// Main parent class
include_once 'class_members.inc.php';


// Hook to load the class
add_action('init', 'WPCW_Premise_init');

/**
 * Initialise the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_Premise_init()
{
	$item = new WPCW_Premise();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Courseware, attach.
	$item->attachToTools();
}


/**
 * Membership class that handles the specifics of the Premise Membership WordPress plugin and
 * handling the data for levels for that plugin.
 */
class WPCW_Premise extends WPCW_Members
{
	const GLUE_VERSION  = 1.00; 
	const EXTENSION_NAME = 'Premise Membership';
	const EXTENSION_ID = 'WPCW_Premise';
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialise using the parent constructor 
		parent::__construct(WPCW_Premise::EXTENSION_NAME, WPCW_Premise::EXTENSION_ID, WPCW_Premise::GLUE_VERSION);
	}
	
	
	/**
	 * Get the membership levels for this specific membership plugin.
	 */
	protected function getMembershipLevels()
	{
	$args=array(
  		'post_type' => 'acp-products',
  		'post_status' => 'publish',
  		'numberposts' => -1
	);
	
	$levelData = get_posts($args);
	
		if ($levelData && count($levelData) > 0)
		{
			$levelDataStructured = array();

			// Format the data in a way that we expect and can process
			foreach ($levelData as $levelDatum)
			{
				$levelItem = array();
				$levelItem['name'] 	= $levelDatum->post_title;
				$levelItem['id'] 	= $levelDatum->ID;
				$levelItem['raw'] 	= $levelDatum;

				$levelDataStructured[$levelItem['id']] = $levelItem;
			}
			return $levelDataStructured;
		}
		return false;
	}
	
	
	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	protected function attach_updateUserCourseAccess()
	{
		// Events called whenever the user products are changed, which updates the user access.
		add_action('premise_membership_create_order', array($this, 'handle_newUserCourseAccess'), 10, 3);
		add_action('admin_notices', array($this, 'handle_updateUserCourseAccess'), 10);

	}

	/**
	 * Assign selected courses to members of a paticular level.
	 * @param Level ID in which members will get courses enrollment adjusted.
	 */
	protected function retroactive_assignment($level_ID)
    {
    	global $wpdb;

    	$page = new PageBuilder(false);

    	//$args for query
		$args = array(
		    'numberposts' => -1,
		    'post_type' => 'acp-orders',
		    'meta_query' => array(
		        array(
		            'key' => '_acp_order_product_id',
		            'value' => $level_ID,
		            'type' => 'NUMERIC',
		            'compare' => '='
		        )
		    )
		);

		$order_posts = get_posts( $args );

		if ($order_posts){

			$user_orders = array();
			//get order ID's
			foreach ($order_posts as $key => $order_post){
				$user_orders[$key] = $order_post->ID;
			}

			$users_with_products = array();
			if ($user_orders){
				//get user's ID based on order ID
				foreach ($user_orders as $key => $user_order){
					$get_userid = get_post_meta($user_order, '_acp_order_member_id',true);
					$users_with_products[$key] = $get_userid;
				}
			}

			foreach ($users_with_products as $user){
				//enroll users
				$this->wpcw_enroll_users($user);
			}

		$page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            
        return;

		}else {
            $page->showMessage(__('No existing customers found for the specified product.', 'wp_courseware'));
        }
    }

	/**
	 * Functions just for handling the membership callback
	 * 
	 */
	public function handle_newUserCourseAccess($member_id)
	{
		$user = $member_id;
		$this->wpcw_enroll_users($user);
	}

	public function handle_updateUserCourseAccess()
	{


		if ( empty( $post->post_name ) && isset( $_GET['member'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce(  $_GET['_wpnonce'], 'comp-product-' .  $_GET['member'] ) ) {
			$user = (int) $_GET['member'];
		}else{
			$user = accesspress_get_custom_field( '_acp_order_member_id' );
		}

		$this->wpcw_enroll_users($user);
		
	}

	function wpcw_enroll_users($user){

		$user_products = array();
		$orders = (array) memberaccess_get_member_products( $user , 0 , true );
		foreach ($orders as $key => $order){
			$user_products[$key] = $order;
		}

		parent::handle_courseSync($user, $user_products);
	}
		
	
	/**
	 * Detect presence of the membership module.
	 */
	public function found_membershipTool()
	{
	     return function_exists( 'memberaccess_init' );
	}
}
?>