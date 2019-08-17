<?php

require_once ABSPATH . '/vendor/autoload.php';

registerHooks();

function registerHooks()
{
    add_action('after_setup_theme', 'setupTheme');
    add_action('rest_api_init', 'registerEndpoints');
}

function setupTheme()
{
    add_theme_support('post-thumbnails', ['destination']);
    registerPostTypes();
    registerTaxonomies();
}

function registerPostTypes()
{
    register_post_type('destination', [
        'public' => true,
        'label' => 'Destinations',
        'supports' => ['title', 'editor', 'thumbnail']
    ]);
    register_post_type('review', [
        'public' => true,
        'label' => 'Reviews',
        'supports' => ['title', 'editor']
    ]);
}

function registerTaxonomies()
{
    register_taxonomy('region', ['destination'], [
        'public' => true,
        'label' => 'Regions',
        'hierarchical' => true,
        'show_admin_column' => true
    ]);
}

function registerEndpoints()
{
    $namespace = 'travel-review-app/v1';

    register_rest_route(
        $namespace,
        '/destinations-default-wordpress',
        [
            'methods' => 'GET',
            'callback' => 'listDestinationsDefaultWordPress',
        ]
    );
}

function listDestinationsDefaultWordPress()
{
    $destinationsQuery = new WP_Query([
        'post_type' => ['destination'],
        'limit' => -1
    ]);

    $allDestinations = $destinationsQuery->get_posts();
    $destinations = [];
    foreach ($allDestinations as $post) {
        $destination = [];
        $destination['post'] = $post;
        $destination['image'] = get_the_post_thumbnail($post->ID);
        $destination['our_rating'] = get_post_meta($post->ID, 'our_rating', true);
        $destination['hotel_link'] = get_post_meta($post->ID, 'hotel_link', true);

        $regions = get_the_terms($post->ID, 'region');
        $destination['region'] = $regions[0];

        $reviewsQuery = new WP_Query([
            'post_type' => ['review'],
            'meta_key' => 'destination',
            'meta_value' => $post->ID,
            'limit' => -1
        ]);

        $reviews = $reviewsQuery->get_posts();
        $destinationReviews = [];
        foreach ($reviews as $review) {
            $destinationReview = [];
            $destinationReview['post'] = $review;
            $destinationReview['rating'] = get_post_meta($review->ID, 'rating', true);

            $destinationReviews[] = $destinationReview;
        }
        $destination['reviews'] = $destinationReviews;

        $destinations[] = $destination;
    }

    return $destinations;
}
