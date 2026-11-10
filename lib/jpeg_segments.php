<?php
/**
 * Réécriture des métadonnées d'un JPEG SANS ré-encoder l'image (v2.4b.2).
 *
 * Avant : Imagick::writeImage() (ou `convert` externe) ré-encodait toute l'image à chaque
 * enregistrement de visages (perte de qualité cumulative, tables de Huffman changées).
 * Ici, seuls le segment APP1 XMP standard et la ressource IPTC (0x0404) du segment APP13
 * « Photoshop 3.0 » sont remplacés ; tous les autres segments et les données de l'image (à partir
 * du marqueur SOS) sont recopiés octet pour octet, ce qui est vérifié avant de renvoyer le résultat.
 * Code repris (et adapté) du moteur de tag_manager, volontairement autonome.
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

define('FACETAGWRITE_XMP_ID', "http://ns.adobe.com/xap/1.0/\0");
define('FACETAGWRITE_XMP_EXT_ID', "http://ns.adobe.com/xmp/extension/\0");
define('FACETAGWRITE_PS_ID', "Photoshop 3.0\0");

/**
 * Découpe un JPEG en segments jusqu'au marqueur SOS.
 *
 * @return array|null array('segs' => [...], 'sos' => position du marqueur SOS) ; null si ce n'est
 *   pas un JPEG exploitable. Chaque segment : m (marqueur), s (début, 0xFF inclus), e (fin),
 *   p (début de la charge utile).
 */
function facetagwrite_jpeg_parse($d)
{
  $n = strlen($d);
  if ($n < 4 || $d[0] !== "\xFF" || $d[1] !== "\xD8")
  {
    return null;
  }
  $segs = array();
  $o = 2;
  while ($o < $n)
  {
    if ($d[$o] !== "\xFF")
    {
      return null;
    }
    while ($o < $n && $d[$o] === "\xFF")
    {
      $o++; // octets de bourrage
    }
    if ($o >= $n)
    {
      return null;
    }
    $m = ord($d[$o]);
    $start = $o - 1;
    $o++;
    if ($m === 0xDA)
    {
      return array('segs' => $segs, 'sos' => $start);
    }
    if ($m === 0x01 || ($m >= 0xD0 && $m <= 0xD8))
    {
      $segs[] = array('m' => $m, 's' => $start, 'e' => $o, 'p' => $o);
      continue;
    }
    if ($o + 2 > $n)
    {
      return null;
    }
    $len = (ord($d[$o]) << 8) | ord($d[$o + 1]);
    if ($len < 2 || $o + $len > $n)
    {
      return null;
    }
    $segs[] = array('m' => $m, 's' => $start, 'e' => $o + $len, 'p' => $o + 2);
    $o += $len;
  }
  return null;
}

function facetagwrite_jpeg_payload($d, $seg)
{
  return substr($d, $seg['p'], $seg['e'] - $seg['p']);
}

function facetagwrite_jpeg_segment($marker, $payload)
{
  return "\xFF" . chr($marker) . pack('n', 2 + strlen($payload)) . $payload;
}

/** Index du premier segment APP<marqueur> dont la charge utile commence par $prefix, ou -1. */
function facetagwrite_jpeg_find($d, $segs, $marker, $prefix)
{
  $l = strlen($prefix);
  foreach ($segs as $i => $s)
  {
    if ($s['m'] === $marker && substr($d, $s['p'], $l) === $prefix)
    {
      return $i;
    }
  }
  return -1;
}

/** Largeur et hauteur lues dans le segment SOFn (dimensions stockées, sans orientation). */
function facetagwrite_jpeg_size($d, $segs)
{
  foreach ($segs as $s)
  {
    $m = $s['m'];
    if ($m >= 0xC0 && $m <= 0xCF && $m !== 0xC4 && $m !== 0xC8 && $m !== 0xCC && $s['e'] - $s['p'] >= 5)
    {
      $h = (ord($d[$s['p'] + 1]) << 8) | ord($d[$s['p'] + 2]);
      $w = (ord($d[$s['p'] + 3]) << 8) | ord($d[$s['p'] + 4]);
      return array($w, $h);
    }
  }
  return array(0, 0);
}

/** Ressources 8BIM d'un segment APP13 « Photoshop 3.0 », ou null si illisible. */
function facetagwrite_ps_parse($payload)
{
  $l = strlen(FACETAGWRITE_PS_ID);
  if (substr($payload, 0, $l) !== FACETAGWRITE_PS_ID)
  {
    return null;
  }
  $o = $l;
  $n = strlen($payload);
  $res = array();
  while ($o + 12 <= $n && substr($payload, $o, 4) === '8BIM')
  {
    $u = unpack('nid', substr($payload, $o + 4, 2));
    $namelen = 1 + ord($payload[$o + 6]);
    if ($namelen % 2)
    {
      $namelen++;
    }
    $p = $o + 6 + $namelen;
    if ($p + 4 > $n)
    {
      return null;
    }
    $sz = unpack('Nsize', substr($payload, $p, 4));
    $size = $sz['size'];
    $p += 4;
    if ($p + $size > $n)
    {
      return null;
    }
    $res[] = array('id' => $u['id'], 'name' => substr($payload, $o + 6, $namelen), 'data' => substr($payload, $p, $size));
    $o = $p + $size + ($size % 2);
  }
  return array('res' => $res, 'tail' => substr($payload, min($o, $n)));
}

function facetagwrite_ps_build($ps)
{
  $out = FACETAGWRITE_PS_ID;
  foreach ($ps['res'] as $r)
  {
    $out .= '8BIM' . pack('n', $r['id']) . $r['name'] . pack('N', strlen($r['data'])) . $r['data'];
    if (strlen($r['data']) % 2)
    {
      $out .= "\0";
    }
  }
  return $out . $ps['tail'];
}

/**
 * Lit le XMP standard et le bloc IPTC (ressource 0x0404) d'un JPEG.
 *
 * @return array|null array('xmp' => string|false, 'iptc' => string|false, 'width', 'height') ;
 *   null si ce n'est pas un JPEG exploitable
 */
function facetagwrite_jpeg_read_meta($d)
{
  $j = facetagwrite_jpeg_parse($d);
  if ($j === null)
  {
    return null;
  }
  $out = array('xmp' => false, 'iptc' => false);
  list($out['width'], $out['height']) = facetagwrite_jpeg_size($d, $j['segs']);

  $i = facetagwrite_jpeg_find($d, $j['segs'], 0xE1, FACETAGWRITE_XMP_ID);
  if ($i >= 0)
  {
    $out['xmp'] = substr(facetagwrite_jpeg_payload($d, $j['segs'][$i]), strlen(FACETAGWRITE_XMP_ID));
  }
  $i = facetagwrite_jpeg_find($d, $j['segs'], 0xED, FACETAGWRITE_PS_ID);
  if ($i >= 0)
  {
    $ps = facetagwrite_ps_parse(facetagwrite_jpeg_payload($d, $j['segs'][$i]));
    if ($ps !== null)
    {
      foreach ($ps['res'] as $r)
      {
        if ($r['id'] === 0x0404)
        {
          $out['iptc'] = $r['data'];
          break;
        }
      }
    }
  }
  return $out;
}

/**
 * Remplace le XMP standard et (si $iptc !== null) le bloc IPTC d'un JPEG, sans toucher à l'image.
 * Les segments « Extended XMP » sont retirés (même règle qu'avant, voir
 * FaceTagMetadataWriterSimple::stripOrphanedExtendedXmp()).
 *
 * @param string $d Contenu du fichier
 * @param string $xmp Paquet XMP complet
 * @param string|null $iptc Nouveau bloc IPTC (null = segment APP13 inchangé)
 * @return string Nouveau contenu du fichier
 * @throws Exception si le fichier n'est pas exploitable ou si une limite est dépassée
 */
function facetagwrite_jpeg_write_meta($d, $xmp, $iptc)
{
  $j = facetagwrite_jpeg_parse($d);
  if ($j === null)
  {
    throw new Exception('JPEG structure not supported');
  }
  $segs = $j['segs'];

  $xmp_payload = FACETAGWRITE_XMP_ID . $xmp;
  if (strlen($xmp_payload) > 65533)
  {
    throw new Exception('XMP packet too large (' . strlen($xmp) . ' bytes)');
  }
  $xmp_seg = facetagwrite_jpeg_segment(0xE1, $xmp_payload);

  $iptc_seg = null;
  $ps_index = facetagwrite_jpeg_find($d, $segs, 0xED, FACETAGWRITE_PS_ID);
  if ($iptc !== null)
  {
    $ps = ($ps_index >= 0) ? facetagwrite_ps_parse(facetagwrite_jpeg_payload($d, $segs[$ps_index])) : null;
    if ($ps_index >= 0 && $ps === null)
    {
      throw new Exception('Photoshop APP13 segment not readable');
    }
    if ($ps === null)
    {
      $ps = array('res' => array(), 'tail' => '');
    }
    $found = false;
    foreach ($ps['res'] as $k => $r)
    {
      if ($r['id'] === 0x0404)
      {
        $ps['res'][$k]['data'] = $iptc;
        $found = true;
      }
    }
    if (!$found)
    {
      $ps['res'][] = array('id' => 0x0404, 'name' => "\0\0", 'data' => $iptc);
    }
    // Condensé IPTC (0x0425 = MD5 des données IPTC) : recalculé s'il existe, sinon les outils
    // (exiftool, Lightroom...) considéreraient l'IPTC comme modifié par un tiers.
    foreach ($ps['res'] as $k => $r)
    {
      if ($r['id'] === 0x0425)
      {
        $ps['res'][$k]['data'] = md5($iptc, true);
      }
    }
    $ps_payload = facetagwrite_ps_build($ps);
    if (strlen($ps_payload) > 65533)
    {
      throw new Exception('IPTC block too large');
    }
    $iptc_seg = facetagwrite_jpeg_segment(0xED, $ps_payload);
  }

  // Point d'insertion d'un segment absent : après les segments APPn et COM de tête (donc après
  // JFIF et Exif), avant les tables (DQT, SOF, DHT...).
  $insert_at = 0;
  foreach ($segs as $s)
  {
    if (!(($s['m'] >= 0xE0 && $s['m'] <= 0xEF) || $s['m'] === 0xFE))
    {
      break;
    }
    $insert_at++;
  }

  $xmp_index = facetagwrite_jpeg_find($d, $segs, 0xE1, FACETAGWRITE_XMP_ID);
  $out = substr($d, 0, 2);
  $count = count($segs);
  for ($i = 0; $i <= $count; $i++)
  {
    if ($i === $insert_at)
    {
      if ($xmp_index < 0)
      {
        $out .= $xmp_seg;
      }
      if ($iptc_seg !== null && $ps_index < 0)
      {
        $out .= $iptc_seg;
      }
    }
    if ($i === $count)
    {
      break;
    }
    $s = $segs[$i];
    if ($i === $xmp_index)
    {
      $out .= $xmp_seg;
    }
    elseif ($i === $ps_index && $iptc_seg !== null)
    {
      $out .= $iptc_seg;
    }
    elseif ($s['m'] === 0xE1 && substr($d, $s['p'], strlen(FACETAGWRITE_XMP_EXT_ID)) === FACETAGWRITE_XMP_EXT_ID)
    {
      // Extended XMP : omis
    }
    else
    {
      $out .= substr($d, $s['s'], $s['e'] - $s['s']);
    }
  }
  $out .= substr($d, $j['sos']);

  // Vérification : structure relisible et image (à partir de SOS) identique octet pour octet.
  $check = facetagwrite_jpeg_parse($out);
  if ($check === null || substr($out, $check['sos']) !== substr($d, $j['sos']))
  {
    throw new Exception('JPEG rewrite verification failed');
  }
  return $out;
}
