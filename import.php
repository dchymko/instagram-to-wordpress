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

class Custom_Commands
{

    function media($args, $assoc_args)
    {
        $postsFile = $args[0];
        $posts = json_decode(file_get_contents($postsFile));
        $itemsProcessed = 1;
        foreach ($posts as $post) {
            $title = null;
            $taken = null;
            //var_dump($post->creation_timestamp);
            if (isset($post->title)) {
                $title = $post->title;
            }
            if (isset($post->creation_timestamp)) {
                $taken = $post->creation_timestamp;
            }
            $images = [];
            //Get the media items to add to the post
            foreach ($post->media as $mediaItem) {
                if (!isset($title) || $title == null) {
                    $title = $mediaItem->title;
                }
                if (!isset($taken) || $taken == null) {
                    $taken = $mediaItem->creation_timestamp;
                }
                $images[] = ['uri' => $mediaItem->uri];
            }

            $dt = new DateTime();
            $dt->setTimestamp($taken);
            $postTitle = $dt->format('Y-m-d');
            //Create an actual post
            $postId = Importer::createPost($postTitle, '', $dt->format('c'));

            //Now we can actually upload and attach the files
            $attachments = [];
            $mediaCount = 0;
            foreach ($images as $mediaItem) {
                $mediaCount++;
                WP_CLI::success(sprintf('Processing Media Item: %d', $mediaCount));
                $mediaFilePath = dirname(__FILE__) . '/export/' . $mediaItem['uri'];
                //Sometimes video files have no extension for some reason so we need to add it
                if (pathinfo($mediaFilePath, PATHINFO_EXTENSION) == "") {
                    $mimetype = mime_content_type($mediaFilePath);
                    $newPath = $mediaFilePath;
                    switch ($mimetype) {
                        case 'video/mp4':
                            $newPath = $mediaFilePath . '.mp4';
                            break;
                    }
                    copy($mediaFilePath, $newPath);
                    $mediaFilePath = $newPath;
                }
                $attachmentName = $postTitle . '.' . pathinfo($mediaFilePath, PATHINFO_EXTENSION);
                $upload_file = wp_upload_bits($attachmentName, null, file_get_contents($mediaFilePath));
                $attachId = Importer::importFileAsAttachment($upload_file['file'], $upload_file['type'], $postId, $postTitle, $dt->format('Y-m-d'));
                array_push($attachments, ["id" => $attachId, "type" => $upload_file['type']]);
            }
            $post_content = "";
            foreach ($attachments as $attachment) {
                switch ($attachment["type"]) {
                    case 'video/mp4': {
                            $url = wp_get_attachment_url($attachment["id"]);
                            $post_content .= '<!-- wp:video {"id":' . $attachment["id"] . '} -->
                    <figure class="wp-block-video"><video controls src="' . $url . '"></video></figure>
                    <!-- /wp:video -->';
                        }
                        break;
                    default: {
                            $image_data = wp_get_attachment_image_src($attachment["id"], 'full');
                            $post_content .= '<!-- wp:image --><figure class="wp-block-image"><img src="' . $image_data[0] . '"/></figure><!-- /wp:image -->';
                        }
                }
            }

            $post_content .= '<!-- wp:paragraph --><p>' . $title . '</p><!-- /wp:paragraph -->';

            wp_update_post(
                array(
                    'ID' => $postId,
                    'post_content' => $post_content,
                )
            );

            $itemsProcessed++;
            WP_CLI::success(sprintf('Created a new Post: %s %s', $postId, $postTitle));
        }
        if ($posts === false)
            die("Could not open and/or parse " . $postsFile);

    }

}

class Importer
{
    static function createPost($title, $content, $postDate)
    {
        // Create post object
        $my_post = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
	    'post_author' => 1,
	    'post_date' => $postDate
        );

        // Insert the post into the database
        $post = wp_insert_post($my_post);

        return $post;
    }

    static function importFileAsAttachment($filePath, $fileType, $parent_post_id = null, $postTitle, $postDate)
    {

        $filetype = wp_check_filetype(basename($filePath), null);


        $mediaTitle = 'media-' . $postTitle . '-' . rand(10000, 99999);

        // Prepare an array of post data for the attachment.
        $attachment = array(
            'guid' => $mediaTitle,
            'post_mime_type' => $fileType,
            'post_title' => $mediaTitle,
            'post_content' => '',
	    'post_status' => 'inherit',
	    'post_date' => $postDate
        );
        // Insert the attachment.
        $attach_id = wp_insert_attachment($attachment, $filePath, $parent_post_id);

        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Generate the metadata for the attachment, and update the database record.
        $attach_data = wp_generate_attachment_metadata($attach_id, $filePath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        set_post_thumbnail($parent_post_id, $attach_id);
        return $attach_id;
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('instagram-import', 'Custom_Commands');
}
