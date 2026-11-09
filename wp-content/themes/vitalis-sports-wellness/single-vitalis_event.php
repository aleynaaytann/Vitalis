<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
    $event_id=get_the_ID();
    $date=(string)get_post_meta($event_id,'vitalis_event_date',true);
    $time=(string)get_post_meta($event_id,'vitalis_event_time',true);
    $venue=(string)get_post_meta($event_id,'vitalis_event_venue',true);
    $fee=(string)get_post_meta($event_id,'vitalis_event_fee',true);
    $capacity_label=(string)get_post_meta($event_id,'vitalis_event_capacity',true);
    $availability=vitalis_core_event_capacity_snapshot($event_id);
    $image=get_the_post_thumbnail_url($event_id,'full');
    if (!$image) { $local=get_post_meta($event_id,'vitalis_local_image',true); $image=$local?vitalis_img($local):vitalis_img('event-fitness.jpg'); }
    ?>
    <article class="event-single-page">
        <header class="event-single-hero" style="--event-image:url('<?php echo esc_url($image); ?>')">
            <div class="wrap event-single-hero-copy">
                <a class="event-back-link" href="<?php echo esc_url(home_url('/etkinlikler/')); ?>">← Tüm etkinlikler</a>
                <span class="eyebrow">VITALIS SPORTS & WELLNESS CLUB</span>
                <h1><?php the_title(); ?></h1>
                <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()?:get_the_content()),32)); ?></p>
            </div>
        </header>
        <main class="wrap event-single-content">
            <div class="event-single-facts">
                <div><span>TARİH</span><strong><?php echo esc_html($date?wp_date('l, j F Y',strtotime($date)):'Tarih duyurulacak'); ?></strong></div>
                <div><span>SAAT</span><strong><?php echo esc_html($time?:'Saat duyurulacak'); ?></strong></div>
                <div><span>YER</span><strong><?php echo esc_html($venue?:'Mekân bilgisi duyurulacak'); ?></strong></div>
                <div><span>KATILIM</span><strong><?php echo esc_html($fee?:'Ücret bilgisi için kulüple görüşün'); ?></strong></div>
            </div>
            <div class="event-detail-layout">
                <section class="event-detail-copy">
                    <span class="eyebrow">ETKİNLİK BİLGİSİ</span>
                    <h2>Program ve katılım ayrıntıları</h2>
                    <div class="event-long-description"><?php the_content(); ?></div>
                    <div class="event-capacity-panel">
                        <div><span>MAKSİMUM KONTENJAN</span><strong><?php echo number_format_i18n($availability['capacity']); ?> kişi</strong></div>
                        <div><span>ŞU ANDA KALAN</span><strong><?php echo number_format_i18n($availability['available']); ?> kişi</strong></div>
                        <?php if ($capacity_label) : ?><small>Etkinlik formatı: <?php echo esc_html($capacity_label); ?> · kişi sayısı başvuru formunda seçilir.</small><?php endif; ?>
                    </div>
                    <p class="event-signup-explainer">Katılımcı sayısını başvuruda seçin. Bekleyen ve onaylanan katılımcı sayısı kontenjanda yer tutar; reddedilen veya iptal edilen başvurularda yerler yeniden açılır.</p>
                </section>
                <aside class="event-signup-sidebar">
                    <?php vitalis_core_event_registration_form($event_id); ?>
                </aside>
            </div>
        </main>
    </article>
    <?php
endwhile;
get_footer();
