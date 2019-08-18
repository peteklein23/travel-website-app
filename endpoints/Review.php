<?php
require_once 'lib/PostCollection.php';

class Review
{
    private $destinationIdList = [];
    private $collection;

    public function __construct(string $destinationIdList)
    {
        $this->destinationIdList = $destinationIdList;
        $reviews = $this->fetchReviewsForDestinations();
        $metaKeys = ['rating', 'destination'];

        $this->collection = new PostCollection($reviews, false, $metaKeys);
    }

    private function fetchReviewsForDestinations(): array
    {
        global $wpdb;

        $reviewsQuery = "SELECT
            p.*
        FROM $wpdb->posts p
        INNER JOIN $wpdb->postmeta pm 
            ON pm.post_id = p.ID
            AND pm.meta_key = 'destination'
            AND pm.meta_value IN ($this->destinationIdList)
        WHERE p.post_type = 'review'
        AND p.post_status = 'publish'";

        return $wpdb->get_results($reviewsQuery);
    }

    public function listResultsForDestinations(): array
    {
        return $this->collection->listResults();
    }

    public function indexReviewsByDestinationId(array $reviews): array
    {
        $indexedReviews = [];
        foreach ($reviews as $review) {
            $destinationId = $review['meta']['destination'];
            if (empty($indexedReviews[$destinationId])) {
                $indexedReviews[$destinationId] = [];
            }
            $indexedReviews[$destinationId][] = $review;
        }

        return $indexedReviews;
    }
}
