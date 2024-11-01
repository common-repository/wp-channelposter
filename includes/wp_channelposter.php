<?php

/*
 * @class - wp_cposter
 */

if( ! defined( 'WPCPOSTER_FILE' ) ) die( 'Silence ' );

if( ! class_exists('wp_cposter')):
class wp_cposter
{
	function __construct()
	{
		global $wpdb;

		//few definitions
		define( "WPCPOSTER_DIR" 			, plugin_dir_path( WPCPOSTER_FILE ) 		);
		define( "WPCPOSTER_URL"				, esc_url( plugins_url( '', WPCPOSTER_FILE ) ).'/');

		define( "WPCPOSTER_VER"				, "1.0.2" 							);
		define( "WPCPOSTER_DEBUG"			, false							);

		register_activation_hook( WPCPOSTER_FILE		, array( &$this, 'wpcposter_activate'	));
		register_deactivation_hook ( WPCPOSTER_FILE	, array( &$this, 'wpcposter_deactivate'	));

		add_action( 'admin_menu'			, array( &$this, 'wpcposter_options_page'		));
		add_filter( 'plugin_action_links'		, array( &$this, 'wpcposter_plugin_actions'	), 10, 2 );
	}

	function wpcposter_activate()
	{
		global $wpdb;

		if( ! $wpcposter_cron = get_option ("wpcposter_cron") )
		{
			$cron = '';
			foreach(range(1,5) as $a)
				$cron .= chr(mt_rand(97, 122));
			$cron = strtolower($cron);
			update_option ("wpcposter_cron", $cron);
		}

		if( ! $wpcposter_ver = get_option ("wpcposter_ver") )
			update_option ("wpcposter_ver", WPCPOSTER_VER);
	}

	function wpcposter_deactivate()
	{
		wp_clear_scheduled_hook( 'wpcposter_cronjob' );
		//nothing here//
	}

	static function wpcposter_footer() 
	{
		$plugin_data = get_plugin_data( WPCPOSTER_FILE );
		printf('%1$s plugin | Version %2$s | by %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']); 
	}

	static function wpcposter_footer_rating() 
	{
		$footer_text = sprintf(
			__( 'Please support <strong><em>WP ChannelPoster</em></strong> by leaving us a %s rating. A huge thanks in advance!', 'wpcposter_lang' ),
			'<a href="https://wordpress.org/support/plugin/wp-channelposter/reviews?rate=5#new-post" target="_blank" 
				class="wpcposter-rating-link">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
		);
		echo $footer_text;
	}

	static function wpcposter_page_footer() 
	{
		echo '<br/><div id="page_footer" class="postbox" style="text-align:center;padding:10px;clear:both"><em>';
			self::wpcposter_footer(); 
		echo '</em><br/>'."\n";

		echo '<div>';
			self::wpcposter_footer_rating(); 
		echo '</div>';

		echo '</div>';
	}

	function wpcposter_plugin_actions($links, $file)
	{
		if( strpos( $file, basename(WPCPOSTER_FILE)) !== false )
		{
			$link = '<a href="'.admin_url( 'options-general.php?page=wpcposter_main').'">'.__('Settings', 'wpcposter_lang').'</a>';
			array_unshift( $links, $link );
		}
		return $links;
	}

	function wpcposter_options_page()
	{
		global $wp_cposter_admin;
		add_options_page(__('WP Channnel Poster', 'wpcposter_lang'), __('WP Channnel Poster', 'wpcposter_lang'), 8, 'wpcposter_main', array( &$wp_cposter_admin, 'wpcposter_main' ) );
	}
}
endif;

require_once __DIR__.'/wp_channelposter_admin.php';

global $wp_cposter;
if( ! $wp_cposter ) $wp_cposter = new wp_cposter();