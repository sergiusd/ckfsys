<?php

function GetFolders( $resourceType, $currentFolder )
{
	// Map the virtual path to the local server path.
	$sServerDir = ServerMapFolder( $resourceType, $currentFolder, 'GetFolders' ) ;

	// Array that will hold the folders names.
	$aFolders	= array() ;

	$oCurrentFolder = opendir( $sServerDir ) ;

	while ( $sFile = readdir( $oCurrentFolder ) )
	{
		if ( $sFile != '.' && $sFile != '..' && is_dir( $sServerDir . $sFile ) )
			$aFolders[] = '<Folder name="' . ConvertToXmlAttribute( $sFile ) . '" />' ;
	}

	closedir( $oCurrentFolder ) ;

	// Open the "Folders" node.
	echo "<Folders>" ;

	natcasesort( $aFolders ) ;
	foreach ( $aFolders as $sFolder )
		echo $sFolder ;

	// Close the "Folders" node.
	echo "</Folders>" ;
}

function GetFoldersAndFiles( $resourceType, $currentFolder )
{
	global $Config;

	// Map the virtual path to the local server path.
	$sServerDir = ServerMapFolder( $resourceType, $currentFolder, 'GetFoldersAndFiles' ) ;
	$sCurrentPath = GetUrlFromPath( $resourceType, $currentFolder, 'GetFoldersAndFiles' );

	// Arrays that will hold the folders and files names.
	$aFolders	= array() ;
	$aFiles		= array() ;

	$oCurrentFolder = opendir( $sServerDir ) ;

	$generatedThumbCount = 0;
	while ( $sFile = readdir( $oCurrentFolder ) )
	{
		if ( $sFile != '.' && $sFile != '..' )
		{
			if ( is_dir( $sServerDir . $sFile ) )
				$aFolders[] = '<Folder name="' . ConvertToXmlAttribute( $sFile ) . '" size="'. filemanager_dirsize($sServerDir.$sFile) .'"/>' ;
			else
			{
				$iFileSize = @filesize( $sServerDir . $sFile ) ;
				if ( !$iFileSize ) {
					$iFileSize = 0 ;
				}
				if ( $iFileSize > 0 )
				{
					$iFileSize = filemanager_size($iFileSize);
				}
				if ($resourceType=='Image' && $Config['ThumbList']) {
					$t = 'X';
					$imageFile = $sServerDir.$sFile;
					$new = filemanager_getthumbname( $currentFolder.$sFile );
					$thumbFile = CombinePaths($_SERVER['DOCUMENT_ROOT'].GetResourceTypePath('ImageThumb', 'GetFoldersAndFiles'), $new);
					if (file_exists($thumbFile)) {
						$t = CombinePaths(GetResourceTypePath('ImageThumb', 'GetFoldersAndFiles'), $new);
					} elseif ($Config['ThumbMaxGenerate'] > $generatedThumbCount && $Config['ThumbList'] && $resourceType=='Image') {
						$sThumbPath = CombinePaths($_SERVER['DOCUMENT_ROOT'].GetResourceTypePath('ImageThumb', 'Upload'), $new);
						if (filemanager_thumb($imageFile, $Config['ThumbListSize'], $Config['ThumbListSize'], $sThumbPath)) {
							$generatedThumbCount++;
							$t = CombinePaths(GetResourceTypePath('ImageThumb', 'GetFoldersAndFiles'), $new);
						}
					}
					list($w, $h) = getimagesize($imageFile);
					$add = 'thumb="' . ConvertToXmlAttribute($t) . '" width="'.$w.'" height="'.$h.'"';
				} else {
					$add = '';
				}

				$aFiles[] = '<File name="' . ConvertToXmlAttribute( $sFile ) . '" size="' . $iFileSize . '" '.$add.'/>' ;
			}
		}
	}

	// Send the folders
	natcasesort( $aFolders ) ;
	echo '<Folders>' ;

	foreach ( $aFolders as $sFolder )
		echo $sFolder ;

	echo '</Folders>' ;

	// Send the files
	natcasesort( $aFiles ) ;
	echo '<Files>' ;

	foreach ( $aFiles as $sFiles )
		echo $sFiles ;

	echo '</Files>' ;
}

function CreateFolder( $resourceType, $currentFolder )
{
	if (!isset($_GET)) {
		global $_GET;
	}
	$sErrorNumber	= '0' ;
	$sErrorMsg		= '' ;

	if ( isset( $_GET['NewFolderName'] ) )
	{
		$sNewFolderName = filemanager_translit($_GET['NewFolderName']) ;
		$sNewFolderName = SanitizeFolderName( $sNewFolderName ) ;

		if ( strpos( $sNewFolderName, '..' ) !== FALSE )
			$sErrorNumber = '102' ;		// Invalid folder name.
		else
		{
			// Map the virtual path to the local server path of the current folder.
			$sServerDir = ServerMapFolder( $resourceType, $currentFolder, 'CreateFolder' ) ;

			if ( is_writable( $sServerDir ) )
			{
				$sServerDir .= $sNewFolderName ;

				$sErrorMsg = CreateServerFolder( $sServerDir ) ;

				switch ( $sErrorMsg )
				{
					case '' :
						$sErrorNumber = '0' ;
						break ;
					case 'Invalid argument' :
					case 'No such file or directory' :
						$sErrorNumber = '102' ;		// Path too long.
						break ;
					default :
						$sErrorNumber = '110' ;
						break ;
				}
			}
			else
				$sErrorNumber = '103' ;
		}
	}
	else
		$sErrorNumber = '102' ;

	// Create the "Error" node.
	echo '<Error number="' . $sErrorNumber . '" originalDescription="' . ConvertToXmlAttribute( $sErrorMsg ) . '" />' ;
}

// Notice the last paramter added to pass the CKEditor callback function
function FileUpload( $resourceType, $currentFolder, $sCommand, $CKEcallback = '' )
{
	if (!isset($_FILES)) {
		global $_FILES;
	}
	$sErrorNumber = '0' ;
	$sFileName = '' ;

	if (( isset( $_FILES['NewFile'] ) && !is_null( $_FILES['NewFile']['tmp_name'] ) ) || (isset( $_FILES['upload'] ) && !is_null( $_FILES['upload']['tmp_name'] ) ))
	{
		global $Config ;

		$oFile = isset($_FILES['NewFile']) ? $_FILES['NewFile'] : $_FILES['upload'];

		// Map the virtual path to the local server path.
		$sServerDir = ServerMapFolder( $resourceType, $currentFolder, $sCommand ) ;

		// Get the uploaded file name.
		$sFileName = filemanager_translit($oFile['name']);
		$sFileName = SanitizeFileName( $sFileName ) ;

		$sOriginalFileName = $sFileName ;

		// Get the extension.
		$sExtension = substr( $sFileName, ( strrpos($sFileName, '.') + 1 ) ) ;
		$sExtension = strtolower( $sExtension ) ;
		if ( isset( $Config['SecureImageUploads'] ) )
		{
			if ( ( $isImageValid = IsImageValid( $oFile['tmp_name'], $sExtension ) ) === false )
			{
				$sErrorNumber = '202' ;
			}
		}

		if ( isset( $Config['HtmlExtensions'] ) )
		{
			if ( !IsHtmlExtension( $sExtension, $Config['HtmlExtensions'] ) &&
				( $detectHtml = DetectHtml( $oFile['tmp_name'] ) ) === true )
			{
				$sErrorNumber = '202' ;
			}
		}

		// Check if it is an allowed extension.
		if ( !$sErrorNumber && IsAllowedExt( $sExtension, $resourceType ) )
		{
			$iCounter = 0 ;

			while ( true )
			{
				$sFilePath = $sServerDir . $sFileName ;

				if ( is_file( $sFilePath ) )
				{
					$iCounter++ ;
					$sFileName = RemoveExtension( $sOriginalFileName ) . '(' . $iCounter . ').' . $sExtension ;
					$sErrorNumber = '201' ;
				}
				else
				{
					move_uploaded_file( $oFile['tmp_name'], $sFilePath ) ;

					if ( is_file( $sFilePath ) )
					{
						if ( isset( $Config['ChmodOnUpload'] ) && !$Config['ChmodOnUpload'] )
						{
							break ;
						}

						$permissions = 0777;

						if ( isset( $Config['ChmodOnUpload'] ) && $Config['ChmodOnUpload'] )
						{
							$permissions = $Config['ChmodOnUpload'] ;
						}

						$oldumask = umask(0) ;
						chmod( $sFilePath, $permissions ) ;
						umask( $oldumask ) ;

						if ($Config['ThumbCreate'] && $_POST['thumb'] && in_array($sExtension, array("gif", "jpg", "jpeg", "png", "wbmp"))) {
							filemanager_thumb($sFilePath, $_POST['thumb_x'], $_POST['thumb_y']);
					}

					if ($Config['ThumbList'] && $resourceType=='Image') {
						$sThumbPath = CombinePaths($_SERVER['DOCUMENT_ROOT'].GetResourceTypePath('ImageThumb', 'Upload'),
							filemanager_getthumbname($currentFolder.$sFileName));
						filemanager_thumb($sFilePath, $Config['ThumbListSize'], $Config['ThumbListSize'], $sThumbPath);
					}

					}

					break ;
				}
			}

			if ( !empty($sFilePath) && file_exists( $sFilePath ) )
			{
				//previous checks failed, try once again
				if ( isset( $isImageValid ) && $isImageValid === -1 && IsImageValid( $sFilePath, $sExtension ) === false )
				{
					@unlink( $sFilePath ) ;
					$sErrorNumber = '202' ;
				}
				else if ( isset( $detectHtml ) && $detectHtml === -1 && DetectHtml( $sFilePath ) === true )
				{
					@unlink( $sFilePath ) ;
					$sErrorNumber = '202' ;
				}
			}
		}
		else
			$sErrorNumber = '202' ;
	}
	else
		$sErrorNumber = '202' ;

	$sFileUrl = CombinePaths( GetResourceTypePath( $resourceType, $sCommand ) , $currentFolder ) ;
	$sFileUrl = CombinePaths( $sFileUrl, $sFileName ) ;

	if($CKEcallback == '')
	{
		SendUploadResults( $sErrorNumber, $sFileUrl, $sFileName ) ;
	}
	else
	{
		//issue the CKEditor Callback
		SendCKEditorResults ($sErrorNumber, $CKEcallback, $sFileUrl, $sFileName);
	}
	exit ;
}

// SergiusD add

function FileDelete($resourceType, $currentFolder, $Command) {
	global $Config;
	if ($resourceType=='Image' && $Config['ThumbList']) {
		@unlink(CombinePaths($_SERVER['DOCUMENT_ROOT'].GetResourceTypePath('ImageThumb', $Command), filemanager_getthumbname($currentFolder.$_GET['DelFile'])));
	}
	$sServerDir = ServerMapFolder( $resourceType, $currentFolder, $Command ) ;
	if (!unlink($sServerDir.$_GET['DelFile']))
		echo '<Error number="1" originalDescription="Ошибка при удалении файла" />' ;
}

function FolderDelete($resourceType, $currentFolder, $Command) {
	global $Config;
	$thumb = 1;//($resourceType=='Image' && $Config['ThumbList']) ? true : false;
	$sServerDir = ServerMapFolder( $resourceType, $currentFolder, $Command ) ;
	if (
		!filemanager_deldir($_SERVER['DOCUMENT_ROOT'].GetResourceTypePath($resourceType, 'Delete'),
			$currentFolder.$_GET['DelFolder'].'/', $thumb)
		|| !rmdir($sServerDir.$_GET['DelFolder'].'/')
	)
		echo '<Error number="1" originalDescription="Ошибка при удалении папки" />' ;
}

function filemanager_dirsize($dir,$size=0) {
	$hdl=opendir($dir);
	while (false !== ($file = readdir($hdl))) {
		if (($file != ".") && ($file != "..")) {
			if (is_dir($dir."/".$file)) {
				return filemanager_dirsize($dir."/".$file,$size);
			} else {
				$size += filesize($dir."/".$file);
			}
		}
	}
	closedir($hdl);
	return filemanager_size($size);
}
function filemanager_size($size) {
	if ($size < 1024)
		return $size.' Б';
	elseif ($size < 1048576)
		return ceil($size/1024)." КБ";
	else
		return round($size/1048576)." МБ";
}

function filemanager_translit($input_string) {
	//$input_string = urldecode($input_string);
	$trans = array();
	$ch1 = "/\r\n-абвгдеёзийклмнопрстуфхцыэАБВГДЕЁЗИЙКЛМНОПРСТУФХЦЫЭABCDEFGHIJKLMNOPQRSTUVWXYZ";
  $ch2 = "    abvgdeeziyklmnoprstufhcyeabvgdeeziyklmnoprstufhcyeabcdefghijklmnopqrstuvwxyz";
  for($i=0; $i<mb_strlen($ch1); $i++)
		$trans[mb_substr($ch1, $i, 1)] = mb_substr($ch2, $i, 1);
	$trans["Ж"] = "zh";  $trans["ж"] = "zh";
	$trans["Ч"] = "ch";  $trans["ч"] = "ch";
	$trans["Ш"] = "sh";  $trans["ш"] = "sh";
	$trans["Щ"] = "sch"; $trans["щ"] = "sch";
	$trans["Ъ"] = "";    $trans["ъ"] = "";
	$trans["Ь"] = "";    $trans["ь"] = "";
	$trans["Ю"] = "yu";  $trans["ю"] = "yu";
	$trans["Я"] = "ya";  $trans["я"] = "ya";
	$trans["\\\\"] = " ";
	$trans["[^\. a-z0-9]"] = " "; // контрольная проверка
	$trans["^[ ]+|[ ]+$"] = ""; // убираю пробелы вначале и конце
	$trans["[ ]+"] = "_"; // пробелы на подчеркивание
	foreach($trans as $from=>$to)
		$input_string = mb_ereg_replace(str_replace("\\", "\\", $from), $to, $input_string);
	return $input_string;
}

function filemanager_deldir($root, $del, $thumb=0) {
	$cont = glob(CombinePaths($root, $del)."*");
	$rootLen = strlen($root);
	$ok = 1;
	foreach ($cont as $val) {
		if (is_dir($val)) {
			$ok *= filemanager_deldir($root, substr($val, $rootLen-1)."/", $thumb);
			$ok *= rmdir($val)?1:0;
		} else {
			$ok *= unlink($val)?1:0;
			if ($thumb) {
				$rootThumb = $_SERVER['DOCUMENT_ROOT'].GetResourceTypePath('ImageThumb', 'Delete');
				@unlink($rootThumb.filemanager_getthumbname(substr($val, $rootLen-1)));
			}
		}
	}
	return $ok;
}

function filemanager_thumb($IMAGE_SOURCE, $THUMB_X, $THUMB_Y, $IMAGE_OUT='') {
	list($width, $height, $type, $attr) = getimagesize($IMAGE_SOURCE);
	if ($THUMB_Y < 0 || $THUMB_X < 0) {
		$THUMB_CUT = 1;
		$THUMB_Y = (int)abs($THUMB_Y);
		$THUMB_X = (int)abs($THUMB_X);
	} else {
		$THUMB_CUT = 0;
		$THUMB_Y = (int)$THUMB_Y;
		$THUMB_X = (int)$THUMB_X;
		$SRC_W = $width;
		$SRC_H = $height;
		$SRC_L = 0;
		$SRC_T = 0;
	}
	if ($THUMB_Y == 0 && $THUMB_X == 0) {
		$THUMB_Y = $height;
		$THUMB_X = $width;
	} elseif ($THUMB_Y == 0) {
		$THUMB_Y = (int)($height * ($THUMB_X / $width));
	} elseif ($THUMB_X == 0) {
		$THUMB_X = (int)($width * ($THUMB_Y / $height));
	} elseif ($THUMB_CUT) {
		// Если заданы оба и вырезать, то вырезаю из изображения прямоугольник
		$zoom_x = $width/$THUMB_X;
		$zoom_y = $height/$THUMB_Y;
		if ($zoom_x <= $zoom_y) {
			$SRC_W = $width;
			$SRC_H = (int)($THUMB_Y * $zoom_x);
			$SRC_L = 0;
			$SRC_T = ($height - $SRC_H) / 2;
		} elseif ($zoom_x > $zoom_y) {
			$SRC_W = (int)($THUMB_X * $zoom_y);
			$SRC_H = $height;
			$SRC_L = ($width - $SRC_W) / 2;
			$SRC_T = 0;
		}
	} else {
		// Если заданы оба, то вписываю в прямоугольник
		$zoom_x = $width/$THUMB_X;
		$zoom_y = $height/$THUMB_Y;
		if ($zoom_x >= $zoom_y) {
			$THUMB_Y = (int)($height * ($THUMB_X / $width));
		} elseif ($zoom_x < $zoom_y) {
			$THUMB_X = (int)($width * ($THUMB_Y / $height));
		}
	}

	// Если картинка меньше по обоим измерениям, то ничего не делаю
	if ($THUMB_X > $width && $THUMB_Y > $height) {
		if ($IMAGE_OUT!=$IMAGE_SOURCE)
			copy($IMAGE_SOURCE, $IMAGE_OUT);
		return true;
	}

	$IMAGE_OUT = $IMAGE_OUT?$IMAGE_OUT:$IMAGE_SOURCE;

	if (filemanager_imagemagick_check()) {
		$filter = (($SRC_L || $SRC_T)?'-crop '.$SRC_W.'x'.$SRC_H.'+'.$SRC_L.'+'.$SRC_T.'! ':'')."-resize ".$THUMB_X."x".$THUMB_Y;
		return exec('convert "'.$IMAGE_SOURCE.'" '.$filter.' "'.$IMAGE_OUT.'"')?false:true;
	} elseif (filemanager_gd2_check()) {
		$img_type = array(
	    1 => array("r"=>"gif", "w"=>"png", "vr"=>"GIF Read Support", "vw"=>"PNG Support"),
	    2 => array("r"=>"jpeg", "w"=>"jpeg", "vr"=>"JPG Support", "vw"=>"JPG Support"),
	    3 => array("r"=>"png", "w"=>"png", "vr"=>"PNG Support", "vw"=>"PNG Support"),
	    15 => array("r"=>"wbmp", "w"=>"wbmp", "vr"=>"WBMP Support", "vw"=>"WBMP Support")
	  );
		if (@PHP_VERSION_ID>=50300) {
			$img_type[2]=array("r"=>"jpeg", "w"=>"jpeg", "vr"=>"JPEG Support", "vw"=>"JPEG Support");
		}
	  if (!$cmd = $img_type[$type]) {
	  	echo "Thumb - Неизвестный формат - $type ($IMAGE_SOURCE)<br>";
	  	return false;
	 	}
	  $gd = gd_info();
	  if (!$gd[$cmd['vr']] || !$gd[$cmd['vw']]) {
	  	echo "Thumb - Формат не поддерживается PHP - ".$cmd['vr']." или ".$gd[$cmd['vw']]."<br>";
	  	return false;
	  }
	  eval('$SRC_IMAGE = ImageCreateFrom'.$cmd['r'].'($IMAGE_SOURCE);');
	  $DEST_IMAGE = ImageCreateTrueColor ($THUMB_X, $THUMB_Y);
	  $res = imagecopyresampled($DEST_IMAGE, $SRC_IMAGE, 0, 0, $SRC_L, $SRC_T, $THUMB_X, $THUMB_Y, $SRC_W, $SRC_H);
	  if ($res)
	  	eval('$res = Image'.$cmd['w'].'($DEST_IMAGE, $IMAGE_OUT);');
	  @imagedestroy($SRC_IMAGE);
	  @imagedestroy($DEST_IMAGE);
	  return $res;
	} else {
		return false;
	}
}
function filemanager_getthumbname($path) {
	if (substr($path,0,1)=='/') $path = substr($path,1);
	return str_replace('/', '_-_', $path);
}
function filemanager_imagemagick_check() {
	return strpos(`convert -version`, 'ImageMagick') !== false ? true : false;
}
function filemanager_gd2_check() {
	return function_exists('gd_info')?true:false;
}
function filemanager_debug($str) {
	$f = fopen($_SERVER['DOCUMENT_ROOT'].'/debug.txt', 'a');
	fwrite($f, $str."\n");
	fclose($f);
}
?>