<?php

require('./config.php');
require('./util.php');
require('./io.php');
require('./commands.php');
require('./phpcompat.php');

function SendError( $number, $text )
{
	SendUploadResults( $number, '', '', $text ) ;
}


// Check if this uploader has been enabled.
if ( !$Config['Enabled'] )
	SendUploadResults( '1', '', '', 'Закачка файлов недоступна. Пожалуйста проверьте "filemanager/connectors/php/config.php"' ) ;

$sCommand = 'QuickUpload' ;

// The file type (from the QueryString, by default 'File').
$sType = isset( $_GET['Type'] ) ? $_GET['Type'] : 'File' ;

$sCurrentFolder	= GetCurrentFolder() ;

// Is enabled the upload?
if ( ! IsAllowedCommand( $sCommand ) )
	SendUploadResults( '1', '', '', 'Команда ""' . $sCommand . '"" недоступна' ) ;

// Check if it is an allowed type.
if ( !IsAllowedType( $sType ) )
    SendUploadResults( 1, '', '', 'Неверный тип' ) ;

// Get the CKEditor Callback
$CKEcallback = $_GET['CKEditorFuncNum'];

//pass it on to file upload function
FileUpload( $sType, $sCurrentFolder, $sCommand, $CKEcallback );

?>
