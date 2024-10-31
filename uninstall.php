<?php
    // if uninstall.php is not called by WordPress, die
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        die;
    }
    if (!defined('WP_UNINSTALL_PLUGIN')) exit;
    
    $options=['plinkpg_id_paymentflag','plinkpg_id_redirect','plinkpg_inqury_payment_status'];
    
    foreach($options as $option){
        delete_option($option);
        delete_site_option($option);
    }
    
    //disable routes
    add_filter('rest_endpoints', function( $endpoints ) {

        foreach( $endpoints as $route => $endpoint ){
            if( 0 === stripos( $route, '/plinkpg/' ) ){
                unset( $endpoints[ $route ] );
            }
        }
    
        return $endpoints;
    });
    
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}plinkpg");
    
    $wpdb->query("DELETE FROM ".$wpdb->posts." WHERE post_name IN ('plinkpg-redirection','plinkpg-paymentflag')");


?>