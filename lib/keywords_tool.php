<?php
/**
 * Outil de contrôle (2.5.2) : écrire les mots-clés manquants des visages.
 *
 * Pour chaque cadre de visage nommé d'une photo dont le fichier n'a pas le mot-clé de la personne,
 * ajoute ce mot-clé. N'écrit QUE des mots-clés, jamais les régions, et n'en retire aucun :
 * dc:subject (Nom), lr:hierarchicalSubject (Racine|Nom), digiKam:TagsList (Racine/Nom), et s'ils
 * existent déjà MicrosoftPhoto:LastKeywordXMP, mediapro:CatalogSets ; IPTC 2:25.
 * Écriture par segments JPEG (lib/jpeg_segments.php, pixels intacts), sans exiftool.
 *
 * Cas d'origine : photos étiquetées par Picasa, qui écrit les cadres MWG mais pas les mots-clés.
 * Modèle : tagmanager_xmp_modify() de tag_manager/lib/xmp_writer.php (copie autonome, volontairement
 * non partagée, réduite à l'ajout). Requiert lib/editor_xmp_ex.php et lib/jpeg_segments.php.
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('FACETAGWRITE_KW_NS_DC', 'http://purl.org/dc/elements/1.1/');
define('FACETAGWRITE_KW_NS_LR', 'http://ns.adobe.com/lightroom/1.0/');
define('FACETAGWRITE_KW_NS_DK', 'http://www.digikam.org/ns/1.0/');
define('FACETAGWRITE_KW_NS_MS', 'http://ns.microsoft.com/photo/1.0/');
define('FACETAGWRITE_KW_NS_MS2', 'http://ns.microsoft.com/photo/1.0'); // forme écrite par ExifTool
define('FACETAGWRITE_KW_NS_MP', 'http://ns.iview-multimedia.com/mediapro/1.0/');
define('FACETAGWRITE_KW_XMP_MAX', 65533 - 29); // segment APP1 moins l'identifiant XMP
define('FACETAGWRITE_KW_IPTC_MAX', 64);       // longueur maximale d'un mot-clé IPTC (octets)

/**
 * Champs de mots-clés.
 *   kind   : leaf (un niveau), hier (chemin « | »), tags (chemin « / »)
 *   create : true = créé s'il manque ; false = complété seulement s'il existe
 */
function facetagwrite_kw_fields()
{
  return array(
    'leaf'  => array('p' => 'dc',             'ns' => array(FACETAGWRITE_KW_NS_DC),                         'name' => 'subject',             'kind' => 'leaf', 'box' => 'Bag', 'create' => true),
    'hier'  => array('p' => 'lr',             'ns' => array(FACETAGWRITE_KW_NS_LR),                         'name' => 'hierarchicalSubject', 'kind' => 'hier', 'box' => 'Bag', 'create' => true),
    'tags'  => array('p' => 'digiKam',        'ns' => array(FACETAGWRITE_KW_NS_DK),                         'name' => 'TagsList',            'kind' => 'tags', 'box' => 'Seq', 'create' => true),
    'ms'    => array('p' => 'MicrosoftPhoto', 'ns' => array(FACETAGWRITE_KW_NS_MS, FACETAGWRITE_KW_NS_MS2), 'name' => 'LastKeywordXMP',      'kind' => 'tags', 'box' => 'Bag', 'create' => false),
    'cat'   => array('p' => 'mediapro',       'ns' => array(FACETAGWRITE_KW_NS_MP),                         'name' => 'CatalogSets',         'kind' => 'hier', 'box' => 'Bag', 'create' => false),
    // 2.5.6 : digiKam:CatalogSets retiré de la liste (champ non standard, plus écrit,
    // retiré du fichier par le writer de la modale).
  );
}

/** Premier élément du document qui correspond au champ. */
function facetagwrite_kw_find_node(DOMDocument $doc, array $f)
{
  foreach ($f['ns'] as $ns)
  {
    $l = $doc->getElementsByTagNameNS($ns, $f['name']);
    if ($l->length)
    {
      return $l->item(0);
    }
  }
  return null;
}

/** Conteneur rdf:Bag / Seq / Alt d'un champ, ou null. */
function facetagwrite_kw_box(DOMElement $field)
{
  foreach ($field->childNodes as $c)
  {
    if ($c instanceof DOMElement && $c->namespaceURI === FACETAG_EDITOR_NS_RDF
      && in_array($c->localName, array('Bag', 'Seq', 'Alt'), true))
    {
      return $c;
    }
  }
  return null;
}

/** Valeurs (rdf:li) d'un champ liste. */
function facetagwrite_kw_read_list(DOMElement $field)
{
  $out = array();
  $box = facetagwrite_kw_box($field);
  if ($box)
  {
    foreach ($box->childNodes as $li)
    {
      if ($li instanceof DOMElement && $li->localName === 'li')
      {
        $out[] = $li->textContent;
      }
    }
  }
  return $out;
}

/** Dernier niveau d'une entrée (Nom), selon le type de champ. */
function facetagwrite_kw_leaf($entry, $kind)
{
  $sep = ($kind === 'hier') ? '|' : (($kind === 'tags') ? '/' : null);
  if ($sep !== null)
  {
    $parts = explode($sep, $entry);
    $entry = end($parts);
  }
  return trim($entry);
}

/** Entrée à écrire pour un nom, selon le type de champ ($root = niveaux de la racine). */
function facetagwrite_kw_entry($name, array $root, $kind)
{
  if ($kind === 'leaf' || empty($root))
  {
    return $name;
  }
  return implode($kind === 'hier' ? '|' : '/', array_merge($root, array($name)));
}

// ---------------------------------------------------------------------------------------------
// IPTC (IIM) : liste ordonnée d'enregistrements, reconstruite telle quelle avec les ajouts
// ---------------------------------------------------------------------------------------------

/** @return array|null liste de array(record, dataset, valeur) ; null si le bloc est illisible */
function facetagwrite_kw_iptc_parse($bin)
{
  $out = array();
  $pos = 0;
  $len = strlen($bin);
  while ($pos < $len)
  {
    if (ord($bin[$pos]) !== 0x1C)
    {
      if (trim(substr($bin, $pos), "\0") === '')
      {
        break; // bourrage final
      }
      return null;
    }
    if ($pos + 5 > $len)
    {
      return null;
    }
    $size = (ord($bin[$pos + 3]) << 8) | ord($bin[$pos + 4]);
    if ($size & 0x8000 || $pos + 5 + $size > $len)
    {
      return null; // longueur étendue (jamais utilisée pour ces champs) ou bloc tronqué
    }
    $out[] = array(ord($bin[$pos + 1]), ord($bin[$pos + 2]), substr($bin, $pos + 5, $size));
    $pos += 5 + $size;
  }
  return $out;
}

function facetagwrite_kw_iptc_build(array $records)
{
  $bin = '';
  foreach ($records as $r)
  {
    $bin .= chr(0x1C) . chr($r[0]) . chr($r[1]) . pack('n', strlen($r[2])) . $r[2];
  }
  return $bin;
}

/** Mots-clés IPTC (2:25) d'une liste d'enregistrements. */
function facetagwrite_kw_iptc_keywords(array $records)
{
  $out = array();
  foreach ($records as $r)
  {
    if ($r[0] === 2 && $r[1] === 25)
    {
      $out[] = trim($r[2]);
    }
  }
  return $out;
}

/**
 * Faux si les textes IPTC sont dans un autre jeu de caractères que UTF-8 sans le déclarer
 * (Latin-1...) : y ajouter un mot-clé UTF-8 et déclarer UTF-8 les corromprait.
 */
function facetagwrite_kw_iptc_writable(array $records)
{
  $has_charset = false;
  $all_utf8 = true;
  foreach ($records as $r)
  {
    if ($r[0] === 1 && $r[1] === 90)
    {
      $has_charset = true;
    }
    if ($r[0] === 2 && !preg_match('//u', $r[2]))
    {
      $all_utf8 = false;
    }
  }
  return $has_charset || $all_utf8;
}

/**
 * Ajoute des mots-clés IPTC (2:25) sans toucher aux autres enregistrements.
 * @return array changed (bool), bin (string|null), warnings
 */
function facetagwrite_kw_iptc_add($bin, array $names)
{
  $res = array('changed' => false, 'bin' => null, 'warnings' => array());
  $records = ($bin === false || $bin === '') ? array() : facetagwrite_kw_iptc_parse($bin);
  if ($records === null)
  {
    $res['warnings'][] = 'iptc_illisible';
    return $res;
  }

  if (!facetagwrite_kw_iptc_writable($records))
  {
    $res['warnings'][] = 'iptc_jeu_caracteres';
    return $res;
  }
  $has_charset = false;
  foreach ($records as $r)
  {
    if ($r[0] === 1 && $r[1] === 90)
    {
      $has_charset = true;
    }
  }

  $existing = facetagwrite_kw_iptc_keywords($records);
  $add = array();
  foreach ($names as $n)
  {
    if (in_array($n, $existing, true) || in_array($n, $add, true))
    {
      continue;
    }
    if (strlen($n) > FACETAGWRITE_KW_IPTC_MAX)
    {
      $res['warnings'][] = 'iptc_trop_long:' . $n;
      continue;
    }
    $add[] = $n;
  }
  if (empty($add))
  {
    return $res;
  }

  if (empty($records))
  {
    $records = array(array(1, 0, pack('n', 4)), array(2, 0, pack('n', 4)));
  }
  if (!$has_charset)
  {
    // Après 1:00 s'il existe, sinon en tête (le jeu UTF-8 vaut pour les textes déjà présents,
    // vérifiés valides ci-dessus)
    $at = 0;
    foreach ($records as $i => $r)
    {
      if ($r[0] === 1 && $r[1] === 0)
      {
        $at = $i + 1;
      }
    }
    array_splice($records, $at, 0, array(array(1, 90, "\x1B%G")));
  }

  // Position : après le dernier 2:25, sinon avant le premier dataset de l'enregistrement 2 de
  // numéro > 25, sinon après le dernier enregistrement 2, sinon à la fin
  $at = null;
  foreach ($records as $i => $r)
  {
    if ($r[0] === 2 && $r[1] === 25)
    {
      $at = $i + 1;
    }
  }
  if ($at === null)
  {
    foreach ($records as $i => $r)
    {
      if ($r[0] === 2 && $r[1] > 25)
      {
        $at = $i;
        break;
      }
    }
  }
  if ($at === null)
  {
    $at = count($records);
    foreach ($records as $i => $r)
    {
      if ($r[0] > 2)
      {
        $at = $i;
        break;
      }
    }
  }
  $new = array();
  foreach ($add as $n)
  {
    $new[] = array(2, 25, $n);
  }
  array_splice($records, $at, 0, $new);

  $res['changed'] = true;
  $res['bin'] = facetagwrite_kw_iptc_build($records);
  return $res;
}

// ---------------------------------------------------------------------------------------------
// Analyse et écriture
// ---------------------------------------------------------------------------------------------

/**
 * Analyse le contenu d'un JPEG : noms des cadres de visage et, pour chacun, les champs où le
 * mot-clé manque.
 *
 * @param string $d contenu du fichier
 * @return array error (string|null), names (noms des cadres), missing (nom => champs),
 *               root (niveaux de racine trouvés dans le fichier, ou null)
 */
function facetagwrite_kw_analyse_data($d)
{
  $res = array('error' => null, 'names' => array(), 'missing' => array(), 'root' => null);
  $meta = facetagwrite_jpeg_read_meta($d);
  if ($meta === null)
  {
    $res['error'] = 'jpeg_illisible';
    return $res;
  }
  $xmp = $meta['xmp'];
  if ($xmp === false || $xmp === '')
  {
    return $res; // pas de XMP : pas de cadre
  }
  foreach (facetag_editor_faces_from_xmp(array($xmp)) as $face)
  {
    if (!in_array($face['name'], $res['names'], true))
    {
      $res['names'][] = $face['name'];
    }
  }
  if (empty($res['names']))
  {
    return $res;
  }
  $doc = facetag_editor_xmp_dom($xmp);
  if ($doc === null)
  {
    $res['error'] = 'xmp_illisible';
    return $res;
  }

  $lists = array();
  foreach (facetagwrite_kw_fields() as $k => $f)
  {
    $node = facetagwrite_kw_find_node($doc, $f);
    if ($node === null && !$f['create'])
    {
      continue; // champ absent, jamais créé
    }
    $lists[$k] = array('kind' => $f['kind'], 'leaves' => array());
    if ($node !== null)
    {
      foreach (facetagwrite_kw_read_list($node) as $v)
      {
        $lists[$k]['leaves'][] = facetagwrite_kw_leaf($v, $f['kind']);
        // Racine déjà employée dans le fichier pour un visage : Racine|Nom
        if ($k === 'hier' && $res['root'] === null)
        {
          $levels = array_map('trim', explode('|', $v));
          if (count($levels) > 1 && in_array(end($levels), $res['names'], true))
          {
            $res['root'] = array_slice($levels, 0, -1);
          }
        }
      }
    }
  }

  $iptc_records = ($meta['iptc'] === false) ? array() : facetagwrite_kw_iptc_parse($meta['iptc']);
  if ($iptc_records !== null && facetagwrite_kw_iptc_writable($iptc_records))
  {
    $lists['iptc'] = array('kind' => 'leaf', 'leaves' => facetagwrite_kw_iptc_keywords($iptc_records));
  }

  foreach ($res['names'] as $n)
  {
    foreach ($lists as $k => $l)
    {
      if (!in_array($n, $l['leaves'], true))
      {
        if ($k === 'iptc' && strlen($n) > FACETAGWRITE_KW_IPTC_MAX)
        {
          continue; // ne peut pas y être écrit : ne compte pas comme manquant
        }
        $res['missing'][$n][] = $k;
      }
    }
  }
  if (!empty($res['missing']) && strpos($xmp, 'HasExtendedXMP') !== false)
  {
    // Le réécrire supprimerait l'extension (voir facetagwrite_jpeg_write_meta()) : on n'y touche pas
    $res['error'] = 'xmp_etendu';
  }
  return $res;
}

/**
 * Ajoute au paquet XMP les mots-clés des noms donnés là où ils manquent.
 *
 * @param string|false $packet paquet existant
 * @param string[] $names
 * @param string[] $root niveaux de la racine (ex. array('Personnes'))
 * @return array changed, packet, fields (champs modifiés), error
 */
function facetagwrite_kw_xmp_add($packet, array $names, array $root)
{
  $res = array('changed' => false, 'packet' => null, 'fields' => array(), 'error' => null);
  $rdf_ns = FACETAG_EDITOR_NS_RDF;

  if ($packet === false || $packet === '')
  {
    $doc = facetag_editor_xmp_dom('<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="' . $rdf_ns
      . '"><rdf:Description rdf:about=""/></rdf:RDF></x:xmpmeta>');
  }
  else
  {
    $doc = facetag_editor_xmp_dom($packet);
  }
  if ($doc === null)
  {
    $res['error'] = 'xmp_illisible'; // on n'écrase jamais un XMP que l'on ne sait pas lire
    return $res;
  }
  $doc->preserveWhiteSpace = true;
  $rdfs = $doc->getElementsByTagNameNS($rdf_ns, 'RDF');
  if ($rdfs->length === 0)
  {
    $res['error'] = 'xmp_sans_rdf';
    return $res;
  }
  $rdf = $rdfs->item(0);
  $descs = $rdf->getElementsByTagNameNS($rdf_ns, 'Description');
  if ($descs->length === 0)
  {
    $desc0 = $doc->createElementNS($rdf_ns, 'rdf:Description');
    $desc0->setAttributeNS($rdf_ns, 'rdf:about', '');
    $rdf->appendChild($desc0);
  }
  else
  {
    $desc0 = $descs->item(0);
  }
  $rdfp = $desc0->lookupPrefix($rdf_ns);
  if (!$rdfp)
  {
    $rdfp = 'rdf';
  }

  foreach (facetagwrite_kw_fields() as $k => $f)
  {
    $node = facetagwrite_kw_find_node($doc, $f);
    if ($node === null && !$f['create'])
    {
      continue;
    }
    $leaves = array();
    if ($node !== null)
    {
      foreach (facetagwrite_kw_read_list($node) as $v)
      {
        $leaves[] = facetagwrite_kw_leaf($v, $f['kind']);
      }
    }
    $add = array();
    foreach ($names as $n)
    {
      if (!in_array($n, $leaves, true))
      {
        $add[] = facetagwrite_kw_entry($n, $root, $f['kind']);
        $leaves[] = $n;
      }
    }
    if (empty($add))
    {
      continue;
    }

    if ($node === null)
    {
      $prefix = $desc0->lookupPrefix($f['ns'][0]);
      if (!$prefix)
      {
        $prefix = $f['p'];
        $desc0->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $f['ns'][0]);
      }
      // createElement (sans NS) : le préfixe est résolu par l'ancêtre, pas de xmlns redondant
      $node = $doc->createElement($prefix . ':' . $f['name']);
      $desc0->appendChild($node);
    }
    $box = facetagwrite_kw_box($node);
    if ($box === null)
    {
      if (trim($node->textContent) !== '')
      {
        continue; // valeur simple sans conteneur : forme inattendue, on n'y touche pas
      }
      $box = $doc->createElement($rdfp . ':' . $f['box']);
      $node->appendChild($box);
    }
    foreach ($add as $v)
    {
      $li = $doc->createElement($rdfp . ':li');
      $li->appendChild($doc->createTextNode($v));
      $box->appendChild($li);
    }
    $res['changed'] = true;
    $res['fields'][] = $f['p'] . ':' . $f['name'];
  }

  if (!$res['changed'])
  {
    return $res;
  }
  $doc->encoding = 'UTF-8';
  $xml = $doc->saveXML($doc->documentElement);
  $out = "<?xpacket begin=\"\xEF\xBB\xBF\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n" . $xml . "\n<?xpacket end=\"w\"?>";
  if (strlen($out) > FACETAGWRITE_KW_XMP_MAX)
  {
    $res['changed'] = false;
    $res['error'] = 'xmp_trop_gros'; // on ne sacrifie jamais d'autres métadonnées pour faire tenir le paquet
    return $res;
  }
  $res['packet'] = $out;
  return $res;
}

/**
 * Écrit dans un JPEG les mots-clés manquants des noms donnés (parmi les cadres du fichier).
 *
 * @param string $path chemin réel
 * @param string[] $names noms autorisés (cas sûrs)
 * @param string[] $default_root racine à employer si le fichier n'en a pas déjà une
 * @param bool $make_original créer path.original s'il n'existe pas
 * @return array changed, written (noms écrits), fields, warnings, error
 */
function facetagwrite_kw_write_file($path, array $names, array $default_root, $make_original)
{
  $res = array('changed' => false, 'written' => array(), 'fields' => array(), 'warnings' => array(), 'error' => null);
  $d = @file_get_contents($path);
  if ($d === false)
  {
    $res['error'] = 'fichier_illisible';
    return $res;
  }
  $a = facetagwrite_kw_analyse_data($d);
  if ($a['error'] !== null)
  {
    $res['error'] = $a['error'];
    return $res;
  }
  $todo = array();
  foreach ($names as $n)
  {
    if (isset($a['missing'][$n]))
    {
      $todo[] = $n;
    }
  }
  if (empty($todo))
  {
    return $res; // déjà fait (lot repris) ou plus de cadre de ce nom
  }
  $root = ($a['root'] !== null) ? $a['root'] : $default_root;
  $meta = facetagwrite_jpeg_read_meta($d);

  $x = facetagwrite_kw_xmp_add($meta['xmp'], $todo, $root);
  if ($x['error'] !== null)
  {
    $res['error'] = $x['error'];
    return $res;
  }
  $i = facetagwrite_kw_iptc_add($meta['iptc'], $todo);
  $res['warnings'] = $i['warnings'];
  if (!$x['changed'] && !$i['changed'])
  {
    return $res;
  }
  $res['fields'] = $x['fields'];
  if ($i['changed'])
  {
    $res['fields'][] = 'IPTC:Keywords';
  }

  try
  {
    $out = facetagwrite_jpeg_write_meta($d, $x['changed'] ? $x['packet'] : $meta['xmp'], $i['changed'] ? $i['bin'] : null);
  }
  catch (Exception $e)
  {
    $res['error'] = $e->getMessage();
    return $res;
  }
  if ($out === $d)
  {
    return $res;
  }

  if ($make_original && !file_exists($path . '.original'))
  {
    if (!@copy($path, $path . '.original'))
    {
      $res['error'] = 'original_impossible';
      return $res;
    }
    @chmod($path . '.original', 0444);
  }

  @chmod($path, 0666);
  $tmp = $path . '.facetag_tmp';
  if (@file_put_contents($tmp, $out) !== strlen($out))
  {
    @unlink($tmp);
    $res['error'] = 'ecriture_impossible';
    return $res;
  }
  if (!@rename($tmp, $path))
  {
    // Windows / partages réseau : rename() ne remplace pas toujours un fichier existant
    if (@file_put_contents($path, $out) !== strlen($out))
    {
      @unlink($tmp);
      $res['error'] = 'ecriture_impossible';
      return $res;
    }
    @unlink($tmp);
  }
  @chmod($path, 0644);
  clearstatcache(true, $path);
  $res['changed'] = true;
  $res['written'] = $todo;
  return $res;
}
?>
