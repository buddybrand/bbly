<?php
/* Libs */
require("config.php");
require("S3.php");

/* Initialization */
$m = new Mongo($config['mongo']['uri']);
$db = $m->selectDB($config['mongo']['db']);
$col = new MongoCollection($db, "link");

$link = $col->findOne(array("code" => $_REQUEST['target']));
if (!$link) {
	header("Location: " . $config['bbly']['fallbackUrl']);
	die();
}

/* Update Link Counter */
if (isset($link['count']) && $link['count'] > 0) {
	$link['count'] +=1;
}
else {
	$link['count'] = 1;
}

$col->update(array("code" => $_REQUEST['target']), $link);

/* Check if it is a Social Network Bot or redirect */
if (in_array($_SERVER['HTTP_USER_AGENT'], array(
	'Twitterbot/1.0',
	'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	'facebookexternalhit/1.1 (+https://www.facebook.com/externalhit_uatext.php)',
	'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
	'Mozilla/5.0 (Windows NT 6.1; rv:6.0) Gecko/20110814 Firefox/6.0 Google (+https://developers.google.com/+/web/snippet/)',
	'Google-HTTP-Java-Client/1.17.0-rc (gzip)',
	'Mozilla/5.0 (compatible; X11; Linux x86_64; Google-StructuredDataTestingTool; +http://www.google.com/webmasters/tools/richsnippets)'
)) ) {
}
else {
	/* Save the Parameters! */
	if (isset($_REQUEST['utm_source'])) {
		$redirect = $link['link'] . "?utm_source=bbly-".$link['code'] . "-". $_REQUEST['utm_source']."&utm_medium=bbly-".$link['code'] . "-". $_REQUEST['utm_medium']."&utm_content=bbly-".$link['code'] . "-". $_REQUEST['utm_content']."&utm_term=bbly-".$link['code'] . "-". $_REQUEST['utm_term']."&utm_campaign=bbly-".$link['code'] . "-". $_REQUEST['utm_campaign'];
	}
	else {
		$anchor = null;
		preg_match ('/#(.*)$/', $link['link'], $anchor);
		$redirect = $link['link'] . "?utm_source=bbly&utm_medium=redirect&utm_content=".date("Y-m-d", $link['created_at']->sec)."&utm_campaign=bbly-".$link['code'];
		if (count ($anchor)) {
			$redirect = preg_replace ('/#(.*)\?/', '?', $redirect);
			$redirect .= $anchor[0];
		}
	}
		
	$redirect = str_replace("?", "&", $redirect);
	$redirect = preg_replace('/&/', "?", $redirect, 1);
	header("Location: $redirect",TRUE,301);
	die();
}
?><!doctype html>
<html itemscope itemtype="http://schema.org/Article">
<head>
	<meta charset="utf-8">
	<title><?php echo $link['name'] ?></title>
	<meta property="og:type" content="website"/>
	<meta name="twitter:card" content="summary_large_image">
	<?php if (strlen($link['visual']) >5 ): ?>
		<meta property="og:image" content="<?php echo $link['visual'] ?>"/>
		<meta name="twitter:image" content="<?php echo $link['visual'] ?>">
		<meta name="twitter:image:src" content="<?php echo $link['visual'] ?>">
		<meta itemprop="image" content="<?php echo $link['visual'] ?>">
	<?php endif ?>
	<?php if (strlen($link['name']) >1 ): ?>
		<meta property="og:title" content="<?php echo $link['name'] ?>"/>
		<meta name="twitter:title" content="<?php echo $link['name'] ?>">
		<meta itemprop="name" content="<?php echo $link['name'] ?>">
	<?php endif ?>
	<?php if (strlen($link['copy']) >1 ): ?>
		<meta property="og:description" content="<?php echo $link['copy'] ?>" />
		<meta name="twitter:description" content="<?php echo $link['copy'] ?>">
		<meta itemprop="description" content="<?php echo $link['copy'] ?>">
		<meta name="description" content="<?php echo $link['copy'] ?>" />
	<?php endif ?>
	<meta name="twitter:url" content="http://bbly.de/<?php echo $link['code'] ?>">
	<meta property="og:url" content="http://bbly.de/<?php echo $link['code'] ?>"/>

	<style type="text/css" media="screen">
	*, body {
		font-family: Arial;
		font-size:10px;
	}
	img {
		border:none;
	}
	</style>
</head>

<body onLoad="window.location = '<?php echo $redirect; ?>'">
	<?php if (strlen($link['name']) >1 ): ?>
		<h1><?php echo $link['name'] ?></h1>
	<?php endif ?>
	<?php if (strlen($link['copy']) >1 ): ?>
		<p><?php echo $link['copy'] ?></p>
	<?php endif ?>
	<img src="<?php echo $link['visual'] ?>" />
</body>
</html>
