<div class="titlePage" id="kw-title">
  <h2>{'kwtool_title'|@translate}</h2>
</div>

<style>
  #kw-title, #kw-title h2 { text-align: left; }
  #kwtool, #kwtool * { text-align: left; }
  #kwtool fieldset { margin-bottom: 18px; }
  #kwtool legend i { margin-right: 4px; }
  #kwtool .kw-intro { max-width: 820px; margin: 0 0 14px 4px; }
  #kwtool .kw-hint { margin: 2px 0 8px 0; color: #888; font-size: 0.85em; font-style: italic; }
  #kwtool .kw-row { margin: 8px 0; }
  #kwtool .kw-row label { font-weight: bold; }
  #kwtool .kw-row label.kw-plain { font-weight: normal; }
  #kwtool .kw-actions { margin: 12px 0 6px; }
  #kwtool .kw-actions .buttonLike { margin-right: 6px; }
  #kwtool .kw-progress { display: none; max-width: 700px; margin-top: 12px; }
  #kwtool .kw-track { border: 1px solid #ccc; border-radius: 3px; height: 18px; background: #eee; overflow: hidden; }
  #kwtool .kw-fill { height: 100%; width: 0%; background: #4a90d9; transition: width 0.3s ease; }
  #kwtool .kw-fill.kw-indeterminate {
    width: 100% !important;
    background-image: repeating-linear-gradient(45deg, #4a90d9, #4a90d9 12px, #6ba7e0 12px, #6ba7e0 24px);
    background-size: 34px 34px;
    animation: kwStripes 1s linear infinite;
  }
  @keyframes kwStripes { from { background-position: 0 0; } to { background-position: 34px 0; } }
  @keyframes kwSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  #kwtool .kw-spin { display: none; width: 12px; height: 12px; margin-left: 6px; vertical-align: -2px; border: 2px solid #4a90d9;
    border-right-color: transparent; border-radius: 50%; animation: kwSpin 0.6s linear infinite; }
  #kwtool .kw-status { margin-top: 8px; }
  #kwtool .kw-stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0 4px; max-width: 880px; }
  #kwtool .kw-stat { flex: 1 1 130px; border: 1px solid #ddd; border-radius: 4px; padding: 8px 10px; color: #555; font-size: 0.9em; }
  #kwtool .kw-stat b { display: block; font-size: 1.6em; color: #333; font-variant-numeric: tabular-nums; }
  #kwtool .kw-stat.kw-warn b { color: #b9770e; }
  #kwtool .kw-stat.kw-err b { color: #c0392b; }
  #kwtool .kw-resume { display: none; margin-top: 10px; padding: 10px; border: 1px solid #a9c8ea; background: #eef5fc; color: #24486e; border-radius: 4px; max-width: 700px; }
  #kwtool .kw-alert { display: none; margin-top: 12px; padding: 10px; border: 1px solid #f0c48a; background: #fff4e8; color: #7a4a11; border-radius: 4px; max-width: 700px; }
  #kwtool table.table2 { margin: 8px 0; min-width: 600px; }
  #kwtool table.table2 td.kw-num { text-align: right; font-variant-numeric: tabular-nums; }
  #kwtool .kw-scroll { max-height: 420px; overflow-y: auto; display: inline-block; }
  #kwtool .kw-box-warn { border: 1px solid #f0c48a; background: #fff8ee; border-radius: 4px; padding: 4px 10px 10px; }
  #kwtool .kw-box-err { border: 1px solid #e0a0a0; background: #fdf3f3; border-radius: 4px; padding: 4px 10px 10px; }
  #kwtool .kw-muted { color: #888; font-size: 0.85em; }
  /* Arbre des albums (repris de synchro_smart) */
  #kwtool .kw-albums-scroll { max-height: 420px; max-width: 560px; overflow: auto; border: 1px solid #ddd; border-radius: 4px; padding: 6px 4px; }
  #kwtool .kw-album-row { padding: 3px 8px; white-space: nowrap; cursor: pointer; border-radius: 3px; }
  #kwtool .kw-album-row:hover { background: #f2f2f2; }
  #kwtool .kw-album-row.kw-selected { background: #eaf2fb; font-weight: bold; }
  #kwtool .kw-album-row.kw-implied { background: #f3f8fd; font-weight: normal; opacity: 0.6; cursor: default; }
  #kwtool .kw-album-meta { color: #888; font-size: 0.85em; margin-left: 8px; font-weight: normal; }
  #kwtool .kw-toggle { display: inline-block; width: 14px; text-align: center; cursor: pointer; color: #888; user-select: none; font-size: 10px; }
  #kwtool .kw-toggle-spacer { display: inline-block; width: 14px; }
  #kwtool .kw-selection { margin: 6px 0 0; color: #555; }
  #kwtool.kw-busy .kw-albums-scroll { opacity: 0.6; pointer-events: none; }
</style>

<div id="kwtool" data-url="{$KW_URL}" data-token="{$KW_TOKEN}">

  <p class="kw-intro">{'kwtool_intro'|@translate}</p>

  <fieldset>
    <legend><i class="icon-cog"></i>{'kwtool_settings'|@translate}</legend>
    <div class="kw-row">
      <label for="kw_root">{'kwtool_root'|@translate}</label>
      <input type="text" id="kw_root" value="{$KW_ROOT|escape:'html'}" size="30">
      <p class="kw-hint">{'kwtool_root_help'|@translate}</p>
    </div>
    <div class="kw-row">
      <label class="kw-plain"><input type="checkbox" id="kw_original" {if $KW_ORIGINAL}checked{/if}> {'kwtool_original'|@translate}</label>
    </div>
    <div class="kw-actions">
      <button type="button" class="buttonLike" id="kw_save"><i class="icon-floppy"></i> {'Enregistrer'|@translate}</button>
      <span id="kw_save_status"></span>
    </div>
  </fieldset>

  <fieldset>
    <legend><i class="icon-search"></i>{'kwtool_scan'|@translate}</legend>
    <p class="kw-hint">{'kwtool_scan_help'|@translate}</p>
    <div class="kw-row">
      <label>{'kwtool_album'|@translate}</label>
      <p class="kw-hint">{'kwtool_albums_help'|@translate}</p>
      <div class="kw-albums-scroll" id="kw_tree">
        {foreach from=$KW_ALBUMS item=album}
        <div class="kw-album-row" data-id="{$album.id}" data-depth="{$album.depth}"{if $album.depth > 0} style="display:none;"{/if}>
          <span style="padding-left:{$album.depth*20}px;">
            {if $album.has_children}<span class="kw-toggle" data-state="collapsed">&#9658;</span>{else}<span class="kw-toggle-spacer"></span>{/if}
            {$album.name|escape:'html'}
            <span class="kw-album-meta">{$album.nb}{if $album.virtual} · {'kwtool_virtual'|@translate}{/if}</span>
          </span>
        </div>
        {/foreach}
      </div>
      <p class="kw-selection"><span id="kw_selection"></span> <a href="#" id="kw_clear" style="display:none">{'kwtool_clear_selection'|@translate}</a></p>
    </div>
    <div class="kw-actions">
      <button type="button" class="buttonLike" id="kw_scan"><i class="icon-search"></i> {'kwtool_scan_button'|@translate}</button>
      <button type="button" class="buttonLike" id="kw_resume" style="display:none"><i class="icon-arrows-cw"></i> {'kwtool_resume'|@translate}</button>
      <button type="button" class="buttonLike" id="kw_stop" style="display:none"><i class="icon-cancel"></i> {'kwtool_stop'|@translate}</button>
      <span class="kw-spin" id="kw_spin"></span>
    </div>
    <div class="kw-resume" id="kw_resume_box"></div>
    <div class="kw-alert" id="kw_alert"></div>
    <div class="kw-progress" id="kw_progress_wrap">
      <div class="kw-track"><div class="kw-fill" id="kw_fill"></div></div>
      <p class="kw-status formHint" id="kw_progress"></p>
    </div>
    <div class="kw-stats" id="kw_stats" style="display:none">
      <div class="kw-stat"><b id="kw_n_scanned">0</b>{'kwtool_stat_scanned'|@translate}</div>
      <div class="kw-stat"><b id="kw_n_photos">0</b>{'kwtool_stat_photos'|@translate}</div>
      <div class="kw-stat"><b id="kw_n_keywords">0</b>{'kwtool_stat_keywords'|@translate}</div>
      <div class="kw-stat"><b id="kw_n_tags">0</b>{'kwtool_stat_tags'|@translate}</div>
      <div class="kw-stat kw-warn"><b id="kw_n_doubts">0</b>{'kwtool_stat_doubts'|@translate}</div>
      <div class="kw-stat kw-err"><b id="kw_n_errors">0</b>{'kwtool_stat_errors'|@translate}</div>
    </div>
  </fieldset>

  <fieldset id="kw_results" style="display:none">
    <legend><i class="icon-ok"></i>{'kwtool_preview'|@translate}</legend>
    <p class="kw-hint">{'kwtool_preview_help'|@translate}</p>
    <div class="kw-scroll">
      <table class="table2" id="kw_albums">
        <thead><tr>
          <th><input type="checkbox" id="kw_all" checked></th>
          <th>{'kwtool_col_album'|@translate}</th>
          <th>{'kwtool_col_photos'|@translate}</th>
          <th>{'kwtool_col_keywords'|@translate}</th>
          <th>{'kwtool_col_tags'|@translate}</th>
          <th>{'kwtool_col_names'|@translate}</th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="kw-actions">
      <button type="button" class="buttonLike" id="kw_write"><i class="icon-pencil"></i> {'kwtool_write_button'|@translate}</button>
      <button type="button" class="buttonLike" id="kw_write_stop" style="display:none"><i class="icon-cancel"></i> {'kwtool_stop'|@translate}</button>
    </div>
    <div class="kw-progress" id="kw_write_wrap">
      <div class="kw-track"><div class="kw-fill" id="kw_write_fill"></div></div>
      <p class="kw-status formHint" id="kw_write_status"></p>
    </div>
    <ul id="kw_write_log" class="kw-muted"></ul>
  </fieldset>

  <fieldset id="kw_doubts_box" style="display:none">
    <legend><i class="icon-attention"></i>{'kwtool_doubts'|@translate}</legend>
    <div class="kw-box-warn">
      <p class="kw-hint">{'kwtool_doubts_help'|@translate}</p>
      <div class="kw-scroll">
        <table class="table2" id="kw_doubts">
          <thead><tr>
            <th>{'kwtool_col_name'|@translate}</th>
            <th>{'kwtool_col_reason'|@translate}</th>
            <th>{'kwtool_col_photos'|@translate}</th>
            <th>{'kwtool_col_examples'|@translate}</th>
          </tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </fieldset>

  <fieldset id="kw_errors_box" style="display:none">
    <legend><i class="icon-cancel-circled"></i>{'kwtool_errors'|@translate}</legend>
    <div class="kw-box-err">
      <p class="kw-hint">{'kwtool_errors_help'|@translate}</p>
      <div class="kw-scroll">
        <table class="table2" id="kw_errors">
          <thead><tr><th>{'kwtool_col_photo'|@translate}</th><th>{'kwtool_col_album'|@translate}</th><th>{'kwtool_col_reason'|@translate}</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </fieldset>
</div>
<script type="text/javascript">
var kwToolLang = {$KW_LANG_JSON};
</script>
