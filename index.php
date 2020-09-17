<?php
require_once 'classes/filename.class.php';
require_once 'classes/message.class.php';
require_once 'classes/session.class.php';
require_once 'classes/log.class.php';
// GET:
// action (bookmark, logout)
// dir [, page]
// dir, action (export)
// dir, file, action (download, delete, upload-form)
// dir, page, file, action (confirm-delete)
// POST:
// login, password
// action (upload)

$booleanfilters = [
	'showfiles'=> [ 'label' => 'Files', 'value' => true ],
	'showdir'=>['label' => 'Directories', 'value' => true ]
];

$use_dropdowns = 0;	// dropdown require bootstrap.js + popper.js - if not used, have lighter footprint by not importing them

$path = '';   // dir in FS encoding
$file = 0; // file in FS encoding
$root = ''; // volume to be considered for $path
$page = 0;
$action = '';
$filter = '*';
$log = null;
$status = 'OK';
$fastmode_threshold = 5000;
$multi_enabled = false;

$microstart = microtime(true);

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

$error = new message(); // error is displayed *instead* of files - this is used for critical situation where no data can be displayed
$info = new message(); // info is display *before* files when error does not prevents from displaying dir content

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

if (!array_key_exists ('fastmode', $conf)
&&  !array_key_exists ('fastmode', $conf['fastmode']))
	$fastmode_threshold = $conf['fastmode']['threshold'];

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

if (array_key_exists ('tz', $conf))
	date_default_timezone_set($conf['tz']);

//----------------------------------
// Session management
//----------------------------------
$session = new session();

if ($action == 'logout')
	$session -> invalidate();

// if changing auth method to ldap, sessions already open
// and gained with no auth are no longer valid
if ($session -> is_valid() && $session -> getLogin() == 'anonymous' && $conf['auth'] == 'ldap')
	$session -> invalidate();

if (!$session -> is_valid())
{
	if ($conf['auth'] == 'none') // auto-login
		$session -> setLogin ('anonymous');
	else // real login
	{
		// without a valid session, only two actions can be performed:
		// - display login form
		// - process login form

		if ($action == 'login')
			authenticate ();

		if (!$session -> is_valid())
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

	if ($action == 'bookmark')
	{
		set_path();
		if (is_dir_allowed ($path))
		{
			if (in_array ($path, $bookmarks))
			{
				$info -> set ([
					'msg'  => 'Bookmark removed',
					'feather' => 'check-circle',
					'level' => 'success'
				]);

				if (($key = array_search($path, $bookmarks)) !== false)
				{
					unset($bookmarks[$key]);
					$status = 'OK';
				}
				else
					$status = 'NOK';
			}
			else
			{
				if (count($bookmarks) >= $conf['bookmarks']['max'])
				{
					$info -> set([
						'msg' => 'Maximum number of bookmarks reached',
						'level' => 'warning',
						'feather' => 'slash'
					]);
					$status = 'NOK';
				}
				else
				{
					$info -> set ([
						'msg' => 'Bookmark added',
						'feather' =>  'check-circle',
						'level' => 'success'
					]);

					$bookmarks[] = $path;
					$status = 'OK';
				}
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

	if ($root == null || $root['upload'] == 'no')
	{
		$info -> set ([
			'msg' => 'Upload not allowed in this directory',
			'feather' => 'slash',
			'level' => 'danger'
		]);
		$status = 'NOK';
	}
	else
	{
		$name = $_FILES["fileToUpload"]["name"];
		$size = $_FILES["fileToUpload"]["size"];
		$ext  = strtolower(pathinfo($name,PATHINFO_EXTENSION));

		if (strlen ($name) == 0)
		{
			$info -> set ([
				'msg' => 'No file selected for upload',
				'level' => 'warning',
				'feather' => 'slash'
			]);
			$status = 'NOK';
		}
		else
		{
			$target_file = $path . '/' . $name;

			if (@move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
			{
				$info -> set ([
					'msg' => 'File successfuly uploaded',
					'level' => 'success',
					'feather' => 'check-circle'
				]);
				$status = 'OK';
			}
			else
			{
				// $_FILES["fileToUpload"]["error"] might contain an error number
				// from 1 to 8, or zero
				// for instance, attempting to override a file without appropriate perms will lead to code 0
				$info -> set ([
					'msg' => 'Upload failure',
					'level' => 'danger',
					'feather' => 'alert-triangle'
				]);
				$status = 'NOK';
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

	if ($root == null || $root['delete'] == 'no')
	{
		$status = 'NOK';
		$info -> set ([
			'msg' => 'Deletion is not allowed in this directory',
			'feather' => 'slash',
			'level' => 'danger'
		]);
	}
	else
	{
		$no_link = make_link ([ ]);
		$yes_link = make_link ([ 'file' => $file, 'action' => 'delete' ]);

		$buttons = [ ];
		$buttons[] = '<a class="btn btn-primary" href="'.$yes_link.'" role="button">Yes</a>';
		$buttons[] = '<a class="btn btn-primary" href="'.$no_link.'" role="button">No</a>';

		$info -> set ([
			'msg' => 'Delete ' . $file . ' ?', // TODO: utf8_encode
			'feather' => 'help-circle',
			'level' => 'warning',
			'buttons' => $buttons
		]);
		$status = 'OK';
	}
}

//==============================================================
// Deletion (effective)
//==============================================================

if ($action == 'delete')
{
	set_path();
	if ($root == null || $root['delete'] == 'no')
	{
		$info -> set ([
			'msg' => 'This action is not allowed',
			'feather' => 'slash',
			'level' => 'danger'
		]);
		$status = 'NOK';
	}
	else
	{
		if (@unlink($pathname))
		{
			$info -> set ([
				'msg' => 'File ' .$file. ' deleted', // TODO: utf8_encode
				'level' =>  'success',
				'feather' => 'check-circle'
			]);
			$status = 'OK';
		}
		else
		{
			$info -> set ([
				'msg' => 'Deletion failed',
				'level' => 'danger',
				'feather' => 'alert-triangle'
			]);
			$status = 'NOK';
		}
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
		$p = new filename ($path);
		$filename = $p -> get_basename ($path) . '.csv';

		//header('Content-Type: application/octet-stream');
		header('Content-Type: text/csv');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $filename . "\""); 

		// Insert the UTF-8 BOM in the file
		// always do that, as native iso-8859-1 directories will be exported as utf-8
		$bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
		echo $bom;

		csv_to_stdout ($path); 

		$status = 'OK';

		if (array_key_exists ('log', $conf)
		&&  array_key_exists ('file', $conf['log']))
		{
			$log = new log($conf['log']['file']);
			try
			{
				$log -> log([ $action, $path, '', $status ]);
			} catch (Exception $e) {
			global_failure ($e -> getMessage() );
			}
		}

		die();
	}

	$info -> set ([
		'level' => 'danger',
		'feather' => 'slash',
		'msg' => 'Operation not permitted'
	]);
	$status = 'NOK';
}
//==============================================================
// Download a file
//==============================================================
if ($action == 'download')
{
	set_path();
	if ($root != null && $root['download'] == 'yes')
	{
		$sz = @filesize($pathname);
		$type = @filetype ($pathname);

		if ($type == 'file')
		{
			header('Content-Type: application/octet-stream');
			header("Content-Transfer-Encoding: Binary"); 
			header("Content-disposition: attachment; filename=\"" . $file . "\""); 
			header("Content-Length: " . $sz);
			readfile($pathname); 

			$status = 'OK';
			if (array_key_exists ('log', $conf)
			&&  array_key_exists ('file', $conf['log']))
			{
				$log = new log($conf['log']['file']);
				try
				{
					$log -> log([ $action, $path, $file, $status ]);
				} catch (Exception $e) {
				global_failure ($e -> getMessage() );
				}
			}

			die();
		}
	}

	$info -> set ([
		'level' => 'danger',
		'feather' => 'slash',
		'msg' => 'Operation not permitted'
	]);
	$status = 'NOK';
}

//==============================================================
// Functions used
//==============================================================

// return applicable array among GET, POST, REQUEST, COOKIE
function
get_origin (string $origin): ?array
{
	$from = null;

	switch ($origin)
	{
	case 'GET' :
		$from = $_GET;
		break;
	case 'POST' :
		$from = $_POST;
		break;
	case 'REQUEST' :
		$from = $_REQUEST;
		break;
	case 'COOKIE' :
		$from = $_COOKIE;
		break;
	}

	return $from;
}

function
set_orderby(string $origin = 'GET'): void
{
	global $orderby, $order;

	$from = get_origin ($origin);
	if (is_null ($from))
		$from = [ ];

	if (array_key_exists ('orderby', $from) && is_valid_orderby ($from['orderby']))
		$orderby = $from['orderby'];
	else
		$orderby = 'name';

	if (array_key_exists ('order', $from) && is_valid_order ($from['order']))
		$order = $from['order'];
	else
		$order = 'asc';
}

function
set_filters(string $origin = 'GET'): void
{
	global $booleanfilters;

	$from = get_origin ($origin);
	if (is_null ($from))
		$from = [ ];

	foreach ($booleanfilters as $filtername=>$booleanfilter)
		if (array_key_exists ($filtername, $from))
			$booleanfilters[$filtername]['value'] = ($from[$filtername] == '1');
		else
			$booleanfilters[$filtername]['value'] = true;

	global $filter;
	if (array_key_exists ('filter', $from))
	{
		$filter = $from['filter'];
		if ($filter == '')
			$filter = '*';
	}
	else
		$filter = '*';
}

// this function sets $path, $file and $pathname globals
// from $_GET parameters
// whatever the parameters (relative, absolute, with path in filename etc.)
// it will provide a clean canonical directory and a clean filename
// this is important so to avoid bypassing configured permissions

function
set_path(string $origin = 'GET'): void
{
	global $path, $file, $pathname, $error;

	$path = $file = '';

	$from = get_origin ($origin);
	if (is_null ($from))
	{
		$error -> set ([
			'msg' => 'Invalid origin',
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
		return;
	}

	if (!array_key_exists('path', $from))
		return; // no path, no info

	// we have a path
	$path = rawurldecode ($from['path']);

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
		// new filename('/home/mydir/../../etc/passwd')
		// get_real_pathname(): /etc/passwd
		// get_dirname(): /etc
		// get_basename(): passwd

		$pathname = $path . '/' . $file;
		$p = new filename ($pathname);

		// pathname uses native fs encoding (might not be suitable for display)

		$path = $p -> get_dirname();
		$file = $p -> get_basename();

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
		$p = new filename ($path);
		$path = $p -> get_real_pathname($path);
	}

/*
	if ($path == '')
		$error -> set ([
			'msg' => 'Cannot read directory', // use same message for non-exitent and unreadable
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
*/
	global $root;
	$root = get_volume ($path);
}

// set pageno from $_GET or $_POST or $_REQUEST array
function
set_pageno(string $origin = 'GET'): void
{
	global $pageno, $nbpages;

	$from = get_origin ($origin);

	if (array_key_exists ('page', $from) && is_numeric($from['page']))
		$pageno = ($from['page'] - 1);
	else
		$pageno = 0;

	if ($nbpages == 0)
		$pageno = 0;
	else
		if ($pageno >= $nbpages)
			$pageno = $nbpages - 1;
	if ($pageno < 0)
		$pageno = 0;
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
				echo $file['name-utf8'];
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
	global $info;
	global $conf;
	global $session;

	if (!array_key_exists ('login', $_POST) || ($_POST['login'] == ''))
	{
		$info -> set ([
			'msg' => 'Empty login',
			'level' => 'danger',
			'feather' => ''
		]);
		return;
	}
	if (!array_key_exists ('password', $_POST) || ($_POST['password'] == ''))
	{
		$info -> set ([
			'msg' => 'Empty password',
			'level' => 'danger',
			'feather' => ''
		]);
		return;
	}

	$login = $_POST['login'];
	$password = $_POST['password'];

	if (!function_exists ('ldap_connect'))
	{
		$info -> set ([
			'msg' => 'missing function ldap_connect(), please review PHP configuration',
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
		return;
	}

	$ldapconn = @ldap_connect($conf['ldap']['server'],$conf['ldap']['port']);
	if (!$ldapconn)
	{
		$info -> set ([
			'msg' => 'Cannot connect to LDAP server (' . $conf['ldap']['server'] . ')',
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
		return;
	}

	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION,$conf['ldap']['protocol_version']);
	ldap_set_option($ldapconn, LDAP_OPT_REFERRALS,0);

	$pattern = $conf['ldap']['pattern'];
	$dn = str_replace ('{login}', $login, $pattern);

	$lb = @ldap_bind($ldapconn,$dn,$password);

	ldap_close($ldapconn);

	if ($lb === true)	// LDAP - OK
		$session -> setLogin ($login);
	else
		$info -> set ([
			'msg' => 'Invalid credential',
			'level' => 'danger',
			'feather' => 'slash'
		]);
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
    <meta name="description" content="filebrowser">
    <meta name="author" content="willycat">
    <link rel="icon" href="images/folder.png" />

    <title>File browsing '.$ver.'</title>

    <!-- Bootstrap core CSS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<?php if ($use_dropdowns) { ?>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
<?php } ?>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
<link rel="stylesheet" href="css/filebrowser.css">
<link rel="stylesheet" href="css/check-box.css">
<script src="js/filebrowser.js"></script>

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
	$perms = @fileperms ($pathname);
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
	global $total_size_used;
	global $conf;
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


function
extend (array &$a): void
{
	$pathname = $a['pathname'];

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
	$a['mtime'] = @filemtime ($pathname);
	if ($a['mtime'])
		$a['mtime-display'] = strftime ('%F %T', $a['mtime']);
	else
		$a['mtime-display'] = '';
	$a['ctime'] = @filectime ($pathname);
	if ($a['ctime'])
		$a['ctime-display'] = strftime ('%F %T', $a['ctime']);
	else
		$a['ctime-display'] = '';
}
// reads $path
function
parse_dir (): void
{
	global $error;
	global $conf, $path, $files;
	global $root;
	global $booleanfilters;
	global $total_size_used;
	global $filter;
	global $status;

	//-------------------
	// checks is the user is allowed to display this directory
	// it is allowed only if it is below one of its allowed roots
	//-------------------

	if ($root == null)
	{
		$error -> set ([
			'msg' => 'You are not allowed to view this directory',
			'level' =>  'danger',
			'feather' => 'slash'
		]);
		$status = 'NOK';
		return;
	}

	//-------------------
	// check if it is a directory
	//-------------------

	if (!is_dir ($path))
	{
		$error -> set ([
			'msg' => 'Not a directory',
			'level' => 'danger',
			'feather' => 'slash'
		]);
		$status = 'NOK';
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

	$dirfiles = @scandir ($path);
	if ($dirfiles === false)
	{
		$error -> set ([
			'msg' => 'Cannot read directory', // use same message for non-exitent and unreadable
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
		$status = 'NOK';
		return;
	}

	/*
	$dh = opendir ($path);
	if (!$dh)
	{
		$error -> set ([
			'msg' => 'Cannot read directory', // use same message for non-exitent and unreadable
			'level' => 'danger',
			'feather' => 'alert-triangle'
		]);
		$status = 'NOK';
		return;
	}

	clearstatcache(); // force re-reading of content, resetting cache

	$i = 0;
	while (($file = readdir ($dh)) !== false)
	{
	*/

	global $fastmode;
	if (count ($dirfiles) > $fastmode_threshold)
		$fastmode = true;
	else
		$fastmode = false;

	$i = 0;
	foreach ($dirfiles as $file)
	{
		if ($file == '.' || $file == '..')
			continue;

		if ($root['showhiddenfiles'] == 'no')
			if (substr ($file, 0, 1) == '.')
				continue;

		$pathname = $path . '/' . $file;

		$a = [ ];
		$a['pathname'] = $pathname;

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
		if (!$fastmode)
			extend ($a);

		if ($a['type'] == 'link')
		{
			$linktarget = readlink ($pathname);
			if (substr($linktarget,0,1) == '/')
				$p = new filename ($linktarget);
			else
				$p = new filename ($path . '/' . $linktarget);
			$a['target'] = $p -> get_real_pathname();
		}

		if ($a['type'] == 'dir' && !$booleanfilters['showdir']['value'])
			continue;
		if ($a['type'] == 'file' && !$booleanfilters['showfiles']['value'])
			continue;
		if (!filter_match ($a['name-utf8'], $filter))
			continue;

		$files[$i++] = $a;
	}

	/*
	closedir ($dh);
	*/
}

function
filter_match (string $str, string $filter): bool
{
	if ($filter == '' || $filter == '*')
		return true;

	return fnmatch ($filter, $str);
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
	echo $msg;
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

	global $info;
	$info -> display();

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
			$action_link = make_link ([
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
			$action_link = make_link ([
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
display_column (int $linenum, int $from, int $to, array $file, string $column, array $root): void
{
	global $path;
	global $fastmode;

	if ($fastmode)
		extend ($file);

	$a_open = false;

	echo '<td class="col-'.strtolower($column).'">';
	switch ($column)
	{
	case 'checkbox' :
		if ($file['type'] == 'file')
			echo '<input type="checkbox" id="L'.($linenum+1).'" onClick="showHideGroupActions('.$from.','.$to.')">';
		break;
	case 'Filename' :
		if ($file['type'] == 'dir')
		{
			echo '<a href="' . make_link([ 'path' => $path. '/' . $file['name'] ]) . '">';
			$a_open = true;
		}

		if ($file['type'] == 'link')
			if (is_dir ($file['target']))
			{
				echo '<a href="' . make_link([ 'path' => $file['target']]) .'" title="'.$file['target'].'">';
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
		if ($file['type'] == 'file') // if empty, unreadable, if <> 'file', might not be suitable
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
display_line (int $linenum, int $from, int $to, array $file, array $columns, array $root): void
{
	if ($from == -1)
		return;

	echo '<tr>';
	foreach ($columns as $column)
		display_column ($linenum, $from, $to, $file, $column, $root);
	echo '</tr>';
}

function
make_js_link (array $parms): string
{
	$url = 'index.php' . make_link ($parms);
	return 'window.location=' . "'" . $url . "'";
}

function
make_link (array $parms, string $format='get', array $exclude = []): string
{
	// fill missing entries
	$parmskeys = [ 'path', 'orderby', 'order', 'file', 'filter' ];
	foreach ($parmskeys as $parmkey)
		if (!array_key_exists ($parmkey, $parms))
			$parms[$parmkey] = '';

	if (!array_key_exists ('action', $parms))
		$parms['action'] = 'list';

	if (!array_key_exists ('page', $parms))
	{
		global $pageno;
		$parms['page'] = ($pageno+1);
	}

	// re-use current settings
	global $orderby, $order, $path, $filter;
	if ($parms['filter'] == '')
		$parms['filter'] = $filter;
	if ($parms['orderby'] == '')
		$parms['orderby'] = $orderby;
	if ($parms['order'] == '')
		$parms['order'] = $order;
	if ($parms['path'] == '')
		$parms['path'] = $path;

	// if not set to a special value, then re use current parms
	global $booleanfilters;
	foreach ($booleanfilters as $filtername=>$booleanfilter)
		if (!array_key_exists ($filtername, $parms))
			$parms[$filtername] = ($booleanfilters[$filtername]['value'] ? '1' : '0' );

	switch ($format)
	{
	case 'get' :
		// encode each part
		$urlparts = [ ];
		foreach ($parms as $key => $value)
			if (!in_array ($key, $exclude))
				$urlparts[] = $key . '=' . rawurlencode ($parms[$key]);

		// paste parts together
		$link = '?' . implode ('&', $urlparts);
		break;

	case 'post' :
		// encode each part
		$urlparts = [ ];
		foreach ($parms as $key => $value)
			if (!in_array ($key, $exclude))
				$urlparts[] = '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars ($parms[$key]) . '">'; // TODO : pas rawurlencode
		// paste parts together
		$link = implode ("\n", $urlparts);
		break;
	}

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
	//if ($nbpages < 2)
		//return;

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
display_checkbox ($parms): void
{
	echo build_checkbox($parms);
}

// label, name, checked, id
function
build_checkbox(array $parms): string
{
	if ($parms['checked'])
	{
		$checked = 'checked';
		$new_value = 0;
	}
	else
	{
		$checked = '';
		$new_value = 1;
	}

	$js_link = make_js_link([ 'page' => 1, $parms['name'] => $new_value ]);

	return '
	<div class="checkbox checbox-switch switch-primary">
	    <label>
		<input type="checkbox" name="'.$parms['name'].'" '.$checked.' onChange="'.$js_link.'" />
		<span></span>
		' . $parms['label'] . '
	    </label>
	</div>
	';
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
show_breadcrumb(): void
{
	global $path, $conf;

	if ($path == '')
		return;
?>
	<div>
	<ul class="breadcrumb">
	<?php
	$dirparts = explode ('/' , $path);
	$linkpath = '';
	echo '<li class="breadcrumb-item">';
	if ($conf['bookmarks']['enabled'] == 'yes')
	{
		echo '<A HREF="'.make_link([
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
		if ($root == null)	// not allowed
		{
			$dirlevel_utf8 = $dirlevel;
			echo '<li class="breadcrumb-item">' . $dirlevel_utf8 . '</li>';
		}
		else
		{
			if ($root['encoding'] == 'iso-8859-1')
				$dirlevel_utf8 = utf8_encode($dirlevel);
			else
				$dirlevel_utf8 = $dirlevel;

			$link = make_link ([ 'page' => 1, 'path' => $linkpath ]);
			echo '<li class="breadcrumb-item"><A HREF="' . $link . '" title="'.$linkpath.'">' . $dirlevel_utf8 . '</a></li>';
		}
	}
	?>
	</ul>
	</div>
<?php
}

function
display_checkboxes_and_pagination(): void
{
	global $booleanfilters;
	global $pageno, $nbpages;
?>
<div class="">
  <div class="row justify-content-around">
<?php
	foreach ($booleanfilters as $filtername=>$booleanfilter)
	{
?>
    <div class="col-md-auto">
      <?= display_checkbox([
	'label' => $booleanfilter['label'],
	'name' => $filtername,
	'checked' => $booleanfilter['value'],
	'id' => $filtername
	]); ?>
    </div>
<?php
	}
?>
    <div class="col-md-auto" id="multi">
MULTI
    </div>

    <div class="col-sm">
	<?php show_pagination ($pageno, $nbpages); ?>
    </div>

  </div>
</div>
<?php
}

function
display_files (): void
{
	global $path;
	global $files;
	global $pageno, $nbpages;
	global $order, $orderby;

	display_checkboxes_and_pagination();

	global $conf;
	$columns = $conf['display']['columns'];
	$n = count($files);

	if ($n == 0)	// no data
		$from = $to = -1;
	else
	{
		// global indexes displayed on this page
		$from = $pageno * $conf['display']['pagesize'];
		$to = min ($n-1, ($pageno+1)* $conf['display']['pagesize'] -1);
	}
?>
          <div class="table-responsive">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
<?php
global $fastmode;

foreach ($columns as $column)
{
	$add = '';
	if ($column == 'actions')
		$add = 'width=100';
	echo '<th ' . $add . '>';

	$sortname = '';
	switch ($column)
	{
	case 'checkbox' :
		echo '<input type="checkbox" onClick="revertSelection('.$from.','.$to.')" id="LREVERT">';
		break;
	case 'Filename' :
		$sortname = 'name';
		break;
	case 'Size' :
		if (!$fastmode)
			$sortname = 'size';
		break;
	case 'mtime' :
		if (!$fastmode)
			$sortname = 'mtime';
		break;
	default:
		break;
	}

	if ($sortname != '')
	{
		$new_order = (($order == 'asc') ? 'desc' : 'asc');
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
		if ($column != 'checkbox')
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
if ($n > 0) // some data
	for ($lineno = 0, $i = $from; $i <= $to; $i++, $lineno++)
	{
		if ($files[$i]['type'] == 'file')
			$page_size_used += $files[$i]['size'];
		display_line ($i, $from, $to, $files[$i], $columns, $root);
	}
?>
              </tbody>
            </table>
          </div>
<?php
global $total_size_used;
global $footer;
global $microstart;
$microduration = microtime(true) - $microstart;
$footer = '';
$footer .= "Displaying page ".($pageno+1)."/".$nbpages." sorted on ".$orderby." in ".$order." order";
if ($n > 0)
{
	$footer .= " - Files " . (1+$from) . ' - ' . (1+$to) . ' out of ' . $n;
	$footer .= ' - ' . "Page contains " . formatBytes($page_size_used,2) . ' out of ' . formatBytes($total_size_used,2);
}
$footer .= ' - Native encoding: ' . $root['encoding'];
$footer .= '&nbsp;&nbsp;' . csv_button ();
if ($root['upload'] == 'yes')
	$footer .= '  ' . upload_button();
//global $fastmode;
//$footer .= '<BR>Took ' . sprintf('%.2f', $microduration) . ' s (fastmode ' . ($fastmode ? 'on' :'off') . ')';
}

//==========================================================
// MAIN
//==========================================================

$debugstr = '';
if ($path == '')
	set_path('REQUEST');
if ($path == '')
{
	$error -> set ([
		'msg' => 'Please select a directory',
		'level' => 'info'
	]);
}
else
{
	set_filters('REQUEST');
	get_dir_content ();
	set_pageno('REQUEST');
}

if (array_key_exists ('log', $conf)
&&  array_key_exists ('file', $conf['log']))
{
	$log = new log($conf['log']['file']);
	try
	{
		$log -> log([ $action, $path, $file, $status ]);
	} catch (Exception $e) {
		global_failure ($e -> getMessage() );
	}
}

send_html_head();
?>

  <body>
	<form name="filter" method="POST" action="index.php">
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
      <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="<?=$conf['title']['url'] ?>" alt="<?=$conf['title']['text'] ?>" title="<?=$conf['title']['text'] ?>">
<?php
	if ($conf['title']['image'] != '')
		echo '<img src="' . $conf['title']['image'] . '">';
	else
		echo htmlentities($conf['title']['text']);
?>
	</a>
	<?= make_link ([ 'page' => 1 ], 'post', [ 'filter' ]) ?>
      <input class="form-control form-control-dark w-100" type="text" placeholder="Filter" aria-label="Filter" name="filter" value="<?=htmlspecialchars($filter)?>">
      <ul class="navbar-nav px-3">
        <li class="nav-item text-nowrap">
        </li>
      </ul>
    </nav>
	</form>

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
                  Logout <?=$session -> getLogin() ?>
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
	$info -> display();
	show_breadcrumb();
?>
	<div>
<?php
	if ($error -> getMsg() != '')
		$error -> display();
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
		hideGroupActions();
            });
    </script>

  </body>
</html>
