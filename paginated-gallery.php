<?php
/*
Plugin Name: Paginated Gallery
Plugin URI: http://www.phil-barker.com/*page here*
Description: Adds pages to the wordpress gallery
Version: 0.1
Author: Phil Barker
Author URI: http://www.phil-barker.com
License: GPL2
*/

/*  Copyright 2011  Phil Barker  (email : mail@phil-barker.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once(dirname(__FILE__) . '/options.php');

if (get_option('use_gallery_shortcode')) {
	// Over-ride the standard gallery shortcode with our own
	remove_shortcode('gallery');
	add_shortcode('gallery', 'paginated_gallery');
} else {
	add_shortcode('paginated-gallery', 'paginated_gallery');
}

function paginated_gallery($attr) {
	/* Outputs a gallery of attachments with pagination
	Most of this code is lifted from the standard gallery function - with a few tweeks for pagination
	*/
	global $post;
	
	static $instance = 0;
	$instance++;
	
	$imagesPerPage = get_option('thumbnails_per_page');
	
	// Define some default options
	$options = array(
		'order'=> 'ASC', 
		'orderby'=> 'menu_order ID',
		'itemtag'=> 'dl',
		'icontag'=> 'dt', 
		'captiontag'=> 'dd', 
		'columns'=> 3, 
		'size'=> 'thumbnail', 
		'perpage'=> $imagesPerPage, 
		'link'=>'attachment', 
		'show_edit_links'=>'Y', 
		'use_shortcode'=>'gallery', 
		'exclude'=>''
	);
	
	// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
	if ( isset( $attr['orderby'] ) ) {
		$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
		if ( !$attr['orderby'] )
			unset( $attr['orderby'] );
	}
	
	// Overwrite the defaults with any options passed in
	if (is_array($attr)) $options = array_merge($options, $attr);
	
	// Start by getting the attachments
	$attachments = get_children(array(
		'post_parent'=> $post->ID, 
		'post_status'=>'inherit', 
		'post_type'=> 'attachment', 
		'post_mime_type'=>'image', 
		'order'=> $options['order'], 
		'orderby'=> $options['orderby'], 
		'exclude'=> $options['exclude']
	));
	
	// If we don't have any attachments - output nothing
	if ( empty($attachments) ) return '';
	
	// Output feed if requested
	if ( is_feed() ) {
		$output = "\n";

		foreach ( $attachments as $id => $attachment )
			$output .= wp_get_attachment_link($id, $options['size'], true) . "\n";
		return $output;
	}
	
	// Standard post output
	
	// Work out how many pages we need and what page we are currently on
	$imageCount = count($attachments);
	$pageCount = ceil($imageCount / $imagesPerPage);
	
	$currentPage = intval($_GET['galleryPage']);
	if ( empty($currentPage) || $currentPage<=0 ) $currentPage=1;
	
	$maxImage = $currentPage * $imagesPerPage;
	$minImage = ($currentPage-1) * $imagesPerPage;
	
	
	if ($pageCount > 1)
	{
		$page_link= get_permalink();
		$page_link_perma= true;
		if ( strpos($page_link, '?')!==false )
			$page_link_perma= false;

		$gplist= '<div class="gallery_pages_list">'.__('Pages').'&nbsp; ';
		for ( $j=1; $j<= $pageCount; $j++)
		{
			if ( $j==$currentPage )
				$gplist .= '[<strong class="current_gallery_page_num"> '.$j.' </strong>]&nbsp; ';
			else
				$gplist .= '[ <a href="'.$page_link. ( ($page_link_perma?'?':'&amp;') ). 'galleryPage='.$j.'">'.$j.'</a> ]&nbsp; ';
		}

		$gplist .= '</div>';
	}
	else
		$gplist= '';
	
	$itemtag = tag_escape($options['itemtag']);
	$captiontag = tag_escape($options['captiontag']);
	$columns = intval($options['columns']);
	$itemwidth = $options['columns'] > 0 ? floor(100/$options['columns']) : 100;
	$float = is_rtl() ? 'right' : 'left';
	$icontag = $options['icontag'];
	$id = $options['id'];
	$size = $options['size'];
	
	$selector = "gallery-{$instance}";

	$gallery_style = $gallery_div = '';
	if ( apply_filters( 'use_default_gallery_style', true ) )
		$gallery_style = "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} .gallery-item {
				float: {$float};
				margin-top: 10px;
				text-align: center;
				width: {$itemwidth}%;
			}
			#{$selector} img {
				border: 2px solid #cfcfcf;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
		</style>
		<!-- see gallery_shortcode() in wp-includes/media.php -->";
	$size_class = sanitize_html_class( $size );
	$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
	$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );
	
	$i = 0;
	$k = 0;
	foreach ( $attachments as $id => $attachment ) {
		if ($k >= $minImage && $k < $maxImage) {
			$link = isset($options['link']) && 'file' == $options['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);
			
			$output .= "<{$itemtag} class='gallery-item'>";
			$output .= "
				<{$icontag} class='gallery-icon'>
					$link
				</{$icontag}>";
			if ( $captiontag && trim($attachment->post_excerpt) ) {
				$output .= "
					<{$captiontag} class='wp-caption-text gallery-caption'>
					" . wptexturize($attachment->post_excerpt) . "
					</{$captiontag}>";
			}
			$output .= "</{$itemtag}>";
			if ( $columns > 0 && ++$i % $columns == 0 )
				$output .= '<br style="clear: both" />';
		}
		$k++;
	}
	$output .= "\n<br style='clear: both;' />$gplist\n</div>\n";

	return $output;
	
	// If we've got this far then we must have some attachments to play with!
	return 'Gallery Here - Page '. $currentPage. ' of '. $pageCount;
}

?>