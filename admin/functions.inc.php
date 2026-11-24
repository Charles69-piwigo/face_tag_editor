<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Crée et affiche le tabsheet (onglets) pour les pages d'administration
 * @param string $selected L'onglet actuellement sélectionné 
 */
function facetageditor_admin_tabsheet($selected = 'help')
{
  include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
  $tabsheet = new tabsheet();
  $tabsheet->set_id('face_tag_editor');
  $tabsheet->add('help', l10n('Aide'), FACETAGWRITE_ADMIN . '&tab=help');
  $tabsheet->add('manage_originals', l10n('Gestion des .original'), FACETAGWRITE_ADMIN . '&tab=manage_originals');
  $tabsheet->add('manage_rights', l10n('Gestion des droits'), FACETAGWRITE_ADMIN . '&tab=manage_rights');
  // 2.5.2 : outil masqué par défaut, affiché avec $conf['face_tag_editor_keywords_tool'] = true (LocalFiles Editor)
  global $conf;
  if (!empty($conf['face_tag_editor_keywords_tool']) && is_webmaster())
  {
    $tabsheet->add('keywords_tool', l10n('kwtool_tab'), FACETAGWRITE_ADMIN . '&tab=keywords_tool');
  }
  
  $tabsheet->select($selected);
  $tabsheet->assign();
}
?>