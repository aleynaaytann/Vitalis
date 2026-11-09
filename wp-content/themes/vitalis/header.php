<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<header class="site-header"><div class="container header-inner">
<a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-mark">V</span><span>VITALIS<small>SPORTS &amp; WELLNESS CLUB</small></span></a>
<button class="mobile-toggle" aria-expanded="false" aria-controls="primary-nav">Menü ☰</button>
<nav id="primary-nav" class="main-nav" aria-label="Ana Menü">
<?php if (has_nav_menu('primary')) { wp_nav_menu(array('theme_location'=>'primary','container'=>false,'items_wrap'=>'%3$s')); } else { ?>
<a href="<?php echo esc_url(home_url('/')); ?>">Ana Sayfa</a><a href="#hakkimizda">Hakkımızda</a><a href="#tesislerimiz">Tesislerimiz</a><a href="#hizmetlerimiz">Hizmetlerimiz</a><a href="<?php echo esc_url(home_url('/uyelik/')); ?>">Üyelik</a><a href="<?php echo esc_url(home_url('/duyurular/')); ?>">Etkinlikler</a><a href="#iletisim">İletişim</a>
<?php } ?>
</nav><a class="btn header-cta" href="<?php echo esc_url(home_url('/randevu/')); ?>">Üyelik Başvurusu →</a>
</div></header>
