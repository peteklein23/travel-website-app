<?php

class PostCollection
{
    private $posts = [];
    private $hasFeaturedImage = true;
    private $metaKeys = [];
    private $taxonomies = [];

    private $postsFeaturedImages = [];
    private $postsMeta = [];
    private $postsTaxonomies = [];

    public function __construct(array $posts, bool $hasFeaturedImage, array $metaKeys = [], array $taxonomies = [])
    {
        $this->posts = $posts;
        $this->hasFeaturedImage = $hasFeaturedImage;
        $this->metaKeys = $metaKeys;
        $this->taxonomies = $taxonomies;

        $this->populateMeta();
        $this->populateFeaturedImages();
        $this->populateTaxonomies();
    }

    private function listPostIds(): array
    {
        return array_column($this->posts, 'ID');
    }

    public function getPostIdList(): string
    {
        $postIds = $this->listPostIds();
        if (empty($postIds)) {
            return $postIds;
        }

        return $this->getQueryList($postIds);
    }

    private function getQueryList(array $listItems): string
    {
        return '"' . join('","', $listItems) . '"';
    }

    private function getMetaKeyList(): string
    {
        return $this->getQueryList($this->metaKeys);
    }

    private function fetchMeta(): array
    {
        global $wpdb;

        $postIdList = $this->getPostIdList();
        $metaKeyList = $this->getMetaKeyList();

        $metaQuery = "SELECT
            *
        FROM $wpdb->postmeta
        WHERE post_id IN ($postIdList)
        AND meta_key IN ($metaKeyList)";

        return $wpdb->get_results($metaQuery);
    }

    private function populateMeta(): void
    {
        if (empty($this->posts) || empty($this->metaKeys)) {
            $this->postsMeta = [];
            return;
        }

        $meta = $this->fetchMeta();

        $this->postsMeta = [];
        foreach ($meta as $m) {
            $postId = $m->post_id;
            $key = $m->meta_key;
            if (empty($this->postsMeta[$postId])) {
                $this->postsMeta[$postId] = [];
            }

            $this->postsMeta[$postId][$key] = $m->meta_value;
        }
    }

    private function fetchFeaturedImages(): array
    {
        global $wpdb;

        $postIdList = $this->getPostIdList();

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

        return $wpdb->get_results($featuredImageQuery);
    }

    private function populateFeaturedImages(): void
    {
        $this->postsFeaturedImages = [];
        if (!$this->hasFeaturedImage || empty($this->posts)) {
            return;
        }

        $featuredImages = $this->fetchFeaturedImages();

        foreach ($featuredImages as $featuredImage) {
            $postId = $featuredImage->post_id;
            $meta = maybe_unserialize($featuredImage->attachment_metadata);
            $sizes = $meta['sizes'];
            $file = $meta['file'];

            $baseUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $uploadsUrl = $baseUrl . '/wp-content/uploads/';
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
            $this->postsFeaturedImages[$postId] = [
                'attachment_id' => $featuredImage->attachment_id,
                'alt' => $featuredImage->alt,
                'description' => $featuredImage->description,
                'caption' => $featuredImage->caption,
                'title' => $featuredImage->title,
                'sizes' => $formattedSizes
            ];
        }
    }

    private function getTaxonomyList(): string
    {
        return $this->getQueryList($this->taxonomies);
    }

    private function fetchTaxonomyTerms(): array
    {
        global $wpdb;

        $postIdList = $this->getPostIdList();
        $taxonomyList = $this->getTaxonomyList();

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

        return $wpdb->get_results($taxonomyQuery);
    }

    private function populateTaxonomies(): void
    {
        $this->postsTaxonomies = [];
        if (empty($this->taxonomies) || empty($this->posts)) {
            return;
        }

        $terms = $this->fetchTaxonomyTerms();
        foreach ($terms as $term) {
            $postId = $term->post_id;
            if (empty($this->postsTaxonomies[$postId])) {
                $this->postTaxonomies[$postId] = [];
            }

            $taxonomy = $term->taxonomy;
            if (empty($this->postsTaxonomies[$postId][$taxonomy])) {
                $this->postTaxonomies[$postId][$taxonomy] = [];
            }

            $this->postsTaxonomies[$postId][$taxonomy][] = [
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
    }

    public function listResults(): array
    {
        $formattedPosts = [];
        foreach ($this->posts as $post) {
            $postId = $post->ID;
            $featuredImage = !empty($this->postsFeaturedImages[$postId]) ? $this->postsFeaturedImages[$postId] : [];
            $meta = !empty($this->postsMeta[$postId]) ? $this->postsMeta[$postId] : [];
            $taxonomies = !empty($this->postsTaxonomies[$postId]) ? $this->postsTaxonomies[$postId] : [];

            $formattedPosts[] = [
                'post' => $post,
                'featured_image' => $featuredImage,
                'meta' => $meta,
                'taxonomies' => $taxonomies
            ];
        }

        return $formattedPosts;
    }
}
