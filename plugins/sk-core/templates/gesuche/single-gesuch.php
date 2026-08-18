<?php
/* Single Template für CPT "gesuch" */
wp_enqueue_style( 'sk-gesuch-single' );
wp_enqueue_script( 'sk-gesuch-copy-link' );

get_header();
?>


<div class="gesuch-single-wrap">
<?php
if ( have_posts() ) :
  while ( have_posts() ) : the_post();

    $author_id       = get_the_author_meta('ID');
    $is_sk_seller = function_exists('sk_is_user_seller') ? sk_is_user_seller($author_id) : false;
    $store_name      = '';
    $store_url       = '';

    if ( $is_sk_seller ) {
      $store_name = get_user_meta($author_id, 'sk_store_name', true);
      $store_url  = sk_get_store_url($author_id);
    }

    $avatar_url = get_avatar_url($author_id, ['size' => 96]);
    if (strpos($avatar_url, 'gravatar.com') !== false) {
      $avatar_url = get_stylesheet_directory_uri() . '/assets/default-avatar.jpg';
    }

    $date_iso  = get_the_date('c');
    $date_txt  = get_the_date('d.m.Y');
    $time_ago  = human_time_diff(get_the_time('U'), current_time('timestamp')) . ' her';
?>
  <article class="gesuch-card-single">
    <div class="gesuch-single-inner">

      <header class="gesuch-single-header">
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
              <?php echo esc_html($date_txt . ' · ' . $time_ago); ?>
            </time>
          </div>
        </div>

        <h1 class="gesuch-title"><?php the_title(); ?></h1>
      </header>

      <div class="gesuch-content">
        <?php the_content(); ?>
      </div>

      <hr class="hr-soft">

      <section class="gesuch-kontakt">
        <h2>Kontakt</h2>
        <div class="vendor-info">
          <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="vendor-avatar" loading="lazy" decoding="async">
          <?php if ($is_sk_seller && !empty($store_name) && !is_wp_error($store_url)) : ?>
            <a href="<?php echo esc_url($store_url); ?>" class="vendor-link">@<?php echo esc_html($store_name); ?></a>
          <?php else : ?>
            <span class="vendor-name">@<?php echo esc_html(get_the_author()); ?></span>
          <?php endif; ?>
        </div>
      </section>

      <div class="gesuch-footer">
        <a class="btn btn-outline-orange" href="<?php echo esc_url(home_url('/alle-gesuche/')); ?>">
          Zur Übersicht
        </a>
        <a class="btn btn-secondary" href="#" id="copy-gesuch-link" data-url="<?php echo esc_url( get_permalink() ); ?>">
          Link kopieren
        </a>
        <span id="copy-ok" class="copy-ok">kopiert ✓</span>
      </div>

    </div>
  </article>
<?php
  endwhile;
endif;
?>
</div>

<?php
get_footer();
