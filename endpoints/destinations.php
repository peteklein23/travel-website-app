<?php
define('DOING_AJAX', true);
define('SHORTINIT', true);

require_once '../wp-load.php';


// get destinations
$postQuery = "SELECT
    *
FROM $wpdb->posts
WHERE post_type = 'destination'
AND post_status = 'publish'";

$posts = $wpdb->get_results($postQuery);
$postIds = array_column($posts, 'ID');
if (empty($postIds)) {
    wp_send_json([]);
    exit;
}
$postIdList = join(',', $postIds);


// get meta
$metaKeys = [
    'our_rating',
    'hotel_link'
];
$metaKeyList = '"' . join('","', $metaKeys) . '"';

$metaQuery = "SELECT
    *
FROM $wpdb->postmeta
WHERE post_id IN ($postIdList)
AND meta_key IN ($metaKeyList)";

$meta = $wpdb->get_results($metaQuery);

$postMeta = [];
foreach ($meta as $m) {
    $postId = $m->post_id;
    $key = $m->meta_key;
    if (empty($postMeta[$postId])) {
        $postMeta[$postId] = [];
    }

    $postMeta[$postId][$key] = $m->meta_value;
}


// get reviews
$reviewsQuery = "SELECT
    p.*,
    pm.meta_value as destination_id
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm 
    ON pm.post_id = p.ID
    AND pm.meta_key = 'destination'
    AND pm.meta_value IN ($postIdList)
WHERE p.post_type = 'review'
AND p.post_status = 'publish'";

$reviews = $wpdb->get_results($reviewsQuery);

// format reviews into it's own destination ID indexed array for easy insertion into destinations
$postReviews = [];
foreach ($reviews as $review) {
    $postId = $review->destination_id;
    if (empty($postReviews[$postId])) {
        $postReviews[$postId] = [];
    }

    $postReviews[$postId][] = $review;
}


// get featured images
$featuredImageQuery = "SELECT
    pm1.post_id,
    pm1.meta_value AS attachment_id,
    pm2.meta_value AS attachment_metadata,
    pm3.meta_value AS alt,
    p.post_title AS title,
    p.post_content AS description,
    p.post_excerpt AS caption
FROM $wpdb->postmeta pm1
LEFT JOIN $wpdb->postmeta pm2 ON pm1.meta_value = pm2.post_id AND pm2.meta_key = '_wp_attachment_metadata'
LEFT JOIN $wpdb->postmeta pm3 ON pm1.meta_value = pm3.post_id AND pm3.meta_key = '_wp_attachment_image_alt'
INNER JOIN $wpdb->posts p ON pm1.meta_value = p.ID
WHERE pm1.post_id IN ($postIdList)
AND pm1.meta_key = '_thumbnail_id'";

$featuredImages = $wpdb->get_results($featuredImageQuery);

$postFeaturedImages = [];
foreach ($featuredImages as $featuredImage) {
    $postId = $featuredImage->post_id;
    $attachment_id = $featuredImage->attachment_id;
    $meta = maybe_unserialize($featuredImage->attachment_metadata);
    $sizes = $meta['sizes'];
    $file = $meta['file'];

    $domain = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
    $uploadsUrl = $domain . '/wp-content/uploads/';

    $formattedSizes = [];
    $formattedSizes['full'] = [
        'height' => $meta['height'],
        'width' => $meta['width'],
        'file' => $uploadsUrl . $file
    ];
    foreach ($sizes as $sizeLabel => $sizeInfo) {
        $formattedSizes[$sizeLabel] = [
            'height' => $sizeInfo['height'],
            'width' => $sizeInfo['width'],
            'file' => $sizeInfo['file'],
        ];
    }
    $postFeaturedImages[$postId] = [
        'alt' => $featuredImage->alt,
        'description' => $featuredImage->description,
        'caption' => $featuredImage->caption,
        'title' => $featuredImage->title,
        'sizes' => $formattedSizes
    ];
}


// add meta keys to results
$formattedPosts = [];
foreach ($posts as $post) {
    $postId = $post->ID;
    $featuredImage = !empty($postFeaturedImages[$postId]) ? $postFeaturedImages[$postId] : [];
    $meta = !empty($postMeta[$postId]) ? $postMeta[$postId] : [];
    $reviews = !empty($postReviews[$postId]) ? $postReviews[$postId] : [];

    $formattedPosts[] = [
        'post' => $post,
        'featured_image' => $featuredImage,
        'meta' => $meta,
        'reviews' => $reviews
    ];
}

wp_send_json($formattedPosts);
exit;
