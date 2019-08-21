<?php
define('DOING_AJAX', true);
define('SHORTINIT', true);
define('WP_CACHE', false);

require_once '../wp-load.php';


// get destinations
$postQuery = "SELECT
    *
FROM $wpdb->posts
WHERE post_type = 'destination'
AND post_status = 'publish'";

$posts = $wpdb->get_results($postQuery);

// get the post IDs
$postIds = array_column($posts, 'ID'); // array_column works on both arrays and objects
// if no posts, bail early
if (empty($postIds)) {
    wp_send_json([]);
    exit;
}

// create a list to pass to MySQL IN() operator for meta and taxonomies
$postIdList = join(',', $postIds);


// get meta
$metaKeys = [
    'our_rating',
    'hotel_link'
];
// create a list to pass to MySQL IN() operator
$metaKeyList = '"' . join('","', $metaKeys) . '"';

// get meta for destinations we retrieved
$metaQuery = "SELECT
    *
FROM $wpdb->postmeta
WHERE post_id IN ($postIdList)
AND meta_key IN ($metaKeyList)";

$meta = $wpdb->get_results($metaQuery);

// index post meta by destination ID for easy assembly later
$postMeta = [];
foreach ($meta as $m) {
    $postId = $m->post_id;
    $key = $m->meta_key;
    if (empty($postMeta[$postId])) {
        $postMeta[$postId] = [];
    }

    $postMeta[$postId][$key] = $m->meta_value;
}


// get featured images for destinations
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

// assemble a usable featured image from query results
$postFeaturedImages = [];
foreach ($featuredImages as $featuredImage) {
    $postId = $featuredImage->post_id;
    $attachment_id = $featuredImage->attachment_id;
    $meta = maybe_unserialize($featuredImage->attachment_metadata);
    $sizes = $meta['sizes'];
    $file = $meta['file'];
    $dirname = dirname($file);

    $domain = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $uploadsUrl = $domain . '/wp-content/uploads/';
    $relativePath = dirname($file);
    $fullPath = $uploadsUrl . $relativePath . '/';

    $formattedSizes = [];
    $formattedSizes['full'] = [
        'height' => $meta['height'],
        'width' => $meta['width'],
        'file' => $fullPath . basename($file)
    ];
    foreach ($sizes as $sizeLabel => $sizeInfo) {
        $formattedSizes[$sizeLabel] = [
            'height' => $sizeInfo['height'],
            'width' => $sizeInfo['width'],
            'file' => $fullPath . $sizeInfo['file'],
        ];
    }

    // index by destination id for easy assembly later
    $postFeaturedImages[$postId] = [
        'alt' => $featuredImage->alt,
        'description' => $featuredImage->description,
        'caption' => $featuredImage->caption,
        'title' => $featuredImage->title,
        'sizes' => $formattedSizes
    ];
}


// Get taxonomies
$taxonomies = ['region'];
// create a list to pass to MySQL IN() operator
$taxonomyList = '"' . join('","', $taxonomies) . '"';

$taxonomyQuery = "SELECT
    tr.object_id as post_id,
    tt.term_id,
    t.name,
    t.slug,
    t.term_group,
    tt.term_taxonomy_id,
    tt.taxonomy,
    tt.description,
    tt.parent,
    tt.count
FROM $wpdb->term_relationships tr
INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
INNER JOIN $wpdb->terms AS t ON t.term_id = tt.term_id
WHERE tt.taxonomy IN ($taxonomyList)
AND tr.object_id IN ($postIdList)";

$taxonomyTerms = $wpdb->get_results($taxonomyQuery);

$postTaxonomies = [];
foreach ($taxonomyTerms as $term) {
    $postId = $term->post_id;
    if (empty($postTaxonomies[$postId])) {
        $postTaxonomies[$postId] = [];
    }

    $taxonomy = $term->taxonomy;
    if (empty($postTaxonomies[$postId][$taxonomy])) {
        $postTaxonomies[$postId][$taxonomy] = [];
    }

    // index by destination ID for easy assembly later
    $postTaxonomies[$postId][$taxonomy][] = [
        'term_id' => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'term_group' => $term->term_group,
        'term_taxonomy_id' => $term->term_taxonomy_id,
        'taxonomy' => $term->taxonomy,
        'description' => $term->description,
        'parent' => $term->parent,
        'count' => $term->count
    ];
}


// get reviews
$reviewsQuery = "SELECT
    p.*
FROM $wpdb->posts p
INNER JOIN $wpdb->postmeta pm 
    ON pm.post_id = p.ID
    AND pm.meta_key = 'destination'
    AND pm.meta_value IN ($postIdList)
WHERE p.post_type = 'review'
AND p.post_status = 'publish'";

$reviews = $wpdb->get_results($reviewsQuery);

$reviewIds = array_column($reviews, 'ID');
// create a list to pass to MySQL IN() operator
$reviewIdList = join(',', $reviewIds);

// get meta for reviews
$reviewMetaKeys = [
    'rating',
    'destination'
];
// create a list to pass to MySQL IN() operator
$reviewMetaList = '"' . join('","', $reviewMetaKeys) . '"';

$reviewMetaQuery = "SELECT
    *
FROM $wpdb->postmeta
WHERE post_id IN ($reviewIdList)
AND meta_key IN ($reviewMetaList)";

$reviewMetaResults = $wpdb->get_results($reviewMetaQuery);

$reviewMeta = [];
foreach ($reviewMetaResults as $m) {
    $postId = $m->post_id;
    $key = $m->meta_key;
    if (empty($reviewMeta[$postId])) {
        $reviewMeta[$postId] = [];
    }

    // index by review ID for easy assembly later
    $reviewMeta[$postId][$key] = $m->meta_value;
}


// insert review meta into the review object
$formattedReviews = [];
foreach ($reviews as $review) {
    $postId = $review->ID;
    // use the review ID index we set earlier
    $meta = !empty($reviewMeta[$postId]) ? $reviewMeta[$postId] : [];

    $formattedReviews[] = [
        'post' => $review,
        'meta' => $meta
    ];
}


// index reviews by destination ID for easy assembly later
$postReviews = [];
foreach ($formattedReviews as $review) {
    $postId = $review['meta']['destination'];
    if (empty($postReviews[$postId])) {
        $postReviews[$postId] = [];
    }

    $postReviews[$postId][] = $review;
}


// combine destinations into complete results by using indexed arrays
$formattedPosts = [];
foreach ($posts as $post) {
    $postId = $post->ID;
    $featuredImage = !empty($postFeaturedImages[$postId]) ? $postFeaturedImages[$postId] : [];
    $meta = !empty($postMeta[$postId]) ? $postMeta[$postId] : [];
    $taxonomies = !empty($postTaxonomies[$postId]) ? $postTaxonomies[$postId] : [];
    $reviews = !empty($postReviews[$postId]) ? $postReviews[$postId] : [];

    $formattedPosts[] = [
        'post' => $post,
        'featured_image' => $featuredImage,
        'meta' => $meta,
        'taxonomies' => $taxonomies,
        'reviews' => $reviews
    ];
}

// send the results
wp_send_json($formattedPosts);
exit;
