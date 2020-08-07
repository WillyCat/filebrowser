<?php
// GET:
// action (bookmark, logout)
// dir [, page]
// dir, action (export)
// dir, file, action (download, delete, upload-form)
// dir, page, file, action (confirm-delete)
// POST:
// login, password
// action (upload)

$use_dropdowns = 0;	// dropdown require bootstrap.js + popper.js - if not used, have lighter footprint by not importing them

$path = '';   // dir in FS encoding
$file = 0; // file in FS encoding
$page = 0;
$action = '';

$pageno = 0 ; // current page, first is 0

$ver = '';
if (file_exists ('version'))
{
	$ver = file_get_contents ('version');
	if ($ver === false)
		$ver = '';
}

if (array_key_exists ('action', $_GET))
	$action = $_GET['action'];
else
	if (array_key_exists ('action', $_POST))
		$action = $_POST['action'];

//----------------------------------
// Misc. globals
// Code can set them anywhere
//----------------------------------
$errmsg = ''; // error is displayed *instead* of files - this is used for critical situation where no data can be displayed
$errlevel = ''; // error level
// info is displayed before files - it can be used for errors but does not prevents from displaying directory content
$feather = '';

$info_level = ''; // primary (bleu), secondary (gris clair), success (vert), danger (rouge), warning (jaune), info (gris-bleu), light (blanc), dark (gris fonce)
$info_msg = '';
$info_buttons = [ ];
$info_feather = '';

$footer = ''; // footer is displayed at bottom of page
$default_dir = '/tmp';

set_orderby();

//----------------------------------
// Read configuration file
//----------------------------------
$conf_content = @file_get_contents ('/etc/filebrowser/conf.json');
if ($conf_content === false)
	global_failure ('Cannot read configuration file');

try
{
	$conf = json_decode ($conf_content, true, 512, JSON_THROW_ON_ERROR );
} catch (Exception $e) {
	global_failure ('Cannot decode conf file');
}
$conf['actions'] = [ 'download', 'delete' ]; // possible actions on a file

if (!array_key_exists ('volumes', $conf))
	global_failure ('conf file is missing volumes entry');

if (count ($conf['volumes']) == 0)
	global_failure ('no volume configured');

// Setting defaults and converting encodings to lower case
foreach ($conf['volumes'] as $key => $value)
{
	if (!array_key_exists ('encoding', $conf['volumes'][$key]))
		$conf['volumes'][$key]['encoding'] = 'utf-8';
	else
		$conf['volumes'][$key]['encoding'] = strtolower($conf['volumes'][$key]['encoding']);
	if (!array_key_exists ('delete', $conf['volumes'][$key]))
		$conf['volumes'][$key]['delete'] = 'no';
	if (!array_key_exists ('download', $conf['volumes'][$key]))
		$conf['volumes'][$key]['download'] = 'no';
	if (!array_key_exists ('upload', $conf['volumes'][$key]))
		$conf['volumes'][$key]['upload'] = 'no';
	if (!array_key_exists ('showhiddenfiles', $conf['volumes'][$key]))
		$conf['volumes'][$key]['showhiddenfiles'] = 'no';
}

date_default_timezone_set($conf['tz']);

//----------------------------------
// Session management
//----------------------------------
session_start();

if ($action == 'logout')
	session_invalidate();

// if changing auth method to ldap, sessions already open
// and gained with no auth are no longer valid
if ($_SESSION['filebrowseruser'] == 'anonymous' && $conf['auth'] == 'ldap')
	session_invalidate();

if (!session_is_valid())
{
	if ($conf['auth'] == 'none') // auto-login
		login_as ('anonymous');
	else // real login
	{
		// without a valid session, only two actions can be performed:
		// - display login form
		// - process login form

		if ($action == 'login')
			authenticate ();

		if (!session_is_valid())
		{
			show_login_form();
			die();
		}
	}
}

//==============================================================
// ADD/REMOVE BOOKMARK
//==============================================================

if ($conf['bookmarks']['enabled'] == 'yes')
{
	$bookmarks_cookie_name = 'bookmarks';
	if (array_key_exists ($bookmarks_cookie_name, $_COOKIE))
		$bookmarks = unserialize ($_COOKIE[$bookmarks_cookie_name]);
	else
		$bookmarks = [ ];

	if ($aaction == 'bookmark')
	{
		set_path();
		if (is_dir_allowed ($path))
		{
			$info_level = 'success';
			if (in_array ($path, $bookmarks))
			{
				$info_msg   = 'Bookmark removed';
				$info_feather = 'check-circle';

				if (($key = array_search($path, $bookmarks)) !== false)
				    unset($bookmarks[$key]);
			}
			else
				if (count($bookmarks) >= $conf['bookmarks']['max'])
				{
					$info_msg   = 'Maximum number of bookmarks reached';
					$info_level = 'warning';
					$info_feather = 'slash';
				}
				else
				{
					$info_msg   = 'Bookmark added';
					$bookmarks[] = $path;
					$info_feather = 'check-circle';
				}
			$bookmarks_cookie_value = serialize($bookmarks);
			setcookie ($bookmarks_cookie_name, $bookmarks_cookie_value);
		}
	}
}

//==============================================================
// UPLOAD A FILE
//==============================================================
if ($action == 'upload')
{
	set_path('POST');

	$root = get_volume ($path);
	if ($root == null || $root['upload'] == 'no')
	{
		$info_msg = 'Upload not allowed in this directory';
		$info_feather = 'slash';
		$info_level = 'danger';
	}
	else
	{
		$name = $_FILES["fileToUpload"]["name"];
		$size = $_FILES["fileToUpload"]["size"];
		$ext  = strtolower(pathinfo($name,PATHINFO_EXTENSION));

		if (strlen ($name) == 0)
		{
			$info_msg = 'No file selected for upload';
			$info_level = 'warning';
			$info_feather = 'slash';
		}
		else
		{
			$target_file = $path . '/' . $name;

			if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
			{
				$info_msg = 'File successfuly uploaded';
				$info_level = 'success';
				$info_feather = 'check-circle';
			}
			else
			{
				$info_msg = 'Upload failure';
				$info_level = 'danger';
				$info_feather = 'alert-triangle';
			}
		}
	}
}
//==============================================================
// Deletion confirmation
//==============================================================

if ($action == 'confirm-delete')
{
	set_path();
	set_pageno();

	$root = get_volume ($path);
	if ($root == null || $root['delete'] == 'no')
	{
		$info_msg = 'Deletion is not allowed in this directory';
		$info_feather = 'slash';
		$info_level = 'danger';
	}
	else
	{
		$info_msg = 'Delete ' . htmlentities($file) . ' ?';
		$info_feather = 'help-circle';

		global $pageno;
		$no_link = make_link ([ 'page'=>$pageno+1 ]);
		$yes_link = make_link ([ 'page'=>$pageno+1, 'file' => $file, 'action' => 'delete' ]);

		$info_buttons[] = '<a class="btn btn-primary" href="'.$yes_link.'" role="button">Yes</a>';
		$info_buttons[] = '<a class="btn btn-primary" href="'.$no_link.'" role="button">No</a>';

		$info_level = 'warning';
	}
}

//==============================================================
// Deletion (effective)
//==============================================================

if ($action == 'delete')
{
	set_path();
	$root = get_volume ($path);
	if ($root == null || $root['delete'] == 'no')
	{
		$info_msg = 'This action is not allowed';
		$info_feather = 'slash';
		$info_level = 'danger';
	}
	else
	{
		if (@unlink($pathname))
		{
			$info_msg = 'File ' .$file. ' deleted';
			$info_level = 'success';
			$info_feather = 'check-circle';
		}
		else
		{
			$info_msg = 'Deletion failed';
			$info_level = 'danger';
			$info_feather = 'alert-triangle';
		}

		/*
		$info_msg = 'Deletion not implemented yet';
		$info_level = 'warning';
		*/
	}
}

//==============================================================
// Export CSV file with dir content
//==============================================================
if ($action == 'export')
{
	set_path();
	if ($conf['csv']['enabled'] == 'yes' && is_dir_allowed ($path))
	{
		$filename = get_basename ($path) . '.csv';
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $filename . "\""); 
		csv_to_stdout ($path); 
		die();
	}
	$info_level = 'danger';
	$info_msg   = 'Operation not permitted';
}
//==============================================================
// Download a file
//==============================================================
if ($action == 'download')
{
	set_path();
	$root = get_volume ($path);
	if ($root != null && $root['download'] == 'yes')
	{
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $file . "\""); 
		readfile($pathname); 
		die();
	}

	$info_level = 'danger';
	$info_msg   = 'Operation not permitted in '.$path;
}

//==============================================================
// Function used
//==============================================================

function
set_orderby(): void
{
	global $orderby, $order;

	if (array_key_exists ('orderby', $_GET) && is_valid_orderby ($_GET['orderby']))
		$orderby = $_GET['orderby'];
	else
		$orderby = 'name';

	if (array_key_exists ('order', $_GET) && is_valid_order ($_GET['order']))
		$order = $_GET['order'];
	else
		$order = 'asc';
}

// from  https://www.php.net/manual/fr/function.realpath.php
// similar to realpath()
// but always returns info, even if file is non-existent
// realpath with return an empty string if file does not exists
function get_absolute_path($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes);
    }

// basename() relies on locale
// this function does not
function
get_basename (string $pathname): string
{
        $pathname = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $pathname);
	$parts = explode (DIRECTORY_SEPARATOR, $pathname);
	return array_pop ($parts);
}

// this function sets $path, $file and $pathname globals
// from $_GET parameters
// whatever the parameters (relative, absolute, with path in filename etc.)
// it will provide a clean canonical directory and a clean filename
// this is important so to avoid bypassing configured permissions

function
set_path(string $origin = 'GET'): void
{
	global $path, $queried_path, $file, $pathname, $errmsg, $errlevel, $feather;

	$path = $file = '';

	switch ($origin)
	{
	case 'GET' :
		$from = $_GET;
		break;
	case 'POST' :
		$from = $_POST;
		break;
	default :
		$errmsg = 'Invalid origin';
		$feather = 'alert-triangle';
		return;
	}

	if (!array_key_exists('path', $from))
		return; // no path, no info

	// we have a path
	$path = rawurldecode ($from['path']);
	$queried_path = $path;

	// do we also have a file ?
	if (array_key_exists('file', $from) && ($from['file'] != ''))
	{
		// yes, path + file

		// retrieve file
		$file = rawurldecode ($from['file']);

		// put them together and retrieve each one
		// this will prevent something like
		// path=/home/mydir   (authorized)
		// filename=../../etc/password (unauthorized)

		// here :
		// get_absolute_path('/home/mydir/../../etc/passwd'): /etc/passwd
		// path: /etc
		// file: passwd

		$pathname = $path . '/' . $file;
		$pathname = get_absolute_path ($pathname);

		// pathname uses native fs encoding (might not be suitable for display)

		$path = dirname($pathname);
		$file = get_basename($pathname);

/*
echo 'real pathname: ' . $pathname . '<br>' . "\n";
echo 'exists: ' . (file_exists($pathname) ? 'yes' : 'no') . '<br>' . "\n";
echo 'path: ' . $path . '<br>';
echo 'file: ' . $file . '<br>';
die();
*/
	}
	else // no, path only
	{
		$path = get_absolute_path($path);
	}

	if ($path == '')
	{
		$errmsg = 'Cannot read directory'; // use same message for non-exitent and unreadable
		$errlevel = 'danger';
		$feather = 'alert-triangle';
	}
}

// set pageno from $_GET array
function
set_pageno(): void
{
	global $pageno, $nbpages;

	if (array_key_exists ('page', $_GET))
		$pageno = ($_GET['page'] - 1);
	else
		$pageno = 0;

	if ($pageno < 0)
		$pageno = 0;
	if ($pageno >= $nbpages)
		$pageno = $nbpages - 1;
}

// true if this field is a valid order criteria
function
is_valid_orderby (string $orderby): bool
{
	return in_array ($orderby, [ 'name', 'size', 'mtime' ] );
}

// true if this is a correct value for order
function
is_valid_order (string $order): bool
{
	return in_array ($order, [ 'asc', 'desc' ] );
}

// sorten string with ellipsis if necessary
function
shorten (string $str, int $len = 20): string
{
	if (strlen($str) > $len)
		return substr ($str, 0, $len) . '...';
	else
		return $str;
}

function
csv_to_stdout (string $path): void
{
	global $files;
	global $conf;

	$columns = $conf['csv']['columns'];

	get_dir_content ($path);
	$sep = ';';
	$eol = "\n";

	// Title

	foreach ($columns as $column)
		if ($column != 'actions')
			echo '"' . $column . '"' . $sep;
	echo $eol;

	// Lines

	foreach ($files as $file)
	{
		foreach ($columns as $column)
		{
			echo '"';
			switch ($column)
			{
			case 'Filename' :
				echo $file['name-8859-1'];
				break;
			case 'Size' :
				if ($file['type'] == 'file')
					echo $file['size'];
				break;
			case 'Perms' :
				echo $file['perms'];
				break;
			case 'mtime' :
				echo $file['mtime-display'];
				break;
			case 'ctime' :
				echo $file['ctime-display'];
				break;
			case 'type' :
				echo $file['type'];
				break;
			}
			echo '"';
			echo $sep;
		}
		echo $eol;
	}
} 

function
authenticate (): void
{
	global $info_msg, $info_level, $info_feather;
	global $conf;

	if (!array_key_exists ('login', $_POST) || ($_POST['login'] == ''))
	{
		$info_msg = 'Empty login';
		$info_level = 'danger';
		$info_feather = '';
		return;
	}
	if (!array_key_exists ('password', $_POST) || ($_POST['password'] == ''))
	{
		$info_msg = 'Empty password';
		$info_level = 'danger';
		$info_feather = '';
		return;
	}

	$login = $_POST['login'];
	$password = $_POST['password'];

	if (!function_exists ('ldap_connect'))
	{
		$info_msg = 'missing function ldap_connect(), please review PHP configuration';
		$info_level = 'danger';
		$info_feather = 'alert-triangle';
		return;
	}

	$ldapconn = ldap_connect($conf['ldap']['server'],$conf['ldap']['port']);
	if (!$ldapconn)
	{
		$info_msg = 'Cannot connect to LDAP server (' . $conf['ldap']['server'] . ')';
		$info_level = 'danger';
		$info_feather = 'alert-triangle';
		return;
	}

	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION,$conf['ldap']['protocol_version']);
	ldap_set_option($ldapconn, LDAP_OPT_REFERRALS,0);

	$pattern = $conf['ldap']['pattern'];
	$dn = str_replace ('{login}', $login, $pattern);

	$lb = ldap_bind($ldapconn,$dn,$password);

	ldap_close($ldapconn);

	if ($lb === true)	// LDAP - OK
		login_as ($login);
	else
	{
		$info_msg = 'Invalid credential';
		$info_level = 'danger';
		$info_feather = 'slash';
	}
}

function
send_html_head(): void
{
	global $ver;

	echo '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="folder.png" />

    <title>File browsing '.$ver.'</title>

    <!-- Bootstrap core CSS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<?php if ($use_dropdowns) { ?>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
<?php } ?>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
<link rel="stylesheet" href="filebrowser.css">

  </head>
';
}

//----------------------------------------------------------
// from https://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
function
formatBytes(int $bytes, int $precision = 2):string
{ 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
    $bytes /= pow(1024, $pow);	// 1K = 1024
    // $bytes /= (1 << (10 * $pow));  // 1K = 1000

    return round($bytes, $precision) . ' ' . $units[$pow]; 
} 

//----------------------------------------------------------

function
formatPerms (string $pathname): string
{
	$perms = fileperms ($pathname);
	switch ($perms & 0xF000) {
	    case 0xC000: // Socket
		$info = 's';
		break;
	    case 0xA000: // sym link
		$info = 'l';
		break;
	    case 0x8000: // regular file
		$info = '-';
		break;
	    case 0x6000: // Block special
		$info = 'b';
		break;
	    case 0x4000: // directory
		$info = 'd';
		break;
	    case 0x2000: // char special
		$info = 'c';
		break;
	    case 0x1000: // pipe FIFO
		$info = 'p';
		break;
	    default: // unknown (or cannot get perms)
		return '';
	}

	// owner
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ?
		    (($perms & 0x0800) ? 's' : 'x' ) :
		    (($perms & 0x0800) ? 'S' : '-'));

	// Group
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ?
		    (($perms & 0x0400) ? 's' : 'x' ) :
		    (($perms & 0x0400) ? 'S' : '-'));

	// other
	$info .= (($perms & 0x0004) ? 'r' : '-');
	$info .= (($perms & 0x0002) ? 'w' : '-');
	$info .= (($perms & 0x0001) ?
		    (($perms & 0x0200) ? 't' : 'x' ) :
		    (($perms & 0x0200) ? 'T' : '-'));

	return $info;
}

//----------------------------------------------------------

function
get_volume (string $path): ?array
{
	global $conf;

	foreach ($conf['volumes'] as $volume)
		if (starts_with ($path, $volume['path']))
			return $volume;

	return null;
}

function
is_dir_allowed (string $path): bool
{
	$root = get_volume ($path);
	if ($root != null)
		return true;
	return false;
}

function
get_dir_content (): void
{
	global $errmsg, $conf, $path, $errlevel;
	global $total_size_used;
	global $files;

	$files = [];
	$total_size_used = 0;

	parse_dir();

	global $n;
	$n = count ($files);

	//-------------------
	// paging computations
	//-------------------

	global $nbpages;
	$nbpages = floor ($n / $conf['display']['pagesize']);
	if ($n % $conf['display']['pagesize'])
		$nbpages++;
	if ($nbpages == 0)
		$nbpages = 1; // even if no result, we need 1 page

	//-------------------
	// sort content
	//-------------------

	usort ($files, function ($a, $b) {
		global $orderby, $order;


		if ($orderby == 'size')
			$cr = $a[$orderby] - $b[$orderby];
		else
			$cr = strcasecmp($a[$orderby], $b[$orderby]);

		if ($order == 'desc')
			$cr = -$cr;

		return $cr;
	});
}

// reads $path
function
parse_dir (): void
{
	global $errmsg, $feather, $conf, $path, $files, $errlevel, $queried_path;

	//-------------------
	// Determines real pathname
	// Changes /toto/titi/../tutu to /toto/tutu
	// if target does not exists, path equals to empty string
	// (might exists and not a directory)
	//-------------------

	if ($path == '')
	{
		if ($queried_path == '')
		{
			$errmsg = 'Please select a directory';
			$errlevel = 'info';
		}
		else
		{
			$errmsg = 'Not a valid directory';
			$errlevel = 'danger';
			$feather = 'alert-triangle';
		}
		return;
	}

	//-------------------
	// checks is the user is allowed to display this directory
	// it is allowed only if it is below one of its allowed roots
	//-------------------

	$root = get_volume ($path);
	if ($root == null)
	{
		$errmsg = 'You are not allowed to view this directory ('.$path.')';
		$errlevel = 'danger';
		$feather = 'slash';
		return;
	}

	//-------------------
	// check if it is a directory
	//-------------------

	if (!is_dir ($path))
	{
		$errmsg = 'Not a directory';
		$errlevel = 'danger';
		$feather = 'slash';
		return;
	}

	//-------------------
	// consider this directory is now current one
	// currently not used, for future use
	//-------------------
	$_SESSION['dir'] = $path;

	//-------------------
	// attempt to read content
	//-------------------

	$dh = opendir ($path);
	if (!$dh)
	{
		$errmsg = 'Cannot read directory'; // use same message for non-exitent and unreadable
		$errlevel = 'danger';
		$feather = 'alert-triangle';
		return;
	}

	clearstatcache(); // force re-reading of content, resetting cache

	$i = 0;
	while (($file = readdir ($dh)) !== false)
	{
//echo "FILE=" . $file . '<br>';
		if ($file == '.' || $file == '..')
			continue;

		if ($root['showhiddenfiles'] == 'no')
			if (substr ($file, 0, 1) == '.')
				continue;

		$pathname = $path . '/' . $file;

		$a = [ ];

		// name => FS encoding (can be used to access file)
		// name-utf8 => UTF-8 (can be used for HTML display)
		// name-8859-1 => iso-8859-1 (can be used for CSV export)

		$a['name'] = $file; // local encoding
		if ($root['encoding'] == 'iso-8859-1')
		{
			$a['name-8859-1'] = $file;
			$a['name-utf8'] = utf8_encode($file);
		}
		else
		{
			$a['name-utf8'] = $file;
			$a['name-8859-1'] = utf8_decode ($file);
		}

		$a['type'] = @filetype ($pathname);
		if ($a['type'] == 'file')
		{
			$a['size'] = @filesize ($pathname);
			$a['size-display'] = formatBytes($a['size']);
			$total_size_used += $a['size'];
		}
		else
		{
			$a['size'] = 0;
			$a['size-display'] = '';
		}
		$a['perms'] = formatPerms ($pathname);
		$a['mtime'] = filemtime ($pathname);
		if ($a['mtime'])
			$a['mtime-display'] = strftime ('%F %T', $a['mtime']);
		else
			$a['mtime-display'] = '';
		$a['ctime'] = filectime ($pathname);
		if ($a['ctime'])
			$a['ctime-display'] = strftime ('%F %T', $a['ctime']);
		else
			$a['ctime-display'] = '';
		if ($a['type'] == 'link')
		{
			$linktarget = readlink ($pathname);
			if (substr($linktarget,0,1) == '/')
				$a['target'] = $linktarget;
			else
				$a['target'] = get_absolute_path($path . '/' . $linktarget);
		}
		$files[$i++] = $a;
	}
	closedir ($dh);
}

//----------------------------------------------------------

// display the error and leave
// (to be used for general failures only)

function
global_failure(string $msg): void
{
	send_html_head();
	echo '
<body class="text-center">
<main role="main">
<div class="jumbotron">
<div class="container">
<h1 class="display-3">Error !</h1>
<p>
';
	display_error ($msg, 'danger', 'alert-triangle');
	echo '
</p>
</div>
</div>
</main>
</body></html>';
	die();
}

//----------------------------------------------------------

function
show_login_form(): void
{
	global $ver;

	send_html_head();

	echo '
	<body class="text-center">
	<form class="form-signin" data-bitwarden-watching="1" method="POST" action="index.php">
	';

	global $info_msg, $info_level, $info_feather;
	display_error ($info_msg, $info_level, $info_feather);

	echo '
      <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
      <label for="inputEmail" class="sr-only">Account</label>
      <input type="text" id="inputEmail" class="form-control" placeholder="Account" required="" autofocus="" name="login">
      <label for="inputPassword" class="sr-only">Password</label>
      <input type="password" id="inputPassword" class="form-control" placeholder="Password" required="" name="password">
      <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
      <p class="mt-5 mb-3 text-muted">V '.$ver.' (c) 2020</p>';

/*
	global $path;
	if ($path != '')
		echo '<input type="hidden" path="' . $path . '">' . "\n";

	global $file;
	if ($file != '')
		echo '<input type="hidden" file="' . $file . '">' . "\n";

	global $page;
	if ($page != 0)
		echo '<input type="hidden" page="' . $page . '">' . "\n";

	global $action;
	if ($action != '')
		echo '<input type="hidden" action="' . $action . '">' . "\n";
*/
	echo '<input type="hidden" name="action" value="login">';

	echo ' </form> </body> </html>';
}

function
display_action(string $action, array $file, array $root): void
{
	$feather = $action_link = '';

	if (array_key_exists ($action, $root)
	&& $root[$action] == 'no')
		return;

	switch ($action)
	{
	case 'delete' :
		if ($file['type'] == 'file')
		{
			$feather = 'trash';
			$title = 'Delete file';
			global $pageno;
			$action_link = make_link ([
				'page' => $pageno+1,
				'action' => 'confirm-delete',
				'file' => $file['name']
			]);
		}
		break;
	case 'download': 
		if ($file['type'] == 'file')
		{
			$feather = 'download';
			$title = 'Download file';
			global $pageno;
			$action_link = make_link ([
				'page' => $pageno+1,
				'action' => 'download',
				'file' => $file['name']
			]);
		}
		break;
	default :
		break;
	}

	if ($feather != '')
	{
		echo '<a href="'.$action_link.'" title="'.$title.'">';
		echo '<span class="icon" >';
		echo '<span data-feather="'.$feather.'"></span>';
		echo '</span>';
		echo '</a>';
	}
}

function
display_actions (array $file, array $root): void
{
	global $conf;
	foreach ($conf['actions'] as $action)
		display_action ($action, $file, $root);
}

function
display_column (array $file, string $column, array $root): void
{
	global $path;

	$a_open = false;

	echo '<td class="col-'.strtolower($column).'">';
	switch ($column)
	{
	case 'Filename' :
		if ($file['type'] == 'dir')
		{
			echo '<a href="' . make_link([ 'path' => $path. '/' . $file['name'] ]) . '">';
			$a_open = true;
		}

		if ($file['type'] == 'link')
			if (is_dir ($file['target']))
			{
				echo '<a href="' . make_path([ 'path' => $file['target']]) .'" title="'.$file['target'].'">';
				$a_open = true;
			}

		echo htmlentities($file['name-utf8']);
		if ($file['type'] == 'dir')
			echo '/';
		break;
	case 'Size' :
		if ($file['type'] == 'file')
		{
			echo '<span title="'.$file['size'].'">';
			echo $file['size-display'];
			echo '</span>';
		}
		break;
	case 'Perms' :
		echo $file['perms'];
		break;
	case 'mtime' :
		echo $file['mtime-display'];
		break;
	case 'ctime' :
		echo $file['ctime-display'];
		break;
	case 'type' :
		echo $file['type'];
		break;
	case 'actions' :
		display_actions( $file, $root);
		break;
	default :
		echo '?';
		break;
	}
	if ($a_open)
		echo '</a>';
	echo '</td>';
}

function
display_line (int $i, array $file, array $columns, array $root): void
{
	echo '<tr>';
	foreach ($columns as $column)
		display_column ($file, $column, $root);
	echo '</tr>';
}

function
make_js_link (array $parms): string
{
	$url = 'index.php' . make_link ($parms);
	return 'window.location=' . "'" . $url . "'";
}

function
make_link (array $parms): string
{
	// fill missing entries
	$parmskeys = [ 'path', 'page', 'orderby', 'order', 'action', 'file' ];
	foreach ($parmskeys as $parmkey)
		if (!array_key_exists ($parmkey, $parms))
			$parms[$parmkey] = '';

	// re-use current settings
	global $orderby, $order, $path;
	if ($parms['orderby'] == '')
		$parms['orderby'] = $orderby;
	if ($parms['order'] == '')
		$parms['order'] = $order;
	if ($parms['path'] == '')
		$parms['path'] = $path;

	// encode each part
	$urlparts = [ ];
	foreach ($parms as $key => $value)
		$urlparts[] = $key . '=' . rawurlencode ($parms[$key]);

	// paste parts together
	$link = '?' . implode ('&', $urlparts);

	return $link;
}

function
show_page (int $page, int $pageno): void
{
	global $path;

	if ($page == ($pageno+1) )
		$item_attributes = 'active';
	else
		$item_attributes = '';

	$page_link = make_link (['page'=>$page]);
?>    
    <li class="page-item <?=$item_attributes ?>"><a class="page-link" href="<?=$page_link?>"><?=$page ?></a></li>
<?php
}

function
show_pagination (int $pageno_active, int $nbpages): void
{
	// no paginate if only 1 page
	if ($nbpages < 2)
		return;

	$large = 10;

	if ($nbpages < $large) // small number of pages
		show_pagination_small ($pageno_active, $nbpages);
	else
		show_pagination_large ($pageno_active, $nbpages);
}

function
show_pagination_large (int $pageno_active, int $nbpages): void
{
	$prev_class = $prev_attributes = $next_attributes = $next_class = '';

	if ($pageno_active == 0)
	{
		$prev_class = 'disabled';
		$prev_attributes = 'aria-disabled="true"';
		$prev_link = make_js_link ([ 'page' => $pageno_active + 1 ]);
	}
	else
		$prev_link = make_js_link ([ 'page' => $pageno_active ]);

	if (($pageno_active+1) >= $nbpages)
	{
		$next_class = 'disabled';
		$next_attributes = 'aria-disabled="true"';
		$next_link = make_js_link ([ 'page' => $pageno_active+1 ]);
	}
	else
		$next_link = make_js_link ([ 'page' => $pageno_active+2 ]);

	global $path;
?>
<form action="index.php" method="get">
<input type=hidden name="path" value="<?=$path ?>">
<div class="largepaginate">
<div class="input-group input-group-sm mb-3">
  <div class="input-group-prepend">
<button class="btn btn-secondary <?=$prev_class?>" <?=$prev_attributes ?> type="button" onClick="<?=$prev_link ?>">Previous</button>
  </div>
  <input type="text" name="page" class="form-control" aria-label="Small" aria-describedby="inputGroup-sizing-sm" value="<?=$pageno_active+1 ?>">
  <div class="input-group-prepend">
<button class="btn btn-secondary <?=$next_class?>" <?=$next_attributes ?> type="button" onClick="<?=$next_link ?>">Next</button>
  </div>
</div>
</div>
</form>

<?php
}

function
show_pagination_small (int $pageno_active, int $nbpages): void
{
	global $path;
	global $conf;

	if ($pageno_active == 0)
		$prev_attributes = 'disabled';
	else
		$prev_attributes = '';
	$prev_link = make_link (['page'=>$pageno_active]);

	if (($pageno_active+1) >= $nbpages)
		$next_attributes = 'disabled';
	else
		$next_attributes = '';
	$next_link = make_link (['page'=>$pageno_active+2]);
?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-end">
    <li class="page-item <?=$prev_attributes ?>">
      <a class="page-link" href="<?=$prev_link?>" tabindex="-1">Previous</a>
    </li>
<?php

		$pages = [ ];

		// compute pages to display
		for ($i = 0; $i < $nbpages; $i++)
			$pages[] = $i+1;

		// display pages
		foreach ($pages as $page)
			show_page ($page, $pageno_active);

 ?>
    <li class="page-item <?=$next_attributes ?>">
      <a class="page-link" href="<?=$next_link?>">Next</a>
    </li>
  </ul>
</nav>
<?php
}

function
upload_button (): string
{
	global $conf, $pageno;

	if ($conf['csv']['enabled'] != 'yes')
		return '';

	$str  = '<span';
	$str .= ' class="badge badge-secondary clickable"';
	$str .= ' onClick=' . '"' . make_js_link([ 'page' => $pageno+1,'action' => 'upload-form' ]) . '"';
	$str .= '>';
	$str .= '<span data-feather="upload"></span>';
	$str .= '&nbsp;&nbsp;UPLOAD';
	$str .= '</span>';
	$str .= '</span>';

	return $str;
}


function
csv_button (): string
{
	global $conf, $pageno;

	if ($conf['csv']['enabled'] != 'yes')
		return '';

	$str  = '<span';
	$str .= ' class="badge badge-secondary clickable"';
	$str .= ' onClick=' . '"' . make_js_link([ 'page' => $pageno+1,'action' => 'export' ]) . '"';
	$str .= ' title="Export directory content in CSV format"';
	$str .= '>';
	$str .= '<span data-feather="arrow-down-circle"></span>';
	$str .= '&nbsp;&nbsp;CSV';
	$str .= '</span>';

	return $str;
}

//----------------------------------------------------------

function
display_error (string $msg, string $level = 'danger', string $feather = '', array $buttons = null): void
{
	if ($msg == '')
		return;

?>
	<div class="alert alert-<?=$level ?>" role="alert">
<?php
	if ($feather != '')
		echo '<span class="feather-32" data-feather="'.$feather.'"></span>';
?>
	  <div class="align-middle message"><?=htmlentities($msg) ?></div>
<?php
	if ($buttons != null)
	{
		echo '&nbsp;';
		echo '&nbsp;';
		echo '&nbsp;';
		foreach ($buttons as $button)
			echo $button . '&nbsp;';
	}
?>
	</div>
<?php
}

function
starts_with (string $str, string $start): bool
{
	return (substr ($str, 0, strlen($start)) == $start);
}

function
display_upload_form(): void
{
	global $path;
?>
<form action="index.php" method="post" enctype="multipart/form-data">
<h6>File upload</h6>
 <div class="custom-file">
<input type="hidden" name="action" value="upload">
<input type="hidden" name="path" value="<?=$path ?>">
  <input type="file" name="fileToUpload" id="fileToUpload" class="custom-file-input">
  <label class="custom-file-label" for="fileToUpload">Choose file</label>
<br>
<br>
<div>
  <input type="submit" class="btn btn-primary" value="Upload" name="submit">
</div>
</div>
</form>
<BR>
<?php
}

function
display_files (): void
{
	global $path;
	global $files;
	global $pageno, $nbpages;
	global $order, $orderby;

	echo '<div>';
	show_pagination ($pageno, $nbpages);
	echo '</div>';

	global $conf;
	$columns = $conf['display']['columns'];
	$n = count($files);
?>
          <div class="table-responsive">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
<?php
foreach ($columns as $column)
{
	$add = '';
	if ($column == 'actions')
		$add = 'width=100';
	echo '<th ' . $add . '>';

	switch ($column)
	{
	case 'Filename' :
		$sortname = 'name';
		break;
	case 'Size' :
		$sortname = 'size';
		break;
	case 'mtime' :
		$sortname = 'mtime';
		break;
	default:
		$sortname = '';
		break;
	}

	if ($sortname != '')
	{
		$new_order = (($order == 'asc') ? 'desc' : 'asc');
		//$sortlink = make_link($pageno+1, '', '', $sortname, $new_order);
		$sortlink = make_link([
			'page' => 1,
			'orderby' => $sortname,
			'order' => $new_order
		]);
		echo '<a href="'.$sortlink.'">';
		echo $column;
		echo '</a>';
	}
	else
		echo $column;

	if ($sortname == $orderby) // show a mark indicating this column is current sort
	{
		$feather = ($order == 'asc') ? "chevron-up" : "chevron-down";
                echo '<span data-feather="'.$feather.'"></span>';
	}

	echo '</th>';
}
?>
                </tr>
              </thead>
              <tbody>
<?php
$root = get_volume ($path);
$page_size_used = 0;
for ($lineno = 0, $i = ($pageno * $conf['display']['pagesize']); ($lineno < $conf['display']['pagesize']) && ($i < $n); $i++, $lineno++)
{
	if ($files[$i]['type'] == 'file')
		$page_size_used += $files[$i]['size'];
	display_line ($i, $files[$i], $columns, $root);
}
?>
              </tbody>
            </table>
          </div>
<?php
global $total_size_used;
global $footer;
$fotter = '';
$footer .= "Displaying page ".($pageno+1)."/".$nbpages." sorted on ".$orderby." in ".$order." order";
if ($n > 0)
{
	$footer .= " - Files " . (1+$pageno * $conf['display']['pagesize']) . ' - ' . min($n, ($pageno+1)*$conf['display']['pagesize']) . ' out of ' . $n;
	$footer .= ' - ' . "Page contains " . formatBytes($page_size_used,2) . ' out of ' . formatBytes($total_size_used,2);
}
$footer .= ' - Native encoding: ' . $root['encoding'];
$footer .= '&nbsp;&nbsp;' . csv_button ();
if ($root['upload'] == 'yes')
	$footer .= '  ' . upload_button();
}

function
session_is_valid(): bool
{
	return array_key_exists ('filebrowseruser', $_SESSION)
		&& ($_SESSION['filebrowseruser'] != '');
}

function
session_invalidate(): void
{
	unset ($_SESSION['filebrowseruser']);
}

function
login_as (string $username): void
{
	$_SESSION['filebrowseruser'] = $username; // make session valid
}

//==========================================================
// MAIN
//==========================================================

$debugstr = '';
if ($path == '')
	set_path();
//if ($path == '')
	//$path = $default_dir;
get_dir_content ();
set_pageno();
send_html_head();
?>

  <body>
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
      <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="<?=$conf['title']['url'] ?>" alt="<?=$conf['title']['text'] ?>" title="<?=$conf['title']['text'] ?>">
<?php
	if ($conf['title']['image'] != '')
		echo '<img src="' . $conf['title']['image'] . '">';
	else
		echo htmlentities($conf['title']['text']);
?>
	</a>
	<!--
      <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
	-->
      <ul class="navbar-nav px-3">
        <li class="nav-item text-nowrap">
        </li>
      </ul>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
          <div class="sidebar-sticky">
<?php echo $debugstr; ?>
            <ul class="nav flex-column">

<?php
	foreach ($conf['volumes'] as $volume)
	{
		if (is_dir($volume['path']))
		{
			$href = make_link ([
				'page' => 1,
				'path' => $volume['path']
			]);
			$title = $volume['path'];
			$f = 'folder';
		}
		else
		{
			$href = "javascript:alert('Unavailable directory')";
			$title = 'Unreachable directory';
			$f = 'slash';
		}

		echo '<li class="nav-item">';
		//if ($href != '')
			echo '<a class="nav-link active" href="' . $href . '" title="' . $title . '">';
		echo '<span data-feather="' . $f . '"></span>';
		echo '<span class="">' . htmlentities($volume['name']) . '</span>';
		//if ($href != '')
			echo '</a>';
		echo '</li>';
	}
?>
<?php
	if ($conf['bookmarks']['enabled'] == 'yes')
		foreach ($bookmarks as $bookmark)
		{
?>
		      <li class="nav-item">
			<a class="nav-link active" href="<?= make_link ([ 'page' => 1, 'path' => $bookmark ]) ?>" title="<?=$bookmark?>">
			  <span data-feather="bookmark"></span>
			  <span class="bookmark"><?=shorten($bookmark) ?></span>
			</a>
		      </li>
<?php
		} // foreach
?>

<?php
if ($conf['auth'] == 'ldap') {
?>
              <li class="nav-item ">
                <a class="nav-link" href="?action=logout" title="Logout">
                  <span data-feather="log-out"></span>
                  Logout <?=$_SESSION['filebrowseruser'] ?>
                </a>
              </li>
<?php
		} // auth
?>

            </ul>
          </div>
        </nav>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">

<?php
	display_error($info_msg, $info_level, $info_feather, $info_buttons);
?>

<?php if ($path != '') { ?>
	<div>
	<ul class="breadcrumb">
	<?php
	$dirparts = explode ('/' , $path);
	$linkpath = '';
	echo '<li class="breadcrumb-item">';
	if ($conf['bookmarks']['enabled'] == 'yes')
	{
		echo '<A HREF="'.make_link([
			'page' => $pageno+1,
			'action' => 'bookmark'
		]).'" title="Bookmark this directory">';
		echo '<span';
		echo ' class="clickable"';
		echo ' data-feather="bookmark"';
		echo '>';
		echo '</span>';
		echo '</a>';
	}
	echo '</li>';
	foreach ($dirparts as $dirlevel)
	{
		if ($dirlevel == '')
			continue;
		$linkpath .= '/' . $dirlevel;

		// get_volume() has to be called at each level
		// as encoding can change due to mount points with different encodings
		$root = get_volume ($linkpath);
		if ($root['encoding'] == 'iso-8859-1')
			$dirlevel_utf8 = utf8_encode($dirlevel);
		else
			$dirlevel_utf8 = $dirlevel;

		$link = make_link ([ 'page' => 1, 'path' => $linkpath ]);
		echo '<li class="breadcrumb-item"><A HREF="' . $link . '" title="'.$linkpath.'">' . $dirlevel_utf8 . '</a></li>';
	}
	?>
	</ul>
	</div>
	<div>
<?php } // path != '' ?>
<?php
	if ($errmsg != '')
		display_error ($errmsg, $errlevel, $feather);
	else
		if ($action == 'upload-form')
			display_upload_form ();
		else
			display_files ();
?>
	</div>
	<div>
	<footer class="footer">
		<span class="text-muted"><?=$footer?></span>
	</footer>
	</div>
        </main>
      </div>
    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->

    <!-- Icons -->
    <script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
    <script>
      feather.replace()
    </script>

    <script>
            $(document).ready(function(){
		// Add the following code if you want the name of the file appear on select
		$(".custom-file-input").on("change", function() {
		  var fileName = $(this).val().split("\\").pop();
		  $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
		});
<?php if ($use_dropdowns) { ?>
$('.dropdown-toggle').dropdown();
<?php } ?>
            });
    </script>

  </body>
</html>
