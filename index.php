<?php
$conf['actions'] = [ 'download', 'delete' ];
$conf_content = @file_get_contents ('/etc/filebrowser/conf');
if ($conf_content === false)
	die ('Cannot read configuration file');

try
{
	$conf = json_decode ($conf_content, true, 512, JSON_THROW_ON_ERROR );
} catch (Exception $e) {
	die ('Cannot decode conf file');
}
$conf['actions'] = [ 'download', 'delete' ];
// Setting volume entries to defaults or to lower case
foreach ($conf['volumes'] as $key => $value)
{
	if (!array_key_exists ('encoding', $conf['volumes'][$key]))
		$conf['volumes'][$key]['encoding'] = 'utf-8';
	else
		$conf['volumes'][$key]['encoding'] = strtolower($conf['volumes'][$key]['encoding']);
	if (!array_key_exists ('delete', $conf['volumes'][$key]))
		$conf['volumes'][$key]['delete'] = '0';
	if (!array_key_exists ('download', $conf['volumes'][$key]))
		$conf['volumes'][$key]['download'] = '0';
	if (!array_key_exists ('upload', $conf['volumes'][$key]))
		$conf['volumes'][$key]['upload'] = '0';
}

$info_level = ''; // primary (bleu), secondary (gris clair), success (vert), danger (rouge), warning (jaune), info (gris-bleu), light (blanc), dark (gris fonce)
$info_msg = '';
session_start();
if (array_key_exists ('action', $_GET) && $_GET['action'] == 'logout')
	session_invalidate();
if (!session_is_valid())
{
	if (array_key_exists ('login', $_POST))
		do_login();

	if (!session_is_valid())
	{
		send_html_head();
		show_login_form();
		die();
	}
}


//==============================================================
// ADD/REMOVE BOOKMARK
//==============================================================

if ($conf['bookmarks']['enabled'] == '1')
{
	$bookmarks_cookie_name = 'bookmarks';
	if (array_key_exists ($bookmarks_cookie_name, $_COOKIE))
		$bookmarks = unserialize ($_COOKIE[$bookmarks_cookie_name]);
	else
		$bookmarks = [ ];
	if (array_key_exists ('action', $_GET) && $_GET['action'] == 'bookmark')
	{
		$path = $_GET['path'];
		if (is_dir_allowed ($path))
		{
			$info_level = 'success';
			if (in_array ($path, $bookmarks))
			{
				$info_msg   = 'Bookmark removed';
				if (($key = array_search($path, $bookmarks)) !== false)
				    unset($bookmarks[$key]);
			}
			else
				if (count($bookmarks) >= $conf['bookmarks']['max'])
				{
					$info_msg   = 'Maximum number of bookmarks reached';
					$info_level = 'warning';
				}
				else
				{
					$info_msg   = 'Bookmark added';
					$bookmarks[] = $path;
				}
			$bookmarks_cookie_value = serialize($bookmarks);
			setcookie ($bookmarks_cookie_name, $bookmarks_cookie_value);
		}
	}
}

if (array_key_exists ('action', $_POST) && $_POST['action'] == 'upload')
{
	$path = $_POST['path'];

	if (is_dir_allowed ($path))
	{
		$name = $_FILES["fileToUpload"]["name"];
		$size = $_FILES["fileToUpload"]["size"];
		$ext  = strtolower(pathinfo($name,PATHINFO_EXTENSION));

		if (strlen ($name) == 0)
		{
			$info_msg = 'No file selected';
			$info_level = 'warning';
		}
		else
		{
			$target_file = $path . '/' . $name;

			if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file))
			{
				$info_msg = 'File successfuly uploaded';
				$info_level = 'success';
			}
			else
			{
				$info_msg = 'Upload failure';
				$info_level = 'danger';
			}
		}
	}
	else
	{
		$info_msg = 'Upload not allowed in this directory';
		$info_level = 'danger';
	}
}

if (array_key_exists ('action', $_GET) && $_GET['action'] == 'confirm-delete')
{
	$path = $_GET['path'];
	$file = $_GET['file'];
	$pageno = $_GET['page']-1;

	$root = get_volume ($path);
	if ($root == null || $root['download'] == '0')
	{
		$info_msg = 'This action is not allowed in this directory';
		$info_level = 'danger';
	}
	else
	{
		$info_msg = 'Delete ' . htmlentities($file) . ' ?';
		$info_msg .= '&nbsp;';
		$info_msg .= '&nbsp;';
		$info_msg .= '&nbsp;';
		$info_msg .= '&nbsp;';
		//$info_msg .= '<button type="button" class="btn btn-danger" ><span data-feather="trash"></span>&nbsp;&nbsp;YES</button>';
		$no_link = make_link ($pageno+1);
		$no_link .= '&file=' . $file['name'];
		$yes_link = $no_link . '&action=delete';
		$info_msg .= '<a class="btn btn-primary" href="'.$yes_link.'" role="button">Yes</a>';
		$info_msg .= '&nbsp;';
		$info_msg .= '<a class="btn btn-primary" href="'.$no_link.'" role="button">No</a>';
		$info_level = 'warning';
	}
}


if (array_key_exists ('action', $_GET) && $_GET['action'] == 'delete')
{
	$path = $_GET['path'];
	$root = get_volume ($path);
	if ($root == null || $root['download'] == '0')
	{
		$info_msg = 'This action is not allowed in this directory';
		$info_level = 'danger';
	}
	else
	{
		$info_msg = 'Deletion not implemented yet';
		$info_level = 'warning';
	}
}

if (array_key_exists ('action', $_GET) && $_GET['action'] == 'export')
{
	$path = $_GET['path'];
	if ($conf['csv']['enabled'] == '1' && is_dir_allowed ($path))
	{
		$filename = basename ($path) . '.csv';
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $filename . "\""); 
		csv_to_stdout ($path); 
		die();
	}
	// else, just ignore
}
if (array_key_exists ('action', $_GET) && $_GET['action'] == 'download')
{
	$path = $_GET['path'];
	$filename = $_GET['file'];

	if (is_dir_allowed ($path))
	{
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . $filename . "\""); 
		readfile($path . '/' . $filename); 
		die();
	}

	$info_level = 'danger';
	$info_msg   = 'Operation not permitted';
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
				echo $file['name'];
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
do_login(): void
{
	global $info_msg, $info_level;
	global $conf;

	$login = $_POST['login'];

	if (!array_key_exists ('login', $_POST) || ($_POST['login'] == ''))
	{
		$info_msg = 'Empty login';
		$info_level = 'danger';
		return;
	}
	if (!array_key_exists ('password', $_POST) || ($_POST['password'] == ''))
	{
		$info_msg = 'Empty password';
		$info_level = 'danger';
		return;
	}
	$password = $_POST['password'];

	if (!function_exists ('ldap_connect'))
	{
		$info_msg = 'missing function ldap_connect(), please review PHP configuration';
		$info_level = 'danger';
		return;
	}

	$ldapconn = ldap_connect($conf['ldap']['server'],$conf['ldap']['port']);
	if (!$ldapconn)
	{
		$info_msg = 'Cannot connect to LDAP server (' . $conf['ldap']['server'] . ')';
		$info_level = 'danger';
		return;
	}

	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION,$conf['ldap']['protocol_version']);
	ldap_set_option($ldapconn, LDAP_OPT_REFERRALS,0);

	$pattern = $conf['ldap']['pattern'];
	$dn = str_replace ('{login}', $login, $pattern);

	$lb = ldap_bind($ldapconn,$dn,$password);

	ldap_close($ldapconn);

	if ($lb === true)	// LDAP - OK
		$_SESSION['filebrowseruser'] = $login; // make session valid
	else
	{
		$info_msg = 'Invalid credential';
		$info_level = 'danger';
	}
}


function
send_html_head(): void
{
	echo '
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="ico/darva.png">

    <title>File browsing</title>

    <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="lib/bootstrap/css/bootstrap.min.css">
<style>
.bookmark {
	text-overflow: ellipsis;
	width: 200px;
	overflow: hidden;
	display: block;
}

.icon {
	display: none;
	padding-right: 7px;
	padding-left: 7px;
}

.clickable {
	cursor: pointer;
}

.col-perms {
    font-family: monospace;
    font-size: initial;
}

.icon:hover {
	background: lightgrey;
}

tr:hover .icon {
	display: inline-block;
	cursor: pointer;
}

.form-signin {
    width: 100%;
    max-width: 330px;
    padding: 15px;
    margin: auto;
}

.footer {
    _position: absolute;
    bottom: 0;
    width: 100%;
    height: 60px;
    line-height: 60px;
    background-color: #f5f5f5;
}
</style>


    <!-- Custom styles for this template -->
    <link href="dashboard.css" rel="stylesheet">
  </head>
';
}

//----------------------------------------------------------
$default_dir = '/var/www/html/http_test/www/filebrowser';
$footer = '';

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
getperms (string $pathname): string
{
	$perms = fileperms ($pathname);
	switch ($perms & 0xF000) {
	    case 0xC000: // Socket
		$info = 's';
		break;
	    case 0xA000: // Lien symbolique
		$info = 'l';
		break;
	    case 0x8000: // Régulier
		$info = '-';
		break;
	    case 0x6000: // Block special
		$info = 'b';
		break;
	    case 0x4000: // dossier
		$info = 'd';
		break;
	    case 0x2000: // Caractère spécial
		$info = 'c';
		break;
	    case 0x1000: // pipe FIFO
		$info = 'p';
		break;
	    default: // Inconnu
		return '';
	}

	// Propriétaire
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ?
		    (($perms & 0x0800) ? 's' : 'x' ) :
		    (($perms & 0x0800) ? 'S' : '-'));

	// Groupe
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ?
		    (($perms & 0x0400) ? 's' : 'x' ) :
		    (($perms & 0x0400) ? 'S' : '-'));

	// Tout le monde
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
get_dir_content (string $queried_path): void
{
	global $errmsg, $conf;

	//-------------------
	// Determines real pathname
	// Changes /toto/titi/../tutu to /toto/tutu
	// if target does not exists, path equals to empty string
	// (might exists and not a directory)
	//-------------------

	global $path;
	$path = realpath ($queried_path);
	if ($path == '')
	{
		$errmsg = $queried_path . ' does not exists or is unreadable';
		return;
	}

	//-------------------
	// checks is the user is allowed to display this directory
	// it is allowed only if it is below one of its allowed roots
	//-------------------

	$root = get_volume ($path);
	if ($root == null)
	{
		$errmsg = 'You are not allowed to view this directory';
		return;
	}

	//-------------------
	// check if it is a directory
	//-------------------

	if (!is_dir ($path))
	{
		$errmsg = 'Not a directory';
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
		$errmsg = 'Cannot read directory';
		return;
	}

	global $files;
	global $total_size_used;
	$files = [];
	$total_size_used = 0;

	clearstatcache(); // force re-reading of content, resetting cache

	$i = 0;
	while (($file = readdir ($dh)) !== false)
	{
		if ($file == '.' || $file == '..')
			continue;

		if (substr ($file, 0, 1) == '.')
			continue;

		$pathname = $path . '/' . $file;

		$a = [ ];
		if ($root['encoding'] == 'iso-8859-1')
			$a['name'] = utf8_encode($file);
		else
			$a['name'] = $file;
		$a['size'] = @filesize ($pathname);
		$a['formattedsize'] = formatBytes(filesize ($pathname));
		$a['type'] = @filetype ($pathname);
		$a['perms'] = getperms ($pathname);
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
				$a['target'] = realpath($path . '/' . $linktarget);
		}
		$files[$i++] = $a;
		if ($a['type'] == 'file')
			$total_size_used += $a['size'];
	}
	closedir ($dh);

	//-------------------
	// some stats
	//-------------------

	global $n;
	$n = count ($files);

	//-------------------
	// paging computations
	//-------------------

	global $conf;
	global $nbpages;
	$nbpages = floor ($n / $conf['display']['pagesize']);
	if ($n % $conf['display']['pagesize'])
		$nbpages++;

	//-------------------
	// sort content
	//-------------------

	usort ($files, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
}

//----------------------------------------------------------

function
show_login_form(): void
{
	send_html_head();
	echo '
<body class="text-center">
	<form class="form-signin" data-bitwarden-watching="1" method="POST" action="index.php">
      <img class="mb-4" src="images/darva.png" >';
	global $info_msg, $info_level;
	display_error ($info_msg, $info_level);
	echo '
      <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
      <label for="inputEmail" class="sr-only">Account</label>
      <input type="text" id="inputEmail" class="form-control" placeholder="Account" required="" autofocus="" name="login">
      <label for="inputPassword" class="sr-only">Password</label>
      <input type="password" id="inputPassword" class="form-control" placeholder="Password" required="" name="password">
      <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
      <p class="mt-5 mb-3 text-muted">© 2020</p>
    </form>
</body>
</html>
';
}

function
display_action(string $action, array $file, array $root): void
{
	$feather = $action_link = '';

	if (array_key_exists ($action, $root)
	&& $root[$action] == '0')
		return;

	switch ($action)
	{
	case 'delete' :
		if ($file['type'] == 'file')
		{
			$feather = 'trash';
			$title = 'Delete file';
			global $pageno;
			$action_link = make_link ($pageno+1);
			$action_link .= '&action=confirm-delete';
			$action_link .= '&file=' . $file['name'];
		}
		break;
	case 'download': 
		if ($file['type'] == 'file')
		{
			$feather = 'download';
			$title = 'Download file';
			global $pageno;
			$action_link = make_link ($pageno+1);
			$action_link .= '&action=download';
			$action_link .= '&file=' . $file['name'];
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
			echo '<a href="?path='.$path.'/' . $file['name'].'">';
			$a_open = true;
		}

		if ($file['type'] == 'link')
			if (is_dir ($file['target']))
			{
				echo '<a href="?path='.$file['target'].'" title="'.$file['target'].'">';
				$a_open = true;
			}

		echo htmlentities($file['name']);
		if ($file['type'] == 'dir')
			echo '/';
		break;
	case 'Size' :
		if ($file['type'] == 'file')
		{
			echo '<span title="'.$file['size'].'">';
			echo $file['formattedsize'];
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
display_line (array $file, array $columns, array $root): void
{
	echo '<tr>';
	foreach ($columns as $column)
		display_column ($file, $column, $root);
	echo '</tr>';
}

function
make_js_link (int $page, string $extra = ''): string
{
	$url = 'index.php' . make_link ($page, '', $extra);
	return 'window.location=' . "'" . $url . "'";
}

function
make_link (int $page, string $linkpath = '',string $extra=''): string
{
	if ($linkpath == '')
	{
		global $path;
		$linkpath = $path;
	}

	$link = '?path='.$linkpath.'&page='.$page;
	if ($extra != '')
		$link .= '&' . $extra;

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

	$page_link = make_link ($page);
?>    
    <li class="page-item <?=$item_attributes ?>"><a class="page-link" href="<?=$page_link?>"><?=$page ?></a></li>
<?php
}

function
show_pagination (int $pageno, int $nbpages): void
{
	global $path;
	global $conf;

	if ($nbpages >= 2)
	{
	if ($pageno == 0)
		$prev_attributes = 'disabled';
	else
		$prev_attributes = '';
	$prev_link = make_link ($pageno);

	if (($pageno+1) >= $nbpages)
		$next_attributes = 'disabled';
	else
		$next_attributes = '';
	$next_link = make_link ($pageno+2);


?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-end">
    <li class="page-item <?=$prev_attributes ?>">
      <a class="page-link" href="<?=$prev_link?>" tabindex="-1">Previous</a>
    </li>
<?php
	for ($page = 1; $page <= $nbpages; $page++)
		show_page ($page, $pageno);
 ?>
    <li class="page-item <?=$next_attributes ?>">
      <a class="page-link" href="<?=$next_link?>">Next</a>
    </li>
  </ul>
</nav>
<?php } // nbpages >= 2 ?>
<?php
}

function
upload_button (): string
{
	global $conf, $pageno;

	if ($conf['csv']['enabled'] != '1')
		return '';

	$str  = '<span';
	$str .= ' class="badge badge-secondary clickable"';
	$str .= ' onClick=' . '"' . make_js_link($pageno+1,'action=upload-form') . '"';
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

	if ($conf['csv']['enabled'] != '1')
		return '';

	$str  = '<span';
	$str .= ' class="badge badge-secondary clickable"';
	$str .= ' onClick=' . '"' . make_js_link($pageno+1,'action=export') . '"';
	$str .= '>';
	$str .= '<span data-feather="arrow-down-circle"></span>';
	$str .= '&nbsp;&nbsp;CSV';
	$str .= '</span>';

	return $str;
}

//----------------------------------------------------------

function
display_error (string $msg, string $level = 'danger'): void
{
	if ($msg == '')
		return;
?>
	<div class="alert alert-<?=$level ?>" role="alert">
	  <?=$msg ?>
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
<input type="hidden" name="action" value="upload">
<input type="hidden" name="path" value="<?=$path ?>">
  <input type="file" name="fileToUpload" id="fileToUpload">
  <input type="submit" value="Upload" name="submit">
</form>
<?php
}

function
display_files (): void
{
	global $path;
	global $files;
	global $pageno, $nbpages;
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
	echo '<th ' . $add . '>' . $column . '</th>';
}
?>
                </tr>
              </thead>
              <tbody>
<?php
$root = get_volume ($path);
$page_size_used = 0;
for ($lineno = 0, $i = ($pageno * $conf['display']['pagesize']); ($lineno < $conf['display']['pagesize']) && ($i < $n); $i++)
{
	if ($files[$i]['type'] == 'file')
		$page_size_used += $files[$i]['size'];
	display_line ($files[$i], $columns, $root);
}
?>
              </tbody>
            </table>
          </div>
<?php
global $total_size_used;
global $footer;
$footer = "Displaying files " . (1+$pageno * $conf['display']['pagesize']) . ' - ' . min($n, ($pageno+1)*$conf['display']['pagesize']) . ' out of ' . $n . ' - ' . formatBytes($page_size_used,2) . ' out of ' . formatBytes($total_size_used,2);
$footer .= ' - Native encoding: ' . $root['encoding'];
$footer .= '&nbsp;&nbsp;' . csv_button ();
if ($root['upload'] == '1')
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

//----------------------------------------------------------

if (array_key_exists ('path', $_GET))
	$queried_path = $_GET['path'];
else
	$queried_path = $default_dir;

if (array_key_exists ('page', $_GET))
	$pageno = ($_GET['page'] - 1);
else
	$pageno = 0;

$errmsg = '';
get_dir_content ($queried_path);

send_html_head();
?>

  <body>
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
      <a class="navbar-brand col-sm-3 col-md-2 mr-0" href="#">DARVA</a>
      <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
      <ul class="navbar-nav px-3">
        <li class="nav-item text-nowrap">
        </li>
      </ul>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
          <div class="sidebar-sticky">
            <ul class="nav flex-column">

<?php
	foreach ($conf['volumes'] as $volume)
	{
?>
              <li class="nav-item">
                <a class="nav-link active" href="<?= make_link (1,$volume['path']) ?>">
                  <span data-feather="folder"></span>
                  <?=htmlentities($volume['name']) ?>
                </a>
              </li>
<?php
	}
?>
<?php
	if ($conf['bookmarks']['enabled'] == '1')
		foreach ($bookmarks as $bookmark)
		{
?>
		      <li class="nav-item">
			<a class="nav-link active" href="<?= make_link (1,$bookmark) ?>" title="<?=$bookmark?>">
			  <span data-feather="bookmark"></span>
			  <span class="bookmark"><?=$bookmark ?></span>
			</a>
		      </li>
<?php
		} // foreach
?>

              <li class="nav-item ">
                <a class="nav-link" href="?action=logout">
                  <span data-feather="log-out"></span>
                  Logout <?=$_SESSION['filebrowseruser'] ?>
                </a>
              </li>

            </ul>
          </div>
        </nav>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">

<?php
	display_error($info_msg, $info_level);
?>

	<div>
	<ul class="breadcrumb">
	<?php
	$dirparts = explode ('/' , $path);
	$linkpath = '';
	echo '<li class="breadcrumb-item">';
	if ($conf['bookmarks']['enabled'] == '1')
	{
		echo '<A HREF="'.make_link($pageno+1,'','action=bookmark').'">';
		echo '<span';
		echo ' class="clickable"';
		echo ' data-feather="bookmark"';
		echo '>';
		echo '</span>';
		echo '</a>';
	}
	echo '</li>';
	//echo '<li class="breadcrumb-item"></li>';
	foreach ($dirparts as $dirlevel)
	{
		if ($dirlevel == '')
			continue;
			$linkpath .= '/' . $dirlevel;
		$link = make_link (1,$linkpath);
		echo '<li class="breadcrumb-item"><A HREF="' . $link . '">' . $dirlevel . '</a></li>';
	}
	?>
	</ul>
	</div>
	<div>
<?php
	if ($errmsg != '')
		display_error ($errmsg);
	else
		if (array_key_exists ('action', $_GET) && $_GET['action'] == 'upload-form')
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
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>

    <!-- Icons -->
    <script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
    <script>
      feather.replace()
    </script>

  </body>
</html>

