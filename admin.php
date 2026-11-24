<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

//*error_log('DEBUG: admin.php chargé');

// Charger les fonctions
include_once(FACETAGWRITE_PATH . 'admin/functions.inc.php');  // <-- Changer ici

//*error_log('DEBUG: functions_inc chargé');

// Déterminer l'onglet actif
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : 'help';

//*error_log('DEBUG: tab = ' . $page['tab']);

// Charger le contenu de l'onglet
switch ($page['tab']) {
    case 'help':
        //*error_log('DEBUG: avant include help.php');
        include(FACETAGWRITE_PATH . 'admin/help.php');
        break;
    
    case 'manage_originals':
        //*error_log('DEBUG: avant include manage_originals.php');
        include(FACETAGWRITE_PATH . 'admin/manage_originals.php');
        break;

    case 'manage_rights':
        //*error_log('DEBUG: avant include manage_rights.php');
        include(FACETAGWRITE_PATH . 'admin/manage_rights.php');
        break;

    case 'keywords_tool':
        include(FACETAGWRITE_PATH . 'admin/keywords_tool.php');
        break;



    default:
        include(FACETAGWRITE_PATH . 'admin/help.php');
        break;
}

//*error_log('DEBUG: admin.php terminé');
?>