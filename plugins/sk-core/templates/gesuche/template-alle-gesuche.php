<?php
/**
 * Template Name: Alle Gesuche
 */

wp_enqueue_style( 'sk-gesuche-list' );

get_header();
?>

<div id="primary" class="content-area alle-gesuche-page">
  <main id="main" class="site-main">

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>
        <?php if (!empty(get_the_excerpt())): ?>
          <p class="entry-subtitle"><?php echo esc_html(get_the_excerpt()); ?></p>
        <?php endif; ?>
      </header>

      <div class="entry-content alle-gesuche-seite">
        <div class="gesuche-grid-container">
          <?php
          $args = [
            'post_type'      => 'gesuch',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
          ];
          $loop = new WP_Query($args);

          if ($loop->have_posts()) :
            while ($loop->have_posts()) : $loop->the_post();
              $author_id       = get_the_author_meta('ID');
              $is_sk_seller = function_exists('sk_is_user_seller') ? sk_is_user_seller($author_id) : false;
              $store_name      = '';
              $store_url       = '';

              if ($is_sk_seller) {
                $store_name = get_user_meta($author_id, 'sk_store_name', true);
                $store_url  = sk_get_store_url($author_id);
              }

              $avatar_url = get_avatar_url($author_id, ['size' => 96]);
              if (strpos($avatar_url, 'gravatar.com') !== false) {
                $avatar_url = get_stylesheet_directory_uri() . '/assets/default-avatar.jpg';
              }

              $date_iso  = get_the_date('c');
              $date_text = get_the_date('d.m.Y');
              $time_ago  = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' her';
              ?>
              <article class="gesuch-card">
                <div class="gesuch-card__inner">
                  <header class="gesuch-header">
                    <div class="vendor-info">
                      <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="vendor-avatar" loading="lazy" decoding="async">
                      <div class="vendor-meta">
                        <?php if ($is_sk_seller && !empty($store_name) && !is_wp_error($store_url)) : ?>
                          <a href="<?php echo esc_url($store_url); ?>" class="vendor-link">@<?php echo esc_html($store_name); ?></a>
                        <?php else : ?>
                          <span class="vendor-name">@<?php echo esc_html(get_the_author()); ?></span>
                        <?php endif; ?>
                        <time class="created-date" datetime="<?php echo esc_attr($date_iso); ?>"
                              title="<?php echo esc_attr(get_the_date('d.m.Y H:i')); ?>">
                          <?php echo esc_html($date_text); ?> · <?php echo esc_html($time_ago); ?>
                        </time>
                      </div>
                    </div>

                    <h3 class="gesuch-title">
                      <a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a>
                    </h3>
                  </header>

                  <div class="gesuch-content">
                    <?php the_content(); /* Volltext, visuell geclamped */ ?>
                  </div>

                  <footer class="gesuch-footer">
                    <a class="btn btn-outline-orange" href="<?php the_permalink(); ?>">Ansehen</a>
                  </footer>
                </div>
              </article>
              <?php
            endwhile;
            ?>
        </div>
        <?php
          else :
            echo '<p class="empty-state">Es wurden noch keine Gesuche veröffentlicht.</p>';
          endif;
          wp_reset_postdata();
        ?>
      </div>

      <?php if (comments_open() || get_comments_number()) :
        comments_template();
      endif; ?>
    </article>

  </main>
</div>


<?php
get_sidebar();
get_footer();
