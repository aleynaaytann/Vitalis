<?php
if (!defined('ABSPATH')) { exit; }

function vitalis_trainer_schedule($trainer_id) {
    $raw = get_post_meta($trainer_id,'vitalis_availability',true);
    $schedule = array();
    foreach (preg_split('/\r\n|\r|\n/',(string)$raw) as $line) {
        $parts = array_map('trim',explode('|',$line));
        if (count($parts)!==3) { continue; }
        $day=absint($parts[0]); $open=$parts[1]; $close=$parts[2];
        if ($day<1 || $day>7 || !preg_match('/^(?:[01]\d|2[0-3]):00$/',$open) || !preg_match('/^(?:[01]\d|2[0-3]):00$/',$close) || $open >= $close) { continue; }
        $schedule[$day]=array('open'=>$open,'close'=>$close);
    }
    return $schedule;
}

function vitalis_trainer_times_for_date($trainer_id,$date,$include_past=false) {
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
    if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<wp_date('Y-m-d')) { return array(); }
    $schedule=vitalis_trainer_schedule($trainer_id);
    $day=(int)$parsed->format('N');
    if (!isset($schedule[$day])) { return array(); }
    $start=(int)substr($schedule[$day]['open'],0,2);
    $end=(int)substr($schedule[$day]['close'],0,2);
    $times=array();
    for ($hour=$start;$hour<$end;$hour++) {
        $time=sprintf('%02d:00',$hour);
        if (!$include_past && $date===wp_date('Y-m-d') && $time<=wp_date('H:00')) { continue; }
        $times[]=$time;
    }
    return $times;
}

function vitalis_trainer_date_allowed($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { return false; }
    $parts=explode('-',$date);
    if (!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) || $date<wp_date('Y-m-d')) { return false; }
    $limit=(new DateTimeImmutable(wp_date('Y-01-01'),wp_timezone()))->modify('+3 years')->modify('-1 day')->format('Y-m-d');
    return $date<=$limit;
}

function vitalis_trainer_max_month() {
    return (new DateTimeImmutable(wp_date('Y-01-01'),wp_timezone()))->modify('+3 years')->modify('-1 day')->format('Y-m');
}

function vitalis_trainer_busy_map($trainer_id,$start,$end) {
    global $wpdb;
    $table=vitalis_core_trainer_appointments_table();
    $rows=$wpdb->get_results($wpdb->prepare("SELECT appointment_date, appointment_time, is_demo FROM $table WHERE trainer_id=%d AND appointment_date BETWEEN %s AND %s AND status IN ('pending','approved')",$trainer_id,$start,$end));
    $busy=array();
    foreach ((array)$rows as $row) {
        $day=$row->appointment_date;
        if (!isset($busy[$day])) { $busy[$day]=array(); }
        $busy[$day][substr($row->appointment_time,0,5)]=array('demo'=>(int)$row->is_demo===1);
    }
    return $busy;
}

function vitalis_trainer_day_summary($trainer_id,$date,$busy=null) {
    $times=vitalis_trainer_times_for_date($trainer_id,$date);
    if (!$times) { return array('date'=>$date,'status'=>'closed','label'=>'Kapalı','available'=>0,'total'=>0); }
    if ($busy===null) { $busy=vitalis_trainer_busy_map($trainer_id,$date,$date); }
    $occupied=isset($busy[$date])?$busy[$date]:array();
    $available=0;
    foreach ($times as $time) { if (!isset($occupied[$time])) { $available++; } }
    $status=$available===0?'full':($available<count($times)?'partial':'available');
    return array('date'=>$date,'status'=>$status,'label'=>$status==='full'?'Dolu':($status==='partial'?'Kısmen dolu':'Müsait'),'available'=>$available,'total'=>count($times));
}

function vitalis_trainer_ajax_calendar_month() {
    check_ajax_referer('vitalis_trainer_calendar','nonce');
    $trainer_id=absint(vitalis_core_request_value($_REQUEST,'trainer_id',0));
    $month=sanitize_text_field(vitalis_core_request_value($_REQUEST,'month'));
    $trainer=get_post($trainer_id);
    if (!$trainer || $trainer->post_type!=='vitalis_trainer' || $trainer->post_status!=='publish' || !preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/',$month)) { wp_send_json_error(array('message'=>'Antrenör veya ay bilgisi geçersiz.'),400); }
    $current_month=wp_date('Y-m');
    $latest_month=vitalis_trainer_max_month();
    if ($month<$current_month || $month>$latest_month) { wp_send_json_error(array('message'=>'Takvim en fazla dört ay sonrasını gösterir.'),400); }
    $first=$month.'-01';
    $last=DateTimeImmutable::createFromFormat('!Y-m-d',$first,wp_timezone())->modify('last day of this month')->format('Y-m-d');
    $busy=vitalis_trainer_busy_map($trainer_id,$first,$last);
    $days=array();
    $count=(int)substr($last,8,2);
    for ($day=1;$day<=$count;$day++) {
        $date=$month.'-'.sprintf('%02d',$day);
        $summary=vitalis_trainer_day_summary($trainer_id,$date,$busy);
        $summary['day']=$day;
        $summary['weekday']=(int)DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone())->format('N');
        $days[]=$summary;
    }
    wp_send_json_success(array('days'=>$days,'month'=>$month));
}
add_action('wp_ajax_vitalis_trainer_calendar_month','vitalis_trainer_ajax_calendar_month');
add_action('wp_ajax_nopriv_vitalis_trainer_calendar_month','vitalis_trainer_ajax_calendar_month');

function vitalis_trainer_ajax_calendar_slots() {
    check_ajax_referer('vitalis_trainer_calendar','nonce');
    $trainer_id=absint(vitalis_core_request_value($_REQUEST,'trainer_id',0));
    $date=sanitize_text_field(vitalis_core_request_value($_REQUEST,'date'));
    $trainer=get_post($trainer_id);
    if (!$trainer || $trainer->post_type!=='vitalis_trainer' || $trainer->post_status!=='publish' || !vitalis_trainer_date_allowed($date)) { wp_send_json_error(array('message'=>'Antrenör veya tarih bilgisi geçersiz.'),400); }
    $times=vitalis_trainer_times_for_date($trainer_id,$date);
    $busy=vitalis_trainer_busy_map($trainer_id,$date,$date);
    $occupied=isset($busy[$date])?$busy[$date]:array();
    $slots=array();
    foreach ($times as $time) {
        $is_available=!isset($occupied[$time]);
        $slots[]=array('time'=>$time,'available'=>$is_available,'demo'=>!$is_available && !empty($occupied[$time]['demo']));
    }
    wp_send_json_success(array('slots'=>$slots,'date'=>$date,'message'=>$times?'Bu saat için antrenörünüze randevu talebi gönderebilirsiniz.':'Bu gün için çalışma saati bulunmuyor.'));
}
add_action('wp_ajax_vitalis_trainer_calendar_slots','vitalis_trainer_ajax_calendar_slots');
add_action('wp_ajax_nopriv_vitalis_trainer_calendar_slots','vitalis_trainer_ajax_calendar_slots');

function vitalis_trainer_submit_appointment() {
    $trainer_id=absint(vitalis_core_request_value($_POST,'trainer_id',0));
    $redirect=$trainer_id?get_permalink($trainer_id):false;
    if (!$redirect) { $redirect=home_url('/antrenorler/'); }
    if (!wp_verify_nonce(sanitize_text_field(vitalis_core_request_value($_POST,'vitalis_trainer_nonce')),'vitalis_trainer_appointment_'.$trainer_id)) { wp_safe_redirect(add_query_arg('randevu','hata',$redirect)); exit; }
    $trainer=get_post($trainer_id);
    $date=sanitize_text_field(vitalis_core_request_value($_POST,'appointment_date'));
    $time=sanitize_text_field(vitalis_core_request_value($_POST,'appointment_time'));
    $type=sanitize_key(vitalis_core_request_value($_POST,'session_type'));
    $name=sanitize_text_field(vitalis_core_request_value($_POST,'full_name'));
    $email=sanitize_email(vitalis_core_request_value($_POST,'email'));
    $phone=sanitize_text_field(vitalis_core_request_value($_POST,'phone'));
    $group_size=absint(vitalis_core_request_value($_POST,'group_size',1));
    $group_capacity=absint(get_post_meta($trainer_id,'vitalis_group_capacity',true));
    if ($group_capacity<2) { $group_capacity=20; }
    $note=sanitize_textarea_field(vitalis_core_request_value($_POST,'note'));
    $length=function($value) { return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); };
    if (!$trainer || $trainer->post_type!=='vitalis_trainer' || $trainer->post_status!=='publish' || !in_array($type,array('individual','group'),true) || !$name || $length($name)>160 || !is_email($email) || $length($email)>190 || !$phone || $length($phone)>50 || $length($note)>1000 || vitalis_core_request_value($_POST,'consent')!=='1' || ($type==='group' && ($group_size<2 || $group_size>$group_capacity)) || !preg_match('/^(?:[01]\d|2[0-3]):00$/',$time) || !vitalis_trainer_date_allowed($date) || !in_array($time,vitalis_trainer_times_for_date($trainer_id,$date),true)) {
        wp_safe_redirect(add_query_arg('randevu','hata',$redirect)); exit;
    }
    global $wpdb;
    $table=vitalis_core_trainer_appointments_table();
    $busy=$wpdb->get_row($wpdb->prepare("SELECT id,status FROM $table WHERE trainer_id=%d AND appointment_date=%s AND appointment_time=%s",$trainer_id,$date,$time));
    if ($busy && !in_array($busy->status,array('cancelled','rejected'),true)) { wp_safe_redirect(add_query_arg('randevu','dolu',$redirect)); exit; }
    $data=array('session_type'=>$type,'full_name'=>$name,'email'=>$email,'phone'=>$phone,'group_size'=>$type==='group'?$group_size:1,'note'=>$note,'user_id'=>get_current_user_id(),'is_demo'=>0,'status'=>'pending','created_at'=>current_time('mysql'));
    if ($busy) { $saved=$wpdb->update($table,$data,array('id'=>(int)$busy->id,'status'=>$busy->status),array('%s','%s','%s','%s','%d','%s','%d','%d','%s','%s'),array('%d','%s')); }
    else { $data['trainer_id']=$trainer_id; $data['appointment_date']=$date; $data['appointment_time']=$time; $saved=$wpdb->insert($table,$data,array('%s','%s','%s','%s','%d','%s','%d','%d','%s','%s','%d','%s','%s')); }
    if ($saved===false || $saved===0) { wp_safe_redirect(add_query_arg('randevu','dolu',$redirect)); exit; }
    $type_label=$type==='group'?'Grup dersi':'Bireysel ders';
    $body="Antrenör: {$trainer->post_title}\nDers biçimi: $type_label\nTarih: $date\nSaat: $time\nAd Soyad: $name\nE-posta: $email\nTelefon: $phone\nKatılımcı sayısı: ".($type==='group'?$group_size:'1')."\nNot: $note\n\nTalep antrenörün kabul veya reddetmesi için iletildi. Ödeme alınmamıştır.";
    wp_mail(get_option('admin_email'),'Vitalis antrenör randevu talebi — '.$trainer->post_title,$body,'Reply-To: '.$name.' <'.$email.'>');
    $coach_id=absint(get_post_meta($trainer_id,'vitalis_coach_user_id',true)); $coach=$coach_id?get_userdata($coach_id):false;
    if ($coach && is_email($coach->user_email) && strtolower($coach->user_email)!==strtolower(get_option('admin_email'))) { wp_mail($coach->user_email,'Yeni randevu talebi — '.$trainer->post_title,$body,'Reply-To: '.$name.' <'.$email.'>'); }
    wp_safe_redirect(add_query_arg('randevu','alindi',$redirect)); exit;
}
add_action('admin_post_nopriv_vitalis_trainer_appointment_submit','vitalis_trainer_submit_appointment');
add_action('admin_post_vitalis_trainer_appointment_submit','vitalis_trainer_submit_appointment');

function vitalis_trainer_seed_demo_appointments() {
    if (get_option('vitalis_demo_trainer_appointments_seeded_v1')) { return; }
    global $wpdb;
    $table=vitalis_core_trainer_appointments_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) { return; }
    $trainers=get_posts(array('post_type'=>'vitalis_trainer','post_status'=>'publish','numberposts'=>-1));
    foreach ($trainers as $trainer) {
        $first_date=''; $full_date=''; $first_times=array(); $full_times=array();
        for ($offset=1;$offset<=28;$offset++) {
            $date=(new DateTimeImmutable(wp_date('Y-m-d'),wp_timezone()))->modify('+'.$offset.' days')->format('Y-m-d');
            $times=vitalis_trainer_times_for_date($trainer->ID,$date,true);
            if (count($times)<2) { continue; }
            if ($first_date==='') { $first_date=$date; $first_times=$times; continue; }
            if ($offset>=7) { $full_date=$date; $full_times=$times; break; }
        }
        if ($first_date) {
            foreach (array(array($first_times[0],'pending'),array($first_times[min(2,count($first_times)-1)],'approved')) as $index=>$item) {
                $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE trainer_id=%d AND appointment_date=%s AND appointment_time=%s",$trainer->ID,$first_date,$item[0]));
                if (!$exists) { $wpdb->insert($table,array('trainer_id'=>$trainer->ID,'session_type'=>$index?'group':'individual','appointment_date'=>$first_date,'appointment_time'=>$item[0],'full_name'=>'DEMO — Örnek doluluk','email'=>'demo-randevu@example.com','phone'=>'000 000 00 00','group_size'=>$index?4:1,'note'=>'DEMO KAYDI — Takvim doluluğunu göstermek için eklenmiştir; gerçek randevu değildir.','user_id'=>0,'is_demo'=>1,'status'=>$item[1],'created_at'=>current_time('mysql'))); }
            }
        }
        if ($full_date) {
            foreach ($full_times as $time) {
                $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE trainer_id=%d AND appointment_date=%s AND appointment_time=%s",$trainer->ID,$full_date,$time));
                if (!$exists) { $wpdb->insert($table,array('trainer_id'=>$trainer->ID,'session_type'=>'individual','appointment_date'=>$full_date,'appointment_time'=>$time,'full_name'=>'DEMO — Örnek doluluk','email'=>'demo-randevu@example.com','phone'=>'000 000 00 00','group_size'=>1,'note'=>'DEMO KAYDI — Bu günün tüm saatleri takvim demosu için dolu gösterilmiştir.','user_id'=>0,'is_demo'=>1,'status'=>'approved','created_at'=>current_time('mysql'))); }
            }
        }
    }
    update_option('vitalis_demo_trainer_appointments_seeded_v1',1,false);
}

function vitalis_trainer_sync_demo_coach_profile() {
    $coaches=get_users(array('meta_key'=>'vitalis_demo_account','meta_value'=>'coach','number'=>1));
    $trainer=get_page_by_path('mert-aydin-kuvvet-kondisyon',OBJECT,'vitalis_trainer');
    if (!$coaches || !$trainer || get_post_meta($trainer->ID,'vitalis_coach_user_id',true)) { return; }
    update_post_meta($trainer->ID,'vitalis_coach_user_id',(int)$coaches[0]->ID);
}

function vitalis_trainer_render_coach_dashboard($coach_id) {
    global $wpdb;
    if (current_user_can('manage_options')) {
        $trainer_ids=get_posts(array('post_type'=>'vitalis_trainer','post_status'=>array('publish','draft'),'numberposts'=>-1,'fields'=>'ids'));
    } else {
        $trainer_ids=get_posts(array('post_type'=>'vitalis_trainer','post_status'=>array('publish','draft'),'numberposts'=>-1,'fields'=>'ids','meta_key'=>'vitalis_coach_user_id','meta_value'=>(string)absint($coach_id)));
    }
    echo '<section class="member-card coach-appointment-panel"><span class="eyebrow">RANDEVU YÖNETİMİ</span><h2>Antrenör randevu talepleri</h2><p>Size bağlı profillerden gelen talepleri uygunluğunuza göre kabul veya reddedin. Kabul edilen saat takvimde ayrılır.</p>';
    if (!$trainer_ids) { echo '<p>Antrenör profiliniz henüz kullanıcı hesabınıza bağlanmamış. Site yöneticisi Antrenörler bölümünden hesabınızı ilgili profile atayabilir.</p></section>'; return; }
    $trainer_ids=array_map('absint',$trainer_ids);
    $marks=implode(',',array_fill(0,count($trainer_ids),'%d'));
    $table=vitalis_core_trainer_appointments_table();
    $sql=$wpdb->prepare("SELECT * FROM $table WHERE trainer_id IN ($marks) ORDER BY CASE status WHEN 'pending' THEN 0 ELSE 1 END, appointment_date ASC, appointment_time ASC LIMIT 40",$trainer_ids);
    $rows=$wpdb->get_results($sql);
    $labels=array('pending'=>'Karar bekliyor','approved'=>'Kabul edildi','rejected'=>'Reddedildi','cancelled'=>'İptal edildi');
    if (!$rows) { echo '<p>Henüz bu antrenör profillerine randevu talebi gelmedi.</p></section>'; return; }
    echo '<div class="progress-table-wrap"><table class="progress-table"><thead><tr><th>Tarih / saat</th><th>Antrenör ve ders</th><th>Üye bilgisi</th><th>Not</th><th>Durum / karar</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $trainer=get_post((int)$row->trainer_id); $demo=(int)$row->is_demo===1;
        echo '<tr><td>'.esc_html(mysql2date('d.m.Y',$row->appointment_date)).'<br>'.esc_html(substr($row->appointment_time,0,5)).'</td><td>'.esc_html($trainer?$trainer->post_title:'Antrenör profili bulunamadı').'<br>'.esc_html($row->session_type==='group'?'Grup dersi — '.$row->group_size.' kişi':'Bireysel ders').'</td><td><strong>'.esc_html($row->full_name).($demo?' · DEMO':'').'</strong><br><a href="mailto:'.esc_attr($row->email).'">'.esc_html($row->email).'</a><br>'.esc_html($row->phone).'</td><td>'.esc_html($row->note).'</td><td><strong>'.esc_html($labels[$row->status]??$row->status).'</strong>';
        if ($row->status==='pending') {
            foreach (array('approved'=>'Kabul et','rejected'=>'Reddet') as $status=>$label) {
                echo '<form class="coach-appointment-decision" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_trainer_coach_decision"><input type="hidden" name="appointment_id" value="'.absint($row->id).'"><input type="hidden" name="status" value="'.esc_attr($status).'">';
                wp_nonce_field('vitalis_trainer_coach_decision_'.$row->id);
                echo '<button class="button '.($status==='approved'?'button-primary':'').'" type="submit">'.esc_html($label).'</button></form>';
            }
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div><p class="form-note">DEMO etiketli kayıtlar örnek takvim verisidir; karar düğmeleri panel akışını göstermek içindir ve örnek kayıtlarda e-posta gönderilmez.</p></section>';
}

function vitalis_trainer_coach_decision() {
    if (!is_user_logged_in()) { wp_safe_redirect(home_url('/uye-girisi/')); exit; }
    $id=absint(vitalis_core_request_value($_POST,'appointment_id',0));
    check_admin_referer('vitalis_trainer_coach_decision_'.$id);
    $status=sanitize_key(vitalis_core_request_value($_POST,'status'));
    if (!in_array($status,array('approved','rejected'),true)) { wp_safe_redirect(add_query_arg('antrenor_randevu','hata',home_url('/antrenor-paneli/'))); exit; }
    global $wpdb; $table=vitalis_core_trainer_appointments_table();
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND status='pending'",$id));
    $trainer=$row?get_post((int)$row->trainer_id):false;
    $assigned=$trainer?absint(get_post_meta($trainer->ID,'vitalis_coach_user_id',true)):0;
    $authorized=$row && $trainer && (current_user_can('manage_options') || (function_exists('vitalis_core_is_coach') && vitalis_core_is_coach() && $assigned===get_current_user_id()));
    if (!$authorized) { wp_safe_redirect(add_query_arg('antrenor_randevu','hata',home_url('/antrenor-paneli/'))); exit; }
    if ($status==='approved') {
        $valid_time=in_array(substr($row->appointment_time,0,5),vitalis_trainer_times_for_date($trainer->ID,$row->appointment_date),true);
        $conflict=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE trainer_id=%d AND appointment_date=%s AND appointment_time=%s AND id<>%d AND status IN ('pending','approved')",$trainer->ID,$row->appointment_date,$row->appointment_time,$id));
        if (!vitalis_trainer_date_allowed($row->appointment_date) || !$valid_time || $conflict) { wp_safe_redirect(add_query_arg('antrenor_randevu','hata',home_url('/antrenor-paneli/'))); exit; }
    }
    $updated=$wpdb->update($table,array('status'=>$status),array('id'=>$id,'status'=>'pending'),array('%s'),array('%d','%s'));
    if ($updated && (int)$row->is_demo!==1) {
        $label=$status==='approved'?'kabul edildi':'reddedildi';
        wp_mail($row->email,'Vitalis antrenör randevu talebiniz '.$label,"Antrenör: {$trainer->post_title}\nTarih: {$row->appointment_date}\nSaat: ".substr($row->appointment_time,0,5)."\n\nRandevu talebiniz antrenör tarafından $label.");
    }
    wp_safe_redirect(add_query_arg('antrenor_randevu',$updated?'guncellendi':'hata',home_url('/antrenor-paneli/'))); exit;
}
add_action('admin_post_vitalis_trainer_coach_decision','vitalis_trainer_coach_decision');

function vitalis_trainer_admin_appointments() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $table=vitalis_core_trainer_appointments_table();
    $rows=$wpdb->get_results("SELECT * FROM $table ORDER BY appointment_date DESC, appointment_time ASC LIMIT 500");
    $labels=array('pending'=>'Antrenör kararı bekleniyor','approved'=>'Kabul edildi','rejected'=>'Reddedildi','cancelled'=>'İptal edildi');
    echo '<div class="wrap vitalis-admin-home"><h1>Antrenör randevu talepleri</h1><p class="vitalis-admin-intro">Bekleyen ve kabul edilen randevular uygunluk takviminde dolu görünür. Atanan antrenör kendi panelinden talebi kabul veya reddeder. DEMO etiketli satırlar örnek veridir.</p><table class="widefat striped"><thead><tr><th>Tarih / saat</th><th>Antrenör</th><th>Ders</th><th>İletişim</th><th>Not / kayıt türü</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="7">Henüz randevu talebi yok.</td></tr>'; }
    foreach ((array)$rows as $row) {
        $trainer=get_post((int)$row->trainer_id); $demo=(int)$row->is_demo===1;
        echo '<tr><td>'.esc_html(mysql2date('d.m.Y',$row->appointment_date)).'<br>'.esc_html(substr($row->appointment_time,0,5)).'</td><td>'.esc_html($trainer?$trainer->post_title:'Silinmiş antrenör').'</td><td>'.esc_html($row->session_type==='group'?'Grup ('.$row->group_size.' kişi)':'Bireysel').'</td><td><strong>'.esc_html($row->full_name).'</strong><br><a href="mailto:'.esc_attr($row->email).'">'.esc_html($row->email).'</a><br>'.esc_html($row->phone).'</td><td>'.($demo?'<strong>DEMO KAYDI</strong><br>':'').esc_html($row->note).'</td><td>'.esc_html($labels[$row->status]??$row->status).'</td><td>';
        if (!$demo && $row->status!=='cancelled') {
            foreach (array('approved'=>'Onayla','cancelled'=>'İptal et') as $status=>$action_label) {
                echo '<form style="display:inline-block;margin:0 5px 5px 0" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vitalis_trainer_appointment_status"><input type="hidden" name="appointment_id" value="'.absint($row->id).'"><input type="hidden" name="status" value="'.esc_attr($status).'">';
                wp_nonce_field('vitalis_trainer_appointment_status_'.$row->id);
                echo '<button class="button" type="submit">'.esc_html($action_label).'</button></form>';
            }
        } else { echo $demo?'<span class="description">Örnek kayıt</span>':'—'; }
        echo '</td></tr>';
    }
    echo '</tbody></table><p class="description">Randevu talebi gönderildiğinde saat antrenör karar verene kadar geçici olarak kapanır. Kabul edilen randevu kesinleşir; çevrimiçi ödeme alınmaz.</p></div>';
}

function vitalis_trainer_appointment_status() {
    if (!current_user_can('manage_options')) { wp_die('Bu işlem için yetkiniz yok.'); }
    $id=absint(vitalis_core_request_value($_POST,'appointment_id',0));
    check_admin_referer('vitalis_trainer_appointment_status_'.$id);
    $status=sanitize_key(vitalis_core_request_value($_POST,'status'));
    if (!in_array($status,array('approved','cancelled'),true)) { wp_die('Durum geçersiz.'); }
    global $wpdb; $table=vitalis_core_trainer_appointments_table();
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND is_demo=0",$id));
    if ($row) {
        $wpdb->update($table,array('status'=>$status),array('id'=>$id),array('%s'),array('%d'));
        if ($status==='approved') { wp_mail($row->email,'Vitalis antrenör randevu talebiniz onaylandı','Antrenör randevu talebiniz kulüp tarafından onaylandı. Ödeme ve kesin ders planı için kulüp ekibinden ayrıca bilgi alınız.'); }
    }
    wp_safe_redirect(admin_url('admin.php?page=vitalis-trainer-appointments&updated=1')); exit;
}
add_action('admin_post_vitalis_trainer_appointment_status','vitalis_trainer_appointment_status');

function vitalis_trainer_front_assets() {
    if (!is_singular('vitalis_trainer')) { return; }
    wp_enqueue_style('vitalis-trainer-profile',get_template_directory_uri().'/assets/css/trainer-profile.css',array('vitalis-main'),'1.14.0');
    wp_enqueue_script('vitalis-trainer-calendar',get_template_directory_uri().'/assets/js/trainer-calendar.js',array(),'1.14.0',true);
    wp_localize_script('vitalis-trainer-calendar','VitalisTrainerCalendar',array(
        'ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('vitalis_trainer_calendar'),
        'trainerId'=>get_queried_object_id(),'monthNames'=>array('Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'),
        'weekdays'=>array('Pzt','Sal','Çar','Per','Cum','Cmt','Paz'),'loading'=>'Takvim yükleniyor…','error'=>'Takvim bilgisi alınamadı. Sayfayı yenileyip tekrar deneyin.',
        'today'=>wp_date('Y-m-d'),'maxMonth'=>vitalis_trainer_max_month()
    ));
}
add_action('wp_enqueue_scripts','vitalis_trainer_front_assets',30);
