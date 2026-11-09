<?php
if (!defined('ABSPATH')) exit;
function vitalis_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('custom-logo');
  add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script'));
  register_nav_menus(array('primary' => __('Ana Menü', 'vitalis')));
}
add_action('after_setup_theme','vitalis_setup');
function vitalis_assets() {
  wp_enqueue_style('vitalis-style', get_stylesheet_uri(), array(), '1.0.0');
  wp_enqueue_script('vitalis-script', get_template_directory_uri().'/assets/js/theme.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts','vitalis_assets');
function vitalis_customize_register($wp_customize) {
  $wp_customize->add_section('vitalis_brand', array('title'=>__('Vitalis Ana Sayfa Ayarları','vitalis'),'priority'=>30));
  $fields = array(
    'vitalis_phone' => array('Telefon','0 (224) 123 45 67'),
    'vitalis_email' => array('E-posta','info@vitalisclub.com'),
    'vitalis_address' => array('Adres','Nilüfer, Bursa'),
    'vitalis_hero_title' => array('Ana başlık','DAHA İYİ BİR SEN'),
    'vitalis_hero_text' => array('Ana açıklama','Modern tesislerimiz ve uzman kadromuzla sağlıklı, güçlü ve dengeli bir yaşama adım atın.')
  );
  foreach ($fields as $id=>$field) {
    $wp_customize->add_setting($id, array('default'=>$field[1], 'sanitize_callback'=>'sanitize_text_field'));
    $wp_customize->add_control($id, array('label'=>$field[0], 'section'=>'vitalis_brand', 'type'=>'text'));
  }
}
add_action('customize_register','vitalis_customize_register');
function vitalis_page_setup() {
  $pages = array(
    'tesislerimiz'=>'Tesislerimiz',
    'pt-egitimleri'=>'PT Eğitimleri',
    'antrenorler'=>'Antrenörler',
    'salon-kiralama'=>'Salon Kiralama',
    'uyelik'=>'Üyelik',
    'duyurular'=>'Duyurular ve Etkinlikler',
    'randevu'=>'Randevu ve Rezervasyon',
  );
  foreach ($pages as $slug=>$title) {
    if (!get_page_by_path($slug)) {
      wp_insert_post(array('post_title'=>$title,'post_name'=>$slug,'post_status'=>'publish','post_type'=>'page','post_content'=>vitalis_default_page_content($slug)));
    }
  }
}
function vitalis_default_page_content($slug) {
  $content = array(
    'tesislerimiz'=>'<p>Fitness salonu, yüzme havuzu, 2 basketbol sahası ve 1 voleybol sahası, grup dersleri, soyunma odaları, duşlar, otopark ve kafe olanaklarımızı keşfedin.</p>',
    'pt-egitimleri'=>'<p>Bireysel ve grup antrenmanları, PT paketleri, seans ücretleri, eğitim kapsamı, içerikleri ve günleri için bizimle iletişime geçin.</p>',
    'antrenorler'=>'<p>Antrenör profilleri; uzmanlık alanları, özgeçmiş, sertifika ve belgeler ile uygun antrenman takvimi bilgileri bu bölümde yer alır.</p>',
    'salon-kiralama'=>'<p>2 basketbol sahası ve 1 voleybol sahası için salon bilgileri, fotoğraflar ve kiralama talepleri. Talep üzerine hakem ayarlama hizmeti de sunulabilir.</p>',
    'uyelik'=>'<p>Aylık, 6 aylık ve yıllık üyelik seçenekleri için başvuru talebinizi iletin. Üyelik detayları ve ücretleri yönetim tarafından güncellenmelidir.</p>',
    'duyurular'=>'<p>Salon duyuruları, etkinlikler, grup dersleri ve kampanyalar hakkında güncel bilgiler.</p>',
    'randevu'=>'<p>PT antrenörü seçimi, çevrim içi randevu ve salon rezervasyon talebi için formu doldurun. Bu form talep e-postası oluşturur; canlı takvim ve ödeme için ayrıca rezervasyon eklentisi gerekir.</p>[vitalis_booking_form]'
  );
  return isset($content[$slug]) ? $content[$slug] : '';
}
add_action('after_switch_theme','vitalis_page_setup');
function vitalis_booking_shortcode() {
  ob_start(); ?>
  <form class="form-card" method="post">
    <div class="form-grid">
      <div><label>Ad Soyad</label><input required name="v_name" autocomplete="name"></div>
      <div><label>E-posta</label><input required type="email" name="v_email" autocomplete="email"></div>
      <div><label>Talep Türü</label><select name="v_type"><option>PT bireysel antrenman</option><option>PT grup antrenmanı</option><option>Basketbol sahası kiralama</option><option>Voleybol sahası kiralama</option><option>Üyelik başvurusu</option></select></div>
      <div><label>Tercih Edilen Tarih</label><input type="date" name="v_date"></div>
    </div>
    <label>Mesaj / Saat Tercihi</label><textarea name="v_message" rows="4"></textarea>
    <p><button class="btn" type="submit" name="vitalis_booking_submit" value="1">Talep Gönder →</button></p>
    <small>Demo formudur. Canlı e-posta gönderimi ve müsaitlik kontrolü için WordPress form/rezervasyon eklentisi kurulmalıdır.</small>
  </form>
  <?php
  return ob_get_clean();
}
add_shortcode('vitalis_booking_form','vitalis_booking_shortcode');
function vitalis_handle_booking() {
  if (isset($_POST['vitalis_booking_submit'])) {
    $name = sanitize_text_field(wp_unslash($_POST['v_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['v_email'] ?? ''));
    $type = sanitize_text_field(wp_unslash($_POST['v_type'] ?? ''));
    $date = sanitize_text_field(wp_unslash($_POST['v_date'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['v_message'] ?? ''));
    if ($name && is_email($email)) {
      $to = get_theme_mod('vitalis_email','info@vitalisclub.com');
      wp_mail($to, 'Vitalis web sitesi talebi', "Ad Soyad: $name\nE-posta: $email\nTalep: $type\nTarih: $date\nMesaj: $message");
      wp_safe_redirect(add_query_arg('talep','gonderildi',wp_get_referer() ?: home_url('/randevu/')));
      exit;
    }
  }
}
add_action('init','vitalis_handle_booking');
