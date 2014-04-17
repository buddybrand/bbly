<?php
/* Header */
session_start();
header("Content-Type: text/html; charset=utf-8");

/* Libs */
require("config.php");
require("S3.php");

/* Initialization */
$s3 = new S3($config['aws']['accessKey'], $config['aws']['secretKey']);
$m = new Mongo($config['mongo']['uri']);
$db = $m->selectDB($config['mongo']['db']);
$col = new MongoCollection($db, "link");

/* Methods */
function bringmehome($message, $code) {
	header("Location: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?message=" . $message . "&code=" . $code);
}
function shorten($text, $length) {
	return (strlen($text) > $length) ? substr($text, 0,$length) . ".." : $text;
}
function resizeImage($filename) {
	$max_width = 1200;
	$max_height = 1200;
    list($orig_width, $orig_height) = getimagesize($filename);

    $width = $orig_width;
    $height = $orig_height;

    # taller
    if ($height > $max_height) {
        $width = ($max_height / $height) * $width;
        $height = $max_height;
    }

    # wider
    if ($width > $max_width) {
        $height = ($max_width / $width) * $height;
        $width = $max_width;
    }

    $image_p = imagecreatetruecolor($width, $height);

    $image = imagecreatefromjpeg($filename);

    imagecopyresampled($image_p, $image, 0, 0, 0, 0,  $width, $height, $orig_width, $orig_height);

    // return $image_p;
	imagejpeg($image_p, "output.jpg");
}

$message = array("Link updated!", "Link saved!");

/* Logic */
if (($_POST)) {
	if (isset($_POST['code'])) {
		$link = $col->findOne(array("code" => $_POST['code']));
	}
	if (isset($_POST['pass'])) {
		$_SESSION['pass'] = $_POST['pass'];
		bringmehome(99, "");
	}
	else {
		$url = "";
		if ($_FILES && strlen($_FILES['visual']['tmp_name']) > 5) {
			$uploadFile = $_FILES['visual']['tmp_name'];
			imagejpeg(imagecreatefromstring(file_get_contents($_FILES['visual']['tmp_name'])), "output.jpg");
			resizeImage("output.jpg");
			// Put our file (also with public read access)
			$filename = uniqid("bbly") . str_replace(".", "-", str_replace(" ", "", $_FILES['visual']['name'])) . ".jpg";
			if ($s3->putObjectFile("output.jpg", $config['aws']['bucketName'], $filename, S3::ACL_PUBLIC_READ, array("image/jpeg"))) {
				$url = $config['aws']['pathPrefix'] . $filename;
			} 

			unlink("output.jpg");
		}
		else {
			if (isset($link)) {
				$url = $link['visual'];
			}
		}

		$arr = array("code" => $_POST['code'], "link" => $_POST['link'], "name" => $_POST['name'], "copy" => $_POST['copy'],  "created_at" =>  new MongoDate(), "visual" => $url);
		if (isset($link)) {
			$arr['updated_at'] = new MongoDate();
			$arr['created_at'] =  $link['created_at'];
			$arr['count'] = $link['count'];
			$col->update(array("code" => $_POST['code']), $arr);
			$message = 0;
		}
		else {
			$col->insert($arr);
			$message = 1;
		}
		bringmehome($message, $_POST['code']);
	}
}
$cursor = $col->find(array(), array());
$cursor = $cursor->sort(array("created_at" => -1));
?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css" rel="stylesheet">
	<title>bbly.de v0.6</title>
</head>
<body>
	<style type="text/css" media="screen">
	.left {
		width:550px;
		float:left;
	}
	.right {
		width:550px;
		float:right;
	}
	table {
		width:100%;
	}
	tr {
		cursor:pointer;
	}
	</style>
	<div id="wrap">
		<div class="container">
			<div class="jumbotron">
				<div class="container">
					<div class="page-header">
						<h1>bbly.de</h1>
						<p>Shortlinks inklusive Open Graph, Twitter Cards, Google+ Share Preview und UTM. (v0.6)</p>
					</div>
				</div>
			</div>
			<?php if (isset($_GET['message']) && $_GET['message'] != 99 && isset($_SESSION['pass']) && $_SESSION['pass'] == $config['bbly']['adminPass']): ?>
				<div class="alert alert-success">
					<a class="close" data-dismiss="alert">×</a>
					<strong>Juhu!</strong> <?php echo $message[$_GET['message']]; ?> <a href="http://bbly.de/<?php echo $_GET['code'] ?>">http://bbly.de/<?php echo $_GET['code'] ?></a>
				</div>
			<?php endif ?>

			<?php if (isset($_SESSION['pass']) && $_SESSION['pass'] == $config['bbly']['adminPass']): ?>
				<form class="form-signin" method="post" action="" enctype="multipart/form-data">
					<h2 class="form-signin-heading">Neuer Link</h2>
					<div class="left">
						<label>*Link (Mit http://)</label>
						<input type="text" class="form-control" placeholder="Link" id="link" autofocus="" name="link">
						<br>
						<label>*Head (30 Zeichen / Aktuell: <span id="namecount">0</span>)</label>
						<input type="text" class="form-control" placeholder="Name" id="name" autofocus="" name="name" onchange="namecount();" onkeydown="namecount();" onkeyup="namecount();">
						<br>
						<label>*Copy (Desktop: 250 Zeichen / Mobile 40 Zeichen / Aktuell: <span id="copycount">0</span>)</label>
						<textarea class="form-control" name="copy" rows="4" id="copy" placeholder="Copy" onchange="copycount();" onkeyup="copycount();" onkeydown="copycount();"></textarea>
					</div>
					<div class="right">
						<label>Visual (200x200 minimum, 1200x1200 preferred, Optional)</label>
						<input type="file" class="form-control" placeholder="visual" id="visual" autofocus="" name="visual">
						<br>
						<label>*Abkürzungscode (http://bbly.de/Abkürzungscode)</label>
						<input type="text" class="form-control" placeholder="Code" autofocus="" id="code" name="code" value="<?php echo substr(uniqid(), -5); ?>">
						<br>
						<label>Alles nochmals gecheckt?</label>
						<button class="btn btn-lg btn-primary btn-block" type="submit" >Hinzufügen</button>
						<br>
						<a href="#" onclick="resetForm(); return false;">Zurücksetzen</a>
					</div>
				</form>
				<br style="clear:both;">
				
				<h2>Existierende Links</h2>
				<p>Einen auswählen, um ihn zu bearbeiten.</p>
				<table class="table table-hover">
					<tr>
						<th>Datum</th>
						<th>Link</th>
						<th>Head</th>
						<th>Copy</th>
						<th>Visual</th>
						<th>Link</th>
						<th>Aufrufe</th>
					</tr>

					<?php foreach ($cursor as $doc): ?>
						<tr  onclick="toForm(this);" data-link="<?php echo $doc['link'] ?>" data-name="<?php echo $doc['name'] ?>" data-copy="<?php echo $doc['copy'] ?>" data-visual="<?php echo $doc['visual'] ?>" data-code="<?php echo $doc['code'] ?>">
							<td><?php echo date("d.m.Y", $doc['created_at']->sec) ?></td>
							<td><a href="<?php echo $doc['link'] ?>" title="<?php echo $doc['link'] ?>"><?php echo (strlen($doc['link']) > 30) ? substr($doc['link'], 0, 30) . ".." : $doc['link'] ?></a></td>
							<td title="<?php echo $doc['name'] ?>"><?php echo $doc['name'] ?></td>
							<td title="<?php echo $doc['copy'] ?>"><?php echo shorten($doc['copy'], 250) ?></td>
							<td><img src="<?php echo $doc['visual'] ?>" style="width:100px;"/></td>
							<td><a href="http://bbly.de/<?php echo $doc['code'] ?>" title="<?php echo $doc['code'] ?>">http://bbly.de/<?php echo shorten($doc['code'], 10) ?></a></td>
							<td><?php echo (isset($doc['count'])) ? $doc['count'] :0 ?></td>
						</tr>
					<?php endforeach ?>
				</table>

			<?php else: ?>

				<form class="form-signin" method="post" action="" enctype="multipart/form-data">
					<h2 class="form-signin-heading">Login</h2>
					<label>Passwort</label>
					<input type="password" class="form-control" placeholder="Passwort" autofocus="" name="pass">
					<br>

					<button class="btn btn-lg btn-primary btn-block" type="submit">Login</button>
				</form>
			<?php endif ?>

		</div>
	</div>
	<script type="text/javascript" charset="utf-8">
	function toForm(tr) {
		$("#link").val($(tr).attr("data-link"))
		$("#copy").val($(tr).attr("data-copy"))
		$("#name").val($(tr).attr("data-name"))
		$("#code").val($(tr).attr("data-code"))
		window.scrollTo(0,0);
	}
	
	function resetForm() {
		$("#link").val("")
		$("#copy").val("")
		$("#name").val("")
		$("#code").val("")
	}
	
	function namecount() {
		$("#namecount").text($("#name").val().length);
	}
	
	function copycount() {
		$("#copycount").text($("#copy").val().length);
	}
	</script>
	<br><br>
	<div id="footer">
		<div class="container">
			<p class="text-muted credit">bbly.de - &copy; <a taget="_blank" href="http://buddybrand.com">buddybrand GmbH</a> </p>
		</div>
	</div>
	<script src="//code.jquery.com/jquery-1.10.2.min.js"></script>
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js"></script>
</body>
</html>
