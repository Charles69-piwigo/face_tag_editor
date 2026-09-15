<?php
/*
Plugin Name: face_tag_editor
Version: auto
Description: Créer et enregistrer les tags de visages dans les métadonnées XMP et description 
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1053
Author: Charles69
Has Settings: webmaster
*/

//============= VERSIONS ============================================
/*

version 2.4 - 15/09/2026
    corrigé : les clés de traduction des messages de confirmation/erreur de restauration
    .original contenaient un \n littéral (non interprété par PHP en guillemets simples), ce
    qui empêchait toute traduction (renommées en clés courtes stables) — issue #2/PR #1 (Gvf63)
    corrigé : conflit avec geo_tag_editor quand les deux plugins chargent Trumbowyg sur la même
    page (le second chargement réinitialisait jQuery.trumbowyg et effaçait les traductions déjà
    chargées par le premier) ; ajout d'une garde de chargement unique de la librairie, symétrique
    dans les deux plugins
    ajouté : support italien (plugin.lang.php + langue Trumbowyg it.min.js, dans les deux plugins)
    corrigé : le texte du bouton "Enregistrement..." pendant la sauvegarde n'était jamais traduit
    supprimé : webservice facetagwrite.clearLog (reste de mise au point, fonction jamais implémentée,
    provoquait une erreur fatale si appelé) ; le système de log de debug reste activable manuellement
    dans le code (voir commentaire en tête de fichier)
    supprimé : 3 fichiers XML orphelins dans template/ (face_mpreg_template.xml, face_mwgrs_template.xml,
    xmp_template.xml), reliquats de l'ancienne approche "template" d'écriture XMP abandonnée en v2.3,
    non référencés par aucun code
    corrigé : clé de traduction du bouton "Enregistrer" de la modale incohérente entre main.inc.php
    (sans espace finale) et draw_faces.js/fichiers de langue (avec espace finale) -> le bouton
    s'affichait toujours en français quelle que soit la langue de l'utilisateur

version 2.3 - 18/08/2026
    version auto pour PEM piwigo & github
    ajouté .gitignore
    corrigé : la désignation saisie n'était jamais enregistrée en base (uniquement écrite dans l'IPTC du fichier)
    ajouté un éditeur wysiwyg (Trumbowyg) pour la désignation, identique à geo_tag_editor
    déplacé le champ désignation dans la colonne de droite de la modale
    les tags des visages créés/renommés sont automatiquement enregistrés dans le registre
    facetag_person_tags (table créée si nécessaire), indépendamment de l'état d'activation de
    face_tag, pour que le masquage du nuage de tags reste correct même après une désactivation
    temporaire de face_tag (la politique d'affichage/l'option restent entièrement dans face_tag)
    corrigé : l'écriture XMP (template) écrasait silencieusement toutes les métadonnées non liées
    aux visages (historique d'édition, Extended XMP...) à chaque sauvegarde ; réécrit en fusion
    DOM chirurgicale qui ne touche qu'aux 6 champs visages/tags et préserve le reste du document

version 2.2 - 23/06/2026
    conformation au standard get_original_url

version 2.1a - 24/01/2026 (non diffusé)
    traduction : méthode lazy loading
    langue uk prioritaire

version 2.1  22/12/2025
    ajouté : gestion des .original
    ajouté : gestion des droits des users
    corrigé décalage texte sur jpg exporté
    corrigé changement de langue aléatoire

version 2.0 - 18/12/2025
    PHP IMagick ou External ImageMagick requis - fonctionnement sans exiftool 
    ajouté création image avec visages tagués
    corrigé orientation 270CW
    commentaire sur consol.log et error_log
    commentaire sur les alertes débug
    méthode traduction modifée 

version 1.9D
    ajout download photo taguée

version 1.9C   16/12/2025
    suite à régression de fonctionnalités avec External Imagick seul
    fonctionnement sans exiftool 
    corrigé orientation 270CW


version 1.9 - 10/12/2025 diffusée
    changement de méthode pour la lecture des visages en php  pur
    traduction en anglais de l'interface

version 1.8 - 08/12/2025
    ajouté description ( commment )
    modifié Plugin URI

version 1.7A - 06/12/2025 
    base PHP Imagick , External Imagick, Exiftool
    corrigé problème avec item Photoshop 
    à voir 270CW ?

version 1.6 - 04/12/2025 diffusée
    à voir : 270 CW
    External Imagick & PHP IMagick
    corrigé : saisie nom annule
    corrigé : restauration fichier
    ajouté  : modification nom  
    bug enregistrement original

version 1.5 - 01/12/2025 diffusée
    contourné le problème allow_url_fopen
    bug sur restaurer l'original <- 1.4C (pas 1.4A)

version 1.4C - 30/11/2025
    pb valider qui ne fonctionne pas -> contournement
    régénération des miniatures = non nécessaire & ne fonctionne pas
    la régénération des miniatures est automatique par piwigo

version 1.4A - 29/11/2025 diffusée
    corrigé rotation 8
    corrigé caractères accentué dans les noms
    corrigé XMP nécessaires à xnview
    nouvelle façon d'écrire les XMP

version 1.3A - 27/11/2025
    ajouté logs

version 1.3 - 27/11/2025
    corrigé chemin codé en dur !!

version 1.2C - 26/11/2025
    aspect visuel css svg
    ajouté logs

version 1.2B - 26/11/2025
    ajouté logs

version 1.2A 25/11/2025
    suppression de .jpg.original après une restauration
    corrigé rotation type 6 = 90 CW
    bouton taguer noir & blanc

version 1.2 - 24/11/2025 
    changement de nom du plugin face_tag_editor
    corrigé suppression tous les visages
    corrigé photo sans XMP
    message en clair quand les fichiers sont verrouillés
    décommenter @unlink effact fichiers temporaires

version 1.1 - 23/11/2025
    ok avec les liens symboliques et upload
    ajout d'une fonction de restauration du fichier original
    ajout de conditions sur les utilisateurs autorisés

version 1.0A - 23/11/2025
    sur les fichiers jpg qui se trouvent dans ./galleries
    prise en compte des tags visage existants
    deplacement modification des cadres
    ajout suppression de visage

*/
//====================================================================

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'face_tag_editor')
{
  add_event_handler('init', 'face_tag_editor_error');
  function face_tag_editor_error()
  {
    global $page;
    $page['errors'][] = 'Désactiver le plugin et renommer le répertoire  "face_tag_editor"';
  }
  return;
}

// changt de nom du plugin de face_tag_write à face_tag_editor , mais les noms des variables sont restées tag_face_write

// Plugin constants
define('FACETAGWRITE_ID', basename(dirname(__FILE__)));
define('FACETAGWRITE_PATH', PHPWG_PLUGINS_PATH . FACETAGWRITE_ID . '/');
define('FACETAGWRITE_ADMIN', get_root_url() . 'admin.php?page=plugin-' . FACETAGWRITE_ID); // admin.php?page=plugin-face_tag_editor

// Logs -------------------------------------- à activer pour débugage -  V2.0
//error_reporting(E_ALL);
//ini_set('display_errors', 0);
//ini_set('log_errors', 1);
//ini_set('error_log', './plugins/face_tag_editor/face_tag_editor_debug.log');



// Utilisation de la méthode face_tag V2
require_once(FACETAGWRITE_PATH . 'lib/editor_xmp_ex.php');


  // Charger les classes
require_once(FACETAGWRITE_PATH . 'lib/imagick_wrapper.php');
require_once(FACETAGWRITE_PATH . 'lib/metadata_writer.php');
require_once(FACETAGWRITE_PATH . 'lib/metadata_merger.php');
require_once(FACETAGWRITE_PATH . 'lib/file_resolver.php');
require_once(FACETAGWRITE_PATH . 'lib/restore_original.php');
require_once(FACETAGWRITE_PATH . 'lib/rights_manager.php');
require_once(FACETAGWRITE_PATH . 'img/icon_svg.php'); // image du bouton taguer


//===================== CHARGEMENT DES LANGUES , UK PAR DEFAUT ==================
// Charger d'abord l'anglais comme base
load_language('plugin.lang', FACETAGWRITE_PATH, array('language' => 'en_UK', 'no_fallback' => true));
// Puis charger la langue de l'utilisateur (qui écrasera l'anglais si c'est du français)
load_language('plugin.lang', FACETAGWRITE_PATH);
//=================================================================================


// ==================== CHARGER JQUERY ====================
add_event_handler('loc_begin_page_header', 'face_tag_write_load_jquery');
function face_tag_write_load_jquery()
{
  global $template;
  
  $template->append('head_elements', '
  <script type="text/javascript">
    if (typeof jQuery === "undefined") {
      document.write(\'<script type="text/javascript" src="' . get_root_url() . 'themes/default/js/jquery.min.js"><\/script>\');
    }
  </script>
  ');
}

// ==================== CHARGER LE CSS DU BOUTON ====================
add_event_handler('loc_begin_page_header', 'face_tag_write_load_css');
function face_tag_write_load_css()
{
  global $template;
  
  $template->append('head_elements', '
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/face_tag_button.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/face_tag_modal.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/vendor/trumbowyg/trumbowyg.min.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/vendor/trumbowyg/trumbowyg.colors.min.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/vendor/fonts/roboto.css">
  <link rel="stylesheet" href="' . FACETAGWRITE_PATH . 'css/vendor/fonts/raleway.css">
  ');
}

// ==================== CHARGER SCRIPT ====================
add_event_handler('loc_end_page_tail', 'face_tag_write_load_scripts');
function face_tag_write_load_scripts()
{
  global $template, $page, $user;
  
  if (!isset($page['image_id'])) {
    return;
  }
  
  // Récupérer la configuration
  $config_string = conf_get_param('face_tag_editor_config', false);
  $config = $config_string ? unserialize($config_string) : array();
  
  // Définir les valeurs par défaut
  $save_original = isset($config['save_original']) ? $config['save_original'] : true;
  
  // Choisir la langue Trumbowyg (fr/de/ru/it disponibles localement, en par défaut sinon)
  $piwigo_lang = isset($user['language']) ? $user['language'] : 'en_UK';
  $trumbowyg_lang = 'en';
  $trumbowyg_lang_script = '';
  if (strpos($piwigo_lang, 'fr') === 0) {
    $trumbowyg_lang = 'fr';
  } elseif (strpos($piwigo_lang, 'de') === 0) {
    $trumbowyg_lang = 'de';
  } elseif (strpos($piwigo_lang, 'ru') === 0) {
    $trumbowyg_lang = 'ru';
  } elseif (strpos($piwigo_lang, 'it') === 0) {
    $trumbowyg_lang = 'it';
  }
  if ($trumbowyg_lang !== 'en') {
    $trumbowyg_lang_script = '<script src="' . FACETAGWRITE_PATH . 'js/vendor/trumbowyg/langs/' . $trumbowyg_lang . '.min.js"></script>';
  }
  
  // Éviter le double chargement de la librairie Trumbowyg quand geo_tag_editor est aussi actif :
  // le script core fait une affectation directe (jQuery.trumbowyg = {...}) et non un $.extend,
  // donc une seconde exécution efface les traductions déjà chargées par l'autre plugin (issue #2).
  $trumbowyg_shared_scripts = '';
  if (!defined('TRUMBOWYG_CORE_LOADED')) {
    define('TRUMBOWYG_CORE_LOADED', true);
    $trumbowyg_shared_scripts = '
  <script src="' . FACETAGWRITE_PATH . 'js/vendor/trumbowyg/trumbowyg.min.js"></script>
  <script src="' . FACETAGWRITE_PATH . 'js/vendor/trumbowyg/plugins/trumbowyg.fontsize.min.js"></script>
  <script src="' . FACETAGWRITE_PATH . 'js/vendor/trumbowyg/plugins/trumbowyg.fontfamily.min.js"></script>
  <script src="' . FACETAGWRITE_PATH . 'js/vendor/trumbowyg/plugins/trumbowyg.colors.min.js"></script>';
  }
  
  $template->append('footer_elements', '
  <script>
  // Configuration globale pour face_tag_editor
  window.faceTagConfig = {
      saveOriginal: ' . ($save_original ? 'true' : 'false') . '
  };
  window.FaceTagTrumbowygSvgPath = "' . FACETAGWRITE_PATH . 'css/vendor/trumbowyg/icons.svg";
  window.FaceTagTrumbowygLang = "' . $trumbowyg_lang . '";
  </script>
  ' . $trumbowyg_shared_scripts . '
  ' . $trumbowyg_lang_script . '
  <script src="' . FACETAGWRITE_PATH . 'template/draw_faces.js"></script>
  ');
}

// ==================== AJOUTER LE BOUTON ====================
add_event_handler('loc_end_picture', 'face_tag_write_add_button');

// ==================== VÉRIFICATION DES DROITS D'ACCÈS ====================  V2.1
function face_tag_write_check_access()
{
  global $user, $page;
  
  // Webmaster : accès total
  if (is_webmaster())
  {
    return true;
  }
  
  // Administrateur : accès total
  if (is_admin())
  {
    return true;
  }
  
  // Vérifier si l'utilisateur appartient au groupe 'FaceTag'
  if (!empty($user['id']))
  {
    // Charger les fonctions de gestion des droits
    include_once(FACETAGWRITE_PATH . 'lib/rights_manager.php');
    
    if (!user_in_facetag_group($user['id'])) {
      return false;
    }
    
    // Si on est sur une page photo, vérifier les droits spécifiques
    if (isset($page['image_id'])) {
      return can_user_tag_image($user['id'], $page['image_id']);
    }
    
    // Sinon (page générale), autoriser
    return true;
  }
  
  // Accès refusé
  return false;
}

//---------------------------------------------------------------------------
function face_tag_write_add_button()
{
  global $template, $picture, $user;
  
  // Vérifier les droits d'accès
  if (!face_tag_write_check_access())
  {
    return;
  }
 
  $query = '
  SELECT path, file, comment
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($picture['current']['id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) return;
  
  $original_path = $row['path'];
  $image_url = embellish_url(get_root_url() . $original_path);

  // Un attribut class="..." dans le HTML indique une page élaborée dépendant de CSS externe
  // (ex. collée depuis un logiciel tiers) : on passe alors la description en lecture seule
  // pour ne pas risquer de la dégrader avec l'éditeur (même logique que geo_tag_editor).
  $description_is_readonly = (bool) preg_match('/class\s*=\s*["\']/i', $row['comment'] ?? '');

  // Compatibilité pdp : laisser le plugin de protection réécrire l'URL si actif
  // (redirige vers serve_original.php?id=X qui vérifie les droits avant de servir)
  include_once(PHPWG_ROOT_PATH . 'include/derivative.inc.php');
  $src_image = new SrcImage(array(
    'id'   => $picture['current']['id'],
    'path' => $row['path'],
    'file' => $row['file'],
  ));
  $image_url = trigger_change('get_original_url', $image_url, $src_image);

  $save_url = get_root_url() . 'ws.php?format=json&method=facetagwrite.saveXMP';
  
  // Vérifier si le fichier de backup .original existe
  $real_local_path = face_tag_write_resolve_path($row['path']);
  $has_original = file_exists($real_local_path . '.original') ? 'true' : 'false';

  // Textes traduits
  $tag_text = l10n('Taguer');
  $tag_title = l10n('Taguer les visages');

  // VERSION 1 : Thème par défaut - SANS styles inline
  $button_html = '
    <a href="#" 
       id="facetag-open-editor"
       data-image-id="' . $picture['current']['id'] . '"
       data-image-src="' . $image_url . '"
       data-save-url="' . $save_url . '"
       data-has-original="' . $has_original . '"
       data-description="' . htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8') . '"
       data-description-is-readonly="' . ($description_is_readonly ? 'true' : 'false') . '"
       class="pwg-state-default pwg-button" 
       title="' . $tag_title . '" 
       rel="nofollow">
      <span class="pwg-icon">' . FACETAGWRITE_ICON . '</span>
      <span class="pwg-button-text">' . $tag_text . '</span>
    </a>';

  // VERSION 2 : Thèmes Bootstrap - SANS styles inline
  if ($user['theme'] == 'bootstrapdefault' || $user['theme'] == 'bootstrap_darkroom') {
    $button_html = '
      <a href="#" 
         id="facetag-open-editor"
         data-image-id="' . $picture['current']['id'] . '"
         data-image-src="' . $image_url . '"
         data-save-url="' . $save_url . '"
         data-has-original="' . $has_original . '"
         data-description="' . htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8') . '"
       data-description-is-readonly="' . ($description_is_readonly ? 'true' : 'false') . '"
         class="btn btn-primary" 
         title="' . $tag_title . '" 
         rel="nofollow">
        ' . FACETAGWRITE_ICON . ' ' . $tag_text . '
      </a>';
  }
  
  $template->concat('PLUGIN_PICTURE_ACTIONS', $button_html);
}
// ==================== WEB SERVICE ====================
add_event_handler('ws_add_methods', 'face_tag_write_ws_methods');

function face_tag_write_ws_methods($arr)
{
  $service = &$arr[0];

  //----------------------------
  $service->addMethod(
  'facetagwrite.getFaces',
  'facetagwrite_ws_get_faces',
  array(
    'image_id' => array('default' => null),
  ),
  'Récupère les faces déjà parsées d\'une image',
  null,
  array('admin_status' => ACCESS_GUEST)
);

  
  $service->addMethod(
    'facetagwrite.getXMP',
    'face_tag_write_get_xmp',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
    ),
    'Récupère les données XMP des visages (face_tag_write)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );
  
  $service->addMethod(
    'facetagwrite.saveXMP',
    'face_tag_write_save_xmp',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
      'faces' => array('default' => null, 'type' => WS_TYPE_NOTNULL),
    ),
    'Sauvegarde les données XMP des visages (face_tag_write)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );

  $service->addMethod(
    'facetagwrite.restoreOriginal',
    'face_tag_write_restore_original',
    array(
      'image_id' => array('default' => null, 'type' => WS_TYPE_INT),
    ),
    'Restaure le fichier .original (supprime tous les tags)',
    null,
    array('admin_status' => ACCESS_ADMINISTRATOR)
  );
}

// ==================== WEB SERVICE POUR LIRE LES XMP ====================
function face_tag_write_get_xmp($params, &$service)
{
  
  header('Content-Type: application/json; charset=UTF-8');
  
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');

  //*error_log('**** DEBUT LOG ****');
  
  //*error_log('1 (333) Image ID -> ' . $params['image_id']);
  
  if (empty($params['image_id']))
  {
    //*error_log('ERROR (337) : Missing image_id');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  $query = '
  SELECT path
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);

  //error_log('STEP1: Query executed');
  
  if (!$row)
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  
  $real_local_path = face_tag_write_resolve_path($row['path']);

  if ($real_local_path === false) {
    error_log('❌ Impossible de résoudre le chemin du fichier');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }

  if (!file_exists($real_local_path)) {
    error_log('❌ Le fichier n\'existe pas: ' . $real_local_path);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }

  //*error_log('=== GET XMP ===');
  //*error_log('2 (378) Fichier local -> ' . $real_local_path);

  // Créer un fichier temporaire
  $temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
  if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
    if (!is_dir($temp_dir)) {
      error_log("Créer un repertoire tmp, ./_data/tmp avec des droits en écriture");
    }
  } else {
    //*error_log("3 - le rep ./_data/tmp existe");
  }


  $temp_file = $temp_dir . '/facetag_write_' . uniqid() . '.jpg';

  // Lire le fichier local directement (pas de allow_url_fopen nécessaire)
  $image_content = @file_get_contents($real_local_path);
  if ($image_content === false) {
    //*error_log('ERROR: file_get_contents du fichier local FAILED');
    $last_error = error_get_last();
    if ($last_error) {
      //*error_log('ERROR: PHP error = ' . $last_error['message']);
    }
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot read image file');
  }

  //*error_log('STEP2B: file_get_contents SUCCESS, size = ' . strlen($image_content) . ' bytes');

  @file_put_contents($temp_file, $image_content);
  //error_log('STEP3: Temp file size = ' . filesize($temp_file) . ' octets');
  //*error_log('Temp file size = ' . filesize($temp_file) . ' octets');

  // Try to extract XMP using wrapper (with fallback to external ImageMagick)
  if (function_exists('facetag_extract_xmp')) {
    //error_log('STEP4: Using facetag_extract_xmp');
    $xmp_data = facetag_extract_xmp($temp_file);
  } else {
    //*error_log('STEP4: Using face_tag_write_extract_xmp');
    $xmp_data = face_tag_write_extract_xmp($temp_file);
  }

  // Check if extraction had an error
  if (isset($xmp_data['error'])) {
    //*error_log('ERROR: XMP extraction failed - ' . $xmp_data['error']);
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return array(
      'stat' => 'ok',
      'result' => array(
        'xmp' => array('_raw_xmp' => '', 'error' => $xmp_data['error']),
        'orientation' => 1
      )
    );
  }
  
  // Récupérer l'orientation EXIF
  $orientation = 1;
  if (function_exists('exif_read_data')) {
    //error_log('STEP5: Reading EXIF');
    $exif = @exif_read_data($temp_file);
    if ($exif && isset($exif['Orientation'])) {
      $orientation = $exif['Orientation'];
      //error_log('STEP5: Orientation = ' . $orientation); 
    }else{
      //error_log('STEP5: No orientation found, using 1'); 
    }
  }else{
    //*error_log('ERROR: exif_read_data not available'); 
  }
  
  // Nettoyer
  @unlink($temp_file);
  
  error_reporting($old_error_reporting);
  ini_set('display_errors', $old_display_errors);
  
  //*error_log('SUCCESS: Returning data');

  // === AJOUT : Parser les faces côté serveur ===
  require_once(FACETAGWRITE_PATH . 'lib/metadata_reader.php');
  $reader = new FaceTagMetadataReader();
  $metadata = $reader->readAll($real_local_path);
  
  $faces = isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array();
  
  //*error_log('Faces parsées côté serveur: ' . count($faces));
  
  // Ajouter les faces au XMP
  if (!isset($xmp_data['faces'])) {
    $xmp_data['faces'] = $faces;
  }

  return array(
    'stat' => 'ok',
    'result' => array(
      'xmp' => $xmp_data,
      'image_id' => $params['image_id'],
      'orientation' => $orientation
    )
  );
}

// ==================== FONCTION D'EXTRACTION XMP ====================
function face_tag_write_extract_xmp($image_path)
{
  try
  {
    // Use wrapper for fallback support
    $imagick = ImagickWrapper::load($image_path);

    if ($imagick->hasError())
    {
      return array('error' => $imagick->getError());
    }

    $properties = $imagick->getImageProperties();

    $xmp_data = array();

    foreach ($properties as $key => $value)
    {
      if (strpos($key, 'xmp:') === 0 ||
          strpos($key, 'dc:') === 0 ||
          strpos($key, 'Iptc4xmpCore:') === 0 ||
          strpos($key, 'mwg-rs:') === 0 ||
          strpos($key, 'MP:') === 0)
      {
        $xmp_data[$key] = $value;
      }
    }

    $xmp_profile = $imagick->getImageProfile('xmp');
    if ($xmp_profile)
    {
      $xmp_data['_raw_xmp'] = $xmp_profile;
    }

    $imagick->clear();
    $imagick->destroy();

    return $xmp_data;
  }
  catch (Exception $e)
  {
    return array('error' => $e->getMessage());
  }
}

// ==================== WEB SERVICE POUR SAUVEGARDER ====================
function face_tag_write_save_xmp($params, &$service)
{
  
  header('Content-Type: application/json; charset=UTF-8');
  
  $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
  $old_display_errors = ini_get('display_errors');
  ini_set('display_errors', '0');
  
  //*error_log('=== SAVE XMP REQUEST ===');

// Nettoyer les anciens fichiers temporaires (>1h)
$temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
if (is_dir($temp_dir)) {
  $files = glob($temp_dir . '/facetag_*');
  $now = time();
  foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 3600) {
      @unlink($file);
    }
  }
}

  
  if (empty($params['image_id']))
  {
    error_log('Missing image_id');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  if (empty($params['faces']))
  {
    error_log('ERREUR Missing faces data');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing faces data');
  }
  
 // Récupérer la description - forcer null si vide    ---------------------------------------------------------        V1.9A

$description = isset($params['description']) ? stripslashes(trim($params['description'])) : null;
if ($description === '') {
  $description = null;
  //*error_log('Description vide - sera supprimée');
} else if ($description !== null) {
  //*error_log('Description reçue: ' . strlen($description) . ' caractères');
} else {
  //*error_log('Description non fournie (null)');
}

// La description n'est envoyée par le JS que si le champ était éditable (pas en lecture seule).
// Si elle n'a pas été envoyée, on ne touche ni à l'IPTC ni au commentaire en base : on garde
// tel quel ce qui existe déjà (voir $comment_to_keep plus bas).
$description_sent = isset($params['description']);
  
 $faces_json = $params['faces'];

 //error_log('FACES JSON reçu (raw bytes): ' . bin2hex(substr($faces_json, 0, 100)));

  
  if (strpos($faces_json, '\\"') !== false) {
    $faces_json = stripslashes($faces_json);
  }
  
  $faces = json_decode($faces_json, true);
  
  
 
  if (!is_array($faces))
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid faces data');
  }
  
  // Permettre tableau vide pour supprimer tous les tags
  if (count($faces) === 0) {
    //*error_log('-> Suppression de tous les visages (tableau vide)');
  }
  
  //*error_log('-> ' . count($faces) . ' visages à  enregistrer');
  
  $query = '
  SELECT path, comment
  FROM ' . IMAGES_TABLE . '
  WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row)
  {
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'Image not found');
  }
  
  // Valeur de comment à réaffirmer en base après l'écriture des métadonnées :
  // - description envoyée -> c'est la nouvelle valeur (HTML riche compris, peut être null pour effacer)
  // - description non envoyée (champ en lecture seule) -> on garde la valeur actuelle en base
  $comment_to_keep = $description_sent ? $description : $row['comment'];
  
  // Construire le chemin local et le résoudre (gère les liens symboliques)
  $real_local_path = face_tag_write_resolve_path($row['path']);

  //*error_log('Fichier local: ' . $real_local_path);

  if ($real_local_path === false) {
    //*error_log('❌ Impossible de résoudre le chemin du fichier');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot resolve file path');
  }

  //*error_log('Chemin local résolu: ' . $real_local_path);

  // Vérifier que le fichier existe et est accessible
  if (!file_exists($real_local_path)) {
    //*error_log('❌ Le fichier n\'existe pas: ' . $real_local_path);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(404, 'File not found at resolved path');
  }

  // Vérifier les permissions en lecture
  if (!is_readable($real_local_path)) {
    //*error_log('❌ Le fichier n\'est pas lisible');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(403, 'File is not readable');
  }

  // Créer un répertoire temporaire si nécessaire
  $temp_dir = PHPWG_ROOT_PATH . '_data/tmp';
  if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
    if (!is_dir($temp_dir)) {
      //*error_log('⚠️ Impossible de créer le répertoire temporaire');
      error_reporting($old_error_reporting);
      ini_set('display_errors', $old_display_errors);
      return new PwgError(500, 'Cannot create temporary directory');
    }
  }

  // Créer un fichier temporaire pour lire les métadonnées originales
  $temp_file = $temp_dir . '/facetag_write_' . uniqid() . '.jpg';
  $temp_for_reading = $temp_dir . '/facetag_read_' . uniqid() . '.jpg';

  // Copier le fichier original vers le fichier temporaire (pour écriture)
  if (!@copy($real_local_path, $temp_file)) {
    //*error_log('❌ Impossible de copier le fichier vers le fichier temporaire');
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot copy file to temporary location');
  }

  // Copier aussi pour lecture des métadonnées
  if (!@copy($real_local_path, $temp_for_reading)) {
    //*error_log('❌ Impossible de copier le fichier pour la lecture des métadonnées');
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Cannot copy file for metadata reading');
  }
  
  //*error_log('Fichier pour lecture métadonnées: ' . $temp_for_reading);
  
// ------------------------- BACKUP . original ------------------------------------------------------- V2.1
  // Récupérer le paramètre save_original depuis la requête 
$save_original_param = isset($params['save_original']) ? $params['save_original'] : null;

// Si non fourni dans la requête, utiliser la config par défaut
if ($save_original_param === null) {
  $config_string = conf_get_param('face_tag_editor_config', false);
  $config = $config_string ? unserialize($config_string) : array();
  $save_original = isset($config['save_original']) ? $config['save_original'] : true;
} else {
  // Convertir en booléen (au cas où c'est une string 'true'/'false' depuis JS)
  $save_original = ($save_original_param === 'true' || $save_original_param === true || $save_original_param === 1);
}

// Créer backup SEULEMENT si activé
$backup_path = $real_local_path . '.original';
$backup_created = false;

if ($save_original && !file_exists($backup_path)) {
  if (@copy($real_local_path, $backup_path)) {
    @chmod($backup_path, 0444);
    $backup_created = true;
    //*error_log('Backup créé');
  }
} else {
  if (!$save_original) {
    //*error_log('Création backup désactivée dans la config');
  } else {
    //*error_log('Backup existe déjà');
  }
}

//----------------------------------------------------------------------------------------------------------
  
  // IMPORTANT : Toujours rendre le fichier writable avant modification
  // (car il peut avoir été mis en 0644 lors d'une précédente sauvegarde)
  @chmod($real_local_path, 0666);
  //*error_log('Permissions du fichier mises à 0666 pour permettre l\'écriture');
  
// ✅ AJOUTER CE LOG
//$perms = fileperms($real_local_path);
//error_log('Permissions actuelles: ' . decoct($perms & 0777));


  
  $writer = new FaceTagMetadataWriterSimple();
  $merger = new FaceTagMetadataMerger();
  
  // Fusionner métadonnées (on passe le fichier de lecture pour lire les métadonnées existantes)
  $merged_data = $merger->merge($temp_for_reading, $faces);
  //*error_log('Métadonnées fusionnées');
  
  // Nettoyer le fichier de lecture
  @unlink($temp_for_reading);
  
  // Dériver une version texte brut de la description pour l'IPTC (2#120 ne supporte pas le HTML) ;
  // ne l'écrire dans le fichier que si elle a été explicitement envoyée par le client.
  $iptc_description = ($description !== null) ? face_tag_html_to_plain_text($description) : null;
  
  // Écrire métadonnées sur le fichier temporaire
  try {
    $result = $writer->writeMetadata($temp_file, $faces, $merged_data, $iptc_description, $description_sent);
  } catch (Exception $e) {
    //*error_log('Exception: ' . $e->getMessage());
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Exception: ' . $e->getMessage());
  }
  
  if ($result['success'])
  {
    //*error_log(' XMP écrit sur fichier temporaire');
    
    // Essayer d'écrire sur le chemin résolu
    //*error_log('>> main.inc.php');
    //*error_log('Tentative copie vers: ' . $real_local_path);
    //*error_log('Fichier existe: ' . (file_exists($real_local_path) ? 'OUI' : 'NON'));
    //*error_log('Writable: ' . (is_writable($real_local_path) ? 'OUI' : 'NON'));
    
    if (@copy($temp_file, $real_local_path)) {
      //*error_log('Fichier copié vers: ' . $real_local_path);
      @chmod($real_local_path, 0644);
      // Forcer le vidage du cache PHP et système
      clearstatcache(true, $real_local_path);
      // Mettre à jour la date de modification pour forcer le rechargement
      @touch($real_local_path);
      //*error_log('Cache vidé et date de modification mise à jour');
    } else {
      //*error_log('Échec copie - Dernière tentative: écriture directe');

      // Dernière tentative : lire le temp et écrire directement
      $content = file_get_contents($temp_file);
      if (@file_put_contents($real_local_path, $content) !== false) {
        //*error_log('Écriture directe réussie');
        @chmod($real_local_path, 0644);
        // Forcer le vidage du cache PHP et système
        clearstatcache(true, $real_local_path);
        // Mettre à jour la date de modification pour forcer le rechargement
        @touch($real_local_path);
        //*error_log('Cache vidé et date de modification mise à jour');
      } else {
        //*error_log('Toutes les méthodes ont échoué');
        @unlink($temp_file);
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        return new PwgError(500, 'Cannot write to file - check permissions on: ' . $real_local_path);
      }
    }

    // Nettoyer le fichier temporaire
    @unlink($temp_file);
    
    // Régénérer les miniatures  
    
    try {
      face_tag_write_regenerate_metadata($params['image_id'], $faces, $comment_to_keep, $real_local_path, $merged_data['all_subjects']);
      //*error_log(' Métadata synchronisées');
    } catch (Exception $e) {
      //*error_log('Erreur synchro metadonnées: ' . $e->getMessage());
    }

    try {
      face_tag_write_register_facetag_person_tags($params['image_id'], $faces);
    } catch (Exception $e) {
      error_log('face_tag_editor: erreur enregistrement facetag_person_tags: ' . $e->getMessage());
    }
    
    
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    $response = array(
      'stat' => 'ok',
      'message' => 'XMP saved successfully',
      'faces_count' => count($faces),
      'backup_created' => $backup_created
    );
    
    // Propager le warning si exiftool manque
    if (isset($result['warning'])) {
      $response['warning'] = $result['warning'];
      //*error_log('⚠ Warning propagé au client: ' . $result['warning']);
    }
    
    return $response;

  }
  else
  {
    //*error_log(' Échec écriture XMP: ' . $result['error']);
    @unlink($temp_file);
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    return new PwgError(500, 'Failed to write XMP: ' . $result['error']);
  }
}

// ==================== SYNCHRONISATION DES METADONNEES ====================
/**
 * Met à jour les tags Piwigo de la photo d'après l'ensemble exact des
 * mots-clés qui viennent d'être écrits dans le fichier (visages ET tags
 * généraux préexistants comme "Scan Alain"), et rafraîchit les champs
 * impactés par la réécriture (taille, date de synchro).
 *
 * N'appelle PAS sync_metadata() : cette fonction core RELIT le fichier et
 * reconstruit les tags depuis les métadonnées IPTC/EXIF, ce qui s'est avéré
 * peu fiable lors d'une mise à jour (ex: un tiret dans un nom disparaît).
 * On utilise ici $all_subjects, la liste EXACTE déjà calculée par
 * FaceTagMetadataMerger et utilisée par le writer pour composer le XMP/IPTC
 * — la même source de vérité, sans repasser par un roundtrip fichier ->
 * Piwigo. Remplace entièrement les tags de la photo par cet ensemble : un
 * tag qui n'est plus dans $all_subjects (visage renommé/retiré) est retiré,
 * ceux qui manquent sont ajoutés.
 *
 * Réaffirme aussi systématiquement la colonne 'comment' (désignation) avec la valeur voulue
 * par l'appelant ($description, HTML riche compris) : c'est la seule mise à jour de ce champ,
 * donc l'appelant doit y passer soit la nouvelle valeur envoyée par le client, soit la valeur
 * actuelle inchangée si le client n'en a pas envoyé (voir $comment_to_keep dans
 * face_tag_write_save_xmp()).
 *
 * @param int $image_id
 * @param array $faces Visages actuellement tagués (chaque élément a une clé 'name') — non utilisé directement ici, gardé pour compat d'appel
 * @param string|null $description Valeur voulue pour piwigo_images.comment (HTML riche compris), null pour effacer
 * @param string|null $file_path Chemin réel du fichier réécrit sur le disque
 * @param array $all_subjects Liste exacte des mots-clés écrits dans le fichier (issus de FaceTagMetadataMerger::merge()['all_subjects'])
 */
function face_tag_write_regenerate_metadata($image_id, $faces = array(), $description = null, $file_path = null, $all_subjects = array())
{
  if (!function_exists('tag_id_from_tag_name')) {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  }

  $names = array();
  foreach ($all_subjects as $name)
  {
    $name = trim((string)$name);
    if ($name !== '' && !in_array($name, $names))
    {
      $names[] = $name;
    }
  }

  if (empty($names))
  {
    // Aucun mot-clé du tout (ni visage, ni tag général) : la photo perd tous ses tags.
    pwg_query('DELETE FROM ' . IMAGE_TAG_TABLE . ' WHERE image_id = ' . intval($image_id) . ';');
  }
  else
  {
    $tag_ids = array();
    foreach ($names as $name)
    {
      $tag_ids[] = tag_id_from_tag_name($name);
    }
    $tag_ids = array_unique($tag_ids);

    // Retire les tags qui ne correspondent plus à un mot-clé du fichier
    pwg_query('
DELETE FROM ' . IMAGE_TAG_TABLE . '
  WHERE image_id = ' . intval($image_id) . '
    AND tag_id NOT IN (' . implode(',', $tag_ids) . ')
;');

    $existing_tag_ids = query2array('
SELECT tag_id
  FROM ' . IMAGE_TAG_TABLE . '
  WHERE image_id = ' . intval($image_id) . '
    AND tag_id IN (' . implode(',', $tag_ids) . ')
;', null, 'tag_id');

    $missing_tag_ids = array_diff($tag_ids, $existing_tag_ids);
    if (!empty($missing_tag_ids))
    {
      $inserts = array();
      foreach ($missing_tag_ids as $tag_id)
      {
        $inserts[] = array('image_id' => $image_id, 'tag_id' => $tag_id);
      }
      mass_inserts(IMAGE_TAG_TABLE, array('image_id', 'tag_id'), $inserts);
    }
  }

  // Réaffirmer la désignation (comment) en base : c'est la seule mise à jour de ce champ, elle
  // doit donc couvrir tous les cas (nouvelle valeur HTML riche, effacement, ou valeur inchangée
  // transmise telle quelle par l'appelant quand la description n'a pas été envoyée par le client).
  $comment_sql = ($description === null || strlen($description) == 0)
    ? 'NULL'
    : "'" . pwg_db_real_escape_string($description) . "'";
  pwg_query('UPDATE ' . IMAGES_TABLE . ' SET comment = ' . $comment_sql . ' WHERE id = ' . intval($image_id) . ';');

  // Le fichier a été réécrit sur le disque (métadonnées ajoutées/modifiées) :
  // on rafraîchit sa taille et sa date de synchro sans repasser par
  // sync_metadata().
  $update_fields = array('date_metadata_update' => "'" . date('Y-m-d') . "'");
  if ($file_path !== null && is_file($file_path))
  {
    $filesize_kb = @filesize($file_path);
    if ($filesize_kb !== false)
    {
      $update_fields['filesize'] = intval(floor($filesize_kb / 1024));
    }
  }
  $set_clauses = array();
  foreach ($update_fields as $field => $value)
  {
    $set_clauses[] = $field . ' = ' . $value;
  }
  pwg_query('UPDATE ' . IMAGES_TABLE . ' SET ' . implode(', ', $set_clauses) . ' WHERE id = ' . intval($image_id) . ';');

  invalidate_user_cache();
  //*error_log('✓ Tags Piwigo mis à jour directement pour image ' . $image_id);

  return true;
}

// ==================== INTÉGRATION AVEC LE PLUGIN face_tag ====================
/**
 * Le plugin face_tag peut masquer les tags de visage du nuage de tags public, via un registre
 * (table facetag_person_tags, tag_id -> is_face_tag=1) qu'il relit à chaque affichage du nuage
 * si son option "masquer les tags visage" est cochée. Cette politique d'affichage (option +
 * filtrage) reste entièrement dans face_tag, inchangée.
 *
 * En revanche, la tenue à jour du registre lui-même NE DOIT PAS dépendre de l'état d'activation
 * de face_tag : si face_tag est désactivé temporairement pendant qu'on crée/renomme des visages
 * avec face_tag_editor, puis réactivé, les tags créés dans l'intervalle doivent déjà être dans
 * le registre - sinon ils restent visibles dans le nuage jusqu'à un scan manuel, comme si
 * l'option avait été "annulée" (cas remonté par l'utilisateur le 2026-08-17). On enregistre donc
 * ici systématiquement, à chaque sauvegarde de visages, que face_tag soit actif ou non, avec
 * exactement la même requête SQL que face_tag utilise pour ses propres écritures (même table,
 * mêmes règles de préservation d'une ligne 'manual').
 *
 * @return string Nom complet (avec préfixe) de la table facetag_person_tags
 */
function face_tag_write_ensure_facetag_person_tags_table()
{
  global $prefixeTable;
  $table = $prefixeTable . 'facetag_person_tags';

  // Même définition que facetag_ensure_sync_tables() dans face_tag/admin/functions.inc.php :
  // indépendante de face_tag pour que la table existe même s'il n'a jamais été activé.
  pwg_query('
CREATE TABLE IF NOT EXISTS ' . $table . ' (
  tag_id           INT UNSIGNED NOT NULL,
  is_face_tag      TINYINT(1)   NOT NULL DEFAULT 1,
  source           ENUM(\'auto\',\'manual\') NOT NULL DEFAULT \'auto\',
  example_image_id INT UNSIGNED NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  return $table;
}

/**
 * Enregistre les tag_id des visages de la photo dans le registre facetag_person_tags,
 * inconditionnellement (voir docblock ci-dessus).
 *
 * @param int $image_id
 * @param array $faces Visages actuellement tagués (chaque élément a une clé 'name')
 */
function face_tag_write_register_facetag_person_tags($image_id, $faces)
{
  if (empty($faces))
  {
    return;
  }

  if (!function_exists('tag_id_from_tag_name'))
  {
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  }

  $names = array();
  foreach ($faces as $face)
  {
    if (empty($face['name']))
    {
      continue;
    }
    $name = trim((string)$face['name']);
    if ($name !== '' && !in_array($name, $names))
    {
      $names[] = $name;
    }
  }

  if (empty($names))
  {
    return;
  }

  $person_tags_table = face_tag_write_ensure_facetag_person_tags_table();
  $now = date('Y-m-d H:i:s');

  foreach ($names as $name)
  {
    $tag_id = tag_id_from_tag_name($name);

    pwg_query('
INSERT INTO ' . $person_tags_table . ' (tag_id, is_face_tag, source, example_image_id, updated_at)
VALUES (' . intval($tag_id) . ', 1, \'auto\', ' . intval($image_id) . ', \'' . $now . '\')
ON DUPLICATE KEY UPDATE
  example_image_id = IF(source=\'manual\', example_image_id, VALUES(example_image_id)),
  updated_at = IF(source=\'manual\', updated_at, VALUES(updated_at))
;');
  }
}

// ==================== DÉRIVATION TEXTE BRUT POUR IPTC ====================
// L'IPTC (tag 2#120, Caption-Abstract) ne supporte pas le HTML. On en dérive une version
// texte brut pour le fichier, sans jamais relire ce texte brut pour reconstituer le HTML
// riche stocké en BDD (round-trip à sens unique, portage de geo_tag_editor).
function face_tag_html_to_plain_text($html)
{
  $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
  $text = preg_replace('/<\/p>/i', "\n", $text);
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
  $text = trim($text);
  return ($text === '') ? null : $text;
}

//------------------------------------------------------------------------------
// Nouvelle fonction Web Service
function facetagwrite_ws_get_faces($params, &$service)
{
  if (empty($params['image_id']))
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Missing image_id');
  }
  
  // Récupérer le chemin de l'image
  $query = '
SELECT path
FROM ' . IMAGES_TABLE . '
WHERE id = ' . intval($params['image_id']);
  
  $result = pwg_query($query);
  $row = pwg_db_fetch_assoc($result);
  
  if (!$row) {
    return new PwgError(404, 'Image not found');
  }
  
  $image_path = PHPWG_ROOT_PATH . $row['path'];
  $image_path = face_tag_write_resolve_path($row['path']);
  
  if (!file_exists($image_path)) {
    return new PwgError(404, 'Image file not found');
  }
  
  // Utiliser metadata_reader pour lire les faces
  require_once(FACETAGWRITE_PATH . 'lib/metadata_reader.php');
  $reader = new FaceTagMetadataReader();
  $metadata = $reader->readAll($image_path);
  
  //*error_log('=== getFaces Web Service ===');
  //*error_log('Faces trouvées: ' . (isset($metadata['xmp']['faces']) ? count($metadata['xmp']['faces']) : 0));
  
  // Retourner les faces au format JSON
  return array(
    'image_id' => $params['image_id'],
    'orientation' => isset($metadata['orientation']) ? $metadata['orientation'] : 1,
    'faces' => isset($metadata['xmp']['faces']) ? $metadata['xmp']['faces'] : array()
  );
}




// ==================== TRADUCTIONS Méthode Lazy Loading ====================

add_event_handler('ws_add_methods', 'facetag_add_ws_methods');

function facetag_add_ws_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'facetag.getTranslations',
    'facetag_ws_get_translations',
    array(),
    'Get translations for face tag editor'
  );
}

function facetag_ws_get_translations($params, &$service)
{
  
  // Créer le tableau JavaScript
  $translations = array(
    'Éditeur de visages' => l10n('Éditeur de visages'),
    'Image' => l10n('Image'),
    'Instructions :' => l10n('Instructions :'),
    'Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage' => l10n('Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage'),
    'Visages tagués' => l10n('Visages tagués'),
    'Aucun visage tagué' => l10n('Aucun visage tagué'),
    'Tout effacer' => l10n('Tout effacer'),
    'Restaurer l\'original' => l10n('Restaurer l\'original'),
    'Restaurer le fichier .original (supprime tous les tags)' => l10n('Restaurer le fichier .original (supprime tous les tags)'),
    'Description...' => l10n('Description...'),
    'Description' => l10n('Description'),
    'Lecture seule : mise en forme HTML complexe détectée, non modifiable ici.' => l10n('Lecture seule : mise en forme HTML complexe détectée, non modifiable ici.'),
    'Annuler' => l10n('Annuler'),
    'Enregistrer ' => l10n('Enregistrer '),
    'Enregistrement...' => l10n('Enregistrement...'),
    'existant' => l10n('existant'),
    'Supprimer' => l10n('Supprimer'),
    'Nommer la personne' => l10n('Nommer la personne'),
    'Nom de la personne' => l10n('Nom de la personne'),
    'Personnes existantes :' => l10n('Personnes existantes :'),
    'Valider' => l10n('Valider'),
    'Veuillez entrer un nom' => l10n('Veuillez entrer un nom'),
    'Renommer la personne' => l10n('Renommer la personne'),
    'Ancien nom :' => l10n('Ancien nom :'),
    'Nouveau nom' => l10n('Nouveau nom'),
    'Autres personnes :' => l10n('Autres personnes :'),
    'Renommer' => l10n('Renommer'),
    'Taguer' => l10n('Taguer'),
    'Taguer les visages' => l10n('Taguer les visages'),
    'Aucun visage à effacer' => l10n('Aucun visage à effacer'),
    '✅ Fichier original restauré avec succès !' => l10n('✅ Fichier original restauré avec succès !'),
    'error_no_original_file' => l10n('error_no_original_file'),
    '❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.' => l10n('❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.'),
    '✅ Visages enregistrés avec succès !' => l10n('✅ Visages enregistrés avec succès !'),
    'Visages: ' => l10n('Visages: '),
    'Backup créé: Oui (.original)' => l10n('Backup créé: Oui (.original)'),
    'Backup: Déjà existant' => l10n('Backup: Déjà existant'),
    'Voulez-vous vraiment supprimer tous les tags de visages de cette image ?' => l10n('Voulez-vous vraiment supprimer tous les tags de visages de cette image ?'),
    'Êtes-vous sûr de vouloir effacer tous les rectangles ?' => l10n('Êtes-vous sûr de vouloir effacer tous les rectangles ?'),
    'confirm_restore_original' => l10n('confirm_restore_original'),
    'Télécharger JPG' => l10n('Télécharger JPG'),
    'Télécharger l\'image avec les rectangles visibles' => l10n('Télécharger l\'image avec les rectangles visibles'),
    'Aucun visage tagué à télécharger' => l10n('Aucun visage tagué à télécharger'),
    'Erreur lors de la génération de l\'image' => l10n('Erreur lors de la génération de l\'image')
  );

   return $translations;
  
}



?>