<?php
/* Single Template für CPT "gesuch" */
get_header();

wp_enqueue_script( 'sk-gesuch-copy-link' );
?>

<style>
/* === Wrapper zentriert ================================================= */
.gesuch-single-wrap{
  width:100%;
  max-width:760px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  box-sizing:border-box;
}

/* === Card-Stil (jetzt #252d38) ======================================= */
.gesuch-card-single{
  position:relative;
  border-radius:14px;
  background:#252d38;                 /* NEU: statt Gradient */
  border: 1px solid #2e3847;          /* kühler Rahmen passend zur Liste */
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
  overflow:hidden;
}
/* optional: Akzentkante oben beibehalten */
.gesuch-card-single::before{
  content:"";
  position:absolute; inset:0 0 auto 0; height:3px;
  background: linear-gradient(90deg, rgba(247,147,26,.7), rgba(247,147,26,0) 40%, rgba(247,147,26,0) 60%, rgba(247,147,26,.7));
  opacity:.35;
}

/* Innenabstände */
.gesuch-single-inner{
  padding:18px 18px 16px;
  display:flex; flex-direction:column;
}

/* === Header mit Vendor + Datum ======================================== */
.gesuch-single-header{
  display:flex; flex-direction:column; gap:12px; margin-bottom:10px;
}
.vendor-info{ display:flex; align-items:center; gap:12px; }
.vendor-avatar{
  width:48px; height:48px; border-radius:50%;
  object-fit:cover; flex:0 0 48px;
  border:1px solid #2e3847;          /* NEU: an Card-Rahmen angepasst */
}
.vendor-meta{ display:flex; flex-direction:column; line-height:1.2; }
.vendor-link,.vendor-name{
  color:#e5e7eb; font-weight:700; text-decoration:none;
}
.vendor-link:hover{ color:#F7931A; }
.created-date{ font-size:.9rem; color:#92a0b3; }

/* Titel */
.gesuch-title{
  margin:0;
  font-size: clamp(1.3rem, 1.1rem + .8vw, 1.8rem);
  line-height:1.2;
  color:#fff;
}

/* === Content =========================================================== */
.gesuch-content{
  color:#d6dbe3;
  margin-top:6px;
}
.gesuch-content p:last-child{ margin-bottom:0; }

/* Trennlinie */
.hr-soft{
  margin:14px 0 12px;
  border:0;
  border-top:1px dashed #2e3847;     /* NEU: an Card-Farbe angepasst */
}

/* === Kontaktbereich ==================================================== */
.gesuch-kontakt{
  margin-top:10px;
  padding-top:6px;
}
.gesuch-kontakt h2{
  margin:0 0 12px;
  color:#fff;
  font-size:1.1rem;
}

/* === Footer-Buttons ==================================================== */
.gesuch-footer{
  margin-top:16px;
  display:flex; gap:.6rem; flex-wrap:wrap;
}
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  gap:.4rem; padding:.6rem .95rem;
  border-radius:10px; font-weight:700;
  text-decoration:none; line-height:1;
  border:1px solid transparent; user-select:none;
  transition: transform .08s ease, background-color .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease;
}
.btn:active{ transform: translateY(0); }

/* Bitcoin-Orange Outline */
.btn-outline-orange{
  color:#F7931A; background:transparent; border-color:#F7931A33;
  box-shadow: 0 2px 10px rgba(247,147,26,.12) inset;
}
.btn-outline-orange:hover{
  background:#241a11;
  border-color:#F7931A;
  box-shadow: 0 4px 16px rgba(247,147,26,.22) inset;
}

/* Sekundär (neutral) */
.btn-secondary{
  background:#334155; border-color:#334155; color:#fff;
}
.btn-secondary:hover{ background:#42536a; border-color:#42536a; transform:translateY(-1px); }

/* Kopieren-Feedback */
.copy-ok{
  display:none; margin-left:.25rem; color:#9ae6b4; font-weight:600; font-size:.9rem;
}
.copy-ok.show{ display:inline; }
</style>

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
