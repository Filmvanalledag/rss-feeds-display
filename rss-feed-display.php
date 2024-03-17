<?php
   /*
   Plugin Name: RSS Feeds Display
   Description: Display links to the latest articles from multiple RSS feeds.
   Version: 0.1
   Author: Maarten (Filmvanalledag.nl)
   */

   // Plugin code will go here

// Get the feed URLs from the OPML file
function get_feed_urls_from_opml() {
    $feed_urls = array();

    $opml_file = plugin_dir_path(__FILE__) . 'feeds.opml';

    if (file_exists($opml_file)) {
        $opml = simplexml_load_file($opml_file);

        foreach ($opml->body->outline as $outline) {
            if ($outline['type'] == 'rss') {
                $feed_urls[] = (string) $outline['xmlUrl'];
            }
        }
    }

    return $feed_urls;
}


// Fetch and display RSS feeds with caching
function display_rss_feeds_cached() {
    // Remove any cache data for testing purposes with the following function
    // delete_transient('rss_feeds_cache');

    $cache_key = 'rss_feeds_cache'; // Cache key for storing the RSS feed data
    $cache_duration = 3600; // Cache duration in seconds (e.g., 1 hour)

    // Try to get cached data
    $cached_data = get_transient($cache_key);

    if ($cached_data) {
        // If cached data is available, return it
        return $cached_data;
    } else {
        // If cached data is not available, fetch new RSS feeds
        $feeds = get_feed_urls_from_opml();

        $items = array();

        foreach ($feeds as $feed) {
            $rss = fetch_feed($feed);

            if (!is_wp_error($rss)) {
                $max_items = $rss->get_item_quantity(1); // Change the number of items to display per feed
                $rss_items = $rss->get_items(0, $max_items);

                foreach ($rss_items as $item) {
                    $items[] = $item;
                }
            }
        }

        // Sort items by publication date (newest first)
        usort($items, function($a, $b) {
            return $b->get_date('U') - $a->get_date('U');
        });

        // Generate the HTML output
       ob_start();
        if (!empty($items)) {
            echo '<p>';
            foreach ($items as $item) {
                $date_published = date('j m Y', strtotime($item->get_date()));
                $site_published = esc_html($item->get_feed()->get_title()); // Site title 
            
                $item_title = $item->get_title();
                if (empty($item_title)) {
                $item_title = $site_published; // Use site title if item title is empty
            }
            
                echo '';
                echo '<a href="' . esc_url($item->get_permalink()) . '">' . esc_html($item_title) . '</a> ';
                echo '<small>- ' . esc_html($date_published) . ' - ' . esc_html($site_published) . '</small>';
                echo '<br/>';
            }
            echo '</p>';
        } else {
           echo 'No items to display.';
        }
        $output = ob_get_clean();

        // Cache the output
        set_transient($cache_key, $output, $cache_duration);

        // Return the generated HTML output
        return $output;
    }
}

// Register the shortcode
add_shortcode('display_rss_feeds_cached', 'display_rss_feeds_cached');
