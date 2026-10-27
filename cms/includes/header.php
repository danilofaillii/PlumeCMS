<?php
// $page_title, $page_desc, $page_canonical devono essere definiti prima dell'include
$title = isset($page_title) ? h($page_title) . ' | ' . SITE_NAME : SITE_NAME;
$desc  = isset($page_desc) ? h($page_desc) : SITE_DESC;
$canonical = isset($page_canonical) ? h($page_canonical) : site_url($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <meta name="description" content="<?= $desc ?>">
  <link rel="canonical" href="<?= $canonical ?>">
  <meta property="og:type" content="<?= isset($og_type) ? $og_type : 'website' ?>">
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= $desc ?>">
  <meta property="og:url" content="<?= $canonical ?>">
  <meta property="og:site_name" content="<?= SITE_NAME ?>">
  <?php if (!empty($og_image)): ?><meta property="og:image" content="<?= h($og_image) ?>"><?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght,ital@9..144,400;9..144,500;9..144,600;9..144,400,1&family=Caveat:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= site_url('assets/css/style.css') ?>">
?>
</head>
<body>
<header class="site-header">
  <nav class="nav-container">
    <a href="<?= site_url() ?>" class="logo">
      <strong>Danilo Failli</strong>
      <span>Deep Office Coach</span>
    </a>
    <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
    <div class="nav-links">
      <a href="<?= site_url('blog/') ?>">Blog</a>
      <a href="<?= site_url('laser-coaching/') ?>">Laser Coaching</a>
      <a href="<?= site_url('percorso-doc/') ?>">Percorso D.O.C.</a>
      <a href="<?= site_url('ebook/') ?>" class="cta-nav">Ebook Gratuito</a>
    </div>
  </nav>
</header>
<main>
