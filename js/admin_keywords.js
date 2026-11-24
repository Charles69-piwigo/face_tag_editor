/**
 * face_tag_editor - onglet « Mots-clés des visages » (2.5.2)
 * Analyse par lots (lecture seule) dont l'état est gardé sur le serveur : elle peut être arrêtée,
 * la page rechargée, puis reprise au même endroit. Aperçu par album, cas douteux à part,
 * écriture par lots (interruptible elle aussi). Présentation reprise de synchro_smart.
 */
jQuery(function ($) {
  var $box = $('#kwtool');
  if (!$box.length) {
    return;
  }
  var url = $box.data('url');
  var token = $box.data('token');
  var L = window.kwToolLang || {};
  var t = function (k) { return L[k] || k; };
  var esc = function (s) { return $('<div>').text(String(s)).html(); };

  var items = [];      // photos signalées (voir facetagwrite_kw_scan_image())
  var albums = {};     // id -> libellé
  var state = null;    // résumé de l'analyse conservée sur le serveur
  var stopScan = false;
  var stopWrite = false;
  var scanning = false;

  function post(data) {
    data.pwg_token = token;
    return $.ajax({ url: url, type: 'POST', data: data, dataType: 'json', timeout: 120000 });
  }

  function errText(code) {
    var parts = String(code).split(':');
    var label = L['kwtool_err_' + parts[0]];
    return label ? label + (parts.length > 1 ? ' (' + parts.slice(1).join(':') + ')' : '') : code;
  }

  function photoLink(item) {
    return '<a href="picture.php?/' + item.id + '" target="_blank">' + esc(item.file) + '</a>';
  }

  function fmtDate(ts) {
    return ts ? new Date(ts * 1000).toLocaleString() : '';
  }

  function alertBox(msg) {
    $('#kw_alert').text(msg || '').toggle(!!msg);
  }

  // ---------- Réglages ----------
  $('#kw_save').on('click', function () {
    post({ kw_action: 'save', root: $('#kw_root').val(), original: $('#kw_original').is(':checked') ? 1 : 0 })
      .done(function (r) {
        if (r.stat === 'ok') {
          $('#kw_root').val(r.root);
          $('#kw_save_status').text(t('kwtool_saved'));
        } else {
          $('#kw_save_status').text(r.message || t('kwtool_error'));
        }
      })
      .fail(function () { $('#kw_save_status').text(t('kwtool_error')); });
  });

  // ---------- Arbre des albums (repris de synchro_smart, sélection multiple) ----------
  // Lignes en ordre préfixe (parent avant ses enfants) : les descendants d'une ligne sont les
  // lignes suivantes de profondeur supérieure. « kw-explicit » = cochée par l'utilisateur ;
  // « kw-implied » = un ancêtre est coché (incluse d'office côté serveur, get_subcat_ids()).
  var $rows = $('#kw_tree .kw-album-row');

  function depthOf($r) { return parseInt($r.attr('data-depth'), 10); }

  function ancestorsOf(row) {
    var out = [];
    var d = depthOf($(row));
    var el = row.previousElementSibling;
    while (el && d > 0) {
      var ed = depthOf($(el));
      if (ed < d) { out.push(el); d = ed; }
      el = el.previousElementSibling;
    }
    return out;
  }

  function refreshTree() {
    var n = 0;
    $rows.each(function () {
      var explicit = $(this).hasClass('kw-explicit');
      var implied = !explicit && ancestorsOf(this).some(function (a) { return $(a).hasClass('kw-explicit'); });
      $(this).toggleClass('kw-implied', implied).toggleClass('kw-selected', explicit);
      if (explicit) { n++; }
    });
    $('#kw_selection').text(n ? t('kwtool_selection_count').replace('{count}', n) : t('kwtool_selection_none'));
    $('#kw_clear').toggle(n > 0);
  }

  function selectedAlbumIds() {
    return $rows.filter('.kw-explicit').map(function () { return $(this).attr('data-id'); }).get();
  }

  /** Coche des albums (état conservé) et déplie leurs ancêtres pour les montrer. */
  function selectAlbums(ids) {
    var want = {};
    $.each(ids, function (i, id) { want[String(id)] = true; });
    $rows.removeClass('kw-explicit');
    $rows.each(function () {
      if (want[$(this).attr('data-id')]) {
        $(this).addClass('kw-explicit');
        $.each(ancestorsOf(this), function (i, a) {
          var $tg = $(a).find('.kw-toggle');
          if ($tg.attr('data-state') !== 'expanded') { $tg.trigger('click'); }
        });
      }
    });
    refreshTree();
  }

  $rows.on('click', function (ev) {
    if (scanning || $(ev.target).closest('.kw-toggle').length || $(this).hasClass('kw-implied')) {
      return;
    }
    var $r = $(this);
    $r.toggleClass('kw-explicit');
    if ($r.hasClass('kw-explicit')) {
      // un sous-album d'un album coché est déjà inclus : inutile de le garder coché
      var d = depthOf($r);
      var next = this.nextElementSibling;
      while (next && depthOf($(next)) > d) {
        $(next).removeClass('kw-explicit');
        next = next.nextElementSibling;
      }
    }
    refreshTree();
  });

  $('#kw_tree .kw-toggle').on('click', function () {
    var row = $(this).closest('.kw-album-row')[0];
    var d = depthOf($(row));
    var expanded = $(this).attr('data-state') === 'expanded';
    var next = row.nextElementSibling;
    while (next && depthOf($(next)) > d) {
      if (expanded) {
        next.style.display = 'none';
        $(next).find('.kw-toggle').attr('data-state', 'collapsed').html('&#9658;');
      } else if (depthOf($(next)) === d + 1) {
        next.style.display = '';
      }
      next = next.nextElementSibling;
    }
    $(this).attr('data-state', expanded ? 'collapsed' : 'expanded').html(expanded ? '&#9658;' : '&#9660;');
  });

  $('#kw_clear').on('click', function (ev) {
    ev.preventDefault();
    if (!scanning) {
      $rows.removeClass('kw-explicit');
      refreshTree();
    }
  });

  refreshTree();

  // ---------- Progression ----------
  function showProgress(s, label) {
    var pct = s.total > 0 ? Math.min(100, Math.round(100 * s.scanned / s.total)) : 100;
    $('#kw_progress_wrap').show();
    $('#kw_fill').removeClass('kw-indeterminate').css('width', pct + '%');
    $('#kw_progress').text(label + ' ' + s.scanned + ' / ' + s.total + ' (' + pct + '%)'
      + (s.current && !s.done ? ' — ' + s.current : ''));
    $('#kw_n_scanned').text(s.scanned);
  }

  function setScanning(on) {
    scanning = on;
    $('#kw_scan, #kw_write').prop('disabled', on);
    $box.toggleClass('kw-busy', on); // arbre des albums figé pendant l'analyse
    $('#kw_stop').toggle(on).prop('disabled', false);
    $('#kw_spin').toggle(on);
    if (on) {
      $('#kw_resume, #kw_resume_box').hide();
    }
  }

  function showResume() {
    var resumable = state && !state.done;
    $('#kw_resume').toggle(!!resumable);
    if (!state) {
      $('#kw_resume_box').hide();
      return;
    }
    var scope = state.cat_label || t('kwtool_whole_gallery_label');
    var msg = t('kwtool_last_scan') + ' ' + scope + ' — ' + fmtDate(state.started) + ' — '
      + state.scanned + ' / ' + state.total;
    if (resumable) {
      msg += '. ' + t('kwtool_resume_hint');
    }
    $('#kw_resume_box').text(msg).show();
  }

  // ---------- Analyse ----------
  $('#kw_scan').on('click', function () {
    if (state && !state.done && !confirm(t('kwtool_confirm_restart'))) {
      return;
    }
    items = [];
    albums = {};
    $('#kw_write_log').empty();
    $('#kw_write_wrap').hide();
    alertBox('');
    setScanning(true);
    $('#kw_progress_wrap').show();
    $('#kw_fill').addClass('kw-indeterminate');
    $('#kw_progress').text(t('kwtool_starting'));
    render();
    post({ kw_action: 'scan_start', cats: selectedAlbumIds().join(',') }).done(function (r) {
      if (r.stat !== 'ok') {
        alertBox(r.message || t('kwtool_error'));
        setScanning(false);
        return;
      }
      state = r.state;
      runScan(0);
    }).fail(function () {
      alertBox(t('kwtool_error'));
      setScanning(false);
    });
  });

  $('#kw_resume').on('click', function () {
    alertBox('');
    setScanning(true);
    runScan(0);
  });

  $('#kw_stop').on('click', function () {
    stopScan = true;
    $(this).prop('disabled', true);
    $('#kw_progress').text(t('kwtool_stop_requested'));
  });

  function runScan(retries) {
    if (stopScan) {
      stopScan = false;
      setScanning(false);
      showProgress(state, t('kwtool_stopped'));
      showResume();
      return;
    }
    post({ kw_action: 'scan_batch' }).done(function (r) {
      if (r.stat !== 'ok') {
        alertBox(r.message || t('kwtool_error'));
        setScanning(false);
        showResume();
        return;
      }
      state = r.state;
      $.extend(albums, r.albums);
      items = items.concat(r.items);
      render();
      if (state.done) {
        showProgress(state, t('kwtool_scan_done'));
        setScanning(false);
        showResume();
        return;
      }
      showProgress(state, t('kwtool_scanning'));
      runScan(0);
    }).fail(function () {
      // Réseau ou serveur momentanément indisponible : nouvel essai (comme synchro_smart)
      if (retries < 5) {
        $('#kw_progress').text(t('kwtool_network_retry'));
        setTimeout(function () { runScan(retries + 1); }, 3000);
      } else {
        alertBox(t('kwtool_error'));
        setScanning(false);
        showResume();
      }
    });
  }

  // ---------- Affichage ----------
  function render() {
    var byAlbum = {};
    var doubts = {};
    var errors = [];
    var nPhotos = 0, nKw = 0, nTags = 0, nDoubtPhotos = 0;
    $.each(items, function (i, it) {
      if (it.error) {
        errors.push(it);
        return;
      }
      it.create = it.create || []; // absent des analyses conservées avant 2.5.2 final
      if (it.write.length || it.tag.length || it.create.length) {
        var a = byAlbum[it.album_id] || (byAlbum[it.album_id] = { photos: 0, kw: 0, tags: 0, names: {} });
        a.photos++;
        a.kw += it.write.length;
        a.tags += it.tag.length + it.create.length;
        nPhotos++;
        nKw += it.write.length;
        nTags += it.tag.length + it.create.length;
        $.each(it.write.concat(it.tag), function (j, n) { a.names[n] = a.names[n] || ''; });
        $.each(it.create, function (j, n) { a.names[n] = ' (' + t('kwtool_new_tag') + ')'; });
      }
      if (it.doubts.length) {
        nDoubtPhotos++;
      }
      $.each(it.doubts, function (j, d) {
        var key = d.name + '\u0000' + d.reason;
        var o = doubts[key] || (doubts[key] = { name: d.name, reason: d.reason, near: d.near, items: [] });
        o.items.push(it);
      });
    });

    $('#kw_stats').toggle(!!state);
    $('#kw_n_photos').text(nPhotos);
    $('#kw_n_keywords').text(nKw);
    $('#kw_n_tags').text(nTags);
    $('#kw_n_doubts').text(nDoubtPhotos);
    $('#kw_n_errors').text(errors.length);

    var checked = {};
    $('#kw_albums tbody input[type=checkbox]').each(function () { checked[$(this).val()] = $(this).is(':checked'); });
    var ids = Object.keys(byAlbum).sort(function (x, y) { return String(albums[x] || x).localeCompare(String(albums[y] || y)); });
    var rows = '';
    $.each(ids, function (k, id) {
      var a = byAlbum[id];
      var names = Object.keys(a.names).map(function (n) { return n + a.names[n]; });
      var isChecked = (checked[id] === undefined) ? $('#kw_all').is(':checked') : checked[id];
      rows += '<tr><td><input type="checkbox" value="' + esc(id) + '"' + (isChecked ? ' checked' : '') + '></td>'
        + '<td>' + esc(albums[id] || id) + '</td><td class="kw-num">' + a.photos + '</td><td class="kw-num">' + a.kw + '</td><td class="kw-num">' + a.tags + '</td>'
        + '<td>' + esc(names.slice(0, 8).join(', ')) + (names.length > 8 ? ' (+' + (names.length - 8) + ')' : '') + '</td></tr>';
    });
    $('#kw_albums tbody').html(rows || '<tr><td colspan="6">' + esc(t('kwtool_nothing')) + '</td></tr>');
    $('#kw_results').toggle(!!state);
    $('#kw_write').prop('disabled', !rows || scanning);

    var dkeys = Object.keys(doubts).sort(function (x, y) { return doubts[x].name.localeCompare(doubts[y].name); });
    var drows = '';
    $.each(dkeys, function (k, key) {
      var d = doubts[key];
      var reason = t('kwtool_reason_' + d.reason);
      if (d.near && d.near.length) {
        reason += ' — ' + t('kwtool_near') + ' ' + d.near.join(', ');
      }
      drows += '<tr><td>' + esc(d.name) + '</td><td>' + esc(reason) + '</td><td class="kw-num">' + d.items.length + '</td><td>'
        + d.items.slice(0, 5).map(photoLink).join(', ') + '</td></tr>';
    });
    $('#kw_doubts tbody').html(drows);
    $('#kw_doubts_box').toggle(drows !== '');

    var erows = '';
    $.each(errors, function (i, it) {
      erows += '<tr><td>' + photoLink(it) + '</td><td>' + esc(albums[it.album_id] || it.album_id) + '</td><td>' + esc(errText(it.error)) + '</td></tr>';
    });
    $('#kw_errors tbody').html(erows);
    $('#kw_errors_box').toggle(erows !== '');
  }

  $('#kw_all').on('change', function () {
    $('#kw_albums tbody input[type=checkbox]').prop('checked', $(this).is(':checked'));
  });

  // ---------- Écriture ----------
  $('#kw_write').on('click', function () {
    var selected = {};
    $('#kw_albums tbody input[type=checkbox]:checked').each(function () { selected[$(this).val()] = true; });
    var ids = [];
    $.each(items, function (i, it) {
      if (!it.error && selected[it.album_id] && (it.write.length || it.tag.length || (it.create || []).length)) {
        ids.push(it.id);
      }
    });
    if (!ids.length) {
      alert(t('kwtool_select_album'));
      return;
    }
    if (!confirm(t('kwtool_confirm_write').replace('{count}', ids.length))) {
      return;
    }
    stopWrite = false;
    $('#kw_write, #kw_scan, #kw_resume').prop('disabled', true);
    $('#kw_write_stop').show().prop('disabled', false);
    $('#kw_write_log').empty();
    $('#kw_write_wrap').show();
    writeBatch(ids, 0, { files: 0, tags: 0, fail: 0 }, 0);
  });

  $('#kw_write_stop').on('click', function () {
    stopWrite = true;
    $(this).prop('disabled', true);
    $('#kw_write_status').text(t('kwtool_stop_requested'));
  });

  function endWrite(msg, sum) {
    $('#kw_write_status').text(msg + ' ' + t('kwtool_written') + ' ' + sum.files + ', '
      + t('kwtool_tags_added') + ' ' + sum.tags + ', ' + t('kwtool_failures') + ' ' + sum.fail);
    $('#kw_write_stop').hide();
    $('#kw_scan, #kw_resume').prop('disabled', false);
    render();
  }

  function writeBatch(ids, pos, sum, retries) {
    var pct = Math.round(100 * pos / ids.length);
    $('#kw_write_fill').css('width', pct + '%');
    if (pos >= ids.length) {
      endWrite(t('kwtool_write_done'), sum);
      return;
    }
    if (stopWrite) {
      endWrite(t('kwtool_stopped'), sum);
      return;
    }
    var chunk = ids.slice(pos, pos + 10);
    $('#kw_write_status').text(t('kwtool_writing') + ' ' + pos + ' / ' + ids.length + ' (' + pct + '%)');
    post({ kw_action: 'write', ids: chunk.join(',') }).done(function (r) {
      if (r.stat !== 'ok') {
        endWrite(r.message || t('kwtool_error'), sum);
        return;
      }
      $.each(r.results, function (i, res) {
        var idx = -1;
        $.each(items, function (j, x) { if (x.id === res.id) { idx = j; } });
        var it = idx >= 0 ? items[idx] : null;
        if (res.written.length) { sum.files++; }
        sum.tags += res.tags_added;
        if (res.error) {
          sum.fail++;
          if (it) { it.error = res.error; }
        } else if (it) {
          // Traité : plus à faire (les cas douteux de la photo restent listés)
          it.write = [];
          it.tag = [];
          it.create = [];
          if (!it.doubts.length) { items.splice(idx, 1); }
        }
        if (res.error || res.warnings.length) {
          $('#kw_write_log').append('<li>' + (it ? photoLink(it) : res.id) + ' : '
            + esc((res.error ? errText(res.error) + ' ' : '') + res.warnings.map(errText).join(', ')) + '</li>');
        }
      });
      render();
      writeBatch(ids, pos + chunk.length, sum, 0);
    }).fail(function () {
      if (retries < 5) {
        $('#kw_write_status').text(t('kwtool_network_retry'));
        setTimeout(function () { writeBatch(ids, pos, sum, retries + 1); }, 3000);
      } else {
        endWrite(t('kwtool_error'), sum);
      }
    });
  }

  // ---------- Au chargement : analyse conservée sur le serveur ----------
  post({ kw_action: 'state' }).done(function (r) {
    if (r.stat !== 'ok' || !r.state) {
      return;
    }
    state = r.state;
    items = r.items || [];
    albums = r.albums || {};
    selectAlbums(state.cats || []);
    showProgress(state, state.done ? t('kwtool_scan_done') : t('kwtool_stopped'));
    render();
    showResume();
  });
});
