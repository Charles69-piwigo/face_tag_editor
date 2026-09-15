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




?>