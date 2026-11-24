<?php
/**
 * Bibliothèque partagée : Extraction XMP et détection de visages
 * Utilisée par face_tag (lecture) et face_tag_write (écriture)
 * Version: 2.0
 */

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Extraire le segment XMP d'un fichier JPEG, en recollant l'éventuel "Extended XMP"
 * (mécanisme standard quand le paquet XMP dépasse 64 Ko : un segment "principal" avec un
 * pointeur xmpNote:HasExtendedXMP, plus un ou plusieurs segments APP1 "extension" contenant
 * la suite). Sans ça, un fichier avec beaucoup de métadonnées préexistantes (ex: photos
 * Google Pixel HDR+/Motion Photo) ne renvoie que le segment principal tronqué, et les
 * visages - souvent placés plus loin dans le document - disparaissent silencieusement de
 * l'extraction alors qu'ils sont bien présents dans le fichier.
 *
 * @param string $filepath Chemin vers le fichier JPEG
 * @return string|null Le contenu XML XMP (principal + extension recollée) ou null si non trouvé
 */
function facetag_editor_segment_parts($filepath) {
    $fp = fopen($filepath, 'rb');
    if (!$fp) {
        return null;
    }
    
    // Vérifier SOI (Start Of Image)
    $soi = fread($fp, 2);
    if ($soi !== "\xFF\xD8") {
        fclose($fp);
        return null;
    }
    
    $xmp_id = "http://ns.adobe.com/xap/1.0/\0";
    $ext_id = "http://ns.adobe.com/xmp/extension/\0";
    
    $main_xmp = null;
    $extended_chunks = array(); // offset => octets du morceau
    
    // Parcourir tous les segments (pas d'arrêt au premier XMP trouvé : les segments
    // d'extension, s'ils existent, se trouvent plus loin dans le fichier)
    while (!feof($fp)) {
        $marker = fread($fp, 2);
        if ($marker === false || strlen($marker) !== 2) break;
        if ($marker[0] !== "\xFF") break;
        
        $marker_type = ord($marker[1]);
        
        // SOS ou EOI = fin
        if ($marker_type === 0xDA || $marker_type === 0xD9) break;
        
        // Marqueurs sans longueur
        if ($marker_type === 0x01 || ($marker_type >= 0xD0 && $marker_type <= 0xD7)) {
            continue;
        }
        
        // Lire la longueur du segment
        $length_bytes = fread($fp, 2);
        if (strlen($length_bytes) !== 2) break;
        $length = unpack('n', $length_bytes)[1];
        $data_length = $length - 2;
        if ($data_length <= 0) break;
        
        // Lire les données
        $data = fread($fp, $data_length);
        if (strlen($data) !== $data_length) break;
        
        if ($marker_type === 0xE1) {
            if ($main_xmp === null && substr($data, 0, strlen($xmp_id)) === $xmp_id) {
                $main_xmp = substr($data, strlen($xmp_id));
            } elseif (substr($data, 0, strlen($ext_id)) === $ext_id) {
                // Après l'identifiant : GUID (32 octets) + longueur totale (4) + offset (4).
                // L'offset est donc aux octets 36-39, pas 32-35 (qui sont la longueur totale) -
                // erreur corrigée ici : avec plusieurs segments d'extension, l'ancienne version
                // utilisait la longueur totale (identique pour tous les morceaux) comme clé,
                // donc chaque morceau écrasait le précédent au lieu de s'accumuler.
                $rest = substr($data, strlen($ext_id));
                if (strlen($rest) > 40) {
                    $offset = unpack('N', substr($rest, 36, 4))[1];
                    $extended_chunks[$offset] = substr($rest, 40);
                }
            }
        }
    }
    
    fclose($fp);
    
    if ($main_xmp === null) {
        return null;
    }
    
    $ext_xmp = null;
    if (!empty($extended_chunks)) {
        ksort($extended_chunks);
        // L'extension est un second document XMP complet, découpé en morceaux : on la
        // recolle à part (2.5.1), pour pouvoir la lire avec un vrai analyseur XML.
        $ext_xmp = implode('', $extended_chunks);
    }
    
    return array('main' => $main_xmp, 'ext' => $ext_xmp);
}

/**
 * Texte XMP du fichier : segment principal suivi de l'éventuelle extension (simple
 * concaténation, pour les recherches par regex - voir facetag_editor_segment_parts()).
 *
 * @param string $filepath Chemin vers le fichier JPEG
 * @return string|null
 */
function facetag_editor_segment($filepath) {
    $parts = facetag_editor_segment_parts($filepath);
    if ($parts === null) {
        return null;
    }
    return $parts['main'] . (string)$parts['ext'];
}

// ==================== CADRES DE VISAGE (2.5.1) ====================
// Lecture par analyseur XML, région par région : le nom et la zone d'un cadre sont pris dans
// la MÊME région. Jusqu'à 2.5, les noms et les zones étaient relevés séparément par regex
// sur tout le document puis appariés par rang : un nom sans rectangle, une région sans nom
// (visage non identifié), une région BarCode/Focus ou une zone écrite en attributs
// décalaient les noms ou faisaient disparaître des cadres - et l'enregistrement, qui
// réécrit les régions depuis cette liste, rendait l'erreur définitive.
// Ces fonctions servent aussi à l'écriture (lib/metadata_writer.php) : une région n'est
// remplacée que si elle est lue ici comme cadre de visage ; les autres sont conservées.

if (!defined('FACETAG_EDITOR_NS_RDF')) {
    define('FACETAG_EDITOR_NS_RDF', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    define('FACETAG_EDITOR_NS_MWGRS', 'http://www.metadataworkinggroup.com/schemas/regions/');
    define('FACETAG_EDITOR_NS_AREA', 'http://ns.adobe.com/xmp/sType/Area#');
    define('FACETAG_EDITOR_NS_MPRI', 'http://ns.microsoft.com/photo/1.2/t/RegionInfo#');
    define('FACETAG_EDITOR_NS_MPREG', 'http://ns.microsoft.com/photo/1.2/t/Region#');
}

/**
 * Charge un paquet XMP dans un DOMDocument (octets nuls de fin retirés ; si le texte complet
 * est refusé, nouvel essai sur le seul bloc x:xmpmeta ou rdf:RDF).
 *
 * @param string|null $xml
 * @return DOMDocument|null
 */
function facetag_editor_xmp_dom($xml) {
    if (!is_string($xml)) {
        return null;
    }
    $xml = rtrim($xml, "\0 \t\r\n");
    if ($xml === '') {
        return null;
    }
    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadXML($xml, LIBXML_NONET);
    if (!$ok && preg_match('~<x:xmpmeta\b.*</x:xmpmeta>|<rdf:RDF\b.*</rdf:RDF>~s', $xml, $m)) {
        $dom = new DOMDocument();
        $ok = $dom->loadXML($m[0], LIBXML_NONET);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $ok ? $dom : null;
}

/**
 * Éléments qui portent les propriétés d'une ressource RDF : l'élément lui-même
 * (rdf:parseType="Resource" ou propriétés en attributs) et ses rdf:Description enfants.
 *
 * @return DOMElement[]
 */
function facetag_editor_rdf_holders($node) {
    $holders = array($node);
    foreach ($node->childNodes as $c) {
        if ($c instanceof DOMElement && $c->namespaceURI === FACETAG_EDITOR_NS_RDF && $c->localName === 'Description') {
            $holders[] = $c;
        }
    }
    return $holders;
}

/**
 * Valeur d'une propriété simple d'une ressource RDF, quelle que soit sa forme : attribut
 * (ns:prop="…") ou balise (<ns:prop>…</ns:prop>), sur l'élément ou sur un rdf:Description enfant.
 *
 * @return string|null null si absente
 */
function facetag_editor_rdf_prop($node, $ns, $local) {
    if (!($node instanceof DOMElement)) {
        return null;
    }
    foreach (facetag_editor_rdf_holders($node) as $h) {
        if ($h->hasAttributeNS($ns, $local)) {
            return $h->getAttributeNS($ns, $local);
        }
        foreach ($h->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === $ns && $c->localName === $local) {
                return $c->textContent;
            }
        }
    }
    return null;
}

/**
 * Élément d'une propriété structurée (ex. mwg-rs:Area), sur l'élément ou sur un rdf:Description enfant.
 *
 * @return DOMElement|null
 */
function facetag_editor_rdf_struct($node, $ns, $local) {
    if (!($node instanceof DOMElement)) {
        return null;
    }
    foreach (facetag_editor_rdf_holders($node) as $h) {
        foreach ($h->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === $ns && $c->localName === $local) {
                return $c;
            }
        }
    }
    return null;
}

/** @return DOMXPath avec les préfixes rdf, mwgrs, mpri */
function facetag_editor_regions_xpath($dom) {
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('rdf', FACETAG_EDITOR_NS_RDF);
    $xp->registerNamespace('mwgrs', FACETAG_EDITOR_NS_MWGRS);
    $xp->registerNamespace('mpri', FACETAG_EDITOR_NS_MPRI);
    return $xp;
}

/** Régions MWG (rdf:li des mwg-rs:RegionList) du document, ou de $context. */
function facetag_editor_mwg_region_nodes($xp, $context = null) {
    $q = './/mwgrs:RegionList/rdf:Bag/rdf:li | .//mwgrs:RegionList/rdf:Seq/rdf:li';
    return iterator_to_array($context === null ? $xp->query('/' . $q) : $xp->query($q, $context));
}

/** Régions Microsoft (rdf:li des MPRI:Regions) du document, ou de $context. */
function facetag_editor_mp_region_nodes($xp, $context = null) {
    $q = './/mpri:Regions/rdf:Bag/rdf:li | .//mpri:Regions/rdf:Seq/rdf:li';
    return iterator_to_array($context === null ? $xp->query('/' . $q) : $xp->query($q, $context));
}

/**
 * Cadre de visage porté par une région MWG, ou null si ce n'en est pas un au sens de
 * l'éditeur : type autre que Face (BarCode, Focus, Pet…), pas de nom (visage non
 * identifié), zone absente, incomplète ou pas en unités normalisées.
 * Coordonnées MWG : centre de la zone, pas de conversion.
 *
 * @return array|null
 */
function facetag_editor_mwg_region_face($li) {
    $type = facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MWGRS, 'Type');
    if ($type !== null && strtolower(trim($type)) !== 'face') {
        return null;
    }
    $name = trim((string)facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MWGRS, 'Name'));
    if ($name === '') {
        return null;
    }
    $area = facetag_editor_rdf_struct($li, FACETAG_EDITOR_NS_MWGRS, 'Area');
    if ($area === null) {
        return null;
    }
    $unit = facetag_editor_rdf_prop($area, FACETAG_EDITOR_NS_AREA, 'unit');
    if ($unit !== null && strtolower(trim($unit)) !== 'normalized') {
        return null;
    }
    $v = array();
    foreach (array('x', 'y', 'w', 'h') as $k) {
        $raw = facetag_editor_rdf_prop($area, FACETAG_EDITOR_NS_AREA, $k);
        if ($raw === null || !is_numeric(trim($raw))) {
            return null;
        }
        $v[$k] = floatval(trim($raw));
    }
    if ($v['w'] <= 0 || $v['h'] <= 0) {
        return null;
    }
    return array('name' => $name, 'x' => $v['x'], 'y' => $v['y'], 'w' => $v['w'], 'h' => $v['h'], 'methode' => 'MWG-RS');
}

/**
 * Propriété d'une région Microsoft. Certains fichiers (vu sur une photo écrite par
 * digiKam / ExifTool 13.41) lient le préfixe MPReg à l'URI de MPRI (…/t/RegionInfo#) :
 * ExifTool les lit quand même, on fait de même.
 *
 * @return string|null
 */
function facetag_editor_mp_prop($li, $local) {
    $v = facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MPREG, $local);
    return ($v !== null) ? $v : facetag_editor_rdf_prop($li, FACETAG_EDITOR_NS_MPRI, $local);
}

/**
 * Cadre de visage porté par une région Microsoft (nom + rectangle « x, y, w, h »), ou null.
 * MPReg stocke le coin supérieur gauche : converti vers le centre pour l'éditeur.
 *
 * @return array|null
 */
function facetag_editor_mp_region_face($li) {
    $name = trim((string)facetag_editor_mp_prop($li, 'PersonDisplayName'));
    if ($name === '') {
        return null;
    }
    $rect = facetag_editor_mp_prop($li, 'Rectangle');
    if ($rect === null) {
        return null; // nom sans rectangle : pas un cadre
    }
    $coords = array_map('trim', explode(',', $rect));
    if (count($coords) !== 4) {
        return null;
    }
    foreach ($coords as $c) {
        if (!is_numeric($c)) {
            return null;
        }
    }
    $w = floatval($coords[2]);
    $h = floatval($coords[3]);
    if ($w <= 0 || $h <= 0) {
        return null;
    }
    return array(
        'name' => $name,
        'x' => floatval($coords[0]) + ($w / 2),
        'y' => floatval($coords[1]) + ($h / 2),
        'w' => $w,
        'h' => $h,
        'methode' => 'MPReg'
    );
}

/**
 * Cadres de visage d'un ou plusieurs paquets XMP (principal, extension).
 * Les régions MWG sont prises d'abord ; une région Microsoft n'est ajoutée que si aucune
 * région MWG de même nom ne lui correspond encore (digiKam écrit les deux formats pour le
 * même visage). Deux visages de même nom restent deux cadres.
 *
 * @param array $xmps chaînes XMP (null ignoré)
 * @return array liste de cadres (name, x, y, w, h, methode)
 */
function facetag_editor_faces_from_xmp($xmps) {
    $mwg = array();
    $mp = array();
    foreach ($xmps as $xml) {
        if (!is_string($xml) || (strpos($xml, 'regions/') === false && strpos($xml, 'Region#') === false)) {
            continue; // ni MWG ni Microsoft
        }
        $dom = facetag_editor_xmp_dom($xml);
        if ($dom === null) {
            continue;
        }
        $xp = facetag_editor_regions_xpath($dom);
        foreach (facetag_editor_mwg_region_nodes($xp) as $li) {
            $face = facetag_editor_mwg_region_face($li);
            if ($face !== null) {
                $mwg[] = $face;
            }
        }
        foreach (facetag_editor_mp_region_nodes($xp) as $li) {
            $face = facetag_editor_mp_region_face($li);
            if ($face !== null) {
                $mp[] = $face;
            }
        }
    }

    $faces = $mwg;
    $matched = array();
    foreach ($mp as $face) {
        $twin = false;
        foreach ($mwg as $i => $f) {
            if (!isset($matched[$i]) && $f['name'] === $face['name']) {
                $matched[$i] = true;
                $twin = true;
                break;
            }
        }
        if (!$twin) {
            $faces[] = $face;
        }
    }
    return $faces;
}

/**
 * Extraire toutes les données de visages d'un fichier JPEG
 *
 * @param string $filepath Chemin vers le fichier JPEG
 * @return array Tableau avec wjpg, hjpg, or (orientation), faces
 */
function facetag_editor_faces($filepath) {
    $result = array(
        'wjpg' => 0,
        'hjpg' => 0,
        'or' => 1,
        'faces' => array()
    );

    // 1. DIMENSIONS de l'image
    $size = @getimagesize($filepath);
    if ($size) {
        $result['wjpg'] = $size[0];
        $result['hjpg'] = $size[1];
    }

    // 2. ORIENTATION EXIF
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filepath);
        if ($exif && isset($exif['Orientation'])) {
            $result['or'] = intval($exif['Orientation']);
        }
    }

    // 3. CADRES, région par région (segment principal et extension lus séparément)
    $parts = facetag_editor_segment_parts($filepath);
    if ($parts !== null) {
        $result['faces'] = facetag_editor_faces_from_xmp(array($parts['main'], $parts['ext']));
    }

    return $result;
}
?>
