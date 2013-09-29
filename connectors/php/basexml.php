<?php

function SetXmlHeaders()
{
	ob_end_clean() ;

	// Prevent the browser from caching the result.
	// Date in the past
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT') ;
	// always modified
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT') ;
	// HTTP/1.1
	header('Cache-Control: no-store, no-cache, must-revalidate') ;
	header('Cache-Control: post-check=0, pre-check=0', false) ;
	// HTTP/1.0
	header('Pragma: no-cache') ;

	// Set the response format.
	header( 'Content-Type: text/xml; charset=utf-8' ) ;
}

function CreateXmlHeader( $command, $resourceType, $currentFolder )
{
	SetXmlHeaders() ;

	// Create the XML document header.
	echo '<?xml version="1.0" encoding="utf-8" ?>' ;

	// Create the main "Connector" node.
	echo '<Connector command="' . $command . '" resourceType="' . $resourceType . '">' ;

	// Add the current folder node.
	echo '<CurrentFolder path="' . ConvertToXmlAttribute( $currentFolder ) . '" url="' . ConvertToXmlAttribute( GetUrlFromPath( $resourceType, $currentFolder, $command ) ) . '" />' ;

	$GLOBALS['HeaderSent'] = true ;
}

function CreateXmlFooter()
{
	echo '</Connector>' ;
}

function SendError( $number, $text )
{
	if ( $_GET['Command'] == 'FileUpload' )
		SendUploadResults( $number, "", "", $text ) ;

	if ( isset( $GLOBALS['HeaderSent'] ) && $GLOBALS['HeaderSent'] )
	{
		SendErrorNode( $number, $text ) ;
		CreateXmlFooter() ;
	}
	else
	{
		SetXmlHeaders() ;

		// Create the XML document header
		echo '<?xml version="1.0" encoding="utf-8" ?>' ;

		echo '<Connector>' ;

		SendErrorNode( $number, $text ) ;

		echo '</Connector>' ;
	}
	exit ;
}

function SendErrorNode(  $number, $text )
{
	if ($text)
		echo '<Error number="' . $number . '" text="' . htmlspecialchars( $text ) . '" />' ;
	else
		echo '<Error number="' . $number . '" />' ;
}
?>
