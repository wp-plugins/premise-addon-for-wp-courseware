<?php
/*
 * Plugin Name: WP Courseware - Premise Membership Add On
 * Version: 1.0
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add support for the Premise Membership membership plugin for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */
/*
 Copyright 2013 Fly Plugins

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */


// Main parent class
include_once 'class_members.inc.php';


// Hook to load the class
add_action('init', 'WPCW_Premise_init',1);

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
		add_action('premise_checkout_complete_after', 		array($this, 'handle_newUserCourseAccess'),10,3);
		add_action('memberaccess_edit_order', 		array($this, 'handle_updateUserCourseAccess'),10,3);


	}

	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 * 
	 * @param Integer $user is the ID if the user being created.
	 * @param Array $membership_level is level assigned to user.
	 */
	public function handle_newUserCourseAccess( $checkout_args, $product_id, $args)
	{
		// Get user ID from transaction
		$user_info = wp_get_current_user();
        $user = $user_info->ID;
        //Returns product the user has purchased and is paid up on.
		$membership_level = $product_id;
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($user, array($membership_level));
	}

	public function handle_updateUserCourseAccess( $post, $values, $old_values)
	{
		// Get user ID from change in order status
		$user = $old_values['_acp_order_member_id'] ;
        //Returns product the user has changed to.
		$membership_level = $values['_acp_order_product_id'];
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($user, array($membership_level));
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