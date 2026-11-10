<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * APPROCHE FUSION DOM (v2) : on modifie chirurgicalement le XMP existant
 * au lieu de le reconstruire integralement depuis un template.
 *
 * Pourquoi ce changement :
 * L'ancienne version ("approche template") appelait
 * $imagick->setImageProfile('xmp', $xmp_neuf) avec un $xmp_neuf ne contenant
 * QUE 6 champs (RegionInfo MP, Regions mwg-rs, dc:subject, hierarchicalSubject,
 * digiKam:TagsList, digiKam:CatalogSets). Cela ecrasait silencieusement tout
 * le reste du paquet XMP existant a chaque sauvegarde : XMP-microsoft:LastKeywordXMP,
 * XMP-acdsee:Categories/Notes, l'historique d'edition darktable, Rating,
 * ColorLabels, CaptionsDateTimeStamps, et cassait le lien GUID vers un eventuel
 * bloc "Extended XMP" (packet > 65 Ko), d'ou les warnings ExifTool
 * "Ignored non-standard extended XMP" et la perte de donnees associee.
 *
 * Cette version charge le XMP existant en DOM (comme le fait deja
 * metadata_reader.php) et ne touche qu'aux 6 noeuds lies aux visages,
 * en preservant leur type de conteneur d'origine (rdf:Seq vs rdf:Bag â
 * digiKam ecrit TagsList en Seq, pas en Bag, d'ou le warning
 * "[minor] Fixed incorrect list type" observe avec l'ancienne version)
 * et absolument tout le reste du document.
 *
 * Limite connue : sans ExifTool, on ne peut pas reconstruire un bloc
 * "Extended XMP" qui aurait ete perdu par une PRECEDENTE ecriture de
 * l'ancienne version du plugin. Mais cette version n'en cree plus de
 * nouveaux problemes : le GUID xmpNote:HasExtendedXMP (s'il existe deja
 * sur le document lu) n'est plus touche, et le contenu du segment
 * standard n'est plus tronque.
 */
class FaceTagMetadataWriterSimple
{
  private $config;
  private $template_dir;

  const NS = array(
    'x'      => 'adobe:ns:meta/',
    'rdf'    => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'MP'     => 'http://ns.microsoft.com/photo/1.2/',
    'MPRI'   => 'http://ns.microsoft.com/photo/1.2/t/RegionInfo#',
    'MPReg'  => 'http://ns.microsoft.com/photo/1.2/t/Region#',
    'mwg-rs' => 'http://www.metadataworkinggroup.com/schemas/regions/',
    'stArea' => 'http://ns.adobe.com/xmp/sType/Area#',
    'stDim'  => 'http://ns.adobe.com/xap/1.0/sType/Dimensions#',
    'dc'     => 'http://purl.org/dc/elements/1.1/',
    'lr'     => 'http://ns.adobe.com/lightroom/1.0/',
    'digiKam'=> 'http://www.digikam.org/ns/1.0/'
  );

  const SAFE_XMP_SIZE = 60000; // marge de securite sous la limite JPEG (segment APP1 = 65533 octets max)

  public function __construct()
  {
    $this->template_dir = FACETAGWRITE_PATH . 'template/';

    $config_file = FACETAGWRITE_PATH . 'config/metadata_fields.json';
    if (file_exists($config_file)) {
      $this->config = json_decode(file_get_contents($config_file), true);
    } else {
      $this->config = array(
        'write_fields' => array(
          'iptc_keywords' => true,
          'xmp_subject' => true,
          'xmp_hierarchical' => true,
          'xmp_tagslist' => true,
          'xmp_catalogsets' => true
        ),
        'category_format' => 'Personnes|{name}',
        'tags_format' => 'Personnes/{name}'
      );
    }
  }

  /**
   * Ecrire les metadonnees - APPROCHE FUSION DOM
   */
  public function writeMetadata($image_path, $faces, $merged_data = null, $description = null, $description_provided = true)
  {
    // JPEG : réécriture des seuls segments XMP / IPTC, sans ré-encoder l'image (v2.4b.2).
    // Autres formats : ancienne voie Imagick, inchangée.
    $jpeg_data = @file_get_contents($image_path);
    if ($jpeg_data !== false && substr($jpeg_data, 0, 2) === "\xFF\xD8") {
      return $this->writeMetadataJpeg($image_path, $jpeg_data, $faces, $merged_data, $description, $description_provided);
    }

    try {
      $imagick = ImagickWrapper::load($image_path);

      if ($imagick->hasError()) {
        return array('success' => false, 'error' => $imagick->getError());
      }

      $image_width = $imagick->getImageWidth();
      $image_height = $imagick->getImageHeight();

      $person_names = $this->extractPersonNames($faces);

      if ($merged_data) {
        $all_subjects = $merged_data['all_subjects'];
        $all_hierarchical = $merged_data['all_hierarchical'];
        $all_tagslist = $merged_data['all_tagslist'];
        $all_catalogsets = $merged_data['all_catalogsets'];
      } else {
        $all_subjects = $person_names;
        $all_hierarchical = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
        $all_tagslist = array_map(function($n) { return 'Personnes/' . $n; }, $person_names);
        $all_catalogsets = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
      }

      // Recuperer le XMP EXISTANT du fichier (peut etre vide/absent -> on repart d'un squelette minimal)
      $existing_xmp = false;
      try {
        $existing_xmp = $imagick->getImageProfile('xmp');
      } catch (Exception $e) {
        $existing_xmp = false;
      }

      $xmp = $this->mergeXmp($existing_xmp, $faces, $image_width, $image_height, $all_subjects, $all_hierarchical, $all_tagslist, $all_catalogsets);

      $imagick->setImageProfile('xmp', $xmp);

      if ($this->config['write_fields']['iptc_keywords']) {
        $this->writeIptcData($imagick, $all_subjects, $description, $description_provided);
      }

      $imagick->writeImage($image_path);

      $imagick->clear();
      $imagick->destroy();

      // Retirer du JPEG les segments "Extended XMP" orphelins (deja inexploitables
      // par n'importe quel lecteur standard -- ExifTool les ignore aussi). Un gros
      // segment de ce type, place avant le vrai segment XMP primaire, est ce qui
      // faisait planter la lecture chez face_tag/face_tag_editor NON PATCHES
      // (fread() sans boucle -- voir editor_xmp_ex.php). Les retirer rend le
      // fichier lisible par ces anciennes versions sans qu'elles aient besoin
      // d'etre mises a jour.
      $this->stripOrphanedExtendedXmp($image_path);

      return array('success' => true);

    } catch (Exception $e) {
      return array('success' => false, 'error' => $e->getMessage());
    }
  }

  /**
   * Écriture JPEG par segments (voir lib/jpeg_segments.php) : même fusion XMP et même IPTC que la
   * voie Imagick, mais les données de l'image ne sont jamais ré-encodées. Écrit dans un fichier
   * voisin puis renomme ; n'écrit rien si le contenu ne change pas.
   */
  private function writeMetadataJpeg($image_path, $data, $faces, $merged_data, $description, $description_provided)
  {
    try {
      $meta = facetagwrite_jpeg_read_meta($data);
      if ($meta === null) {
        return array('success' => false, 'error' => 'JPEG structure not supported');
      }

      $person_names = $this->extractPersonNames($faces);
      if ($merged_data) {
        $all_subjects = $merged_data['all_subjects'];
        $all_hierarchical = $merged_data['all_hierarchical'];
        $all_tagslist = $merged_data['all_tagslist'];
        $all_catalogsets = $merged_data['all_catalogsets'];
      } else {
        $all_subjects = $person_names;
        $all_hierarchical = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
        $all_tagslist = array_map(function($n) { return 'Personnes/' . $n; }, $person_names);
        $all_catalogsets = array_map(function($n) { return 'Personnes|' . $n; }, $person_names);
      }

      $xmp = $this->mergeXmp($meta['xmp'], $faces, $meta['width'], $meta['height'], $all_subjects, $all_hierarchical, $all_tagslist, $all_catalogsets);

      $iptc = null;
      if ($this->config['write_fields']['iptc_keywords']) {
        $iptc = $this->buildIptcData($meta['iptc'], $all_subjects, $description, $description_provided);
      }

      $out = facetagwrite_jpeg_write_meta($data, $xmp, $iptc);
      if ($out === $data) {
        return array('success' => true);
      }

      $tmp = $image_path . '.facetag_tmp';
      if (@file_put_contents($tmp, $out) !== strlen($out)) {
        @unlink($tmp);
        return array('success' => false, 'error' => 'Cannot write temporary file');
      }
      if (!@rename($tmp, $image_path)) {
        // Windows / partages réseau : rename() ne remplace pas toujours un fichier existant
        if (@file_put_contents($image_path, $out) !== strlen($out)) {
          @unlink($tmp);
          return array('success' => false, 'error' => 'Cannot write file');
        }
        @unlink($tmp);
      }
      clearstatcache(true, $image_path);
      return array('success' => true);

    } catch (Exception $e) {
      return array('success' => false, 'error' => $e->getMessage());
    }
  }

  /**
   * Fusionner les donnees de visages dans le DOM XMP existant.
   * Ne touche qu'aux 6 noeuds concernes ; tout le reste du document est preserve tel quel.
   */
  private function mergeXmp($existing_xmp, $faces, $width, $height, $subjects, $hierarchical, $tagslist, $catalogsets)
  {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = true;

    $loaded = false;
    if ($existing_xmp) {
      // DOMDocument accepte nativement les Processing Instructions xpacket (begin/end),
      // pas besoin de les retirer avant loadXML.
      $loaded = @$doc->loadXML($existing_xmp);
    }

    if (!$loaded) {
      // Pas de XMP existant, ou invalide/illisible : on repart d'un squelette minimal
      $doc->loadXML($this->emptyXmpSkeleton());
    }

    $xpath = new DOMXPath($doc);
    foreach (self::NS as $prefix => $uri) {
      $xpath->registerNamespace($prefix, $uri);
    }

    $rdfList = $xpath->query('//rdf:RDF');
    if ($rdfList->length === 0) {
      // Document sans rdf:RDF exploitable -> on repart d'un squelette minimal plutot que planter
      $doc = new DOMDocument();
      $doc->preserveWhiteSpace = true;
      $doc->loadXML($this->emptyXmpSkeleton());
      $xpath = new DOMXPath($doc);
      foreach (self::NS as $prefix => $uri) {
        $xpath->registerNamespace($prefix, $uri);
      }
      $rdfList = $xpath->query('//rdf:RDF');
    }
    $rdf = $rdfList->item(0);

    // On reutilise la premiere rdf:Description existante (evite de dupliquer les
    // declarations de namespace / rdf:about a chaque sauvegarde)
    $descList = $xpath->query('//rdf:Description');
    if ($descList->length > 0) {
      $desc = $descList->item(0);
    } else {
      $desc = $doc->createElementNS(self::NS['rdf'], 'rdf:Description');
      $desc->setAttributeNS(self::NS['rdf'], 'rdf:about', '');
      $rdf->appendChild($desc);
    }

    // S'assurer que les namespaces necessaires sont bien declares (pour rester
    // lisible par d'autres outils qui ne resolvent pas les prefixes hors contexte)
    foreach (array('MP', 'MPRI', 'MPReg', 'mwg-rs', 'stArea', 'stDim', 'dc', 'lr', 'digiKam') as $prefix) {
      if (!$desc->hasAttributeNS('http://www.w3.org/2000/xmlns/', $prefix)) {
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, self::NS[$prefix]);
      }
    }

    $this->mergeRegionInfoMP($doc, $xpath, $desc, $faces);
    $this->mergeMwgRegions($doc, $xpath, $desc, $faces, $width, $height);
    $this->mergeListField($doc, $xpath, $desc, 'dc', 'subject', 'Bag', $subjects);
    $this->mergeListField($doc, $xpath, $desc, 'lr', 'hierarchicalSubject', 'Bag', $hierarchical);
    // digiKam ecrit TagsList en Seq (liste ordonnee) : on respecte cette convention
    // par defaut, et on preserve le type existant si le champ etait deja present.
    $this->mergeListField($doc, $xpath, $desc, 'digiKam', 'TagsList', 'Seq', $tagslist);
    $this->mergeListField($doc, $xpath, $desc, 'digiKam', 'CatalogSets', 'Bag', $catalogsets);

    // IMPORTANT : face_tag_editor ET le plugin independant "face_tag" (affichage cote
    // galerie) lisent tous deux les tags de visage en scannant directement le PREMIER
    // (et unique) segment JPEG APP1 XMP, avec des regex tolerantes mais qui s'arretent
    // la ou le segment s'arrete. Si le paquet XMP fusionne depasse la limite d'un
    // segment JPEG (65533 octets), ImageMagick le tronque silencieusement au lieu de le
    // scinder correctement (Extended XMP). Pour que les tags de visage survivent meme
    // dans ce cas, on les place en TETE du document plutot qu'a la fin.
    $protectedTags = array(
      'MP:RegionInfo', 'mwg-rs:Regions', 'dc:subject',
      'lr:hierarchicalSubject', 'digiKam:TagsList', 'digiKam:CatalogSets'
    );
    $this->moveToFront($doc, $xpath, $desc, array_map(function($t) { return '//' . $t; }, $protectedTags));

    // Si, malgre tout, le paquet reste trop volumineux pour un segment JPEG, on
    // sacrifie en dernier recours les champs les PLUS VOLUMINEUX qui ne font pas
    // partie des 6 champs geres par le plugin -- quel que soit leur namespace
    // d'origine. Volontairement generique (pas de liste figee de namespaces a
    // sacrifier) : un smartphone (Pixel, Samsung...) peut embarquer ses propres
    // blocs XMP proprietaires et volumineux (Google Camera, gain map HDR Ultra HDR,
    // Container/Motion Photo...) que darktable ou ACDSee n'utilisent pas, et
    // l'inverse est vrai aussi selon le logiciel d'edition. On ne peut pas connaitre
    // a l'avance tous ces schemas : on se contente de reperer et virer ce qui pese
    // le plus lourd parmi tout ce qu'on ne gere pas nous-memes.
    $doc = $this->enforceSizeLimit($doc, $xpath, $protectedTags);

    // Nettoyage des declarations de namespace redondantes : createElementNS() en PHP/libxml
    // attache une declaration xmlns:PREFIX LOCALE a CHAQUE element cree, meme quand le
    // namespace est deja en vigueur via un ancetre (ex: $desc). Cette declaration locale est
    // stockee par libxml2 dans une liste interne separee des attributs "normaux" (nsDef, pas
    // properties) : DOMElement::$attributes ne la voit pas, un nettoyage base sur le DOM ne
    // peut donc pas la retirer (verifie en pratique : le XML produit gardait bien un
    // xmlns:MPReg="..." sur CHAQUE <MPReg:PersonDisplayName>, malgre le nettoyage DOM
    // ci-dessus qui ne trouvait rien a faire). On agit donc apres coup sur le texte serialise.
    //
    // Ce n'est pas un probleme pour un lecteur XML correct (comme ExifTool), mais ca casse
    // les lecteurs a base de regex strictes qui n'acceptent pas d'attributs dans la balise
    // ouvrante -- dont la version NON PATCHEE de face_tag, qui peut rester deployee chez des
    // utilisateurs n'ayant mis a jour que face_tag_editor.
    // A remettre juste avant saveXML() : loadXML() reinitialise $doc->encoding d'apres
    // ce qu'il detecte dans la chaine chargee (absent -> vide), donc une assignation faite
    // plus haut, avant loadXML(), est ecrasee et n'a aucun effet sur la sortie.
    $doc->encoding = 'UTF-8';
    $xml = $doc->saveXML();
    $xml = $this->stripRedundantNamespaceDeclarations($xml);

    return $xml;
  }

  /**
   * Retire, dans le texte XML deja serialise, les declarations xmlns:PREFIX="URI"
   * redondantes pour les namespaces geres par le plugin (self::NS) : ne garde que la
   * PREMIERE occurrence de chaque paire prefixe/URI (portee par rdf:Description, ou elle
   * est necessaire et suffisante), retire toutes les suivantes, identiques mot pour mot.
   * Le XML reste strictement equivalent pour un lecteur qui resout les namespaces par
   * heritage (le prefixe reste declare une fois, en ancetre de tous ses usages), juste
   * moins verbeux et sans attribut parasite sur les balises de contenu.
   */
  private function stripRedundantNamespaceDeclarations($xml)
  {
    foreach (self::NS as $prefix => $uri) {
      $needle = ' xmlns:' . $prefix . '="' . $uri . '"';
      $firstPos = strpos($xml, $needle);
      if ($firstPos === false) {
        continue;
      }
      $searchFrom = $firstPos + strlen($needle);
      while (($nextPos = strpos($xml, $needle, $searchFrom)) !== false) {
        $xml = substr($xml, 0, $nextPos) . substr($xml, $nextPos + strlen($needle));
        // $searchFrom inchange : on vient de retirer du texte APRES cette position,
        // la prochaine occurrence eventuelle sera cherchee au meme endroit.
      }
    }
    return $xml;
  }

  /**
   * Deplace en tete du document (avant tout le reste) les elements matches par les
   * XPath donnes, dans l'ordre fourni. Garantit leur survie si le paquet XMP doit
   * etre tronque a la lecture ou a l'ecriture.
   *
   * Point d'ancrage = le premier enfant de $desc qui n'est PAS l'un des noeuds a
   * deplacer (identite verifiee via isSameNode(), pas ===). Deux pieges evites en
   * cherchant l'ancre ainsi plutot que de prendre bêtement $desc->firstChild :
   * - rdf:Description vide (aucun contenu preexistant) : firstChild serait alors
   *   lui-meme l'un des noeuds a deplacer, et une ancre fixe sur lui produirait un
   *   ordre final inverse par rapport a $xpathExpressions.
   * - reedition d'une photo qui n'a QUE ces 6 champs (deja deplaces en tete lors
   *   d'un enregistrement precedent) : meme piege, meme si le document n'est pas
   *   vide au sens strict.
   */
  private function moveToFront($doc, $xpath, $desc, $xpathExpressions)
  {
    $nodes = array();
    foreach ($xpathExpressions as $expr) {
      $node = $xpath->query($expr)->item(0);
      if ($node) {
        $nodes[] = $node;
      }
    }

    $isTargetNode = function($candidate) use ($nodes) {
      foreach ($nodes as $n) {
        if ($candidate->isSameNode($n)) {
          return true;
        }
      }
      return false;
    };

    $anchor = $desc->firstChild;
    while ($anchor !== null && $isTargetNode($anchor)) {
      $anchor = $anchor->nextSibling;
    }

    foreach ($nodes as $node) {
      $desc->insertBefore($node, $anchor);
    }
  }

  /**
   * Dernier recours si le paquet XMP fusionne depasse la taille d'un segment JPEG :
   * repere tous les elements enfants directs d'une rdf:Description qui ne font PAS
   * partie des champs proteges (les 6 champs geres par le plugin), les trie du plus
   * volumineux au plus petit, et les retire un a un jusqu'a repasser sous
   * SAFE_XMP_SIZE (ou jusqu'a epuisement des candidats).
   */
  private function enforceSizeLimit($doc, $xpath, $protectedTags)
  {
    if (strlen($doc->saveXML()) <= self::SAFE_XMP_SIZE) {
      return $doc;
    }

    // Construire l'ensemble des noeuds proteges (nos 6 champs geres par le plugin)
    $protectedNodes = array();
    foreach ($protectedTags as $tag) {
      $node = $xpath->query('//' . $tag)->item(0);
      if ($node) {
        $protectedNodes[] = $node;
      }
    }

    // Candidats a la suppression : tout enfant direct d'une rdf:Description qui
    // n'est pas un champ protege. On reste au niveau "champ" (pas de fragmentation
    // a l'interieur d'un champ) pour ne jamais produire un champ tronque/invalide.
    $candidates = array();
    foreach (iterator_to_array($xpath->query('//rdf:Description')) as $description) {
      foreach (iterator_to_array($description->childNodes) as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
          continue;
        }
        if ($this->isProtectedNode($child, $protectedNodes)) {
          continue;
        }
        $candidates[] = $child;
      }
    }

    usort($candidates, function($a, $b) {
      return strlen($b->textContent) - strlen($a->textContent);
    });

    foreach ($candidates as $el) {
      if (strlen($doc->saveXML()) <= self::SAFE_XMP_SIZE) {
        break;
      }
      if ($el->parentNode) {
        error_log('face_tag_editor: paquet XMP trop volumineux, suppression du champ "' . $el->nodeName . '" (' . strlen($el->textContent) . ' octets) pour preserver les tags de visage.');
        $el->parentNode->removeChild($el);
      }
    }

    if (strlen($doc->saveXML()) > self::SAFE_XMP_SIZE) {
      error_log('face_tag_editor: paquet XMP toujours volumineux (' . strlen($doc->saveXML()) . ' octets) apres nettoyage -- risque de troncature par ImageMagick.');
    }

    return $doc;
  }

  /**
   * Comparaison fiable de noeuds DOM : "===" sur des DOMNode n'est pas garanti
   * (deux appels a DOMXPath::query peuvent retourner des wrappers PHP distincts
   * pour le meme noeud sous-jacent) ; isSameNode() est la comparaison correcte.
   */
  private function isProtectedNode($node, $protectedNodes)
  {
    foreach ($protectedNodes as $protected) {
      if ($node->isSameNode($protected)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Squelette XMP minimal utilise uniquement si le fichier n'a aucun XMP existant.
   */
  private function emptyXmpSkeleton()
  {
    return '<?xml version="1.0" encoding="UTF-8"?>' .
      '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="face_tag_editor">' .
      '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' .
      '<rdf:Description rdf:about=""/>' .
      '</rdf:RDF>' .
      '</x:xmpmeta>';
  }

  /**
   * Remplace (ou cree) MP:RegionInfo avec la liste actuelle de visages.
   * Ce noeud est entierement possede par face_tag_editor/digiKam : un remplacement
   * complet est correct ici (pas de fusion partielle a faire, $faces est deja la
   * liste complete et a jour des visages de la photo).
   */
  private function mergeRegionInfoMP($doc, $xpath, $desc, $faces)
  {
    foreach (iterator_to_array($xpath->query('//MP:RegionInfo')) as $old) {
      $old->parentNode->removeChild($old);
    }

    if (empty($faces)) {
      return; // rien a ecrire, pas de visages
    }

    $regionInfo = $doc->createElementNS(self::NS['MP'], 'MP:RegionInfo');
    $regionInfo->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');

    $regions = $doc->createElementNS(self::NS['MPRI'], 'MPRI:Regions');
    $bag = $doc->createElementNS(self::NS['rdf'], 'rdf:Bag');

    foreach ($faces as $face) {
      $topLeftX = $face['x'] - ($face['w'] / 2);
      $topLeftY = $face['y'] - ($face['h'] / 2);

      $li = $doc->createElementNS(self::NS['rdf'], 'rdf:li');
      $li->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');

      $name = $doc->createElementNS(self::NS['MPReg'], 'MPReg:PersonDisplayName', $face['name']);
      $rect = $doc->createElementNS(self::NS['MPReg'], 'MPReg:Rectangle',
        number_format($topLeftX, 6, '.', '') . ', ' .
        number_format($topLeftY, 6, '.', '') . ', ' .
        number_format($face['w'], 6, '.', '') . ', ' .
        number_format($face['h'], 6, '.', '')
      );

      $li->appendChild($name);
      $li->appendChild($rect);
      $bag->appendChild($li);
    }

    $regions->appendChild($bag);
    $regionInfo->appendChild($regions);
    $desc->appendChild($regionInfo);
  }

  /**
   * Remplace (ou cree) mwg-rs:Regions avec la liste actuelle de visages.
   * Meme logique que mergeRegionInfoMP : remplacement complet legitime.
   */
  private function mergeMwgRegions($doc, $xpath, $desc, $faces, $width, $height)
  {
    foreach (iterator_to_array($xpath->query('//mwg-rs:Regions')) as $old) {
      $old->parentNode->removeChild($old);
    }

    if (empty($faces)) {
      return;
    }

    $regionsEl = $doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:Regions');
    $regionsEl->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');

    $dims = $doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:AppliedToDimensions');
    $dims->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');
    $dims->appendChild($doc->createElementNS(self::NS['stDim'], 'stDim:w', $width));
    $dims->appendChild($doc->createElementNS(self::NS['stDim'], 'stDim:h', $height));
    $dims->appendChild($doc->createElementNS(self::NS['stDim'], 'stDim:unit', 'pixel'));
    $regionsEl->appendChild($dims);

    $regionList = $doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:RegionList');
    $bag = $doc->createElementNS(self::NS['rdf'], 'rdf:Bag');

    foreach ($faces as $face) {
      $li = $doc->createElementNS(self::NS['rdf'], 'rdf:li');
      $li->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');

      $li->appendChild($doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:Type', 'Face'));
      $li->appendChild($doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:Name', $face['name']));

      $area = $doc->createElementNS(self::NS['mwg-rs'], 'mwg-rs:Area');
      $area->setAttributeNS(self::NS['rdf'], 'rdf:parseType', 'Resource');
      $area->appendChild($doc->createElementNS(self::NS['stArea'], 'stArea:x', number_format($face['x'], 6, '.', '')));
      $area->appendChild($doc->createElementNS(self::NS['stArea'], 'stArea:y', number_format($face['y'], 6, '.', '')));
      $area->appendChild($doc->createElementNS(self::NS['stArea'], 'stArea:w', number_format($face['w'], 6, '.', '')));
      $area->appendChild($doc->createElementNS(self::NS['stArea'], 'stArea:h', number_format($face['h'], 6, '.', '')));
      $area->appendChild($doc->createElementNS(self::NS['stArea'], 'stArea:unit', 'normalized'));
      $li->appendChild($area);

      $bag->appendChild($li);
    }

    $regionList->appendChild($bag);
    $regionsEl->appendChild($regionList);
    $desc->appendChild($regionsEl);
  }

  /**
   * Remplace le contenu d'un champ liste (dc:subject, lr:hierarchicalSubject,
   * digiKam:TagsList, digiKam:CatalogSets) en preservant :
   * - le noeud lui-meme s'il existe deja ailleurs dans le document (peu importe
   *   sous quelle rdf:Description) plutot que d'en creer un doublon
   * - son type de conteneur d'origine (rdf:Seq vs rdf:Bag) si deja present ;
   *   sinon on utilise $default_container
   */
  private function mergeListField($doc, $xpath, $desc, $nsPrefix, $localName, $default_container, $values)
  {
    $tag = $nsPrefix . ':' . $localName;
    $existing = $xpath->query('//' . $tag)->item(0);

    $container_name = $default_container; // 'Seq' ou 'Bag'
    $target_parent = $desc;

    if ($existing) {
      $container_name = $default_container;
      // Detecter le conteneur existant (rdf:Seq ou rdf:Bag) pour le preserver
      foreach (array('Seq', 'Bag') as $type) {
        if ($xpath->query('rdf:' . $type, $existing)->length > 0) {
          $container_name = $type;
          break;
        }
      }
      $target_parent = $existing->parentNode;
      $existing->parentNode->removeChild($existing);
    }

    if (empty($values)) {
      return; // rien a ecrire : le champ reste absent (pas de <tag/> vide inutile)
    }

    $field = $doc->createElementNS(self::NS[$nsPrefix], $tag);
    $container = $doc->createElementNS(self::NS['rdf'], 'rdf:' . $container_name);

    foreach ($values as $val) {
      $li = $doc->createElementNS(self::NS['rdf'], 'rdf:li', $val);
      $container->appendChild($li);
    }

    $field->appendChild($container);
    $target_parent->appendChild($field);
  }

  /**
   * Retire du fichier JPEG tout segment APP1 "Extended XMP" (signature
   * "http://ns.adobe.com/xmp/extension/") laisse orphelin par un ecrivain
   * anterieur (Google Camera, digiKam/Exiv2...). Ces segments sont deja ignores
   * par tout lecteur standard (ExifTool inclus) faute de lien GUID valide ; les
   * laisser en place n'apporte rien et peut faire echouer un lecteur fragile
   * (fread() sans boucle sur un gros segment) avant qu'il n'atteigne le vrai
   * segment XMP primaire. Best-effort : en cas de structure JPEG inattendue, on
   * abandonne sans modifier le fichier plutot que de risquer de le corrompre.
   */
  private function stripOrphanedExtendedXmp($image_path)
  {
    $data = @file_get_contents($image_path);
    if ($data === false || strlen($data) < 4 || substr($data, 0, 2) !== "\xFF\xD8") {
      return;
    }

    $extensionSignature = "http://ns.adobe.com/xmp/extension/\0";
    $out = "\xFF\xD8";
    $pos = 2;
    $len = strlen($data);
    $changed = false;

    while ($pos < $len - 1) {
      if ($data[$pos] !== "\xFF") {
        // structure inattendue : on abandonne, fichier deja ecrit par Imagick reste intact
        return;
      }
      $marker_type = ord($data[$pos + 1]);

      // SOS ou EOI : fin des segments, on recopie le reste (donnees image) tel quel
      if ($marker_type === 0xDA || $marker_type === 0xD9) {
        $out .= substr($data, $pos);
        $pos = $len;
        break;
      }

      // Marqueurs sans champ de longueur (padding, RSTn)
      if ($marker_type === 0x01 || ($marker_type >= 0xD0 && $marker_type <= 0xD7)) {
        $out .= substr($data, $pos, 2);
        $pos += 2;
        continue;
      }

      if ($pos + 4 > $len) {
        $out .= substr($data, $pos);
        $pos = $len;
        break;
      }

      $length = unpack('n', substr($data, $pos + 2, 2))[1];
      $segment_total = 2 + $length; // marqueur (2 octets) + longueur+donnees ($length inclut ses propres 2 octets)

      if ($length < 2 || $pos + $segment_total > $len) {
        $out .= substr($data, $pos);
        $pos = $len;
        break;
      }

      $segment_data = substr($data, $pos + 4, $length - 2);

      if ($marker_type === 0xE1 && substr($segment_data, 0, strlen($extensionSignature)) === $extensionSignature) {
        // Segment Extended XMP orphelin : omis de la sortie
        $changed = true;
      } else {
        $out .= substr($data, $pos, $segment_total);
      }

      $pos += $segment_total;
    }

    if ($changed) {
      @file_put_contents($image_path, $out);
    }
  }

  /**
   * Extraire les noms des personnes
   */
  private function extractPersonNames($faces)
  {
    $names = array();
    foreach ($faces as $face) {
      if (!in_array($face['name'], $names)) {
        $names[] = $face['name'];
      }
    }
    return $names;
  }

  /**
   * Ecrire les donnees IPTC (Keywords + Description)
   */
  private function writeIptcData($imagick, $keywords, $description = null, $description_provided = true)
  {
    try {
      $iptc_profile = $imagick->getImageProfile('iptc');
    } catch (Exception $e) {
      $iptc_profile = false;
    }

    $imagick->setImageProfile('iptc', $this->buildIptcData($iptc_profile, $keywords, $description, $description_provided));
  }

  /**
   * Nouveau bloc IPTC binaire : mots-clés (2:25) remplacés, description (2:120) selon
   * $description_provided, le reste conservé (commun aux voies Imagick et JPEG par segments).
   */
  private function buildIptcData($iptc_profile, $keywords, $description = null, $description_provided = true)
  {
    if ($iptc_profile) {
      $iptc_data = $this->parseIptcProfile($iptc_profile);
    } else {
      $iptc_data = array();
    }

    $iptc_data['2#025'] = $keywords;

    if ($description_provided) {
      if ($description !== null && strlen($description) > 0) {
        $iptc_data['2#120'] = $description;
      } else {
        if (isset($iptc_data['2#120'])) {
          unset($iptc_data['2#120']);
        }
      }
    }

    return $this->buildIptcProfile($iptc_data);
  }

  /**
   * Parser un profil IPTC binaire
   */
  private function parseIptcProfile($binary)
  {
    $data = array();
    $pos = 0;
    $len = strlen($binary);

    while ($pos < $len) {
      if (ord($binary[$pos]) != 0x1C) {
        $pos++;
        continue;
      }

      if ($pos + 4 >= $len) break;

      $record = ord($binary[$pos + 1]);
      $tag = ord($binary[$pos + 2]);
      $size = (ord($binary[$pos + 3]) << 8) | ord($binary[$pos + 4]);

      if ($pos + 5 + $size > $len) break;

      $value = substr($binary, $pos + 5, $size);
      $key = sprintf('%d#%03d', $record, $tag);

      if ($key == '2#025') {
        if (!isset($data[$key])) {
          $data[$key] = array();
        }
        $data[$key][] = $value;
      } else {
        $data[$key] = $value;
      }

      $pos += 5 + $size;
    }

    return $data;
  }

  /**
   * Construire un profil IPTC binaire
   */
  private function buildIptcProfile($data)
  {
    $binary = '';

    if (!isset($data['1#000'])) {
      $data['1#000'] = pack('n', 4);
    }
    $val = $data['1#000'];
    $binary .= chr(0x1C) . chr(1) . chr(0) . pack('n', strlen($val)) . $val;

    if (!isset($data['1#090'])) {
      $data['1#090'] = "\x1B%G"; // UTF-8
    }
    $val = $data['1#090'];
    $binary .= chr(0x1C) . chr(1) . chr(90) . pack('n', strlen($val)) . $val;

    if (!isset($data['2#000'])) {
      $data['2#000'] = pack('n', 4);
    }
    $val = $data['2#000'];
    $binary .= chr(0x1C) . chr(2) . chr(0) . pack('n', strlen($val)) . $val;

    foreach ($data as $key => $value) {
      if ($key === '1#000' || $key === '1#090' || $key === '2#000') {
        continue;
      }

      list($record, $tag) = explode('#', $key);
      $record = intval($record);
      $tag = intval($tag);

      $values = is_array($value) ? $value : array($value);

      foreach ($values as $val) {
        $size = strlen($val);
        $binary .= chr(0x1C);
        $binary .= chr($record);
        $binary .= chr($tag);
        $binary .= pack('n', $size);
        $binary .= $val;
      }
    }

    return $binary;
  }

}
?>
