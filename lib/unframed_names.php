<?php
/**
 * Noms sans cadre (2.5.4) : personnes nommées dans le fichier sans cadre de visage.
 *
 * Deux formes, vues sur les photos réelles :
 * - une région nommée sans zone : nom Microsoft (MPReg:PersonDisplayName) sans MPReg:Rectangle, ou
 *   région MWG de type Face nommée sans mwg-rs:Area exploitable. digiKam écrit ainsi un tag de
 *   personne attaché à la photo sans cadre (cas réel : « Aurore » sur une photo de 1947) ;
 * - un mot-clé de personne (Racine|Nom, Racine/Nom sous une racine de personnes) sans cadre du même nom.
 *
 * Depuis 2.5.1, l'éditeur n'affiche que les vrais cadres et conserve le reste tel quel à
 * l'enregistrement : ces noms traversaient donc les enregistrements sans pouvoir être retirés.
 * La modale les liste ; « Retirer » les enlève du fichier à l'enregistrement suivant :
 * régions sans zone de ce nom, mots-clés de personne (dc:subject, lr:hierarchicalSubject,
 * digiKam:TagsList, MicrosoftPhoto:LastKeywordXMP, mediapro:CatalogSets ; digiKam:CatalogSets est
 * retiré en entier par le writer depuis 2.5.6) et
 * IPTC 2:25. Un mot-clé simple (dc:subject, IPTC) est gardé si le fichier porte aussi un tag ORDINAIRE
 * de ce nom (homonyme : chemin hors des racines de personnes).
 * Non traités : acdsee:categories et Exif XPKeywords (comme pour tout l'éditeur).
 *
 * Écriture par segments JPEG (lib/jpeg_segments.php), après l'écriture habituelle, sur le fichier
 * temporaire : rien n'atteint la photo si une étape échoue.
 * Requiert lib/editor_xmp_ex.php, lib/jpeg_segments.php, lib/keywords_tool.php.
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

require_once(FACETAGWRITE_PATH . 'lib/keywords_tool.php');

/** Comparaison des racines : casse ignorée. */
function facetagwrite_unframed_fold($s)
{
  $s = trim((string)$s);
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/**
 * Racines des personnes (forme repliée) : « Personnes », la racine réglée dans l'outil
 * « Mots-clés des visages » (kw_root), et celles de tag_manager (réglage enregistré, sinon
 * $conf['tag_manager_person_roots']).
 *
 * @return string[]
 */
function facetagwrite_person_roots()
{
  global $conf;
  $roots = array('Personnes');

  $raw = conf_get_param('face_tag_editor_config', false);
  $cfg = is_array($raw) ? $raw : ($raw ? @unserialize($raw) : array());
  if (is_array($cfg) && !empty($cfg['kw_root']))
  {
    $levels = array_values(array_filter(array_map('trim', explode('|', $cfg['kw_root'])), 'strlen'));
    if (!empty($levels))
    {
      $roots[] = $levels[0];
    }
  }

  $raw = conf_get_param('tag_manager_config', null);
  $tm = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
  if (is_array($tm) && !empty($tm['reserved']) && is_array($tm['reserved']))
  {
    $roots = array_merge($roots, $tm['reserved']);
  }
  elseif (!empty($conf['tag_manager_person_roots']) && is_array($conf['tag_manager_person_roots']))
  {
    $roots = array_merge($roots, $conf['tag_manager_person_roots']);
  }

  return array_values(array_unique(array_map('facetagwrite_unframed_fold', $roots)));
}

/** Niveaux non vides d'une entrée de chemin. */
function facetagwrite_unframed_levels($entry, $kind)
{
  $sep = ($kind === 'hier') ? '|' : '/';
  return array_values(array_filter(array_map('trim', explode($sep, $entry)), 'strlen'));
}

/**
 * Région nommée qui n'est pas un cadre pour l'éditeur, ou null.
 * @return string|null le nom
 */
function facetagwrite_unframed_region_name(DOMElement $li, $mp)
{
  if ($mp)
  {
    $name = trim((string)facetag_editor_mp_prop($li, 'PersonDisplayName'));
    return ($name !== '' && facetag_editor_mp_region_face($li) === null) ? $name : null;
  }
  $type = facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MWGRS, 'Type');
  if ($type !== null && strtolower(trim($type)) !== 'face')
  {
    return null; // BarCode, Focus, Pet… : pas une personne
  }
  $name = trim((string)facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MWGRS, 'Name'));
  return ($name !== '' && facetag_editor_mwg_region_face($li) === null) ? $name : null;
}

/**
 * Analyse d'un paquet XMP déjà chargé.
 *
 * @param DOMDocument $doc
 * @param string[] $frame_names noms des cadres (non listés)
 * @param string[] $roots racines de personnes (repliées)
 * @return array names : nom => array('region' => bool, 'keyword' => bool) dans l'ordre du fichier ;
 *               ordinary : nom => true pour les noms portés aussi par un tag ordinaire (chemin hors racines)
 */
function facetagwrite_unframed_analyse_doc(DOMDocument $doc, array $frame_names, array $roots)
{
  $res = array('names' => array(), 'ordinary' => array());
  $xp = facetag_editor_regions_xpath($doc);
  foreach (array(true, false) as $mp)
  {
    $nodes = $mp ? facetag_editor_mp_region_nodes($xp) : facetag_editor_mwg_region_nodes($xp);
    foreach ($nodes as $li)
    {
      $n = facetagwrite_unframed_region_name($li, $mp);
      if ($n !== null && !in_array($n, $frame_names, true))
      {
        if (!isset($res['names'][$n]))
        {
          $res['names'][$n] = array('region' => false, 'keyword' => false);
        }
        $res['names'][$n]['region'] = true;
      }
    }
  }

  foreach (facetagwrite_kw_fields() as $f)
  {
    if ($f['kind'] === 'leaf')
    {
      continue;
    }
    $node = facetagwrite_kw_find_node($doc, $f);
    if ($node === null)
    {
      continue;
    }
    foreach (facetagwrite_kw_read_list($node) as $v)
    {
      $levels = facetagwrite_unframed_levels($v, $f['kind']);
      if (count($levels) < 2)
      {
        continue;
      }
      $n = end($levels);
      if (!in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
      {
        $res['ordinary'][$n] = true;
        continue;
      }
      if (in_array($n, $frame_names, true))
      {
        continue;
      }
      if (!isset($res['names'][$n]))
      {
        $res['names'][$n] = array('region' => false, 'keyword' => false);
      }
      $res['names'][$n]['keyword'] = true;
    }
  }
  return $res;
}

/**
 * Noms sans cadre d'un fichier (lecture seule), pour la modale.
 *
 * @param string $path chemin réel
 * @return array liste de array('name', 'region', 'keyword', 'ordinary')
 */
function facetagwrite_unframed_list($path)
{
  $parts = facetag_editor_segment_parts($path);
  if ($parts === null)
  {
    return array();
  }
  $doc = facetag_editor_xmp_dom($parts['main']);
  if ($doc === null)
  {
    return array();
  }
  $frame_names = array();
  foreach (facetag_editor_faces_from_xmp(array($parts['main'], $parts['ext'])) as $f)
  {
    $frame_names[] = $f['name'];
  }
  $a = facetagwrite_unframed_analyse_doc($doc, $frame_names, facetagwrite_person_roots());
  $out = array();
  foreach ($a['names'] as $n => $info)
  {
    $out[] = array(
      'name' => $n,
      'region' => $info['region'],
      'keyword' => $info['keyword'],
      'ordinary' => isset($a['ordinary'][$n])
    );
  }
  return $out;
}

/**
 * Retire d'un contenu JPEG les noms donnés, s'ils sont toujours sans cadre dans ce contenu.
 * Les régions sans zone d'un nom qui A un cadre sont aussi retirées (doublon d'un cadre dessiné
 * dans l'éditeur) ; ses mots-clés, eux, restent.
 *
 * @param string $d contenu du fichier
 * @param string[] $names noms demandés par la modale
 * @param string[] $roots racines de personnes (repliées)
 * @return array changed, data (nouveau contenu), removed (noms retirés), leaf_removed (noms retirés
 *               de dc:subject / IPTC : à retirer aussi des tags Piwigo), warnings, error
 */
function facetagwrite_unframed_remove_data($d, array $names, array $roots)
{
  $res = array('changed' => false, 'data' => $d, 'removed' => array(), 'leaf_removed' => array(),
    'warnings' => array(), 'error' => null);

  $meta = facetagwrite_jpeg_read_meta($d);
  if ($meta === null)
  {
    $res['error'] = 'jpeg_illisible';
    return $res;
  }
  if ($meta['xmp'] === false || $meta['xmp'] === '')
  {
    return $res;
  }
  $doc = facetag_editor_xmp_dom($meta['xmp']);
  if ($doc === null)
  {
    $res['error'] = 'xmp_illisible';
    return $res;
  }
  $doc->preserveWhiteSpace = true;

  $frame_names = array();
  foreach (facetag_editor_faces_from_xmp(array($meta['xmp'])) as $f)
  {
    $frame_names[] = $f['name'];
  }
  $a = facetagwrite_unframed_analyse_doc($doc, $frame_names, $roots);
  $todo = array();
  foreach ($names as $n)
  {
    $n = trim((string)$n);
    if ($n !== '' && isset($a['names'][$n]) && !in_array($n, $todo, true))
    {
      $todo[] = $n;
    }
  }
  $xmp_changed = false;

  // 1. Régions sans zone : noms demandés, et noms qui ont désormais un cadre
  $xp = facetag_editor_regions_xpath($doc);
  foreach (array(true, false) as $mp)
  {
    $nodes = $mp ? facetag_editor_mp_region_nodes($xp) : facetag_editor_mwg_region_nodes($xp);
    foreach ($nodes as $li)
    {
      $n = facetagwrite_unframed_region_name($li, $mp);
      if ($n !== null && (in_array($n, $todo, true) || in_array($n, $frame_names, true)))
      {
        $box = $li->parentNode;
        $box->removeChild($li);
        $xmp_changed = true;
        if (!in_array($n, $res['removed'], true) && in_array($n, $todo, true))
        {
          $res['removed'][] = $n;
        }
        // Conteneur devenu vide : retirer toute la structure (MP:RegionInfo / mwg-rs:Regions)
        $has_li = false;
        foreach ($box->childNodes as $c)
        {
          if ($c instanceof DOMElement && $c->localName === 'li')
          {
            $has_li = true;
            break;
          }
        }
        if (!$has_li)
        {
          $top = $box;
          while ($top->parentNode instanceof DOMElement
            && !in_array($top->localName, array('RegionInfo', 'Regions'), true))
          {
            $top = $top->parentNode;
          }
          if ($top->parentNode instanceof DOMElement && in_array($top->localName, array('RegionInfo', 'Regions'), true))
          {
            // mwg-rs:Regions porte aussi AppliedToDimensions : sans région, il ne sert plus
            $top->parentNode->removeChild($top);
          }
        }
      }
    }
  }

  // 2. Mots-clés
  $leaf_ok = array();
  foreach ($todo as $n)
  {
    $leaf_ok[$n] = !isset($a['ordinary'][$n]);
  }
  foreach (facetagwrite_kw_fields() as $f)
  {
    $node = facetagwrite_kw_find_node($doc, $f);
    if ($node === null || empty($todo))
    {
      continue;
    }
    $box = facetagwrite_kw_box($node);
    if ($box === null)
    {
      continue;
    }
    $removed_here = false;
    foreach (iterator_to_array($box->childNodes) as $li)
    {
      if (!($li instanceof DOMElement) || $li->localName !== 'li')
      {
        continue;
      }
      $v = trim($li->textContent);
      $drop = null;
      if ($f['kind'] === 'leaf')
      {
        if (in_array($v, $todo, true) && $leaf_ok[$v])
        {
          $drop = $v;
        }
      }
      else
      {
        $levels = facetagwrite_unframed_levels($v, $f['kind']);
        if (count($levels) >= 2 && in_array(end($levels), $todo, true)
          && in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
        {
          $drop = end($levels);
        }
      }
      if ($drop !== null)
      {
        $box->removeChild($li);
        $removed_here = true;
        if (!in_array($drop, $res['removed'], true))
        {
          $res['removed'][] = $drop;
        }
        if ($f['kind'] === 'leaf' && !in_array($drop, $res['leaf_removed'], true))
        {
          $res['leaf_removed'][] = $drop;
        }
      }
    }
    if ($removed_here)
    {
      $xmp_changed = true;
      $has_li = false;
      foreach ($box->childNodes as $c)
      {
        if ($c instanceof DOMElement && $c->localName === 'li')
        {
          $has_li = true;
          break;
        }
      }
      if (!$has_li)
      {
        $node->parentNode->removeChild($node); // comme l'éditeur : pas de champ vide
      }
    }
  }

  // 3. IPTC 2:25 (même règle que dc:subject)
  $iptc_bin = null;
  if (!empty($todo) && $meta['iptc'] !== false && $meta['iptc'] !== '')
  {
    $records = facetagwrite_kw_iptc_parse($meta['iptc']);
    if ($records === null)
    {
      $res['warnings'][] = 'iptc_illisible';
    }
    else
    {
      $kept = array();
      $iptc_changed = false;
      foreach ($records as $r)
      {
        $v = ($r[0] === 2 && $r[1] === 25) ? trim($r[2]) : null;
        if ($v !== null && in_array($v, $todo, true) && $leaf_ok[$v])
        {
          $iptc_changed = true;
          if (!in_array($v, $res['removed'], true))
          {
            $res['removed'][] = $v;
          }
          if (!in_array($v, $res['leaf_removed'], true))
          {
            $res['leaf_removed'][] = $v;
          }
          continue;
        }
        $kept[] = $r;
      }
      if ($iptc_changed)
      {
        $iptc_bin = facetagwrite_kw_iptc_build($kept);
      }
    }
  }

  if (!$xmp_changed && $iptc_bin === null)
  {
    return $res;
  }

  $packet = $meta['xmp'];
  if ($xmp_changed)
  {
    $doc->encoding = 'UTF-8';
    $xml = $doc->saveXML($doc->documentElement);
    $packet = "<?xpacket begin=\"\xEF\xBB\xBF\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n" . $xml . "\n<?xpacket end=\"w\"?>";
    if (strlen($packet) > FACETAGWRITE_KW_XMP_MAX)
    {
      $res['error'] = 'xmp_trop_gros';
      return $res;
    }
  }
  try
  {
    $res['data'] = facetagwrite_jpeg_write_meta($d, $packet, $iptc_bin);
  }
  catch (Exception $e)
  {
    $res['error'] = $e->getMessage();
    return $res;
  }
  $res['changed'] = ($res['data'] !== $d);
  return $res;
}
?>
