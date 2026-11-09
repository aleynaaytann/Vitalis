<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
$vitalis_staff_portal = is_user_logged_in() && (current_user_can('manage_options') || (function_exists('vitalis_core_is_coach') && vitalis_core_is_coach()));
?>
<header class="site-header">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-mark">V</span><span class="brand-text">VITALIS<small>SPORTS & WELLNESS CLUB</small></span></a>
    <button class="menu-toggle" aria-expanded="false" aria-label="Menüyü aç">☰</button>
    <nav class="main-nav">
        <a href="<?php echo esc_url(home_url('/')); ?>">Ana Sayfa</a>
        <a href="<?php echo esc_url(home_url('/hakkimizda/')); ?>">Hakkımızda</a>
        <a href="<?php echo esc_url(home_url('/tesislerimiz/')); ?>">Tesislerimiz</a>
        <a href="<?php echo esc_url(home_url('/fitness-salonu/')); ?>">Hizmetlerimiz</a>
        <a href="<?php echo esc_url(home_url('/antrenorler/')); ?>">Antrenörler</a>
        <a href="<?php echo esc_url(home_url('/uyelik/')); ?>">Üyelik</a>
        <a href="<?php echo esc_url(home_url('/etkinlikler/')); ?>">Etkinlikler</a>
        <a href="<?php echo esc_url(home_url('/galeri/')); ?>">Galeri</a>
        <a class="nav-reservation" href="<?php echo esc_url(home_url('/rezervasyon/')); ?>">Rezervasyon</a>
        <a href="<?php echo esc_url(home_url('/iletisim/')); ?>">İletişim</a>
        <?php if ($vitalis_staff_portal) : ?><a href="<?php echo esc_url(home_url('/antrenor-paneli/')); ?>">Antrenör Paneli</a><?php endif; ?>
    </nav>
    <a class="header-cta" href="<?php echo esc_url(is_user_logged_in()?($vitalis_staff_portal?home_url('/antrenor-paneli/'):home_url('/uye-paneli/')):home_url('/uye-kaydi/')); ?>"><?php echo is_user_logged_in()?($vitalis_staff_portal?'Antrenör Paneli →':'Üye Panelim →'):'Üyelik Başvurusu →'; ?></a>
</header>
<div class="vitalis-accessibility-controls" aria-label="Erişilebilirlik yazı boyutu ayarları">
    <button type="button" class="vitalis-accessibility-toggle" aria-expanded="false" aria-controls="vitalis-font-options" aria-label="Yazı boyutu ayarlarını aç" title="Yazı boyutu ayarları">
        <span class="vitalis-toggle-open" aria-hidden="true">A</span>
        <span class="vitalis-toggle-close" aria-hidden="true">×</span>
    </button>
    <div class="vitalis-accessibility-options" id="vitalis-font-options" role="group" aria-label="Yazı boyutu ayarları">
        <span class="vitalis-accessibility-title">Yazı boyutu</span>
        <button type="button" class="vitalis-font-button" data-font-action="decrease" aria-label="Yazıyı küçült" title="Yazıyı küçült">A−</button>
        <button type="button" class="vitalis-font-button vitalis-font-reset" data-font-action="reset" aria-label="Yazı boyutunu normale getir" title="Normal boyut">A</button>
        <button type="button" class="vitalis-font-button" data-font-action="increase" aria-label="Yazıyı büyüt" title="Yazıyı büyüt">A+</button>
        <span class="vitalis-font-status" aria-live="polite" aria-atomic="true">%100</span>
    </div>
</div>
<main>
