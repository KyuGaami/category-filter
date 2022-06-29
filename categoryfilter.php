<?php
defined('ABSPATH') or die('No script kiddies please!');
/*
Plugin Name: CategoryFilter
Description: Display Category Filter with load more
Version:     1.0
*/


function category_filter($atts)
{

    $js_relative_path = '/asset/category_section.js';


    // Register JavaScript
    wp_register_script('category_section', plugins_url($js_relative_path, __FILE__), '', '', true);

    $trending_resource_param = array('trending_category_resource' => get_template_directory_uri() . '/assets');

    // Pass PHP variable to JavaScript
    //wp_localize_script( 'category_section', 'trending_resource_param', $trending_resource_param );
    wp_localize_script('category_section', 'bobz', array(
        'nonce' => wp_create_nonce('bobz'),
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    // Enqueue JavaScript
    wp_enqueue_script('category_section');

    $a = shortcode_atts(array(
        'tax' => 'dealers_category', // Taxonomy
        'terms' => false, // Get specific taxonomy terms only
        'active' => false, // Set active term by ID
        'per_page' => 4, // How many posts per page,
        'pager' => 'infscr' // 'pager' to use numbered pagination || 'infscr' to use infinite scroll
    ), $atts);

    $result = NULL;
    $terms = get_terms($a['tax']);


    if (count($terms)) :
        ob_start(); ?>
        <div id="container-async" data-paged="<?= $a['per_page']; ?>" class="sc-ajax-filter sc-ajax-filter-multi">
            <ul class="nav-filter">
                <li>
                    <a href="#" data-filter="<?= $terms[0]->taxonomy; ?>" data-term="all-terms" data-page="1">
                        Show All
                    </a>
                </li>
                <?php foreach ($terms as $term) : ?>
                    <li<?php if ($term->term_id == $a['active']) : ?> class="active"<?php endif; ?>>
                        <a href="<?= get_term_link($term, $term->taxonomy); ?>" data-filter="<?= $term->taxonomy; ?>"
                           data-term="<?= $term->slug; ?>" data-page="1">
                            <?= $term->name; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="status"></div>
            <div class="content"></div>

            <?php if ($a['pager'] == 'infscr') : ?>
                <nav class="pagination infscr-pager">
                    <a href="#page-2" class="btn btn-primary">Load More</a>
                </nav>
            <?php endif; ?>
        </div>

        <?php $result = ob_get_clean();
    endif;

    return $result;
}

add_shortcode('categoryfilter', 'category_filter');

function category_filter_posts()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bobz'))
        die('Permission denied');

    /**
     * Default response
     */
    $response = [
        'status' => 500,
        'message' => 'Something is wrong, please try again later ...',
        'content' => false,
        'found' => 0
    ];


    $all = false;
    $terms = $_POST['params']['terms'];
    $page = $_POST['params']['page'];
    $qty = $_POST['params']['qty'];
    $pager = isset($_POST['pager']) ? $_POST['pager'] : 'infscr';
    $tax_qry = [];
    $msg = '';

    /**
     * Check if term exists
     */
    if (!is_array($terms)) :
        $response = [
            'status' => 501,
            'message' => 'Term doesn\'t exist',
            'content' => 0
        ];

        die(json_encode($response));
    else :

        foreach ($terms as $tax => $slugs) :

            if (in_array('all-terms', $slugs)) {
                $all = true;
            }

            $tax_qry[] = [
                'taxonomy' => $tax,
                'field' => 'slug',
                'terms' => $slugs,
            ];
        endforeach;
    endif;

    /**
     * Setup query
     */
    $args = [
        'paged' => $page,
        'post_type' => 'dealers',
        'post_status' => 'publish',
        'posts_per_page' => $qty,
        'order' => 'desc',
        'orderby' => 'name'
    ];

    if ($tax_qry && !$all) :
        $args['tax_query'] = $tax_qry;
    endif;

    $qry = new WP_Query($args);


    ob_start();
    if ($qry->have_posts()) :
        while ($qry->have_posts()) : $qry->the_post(); ?>

            <article class="loop-item">
                <header>
                    <h2 class="entry-title">
                        <a href="<?php the_permalink(); ?>">
                            <?php echo get_the_post_thumbnail($post_id, 'thumbnail'); ?>
                            <?php the_title(); ?></a></h2>
                </header>
                <div class="entry-summary">
                    <?php the_excerpt(); ?>
                </div>
            </article>

        <?php endwhile;

        /*foreach ($tax_qry as $tax) :
            $msg .= 'Displaying terms: ';

            foreach ($tax['terms'] as $trm) :
                $msg .= $trm . ', ';
            endforeach;

            $msg .= ' from taxonomy: ' . $tax['taxonomy'];
            $msg .= '. Found: ' . $qry->found_posts . ' posts';
        endforeach;*/

        $response = [
            'status' => 200,
            'found' => $qry->found_posts,
            'message' => $msg,
            'method' => $pager,
            'next' => $page + 1
        ];


    else :

        $response = [
            'status' => 201,
            'message' => 'No posts found',
            'next' => 0
        ];

    endif;

    $response['content'] = ob_get_clean();

    die(json_encode($response));

}
add_action('wp_ajax_do_filter_posts_mt', 'category_filter_posts');
add_action('wp_ajax_nopriv_do_filter_posts_mt', 'category_filter_posts');
?>