<?php
if (!defined('ABSPATH')) { exit; }

/** Render the editor page modules used by the Vitalis demo pages. */
function vitalis_core_render_facility_index() {
    $posts=get_posts(array('post_type'=>'vitalis_facility','post_status'=>'publish','numberposts'=>-1,'orderby'=>'menu_order title','order'=>'ASC'));
    echo '<section class="wrap content-section vitalis-facility-index"><div class="facility-grid facility-listing">';
    if (!$posts) { echo '<p class="event-notice">Tesis bilgileri henüz eklenmemiş. Yönetici, Vitalis Kulüp → Tesis ve galeriler bölümünden tesisleri ekleyebilir.</p>'; }
    foreach ($posts as $post) {
        $slug=get_post_meta($post->ID,'vitalis_page_slug',true);
        if (!$slug) { continue; }
        $image=get_the_post_thumbnail_url($post,'large');
        if (!$image) { $local=get_post_meta($post->ID,'vitalis_local_image',true); $image=$local?vitalis_img($local):vitalis_img('club.jpg'); }
        $summary=$post->post_excerpt?:$post->post_content;
        echo '<a class="facility-card" href="'.esc_url(home_url('/'.$slug.'/')).'"><img src="'.esc_url($image).'" alt="'.esc_attr($post->post_title).'" loading="lazy"><h3>'.esc_html($post->post_title).' <span>→</span></h3><p>'.esc_html(wp_trim_words(wp_strip_all_tags($summary),24)).'</p></a>';
    }
    echo '</div></section>';
}

function vitalis_core_page_module($attrs) {
    $module=isset($attrs['module'])?sanitize_key($attrs['module']):'';
    $slug=isset($attrs['slug'])?sanitize_title($attrs['slug']):'';
    $limit=isset($attrs['limit'])?max(1,min(12,absint($attrs['limit']))):-1;
    ob_start();
    switch ($module) {
        case 'facility-index': vitalis_core_render_facility_index(); break;
        case 'facility-gallery': if ($slug && function_exists('vitalis_core_facility_gallery')) { vitalis_core_facility_gallery($slug); } break;
        case 'hygiene': if ($slug && function_exists('vitalis_core_render_hygiene')) { vitalis_core_render_hygiene($slug); } break;
        case 'trainers': if (function_exists('vitalis_core_render_trainers')) { vitalis_core_render_trainers(); } break;
        case 'events': if (function_exists('vitalis_core_render_events')) { vitalis_core_render_events($limit); } break;
        case 'event-teasers': if (function_exists('vitalis_core_event_teasers')) { echo '<div class="news-grid">'; vitalis_core_event_teasers(); echo '</div>'; } break;
        case 'gallery': if (function_exists('vitalis_core_render_gallery_index')) { vitalis_core_render_gallery_index(); } break;
        case 'booking': if (function_exists('vitalis_core_booking_form')) { vitalis_core_booking_form(); } break;
        case 'contact-form': if (function_exists('vitalis_form')) { vitalis_form(); } break;
        case 'contact-map':
            echo '<aside class="contact-card vitalis-contact-map-card"><h3>Vitalis Sports &amp; Wellness Club</h3><p>Nilüfer, Bursa</p><p><a href="tel:+902241234567">0 (224) 123 45 67</a></p><p><a href="mailto:info@vitalisclub.com">info@vitalisclub.com</a></p><div class="contact-map"><iframe title="Vitalis Sports &amp; Wellness Club — Nilüfer, Bursa haritası" src="https://maps.google.com/maps?q=Nil%C3%BCfer%2C%20Bursa&amp;t=&amp;z=13&amp;ie=UTF8&amp;iwloc=&amp;output=embed" width="600" height="380" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div><small>Harita ve adres demo bilgisi olarak eklenmiştir; gerçek kulüp konumuyla güncelleyin.</small></aside>';
            break;
        case 'member-links': if (function_exists('vitalis_core_member_links')) { vitalis_core_member_links(); } break;
    }
    return ob_get_clean();
}
add_shortcode('vitalis_page_module','vitalis_core_page_module');

/** Normalize smart quotes before WordPress's regular shortcode pass. */
function vitalis_core_normalize_module_quotes($content) {
    if (strpos($content,'[vitalis_page_module')===false) { return $content; }
    return preg_replace_callback('/\[vitalis_page_module\b[^\]]*\]/u',function($match) {
        return html_entity_decode(strtr($match[0],array('“'=>'"','”'=>'"','‘'=>"'",'’'=>"'")),ENT_QUOTES|ENT_HTML5,'UTF-8');
    },$content);
}
add_filter('the_content','vitalis_core_normalize_module_quotes',10);

/**
 * Repair module markers that were pasted into Gutenberg as plain text, including
 * versions containing typographic quotes. WordPress's normal shortcode pass
 * runs first; this only replaces markers that remain in the rendered content.
 */
function vitalis_core_repair_plain_page_modules($content) {
    if (strpos($content,'[vitalis_page_module')===false) { return $content; }
    return preg_replace_callback('/\[vitalis_page_module\b([^\]]*)\]/u',function($match) {
        $attributes=array();
        $pattern='/([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|“([^”]*)”|‘([^’]*)’|([^\s\]]+))/u';
        if (!preg_match_all($pattern,$match[1],$found,PREG_SET_ORDER)) { return $match[0]; }
        foreach ($found as $attribute) {
            $value='';
            for ($i=2;$i<=6;$i++) { if (isset($attribute[$i]) && $attribute[$i]!=='') { $value=$attribute[$i]; break; } }
            $value=html_entity_decode(trim($value," \t\n\r\0\x0B\"'“”‘’"),ENT_QUOTES|ENT_HTML5,'UTF-8');
            $attributes[strtolower($attribute[1])]=$value;
        }
        if (empty($attributes['module'])) { return $match[0]; }
        $rendered=vitalis_core_page_module($attributes);
        return $rendered!=='' ? $rendered : $match[0];
    },$content);
}
add_filter('the_content','vitalis_core_repair_plain_page_modules',12);

/** Give the reservation page's editor-written guidance clear visual sections. */
function vitalis_core_format_reservation_guidance($content) {
    if (!is_page('rezervasyon') || strpos($content,'vitalis-reservation-info')!==false) { return $content; }

    $pattern='/(<h2\b[^>]*>.*?<\/h2>)(.*?)(?=<h2\b|<section\b[^>]*class=["\'][^"\']*\bbooking-system\b|$)/isu';
    $content=preg_replace_callback($pattern,function($match) {
        $heading=trim(wp_strip_all_tags($match[1]));
        if ($heading==='Takvim ve rezervasyon süreci') {
            $class='vitalis-reservation-info vitalis-reservation-info--steps';
        } elseif ($heading==='Önemli bilgi') {
            $class='vitalis-reservation-info vitalis-reservation-info--notice';
        } else {
            return $match[0];
        }

        $body=preg_replace('/<div\b(?=[^>]*class=["\'][^"\']*\bwp-block-spacer\b)[^>]*>.*?<\/div>/isu','',$match[2]);
        return '<section class="'.$class.'">'.$match[1].'<div class="vitalis-reservation-info__body">'.$body.'</div></section>';
    },$content);

    return $content;
}
add_filter('the_content','vitalis_core_format_reservation_guidance',20);
