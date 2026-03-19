<?php
/**
 * Template Name: Alle Gesuche
 */

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
                      <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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

<style>
/* ===== Page Container ===== */
#primary.content-area.alle-gesuche-page{
  width:100%;
  max-width:1320px;
  box-sizing:border-box;
  margin:0 auto;
  padding:0 20px 40px;
}

/* ===== Header ===== */
.entry-header{ text-align:left; margin: 16px 0 24px; }
.entry-title{
  margin:0;
  font-size: clamp(1.6rem, 1.1rem + 2vw, 2.2rem);
  line-height:1.15;
  color:#fff;
}
.entry-subtitle{ margin:.35rem 0 0; color:#cbd5e1; }

/* ===== Grid ===== */
.gesuche-grid-container{
  display:grid;
  gap: 20px;
  grid-template-columns: 1fr;
  padding-bottom: 40px;
}
@media (min-width: 768px){
  .gesuche-grid-container{ grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1100px){
  .gesuche-grid-container{ grid-template-columns: repeat(3, 1fr); }
}

/* ===== Card: jetzt #252d38 ===== */
.gesuch-card{
  position:relative;
  border-radius:14px;
  background:#252d38;                 /* NEU: statt Gradient */
  border: 1px solid #2e3847;          /* kühler Rahmen */
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
  overflow:hidden;
  display:flex;
  flex-direction:column;
}
.gesuch-card:hover{
  transform: translateY(-3px);
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  box-shadow: 0 16px 40px rgba(0,0,0,.35);
  border-color:#344155;
}
/* optional: Akzentkante oben beibehalten */
.gesuch-card::before{
  content:"";
  position:absolute; inset:0 0 auto 0; height:3px;
  background: linear-gradient(90deg, rgba(247,147,26,.7), rgba(247,147,26,0) 40%, rgba(247,147,26,0) 60%, rgba(247,147,26,.7));
  opacity:.35;
}

/* Innerer Flex-Wrapper */
.gesuch-card__inner{
  display:flex; flex-direction:column;
  padding:16px 16px 14px;
  min-height: 260px;
}

/* Header block */
.gesuch-header{
  display:flex; flex-direction:column; gap:10px; margin-bottom:8px;
}
.vendor-info{ display:flex; align-items:center; gap:10px; }
.vendor-avatar{
  width:40px; height:40px; border-radius:50%;
  object-fit:cover; flex:0 0 40px; border:1px solid #2e3847;
}
.vendor-meta{ display:flex; flex-direction:column; line-height:1.2; }
.vendor-link, .vendor-name{ color:#e5e7eb; font-weight:700; text-decoration:none; }
.vendor-link:hover{ color:#F7931A; }
.created-date{ font-size:.86rem; color:#92a0b3; }

/* Title */
.gesuch-title{ margin:2px 0 0; font-size: clamp(1.05rem, .95rem + .5vw, 1.25rem); }
.gesuch-title a{
  color:#fff; text-decoration:none;
  display:-webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2;
  overflow:hidden;
}
.gesuch-title a:hover{ color:#F7931A; }

/* Content (clamped visually) */
.gesuch-content{
  color:#d6dbe3;
  margin-top:6px; margin-bottom:10px;
  overflow:hidden;
  display:-webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 8;
  flex-grow:1;
}
.gesuch-content p:last-child{ margin-bottom:0; }

/* Footer / Actions */
.gesuch-footer{
  margin-top:auto;
  display:flex; justify-content:flex-end;
  padding-top:10px;
  border-top:1px dashed #2e3847;      /* an neue Farbe angepasst */
}
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  gap:.4rem; padding:.55rem .9rem; border-radius:10px;
  font-weight:700; text-decoration:none; line-height:1;
  border:1px solid transparent;
  transition: transform .08s ease, background-color .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease;
  user-select:none;
}
.btn:active{ transform: translateY(0); }
.btn-outline-orange{
  color:#F7931A; background:transparent; border-color:#F7931A33;
  box-shadow: 0 2px 10px rgba(247,147,26,.12) inset;
}
.btn-outline-orange:hover{
  background:#241a11;
  border-color:#F7931A;
  box-shadow: 0 4px 16px rgba(247,147,26,.22) inset;
}

/* Empty state – auf #252d38 angleichen */
.empty-state{
  color:#cbd5e1;
  background:#252d38;
  border:1px dashed #2e3847;
  border-radius:10px;
  padding:1.25rem;
  text-align:center;
}

/* Karte in der Grid-Zelle voll strecken */
.gesuche-grid-container .gesuch-card{ height: 100%; }

/* Innerer Flex-Wrapper soll die ganze Kartenhöhe einnehmen */
.gesuch-card__inner{ display:flex; flex-direction:column; flex:1; min-height:0; }

/* Content darf wachsen und Restplatz füllen */
.gesuch-content{ flex:1; min-height:0; }
</style>

<?php
get_sidebar();
get_footer();
