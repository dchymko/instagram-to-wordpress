<?php
/**
* Hot Recipes
*
* @package instagram-data-import
* @author dchymko
* @license gplv2-or-later
* @version 1.0.0
*
* @wordpress-plugin
* Plugin Name: Instagram Download Media Import
* Plugin URI: https:
* Description: Imports media into posts from an Instagram data download
* Author: dchymko
* Author URI: https://vistamedia.me
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*
 */
if ( ! WP_CLI ) {
	return;
}
class Custom_Commands {

    function media( $args, $assoc_args ) {
        $postsFile = $args[ 0 ];
        $posts = json_decode( file_get_contents( $postsFile ) );
        $itemsProcessed = 1;
        foreach( $posts as $post ) {
            $title = $post->title;
            $taken = $post->creation_timestamp;
            $images = [];
            //Get the media items to add to the post
            foreach( $post->media as $mediaItem ) {
                if (! isset( $title ) ) {
                    $title = $mediaItem->title;
                }
                if (! isset( $taken ) ) {
                    $taken = $mediaItem->creation_timestamp;
                }
                $images[] = [ 'uri' => $mediaItem->uri ];
            }

            $dt = new DateTime();
            $dt->setTimestamp($taken);
            $postTitle = $dt->format('Y-m-d');
            //Create an actual post
            $postId = Importer::createPost( $postTitle, '');

            //Now we can actually upload and attach the files
            $attachments = [];
            $mediaCount = 0;
            foreach( $images as $mediaItem ) {
                $mediaCount++;
                WP_CLI::success( sprintf( 'Processing Media Item: %d', $mediaCount) );
                $mediaFilePath = dirname(__FILE__) . '//export//' . $mediaItem['uri'];
                $upload_file = wp_upload_bits( basename( $mediaFilePath ), null, file_get_contents( $mediaFilePath ) );
                $attachId = Importer::importFileAsAttachment( $upload_file[ 'file' ], $upload_file[ 'type' ], $postId, $postTitle );
            }
            $post_content = "";
            foreach( $attachments as $attachment ) {
                $image_data = wp_get_attachment_image_src( $attachment, 'full' );
                $post_content .= '<!-- wp:image --><figure class="wp-block-image"><img src="' . $image_data[0]. '"/></figure><!-- /wp:image -->';
            }

            wp_update_post( array(
                'ID'           => $postId,
                'post_content' => $post_content,
            ) );

            $itemsProcessed++;
            WP_CLI::success( sprintf( 'Created a new Post: %s', $postTitle) );
        }
        if($media === false) die("Could not open and/or parse $instagram_backup_path/content/posts_1.json");

    }

}

class Importer {
    static function createPost( $title, $content ) {
        // Create post object
        $my_post = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_author'   => 1,
        );

        // Insert the post into the database
        $post = wp_insert_post( $my_post );

        return $post;
    }

    static function importFileAsAttachment( $filePath, $fileType, $parent_postId = null, $postTitle ) {

        $filetype = wp_check_filetype( basename( $filePath ), null );


        $mediaTitle = 'media-' . $postTitle . '-' . rand(10000,99999);

        // Prepare an array of post data for the attachment.
        $attachment = array(
            'guid'           => $mediaTitle,
            'post_mime_type' => $fileType,
            'post_title'     => $mediaTitle,
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        // Insert the attachment.
        $attach_id = wp_insert_attachment( $attachment, $filePath, $parent_post_id );

        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Generate the metadata for the attachment, and update the database record.
        $attach_data = wp_generate_attachment_metadata( $attach_id, $filePath );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        set_post_thumbnail( $parent_post_id, $attach_id );
        return $attach_id;
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'instagram-import', 'Custom_Commands' );
}
