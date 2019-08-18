<?php
require_once 'lib/PostCollection.php';

class Destination
{
    private $collection;

    public function __construct()
    {
        $destinationResults = $this->fetchAllDestinations();
        $metaKeys = ['our_rating', 'hotel_link'];
        $taxonomies = ['region'];

        $this->collection = new PostCollection($destinationResults, true, $metaKeys, $taxonomies);
    }

    private function fetchAllDestinations(): array
    {
        global $wpdb;

        $postQuery = "SELECT
            *
        FROM $wpdb->posts
        WHERE post_type = 'destination'
        AND post_status = 'publish'";

        return $wpdb->get_results($postQuery);
    }

    public function getPostIdList(): string
    {
        return $this->collection->getPostIdList();
    }

    public function listDestinations(): array
    {
        return $this->collection->listResults();
    }

    public function addReviewsToDestinations(array $indexedReviews): array
    {
        $destinations = $this->listDestinations();

        $destinationsWithReviews = [];
        foreach ($destinations as $destination) {
            $destinationId = $destination['post']->ID;
            $destinationReviews = !empty($indexedReviews[$destinationId]) ? $indexedReviews[$destinationId] : [];
            $destination['reviews'] = $destinationReviews;
            $destinationsWithReviews[] = $destination;
        }

        return $destinationsWithReviews;
    }
}
