<?php
get_header();
while (have_posts()) {
    the_post();
    $slug = get_post_field('post_name', get_the_ID());
    if (get_post_meta(get_the_ID(),'vitalis_editable_page_content',true)) {
        $editable_class = $slug === 'anasayfa' ? 'vitalis-editable-page vitalis-editable-home' : 'vitalis-editable-page';
        echo '<main class="'.esc_attr($editable_class).' vitalis-editable-page--'.esc_attr(sanitize_html_class($slug)).'">';
        the_content();
        echo '</main>';
        continue;
    }
    $custom = vitalis_page_content($slug);
    if ($custom) {
        echo $custom;
        if (function_exists('vitalis_core_render_hygiene')) { vitalis_core_render_hygiene($slug); }
        $managed_gallery_index = $slug === 'galeri' && function_exists('vitalis_core_render_gallery_index') && vitalis_core_render_gallery_index();
        $managed_trainers = $slug === 'antrenorler' && function_exists('vitalis_core_render_trainers');
        $managed_events = $slug === 'etkinlikler' && function_exists('vitalis_core_render_events');
        if ($managed_trainers) { vitalis_core_render_trainers(); }
        if ($managed_events) { vitalis_core_render_events(); }
        if ($slug === 'rezervasyon' && function_exists('vitalis_core_booking_form')) { vitalis_core_booking_form(); }
        if ($slug === 'galeri' && !$managed_gallery_index) {
            ?>
            <section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">VITALIS’TEN KARELER</span><h2>Alanlarımızı keşfedin</h2><p>Bir karta tıklayarak ilgili alanın tanıtımını ve tüm fotoğraf galerilerini açın.</p></div></div><div class="gallery-grid gallery-index-grid">
                <?php foreach (array(array('club.jpg','Kulüp binamız','/hakkimizda/'),array('gym.jpg','Fitness salonu','/fitness-salonu/'),array('pool.jpg','Yüzme havuzu','/yuzme-havuzu/'),array('court.jpg','Spor sahaları','/spor-sahalari/'),array('studio.jpg','Grup dersleri','/grup-dersleri/'),array('yoga.jpg','Wellness alanı','/wellness/')) as $photo): ?>
                    <a href="<?php echo esc_url(home_url($photo[2].'#alan-galerisi')); ?>"><img src="<?php echo vitalis_img($photo[0]); ?>" alt="<?php echo esc_attr($photo[1]); ?>"><figcaption><?php echo esc_html($photo[1]); ?><span class="gallery-more">Daha fazla görseli gör →</span></figcaption></a>
                <?php endforeach; ?>
            </div></section>
            <?php
        }
        if ($slug === 'fitness-salonu') {
            ?><section class="wrap section-action"><a class="btn" href="<?php echo esc_url(home_url('/antrenorler/')); ?>">Kişisel antrenörleri keşfet →</a></section><?php
        }
        if ($slug === 'tesislerimiz') {
            ?>
            <section class="wrap content-section"><div class="facility-grid facility-listing">
            <?php foreach (array(array('Fitness Salonu','gym.jpg','Kardiyo, kuvvet ve serbest ağırlık alanları.','fitness-salonu'),array('Yüzme Havuzu','pool.jpg','Kapalı havuz ve yüzme dersleri hakkında bilgi alın.','yuzme-havuzu'),array('Spor Sahaları','court.jpg','Tenis kortumuz ve saha kiralama bilgisi.','spor-sahalari'),array('Grup Dersleri','studio.jpg','Pilates, yoga ve kondisyon dersleri.','grup-dersleri'),array('Wellness Alanı','yoga.jpg','Dinlenme ve iyi yaşam alanları.','wellness'),array('Soyunma Odaları','locker-room-1.jpg','Dolap, duş ve lavabo alanlarımızı fotoğraflarla keşfedin.','soyunma-odalari'),array('Beslenme Danışmanlığı','studio.jpg','Hedeflerinize uygun danışmanlık görüşmesi.','beslenme')) as $facility): ?>
                <a class="facility-card" href="<?php echo esc_url(home_url('/'.$facility[3].'/')); ?>"><img src="<?php echo vitalis_img($facility[1]); ?>" alt="<?php echo esc_attr($facility[0]); ?>"><h3><?php echo esc_html($facility[0]); ?> <span>→</span></h3><p><?php echo esc_html($facility[2]); ?></p></a>
            <?php endforeach; ?></div><p class="section-action"><a class="btn" href="<?php echo esc_url(home_url('/rezervasyon/')); ?>">Saha veya grup talebi gönder →</a></p></section>
            <?php
        }
        if ($slug === 'uyelik') {
            ?>
            <section class="wrap content-section"><div class="membership-detail"><div class="section-heading"><div><span class="eyebrow">ÜYELİK PAKETLERİ</span><h2>Sana uygun paketi seç</h2><p>Paket kapsamı ve güncel koşullar için başvuru gönderin.</p></div></div><div class="plans">
            <?php foreach (array(array('Aylık Üyelik','₺1.000','/ ay'),array('6 Aylık Üyelik','₺5.000',''),array('Yıllık Üyelik','₺9.000','')) as $i=>$plan): ?>
                <article class="plan <?php echo $i===1?'popular':''; ?>"><?php if($i===1): ?><span class="plan-tag">En Popüler</span><?php endif; ?><h3><?php echo esc_html($plan[0]); ?></h3><strong><?php echo esc_html($plan[1]); ?><small><?php echo esc_html($plan[2]); ?></small></strong><ul><li>Fitness salonu kullanımı</li><li>Yüzme havuzu kullanımı</li><li>Grup derslerine katılım</li><li>Soyunma odası ve duş alanı</li><li>Üyelik süresince tesis kullanımı</li></ul><a href="<?php echo esc_url(add_query_arg('paket',$plan[0],home_url('/uye-kaydi/'))); ?>">Üyelik için başvur →</a></article>
            <?php endforeach; ?></div><p class="form-note">Ücret ve üyelik kapsamı görseldeki örnek pakete göre listelenmiştir; satın alma öncesinde kulüpten güncel bilgiyi teyit edin. Bu sayfadan çevrimiçi ödeme alınmaz.</p></div></section>
            <?php
        }
        if ($slug === 'uyelik' && function_exists('vitalis_core_member_links')) { vitalis_core_member_links(); }
        if ($slug === 'etkinlikler' && !$managed_events) {
            $events = array(
                array('24 EKİM 2026 • CUMARTESİ','10:00–13:00','Vitalis ile Tanışma Günü','Tesis turu, antrenörlerle kısa hedef görüşmesi ve tanışma derslerinden oluşan açık kulüp günü. İlk kez gelecek yetişkinler ve aileler için tanıtım akışı; rahat spor kıyafeti önerilir.','Kulüp ana girişi ve stüdyo alanı','Ücretsiz • Kayıt talebi gerekir','40 kişi','studio.jpg'),
                array('01 KASIM 2026 • PAZAR','10:00–12:00','Grup Dersleri Deneme Buluşması','Pilates, mobilite ve düşük tempolu kondisyon derslerinden oluşan başlangıç seviyesi tanışma akışı. Her bölüm öncesi eğitmen hareket seçeneklerini anlatır; deneyim şartı yoktur.','Grup stüdyosu • Vitalis Nilüfer','Üye ücretsiz • Misafir ₺350','24 kişi','event-fitness.jpg'),
                array('08 KASIM 2026 • PAZAR','11:00–13:30','Nefes, Esneme ve Denge Atölyesi','Günlük hareketliliği destekleyen nefes farkındalığı, esneme ve denge çalışması. Atölye yeni başlayanlara uygundur; matlar kulüpte sağlanır. Sağlık durumunu kayıt sırasında belirtin.','Wellness stüdyosu','Üye ₺450 • Misafir ₺650','18 kişi','yoga.jpg'),
                array('14 KASIM 2026 • CUMARTESİ','09:00–13:00','Vitalis Tenis Çiftler Buluşması','Dostça formatta çiftler turnuvası; kısa grup aşaması, ardından final karşılaşması. Raketini getirebilir veya kayıt sırasında ekipman isteği bırakabilirsin. Katılım seviyeye göre eşleştirilir.','Açık tenis sahası','Oyuncu başı ₺450 • Su ikramı dahil','16 çift','event-tennis.jpg'),
                array('21 KASIM 2026 • CUMARTESİ','14:00–16:00','Aile Yüzme ve Suya Alışma Saati','Ebeveyn eşliğinde suya alışma ve güvenli havuz rutini tanıtımı. Çocuklar yetişkin refakatiyle katılır; yüzme dersi veya cankurtaran gözetimi yerine geçmez. Havuz kuralları kayıt öncesi paylaşılır.','Kapalı havuz • Vitalis Nilüfer','Katılımcı başı ₺400','12 aile','event-pool.jpg')
            );
            ?>
            <section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">KULÜPTE YAŞAM</span><h2>Etkinlik takvimi</h2><p>Her buluşmanın saati, mekânı, hedef kitlesi, kapasitesi ve katılım bedeli aşağıda yer alıyor. Kayıt bağlantısı ekibe talep iletir; yer ayırma, ödeme veya kesin katılım ancak kulübün ayrıca teyidiyle tamamlanır.</p></div></div><div class="event-notice"><strong>Örnek program ve ücret bilgilendirmesi</strong><p>Bu takvim, tema kurulumu için hazırlanmış örnek içeriktir; gerçek Vitalis etkinlik takvimi, kontenjanı ve ücretleri işletme tarafından doğrulanmalıdır. Form ödeme almaz ve otomatik rezervasyon oluşturmaz.</p></div><div class="event-list">
            <?php foreach ($events as $event): $msg='Etkinlik katılım talebi: '.$event[2].' | Tarih: '.$event[0].' | Saat: '.$event[1].' | Yer: '.$event[4].' | Katılım: '.$event[5].'. Lütfen gerçek tarih, ücret ve kontenjanı teyit edip kayıt talebimi değerlendiriniz.'; $url=add_query_arg(array('konu'=>'Etkinlik katılımı','mesaj'=>$msg),home_url('/rezervasyon/')); ?>
                <article class="event-card"><img src="<?php echo vitalis_img($event[7]); ?>" alt="<?php echo esc_attr($event[2]); ?>"><div class="event-copy"><span class="event-date"><?php echo esc_html($event[0]); ?></span><h3><?php echo esc_html($event[2]); ?></h3><p><?php echo esc_html($event[3]); ?></p><div class="event-facts"><span><b>Saat</b><?php echo esc_html($event[1]); ?></span><span><b>Yer</b><?php echo esc_html($event[4]); ?></span><span><b>Katılım</b><?php echo esc_html($event[5]); ?></span><span><b>Kontenjan</b><?php echo esc_html($event[6]); ?></span></div><a class="btn" href="<?php echo esc_url($url); ?>">Bu etkinlik için talep bırak →</a></div></article>
            <?php endforeach; ?></div></section>
            <?php
        }
        if ($slug === 'antrenorler' && !$managed_trainers) {
            $coaches = array(
                array('coach-strength.jpg','Kuvvet & kondisyon koçu','Kuvvet temeli, güvenli ekipman kullanımı ve hedef odaklı salon planı. İlk görüşmede hareket geçmişi ve hedefler değerlendirilir; seans seviyesi buna göre uyarlanır.','Yeni başlayanlar • kuvvet gelişimi • kondisyon','55 dk birebir / salon'),
                array('coach-pilates.jpg','Pilates & hareket eğitmeni','Reformer ve mat çalışmalarında merkez bölge kontrolü, mobilite ve dengeli hareket alışkanlığı. Ders öncesi hareket kısıtları ve deneyim seviyesi konuşulur.','Mobilite • postür farkındalığı • başlangıç seviyesi','50 dk birebir veya küçük grup'),
                array('coach-swim.jpg','Yüzme eğitmeni','Suya alışmadan teknik gelişime kadar seviye odaklı yüzme seansları. Grup ve birebir formatlar için yaş, seviye ve havuz saatleri ön görüşmede belirlenir.','Çocuk ve yetişkin • başlangıç • teknik geliştirme','45 dk birebir / seviye grubu')
            );
            ?>
            <section class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">UZMAN DESTEĞİ</span><h2>Hedefine eşlik eden antrenör kadrosu</h2><p>Her profil kartında çalışma alanı, kime uygun olduğu ve seans formatı bulunur. Görseller ve profil metinleri tanıtım amaçlı örnek içeriktir; gerçek ekip ve eğitmen uygunluğu işletme tarafından teyit edilmelidir.</p></div></div><div class="trainer-grid trainer-profiles">
            <?php foreach ($coaches as $coach): $message='Antrenör görüşme talebi: '.$coach[1].' için '.$coach[4].' formatında seans ve uygunluk bilgisi almak istiyorum. Lütfen güncel eğitmen, fiyat ve programı teyit ediniz.'; $url=add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>$message),home_url('/rezervasyon/')); ?>
                <article><img src="<?php echo vitalis_img($coach[0]); ?>" alt="<?php echo esc_attr($coach[1]); ?> portre"><div class="coach-copy"><span class="coach-tag">VITALIS ANTRENÖR EKİBİ</span><h3><?php echo esc_html($coach[1]); ?></h3><p><?php echo esc_html($coach[2]); ?></p><dl><div><dt>Odak alanı</dt><dd><?php echo esc_html($coach[3]); ?></dd></div><div><dt>Seans biçimi</dt><dd><?php echo esc_html($coach[4]); ?></dd></div></dl><a href="<?php echo esc_url($url); ?>">Görüşme talep et →</a></div></article>
            <?php endforeach; ?></div></section>
            <section class="wrap content-section coach-pricing"><div class="section-heading"><div><span class="eyebrow">SEANS VE PAKETLER</span><h2>Ücretler ve paket içeriği</h2><p>Ücret, seans süresi ve geçerlilik birlikte listelenmiştir; her paketin kapsamı açıklanmıştır.</p></div></div><div class="pricing-grid">
                <article class="price-card"><span>KUVVET & KONDİSYON</span><h3>Birebir antrenman</h3><strong>₺1.200 <small>/ 55 dk</small></strong><p>Tek seans • kişiye özel çalışma akışı • ilk hedef değerlendirmesi.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'Kuvvet ve kondisyon birebir seansını soruyorum. Lütfen güncel fiyat, eğitmen uygunluğu ve paket koşullarını teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Seans sor →</a></article>
                <article class="price-card"><span>PAKET AVANTAJI</span><h3>8 seanslık PT paketi</h3><strong>₺8.800 <small>/ 8 × 55 dk</small></strong><p>45 gün kullanım süresi • haftalık planlama • temel ilerleme takibi.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'8 seanslık kuvvet ve kondisyon paketini soruyorum. Lütfen güncel ücret, geçerlilik ve koşulları teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Paketi sor →</a></article>
                <article class="price-card"><span>REFORMER PİLATES</span><h3>Küçük grup paketi</h3><strong>₺4.400 <small>/ 8 ders</small></strong><p>En çok 4–8 kişilik grup • 30 gün kullanım • başlangıç seviye tanışma.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'Reformer küçük grup 8 ders paketini soruyorum. Lütfen grup mevcudu, güncel ders saati ve fiyatı teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Dersleri sor →</a></article>
                <article class="price-card"><span>YÜZME DERSİ</span><h3>Birebir teknik seans</h3><strong>₺1.100 <small>/ 45 dk</small></strong><p>Seviye tespiti • teknik odaklı çalışma • havuz saatine göre planlama.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'Birebir yüzme seansı hakkında bilgi istiyorum. Yüzücü yaşı/seviyesi: . Lütfen güncel ücret ve uygun saatleri teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Seans sor →</a></article>
                <article class="price-card"><span>ÖZEL PİLATES</span><h3>8 seanslık birebir</h3><strong>₺8.500 <small>/ 8 × 50 dk</small></strong><p>30 gün kullanım • kişisel hareket uyarlaması • ders öncesi kısa değerlendirme.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'8 seanslık birebir Pilates paketini soruyorum. Lütfen güncel fiyat ve paket koşullarını teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Paketi sor →</a></article>
                <article class="price-card"><span>YÜZMEYE BAŞLANGIÇ</span><h3>4 derslik küçük grup</h3><strong>₺2.800 <small>/ 4 × 45 dk</small></strong><p>4 hafta • seviye grubu • katılımcı yaşı ve saat seçimi ön görüşmede netleşir.</p><a href="<?php echo esc_url(add_query_arg(array('konu'=>'Antrenör ve seans ücretleri','mesaj'=>'4 derslik küçük grup yüzme başlangıç paketini soruyorum. Lütfen yaş grubu, güncel ücret ve ders saatlerini teyit ediniz.'),home_url('/rezervasyon/'))); ?>">Paketi sor →</a></article>
            </div><p class="form-note">Örnek liste fiyatlarıdır; Vitalis’in doğrulanmış satış tarifesi değildir. Tutarlar, Bursa’da yayımlanan benzer hizmet paketlerini referans alan örnek içeriktir. Gerçek satış öncesinde kulüp tarafından güncellenmeli; vergi, iptal ve dondurma koşulları teyit edilmelidir. Form ödeme almaz.</p></section>
            <?php
        }
        $facility_galleries = array(
            'hakkimizda'=>array('Kulüp binamız',array(array('club.jpg','Kulüp binası • dış görünüm'),array('detail-club-1.jpg','Kulüp binası • peyzajlı giriş'),array('detail-club-2.jpg','Kulüp binası • karşılama alanı'))),
            'fitness-salonu'=>array('Fitness salonu',array(array('gym.jpg','Fitness salonu • ağırlık alanı'),array('detail-fitness-1.jpg','Fitness salonu • kuvvet antrenmanı'),array('detail-fitness-2.jpg','Fitness salonu • kardiyo alanı'))),
            'yuzme-havuzu'=>array('Yüzme havuzu',array(array('pool.jpg','Kapalı havuz • genel görünüm'),array('detail-pool-1.jpg','Kapalı havuz • yüzme kulvarları'),array('detail-pool-2.jpg','Kapalı havuz • dinlenme bölümü'))),
            'spor-sahalari'=>array('Spor sahaları',array(array('court.jpg','Spor sahası • çok amaçlı alan'),array('detail-courts-1.jpg','Spor sahası • salon genel görünümü'),array('detail-courts-2.jpg','Spor sahası • saha kenarı'))),
            'grup-dersleri'=>array('Grup dersleri',array(array('studio.jpg','Grup dersi • stüdyo'),array('detail-group-1.jpg','Grup dersi • Pilates çalışması'),array('detail-group-2.jpg','Grup dersi • yoga ve mobilite'))),
            'wellness'=>array('Wellness alanı',array(array('yoga.jpg','Wellness • esneme alanı'),array('detail-wellness-1.jpg','Wellness • sauna'),array('detail-wellness-2.jpg','Wellness • dinlenme alanı'))),
            'soyunma-odalari'=>array('Soyunma odaları',array(array('locker-room-1.jpg','Soyunma alanı • kişisel dolaplar'),array('locker-room-2.jpg','Duş alanı • konsept görsel'),array('locker-room-3.jpg','Lavabo ve aynalar • konsept görsel'),array('locker-room-4.jpg','Dolap koridoru • konsept görsel')))
        );
        $managed_facility_gallery = function_exists('vitalis_core_facility_gallery') && vitalis_core_facility_gallery($slug);
        if (!$managed_facility_gallery && isset($facility_galleries[$slug])) {
            $gallery = $facility_galleries[$slug];
            ?>
            <section id="alan-galerisi" class="wrap content-section facility-photo-section"><div class="section-heading"><div><span class="eyebrow">ALAN GALERİSİ</span><h2><?php echo esc_html($gallery[0]); ?> fotoğrafları</h2><p>Fotoğraflardan birine tıklayarak büyük görünümde açın; oklarla bu alandaki diğer fotoğraflara geçin.</p></div></div><div class="gallery-grid detail-gallery" data-gallery-set="<?php echo esc_attr($slug); ?>">
                <?php foreach ($gallery[1] as $photo): $url=vitalis_img($photo[0]); ?>
                    <a href="<?php echo $url; ?>" data-vitalis-lightbox><img src="<?php echo $url; ?>" alt="<?php echo esc_attr($photo[1]); ?>" loading="lazy"><figcaption><?php echo esc_html($photo[1]); ?><span class="gallery-more">Büyütmek için tıkla ↗</span></figcaption></a>
                <?php endforeach; ?>
            </div></section>
            <?php
        }
        if (trim(get_the_content())) {
            ?> <section class="wrap content-section entry-content"><?php the_content(); ?></section> <?php
        }
    } else {
        if (in_array($slug,array('uye-paneli','uye-kaydi','uye-girisi','antrenor-paneli'),true)) {
            $account_intro = array(
                'uye-paneli'=>'Üyelik bilgilerinizi ve kendi hesabınıza özel gelişim kayıtlarınızı buradan yönetin.',
                'uye-kaydi'=>'Hesabınızı oluşturun, size uygun paketi seçin ve üyelik başvurunuzu kulüp ekibine iletin.',
                'uye-girisi'=>'Kişisel üye hesabınıza güvenli biçimde giriş yapın.',
                'antrenor-paneli'=>'Size atanmış öğrencilerin gelişim notlarını güvenli biçimde yönetin.'
            );
            ?>
            <section class="account-hero"><div class="wrap"><span class="eyebrow">VITALIS ÜYE ALANI</span><h1><?php the_title(); ?></h1><p><?php echo esc_html($account_intro[$slug]); ?></p></div></section>
        <?php } else { ?>
        <section class="page-hero"><div class="wrap"><span class="eyebrow">VITALIS SPORTS & WELLNESS CLUB</span><h1><?php the_title(); ?></h1></div></section>
        <?php } ?>
        <?php if (in_array($slug,array('uye-paneli','uye-kaydi','uye-girisi','antrenor-paneli'),true)) { ?>
            <div class="member-route-content"><?php the_content(); ?></div>
        <?php } else { ?>
            <section class="wrap content-section entry-content"><?php the_content(); ?></section>
        <?php } ?>
        <?php
    }
}
get_footer();
?>
