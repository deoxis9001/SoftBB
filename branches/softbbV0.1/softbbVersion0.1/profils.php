<?php
/***************************************************************************
 *
 *   SoftBB - Forum de discussion 
 *   Version : 0.1
 *
 *   copyright            : (C) 2005 J�r�my Dombier [Belgium]
 *   email                : satapi@gmail.com
 *   site-web             : http://softbb.be/
 *
 *   Ce programme est un logiciel libre ; vous pouvez le redistribuer et/ou 
 *   le modifier au titre des clauses de la Licence Publique G�n�rale GNU, 
 *   telle que publi�e par la Free Software Foundation ; soit la version 2 de 
 *   la Licence, ou (� votre discr�tion) une version ult�rieure quelconque. 
 *   Ce programme est distribu� dans l'espoir qu'il sera utile, mais 
 *   SANS AUCUNE GARANTIE ; sans m�me une garantie implicite de COMMERCIABILITE 
 *   ou DE CONFORMITE A UNE UTILISATION PARTICULIERE. Voir la Licence Publique 
 *   G�n�rale GNU pour plus de d�tails. Vous devriez avoir re�u un exemplaire 
 *   de la Licence Publique G�n�rale GNU avec ce programme ; si ce n'est pas le 
 *   cas, �crivez � la Free Software Foundation Inc., 
 *   51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 ***************************************************************************/
 // version correctif patate_violente pour faille xss au niveau de l'upload
 
if(!isset($_POST['Submit']))				// cas o� pas de POST
	exit('Passer par le formulaire');

include('./includes/gpc.php');
 
session_start();
include('info_bdd.php');
include('info_options.php');

if(isset($_GET['id']) && !is_numeric($_GET['id']))			// ID erron�
	exit();

// connexion mysql
$db = mysql_connect($host,$user,$mdpbdd);
mysql_select_db($bdd,$db);

if(isset($_SESSION['idlog']) && $_SESSION['idlog'] != "")	// r�cup�ration de l'id membre
	$ida = $_SESSION['idlog'];		
else 	
{
	header('Location: index.php?page=erreur');
	exit(); 
}

// copy dans des nom de variables + simple
$avatar = $_POST['avatar'];
$localisation = $_POST['localisation'];
$avatarr = $_POST['avatarr'];
(isset($_POST['he'])) ? $he = 1 : $he = 0;  	// heure d'�t� ou pas

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Mise � jour suite � la faille trouv�e sur le contenu possible dans $_POST['avatarr'] on test la variable avatarr qui causait probl�me
if(!empty($avatarr) && !preg_match('/avatarup\/avatar\d+\.(jpg)|(jpeg)|(gif)|(png)/i', $avatarr))
	die('Waf !');

$sql2 = 'UPDATE '.$prefixtable.'membres SET sign = "'.add_gpc($_POST['signtxt']).'" , signaff= '.intval($_POST['sign']).' , afflist = '.intval($_POST['ligne']).' , www = "'.add_gpc($_POST['urlwww']).'" , he = "'.intval($he).'" , gmt = "'.add_gpc($_POST['gmt']).'" , localisation = "'.add_gpc($_POST['localisation']).'"';

// Si administrateur, droit de modifier le compte de tous
$sql1 = 'SELECT id FROM '.$prefixtable.'membres WHERE id = "'.intval($_SESSION['idlog']).'" AND `rang` = 2';
$req1 = mysql_query($sql1);
if(mysql_num_rows($req1) == 1)	// (admin seulement)
{
	$rang = 2;
	if(isset($_GET['id'])) 
		$ad = 1;
	$rang2 = $_POST['rang'];
	
	if($_POST['rang'] != $_POST['rangi'])					// SI changement de rang
	{
		if($_POST['rang'] == 3 && $_POST['rangi'] == 0)	 	// si chef de groupe
			$rang2 = 0;
			
		elseif($_POST['rang'] == 0 && $_POST['rangi'] == 3)	// si plus chef de groupe, modifier table groupe
		{
			$sql = 'UPDATE '.$prefixtable.'groupemembre SET stat = 0  WHERE idm = '.intval($_GET['id']).' AND stat = 1';
			$req = mysql_query($sql) or die('Erreur SQL !<br />'.$sql.'<br />'.mysql_error()); 
			$rang2 = 0;
		}
														// si downgrade en membre
		elseif($_POST['rang'] == 0 && $_POST['rangi'] == 2 || $_POST['rang'] == 0 && $_POST['rangi'] == 1)
		{
			$sql = 'SELECT id FROM '.$prefixtable.'groupemembre WHERE idm = "'.intval($_GET['id']).'"  AND stat = 1';
			$req = mysql_query($sql);
			(mysql_num_rows($req) > 0) ? $rang2 = 3 : $rang2 = 0;		// 
		}
	}
	
	if($_POST['valid'] == 1 && $_POST['validi'] == 0)		// validation
	{
		$sql = 'DELETE FROM '.$prefixtable.'membresvalid WHERE pseudo = "'.intval($_GET['id']).'"';
		$req = mysql_query($sql) or die('Erreur SQL !<br />'.$sql.'<br />'.mysql_error()); 
	}
	
	if(!isset($rang)) $rang = 0;
	
	$sql2 .= ', mail = "'.add_gpc($_POST['mail']).'" , rangspec='.intval($_POST['rangspec']).' ,rang = "'.intval($rang2).'" , valid = "'.intval($_POST['valid']).'"';
}

if(!isset($rang)) $rang = 0;

if(!empty($_FILES['avatarup']['tmp_name']))
{
	$tmp_file = $_FILES['avatarup']['tmp_name'];		// l'avatar est momentan�ment copi� dans avatarup avec pr�fixe temp 
	$file_name = $_FILES['avatarup']['name'];
	////////////////////////////////////////////////////////
	// ajout pour la s�curit� faille trouv�e par jojolapatate
	function GetExtensionName($File){			// retourne l'extension du fichier
	  return strtolower(substr($File, strrpos($File, '.') + 1));
	}
	$format_img_accept = array('jpg', 'jpeg', 'gif', 'png');
	
	// on test l'extension, on test le MIME
	if(in_array(GetExtensionName($file_name), $format_img_accept) && preg_match('/image\/(jpeg)|(gif)|(png)/i', $_FILES['avatarup']['type']))
	{
		if($rang == 2 && isset($_GET['id'])) 
			$ida = intval($_GET['id']);
		$type_file = $_FILES['avatarup']['type'];
		$handle = opendir('avatarup/');
		
		if(strstr($type_file, 'jpg') || strstr($type_file, 'jpeg')) $type="jpg"; 
		elseif(strstr($type_file, 'gif')) $type="gif"; 
		elseif(strstr($type_file, 'png')) $type="png"; 
		else $type=""; 

		$nom = 'avatar'.$ida.'.'.$type;
		$size = getimagesize($tmp_file);

		if($_FILES['avatarup']['size'] > $pmax) { $avatar = $avatarr; $p=1; }		// on reprends l'ancien avatar
		elseif( !strstr($type_file, 'jpg') && !strstr($type_file, 'jpeg') && !strstr($type_file, 'gif') && !strstr($type_file, 'png')) 
		{
			$avatar = $avatarr;
			$f=1; 
		}
		else
		{
			$size = getimagesize($tmp_file);
			if($size['0'] > $lmax || $size['1'] > $hmax)
			{ 
				$s=1;
				$avatar = $avatarr; 
			}
			else 
			{ 
				$avatar = 'avatarup/'.$nom; 
				if(strstr($avatarr, 'avatarup/avatar')) { @unlink("$avatarr"); }
				@unlink("$avatarr");
				move_uploaded_file($tmp_file, 'avatarup/'.$nom);
			}
		}
	}
	else
		echo 'nok'.GetExtensionName($file_name);
	@unlink($tmp_file);
}

elseif($avatar != "")			// avatat depuis une url
{
	if(ereg(".jpg$",$avatar) && ereg("^http://",$avatar) || ereg(".jpeg$",$avatar) && ereg("^http://",$avatar) || ereg(".gif$",$avatar) && ereg("^http://",$avatar))
	{
		$size = getimagesize($avatar);
		if($size['0'] < $lmax && $size['1'] < $hmax)
		{
			// && filesize($avatar) < $pmax
			$sqli = intval($_SESSION['idlog']);
			if($rang == 2 && isset($_GET['id'])) $sqli = intval($_GET['id']);
			copy($avatar,'avatarup/avatar'.$sqli.'tmp');
			
			if(filesize('avatarup/avatar'.$sqli.'tmp') > $pmax)
			{
				$p = 1;
				unlink('avatarup/avatar'.$sqli.'tmp');
				$avatar = $avatarr;				
			}
			else
			{
				if(ereg(".jpg$",$avatar) || ereg(".jpeg$",$avatar)) 
				{  
					if(ereg("^avatarup/avatar",$avatarr)) @unlink("$avatarr");
					rename('avatarup/avatar'.$sqli.'tmp','avatarup/avatar'.$sqli.'.jpg'); $avatar = 'avatarup/avatar'.$sqli.'.jpg';
				}
				elseif(ereg(".gif$",$avatar))
				{  
					if(ereg("^avatarup/avatar",$avatarr)) @unlink("$avatarr");
					rename('avatarup/avatar'.$sqli.'tmp','avatarup/avatar'.$sqli.'.gif'); $avatar = 'avatarup/avatar'.$sqli.'.gif';
				}
				else
					$avatar = $avatarr;
			}
		}
		else
		{
			$s = 1;
			$avatar = $avatarr;
		}
	}
	else
		$m = 1; $avatar = $avatarr;
}
else
	$avatar = $avatarr;

if(!isset($s)) $s=0;
if(!isset($f)) $f=0;
if(!isset($p)) $p=0;
if(!isset($m)) $m=0;

if(isset($_POST['delavatar']))
{
	$avatar = "";
	if(strstr($avatarr, 'avatarup/avatar')) 
		@unlink("$avatarr");
}

if(!empty($_POST['mdp']) && !empty($_POST['mdp1']))
{
	if(isset($_GET['id']) && rang == 2) $sqlmp = 'SELECT id FROM '.$prefixtable.'membres WHERE mdp = "'.md5($_POST['mdp1']).'" AND id = "'.intval($_GET['id']).'"';
	else $sqlmp = 'SELECT id FROM '.$prefixtable.'membres WHERE mdp = "'.md5($_POST['mdp']).'" AND id = "'.intval($_SESSION['idlog']).'"';
	if($_POST['mdp1'] == $_POST['mdp2']) { $req1mp = mysql_query($sqlmp); $countmdp = mysql_num_rows($req1mp); }
			
	if(isset($countmdp) && $countmdp == 1) 
	{
		$sql2 .= ' , mdp = "'.md5($_POST['mdp1']).'" ';
		$expire = 365*24*3600;
		if(isset($_COOKIE['idlog']) && !isset($_GET['id'])) setcookie("mdp",md5($_POST['mdp1']),time()+$expire);
	}
	else 
		$countmdp = 0;
}

if($rang == 2 || $autmodpseudo)
{

	$sqlp = 'SELECT id FROM '.$prefixtable.'membres WHERE pseudo = "'.trim(add_gpc($_POST['pseudoren'])).'"';
	$req1p = mysql_query($sqlp);
	$ps = trim($_POST['pseudoren']);
	if(mysql_num_rows($req1p) == 0 && !empty($ps)) $sql2 .= ' , pseudo = "'.add_gpc(trim($_POST['pseudoren'])).'" ';
	
}

if(!isset($countmdp)) 
	$countmdp = 1;
if(isset($ad))  
	$sql2 .= ' , avatar = "'.add_gpc($avatar).'"  WHERE id = "'.intval($_GET['id']).'"'; 
else 
	$sql2 .= ' , avatar = "'.add_gpc($avatar).'"  WHERE id = "'.intval($_SESSION['idlog']).'"';
$req = mysql_query($sql2) or die('Erreur SQL !<br />'.$sql2.'<br />'.mysql_error()); 
$loc = 'index.php?page=profil&save&s='.$s.'&f='.$f.'&p='.$p.'&m='.$m;
if($countmdp == 0) 
	$loc .= '&countmdp';
if(isset($rang) && isset($_GET['id']) && $rang == 2)
		$loc .= '&id='.$_GET['id'];

if($p == 0 && $f == 0 && $m == 0 && $s == 0 && $countmdp == 1) 
	$loc = 'index.php?page=profsave';
	
header('Location: '.$loc);
mysql_close();
?>
