<?php
/**
 * Onglet « Mots-clés des visages » (2.5.2) : écrit dans les fichiers les mots-clés manquants des
 * cadres de visage nommés (voir lib/keywords_tool.php).
 *
 * Onglet masqué par défaut : visible seulement si $conf['face_tag_editor_keywords_tool'] = true
 * (LocalFiles Editor). Webmestres seulement. Chaque action AJAX exige le pwg_token.
 * Analyse en lecture seule, par lots ; écriture des seuls cas sûrs (nom présent dans le registre
 * facetag_person_tags comme visage), les cas douteux sont listés à part sans écriture.
 */
defined('FACETAGWRITE_PATH') or die('Hacking attempt!');

global $conf, $template, $prefixeTable;

load_language('plugin.lang', FACETAGWRITE_PATH);
include_once(FACETAGWRITE_PATH . 'lib/keywords_tool.php');

if (empty($conf['face_tag_editor_keywords_tool']) || !is_webmaster())
{
  redirect(FACETAGWRITE_ADMIN . '&tab=help');
}

define('FACETAGWRITE_KW_SCAN_BATCH', 40);
define('FACETAGWRITE_KW_WRITE_BATCH', 10);

/** Réglages de l'outil (dans face_tag_editor_config). */
function facetagwrite_kw_settings()
{
  $config_string = conf_get_param('face_tag_editor_config', false);
  $config = $config_string ? unserialize($config_string) : array();
  if (!is_array($config))
  {
    $config = array();
  }
  return array(
    'root' => isset($config['kw_root']) ? $config['kw_root'] : 'Personnes',
    'original' => isset($config['kw_original']) ? (bool)$config['kw_original']
      : (isset($config['save_original']) ? (bool)$config['save_original'] : true),
  );
}

/**
 * Racine validée : niveaux séparés par « | », non vides, sans « / » (séparateur de digiKam:TagsList).
 * @return string[]|null niveaux, ou null si invalide
 */
function facetagwrite_kw_parse_root($root)
{
  $root = trim((string)$root);
  if ($root === '' || strlen($root) > 200 || strpos($root, '/') !== false || !preg_match('//u', $root))
  {
    return null;
  }
  $levels = array_map('trim', explode('|', $root));
  foreach ($levels as $l)
  {
    if ($l === '')
    {
      return null;
    }
  }
  return $levels;
}

/** Comparaison souple : minuscules, sans accents. */
function facetagwrite_kw_norm($s)
{
  $s = remove_accents($s);
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/**
 * Classe un nom de cadre : sûr (tag existant, écrit pareil, présent comme visage dans le registre)
 * ou douteux (raison + noms de personnes proches).
 *
 * @return array status (sure|doubt), tag_id (int|null), reason (no_tag|variant|ordinary_tag|null), near (string[])
 */
function facetagwrite_kw_classify($name)
{
  global $prefixeTable;
  static $cache = array();
  static $registry = null; // tag_id => nom

  if (isset($cache[$name]))
  {
    return $cache[$name];
  }
  if ($registry === null)
  {
    $registry = array();
    $table = $prefixeTable . 'facetag_person_tags';
    if (pwg_db_num_rows(pwg_query('SHOW TABLES LIKE \'' . pwg_db_real_escape_string($table) . '\';')) > 0)
    {
      $rows = query2array('
SELECT t.id, t.name
  FROM ' . $table . ' AS r
  INNER JOIN ' . TAGS_TABLE . ' AS t ON t.id = r.tag_id
  WHERE r.is_face_tag = 1
;');
      foreach ($rows as $row)
      {
        $registry[intval($row['id'])] = $row['name'];
      }
    }
  }

  $res = array('status' => 'doubt', 'tag_id' => null, 'reason' => null, 'near' => array(), 'via' => null);

  // Tag que Piwigo associerait à ce nom, avec la même règle que tag_id_from_tag_name() (utilisée par la
  // synchronisation des métadonnées et par l'enregistrement de la modale) : nom (collation MySQL :
  // casse et accents selon la table), sinon nom d'URL. Ex. « @ macareux » = tag « macareux » (même
  // nom d'URL) : Piwigo ne peut pas avoir les deux.
  $rows = query2array('
SELECT id, name
  FROM ' . TAGS_TABLE . '
  WHERE name = \'' . pwg_db_real_escape_string($name) . '\'
;');
  if (empty($rows))
  {
    $rows = query2array('
SELECT id, name
  FROM ' . TAGS_TABLE . '
  WHERE url_name = \'' . pwg_db_real_escape_string(trigger_change('render_tag_url', $name)) . '\'
;');
  }
  $tag = null;
  foreach ($rows as $row)
  {
    if ($tag === null || $row['name'] === $name)
    {
      $tag = $row;
    }
  }

  if ($tag === null)
  {
    $res['reason'] = 'no_tag'; // Piwigo créerait un tag
  }
  else
  {
    $res['tag_id'] = intval($tag['id']);
    if (isset($registry[$res['tag_id']]))
    {
      $res['status'] = 'sure'; // personne connue (même tag pour Piwigo, même si l'écriture diffère)
      $res['via'] = 'registry';
    }
    elseif (count(face_tag_write_tag_manager_paths(array($res['tag_id']), true)) > 0)
    {
      // Rangé comme personne dans tag_manager (Personnes|Nom, ou autre racine réservée aux visages) :
      // cas typique de la reprise Picasa, personne étiquetée autrefois sans cadre, donc jamais entrée
      // au registre. Un homonyme (tag ordinaire de même nom) a, lui, un chemin ordinaire.
      $res['status'] = 'sure';
      $res['via'] = 'tag_manager';
    }
    elseif ($tag['name'] === $name)
    {
      $res['reason'] = 'ordinary_tag'; // homonyme possible : tag ordinaire de même nom
    }
    else
    {
      $res['reason'] = 'variant'; // tag ordinaire que Piwigo confond avec ce nom (casse, accents, symboles)
      $res['near'][] = $tag['name'];
    }
  }

  if ($res['status'] === 'doubt' && $res['reason'] !== 'variant')
  {
    // Personnes connues d'orthographe proche (ex. Chevalier / Chevallier)
    $n = facetagwrite_kw_norm($name);
    foreach ($registry as $id => $other)
    {
      if ($other === $name)
      {
        continue;
      }
      $o = facetagwrite_kw_norm($other);
      if ($o === $n || (strlen($n) >= 5 && strlen($o) >= 5 && strlen($n) <= 255 && strlen($o) <= 255
        && levenshtein($n, $o) <= 2))
      {
        $res['near'][] = $other;
        if (count($res['near']) >= 5)
        {
          break;
        }
      }
    }
  }

  $cache[$name] = $res;
  return $res;
}

/** Contenu d'un JPEG pour l'analyse : début du fichier, ou le fichier entier si l'en-tête est plus long. */
function facetagwrite_kw_read_head($path)
{
  $fp = @fopen($path, 'rb');
  if (!$fp)
  {
    return false;
  }
  $d = fread($fp, 524288);
  if ($d !== false && strlen($d) === 524288 && facetagwrite_jpeg_parse($d) === null)
  {
    while (!feof($fp))
    {
      $chunk = fread($fp, 1048576);
      if ($chunk === false || $chunk === '')
      {
        break;
      }
      $d .= $chunk;
    }
  }
  fclose($fp);
  return $d;
}

/** Chemin lisible d'un album (« Parent / Enfant »). */
function facetagwrite_kw_album_label($cat_id)
{
  static $cats = null;
  if ($cats === null)
  {
    $cats = array();
    foreach (query2array('SELECT id, name, uppercats FROM ' . CATEGORIES_TABLE . ';') as $row)
    {
      $cats[intval($row['id'])] = $row;
    }
  }
  $cat_id = intval($cat_id);
  if (!isset($cats[$cat_id]))
  {
    return '?';
  }
  $names = array();
  foreach (explode(',', $cats[$cat_id]['uppercats']) as $id)
  {
    $id = intval($id);
    if (isset($cats[$id]))
    {
      $names[] = strip_tags(trigger_change('render_category_name', $cats[$id]['name'], 'admin_cat_list'));
    }
  }
  return implode(' / ', $names);
}

/**
 * Analyse une photo.
 * @return array|null null si rien à signaler
 */
function facetagwrite_kw_scan_image(array $row, array $photo_tags)
{
  $item = array(
    'id' => intval($row['id']),
    'file' => $row['file'],
    'album_id' => intval($row['storage_category_id']), // répertoire physique, sinon premier album (photo téléversée)
    'write' => array(),  // noms sûrs dont le mot-clé manque dans le fichier
    'tag' => array(),    // noms sûrs dont le tag Piwigo manque sur la photo
    'create' => array(), // noms sans tag Piwigo, mais déjà dans tous les mots-clés du fichier : tag à créer
    'doubts' => array(), // array(name, reason, near)
    'error' => null,
  );
  $path = face_tag_write_resolve_path($row['path']);
  if ($path === false || !is_file($path))
  {
    $item['error'] = 'fichier_introuvable';
    return $item;
  }
  $d = facetagwrite_kw_read_head($path);
  if ($d === false)
  {
    $item['error'] = 'fichier_illisible';
    return $item;
  }
  $a = facetagwrite_kw_analyse_data($d);
  if (empty($a['names']))
  {
    return null; // pas de cadre nommé
  }
  if ($a['error'] !== null)
  {
    $item['error'] = $a['error'];
    return $item;
  }
  foreach ($a['names'] as $name)
  {
    $c = facetagwrite_kw_classify($name);
    $missing_in_file = isset($a['missing'][$name]);
    $tag_missing = ($c['tag_id'] === null || !in_array($c['tag_id'], $photo_tags, true));
    if (!$missing_in_file && !$tag_missing)
    {
      continue;
    }
    if ($c['status'] === 'sure')
    {
      if ($missing_in_file)
      {
        $item['write'][] = $name;
      }
      if ($tag_missing)
      {
        $item['tag'][] = $name;
      }
    }
    elseif ($c['reason'] === 'no_tag' && !$missing_in_file)
    {
      // Le fichier est cohérent (cadre + mots-clés de ce nom) : seul Piwigo n'a pas suivi (ex. nom
      // de cadre renommé, tag jamais créé). Rien à écrire dans le fichier, le tag est à créer.
      $item['create'][] = $name;
    }
    else
    {
      $item['doubts'][] = array('name' => $name, 'reason' => $c['reason'], 'near' => $c['near']);
    }
  }
  if (empty($item['write']) && empty($item['tag']) && empty($item['create']) && empty($item['doubts']))
  {
    return null;
  }
  return $item;
}

/** Ajoute les tags Piwigo manquants d'une photo (sans en retirer). @return int nombre ajouté */
function facetagwrite_kw_add_photo_tags($image_id, array $tag_ids)
{
  if (empty($tag_ids))
  {
    return 0;
  }
  $existing = query2array('
SELECT tag_id
  FROM ' . IMAGE_TAG_TABLE . '
  WHERE image_id = ' . intval($image_id) . '
    AND tag_id IN (' . implode(',', array_map('intval', $tag_ids)) . ')
;', null, 'tag_id');
  $inserts = array();
  foreach (array_unique($tag_ids) as $tag_id)
  {
    if (!in_array($tag_id, array_map('intval', $existing), true))
    {
      $inserts[] = array('image_id' => intval($image_id), 'tag_id' => intval($tag_id));
    }
  }
  if (!empty($inserts))
  {
    mass_inserts(IMAGE_TAG_TABLE, array('image_id', 'tag_id'), $inserts);
  }
  return count($inserts);
}

// ==================== ÉTAT DE L'ANALYSE (reprise) ====================
// L'analyse d'une grande galerie dure longtemps (≈ 30 photos/s) : son état est gardé sur le serveur,
// pour pouvoir l'interrompre, recharger la page et la reprendre plus tard au même endroit.

function facetagwrite_kw_state_file()
{
  global $conf;
  return PHPWG_ROOT_PATH . $conf['data_location'] . 'plugins/face_tag_editor_kwscan.json';
}

/** @return array|null */
function facetagwrite_kw_state_load()
{
  $f = facetagwrite_kw_state_file();
  if (!is_file($f))
  {
    return null;
  }
  $s = json_decode((string)@file_get_contents($f), true);
  return is_array($s) ? $s : null;
}

function facetagwrite_kw_state_save(array $s)
{
  $f = facetagwrite_kw_state_file();
  if (!is_dir(dirname($f)))
  {
    @mkdir(dirname($f), 0755, true);
  }
  $s['updated'] = time();
  @file_put_contents($f, json_encode($s), LOCK_EX);
}

/** Albums choisis d'un état (liste ; ancien format : 'cat' seul). @return int[] */
function facetagwrite_kw_state_cats(array $s)
{
  if (isset($s['cats']) && is_array($s['cats']))
  {
    return array_values(array_filter(array_map('intval', $s['cats'])));
  }
  return (!empty($s['cat'])) ? array(intval($s['cat'])) : array();
}

/** Condition SQL des photos des albums choisis (et de leurs sous-albums), '' pour toute la galerie. */
function facetagwrite_kw_scope_sql(array $cats)
{
  $cats = array_values(array_filter(array_map('intval', $cats)));
  if (empty($cats))
  {
    return '';
  }
  $cat_ids = get_subcat_ids($cats);
  return ' AND id IN (SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE
    . ' WHERE category_id IN (' . implode(',', array_map('intval', $cat_ids)) . '))';
}

/** Lot suivant de l'analyse (modifie $state). @return array photos signalées dans ce lot */
function facetagwrite_kw_scan_batch(array &$state)
{
  $rows = query2array('
SELECT id, path, file,
    COALESCE(storage_category_id, (SELECT MIN(ic.category_id) FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic WHERE ic.image_id = i.id)) AS storage_category_id
  FROM ' . IMAGES_TABLE . ' AS i
  WHERE id > ' . intval($state['last']) . facetagwrite_kw_scope_sql(facetagwrite_kw_state_cats($state)) . '
  ORDER BY id
  LIMIT ' . FACETAGWRITE_KW_SCAN_BATCH . '
;');
  $photo_tags = array();
  if (!empty($rows))
  {
    $ids = array();
    foreach ($rows as $row)
    {
      $ids[] = intval($row['id']);
    }
    foreach (query2array('SELECT image_id, tag_id FROM ' . IMAGE_TAG_TABLE . ' WHERE image_id IN (' . implode(',', $ids) . ');') as $t)
    {
      $photo_tags[intval($t['image_id'])][] = intval($t['tag_id']);
    }
  }
  $new = array();
  foreach ($rows as $row)
  {
    $state['last'] = intval($row['id']);
    $state['scanned']++;
    if (!preg_match('/\.jpe?g$/i', $row['path']))
    {
      continue; // l'écriture par segments ne concerne que le JPEG
    }
    $item = facetagwrite_kw_scan_image($row, isset($photo_tags[intval($row['id'])]) ? $photo_tags[intval($row['id'])] : array());
    if ($item !== null)
    {
      $new[] = $item;
      $state['items'][] = $item;
      if (!isset($state['albums'][$item['album_id']]))
      {
        $state['albums'][$item['album_id']] = facetagwrite_kw_album_label($item['album_id']);
      }
    }
  }
  if (count($rows) < FACETAGWRITE_KW_SCAN_BATCH)
  {
    $state['done'] = true;
    $state['scanned'] = max($state['scanned'], $state['total']);
  }
  if (!empty($rows))
  {
    $state['current'] = facetagwrite_kw_album_label(intval($rows[count($rows) - 1]['storage_category_id']));
  }
  return $new;
}

/** Résumé de l'état pour la page (sans la liste des photos). */
function facetagwrite_kw_state_summary($s)
{
  if ($s === null)
  {
    return null;
  }
  return array(
    'cats' => facetagwrite_kw_state_cats($s), 'cat_label' => $s['cat_label'], 'total' => $s['total'], 'scanned' => $s['scanned'],
    'done' => $s['done'], 'started' => $s['started'], 'updated' => isset($s['updated']) ? $s['updated'] : $s['started'],
    'current' => isset($s['current']) ? $s['current'] : '',
  );
}

// ==================== ACTIONS AJAX ====================
if (isset($_POST['kw_action']))
{
  header('Content-Type: application/json; charset=UTF-8');
  $out = array('stat' => 'ok');

  if (!isset($_POST['pwg_token']) || !hash_equals(get_pwg_token(), (string)$_POST['pwg_token']))
  {
    echo json_encode(array('stat' => 'fail', 'message' => 'Invalid security token'));
    exit;
  }

  $settings = facetagwrite_kw_settings();

  switch ($_POST['kw_action'])
  {
    case 'save':
      $root_raw = isset($_POST['root']) ? stripslashes($_POST['root']) : '';
      $levels = facetagwrite_kw_parse_root($root_raw);
      if ($levels === null)
      {
        $out = array('stat' => 'fail', 'message' => l10n('kwtool_root_invalid'));
        break;
      }
      $config_string = conf_get_param('face_tag_editor_config', false);
      $config = $config_string ? unserialize($config_string) : array();
      if (!is_array($config))
      {
        $config = array();
      }
      $config['kw_root'] = implode('|', $levels);
      $config['kw_original'] = !empty($_POST['original']) && $_POST['original'] !== 'false';
      conf_update_param('face_tag_editor_config', serialize($config));
      $out['root'] = $config['kw_root'];
      break;

    case 'state':
      // État conservé : résumé + photos signalées (pour réafficher l'aperçu après rechargement)
      $s = facetagwrite_kw_state_load();
      $out['state'] = facetagwrite_kw_state_summary($s);
      $out['items'] = ($s === null) ? array() : $s['items'];
      $out['albums'] = ($s === null) ? new stdClass() : $s['albums'];
      break;

    case 'scan_start':
      // Albums cochés dans l'arbre (aucun = toute la galerie), existants seulement
      $cats = array();
      if (isset($_POST['cats']) && $_POST['cats'] !== '')
      {
        $asked = array_slice(array_filter(array_map('intval', explode(',', $_POST['cats']))), 0, 500);
        if (!empty($asked))
        {
          $cats = array_map('intval', query2array('SELECT id FROM ' . CATEGORIES_TABLE . ' WHERE id IN (' . implode(',', $asked) . ');', null, 'id'));
        }
      }
      list($total) = pwg_db_fetch_row(pwg_query('SELECT COUNT(*) FROM ' . IMAGES_TABLE . ' WHERE 1' . facetagwrite_kw_scope_sql($cats) . ';'));
      $labels = array();
      foreach ($cats as $c)
      {
        $labels[] = facetagwrite_kw_album_label($c);
      }
      $s = array(
        'cats' => $cats, 'cat_label' => implode(' ; ', $labels),
        'total' => intval($total), 'scanned' => 0, 'last' => 0, 'done' => false,
        'started' => time(), 'items' => array(), 'albums' => array(),
      );
      facetagwrite_kw_state_save($s);
      $out['state'] = facetagwrite_kw_state_summary($s);
      break;

    case 'scan_batch':
      $s = facetagwrite_kw_state_load();
      if ($s === null)
      {
        $out = array('stat' => 'fail', 'message' => l10n('kwtool_no_scan'));
        break;
      }
      $out['items'] = $s['done'] ? array() : facetagwrite_kw_scan_batch($s);
      facetagwrite_kw_state_save($s);
      $out['albums'] = $s['albums'];
      $out['state'] = facetagwrite_kw_state_summary($s);
      break;

    case 'write':
      $root = facetagwrite_kw_parse_root($settings['root']);
      if ($root === null)
      {
        $out = array('stat' => 'fail', 'message' => l10n('kwtool_root_invalid'));
        break;
      }
      $ids = isset($_POST['ids']) ? array_slice(array_filter(array_map('intval', explode(',', $_POST['ids']))), 0, FACETAGWRITE_KW_WRITE_BATCH) : array();
      $out['results'] = array();
      foreach ($ids as $id)
      {
        $r = array('id' => $id, 'written' => array(), 'tags_added' => 0, 'warnings' => array(), 'error' => null);
        $row = query2array('SELECT id, path FROM ' . IMAGES_TABLE . ' WHERE id = ' . $id . ';');
        $path = empty($row) ? false : face_tag_write_resolve_path($row[0]['path']);
        if ($path === false || !is_file($path))
        {
          $r['error'] = 'fichier_introuvable';
          $out['results'][] = $r;
          continue;
        }
        // Nouvelle lecture du fichier : seuls les cas sûrs, et ce qui manque encore (lot repris)
        $a = facetagwrite_kw_analyse_data((string)@file_get_contents($path));
        if ($a['error'] !== null)
        {
          $r['error'] = $a['error'];
          $out['results'][] = $r;
          continue;
        }
        $sure = array();
        $tag_ids = array();
        $create = array();
        $to_register = array(); // personnes rangées dans tag_manager, pas encore au registre
        foreach ($a['names'] as $name)
        {
          $c = facetagwrite_kw_classify($name);
          if ($c['status'] === 'sure')
          {
            $sure[] = $name;
            $tag_ids[] = $c['tag_id'];
            if ($c['via'] === 'tag_manager')
            {
              $to_register[] = array('name' => $name);
            }
          }
          elseif ($c['reason'] === 'no_tag' && !isset($a['missing'][$name]))
          {
            $create[] = $name;
          }
        }
        if (!empty($create))
        {
          // Tag créé et inscrit au registre des visages, comme à l'enregistrement de la modale
          $faces = array();
          foreach ($create as $name)
          {
            $faces[] = array('name' => $name);
          }
          face_tag_write_register_facetag_person_tags($id, $faces);
          $new_ids = array();
          foreach ($create as $name)
          {
            // Le tag exact (la collation MySQL peut aussi renvoyer une graphie voisine)
            $rows = query2array('SELECT id, name FROM ' . TAGS_TABLE . ' WHERE name = \'' . pwg_db_real_escape_string($name) . '\';');
            foreach ($rows as $t)
            {
              if ($t['name'] === $name)
              {
                $new_ids[] = intval($t['id']);
              }
            }
          }
          $r['tags_added'] += facetagwrite_kw_add_photo_tags($id, $new_ids);
          $r['created'] = $create;
        }
        if (!empty($sure))
        {
          $w = facetagwrite_kw_write_file($path, $sure, $root, $settings['original']);
          $r['written'] = $w['written'];
          $r['warnings'] = $w['warnings'];
          $r['error'] = $w['error'];
          if ($w['changed'])
          {
            $size = @filesize($path);
            pwg_query('UPDATE ' . IMAGES_TABLE . ' SET '
              . ($size !== false ? 'filesize = ' . intval(floor($size / 1024)) . ', ' : '')
              . 'date_metadata_update = \'' . date('Y-m-d') . '\' WHERE id = ' . $id . ';');
          }
          if ($w['error'] === null)
          {
            // Tags Piwigo depuis la liste écrite (pas de sync_metadata(), voir face_tag_write_regenerate_metadata())
            $r['tags_added'] += facetagwrite_kw_add_photo_tags($id, $tag_ids);
            if (!empty($to_register))
            {
              // Elles ont maintenant un cadre et le mot-clé : inscription au registre, comme la modale
              face_tag_write_register_facetag_person_tags($id, $to_register);
            }
          }
        }
        $out['results'][] = $r;
      }
      if (!empty($out['results']))
      {
        invalidate_user_cache_nb_tags();
        // État conservé : les cas sûrs traités sans erreur ne sont plus à faire
        $s = facetagwrite_kw_state_load();
        if ($s !== null)
        {
          $ok = array();
          foreach ($out['results'] as $r)
          {
            if ($r['error'] === null)
            {
              $ok[$r['id']] = true;
            }
          }
          $kept = array();
          foreach ($s['items'] as $item)
          {
            if (isset($ok[$item['id']]))
            {
              $item['write'] = array();
              $item['tag'] = array();
              $item['create'] = array();
              if (empty($item['doubts']))
              {
                continue;
              }
            }
            $kept[] = $item;
          }
          $s['items'] = $kept;
          facetagwrite_kw_state_save($s);
        }
      }
      break;

    default:
      $out = array('stat' => 'fail', 'message' => 'Unknown action');
  }

  echo json_encode($out);
  exit;
}

/**
 * Arbre des albums pour la sélection (comme synchro_smart) : ordre global_rank (parent avant ses
 * enfants), profondeur, nombre de photos propres à l'album.
 */
function facetagwrite_kw_albums_tree()
{
  $rows = query2array('SELECT id, name, global_rank, dir FROM ' . CATEGORIES_TABLE . ';');
  usort($rows, 'global_rank_compare');
  $counts = array();
  foreach (query2array('SELECT category_id, COUNT(*) AS nb FROM ' . IMAGE_CATEGORY_TABLE . ' GROUP BY category_id;') as $c)
  {
    $counts[intval($c['category_id'])] = intval($c['nb']);
  }
  $albums = array();
  $n = count($rows);
  foreach ($rows as $i => $row)
  {
    $depth = substr_count($row['global_rank'], '.');
    $albums[] = array(
      'id' => intval($row['id']),
      'name' => strip_tags(trigger_change('render_category_name', $row['name'], 'admin_cat_list')),
      'depth' => $depth,
      'nb' => isset($counts[intval($row['id'])]) ? $counts[intval($row['id'])] : 0,
      'virtual' => ($row['dir'] === null),
      'has_children' => ($i + 1 < $n) && substr_count($rows[$i + 1]['global_rank'], '.') > $depth,
    );
  }
  return $albums;
}

// ==================== PAGE ====================
facetageditor_admin_tabsheet('keywords_tool');

$settings = facetagwrite_kw_settings();
$template->assign('KW_ALBUMS', facetagwrite_kw_albums_tree());

$js_keys = array(
  'kwtool_starting', 'kwtool_scanning', 'kwtool_scan_done', 'kwtool_stopped', 'kwtool_stop_requested',
  'kwtool_network_retry', 'kwtool_nothing', 'kwtool_confirm_write', 'kwtool_confirm_restart', 'kwtool_writing',
  'kwtool_write_done', 'kwtool_error', 'kwtool_saved', 'kwtool_near', 'kwtool_select_album', 'kwtool_written',
  'kwtool_tags_added', 'kwtool_failures', 'kwtool_last_scan', 'kwtool_whole_gallery_label', 'kwtool_resume_hint',
  'kwtool_selection_count', 'kwtool_selection_none', 'kwtool_new_tag',
  'kwtool_reason_no_tag', 'kwtool_reason_variant', 'kwtool_reason_ordinary_tag',
  'kwtool_err_fichier_introuvable', 'kwtool_err_fichier_illisible', 'kwtool_err_jpeg_illisible',
  'kwtool_err_xmp_etendu', 'kwtool_err_xmp_illisible', 'kwtool_err_xmp_sans_rdf', 'kwtool_err_xmp_trop_gros',
  'kwtool_err_original_impossible', 'kwtool_err_ecriture_impossible', 'kwtool_err_iptc_jeu_caracteres',
  'kwtool_err_iptc_illisible', 'kwtool_err_iptc_trop_long',
);
$js_lang = array();
foreach ($js_keys as $k)
{
  $js_lang[$k] = l10n($k);
}

$template->assign(array(
  'KW_ROOT' => $settings['root'],
  'KW_ORIGINAL' => $settings['original'],
  'KW_URL' => FACETAGWRITE_ADMIN . '&tab=keywords_tool',
  'KW_TOKEN' => get_pwg_token(),
  'KW_LANG_JSON' => json_encode($js_lang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
));

$template->func_combine_script(array(
  'id' => 'face_tag_editor_admin_keywords',
  'path' => FACETAGWRITE_PATH . 'js/admin_keywords.js',
  'require' => 'jquery',
));

$template->set_filename('face_tag_editor_keywords_tool', dirname(__FILE__) . '/../template/keywords_tool.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'face_tag_editor_keywords_tool');
?>
