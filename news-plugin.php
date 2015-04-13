<?php
/*
Plugin Name: NewsPlugin
Plugin URI: http://newsplugin.com/
Description: Create custom newsfeeds for your website. Choose keywords, number of articles and other settings, put the feed wherever you want using widgets or shortcodes, and watch the fresh relevant news headlines appear on your pages (or approve and publish them manually). You can always shape the news right from your website, remove unwanted articles or star the good ones. Thanks for using the NewsPlugin, and we hope you like it.
Author: newsplugin.com
Version: 1.0
Author URI: http://newsplugin.com/
*/

// Prevent ourselves from being run directly.
defined('ABSPATH') or die("No script kiddies please!");

// Include the fetch_feed functionality (to be replaced eventually).
include_once( ABSPATH . WPINC . '/feed.php' );

/**
 * The NewsPlugin widget.
 */
class News_Plugin_Widget extends WP_Widget {

    /**
     * Register widget with WordPress.
     */
    function __construct() {
        parent::__construct(
            'news_plugin_widget',
            __('NewsPlugin', 'news_plugin'),
            array( 'description' => __( 'Create custom newsfeeds and let fresh relevant news appear on your website (or approve and publish them manually).', 'news_plugin' ), )
        );
    }

    /**
     * Get the id for identifying this widget instance.
     */
    private function widget_id() {
      return $this->id ;
    }

    /**
     * Get the private options specific for this widget.
     */
    private function current_options() {
      $opts = get_option( 'news_plugin_widget_options', array() ) ;
      $opts = $opts[ $this->widget_id() ] ;
      return ( isset( $opts ) ? $opts : array() ) ;
    }

    /**
     * Update the private options specific for this widget.
     */
    private function update_options( $args ) {
      $opts = get_option( 'news_plugin_widget_options', array() ) ;
      $opts[ $this->widget_id() ] = $args ;
      update_option( 'news_plugin_widget_options', $opts ) ;
      return $args ;
    }

    /**
     * Get the list of currently excluded posts for this widget.
     */
    private function excluded_posts() {
        $opts = $this->current_options() ;
        $posts = $opts[ 'excluded' ] ;
        return ( isset( $posts ) ? $posts : array() ) ;
    }

    /**
     * Add given id to the list of excluded posts.
     */
    private function exclude_post( $id, $limit = 100 ) {
        $opts = $this->current_options() ;
        $posts = $this->excluded_posts() ;
        array_unshift( $posts, $id ) ;
        $posts = array_slice( $posts, 0, $limit ) ;
        $opts[ 'excluded' ] = $posts ;
        $this->update_options( $opts ) ;
        return $posts ;
    }

    /**
     * Reset the list of the excluded posts.
     */
    private function reset_excluded_posts() {
        $opts = $this->current_options() ;
        $posts = array() ;
        $opts[ 'excluded' ] = $posts ;
        $this->update_options( $opts ) ;
        return $posts ;
    }

    /**
     * Get the list of currently favorite posts for this widget.
     */
    private function favorite_posts() {
        $opts = $this->current_options() ;
        $posts = $opts[ 'favorite' ] ;
        return ( isset( $posts ) ? $posts : array() ) ;
    }

    /**
     * Add given id to the list of favorite posts.
     */
    private function star_favorite_post( $id, $limit = 100 ) {
        $opts = $this->current_options() ;
        $posts = $this->favorite_posts() ;
        array_unshift( $posts, $id ) ;
        $posts = array_slice( $posts, 0, $limit ) ;
        $opts[ 'favorite' ] = $posts ;
        $this->update_options( $opts ) ;
        return $posts ;
    }

    /**
     * Remove given id from the list of favorite posts.
     */
    private function unstar_favorite_post( $id ) {
        $opts = $this->current_options() ;
        $posts = $this->favorite_posts() ;
        $posts = array_diff( $posts, array( $id ) ) ;
        $opts[ 'favorite' ] = $posts ;
        $this->update_options( $opts ) ;
        return $posts ;
    }

    /**
     * Reset the list of the favorite posts.
     */
    private function reset_favorite_posts() {
        $opts = $this->current_options() ;
        $posts = array() ;
        $opts[ 'favorite' ] = $posts ;
        $this->update_options( $opts ) ;
        return $posts ;
    }

    /**
     * Get the timestamp of the last publishing in manual publishing mode.
     */
    private function publish_time() {
        $opts = $this->current_options() ;
        $time = $opts[ 'published' ] ;
        return ( isset( $time ) ? $time : 0 ) ;
    }

    /**
     * Set the timestamp of the last publishing in manual publishing mode.
     */
    private function update_publish_time( $time ) {
        $opts = $this->current_options() ;
        $opts[ 'published' ] = $time ;
        $this->update_options( $opts ) ;
        return $time ;
    }

    /**
     * Prepare the args for URL managing posts of this widget.
     */
    private function create_action_args( $action, $arg = 0 ) {
        return array(
          'news_plugin_instance' => $this->widget_id(),
          'news_plugin_action' => $action,
          'news_plugin_arg' => $arg,
        ) ;

    }

    /**
     * Parse the URL args for managing posts of this widget.
     */
    private function parse_action_args() {
      if ( $_GET[ 'news_plugin_instance' ] != $this->widget_id() ) {
          return array() ;
      }
      return array(
        'action' => $_GET[ 'news_plugin_action' ],
        'arg' => $_GET[ 'news_plugin_arg' ],
      ) ;
    }

    /**
     * Get the action associated with given URL request, if any.
     */
    private function current_action()
    {
        $args = $this->parse_action_args() ;
        return $args[ 'action' ] ;
    }

    /**
     * Get the argument associated with given URL request, if any.
     */
    private function current_arg()
    {
        $args = $this->parse_action_args() ;
        return $args[ 'arg' ] ;
    }

    /**
     * Test if the current user can manage the feed.
     */
    private function can_manage() {
      return ( current_user_can( 'edit_pages' ) ) ;
    }

    /**
     * Test if the edit mode is enabled for this widget.
     */
    private function edit_mode_enabled() {
      $action = $_GET[ 'news_plugin_action' ] ;
      return ! empty( $action ) ;
    }

    /**
     * Manage the feed as necessary.
     */
    private function manage( $opts ) {
        switch ( $this->current_action() ) {
            case 'exclude': {
               $id = sanitize_key( $this->current_arg() ) ;
               $limit = max( 100, 2 * $opts[ 'count' ] ) ;
               $this->exclude_post( $id, $limit ) ;
               break ;
            }
            case 'star': {
               $id = sanitize_key( $this->current_arg() ) ;
               $limit = max( 100, 2 * $opts[ 'count' ] ) ;
               $this->star_favorite_post( $id, $limit ) ;
               break ;
            }
            case 'unstar': {
               $id = sanitize_key( $this->current_arg() ) ;
               $this->unstar_favorite_post( $id ) ;
               break ;
            }
            case 'reset': {
               $this->reset_excluded_posts() ;
               $this->reset_favorite_posts() ;
               break ;
            }
            case 'publish': {
               $time = min( time(), absint( $this->current_arg() ) ) ;
               $this->update_publish_time( $time ) ;
               break ;
            }
        }
    }
    
    /**
     * Silly helper for returning caching duration for fetch_feed().
     */
    function get_feed_caching_duration( $seconds ) {
        return 3600 ;
    }

    /**
     * Get our data feed.
     */
    private function get_feed( $time, $opts, $limit = 100 ) {
        $key = get_option( 'news_plugin_api_key' ) ;

        $args = array(
          'k' => $key,
          'q' => $opts[ 'keywords' ],
          'l' => $limit,
          'c' => $opts[ 'count' ],
          't' => $opts[ 'title' ]
          // o offset
          // a after
          // b before
        ) ;
        
        if ( $opts[ 'feed_mode' ] == 'manual' ) {
            if ( ! ( $this->can_manage() && $this->edit_mode_enabled() ) ) {
                $time = $this->publish_time() ;
            }
            $args[ 'b' ] = $time ;
        }
        
        if ( ! empty( $opts[ 'age' ] ) ) { $args[ 'a' ] = $time - 3600 * $opts[ 'age' ] ; }

        if ( ! empty( $opts[ 'sources' ] ) ) { $args[ 'src' ] = $opts[ 'sources' ] ; }
        if ( ! empty( $opts[ 'excluded_sources' ] ) ) { $args[ 'exclude' ] = $opts[ 'excluded_sources' ] ; }
        if ( ! empty( $opts[ 'search_mode' ] ) ) { $args[ 'mode' ] = $opts[ 'search_mode' ] ; }
        if ( ! empty( $opts[ 'search_type' ] ) ) { $args[ 'type' ] = $opts[ 'search_type' ] ; }
        if ( ! empty( $opts[ 'sort_mode' ] ) ) { $args[ 'sort' ] = $opts[ 'sort_mode' ] ; }
        if ( ! empty( $opts[ 'link_type' ] ) ) { $args[ 'link' ] = $opts[ 'link_type' ] ; } 
    
        $url = 'http://api.newsplugin.com/search' ;
        $url = add_query_arg( urlencode_deep( $args ), $url ) ;
    
        // Talk about stupid API. Like if the cache duration couldn't be a simple parameter.
        $cache_filter = array( $this, 'get_feed_caching_duration' ) ;
        add_filter( 'wp_feed_cache_transient_lifetime' , $cache_filter );
        $feed = fetch_feed( $url ) ;
        remove_filter( 'wp_feed_cache_transient_lifetime' , $cache_filter );

        return ( is_wp_error( $feed ) ? NULL : $feed ) ;
    }

    /**
     * Generate the feed content.
     *
     * @param array $opts Saved values from database.
     */
    private function content( $opts ) {
        $time = time() ;
    
        $rss = $this->get_feed( $time, $opts ) ;

        if ( ! isset( $rss ) ) {
           _e( 'Feed fetch failed ', 'news_plugin' );
           return ;
        }
    
        $manual_mode = ( $opts[ 'feed_mode' ] == 'manual' ) ;
        
        $exclude = array_fill_keys( $this->excluded_posts(), true ) ;
        $favorite = array_fill_keys( $this->favorite_posts(), true ) ;
    
        $limit = $opts[ 'count' ] ;

        $visible = $limit ;
        if ( $this->can_manage() && $manual_mode && $this->edit_mode_enabled() ) {
            $visible = max( 2 * $limit, 5 ) ;
        }
    
        if ( $this->can_manage() ) {
            echo '<div class="news-plugin-edit-box">';
            if ( $this->edit_mode_enabled() ) {
                echo '<p class="news-plugin-edit-buttons">' ;

                $args = $this->create_action_args( 'reset' ) ;
                echo '<a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                echo 'Reset' ;
                echo '</a>';

                if ( $manual_mode ) {
                    $args = $this->create_action_args( 'publish', $time ) ;
                    echo ' | ';
                    echo '<a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                    echo 'Publish Headlines' ;
                    echo '</a>';
                }

                $args = $this->create_action_args( NULL, NULL ) ;
                echo ' | ';
                echo '<a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                echo 'Leave Edit Newsfeed Mode' ;
                echo '</a>';

                echo '</p>' ;
            }
            else {
                $args = $this->create_action_args( 'edit' ) ;
                echo '<p class="news-plugin-edit-buttons">' ;
                echo '<a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                echo 'Edit Newsfeed Mode' ;
                echo '</a>';
            }

            if ( $manual_mode ) {
                $t = $this->publish_time() ;
                if ( $t == 0 ) {
                    if ( $this->edit_mode_enabled() ) {
                        echo '<p>';
                        echo 'No headlines published yet.';
                        echo '</p>';
                    }
                    else {
                        echo '<p>';
                        echo 'No headlines published yet. Use the Edit Newsfeed Mode to edit and publish your feed.';
                        echo '</p>';
                    }
                }
                else {
                    $t = date( 'd M Y H:i', $t );
                    echo '<p>';
                    echo "Headlines last published on {$t}.";
                    echo '</p>';
                }
            }
            
            if ( $this->edit_mode_enabled() ) {
                if ( $manual_mode ) {
                    echo '<p>';
                    echo "Once published, only the first {$limit} headline" . ( $limit == 1 ? '' : 's') . " will be displayed in your feed.";
                    echo ' You can <span style="font-size:110%;">&#9734;</span>&nbsp;Star individual headlines to move them to the top or &#10005;&nbsp;Remove them from the feed. Click Reset to undo these changes.';
                    echo ' Don’t forget to Publish Headlines when you are done.';
                    echo '</p>';
                }
                else {
                    echo '<p>';
                    echo 'You can <span style="font-size:110%;">&#9734;</span>&nbsp;Star individual headlines to move them to the top or &#10005;&nbsp;Remove them from the feed. Click Reset to undo these changes.';
                    echo '</p>';
                }
            }
            echo '</div>';
        }

        $count = $rss->get_item_quantity( $visible + count( $exclude ) ) ;
        $items = $rss->get_items( 0, $count ) ;
    
        $index = 0 ;
    
        echo '<ul>';
        for ( $pass = 0 ; $pass < 2 ; $pass++ ) {
            foreach ( $items as $item ) {
                if ( $index >= $visible ) {
                    break ;
                }
            
                $id = md5( $item->get_id( false ) ) ;
                if ( $exclude[ $id ] ) {
                    continue ;
                }
                
                if ( $favorite[ $id ] xor ( $pass == 0 ) ) {
                    continue ;
                }
                
                if ( $index == $limit ) {
                    echo '<hr>' ;
                }

                echo '<li>';
                echo '<a href="' . esc_attr( $item->get_permalink() ) . '">' ;
                echo '<span class="news-plugin-title">';
                echo esc_html( $item->get_title() ) ;
                echo '</span>';
                echo '</a>';
                if ( $opts[ 'show_date' ] ) {
                    echo "\n" ;
                    echo '<span class="news-plugin-date">';
                    echo esc_html( $item->get_date( 'd M Y H:i' ) ) ;
                    echo '</span>';
                }
                if ( $opts[ 'show_source' ] ) {
                    // Because RSS doesn't support the source field, we use the author field.
                    // $source = $item->get_source() ;
                    $source = $item->get_author() ;
                    if ( $source ) $source = $source->get_email() ;
                    if ( ! empty( $source ) ) {
                        echo "\n" ;
                        echo '<span class="news-plugin-source">';
                        echo esc_html( $source ) ;
                        echo '</span>';
                    }
                }
                if ( $opts[ 'show_abstract' ] ) {
                    echo "\n" ;
                    echo '<span class="news-plugin-abstract">';
                    echo esc_html( $item->get_description() ) ;
                    echo '</span>';
                }

                if ( $this->can_manage() && $this->edit_mode_enabled() ) {
                    $args = $this->create_action_args( 'exclude', $id ) ;
                    echo ' &nbsp; <a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                    // echo 'X' ;
                    echo '<span style="text-decoration: underline;">';
                    echo '&#10005;&nbsp;Remove' ;
                    echo '</span>';
                    echo '</a> &nbsp;';
                    if ( $favorite[ $id ] ) {
                        $args = $this->create_action_args( 'unstar', $id ) ;
                        echo ' <a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                        // echo '-' ;
                        echo '<span style="text-decoration: underline;">';
                        echo '<span style="font-size:110%;">&#9733;</span>&nbsp;Unstar' ;
                        echo '</span>';
                        echo '</a>';
                    }
                    else {
                        $args = $this->create_action_args( 'star', $id ) ;
                        echo ' <a href="' . esc_attr( add_query_arg( $args ) ) . '">' ;
                        // echo '+' ;
                        echo '<span style="text-decoration: underline;">';
                        echo '<span style="font-size:110%;">&#9734;</span>&nbsp;Star' ;
                        echo '</span>';
                        echo '</a>';
                    }
                }

                echo '</li>';

                $index++ ;
            }
        }
        echo '</ul>';

    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $opts Saved values from database.
     */
    public function widget( $args, $opts ) {
        $id = absint( $opts[ 'id' ] ) ;
        if ( $id > 0 ) {
            $this->_set( $id ) ;
        }

        $key = get_option( 'news_plugin_api_key' ) ;
        if ( empty( $key ) ) {
            if ( $this->can_manage() ) {
                ?>
                <p>
                Your feed is currently inactive.
                Please enter your Activation Key on the
                <a href="<?php echo admin_url( 'admin.php?page=news-plugin-settings' ) ?>">NewsPlugin Settings</a>
                page first.
                </p>
                <?php
            }
            return ;
        }

        if ( $this->can_manage() ) {
            $this->manage( $opts ) ;
        }

        $title = apply_filters( 'widget_title', $opts['title'] );

        echo $args['before_widget'];
        if ( ! empty( $title ) )
            echo $args['before_title'] . $title . $args['after_title'];
        $this->content( $opts ) ;
        echo $args['after_widget'];
    }

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     *
     * @param array $opts Previously saved values from database.
     */
    public function form( $opts ) {
        $key = get_option( 'news_plugin_api_key' ) ;
        if ( empty( $key ) ) {
            ?>
            <p>
            Please enter your Activation Key on the
            <a href="<?php echo admin_url( 'admin.php?page=news-plugin-settings' ) ?>">NewsPlugin Settings</a>
            page first.
            </p>
            <?php
            return ;
        }

        if ( isset( $opts[ 'title' ] ) ) {
            $title = $opts[ 'title' ];
        }
        else {
            $title = __( 'New title', 'news_plugin' );
        }

        if ( isset( $opts[ 'keywords' ] ) ) {
            $keywords = $opts[ 'keywords' ];
        }
        else {
            $keywords = __( 'keywords', 'news_plugin' );
        }

        if ( isset( $opts[ 'count' ] ) ) {
            $count = $opts[ 'count' ];
        }
        else {
            $count = 5;
        }
        if ( isset( $opts[ 'age' ] ) ) {
            $age = $opts[ 'age' ];
        }
        else {
            $age = 0;
        }

        if ( isset( $opts[ 'search_mode' ] ) ) {
            $search_mode = $opts[ 'search_mode' ];
        }
        else {
            $search_mode = "";
        }

        if ( isset( $opts[ 'search_type' ] ) ) {
            $search_type = $opts[ 'search_type' ];
        }
        else {
            $search_type = "";
        }

        if ( isset( $opts[ 'sort_mode' ] ) ) {
            $sort_mode = $opts[ 'sort_mode' ];
        }
        else {
            $sort_mode = "";
        }

        if ( isset( $opts[ 'link_type' ] ) ) {
            $link_type = $opts[ 'link_type' ];
        }
        else {
            $link_type = "";
        }

        if ( isset( $opts[ 'sources' ] ) ) {
            $sources = $opts[ 'sources' ];
        }
        else {
            $sources = "";
        }
        if ( isset( $opts[ 'excluded_sources' ] ) ) {
            $excluded_sources = $opts[ 'excluded_sources' ];
        }
        else {
            $excluded_sources = "";
        }

        if ( isset( $opts[ 'show_date' ] ) ) {
            $show_date = $opts[ 'show_date' ];
        }
        else {
            $show_date = false;
        }
        if ( isset( $opts[ 'show_source' ] ) ) {
            $show_source = $opts[ 'show_source' ];
        }
        else {
            $show_source = false;
        }
        if ( isset( $opts[ 'show_abstract' ] ) ) {
            $show_abstract = $opts[ 'show_abstract' ];
        }
        else {
            $show_abstract = false;
        }

        if ( isset( $opts[ 'feed_mode' ] ) ) {
            $feed_mode = $opts[ 'feed_mode' ];
        }
        else {
            $feed_mode = "";
        }
        
        // Force expert user mode for now.
        // $user_mode = get_option( 'news_plugin_user_mode' ) ;
        $user_mode = 2;

        ?>
        <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Newsfeed Name:' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        <br>
        <small>Give your feed a good name.</small>
        <br>
        <small>Example: Canada Solar Energy News</small>
        </p>
        <p>
        <label for="<?php echo $this->get_field_id( 'keywords' ); ?>"><?php _e( 'Keywords:' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'keywords' ); ?>" name="<?php echo $this->get_field_name( 'keywords' ); ?>" type="text" value="<?php echo esc_attr( $keywords ); ?>">
        <br>
        <small>Use keywords to find relevant news.</small>
        <br>
        <small>Example: Canada AND "Solar Energy"</small>
        <br>
        <small>Visit the <a href="http://newsplugin.com/faq#keyword-tips" target="_blank">FAQ</a> for keywords tips.</small>
        </p>
        <p>
        <label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php _e( 'Number of Articles:' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" type="text" value="<?php echo $count; ?>">
        <br>
        <small>Set how many headlines to show in your feed.</small>
        <br>
        <small>Example: 10</small>
        </p>
        <p>
        <input id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" type="checkbox" <?php if ( $show_date ) echo 'checked="checked"' ?>>
        <label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Show Dates' ); ?></label>
        </p>
        <p>
        <input id="<?php echo $this->get_field_id( 'show_source' ); ?>" name="<?php echo $this->get_field_name( 'show_source' ); ?>" type="checkbox" <?php if ( $show_source ) echo 'checked="checked"' ?>>
        <label for="<?php echo $this->get_field_id( 'show_source' ); ?>"><?php _e( 'Show Sources' ); ?></label>
        </p>
        <p>
        <input id="<?php echo $this->get_field_id( 'show_abstract' ); ?>" name="<?php echo $this->get_field_name( 'show_abstract' ); ?>" type="checkbox" <?php if ( $show_abstract ) echo 'checked="checked"' ?>>
        <label for="<?php echo $this->get_field_id( 'show_abstract' ); ?>"><?php _e( 'Show Abstracts' ); ?></label>
        <br>
        <small>By default, your feed displays headlines only. You can add more information.</small>
        <br>
        <small>Example: New Reports on Canada Solar Energy, 12 Feb 2015 (BBC)</small>
        </p>
        <?php
        if ( $user_mode > 0 ) {

        /*
        <p>
        <label for="<?php echo $this->get_field_id( 'sources' ); ?>"><?php _e( 'Sources:' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'sources' ); ?>" name="<?php echo $this->get_field_name( 'sources' ); ?>" type="text" value="<?php echo esc_attr( $sources ) ; ?>">
        <br>
        <small>Show news from only selected sources. Leave blank for all sources.</small>
        <br>
        <small>Example: BBC</small>
        </p>
        <p>
        <label for="<?php echo $this->get_field_id( 'excluded_sources' ); ?>"><?php _e( 'Excluded Sources:' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'excluded_sources' ); ?>" name="<?php echo $this->get_field_name( 'excluded_sources' ); ?>" type="text" value="<?php echo esc_attr( $excluded_sources ) ; ?>">
        <br>
        <small>Don’t show news from selected sources.</small>
        <br>
        <small>Example: BBC</small>
        </p>
        */

        ?>
        <p>
        <label for="<?php echo $this->get_field_id( 'search_mode' ); ?>"><?php _e( 'Search Mode:' ); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'search_mode' ); ?>" name="<?php echo $this->get_field_name( 'search_mode' ); ?>">
        <option value="">Default</option>
        <option value="title" <?php if ( $search_mode == "title" ) echo 'selected="selected"' ?>>Headlines Only</option>
        <option value="text"<?php if ( $search_mode == "text" ) echo 'selected="selected"' ?>>Headlines &amp; Full Text</option>
        </select>
        <br>
        <small>Show news that has your keywords in a headline or anywhere in an article. Default is headlines and full text.</small>
        </p>

        <?php
        /*
        <p>
        <label for="<?php echo $this->get_field_id( 'search_type' ); ?>"><?php _e( 'Search Type:' ); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'search_type' ); ?>" name="<?php echo $this->get_field_name( 'search_type' ); ?>">
        <option value="">Default</option>
        <option value="news" <?php if ( $search_type == "news" ) echo 'selected="selected"' ?>>News</option>
        <option value="pr" <?php if ( $search_type == "pr" ) echo 'selected="selected"' ?>>Press Releases</option>
        <option value="event"<?php if ( $search_type == "event" ) echo 'selected="selected"' ?>>Events</option>
        </select>
        <br>
        <small>Show only selected types of news. Default is a combination of all types.</small>
        </p>
        */
        ?>
        
        <p>
        <label for="<?php echo $this->get_field_id( 'sort_mode' ); ?>"><?php _e( 'Sort Mode:' ); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'sort_mode' ); ?>" name="<?php echo $this->get_field_name( 'sort_mode' ); ?>">
        <option value="">Default</option>
        <option value="relevance" <?php if ( $sort_mode == "relevance" ) echo 'selected="selected"' ?>>Relevance</option>
        <option value="date"<?php if ( $sort_mode == "date" ) echo 'selected="selected"' ?>>Date</option>
        </select>
        <br>
        <small>Show headlines sorted by date or relevance. Default is by relevance.</small>
        </p>
        <p>
        <label for="<?php echo $this->get_field_id( 'age' ); ?>"><?php _e( 'News Age Limit (in hours):' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'age' ); ?>" name="<?php echo $this->get_field_name( 'age' ); ?>" type="text" value="<?php echo $age; ?>">
        <br>
        <small>Don’t show articles older than given period. 0 means no limit.</small>
        </p>
        <?php
        
        /*
        <p>
        <label for="<?php echo $this->get_field_id( 'link_type' ); ?>"><?php _e( 'Link mode:' ); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'link_type' ); ?>" name="<?php echo $this->get_field_name( 'link_type' ); ?>">
        <option value="">Default</option>
        <option value="frame" <?php if ( $link_type == "frame" ) echo 'selected="selected"' ?>>Framed</option>
        <option value="orig"<?php if ( $link_type == "orig" ) echo 'selected="selected"' ?>>Original</option>
        </select>
        <br>
        <small>Choose where headlines in your feed link to. These can be either direct links to original articles (bbc.co.uk) or those articles can be framed with your custom name/links.</small>
        </p>
        */

        ?>
        <?php
        }
        if ( $user_mode > 1 ) {
        ?>
        <p>
        <label for="<?php echo $this->get_field_id( 'feed_mode' ); ?>"><?php _e( 'Feed publishing:' ); ?></label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'feed_mode' ); ?>" name="<?php echo $this->get_field_name( 'feed_mode' ); ?>">
        <option value="">Default</option>
        <option value="auto" <?php if ( $feed_mode == "auto" ) echo 'selected="selected"' ?>>Automatic</option>
        <option value="manual"<?php if ( $feed_mode == "manual" ) echo 'selected="selected"' ?>>Manual</option>
        </select>
        <br>
        <small>Your feed can be automatically updated with new headlines, or you can choose headlines and publish them manually using news buffering. Default is automatic.</small>
        </p>
        <?php
        }
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_opts Values just sent to be saved.
     * @param array $old_opts Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update( $new_opts, $old_opts ) {
        $opts = array();
        $opts['title'] = ( ! empty( $new_opts['title'] ) ) ? strip_tags( $new_opts['title'] ) : '';
        $opts['keywords'] = ( ! empty( $new_opts['keywords'] ) ) ? strip_tags( $new_opts['keywords'] ) : '';
        $opts['count'] = ( ! empty( $new_opts['count'] ) ) ? absint( $new_opts['count'] ) : 5;
        $opts['age'] = ( ! empty( $new_opts['age'] ) ) ? absint( $new_opts['age'] ) : 0 ;
        $opts['sources'] = ( ! empty( $new_opts['sources'] ) ) ? strip_tags( $new_opts['sources'] ) : '';
        $opts['excluded_sources'] = ( ! empty( $new_opts['excluded_sources'] ) ) ? strip_tags( $new_opts['excluded_sources'] ) : '';
        $opts['search_mode'] = ( ! empty( $new_opts['search_mode'] ) ) ? strip_tags( $new_opts['search_mode'] ) : '';
        $opts['search_type'] = ( ! empty( $new_opts['search_type'] ) ) ? strip_tags( $new_opts['search_type'] ) : '';
        $opts['sort_mode'] = ( ! empty( $new_opts['sort_mode'] ) ) ? strip_tags( $new_opts['sort_mode'] ) : '';
        $opts['link_type'] = ( ! empty( $new_opts['link_type'] ) ) ? strip_tags( $new_opts['link_type'] ) : '';
        $opts['show_date'] = ! empty( $new_opts['show_date'] ) ;
        $opts['show_source'] = ! empty( $new_opts['show_source'] ) ;
        $opts['show_abstract'] = ! empty( $new_opts['show_abstract'] ) ;
        $opts['feed_mode'] = ( ! empty( $new_opts['feed_mode'] ) ) ? strip_tags( $new_opts['feed_mode'] ) : '';

        return $opts;
    }

}

/**
 * The NewsPlugin itself, for encapsulating the hooks.
 */
class News_Plugin {

    /**
     * Register plugin with WordPress.
     */
    function __construct() {
        // Widgets.
        add_action( 'widgets_init', array( $this, 'widgets_init' ) ) ;
        add_action( 'admin_init', array( $this, 'admin_init' ) ) ;
        add_action( 'admin_menu', array( $this, 'admin_menu' ) ) ;
        add_action( 'admin_init', array( &$this, 'register_help_section' ) ) ;
        add_action( 'admin_init', array( &$this, 'register_activation_section' ) ) ;
        add_action( 'admin_init', array( &$this, 'register_shortcode_section' ) ) ;
        add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );
    }

    /**
     * Register the plugin widget, widget areas and widget shorcodes.
     */
    function widgets_init() {
        register_widget( 'News_Plugin_Widget' );
        for ( $area = 1 ; $area <= 4 ; $area++ ) {
            register_sidebar( array(
              'name'=>"NewsPlugin Widget Area {$area}",
              'id'=>"newsplugin_widgets_{$area}",
              'description'=>"Use the [newsplugin_widgets&nbsp;area={$area}] shortcode to show your newsfeed anywhere you want.",
              'before_widget'=>'<div id="%1$s" class="widget %2$s">',
              'after_widget'=>'</div>'
            ) );
        }
        add_shortcode( 'newsplugin_widgets', array( $this, 'widget_area_shortcode' ) );
        add_shortcode( 'newsplugin_feed', array( $this, 'feed_shortcode' ) );
    }
    
    /**
     * Process the widget area shortcode.
     */
    function widget_area_shortcode( $attrs ) {
        $a = shortcode_atts( array( 'area' => '1' ), $attrs );
        $sidebar = "newsplugin_widgets_{$a['area']}" ;
        ob_start() ;
        if ( is_active_sidebar( $sidebar ) ) {
          echo '<div class="newsplugin_widget_area">' ;
          dynamic_sidebar( $sidebar ) ;
          echo '</div>' ;
        }
        return ob_get_clean();
    }


//[feed_shortcode title="" keywords="News" count="" age="" sources="" excluded_sources="" search_mode="" search_type="" sort_mode="" link_type="" show_date="" show_source="" show_abstract="" feed_mode=""]

    /**
     * Process the newsfeed shortcode.
     */
    function feed_shortcode( $attrs ) {
        $attrs = shortcode_atts( array(
          'id' => '',
          'title' => '',
          'keywords' => 'News',
          'count' => '',
          'age' => '',
          'sources' => '',
          'excluded_sources' => '',
          'search_mode' => '',
          'search_type' => '',
          'sort_mode' => '',
          'link_type' => '',
          'show_date' => '',
          'show_source' => '',
          'show_abstract' => '',
          'feed_mode' => ''
        ), $attrs );
        $a = News_Plugin_Widget::update( $attrs, array() );
        $a[ 'id' ] = $attrs[ 'id' ] ;
        ob_start() ;
        the_widget( 'News_Plugin_Widget', $a, array() );
        return ob_get_clean();
    }

    /**
     * Register the plugin CSS style.
     */
    function register_styles() {
        wp_register_style( 'news-plugin', plugins_url( 'news-plugin/news-plugin.css' ), array(), "0.1" );
        wp_enqueue_style( 'news-plugin' );
    }

    /**
     * Register the plugin options.
     */
	function admin_init() {
       add_settings_section(
           'default',
           NULL,
           NULL,
           'news-plugin-settings'
       );

       add_settings_field(
           'news_plugin_api_key',
           __('Activation Key:','news_plugin'),
           array( $this, 'settings_api_key' ),
           'news-plugin-settings',
           'default'
       );
       register_setting(
           'news-plugin-settings',
           'news_plugin_api_key',
           array( $this, 'validate_api_key' )
       );

       /* Disable User Mode for now.
       add_settings_field(
           'news_plugin_user_mode',
           __('Choose User Mode:','news_plugin'),
           array( $this, 'settings_user_mode' ),
           'news-plugin-settings',
           'default'
       );
       register_setting(
           'news-plugin-settings',
           'news_plugin_user_mode',
           array( $this, 'validate_user_mode' )
       );
       */
   }

    /**
     * Register the plugin menu.
     */
   function admin_menu() {
     add_menu_page(
         __('NewsPlugin Settings','news_plugin'),
         __('NewsPlugin','news_plugin'),
         'manage_options',
         'news-plugin-settings',
         array( $this, 'newsplugin_options_page'),
		 'dashicons-megaphone',
		 '3'
     );
     add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_action_links' ) );
   }
	
	/*
	 * For easier overriding I declared the keys
	 * here as well as our tabs array which is populated
	 * when registering settings
	 */
	private $activation_settings_key = 'newsplugin_activation_settings';
	private $shortcode_settings_key = 'newsplugin_shortcode_settings';
	private $help_settings_key = 'newsplugin_help_settings';
	private $plugin_options_key = 'news-plugin-settings';
	private $plugin_settings_tabs = array();
	
	/*
	 * Registering the sections.
	 */
	function register_activation_section() {
		$this->plugin_settings_tabs[$this->activation_settings_key] = 'Activate';
	}
	function register_shortcode_section() {
		$this->plugin_settings_tabs[$this->shortcode_settings_key] = 'Generate Shortcode';
	}
	function register_help_section() {
		$this->plugin_settings_tabs[$this->help_settings_key] = 'Instructions!';
	}
	
	/*
	 * Plugin Options page rendering goes here, checks
	 * for active tab and replaces key with the related
	 * settings key. Uses the plugin_options_tabs method
	 * to render the tabs.
	 */
	function newsplugin_options_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->help_settings_key;
		?>
		<div class="wrap">
            <h2>NewsPlugin Settings</h2>
			<?php $this->newsplugin_options_tabs( $tab ); ?>
            <?php $key = get_option( 'news_plugin_api_key' ); if ( empty( $key ) ) { ?>
            <div class="error">
                <p>Activate the NewsPlugin otherwise the generated shortcodes or NewsPlugin widgets will not work!</p>
            </div>
            <?php } ?>
            <?php if($tab === $this->activation_settings_key) { ?>
			<form method="post" action="options.php">
				<?php wp_nonce_field( 'update-options' ); ?>
				<?php settings_fields( $this->plugin_options_key ); ?>
				<?php do_settings_sections( $this->plugin_options_key ); ?>
				<?php submit_button(); ?>
			</form>
            <?php } else if($tab === $this->shortcode_settings_key && ! empty( $key ) ) { ?>
            <table id="shortcodeTable" class="form-table">
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_title">Newsfeed Title: </label>
                </th>
                <td>
            		<input type="text" id="newsplugin_title" name="newsplugin_title" value="" class="regular-text" onclick="validationFocus('newsplugin_title')" onfocus="validationFocus('newsplugin_title')">
                    <p class="description">Give your feed a good name. For example: Canada Solar Energy News</p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_keywords">Keywords: </label>
                </th>
                <td>
            		<input type="text" id="newsplugin_keywords" name="newsplugin_keywords" value="" class="regular-text" onclick="validationFocus('newsplugin_keywords')" onfocus="validationFocus('newsplugin_keywords')">
                    <p class="description">Use keywords to find relevant news. Example: Canada AND "Solar Energy"
                      <br>
                      Visit the <a href="http://newsplugin.com/faq#keyword-tips">FAQ</a> for keywords tips.
                    </p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_articles">Number of Articles: </label>
                </th>
                <td>
            		<input type="text" id="newsplugin_articles" name="newsplugin_articles" value="" class="regular-text" onclick="validationFocus('newsplugin_articles')" onfocus="validationFocus('newsplugin_articles')">
                    <p class="description">Set how many headlines to show in your feed. Example: 10</p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	More Information:
                </th>
                <td>
                	<fieldset>
                	<label for="newsplugin_more_dates">
            			<input type="checkbox" id="newsplugin_more_dates" name="newsplugin_more_dates">
                        Show Dates
                    </label>
                    <br>
                	<label for="newsplugin_more_sources">
            			<input type="checkbox" id="newsplugin_more_sources" name="newsplugin_more_sources">
                        Show Sources
                    </label>
                    <br>
                	<label for="newsplugin_more_abstracts">
            			<input type="checkbox" id="newsplugin_more_abstracts" name="newsplugin_more_abstracts">
                        Show Abstracts
                    </label>
                    <br>
                    <p class="description">By default, your feed displays headlines only. You can add more information. Example: New Reports on Canada Solar Energy, 12 Feb 2015 (BBC)</p>
                    </fieldset>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_search">Search Mode: </label>
                </th>
                <td>
            		<select id="newsplugin_search" name="newsplugin_search">
                    	<option value="">Default</option>
                    	<option value="title">Headline Only</option>
                    	<option value="text">Headline &amp; Full Text</option>
                    </select>
                    <p class="description">Show news that has your keywords in a headline or anywhere in an article. Default is headlines and full text.</p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_sort">Sort Mode: </label>
                </th>
                <td>
            		<select id="newsplugin_sort" name="newsplugin_sort">
                    	<option value="">Default</option>
                    	<option value="relevance">Relevance</option>
                    	<option value="date">Date</option>
                    </select>
                    <p class="description">Show feed sorted by date or relevance. Default is by relevance.</p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_age">News Age Limit (in hours): </label>
                </th>
                <td>
            		<input type="text" id="newsplugin_age" name="newsplugin_age" value="0" class="regular-text">
                    <p class="description">Don’t show articles older than given period. 0 means no limit.</p>
                </td>
            </tr>
        	<tr>
            	<th scope="row">
                	<label for="newsplugin_publishing">Feed Publishing: </label>
                </th>
                <td>
            		<select id="newsplugin_publishing" name="newsplugin_publishing">
                    	<option value="">Default</option>
                    	<option value="auto">Automatic</option>
                    	<option value="manual">Manual</option>
                    </select>
                    <p class="description">Your feed can be automatically updated with new headlines, or you can choose headlines and publish them manually using news buffering. Default is automatic.</p>
                </td>
            </tr>
            </table>
            <p class="submit">
            	<?php add_thickbox(); ?>
                <div id="shortcode-generated" style="display:none;"></div>
            	<input type="button" value="Generate Shortcode" class="button button-primary" onclick="validateShortcode()">
            </p>
            <script type="text/javascript">
				function validationFocus(id) {
					document.getElementById(id).style.border = "1px solid #ddd";
					document.getElementById(id).style.boxShadow = "0 1px 2px rgba(0, 0, 0, 0.07) inset";
				}
				function validateShortcode() {
					var newsplugin_title = document.getElementById('newsplugin_title');
					var newsplugin_keywords = document.getElementById('newsplugin_keywords');
					var newsplugin_articles = document.getElementById('newsplugin_articles');
					if(newsplugin_title.value == "" || /^\s*$/.test(newsplugin_title.value) || newsplugin_keywords.value == "" || /^\s*$/.test(newsplugin_keywords.value) || newsplugin_articles.value == "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
						if(newsplugin_title.value == "" || /^\s*$/.test(newsplugin_title.value)) {
							newsplugin_title.style.border = "1px solid #ff0000";
							newsplugin_title.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
						}
						if(newsplugin_keywords.value == "" || /^\s*$/.test(newsplugin_keywords.value)) {
							newsplugin_keywords.style.border = "1px solid #ff0000";
							newsplugin_keywords.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
						}
						if(newsplugin_articles.value == "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
							newsplugin_articles.style.border = "1px solid #ff0000";
							newsplugin_articles.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
						}
						window.scrollTo(0,0);
						if(!jQuery(".error").length) {
							jQuery("<div class='error'><p>Fill the required fields properly.</p></div>").insertBefore("#shortcodeTable");
						}
					} else {
						window.scrollTo(0,0);
						generateShortcode();
						jQuery(".error").hide();
					}
				}
				function generateShortcode() {
					var shortcode_params = "";
					var newsplugin_title = document.getElementById('newsplugin_title').value;
					if(newsplugin_title != "") {
						shortcode_params += " title='"+newsplugin_title+"'";
					}
					var newsplugin_keywords = document.getElementById('newsplugin_keywords').value;
					if(newsplugin_keywords != "") {
						shortcode_params += " keywords='"+newsplugin_keywords+"'";
					}
					var newsplugin_articles = Math.abs(parseInt(document.getElementById('newsplugin_articles').value));
					if(newsplugin_articles != "" && !isNaN(newsplugin_articles)) {
						shortcode_params += " count='"+newsplugin_articles+"'";
					}
					var newsplugin_more_dates = document.getElementById('newsplugin_more_dates').checked;
					if(newsplugin_more_dates) {
						shortcode_params += " show_date='true'";
					}
					var newsplugin_more_sources = document.getElementById('newsplugin_more_sources').checked;
					if(newsplugin_more_sources) {
						shortcode_params += " show_source='true'";
					}
					var newsplugin_more_abstracts = document.getElementById('newsplugin_more_abstracts').checked;
					if(newsplugin_more_dates) {
						shortcode_params += " show_abstract='true'";
					}
					var newsplugin_search = document.getElementById('newsplugin_search').value;
					if(newsplugin_search != "") {
						shortcode_params += " search_mode='"+newsplugin_search+"'";
					}
					var newsplugin_sort = document.getElementById('newsplugin_sort').value;
					if(newsplugin_sort != "") {
						shortcode_params += " sort_mode='"+newsplugin_sort+"'";
					}
					var newsplugin_age = Math.abs(parseInt(document.getElementById('newsplugin_age').value));
					if(newsplugin_age != "" && !isNaN(newsplugin_age)) {
						shortcode_params += " age='"+newsplugin_age+"'";
					}
					var newsplugin_publishing = document.getElementById('newsplugin_publishing').value;
					if(newsplugin_publishing != "") {
						shortcode_params += " feed_mode='"+newsplugin_publishing+"'";
					}
					document.getElementById('shortcode-generated').innerHTML = "<p>Press Ctrl+C to copy to clipboard and paste it in your posts or pages.</p>";
					document.getElementById('shortcode-generated').innerHTML += "<p><textarea id='shortcode-field' onfocus='this.select()' onclick='this.select()' readonly='readonly' style='width:300px; height:100px; max-width:300px; max-height:100px; min-width:300px; min-height:100px;'>[newsplugin_feed id='"+new Date().valueOf()+"'"+shortcode_params+"]</textarea></p>";
					tb_show("NewsPlugin Shortcode Generated!", "#TB_inline?width=310&height=205&inlineId=shortcode-generated");
					document.getElementById('shortcode-field').focus();
					return false;
				}
			</script>
            <?php } else if($tab === $this->help_settings_key) { ?>
            	<h3>Instructions</h3>
                <p>Please read the instructions below carefully to easily setup and use the NewsPlugin.</p>
                <p><strong>Enter Activation Key:</strong> First of all, enter your Activation Key in the <a href="<?php echo admin_url( 'admin.php?page=news-plugin-settings&tab='.$this->activation_settings_key ) ?>">Activate</a> tab.</p>
                <p><strong>Generate Shortcode:</strong> Then you can create your newsfeed by generating shortcode from <a href="<?php echo admin_url( 'admin.php?page=news-plugin-settings&tab='.$this->shortcode_settings_key ) ?>">Generate Shortcode</a> tab. Put this shortcode in posts or pages where you want to display your newsfeed.</p>
                <p><strong>Create NewsFeed from Widgets: </strong>OR you can create your newsfeed from <a href="<?php echo admin_url( 'widgets.php' ) ?>">Appearance &gt; Widgets</a> and from the widgets panel drag the "NewsPlugin" widget to the desired sidebar or widget area where you want to show your newsfeed. The NewsPlugin added 4 new widget areas, you can use their shortcodes to show your newsfeed anywhere you want.</p>
                <p><strong>Setup / Customize your newsfeed:</strong> Edit the widget features to create/edit your newsfeed. Choose the name, number of headlines, keywords and other settings.</p>
                <p><strong>Preview: </strong> Preview your site to see your newsfeed with news headlines.</p>
                <p><strong>Edit headlines (if you want to):</strong> You can remove unwanted headlines or star the good ones right from your site. Note that you must be logged in to WordPress as an administrator or an editor to see the Edit Newsfeed Mode link on your page (next to your newsfeed).</p>
                <h3>Support</h3>
                <p>For more information about this plugin, please visit <a href="http://newsplugin.com" target="_blank">NewsPlugin.com</a> and the <a href="http://newsplugin.com/faq" target="_blank">FAQ</a>. Thanks for using the NewsPlugin, and we hope you like it.</p>
                <p><a href="http://newsplugin.com/contact" target="_blank">Contact us!</a></p>
            <?php } ?>
		</div>
		<?php
	}
	
	/*
	 * Renders our tabs in the plugin options page,
	 * walks through the object's tabs array and prints
	 * them one by one. Provides the heading for the
	 * plugin_options_page method.
	 */
	function newsplugin_options_tabs( $current_tab ) {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';	
		}
		echo '</h2>';
	}
   
   /**
    * Add link to the options page to the plugin action links.
    */
   function add_action_links( $default_links ) {
     $links = array(
       '<a href="' . admin_url( 'admin.php?page=news-plugin-settings' ) . '">Settings</a>',
     ) ;
     return array_merge( $links, $default_links ) ;
   }

   /**
    * Render the API key settings.
    */
   function settings_api_key() {
       $v = get_option( 'news_plugin_api_key' ) ;
       echo '<input class="regular-text" name="news_plugin_api_key" id="news_plugin_api_key" type="text" size="64" value="' . esc_attr( $v ) . '" />';
       echo '<p class="description">';
       echo 'You can get it at <a href="http://my.newsplugin.com/register" target="_blank">http://my.newsplugin.com/register</a>.';
       echo '</p>';
   }

   /**
    * Validate the API key settings.
    */
   function validate_api_key( $input ) {
       return sanitize_text_field( $input ) ;
   }
   
   /**
    * Render the user mode settings.
    */
   function settings_user_mode() {
       $v = get_option( 'news_plugin_user_mode' ) ;
       echo '<p>';
       echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_0" value="0"', ( $v == 0 ? ' checked="checked"' : '' ), '>';
       echo '<label for="news_plugin_user_mode_0">Basic - Simple &amp; easy way to start with.</label>';
       echo '<br>';
       echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_1" value="1"', ( $v == 1 ? ' checked="checked"' : '' ), '>';
       echo '<label for="news_plugin_user_mode_1">Advanced - More features for advanced users.</label>';
       echo '<br>';
       echo '<input type="radio" name="news_plugin_user_mode" id="news_plugin_user_mode_2" value="2"', ( $v == 2 ? ' checked="checked"' : '' ), '>';
       echo '<label for="news_plugin_user_mode_2">Expert - Manual publishing mode for professionals.</label>';
       echo '</p>';
   }

   /**
    * Validate the user mode settings.
    */
   function validate_user_mode( $input ) {
       $v = absint( $input ) ;
       return ( $v < 3 ? $v : 0 ) ;
   }
   
}

// Hook ourselves into the Wordpress.
new News_Plugin() ;

?>
