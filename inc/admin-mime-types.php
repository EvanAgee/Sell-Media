<?php

/**
 * Process the image that was uploaded from a meta field. Creates new image
 * sizes, moves the original image to the proctected area, and also saves IPTC
 * data if any as taxonomies/terms.
 *
 * @param $moved_file The file we are referencing
 * @param $_FILES The Global PHP $_FILES array.
 * @since 1.0.1
 * @return $destination_file The location to the new file
 */
function sell_media_move_image_from_meta( $moved_file=null, $_FILES=null ){

    $wp_upload_dir = wp_upload_dir();

    // Would rather check if the correct function exists
    // but the function 'image_make_intermediate_size' uses other
    // functions that are in trunk and not in 3.4
    if ( get_bloginfo('version') >= '3.5' ) {
        $image_new_size = image_make_intermediate_size( $moved_file, get_option('large_size_h'), get_option('large_size_w'), $crop=false );
        $resized_image = dirname( $destination ) . '/' . date('m') . '/' . $image_new_size['file'];
    } else {
        $resized_image = image_resize( $moved_file, get_option('large_size_w'), get_option('large_size_h'), false, null, $wp_upload_dir['path'], 90 );
    }

    $destination_file = $wp_upload_dir['path'] . '/' . $_FILES['sell_media_file']['name'];

    do_action( 'sell_media_after_upload' );

    // image_resize() creates the file with the prefixed file size, we
    // don't want this. So we copy the resized image to the same location
    // just without the prefixed image resolution.

    // We still don't have an original, so we copy the resized image to.
    @copy( $resized_image, $destination_file );

    // Since we copied our WP generated image size to the "protected area"
    // we need to copy it back to the WP area. Then we need to unlink it,
    // i.e. "delete".
    $new_resize_location = dirname( $destination_file ) . '/' . basename( $resized_image );
    @copy( $resized_image, $new_resize_location );
    unlink( $resized_image );

    // Get iptc info
    $city = sell_media_iptc_parser( 'city', $destination_file );
    $state = sell_media_iptc_parser( 'state', $destination_file );
    $creator = sell_media_iptc_parser( 'creator', $destination_file );
    $keywords = sell_media_iptc_parser( 'keywords', $destination_file );

    // If we have iptc info save it
    if ( $city ) sell_media_iptc_save( 'city', $city, $post_id );
    if ( $state ) sell_media_iptc_save( 'state', $state, $post_id );
    if ( $creator ) sell_media_iptc_save( 'creator', $creator, $post_id );
    if ( $keywords ) sell_media_iptc_save( 'keywords', $keywords, $post_id );

    return $destination_file;
}


/**
 * Parse IPTC info and move the uploaded file into the proctected area
 *
 * In order to "protect" our uploaded file, we resize the original
 * file down to the largest WordPress size set in Media Settings.
 * Then we take the uploaded file and move it to the "proteced area".
 * Last, we copy (rename) our resized uploaded file to be the original
 * file.
 *
 * @param $attached_file As WordPress see's it in *postmeta table
 * "_wp_attached_file", i.e., YYYY/MM/file-name.ext
 * @since 1.0.1
 */
function sell_media_move_image_from_attachment( $attached_file=null, $attachment_id=null ){

    // Since our attached file does not contain the full path.
    // We build it using the wp_upload_dir function.
    $wp_upload_dir = wp_upload_dir();
    $original_file = $wp_upload_dir['basedir'] . '/' . $attached_file;

    // Extract IPTC meta info from the uploaded image.
    $city = sell_media_iptc_parser( 'city', $original_file );
    $state = sell_media_iptc_parser( 'state', $original_file );
    $creator = sell_media_iptc_parser( 'creator', $original_file );
    $keywords = sell_media_iptc_parser( 'keywords', $original_file );

    // Save iptc info as taxonomies
    if ( ! empty( $attachment_id ) ) {
        if ( $city )
            sell_media_iptc_save( 'city', $city, $attachment_id );

        if ( $state )
            sell_media_iptc_save( 'state', $state, $attachment_id );

        if ( $creator )
            sell_media_iptc_save( 'creator', $creator, $attachment_id );

        if ( $keywords )
            sell_media_iptc_save( 'keywords', $keywords, $attachment_id );
    }

    // Assign the FULL PATH to our destination file.
    $destination_file = $wp_upload_dir['basedir'] . SellMedia::upload_dir . '/' . $attached_file;

    // Check if the destinatin directory exists, i.e.
    // wp-content/uploads/sell_media/YYYY/MM if not we create it.
    if ( ! file_exists( dirname( $destination_file ) ) ){
        wp_mkdir_p( dirname( $destination_file ) );
    }

    // Resize original file down to the largest size set in the Media Settings
    //
    // Determine which version of WP we are using.
    // Would rather check if the correct function exists
    // but the function 'image_make_intermediate_size' uses other
    // functions that are in trunk and not in 3.4
    if ( get_bloginfo('version') >= '3.5' ) {
        $image_new_size = image_make_intermediate_size( $original_file, get_option('large_size_w'), get_option('large_size_h'), $crop = false );
        $resized_image = dirname( $destination ) . '/' . date('m') . '/' . $image_new_size['file'];
    } else {
        $resized_image = image_resize( $original_file, get_option('large_size_w'), get_option('large_size_h'), false, null, $wp_upload_dir['path'], 90 );
    }

    // Copy original to our protected area
    @copy( $original_file, $destination_file );

    // Copy (rename) our resized image to the original
    @copy( $resized_image, dirname( $resized_image ) . '/' . basename( $original_file ) );
}


/**
 * Determines the default icon used for an Attachment. If an
 * image mime type is detected than the attachment image is used.
 */
function sell_media_item_icon( $attachment_id=null, $size='medium' ){

    if ( empty( $attachment_id ) )
        return;

    $mime_type = get_post_mime_type( $attachment_id );

    switch( $mime_type ){
        case 'image/jpeg':
        case 'image/png':
        case 'image/gif':
                $image = wp_get_attachment_image_src( $attachment_id, $size );
                echo  '<img src="'.$image[0].'" class="sell_media_image wp-post-image" alt="" height="'.$image[2].'" width="'.$image[1].'" style="max-width:100%;height:auto;"/>';
            return;
        case 'video/mpeg':
        case 'video/mp4':
        case 'video/quicktime':
        case 'application/octet-stream':
            $mime_type = 'video/mpeg';
            break;
        case 'text/csv':
        case 'text/plain':
        case 'text/xml':
        case 'text/document':
        case 'application/pdf':
            $mime_type = 'text/document';
            break;
    }
    print '<img src="' . wp_mime_type_icon( $mime_type ) . '" />';
}


/**
 * Parse IPTC info and move the uploaded file into the proctected area
 *
 * @param $original_file Full path of the file with the file.
 * @since 1.0.1
 */
function sell_media_default_move( $original_file=null ){

    if ( file_exists( $original_file ) ){

        $destination_file = $dir['basedir'] . SellMedia::upload_dir . '/' . $meta['file'];

        // Check if the destinatin dir is exists, i.e.
        // sell_media/YYYY/MM if not we create it first
        $destination_dir = dirname( $destination_file );

        if ( ! file_exists( $destination_dir ) ){
            wp_mkdir_p( $destination_dir );
        }

        // Copy original to our protected area
        @copy( $original_file, $destination_file );
    }
}