<?php
/*
Plugin Name: Better Embed
Version: 1.0
Plugin URI: http://wordpress.pressible.org/plugin-embed-code-field
Description: Adds a field in which to paste embed codes. Built to be XSS safe. Adapted from the custom fields code of Steve Taylor (http://sltaylor.co.uk/blog/control-your-own-wordpress-custom-fields/).
Author: EdLab Publishing
Author URI: http://edlab.tc.columbia.edu
*/


# functions

add_action( 'admin_menu', 'createEmbedField' );

function createEmbedField() {
	if ( function_exists( 'add_meta_box' ) ) 
		add_meta_box( 'embed-field', 'Embed Code (for featured media)', 'displayEmbedField', 'post', 'normal', 'high' );
}


add_action( 'save_post', 'saveEmbedField', 1, 2 );

function displayEmbedField() {
	global $post;
	
	
	print '<div>';
	wp_nonce_field( 'embed-field', 'embed-field_wpnonce', false, true );
	
	
	# check if this is a post and the user can edit it
	
	if ( 
		(basename( $_SERVER['SCRIPT_FILENAME'] )=="post-new.php" || $post->post_type=="post") and
		current_user_can( 'edit_post', $post->ID )
	){
		
		
		# print out the embed field
		
		?>
		
<label for="_embed" class="screen-reader-text"><b>Paste Embed Code</b></label>
<textarea style="margin:0;height:4em;width:98%;color:#999;font-size:.8em;overflow:hidden" name="_embed" id="_embed" columns="40" rows="1"><?php print htmlspecialchars( get_post_meta( $post->ID, '_embed', true ) ); ?></textarea>
<p>
	Paste <em>one</em> embed code from any service to be included with your post.
	Broken, incomplete, or duplicate codes will be removed. Please note that
	only <a href="http://codex.wordpress.org/Embeds#Okay.2C_So_What_Sites_Can_I_Embed_From.3F">services that support oEmbed</a> are allowed in the main content area. <a href="http://pressible.org/header/faq#Images">Learn
	more</a> about embedding media.
</p>

		<?php
	}
		
	print '</div>';

}

function saveEmbedField( $post_id, $post ) {
	
	if (
		wp_verify_nonce( $_POST[ 'embed-field_wpnonce' ], 'embed-field' ) and
		current_user_can( 'edit_post', $post_id ) and
		$post->post_type == 'post'
	){
		
		
	
		if ( isset( $_POST[ '_embed' ] ) and trim( $_POST[ '_embed' ] ) ) {
		
			# allowed tags and attributes

			$filter = array(
				'object' => array(
					'width' => array(),
					'height' => array()
					),
				'param' => array(
					'name' => array(),
					'value' => array()
					),
				'embed' => array(
					'width' => array(),
					'height' => array(),
					'src' => array(),
					'type' => array(),
					'allowscriptaccess' => array(),
					'allowfullscreen' => array(),
					'allownetworking' => array(),
 					'pluginspace' => array(),
					'wmode' => array(),
					'flashvars' => array()
					),
				);
		
			$filtered = $_POST[ '_embed' ];
			
			$filtered = preg_replace("/=(.)'([^\']*)(.)'/", '=$1"$2$3"', $filtered);
			$filtered = wp_kses($filtered, $filter, array('http'));
			$filtered = preg_replace('/>[^<]+</','><', $filtered);
			$filtered = preg_replace('/^[^<]+/','', $filtered);
			$filtered = preg_replace('/[^>]+$/','', $filtered);
						
			if(
				preg_match("/\<(object|embed)[^>]*>/", $filtered, $type) and
				$end = stripos($filtered, '</' . $type[1] . '>')
			)
				update_post_meta( $post_id, '_embed', substr($filtered, 0, $end + strlen($type[1]) + 3));
			else
				delete_post_meta( $post_id, '_embed' );
		}
		else
			delete_post_meta( $post_id, '_embed' );
	}
}



# template tag

function the_embed($new_total_width=400, $print=TRUE){
	global $post;

	
	# get the embed meta data

	$embed = get_post_meta( $post->ID, '_embed', true );

	
	# check if dimensions are specified
	
	if(preg_match("/width=\"(\d+)/", $embed, $width) and preg_match("/height=\"(\d+)/", $embed, $height)){

		$original_width = $width[1];

		$embed = preg_replace("/(width=\")\d+/",'${1}' . $new_total_width, $embed);
		$embed = preg_replace("/(height=\")\d+/",'${1}' . ceil($height[1] * $new_total_width / $original_width), $embed);


		# pairs that need to scale proportionally without changing margins
	
		$pairs = array(
				'(vw=)(\d+)'	=> '(vh=)(\d+)',
				'(=)(\d+)(?=x)'	=> '(=\d+x)(\d+)',	
			);
	
		foreach($pairs as $w => $h){
			if(preg_match("/$w/", $embed, $width) and $width[2] and preg_match("/$h/", $embed, $height)){
			
			
				$new_width = $new_total_width - $original_width + $width[2];
				$new_height = ceil($height[2] * $new_width / $width[2]);

				$embed = preg_replace("/$w/", '${1}' . $new_width, $embed);
				$embed = preg_replace("/$h/", '${1}' . $new_height, $embed);
			}
		}
	}

	if($print)
		print $embed;

	return $embed;
}

function embed_content($content){
	if($embed = the_embed(600, FALSE))
		$content = "<p>$embed</p>\n$content";
		
	return $content;
}

add_filter('the_content', 'embed_content');


?>