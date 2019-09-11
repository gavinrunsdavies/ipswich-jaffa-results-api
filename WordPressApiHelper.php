<?php
namespace IpswichJAFFARunningClubAPI;
	
class WordPressApiHelper 
{
    public function custom_wp_user_token_response ($data, $user) {      
        $data['isAdmin'] = (user_can($user, 'editor') || user_can($user, 'administrator')) ? true : false;
        return $data;
    }
}
?>