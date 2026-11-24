<?php

// fenêtre modale
// éditeur
$lang['Éditeur de visages'] = 'Face Editor';
$lang['Instructions :'] = 'Instructions:';
$lang['Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage'] = 'Click and drag on the image to draw a rectangle around a face. Double-click on a frame to rename a face';
$lang['Visages tagués'] = 'Tagged faces';
$lang['Aucun visage tagué'] = 'No tagged faces';
$lang['Tout effacer'] = 'Clear all';
$lang['Restaurer l\'original'] = 'Restore original';
$lang['Restaurer le fichier .original (supprime tous les tags)'] = 'Restore .original file (removes all tags)';
$lang['Description...'] = 'Description...';
$lang['Description'] = 'Description';
$lang['Lecture seule : mise en forme HTML complexe détectée, non modifiable ici.'] = 'Read-only: complex HTML formatting detected, not editable here.';
$lang['Annuler'] = 'Cancel';
$lang['Enregistrer '] = 'Save';
$lang['Enregistrement...'] = 'Saving...';

// éditeur liste visages
$lang['existant'] = 'existing';
$lang['Supprimer'] = 'Delete';

// fenêtre nommer
$lang['Nommer la personne'] = 'Name the person';
$lang['Nom de la personne'] = 'Person name';
$lang['Personnes existantes :'] = 'Existing people:';
$lang['Valider'] = 'Validate';
$lang['Veuillez entrer un nom'] = 'Please enter a name';

// fenêtre renommer
$lang['Renommer la personne'] = 'Rename person';
$lang['Ancien nom :'] = 'Old name:';
$lang['Nouveau nom'] = 'New name';
$lang['Autres personnes :'] = 'Other people:';
$lang['Renommer'] = 'Rename';

// bouton taguer 
$lang['Taguer'] = 'Tag';
$lang['Taguer les visages'] = 'Tag faces';

// alertes du js
$lang['Aucun visage à effacer'] = 'No faces to clear';
$lang['✅ Fichier original restauré avec succès !'] = '✅ Original file restored successfully!';
$lang['error_no_original_file'] = '❌ No .original file found to restore.\n\nThe original file only exists if you have already saved tags.';
$lang['❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.'] = '❌ Access denied. You do not have the necessary permissions.';

$lang['✅ Visages enregistrés avec succès !'] = '✅ Faces saved successfully!';
$lang['Visages: '] = 'Faces: ';
$lang['Backup créé: Oui (.original)'] = 'Backup created: Yes (.original)';
$lang['Backup: Déjà existant'] = 'Backup: Already exists';

$lang['Voulez-vous vraiment supprimer tous les tags de visages de cette image ?'] = 'Do you really want to delete all face tags from this image?';
$lang['Êtes-vous sûr de vouloir effacer tous les rectangles ?'] = 'Are you sure you want to clear all rectangles?';
$lang['confirm_restore_original'] = '⚠️ WARNING ⚠️\n\nThis action will:\n• Restore the .original file\n• Regenerate thumbnails\n\nAre you sure you want to continue?';

// fichier jpg tagué
$lang['Télécharger JPG'] = 'Download JPG';
$lang['Télécharger l\'image avec les rectangles visibles'] = 'Download image with visible rectangles';
$lang['Aucun visage tagué à télécharger'] = 'No tagged faces to download';
$lang['Erreur lors de la génération de l\'image'] = 'Error generating image';

$lang['Gestion des .original'] = 'Manage .original files';
$lang['Gestion des droits'] = 'Manage rights';

// Gestion des .original
$lang['Configuration enregistrée'] = 'Configuration saved';
$lang['Créer un fichier de sauvegarde .original lors du premier enregistrement de tags'] = 'Create a .original backup file when saving tags for the first time';
$lang['Rechercher et gérer les fichiers .original'] = 'Search and manage .original files';
$lang['Rechercher les fichiers .original'] = 'Search for .original files';
$lang['Voir la photo actuelle'] = 'View current photo';
$lang['Filtrer par date de création (avant le) :'] = 'Filter by creation date (before):';
$lang['Filtrer par répertoire (contient) :'] = 'Filter by directory (contains):';
$lang['Appliquer les filtres'] = 'Apply filters';
$lang['Effacer les filtres'] = 'Clear filters';
$lang['Supprimer les fichiers listés'] = 'Delete listed files';
$lang['Répertoire'] = 'Directory';
$lang['Nom du fichier'] = 'Filename';
$lang['Date de création'] = 'Creation date';
$lang['Taille'] = 'Size';
$lang['Aucun fichier .original trouvé'] = 'No .original file found';
$lang['fichier(s) .original trouvé(s) - Espace disque total :'] = '.original file(s) found - Total disk space:';
$lang['créés avant le'] = 'created before';
$lang['répertoire contient'] = 'directory contains';
$lang['Veuillez sélectionner au moins un filtre (date ou répertoire)'] = 'Please select at least one filter (date or directory)';
$lang['Aucun fichier à supprimer'] = 'No files to delete';
$lang['Êtes-vous sûr de vouloir supprimer'] = 'Are you sure you want to delete';
$lang['fichier(s) .original ?'] = '.original file(s)?';
$lang['Cette action est irréversible !'] = 'This action is irreversible!';
$lang['fichier(s) supprimé(s)'] = 'file(s) deleted';
$lang['échec(s)'] = 'failure(s)';
$lang['Erreurs lors de la suppression:'] = 'Errors during deletion:';
$lang['Espace disque total :'] = 'Total disk space:';
$lang['créés avant le'] = 'created before';
$lang['répertoire contient'] = 'directory contains';
$lang['fichier(s) trouvé(s)'] = 'file(s) found';

// Gestion des droits
$lang['Gestion des droits de tagging'] = 'Manage tagging rights';
$lang['Mode de fonctionnement du groupe FaceTag'] = 'FaceTag group operating mode';
$lang['Les webmasters et administrateurs ont toujours un accès total, quel que soit le mode sélectionné.'] = 'Webmasters and administrators always have full access, regardless of the selected mode.';
$lang['Tous les albums'] = 'All albums';
$lang['Les utilisateurs du groupe FaceTag peuvent taguer dans tous les albums qu\'ils peuvent voir'] = 'Users in the FaceTag group can tag in all albums they can view';
$lang['Sélectif par utilisateur'] = 'Selective by user';
$lang['Configuration individuelle des albums autorisés pour chaque utilisateur'] = 'Individual configuration of authorized albums for each user';
$lang['Enregistrer le mode'] = 'Save mode';
$lang['Configuration des utilisateurs du groupe FaceTag'] = 'Configuration of FaceTag group users';
$lang['Aucun utilisateur dans le groupe FaceTag.'] = 'No users in the FaceTag group.';
$lang['Gérer les groupes'] = 'Manage groups';
$lang['utilisateur(s) dans le groupe FaceTag. Sélectionnez jusqu\'à 5 albums par utilisateur (les sous-albums sont automatiquement inclus).'] = 'user(s) in the FaceTag group. Select up to 5 albums per user (sub-albums are automatically included).';
$lang['Enregistrer les permissions'] = 'Save permissions';
$lang['Filtrer les albums...'] = 'Filter albums...';
$lang['-- Sélectionner un album --'] = '-- Select an album --';
$lang['Tapez pour rechercher...'] = 'Type to search...';
$lang['Aucun résultat'] = 'No results';
$lang['Maximum 5 albums par utilisateur'] = 'Maximum 5 albums per user';
$lang['Albums autorisés (sous-albums inclus) :'] = 'Authorized albums (sub-albums included):';
$lang['albums'] = 'albums';
$lang['Veuillez sélectionner un album dans la liste'] = 'Please select an album from the list';
$lang['Configuration enregistrée avec succès'] = 'Configuration saved successfully';
$lang['Mode: Tous les albums'] = 'Mode: All albums';
$lang['Les configurations utilisateurs sont conservées et seront réappliquées si vous revenez en mode sélectif.'] = 'User configurations are preserved and will be reapplied if you switch back to selective mode.';
$lang['Les configurations utilisateurs sont conservées même en mode "Tous les albums". Elles seront automatiquement réappliquées si vous revenez en mode "Sélectif".'] = 'User configurations are preserved even in "All albums" mode. They will be automatically reapplied if you switch back to "Selective" mode.';

// ==================== PAGE D'AIDE ====================
$lang['Présentation'] = 'Overview';
$lang['Le plugin face_tag_editor permet de créer et gérer des tags de visage sur les photos directement depuis Piwigo.'] = 'The face_tag_editor plugin lets you create and manage face tags on photos directly from Piwigo.';
$lang['Fonctionnalités'] = 'Features';
$lang['Avec ce plugin vous pouvez :'] = 'With this plugin you can:';
$lang['Créer ou supprimer des cadres de visage'] = 'Create or delete face frames';
$lang['Déplacer ou redimensionner un cadre de visage'] = 'Move or resize a face frame';
$lang['Renommer une personne en double-cliquant sur son cadre'] = 'Rename a person by double-clicking on their frame';
$lang['Rédiger une description enrichie de la photo'] = 'Write a rich-text description of the photo';
$lang['Télécharger la photo avec les cadres de visage incrustés'] = 'Download the photo with the face frames burned in';
$lang['Droits d\'accès'] = 'Access Rights';
$lang['Pour utiliser le plugin, il faut être webmaster, administrateur, ou un utilisateur appartenant au groupe FaceTag. Les webmasters et administrateurs ont toujours un accès total.'] = 'To use the plugin, you must be a webmaster, administrator, or a user belonging to the FaceTag group. Webmasters and administrators always have full access.';
$lang['Pour les membres du groupe FaceTag, l\'onglet "Gestion des droits" propose deux modes :'] = 'For members of the FaceTag group, the "Manage rights" tab offers two modes:';
$lang[': les utilisateurs du groupe peuvent taguer toutes les photos qu\'ils peuvent voir'] = ': group members can tag any photo they can view';
$lang[': les albums autorisés sont configurés individuellement pour chaque utilisateur (jusqu\'à 5 albums, sous-albums inclus)'] = ': authorized albums are configured individually for each user (up to 5 albums, sub-albums included)';
$lang['Un éditeur de texte enrichi (Trumbowyg) permet de rédiger ou modifier la description de la photo, dans la colonne de droite de la fenêtre de tag : mise en forme (gras, italique, souligné, couleurs, polices), listes, liens, mode plein écran.'] = 'A rich-text editor (Trumbowyg) lets you write or edit the photo description in the right-hand column of the tagging window: formatting (bold, italic, underline, colors, fonts), lists, links, fullscreen mode.';
$lang['La description est enregistrée en même temps que les tags de visage, via le même bouton "Enregistrer".'] = 'The description is saved together with the face tags, via the same "Save" button.';
$lang['Si une description existante contient une mise en forme HTML complexe, elle s\'affiche en lecture seule pour éviter de l\'altérer involontairement.'] = 'If an existing description contains complex HTML formatting, it is shown as read-only to avoid unintentionally altering it.';
$lang['Visualisation des résultats'] = 'Viewing Results';
$lang['Pour visualiser les résultats, il faut installer et activer le plugin'] = 'To view the results, you must install and activate the plugin';
$lang['Ce plugin permet de visualiser instantanément le résultat de face_tag_editor.'] = 'This plugin lets you instantly view the results of face_tag_editor.';
$lang['Formats de métadonnées'] = 'Metadata Formats';
$lang['Les tags de visage sont enregistrés dans les métadonnées des photos aux formats :'] = 'Face tags are saved in the photo metadata in the following formats:';
$lang['(Microsoft Photo Region)'] = '(Microsoft Photo Region)';
$lang['(Metadata Working Group - Region Schema)'] = '(Metadata Working Group - Region Schema)';
$lang['Ces formats sont identiques à ceux utilisés par digiKam. Les tags de visage existants créés à ces formats par d\'autres logiciels sont pris en compte par face_tag_editor.'] = 'These formats are identical to those used by digiKam. Existing face tags created in these formats by other software are recognized by face_tag_editor.';
$lang['Permissions requises'] = 'Required Permissions';
$lang['Les fichiers jpg doivent avoir des droits en écriture.'] = 'JPG files must have write permissions.';
$lang['Pour les NAS Synology, il faut donner les droits lecture et écriture au groupe'] = 'For Synology NAS devices, you must grant read and write permissions to the';
$lang['sur les répertoires concernés :'] = 'group on the relevant directories:';
$lang['Compatibilité des chemins'] = 'Path Compatibility';
$lang['L\'édition des tags fonctionne sur les photos situées dans :'] = 'Tag editing works on photos located in:';
$lang['directement'] = 'directly';
$lang['via des liens symboliques'] = 'via symbolic links';
$lang['Sauvegarde et restauration'] = 'Backup and Restore';
$lang['La première fois qu\'un enregistrement de tag visage est effectué, une copie de la photo'] = 'The first time a face tag edit is saved, a copy of the photo';
$lang['est réalisée sous le nom'] = 'is created under the name';
$lang['(que ce soit dans'] = '(whether in';
$lang['ou'] = 'or';
$lang['Dans l\'interface, un bouton "Restaurer" permet de revenir à la photo initiale.'] = 'In the interface, a "Restore" button lets you revert to the original photo.';
$lang['Cette fonctionnalité peut être activée ou désactivée. La suppression sélective des fichiers .original est possible depuis l\'onglet "Gestion des .original".'] = 'This feature can be enabled or disabled. Selective deletion of .original files is available from the "Manage .original files" tab.';
$lang['Intégration Piwigo'] = 'Piwigo Integration';
$lang['Les tags sont ajoutés à la liste des tags Piwigo, et les miniatures sont mises à jour.'] = 'Tags are added to the Piwigo tag list, and thumbnails are updated.';

// nom de visage déjà utilisé comme tag ordinaire (v2.4b.4)
$lang['confirm_face_name_is_tag'] = '"{name}" already exists as a regular tag (not a face), on {count} photo(s).';
$lang['confirm_face_name_is_tag_path'] = 'In this photo: {path}';
$lang['face_name_is_tag_blocked'] = 'Change the regular tag first.';
$lang['face_name_is_tag_nophoto'] = '"{name}" already exists as a regular tag (not a face), on no photo but filed in tag_manager: {path}.';
$lang['face_name_same_tag'] = 'For Piwigo, "{name}" and "{tag}" are the same tag.';

// outil des mots-clés manquants des visages (v2.5.2)
$lang['kwtool_tab'] = 'Face keywords';
$lang['kwtool_title'] = 'Write missing face keywords';
$lang['kwtool_intro'] = 'Repair tool. For each named face region whose file lacks the person keyword, it adds this keyword to the file (Name, Root|Name, Root/Name, IPTC) and the Piwigo tag to the photo. Only names known as people are written; the others are listed separately, without writing. It does not write regions, removes no keyword and no tag, and does not modify the image.';
$lang['kwtool_settings'] = 'Settings';
$lang['kwtool_root'] = 'People root:';
$lang['kwtool_root_help'] = 'Writes Root|Name and Root/Name. Levels separated by |, without /. If the file already files another face under a root, that root is used.';
$lang['kwtool_original'] = 'Create a .original file before the first write';
$lang['kwtool_root_invalid'] = 'Invalid root: non-empty levels separated by |, without /.';
$lang['kwtool_scan'] = 'Scan';
$lang['kwtool_album'] = 'Albums:';
$lang['kwtool_selection_none'] = 'No album selected: whole gallery.';
$lang['kwtool_selection_count'] = '{count} album(s) selected, with their sub-albums.';
$lang['kwtool_clear_selection'] = 'Unselect all';
$lang['kwtool_virtual'] = 'virtual';
$lang['kwtool_albums_help'] = 'Click an album to select it (its sub-albums are included), click again to unselect it. Several albums allowed. No selection: whole gallery. The number shows the album\'s own photos.';
$lang['kwtool_scan_button'] = 'Scan';
$lang['kwtool_stop'] = 'Stop';
$lang['kwtool_preview'] = 'Preview (safe cases)';
$lang['kwtool_preview_help'] = 'Names known as people (face registry, or tag filed under "Personnes" in tag_manager), or missing from Piwigo but already in all the file keywords (tag to create, file not modified). Tick the albums to process.';
$lang['kwtool_col_album'] = 'Album';
$lang['kwtool_col_photos'] = 'Photos';
$lang['kwtool_col_keywords'] = 'Keywords to write';
$lang['kwtool_col_tags'] = 'Piwigo tags to add or create';
$lang['kwtool_col_names'] = 'Names';
$lang['kwtool_write_button'] = 'Write ticked albums';
$lang['kwtool_doubts'] = 'Doubtful cases (nothing is written)';
$lang['kwtool_doubts_help'] = 'Check in the photo editor: correct the region name, or edit the ordinary tag first. Then scan again.';
$lang['kwtool_col_name'] = 'Region name';
$lang['kwtool_col_reason'] = 'Reason';
$lang['kwtool_col_examples'] = 'Examples';
$lang['kwtool_errors'] = 'Photos not processed';
$lang['kwtool_col_photo'] = 'Photo';
$lang['kwtool_scanning'] = 'Scanning:';
$lang['kwtool_scan_done'] = 'Scan finished:';
$lang['kwtool_stopped'] = 'Scan stopped:';
$lang['kwtool_nothing'] = 'No safe case to process.';
$lang['kwtool_confirm_write'] = 'Write the missing keywords into {count} photo(s)? The files will be modified.';
$lang['kwtool_writing'] = 'Writing:';
$lang['kwtool_write_done'] = 'Writing finished.';
$lang['kwtool_error'] = 'Error';
$lang['kwtool_saved'] = 'Settings saved';
$lang['kwtool_reason_no_tag'] = 'no tag with this name';
$lang['kwtool_reason_variant'] = 'ordinary tag that Piwigo treats as this name (case, accents, symbols)';
$lang['kwtool_reason_ordinary_tag'] = 'ordinary tag, not in the face registry (homonym?)';
$lang['kwtool_near'] = 'close to:';
$lang['kwtool_select_album'] = 'Tick at least one album.';
$lang['kwtool_written'] = 'files modified:';
$lang['kwtool_tags_added'] = 'tags added:';
$lang['kwtool_failures'] = 'failures:';
$lang['kwtool_scan_help'] = 'Read only: no file and no tag is modified. The scan can be stopped at any time and resumed, even after leaving the page.';
$lang['kwtool_resume'] = 'Resume';
$lang['kwtool_stat_scanned'] = 'photos scanned';
$lang['kwtool_stat_photos'] = 'photos to complete';
$lang['kwtool_stat_keywords'] = 'keywords to write';
$lang['kwtool_stat_tags'] = 'Piwigo tags to add or create';
$lang['kwtool_new_tag'] = 'new tag';
$lang['kwtool_stat_doubts'] = 'photos with doubtful cases';
$lang['kwtool_stat_errors'] = 'photos not processed';
$lang['kwtool_errors_help'] = 'These photos have a named face region but cannot be completed by the tool.';
$lang['kwtool_starting'] = 'Starting…';
$lang['kwtool_stop_requested'] = 'Stop requested, finishing the current batch…';
$lang['kwtool_network_retry'] = 'Network error, retrying…';
$lang['kwtool_confirm_restart'] = 'An unfinished scan exists. Replace it with a new scan?';
$lang['kwtool_last_scan'] = 'Last scan:';
$lang['kwtool_whole_gallery_label'] = 'whole gallery';
$lang['kwtool_resume_hint'] = 'Unfinished: "Resume" continues where it stopped.';
$lang['kwtool_no_scan'] = 'No scan in progress: start a scan.';
$lang['kwtool_err_fichier_introuvable'] = 'file not found';
$lang['kwtool_err_fichier_illisible'] = 'file not readable';
$lang['kwtool_err_jpeg_illisible'] = 'JPEG structure not supported';
$lang['kwtool_err_xmp_etendu'] = 'extended XMP: rewriting it would remove the extension';
$lang['kwtool_err_xmp_illisible'] = 'XMP not readable';
$lang['kwtool_err_xmp_sans_rdf'] = 'XMP without rdf:RDF block';
$lang['kwtool_err_xmp_trop_gros'] = 'XMP too large for a JPEG segment';
$lang['kwtool_err_original_impossible'] = 'cannot create the .original file';
$lang['kwtool_err_ecriture_impossible'] = 'cannot write the file (permissions?)';
$lang['kwtool_err_iptc_jeu_caracteres'] = 'IPTC not modified: character set other than UTF-8';
$lang['kwtool_err_iptc_illisible'] = 'IPTC not modified: unreadable block';
$lang['kwtool_err_iptc_trop_long'] = 'IPTC not modified: name longer than 64 bytes';

// 2.5.4 : noms sans cadre
$lang['Noms sans cadre'] = 'Names without a face frame';
$lang['unframed_help'] = 'People named in the file but with no frame on the photo. "Remove" deletes them from the file when you save; to keep a person, draw their frame with the same name.';
$lang['nom sans zone'] = 'name without area';
$lang['mot-clé de personne'] = 'person keyword';
$lang['aussi tag ordinaire (gardé)'] = 'also an ordinary tag (kept)';
$lang['cadre dessiné'] = 'frame drawn';
$lang['Retirer'] = 'Remove';
$lang['Garder'] = 'Keep';

?>
