<?php

/*
 * @class - wp_cposter_admin
 */

if( ! defined( 'WPCPOSTER_FILE' ) ) die( 'Silence ' );

if( ! class_exists('wp_cposter_admin')):
class wp_cposter_admin
{
	var $date_format;
	function __construct()
	{
		global $wpdb;
		$this->date_format					= 'j M Y h:i A';
		add_action( 'admin_head'				, array( &$this, 'wpcposter_admin_header'		));
		add_action( 'admin_notices'				, array( &$this, 'wpcposter_admin_notices'	));

		add_action( 'admin_enqueue_scripts'			, array( &$this, 'wpcposter_admin_style'		));
		add_action('wp_ajax_wpcposter_get_terms'		, array( &$this, 'wpcposter_ajax_get_terms'	));

		add_action( 'init'					, array( &$this, 'wpcposter_cron'			));
		add_action( 'wpcposter_cronjob'			, array( &$this, 'wpcposter_wpcronjob'		));

		add_shortcode( 'WPCPOSTER_TITLE'			, array( &$this, 'wpcposter_shortcode_title'	));
		add_shortcode( 'WPCPOSTER_CONTENT'			, array( &$this, 'wpcposter_shortcode_content'	));
		add_shortcode( 'WPCPOSTER_VIDEO'			, array( &$this, 'wpcposter_shortcode_video'	));
		add_shortcode( 'WPCPOSTER_DATE'			, array( &$this, 'wpcposter_shortcode_date'	));

		add_filter( 'the_content'				, array( &$this, 'wpcposter_the_content' 		));

		add_action( 'admin_init'				, array( &$this, 'wpcposter_activate' 		));
	}

	//options-general.php?page=wpcposter_main&activated=1
	function wpcposter_activate()
	{
		if( is_admin() && current_user_can('manage_options') && $_GET['page'] == 'wpcposter_main' && $_GET['activated'] == 1 ) {
			$wpcposter_options = get_option( "wpcposter_options" );
			$wpcposter_options['activated'] = 1;
			update_option( "wpcposter_options", $wpcposter_options );
			wp_safe_redirect( admin_url( 'options-general.php?page=wpcposter_main') );
			die();
		}
	}

	//debug//
	function wpcposter_the_content( $content = '' )
	{
		global $post;

		if( is_singular()){
			$vid_id = get_post_meta($post->ID, '_wpcposter_videoid', true ); 
			if( $vid_id )
				$content .= '<br/> <!-- '.$vid_id.' -->';
		}

		return $content;
	}

	/*
	*
	**/
	function wpcposter_admin_style()
	{
		if( is_admin() && strpos( $_GET['page'] , 'wpcposter' ) !== false )
		{
			wp_enqueue_style( 'wpcposter_css'	, WPCPOSTER_URL. 'assets/css/admin.css' );
			wp_enqueue_script( 'wpcposter_js'	, WPCPOSTER_URL.'assets/js/common.js', array('jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-autocomplete' ) );
		}
	}

	function wpcposter_admin_notices()
	{
		if( is_admin() && strpos( $_GET['page'] , 'wpcposter' ) !== false )
		{
			if( empty( $_POST ) && $playlist = get_option( 'wpcposter_playlists' ) && ! $fetch_fields = get_option( "wpcposter_fetch_fields" ) ) {
				?><div class="notice notice-error is-dismissible"><p><?php _e('<strong>WP ChannelPoster Error:</strong> Please select one or more Playlist below to start posting videos to your blog.', 'wpcposter_lang')?></p></div><?php
			}
		}
	}

	/*
	*
	**/
	function wpcposter_admin_header()
	{
		global $wpdb;

		if( is_admin() && strpos( $_GET['page'] , 'wpcposter' ) !== false )
		{
			?>
		<script type="text/javascript">
		if( typeof jQuery == 'function' ){
			jQuery(document).ready( function($){
				var ajax_nonce 		= '<?php echo wp_create_nonce( 'wpcposter_ajax' ); ?>';
				var ajaxurl 		= '<?php echo admin_url('admin-ajax.php') ?>';

				function split( val ) {
					return val.split( /,\s*/ );
				}
				function extractLast( term ) {
					return split( term ).pop();
				}

				$('body').on( 'change', '#wpcposter_type', function(event){
					$('.wpcposter_typediv').hide();
				/*	$('.wpcposter_typediv .regular-text').val(''); */
					val = $('#wpcposter_type option:selected').val();
					$('#'+val+'_div').slideDown();
				});
				$('#wpcposter_type').change();

				$("#wpcposter_playlist_tbl thead tr:last th:first input:checkbox").click(function() {
					var checkedStatus = this.checked;
					$("#wpcposter_playlist_tbl tbody tr td:first-child input:checkbox").each(function() {
						this.checked = checkedStatus;
					});
				});

				var searchRequest;
				$('.wpcposter_terms').autocomplete({
					minChars: 2,
					source: function(wpcposter_name, response) {
						try { searchRequest.abort(); } catch(err){}
						var search_term = wpcposter_name.term;
						if( wpcposter_name.term.indexOf( ',' ) > 0 ) {
							search_term = extractLast( wpcposter_name.term );
						}

						searchRequest = $.ajax({
							type: 'POST',
							dataType: 'json',
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							data: 'action=wpcposter_get_terms&terms='+search_term,
							success: function(data) {
								response(data);
							}
						});
					},
					search: function() {
						var term = extractLast( this.value );
						if ( term.length < 2 ) {
							return false;
						}
					},
					focus: function() {
						return false;
					},
					select: function( event, ui ) {
						var terms = split( this.value );
						terms.pop();
						terms.push( ui.item.value );
						terms.push( "" );
						this.value = terms.join( ", " );
						return false;
					}
				});

				$('.wpcposter_terms').on('blur', function(){
					$('.wpcposter_terms').autocomplete("close");
				});

				$("#tabs").tabs();

				$("#wpcposter_size_w").blur(function() {
					w = $('#wpcposter_size_w').val();
					if(!isNaN(w))
					{
						h = parseInt((385/480) * w, 10);
						$('#wpcposter_size_h').val(h);
					}
					else
					{
						alert("Please enter a valid number");
						$('#wpcposter_size_w').focus();
					}
					return false;
				});
			});
		}
		</script>
			<?php
		}
	}

	function wpcposter_ajax_get_terms()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		$out = array();

		if (!current_user_can('manage_options'))
		{
			$out = array();
			$out['msg'] = __('Sorry, but you have no permissions to change settings.');
			$out['err'] = __LINE__;
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}
	//	check_ajax_referer( "wpcposter_ajax" );

		if(!defined('DOING_AJAX')) define('DOING_AJAX', 1);
		set_time_limit(60);

		$html = "";

		$wpcposter_arr	= $out = array();

		$terms 	= (isset( $_POST['terms'] )?	trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['terms'] )))): ''  );
		if( empty( $terms ) || strlen( $terms ) < 3 )
		{
			$out = array();
			header( "Content-Type: application/json" );
			echo json_encode( $out );
			die();
		}

		set_time_limit(0);

		$get_terms = get_terms( 'category', array( 'search' => $terms, 'hide_empty' => false ) );
		$out = array();
		foreach( $get_terms as $get_term )
			$out[] = $get_term->name;

		header( "Content-Type: application/json" );
		echo json_encode( $out );
		die();
	}

	/*
	*
	**/
	function wpcposter_main()
	{
		global $wpdb, $current_user;
		get_currentuserinfo();

		if (!current_user_can('manage_options')) wp_die(__('Sorry, but you have no permissions to change settings.'));
		$error = $result = '';

		$wpcposter_options = get_option( "wpcposter_options" );
		if( isset( $_POST['call'] ) && $_POST['call'] == 'wpcposter_saveapi' )
		{
//$this->reset();
			check_admin_referer('wpcposter-saveapi');
			$wpcposter_api 		= (isset( $_POST['wpcposter_api'] )?	trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['wpcposter_api'] )))): ''  );
			if( empty( $wpcposter_api ) )
				$error = __('Please enter a Youtube API key.','wpcposter_lang');
			else
				$wpcposter_options['api'] = $wpcposter_api;

			$wpcposter_options['type'] = $wpcposter_options['username'] = $wpcposter_options['channelid'] = $wpcposter_options['playlistid'] = '';

			if( isset( $_POST['wpcposter_type'] ) && ! empty( $_POST['wpcposter_type'] ) ) {
				if( trim( $_POST['wpcposter_type'] ) == 'username' && isset( $_POST['wpcposter_username'] ) && ! empty( $_POST['wpcposter_username'] )) {
					$wpcposter_options['type'] 		= 'username';
					$wpcposter_options['username'] 	= trim( sanitize_text_field( $_POST['wpcposter_username'] ));
					if( empty( $wpcposter_options['username'] ) )
						$error = __('Please enter a Youtube UserName.','wpcposter_lang');
				}
				else if( trim( $_POST['wpcposter_type'] ) == 'channelid' && isset( $_POST['wpcposter_channelid'] ) && ! empty( $_POST['wpcposter_channelid'] )) {
					$wpcposter_options['type'] 		= 'channelid';
					$wpcposter_options['channelid'] 	= trim( sanitize_text_field( $_POST['wpcposter_channelid'] ));
					if( empty( $wpcposter_options['channelid'] ) )
						$error = __('Please enter a Youtube Channel Id.','wpcposter_lang');
				}
				else if( trim( $_POST['wpcposter_type'] ) == 'playlistid' && isset( $_POST['wpcposter_playlistid'] ) && ! empty( $_POST['wpcposter_playlistid'] )) {
					$wpcposter_options['type'] 		= 'playlistid';
					$wpcposter_options['playlistid'] 	= trim( sanitize_text_field( $_POST['wpcposter_playlistid'] ));
					if( empty( $wpcposter_options['playlistid'] ) )
						$error = __('Please enter a Youtube Playlist Id.','wpcposter_lang');
				}
			}

			if( empty( $wpcposter_options['type'] ) )
				$error = __('Please choose type and enter a valid Youtube.com Username or a ChannelId or a PlayList ID.','wpcposter_lang');

			if( empty( $error ) )
			{
				$wpcposter_options['process_key'] = 0;
				update_option( "wpcposter_options"	, $wpcposter_options );
				delete_option( 'wpcposter_channel' );
				delete_option( 'wpcposter_playlists' );
				delete_option( "wpcposter_fetch_fields" );

				$ret = $this->get_youtube_channel_list();
				if( ! empty( $ret ) )
					$error = implode( "<br/>", $ret );
				else
					$result = __('Settings have been saved. Feed processed','wpcposter_lang');
			}
		}
		else if( isset( $_POST['call'] ) && $_POST['call'] == 'wpcposter_savechannels' )
		{
			check_admin_referer('wpcposter-savechannels');

			/**
			*
			* $_POST['wpcposter_check'], AND $_POST['wpcposter_terms'] are arrays, 
			* 
			* Each element sanitized individually below;
			*
			**/
			if( empty( $_POST['wpcposter_check'] ) ){
				$error = __('Please check at least one channel or playlist.','wpcposter_lang');
			}
			else
			{
				$new_wpcposter_check = array();
				foreach( $_POST['wpcposter_check'] as $ii => $wpcposter_c ){
					$key = trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $wpcposter_c ) ) ) );
					$val = trim( sanitize_text_field( wp_strip_all_tags( stripslashes( $_POST['wpcposter_terms'][$ii] ) ) ), ',' );
					$new_wpcposter_check[$key] = array( $val, '' );
				}
			}
			if( empty( $error ) )
			{
				$wpcposter_options['process_key'] = 0;
				update_option( "wpcposter_options", $wpcposter_options );

				$fetch_fields = json_encode( $new_wpcposter_check );
				update_option( "wpcposter_fetch_fields"	, $fetch_fields );
				$result = __('Settings have been saved','wpcposter_lang');
			}
		}
		else if( isset( $_POST['call'] ) && $_POST['call'] == 'wpcposter_publish' )
		{
			check_admin_referer('wpcposter-publish');

			$wpcposter_options['size_w'] 		= (isset( $_POST['wpcposter_size_w'] )? 		(int)trim( sanitize_text_field( $_POST['wpcposter_size_w'] )): '480'  );
			$wpcposter_options['size_h'] 		= (isset( $_POST['wpcposter_size_h'] )? 		(int)trim( sanitize_text_field( $_POST['wpcposter_size_h'] )): '385'  );
			$wpcposter_options['publish_cnt'] 	= (isset( $_POST['wpcposter_publish_cnt'] )? 	(int)trim( sanitize_text_field( $_POST['wpcposter_publish_cnt'] )): '5'  );
			$wpcposter_options['author'] 		= (isset( $_POST['wpcposter_author'] )? 		(int)trim( sanitize_text_field( $_POST['wpcposter_author'] )): 1  );
			$wpcposter_options['content']		= (isset( $_POST['wpcposter_content'] )? 		trim( wp_kses_post( $_POST['wpcposter_content'] )): ''  );

			$wpcposter_options['enable_cron']	= (isset( $_POST['wpcposter_enable_cron'] ) && trim( $_POST['wpcposter_enable_cron'] ) == 1? 1: 0  );
			$wpcposter_options['enable_cron_hour']= (isset( $_POST['wpcposter_enable_cron_hour'] )? (int)trim( wp_kses_post( $_POST['wpcposter_enable_cron_hour'] )): 6  );

			update_option( "wpcposter_options"	, $wpcposter_options );
				$result = __('Settings have been saved','wpcposter_lang');

			if( $wpcposter_options['enable_cron'] == 1 ){
				if( !wp_next_scheduled( 'wpcposter_cronjob' ) ) {
					wp_schedule_event( time(), 'hourly', 'wpcposter_cronjob' );
				}
			}
			else {
				if( false !== ( $time = wp_next_scheduled( 'wpcposter_cronjob' ) ) ) {
					wp_unschedule_event( $time, 'wpcposter_cronjob' );
				}
			}
		}
?>
		<div class="wrap">
		<h2><?php _e( 'WP ChannelPoster', 'wpcposter_lang' ); ?></h2>
<?php

if($error)
{
?>
<div class="notice notice-error is-dismissible"><p><b><?php _e('Error: ', 'wpcposter_lang')?></b><?php echo $error;?></p></div>
<?php
}

if($result)
{
?>
<div id="message" class="notice notice-success is-dismissible"><p><?php echo $result; ?></p></div>
<?php
}
?>
	<style>.hl{font-style:italic; background-color:#ffff23;}</style>
	<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
	<div id="post-body-content">

	<?php if( empty( $wpcposter_options['activated'] ) ) { ?>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wpcposter_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Activate Plugin', 'wpcposter_lang' ); ?></span></h3>
	      <div class="inside">

<!-- AWeber Web Form Generator 3.0.1 -->

<form method="post" class="af-form-wrapper" accept-charset="UTF-8" action="https://www.aweber.com/scripts/addlead.pl"  >
<div style="display: none;">
<input type="hidden" name="meta_web_form_id" value="1111820190" />
<input type="hidden" name="meta_split_id" value="" />
<input type="hidden" name="listname" value="awlist5365612" />
<input type="hidden" name="redirect" value="<?php echo admin_url( 'options-general.php?page=wpcposter_main&activated=1');?>" id="redirect_4797ef4317f96a1ce5f3d3eccecc76fe" />
<input type="hidden" name="meta_adtracking" value="Amplified" />
<input type="hidden" name="meta_message" value="1" />
<input type="hidden" name="meta_required" value="name,email" />
<input type="hidden" name="meta_tooltip" value="" />
</div>
<div id="af-form-1111820190" class="af-form">
<!-- <div id="af-header-1111820190" class="af-header"><div class="bodyText"><p>&nbsp;</div></div>  -->
<div id="af-body-1111820190" class="af-body af-standards">

<table class="widefat">
<tr>
	<th><div class="af-element"><label class="previewLabel" for="awf_field-104139740"><strong><?php _e( 'Name: ', 'wpcposter_lang' );?></strong></label></div></th>
	<td>
	<div class="af-textWrap">
	<input id="awf_field-104139740" type="text" name="name" class="text regular-text" value=""  onfocus=" if (this.value == '') { this.value = ''; }" onblur="if (this.value == '') { this.value='';} " tabindex="500" />
	</div>
	</td>
</tr>
<tr>
	<th><div class="af-element"><label class="previewLabel" for="awf_field-104139741"><strong><?php _e( 'Email: ', 'wpcposter_lang' );?></strong></label></div></th>
	<td>
	<div class="af-textWrap"><input class="text regular-text" id="awf_field-104139741" type="text" name="email" value="" tabindex="501" onfocus=" if (this.value == '') { this.value = ''; }" onblur="if (this.value == '') { this.value='';} " />
	</div>
	</td>
</tr>

<tr class="af-element buttonContainer">
	<td colspan="2"  style="border-bottom:1px solid #ccc">
	<input name="submit" class="submit button button-primary" type="submit" value="Submit" tabindex="502" />&nbsp;&nbsp;
	<span style="color:#ccc;vertical-align:bottom;"><a style="opacity:0.7;" href="<?php echo admin_url( 'options-general.php?page=wpcposter_main&activated=1');?>"><?php _e('SKIP','wpcposter_lang') ?></a></span>
	</td>
</tr>
<tr>
	<td colspan="2" style="border-bottom:1px solid #ccc"><div class="af-element privacyPolicy" style="text-align: center"><p>We respect your <a title="Privacy Policy" href="https://www.aweber.com/permission.htm" target="_blank" rel="nofollow">email privacy</a></p></td>
</tr>
<tr>
	<td colspan="2" style="border-bottom:1px solid #ccc"><div class="af-element poweredBy" style="text-align: center; font-size: 9px;"><p><a href="https://www.aweber.com" title="AWeber Email Marketing" target="_blank" rel="nofollow">Powered by AWeber Email Marketing</a></p></td>
</tr>

</table>
</div>
<div id="af-footer-1111820190" class="af-footer"><div class="bodyText"><p>&nbsp;</p></div></div>
</div>
</form>
<script type="text/javascript">
    <!--
    (function() {
        var IE = /*@cc_on!@*/false;
        if (!IE) { return; }
        if (document.compatMode && document.compatMode == 'BackCompat') {
            if (document.getElementById("af-form-1111820190")) {
                document.getElementById("af-form-1111820190").className = 'af-form af-quirksMode';
            }
            if (document.getElementById("af-body-1111820190")) {
                document.getElementById("af-body-1111820190").className = "af-body inline af-quirksMode";
            }
            if (document.getElementById("af-header-1111820190")) {
                document.getElementById("af-header-1111820190").className = "af-header af-quirksMode";
            }
            if (document.getElementById("af-footer-1111820190")) {
                document.getElementById("af-footer-1111820190").className = "af-footer af-quirksMode";
            }
        }
    })();
    -->
</script>

<!-- /AWeber Web Form Generator 3.0.1 -->
		</div>
		</div>
	<?php 
		}
		else 
		{ 
	?>

	<div id="tabs">
	<ul id="wpcposter_ul">
		<li><a href="#general_settings"><?php _e('General Settings', 'wpcposter_lang' ); ?></a></li>
		<li><a href="#publish_settings"><?php _e('Publish Settings', 'wpcposter_lang' ); ?></a></li>
	</ul>

	<div id="general_settings">

	<form method="post" id="wpcposter_saveapi" name="wpcposter_saveapi">
	<?php  wp_nonce_field( 'wpcposter-saveapi' ); ?>
	<input type="hidden" name="call" value="wpcposter_saveapi"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wpcposter_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Settings', 'wpcposter_lang' ); ?></span></h3>
	      <div class="inside">
			<table border="0" cellpadding="3" cellspacing="2" class="form-table" width="100%">
			<tr>
			<th><label for="wpcposter_api"><?php _e( 'Youtube API Key: ','wpcposter_lang' );?></th>
			<td><input type="text" name="wpcposter_api" id="wpcposter_api" value="<?php echo esc_attr( $wpcposter_options['api'] );?>" class="regular-text"/><br/>
			<span class="description"><?php printf( __('Please enter Youtube.com API Key. You can get a API key from %shere%s','wpcposter_lang'), '<a href="https://console.developers.google.com/" target="_blank">','</a>');?></span>
			</tr>
			<tr>
			<th><label for="wpcposter_username"><?php _e( 'Youtube : ','wpcposter_lang' );?></th>
			<td>
				<select name="wpcposter_type" id="wpcposter_type">
				<option value="channelid" <?php selected( 'channelid', $wpcposter_options['type'] );?>>ChannelID</option>
				<option value="playlistid" <?php selected( 'playlistid', $wpcposter_options['type'] );?>>PlayList</option>
				<option value="username" <?php selected( 'username', $wpcposter_options['type'] );?>>Username</option>
				</select><br/>
				<div id="channelid_div" class="wpcposter_typediv">
			<label><strong><?php _e('Channel ID','wpcposter_lang')?></strong><br/>
			<input type="text" name="wpcposter_channelid" id="wpcposter_channelid" value="<?php echo esc_attr( $wpcposter_options['channelid'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com Channel ID to import playlists.','wpcposter_lang');?></span><br/>
			<span class="description"><?php _e('You can get Channel ID from the URL of the channel <code>https://www.youtube.com/channel/<strong>UCEXyTZGxffQZjDwq6h2Gldw</strong></code>','wpcposter_lang');?></span>
				</div>

				<div id="playlistid_div" style="display:none" class="wpcposter_typediv">
			<label><strong><?php _e('Playlist ID','wpcposter_lang')?></strong><br/>
			<input type="text" name="wpcposter_playlistid" id="wpcposter_playlistid" value="<?php echo esc_attr( $wpcposter_options['playlistid'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com playlist ID.','wpcposter_lang');?></span><br/>
			<span class="description"><?php _e('You can get PlayList ID from the URL of the channel <code>https://www.youtube.com/playlist?list=<strong>LLDn1pOvN2Ni1Cg_Z4AfmdRg</strong></code>','wpcposter_lang');?></span>
				</div>

				<div id="username_div" style="display:none" class="wpcposter_typediv">
			<label><strong><?php _e('Your Username','wpcposter_lang')?></strong><br/>
			<input type="text" name="wpcposter_username" id="wpcposter_username" value="<?php echo esc_attr( $wpcposter_options['username'] );?>" class="regular-text"/></label><br/>
			<span class="description"><?php _e('Please enter Youtube.com username to import channels and playlists.','wpcposter_lang');?></span><br/>
			<span class="description"><?php _e('You can get User ID from the URL <code>https://www.youtube.com/user/<strong>GoogleDevelopers</strong></code>','wpcposter_lang');?></span>
				</div>
			</td>
			</tr>
			</table>
	      </div>
	    </div>
		<?php  
			if( ! empty( $wpcposter_options['api'] ) ) {
			?>
			<p class="description notice notice-info is-dismissible"><br/>
				<strong><?php _e('Note','wpcposter_lang' );?></strong><br/>
				<span><?php _e('1. Click Save to refresh the list below. This will only refresh the channel Info, and playlist info. New Videos will still be processed.','wpcposter_lang');?></span><br/>
				<span><?php _e('2. Changing the Channel ID above will not delete already published videos. New Videos will be published from the new ChannelID','wpcposter_lang');?></span><br/>
			</p>
			<?php
			}
			submit_button(__(' Save ', 'wpcposter_lang' )); 
		?>
	  </form>
		<hr/>
		<?php
			if( ! empty( $wpcposter_options['api'] ) ) {
				$channel = $playlist_arr = $fetch_fields = array();
				if( $channel = get_option( 'wpcposter_channel' ) ) {
					$channel = json_decode( $channel, true );

					if( $playlist_arr = get_option( 'wpcposter_playlists' ) )
						$playlist_arr = json_decode( $playlist_arr, true );

					if( $fetch_fields = get_option( "wpcposter_fetch_fields" ) ){
						$fetch_fields = json_decode( $fetch_fields, true );
						$fetch_fields_keys = array_keys( $fetch_fields );
					}else{
						$fetch_fields = array();
						$fetch_fields_keys = array();
					}
		?>
	<form method="post" id="wpcposter_savechannels" name="wpcposter_savechannels">

	<?php  wp_nonce_field( 'wpcposter-savechannels' ); ?>
	<input type="hidden" name="call" value="wpcposter_savechannels"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wpcposter_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php printf( __( 'PlayLists for Channel: %s', 'wpcposter_lang' ), esc_attr( $channel['title'] ) ); ?></span><br/>
		<span class="description"><?php _e('Check the playlists you want to publish videos from and hit save at the bottom.','wpcposter_lang');?></span></h3>
	      <div class="inside">

			<table border="0" cellpadding="3" cellspacing="2" class="widefat form-table" id="wpcposter_playlist_tbl">
			<thead>
			<tr id="<?php echo esc_attr( $channel['id'])?>" style="background-color:#efefef;">
			<th style="width:40px!important;"><input type="checkbox" name="" value="" /></th>
			<th style="width:90px!important;"><img src="<?php echo esc_url( $channel['thumbnail'][0] )?>" border="0"/></th>
			<th style="width:80%!important;">
				<div class="wpcposter_alignleft alignleft" style="width:90%;">
					<strong>
						<a target="_blank" href="<?php echo esc_url( $channel['customurl'] )?>"><?php echo esc_attr( $channel['title'] );?></a> 
						 (<i class="dashicons dashicons-video-alt"></i> <?php echo esc_attr( $channel['vid_count'])?>)
					</strong><br/><span>[Channel]</span>
					<p><?php echo ( $channel['description']? esc_html( $channel['description'] ): esc_attr( $channel['title'] ) );?></p>
				</div>
			</th>
			</tr>
			</thead>
			<tbody>
		<?php
			if( ! empty( $playlist_arr ) ){
			foreach( $playlist_arr as $item ) {
		?>
			<tr id="<?php echo $item['id']?>">
			<td><input type="checkbox" name="wpcposter_check[<?php echo esc_attr($item['id'])?>]" value="playlist-<?php echo esc_attr($item['id'])?>"
				<?php echo ( in_array( 'playlist-'.$item['id'], $fetch_fields_keys )? ' checked="checked"':'');?>/></td>
			<td><img src="<?php echo esc_url( $item['thumbnail'][0] )?>" border="0"/></td>
			<td>
				<div class="wpcposter_alignleft alignleft" style="width:90%;">
					<strong>
						<a target="_blank" href="<?php echo esc_url( 'https://www.youtube.com/playlist?list='.$item['id'] )?>"><?php echo esc_attr( $item['title'] );?></a>
						 (<i class="dashicons dashicons-video-alt"></i> <?php echo esc_attr( $item['vid_count'])?>)
					</strong>
					<p><?php echo ( $item['description']? esc_html( $item['description'] ): esc_attr( $item['title'] ) );?></p>
					<p><label><strong><?php _e('Categories: ', 'wpcposter' );?></strong> <input type="text" class="regular-text wpcposter_terms" name="wpcposter_terms[<?php echo esc_attr($item['id'])?>]" 
						value="<?php echo $fetch_fields['playlist-'.$item['id']][0]?>" placeholder="Categories"/></label><br/>
					<span class="description"><?php _e('Publish Posts from this playlist under these Categories. Enter comma separated categories','wpcposter_lang');?></span>

					</p>
				</div>
			</td>
			</tr>
		<?php }} ?>
			</table>
	      </div>
	    </div>
		<?php 
			submit_button(' Save ');
		} ?>

	  </form>
		<?php 
		} //if channel//
		?>


	</div>


	<?php

		if( empty( $wpcposter_options['size_w'] ) ) $wpcposter_options['size_w'] = '480';
		if( empty( $wpcposter_options['size_h'] ) ) $wpcposter_options['size_h'] = '385';

		if( empty( $wpcposter_options['publish_cnt'] ) ) $wpcposter_options['publish_cnt'] = 5;
		if( empty( $wpcposter_options['content'] ) ) $wpcposter_options['content'] = "[WPCPOSTER_VIDEO]
[WPCPOSTER_DATE]

[WPCPOSTER_CONTENT]
";

	?>
	<div id="publish_settings">

	<form method="post" id="wpcposter_publish" name="wpcposter_publish">
	<?php  wp_nonce_field( 'wpcposter-publish' ); ?>
	<input type="hidden" name="call" value="wpcposter_publish"/>

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wpcposter_lang' ); ?>"><br /></div>
	      <h3 class='hndle'><span><?php _e( 'Settings', 'wpcposter_lang' ); ?></span></h3>
	      <div class="inside">
			<table border="0" cellpadding="3" cellspacing="2" class="form-table" width="100%">

		<tr valign="top">
		<th scope="row"><label for="wpcposter_size_w"><?php _e('Video Size:', 'wpcposter_lang')?></label></th>
		<td>
		<input type="number" min="1" name="wpcposter_size_w" id="wpcposter_size_w" value="<?php echo $wpcposter_options['size_w'];?>" style="width:60px" class="regular-text"/>px X 
		<input type="number" min="1" name="wpcposter_size_h" id="wpcposter_size_h" value="<?php echo $wpcposter_options['size_h'];?>" style="width:60px" class="regular-text"/>px
		<br/><span class="description"><?php _e('Recommended: 480px x 385px', 'wpcposter_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wpcposter_publish_cnt"><?php _e('Publish Count:', 'wpcposter_lang')?></label></th>
		<td><input type="number" min="1" max="10" step="1" name="wpcposter_publish_cnt" id="wpcposter_publish_cnt" value="<?php echo $wpcposter_options['publish_cnt'];?>" class="regular-text"/>
		<br/><span class="description"><?php _e('Publish X number of videos per cron call', 'wpcposter_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wpcposter_author"><?php _e('Post Author:', 'wpcposter_lang')?></label></th>
		<td><?php wp_dropdown_users( array( 'name' => 'wpcposter_author', 'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ), 'selected'=> $wpcposter_options['author'] ) ); ?>
		<br/><span class="description"><?php _e('Select the author of the published post', 'wpcposter_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wpcposter_content"><?php _e('Post Content:', 'wpcposter_lang')?></label></th>
		<td> <?php wp_editor( $wpcposter_options['content'], 'wpcposter_content' ); ?> 
		<br/><span class="description"><?php _e('Enter the content of the post. You could use the following Shortcodes in the content. <br/>[WPCPOSTER_TITLE], [WPCPOSTER_CONTENT], [WPCPOSTER_VIDEO], [WPCPOSTER_DATE]', 'wpcposter_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row"><label for="wpcposter_enable_cron"><?php _e('Schedule internal cron:', 'wpcposter_lang')?></label></th>
		<td><input type="checkbox" name="wpcposter_enable_cron" id="wpcposter_enable_cron" value="1" <?php checked( $wpcposter_options['enable_cron'], "1" );?>/>
			<input type="number" min="1" max="24" step="1" name="wpcposter_enable_cron_hour" id="wpcposter_enable_cron_hour" value="<?php echo $wpcposter_options['enable_cron_hour'];?>" class="regular-text"/> Hours

		<br/><span class="description"><?php _e('Enable Wordpress cron. This will run internally X hours as specified above. Setting up of a Unix Cron job is not required.', 'wpcposter_lang');?></span>
		<br/><span class="description"><?php _e('Alternately You could choose to set up a Unix cron job using the command below.', 'wpcposter_lang');?></span>
		</td></tr>

		<tr valign="top">
		<th scope="row" width="25%"><label for="wpcposter_cron_url"><?php _e('Unix cron URL', 'wpcposter_lang')?></label></th>
		<td width="75%">
		<input style="width:450px" class="regular-text" type="text" name="wpcposter_cron_url" id="wpcposter_cron_url" value="<?php echo home_url('/?wpcposter_cron='. get_option("wpcposter_cron"));?>" onclick="this.select()" readonly="readonly"/>
		<br/><span class="description"><?php _e('Please use the above URL to set up a cron job from your servers control panel.',"wpcposter_lang") ?></span>

		<br/><?php _e("Example: ", "wpcposter_lang") ?><br/><input style="width:450px" class="regular-text" type="text" name="wpcposter_cron_url" id="wpcposter_cron_url" value="wget -q -O /dev/null <?php echo home_url('/?wpcposter_cron='. get_option("wpcposter_cron"))?>" onclick="this.select()" readonly="readonly"/>
		</td></tr>
			</table>
	      </div>
	    </div>
		<?php submit_button(' Save '); ?>
	  </form>
	  <hr class="clear" />

	</div>
	</div><!-- tabs -->
	  <hr class="clear" />

		<?php  } ?>


	</div><!-- /post-body-content -->

	<div id="postbox-container-1" class="postbox-container">

	    <div id="settingdiv" class="postbox"><div class="handlediv" title="<?php _e( 'Click to toggle', 'wpcposter_lang' ); ?>"><br /></div>
	      <div class="inside">
			<center><a href="http://amplifiedtrafficsystem.com/" target="_blank"><img src="<?php echo WPCPOSTER_URL.'assets/img/ats_250.png'; ?>" border="0"/></a></center>
		</div>
	   </div>

	</div><!-- postbox-container-1 -->


	</div><!-- /post-body -->

	<br class="clear" />

	</div><!-- /poststuff -->
		</div><!-- /wrap --><br/>
	<?php
		wp_cposter::wpcposter_page_footer();
	} 

	function get_youtube_channel_list()
	{
		global $wpdb;

		$wpcposter_options = get_option( "wpcposter_options" );
		$wpcposter_content_transient = ''; //get_transient( 'wpcposter_content_transient' );
		$ret = array();
		if( empty( $wpcposter_content_transient )  )
		{
			if( $wpcposter_options['type'] == 'username' || $wpcposter_options['type'] == 'channelid' ) {
				if( $xx = $this->get_channel_info() )
					$ret[] = $xx;

				if( $xx = $this->get_playlist_info() )
					$ret[] = $xx;
			}
			else if( $wpcposter_options['type'] == 'playlistid' ) {
				if( $xx = $this->get_playlist_info( $wpcposter_options['playlistid'] ) )
					$ret[] = $xx;
			}
			set_transient( "wpcposter_content_transient", '_wpcposter_content_transient', (10*DAY_IN_SECONDS) );
		}
		return $ret;
	}

	/**
	*
	* Given either a Username or a channeldi, get info about channel;
	*
	*
	**/
	function get_channel_info()
	{
		global $wpdb;

		$wpcposter_options = get_option( "wpcposter_options" );

		if( empty( $wpcposter_options['api'] ) )
			return false;

		if( ! empty( $wpcposter_options['username'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&forUsername=%s&key=%s&maxResults=50", 
						$wpcposter_options['username'], 
						$wpcposter_options['api'] 
				);
		}
		else if( ! empty( $wpcposter_options['channelid'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&id=%s&key=%s&maxResults=50", 
						$wpcposter_options['channelid'], 
						$wpcposter_options['api'] 
				);
		}
		else 
			return false;

		$ret = wp_remote_fopen($url);

		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$err = sprintf( __("Fetching Channel: Recieved an empty response from youtube.com <!-- %s -->", 'wpcposter_lang'), $url );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( isset( $ret['error'] ) ){
			$err = sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wpcposter_lang'), $url, $ret['error']['code'], $ret['error']['message'] );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		$ret 	= $ret['items'][0];
		if( ! empty( $ret ) ) {
			$channel 			= array();
			$channel['id'] 		= sanitize_text_field( $ret['id'] );
			$channel['title'] 	= sanitize_text_field( $ret['snippet']['title'] );
			$channel['description'] = sanitize_textarea_field( $ret['snippet']['description'] );
			$channel['customurl'] 	= ( $ret['snippet']['customUrl']? 'https://www.youtube.com/user/'.sanitize_text_field( $ret['snippet']['customUrl'] ): esc_url( 'https://www.youtube.com/channel/'.esc_attr($ret['id'] )) );
			$channel['thumbnail'] 	= array( esc_url( $ret['snippet']['thumbnails']['default']['url'] ), (int)$ret['snippet']['thumbnails']['default']['width'], (int)$ret['snippet']['thumbnails']['default']['height'] );
			$channel['vid_count']	= sanitize_text_field( $ret['statistics']['videoCount'] );

			update_option( 'wpcposter_channel', json_encode( $channel ) );

			if( empty( $wpcposter_options['channelid'] ) ) {
				$wpcposter_options['channelid'] = sanitize_text_field( $ret['id'] );
				update_option( "wpcposter_options"	, $wpcposter_options );
			}

			if( empty( $wpcposter_options['username'] ) && ! empty( $ret['snippet']['customUrl'] ) ) {
				$wpcposter_options['username'] = sanitize_text_field( $ret['snippet']['customUrl'] );
				update_option( "wpcposter_options"	, $wpcposter_options );
			}
			if( isset( $ret['contentDetails']['relatedPlaylists']['uploads'] ) ){
				$this->get_playlist_info( $ret['contentDetails']['relatedPlaylists']['uploads'] );
			}
		}
 	}
 
	/**
	*
	* Given a channelid, get all playlists in the channel;
	*
	*
	**/
	function get_playlist_info( $playlist_id = '' )
	{
		global $wpdb;

		$wpcposter_options = get_option( "wpcposter_options" );

		if( empty( $wpcposter_options['api'] ) )
			return false;

		if( ! empty( $playlist_id ) ){
			$url = sprintf( "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&id=%s&key=%s&maxResults=50", 
						$playlist_id, 
						$wpcposter_options['api'] 
				);
		}
		else if( ! empty( $wpcposter_options['channelid'] ) ) {
			$url = sprintf( "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&channelId=%s&key=%s&maxResults=50", 
						$wpcposter_options['channelid'], 
						$wpcposter_options['api'] 
				);
		}
		else{
			return false;
		}

		$ret 	= wp_remote_fopen($url);

		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$err = sprintf( __("Fetching Playlist: Recieved an empty response from youtube.com <!-- %s -->", 'wpcposter_lang'), $url );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( isset( $ret['error'] ) ){
			$err = sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wpcposter_lang'), $url, $ret['error']['code'], $ret['error']['message'] );
			$this->log( __FUNCTION__, __LINE__, $err );
			return $err;
		}

		if( ! $playlist_arr = get_option( 'wpcposter_playlists' ) )
			$playlist_arr = array();
		else
			$playlist_arr = json_decode( $playlist_arr, true );

		$channel_id = '';
		if( ! empty( $ret ) && ! empty( $ret['items'] ) ) {
			foreach( $ret['items'] as $ii => $item ) {
				$playlist 			= array();
				$playlist['id'] 		= sanitize_text_field( $item['id'] );
				$playlist['title'] 	= sanitize_text_field( $item['snippet']['title'] );
				$playlist['description']= sanitize_textarea_field( $item['snippet']['description'] );
				$playlist['thumbnail'] 	= array( esc_url( $item['snippet']['thumbnails']['default']['url'] ), (int)$item['snippet']['thumbnails']['default']['width'], (int)$item['snippet']['thumbnails']['default']['height'] );
				$playlist['vid_count']= sanitize_text_field( $item['contentDetails']['itemCount'] );

				if( isset( $item['snippet']['channelId'] ) )
					$channel_id = sanitize_text_field( $item['snippet']['channelId'] );

				$playlist_arr[] = $playlist;
			}
		}

		if( ! empty( $playlist_arr ) ) {
			update_option( 'wpcposter_playlists', json_encode( $playlist_arr ) );

			if( empty( $wpcposter_options['channelid'] ) && ! empty( $channel_id ) ) {
				$wpcposter_options['channelid'] = $channel_id;
				update_option( "wpcposter_options"	, $wpcposter_options );

				if( ! $channel = get_option( 'wpcposter_channel' ) ){
					$this->get_channel_info();
				}
			}
		}
	} 

	function get_videos_by_playlistid( $playlist_id, $next_pagetoken = '' )
	{
		global $wpdb;

		$wpcposter_options = get_option( "wpcposter_options" );

		if( empty( $wpcposter_options['api'] ) )
			return false;

		$wpcposter_options['publish_cnt'] = ( $wpcposter_options['publish_cnt']? $wpcposter_options['publish_cnt']: 5 );


		$playlist_id = str_replace( 'playlist-', '', $playlist_id );
		$url = sprintf( "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=%s&key=%s&maxResults=%d", 
						$playlist_id, 
						$wpcposter_options['api'],
						$wpcposter_options['publish_cnt'] 
				);

		if( ! empty( $next_pagetoken ) )
			$url .= '&pageToken='.$next_pagetoken;

		$ret = wp_remote_fopen($url);
		if( ! empty( $ret ) )
			$ret = json_decode( $ret, true );

		if( empty( $ret ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("URL: %s; Empty return", 'wpcposter_lang'), $url ) );
			return false;
		}

		if( isset( $ret['error'] ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("<!-- URL: %s; <br/> -->Error: %s %s", 'wpcposter_lang'), $url, $ret['error']['code'], $ret['error']['message'] ) );
			return false;
		}

		$nextPageToken = '';
		if( isset( $ret['nextPageToken'] ) )
			$nextPageToken = $ret['nextPageToken'];

		$video_arr = array();
		if( ! empty( $ret ) && ! empty( $ret['items'] ) ) {
			foreach( $ret['items'] as $ii => $item ) {
				$video 			= array();
				$video['id'] 		= sanitize_text_field( $item['snippet']['resourceId']['videoId'] );
				$video['title'] 		= sanitize_text_field( $item['snippet']['title'] );
				$video['description']= sanitize_textarea_field( $item['snippet']['description'] );

				$imagesize = '';
				if( ! empty( $item['snippet']['thumbnails']['standard'] ) && ! empty( $item['snippet']['thumbnails']['standard']['url'] ))
					$imagesize = 'standard';
				else if( ! empty( $item['snippet']['thumbnails']['high'] ) && ! empty( $item['snippet']['thumbnails']['high']['url'] ))
					$imagesize = 'high';
				if( ! empty( $item['snippet']['thumbnails']['medium'] ) && ! empty( $item['snippet']['thumbnails']['medium']['url'] ))
					$imagesize = 'medium';
				if( ! empty( $item['snippet']['thumbnails']['default'] ) && ! empty( $item['snippet']['thumbnails']['default']['url'] ))
					$imagesize = 'default';

				if( ! empty( $imagesize ) )
					$video['thumbnail'] 	= array( esc_url($item['snippet']['thumbnails'][$imagesize]['url']), (int)$item['snippet']['thumbnails'][$imagesize]['width'], (int)$item['snippet'][$imagesize]['default']['height'] );

				$video['publishedAt']	= sanitize_text_field( $item['snippet']['publishedAt'] );
				$video_arr[] = $video;
			}
		}

		return array( $video_arr, $nextPageToken );
	}

	/**
	*
	* Wordpress internal Cron job, setup via wp-admin;
	* This runs at time interval specified in wp-admin;
	*
	**/
	function wpcposter_wpcronjob()
	{
		$wpcposter_options = get_option( "wpcposter_options" );
		if( empty( $wpcposter_options['api'] ) ){
			return false;
		}

		if( $wpcposter_options['enable_cron'] != 1 ){
			return false;
		}

		if( empty( $wpcposter_options['enable_cron_hour'] ) )
			$wpcposter_options['enable_cron_hour'] = 6;

		if( empty( $wpcposter_options['enable_cron_hour_last'] ) )
			$wpcposter_options['enable_cron_hour_last'] = DAY_IN_SECONDS;

		if( ( current_time('timestamp') - $wpcposter_options['enable_cron_hour_last'] ) > ( (int)$wpcposter_options['enable_cron_hour'] * HOUR_IN_SECONDS ) )
		{
			$this->log( __FUNCTION__, __LINE__, __("-- === Internal Cron Start === --", 'wpcposter_lang'));

			$post_ids = $this->wpcposter_run_cron();

			$wpcposter_options['enable_cron_hour_last'] = current_time('timestamp');
			update_option( "wpcposter_options", $wpcposter_options );

			$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted %d Posts", 'wpcposter_lang'), count($post_ids) ) );
			$this->log( __FUNCTION__, __LINE__, __("-- === Internal Cron End === --", 'wpcposter_lang'));
		}
	}

	/**
	*
	* Unix Cron job, called at init;
	*
	*
	**/
	function wpcposter_cron()
	{
		$wpcposter_cron = get_option ("wpcposter_cron");
		if( isset( $_GET['wpcposter_cron'] ) && trim( $_GET['wpcposter_cron'] ) == $wpcposter_cron )
		{
			$this->log( __FUNCTION__, __LINE__, __("-- === Cron Start === --", 'wpcposter_lang'));
			$post_ids = $this->wpcposter_run_cron();
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted %d Posts", 'wpcposter_lang'), count($post_ids) ) );
			$this->log( __FUNCTION__, __LINE__, __("-- === Cron End === --", 'wpcposter_lang'));
			die();
		}
	}

	function get_playlist_name_byid( $playlist_id )
	{
		if( ! $playlist_arr = get_option( 'wpcposter_playlists' ) )
			return false;

		$playlist_id = str_replace( 'playlist-', '', $playlist_id );
		$playlist_arr = json_decode( $playlist_arr, true );
		foreach( $playlist_arr as $playlist_ar )
		{
			if( $playlist_ar['id'] == $playlist_id )
				return esc_attr( $playlist_ar['title'] );
		}
		return false;
	}

	function wpcposter_run_cron()
	{
		global $wpdb;

		$wpcposter_options = get_option( "wpcposter_options" );
		if( empty( $wpcposter_options['api'] ) )
			return false;

		if( ! $fetch_fields = get_option( "wpcposter_fetch_fields" ) ) {
			$this->log( __FUNCTION__, __LINE__, __("Error: Please select Playlists to process from the settings panel.", 'wpcposter_lang') );
			return;
		}

		$fetch_fields = json_decode( $fetch_fields, true );

		$process_key = 0;
		if( ! empty( $wpcposter_options['process_key'] ) )
			$process_key = (int)$wpcposter_options['process_key'];

		if( count( $fetch_fields ) <= $process_key ) {
			$process_key = 0;
		}

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Key: %d; Playlist count: %d", 'wpcposter_lang'), $process_key, count( $fetch_fields ) ) );

		$fetch_fields_key = array_slice($fetch_fields, $process_key, 1, true);
		$fetch_fields_key = key( $fetch_fields_key );
		$fetch_fields_val = $fetch_fields[$fetch_fields_key];

		$process_key++;
		$wpcposter_options['process_key'] = $process_key;
		update_option( "wpcposter_options", $wpcposter_options );

		$playlist_name = $this->get_playlist_name_byid( $fetch_fields_key );
		$this->log( __FUNCTION__, __LINE__, sprintf( __("Processing: %s", 'wpcposter_lang'), $playlist_name.' ['.$fetch_fields_key.']; Next Key:'.$process_key ) );

		$new_cats = array();
		if( ! empty( $fetch_fields_val[0] ) ) {

			if( ! function_exists( 'wp_create_category' ) )
				require_once ABSPATH.'/wp-admin/includes/taxonomy.php';

			$cats = trim( $fetch_fields_val[0], ',' );
			if( ! empty( $cats ) )
				$cats = explode( ',', $cats );

			if( ! empty( $cats ) && ! is_array( $cats ) )
				$cats = array( $cats );

			if( ! empty( $cats ) ){
			foreach( $cats as $cat ) {
				$category = get_term_by('name', $cat, 'category');
				if( ! empty( $category ) ){
					$new_cats[] = (int)$category->term_id;
				}else{
					$new_cats[] = wp_create_category( $cat, 0 );
				}
			}}
		}

		list( $videos, $pagetoken ) = $this->get_videos_by_playlistid( $fetch_fields_key, $fetch_fields_val[1] );//playlist_id, nextPageToken;

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Vid count: %s; NextPageToken: %s", 'wpcposter_lang'), count( $videos ), $pagetoken ) );

		//only overwrite, if we have a new token
		if( ! empty( $pagetoken ) ) {
			$fetch_fields[$fetch_fields_key] = array( $fetch_fields_val[0], $pagetoken );
			$fetch_fields = json_encode( $fetch_fields );
			update_option( "wpcposter_fetch_fields"	, $fetch_fields );
		}

		$post_ids = array();
		if( ! empty( $videos ) ){
		foreach( $videos as $video ){
			if( $xx = $this->wpcposter_publish_videos( $video, $new_cats ) )
				$post_ids[] = $xx;
		}}

		return $post_ids;
	}

	/**
	*
	* $video['id']
	* $video['title']
	* $video['description']
	* $video['thumbnail'] 	= array( 'url', 'width', 'height' );
	* $video['publishedAt']
	*
	*
	**/
	function wpcposter_publish_videos( $post, $new_cats )
	{
		global $wpdb;

		$post_id = $wpdb->get_var( "SELECT `post_id` FROM `".$wpdb->prefix."postmeta` where `meta_key`='_wpcposter_videoid' AND `meta_value`='".$post['id']."'" );
		if( ! empty( $post_id ) ){
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Error - Dup post found ID: %s", 'wpcposter_lang'), $post_id ) );
			return false;
		}

		$wpcposter_options = get_option( "wpcposter_options" );

		//remove_filter('the_content', 'make_clickable');

		$content 	= $this->get_post_content( $post );

		$date 	= gmdate('U');
		$date 	= gmdate('Y-m-d H:i:s', $date + ( get_option('gmt_offset') * 3600 ) );

		$post_array = array(
			'post_author' 	=> ( $wpcposter_options['author']? $wpcposter_options['author']: 1 ), 
			'post_date' 	=> $date, 
			'post_title' 	=> trim( sanitize_text_field($post['title'])),
			'post_content' 	=> $content, 
			'post_category' 	=> $new_cats, 
			'post_status' 	=> 'publish',
			'post_type' 	=> 'post',
		);

		$post_id = wp_insert_post( $post_array, true ); 

		$this->log( __FUNCTION__, __LINE__, sprintf( __("Inserted Post: %s - %s", 'wpcposter_lang'), $post_id, $post_array['post_title'] ) );

		if(is_wp_error($post_id))
		{
			$this->log( __FUNCTION__, __LINE__, sprintf( __("Error - %s", 'wpcposter_lang'), $post_id->get_error_message() ));
			return false;
		}
		add_post_meta($post_id, '_wpcposter_videoid', $post['id'] ); 

		if( ! empty( $post['thumbnail'] ) )
			$this->set_featured_image( $post_id, $post['thumbnail'][0] );

		return $post_id;
	}

	function get_post_content( $post )
	{
		$wpcposter_options = get_option( "wpcposter_options" );

		if( empty( $wpcposter_options['content'] ) )
			$wpcposter_options['content'] = "[WPCPOSTER_VIDEO]\n[WPCPOSTER_DATE]\n\n[WPCPOSTER_CONTENT]";

		$wpcposter_options['content'] = @html_entity_decode($wpcposter_options['content'], ENT_QUOTES, get_option('blog_charset'));

		$this->wpcposter_post = $post;
		return do_shortcode( $wpcposter_options['content'] );
	}

	function set_featured_image( $post_id, $image_url)
	{
		$upload_dir       = wp_upload_dir(); // Set upload folder

		$image_name       = basename( $image_url );
		$image_data       = file_get_contents($image_url); // Get image data
		$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
		$filename         = basename( $unique_file_name ); // Create image file name

		// Check folder permission and define file location
		if( wp_mkdir_p( $upload_dir['path'] ) ) {
		    $file = $upload_dir['path'] . '/' . $filename;
		} else {
		    $file = $upload_dir['basedir'] . '/' . $filename;
		}
		
		// Create the image  file on the server
		file_put_contents( $file, $image_data );
		
		// Check image file type
		$wp_filetype = wp_check_filetype( $filename, null );
		
		// Set attachment data
		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title'     => sanitize_file_name( $filename ),
		    'post_content'   => '',
		    'post_status'    => 'inherit'
		);
		
		// Create the attachment
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		
		// Include image.php
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		
		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		
		// Assign metadata to attachment
		wp_update_attachment_metadata( $attach_id, $attach_data );
		
		// And finally assign featured image to post
		set_post_thumbnail( $post_id, $attach_id );
	}

	function wpcposter_shortcode_title( $atts = array(), $content = '' )
	{
		return esc_attr( $this->wpcposter_post['title'] );
	}
	function wpcposter_shortcode_content( $atts = array(), $content = '' )
	{
		return wpautop( $this->wpcposter_post['description'] );
	}
	function wpcposter_shortcode_video( $atts = array(), $content = '' )
	{
		$wpcposter_options = get_option( "wpcposter_options" );

		return '[embed width="'.(int)$wpcposter_options['size_w'].'" height="'.(int)$wpcposter_options['size_h'].'"]http://www.youtube.com/watch?v='.esc_attr($this->wpcposter_post['id']).'[/embed]';
	}
	function wpcposter_shortcode_date( $atts = array(), $content = '' )
	{
		if( ! strtotime( $this->wpcposter_post['publishedAt'] ) )
			$this->wpcposter_post['publishedAt'] = current_time('timestamp');

		return date( (get_option('date_format').' '.get_option('time_format')), strtotime( $this->wpcposter_post['publishedAt'] ) );
	}

	/**
	* debug
	*
	*/
	function log( $func, $line, $str )
	{
		$log = "\n[".date( "Y-m-d H:i:s", current_time('timestamp') )."] Function: ".$func.'; Line: '.$line.'; '.$str;
		if( isset( $_GET['v'] ) ){
			echo '<br/>'.$log;
		}
	//	$this->write_log( $log );
	}

	/**
	* debug
	*
	*/
	function write_log( $str )
	{
		return false;
		/*
		file_put_contents( __DIR__.'/log.txt' , $str, FILE_APPEND );

		if( filesize( __DIR__.'/log.txt' ) > 100000 ){
			$file = @file( __DIR__.'/log.txt' );
			$file = array_map( 'trim', $file );
			$file = array_filter( $file );
			$file = array_slice( $file, -200 );
			file_put_contents( __DIR__.'/log.txt' , implode("\n", $file ) );
		}
		*/
	}

	/**
	* debug
	*
	*/
	function reset()
	{
		return false;

		//delete_option( "wpcposter_options" );
		delete_option( 'wpcposter_channel' );
		delete_option( 'wpcposter_playlists' );
		delete_option( "wpcposter_fetch_fields" );
		delete_transient( 'wpcposter_content_transient' );
	}
}
endif;

global $wp_cposter_admin;
if( ! $wp_cposter_admin ) $wp_cposter_admin = new wp_cposter_admin();