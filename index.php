<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="/docs/4.0/assets/img/favicons/favicon.ico">

    <title>Dashboard Template for Bootstrap</title>

    <link rel="canonical" href="https://getbootstrap.com/docs/4.0/examples/dashboard/">

    <!-- Bootstrap core CSS -->
<link rel="stylesheet" href="lib/bootstrap/css/bootstrap.min.css">


    <!-- Custom styles for this template -->
    <link href="dashboard.css" rel="stylesheet">
  </head>

<?php

//----------------------------------------------------------
$default_dir = '/var/www/html/http_test/www/filebrowser';

$fw_conf = [ ];
$fw_conf['ldap'] = [ ];
$fw_conf['ldap']['server'] = 'infb-pw1.darva.admin';
$fw_conf['ldap']['port']   = '389';
$fw_conf['ldap']['pattern']= 'cn={login},ou=DAP,ou=DOP,dc=darva,dc=intra';

$npp = 10;

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
	    case 0x8000: // R�gulier
		$info = '-';
		break;
	    case 0x6000: // Block special
		$info = 'b';
		break;
	    case 0x4000: // dossier
		$info = 'd';
		break;
	    case 0x2000: // Caract�re sp�cial
		$info = 'c';
		break;
	    case 0x1000: // pipe FIFO
		$info = 'p';
		break;
	    default: // Inconnu
		return '';
	}

	// Propri�taire
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

function
show_login_form(): string
{
echo '
<body>
<center>
<TABLE WIDTH=500>
<TR>
<TD>
	<form method="post" action="index.php">
		<fieldset>
		<legend>Login</legend>
		<p><label>Login<br><input type="text" name="login" size="30" /></label></p><br>
		<p><label>Password<br><input type="password" name="pass" size="30" /></label></p><br>
		<p><div id="authzone"><input id="authbutton" type="submit" value="Authenticate" /></div></p></center>
		</fieldset>
<input type="hidden" name="dest" value="<?=$dest ?>">
	</form>
</TD>
</TR>
</TABLE>
</center>
</body>
</html>
';
}

session_start();

if (array_key_exists ('login', $_POST))
{
$login = $_POST['login'];
$password = $_POST['password'];

if (!function_exists ('ldap_connect'))
{
	echo 'missing ldap_connect';
	die (1);
}

$ldapconn = ldap_connect($fw_conf['ldap']['server'],$fw_conf['ldap']['port']);
	if (!$ldapconn)
	  die( 'Cannot connect to [' . $fw_conf['ldap']['server'] . ']');

	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION,3);
	ldap_set_option($ldapconn, LDAP_OPT_REFERRALS,0);

	$pattern = $fw_conf['ldap']['pattern'];
	str_replace ('{login}', $login);
	$lb = ldap_bind($ldapconn,$dn,$pass);
	ldap_close($ldapconn);

	if ($lb === true)	// LDAP - OK
	{
		$_SESSION['filebrowseruser'] = $login;
		$_SESSION['filebrowservalid'] = 1;
	}
	else
	{
		unset ($_SESSION['filebrowseruser']);
	}
}

if (array_key_exists ('action', $_GET) && $_GET['action'] == 'logout')
{
	unset ($_SESSION['filebrowseruser']);
	show_login_form();
	die (0);
}

if (!array_key_exists ('filebrowseruser', $_SESSION)) // not logged in
{
	show_login_form();
	die (0);
}

if (array_key_exists ('path', $_GET))
	$path = $_GET['path'];
else
	$path = $default_dir;
$path = realpath ($path); // transforme /toto/titi/../tutu en /toto/tutu

$_SESSION['dir'] = $path;

if (!is_dir ($path))
{
	echo 'Not a dir';
	die(1);
}

$dh = opendir ($path);
if (!$dh)
{
	echo 'Cannot read dir';
	die(1);
}

clearstatcache();
$files = [];
$i = 0;
while (($file = readdir ($dh)) !== false)
{
	if ($file == '.' || $file == '..')
		continue;

	if (substr ($file, 0, 1) == '.')
		continue;

	$pathname = $path . '/' . $file;

	$a = [ ];
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
}

$n = count ($files);
$nbpages = floor ($n / $npp);
if ($n % $npp)
	$nbpages++;
if (array_key_exists ('page', $_GET))
	$pageno = ($_GET['page'] - 1);
else
	$pageno = 0;

closedir ($dh);

usort ($files, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
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
              <li class="nav-item">
                <a class="nav-link active" href="#">
                  <span data-feather="file"></span>
                  Files
                </a>
              </li>
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

	<div>
	<ul class="breadcrumb">
	<?php
	$dirparts = explode ('/' , $path);
	$linkpath = '';
	foreach ($dirparts as $dirlevel)
	{
		if ($dirlevel == '')
			continue;
			$linkpath .= '/' . $dirlevel;
		/*
		{
			$dirlevel = 'ROOT';
			$linkpath = '/';
		}
		else
		*/
		echo '<li class="breadcrumb-item"><A HREF="?path='.$linkpath.'">' . $dirlevel . '</a></li>';
	}
	?>
	</ul>
	</div>
	<div>
		<span><?=count($files)?> file(s)</span>
<?php
if ($pageno == 0)
	$prev_attributes = 'disabled';
else
	$prev_attributes = '';
if (($pageno+1) >= $nbpages)
	$next_attributes = 'disabled';
else
	$next_attributes = '';
?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-end">
    <li class="page-item <?=$prev_attributes ?>">
      <a class="page-link" href="?path=<?=$path?>&page=<?=$pageno?>" tabindex="-1">Previous</a>
    </li>
<?php
	for ($page = 1; $page <= $nbpages; $page++) {
	if ($page == ($pageno+1) )
		$item_attributes = 'active';
	else
		$item_attributes = '';
	$page_link = '?path='.$path.'&page='.$page;
?>    
    <li class="page-item <?=$item_attributes ?>"><a class="page-link" href="<?=$page_link?>"><?=$page ?></a></li>
<?php } ?>
    <li class="page-item <?=$next_attributes ?>">
      <a class="page-link" href=""?path=<?=$path?>&page=<?=$pageno+2?>">Next</a>
    </li>
  </ul>
</nav>
	</div>

          <div class="table-responsive">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>Filename</th>
                  <th>Size</th>
                  <th>Perms</th>
                  <th>mtime</th>
                  <th>ctime</th>
                  <th>type</th>
                </tr>
              </thead>
              <tbody>
<?php
//foreach ($files as $file)
for ($i = ($pageno * $npp); $i < min($n, ($pageno+1)*$npp-1); $i++)
{
	$file = $files[$i];
	echo '<tr>';
	echo '<td>';
	$a_open = false;
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
	echo $file['name'];
	if ($file['type'] == 'dir')
		echo '/';
	if ($a_open)
		echo '</a>';
	echo '</td>';
	echo '<td>';
	if ($file['type'] == 'file')
	{
		echo '<span title="'.$file['size'].'">';
		echo $file['formattedsize'];
		echo '</span>';
	}
	echo '</td>';
	echo '<td>' . $file['perms'] . '</td>';
	echo '<td>' . $file['mtime-display'] . '</td>';
	echo '<td>' . $file['ctime-display'] . '</td>';
	echo '<td>' . $file['type'] . '</td>';
	echo '</tr>';
}
?>
              </tbody>
            </table>
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

