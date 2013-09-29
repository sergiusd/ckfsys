CKfsys
======

Advanced file manager for CKEditor. Can be used with other visual editors.


Configuration CKfsys
====================

Server requirements:
- in the PHP module must be installed php_mbstring (usually at the hosters it's worth);
- for previews - should be allowed to command exec() and stand utility convert (imagemagick), or module for PHP - gd2.

Add the following settings:
```
// Enable deleting files and folders true/false
$Config['Delete'] = true;

// Make thumbnails and allow to resize when downloading
// Utility requires imagemagick or gd2 library
// If they do not, so do not display in the interface unnecessary elements set to false
$Config ['ThumbCreate'] = true;   // When uploading, you can resize
$Config ['ThumbList'] = true;     // Show thumbnails
$Config ['ThumbListSize'] = 100;  // The size of thumbnails, within a square
$Config ['ThumbMaxGenerate'] = 5; // Maximum number of thumbnails generated at a time when all of a sudden they are not
```

And to store preview-version at the end of the configuration file is specified folder, set it:
```
$Config['AllowedExtensions']['ImageThumb']     = $Config['AllowedExtensions']['Image'];
$Config['DeniedExtensions']['ImageThumb']      = $Config['DeniedExtensions']['Image'];
$Config['FileTypesPath']['ImageThumb']         = $Config['UserFilesPath'] . 'imageThumb/';
$Config['FileTypesAbsolutePath']['ImageThumb'] = ($Config['UserFilesAbsolutePath'] == '') ?
                                                 '' : $Config['UserFilesAbsolutePath'].'imageThumb/';
```

What you need to configure in the standard <div>(for those who do not know)</div>
------------------------------------------

Please note that for security purposes definitely need to check the configuration file manager authorizing the user, and only if the user is authorized to give him the opportunity to use the file manager.
To do this, the file CKFSYS_PATH/connectors/php/config.php define a variable:
```
// SECURITY: You must explicitly enable this "connector". (Set it to "true").
// WARNING:  don't just set "$Config['Enabled'] = true ;", you must be sure that only
//           authenticated users can access this file or use some kind of session checking.
$Config['Enabled'] = empty($_SESSION['administrator'])?false:true; // Here enter your check
```

And specify the folder where the downloaded files will be stored:
```
// Path to user files relative to the document root.
$Config['UserFilesPath'] = '/userfiles/'; // Here select your folder, create it with the rights of 0777
```

We connect CKeditor and CKfsys
==============================

The File Manager is configured, now attach it to the editor.
To do this in the file CKEDITOR_PATH/config.js ask the way to the file manager:

```
CKEDITOR.editorConfig = function(config) {

    ...

    // File manager
    // CKFSYS_PATH - the path to the file manager you have, something such as /path/to/ckeditor/filemanager,
    // Specify the path to DOCUMENT_ROOT
    config.filebrowserBrowseUrl = '/CKFSYS_PATH/browser/default/browser.html?Connector=/CKFSYS_PATH/connectors/php/connector.php';
    config.filebrowserImageBrowseUrl = '/CKFSYS_PATH/browser/default/browser.html?type=Image&Connector=/CKFSYS_PATH/connectors/php/connector.php';
};
```
