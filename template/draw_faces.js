// face_tag_write - Interface modale de dessin des visages


(function($) {
  'use strict';
  
  $(document).ready(function() {
    
    // Dans la console du navigateur
   // console.log($('link[href*="font-awesome"]').attr('href'));
    console.log('Face Tag Editor: Script chargé');
    
    var canvas = null;
    var currentRect = null;
    var faces = [];
    var imageId = null;
    var imageSrc = null;
    var saveUrl = null;
    var fabricLoaded = false;
    // Variables partagées pour stocker les XMP (comme face_tag)
    var xmpData = null;
    var existingFaces = [];
    var hasOriginal = false;
    var currentDescription = '';        // Description de l'image (IPTC Caption-Abstract)
    var isDescriptionReadonly = false;  // true si HTML complexe détecté (class="...") : édition désactivée


    // Fonction de traduction (à mettre tout en haut du fichier)
      function _(text) {
        return (typeof facetagLang !== 'undefined' && facetagLang[text]) ? facetagLang[text] : text;
      }


    // ==================== CHARGEMENT LAZY DES TRADUCTIONS ====================
let facetagLang = null;
let translationsPromise = null;

async function loadFacetagTranslations() {
  if (facetagLang) {
    return facetagLang;
  }
  
  if (!translationsPromise) {
    translationsPromise = fetch('ws.php?format=json&method=facetag.getTranslations')
      .then(response => response.json())
      .then(data => {
        if (data.stat === 'ok' && data.result) {
          facetagLang = data.result;
          return facetagLang;
        } else {
          throw new Error('Erreur chargement traductions');
        }
      })
      .catch(error => {
        console.error('Erreur chargement traductions face tag:', error);
        return null;
      });
  }
  
  return translationsPromise;
}  

    // ==================== FONCTION DE TRANSFORMATION DES COORDONNÉES (EXIF) ====================
    // Fonction pour transformer les coordonnées selon l'orientation EXIF
    // Valeurs possibles : 1-8 (voir spec EXIF)
    
    function transformCoordinates(left, top, width, height, orientation) {
      var newLeft, newTop, newWidth, newHeight;
      
      switch(orientation) {
        case 1: // Normal
          return {left: left, top: top, width: width, height: height};
          
        case 2: // Flip horizontal
          newLeft = 100 - left - width;
          return {left: newLeft, top: top, width: width, height: height};
          
        case 3: // Rotate 180
          newLeft = 100 - left - width;
          newTop = 100 - top - height;
          return {left: newLeft, top: newTop, width: width, height: height};
          
        case 4: // Flip vertical
          newTop = 100 - top - height;
          return {left: left, top: newTop, width: width, height: height};
          
        case 5: // Rotate 90 CW + Flip horizontal
          // Transformation : x' = y, y' = x
          newLeft = top;
          newTop = left;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        case 6: // Rotate 90 CW
          // Pour afficher correctement après rotation
          newLeft = 100 - top - height;
          newTop = left;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        case 7: // Rotate 90 CCW + Flip horizontal
          // Transformation : x' = y, y' = 100 - x - width
          newLeft = top;
          newTop = 100 - left - width;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        case 8: // Rotate 90 CCW (270 CW) // V1.9A
          // Transformation : x' = y, y' = 100 - x - width
          newLeft = top;
          newTop = 100 - left - width;
          newWidth = height;
          newHeight = width;
          return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
          
        default:
          return {left: left, top: top, width: width, height: height};
      }
    }

    //---------------------------------------------------------------------------

    // Fonction inverse : canvas → coordonnées originales selon orientation EXIF
  function inverseTransformCoordinates(left, top, width, height, orientation) {
  var newLeft, newTop, newWidth, newHeight;
  
  switch(orientation) {
    case 1: // Normal - pas de transformation
      return {left: left, top: top, width: width, height: height};
      
    case 2: // Flip horizontal
      newLeft = 100 - left - width;
      return {left: newLeft, top: top, width: width, height: height};
      
    case 3: // Rotate 180
      newLeft = 100 - left - width;
      newTop = 100 - top - height;
      return {left: newLeft, top: newTop, width: width, height: height};
      
    case 4: // Flip vertical
      newTop = 100 - top - height;
      return {left: left, top: newTop, width: width, height: height};
      
    case 5: // Rotate 90 CW + Flip horizontal
      newLeft = top;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    case 6: // Rotate 90 CW - INVERSE
      // Inverse: x_orig = y_ecran, y_orig = 100 - x_ecran - w_ecran
      newLeft = top;
      newTop = 100 - left - width;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    case 7: // Rotate 90 CCW + Flip horizontal
      newLeft = 100 - top - height;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
     case 8: // Rotate 90 CCW (270 CW) - INVERSE  // V1.9A
      // Inverse: x = 100 - y' - h', y = x'
      newLeft = 100 - top - height;
      newTop = left;
      newWidth = height;
      newHeight = width;
      return {left: newLeft, top: newTop, width: newWidth, height: newHeight};
      
    default:
      return {left: left, top: top, width: width, height: height};
  }
}


    // ==================== CHARGER FABRIC.JS À LA DEMANDE ====================
    function loadFabricJS(callback) {
      if (typeof fabric !== 'undefined') {
        //console.log('Fabric.js déjà chargé');
        callback();
        return;
      }
      
      //console.log('Chargement de Fabric.js...');
      
      var script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';
      script.onload = function() {
        //console.log('Fabric.js chargé avec succès');
        fabricLoaded = true;
        callback();
      };
      script.onerror = function() {
        console.error('Erreur de chargement de Fabric.js');
      };
      
      document.head.appendChild(script);
    }
    
// ==================== OUVERTURE DE LA MODAL ======================================================
$(document).on('click', '#facetag-open-editor', async function(e) {
  e.preventDefault();
  
  //*console.log('=== CLIC SUR TAGUER ===');
  
  // Désactiver le bouton pendant le chargement
  var $btn = $(this);
  var originalText = $btn.text();
  $btn.prop('disabled', true).text('Chargement...');
  
  imageId = $btn.data('image-id');
  imageSrc = $btn.data('image-src');
  saveUrl = $btn.data('save-url');
  // Lire hasOriginal depuis le bouton du DOM à CHAQUE fois
  hasOriginal = $btn.data('has-original') === 'true' || $btn.data('has-original') === true;
  currentDescription = $btn.data('description') || '';
  isDescriptionReadonly = $btn.data('description-is-readonly') === 'true' || $btn.data('description-is-readonly') === true;

  //*console.log('Image ID:', imageId, '- Has a backup original:', hasOriginal);
  //*console.log('Image ID:', imageId);
  //*console.log('Image URL:', imageSrc);
  //*console.log('Nom du fichier:', imageSrc.split('/').pop());
  //*console.log('Save URL:', saveUrl);

  try {
    // Charger les traductions d'abord
    await loadFacetagTranslations();
    
    // Puis charger Fabric.js et ouvrir la modal
    loadFabricJS(function() {
      //console.log('Fabric.js prêt, ouverture de la modale');
      $btn.prop('disabled', false).text(originalText);
      openModal();
    });
  } catch (error) {
    console.error('Erreur lors du chargement:', error);
    $btn.prop('disabled', false).text(originalText);
    alert('Erreur lors du chargement de l\'éditeur');
  }
});
    
    // ==================== CRÉER LA MODALE ============================================================
    function openModal() {
      // Supprimer les modales existantes
      $('#facetag-modal, #facetag-modal-overlay').remove();

  
      
var modalHtml = `
  <div id="facetag-modal-overlay"></div>
  
  <div id="facetag-modal">
    
    <!-- Header -->
    <div class="modal-header">
      <h3>✏️ ${_('Éditeur de visages')} - ${_('Image')} #${imageId}</h3>
      <button id="facetag-close-modal">&times;</button>
    </div>
    
    <!-- Instructions -->
    <div class="modal-instructions">
      <p><strong>${_('Instructions :')}</strong> ${_('Cliquez et faites glisser sur l\'image pour dessiner un rectangle autour d\'un visage. Double-cliquez sur un cadre pour renommer un visage')}</p>
    </div>
    
    <!-- Contenu principal -->
    <div class="modal-content">
      
      <!-- Zone image + canvas -->
      <div class="modal-canvas-area">
        <div id="facetag-canvas-wrapper">
          <canvas id="facetag-canvas"></canvas>
        </div>
      </div>
      
      <!-- Sidebar : liste des visages + description -->
      <div class="modal-sidebar">
        <h4>👤 ${_('Visages tagués')} (<span id="facetag-count">0</span>)</h4>
        <div id="facetag-faces-list">
          <p>${_('Aucun visage tagué')}</p>
        </div>

        <!-- Description de l'image -->
        <div class="description-section">
          <label for="facetag-description">${_('Description')} :</label>
          ${isDescriptionReadonly ? '<div class="facetag-description-readonly-notice">' + _('Lecture seule : mise en forme HTML complexe détectée, non modifiable ici.') + '</div>' : ''}
          <textarea id="facetag-description" rows="3" placeholder="${_('Description...')}"></textarea>
        </div>
      </div>
      
    </div>

      
    <!-- Footer : boutons -->
    <div class="modal-footer">
      
<div class="modal-footer-left">
  <button id="facetag-clear-all">🗑️ ${_('Tout effacer')}</button>
  <button id="facetag-restore-original" title="${_('Restaurer le fichier .original (supprime tous les tags)')}">⮪️ ${_('Restaurer l\'original')}</button>
  <button id="facetag-download-jpg" title="${_('Télécharger l\'image avec les rectangles visibles')}">📥 ${_('Télécharger JPG')}</button>
</div>
      
      <div class="modal-footer-right">
        <button id="facetag-cancel">${_('Annuler')}</button>
        <button id="facetag-save-xmp">💾 ${_('Enregistrer ')}</button>
      </div>

    </div>
    
  </div>
`;
      
      $('body').append(modalHtml);




  
// Charger la description existante : Trumbowyg si éditable, affichage brut sinon
if (isDescriptionReadonly) {
  $('#facetag-description').replaceWith(
    '<div id="facetag-description" class="facetag-description-readonly-view">' + currentDescription + '</div>'
  );
} else {
  $('#facetag-description').trumbowyg({
    svgPath: window.FaceTagTrumbowygSvgPath,
    lang: window.FaceTagTrumbowygLang || 'en',
    btns: [
      ['undo', 'redo'],
      ['formatting'],
      ['fontfamily'],
      ['fontsize'],
      ['strong', 'em', 'underline', 'strikethrough'],
      ['foreColor', 'backColor'],
      ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
      ['unorderedList', 'orderedList'],
      ['link'],
      ['removeformat'],
      ['viewHTML'],
      ['fullscreen']
    ],
    btnsDef: {
      formatting: {
        dropdown: ['p', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
        ico: 'p'
      }
    },
    plugins: {
      fontsize: { sizeList: ['14px', '16px', '18px', '22px', '24px', '26px'], allowCustomSize: true },
      fontfamily: { fontList: [
        { name: 'Arial', family: 'Arial, Helvetica, sans-serif' },
        { name: 'Roboto', family: 'Roboto, Arial, sans-serif' },
        { name: 'Georgia', family: 'Georgia, serif' },
        { name: 'Times New Roman', family: 'Times New Roman, Times, serif' },
        { name: 'Courier New', family: 'Courier New, Courier, monospace' },
        { name: 'Verdana', family: 'Verdana, Geneva, sans-serif' },
        { name: 'Tahoma', family: 'Tahoma, Geneva, sans-serif' },
        { name: 'Trebuchet MS', family: 'Trebuchet MS, Helvetica, sans-serif' },
        { name: 'Raleway', family: 'Raleway, Arial, sans-serif' }
      ] }
    }
  });
  $('#facetag-description').trumbowyg('html', currentDescription);

  // Bug connu de Trumbowyg : toggleSpan() (déclenché par Entrée/Retour arrière/Suppr,
  // keyCodes 8/13/46) est censé ne supprimer que les spans "fantômes" vides créés
  // par le navigateur lors d'un saut de ligne, mais sa détection par flag
  // (data-tbw-flag) est trop fragile : this.$ed.find("span") cherche dans TOUT
  // l'éditeur, et si un seul span existant perd son flag entre le keydown et le
  // keyup, il est traité comme fantôme et supprimé (contenu conservé, style perdu)
  // même sans rapport avec l'édition en cours. On remplace par une version qui ne
  // nettoie que les spans réellement vides ou sans attribut significatif.
  // (portage de geo_tag_editor)
  var trumbowygInstance = $('#facetag-description').data('trumbowyg');
  if (trumbowygInstance) {
    var trumbowygProto = Object.getPrototypeOf(trumbowygInstance);
    trumbowygProto.toggleSpan = function() {
      this.$ed.find('span').each(function() {
        var $span = $(this);
        var isEmpty = $span.contents().length === 0;
        var hasNoFormatting = !$span.attr('style') && !$span.attr('class') && $span.text().trim() === '';
        if (isEmpty || hasNoFormatting) {
          $span.contents().unwrap();
        }
      });
    };
  }

  // Empêcher un éventuel raccourci clavier du cœur Piwigo de capter les touches tapées
  // dans l'éditeur (sa garde ne fonctionne pas sur un contenteditable). Phase capture
  // pour intercepter avant que l'événement ne remonte au handler global.
  var trumbowygEditorEl = $('#facetag-modal .trumbowyg-editor')[0];
  if (trumbowygEditorEl) {
    trumbowygEditorEl.addEventListener('keydown', function(e) {
      e.stopPropagation();
    }, true);
  }
}


      //-------------------------------------------------------------------------------------------------
      // Afficher le bouton "Restaurer" seulement si un fichier .original existe
      //*console.log('Vérification hasOriginal:', hasOriginal);
      if (hasOriginal === true || hasOriginal === 'true') {
        //*console.log('Affichage du bouton restaurer');
        $('#facetag-restore-original').show();
      } else {
        //*console.log('Masquage du bouton restaurer');
        $('#facetag-restore-original').hide();
      }
      

      // Charger l'image et initialiser le canvas
      loadImageAndInitCanvas();
      
      // Événements de fermeture
      $('#facetag-close-modal, #facetag-cancel').click(closeModal);
      
      //-----------------------------------------------------------------------------------------
      // Événement d'enregistrement
      $('#facetag-save-xmp').click(function() {
        //*console.log("=== CLIC SUR ENREGISTRER ===");
        //*console.log("Image ID:", imageId);
        //*console.log("Nombre de visages:", faces.length);
        
        // Permettre l'enregistrement même avec 0 visages (pour supprimer tous les tags)  V1.9A
        if (faces.length === 0 && existingFaces.length > 0) {
          //*console.log("⚠️ Aucun visage - demande de confirmation");
          if (!confirm(_('Voulez-vous vraiment supprimer tous les tags de visages de cette image ?'))) {
            console.log("❌ Annulation par l'utilisateur");
            return;
          }
          console.log("✅ Confirmation de suppression");
        }
        
        var facesData = faces.map(function(face) {
          return {
            name: face.name,
            x: face.x,
            y: face.y,
            w: face.w,
            h: face.h
          };
        });
        
        //*console.log('📦 Données à enregistrer:', facesData);
        //console.log('📄 JSON:', JSON.stringify(facesData));
        //*console.log('🌐 URL:', saveUrl);
        
        $(this).prop('disabled', true).text(_('Enregistrement...'));
        //console.log("🔒 Bouton désactivé");
        
        // Créer un FormData pour envoyer en POST
        var formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('faces', JSON.stringify(facesData));
        // Ne pas envoyer la description si elle est en lecture seule : le backend garde alors
        // la valeur existante en base et ne touche pas au tag IPTC (voir main.inc.php).
        if (!isDescriptionReadonly) {
          var description = $('#facetag-description').trumbowyg('html') || '';
          formData.append('description', description);
        }

// ✅ AJOUTER LE PARAMÈTRE save_original ---------------------------------------------------- V2.1
        var saveOriginal = window.faceTagConfig ? window.faceTagConfig.saveOriginal : true;
        formData.append('save_original', saveOriginal);


        
      //console.log('Faces JSON avant envoi:', JSON.stringify(facesData));
      //console.log('Faces JSON bytes:', Array.from(JSON.stringify(facesData)).map(c => c.charCodeAt(0).toString(16).padStart(2, '0')).join(' '));

$.ajax({
          url: saveUrl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(data) {
            //*console.log('Réponse:', data);
  
  var result = data.result || data;
  
if (data.stat === 'ok' || result.stat === 'ok') {
  var msg = _('✅ Visages enregistrés avec succès !') + '\n\n';
  msg += _('Visages: ') + (result.faces_count || faces.length) + '\n';
  
  if (result.backup_created) {
    msg += _('Backup créé: Oui (.original)');
  } else {
    msg += _('Backup: Déjà existant');
  }
    

    //*alert(msg);


    closeModal();

    // FORCER RAFRAÎCHISSEMENT COMPLET
    window.location.href = window.location.href;
    

  } else {
    //*console.log('Erreur: ' + (data.message || result.message || 'Erreur inconnue'));
  }
},

          // Un seul handler error: deux blocs dupliqués coexistaient ici (le second écrasait
          // silencieusement le premier dans l'objet $.ajax), ce qui désactivait la récupération
          // "fausse erreur HTTP" ci-dessous — fusionnés en un seul bloc qui fait les deux.
          error: function(xhr, status, error) {
            console.error('❌ ERREUR AJAX');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response:', xhr.responseText);
            console.error('Status Code:', xhr.status);

            var response = null;
            try {
              response = JSON.parse(xhr.responseText);
            } catch(e) {}

            // Vérifier si c'est une fausse erreur (succès en réalité)
            if (response && response.stat === 'ok') {
              console.error('✅ Enregistré (malgré erreur HTTP)');
              closeModal();
              location.reload();
              return;
            }

            var errorMsg = (response && response.message) ? response.message : (xhr.responseText || error);
            alert("⚠️ ERROR⚠️ : " + errorMsg);
          },
          complete: function() {
            $('#facetag-save-xmp').prop('disabled', false).text('💾 ' + _('Enregistrer '));
          }
        });
      });
  //----------------------------------------------------------------------------------------------   
  // Événement effacer tout
      $('#facetag-clear-all').click(function() {
        if (faces.length === 0) {
          alert(_('Aucun visage à effacer'));
          return;
        }
        
        if (!confirm(_('Êtes-vous sûr de vouloir effacer tous les rectangles ?'))) {
          return;
        }
        
        faces.forEach(function(face) {
          canvas.remove(face.rect);
          if (face.rect.label) canvas.remove(face.rect.label);
        });
        
        canvas.renderAll();
        faces = [];
        updateFacesList();
      });
//-----------------------------------------------------------------------------------------------------      
// Événement restaurer l'original
      $('#facetag-restore-original').click(function() {
       if (!confirm(_('confirm_restore_original'))) {
      return; 
      }
        
        //*console.log('=== RESTAURATION DE L\'ORIGINAL ===');
        
        $(this).prop('disabled', true).text('Restauration...');
        
        var formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('action', 'restore');
        
        $.ajax({
          url: saveUrl.replace('facetagwrite.saveXMP', 'facetagwrite.restoreOriginal'),
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(data) {
            //*console.log('Réponse serveur:', data);

            // Vérifier la structure de réponse Piwigo
            var result = data.result || data;

            if (data.stat === 'ok' || result.stat === 'ok') {
              //*alert(_('✅ Fichier original restauré avec succès !'));
              closeModal();
              // Recharger la page pour mettre à jour l'état du bouton restaurer
              setTimeout(function() {
                window.location.href = window.location.href;
              }, 500);
            } else {
              console.error('Erreur: ' + (data.message || result.message || 'Erreur inconnue'));
            }
          },
          error: function(xhr, status, error) {
            console.error('❌ ERREUR AJAX');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response:', xhr.responseText);
            console.error('Status Code:', xhr.status);

            // Vérifier si c'est une fausse erreur (succès en réalité)
            try {
              var response = JSON.parse(xhr.responseText);
              if (response.stat === 'ok') {
                //*alert(_('✅ Fichier original restauré avec succès !'));
                closeModal();
                setTimeout(function() {
                  location.reload();
                }, 500);
                return;
              }
            } catch(e) {}

            var errorMsg = error || 'Erreur inconnue';
            try {
              var response = JSON.parse(xhr.responseText);
              if (response.message) {
                errorMsg = response.message;
              } else if (response.faultString) {
                errorMsg = response.faultString;
              }
            } catch(e) {}

            // Afficher le message d'erreur spécifique
            if (xhr.status === 404) {
              alert(_('error_no_original_file'));
            } else if (xhr.status === 403) {
              alert(_('❌ Accès refusé. Vous n\'avez pas les permissions nécessaires.'));
            } else {
              //*console.log('❌ Erreur : ' + errorMsg);
            }
          },
          complete: function() {
            $('#facetag-restore-original').prop('disabled', false).text('⏮️ Restaurer l\'original');
          }
        });
      });

      // Touche Escape
      $(document).on('keyup.facetag', function(e) {
        if (e.keyCode === 27) closeModal();
      });


    }

    
    // ==================== CHARGER LES XMP (comme face_tag) ===========================================================
    function loadXmpData(callback) {
      //*console.log('Chargement des XMP...');
      
      // Ajouter un timestamp pour éviter TOUT cache (navigateur + serveur)
      var nocache = '&_nocache=' + Date.now() + '&_rand=' + Math.random();
      var ajaxUrl = saveUrl.replace('facetagwrite.saveXMP', 'facetagwrite.getXMP') + '&image_id=' + imageId + nocache;
      //*console.log('URL:', ajaxUrl);
      
      $.ajax({
        url: ajaxUrl,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          //*console.log('=== RÉPONSE AJAX ===');
          //*console.log('data:', data);
          
          // Piwigo enveloppe dans data.result, et notre WS renvoie aussi result
          // Donc les XMP sont dans data.result.result.xmp !
          var xmpContainer = data.result && data.result.result ? data.result.result : data.result;
          
          //*console.log('xmpContainer:', xmpContainer);
          
          if (xmpContainer && xmpContainer.xmp) {
            //*console.log('✓ XMP trouvé');
            //*console.log('_raw_xmp présent:', '_raw_xmp' in xmpContainer.xmp);
            if (xmpContainer.xmp._raw_xmp) {
              //*console.log('_raw_xmp longueur:', xmpContainer.xmp._raw_xmp.length);
            }
          }
          
if (data.stat === 'ok' && xmpContainer && xmpContainer.xmp) {
            xmpData = xmpContainer.xmp;
            
            // ====================  STOCKER L'ORIENTATION =======================================================
            xmpData.orientation = xmpContainer.orientation || 1;
            //*console.log('✓ Orientation EXIF:', xmpData.orientation);
            
            //*console.log('✓ XMP Orientation chargé');
            
            // === UTILISER LES FACES PARSÉES CÔTÉ SERVEUR ===
            if (xmpData.faces && xmpData.faces.length > 0) {
              existingFaces = xmpData.faces;
              //*console.log('✓ Faces reçues du serveur:', existingFaces.length);
              existingFaces.forEach(function(face, i) {
                //*console.log('  Face ' + (i+1) + ':', face.name, '- x:', face.x.toFixed(3), 'y:', face.y.toFixed(3));
              });
            } else {
              // Fallback: Parser le XMP en JavaScript si pas de faces côté serveur
              //*console.log('⚠ Pas de faces du serveur, parsing JavaScript...');
              existingFaces = parseFacesFromXMP(xmpData);
              //*console.log('✓ Visages parsés (JS):', existingFaces.length);
            }
            
            callback(existingFaces);

          } else {
            //*console.log('❌ Pas de XMP disponible');
            callback([]);
          }
        },

//-------------------------------------------------
        error: function(xhr, status, error) {
  console.error('551 ❌ Erreur chargement XMP:', error);
  console.error('Status:', status);
  console.error('Response:', xhr.responseText);
  
  // ⚠️ FALLBACK : créer un xmpData minimal
  xmpData = {
    orientation: 1,
    _raw_xmp: '',
    subjects: [],
    hierarchical_subjects: [],
    tags_list: [],
    catalog_sets: [],
    faces: [],
    error: 'Erreur chargement XMP (HTTP ' + xhr.status + ')'
  };
  existingFaces = [];
  
  console.warn('⚠️ FALLBACK actif : orientation = 1 par défaut');
  console.warn('⚠️ Les visages existants ne seront pas affichés');
  
  callback([]);
}
 //---------------------------------------------------       

      });
    }
    
    // ==================== CHARGER L'IMAGE ET INITIALISER ====================
    function loadImageAndInitCanvas() {
      //*console.log('Chargement de l\'image:', imageSrc);
      
      // Créer un élément image temporaire pour obtenir les dimensions
      var img = new Image();
      
      img.onload = function() {
        //*console.log('Image chargée - Dimensions naturelles:', img.naturalWidth, 'x', img.naturalHeight);
        
        // Calculer les dimensions du canvas pour qu'il tienne dans la zone disponible
        var maxWidth = window.innerWidth - 500; // -500 pour la sidebar (420px + paddings/bordure)
        var maxHeight = window.innerHeight - 200; // -200 pour header/footer
        
        var scale = Math.min(
          maxWidth / img.naturalWidth,
          maxHeight / img.naturalHeight,
          1 // Ne pas agrandir si l'image est petite
        );
        
        var canvasWidth = img.naturalWidth * scale;
        var canvasHeight = img.naturalHeight * scale;
        
        //*console.log('Dimensions canvas:', canvasWidth, 'x', canvasHeight, '(scale:', scale, ')');
        
        // Initialiser Fabric.js
        canvas = new fabric.Canvas('facetag-canvas', {
          width: canvasWidth,
          height: canvasHeight,
          selection: false
        });
        
        // Stocker les dimensions pour les conversions
        canvas.imageWidth = img.naturalWidth;
        canvas.imageHeight = img.naturalHeight;
        canvas.displayWidth = canvasWidth;
        canvas.displayHeight = canvasHeight;
        canvas.scale = scale;
        
        // Définir l'image comme background du canvas
        fabric.Image.fromURL(imageSrc, function(fabricImg) {
          fabricImg.scaleToWidth(canvasWidth);
          canvas.setBackgroundImage(fabricImg, canvas.renderAll.bind(canvas));
          
          //*console.log('Background image défini');
          
          // Charger les visages existants (méthode simplifiée)
          loadXmpData(function(existingFacesData) {
            // Afficher les visages existants
            existingFacesData.forEach(function(face) {
              displayExistingFace(face);
            });
            
            updateFacesList();
            setupDrawingMode();
            //*console.log('Canvas prêt, mode dessin activé');




          });
        });
      };
      




      img.onerror = function() {
        console.error('Erreur de chargement de l\'image');
        closeModal();
      };
      
      img.src = imageSrc;
    }
    
    // ==================== PARSER LES XMP (copié de face_tag) ========================================

    function parseFacesFromXMP(data) {
      var faces = [];
      
      //*console.log('=== PARSING XMP ===');
      
      // Si on a le XMP brut, on le parse
      if (!data._raw_xmp) {
        //*console.log('❌ Pas de _raw_xmp');
        return faces;
      }
      
      //*console.log('✓ _raw_xmp trouvé, longueur:', data._raw_xmp.length);
      
      var xmlString = data._raw_xmp;
      
      // Parser avec DOMParser
      var parser = new DOMParser();
      var xmlDoc = parser.parseFromString(xmlString, "text/xml");
      
      // Vérifier les erreurs de parsing
      var parserError = xmlDoc.getElementsByTagName('parsererror');
      if (parserError.length > 0) {
        console.error('❌ Erreur parsing XML');
        return faces;
      }
      
      // === FORMAT 1 : MPReg (Microsoft Photo Region) ===
      var mpriRegions = xmlDoc.getElementsByTagName('MPRI:Regions');
      
      if (mpriRegions.length > 0) {
        var rdfLis = mpriRegions[0].getElementsByTagName('rdf:li');
        //*console.log('MPReg trouvé:', rdfLis.length, 'visages');
        
        for (var i = 0; i < rdfLis.length; i++) {
          var li = rdfLis[i];
          var name = null;
          var rect = null;
          
          // CAS 1 : Attributs directs
          name = li.getAttribute('MPReg:PersonDisplayName');
          rect = li.getAttribute('MPReg:Rectangle');
          
          // CAS 2 : Balises enfants
          if (!name || !rect) {
            var personNodes = li.getElementsByTagName('MPReg:PersonDisplayName');
            var rectNodes = li.getElementsByTagName('MPReg:Rectangle');
            
            if (personNodes.length > 0) {
              name = personNodes[0].textContent.trim();
            }
            if (rectNodes.length > 0) {
              rect = rectNodes[0].textContent.trim();
            }
          }
          
          if (name && rect) {
            var coords = rect.split(',').map(function(v) {
              return parseFloat(v.trim());
            });
            
            if (coords.length === 4) {
              // MPReg: x,y = coin supérieur gauche
              // Convertir en centre (format mwg-rs)
              var centerX = coords[0] + (coords[2] / 2);
              var centerY = coords[1] + (coords[3] / 2);
              
              faces.push({
                name: name,
                x: centerX,
                y: centerY,
                w: coords[2],
                h: coords[3],
                format: 'mpreg'
              });
              //*console.log('✓ Visage ajouté (MPReg):', name);
            }
          }
        }
      }
      
// === FORMAT 2 : mwg-rs (Metadata Working Group) ==================================================

var mwgRegionList = xmlDoc.getElementsByTagName('mwg-rs:RegionList');

if (mwgRegionList.length > 0) {
  var descriptions = mwgRegionList[0].getElementsByTagName('rdf:li');
  //*console.log('mwg-rs trouvé:', descriptions.length, 'visages');
  
  for (var i = 0; i < descriptions.length; i++) {
    var desc = descriptions[i];
    
    var name = desc.getAttribute('mwg-rs:Name');
    var type = desc.getAttribute('mwg-rs:Type');
    
    // Si pas d'attributs, chercher les balises
    if (!name) {
      var nameEl = desc.getElementsByTagName('mwg-rs:Name')[0];
      var typeEl = desc.getElementsByTagName('mwg-rs:Type')[0];
      if (nameEl) name = nameEl.textContent;
      if (typeEl) type = typeEl.textContent;
    }
    
    if (name && type === 'Face') {
      var areas = desc.getElementsByTagName('mwg-rs:Area');
      
      if (areas.length > 0) {
        var area = areas[0];
        
        // Essayer d'abord les attributs (format 1)
        var x = area.getAttribute('stArea:x');
        var y = area.getAttribute('stArea:y');
        var w = area.getAttribute('stArea:w');
        var h = area.getAttribute('stArea:h');
        
        // Si pas d'attributs, chercher les balises enfants (format 2)

      if (!x) {
      // Chercher avec ET sans namespace
      var xEl = area.getElementsByTagName('stArea:x')[0] || area.getElementsByTagName('x')[0];
      var yEl = area.getElementsByTagName('stArea:y')[0] || area.getElementsByTagName('y')[0];
      var wEl = area.getElementsByTagName('stArea:w')[0] || area.getElementsByTagName('w')[0];
      var hEl = area.getElementsByTagName('stArea:h')[0] || area.getElementsByTagName('h')[0];
      
      if (xEl) x = xEl.textContent || xEl.innerHTML;
      if (yEl) y = yEl.textContent || yEl.innerHTML;
      if (wEl) w = wEl.textContent || wEl.innerHTML;
      if (hEl) h = hEl.textContent || hEl.innerHTML;
      }
        
      
      if (x && y && w && h) {
          // Éviter les doublons
          var alreadyExists = faces.some(function(f) {
            return f.name === name;
          });
          
          if (!alreadyExists) {
            faces.push({
              name: name,
              x: parseFloat(x),
              y: parseFloat(y),
              w: parseFloat(w),
              h: parseFloat(h),
              format: 'mwg-rs'
            });
            //*console.log('✓ Visage ajouté (mwg-rs):', name);
          }
        }
      }
    }
  }
}
      
      //*console.log('=== Total visages parsés:', faces.length, '===');
      return faces;
    }
    
    // ==================== AFFICHER UN VISAGE EXISTANT ======================================================
    function displayExistingFace(face) {
      // Convertir les coordonnées normalisées -> pixels canvas
      // mwg-rs : x,y = centre
      var centerX = face.x * 100;
      var centerY = face.y * 100;
      var width = face.w * 100;
      var height = face.h * 100;
      
      var left = centerX - (width / 2);
      var top = centerY - (height / 2);
      
      // ==================== APPLIQUER LA TRANSFORMATION EXIF ====================
      var orientation = xmpData.orientation || 1;
      //*console.log('Orientation pour affichage:', orientation);
      
      var transformed = transformCoordinates(left, top, width, height, orientation);
      left = transformed.left;
      top = transformed.top;
      width = transformed.width;
      height = transformed.height;
      
      //*console.log('Affichage visage existant:', face.name, 'à', left.toFixed(2) + '%', top.toFixed(2) + '%', width.toFixed(2) + '%', height.toFixed(2) + '%');
      
      // Convertir % en pixels pour le canvas
      var leftPx = (left / 100) * canvas.displayWidth;
      var topPx = (top / 100) * canvas.displayHeight;
      var widthPx = (width / 100) * canvas.displayWidth;
      var heightPx = (height / 100) * canvas.displayHeight;
      
      // Créer le rectangle avec une couleur différente (bleu pour existant)
      var rect = new fabric.Rect({
        left: leftPx,
        top: topPx,
        width: widthPx,
        height: heightPx,
        fill: 'rgba(0, 150, 255, 0.2)',
        stroke: '#0096ff',
        strokeWidth: 2,
        selectable: true,
        hasRotatingPoint: false,
        // Activer tous les contrôles
        hasBorders: true,
        hasControls: true,
        lockRotation: true
      });
      
      canvas.add(rect);
      
      // Ajouter le label
      addLabelToRect(rect, face.name);
      
      // Sauvegarder dans notre liste
      faces.push({
        name: face.name,
        x: face.x,
        y: face.y,
        w: face.w,
        h: face.h,
        format: 'mwg-rs',
        rect: rect,
        existing: true
      });
    }
    
    // ==================== MODE DESSIN =================================================================
    function setupDrawingMode() {
      var isDrawing = false;
      var startX, startY;
      
      // Activer les contrôles sur les objets sélectionnés
      canvas.on('selection:created', function(e) {
        //*console.log('Objet sélectionné');
      });
      
      canvas.on('mouse:down', function(options) {
        // Si on clique sur un objet existant, ne rien faire (mode édition activé automatiquement)
        if (options.target) {
          //*console.log('Clic sur un rectangle existant - mode édition');
          return;
        }
        
        // Sinon, commencer à dessiner un nouveau rectangle
        isDrawing = true;
        var pointer = canvas.getPointer(options.e);
        startX = pointer.x;
        startY = pointer.y;
        
        currentRect = new fabric.Rect({
          left: startX,
          top: startY,
          width: 0,
          height: 0,
          fill: 'rgba(0, 255, 0, 0.2)',
          stroke: '#00ff00',
          strokeWidth: 3,
          selectable: true,
          hasRotatingPoint: false,
          // Activer tous les contrôles
          hasBorders: true,
          hasControls: true,
          lockRotation: true
        });
        
        canvas.add(currentRect);
        canvas.setActiveObject(currentRect);
      });
      
      canvas.on('mouse:move', function(options) {
        if (!isDrawing) return;
        
        var pointer = canvas.getPointer(options.e);
        
        if (pointer.x < startX) {
          currentRect.set({ left: pointer.x });
        }
        if (pointer.y < startY) {
          currentRect.set({ top: pointer.y });
        }
        
        currentRect.set({
          width: Math.abs(pointer.x - startX),
          height: Math.abs(pointer.y - startY)
        });
        
        canvas.renderAll();
      });
      
      canvas.on('mouse:up', function(options) {
        if (!isDrawing) return;
        isDrawing = false;
        
        if (currentRect.width < 10 || currentRect.height < 10) {
          canvas.remove(currentRect);
          currentRect = null;
          return;
        }
        
        promptForName(currentRect);
      });
      
// Mettre à jour le label quand on déplace/redimensionne un rectangle
canvas.on('object:moving', function(e) {
  var obj = e.target;
  if (obj.label) {
    obj.label.set({
      left: obj.left,
      top: obj.top + obj.height + 5 // En dessous au lieu de au-dessus
    });
  }
});

canvas.on('object:scaling', function(e) {
  var obj = e.target;
  if (obj.label) {
    obj.label.set({
      left: obj.left * obj.scaleX,
      top: (obj.top * obj.scaleY) + (obj.height * obj.scaleY) + 5 // En dessous
    });
  }
});
      
      // Mettre à jour les données quand on modifie un rectangle existant
canvas.on('object:modified', function(e) {
  var obj = e.target;

  var faceIndex = faces.findIndex(f => f.rect === obj);

  if (faceIndex !== -1) {
    var x = obj.left / canvas.displayWidth;
    var y = obj.top / canvas.displayHeight;
    var w = (obj.width * obj.scaleX) / canvas.displayWidth;
    var h = (obj.height * obj.scaleY) / canvas.displayHeight;

    var centerX = x + (w / 2);
    var centerY = y + (h / 2);

    // ✅ Appliquer transformation inverse SEULEMENT si orientation ≠ 1
    var orientation = xmpData.orientation || 1;

    if (orientation !== 1) {
      var leftPct = centerX * 100 - (w * 100 / 2);
      var topPct = centerY * 100 - (h * 100 / 2);
      var widthPct = w * 100;
      var heightPct = h * 100;

      var original = inverseTransformCoordinates(leftPct, topPct, widthPct, heightPct, orientation);

      centerX = (original.left + original.width / 2) / 100;
      centerY = (original.top + original.height / 2) / 100;
      w = original.width / 100;
      h = original.height / 100;
    }

    faces[faceIndex].x = centerX;
    faces[faceIndex].y = centerY;
    faces[faceIndex].w = w;
    faces[faceIndex].h = h;

    //*console.log('Rectangle modifié, nouvelles coordonnées:', faces[faceIndex]);
  }
});

      // ==================== DOUBLE-CLIC POUR RENOMMER ====================
      var lastClickTime = 0;
      var lastClickedObject = null;

      canvas.on('mouse:down', function(options) {
        var now = Date.now();
        var timeDiff = now - lastClickTime;

        // Vérifier si c'est un double-clic (< 300ms) sur le même objet
        if (options.target && timeDiff < 300 && lastClickedObject === options.target) {
          //*console.log('=== DOUBLE-CLIC DÉTECTÉ ===');
          var faceIndex = faces.findIndex(f => f.rect === options.target);
          if (faceIndex !== -1) {
            promptForRename(faceIndex);
          }
          lastClickTime = 0; // Réinitialiser pour éviter triple-clic
        } else {
          lastClickTime = now;
          lastClickedObject = options.target;
        }
      });
    }
    
    // ==================== DIALOGUE POUR NOMMER ====================
    function promptForName(rect) {
      var existingNames = faces.map(f => f.name).filter((v, i, a) => a.indexOf(v) === i);


      
      var nameModal = `
        <div id="facetag-name-modal">
          <h3>${_('Nommer la personne')}</h3>
          <input type="text" id="facetag-name-input" placeholder="${_('Nom de la personne')}">
          ${existingNames.length > 0 ? '<div class="existing-names-list">' + _('Personnes existantes :') + ' ' + existingNames.join(', ') + '</div>' : ''}
          <div class="modal-buttons">
            <button id="facetag-name-cancel">${_('Annuler')}</button>
            <button id="facetag-name-save">${_('Valider')}</button>
          </div>
        </div>
        <div id="facetag-name-overlay"></div>
      `;
      
      $('body').append(nameModal);
      $('#facetag-name-input').focus();
      
     $('#facetag-name-save').click(function() {
        //*console.log("=== CLIC SUR VALIDER (nom du visage) ===");
        var name = $('#facetag-name-input').val().trim();
        //*console.log("Nom saisi:", name);
        
        if (!name) {
          //*console.log("⚠️ Nom vide - alerte affichée");
          alert(_('Veuillez entrer un nom'));
          return;
        }
        
//*console.log("✅ Nom valide, ajout du label et sauvegarde...");

try {
  addLabelToRect(rect, name);
  saveFaceData(rect, name);
  //*console.log("✅ Visage créé avec succès");
} catch (error) {
  console.error("❌ ERREUR lors de la sauvegarde du visage:", error);
  console.error("⚠️ Erreur technique : " + error.message + "\n\nLe rectangle a été créé mais les coordonnées n'ont peut-être pas été sauvegardées correctement.");
}

closeNameModal();
      });
      
      $('#facetag-name-cancel').click(function() {
        //*console.log("=== CLIC SUR ANNULER ===");
        canvas.remove(rect);
        closeNameModal();
      });

      $('#facetag-name-input').keypress(function(e) {
        if (e.which === 13) $('#facetag-name-save').click();
      });
    }
    
    function closeNameModal() {
      $('#facetag-name-modal, #facetag-name-overlay').remove();
      currentRect = null;
    }

    // ==================== DIALOGUE POUR RENOMMER UN VISAGE EXISTANT ====================
    function promptForRename(faceIndex) {
      var face = faces[faceIndex];
      var existingNames = faces.map(f => f.name).filter((v, i, a) => a.indexOf(v) === i);

var renameModal = `
        <div id="facetag-rename-modal">
          <h3>${_('Renommer la personne')}</h3>
          <p class="rename-old-name">${_('Ancien nom :')} <strong>${face.name}</strong></p>
          <input type="text" id="facetag-rename-input" placeholder="${_('Nouveau nom')}" value="${face.name}">
          ${existingNames.length > 0 ? '<div class="existing-names-list">' + _('Autres personnes :') + ' ' + existingNames.filter(n => n !== face.name).join(', ') + '</div>' : ''}
          <div class="modal-buttons">
            <button id="facetag-rename-cancel">${_('Annuler')}</button>
            <button id="facetag-rename-save">${_('Renommer')}</button>
          </div>
        </div>
        <div id="facetag-rename-overlay"></div>
      `;

      $('body').append(renameModal);
      $('#facetag-rename-input').focus().select();

      $('#facetag-rename-save').click(function() {
        //*console.log("=== CLIC SUR RENOMMER ===");
        var newName = $('#facetag-rename-input').val().trim();
        //*console.log("Ancien nom:", face.name, "Nouveau nom:", newName);

        if (!newName) {
          //*console.log("⚠️ Nom vide - alerte affichée");
          alert(_('Veuillez entrer un nom'));
          return;
        }

        if (newName === face.name) {
          //*console.log("ℹ️ Nom identique, pas de changement");
          closeRenameModal();
          return;
        }

        // Mettre à jour le nom du visage
        faces[faceIndex].name = newName;

        // Mettre à jour le label sur le canvas
        if (face.rect.label) {
          canvas.remove(face.rect.label);
        }
        addLabelToRect(face.rect, newName);

        // Mettre à jour la liste des visages
        updateFacesList();

        //*console.log("✅ Visage renommé avec succès");
        closeRenameModal();
      });

      $('#facetag-rename-cancel').click(function() {
        //*console.log("=== CLIC SUR ANNULER (renommage) ===");
        closeRenameModal();
      });

      $('#facetag-rename-input').keypress(function(e) {
        if (e.which === 13) $('#facetag-rename-save').click();
      });
    }

    function closeRenameModal() {
      $('#facetag-rename-modal, #facetag-rename-overlay').remove();
    }

   // ==================== LABEL SUR LE RECTANGLE ==================== V1.9B
function addLabelToRect(rect, name) {
  var label = new fabric.Text(name, {
    left: rect.left,
    top: rect.top + rect.height + 5, // Positionner en dessous + 5px de marge
    fontSize: 14,
    fill: '#ffffff',
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    padding: 4,
    selectable: false,
    evented: false, // Le label n'intercepte pas les événements
    //textBaseline: 'top' // Correction du warning Fabric.js
  });
      
      canvas.add(label);
      rect.label = label;
      label.bringToFront();
      
      // Note: les événements moving/scaling sont gérés dans setupDrawingMode()
    }
    
    // ==================== SAUVEGARDER LES DONNÉES ====================
 function saveFaceData(rect, name) {
  var x = rect.left / canvas.displayWidth;
  var y = rect.top / canvas.displayHeight;
  var w = (rect.width * (rect.scaleX || 1)) / canvas.displayWidth;
  var h = (rect.height * (rect.scaleY || 1)) / canvas.displayHeight;
  
  // Coordonnées centre (format mwg-rs)
  var centerX = x + (w / 2);
  var centerY = y + (h / 2);
  
  // ✅ Appliquer transformation inverse SEULEMENT si orientation ≠ 1
  var orientation = xmpData.orientation || 1;
  
  if (orientation !== 1) {
    // Convertir en pourcentages pour la transformation
    var leftPct = centerX * 100 - (w * 100 / 2);
    var topPct = centerY * 100 - (h * 100 / 2);
    var widthPct = w * 100;
    var heightPct = h * 100;
    
    var original = inverseTransformCoordinates(leftPct, topPct, widthPct, heightPct, orientation);
    
    // Recalculer le centre dans les coordonnées originales
    centerX = (original.left + original.width / 2) / 100;
    centerY = (original.top + original.height / 2) / 100;
    w = original.width / 100;
    h = original.height / 100;
    
    //*console.log('Transformation inverse appliquée (orientation=' + orientation + ')');
  }
  
  var face = {
    name: name,
    x: centerX,
    y: centerY,
    w: w,
    h: h,
    format: 'mwg-rs',
    rect: rect
  };
  
  faces.push(face);
  updateFacesList();
}
    
    // ==================== LISTE DES VISAGES ==========================================================
function updateFacesList() {
  var $list = $('#facetag-faces-list');
  var $count = $('#facetag-count');
  
  $count.text(faces.length);
  $list.empty();
  
  if (faces.length === 0) {
    $list.html(`<p>${_('Aucun visage tagué')}</p>`);
    return;
  }
  
faces.forEach(function(face, index) {
  // Déterminer la classe et le badge selon si c'est un visage existant
  var itemClass = face.existing ? 'face-item existing' : 'face-item';
  var badge = face.existing ? `<span class="face-item-badge">${_('existant')}</span>` : '';
  
  var $item = $('<div>')
    .addClass(itemClass)
    .html(`
      <span class="face-item-name">${face.name}${badge}</span>
      <button class="facetag-delete-face" data-index="${index}">${_('Supprimer')}</button>
    `);
  
  $list.append($item);
});
  
  // Event handler pour les boutons supprimer
  //$list.off('click', '.facetag-delete-face'); // Nettoyer les anciens handlers
  $list.on('click', '.facetag-delete-face', function() {
    var index = $(this).data('index');
    deleteFace(index);
  });
}


function deleteFace(index) {
  var face = faces[index];
  canvas.remove(face.rect);
  if (face.rect.label) canvas.remove(face.rect.label);
  canvas.renderAll();
  faces.splice(index, 1);
  updateFacesList();
}
    
    // ==================== FERMER LA MODALE =========================================================
    function closeModal() {
      $('#facetag-modal, #facetag-modal-overlay').remove();
      $(document).off('keyup.facetag');
      canvas = null;
      faces = [];
      
    }
    //--------------------------------------------------------------------------------------------------  

// Fonction pour télécharger l'image avec les rectangles de tags
function downloadImageWithTags() {
    // Créer un nouveau canvas pour le rendu final EN RÉSOLUTION ORIGINALE
    var outputCanvas = document.createElement('canvas');
    var outputCtx = outputCanvas.getContext('2d');
    
    // Utiliser les dimensions ORIGINALES de l'image (pas celles du canvas affiché)
    outputCanvas.width = canvas.imageWidth;
    outputCanvas.height = canvas.imageHeight;
    
    // Calculer le ratio entre résolution originale et canvas affiché
    var scaleRatio = canvas.imageWidth / canvas.displayWidth;
    
    //*console.log('Résolution originale:', canvas.imageWidth, 'x', canvas.imageHeight);
    //*console.log('Résolution affichée:', canvas.displayWidth, 'x', canvas.displayHeight);
    //*console.log('Scale ratio:', scaleRatio);
    
    // Charger l'image originale en haute résolution
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = imageSrc;
    
    img.onload = function() {
        // Dessiner l'image en taille originale
        outputCtx.drawImage(img, 0, 0, canvas.imageWidth, canvas.imageHeight);
        
        // Dessiner tous les rectangles de tags (mis à l'échelle)
        faces.forEach(function(face, index) {
            var rect = face.rect;
            
            // Calculer les coordonnées en pixels (mis à l'échelle pour résolution originale)
            var x = rect.left * scaleRatio;
            var y = rect.top * scaleRatio;
            var width = rect.width * (rect.scaleX || 1) * scaleRatio;
            var height = rect.height * (rect.scaleY || 1) * scaleRatio;
            
            // Dessiner le rectangle (épaisseur adaptée à la résolution)
            outputCtx.strokeStyle = face.existing ? '#0096ff' : '#00ff00';
            outputCtx.lineWidth = 3 * scaleRatio;
            outputCtx.strokeRect(x, y, width, height);
            
            // Dessiner le nom du tag EN DESSOUS du rectangle
            if (face.name && face.name.trim() !== '') {
                // Taille de police adaptée à la résolution
                var fontSize = 16 * scaleRatio;
                outputCtx.font = 'bold ' + fontSize + 'px Arial';
                
                var textWidth = outputCtx.measureText(face.name).width;
                var labelHeight = 24 * scaleRatio;
                var padding = 8 * scaleRatio;
                
                // Position EN DESSOUS : y + height + 5
                var labelY = y + height + (5 * scaleRatio);
                
                outputCtx.fillStyle = face.existing ? 'rgba(0, 150, 255, 0.8)' : 'rgba(0, 255, 0, 0.8)';
                outputCtx.fillRect(x, labelY, textWidth + padding * 2, labelHeight);
                
                // Texte du label
                outputCtx.fillStyle = '#000000';
                outputCtx.textBaseline = 'top';
                outputCtx.fillText(face.name, x + padding, labelY + (4 * scaleRatio));
            }
        });
        
        // Convertir en blob et télécharger
        outputCanvas.toBlob(function(blob) {
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            
            // Nom de fichier avec ID de l'image
            a.download = 'image_' + imageId + '_with_face_tags.jpg';
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            //*console.log('✅ Image téléchargée en résolution', canvas.imageWidth, 'x', canvas.imageHeight);
        }, 'image/jpeg', 0.95);
    };
    
    img.onerror = function() {
        alert(_('Erreur lors de la génération de l\'image'));
    };
}

// Attacher l'événement au bouton
$(document).on('click', '#facetag-download-jpg', function() {
    if (faces.length === 0) {
        alert(_('Aucun visage tagué à télécharger'));
        return;
    }
    downloadImageWithTags();
});



//===================================================================================
    
  }); // fin document.ready
})(jQuery);
