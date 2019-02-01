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
    
    /**
     * Unsets all core WP endpoints registered by the WordPress REST API (via rest_endpoints filter)
     * @param  array   $endpoints   registered endpoints
     * @return array
     */
    public function removeWordpressCoreEndpoints( $endpoints ) {
    
        foreach ( array_keys( $endpoints ) as $endpoint ) {
            if ( stripos( $endpoint, '/wp/v2' ) === 0 ) {
                unset( $endpoints[ $endpoint ] );
            }
        }
    
        return $endpoints;
    }
}
?>