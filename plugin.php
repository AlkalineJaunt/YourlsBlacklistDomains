<?php
/*
Plugin Name: YourlsBlackListDomains
Plugin URI: https://github.com/apelly/YourlsBlacklistDomains
Description: Plugin which disallows blacklisted domains and bans the submitters IP address. GPL v3
Version: 0.03
Author: apelly
Author URI: http://len.io
*/
/*
Copyright(c) (2012) Aaron Pelly

License:
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Hook the custom function into the 'shunt_add_new_link' event
yourls_add_filter( 'shunt_add_new_link', 'apelly_blacklist_domain_root' );

// Hook the admin page into the 'plugins_loaded' event
yourls_add_action( 'plugins_loaded', 'apelly_blacklist_domain_add_page' );

// Get blacklisted domains from YOURLS options feature and compare with current domain address
function apelly_blacklist_domain_root ( $bol, $url ) {
	// Preserve the core pass-through value (false before 1.10.4, a sentinel since 1.10.4).
	$return = $bol;
	$domain_list = yourls_get_option ('apelly_blacklist_domain_list');
	if ( $domain_list ) {
		$domain_list = unserialize ( $domain_list );
		foreach($domain_list as $blacklisted_domain) {
			if (strpos($url,$blacklisted_domain)) {
				// Check if a YourlsBlacklistIPs is installed and active
				if (yourls_is_active_plugin( YOURLS_PLUGINDIR .'/BlackListIP/plugin.php' )) {
					$IP = yourls_get_IP();

					// IP blacklisted already?
					ludo_blacklist_ip_root( array( $IP ) );  //  <---- dies if ip is blacklisted

					// fetch the blacklisted IP addresses
					$IP_List = yourls_get_option ('ludo_blacklist_ip_liste');
					$IP_List = ( $IP_List ) ? ( unserialize ( $IP_List ) ):((array)NULL);

					// add this IP
					$Parsed_IP = ludo_blacklist_ip_Analyze_IP ( $IP ) ;
					if ( $Parsed_IP != "NULL" ) {
						$IP_List[] = $Parsed_IP ;
					}

					// Update the blacklist
					yourls_update_option ( 'ludo_blacklist_ip_liste', serialize ( $IP_List ) );
				}

				// stop
				//yourls_die( 'Blacklisted domain', 'Forbidden', 403 );
				return array(
					'status' => 'fail',
					'code'   => 'error:url',
					'message' => 'Blacklisted domain',
					'errorCode' => '403',
				);
			}
		}
	}
	return $return;
}

// Add admin page
function apelly_blacklist_domain_add_page () {
	yourls_register_plugin_page( 'apelly_blacklist_domain', 'Blacklist domains', 'apelly_blacklist_domain_do_page' );
}

// Display admin page
function apelly_blacklist_domain_do_page () {
	if( isset( $_POST['action'] ) && $_POST['action'] == 'blacklist_domain' ) {
		apelly_blacklist_domain_process ();
	} else {
		apelly_blacklist_domain_form ();
	}
}

// Display form to administrate blacklisted domains list
function apelly_blacklist_domain_form () {
	$nonce = yourls_create_nonce( 'blacklist_domain' ) ;
	$domain_list = yourls_get_option ('apelly_blacklist_domain_list','Enter domain addresses here, one per line');
	if ($domain_list != 'Enter domain addresses here, one per line' ){
		$domain_list_display = implode ( "\r\n" , unserialize ( $domain_list ) );
	}else{
		$domain_list_display = $domain_list;
	}
	echo <<<HTML
		<h2>BlackList domains</h2>
		<form method="post">

		<input type="hidden" name="action" value="blacklist_domain" />
		<input type="hidden" name="nonce" value="$nonce" />

		<p>Blacklist following domains</p>
		<p><textarea cols="60" rows="15" name="blacklist_form">$domain_list_display</textarea></p>

		<p><input type="submit" value="Save" /></p>
		</form>
HTML;
}

// Update blacklisted domains list
function apelly_blacklist_domain_process () {
	// Check nonce
	yourls_verify_nonce( 'blacklist_domain' ) ;

	// Update list
	$blacklist_form = explode ( "\r\n" , $_POST['blacklist_form'] ) ;
	yourls_update_option ( 'apelly_blacklist_domain_list', serialize($blacklist_form) );
	echo "Black list updated. New blacklist is " ;
	if ( count ( $blacklist_form ) == 0 )
		echo "empty.";
	else {
		echo ":<BR />";
		foreach ($blacklist_form as $value) echo $value."<BR />";
	}
}
?>
