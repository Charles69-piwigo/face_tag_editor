<?php

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// Installation du plugin avec les valeurs par défaut
function plugin_install()
{
    
}

// Activation du plugin
function plugin_activate()
{

    


}

// Désinstallation du plugin
function plugin_uninstall()
{
  conf_delete_param('face_tag_editor_config');

  // 2.5.2 : état conservé de l'analyse de l'outil « Mots-clés des visages »
  global $conf;
  @unlink(PHPWG_ROOT_PATH . $conf['data_location'] . 'plugins/face_tag_editor_kwscan.json');
}

// Désactivation du plugin
function plugin_deactivate()
{

}

?>
