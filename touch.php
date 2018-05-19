<?php
/**
 * Crawl the sitemap.xml for 301 redirections and 404 errors.
 * Source: http://edmondscommerce.github.io/php/crawl-an-xml-sitemap-quality-check-301-and-404.html
 *
 * To use this script you need to allocate a huge amount of time to maximum_execution_time to 
 * avoid Fatal error: Maximum execution time...I suggest to run this script on terminal.
 * Ex: $ php test-xml.php > ~/Desktop/sitemap-curl-result.txt
 *
 * For 3000 links the average time the script consumed is around 45 minutes to 1 hour.
 */

$cli = php_sapi_name() === 'cli' ? true : false; 
$break = $cli ? "\n" : "<br>";
// Define the screen width.
$screenwidth = 80;

// Replace this with a correct sitemap.xml url.
$src = isset($argv[1]) ? $argv[1] : 'sitemap.xml';
if(is_url($src)){
	$cwd = dirname(__FILE__);
	$temp = tempnam($cwd, 'tmp_downloaded_');
	copy($src, $temp);
} else{
	$temp = $src;
}
if(!file_exists($temp)){
	print "file $temp not exists!".$break;
	die;
}
$xmlfile = is_gzip($temp) ? ungzip($temp) : $temp;


// Load the sitemap.xml url.
$xml = simplexml_load_file($xmlfile);
$counter = 0;
$threadcount = 5;
$multihandle = curl_multi_init();
$not_found = $moved_permanently = $ok = 0;
if ($xml->getName() != 'urlset') {
  die("Doesn't look like a valid sitemap!");
}
$total_urls = count($xml->children());

// Iterate over the children.
foreach ($xml->children() as $child) {
  if ($child->getName() == 'url') {
    foreach ($child->children() as $subchild) {
      if ($subchild->getName() == 'loc') {
        print "Fetching : " . trimstr($subchild, $screenwidth - 12) . $break;
        $counter++;
        addurltostack($subchild);
        if ($counter%$threadcount == 0 || $counter == $total_urls) {
          do {
            curl_multi_exec($multihandle, $running);
          }
          while ($running > 0);
          processresults();
          print $break . $counter . '/' . $total_urls . ' urls checked - ' . $ok . ' 200s; ' . $moved_permanently . ' 301s; ' . $not_found . ' 404s.' . $break . $break;
        }
      }
    }
  }
}

print $break;

// Print all 404 urls.
if ($not_found > 0) {
  print "The following urls were not found (404): $break";
  foreach ($not_found_urls as $url) {
    print $url . $break;
  }
}

// Print all 301 urls.
if ($moved_permanently > 0) {
  print "The following urls were moved permanently (301): $break";
  foreach ($moved_permanently_urls as $url) {
    print $url . $break;
  }
}

/**
 * Add URL to stack.
 */
function addurltostack($url) {
  global $curls;
  global $multihandle;
  $ch = curl_init();
  $curls[] = $ch;
  // Set the url path we want to call.
  curl_setopt($ch, CURLOPT_URL, $url);
  // Make it so the data coming back is put into a string.
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, TRUE);
  // Remove the body.
  curl_setopt($ch, CURLOPT_NOBODY, TRUE);
  curl_multi_add_handle($multihandle, $ch);
}


/**
 * Process the results.
 */
function processresults() {
  global $curls;
  global $multihandle;
  global $not_found;
  global $not_found_urls;
  global $moved_permanently;
  global $moved_permanently_urls;
  global $ok;
  global $multihandle;
  foreach ($curls as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    // Free up the resources $ch is using.
    curl_multi_remove_handle($multihandle,$ch);
    curl_close($ch);
    switch ($httpCode) {
      case 404:
        $not_found++;
        $not_found_urls[] = $url;
        break;

      case 301:
        $moved_permanently++;
        $moved_permanently_urls[] = $url;
        break;

      case 200: 
        $ok++;
        break;
    }
  }
  curl_multi_close($multihandle);
  $multihandle = curl_multi_init();
  $curls = array();
}

function is_url($url)
{
	$url = strtolower(substr($url, 0 , 8));
	if( $url === 'https://' || substr($url, 0 , -1) === 'http://'){
		return true;
	}
	return false;
}


/**
 * function to decompress gzip file
 */ 
function ungzip($realpath){
	$cwd = dirname(__FILE__);
	$temp = tempnam($cwd, 'tmp_');
	$gzopen = gzopen($realpath, "r");
	$fopen = fopen($temp, "w");
	//разжимаем куски по 2мб и пишем
	while (!feof($gzopen)) {
		$data = gzread($gzopen, 2097152);
		fwrite($fopen, $data);
	}
	fclose($fopen);
	gzclose($gzopen);
	return $temp;
}

/**
 * fuction to check if a file is gzipped 
*/
function is_gzip($realpath)
{
	$mystery_string = file_get_contents($realpath, false, null, 0, 50);

	if (mb_strpos($mystery_string, "\x1f" . "\x8b" . "\x08") !== false) {
		return true;
	}
}

/**
 * Trim string.
 */
function trimstr($str, $maxlength = -1, $middle = '...') {
  global $screenwidth;
  if ($maxlength == -1) {
    $maxlength = $screenwidth - 1;
  }
  if (count($str) > $maxlength) {
    $partlength = round($maxlength - count($middle) / 2);
    $leftpart = substr($str, 0, $partlength);
    $rightpart = substr($str, 0-$partlength);
    return $leftpart . $middle . $rightpart;
  }
  else {
    return $str;
  }
}
