<?php
namespace IpswichJAFFARunningClubAPI;
	
class WordPressApiHelper 
{
    public function pluginsLoaded() {

        // enqueue WP_API_Settings script
        add_action( 'wp_print_scripts', function() {
            wp_enqueue_script( 'wp-api' );
        } );					
    }

    public function custom_wp_user_token_response ($data, $user) {      
        $data['isAdmin'] = (user_can($user, 'editor') || user_can($user, 'administrator')) ? true : false;
        return $data;
    }
}
?>