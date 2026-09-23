<?php
defined('FACETAGWRITE_PATH') or die('Hacking attempt!');

load_language('plugin.lang',FACETAGWRITE_PATH);

// Afficher le tabsheet
facetageditor_admin_tabsheet('help');

// Charger le template
$template->set_filename('face_tag_editor_help', dirname(__FILE__).'/../template/help.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'face_tag_editor_help');
?>
