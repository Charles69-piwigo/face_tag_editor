# face_tag_editor

[Français](#français) · [English](#english)

---

## Français

Plugin [Piwigo](https://piwigo.org) pour **dessiner et nommer les visages sur les photos**, directement depuis la page photo. Les cadres de visage et les noms sont écrits **dans les métadonnées du fichier** (XMP et IPTC), aux formats utilisés par digiKam, Lightroom et Windows, et pas seulement dans la base de Piwigo.

Page du plugin : <https://piwigo.org/ext/extension_view.php?eid=1053>

### Fonctionnalités

- Bouton **« Taguer »** sur la page photo, qui ouvre une fenêtre d'édition :
  - dessiner un cadre autour d'un visage (cliquer-glisser) et lui donner un nom ;
  - déplacer, redimensionner ou supprimer un cadre ; double-clic pour renommer ;
  - liste latérale des visages tagués ;
  - liste **« Noms sans cadre »** : personnes nommées dans le fichier sans cadre de visage (nom sans rectangle, mot-clé `Personnes|Nom` sans cadre), avec un bouton pour les retirer du fichier ;
  - **description enrichie** de la photo (éditeur Trumbowyg : gras, couleurs, polices, listes, liens) ; une description HTML complexe existante s'affiche en lecture seule pour ne pas l'abîmer ;
  - **télécharger la photo en JPG** avec les cadres incrustés ;
  - **restaurer l'original** (copie `.original`).
- Si un nom saisi existe déjà comme tag ordinaire (par exemple le nom d'un animal), il est refusé : la fenêtre indique le nombre de photos concernées et invite à modifier d'abord ce tag ordinaire.
- Les visages déjà présents dans le fichier (créés par digiKam, Lightroom, Windows, Picasa…) sont lus région par région et affichés. À l'enregistrement, seuls les cadres modifiés, ajoutés ou supprimés sont réécrits : les visages sans nom et les autres régions (BarCode, Focus…) restent intacts.
- Chaque nom devient aussi un **tag Piwigo** (hiérarchie `Personnes|Nom` dans le fichier). Les autres tags du fichier, sans rapport avec les visages, sont conservés. Un nom que Piwigo confondrait avec un tag ordinaire (même nom d'URL, par exemple « @ canon » et « CANON ») est refusé.
- Après l'enregistrement, les tags Piwigo et les miniatures sont mis à jour.

### Formats de métadonnées

| Format | Utilisé par |
|---|---|
| MWG-RS (Metadata Working Group – Region Schema) | digiKam, Lightroom |
| MPReg (Microsoft Photo Region) | Windows Photo Gallery |
| `dc:subject`, `lr:hierarchicalSubject`, `digiKam:TagsList` | mots-clés |
| `MicrosoftPhoto:LastKeywordXMP`, `mediapro:CatalogSets`, `acdsee:categories`, Exif `XPKeywords` | mots-clés, mis à jour seulement s'ils existent déjà |
| IPTC 2:25 (mots-clés), 2:120 (description) | la plupart des logiciels |

Le champ non standard `digiKam:CatalogSets`, écrit par les versions antérieures à 2.6, est retiré à l'enregistrement suivant de la photo.

**JPEG : aucune recompression.** Seuls les segments XMP et IPTC sont remplacés ; les pixels et les autres segments sont recopiés octet pour octet (vérifié avant l'écriture, fichier temporaire puis renommage). Les autres formats passent par Imagick.

### Prérequis

- Piwigo avec PHP et l'extension **`imagick`**, ou à défaut les commandes ImageMagick (`convert`, `identify`).
- PHP doit pouvoir **écrire** dans les fichiers photo et dans leur répertoire. Sur un NAS Synology, donner lecture et écriture au groupe `http` sur `./_data`, `./upload` et `./galleries`.
- Photos dans `./upload` ou `./galleries` (liens symboliques acceptés).
- Le navigateur charge Fabric.js depuis cdnjs.cloudflare.com (accès Internet nécessaire).
- Pour **afficher** les visages aux visiteurs, installer aussi le plugin [face_tag](https://fr.piwigo.org/ext/index.php?eid=1051).

### Installation

1. Copier le dossier `face_tag_editor` dans `plugins/` (le nom du dossier doit rester `face_tag_editor`).
2. Activer le plugin dans **Administration > Plugins**.
3. Régler les options dans **Plugins > face_tag_editor** (voir ci-dessous).

**Sauvegardez vos photos avant un premier essai réel** : le plugin réécrit les fichiers.

### Administration

Trois onglets :

- **Aide** : résumé du fonctionnement.
- **Gestion des .original** : active ou désactive la copie de sécurité. Quand elle est active (par défaut), le premier enregistrement d'une photo `xyz.jpg` crée `xyz.jpg.original` à côté. L'onglet liste ces copies et permet de les supprimer sélectivement.
- **Gestion des droits** : voir ci-dessous.

Un quatrième onglet, **Mots-clés des visages**, est masqué par défaut. Il s'affiche pour les webmestres en ajoutant `$conf['face_tag_editor_keywords_tool'] = true;` avec le plugin LocalFiles Editor. C'est un outil de réparation : pour chaque cadre de visage nommé dont le fichier n'a pas le mot-clé de la personne (cas des cadres remis par Picasa, ou des photos écrites par d'anciennes versions de digiKam), il ajoute ce mot-clé au fichier et le tag Piwigo à la photo.

- Il n'écrit que des mots-clés, jamais les cadres, et n'en retire aucun.
- L'analyse, en lecture seule, porte sur toute la galerie ou sur les albums cochés. Elle peut être interrompue et reprise, et elle donne un aperçu chiffré par album avant toute écriture.
- Seuls les noms connus comme visages sont écrits. Les cas douteux sont listés à part, sans écriture : nom sans tag Piwigo (avec les orthographes proches), homonyme d'un tag ordinaire.

### Droits

Les webmestres et administrateurs ont toujours accès. Pour d'autres utilisateurs, créer un groupe Piwigo nommé exactement **`FaceTag`** et y ajouter les membres, puis choisir un mode :

| Mode | Les membres du groupe peuvent taguer |
|---|---|
| Tous les albums | toutes les photos qu'ils peuvent voir |
| Sélectif par utilisateur | les photos de 5 albums au plus par utilisateur, sous-albums inclus |

Les droits sont vérifiés **côté serveur** à chaque appel (lecture, enregistrement, restauration), et pas seulement par l'affichage du bouton. Un utilisateur ne peut agir que sur une photo qu'il voit dans Piwigo (albums interdits, niveau de confidentialité). L'enregistrement et la restauration exigent en plus le jeton de session de Piwigo.

### Langues

Français, anglais, italien.

### Plugins liés

- [face_tag](https://fr.piwigo.org/ext/index.php?eid=1051) : affichage des visages ; partage avec face_tag_editor le registre des tags de visage (table `facetag_person_tags`), qui sert à masquer ces tags du nuage de tags.
- `tag_manager` : s'il est installé, face_tag_editor lit son index des tags (racines de personnes, tags ordinaires rangés dans l'arbre) pour refuser un nom de visage qui est un tag ordinaire et pour reconnaître les personnes. Il ne modifie jamais ses tables.
- `geo_tag_editor` : peut être actif en même temps (éditeur Trumbowyg partagé sans conflit).
- `private_derivative_protection` : compatible (l'image est servie par son script de contrôle des droits).

---

## English

[Piwigo](https://piwigo.org) plugin to **draw and name faces on photos**, straight from the photo page. Face regions and names are written **into the file's metadata** (XMP and IPTC), in the formats used by digiKam, Lightroom and Windows, not only into Piwigo's database.

Plugin page: <https://piwigo.org/ext/extension_view.php?eid=1053>

### Features

- A **"Tag"** button on the photo page opens an editing window:
  - draw a box around a face (click and drag) and give it a name;
  - move, resize or delete a box; double-click to rename;
  - side list of tagged faces;
  - **"Names without a face frame"** list: people named in the file without a face box (a name without a rectangle, a `Personnes|Name` keyword without a box), with a button to remove them from the file;
  - **rich-text description** of the photo (Trumbowyg editor: bold, colours, fonts, lists, links); an existing description with complex HTML is shown read-only so it cannot be damaged;
  - **download the photo as JPG** with the boxes drawn on it;
  - **restore the original** (`.original` copy).
- If a name already exists as an ordinary tag (an animal's name, for example), it is refused: the window shows how many photos carry it and asks you to change that ordinary tag first.
- Faces already present in the file (created by digiKam, Lightroom, Windows, Picasa…) are read region by region and displayed. On saving, only the boxes that were changed, added or deleted are rewritten: unnamed faces and other regions (BarCode, Focus…) are left untouched.
- Each name also becomes a **Piwigo tag** (`Personnes|Name` hierarchy in the file). The file's other tags, unrelated to faces, are kept. A name that Piwigo would confuse with an ordinary tag (same URL name, e.g. "@ canon" and "CANON") is refused.
- After saving, Piwigo tags and thumbnails are updated.

### Metadata formats

| Format | Used by |
|---|---|
| MWG-RS (Metadata Working Group – Region Schema) | digiKam, Lightroom |
| MPReg (Microsoft Photo Region) | Windows Photo Gallery |
| `dc:subject`, `lr:hierarchicalSubject`, `digiKam:TagsList` | keywords |
| `MicrosoftPhoto:LastKeywordXMP`, `mediapro:CatalogSets`, `acdsee:categories`, Exif `XPKeywords` | keywords, updated only if already present |
| IPTC 2:25 (keywords), 2:120 (caption) | most software |

The non-standard `digiKam:CatalogSets` field, written by versions before 2.6, is removed the next time the photo is saved.

**JPEG: no recompression.** Only the XMP and IPTC segments are replaced; pixels and all other segments are copied byte for byte (checked before writing, temporary file then rename). Other formats go through Imagick.

### Requirements

- Piwigo with PHP and the **`imagick`** extension, or else the ImageMagick commands (`convert`, `identify`).
- PHP must be able to **write** to the photo files and their directory. On a Synology NAS, give the `http` group read and write access to `./_data`, `./upload` and `./galleries`.
- Photos in `./upload` or `./galleries` (symbolic links supported).
- The browser loads Fabric.js from cdnjs.cloudflare.com (Internet access required).
- To **show** faces to visitors, also install the [face_tag](https://fr.piwigo.org/ext/index.php?eid=1051) plugin.

### Installation

1. Copy the `face_tag_editor` folder into `plugins/` (the folder name must stay `face_tag_editor`).
2. Activate the plugin in **Administration > Plugins**.
3. Adjust the options in **Plugins > face_tag_editor** (see below).

**Back up your photos before a first real test**: the plugin rewrites the files.

### Administration

Three tabs:

- **Help**: how the plugin works.
- **Manage .original files**: turns the backup copy on or off. When on (the default), the first save of a photo `xyz.jpg` creates `xyz.jpg.original` next to it. The tab lists these copies and lets you delete them selectively.
- **Manage rights**: see below.

A fourth tab, **Face keywords**, is hidden by default. It is shown to webmasters after adding `$conf['face_tag_editor_keywords_tool'] = true;` with the LocalFiles Editor plugin. It is a repair tool: for every named face box whose file lacks the person's keyword (boxes restored with Picasa, or photos written by old digiKam versions), it adds that keyword to the file and the Piwigo tag to the photo.

- It only writes keywords, never boxes, and never removes any.
- The analysis is read-only and covers the whole gallery or the ticked albums. It can be stopped and resumed, and it shows a per-album preview with counts before anything is written.
- Only names known as faces are written. Doubtful cases are listed separately and not written: a name without a Piwigo tag (with similar spellings), a namesake of an ordinary tag.

### Rights

Webmasters and administrators always have access. For other users, create a Piwigo group named exactly **`FaceTag`**, add the members, then choose a mode:

| Mode | Group members can tag |
|---|---|
| All albums | every photo they can see |
| Selective per user | photos in at most 5 albums per user, sub-albums included |

Rights are checked **on the server** for every call (read, save, restore), not just by hiding the button. A user can only act on a photo they can see in Piwigo (forbidden albums, privacy level). Saving and restoring also require Piwigo's session token.

### Languages

French, English, Italian.

### Related plugins

- [face_tag](https://fr.piwigo.org/ext/index.php?eid=1051): displays faces; shares with face_tag_editor the face-tag registry (`facetag_person_tags` table), used to hide those tags from the tag cloud.
- `tag_manager`: if installed, face_tag_editor reads its tag index (person roots, ordinary tags filed in the tree) to refuse a face name that is an ordinary tag and to recognise people. It never changes its tables.
- `geo_tag_editor`: can be active at the same time (shared Trumbowyg editor, no conflict).
- `private_derivative_protection`: compatible (the image is served through its rights-checking script).
