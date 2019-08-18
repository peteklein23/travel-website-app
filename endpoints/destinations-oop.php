<?php
define('DOING_AJAX', true);
define('SHORTINIT', true);
define('WP_CACHE', false);

require_once '../wp-load.php';
require_once 'Destination.php';
require_once 'Review.php';

$destination = new Destination();
$destinationIdList = $destination->getPostIdList();

$review = new Review($destinationIdList);
$reviews = $review->listResultsForDestinations();
$indexedReviews = $review->indexReviewsByDestinationId($reviews);

$destinations = $destination->addReviewsToDestinations($indexedReviews);

wp_send_json($destinations);
