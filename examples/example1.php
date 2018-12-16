#!/usr/bin/env php
<?php
PHP_SAPI == 'cli' or die("Please run this script using the cli sapi");
$BASE = "http://php.net";
function ce(DOMDocument $d, $name, $value, array $attrs = array(), DOMNode $to = null) {
	if ($value) {
		$n = $d->createElement($name, $value);
	} else {
		$n = $d->createElement($name);
	}
	foreach($attrs as $k => $v) {
		$n->setAttribute($k, $v);
	}
	if ($to) {
		return $to->appendChild($n);
	}
	return $n;
}
$archivefile = "archive/archive.xml";
file_exists($archivefile) or die("Can't find $archivefile, are you sure you are in phpweb/?\n");
$filename = date("Y-m-d", $_SERVER["REQUEST_TIME"]);
$count = 0;
do {
  ++$count;
  $id        = $filename. "-" . $count;
  $basename  = $id .".xml";
  $file      = "archive/entries/" . $basename;
  $xincluded = "entries/" . $basename;
  fprintf(STDOUT, "Trying $file\n");
} while (file_exists($file));
$dom = new DOMDocument("1.0", "utf-8");
$dom->formatOutput = true;
$dom->preserveWhiteSpace = false;
$item = $dom->createElementNs("http://www.w3.org/2005/Atom", "entry");
do {
	fwrite(STDOUT, "Please type in the title: ");
	$title = rtrim(fgets(STDIN));
} while(strlen($title)<3);
ce($dom, "title", $title, array(), $item);
$categories = array(
	array("frontpage"   => "PHP.net frontpage news"),
	array("releases"    => "New PHP release"),
	array("conferences" => "Conference announcement"),
	array("cfp"   => "Call for Papers"),
);
$confs = array(2, 3);
$imageRestriction = array(
	'width' => 400,
	'height' => 250
);
do {
	$catVerified = false;
	fwrite(STDOUT, "Categories:\n");
	foreach($categories as $n => $category) {
		fprintf(STDOUT, "\t%d: %-11s\t [%s]\n", $n, key($category), current($category));
	}
	fwrite(STDOUT, "Please select appropriate categories, seperated with space: ");
	$cat = explode(" ", rtrim(fgets(STDIN)));
	$cat = array_intersect(array_keys(array_values($categories)), $cat);
	$intersect = array_intersect(array(2,3), $cat);
	if ($cat && (count($intersect) < 2)) {
		break;
	}
	if (count($intersect) == 2) {
		fwrite(STDERR, "Pick either a CfP OR a conference\n");
	} else {
		fwrite(STDERR, "You have to pick at least one category\n");
	}
} while(1);
$via = $archive = $BASE . "/archive/" .date("Y", $_SERVER["REQUEST_TIME"]).".php#id" .$id;
ce($dom, "id", $archive, array(), $item);
ce($dom, "published", date(DATE_ATOM), array(), $item);
ce($dom, "updated", date(DATE_ATOM), array(), $item);
$conf = false;
foreach($cat as $keys) {
	if (in_array($keys, $confs)) {
		$conf = true;
		break;
	}
}
if ($conf) {
	/* /conferences news item */
	$href = $BASE . "/conferences/index.php";
	do {
		fwrite(STDOUT, "When does the conference start/cfp end? (strtotime() compatible syntax): ");
		$t = strtotime(fgets(STDIN));
		if ($t) {
			break;
		}
		fwrite(STDERR, "I told you I would run it through strtotime()!\n");
	} while(1);
	$item->appendChild($dom->createElementNs("http://php.net/ns/news", "finalTeaserDate", date("Y-m-d", $t)));
} else {
	$href = $BASE . "/index.php";
}
foreach($cat as $n) {
	if (isset($categories[$n])) {
		ce($dom, "category", null, array("term" => key($categories[$n]), "label" => current($categories[$n])), $item);
	}
}
ce($dom, "link", null, array("href" => "$href#id$id", "rel"  => "alternate", "type" => "text/html"), $item);
fwrite(STDOUT, "Will a picture be accompanying this entry? ");
$yn = fgets(STDIN);
if (strtoupper($yn[0]) == "Y") {
	$isValidImage = false;
	do {
		fwrite(STDOUT, "Enter the image name (note: the image has to exist in './images/news'): ");
		$path = basename(rtrim(fgets(STDIN)));
		if (true === file_exists("./images/news/$path")) {
			$isValidImage = true;
			if (true === extension_loaded('gd')) {
				$imageSizes = getimagesize("./images/news/$path");
				if (
					$imageSizes[0] > $imageRestriction['width'] ||
					$imageSizes[1] > $imageRestriction['height']
				) {
					fwrite(STDOUT, "Provided image has a higher size than recommended (" . implode(' by ', $imageRestriction) . "). Continue? ");
					$ynImg = fgets(STDIN);
					if (strtoupper($ynImg[0]) == "Y") {
						break;
					} else {
						$isValidImage = false;
					}
				}
			}
		}
	} while($isValidImage !== true);
	fwrite(STDOUT, "Image title: ");
	$title = rtrim(fgets(STDIN));
	fwrite(STDOUT, "Link (when clicked on the image): ");
	$via = rtrim(fgets(STDIN));
	$image = $item->appendChild($dom->createElementNs("http://php.net/ns/news", "newsImage", $path));
	$image->setAttribute("link", $via);
	$image->setAttribute("title", $title);
}
ce($dom, "link", null, array("href" => $via, "rel"  => "via", "type" => "text/html"), $item);
$content = ce($dom, "content", null, array(), $item);
fwrite(STDOUT, "And at last; paste/write your news item here.\nTo end it, hit <enter>.<enter>\n");
$news = "\n";
while(($line = rtrim(fgets(STDIN))) != ".") {
	$news .= "     $line\n";
}
$tdoc = new DOMDocument("1.0", "utf-8");
$tdoc->formatOutput = true;
if ($tdoc->loadXML('<div>'.$news.'    </div>')) {
	$content->setAttribute("type", "xhtml");
	$div = $content->appendChild($dom->createElement("div"));
	$div->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
	foreach($tdoc->firstChild->childNodes as $node) {
		$div->appendChild($dom->importNode($node, true));
	}
} else {
	fwrite(STDERR, "There is something wrong with your xhtml, falling back to html");
	$content->setAttribute("type", "html");
	$content->nodeValue = $news;
}
$dom->appendChild($item);
$dom->save($file);
$arch = new DOMDocument("1.0", "utf-8");
$arch->formatOutput = true;
$arch->preserveWhiteSpace = false;
$arch->load("archive/archive.xml");
$first = $arch->createElementNs("http://www.w3.org/2001/XInclude", "xi:include");
$first->setAttribute("href", $xincluded);
$second = $arch->getElementsByTagNameNs("http://www.w3.org/2001/XInclude", "include")->item(0);
$arch->documentElement->insertBefore($first, $second);
$arch->save("archive/archive.xml");
fwrite(STDOUT, "File saved.\nPlease git diff $archivefile sanity-check the changes before committing\n");
fwrite(STDOUT, "NOTE: Remeber to git add $file && git commit $file && git push!!\n");