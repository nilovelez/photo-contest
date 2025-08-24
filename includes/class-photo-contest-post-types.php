<?php

class Photo_Contest_Post_Types {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Configure editor settings for photos post type
        add_filter('wp_editor_settings', array($this, 'configure_editor_settings'), 10, 2);
        
        // Add custom CSS for the editor
        add_action('admin_head', array($this, 'add_editor_css'));

        // Add featured image column to admin list
        add_filter('manage_photos_posts_columns', array($this, 'add_featured_image_column'));
        add_action('manage_photos_posts_custom_column', array($this, 'display_featured_image_column'), 10, 2);

        // Add photo_author column to admin list
        add_filter('manage_photos_posts_columns', array($this, 'add_photo_author_column'));
        add_action('manage_photos_posts_custom_column', array($this, 'display_photo_author_column'), 10, 2);

        // Register the photo template
        add_action('init', array($this, 'register_photo_template'));
        
        // Register photo_tag taxonomy
        add_action('init', array($this, 'register_photo_tag_taxonomy'));
        
        // Add taxonomy filter to admin list
        add_action('restrict_manage_posts', array($this, 'add_photo_tag_filter'));
    }

    /**
     * Add custom CSS for the editor
     */
    public function add_editor_css() {
        global $post;
        if ($post && $post->post_type === 'photos') {
            echo '<style>
                #postdivrich #content_ifr {
                    height: 100px !important;
                }
                #postdivrich #content {
                    height: 100px !important;
                }
            </style>';
        }
    }

    /**
     * Configure editor settings for photos post type
     *
     * @since    1.0.0
     * @param    array    $settings    The editor settings.
     * @param    string   $editor_id   The editor ID.
     * @return   array                 The modified settings.
     */
    public function configure_editor_settings($settings, $editor_id) {
        global $post;
        if ($post && $post->post_type === 'photos' && $editor_id === 'content') {
            $settings['media_buttons'] = false;
            $settings['textarea_rows'] = 2;
            $settings['teeny'] = true;
            $settings['quicktags'] = false;
            $settings['tinymce'] = false;
        }
        return $settings;
    }

    public function register_photo_post_type() {
        $labels = array(
            'name'                  => _x('Photos', 'Post Type General Name', 'photo-contest'),
            'singular_name'         => _x('Photo', 'Post Type Singular Name', 'photo-contest'),
            'menu_name'            => __('Photos', 'photo-contest'),
            'name_admin_bar'       => __('Photo', 'photo-contest'),
            'archives'             => __('Photo Archives', 'photo-contest'),
            'attributes'           => __('Photo Attributes', 'photo-contest'),
            'parent_item_colon'    => __('Parent Photo:', 'photo-contest'),
            'all_items'            => __('All Photos', 'photo-contest'),
            'add_new_item'         => __('Add New Photo', 'photo-contest'),
            'add_new'              => __('Add New', 'photo-contest'),
            'new_item'             => __('New Photo', 'photo-contest'),
            'edit_item'            => __('Edit Photo', 'photo-contest'),
            'update_item'          => __('Update Photo', 'photo-contest'),
            'view_item'            => __('View Photo', 'photo-contest'),
            'view_items'           => __('View Photos', 'photo-contest'),
            'search_items'         => __('Search Photo', 'photo-contest'),
            'not_found'            => __('Not found', 'photo-contest'),
            'not_found_in_trash'   => __('Not found in Trash', 'photo-contest'),
            'featured_image'       => __('Featured Image', 'photo-contest'),
            'set_featured_image'   => __('Set featured image', 'photo-contest'),
            'remove_featured_image' => __('Remove featured image', 'photo-contest'),
            'use_featured_image'   => __('Use as featured image', 'photo-contest'),
            'insert_into_item'     => __('Insert into photo', 'photo-contest'),
            'uploaded_to_this_item' => __('Uploaded to this photo', 'photo-contest'),
            'items_list'           => __('Photos list', 'photo-contest'),
            'items_list_navigation' => __('Photos list navigation', 'photo-contest'),
            'filter_items_list'    => __('Filter photos list', 'photo-contest'),
        );

        $args = array(
            'label'                 => __('Photo', 'photo-contest'),
            'description'           => __('Photo entries for the contest', 'photo-contest'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-camera',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rewrite'               => array('slug' => 'photos'),
        );

        register_post_type('photos', $args);
    }

    /**
     * Add featured image column to admin list
     *
     * @param array $columns The existing columns
     * @return array The modified columns
     */
    public function add_featured_image_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns['featured_image'] = __('Featured Image', 'photo-contest');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    /**
     * Display featured image in the admin list
     *
     * @param string $column The column name
     * @param int $post_id The post ID
     */
    public function display_featured_image_column($column, $post_id) {
        if ($column === 'featured_image') {
            $thumbnail_url = get_post_meta($post_id, 'photo_thumbnail_url', true);
            if (!empty($thumbnail_url)) {
                echo '<img src="' . esc_url($thumbnail_url) . '" alt="Photo thumbnail" style="width: 50px; height: 50px; object-fit: cover;">';
            } else {
                echo '<span class="dashicons dashicons-format-image"></span>';
            }
        }
    }  
    /**
     * Add photo_author column to admin list
     */
    public function add_photo_author_column($columns) {
        $columns['photo_author'] = __('Author', 'photo-contest');
        return $columns;
    }
    /**
     * Display photo_author in the admin list
     */
    public function display_photo_author_column($column, $post_id) {
        if ($column === 'photo_author') {
            echo get_post_meta($post_id, 'photo_author', true);
        }
    }


    /**
     * Register the photo template
     */
    public function register_photo_template() {
        register_block_template('photo-contest//single-photo', [
            'title' => __('Photo Entry', 'photo-contest'),
            'description' => __('Template for displaying a photo entry from the WordPress Photo Directory.', 'photo-contest'),
            'content' => '
                <!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->
                <!-- wp:group {"tagName":"main","className":"site-main"} -->
                <main class="wp-block-group site-main">
                    <!-- wp:post-title {"level":1} /-->
                    <!-- wp:group {"className":"entry-meta"} -->
                    <div class="wp-block-group entry-meta">
                        <!-- wp:paragraph -->
                        <p>Posted by <span class="photo-author"></span> on <span class="photo-date"></span></p>
                        <!-- /wp:paragraph -->
                    </div>
                    <!-- /wp:group -->
                    <!-- wp:post-featured-image {"sizeSlug":"large"} /-->
                    <!-- wp:post-content /-->
                    <!-- wp:group {"className":"entry-footer"} -->
                    <div class="wp-block-group entry-footer">
                        <!-- wp:paragraph -->
                        <p><a href="#" class="photo-link" target="_blank">View on WordPress Photo Directory</a></p>
                        <!-- /wp:paragraph -->
                    </div>
                    <!-- /wp:group -->
                </main>
                <!-- /wp:group -->
                <!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
            ',
            'post_types' => ['photos'],
            'template_lock' => false
        ]);

        register_block_template('photo-contest//archive-photos', [
            'title' => __('Photos Archive', 'photo-contest'),
            'description' => __('Template for displaying all photos in a grid layout.', 'photo-contest'),
            'content' => '
                <!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"site-main","layout":{"type":"constrained"}} -->
<main class="wp-block-group site-main"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Photos</h1>
<!-- /wp:heading -->

<!-- wp:query {"queryId":1,"query":{"perPage":12,"pages":0,"offset":0,"postType":"photos","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"align":"wide","layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"className":"photo-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}},"border":{"radius":"8px"}},"backgroundColor":"base"} -->
<div class="wp-block-group photo-card has-base-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:post-featured-image {"width":"100%","height":""} /-->

<!-- wp:post-title {"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"medium"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"paginationArrow":"arrow","align":"wide","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
            ',
            'post_types' => ['photos'],
            'template_lock' => false
        ]);
    }

    /**
     * Register photo_tag taxonomy
     */
    public function register_photo_tag_taxonomy() {
        $labels = array(
            'name'                       => _x('Photo Tags', 'taxonomy general name', 'photo-contest'),
            'singular_name'              => _x('Photo Tag', 'taxonomy singular name', 'photo-contest'),
            'search_items'               => __('Search Photo Tags', 'photo-contest'),
            'popular_items'              => __('Popular Photo Tags', 'photo-contest'),
            'all_items'                  => __('All Photo Tags', 'photo-contest'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Photo Tag', 'photo-contest'),
            'update_item'                => __('Update Photo Tag', 'photo-contest'),
            'add_new_item'               => __('Add New Photo Tag', 'photo-contest'),
            'new_item_name'              => __('New Photo Tag Name', 'photo-contest'),
            'separate_items_with_commas' => __('Separate photo tags with commas', 'photo-contest'),
            'add_or_remove_items'        => __('Add or remove photo tags', 'photo-contest'),
            'choose_from_most_used'      => __('Choose from the most used photo tags', 'photo-contest'),
            'not_found'                  => __('No photo tags found.', 'photo-contest'),
            'menu_name'                  => __('Photo Tags', 'photo-contest'),
        );

        $args = array(
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'show_in_nav_menus'     => true,
            'show_tagcloud'         => true,
            'show_in_rest'          => true,
            'query_var'             => true,
            'rewrite'               => array('slug' => 'photo-tag'),
        );

        register_taxonomy('photo_tag', array('photos'), $args);
    }

    /**
     * Add photo_tag filter to admin list
     */
    public function add_photo_tag_filter() {
        global $typenow;
        
        // Only show filter on photos post type
        if ($typenow !== 'photos') {
            return;
        }

        // Get current selected term
        $selected = isset($_GET['photo_tag']) ? $_GET['photo_tag'] : '';

        // Get all photo tags
        $terms = get_terms(array(
            'taxonomy' => 'photo_tag',
            'hide_empty' => true,
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            echo '<select name="photo_tag" id="photo_tag_filter">';
            echo '<option value="">' . __('All Photo Tags', 'photo-contest') . '</option>';
            
            foreach ($terms as $term) {
                $selected_attr = ($selected == $term->slug) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($term->slug) . '" ' . $selected_attr . '>';
                echo esc_html($term->name) . ' (' . $term->count . ')';
                echo '</option>';
            }
            
            echo '</select>';
        }
    }

} 