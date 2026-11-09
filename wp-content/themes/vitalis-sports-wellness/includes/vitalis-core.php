<?php
if (!defined('ABSPATH')) { exit; }

define('VITALIS_CORE_VERSION', '1.5.0');

function vitalis_core_register_coach_role() {
    if (!get_role('vitalis_coach')) {
        add_role('vitalis_coach','Vitalis Antrenörü',array('read'=>true));
    }
}
add_action('init','vitalis_core_register_coach_role',1);

function vitalis_core_table() {
    global $wpdb;
    return $wpdb->prefix . 'vitalis_bookings';
}

function vitalis_core_trainer_appointments_table() {
    global $wpdb;
    return $wpdb->prefix . 'vitalis_trainer_appointments';
}

function vitalis_core_event_signups_table() {
    global $wpdb;
    return $wpdb->prefix . 'vitalis_event_signups';
}

function vitalis_core_event_capacity_table() {
    global $wpdb;
    return $wpdb->prefix . 'vitalis_event_capacity';
}

function vitalis_core_request_value($source,$key,$default='') {
    if (!isset($source[$key]) || !is_scalar($source[$key])) { return $default; }
    return wp_unslash($source[$key]);
}

function vitalis_core_default_settings() {
    return array(
        'resources' => "Yüzme Havuzu – Kulvar 1\nYüzme Havuzu – Kulvar 2\nTenis Sahası 1\nGrup Stüdyosu",
        'hours' => array(
            1 => array('open'=>'06:00','close'=>'22:00'), 2 => array('open'=>'06:00','close'=>'22:00'),
            3 => array('open'=>'06:00','close'=>'22:00'), 4 => array('open'=>'06:00','close'=>'22:00'),
            5 => array('open'=>'06:00','close'=>'22:00'), 6 => array('open'=>'07:00','close'=>'22:00'),
            7 => array('open'=>'08:00','close'=>'20:00')
        )
    );
}

function vitalis_core_settings() {
    $stored = get_option('vitalis_core_settings', array());
    if (!is_array($stored)) { return vitalis_core_default_settings(); }
    return vitalis_core_sanitize_settings($stored);
}

function vitalis_core_resources() {
    $settings = vitalis_core_settings();
    $rows = preg_split('/\r\n|\r|\n/', (string) $settings['resources']);
    $items = array();
    foreach ($rows as $label) {
        $label = sanitize_text_field(trim($label));
        $key = sanitize_title($label);
        if ($label && $key && !isset($items[$key])) { $items[$key] = $label; }
    }
    return $items;
}

function vitalis_core_is_allowed_resource($label) {
    return !preg_match('/basketbol|voleybol|pilates\s*\/\s*yoga\s+stüdyosu|fonksiyonel\s+antrenman\s+alanı|kişisel\s+antrenman\s+stüdyosu|çok\s+amaçlı\s+salon/iu',(string)$label);
}

function vitalis_core_register_types() {
    $types = array(
        'vitalis_trainer' => array('Antrenörler','Antrenör','Antrenör ekle'),
        'vitalis_event' => array('Etkinlikler','Etkinlik','Etkinlik ekle'),
        'vitalis_facility' => array('Tesis ve galeriler','Tesis alanı','Tesis alanı ekle')
    );
    foreach ($types as $type => $labels) {
        $is_public_trainer = $type === 'vitalis_trainer';
        $is_public_event = $type === 'vitalis_event';
        register_post_type($type, array(
            'labels' => array('name'=>$labels[0], 'singular_name'=>$labels[1], 'add_new_item'=>$labels[2], 'edit_item'=>$labels[1].' düzenle'),
            'public'=>$is_public_trainer || $is_public_event, 'publicly_queryable'=>$is_public_trainer || $is_public_event, 'exclude_from_search'=>$is_public_trainer,
            'has_archive'=>false, 'rewrite'=>$is_public_trainer?array('slug'=>'antrenor','with_front'=>false):($is_public_event?array('slug'=>'etkinlik','with_front'=>false):false),
            'show_ui'=>true, 'show_in_menu'=>'vitalis-club', 'show_in_rest'=>true,
            'supports'=>array('title','editor','thumbnail','excerpt'), 'capability_type'=>'post', 'map_meta_cap'=>true
        ));
    }
}
add_action('init', 'vitalis_core_register_types', 5);

function vitalis_core_create_table() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = vitalis_core_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        resource varchar(120) NOT NULL,
        booking_date date NOT NULL,
        booking_time time NOT NULL,
        full_name varchar(160) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(50) NOT NULL,
        note text NULL,
        user_id bigint(20) unsigned NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY resource_slot (resource,booking_date,booking_time),
        KEY booking_date (booking_date),
        KEY status (status),
        KEY user_id (user_id)
    ) $charset;";
    dbDelta($sql);
    $trainer_table = vitalis_core_trainer_appointments_table();
    $trainer_sql = "CREATE TABLE $trainer_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        trainer_id bigint(20) unsigned NOT NULL,
        session_type varchar(20) NOT NULL,
        appointment_date date NOT NULL,
        appointment_time time NOT NULL,
        full_name varchar(160) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(50) NOT NULL,
        group_size smallint(5) unsigned NOT NULL DEFAULT 1,
        note text NULL,
        user_id bigint(20) unsigned NULL,
        is_demo tinyint(1) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY trainer_slot (trainer_id,appointment_date,appointment_time),
        KEY appointment_date (appointment_date),
        KEY status (status),
        KEY trainer_id (trainer_id)
    ) $charset;";
    dbDelta($trainer_sql);
    $event_signups_table=vitalis_core_event_signups_table();
    $event_signups_sql="CREATE TABLE $event_signups_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        full_name varchar(160) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(50) NOT NULL,
        attendee_count mediumint(8) unsigned NOT NULL DEFAULT 1,
        note text NULL,
        user_id bigint(20) unsigned NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY event_status (event_id,status),
        KEY event_id (event_id),
        KEY status (status)
    ) $charset;";
    dbDelta($event_signups_sql);
    $event_capacity_table=vitalis_core_event_capacity_table();
    $event_capacity_sql="CREATE TABLE $event_capacity_table (
        event_id bigint(20) unsigned NOT NULL,
        max_capacity mediumint(8) unsigned NOT NULL DEFAULT 1,
        reserved_count mediumint(8) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (event_id)
    ) ENGINE=InnoDB $charset;";
    dbDelta($event_capacity_sql);
    update_option('vitalis_core_schema', VITALIS_CORE_VERSION);
}

function vitalis_core_page($slug, $title, $content = '') {
    $page = get_page_by_path($slug);
    if ($page) { return $page->ID; }
    return wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content));
}

function vitalis_core_demo_trainer_profiles() {
    return array(
        'mert-aydin-kuvvet-kondisyon'=>array(
            'education'=>'DEMO eğitim kaydı: Egzersiz ve Spor Bilimleri lisans eğitimi örneği. Odak alanları: egzersiz fizyolojisi, kuvvet antrenmanı programlama ve temel hareket değerlendirmesi. Üniversite ve mezuniyet bilgisi gerçek antrenörle doğrulanmalıdır.',
            'certificates'=>"Kuvvet antrenmanı programlama - demo belge | assets/certificates/vitalis-demo-mert.pdf",
            'individual_detail'=>'55 dakikalık birebir seans; kısa hedef görüşmesi, kişiye göre ısınma, temel kuvvet hareketlerinde teknik çalışma ve seans sonu geri bildirim adımlarından oluşur. İlk seans, mevcut antrenman deneyiminizi ve hareket alışkanlıklarınızı anlamaya ayrılır.',
            'individual_fees'=>'Tek seans ₺1.200 • 8 seans ₺8.800',
            'group_detail'=>'Kuvvet Temelleri grup programı 50 dakika sürer ve demo kontenjanı 4–8 kişidir. Hareket kalıpları, ekipmanı güvenle kullanma ve kademeli yüklenme üzerine ilerler. Eğitmen hareket seçeneklerini grubun deneyimine göre gösterir.',
            'group_fees'=>'Tek ders ₺550 / kişi • 4 ders ₺1.900 / kişi',
            'group_capacity'=>8,
            'availability'=>"1|07:00|21:00\n2|07:00|21:00\n3|07:00|21:00\n4|07:00|21:00\n5|07:00|21:00\n6|09:00|17:00"
        ),
        'selin-kaya-pilates-hareket'=>array(
            'education'=>'DEMO eğitim kaydı: Pilates temel mat çalışmaları ve hareket eğitimi modülleri örneği. Odak alanları: nefes, merkez bölge kontrolü, mobilite ve güvenli hareket seçenekleri. Eğitim kurumu ve mezuniyet/katılım bilgisi gerçek belgelerle doğrulanmalıdır.',
            'certificates'=>"Mat Pilates ve hareket kontrolü - demo belge | assets/certificates/vitalis-demo-selin.pdf",
            'individual_detail'=>'50 dakikalık birebir seans; nefes ve duruş farkındalığı, merkez bölge kontrolü, kişinin hareket seviyesine uygun mat veya reformer çalışması ve seans sonu ev pratiği önerisini kapsar. Ekipman tercihi ve ders hedefi ilk görüşmede netleştirilir.',
            'individual_fees'=>'Tek seans ₺1.250 • 8 seans ₺9.000',
            'group_detail'=>'Mat Pilates ve Mobilite grup dersi 50 dakika sürer; demo kontenjanı 6–10 kişidir. Nefes, temel mat serileri ve eklem hareketliliği üzerinde çalışılır. Her hareket için daha kolay ve daha ileri seçenekler sunulur.',
            'group_fees'=>'Tek ders ₺450 / kişi • 4 ders ₺1.600 / kişi',
            'group_capacity'=>10,
            'availability'=>"1|08:00|20:00\n2|08:00|20:00\n3|08:00|20:00\n4|08:00|20:00\n5|08:00|19:00\n6|09:00|15:00"
        ),
        'emre-yilmaz-yuzme-egitmeni'=>array(
            'education'=>'DEMO eğitim kaydı: Yüzme öğretimi ve temel su güvenliği modülleri örneği. Odak alanları: suya alışma, nefes ritmi, temel teknik ve seviye grubu planlama. Eğitmenlik, kurum ve eğitim bilgileri gerçek belgelerle doğrulanmalıdır.',
            'certificates'=>"Yüzme tekniği ve su güvenliği - demo belge | assets/certificates/vitalis-demo-emre.pdf",
            'individual_detail'=>'45 dakikalık birebir havuz seansı; suya alışma veya mevcut seviyeye uygun teknik hedef belirleme, eğitmen eşliğinde beceri çalışması ve seans sonunda kısa ilerleme değerlendirmesi içerir. Yaş, deneyim ve uygun havuz saatleri ön görüşmede netleştirilir.',
            'individual_fees'=>'Tek seans ₺1.100 • 4 seans ₺4.000',
            'group_detail'=>'Başlangıç Seviyesi Yüzme grubu 45 dakika sürer ve demo kontenjanı en fazla 6 kişidir. Suya alışma, nefes verme ve temel kulaç koordinasyonu kademeli biçimde çalışılır. Grup oluşturulmadan önce katılımcı seviyesi teyit edilir.',
            'group_fees'=>'Tek ders ₺700 / kişi • 4 ders ₺2.800 / kişi',
            'group_capacity'=>6,
            'availability'=>"1|09:00|18:00\n2|09:00|18:00\n3|09:00|18:00\n4|09:00|18:00\n5|09:00|18:00\n6|08:00|16:00"
        )
    );
}

function vitalis_core_seed_trainer_demo_meta($post_id) {
    $slug=get_post_field('post_name',$post_id);
    $profiles=vitalis_core_demo_trainer_profiles();
    if (!isset($profiles[$slug])) { return; }
    foreach ($profiles[$slug] as $key=>$value) {
        $meta_key='vitalis_'.$key;
        if (get_post_meta($post_id,$meta_key,true)==='') { update_post_meta($post_id,$meta_key,$value); }
    }
    if (get_post_meta($post_id,'vitalis_demo_profile',true)==='') { update_post_meta($post_id,'vitalis_demo_profile',1); }
}

function vitalis_core_migrate_demo_trainer_profiles() {
    if (get_option('vitalis_trainer_demo_details_seeded_v1')) { return; }
    foreach (array_keys(vitalis_core_demo_trainer_profiles()) as $slug) {
        $posts=get_posts(array('post_type'=>'vitalis_trainer','post_status'=>'any','name'=>$slug,'numberposts'=>1));
        if ($posts) { vitalis_core_seed_trainer_demo_meta($posts[0]->ID); }
    }
    update_option('vitalis_trainer_demo_details_seeded_v1',1,false);
}

function vitalis_core_seed() {
    if (get_option('vitalis_core_seeded')) { return; }
    $facilities = array(
        array('Kulüp binamız','hakkimizda','club.jpg',array('detail-club-1.jpg','detail-club-2.jpg'),'Modern kulüp binamız; antrenman, yüzme ve iyi yaşam alanlarını tek çatı altında buluşturan ferah bir spor merkezidir.'),
        array('Fitness salonu','fitness-salonu','gym.jpg',array('detail-fitness-1.jpg','detail-fitness-2.jpg'),'Kardiyo, kuvvet ve serbest ağırlık alanlarında her seviyeye uygun antrenman olanakları.'),
        array('Yüzme havuzu','yuzme-havuzu','pool.jpg',array('detail-pool-1.jpg','detail-pool-2.jpg'),'Kapalı havuz, yüzme çalışmaları ve serbest yüzme için düzenlenmiş kulvarlar.'),
        array('Spor sahaları','spor-sahalari','court.jpg',array('detail-courts-1.jpg','detail-courts-2.jpg'),'Kapalı tenis sahamız; bireysel antrenmanlar ve kulüp etkinlikleri için düzenlenmiş, rezervasyon talebiyle kullanılabilen bir alandır.'),
        array('Grup dersleri','grup-dersleri','studio.jpg',array('detail-group-1.jpg','detail-group-2.jpg'),'Pilates, yoga, mobilite ve grup kondisyon dersleri için aydınlık stüdyo.'),
        array('Wellness alanı','wellness','yoga.jpg',array('detail-wellness-1.jpg','detail-wellness-2.jpg'),'Hareket sonrası dinlenme, esneme ve yenilenme için sakin wellness alanı.'),
        array('Soyunma odaları','soyunma-odalari','locker-room-1.jpg',array('locker-room-2.jpg','locker-room-3.jpg','locker-room-4.jpg'),'Geniş dolaplar, oturma alanları, duşlar ve lavabo bölümlerini gösteren tanıtım galerisi. Görseller konsepttir; gerçek tesis fotoğrafları yönetim panelinden yüklenebilir.')
    );
    foreach ($facilities as $item) {
        $existing = get_posts(array('post_type'=>'vitalis_facility','name'=>sanitize_title($item[0]),'posts_per_page'=>1,'post_status'=>'any'));
        if (!$existing) {
            $id = wp_insert_post(array('post_type'=>'vitalis_facility','post_status'=>'publish','post_title'=>$item[0],'post_name'=>sanitize_title($item[0]),'post_content'=>$item[4],'post_excerpt'=>$item[4]));
            if ($id && !is_wp_error($id)) {
                update_post_meta($id,'vitalis_page_slug',$item[1]);
                update_post_meta($id,'vitalis_local_image',$item[2]);
                update_post_meta($id,'vitalis_local_gallery',$item[3]);
                $hygiene = array(
                    'fitness-salonu'=>'Örnek rutin: açılış öncesi genel alan turu, gün içinde yüksek temas yüzeylerinin kontrolü ve gün sonunda ekipman düzeni. Bu örnek metni gerçek kulüp uygulaması ve güncel kontrol kaydıyla değiştirin.',
                    'yuzme-havuzu'=>'Örnek rutin: havuz suyu kontrolleri ve ortak kullanım yüzeylerinin takibi işletme prosedürüne göre yayımlanmalıdır. Bu örnek metni gerçek ölçüm ve kontrol kaydıyla değiştirin.',
                    'soyunma-odalari'=>'Örnek rutin: duş, lavabo, dolap, oturma alanı ve zeminler için açılış ve gün içi kontrol turları. Bu örnek metni gerçek kulüp uygulaması ve güncel kontrol kaydıyla değiştirin.'
                );
                if (isset($hygiene[$item[1]])) { update_post_meta($id,'vitalis_hygiene_text',$hygiene[$item[1]]); }
            }
        }
    }
    $trainers = array(
        array('Mert Aydın — Kuvvet & Kondisyon','Kuvvet temeli, güvenli ekipman kullanımı ve hedef odaklı salon planı. İlk görüşmede hedefler ve antrenman deneyimi değerlendirilir.','Kuvvet • kondisyon • başlangıç seviyesi','55 dk birebir / salon','Tek seans ₺1.200 • 8 seans ₺8.800','coach-strength.jpg'),
        array('Selin Kaya — Pilates & Hareket','Mat ve reformer çalışmalarında merkez bölge kontrolü, mobilite ve dengeli hareket alışkanlığı. Ders deneyim seviyesine göre uyarlanır.','Mobilite • Pilates • küçük grup','50 dk birebir veya küçük grup','Birebir ₺1.250 • 8 ders küçük grup ₺4.400','coach-pilates.jpg'),
        array('Emre Yılmaz — Yüzme Eğitmeni','Suya alışmadan teknik gelişime kadar seviye odaklı seanslar. Grup ve birebir formatlar için yaş ve seviye ön görüşmede netleştirilir.','Çocuk ve yetişkin • teknik gelişim','45 dk birebir / seviye grubu','Tek seans ₺1.100 • 4 ders grup ₺2.800','coach-swim.jpg')
    );
    foreach ($trainers as $item) {
        $exists = get_posts(array('post_type'=>'vitalis_trainer','name'=>sanitize_title($item[0]),'posts_per_page'=>1,'post_status'=>'any'));
        if (!$exists) {
            $id = wp_insert_post(array('post_type'=>'vitalis_trainer','post_status'=>'publish','post_title'=>$item[0],'post_content'=>$item[1],'post_excerpt'=>$item[1]));
            if ($id && !is_wp_error($id)) { update_post_meta($id,'vitalis_specialties',$item[2]); update_post_meta($id,'vitalis_session',$item[3]); update_post_meta($id,'vitalis_fees',$item[4]); update_post_meta($id,'vitalis_local_image',$item[5]); vitalis_core_seed_trainer_demo_meta($id); }
        }
    }
    $event_date = function($offset) { $date = new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone()); return $date->modify('+'.absint($offset).' days')->format('Y-m-d'); };
    $events = array(
        array('Vitalis ile Tanışma Günü',$event_date(3),'10:00–13:00','Kulüp ana girişi ve stüdyo alanı','Ücretsiz • kayıt talebi gerekir','40 kişi','Tesis turu, antrenörlerle kısa hedef görüşmesi ve tanışma dersleri. İlk kez gelecek yetişkinler ve aileler için açık kulüp günü.','studio.jpg',40),
        array('Grup Dersleri Deneme Buluşması',$event_date(10),'10:00–12:00','Grup stüdyosu • Vitalis Nilüfer','Üye ücretsiz • misafir ₺350','24 kişi','Pilates, mobilite ve düşük tempolu kondisyon derslerinden oluşan başlangıç seviyesi tanışma akışı.','event-fitness.jpg',24),
        array('Nefes, Esneme ve Denge Atölyesi',$event_date(17),'11:00–13:30','Wellness stüdyosu','Üye ₺450 • misafir ₺650','18 kişi','Nefes farkındalığı, esneme ve denge çalışması. Yeni başlayanlara uygundur; matlar kulüpte sağlanır.','yoga.jpg',18),
        array('Vitalis Tenis Çiftler Buluşması',$event_date(23),'09:00–13:00','Açık tenis sahası','Oyuncu başı ₺450 • su ikramı dahil','16 çift','Dostça formatta grup aşaması ve final karşılaşması. Eşleşmeler katılım seviyesine göre yapılır.','event-tennis.jpg',32),
        array('Aile Yüzme ve Suya Alışma Saati',$event_date(30),'14:00–16:00','Kapalı havuz • Vitalis Nilüfer','Katılımcı başı ₺400','12 aile','Ebeveyn eşliğinde suya alışma ve güvenli havuz rutini tanıtımı. Havuz kuralları kayıt öncesi paylaşılır.','event-pool.jpg',24)
    );
    foreach ($events as $item) {
        $exists = get_posts(array('post_type'=>'vitalis_event','name'=>sanitize_title($item[0]),'posts_per_page'=>1,'post_status'=>'any'));
        if ($exists) { continue; }
        $id = wp_insert_post(array('post_type'=>'vitalis_event','post_status'=>'publish','post_title'=>$item[0],'post_content'=>$item[6],'post_excerpt'=>$item[6]));
        if ($id && !is_wp_error($id)) {
            update_post_meta($id,'vitalis_event_date',$item[1]); update_post_meta($id,'vitalis_event_time',$item[2]);
            update_post_meta($id,'vitalis_event_venue',$item[3]); update_post_meta($id,'vitalis_event_fee',$item[4]);
            update_post_meta($id,'vitalis_event_capacity',$item[5]); update_post_meta($id,'vitalis_event_max_capacity',$item[8]); update_post_meta($id,'vitalis_local_image',$item[7]);
            if (function_exists('vitalis_core_event_sync_capacity')) { vitalis_core_event_sync_capacity($id); }
        }
    }
    vitalis_core_page('uye-paneli','Üye Paneli','[vitalis_member_dashboard]');
    vitalis_core_page('uye-kaydi','Üye Kaydı','[vitalis_member_register]');
    vitalis_core_page('uye-girisi','Üye Girişi','[vitalis_member_login]');
    vitalis_core_page('soyunma-odalari','Soyunma Odaları ve Hijyen','');
    vitalis_core_page('rezervasyon','Rezervasyon ve Kiralama','');
    update_option('vitalis_core_seeded',1);
}

function vitalis_core_install() {
    vitalis_core_create_table();
    vitalis_core_seed();
    flush_rewrite_rules();
}
add_action('after_switch_theme','vitalis_core_install');
add_action('init',function () {
    if (get_option('vitalis_core_schema') !== VITALIS_CORE_VERSION) { vitalis_core_create_table(); }
    if (!get_option('vitalis_core_seeded')) { vitalis_core_seed(); }
    vitalis_core_migrate_demo_trainer_profiles();
    if (!get_option('vitalis_trainer_rewrite_v1')) { flush_rewrite_rules(false); update_option('vitalis_trainer_rewrite_v1',1,false); }
    if (!get_option('vitalis_event_rewrite_v1')) { flush_rewrite_rules(false); update_option('vitalis_event_rewrite_v1',1,false); }
    if (function_exists('vitalis_core_migrate_event_capacities')) { vitalis_core_migrate_event_capacities(); }
    vitalis_core_page('antrenor-paneli','Antrenör Paneli','[vitalis_coach_dashboard]');
    vitalis_core_seed_demo_accounts();
    if (function_exists('vitalis_core_seed_demo_event_signup')) { vitalis_core_seed_demo_event_signup(); }
    vitalis_core_seed_demo_bookings();
    if (function_exists('vitalis_trainer_seed_demo_appointments')) { vitalis_trainer_seed_demo_appointments(); }
    if (function_exists('vitalis_trainer_sync_demo_coach_profile')) { vitalis_trainer_sync_demo_coach_profile(); }
},20);

function vitalis_core_get_or_create_demo_user($kind,$role,$display_name) {
    $matches = get_users(array('meta_key'=>'vitalis_demo_account','meta_value'=>$kind,'number'=>1));
    $password = wp_generate_password(20,true,true);
    if ($matches) {
        $user = $matches[0];
        wp_set_password($password,$user->ID);
        return array('user'=>$user,'password'=>$password);
    }
    $base = 'vitalis_demo_'.$kind;
    $login = $base;
    for ($suffix=2; username_exists($login); $suffix++) { $login=$base.'_'.$suffix; }
    $email = 'vitalis.demo.'.$kind.'.'.wp_generate_password(12,false,false).'@example.com';
    while (email_exists($email)) { $email='vitalis.demo.'.$kind.'.'.wp_generate_password(12,false,false).'@example.com'; }
    $notification_priority = has_action('user_register','wp_send_new_user_notifications');
    if ($notification_priority !== false) { remove_action('user_register','wp_send_new_user_notifications',$notification_priority); }
    $user_id = wp_insert_user(array('user_login'=>$login,'user_pass'=>$password,'user_email'=>$email,'display_name'=>$display_name,'first_name'=>$display_name,'role'=>$role));
    if ($notification_priority !== false) { add_action('user_register','wp_send_new_user_notifications',$notification_priority,1); }
    if (is_wp_error($user_id)) { return false; }
    update_user_meta($user_id,'vitalis_demo_account',$kind);
    return array('user'=>get_userdata($user_id),'password'=>$password);
}

function vitalis_core_seed_demo_accounts() {
    if (get_option('vitalis_demo_seeded')) { return; }
    $coach_data = vitalis_core_get_or_create_demo_user('coach','vitalis_coach','DEMO Antrenör — Mert Aydın');
    $student_data = vitalis_core_get_or_create_demo_user('student','subscriber','DEMO Üye — Deniz Yılmaz');
    if (!$coach_data || !$student_data || !$coach_data['user'] || !$student_data['user']) { return; }
    $coach_id = (int)$coach_data['user']->ID;
    $student_id = (int)$student_data['user']->ID;
    $demo_date = function($days_ago) {
        $today = new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone());
        return $today->modify('-'.absint($days_ago).' days')->format('Y-m-d');
    };
    update_user_meta($student_id,'vitalis_assigned_coach_id',$coach_id);
    update_user_meta($student_id,'vitalis_member_status','Aktif üye');
    update_user_meta($student_id,'vitalis_member_plan','6 Aylık Üyelik (Demo)');
    update_user_meta($student_id,'vitalis_member_requested_plan','6 Aylık Üyelik');
    update_user_meta($student_id,'vitalis_current_goal','Kuvvetini ve genel kondisyonunu artırmak');
    update_user_meta($student_id,'vitalis_primary_sport','Kuvvet antrenmanı');
    update_user_meta($student_id,'vitalis_progress',array(
        array('date'=>$demo_date(35),'weight'=>'85.4','sport'=>'Kuvvet antrenmanı','note'=>'Başlangıç ölçümü ve temel hareketlerin değerlendirilmesi. DEMO KAYDI.'),
        array('date'=>$demo_date(18),'weight'=>'84.1','sport'=>'Kuvvet antrenmanı','note'=>'Düzenli antrenman rutini oluşturuldu. DEMO KAYDI.'),
        array('date'=>$demo_date(4),'weight'=>'82.8','sport'=>'Kuvvet ve yürüyüş','note'=>'Devamlılık iyi; bir sonraki hedef hareket tekniğini korumak. DEMO KAYDI.')
    ));
    update_user_meta($student_id,'vitalis_trainer_progress',array(
        array('date'=>$demo_date(28),'focus'=>'Temel kuvvet ve güvenli teknik','weight'=>'85.0','note'=>'Temel hareketlerde kontrol ve düzenli devamlılık iyi ilerliyor. Bir sonraki çalışmada hareket açıklığını koruyup yükü kademeli artıracağız. Bu örnek demo değerlendirmesidir.','coach_id'=>$coach_id,'created_at'=>wp_date('Y-m-d H:i:s',current_time('timestamp')-28*DAY_IN_SECONDS)),
        array('date'=>$demo_date(12),'focus'=>'Kuvvette kademeli ilerleme','weight'=>'83.6','note'=>'Antrenman temposu ve toparlanma düzeni istikrarlı. Set aralarında dinlenmeyi koruyarak ana hareketlerde tekniğe odaklanmaya devam ediyoruz. Bu örnek demo değerlendirmesidir.','coach_id'=>$coach_id,'created_at'=>wp_date('Y-m-d H:i:s',current_time('timestamp')-12*DAY_IN_SECONDS)),
        array('date'=>$demo_date(2),'focus'=>'Kondisyon ve hareket kalitesi','weight'=>'82.9','note'=>'Kuvvet çalışmalarına eklenen düşük tempolu kondisyon seanslarına uyum iyi. Önümüzdeki haftanın odağı düzenli katılımı sürdürmek ve hareket kalitesini korumak. Bu örnek demo değerlendirmesidir.','coach_id'=>$coach_id,'created_at'=>wp_date('Y-m-d H:i:s',current_time('timestamp')-2*DAY_IN_SECONDS))
    ));
    update_option('vitalis_demo_credentials',array(
        'coach_login'=>$coach_data['user']->user_login,'coach_password'=>$coach_data['password'],
        'student_login'=>$student_data['user']->user_login,'student_password'=>$student_data['password'],
        'created'=>time()
    ),false);
    update_option('vitalis_demo_seeded',1);
}

function vitalis_core_ensure_demo_booking_resources() {
    if (get_option('vitalis_demo_booking_resources_migrated_v2')) { return; }
    $defaults = vitalis_core_default_settings();
    $stored = get_option('vitalis_core_settings',array());
    $current_raw = is_array($stored) && isset($stored['resources']) && is_scalar($stored['resources']) ? trim((string)$stored['resources']) : '';
    $current_lines = $current_raw!==''?array_map('trim',preg_split('/\r\n|\r|\n/',$current_raw)):array();
    $current = implode("\n",$current_lines);
    $legacy_defaults=array(
        "Yüzme Havuzu – Kulvar 1\nYüzme Havuzu – Kulvar 2\nTenis Sahası 1\nBasketbol / Voleybol Sahası\nGrup Stüdyosu",
        "Yüzme Havuzu – Kulvar 1\nYüzme Havuzu – Kulvar 2\nTenis Sahası 1\nBasketbol / Voleybol Sahası\nGrup Stüdyosu\nPilates / Yoga Stüdyosu\nFonksiyonel Antrenman Alanı\nKişisel Antrenman Stüdyosu\nÇok Amaçlı Salon"
    );
    if (!is_array($stored)) { $stored=array(); }
    if ($current==='' || in_array($current,array_map('trim',$legacy_defaults),true)) {
        $stored['resources']=$defaults['resources'];
    } else {
        $rows=preg_split('/\r\n|\r|\n/',$current);
        $rows=array_values(array_filter(array_map('sanitize_text_field',$rows),function($label){return $label!=='' && vitalis_core_is_allowed_resource($label);}));
        $stored['resources']=$rows?implode("\n",array_unique($rows)):$defaults['resources'];
    }
    if (!isset($stored['hours']) || !is_array($stored['hours'])) { $stored['hours']=$defaults['hours']; }
    $clean=vitalis_core_sanitize_settings($stored);
    if (get_option('vitalis_core_settings')!==$clean) { update_option('vitalis_core_settings',$clean); }
    global $wpdb;
    $table=vitalis_core_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) {
        $allowed=array_keys(vitalis_core_resources());
        if ($allowed) {
            $placeholders=implode(',',array_fill(0,count($allowed),'%s'));
            $sql=$wpdb->prepare("DELETE FROM $table WHERE note LIKE %s AND resource NOT IN ($placeholders)",array_merge(array('DEMO KAYDI — Müsaitlik ekranını göstermek için eklenmiştir%'),$allowed));
            $wpdb->query($sql);
        }
    }
    update_option('vitalis_demo_booking_resources_migrated_v2',1,false);
}

function vitalis_core_seed_demo_bookings() {
    vitalis_core_ensure_demo_booking_resources();
    if (get_option('vitalis_demo_bookings_seeded_v2')) { return; }
    global $wpdb;
    $table = vitalis_core_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) !== $table) { vitalis_core_create_table(); }
    $resources = vitalis_core_resources();
    if (!$resources) { return; }
    $settings = vitalis_core_settings();
    $today = new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone());
    $slots_for_date = function($date) use ($settings) {
        $day_obj = DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
        if (!$day_obj) { return array(); }
        $day=(int)$day_obj->format('N');
        $hours=$settings['hours'][$day] ?? array('open'=>'','close'=>'');
        $open=isset($hours['open'])?(int)substr($hours['open'],0,2):0;
        $close=isset($hours['close'])?(int)substr($hours['close'],0,2):0;
        $times=array();
        for ($hour=$open;$hour<$close;$hour++) {
            $time=sprintf('%02d:00',$hour);
            if ($date===wp_date('Y-m-d') && $time<=wp_date('H:00')) { continue; }
            $times[]=$time;
        }
        return $times;
    };
    $insert_demo = function($resource,$date,$time,$status) use ($wpdb,$table,$resources) {
        if (!isset($resources[$resource])) { return; }
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE resource=%s AND booking_date=%s AND booking_time=%s",$resource,$date,$time));
        if ($exists) { return; }
        $wpdb->insert($table,array(
            'resource'=>$resource,'booking_date'=>$date,'booking_time'=>$time,
            'full_name'=>'DEMO — Örnek rezervasyon','email'=>'demo-reservasyon@example.com','phone'=>'000 000 00 00',
            'note'=>'DEMO KAYDI — Müsaitlik ekranını göstermek için eklenmiştir; gerçek rezervasyon değildir.',
            'user_id'=>0,'status'=>$status,'created_at'=>current_time('mysql')
        ),array('%s','%s','%s','%s','%s','%s','%s','%d','%s','%s'));
    };
    $resource_keys=array_keys($resources);
    foreach (array_slice($resource_keys,0,min(4,count($resource_keys))) as $index=>$resource) {
        $offset=2+$index;
        $date=$today->modify('+'.$offset.' days')->format('Y-m-d');
        $times=$slots_for_date($date);
        if (!$times) { continue; }
        $selected=array_values(array_unique(array($times[0],$times[min(3,count($times)-1)])));
        foreach ($selected as $slot_index=>$time) { $insert_demo($resource,$date,$time,$slot_index===0?'pending':'approved'); }
    }
    $full_day_date=$today->modify('+8 days')->format('Y-m-d');
    $full_day_times=$slots_for_date($full_day_date);
    $full_day_resource=end($resource_keys);
    foreach ($full_day_times as $time) { $insert_demo($full_day_resource,$full_day_date,$time,'approved'); }
    update_option('vitalis_demo_bookings_seeded_v2',1,false);
}

function vitalis_core_demo_credentials_notice() {
    if (!current_user_can('manage_options')) { return; }
    $credentials = get_option('vitalis_demo_credentials',array());
    if (!is_array($credentials) || empty($credentials['coach_login']) || empty($credentials['student_login'])) { return; }
    echo '<div class="notice notice-info"><h2>Vitalis demo hesapları hazır</h2><p>Demo antrenör: <strong>'.esc_html($credentials['coach_login']).'</strong> / parola: <code>'.esc_html($credentials['coach_password']).'</code></p><p>Demo üye: <strong>'.esc_html($credentials['student_login']).'</strong> / parola: <code>'.esc_html($credentials['student_password']).'</code></p><p>Giriş için sitenin <code>/uye-girisi/</code> sayfasını kullanın. Antrenör hesabı Antrenör Paneli sayfasına yönlenir. Hesaplar örnektir; gerçek üyelerle karıştırmayın. Bilgileri kopyaladıktan sonra bu bildirimi gizleyebilirsiniz.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_demo_credentials_dismiss">';
    wp_nonce_field('vitalis_demo_credentials_dismiss','vitalis_demo_credentials_nonce');
    echo '<button class="button button-secondary" type="submit">Giriş bilgilerini aldım, gizle</button></form></div>';
}
add_action('admin_notices','vitalis_core_demo_credentials_notice');

function vitalis_core_demo_credentials_dismiss() {
    if (!current_user_can('manage_options')) { wp_die('Bu işlem için yetkiniz yok.'); }
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_demo_credentials_nonce')),'vitalis_demo_credentials_dismiss')) { wp_die('Güvenlik doğrulaması başarısız.'); }
    delete_option('vitalis_demo_credentials');
    wp_safe_redirect(admin_url());
    exit;
}
add_action('admin_post_vitalis_demo_credentials_dismiss','vitalis_core_demo_credentials_dismiss');

function vitalis_core_meta_boxes() {
    add_meta_box('vitalis-trainer-info','Antrenör bilgileri','vitalis_core_trainer_box','vitalis_trainer','normal','high');
    add_meta_box('vitalis-event-info','Etkinlik ayrıntıları','vitalis_core_event_box','vitalis_event','normal','high');
    add_meta_box('vitalis-facility-info','Tesis sayfası ve galeri','vitalis_core_facility_box','vitalis_facility','normal','high');
}
add_action('add_meta_boxes','vitalis_core_meta_boxes');

function vitalis_core_field($label,$key,$value,$type='text') {
    echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="widefat" type="'.esc_attr($type).'" name="vitalis_meta['.esc_attr($key).']" value="'.esc_attr($value).'" /></label></p>';
}
function vitalis_core_box_nonce() { wp_nonce_field('vitalis_core_meta','vitalis_core_meta_nonce'); }
function vitalis_core_trainer_box($post) {
    vitalis_core_box_nonce();
    vitalis_core_field('Uzmanlık alanları','specialties',get_post_meta($post->ID,'vitalis_specialties',true));
    vitalis_core_field('Seans biçimi ve süresi','session',get_post_meta($post->ID,'vitalis_session',true));
    vitalis_core_field('Ücret ve paket bilgisi','fees',get_post_meta($post->ID,'vitalis_fees',true));
    $coach_users=get_users(array('role'=>'vitalis_coach','number'=>-1,'orderby'=>'display_name','order'=>'ASC'));
    $assigned_coach=absint(get_post_meta($post->ID,'vitalis_coach_user_id',true));
    echo '<p><label><strong>Bu antrenör profilini yönetecek kullanıcı hesabı</strong><br><select class="widefat" name="vitalis_meta[coach_user_id]"><option value="0">Hesap atanmamış</option>';
    foreach ($coach_users as $coach_user) { echo '<option value="'.absint($coach_user->ID).'" '.selected($assigned_coach,$coach_user->ID,false).'>'.esc_html($coach_user->display_name.' — '.$coach_user->user_email).'</option>'; }
    echo '</select></label></p><p class="description">Atanan antrenör, kendi panelinden bu profile gelen randevu taleplerini kabul veya reddedebilir.</p>';
    echo '<hr><h3>Eğitim ve belgeler</h3><p><label><strong>Mezun olunan okul, bölüm ve eğitim detayları</strong><br><textarea class="widefat" rows="4" name="vitalis_meta[education]">'.esc_textarea(get_post_meta($post->ID,'vitalis_education',true)).'</textarea></label></p>';
    echo '<p><label><strong>Sertifika ve belge bağlantıları</strong><br><textarea class="widefat" rows="3" name="vitalis_meta[certificates]" placeholder="Belge adı | https://… (her satıra bir belge)">'.esc_textarea(get_post_meta($post->ID,'vitalis_certificates',true)).'</textarea></label><br><button type="button" class="button" id="vitalis-certificate-select">Medya kütüphanesinden belge seç</button> <span class="description">Her satır: Belge adı | dosya URL’si.</span></p>';
    echo '<hr><h3>Bireysel ders</h3><p><label><strong>Ders içeriği</strong><br><textarea class="widefat" rows="4" name="vitalis_meta[individual_detail]">'.esc_textarea(get_post_meta($post->ID,'vitalis_individual_detail',true)).'</textarea></label></p>';
    vitalis_core_field('Bireysel ders ücreti / paket','individual_fees',get_post_meta($post->ID,'vitalis_individual_fees',true));
    echo '<hr><h3>Grup dersi</h3><p><label><strong>Ders içeriği ve kontenjan</strong><br><textarea class="widefat" rows="4" name="vitalis_meta[group_detail]">'.esc_textarea(get_post_meta($post->ID,'vitalis_group_detail',true)).'</textarea></label></p>';
    vitalis_core_field('Grup dersi ücreti / paket','group_fees',get_post_meta($post->ID,'vitalis_group_fees',true));
    vitalis_core_field('En fazla grup katılımcısı','group_capacity',get_post_meta($post->ID,'vitalis_group_capacity',true),'number');
    echo '<hr><h3>Randevu takvimi</h3><p>Bu antrenör için haftalık ders saatlerini işaretleyin. Takvimde bekleyen ve onaylanan talepler bu saatleri dolu gösterir.</p>';
    $schedule=function_exists('vitalis_trainer_schedule')?vitalis_trainer_schedule($post->ID):array();
    $day_labels=array(1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar');
    echo '<table class="widefat striped"><thead><tr><th>Gün</th><th>Açık</th><th>Başlangıç</th><th>Bitiş</th></tr></thead><tbody>';
    foreach ($day_labels as $day=>$label) {
        $active=isset($schedule[$day]); $open=$active?$schedule[$day]['open']:'09:00'; $close=$active?$schedule[$day]['close']:'18:00';
        echo '<tr><td>'.esc_html($label).'</td><td><label><input type="checkbox" name="vitalis_meta[availability_days]['.$day.'][active]" value="1" '.checked($active,true,false).'> Uygun</label></td><td><select name="vitalis_meta[availability_days]['.$day.'][open]">';
        for ($hour=0;$hour<23;$hour++) { $time=sprintf('%02d:00',$hour); echo '<option value="'.esc_attr($time).'" '.selected($open,$time,false).'>'.esc_html($time).'</option>'; }
        echo '</select></td><td><select name="vitalis_meta[availability_days]['.$day.'][close]">';
        for ($hour=1;$hour<=23;$hour++) { $time=sprintf('%02d:00',$hour); echo '<option value="'.esc_attr($time).'" '.selected($close,$time,false).'>'.esc_html($time).'</option>'; }
        echo '</select></td></tr>';
    }
    echo '</tbody></table><p class="description">Saatleri değiştirince yeni uygunluk takvime yansır. Eski randevular yönetim listesindeki kayıt olarak kalır; çakışma kontrolü seçilen slotta da uygulanır.</p>';
    echo '<p><label><input type="checkbox" name="vitalis_meta[demo_profile]" value="1" '.checked(get_post_meta($post->ID,'vitalis_demo_profile',true),1,false).'> Bu profil ve eğitim bilgileri demo örneğidir</label></p><p>Profil fotoğrafını Öne çıkan görsel bölümünden, kısa tanıtımı içerik alanından düzenleyin.</p>';
}
function vitalis_core_event_box($post) {
    vitalis_core_box_nonce();
    vitalis_core_field('Etkinlik tarihi','event_date',get_post_meta($post->ID,'vitalis_event_date',true),'date');
    vitalis_core_field('Saat aralığı','event_time',get_post_meta($post->ID,'vitalis_event_time',true));
    vitalis_core_field('Mekân / adres','event_venue',get_post_meta($post->ID,'vitalis_event_venue',true));
    vitalis_core_field('Katılım ücreti','event_fee',get_post_meta($post->ID,'vitalis_event_fee',true));
    vitalis_core_field('Kontenjan','event_capacity',get_post_meta($post->ID,'vitalis_event_capacity',true));
    vitalis_core_field('Başvuru alabileceğiniz kişi sayısı','event_max_capacity',get_post_meta($post->ID,'vitalis_event_max_capacity',true),'number');
    echo '<p>Kontenjan bilgisini ziyaretçiye gösterilecek şekilde yazın. Başvuru kapasitesini kişi sayısı olarak girin (ör. 24). Bekleyen ve onaylanan başvurular bu kapasiteden düşer. Açıklama ve fotoğrafı içerik ve Öne çıkan görsel alanlarından düzenleyin.</p>';
}
function vitalis_core_facility_box($post) {
    vitalis_core_box_nonce();
    $page = get_post_meta($post->ID,'vitalis_page_slug',true);
    $ids = (array)get_post_meta($post->ID,'vitalis_gallery_ids',true);
    echo '<p><label><strong>Bağlı sayfa kısa adı</strong><br><input class="widefat" name="vitalis_meta[page_slug]" value="'.esc_attr($page).'" placeholder="fitness-salonu" /></label></p>';
    echo '<p>Kapak fotoğrafı için Öne çıkan görseli kullanın. Seçtiğiniz medya dosyaları ilgili tesisin fotoğraf galerisine eklenir.</p>';
    echo '<input type="hidden" id="vitalis-gallery-ids" name="vitalis_meta[gallery_ids]" value="'.esc_attr(implode(',',$ids)).'" />';
    echo '<button type="button" class="button" id="vitalis-gallery-select">Galeri görsellerini seç</button> <button type="button" class="button" id="vitalis-gallery-clear">Seçimi temizle</button><div id="vitalis-gallery-preview">';
    foreach ($ids as $id) { echo wp_get_attachment_image((int)$id,'thumbnail'); }
    echo '</div>';
    echo '<hr><h3>Temizlik ve bakım bilgilendirmesi</h3><p>Bu metin ziyaretçilere tesis sayfasında gösterilir. Yalnızca gerçek uygulamayı ve doğrulanmış son kontrol tarihini yayınlayın.</p>';
    echo '<p><label><strong>Kontrol planı / ziyaretçi bilgilendirmesi</strong><br><textarea class="widefat" rows="4" name="vitalis_meta[hygiene_text]">'.esc_textarea(get_post_meta($post->ID,'vitalis_hygiene_text',true)).'</textarea></label></p>';
    vitalis_core_field('Son gerçek kontrol tarihi','hygiene_date',get_post_meta($post->ID,'vitalis_hygiene_date',true),'date');
}
function vitalis_core_save_meta($post_id) {
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_core_meta_nonce')),'vitalis_core_meta')) { return; }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post',$post_id)) { return; }
    $data = isset($_POST['vitalis_meta']) && is_array($_POST['vitalis_meta']) ? wp_unslash($_POST['vitalis_meta']) : array();
    $fields = array('specialties'=>'vitalis_specialties','session'=>'vitalis_session','fees'=>'vitalis_fees','individual_fees'=>'vitalis_individual_fees','group_fees'=>'vitalis_group_fees','event_date'=>'vitalis_event_date','event_time'=>'vitalis_event_time','event_venue'=>'vitalis_event_venue','event_fee'=>'vitalis_event_fee','event_capacity'=>'vitalis_event_capacity','page_slug'=>'vitalis_page_slug','hygiene_date'=>'vitalis_hygiene_date');
    foreach ($fields as $key=>$meta) { if (isset($data[$key]) && is_scalar($data[$key])) { update_post_meta($post_id,$meta,sanitize_text_field($data[$key])); } }
    if (isset($data['group_capacity']) && is_scalar($data['group_capacity'])) { update_post_meta($post_id,'vitalis_group_capacity',max(2,min(40,absint($data['group_capacity'])))); }
    foreach (array('education'=>'vitalis_education','certificates'=>'vitalis_certificates','individual_detail'=>'vitalis_individual_detail','group_detail'=>'vitalis_group_detail') as $key=>$meta) { if (isset($data[$key]) && is_scalar($data[$key])) { update_post_meta($post_id,$meta,sanitize_textarea_field($data[$key])); } }
    if (get_post_type($post_id)==='vitalis_trainer' && isset($data['availability_days']) && is_array($data['availability_days'])) {
        $lines=array();
        foreach ($data['availability_days'] as $day=>$hours) {
            $day=absint($day); if ($day<1 || $day>7 || !is_array($hours) || empty($hours['active'])) { continue; }
            $open=isset($hours['open'])?sanitize_text_field($hours['open']):''; $close=isset($hours['close'])?sanitize_text_field($hours['close']):'';
            if (preg_match('/^(?:[01]\d|2[0-3]):00$/',$open) && preg_match('/^(?:[01]\d|2[0-3]):00$/',$close) && $open<$close) { $lines[]=$day.'|'.$open.'|'.$close; }
        }
        update_post_meta($post_id,'vitalis_availability',implode("\n",$lines));
    }
    if (get_post_type($post_id)==='vitalis_trainer') {
        if (!empty($data['demo_profile'])) { update_post_meta($post_id,'vitalis_demo_profile',1); }
        else { delete_post_meta($post_id,'vitalis_demo_profile'); }
        if (isset($data['coach_user_id']) && is_scalar($data['coach_user_id'])) {
            $coach_id=absint($data['coach_user_id']); $coach=$coach_id?get_userdata($coach_id):false;
            if ($coach && in_array('vitalis_coach',(array)$coach->roles,true)) { update_post_meta($post_id,'vitalis_coach_user_id',$coach_id); }
            else { delete_post_meta($post_id,'vitalis_coach_user_id'); }
        }
    }
    if (get_post_type($post_id)==='vitalis_event' && isset($data['event_max_capacity']) && is_scalar($data['event_max_capacity'])) {
        $max_capacity=absint($data['event_max_capacity']);
        if ($max_capacity>0) { update_post_meta($post_id,'vitalis_event_max_capacity',min(99999,$max_capacity)); }
        if (function_exists('vitalis_core_event_sync_capacity')) { vitalis_core_event_sync_capacity($post_id); }
    }
    if (isset($data['hygiene_text']) && is_scalar($data['hygiene_text'])) { update_post_meta($post_id,'vitalis_hygiene_text',sanitize_textarea_field($data['hygiene_text'])); }
    if (isset($data['gallery_ids']) && is_scalar($data['gallery_ids'])) {
        $ids = array_values(array_filter(array_map('absint',explode(',',sanitize_text_field($data['gallery_ids'])))));
        update_post_meta($post_id,'vitalis_gallery_ids',$ids);
    }
}
add_action('save_post_vitalis_trainer','vitalis_core_save_meta');
add_action('save_post_vitalis_event','vitalis_core_save_meta');
add_action('save_post_vitalis_facility','vitalis_core_save_meta');

function vitalis_core_admin_assets($hook) {
    $screen = get_current_screen();
    if (in_array($hook,array('toplevel_page_vitalis-club','vitalis-club_page_vitalis-settings'),true)) {
        wp_enqueue_style('vitalis-core-admin-style',get_template_directory_uri().'/assets/css/admin.css',array(),'1.7.0');
    }
    if (!$screen || !in_array($screen->post_type,array('vitalis_facility','vitalis_trainer'),true)) { return; }
    wp_enqueue_media();
    wp_enqueue_script('vitalis-core-admin',get_template_directory_uri().'/assets/js/admin.js',array('jquery'),'1.7.0',true);
}
add_action('admin_enqueue_scripts','vitalis_core_admin_assets');

function vitalis_core_register_admin() {
    add_menu_page('Vitalis Kulüp Yönetimi','Vitalis Kulüp','manage_options','vitalis-club','vitalis_core_admin_home','dashicons-heart',26);
    add_submenu_page('vitalis-club','Rezervasyon Talepleri','Rezervasyonlar','manage_options','vitalis-bookings','vitalis_core_admin_bookings');
    add_submenu_page('vitalis-club','Antrenör Randevuları','Antrenör Randevuları','manage_options','vitalis-trainer-appointments','vitalis_trainer_admin_appointments');
    add_submenu_page('vitalis-club','Etkinlik Kayıtları','Etkinlik Kayıtları','manage_options','vitalis-event-signups','vitalis_core_admin_event_signups');
    add_submenu_page('vitalis-club','Rapor','Rapor','manage_options','vitalis-settings','vitalis_core_admin_settings');
}
add_action('admin_menu','vitalis_core_register_admin');

function vitalis_core_admin_home() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $member_total=vitalis_core_admin_count_users('subscriber');
    $demo_members=vitalis_core_admin_count_users('subscriber','student');
    $coach_total=vitalis_core_admin_count_users('vitalis_coach');
    $demo_coaches=vitalis_core_admin_count_users('vitalis_coach','coach');
    $active_members=vitalis_core_admin_count_users('subscriber',null,'Aktif üye');
    $demo_active=vitalis_core_admin_count_users('subscriber','student','Aktif üye');
    $trainer_posts=wp_count_posts('vitalis_trainer');
    $trainer_profiles=isset($trainer_posts->publish)?(int)$trainer_posts->publish:0;
    $trainer_pending=$trainer_approved=$trainer_demo_appointments=0;
    $trainer_table=vitalis_core_trainer_appointments_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$trainer_table))===$trainer_table) {
        $trainer_pending=(int)$wpdb->get_var("SELECT COUNT(*) FROM $trainer_table WHERE status='pending' AND is_demo=0");
        $trainer_approved=(int)$wpdb->get_var("SELECT COUNT(*) FROM $trainer_table WHERE status='approved' AND is_demo=0");
        $trainer_demo_appointments=(int)$wpdb->get_var("SELECT COUNT(*) FROM $trainer_table WHERE is_demo=1 AND status<>'cancelled'");
    }
    $pending=$approved=$demo_bookings=0;
    $table=vitalis_core_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) {
        $pending=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='pending' AND note NOT LIKE '%DEMO KAYDI%'");
        $approved=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='approved' AND note NOT LIKE '%DEMO KAYDI%'");
        $demo_bookings=(int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE note LIKE '%DEMO KAYDI%'");
    }
    $event_active=0; $event_demo_active=0;
    $event_signups=vitalis_core_event_signups_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$event_signups))===$event_signups) { $event_active=(int)$wpdb->get_var("SELECT COUNT(*) FROM $event_signups WHERE status IN ('pending','approved') AND note NOT LIKE '%DEMO KAYDI%'"); $event_demo_active=(int)$wpdb->get_var("SELECT COUNT(*) FROM $event_signups WHERE status IN ('pending','approved') AND note LIKE '%DEMO KAYDI%'"); }
    $real_members=max(0,$member_total-$demo_members);
    $real_coaches=max(0,$coach_total-$demo_coaches);
    echo '<div class="wrap vitalis-admin-home"><h1>Vitalis Kulüp Raporu</h1><p class="vitalis-admin-intro">WordPress kullanıcı hesapları, antrenör profilleri ve rezervasyon kayıtlarının güncel özeti. Demo hesapları ve rezervasyonları ayrı gösterilir.</p>';
    echo '<h2>Üyelik ve antrenör kadrosu</h2><div class="vitalis-report-grid">';
    vitalis_core_admin_report_card('Üye hesabı',$member_total,'Demo dahil toplam üye hesabı',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Gerçek üye hesabı',$real_members,'Demo hesabı hariç',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Demo üye hesabı',$demo_members,'Örnek panel ve gelişim verisi',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Aktif üyelik',$active_members,'Demo aktif üyelik: '.$demo_active,admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Antrenör hesabı',$coach_total,'Demo dahil toplam hesap',admin_url('users.php?role=vitalis_coach'));
    vitalis_core_admin_report_card('Gerçek antrenör hesabı',$real_coaches,'Demo hesabı hariç',admin_url('users.php?role=vitalis_coach'));
    vitalis_core_admin_report_card('Demo antrenör hesabı',$demo_coaches,'Atanmış demo öğrenciyle',admin_url('users.php?role=vitalis_coach'));
    vitalis_core_admin_report_card('Yayınlanan antrenör profili',$trainer_profiles,'Web sitesinde görünen profil',admin_url('edit.php?post_type=vitalis_trainer'));
    echo '</div><h2>Rezervasyon takibi</h2><div class="vitalis-report-grid vitalis-report-grid-small">';
    vitalis_core_admin_report_card('Onay bekleyen talep',$pending,'Demo talepleri hariç',admin_url('admin.php?page=vitalis-bookings'));
    vitalis_core_admin_report_card('Onaylanmış talep',$approved,'Demo talepleri hariç',admin_url('admin.php?page=vitalis-bookings'));
    vitalis_core_admin_report_card('Demo uygunluk kaydı',$demo_bookings,'Takvimde dolu saat örnekleri',admin_url('admin.php?page=vitalis-bookings'));
    vitalis_core_admin_report_card('Antrenör randevu talebi',$trainer_pending,'Onay bekleyen gerçek talepler',admin_url('admin.php?page=vitalis-trainer-appointments'));
    vitalis_core_admin_report_card('Onaylı antrenör randevusu',$trainer_approved,'Demo talepleri hariç',admin_url('admin.php?page=vitalis-trainer-appointments'));
    vitalis_core_admin_report_card('Demo dolu PT saati',$trainer_demo_appointments,'Takvimde gösterilen örnek kayıtlar',admin_url('admin.php?page=vitalis-trainer-appointments'));
    vitalis_core_admin_report_card('Aktif etkinlik kaydı',$event_active,'Gerçek üye kayıtları ve bekleyen eski başvurular',admin_url('admin.php?page=vitalis-event-signups'));
    vitalis_core_admin_report_card('Demo etkinlik kaydı',$event_demo_active,'Örnek üye paneli kaydı',admin_url('admin.php?page=vitalis-event-signups'));
    echo '</div><div class="vitalis-admin-actions"><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=vitalis-settings')).'">Detaylı rapor</a> <a class="button" href="'.esc_url(admin_url('users.php')).'">Üyeleri yönet</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=vitalis_trainer')).'">Antrenör profilleri</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=vitalis-trainer-appointments')).'">Antrenör randevuları</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=vitalis-event-signups')).'">Etkinlik kayıtları</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=vitalis-bookings')).'">Rezervasyonlar</a></div>';
    echo '<p class="description">Sayılar bu WordPress kurulumundaki hesap ve kayıtları yansıtır. Demo kullanıcıları ve örnek doluluk kayıtları gerçek üye veya rezervasyon değildir.</p><p>Antrenör, etkinlik ve tesis içeriklerini sol menüden yönetin. Fotoğraf ve tesis galerileri ilgili kaydın düzenleme ekranında değiştirilebilir.</p></div>';
}

function vitalis_core_admin_count_users($role,$demo_kind=null,$member_status=null) {
    $meta_query=array();
    if ($demo_kind!==null) { $meta_query[]=array('key'=>'vitalis_demo_account','value'=>$demo_kind,'compare'=>'='); }
    if ($member_status!==null) { $meta_query[]=array('key'=>'vitalis_member_status','value'=>$member_status,'compare'=>'='); }
    $query=new WP_User_Query(array('role'=>$role,'fields'=>'ID','number'=>1,'count_total'=>true,'meta_query'=>$meta_query));
    return (int)$query->get_total();
}

function vitalis_core_admin_report_card($label,$count,$note,$url) {
    echo '<a class="vitalis-report-card" href="'.esc_url($url).'"><span>'.esc_html($label).'</span><strong>'.esc_html(number_format_i18n((int)$count)).'</strong><small>'.esc_html($note).'</small></a>';
}

function vitalis_core_report_date($value,$fallback) {
    $value=is_scalar($value)?sanitize_text_field((string)$value):'';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) { return $fallback; }
    $parts=explode('-',$value);
    return checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])?$value:$fallback;
}

function vitalis_core_report_busy_rows($table,$kind,$start,$end) {
    global $wpdb;
    $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
    if ($exists!==$table) { return array(); }
    if ($kind==='trainer') {
        $rows=$wpdb->get_results($wpdb->prepare("SELECT trainer_id AS item_id, appointment_date AS item_date, appointment_time AS item_time, status, is_demo AS demo FROM $table WHERE appointment_date BETWEEN %s AND %s AND status IN ('pending','approved')",$start,$end));
    } else {
        $rows=$wpdb->get_results($wpdb->prepare("SELECT resource AS item_id, booking_date AS item_date, booking_time AS item_time, status, note FROM $table WHERE booking_date BETWEEN %s AND %s AND status IN ('pending','approved')",$start,$end));
    }
    $busy=array();
    foreach ((array)$rows as $row) {
        $item=(string)$row->item_id; $date=(string)$row->item_date; $time=substr((string)$row->item_time,0,5);
        if (!isset($busy[$item])) { $busy[$item]=array(); }
        if (!isset($busy[$item][$date])) { $busy[$item][$date]=array(); }
        $demo=$kind==='trainer'?(int)$row->demo===1:(strpos((string)$row->note,'DEMO KAYDI')!==false);
        $busy[$item][$date][$time]=array('demo'=>$demo,'status'=>(string)$row->status);
    }
    return $busy;
}

function vitalis_core_report_render_item($item_id,$label,$kind,$schedule,$dates,$busy_map) {
    echo '<tr><th scope="row" class="vitalis-report-item">'.esc_html($label).'</th>';
    foreach ($dates as $date_info) {
        $date=$date_info['date']; $weekday=(int)$date_info['weekday']; $hours=isset($schedule[$weekday])?$schedule[$weekday]:null; $day_busy=isset($busy_map[$item_id][$date])?$busy_map[$item_id][$date]:array();
        echo '<td class="vitalis-report-day">';
        if (!$hours || empty($hours['open']) || empty($hours['close']) || $hours['open'] >= $hours['close']) {
            echo '<strong class="vitalis-report-closed">Kapalı</strong><span class="vitalis-report-hours">Çalışma saati tanımlı değil</span>';
            if ($day_busy) { echo '<span class="vitalis-report-outside">Saat kaydı: '.esc_html(implode(', ',array_keys($day_busy))).'</span>'; }
            echo '</td>';
            continue;
        }
        $start=(int)substr($hours['open'],0,2); $end=(int)substr($hours['close'],0,2); $free=0; $occupied=0;
        $slot_list=array();
        for ($hour=$start;$hour<$end;$hour++) {
            $time=sprintf('%02d:00',$hour); $key=sprintf('%02d',$hour);
            if (isset($day_busy[$time])) { $occupied++; $demo=!empty($day_busy[$time]['demo']); $class=$demo?'vitalis-slot-demo':'vitalis-slot-busy'; $status=$demo?'Demo doluluğu':'Dolu'; }
            else { $free++; $class='vitalis-slot-free'; $status='Müsait'; }
            $slot_list[]='<span class="vitalis-report-slot '.$class.'" title="'.esc_attr($time.' — '.$status).'">'.$key.'</span>';
        }
        $total=$free+$occupied;
        $outside=array();
        foreach ($day_busy as $time=>$record) { if ((int)substr($time,0,2)<$start || (int)substr($time,0,2)>=$end) { $outside[]=$time; } }
        $summary=$outside?'Saat dışı kayıt':($occupied===0?'Tamamen müsait':($free===0?'Tamamen dolu':'Kısmen dolu'));
        $summary_class=$outside?'vitalis-report-partial':($occupied===0?'vitalis-report-available':($free===0?'vitalis-report-full':'vitalis-report-partial'));
        echo '<strong class="'.$summary_class.'">'.esc_html($summary).'</strong><span class="vitalis-report-hours">'.esc_html($free.' / '.$total.' saat boş').' · '.esc_html($hours['open'].'–'.$hours['close']).'</span><div class="vitalis-report-slots" aria-label="'.esc_attr($date.' saat uygunluğu').'">'.implode('',$slot_list).'</div>';
        if ($outside) { echo '<span class="vitalis-report-outside">Program dışı kayıt: '.esc_html(implode(', ',$outside)).'</span>'; }
        echo '</td>';
    }
    echo '</tr>';
}

function vitalis_core_report_render_table($title,$kind,$items,$dates,$busy_map) {
    echo '<section class="vitalis-report-section"><h3>'.esc_html($title).'</h3><p class="vitalis-report-section-note">Her kutuda boş saat sayısı ve saatlik doluluk görünür. Yeşil: müsait, kırmızı: dolu, sarı: demo doluluğu.</p><div class="vitalis-report-table-wrap"><table class="widefat striped vitalis-occupancy-table"><thead><tr><th>'.($kind==='trainer'?'Antrenör':'Kiralanabilir alan').'</th>';
    foreach ($dates as $date_info) { echo '<th>'.esc_html($date_info['weekday_label']).'<br><span>'.esc_html($date_info['display']).'</span></th>'; }
    echo '</tr></thead><tbody>';
    if (!$items) { echo '<tr><td colspan="8">'.($kind==='trainer'?'Yayınlanmış antrenör profili bulunamadı.':'Kiralanabilir alan tanımlanmamış.').'</td></tr>'; }
    foreach ($items as $id=>$item) {
        vitalis_core_report_render_item((string)$id,$item['label'],$kind,$item['schedule'],$dates,$busy_map);
    }
    echo '</tbody></table></div></section>';
}

function vitalis_core_sanitize_settings($input) {
    $defaults = vitalis_core_default_settings();
    $input = is_array($input) ? $input : array();
    $hours_input = isset($input['hours']) && is_array($input['hours']) ? $input['hours'] : array();
    $clean = array('resources'=>'','hours'=>array());
    $resource_value = isset($input['resources']) && is_scalar($input['resources']) ? (string)wp_unslash($input['resources']) : '';
    $lines = $resource_value !== '' ? preg_split('/\r\n|\r|\n/',$resource_value) : array();
    $lines = array_slice(array_filter(array_map('sanitize_text_field',$lines),function($label){return $label!=='' && vitalis_core_is_allowed_resource($label);}),0,40);
    $clean['resources'] = $lines ? implode("\n",array_unique($lines)) : $defaults['resources'];
    for ($day=1; $day<=7; $day++) {
        $day_input = isset($hours_input[$day]) && is_array($hours_input[$day]) ? $hours_input[$day] : array();
        $open = isset($day_input['open']) && is_scalar($day_input['open']) ? sanitize_text_field($day_input['open']) : $defaults['hours'][$day]['open'];
        $close = isset($day_input['close']) && is_scalar($day_input['close']) ? sanitize_text_field($day_input['close']) : $defaults['hours'][$day]['close'];
        if (!preg_match('/^([01]\d|2[0-3]):00$/',$open)) { $open = $defaults['hours'][$day]['open']; }
        if (!preg_match('/^([01]\d|2[0-3]):00$/',$close)) { $close = $defaults['hours'][$day]['close']; }
        $clean['hours'][$day] = array('open'=>$open,'close'=>$close);
    }
    return $clean;
}

function vitalis_core_register_settings() {
    register_setting('vitalis_core','vitalis_core_settings','vitalis_core_sanitize_settings');
}
add_action('admin_init','vitalis_core_register_settings');

function vitalis_core_admin_settings() {
    if (!current_user_can('manage_options')) { return; }
    $settings = vitalis_core_settings();
    $days = array(1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar');
    $today=wp_date('Y-m-d');
    $selected=vitalis_core_report_date(isset($_GET['rapor_tarihi'])?wp_unslash($_GET['rapor_tarihi']):'',$today);
    $start=DateTimeImmutable::createFromFormat('!Y-m-d',$selected,wp_timezone());
    if (!$start) { $start=new DateTimeImmutable($today,wp_timezone()); }
    $end=$start->modify('+6 days'); $end_date=$end->format('Y-m-d');
    $dates=array();
    for ($offset=0;$offset<7;$offset++) {
        $date=$start->modify('+'.$offset.' days');
        $dates[]=array('date'=>$date->format('Y-m-d'),'weekday'=>(int)$date->format('N'),'weekday_label'=>$days[(int)$date->format('N')],'display'=>$date->format('d.m.Y'));
    }
    $member_total=vitalis_core_admin_count_users('subscriber');
    $demo_members=vitalis_core_admin_count_users('subscriber','student');
    $active_members=vitalis_core_admin_count_users('subscriber',null,'Aktif üye');
    $demo_active_members=vitalis_core_admin_count_users('subscriber','student','Aktif üye');
    $coach_total=vitalis_core_admin_count_users('vitalis_coach');
    $trainer_posts=wp_count_posts('vitalis_trainer'); $published_trainers=isset($trainer_posts->publish)?(int)$trainer_posts->publish:0;
    $real_students=max(0,$member_total-$demo_members);
    $month_current=new DateTimeImmutable($today,wp_timezone());
    $month_start=$month_current->modify('first day of this month')->modify('-11 months');
    $monthly=array();
    for ($i=0;$i<12;$i++) { $month=$month_start->modify('+'.$i.' months')->format('Y-m'); $monthly[$month]=0; }
    global $wpdb;
    $role_key=$wpdb->prefix.'capabilities';
    $month_rows=$wpdb->get_results($wpdb->prepare("SELECT DATE_FORMAT(u.user_registered,'%%Y-%%m') AS month_key, COUNT(DISTINCT u.ID) AS total FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} role_meta ON role_meta.user_id=u.ID AND role_meta.meta_key=%s AND role_meta.meta_value LIKE %s LEFT JOIN {$wpdb->usermeta} demo_meta ON demo_meta.user_id=u.ID AND demo_meta.meta_key='vitalis_demo_account' WHERE u.user_registered >= %s AND (demo_meta.meta_value IS NULL OR demo_meta.meta_value='') GROUP BY month_key",$role_key,'%subscriber%',$month_start->format('Y-m-d 00:00:00')));
    foreach ((array)$month_rows as $row) { if (isset($monthly[$row->month_key])) { $monthly[$row->month_key]=(int)$row->total; } }
    $max_month=max(1,max($monthly));
    $resource_items=array();
    foreach (vitalis_core_resources() as $resource_id=>$resource_label) { $resource_items[$resource_id]=array('label'=>$resource_label,'schedule'=>$settings['hours']); }
    $trainer_items=array();
    $trainer_posts=get_posts(array('post_type'=>'vitalis_trainer','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
    foreach ($trainer_posts as $trainer_post) { $trainer_items[(string)$trainer_post->ID]=array('label'=>$trainer_post->post_title,'schedule'=>function_exists('vitalis_trainer_schedule')?vitalis_trainer_schedule($trainer_post->ID):array()); }
    $resource_busy=vitalis_core_report_busy_rows(vitalis_core_table(),'resource',$selected,$end_date);
    $trainer_busy=vitalis_core_report_busy_rows(vitalis_core_trainer_appointments_table(),'trainer',$selected,$end_date);
    $prev=$start->modify('-7 days')->format('Y-m-d'); $next=$start->modify('+7 days')->format('Y-m-d');
    echo '<div class="wrap vitalis-admin-home vitalis-report-page"><h1>Vitalis Rapor</h1><p class="vitalis-admin-intro">Üye ve antrenör sayılarıyla, kiralanabilir alanların ve PT takvimlerinin gün/saat doluluklarını tek ekranda inceleyin. Bekleyen ve onaylanan kayıtlar dolu saat sayılır; iptal ve reddedilen kayıtlar müsaittir.</p>';
    echo '<nav class="vitalis-report-nav" aria-label="Rapor bölümleri"><a href="#ogrenci-ozeti">Öğrenci özeti</a><a href="#takvim-doluluk">PT ve alan doluluğu</a><a href="#calisma-saatleri">Çalışma saatleri</a></nav>';
    echo '<section class="vitalis-report-block" id="ogrenci-ozeti"><h2>Üye ve öğrenci özeti</h2><p class="vitalis-report-section-note">Sayılar bu WordPress sitesindeki üye hesaplarıdır; demo hesapları ayrı gösterilir.</p><div class="vitalis-report-grid">';
    vitalis_core_admin_report_card('Toplam öğrenci hesabı',$member_total,'Demo dahil tüm üye hesapları',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Gerçek öğrenci hesabı',$real_students,'Demo kullanıcıları hariç',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Demo öğrenci hesabı',$demo_members,'Örnek profil ve gelişim verisi',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Aktif üyelik',$active_members,'Demo dahil aktif üyeler',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Gerçek aktif üye',max(0,$active_members-$demo_active_members),'Demo hesabı hariç',admin_url('users.php?role=subscriber'));
    vitalis_core_admin_report_card('Antrenör hesabı',$coach_total,'Demo hesapları dahil',admin_url('users.php?role=vitalis_coach'));
    vitalis_core_admin_report_card('Yayınlanmış PT profili',$published_trainers,'Saatlik uygunluğu raporda gösterilir',admin_url('edit.php?post_type=vitalis_trainer'));
    echo '</div><div class="vitalis-monthly-report"><h3>Son 12 ayda yeni gerçek üye hesapları</h3><div class="vitalis-month-bars">';
    foreach ($monthly as $month=>$count) {
        $height=max(4,(int)round(($count/$max_month)*100));
        $month_label=wp_date('M Y',strtotime($month.'-01'),wp_timezone());
        echo '<div class="vitalis-month-bar"><strong>'.esc_html(number_format_i18n($count)).'</strong><span class="vitalis-month-bar-track"><i style="height:'.$height.'%"></i></span><small>'.esc_html($month_label).'</small></div>';
    }
    echo '</div><p class="description">Ay bazında, demo olmayan “Abone/Üye” rolü hesapları sayılır. Üyelik durumu ve ücret tahsilatı hakkında çıkarım yapmaz.</p></div></section>';
    echo '<section class="vitalis-report-block" id="takvim-doluluk"><h2>PT ve alan doluluk raporu</h2><p class="vitalis-report-section-note">İstediğiniz başlangıç gününü seçerek 7 günlük görünümü tarayın. Her takvim kutusunda çalışma saatleri, boş saat adedi ve saatlerin durumu gösterilir.</p><form method="get" class="vitalis-report-date-form"><input type="hidden" name="page" value="vitalis-settings"><a class="button" href="'.esc_url(add_query_arg(array('page'=>'vitalis-settings','rapor_tarihi'=>$prev),admin_url('admin.php'))).'">← Önceki 7 gün</a><label for="vitalis-report-date">Başlangıç günü</label><input id="vitalis-report-date" type="date" name="rapor_tarihi" value="'.esc_attr($selected).'" required><button class="button button-primary" type="submit">Tarihi göster</button><a class="button" href="'.esc_url(add_query_arg(array('page'=>'vitalis-settings','rapor_tarihi'=>$next),admin_url('admin.php'))).'">Sonraki 7 gün →</a><a class="button" href="'.esc_url(add_query_arg(array('page'=>'vitalis-settings','rapor_tarihi'=>$today),admin_url('admin.php'))).'">Bugün</a></form>';
    echo '<p class="vitalis-report-date-range"><strong>'.esc_html($start->format('d.m.Y')).' – '.esc_html($end->format('d.m.Y')).'</strong></p>';
    vitalis_core_report_render_table('Kiralanabilir alanlar','resource',$resource_items,$dates,$resource_busy);
    vitalis_core_report_render_table('Antrenör / PT çalışma takvimi','trainer',$trainer_items,$dates,$trainer_busy);
    echo '<p class="description">Demo kayıtları sarı gösterilir ve örnek doluluk içindir. Saatlik alanlarda onaylı veya bekleyen kayıtlar dolu; PT tarafında antrenör kararı bekleyen ya da kabul edilmiş randevular dolu görünür. Canlı ödeme veya harici takvim bağlantısı içermez.</p></section>';
    echo '<section class="vitalis-report-block vitalis-report-settings" id="calisma-saatleri"><h2>Çalışma saatleri ve kiralanabilir alanlar</h2><p class="vitalis-report-section-note">Aşağıdaki ayarlar raporun ve web sitesindeki kiralama takviminin çalışma saatlerini belirler.</p><form method="post" action="options.php">';
    settings_fields('vitalis_core');
    echo '<table class="form-table"><tr><th scope="row"><label for="vitalis-resources">Kiralanabilir alanlar</label></th><td><textarea class="large-text" rows="7" id="vitalis-resources" name="vitalis_core_settings[resources]">'.esc_textarea($settings['resources']).'</textarea></td></tr>';
    foreach ($days as $day=>$label) {
        $hours = isset($settings['hours'][$day]) ? $settings['hours'][$day] : array('open'=>'','close'=>'');
        echo '<tr><th>'.esc_html($label).'</th><td><label>Açılış <input type="time" step="3600" name="vitalis_core_settings[hours]['.$day.'][open]" value="'.esc_attr($hours['open']).'"></label> &nbsp; <label>Kapanış <input type="time" step="3600" name="vitalis_core_settings[hours]['.$day.'][close]" value="'.esc_attr($hours['close']).'"></label></td></tr>';
    }
    echo '</table>'; submit_button('Çalışma saatlerini ve alanları kaydet'); echo '</form></section></div>';
}

function vitalis_core_admin_bookings() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $rows = $wpdb->get_results('SELECT * FROM '.vitalis_core_table().' ORDER BY booking_date DESC, booking_time ASC LIMIT 300');
    $labels = array('pending'=>'Eski talep — onay bekliyor','approved'=>'Rezerve edildi','cancelled'=>'İptal edildi');
    echo '<div class="wrap"><h1>Rezervasyonlar</h1><p>Yeni kiralamalar boş saat seçilip form gönderildiğinde doğrudan rezerve edilir. İptal edilen saatler yeniden müsait olur. Bu modül çevrimiçi ödeme almaz.</p><table class="widefat striped"><thead><tr><th>Tarih / saat</th><th>Alan</th><th>İletişim</th><th>Not</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="6">Henüz rezervasyon talebi yok.</td></tr>'; }
    foreach ($rows as $row) {
        $key = wp_create_nonce('vitalis_booking_status_'.$row->id);
        echo '<tr><td>'.esc_html(mysql2date('d.m.Y', $row->booking_date)).'<br>'.esc_html(substr($row->booking_time,0,5)).'</td><td>'.esc_html(vitalis_core_resources()[$row->resource] ?? $row->resource).'</td><td><strong>'.esc_html($row->full_name).'</strong><br><a href="mailto:'.esc_attr($row->email).'">'.esc_html($row->email).'</a><br>'.esc_html($row->phone).'</td><td>'.esc_html($row->note).'</td><td>'.esc_html($labels[$row->status] ?? $row->status).'</td><td>';
        foreach (array('approved'=>'Onayla','cancelled'=>'İptal et') as $status=>$button) {
            if ($row->status === $status) { continue; }
            echo '<form style="display:inline-block;margin:0 5px 5px 0" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_booking_status"><input type="hidden" name="booking_id" value="'.absint($row->id).'"><input type="hidden" name="status" value="'.esc_attr($status).'"><input type="hidden" name="_wpnonce" value="'.esc_attr($key).'">';
            submit_button($button,'small','submit',false); echo '</form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function vitalis_core_change_booking_status() {
    if (!current_user_can('manage_options')) { wp_die('Bu işlem için yetkiniz yok.'); }
    $id = absint(vitalis_core_request_value($_POST,'booking_id',0));
    $status = sanitize_key(vitalis_core_request_value($_POST,'status'));
    check_admin_referer('vitalis_booking_status_'.$id);
    if ($id && in_array($status,array('approved','cancelled'),true)) {
        global $wpdb;
        $wpdb->update(vitalis_core_table(),array('status'=>$status),array('id'=>$id),array('%s'),array('%d'));
    }
    wp_safe_redirect(admin_url('admin.php?page=vitalis-bookings&updated=1'));
    exit;
}
add_action('admin_post_vitalis_booking_status','vitalis_core_change_booking_status');

function vitalis_core_admin_profile_fields($user) {
    if (!current_user_can('edit_user',$user->ID)) { return; }
    echo '<h2>Vitalis üyelik bilgileri</h2><table class="form-table"><tr><th><label for="vitalis-member-status">Üyelik durumu</label></th><td><select id="vitalis-member-status" name="vitalis_member_status">';
    $status = get_user_meta($user->ID,'vitalis_member_status',true) ?: 'Aday üye';
    foreach (array('Aday üye','Aktif üye','Donduruldu','Pasif') as $option) { echo '<option '.selected($status,$option,false).'>'.esc_html($option).'</option>'; }
    echo '</select></td></tr><tr><th><label for="vitalis-member-plan">Üyelik paketi</label></th><td><p>Üyenin paket talebi: <strong>'.esc_html(get_user_meta($user->ID,'vitalis_member_requested_plan',true) ?: 'Paket seçilmedi').'</strong></p><input class="regular-text" id="vitalis-member-plan" name="vitalis_member_plan" value="'.esc_attr(get_user_meta($user->ID,'vitalis_member_plan',true)).'" placeholder="Örn. 6 aylık üyelik"></td></tr>';
    if (current_user_can('manage_options')) {
        $coaches = get_users(array('role'=>'vitalis_coach','number'=>-1,'orderby'=>'display_name','order'=>'ASC'));
        $assigned = absint(get_user_meta($user->ID,'vitalis_assigned_coach_id',true));
        echo '<tr><th><label for="vitalis-assigned-coach">Sorumlu antrenör</label></th><td>';
        wp_nonce_field('vitalis_assign_coach_'.$user->ID,'vitalis_assign_coach_nonce');
        echo '<select id="vitalis-assigned-coach" name="vitalis_assigned_coach_id"><option value="0">Antrenör atanmamış</option>';
        foreach ($coaches as $coach) { echo '<option value="'.absint($coach->ID).'" '.selected($assigned,$coach->ID,false).'>'.esc_html($coach->display_name.' — '.$coach->user_email).'</option>'; }
        echo '</select><p class="description">Atanan antrenör bu üyeye özel gelişim notları ekleyebilir. Notlar yalnızca bu üyenin panelinde gösterilir.</p></td></tr>';
    }
    echo '</table>';
}
add_action('show_user_profile','vitalis_core_admin_profile_fields');
add_action('edit_user_profile','vitalis_core_admin_profile_fields');
function vitalis_core_save_profile_fields($user_id) {
    if (!current_user_can('edit_user',$user_id)) { return; }
    if (isset($_POST['vitalis_member_status']) && is_scalar($_POST['vitalis_member_status'])) { update_user_meta($user_id,'vitalis_member_status',sanitize_text_field(wp_unslash($_POST['vitalis_member_status']))); }
    if (isset($_POST['vitalis_member_plan']) && is_scalar($_POST['vitalis_member_plan'])) { update_user_meta($user_id,'vitalis_member_plan',sanitize_text_field(wp_unslash($_POST['vitalis_member_plan']))); }
    if (current_user_can('manage_options') && isset($_POST['vitalis_assign_coach_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vitalis_assign_coach_nonce'])),'vitalis_assign_coach_'.$user_id)) {
        $coach_id = absint(vitalis_core_request_value($_POST,'vitalis_assigned_coach_id',0));
        $coach = $coach_id ? get_userdata($coach_id) : false;
        if ($coach_id && $coach && in_array('vitalis_coach',(array)$coach->roles,true)) { update_user_meta($user_id,'vitalis_assigned_coach_id',$coach_id); }
        else { delete_user_meta($user_id,'vitalis_assigned_coach_id'); }
    }
}
add_action('personal_options_update','vitalis_core_save_profile_fields');
add_action('edit_user_profile_update','vitalis_core_save_profile_fields');

function vitalis_core_front_assets() {
    $max_booking_month=(new DateTimeImmutable(wp_date('Y-01-01'),wp_timezone()))->modify('+3 years')->modify('-1 day')->format('Y-m');
    wp_localize_script('vitalis-main','VitalisCore',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('vitalis_booking_slots'),'loading'=>'Saatler yükleniyor…','empty'=>'Bu gün için uygun saat bulunamadı.','daysLoading'=>'Uygunluk günleri yükleniyor…','daysEmpty'=>'Seçilen alan için açık saat bulunamadı.','today'=>wp_date('Y-m-d'),'maxBookingMonth'=>$max_booking_month,'monthNames'=>array('Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'),'weekdays'=>array('Pzt','Sal','Çar','Per','Cum','Cmt','Paz')));
}
add_action('wp_enqueue_scripts','vitalis_core_front_assets',20);

function vitalis_core_booking_day_summary($resource,$date) {
    $day_obj=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
    if (!$day_obj || !vitalis_core_booking_date_allowed($date)) { return array('date'=>$date,'slots'=>array(),'available'=>0,'total'=>0,'status'=>'closed','status_label'=>'Kapalı'); }
    $day=(int)$day_obj->format('N');
    $settings=vitalis_core_settings();
    $hours=$settings['hours'][$day] ?? array('open'=>'','close'=>'');
    $open=isset($hours['open'])?(int)substr($hours['open'],0,2):0;
    $close=isset($hours['close'])?(int)substr($hours['close'],0,2):0;
    $times=array();
    for ($hour=$open;$hour<$close;$hour++) {
        $time=sprintf('%02d:00',$hour);
        if ($date===wp_date('Y-m-d') && $time<=wp_date('H:00')) { continue; }
        $times[]=$time;
    }
    if (!$times || $date<wp_date('Y-m-d')) {
        return array('date'=>$date,'slots'=>array(),'available'=>0,'total'=>count($times),'status'=>'closed','status_label'=>$date===wp_date('Y-m-d')?'Bugün saat kalmadı':'Kapalı');
    }
    global $wpdb;
    $busy=$wpdb->get_col($wpdb->prepare('SELECT booking_time FROM '.vitalis_core_table().' WHERE resource=%s AND booking_date=%s AND status<>%s',$resource,$date,'cancelled'));
    $busy=array_fill_keys(array_map(function($time){return substr($time,0,5);},(array)$busy),true);
    $slots=array(); $available=0;
    foreach ($times as $time) {
        $is_available=!isset($busy[$time]);
        if ($is_available) { $available++; }
        $slots[]=array('value'=>$time,'label'=>$time,'available'=>$is_available);
    }
    $status=$available===0?'full':($available<count($times)?'partial':'available');
    $status_label=$status==='full'?'Dolu':($status==='partial'?'Kısmen dolu':'Müsait');
    return array('date'=>$date,'slots'=>$slots,'available'=>$available,'total'=>count($times),'status'=>$status,'status_label'=>$status_label);
}

function vitalis_core_booking_date_allowed($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { return false; }
    $parts=explode('-',$date);
    if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) || $date<wp_date('Y-m-d')) { return false; }
    $max=(new DateTimeImmutable(wp_date('Y-01-01'),wp_timezone()))->modify('+3 years')->modify('-1 day')->format('Y-m-d');
    return $date<=$max;
}

function vitalis_core_ajax_slots() {
    check_ajax_referer('vitalis_booking_slots','nonce');
    $resource = sanitize_title(vitalis_core_request_value($_REQUEST,'resource'));
    $date = sanitize_text_field(vitalis_core_request_value($_REQUEST,'date'));
    $resources = vitalis_core_resources();
    if (!isset($resources[$resource]) || !vitalis_core_booking_date_allowed($date)) { wp_send_json_error(array('message'=>'Alan veya tarih bilgisi geçersiz.'),400); }
    $parts = explode('-',$date);
    if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])) { wp_send_json_error(array('message'=>'Tarih geçersiz.'),400); }
    $booking_date = DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
    if (!$booking_date) { wp_send_json_error(array('message'=>'Tarih geçersiz.'),400); }
    $summary=vitalis_core_booking_day_summary($resource,$date);
    $message=$summary['available']?'Boş saatler seçilebilir; dolu saatler işaretlenmiştir.':($summary['status']==='closed'?$summary['status_label']:'Bu gün için boş saat kalmadı.');
    wp_send_json_success(array('slots'=>$summary['slots'],'message'=>$message));
}
add_action('wp_ajax_vitalis_booking_slots','vitalis_core_ajax_slots');
add_action('wp_ajax_nopriv_vitalis_booking_slots','vitalis_core_ajax_slots');

function vitalis_core_ajax_days() {
    check_ajax_referer('vitalis_booking_slots','nonce');
    $resource=sanitize_title(vitalis_core_request_value($_REQUEST,'resource'));
    $resources=vitalis_core_resources();
    if (!isset($resources[$resource])) { wp_send_json_error(array('message'=>'Alan bilgisi geçersiz.'),400); }
    $today=new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone());
    $days=array();
    for ($offset=0;$offset<14;$offset++) {
        $day=$today->modify('+'.$offset.' days');
        $date=$day->format('Y-m-d');
        $summary=vitalis_core_booking_day_summary($resource,$date);
        $summary['label']=wp_date('D d.m',$day->getTimestamp());
        $days[]=$summary;
    }
    wp_send_json_success(array('days'=>$days));
}
add_action('wp_ajax_vitalis_booking_days','vitalis_core_ajax_days');
add_action('wp_ajax_nopriv_vitalis_booking_days','vitalis_core_ajax_days');

function vitalis_core_ajax_calendar_month() {
    check_ajax_referer('vitalis_booking_slots','nonce');
    $resource=sanitize_title(vitalis_core_request_value($_REQUEST,'resource'));
    $month=sanitize_text_field(vitalis_core_request_value($_REQUEST,'month'));
    $resources=vitalis_core_resources();
    if (!isset($resources[$resource]) || !preg_match('/^\d{4}-\d{2}$/',$month)) { wp_send_json_error(array('message'=>'Alan veya ay bilgisi geçersiz.'),400); }
    $parts=explode('-',$month);
    if (!checkdate((int)$parts[1],1,(int)$parts[0])) { wp_send_json_error(array('message'=>'Ay bilgisi geçersiz.'),400); }
    $today=new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone());
    $first=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01',wp_timezone());
    $max=(new DateTimeImmutable(wp_date('Y-01-01'),wp_timezone()))->modify('+3 years')->modify('-1 day');
    if (!$first || $month < $today->format('Y-m') || $month > $max->format('Y-m')) { wp_send_json_error(array('message'=>'Seçilen ay takvim aralığının dışında.'),400); }
    $days=array();
    for ($number=1,$total=(int)$first->format('t');$number<=$total;$number++) {
        $day=$first->setDate((int)$first->format('Y'),(int)$first->format('m'),$number);
        $date=$day->format('Y-m-d');
        $summary=vitalis_core_booking_day_summary($resource,$date);
        $summary['day']=$number;
        $summary['weekday']=(int)$day->format('N');
        $summary['label']=wp_date('D d.m',$day->getTimestamp());
        $days[]=$summary;
    }
    wp_send_json_success(array('month'=>$month,'days'=>$days));
}
add_action('wp_ajax_vitalis_booking_calendar_month','vitalis_core_ajax_calendar_month');
add_action('wp_ajax_nopriv_vitalis_booking_calendar_month','vitalis_core_ajax_calendar_month');

function vitalis_core_slot_is_available($resource,$date,$time) {
    global $wpdb;
    $parts = explode('-',$date);
    if (count($parts)!==3 || !checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])) { return false; }
    $slots = array();
    $booking_date = DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
    if (!$booking_date) { return false; }
    $day = (int)$booking_date->format('N');
    $settings = vitalis_core_settings();
    $hours = $settings['hours'][$day] ?? array('open'=>'','close'=>'');
    $open = isset($hours['open']) ? (int)substr($hours['open'],0,2) : 0;
    $close = isset($hours['close']) ? (int)substr($hours['close'],0,2) : 0;
    for ($hour=$open; $hour<$close; $hour++) { $slots[] = sprintf('%02d:00',$hour); }
    if (!in_array($time,$slots,true) || !vitalis_core_booking_date_allowed($date)) { return false; }
    if ($date === wp_date('Y-m-d') && $time <= wp_date('H:00')) { return false; }
    global $wpdb;
    return !(bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.vitalis_core_table().' WHERE resource=%s AND booking_date=%s AND booking_time=%s AND status<>%s',$resource,$date,$time,'cancelled'));
}

function vitalis_core_booking_form() {
    $resources = vitalis_core_resources();
    echo '<section class="wrap content-section booking-system"><div class="section-heading"><div><span class="eyebrow">SAATLİK KİRALAMA</span><h2>Uygun gün ve saati seçin</h2><p>Takvimde boş görünen saati seçip rezervasyonunuzu hemen oluşturun. Dolu ve onay bekleyen saatler seçilemez.</p></div></div>';
    if (isset($_GET['rezervasyon'])) {
        $status = sanitize_key(vitalis_core_request_value($_GET,'rezervasyon'));
        echo '<p class="notice">'.($status==='alindi'?'Rezervasyonunuz oluşturuldu ve seçtiğiniz saat sizin için ayrıldı. Ödeme bu sitede alınmamıştır.':'Rezervasyon oluşturulamadı. Saat bu sırada dolmuş olabilir; takvimden başka bir saat seçin.').'</p>';
    }
    echo '<form class="inquiry-form vitalis-booking-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_booking_submit">';
    wp_nonce_field('vitalis_booking_submit','vitalis_booking_nonce');
    echo '<label class="full">Kiralanabilir alan<select name="resource" id="vitalis-resource" required><option value="">Alan seçin</option>';
    foreach ($resources as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; }
    echo '</select></label><section class="booking-availability full" aria-label="Saatlik kiralama takvimi"><div class="booking-calendar-top"><div><span class="eyebrow">AYLIK GÖRÜNÜM</span><div class="booking-calendar-period"><label class="calendar-sr-only" for="vitalis-booking-month">Ay seçin</label><select id="vitalis-booking-month" aria-label="Takvim ayı"></select><label class="calendar-sr-only" for="vitalis-booking-year">Yıl seçin</label><select id="vitalis-booking-year" aria-label="Takvim yılı"></select></div></div><div class="booking-calendar-nav"><button type="button" id="vitalis-booking-prev" aria-label="Önceki ay">←</button><button type="button" id="vitalis-booking-next" aria-label="Sonraki ay">→</button></div></div><div class="booking-calendar-legend"><span><i class="is-available"></i>Müsait</span><span><i class="is-partial"></i>Kısmen dolu</span><span><i class="is-full"></i>Dolu</span><span><i class="is-closed"></i>Kapalı</span></div><div class="booking-days" id="vitalis-booking-days" aria-live="polite" tabindex="-1">Önce kiralanabilir alanı seçin.</div></section><input type="hidden" name="booking_date" id="vitalis-booking-date"><div class="booking-selected-day full"><span>SEÇİLEN GÜN</span><strong id="vitalis-booking-selected-date">Takvimden bir gün seçin</strong></div><div class="slot-picker full"><strong>Seçilen günün saatleri</strong><div id="vitalis-booking-slots" aria-live="polite">Önce alan ve gün seçin.</div><input type="hidden" name="booking_time" id="vitalis-booking-time"></div>';
    $user = wp_get_current_user();
    echo '<label>Ad Soyad<input name="full_name" required autocomplete="name" value="'.($user->exists?esc_attr($user->display_name):'').'"></label><label>E-posta<input type="email" name="email" required autocomplete="email" value="'.($user->exists?esc_attr($user->user_email):'').'"></label><label>Telefon<input type="tel" name="phone" required autocomplete="tel"></label><label class="full">Notunuz<textarea name="note" rows="3" maxlength="1000" placeholder="Katılımcı sayısı veya ekipman ihtiyacı"></textarea></label><label class="full consent-line"><input type="checkbox" name="consent" value="1" required> Rezervasyon oluşturulması için iletişim bilgilerimin kullanılmasını kabul ediyorum.</label><button class="btn" type="submit">Rezervasyonu oluştur →</button><small>Boş saat seçildiğinde rezervasyon doğrudan oluşturulur ve saat ayrılır. Bu sitede çevrimiçi ödeme alınmaz; ücret ve ödeme kulüp tarafından ayrıca bildirilir.</small></form></section>';
}
function vitalis_core_booking_submit() {
    $referer = wp_get_referer() ?: home_url('/rezervasyon/');
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_booking_nonce')),'vitalis_booking_submit')) { wp_safe_redirect(add_query_arg('rezervasyon','hata',$referer)); exit; }
    $resource = sanitize_title(vitalis_core_request_value($_POST,'resource'));
    $date = sanitize_text_field(vitalis_core_request_value($_POST,'booking_date'));
    $time = sanitize_text_field(vitalis_core_request_value($_POST,'booking_time'));
    $name = sanitize_text_field(vitalis_core_request_value($_POST,'full_name'));
    $email = sanitize_email(vitalis_core_request_value($_POST,'email'));
    $phone = sanitize_text_field(vitalis_core_request_value($_POST,'phone'));
    $note = sanitize_textarea_field(vitalis_core_request_value($_POST,'note'));
    $resources = vitalis_core_resources();
    if (vitalis_core_request_value($_POST,'consent')!=='1' || !$name || !is_email($email) || !$phone || !isset($resources[$resource]) || !preg_match('/^(?:[01]\d|2[0-3]):00$/',$time) || !vitalis_core_slot_is_available($resource,$date,$time)) {
        wp_safe_redirect(add_query_arg('rezervasyon','hata',$referer)); exit;
    }
    global $wpdb;
    $table = vitalis_core_table();
    $cancelled = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE resource=%s AND booking_date=%s AND booking_time=%s AND status=%s",$resource,$date,$time,'cancelled'));
    $data = array('full_name'=>$name,'email'=>$email,'phone'=>$phone,'note'=>$note,'user_id'=>get_current_user_id(),'status'=>'approved','created_at'=>current_time('mysql'));
    if ($cancelled) {
        $saved = $wpdb->update($table,$data,array('id'=>(int)$cancelled->id,'status'=>'cancelled'),array('%s','%s','%s','%s','%d','%s','%s'),array('%d','%s'));
    } else {
        $data['resource']=$resource; $data['booking_date']=$date; $data['booking_time']=$time;
        $saved = $wpdb->insert($table,$data,array('%s','%s','%s','%s','%d','%s','%s','%s','%s','%s'));
    }
    if ($saved === false || $saved === 0) { wp_safe_redirect(add_query_arg('rezervasyon','hata',$referer)); exit; }
    $admin_email = get_option('admin_email');
    $message="Rezervasyonunuz oluşturuldu. Seçtiğiniz saat sizin için ayrılmıştır.\n\nAlan: {$resources[$resource]}\nTarih: $date\nSaat: $time\nNot: $note\n\nBu e-posta rezervasyon teyididir. Çevrimiçi ödeme alınmamıştır; ücret ve ödeme bilgisi için kulüp ekibiyle iletişime geçin.";
    wp_mail($email,'Vitalis rezervasyonunuz oluşturuldu',$message);
    wp_mail($admin_email,'Vitalis yeni rezervasyon: '.$resources[$resource],"Ad Soyad: $name\nE-posta: $email\nTelefon: $phone\nAlan: {$resources[$resource]}\nTarih: $date\nSaat: $time\nNot: $note\n\nRezervasyon doğrudan oluşturuldu; yönetim panelinden görüntülenebilir.",'Reply-To: '.$name.' <'.$email.'>');
    wp_safe_redirect(add_query_arg('rezervasyon','alindi',$referer)); exit;
}
add_action('admin_post_nopriv_vitalis_booking_submit','vitalis_core_booking_submit');
add_action('admin_post_vitalis_booking_submit','vitalis_core_booking_submit');

function vitalis_core_member_register() {
    if (is_user_logged_in()) { return '<div class="member-card"><p>Zaten oturum açtınız.</p><a class="btn" href="'.esc_url(home_url('/uye-paneli/')).'">Üye paneline git →</a></div>'; }
    ob_start();
    if (isset($_GET['uye_kayit']) && sanitize_key(vitalis_core_request_value($_GET,'uye_kayit'))==='hata') { echo '<p class="notice">Kayıt tamamlanamadı. Bilgileri kontrol edin; e-posta adresi daha önce kullanılmış olabilir.</p>'; }
    $selected_plan = sanitize_text_field(vitalis_core_request_value($_GET,'paket'));
    ?>
    <section class="wrap content-section member-page member-register-page"><div class="member-card member-register-card"><span class="eyebrow">VITALIS ÜYELİK</span><h2>Üye hesabı ve üyelik başvurusu</h2><p>Hesabınızla profilinizi ve kendi girdiğiniz antrenman ilerleme kayıtlarını görüntüleyebilirsiniz. Paket seçiminiz ön başvuru olarak kulübe iletilir; bu formda ödeme alınmaz ve üyelik kulüp teyidiyle kesinleşir.</p><form class="inquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vitalis_member_register"><?php wp_nonce_field('vitalis_member_register','vitalis_member_nonce'); ?><label>Ad Soyad<input name="full_name" required autocomplete="name"></label><label>E-posta<input type="email" name="email" required autocomplete="email"></label><label class="full">İlgilendiğiniz üyelik paketi<select name="plan"><option value="">Paket seçmeden hesap oluştur</option><?php foreach(array('Aylık Üyelik','6 Aylık Üyelik','Yıllık Üyelik') as $plan_option): ?><option value="<?php echo esc_attr($plan_option); ?>" <?php selected($selected_plan,$plan_option); ?>><?php echo esc_html($plan_option); ?></option><?php endforeach; ?></select></label><label class="full">Parola<input type="password" name="password" minlength="12" required autocomplete="new-password"><small>En az 12 karakter.</small></label><label class="full consent-line"><input type="checkbox" name="consent" value="1" required> Hesap oluşturmak için verdiğim bilgilerin işlenmesini kabul ediyorum. Kilo ve gelişim kayıtlarım yalnızca kendi hesabımda görüntülenir.</label><button class="btn" type="submit">Hesap oluştur ve başvur →</button></form><p class="member-register-login">Hesabınız var mı? <a href="<?php echo esc_url(home_url('/uye-girisi/')); ?>">Üye girişi</a></p></div></section>
    <?php return ob_get_clean();
}
add_shortcode('vitalis_member_register','vitalis_core_member_register');

function vitalis_core_register_submit() {
    $referer = wp_get_referer() ?: home_url('/uye-kaydi/');
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_member_nonce')),'vitalis_member_register')) { wp_safe_redirect(add_query_arg('uye_kayit','hata',$referer)); exit; }
    $name = sanitize_text_field(vitalis_core_request_value($_POST,'full_name'));
    $email = sanitize_email(vitalis_core_request_value($_POST,'email'));
    $password = (string)vitalis_core_request_value($_POST,'password');
    $plan = sanitize_text_field(vitalis_core_request_value($_POST,'plan'));
    if (!in_array($plan,array('','Aylık Üyelik','6 Aylık Üyelik','Yıllık Üyelik'),true)) { $plan=''; }
    if (vitalis_core_request_value($_POST,'consent')!=='1' || !$name || !is_email($email) || email_exists($email) || strlen($password)<12) { wp_safe_redirect(add_query_arg('uye_kayit','hata',$referer)); exit; }
    $base = sanitize_user(current(explode('@',$email)),true);
    if (!$base) { $base = 'vitalis-uye'; }
    $username = $base;
    for ($i=2; username_exists($username); $i++) { $username = $base.'-'.$i; }
    $user_id = wp_insert_user(array('user_login'=>$username,'user_pass'=>$password,'user_email'=>$email,'display_name'=>$name,'first_name'=>$name,'role'=>'subscriber'));
    if (is_wp_error($user_id)) { wp_safe_redirect(add_query_arg('uye_kayit','hata',$referer)); exit; }
    update_user_meta($user_id,'vitalis_member_status','Aday üye');
    if ($plan) { update_user_meta($user_id,'vitalis_member_requested_plan',$plan); }
    wp_mail(get_option('admin_email'),'Vitalis yeni üye / paket başvurusu',"Ad Soyad: $name\nE-posta: $email\nTalep edilen paket: ".($plan?:'Belirtilmedi')."\n\nHesap oluşturuldu. Bu bildirim ödeme veya onaylanmış üyelik anlamına gelmez.");
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id,true,is_ssl());
    wp_safe_redirect(add_query_arg('kayit','basarili',home_url('/uye-paneli/'))); exit;
}
add_action('admin_post_nopriv_vitalis_member_register','vitalis_core_register_submit');
add_action('admin_post_vitalis_member_register','vitalis_core_register_submit');

function vitalis_core_login_redirect($redirect_to,$requested_redirect_to,$user) {
    if ($user instanceof WP_User && in_array('vitalis_coach',(array)$user->roles,true)) { return home_url('/antrenor-paneli/'); }
    return $redirect_to;
}
add_filter('login_redirect','vitalis_core_login_redirect',10,3);

function vitalis_core_member_login() {
    if (is_user_logged_in()) { $destination=vitalis_core_is_coach()||current_user_can('manage_options')?home_url('/antrenor-paneli/'):home_url('/uye-paneli/'); $label=vitalis_core_is_coach()||current_user_can('manage_options')?'Antrenör paneline git →':'Üye paneline git →'; return '<div class="member-card"><p>Oturumunuz açık.</p><a class="btn" href="'.esc_url($destination).'">'.esc_html($label).'</a></div>'; }
    ob_start(); ?>
    <section class="wrap content-section member-page"><div class="member-card"><span class="eyebrow">ÜYE HESABI</span><h2>Üye girişi</h2>
    <?php if (isset($_GET['login']) && sanitize_key(vitalis_core_request_value($_GET,'login'))==='failed') { echo '<p class="notice">Giriş bilgileri doğrulanamadı. E-posta ve parolanızı kontrol edin.</p>'; }
    $login_redirect = home_url('/uye-paneli/');
    wp_login_form(array('echo'=>true,'redirect'=>$login_redirect,'remember'=>true,'form_id'=>'vitalis-loginform','label_username'=>'E-posta veya kullanıcı adı','label_password'=>'Parola','label_remember'=>'Beni hatırla','label_log_in'=>'Giriş yap'));
    ?><p><a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Parolamı unuttum</a> · <a href="<?php echo esc_url(home_url('/uye-kaydi/')); ?>">Yeni hesap oluştur</a></p></div></section>
    <?php return ob_get_clean();
}
add_shortcode('vitalis_member_login','vitalis_core_member_login');

function vitalis_core_save_progress() {
    if (!is_user_logged_in() || !wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_progress_nonce')),'vitalis_progress')) { wp_safe_redirect(home_url('/uye-paneli/')); exit; }
    $date = sanitize_text_field(vitalis_core_request_value($_POST,'progress_date'));
    $weight = filter_var(vitalis_core_request_value($_POST,'weight'),FILTER_VALIDATE_FLOAT);
    $sport = sanitize_text_field(vitalis_core_request_value($_POST,'sport'));
    $note = sanitize_textarea_field(vitalis_core_request_value($_POST,'progress_note'));
    $parts = explode('-',$date);
    if (count($parts)!==3 || !checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) || $date>wp_date('Y-m-d') || $weight===false || $weight<20 || $weight>350 || !$sport) { wp_safe_redirect(add_query_arg('ilerleme','hata',home_url('/uye-paneli/'))); exit; }
    $records = vitalis_core_progress_records(get_current_user_id());
    $records[] = array('date'=>$date,'weight'=>number_format((float)$weight,1,'.',''),'sport'=>$sport,'note'=>$note);
    usort($records,function($a,$b) { return strcmp($b['date'],$a['date']); });
    update_user_meta(get_current_user_id(),'vitalis_progress',array_slice($records,0,100));
    wp_safe_redirect(add_query_arg('ilerleme','kaydedildi',home_url('/uye-paneli/'))); exit;
}
add_action('admin_post_vitalis_progress_save','vitalis_core_save_progress');

/** Return only well-formed member progress records, including after legacy or imported data. */
function vitalis_core_progress_records($user_id) {
    $raw = get_user_meta((int)$user_id,'vitalis_progress',true);
    if (!is_array($raw)) { return array(); }
    if (isset($raw['date']) || isset($raw['weight'])) { $raw = array($raw); }
    $records = array();
    foreach ($raw as $record) {
        if (!is_array($record)) { continue; }
        $date = isset($record['date']) && is_scalar($record['date']) ? trim((string)$record['date']) : '';
        $weight = isset($record['weight']) && is_scalar($record['weight']) ? trim((string)$record['weight']) : '';
        if (strpos($weight,',') !== false && strpos($weight,'.') === false) { $weight = str_replace(',','.',$weight); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !is_numeric($weight)) { continue; }
        $parts = explode('-',$date);
        if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])) { continue; }
        $weight = (float)$weight;
        if (!is_finite($weight) || $weight < 20 || $weight > 350) { continue; }
        $sport = isset($record['sport']) && is_scalar($record['sport']) ? sanitize_text_field((string)$record['sport']) : '';
        $note = isset($record['note']) && is_scalar($record['note']) ? sanitize_textarea_field((string)$record['note']) : '';
        $records[] = array(
            'date'=>$date,
            'weight'=>number_format($weight,1,'.',''),
            'sport'=>$sport !== '' ? $sport : 'Aktivite belirtilmedi',
            'note'=>$note
        );
    }
    usort($records,function($a,$b) { return strcmp($b['date'],$a['date']); });
    return $records;
}

function vitalis_core_is_coach($user_id=0) {
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    $user = $user_id ? get_userdata($user_id) : false;
    return $user && in_array('vitalis_coach',(array)$user->roles,true);
}

function vitalis_core_trainer_progress_records($student_id) {
    $raw = get_user_meta((int)$student_id,'vitalis_trainer_progress',true);
    if (!is_array($raw)) { return array(); }
    $records = array();
    foreach ($raw as $record) {
        if (!is_array($record)) { continue; }
        $date = isset($record['date']) && is_scalar($record['date']) ? trim((string)$record['date']) : '';
        $note = isset($record['note']) && is_scalar($record['note']) ? sanitize_textarea_field((string)$record['note']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !$note) { continue; }
        $parts = explode('-',$date);
        if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0])) { continue; }
        $weight = isset($record['weight']) && is_scalar($record['weight']) ? trim((string)$record['weight']) : '';
        if ($weight !== '' && (!is_numeric($weight) || (float)$weight < 20 || (float)$weight > 350 || !is_finite((float)$weight))) { $weight = ''; }
        $coach_id = isset($record['coach_id']) ? absint($record['coach_id']) : 0;
        $coach = $coach_id ? get_userdata($coach_id) : false;
        $records[] = array(
            'date'=>$date,
            'focus'=>isset($record['focus']) && is_scalar($record['focus']) ? sanitize_text_field((string)$record['focus']) : '',
            'weight'=>$weight !== '' ? number_format((float)$weight,1,'.','') : '',
            'note'=>$note,
            'coach_id'=>$coach_id,
            'coach_name'=>$coach ? sanitize_text_field($coach->display_name) : 'Vitalis antrenör ekibi',
            'created_at'=>isset($record['created_at']) && is_scalar($record['created_at']) ? sanitize_text_field((string)$record['created_at']) : ''
        );
    }
    usort($records,function($a,$b) { $by_date=strcmp($b['date'],$a['date']); return $by_date!==0?$by_date:strcmp($b['created_at'],$a['created_at']); });
    return $records;
}

function vitalis_core_coach_students($coach_id) {
    if (current_user_can('manage_options')) {
        return get_users(array('role'=>'subscriber','number'=>-1,'orderby'=>'display_name','order'=>'ASC'));
    }
    if (!vitalis_core_is_coach($coach_id)) { return array(); }
    return get_users(array('role'=>'subscriber','number'=>-1,'meta_key'=>'vitalis_assigned_coach_id','meta_value'=>(string)absint($coach_id),'orderby'=>'display_name','order'=>'ASC'));
}

function vitalis_core_can_write_student_progress($student,$coach_id) {
    if (!$student || !isset($student->ID)) { return false; }
    $is_member = in_array('subscriber',(array)$student->roles,true) || (bool)get_user_meta($student->ID,'vitalis_member_status',true);
    if (!$is_member) { return false; }
    if (current_user_can('manage_options')) { return true; }
    return vitalis_core_is_coach($coach_id) && absint(get_user_meta($student->ID,'vitalis_assigned_coach_id',true)) === absint($coach_id);
}

function vitalis_core_coach_progress_save() {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/uye-girisi/')); exit; }
    $coach_id = get_current_user_id();
    if (!current_user_can('manage_options') && !vitalis_core_is_coach($coach_id)) { wp_die('Bu işlem için yetkiniz yok.'); }
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_coach_progress_nonce')),'vitalis_coach_progress')) { wp_die('Güvenlik doğrulaması başarısız.'); }
    $student_id = absint(vitalis_core_request_value($_POST,'student_id',0));
    $student = $student_id ? get_userdata($student_id) : false;
    $date = sanitize_text_field(vitalis_core_request_value($_POST,'progress_date'));
    $focus = sanitize_text_field(vitalis_core_request_value($_POST,'progress_focus'));
    $weight_raw = trim((string)vitalis_core_request_value($_POST,'progress_weight'));
    $note = sanitize_textarea_field(vitalis_core_request_value($_POST,'progress_note'));
    $parts = explode('-',$date);
    $valid_date = count($parts)===3 && checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) && $date<=wp_date('Y-m-d');
    $valid_weight = $weight_raw==='' || (is_numeric($weight_raw) && (float)$weight_raw>=20 && (float)$weight_raw<=350 && is_finite((float)$weight_raw));
    $redirect = home_url('/antrenor-paneli/');
    if ($student_id) { $redirect = add_query_arg('ogrenci',$student_id,$redirect); }
    if (!vitalis_core_can_write_student_progress($student,$coach_id) || !$valid_date || !$valid_weight || !$note || strlen($note)>5000 || strlen($focus)>400) {
        wp_safe_redirect(add_query_arg('antrenor_kayit','hata',$redirect)); exit;
    }
    $records = vitalis_core_trainer_progress_records($student_id);
    $records[] = array(
        'date'=>$date,
        'focus'=>$focus,
        'weight'=>$weight_raw!==''?number_format((float)$weight_raw,1,'.',''):'',
        'note'=>$note,
        'coach_id'=>$coach_id,
        'created_at'=>current_time('mysql')
    );
    usort($records,function($a,$b) { return strcmp((string)($b['date']??''),(string)($a['date']??'')); });
    update_user_meta($student_id,'vitalis_trainer_progress',array_slice($records,0,100));
    wp_safe_redirect(add_query_arg('antrenor_kayit','kaydedildi',$redirect)); exit;
}
add_action('admin_post_vitalis_coach_progress_save','vitalis_core_coach_progress_save');

function vitalis_core_render_coach_notes($records) {
    if (!$records) { return '<p>Henüz antrenör değerlendirmesi eklenmedi.</p>'; }
    $html = '<div class="coach-progress-list">';
    foreach ($records as $record) {
        $html .= '<article class="coach-progress-note"><div class="coach-progress-meta"><strong>'.esc_html(mysql2date('d.m.Y',$record['date'])).'</strong><span>'.esc_html($record['coach_name']).'</span></div>';
        if ($record['focus']!=='') { $html .= '<h3>'.esc_html($record['focus']).'</h3>'; }
        $html .= '<p>'.nl2br(esc_html($record['note'])).'</p>';
        if ($record['weight']!=='') { $html .= '<small>Kaydedilen kilo: '.esc_html($record['weight']).' kg</small>'; }
        $html .= '</article>';
    }
    return $html.'</div>';
}

function vitalis_core_coach_dashboard() {
    if (!is_user_logged_in()) { return '<section class="wrap content-section member-page"><div class="member-card"><h2>Antrenör paneli</h2><p>Öğrenci gelişim kayıtları için antrenör hesabınızla giriş yapın.</p><a class="btn" href="'.esc_url(home_url('/uye-girisi/')).'">Antrenör girişi →</a></div></section>'; }
    $coach_id = get_current_user_id();
    if (!current_user_can('manage_options') && !vitalis_core_is_coach($coach_id)) { return '<section class="wrap content-section member-page"><div class="member-card"><h2>Antrenör paneli</h2><p>Bu alan yalnızca antrenör hesaplarına açıktır.</p><a href="'.esc_url(home_url('/uye-paneli/')).'">Üye paneline dön →</a></div></section>'; }
    $students = vitalis_core_coach_students($coach_id);
    $selected_id = absint(vitalis_core_request_value($_GET,'ogrenci',0));
    $selected_student = false;
    foreach ($students as $student_option) { if ((int)$student_option->ID===$selected_id) { $selected_student=$student_option; break; } }
    ob_start(); ?>
    <section class="wrap content-section member-page coach-dashboard-page">
        <div class="member-welcome"><div><span class="eyebrow">VITALIS ANTRENÖR ALANI</span><h1>Merhaba, <?php echo esc_html(wp_get_current_user()->display_name); ?></h1><p>Atanmış öğrencilerinize özel gelişim değerlendirmeleri ekleyin.</p></div><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Güvenli çıkış →</a></div>
        <?php if (isset($_GET['antrenor_kayit'])) { echo '<p class="notice">'.(sanitize_key(vitalis_core_request_value($_GET,'antrenor_kayit'))==='kaydedildi'?'Değerlendirme ilgili öğrencinin gelişim alanına eklendi.':'Kayıt tamamlanamadı. Öğrenciyi ve tarih / kilo bilgilerini kontrol edin.').'</p>'; } ?>
        <?php if (isset($_GET['antrenor_randevu'])) { echo '<p class="notice">'.(sanitize_key(vitalis_core_request_value($_GET,'antrenor_randevu'))==='guncellendi'?'Randevu kararı kaydedildi.':'Randevu kararı kaydedilemedi; talep durumu değişmiş veya yetkiniz olmayabilir.').'</p>'; } ?>
        <?php if (function_exists('vitalis_trainer_render_coach_dashboard')) { vitalis_trainer_render_coach_dashboard($coach_id); } ?>
        <?php if (!$students) { ?>
            <div class="member-card"><h2>Henüz öğrenci atanmamış</h2><p>Site yöneticisi, WordPress yönetim panelinde öğrencinin kullanıcı profilinden size antrenör ataması yapmalıdır. Atama sonrasında öğrenciniz burada görünür.</p></div>
        <?php } else { ?>
            <div class="member-card coach-student-picker-card"><h2>Öğrencinizi seçin</h2><p>Her değerlendirme yalnızca seçtiğiniz öğrencinin hesabına kaydedilir.</p><form class="inquiry-form coach-student-picker" method="get" action="<?php echo esc_url(home_url('/antrenor-paneli/')); ?>"><label>Öğrenci<select name="ogrenci" required><option value="">Öğrenci seçin</option><?php foreach ($students as $student_option) { ?><option value="<?php echo absint($student_option->ID); ?>" <?php selected($selected_id,$student_option->ID); ?>><?php echo esc_html($student_option->display_name.' — Üye #'.$student_option->ID); ?></option><?php } ?></select></label><button class="btn" type="submit">Öğrenciyi aç →</button></form></div>
            <?php if ($selected_student) {
                $student_notes = vitalis_core_trainer_progress_records($selected_student->ID);
                if (!current_user_can('manage_options')) { $student_notes=array_values(array_filter($student_notes,function($record) use ($coach_id) { return (int)$record['coach_id']===(int)$coach_id; })); }
                ?>
                <div class="member-card coach-student-card"><span class="eyebrow">SEÇİLİ ÖĞRENCİ</span><h2><?php echo esc_html($selected_student->display_name); ?></h2><p>Üyeye özel kayıt. Eklediğiniz not, öğrencinin Üye Paneli içindeki gelişim bölümünde görünür.</p><form class="inquiry-form coach-progress-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vitalis_coach_progress_save"><input type="hidden" name="student_id" value="<?php echo absint($selected_student->ID); ?>"><?php wp_nonce_field('vitalis_coach_progress','vitalis_coach_progress_nonce'); ?><label>Tarih<input type="date" name="progress_date" max="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required></label><label>Ölçüm kilosu (isteğe bağlı)<input type="number" name="progress_weight" min="20" max="350" step="0.1"></label><label class="full">Antrenman / gelişim odağı<input name="progress_focus" maxlength="100" placeholder="Örn. kuvvet çalışması, yüzme tekniği"></label><label class="full">Değerlendirme notu<textarea name="progress_note" rows="4" maxlength="1000" required placeholder="Öğrencinin antrenmandaki ilerlemesi ve sonraki çalışma odağı"></textarea></label><small class="full">Bu not öğrenci hesabında görünür. Sağlık tanısı veya hassas sağlık bilgisi girmeyin.</small><button class="btn" type="submit">Öğrenci gelişimini kaydet →</button></form></div>
                <div class="member-card coach-student-history"><h2>Bu öğrenciye ait önceki notlarınız</h2><?php echo vitalis_core_render_coach_notes($student_notes); ?></div>
            <?php } else { ?>
                <div class="member-card"><h2>Öğrenci seçilmedi</h2><p>Değerlendirme eklemek için yukarıdaki listeden bir öğrenci seçin.</p></div>
            <?php } ?>
        <?php } ?>
    </section>
    <?php return ob_get_clean();
}
add_shortcode('vitalis_coach_dashboard','vitalis_core_coach_dashboard');

function vitalis_core_member_profile_save() {
    if (!is_user_logged_in() || !wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_profile_nonce')),'vitalis_member_profile')) { wp_safe_redirect(home_url('/uye-paneli/')); exit; }
    update_user_meta(get_current_user_id(),'vitalis_current_goal',sanitize_textarea_field(vitalis_core_request_value($_POST,'current_goal')));
    update_user_meta(get_current_user_id(),'vitalis_primary_sport',sanitize_text_field(vitalis_core_request_value($_POST,'primary_sport')));
    wp_safe_redirect(add_query_arg('profil','kaydedildi',home_url('/uye-paneli/'))); exit;
}
add_action('admin_post_vitalis_member_profile_save','vitalis_core_member_profile_save');

function vitalis_core_clear_progress() {
    if (!is_user_logged_in() || !wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_progress_nonce')),'vitalis_progress')) { wp_safe_redirect(home_url('/uye-paneli/')); exit; }
    delete_user_meta(get_current_user_id(),'vitalis_progress');
    wp_safe_redirect(add_query_arg('ilerleme','silindi',home_url('/uye-paneli/'))); exit;
}
add_action('admin_post_vitalis_progress_clear','vitalis_core_clear_progress');

function vitalis_core_member_event_signups($user_id) {
    global $wpdb;
    $table=vitalis_core_event_signups_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) { return array(); }
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT 100",absint($user_id)));
}

function vitalis_core_member_dashboard() {
    if (!is_user_logged_in()) { return '<section class="wrap content-section member-page"><div class="member-card"><h2>Üye paneli</h2><p>İlerlemenizi görüntülemek için giriş yapın.</p><a class="btn" href="'.esc_url(home_url('/uye-girisi/')).'">Üye girişi →</a> <a href="'.esc_url(home_url('/uye-kaydi/')).'">Hesap oluştur</a></div></section>'; }
    $user = wp_get_current_user();
    $records = vitalis_core_progress_records($user->ID);
    $coach_records = vitalis_core_trainer_progress_records($user->ID);
    $event_signups = vitalis_core_member_event_signups($user->ID);
    $latest_weight = $records ? (float)$records[0]['weight'] : null;
    $weight_change = count($records)>1 ? $latest_weight-(float)$records[count($records)-1]['weight'] : null;
    $status = get_user_meta($user->ID,'vitalis_member_status',true) ?: 'Aday üye';
    $plan = get_user_meta($user->ID,'vitalis_member_plan',true) ?: (get_user_meta($user->ID,'vitalis_member_requested_plan',true) ? 'Başvuru: '.get_user_meta($user->ID,'vitalis_member_requested_plan',true) : 'Henüz bir paket tanımlanmadı');
    ob_start(); ?>
    <section class="wrap content-section member-page member-dashboard-page"><div class="member-welcome"><div><span class="eyebrow">KİŞİSEL ALANINIZ</span><h1>Merhaba, <?php echo esc_html($user->display_name); ?></h1><p>Üyelik durumunuz, gelişim notlarınız ve etkinlik kayıtlarınız.</p></div><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Güvenli çıkış →</a></div>
    <?php if (isset($_GET['kayit'])) { echo '<p class="notice">Hesabınız oluşturuldu. Üyelik durumunuz kulüp ekibi tarafından güncellenecektir.</p>'; }
    if (isset($_GET['ilerleme'])) { echo '<p class="notice">'.(sanitize_key(vitalis_core_request_value($_GET,'ilerleme'))==='kaydedildi'?'Gelişim kaydınız eklendi.':(sanitize_key(vitalis_core_request_value($_GET,'ilerleme'))==='silindi'?'Gelişim geçmişiniz silindi.':'Kayıt bilgilerini kontrol edip tekrar deneyin.')).'</p>'; }
    if (isset($_GET['profil']) && sanitize_key(vitalis_core_request_value($_GET,'profil'))==='kaydedildi') { echo '<p class="notice">Güncel hedefiniz kaydedildi.</p>'; }
    if (isset($_GET['etkinlik_kayit'])) { $event_notice=sanitize_key(vitalis_core_request_value($_GET,'etkinlik_kayit')); $event_messages=array('guncellendi'=>'Etkinlik katılımcı sayınız güncellendi.','iptal'=>'Etkinlik kaydınız iptal edildi ve yerler kontenjana eklendi.','kontenjan'=>'İstenen kişi sayısı için yeterli boş yer kalmadı.','kapali'=>'Bu etkinliğin düzenleme süresi sona erdi.','hata'=>'İşlem tamamlanamadı. Kaydınızı ve bilgilerinizi kontrol edip tekrar deneyin.'); if (isset($event_messages[$event_notice])) { echo '<p class="notice">'.esc_html($event_messages[$event_notice]).'</p>'; } }
    ?>
    <div class="member-stats"><article><span>Üyelik durumu</span><strong><?php echo esc_html($status); ?></strong></article><article><span>Üyelik paketi</span><strong><?php echo esc_html($plan); ?></strong></article><article><span>Son kayıtlı kilo</span><strong><?php echo $latest_weight!==null?esc_html(number_format_i18n($latest_weight,1).' kg'):'Henüz yok'; ?></strong></article><article><span>İlk kayda göre değişim</span><strong><?php echo $weight_change!==null?esc_html(sprintf('%+.1f kg',$weight_change)):'İki kayıt ekleyin'; ?></strong></article><article><span>Gelişim kayıtları</span><strong><?php echo count($records)+count($coach_records); ?></strong></article></div>
    <section class="member-card member-event-signups"><span class="eyebrow">KULÜP ETKİNLİKLERİ</span><h2>Etkinlik kayıtlarım</h2><p>Kesinleşen kayıtlarınızı görün; etkinlik başlamadan önce kişi sayısını güncelleyin, kaydınızı istediğiniz zaman iptal edin.</p>
    <?php if (!$event_signups) { echo '<p>Henüz etkinlik kaydınız yok. <a href="'.esc_url(home_url('/etkinlikler/')).'">Etkinlikleri inceleyin →</a></p>'; } else { foreach ($event_signups as $signup) { $event=get_post((int)$signup->event_id); $signup_status=$signup->status==='approved'?'Kesin kayıt':($signup->status==='pending'?'İnceleme bekliyor':($signup->status==='cancelled'?'İptal edildi':'Reddedildi')); if (strpos((string)$signup->note,'DEMO KAYDI')!==false) { $signup_status='DEMO · '.$signup_status; } $active=in_array($signup->status,array('pending','approved'),true); $can_edit=$active && $event && vitalis_core_event_is_open($event->ID); $event_date=$event?get_post_meta($event->ID,'vitalis_event_date',true):''; $event_time=$event?get_post_meta($event->ID,'vitalis_event_time',true):''; $event_venue=$event?get_post_meta($event->ID,'vitalis_event_venue',true):''; $availability=$event?vitalis_core_event_capacity_snapshot($event->ID):array('available'=>0); $attendees=(int)$signup->attendee_count; $max_attendees=max($attendees,$attendees+(int)$availability['available']); ?>
        <article class="member-event-registration"><div class="member-event-registration-info"><div class="member-event-registration-heading"><h3><?php if ($event && $event->post_status==='publish') { ?><a href="<?php echo esc_url(get_permalink($event)); ?>"><?php echo esc_html($event->post_title); ?></a><?php } else { echo esc_html($event?$event->post_title:'Etkinlik kaldırıldı'); } ?></h3><span class="member-event-status <?php echo $active?'is-active':'is-inactive'; ?>"><?php echo esc_html($signup_status); ?></span></div><p><?php echo esc_html($event_date?wp_date('j F Y',strtotime($event_date)):'Tarih bilgisi yok'); ?><?php if ($event_time) { echo ' · '.esc_html($event_time); } ?><?php if ($event_venue) { echo ' · '.esc_html($event_venue); } ?></p><strong><?php echo number_format_i18n($attendees); ?> katılımcı</strong></div>
        <?php if ($active) { ?><div class="member-event-registration-actions"><?php if ($can_edit) { ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vitalis_event_signup_manage"><input type="hidden" name="signup_id" value="<?php echo absint($signup->id); ?>"><input type="hidden" name="operation" value="update"><?php wp_nonce_field('vitalis_event_manage_'.$signup->id,'vitalis_event_manage_nonce'); ?><label>Katılımcı sayısı<input type="number" name="attendee_count" min="1" max="<?php echo esc_attr($max_attendees); ?>" value="<?php echo esc_attr($attendees); ?>" required></label><button class="btn" type="submit">Kişi sayısını güncelle</button></form><?php } else { ?><p class="member-event-edit-closed">Etkinlik başladı veya sona erdiği için kişi sayısı değiştirilemiyor.</p><?php } ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Bu etkinlik kaydını iptal etmek istiyor musunuz? Ayrılan yerler yeniden açılacak.')"><input type="hidden" name="action" value="vitalis_event_signup_manage"><input type="hidden" name="signup_id" value="<?php echo absint($signup->id); ?>"><input type="hidden" name="operation" value="cancel"><?php wp_nonce_field('vitalis_event_manage_'.$signup->id,'vitalis_event_manage_nonce'); ?><button class="text-button" type="submit">Kaydı iptal et</button></form></div><?php } ?></article>
    <?php } } ?></section>
    <div class="member-card member-profile-card"><h2>Güncel hedef ve spor odağı</h2><p>Hedefinizi ve şu sıralar ağırlık verdiğiniz aktiviteyi buradan güncelleyin.</p><form class="inquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vitalis_member_profile_save"><?php wp_nonce_field('vitalis_member_profile','vitalis_profile_nonce'); ?><label>Şu anki hedefim<input name="current_goal" maxlength="200" value="<?php echo esc_attr(get_user_meta($user->ID,'vitalis_current_goal',true)); ?>" placeholder="Örn. kondisyonumu artırmak"></label><label>Ağırlık verdiğim spor<input name="primary_sport" maxlength="100" value="<?php echo esc_attr(get_user_meta($user->ID,'vitalis_primary_sport',true)); ?>" placeholder="Örn. yüzme"></label><button class="btn" type="submit">Profili güncelle →</button></form></div>
    <div class="member-columns"><div class="member-card"><h2>Gelişim kaydı ekle</h2><p>Kilo, yaptığınız aktivite ve isteğe bağlı kısa not ekleyin. Sağlık tanısı veya hassas sağlık bilgisi girmeyin.</p><form class="inquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vitalis_progress_save"><?php wp_nonce_field('vitalis_progress','vitalis_progress_nonce'); ?><label>Tarih<input type="date" name="progress_date" max="<?php echo esc_attr(wp_date('Y-m-d')); ?>" value="<?php echo esc_attr(wp_date('Y-m-d')); ?>" required></label><label>Kilo (kg)<input type="number" name="weight" min="20" max="350" step="0.1" required></label><label class="full">Yaptığınız spor / aktivite<input name="sport" maxlength="100" placeholder="Örn. yüzme, kuvvet antrenmanı" required></label><label class="full">Gelişim notu<textarea name="progress_note" rows="3" maxlength="500" placeholder="İsteğe bağlı kısa not"></textarea></label><button class="btn" type="submit">Kaydı ekle →</button></form></div>
    <div class="member-card"><h2>Kilo ve aktivite geçmişi</h2><?php if (!$records) { echo '<p>Henüz kendi gelişim kaydınızı eklemediniz.</p>'; } else { echo '<div class="progress-table-wrap"><table class="progress-table"><thead><tr><th>Tarih</th><th>Kilo</th><th>Aktivite</th><th>Not</th></tr></thead><tbody>'; foreach ($records as $record) { echo '<tr><td>'.esc_html(mysql2date('d.m.Y',$record['date'])).'</td><td>'.esc_html($record['weight']).' kg</td><td>'.esc_html($record['sport']).'</td><td>'.esc_html($record['note'] ?? '').'</td></tr>'; } echo '</tbody></table></div>'; } ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Kendi gelişim kayıtlarınızı silmek istiyor musunuz? Antrenör notlarınız bu işlemden etkilenmez.')"><input type="hidden" name="action" value="vitalis_progress_clear"><?php wp_nonce_field('vitalis_progress','vitalis_progress_nonce'); ?><button class="text-button" type="submit">Kendi kayıtlarımı sil</button></form><section class="member-coach-notes"><h3>Antrenörümden gelişim değerlendirmeleri</h3><p>Antrenörünüzün hesabınıza özel eklediği notlar burada görünür.</p><?php echo vitalis_core_render_coach_notes($coach_records); ?></section></div></div>
    <p class="form-note">Bu panel çevrimiçi ödeme veya otomatik üyelik başlatmaz. Üyelik durumu ve paket bilgisi kulüp yöneticisince güncellenir. Gelişim kayıtlarınız ve etkinlik kayıtlarınız yalnızca kendi üye hesabınızda görünür.</p></section>
    <?php return ob_get_clean();
}
add_shortcode('vitalis_member_dashboard','vitalis_core_member_dashboard');

function vitalis_core_render_trainers() {
    $posts = get_posts(array('post_type'=>'vitalis_trainer','post_status'=>'publish','numberposts'=>-1,'orderby'=>'menu_order title','order'=>'ASC'));
    echo '<section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">UZMAN DESTEĞİ</span><h2>Hedefinize eşlik eden antrenör kadrosu</h2><p>Her antrenörün eğitim ve belge bilgilerini, bireysel ve grup derslerini, ücretlerini ve kendine özel randevu takvimini görüntüleyin.</p></div></div><div class="event-notice"><strong>Tanıtım profilleri ve örnek ücretler</strong><p>Bu kayıtlar kurulum içeriğidir; gerçek personel adı, fotoğrafı, uzmanlığı, eğitimi, uygunluğu ve ücretleri işletme tarafından doğrulanıp güncellenmelidir.</p></div><div class="trainer-grid trainer-profiles">';
    foreach ($posts as $post) {
        $img = get_the_post_thumbnail_url($post,'large');
        if (!$img) { $local = get_post_meta($post->ID,'vitalis_local_image',true); $img = $local ? vitalis_img($local) : vitalis_img('coach-strength.jpg'); }
        $specialties = get_post_meta($post->ID,'vitalis_specialties',true); $session = get_post_meta($post->ID,'vitalis_session',true); $fees = get_post_meta($post->ID,'vitalis_fees',true);
        $url = get_permalink($post);
        echo '<article><img src="'.esc_url($img).'" alt="'.esc_attr($post->post_title).'" loading="lazy"><div class="coach-copy"><span class="coach-tag">VITALIS ANTRENÖR EKİBİ</span><h3>'.esc_html($post->post_title).'</h3><div class="coach-bio">'.wp_kses_post(wpautop($post->post_content)).'</div><dl><div><dt>Uzmanlık</dt><dd>'.esc_html($specialties).'</dd></div><div><dt>Seans biçimi</dt><dd>'.esc_html($session).'</dd></div><div><dt>Ücret ve paket</dt><dd>'.esc_html($fees).'</dd></div></dl><a href="'.esc_url($url).'">Profili ve takvimi gör →</a></div></article>';
    }
    echo '</div></section>';
}

function vitalis_core_event_capacity_from_label($label) {
    if (!preg_match('/(\d+)/u',(string)$label,$matches)) { return 1; }
    $capacity=max(1,(int)$matches[1]);
    if (preg_match('/çift|aile/iu',(string)$label)) { $capacity*=2; }
    return min(99999,$capacity);
}

function vitalis_core_event_sync_capacity($event_id) {
    $event_id=absint($event_id);
    if (!$event_id || get_post_type($event_id)!=='vitalis_event') { return false; }
    $max_capacity=absint(get_post_meta($event_id,'vitalis_event_max_capacity',true));
    if (!$max_capacity) {
        $max_capacity=vitalis_core_event_capacity_from_label(get_post_meta($event_id,'vitalis_event_capacity',true));
        update_post_meta($event_id,'vitalis_event_max_capacity',$max_capacity);
    }
    $max_capacity=max(1,min(99999,$max_capacity));
    global $wpdb;
    $table=vitalis_core_event_capacity_table();
    $sql=$wpdb->prepare("INSERT INTO $table (event_id,max_capacity,reserved_count,updated_at) VALUES (%d,%d,0,%s) ON DUPLICATE KEY UPDATE max_capacity=VALUES(max_capacity),updated_at=VALUES(updated_at)",$event_id,$max_capacity,current_time('mysql'));
    return $wpdb->query($sql)!==false;
}

function vitalis_core_event_capacity_snapshot($event_id) {
    vitalis_core_event_sync_capacity($event_id);
    global $wpdb;
    $table=vitalis_core_event_capacity_table();
    $row=$wpdb->get_row($wpdb->prepare("SELECT max_capacity,reserved_count FROM $table WHERE event_id=%d",absint($event_id)));
    if (!$row) { return array('capacity'=>0,'reserved'=>0,'available'=>0); }
    $capacity=(int)$row->max_capacity; $reserved=(int)$row->reserved_count;
    return array('capacity'=>$capacity,'reserved'=>$reserved,'available'=>max(0,$capacity-$reserved));
}

function vitalis_core_migrate_event_capacities() {
    if (get_option('vitalis_event_capacity_migration_v1')) { return; }
    $events=get_posts(array('post_type'=>'vitalis_event','post_status'=>'any','numberposts'=>-1,'fields'=>'ids'));
    foreach ($events as $event_id) { vitalis_core_event_sync_capacity($event_id); }
    update_option('vitalis_event_capacity_migration_v1',1,false);
}

function vitalis_core_seed_demo_event_signup() {
    if (get_option('vitalis_demo_event_signup_seeded_v1')) { return; }
    $users=get_users(array('meta_key'=>'vitalis_demo_account','meta_value'=>'student','number'=>1));
    if (!$users) { return; }
    $events=get_posts(array('post_type'=>'vitalis_event','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'vitalis_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>array(array('key'=>'vitalis_event_date','value'=>wp_date('Y-m-d'),'compare'=>'>=','type'=>'DATE'))));
    if (!$events) { return; }
    global $wpdb;
    $table=vitalis_core_event_signups_table();
    foreach ($events as $event) {
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_id=%d AND user_id=%d AND note LIKE %s LIMIT 1",$event->ID,$users[0]->ID,'DEMO KAYDI — örnek etkinlik kaydı%'));
        if ($existing) { update_option('vitalis_demo_event_signup_seeded_v1',1,false); return; }
        if (!vitalis_core_event_is_open($event->ID)) { continue; }
        $snapshot=vitalis_core_event_capacity_snapshot($event->ID);
        $attendees=min(2,(int)$snapshot['available']);
        if ($attendees<1) { continue; }
        $capacity_table=vitalis_core_event_capacity_table();
        $reserved=$wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=reserved_count+%d,updated_at=%s WHERE event_id=%d AND reserved_count+%d<=max_capacity",$attendees,current_time('mysql'),$event->ID,$attendees));
        if ($reserved!==1) { continue; }
        $saved=$wpdb->insert($table,array('event_id'=>$event->ID,'full_name'=>$users[0]->display_name,'email'=>$users[0]->user_email,'phone'=>'0 (000) 000 00 00','attendee_count'=>$attendees,'note'=>'DEMO KAYDI — örnek etkinlik kaydı; gerçek başvuru değildir.','user_id'=>$users[0]->ID,'status'=>'approved','created_at'=>current_time('mysql')),array('%d','%s','%s','%s','%d','%s','%d','%s','%s'));
        if ($saved===false) {
            $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",$attendees,current_time('mysql'),$event->ID));
            continue;
        }
        update_option('vitalis_demo_event_signup_seeded_v1',1,false);
        return;
    }
}

function vitalis_core_event_is_open($event_id) {
    $event=get_post(absint($event_id));
    $date=(string)get_post_meta($event_id,'vitalis_event_date',true);
    if (!$event || $event->post_type!=='vitalis_event' || $event->post_status!=='publish' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { return false; }
    $parts=explode('-',$date);
    if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) || $date<wp_date('Y-m-d')) { return false; }
    if ($date===wp_date('Y-m-d')) {
        $time=(string)get_post_meta($event_id,'vitalis_event_time',true);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/',$time,$match) && sprintf('%02d:%02d',(int)$match[1],(int)$match[2])<=wp_date('H:i')) { return false; }
    }
    return true;
}

function vitalis_core_event_registration_form($event_id) {
    $snapshot=vitalis_core_event_capacity_snapshot($event_id);
    $open=vitalis_core_event_is_open($event_id);
    $user=wp_get_current_user();
    if (isset($_GET['etkinlik_basvuru'])) {
        $status=sanitize_key(vitalis_core_request_value($_GET,'etkinlik_basvuru'));
        $messages=array('kayit'=>'Etkinlik kaydınız kesinleşti. Seçtiğiniz kişi sayısına göre yerler ayrıldı; kaydınızı üye panelinizden görüntüleyip değiştirebilirsiniz.','kapasite'=>'Kontenjan bu sırada değişti. Güncel boş kişi sayısını kontrol edip yeniden deneyin.','mevcut'=>'Bu etkinlik için zaten kesin kaydınız bulunuyor. Kaydınızı üye panelinizden yönetin.','kapali'=>'Etkinlik kaydı kapanmış.','hata'=>'Kayıt tamamlanamadı. Bilgileri kontrol edip yeniden deneyin.');
        if (isset($messages[$status])) { echo '<p class="notice event-signup-notice">'.esc_html($messages[$status]); if (in_array($status,array('kayit','mevcut'),true)) { echo ' <a href="'.esc_url(home_url('/uye-paneli/')).'">Üye panelime git →</a>'; } echo '</p>'; }
    }
    echo '<section id="etkinlik-basvuru" class="event-signup-card"><span class="eyebrow">KESİN ETKİNLİK KAYDI</span><h2>Bu etkinliğe katıl</h2>';
    if (!$open) { echo '<p>Bu etkinliğin kayıt dönemi kapalı.</p></section>'; return; }
    if (!$user->exists) { if ($snapshot['available']<1) { echo '<p class="event-full-state">Kontenjan doldu. Şu anda kayıt alınamıyor.</p></section>'; return; } echo '<p class="event-spots-left"><strong>'.number_format_i18n($snapshot['available']).' kişilik yer kaldı</strong> <span>/ '.number_format_i18n($snapshot['capacity']).' kişi</span></p><p>Etkinliğe doğrudan kaydolup kişi sayısını daha sonra üye panelinizden değiştirmek veya kaydı iptal etmek için üye hesabınızla giriş yapın.</p><div class="event-signup-account-links"><a class="btn" href="'.esc_url(home_url('/uye-girisi/')).'">Üye girişi →</a><a href="'.esc_url(home_url('/uye-kaydi/')).'">Hesap oluştur</a></div></section>'; return; }
    global $wpdb;
    $table=vitalis_core_event_signups_table();
    $existing=$wpdb->get_row($wpdb->prepare("SELECT id,attendee_count FROM $table WHERE event_id=%d AND user_id=%d AND status IN ('pending','approved') ORDER BY created_at DESC LIMIT 1",$event_id,$user->ID));
    if ($existing) { echo '<p class="event-spots-left"><strong>'.number_format_i18n($snapshot['available']).' kişilik yer kaldı</strong> <span>/ '.number_format_i18n($snapshot['capacity']).' kişi</span></p><p>Bu etkinlik için '.number_format_i18n((int)$existing->attendee_count).' kişilik kesin kaydınız bulunuyor.</p><a class="btn" href="'.esc_url(home_url('/uye-paneli/')).'">Kaydımı düzenle →</a></section>'; return; }
    if ($snapshot['available']<1) { echo '<p class="event-full-state">Kontenjan doldu. Şu anda kayıt alınamıyor.</p></section>'; return; }
    echo '<p class="event-spots-left"><strong>'.number_format_i18n($snapshot['available']).' kişilik yer kaldı</strong> <span>/ '.number_format_i18n($snapshot['capacity']).' kişi</span></p><p>Bir başvuruda birden fazla kişi seçebilirsiniz. Formu gönderdiğinizde kayıt hemen kesinleşir ve seçtiğiniz kişi sayısı kontenjandan ayrılır.</p>';
    echo '<form class="inquiry-form event-signup-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_event_signup">';
    wp_nonce_field('vitalis_event_signup_'.$event_id,'vitalis_event_signup_nonce');
    echo '<input type="hidden" name="event_id" value="'.absint($event_id).'">';
    echo '<label>Ad Soyad<input name="full_name" required maxlength="160" autocomplete="name" value="'.esc_attr($user->display_name).'"></label><label>E-posta<input type="email" name="email" required maxlength="190" autocomplete="email" value="'.esc_attr($user->user_email).'"></label><label>Telefon<input type="tel" name="phone" required maxlength="50" autocomplete="tel"></label><label>Katılımcı sayısı<input type="number" name="attendee_count" min="1" max="'.absint($snapshot['available']).'" value="1" required></label><label class="full">Notunuz <span>(isteğe bağlı)</span><textarea name="note" rows="3" maxlength="1000" placeholder="Katılımcı isimleri veya kulübün bilmesini istediğiniz kısa bilgi"></textarea></label><label class="full consent-line"><input type="checkbox" name="consent" value="1" required> Etkinlik kaydım ve katılımcı bilgilerim için iletişim kurulmasını kabul ediyorum.</label><button class="btn" type="submit">'.($snapshot['available']>1?'Kesin kayıt oluştur →':'Etkinliğe kesin kaydol →').'</button><small>Kayıt gönderildiğinde seçtiğiniz yerler hemen ayrılır. Çevrimiçi ödeme alınmaz.</small></form></section>';
}

function vitalis_core_event_signup_submit() {
    $event_id=absint(vitalis_core_request_value($_POST,'event_id',0));
    $redirect=$event_id?get_permalink($event_id):home_url('/etkinlikler/');
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/uye-girisi/')); exit; }
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_event_signup_nonce')),'vitalis_event_signup_'.$event_id)) { wp_safe_redirect(add_query_arg('etkinlik_basvuru','hata',$redirect)); exit; }
    $name=sanitize_text_field(vitalis_core_request_value($_POST,'full_name'));
    $email=sanitize_email(vitalis_core_request_value($_POST,'email'));
    $phone=sanitize_text_field(vitalis_core_request_value($_POST,'phone'));
    $attendees=absint(vitalis_core_request_value($_POST,'attendee_count',0));
    $note=sanitize_textarea_field(vitalis_core_request_value($_POST,'note'));
    $length=function($value) { return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); };
    if (!vitalis_core_event_is_open($event_id) || !$name || $length($name)>160 || !is_email($email) || $length($email)>190 || !$phone || $length($phone)>50 || !$attendees || $attendees>99999 || $length($note)>1000 || vitalis_core_request_value($_POST,'consent')!=='1') { wp_safe_redirect(add_query_arg('etkinlik_basvuru','hata',$redirect)); exit; }
    $snapshot=vitalis_core_event_capacity_snapshot($event_id);
    if ($attendees>$snapshot['available']) { wp_safe_redirect(add_query_arg('etkinlik_basvuru','kapasite',$redirect)); exit; }
    global $wpdb;
    $table=vitalis_core_event_signups_table();
    $user_id=get_current_user_id();
    $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE event_id=%d AND user_id=%d AND status IN ('pending','approved') LIMIT 1",$event_id,$user_id));
    if ($existing) { wp_safe_redirect(add_query_arg('etkinlik_basvuru','mevcut',$redirect)); exit; }
    $capacity_table=vitalis_core_event_capacity_table();
    $reserved=$wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=reserved_count+%d,updated_at=%s WHERE event_id=%d AND reserved_count+%d<=max_capacity",$attendees,current_time('mysql'),$event_id,$attendees));
    if ($reserved!==1) { wp_safe_redirect(add_query_arg('etkinlik_basvuru','kapasite',$redirect)); exit; }
    $saved=$wpdb->insert($table,array('event_id'=>$event_id,'full_name'=>$name,'email'=>$email,'phone'=>$phone,'attendee_count'=>$attendees,'note'=>$note,'user_id'=>$user_id,'status'=>'approved','created_at'=>current_time('mysql')),array('%d','%s','%s','%s','%d','%s','%d','%s','%s'));
    if ($saved===false) {
        $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",$attendees,current_time('mysql'),$event_id));
        wp_safe_redirect(add_query_arg('etkinlik_basvuru','kapasite',$redirect)); exit;
    }
    $event=get_post($event_id); $date=get_post_meta($event_id,'vitalis_event_date',true); $time=get_post_meta($event_id,'vitalis_event_time',true); $venue=get_post_meta($event_id,'vitalis_event_venue',true); $fee=get_post_meta($event_id,'vitalis_event_fee',true);
    $body="Etkinlik: {$event->post_title}\nTarih: $date\nSaat: $time\nYer: $venue\nKatılımcı sayısı: $attendees\nKatılım bedeli: $fee\nAd Soyad: $name\nE-posta: $email\nTelefon: $phone\nNot: $note\n\nKayıt kesinleşti ve kişi sayısı için kontenjan ayrıldı. Çevrimiçi ödeme alınmadı.";
    wp_mail(get_option('admin_email'),'Vitalis kesin etkinlik kaydı — '.$event->post_title,$body,'Reply-To: '.$name.' <'.$email.'>');
    wp_mail($email,'Vitalis etkinlik kaydınız kesinleşti',$body);
    wp_safe_redirect(add_query_arg('etkinlik_basvuru','kayit',$redirect)); exit;
}
add_action('admin_post_nopriv_vitalis_event_signup','vitalis_core_event_signup_submit');
add_action('admin_post_vitalis_event_signup','vitalis_core_event_signup_submit');

function vitalis_core_event_signup_manage() {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/uye-girisi/')); exit; }
    $signup_id=absint(vitalis_core_request_value($_POST,'signup_id',0));
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_event_manage_nonce')),'vitalis_event_manage_'.$signup_id)) { wp_safe_redirect(add_query_arg('etkinlik_kayit','hata',home_url('/uye-paneli/'))); exit; }
    global $wpdb;
    $table=vitalis_core_event_signups_table();
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND user_id=%d LIMIT 1",$signup_id,get_current_user_id()));
    if (!$row || !in_array($row->status,array('pending','approved'),true)) { wp_safe_redirect(add_query_arg('etkinlik_kayit','hata',home_url('/uye-paneli/'))); exit; }
    $capacity_table=vitalis_core_event_capacity_table();
    $operation=sanitize_key(vitalis_core_request_value($_POST,'operation'));
    $old_count=(int)$row->attendee_count;
    if ($operation==='cancel') {
        $updated=$wpdb->query($wpdb->prepare("UPDATE $table SET status='cancelled' WHERE id=%d AND user_id=%d AND status IN ('pending','approved') AND attendee_count=%d",$signup_id,get_current_user_id(),$old_count));
        if ($updated!==1) { wp_safe_redirect(add_query_arg('etkinlik_kayit','hata',home_url('/uye-paneli/'))); exit; }
        $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",$old_count,current_time('mysql'),(int)$row->event_id));
        $event=get_post((int)$row->event_id);
        wp_mail(get_option('admin_email'),'Vitalis etkinlik kaydı iptal edildi',($event?$event->post_title:'Etkinlik').' kaydı üye tarafından iptal edildi. Ayrılan kişi sayısı: '.$old_count.'.');
        wp_safe_redirect(add_query_arg('etkinlik_kayit','iptal',home_url('/uye-paneli/'))); exit;
    }
    if ($operation!=='update' || !vitalis_core_event_is_open((int)$row->event_id)) { wp_safe_redirect(add_query_arg('etkinlik_kayit','kapali',home_url('/uye-paneli/'))); exit; }
    $new_count=absint(vitalis_core_request_value($_POST,'attendee_count',0));
    if (!$new_count || $new_count>99999) { wp_safe_redirect(add_query_arg('etkinlik_kayit','hata',home_url('/uye-paneli/'))); exit; }
    $difference=$new_count-$old_count;
    if ($difference===0) { wp_safe_redirect(add_query_arg('etkinlik_kayit','guncellendi',home_url('/uye-paneli/'))); exit; }
    if ($difference>0) {
        $reserved=$wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=reserved_count+%d,updated_at=%s WHERE event_id=%d AND reserved_count+%d<=max_capacity",$difference,current_time('mysql'),(int)$row->event_id,$difference));
        if ($reserved!==1) { wp_safe_redirect(add_query_arg('etkinlik_kayit','kontenjan',home_url('/uye-paneli/'))); exit; }
    }
    $updated=$wpdb->query($wpdb->prepare("UPDATE $table SET attendee_count=%d WHERE id=%d AND user_id=%d AND attendee_count=%d AND status IN ('pending','approved')",$new_count,$signup_id,get_current_user_id(),$old_count));
    if ($updated!==1) {
        if ($difference>0) { $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",$difference,current_time('mysql'),(int)$row->event_id)); }
        wp_safe_redirect(add_query_arg('etkinlik_kayit','hata',home_url('/uye-paneli/'))); exit;
    }
    if ($difference<0) { $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",abs($difference),current_time('mysql'),(int)$row->event_id)); }
    $event=get_post((int)$row->event_id);
    wp_mail(get_option('admin_email'),'Vitalis etkinlik kişi sayısı güncellendi',($event?$event->post_title:'Etkinlik').' kaydı üye tarafından '.$old_count.' kişiden '.$new_count.' kişiye güncellendi.');
    wp_safe_redirect(add_query_arg('etkinlik_kayit','guncellendi',home_url('/uye-paneli/'))); exit;
}
add_action('admin_post_vitalis_event_signup_manage','vitalis_core_event_signup_manage');

function vitalis_core_admin_event_signups() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb; $table=vitalis_core_event_signups_table();
    $rows=$wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 500");
    $labels=array('pending'=>'Eski kayıt · inceleme bekliyor','approved'=>'Kesin kayıt','rejected'=>'Reddedildi','cancelled'=>'İptal edildi');
    echo '<div class="wrap vitalis-admin-home"><h1>Etkinlik kayıtları</h1><p class="vitalis-admin-intro">Üye girişinden yapılan yeni kayıtlar doğrudan kesinleşir ve seçilen kişi sayısı kontenjandan ayrılır. Üyeler kaydı kendi panellerinden iptal edebilir veya kişi sayısını değiştirebilir. Gerekirse yönetici de kaydı iptal ederek yer açabilir.</p><table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Kayıt zamanı</th><th>Katılımcı</th><th>Kişi</th><th>Not</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="7">Henüz etkinlik başvurusu yok.</td></tr>'; }
    foreach ((array)$rows as $row) {
        $event=get_post((int)$row->event_id);
        echo '<tr><td>'.esc_html($event?$event->post_title:'Silinmiş etkinlik').'</td><td>'.esc_html(mysql2date('d.m.Y H:i',$row->created_at)).'</td><td><strong>'.esc_html($row->full_name).'</strong><br><a href="mailto:'.esc_attr($row->email).'">'.esc_html($row->email).'</a><br>'.esc_html($row->phone).'</td><td>'.number_format_i18n((int)$row->attendee_count).'</td><td>'.esc_html($row->note).'</td><td>'.esc_html($labels[$row->status]??$row->status).'</td><td>';
        $actions=$row->status==='pending'?array('approved'=>'Onayla','rejected'=>'Reddet'):($row->status==='approved'?array('rejected'=>'Reddet / yer aç','cancelled'=>'İptal et'):array('approved'=>'Yeniden onayla'));
        foreach ($actions as $status=>$label) {
            echo '<form style="display:inline-block;margin:0 5px 5px 0" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_event_signup_status"><input type="hidden" name="signup_id" value="'.absint($row->id).'"><input type="hidden" name="status" value="'.esc_attr($status).'">';
            wp_nonce_field('vitalis_event_signup_status_'.$row->id);
            echo '<button class="button" type="submit">'.esc_html($label).'</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table><p class="description">Etkinlik kaydı üyelik başlatmaz ve ödeme tahsil etmez. E-posta bildirimleri sunucudaki WordPress e-posta ayarlarına bağlıdır.</p></div>';
}

function vitalis_core_event_signup_status() {
    if (!current_user_can('manage_options')) { wp_die('Bu işlem için yetkiniz yok.'); }
    $id=absint(vitalis_core_request_value($_POST,'signup_id',0));
    check_admin_referer('vitalis_event_signup_status_'.$id);
    $new_status=sanitize_key(vitalis_core_request_value($_POST,'status'));
    if (!in_array($new_status,array('approved','rejected','cancelled'),true)) { wp_die('Durum geçersiz.'); }
    global $wpdb; $table=vitalis_core_event_signups_table(); $capacity_table=vitalis_core_event_capacity_table();
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
    if (!$row) { wp_safe_redirect(admin_url('admin.php?page=vitalis-event-signups')); exit; }
    $was_active=in_array($row->status,array('pending','approved'),true); $will_be_active=in_array($new_status,array('pending','approved'),true);
    if (!$was_active && $will_be_active) {
        $reserved=$wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=reserved_count+%d,updated_at=%s WHERE event_id=%d AND reserved_count+%d<=max_capacity",(int)$row->attendee_count,current_time('mysql'),(int)$row->event_id,(int)$row->attendee_count));
        if ($reserved!==1) { wp_safe_redirect(add_query_arg('updated','capacity',admin_url('admin.php?page=vitalis-event-signups'))); exit; }
    }
    $updated=$wpdb->update($table,array('status'=>$new_status),array('id'=>$id,'status'=>$row->status),array('%s'),array('%d','%s'));
    if ($updated===false || $updated===0) {
        if (!$was_active && $will_be_active) { $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",(int)$row->attendee_count,current_time('mysql'),(int)$row->event_id)); }
        wp_safe_redirect(admin_url('admin.php?page=vitalis-event-signups')); exit;
    }
    if ($was_active && !$will_be_active) { $wpdb->query($wpdb->prepare("UPDATE $capacity_table SET reserved_count=GREATEST(0,reserved_count-%d),updated_at=%s WHERE event_id=%d",(int)$row->attendee_count,current_time('mysql'),(int)$row->event_id)); }
    $label=$new_status==='approved'?'onaylandı':($new_status==='cancelled'?'iptal edildi':'reddedildi');
    wp_mail($row->email,'Vitalis etkinlik başvurusu '.$label,'Etkinlik başvurunuz '.$label.'. Etkinlik sayfasından ayrıntıları görüntüleyebilirsiniz.');
    wp_safe_redirect(admin_url('admin.php?page=vitalis-event-signups&updated=1')); exit;
}
add_action('admin_post_vitalis_event_signup_status','vitalis_core_event_signup_status');

function vitalis_core_render_events($limit=-1) {
    $posts = get_posts(array('post_type'=>'vitalis_event','post_status'=>'publish','numberposts'=>$limit,'meta_key'=>'vitalis_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>array(array('key'=>'vitalis_event_date','value'=>wp_date('Y-m-d'),'compare'=>'>=','type'=>'DATE'))));
    if (!$posts) { echo '<section class="wrap content-section"><div class="event-notice">Yaklaşan etkinlik programı henüz yayınlanmadı. Yeni duyurular bu alanda paylaşılacaktır.</div></section>'; return; }
    echo '<section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">KULÜPTE YAŞAM</span><h2>Etkinlik takvimi</h2><p>Her etkinliğin kendi sayfasında saat, adres, kişi kapasitesi ve kesin kayıt formu bulunur. Bir kayıtta birden fazla katılımcı ekleyebilirsiniz.</p></div></div><div class="event-notice"><strong>Program ve ücretlerin güncelliğini teyit edin</strong><p>Bu sayfadaki örnek içerikleri WordPress yönetim panelindeki Etkinlikler alanından düzenleyebilirsiniz. Kayıt gönderildiğinde kişi sayısı kontenjandan ayrılır; ödeme alınmaz.</p></div><div class="event-list">';
    foreach ($posts as $post) {
        $date = get_post_meta($post->ID,'vitalis_event_date',true); $time=get_post_meta($post->ID,'vitalis_event_time',true); $venue=get_post_meta($post->ID,'vitalis_event_venue',true); $fee=get_post_meta($post->ID,'vitalis_event_fee',true); $capacity=get_post_meta($post->ID,'vitalis_event_capacity',true);
        $availability=vitalis_core_event_capacity_snapshot($post->ID);
        $img=get_the_post_thumbnail_url($post,'large'); if (!$img) { $local=get_post_meta($post->ID,'vitalis_local_image',true); $img=$local?vitalis_img($local):vitalis_img('event-fitness.jpg'); }
        $url=get_permalink($post);
        $capacity_label=number_format_i18n($availability['available']).' / '.number_format_i18n($availability['capacity']).' kişi kaldı'.($capacity?' · '.$capacity:'');
        echo '<article class="event-card"><img src="'.esc_url($img).'" alt="'.esc_attr($post->post_title).'" loading="lazy"><div class="event-copy"><span class="event-date">'.esc_html($date?wp_date('j F Y',strtotime($date)): 'Tarih duyurulacak').'</span><h3><a href="'.esc_url($url).'">'.esc_html($post->post_title).'</a></h3><div class="event-description">'.wp_kses_post(wpautop($post->post_content)).'</div><div class="event-facts"><span><b>Saat</b>'.esc_html($time).'</span><span><b>Yer</b>'.esc_html($venue).'</span><span><b>Katılım</b>'.esc_html($fee).'</span><span><b>Kalan / toplam</b>'.esc_html($capacity_label).'</span></div><a class="btn" href="'.esc_url($url).'">Etkinlik ayrıntıları ve kayıt →</a></div></article>';
    }
    echo '</div></section>';
}

function vitalis_core_event_teasers() {
    $posts = get_posts(array('post_type'=>'vitalis_event','post_status'=>'publish','numberposts'=>4,'meta_key'=>'vitalis_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>array(array('key'=>'vitalis_event_date','value'=>wp_date('Y-m-d'),'compare'=>'>=','type'=>'DATE'))));
    foreach ($posts as $post) {
        $img=get_the_post_thumbnail_url($post,'medium'); if (!$img) { $local=get_post_meta($post->ID,'vitalis_local_image',true); $img=$local?vitalis_img($local):vitalis_img('event-fitness.jpg'); }
        echo '<a class="news-card" href="'.esc_url(get_permalink($post)).'"><img src="'.esc_url($img).'" alt="'.esc_attr($post->post_title).'" loading="lazy"><span>'.esc_html(get_post_meta($post->ID,'vitalis_event_date',true)).'</span><h3>'.esc_html($post->post_title).'</h3><p>'.esc_html(wp_trim_words(wp_strip_all_tags($post->post_content),18)).'</p><b>Etkinlik ayrıntılarını gör →</b></a>';
    }
}

function vitalis_core_facility_gallery($slug) {
    $posts=get_posts(array('post_type'=>'vitalis_facility','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'vitalis_page_slug','meta_value'=>$slug));
    if (!$posts) { return false; }
    $post=$posts[0];
    $ids=(array)get_post_meta($post->ID,'vitalis_gallery_ids',true);
    if ($ids) {
        $photos=array();
        foreach ($ids as $id) { $full=wp_get_attachment_image_url((int)$id,'full'); $thumb=wp_get_attachment_image_url((int)$id,'large'); if ($full) { $photos[]=array($full,$thumb?:$full,get_post_meta($id,'_wp_attachment_image_alt',true)); } }
    } else {
        $filenames=(array)get_post_meta($post->ID,'vitalis_local_gallery',true);
        $cover=get_post_meta($post->ID,'vitalis_local_image',true);
        if ($cover) { array_unshift($filenames,$cover); }
        $photos=array(); foreach ($filenames as $file) { $photos[]=array(vitalis_img($file),vitalis_img($file),$post->post_title); }
    }
    if (!$photos) { return false; }
    echo '<section id="alan-galerisi" class="wrap content-section facility-photo-section"><div class="section-heading"><div><span class="eyebrow">ALAN GALERİSİ</span><h2>'.esc_html($post->post_title).' fotoğrafları</h2><p>Fotoğraflardan birine tıklayarak büyük görünümde açın.</p></div></div><div class="gallery-grid detail-gallery" data-gallery-set="'.esc_attr($slug).'">';
    foreach ($photos as $photo) { $alt=$photo[2]?:$post->post_title; echo '<a href="'.esc_url($photo[0]).'" data-vitalis-lightbox><img src="'.esc_url($photo[1]).'" alt="'.esc_attr($alt).'" loading="lazy"><figcaption>'.esc_html($alt).'<span class="gallery-more">Büyütmek için tıkla ↗</span></figcaption></a>'; }
    echo '</div></section>';
    return true;
}

function vitalis_core_render_hygiene($slug) {
    if (!in_array($slug,array('fitness-salonu','yuzme-havuzu','soyunma-odalari'),true)) { return; }
    $posts=get_posts(array('post_type'=>'vitalis_facility','post_status'=>'publish','numberposts'=>1,'meta_key'=>'vitalis_page_slug','meta_value'=>$slug));
    if (!$posts) { return; }
    $text=get_post_meta($posts[0]->ID,'vitalis_hygiene_text',true);
    $date=get_post_meta($posts[0]->ID,'vitalis_hygiene_date',true);
    echo '<section class="wrap hygiene-panel"><div class="hygiene-icon" aria-hidden="true">✓</div><div><span class="eyebrow">TEMİZLİK VE BAKIM BİLGİSİ</span><h2>Alan kullanımı ve hijyen</h2><p>'.esc_html($text?:'Temizlik kontrol planı henüz yayınlanmadı. Bilgi için lütfen kulüp ekibiyle görüşün.').'</p>';
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { echo '<small>Son kontrol kaydı: '.esc_html(mysql2date('d.m.Y',$date)).'</small>'; }
    else { echo '<small class="hygiene-sample">Örnek bilgilendirme — bu metin gerçek kontrol kaydı değildir. İşletme tarafından doğrulanmalıdır.</small>'; }
    echo '</div></section>';
}

function vitalis_core_render_gallery_index() {
    $posts=get_posts(array('post_type'=>'vitalis_facility','post_status'=>'publish','numberposts'=>-1,'orderby'=>'menu_order title','order'=>'ASC'));
    if (!$posts) { return false; }
    echo '<section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">VITALIS’TEN KARELER</span><h2>Alanlarımızı keşfedin</h2><p>Bir alana tıklayarak tanıtımını ve fotoğraf galerilerini açın.</p></div></div><div class="gallery-grid gallery-index-grid">';
    foreach ($posts as $post) {
        $slug=get_post_meta($post->ID,'vitalis_page_slug',true); if (!$slug) { continue; }
        $img=get_the_post_thumbnail_url($post,'large'); if (!$img) { $local=get_post_meta($post->ID,'vitalis_local_image',true); $img=$local?vitalis_img($local):vitalis_img('club.jpg'); }
        echo '<a href="'.esc_url(home_url('/'.$slug.'/#alan-galerisi')).'"><img src="'.esc_url($img).'" alt="'.esc_attr($post->post_title).'" loading="lazy"><figcaption>'.esc_html($post->post_title).'<span class="gallery-more">Daha fazla görseli gör →</span></figcaption></a>';
    }
    echo '</div></section>'; return true;
}

function vitalis_core_member_links() {
    if (is_user_logged_in() && vitalis_core_is_coach()) { echo '<section class="wrap content-section member-entry"><div><span class="eyebrow">VITALIS ANTRENÖR ALANI</span><h2>Öğrenci gelişimlerini yönetin</h2><p>Size atanmış öğrenciler için özel değerlendirme notları ekleyin.</p></div><div><a class="btn" href="'.esc_url(home_url('/antrenor-paneli/')).'">Antrenör panelim →</a></div></section>'; return; }
    echo '<section class="wrap content-section member-entry"><div><span class="eyebrow">KİŞİSEL ÜYE ALANI</span><h2>Antrenman ilerlemenizi takip edin</h2><p>Hesabınızda kilo ve aktivite geçmişinizi tutabilir, gelişim notlarınızı daha sonra görüntüleyebilirsiniz. Kayıtlarınız hesabınıza özel görünür.</p></div><div>'.(is_user_logged_in()?'<a class="btn" href="'.esc_url(home_url('/uye-paneli/')).'">Üye panelim →</a>':'<a class="btn" href="'.esc_url(home_url('/uye-kaydi/')).'">Hesap oluştur →</a> <a class="member-text-link" href="'.esc_url(home_url('/uye-girisi/')).'">Üye girişi</a>').'</div></section>';
}

function vitalis_core_privacy_exporter($email,$page=1) {
    $user=get_user_by('email',$email); $data=array();
    if ($user) {
        $records=vitalis_core_progress_records($user->ID); $coach_records=vitalis_core_trainer_progress_records($user->ID);
        $assigned_coach=get_userdata(absint(get_user_meta($user->ID,'vitalis_assigned_coach_id',true)));
        $data[]=array('group_id'=>'vitalis-member-profile','group_label'=>'Vitalis üye profili','item_id'=>'member-profile','data'=>array(array('name'=>'Güncel hedef','value'=>get_user_meta($user->ID,'vitalis_current_goal',true)),array('name'=>'Spor odağı','value'=>get_user_meta($user->ID,'vitalis_primary_sport',true)),array('name'=>'Üyelik durumu','value'=>get_user_meta($user->ID,'vitalis_member_status',true)),array('name'=>'Üyelik paketi talebi','value'=>get_user_meta($user->ID,'vitalis_member_requested_plan',true)),array('name'=>'Sorumlu antrenör','value'=>$assigned_coach?$assigned_coach->display_name:'')));
        foreach ($records as $i=>$record) { $data[]=array('group_id'=>'vitalis-progress','group_label'=>'Vitalis gelişim kayıtları','item_id'=>'progress-'.$i,'data'=>array(array('name'=>'Tarih','value'=>$record['date']),array('name'=>'Kilo (kg)','value'=>$record['weight']),array('name'=>'Spor / aktivite','value'=>$record['sport']),array('name'=>'Not','value'=>$record['note']??''))); }
        foreach ($coach_records as $i=>$record) { $data[]=array('group_id'=>'vitalis-coach-progress','group_label'=>'Antrenör gelişim değerlendirmeleri','item_id'=>'coach-progress-'.$i,'data'=>array(array('name'=>'Tarih','value'=>$record['date']),array('name'=>'Antrenör','value'=>$record['coach_name']),array('name'=>'Gelişim odağı','value'=>$record['focus']),array('name'=>'Kilo (kg)','value'=>$record['weight']),array('name'=>'Değerlendirme','value'=>$record['note']))); }
    }
    global $wpdb; $table=vitalis_core_trainer_appointments_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) {
        $rows=$user?$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE email=%s OR user_id=%d",$email,$user->ID)):$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE email=%s",$email));
        foreach ((array)$rows as $i=>$row) {
            $trainer=get_post((int)$row->trainer_id);
            $data[]=array('group_id'=>'vitalis-trainer-appointments','group_label'=>'Vitalis antrenör randevu talepleri','item_id'=>'trainer-appointment-'.$i,'data'=>array(array('name'=>'Antrenör','value'=>$trainer?$trainer->post_title:''),array('name'=>'Tarih','value'=>$row->appointment_date),array('name'=>'Saat','value'=>substr($row->appointment_time,0,5)),array('name'=>'Ders biçimi','value'=>$row->session_type==='group'?'Grup':'Bireysel'),array('name'=>'Katılımcı sayısı','value'=>(string)$row->group_size),array('name'=>'Not','value'=>$row->note),array('name'=>'Durum','value'=>$row->status)));
        }
    }
    return array('data'=>$data,'done'=>true);
}
add_filter('wp_privacy_personal_data_exporters',function($exporters){$exporters['vitalis-progress']=array('exporter_friendly_name'=>'Vitalis gelişim kayıtları','callback'=>'vitalis_core_privacy_exporter');return $exporters;});
function vitalis_core_privacy_eraser($email,$page=1) {
    $user=get_user_by('email',$email);
    $removed=false;
    if ($user) { foreach (array('vitalis_progress','vitalis_trainer_progress','vitalis_current_goal','vitalis_primary_sport','vitalis_member_requested_plan','vitalis_assigned_coach_id') as $meta_key) { $removed=delete_user_meta($user->ID,$meta_key) || $removed; } }
    global $wpdb; $table=vitalis_core_trainer_appointments_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table) {
        $delete_sql=$user?$wpdb->prepare("DELETE FROM $table WHERE is_demo=0 AND (email=%s OR user_id=%d)",$email,$user->ID):$wpdb->prepare("DELETE FROM $table WHERE is_demo=0 AND email=%s",$email);
        $wpdb->query($delete_sql); if ($wpdb->rows_affected>0) { $removed=true; }
    }
    return array('items_removed'=>$removed,'items_retained'=>false,'messages'=>array(),'done'=>true);
}
add_filter('wp_privacy_personal_data_erasers',function($erasers){$erasers['vitalis-progress']=array('eraser_friendly_name'=>'Vitalis gelişim kayıtları','callback'=>'vitalis_core_privacy_eraser');return $erasers;});

require_once get_template_directory().'/includes/vitalis-trainer-appointments.php';
