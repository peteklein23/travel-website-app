<?php

require_once ABSPATH . '/vendor/autoload.php';

registerHooks();

/**
 * Register all hooks for the theme
 *
 * @return void
 */
function registerHooks()
{
    add_action('after_setup_theme', 'setupTheme');
    add_action('rest_api_init', 'registerEndpoints');
}

/**
 * Add theme support and do other theme setup tasks
 *
 * @return void
 */
function setupTheme()
{
    add_theme_support('post-thumbnails', ['destination']);
    registerPostTypes();
    registerTaxonomies();
}

/**
 * Register post types for the theme
 *
 * @return void
 */
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

/**
 * Register taxonomies for the theme
 *
 * @return void
 */
function registerTaxonomies()
{
    register_taxonomy('region', ['destination'], [
        'public' => true,
        'label' => 'Regions',
        'hierarchical' => true,
        'show_admin_column' => true
    ]);
}

/**
 * Register REST endpoints in the theme
 *
 * @return void
 */
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
        'post_type' => ['destination'], // just get destinations
        'limit' => -1 // show all destinations
    ]);
    $allDestinations = $destinationsQuery->get_posts();

    $destinations = [];
    foreach ($allDestinations as $post) {
        $destination = [];
        $destination['post'] = $post;

        // get featured image
        $destination['image'] = get_the_post_thumbnail($post->ID);

        // get destination meta
        $destination['our_rating'] = get_post_meta($post->ID, 'our_rating', true);
        $destination['hotel_link'] = get_post_meta($post->ID, 'hotel_link', true);

        // get destination taxonomies
        $regions = get_the_terms($post->ID, 'region');
        $destination['region'] = !empty($regions[0]) ? $regions[0] : null;

        // get reviews
        $reviewsQuery = new WP_Query([
            'post_type' => ['review'], // get reviews
            'meta_key' => 'destination', // with the meta key destination
            'meta_value' => $post->ID, // with a destination set to this destination
            'limit' => -1 // get everything
        ]);
        $reviews = $reviewsQuery->get_posts();

        $destinationReviews = [];
        foreach ($reviews as $review) {
            $destinationReview = [];
            $destinationReview['post'] = $review;

            // review meta
            $destinationReview['rating'] = get_post_meta($review->ID, 'rating', true);

            $destinationReviews[] = $destinationReview;
        }
        $destination['reviews'] = $destinationReviews;

        $destinations[] = $destination;
    }

    return $destinations;
}
