<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
    $trainer_id=get_the_ID();
    $image=get_the_post_thumbnail_url($trainer_id,'large');
    if (!$image) {
        $local=get_post_meta($trainer_id,'vitalis_local_image',true);
        $image=$local?vitalis_img($local):vitalis_img('coach-strength.jpg');
    }
    $specialties=get_post_meta($trainer_id,'vitalis_specialties',true);
    $session=get_post_meta($trainer_id,'vitalis_session',true);
    $education=get_post_meta($trainer_id,'vitalis_education',true);
    $certificates=preg_split('/\r\n|\r|\n/',(string)get_post_meta($trainer_id,'vitalis_certificates',true));
    $individual_detail=get_post_meta($trainer_id,'vitalis_individual_detail',true);
    $individual_fees=get_post_meta($trainer_id,'vitalis_individual_fees',true);
    $group_detail=get_post_meta($trainer_id,'vitalis_group_detail',true);
    $group_fees=get_post_meta($trainer_id,'vitalis_group_fees',true);
    $group_capacity=absint(get_post_meta($trainer_id,'vitalis_group_capacity',true));
    if ($group_capacity<2) { $group_capacity=20; }
    $availability=function_exists('vitalis_trainer_schedule')?vitalis_trainer_schedule($trainer_id):array();
    $weekday_labels=array(1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar');
    $user=wp_get_current_user();
    $message=isset($_GET['randevu'])?sanitize_key(wp_unslash($_GET['randevu'])):'';
    ?>
    <article class="trainer-profile-page">
        <section class="trainer-profile-hero"><div class="trainer-profile-wrap trainer-profile-hero-grid">
            <div class="trainer-profile-photo"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" fetchpriority="high"><span class="trainer-photo-caption">VITALIS ANTRENÖR EKİBİ</span></div>
            <div class="trainer-profile-intro"><span class="eyebrow">UZMAN DESTEĞİ · KİŞİYE ÖZEL PLAN</span><h1><?php the_title(); ?></h1>
                <?php if ($specialties) : ?><p class="trainer-specialty-line"><?php echo esc_html($specialties); ?></p><?php endif; ?>
                <div class="trainer-profile-bio"><?php echo wp_kses_post(wpautop(get_the_content())); ?></div>
                <div class="trainer-quick-facts"><div><span>Seans süresi</span><strong><?php echo esc_html($session?:'Süre görüşmede belirlenir'); ?></strong></div><div><span>Randevu yöntemi</span><strong>Takvimden saat seçin</strong></div><div><span>Onay süreci</span><strong>Antrenör kararı verir</strong></div></div>
                <a class="btn trainer-jump-link" href="#randevu-takvimi">Uygun saatleri gör ve talep bırak ↓</a>
            </div>
        </div></section>

        <div class="trainer-profile-wrap trainer-profile-content">
            <?php if (get_post_meta($trainer_id,'vitalis_demo_profile',true)) : ?>
                <aside class="trainer-demo-banner"><span aria-hidden="true">i</span><div><strong>Demo profil ve örnek eğitim kayıtları</strong><p>Bu sayfadaki eğitmen, eğitim, sertifika, fiyat ve takvim bilgileri tema demosuna aittir. Gerçek personel, diploma ve sertifika kanıtı değildir; yayından önce işletme tarafından doğrulanıp güncellenmelidir.</p></div></aside>
            <?php endif; ?>

            <section class="trainer-profile-section trainer-education-grid">
                <div class="trainer-section-heading"><span class="eyebrow">EĞİTİM VE UZMANLIK</span><h2>Bilgi birikimi, çalışma yaklaşımı</h2><p>Profil eğitim geçmişi ve yetkinlik alanları için hazırlanmıştır.</p></div>
                <div class="trainer-info-card"><h3>Eğitim geçmişi</h3><?php echo $education?wp_kses_post(wpautop(esc_html($education))):'<p>Eğitim bilgisi henüz eklenmedi. Güncel bilgi için kulüp ekibine danışın.</p>'; ?>
                    <?php if ($certificates && trim(implode('', $certificates))!=='') : ?><div class="trainer-certificates"><h3>Sertifika ve eğitim belgeleri</h3><ul><?php foreach ($certificates as $certificate) : $parts=array_map('trim',explode('|',$certificate,2)); if (!$parts[0]) { continue; } $url=isset($parts[1])?$parts[1]:''; if ($url && strpos($url,'assets/')===0) { $url=get_template_directory_uri().'/'.$url; } ?>
                        <li><?php if ($url) : ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($parts[0]); ?> <span>Belgeyi aç / indir ↗</span></a><?php else : ?><span><?php echo esc_html($parts[0]); ?></span><?php endif; ?></li>
                    <?php endforeach; ?></ul><?php if (get_post_meta($trainer_id,'vitalis_demo_profile',true)) : ?><small>Bu dosyalar görsel demo örneğidir; gerçek sertifika veya yetkinlik kanıtı olarak kullanılamaz.</small><?php endif; ?></div><?php endif; ?>
                </div>
            </section>

            <section class="trainer-profile-section">
                <div class="trainer-section-heading"><span class="eyebrow">DERS SEÇENEKLERİ</span><h2>Hedefinize uygun çalışma biçimi</h2><p>Bireysel ve grup derslerinin kapsamı, kontenjanı ve örnek ücretleri ayrı ayrı listelenir.</p></div>
                <div class="trainer-offer-grid">
                    <article class="trainer-offer-card"><div class="trainer-offer-icon">01</div><span class="trainer-offer-tag">BİREYSEL ÇALIŞMA</span><h3>Kişisel antrenman</h3><div class="trainer-offer-description"><?php echo $individual_detail?wp_kses_post(wpautop(esc_html($individual_detail))):'<p>Ders ayrıntısı için kulüp ekibiyle görüşün.</p>'; ?></div><div class="trainer-offer-bottom"><span>Ücret ve paket</span><strong><?php echo esc_html($individual_fees?:'Bilgi için iletişim kurun'); ?></strong></div><a href="#randevu-takvimi" data-session-choice="individual">Bireysel seans için saat seç →</a></article>
                    <article class="trainer-offer-card trainer-offer-featured"><div class="trainer-offer-icon">02</div><span class="trainer-offer-tag">KÜÇÜK GRUP / GRUP DERSİ</span><h3>Birlikte antrenman</h3><div class="trainer-offer-description"><?php echo $group_detail?wp_kses_post(wpautop(esc_html($group_detail))):'<p>Grup dersi ayrıntısı için kulüp ekibiyle görüşün.</p>'; ?></div><div class="trainer-offer-bottom"><span>Ücret ve paket</span><strong><?php echo esc_html($group_fees?:'Bilgi için iletişim kurun'); ?></strong></div><a href="#randevu-takvimi" data-session-choice="group">Grup dersi için saat seç →</a></article>
                </div>
            </section>

            <section class="trainer-profile-section trainer-weekly-section"><div class="trainer-section-heading"><span class="eyebrow">HAFTALIK ÇALIŞMA PLANI</span><h2>Uygunluk saatleri</h2><p>Bu saatler başlangıç uygunluğunu gösterir. Talebiniz ilgili antrenörün paneline düşer; antrenör uygunluğuna göre kabul veya reddeder.</p></div><div class="trainer-weekly-grid">
                <?php foreach ($weekday_labels as $day=>$label) : $hours=isset($availability[$day])?$availability[$day]:false; ?><div class="trainer-weekday"><span><?php echo esc_html($label); ?></span><strong><?php echo $hours?esc_html($hours['open'].' – '.$hours['close']):'Kapalı'; ?></strong></div><?php endforeach; ?>
            </div></section>

            <section id="randevu-takvimi" class="trainer-profile-section trainer-booking-section"><div class="trainer-section-heading"><span class="eyebrow">RANDEVU TAKVİMİ</span><h2>Bir gün ve saat seçin</h2><p>Her antrenörün uygunluk durumu ayrıdır. Bekleyen ve kabul edilen randevular dolu gösterilir; talebinizi antrenör kabul veya reddeder.</p></div>
                <?php if ($message) : ?><div class="trainer-form-alert <?php echo $message==='alindi'?'is-success':'is-error'; ?>" role="status"><?php echo $message==='alindi'?'Randevu talebiniz antrenöre iletildi. Antrenör kabul veya ret kararını e-posta ile bildirecek.':($message==='dolu'?'Seçtiğiniz saat az önce doldu. Takvimden başka bir saat seçin.':'Talep kaydedilemedi. Alanları kontrol edip yeniden deneyin.'); ?></div><?php endif; ?>
                <div class="trainer-booking-grid">
                    <div class="trainer-calendar-card"><div class="trainer-calendar-top"><div><span class="eyebrow">AYLIK GÖRÜNÜM</span><div class="trainer-calendar-period"><label class="calendar-sr-only" for="vitalis-trainer-month">Ay seçin</label><select id="vitalis-trainer-month" aria-label="Takvim ayı"></select><label class="calendar-sr-only" for="vitalis-trainer-year">Yıl seçin</label><select id="vitalis-trainer-year" aria-label="Takvim yılı"></select></div></div><div class="trainer-calendar-nav"><button type="button" id="vitalis-trainer-prev" aria-label="Önceki ay">←</button><button type="button" id="vitalis-trainer-next" aria-label="Sonraki ay">→</button></div></div>
                        <div class="trainer-calendar-legend"><span><i class="is-available"></i>Müsait</span><span><i class="is-partial"></i>Kısmen dolu</span><span><i class="is-full"></i>Dolu</span><span><i class="is-closed"></i>Kapalı</span></div>
                        <div class="trainer-calendar-grid" id="vitalis-trainer-calendar" aria-live="polite"><p>Takvim yükleniyor…</p></div>
                        <div class="trainer-slot-panel"><div class="trainer-slot-panel-head"><strong id="vitalis-trainer-selected-date">Önce uygun bir gün seçin</strong><span id="vitalis-trainer-slot-summary"></span></div><div class="trainer-slot-grid" id="vitalis-trainer-slots" aria-live="polite"><p>Günün saatleri burada görünür.</p></div></div>
                        <p class="trainer-calendar-demo-note"><strong>Demo takvim:</strong> Örnek dolu saatler örnek randevu kaydıdır; gerçek rezervasyon değildir.</p>
                    </div>
                    <div class="trainer-appointment-card"><span class="eyebrow">RANDEVU TALEBİ</span><h3>Seçtiğiniz saati isteyin</h3><p>Talep antrenörün paneline iletilir. Antrenör uygunluğuna göre kabul veya reddeder; ücret bilgisi ayrıca paylaşılır.</p>
                        <form class="trainer-appointment-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="vitalis_trainer_appointment_submit"><input type="hidden" name="trainer_id" value="<?php echo absint($trainer_id); ?>"><input type="hidden" name="appointment_date" id="vitalis-trainer-date"><input type="hidden" name="appointment_time" id="vitalis-trainer-time">
                            <?php wp_nonce_field('vitalis_trainer_appointment_'.$trainer_id,'vitalis_trainer_nonce'); ?>
                            <label>Ders biçimi<select name="session_type" id="vitalis-trainer-type" required><option value="individual">Bireysel ders</option><option value="group">Grup dersi</option></select></label>
                            <label class="trainer-group-size-field" hidden>Katılımcı sayısı<input type="number" name="group_size" id="vitalis-trainer-group-size" min="2" max="<?php echo esc_attr($group_capacity); ?>" value="<?php echo esc_attr(min(4,$group_capacity)); ?>"><small>Bu grup için en fazla <?php echo esc_html($group_capacity); ?> kişi. Talep ekip tarafından ayrıca teyit edilir.</small></label>
                            <label>Ad Soyad<input type="text" name="full_name" required autocomplete="name" value="<?php echo $user->exists?esc_attr($user->display_name):''; ?>"></label>
                            <label>E-posta<input type="email" name="email" required autocomplete="email" value="<?php echo $user->exists?esc_attr($user->user_email):''; ?>"></label>
                            <label>Telefon<input type="tel" name="phone" required autocomplete="tel"></label>
                            <label>Notunuz <span>(isteğe bağlı)</span><textarea name="note" rows="3" maxlength="1000" placeholder="Hedefiniz, deneyim seviyeniz veya grup hakkında kısa bilgi"></textarea></label>
                            <label class="trainer-consent"><input type="checkbox" name="consent" value="1" required><span>Randevu talebimin değerlendirilmesi için iletişim bilgilerimin kullanılmasını kabul ediyorum.</span></label>
                            <button class="btn trainer-submit-button" id="vitalis-trainer-submit" type="submit" disabled>Önce takvimden saat seçin →</button>
                            <small>Bu form ödeme almaz. Antrenör karar verene kadar aynı saat için yeni talep alınmaz; kabul edilmeyen talepte saat yeniden açılır.</small>
                        </form>
                    </div>
                </div>
            </section>
            <section class="trainer-back-link"><a href="<?php echo esc_url(home_url('/antrenorler/')); ?>">← Tüm antrenörlere dön</a><a href="<?php echo esc_url(home_url('/iletisim/')); ?>">Sorunuz mu var? İletişime geçin →</a></section>
        </div>
    </article>
<?php endwhile; get_footer(); ?>
