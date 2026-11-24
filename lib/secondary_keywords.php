<?php
/**
 * Champs de mots-clés secondaires (2.5.5) : tenus d'accord avec les visages à chaque enregistrement.
 *
 * L'éditeur écrit dc:subject, lr:hierarchicalSubject, digiKam:TagsList et l'IPTC. D'autres champs,
 * écrits par digiKam et d'autres logiciels, portent aussi les personnes et restaient en retard
 * (personne ajoutée absente, personne retirée toujours là) :
 *   - Exif IFD0:XPKeywords (« Mots-clés » de l'Explorateur Windows) : noms seuls, « ; » ;
 *   - MicrosoftPhoto:LastKeywordXMP : chemins « / » ;
 *   - mediapro:CatalogSets : chemins « | » ;
 *   - acdsee:categories : arbre XML dans un texte, vu comme des chemins « / ».
 * Règle : un champ absent n'est jamais créé. Référence = les chemins de personnes (racine de
 * personnes|Nom) de lr:hierarchicalSubject tel que l'éditeur vient de l'écrire. Dans un champ à
 * chemins, une entrée sous une racine de personnes dont le nom n'y est plus est retirée, une
 * personne manquante est ajoutée (même chemin). Dans XPKeywords, un nom de personne (connu par les
 * chemins de personnes, les cadres d'avant l'enregistrement ou les noms retirés) est retiré s'il
 * n'est plus dans dc:subject ; une personne manquante est ajoutée. Les autres entrées ne bougent pas.
 *
 * Code repris de tag_manager (lib/exif_keywords.php, acdsee de lib/xmp_writer.php), copie autonome.
 * Requiert lib/editor_xmp_ex.php, lib/jpeg_segments.php, lib/keywords_tool.php, lib/unframed_names.php.
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('FACETAGWRITE_EXIF_ID', "Exif\0\0");
define('FACETAGWRITE_TAG_XPKEYWORDS', 0x9C9E);
define('FACETAGWRITE_NS_ACD', 'http://ns.acdsee.com/iptc/1.0/');

// ---------------------------------------------------------------------------------------------
// Exif XPKeywords (UTF-16LE, « ; », nul final)
// ---------------------------------------------------------------------------------------------

function facetagwrite_code_to_utf8($c)
{
  if ($c < 0x80)
  {
    return chr($c);
  }
  if ($c < 0x800)
  {
    return chr(0xC0 | ($c >> 6)) . chr(0x80 | ($c & 0x3F));
  }
  if ($c < 0x10000)
  {
    return chr(0xE0 | ($c >> 12)) . chr(0x80 | (($c >> 6) & 0x3F)) . chr(0x80 | ($c & 0x3F));
  }
  return chr(0xF0 | ($c >> 18)) . chr(0x80 | (($c >> 12) & 0x3F)) . chr(0x80 | (($c >> 6) & 0x3F)) . chr(0x80 | ($c & 0x3F));
}

function facetagwrite_utf16le_to_utf8($b)
{
  if (strlen($b) % 2)
  {
    $b = substr($b, 0, -1);
  }
  $u = ($b === '') ? array() : array_values(unpack('v*', $b));
  $out = '';
  $n = count($u);
  for ($i = 0; $i < $n; $i++)
  {
    $c = $u[$i];
    if ($c >= 0xD800 && $c <= 0xDBFF && $i + 1 < $n && $u[$i + 1] >= 0xDC00 && $u[$i + 1] <= 0xDFFF)
    {
      $c = 0x10000 + (($c - 0xD800) << 10) + ($u[$i + 1] - 0xDC00);
      $i++;
    }
    $out .= facetagwrite_code_to_utf8($c);
  }
  return $out;
}

/** @return string|null UTF-16LE ; null si $s n'est pas de l'UTF-8 valide */
function facetagwrite_utf8_to_utf16le($s)
{
  if (preg_match('//u', $s) !== 1)
  {
    return null;
  }
  $out = '';
  foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch)
  {
    $b = array_values(unpack('C*', $ch));
    switch (count($b))
    {
      case 1: $c = $b[0]; break;
      case 2: $c = (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F); break;
      case 3: $c = (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F); break;
      default: $c = (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F);
    }
    if ($c >= 0x10000)
    {
      $c -= 0x10000;
      $out .= pack('v', 0xD800 + ($c >> 10)) . pack('v', 0xDC00 + ($c & 0x3FF));
    }
    else
    {
      $out .= pack('v', $c);
    }
  }
  return $out;
}

/**
 * Entrée XPKeywords de l'IFD0.
 * @return array|null|false false : structure non gérée ; null : champ absent ;
 *         sinon entry, count, data, data_off (null si dans l'entrée), little
 */
function facetagwrite_exif_find($payload)
{
  $h = strlen(FACETAGWRITE_EXIF_ID);
  $n = strlen($payload);
  if ($n < $h + 8 || substr($payload, 0, $h) !== FACETAGWRITE_EXIF_ID)
  {
    return false;
  }
  $bo = substr($payload, $h, 2);
  if ($bo !== 'II' && $bo !== 'MM')
  {
    return false;
  }
  $little = ($bo === 'II');
  $u16 = function ($o) use ($payload, $little) { $x = unpack($little ? 'v' : 'n', substr($payload, $o, 2)); return $x[1]; };
  $u32 = function ($o) use ($payload, $little) { $x = unpack($little ? 'V' : 'N', substr($payload, $o, 4)); return $x[1]; };

  if ($u16($h + 2) !== 42)
  {
    return false;
  }
  $ifd = $h + $u32($h + 4);
  if ($ifd + 2 > $n)
  {
    return false;
  }
  $cnt = $u16($ifd);
  if ($ifd + 2 + 12 * $cnt > $n)
  {
    return false;
  }
  for ($i = 0; $i < $cnt; $i++)
  {
    $e = $ifd + 2 + 12 * $i;
    if ($u16($e) !== FACETAGWRITE_TAG_XPKEYWORDS)
    {
      continue;
    }
    $type = $u16($e + 2);
    $count = $u32($e + 4);
    if ($type !== 1 && $type !== 7)
    {
      return false;
    }
    if ($count <= 4)
    {
      return array('entry' => $e, 'count' => $count, 'data' => substr($payload, $e + 8, $count), 'data_off' => null, 'little' => $little);
    }
    $off = $h + $u32($e + 8);
    if ($off + $count > $n)
    {
      return false;
    }
    return array('entry' => $e, 'count' => $count, 'data' => substr($payload, $off, $count), 'data_off' => $off, 'little' => $little);
  }
  return null;
}

function facetagwrite_xpkeywords_decode($data)
{
  $s = rtrim(facetagwrite_utf16le_to_utf8($data), "\0");
  if ($s === '')
  {
    return array();
  }
  return array_values(array_filter(array_map('trim', explode(';', $s)), function ($x) { return $x !== ''; }));
}

/**
 * Remplace la valeur de XPKeywords. Si elle tient dans l'ancien emplacement elle l'y remplace,
 * sinon elle est ajoutée en fin de bloc TIFF et l'entrée est repointée : aucun autre octet ne
 * bouge (vignette IFD1, MakerNote… intactes).
 * @return string|null nouvelle charge utile, null si impossible (avertissement ajouté)
 */
function facetagwrite_exif_set_keywords($payload, array $f, array $list, array &$warnings)
{
  foreach ($list as $k)
  {
    if (strpos($k, ';') !== false)
    {
      $warnings[] = 'exif_point_virgule';
      return null;
    }
  }
  $bin = facetagwrite_utf8_to_utf16le(implode(';', $list));
  if ($bin === null)
  {
    $warnings[] = 'exif_encodage';
    return null;
  }
  $bin .= "\0\0";
  $len = strlen($bin);
  $h = strlen(FACETAGWRITE_EXIF_ID);
  $pack32 = $f['little'] ? 'V' : 'N';

  $out = $payload;
  if ($len <= 4)
  {
    $out = substr_replace($out, str_pad($bin, 4, "\0"), $f['entry'] + 8, 4);
    if ($f['data_off'] !== null)
    {
      $out = substr_replace($out, str_repeat("\0", $f['count']), $f['data_off'], $f['count']);
    }
  }
  elseif ($f['data_off'] !== null && $len <= $f['count'])
  {
    $out = substr_replace($out, str_pad($bin, $f['count'], "\0"), $f['data_off'], $f['count']);
  }
  else
  {
    if ($f['data_off'] !== null)
    {
      $out = substr_replace($out, str_repeat("\0", $f['count']), $f['data_off'], $f['count']);
    }
    if ((strlen($out) - $h) % 2)
    {
      $out .= "\0";
    }
    $new_off = strlen($out) - $h;
    $out .= $bin;
    $out = substr_replace($out, pack($pack32, $new_off), $f['entry'] + 8, 4);
  }
  $out = substr_replace($out, pack($pack32, $len), $f['entry'] + 4, 4);

  if (strlen($out) > 65533)
  {
    $warnings[] = 'exif_trop_gros';
    return null;
  }
  return $out;
}

/** Remplace la charge utile du segment APP1 Exif ; image (à partir de SOS) vérifiée identique. */
function facetagwrite_jpeg_replace_exif($d, $payload)
{
  $j = facetagwrite_jpeg_parse($d);
  if ($j === null)
  {
    throw new Exception('JPEG structure not supported');
  }
  $i = facetagwrite_jpeg_find($d, $j['segs'], 0xE1, FACETAGWRITE_EXIF_ID);
  if ($i < 0)
  {
    throw new Exception('Exif segment not found');
  }
  $s = $j['segs'][$i];
  $out = substr($d, 0, $s['s']) . facetagwrite_jpeg_segment(0xE1, $payload) . substr($d, $s['e']);
  $check = facetagwrite_jpeg_parse($out);
  if ($check === null || substr($out, $check['sos']) !== substr($d, $j['sos']))
  {
    throw new Exception('JPEG rewrite verification failed');
  }
  return $out;
}

// ---------------------------------------------------------------------------------------------
// acdsee:categories : <Categories><Category Assigned="0">Personnes<Category Assigned="1">Nom
// </Category></Category></Categories> ; Assigned="1" = porté, "0" = simple ancêtre.
// ---------------------------------------------------------------------------------------------

/** @return array|null chemins « A/B » portés ; null si le texte est illisible */
function facetagwrite_acdsee_decode($text)
{
  $doc = new DOMDocument();
  $prev = libxml_use_internal_errors(true);
  $ok = $doc->loadXML($text, LIBXML_NONET);
  libxml_clear_errors();
  libxml_use_internal_errors($prev);
  if (!$ok || $doc->documentElement->nodeName !== 'Categories')
  {
    return null;
  }
  $out = array();
  $walk = function (DOMElement $el, array $chain) use (&$walk, &$out)
  {
    foreach ($el->childNodes as $c)
    {
      if ($c->nodeType !== XML_ELEMENT_NODE || $c->nodeName !== 'Category')
      {
        continue;
      }
      $label = '';
      foreach ($c->childNodes as $t)
      {
        if ($t->nodeType === XML_TEXT_NODE || $t->nodeType === XML_CDATA_SECTION_NODE)
        {
          $label .= $t->nodeValue;
        }
      }
      $chain2 = array_merge($chain, array(trim($label)));
      if ($c->getAttribute('Assigned') === '1')
      {
        $out[] = implode('/', $chain2);
      }
      $walk($c, $chain2);
    }
  };
  $walk($doc->documentElement, array());
  return $out;
}

function facetagwrite_acdsee_encode(array $paths)
{
  $tree = array();
  foreach ($paths as $p)
  {
    $segs = facetagwrite_unframed_levels($p, 'tags');
    $level = &$tree;
    foreach ($segs as $i => $s)
    {
      if (!isset($level[$s]))
      {
        $level[$s] = array('a' => false, 'c' => array());
      }
      if ($i === count($segs) - 1)
      {
        $level[$s]['a'] = true;
      }
      $level = &$level[$s]['c'];
    }
    unset($level);
  }
  $render = function (array $nodes) use (&$render)
  {
    $s = '';
    foreach ($nodes as $name => $n)
    {
      $s .= '<Category Assigned="' . ($n['a'] ? '1' : '0') . '">'
        . htmlspecialchars((string)$name, ENT_XML1 | ENT_NOQUOTES, 'UTF-8')
        . $render($n['c']) . '</Category>';
    }
    return $s;
  };
  return '<Categories>' . $render($tree) . '</Categories>';
}

/** Nœud acdsee:categories (élément, ou attribut de rdf:Description), ou null. */
function facetagwrite_acdsee_node(DOMDocument $doc)
{
  foreach (array('categories', 'Categories') as $n)
  {
    $l = $doc->getElementsByTagNameNS(FACETAGWRITE_NS_ACD, $n);
    if ($l->length)
    {
      return $l->item(0);
    }
  }
  foreach ($doc->getElementsByTagNameNS(FACETAG_EDITOR_NS_RDF, 'Description') as $d)
  {
    foreach (array('categories', 'Categories') as $n)
    {
      if ($d->hasAttributeNS(FACETAGWRITE_NS_ACD, $n))
      {
        return $d->getAttributeNodeNS(FACETAGWRITE_NS_ACD, $n);
      }
    }
  }
  return null;
}

// ---------------------------------------------------------------------------------------------
// Synchronisation
// ---------------------------------------------------------------------------------------------

/**
 * Nouvelle liste d'un champ à chemins : entrées de personnes absentes de $persons retirées,
 * personnes manquantes ajoutées (chemin $persons[nom] avec le séparateur du champ).
 *
 * @param array $persons nom => niveaux du chemin (ex. array('Personnes', 'Alain'))
 */
function facetagwrite_sec_path_list(array $list, $kind, array $persons, array $roots)
{
  $out = array();
  $have = array();
  foreach ($list as $v)
  {
    $levels = facetagwrite_unframed_levels($v, $kind);
    if (count($levels) >= 2 && in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
    {
      $n = end($levels);
      if (!isset($persons[$n]))
      {
        continue; // personne retirée
      }
      $have[$n] = true;
    }
    $out[] = $v;
  }
  $sep = ($kind === 'hier') ? '|' : '/';
  foreach ($persons as $n => $levels)
  {
    if (!isset($have[$n]))
    {
      $out[] = implode($sep, $levels);
    }
  }
  return $out;
}

/**
 * Met les champs secondaires d'un contenu JPEG d'accord avec ses chemins de personnes.
 *
 * @param string $d contenu du fichier (après l'écriture de l'éditeur)
 * @param string[] $roots racines de personnes (repliées)
 * @param string[] $known_names autres noms de personnes à considérer (cadres d'avant
 *                 l'enregistrement, noms sans cadre retirés) pour XPKeywords
 * @return array changed, data, fields (champs modifiés), warnings, error
 */
function facetagwrite_sec_sync_data($d, array $roots, array $known_names)
{
  $res = array('changed' => false, 'data' => $d, 'fields' => array(), 'warnings' => array(), 'error' => null);
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
  $fields = facetagwrite_kw_fields();

  // Référence : lr:hierarchicalSubject (sans lui, on ne sait pas qui est sur la photo : rien)
  $hier = facetagwrite_kw_find_node($doc, $fields['hier']);
  if ($hier === null)
  {
    return $res;
  }
  $persons = array();
  foreach (facetagwrite_kw_read_list($hier) as $v)
  {
    $levels = facetagwrite_unframed_levels($v, 'hier');
    if (count($levels) >= 2 && in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
    {
      $persons[end($levels)] = $levels;
    }
  }
  $subjects = array();
  $dc = facetagwrite_kw_find_node($doc, $fields['leaf']);
  if ($dc !== null)
  {
    $subjects = array_map('trim', facetagwrite_kw_read_list($dc));
  }

  $known = array_keys($persons);
  foreach ($known_names as $n)
  {
    $known[] = trim((string)$n);
  }

  // 1. Champs XMP à chemins (existants seulement)
  $xmp_changed = false;
  foreach (array('ms', 'cat') as $k)
  {
    $f = $fields[$k];
    $node = facetagwrite_kw_find_node($doc, $f);
    if ($node === null)
    {
      continue;
    }
    $box = facetagwrite_kw_box($node);
    if ($box === null)
    {
      continue; // forme inattendue : on n'y touche pas
    }
    $old = facetagwrite_kw_read_list($node);
    foreach ($old as $v)
    {
      $levels = facetagwrite_unframed_levels($v, $f['kind']);
      if (count($levels) >= 2 && in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
      {
        $known[] = end($levels);
      }
    }
    $new = facetagwrite_sec_path_list($old, $f['kind'], $persons, $roots);
    if ($new === $old)
    {
      continue;
    }
    $rdfp = $box->prefix ? $box->prefix : 'rdf';
    while ($box->firstChild)
    {
      $box->removeChild($box->firstChild);
    }
    foreach ($new as $v)
    {
      $li = $doc->createElement($rdfp . ':li');
      $li->appendChild($doc->createTextNode($v));
      $box->appendChild($li);
    }
    $xmp_changed = true;
    $res['fields'][] = $f['p'] . ':' . $f['name'];
  }

  // 2. acdsee:categories (existant et lisible seulement)
  $acd = facetagwrite_acdsee_node($doc);
  if ($acd !== null)
  {
    $old = facetagwrite_acdsee_decode($acd->textContent);
    if ($old === null)
    {
      $res['warnings'][] = 'acdsee_illisible';
    }
    else
    {
      foreach ($old as $v)
      {
        $levels = facetagwrite_unframed_levels($v, 'tags');
        if (count($levels) >= 2 && in_array(facetagwrite_unframed_fold($levels[0]), $roots, true))
        {
          $known[] = end($levels);
        }
      }
      $new = facetagwrite_sec_path_list($old, 'tags', $persons, $roots);
      if ($new !== $old)
      {
        $text = facetagwrite_acdsee_encode($new);
        if ($acd->nodeType === XML_ATTRIBUTE_NODE)
        {
          $acd->value = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }
        else
        {
          while ($acd->firstChild)
          {
            $acd->removeChild($acd->firstChild);
          }
          $acd->appendChild($doc->createTextNode($text));
        }
        $xmp_changed = true;
        $res['fields'][] = 'acdsee:categories';
      }
    }
  }

  // 3. Exif XPKeywords (existant seulement)
  $exif_payload = null;
  $j = facetagwrite_jpeg_parse($d);
  $ei = ($j === null) ? -1 : facetagwrite_jpeg_find($d, $j['segs'], 0xE1, FACETAGWRITE_EXIF_ID);
  if ($ei >= 0)
  {
    $payload = facetagwrite_jpeg_payload($d, $j['segs'][$ei]);
    $f = facetagwrite_exif_find($payload);
    if ($f === false)
    {
      $res['warnings'][] = 'exif_non_gere';
    }
    elseif ($f !== null)
    {
      $old = facetagwrite_xpkeywords_decode($f['data']);
      $new = array();
      foreach ($old as $v)
      {
        if (in_array($v, $known, true) && !in_array($v, $subjects, true) && !isset($persons[$v]))
        {
          continue; // personne qui n'est plus sur la photo
        }
        $new[] = $v;
      }
      foreach (array_keys($persons) as $n)
      {
        if (!in_array($n, $new, true))
        {
          $new[] = $n;
        }
      }
      if ($new !== $old)
      {
        $p2 = facetagwrite_exif_set_keywords($payload, $f, $new, $res['warnings']);
        if ($p2 !== null)
        {
          $exif_payload = $p2;
          $res['fields'][] = 'Exif:XPKeywords';
        }
      }
    }
  }

  if (!$xmp_changed && $exif_payload === null)
  {
    return $res;
  }

  try
  {
    $out = $d;
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
      $out = facetagwrite_jpeg_write_meta($out, $packet, null);
    }
    if ($exif_payload !== null)
    {
      $out = facetagwrite_jpeg_replace_exif($out, $exif_payload);
    }
  }
  catch (Exception $e)
  {
    $res['error'] = $e->getMessage();
    return $res;
  }
  $res['data'] = $out;
  $res['changed'] = ($out !== $d);
  return $res;
}
?>
