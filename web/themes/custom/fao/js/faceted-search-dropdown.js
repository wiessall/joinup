/* faceted-search-dropdown.js — Drupal 10, Chosen, caret-only expand with capture-phase interception 
THIS IS 100% CHAT GPT! Take this into consideration, I'll need more time to learn the details of js, tbh.
*/
(function (root, factory) { factory(root.Drupal, root.once, root.jQuery); })(typeof self!=='undefined'?self:this, function (Drupal, once, $) {
  'use strict'; if (!Drupal || !once || !$) return;

  const SHOW_INITIAL_DEPTH = 0;                  // 0 = only top-level visible on open
  const TARGET_SELECTOR   = 'select[multiple]';  // enhance all multi-selects

  function looksHierarchical($select) {
    if ($select.hasClass('facet-hierarchy-skip')) return false;
    if ($select.hasClass('facet-hierarchy')) return true;
    const attr = ($select.attr('data-facet-hierarchy') || '').toLowerCase();
    if (attr === 'on' || attr === 'true' || attr === '1') return true;
    let flag = false;
    $select.find('option').each(function () {
      const t = (this.textContent || '').trim();
      if (/^[-–—]+\s*/.test(t)) { flag = true; return false; }
    });
    return flag;
  }

  function buildTree($lis) {
    const stack = [];
    $lis.each(function (idx) {
      const $li = $(this);
      const depth = Number($li.attr('data-depth') || 0);
      const $parent = depth > 0 ? $(stack[depth - 1]) : $();
      $li.attr({ 'data-node-id': 'node-' + idx, 'data-depth': depth }).css('--depth', depth);
      if ($parent.length) { $li.attr('data-parent-id', $parent.attr('data-node-id')); $parent.addClass('has-children'); }
      stack[depth] = this; stack.length = depth + 1;
    });

    // Mark deepest level generically (no hard-coded numbers)
    let maxDepth = 0;
    $lis.each(function () { maxDepth = Math.max(maxDepth, Number($(this).attr('data-depth') || 0)); });
    $lis.each(function () { $(this).toggleClass('is-max-depth', Number($(this).attr('data-depth') || 0) === maxDepth); });
  }

  function addCarets($lis) {
    $lis.each(function () {
      const $li = $(this);

      // Ensure label span exists
      if (!$li.find('.facet-label').length) {
        const txt = $li.text().trim();
        $li.contents().filter(function(){ return this.nodeType === 3; }).first()
          .replaceWith('<span class="facet-label">' + Drupal.checkPlain(txt) + '</span> ');
      }

      // Insert toggle caret for parents; insert a "leaf caret" for leaves
      if ($li.find('.facet-caret').length) return;
      if ($li.hasClass('has-children')) {
        $li.prepend('<span class="facet-caret" role="button" aria-label="Toggle children" aria-expanded="false" tabindex="0"></span>');
      } else {
        // Leaf marker (non-interactive). Bullet is handled via CSS, and only shown for deepest level.
        $li.prepend('<span class="facet-caret facet-caret--leaf" aria-hidden="true"></span>');
      }
    });
  }

  function setInitialCollapse($lis) {
    $lis.each(function () {
      const $li = $(this);
      const depth = Number($li.attr('data-depth') || 0);
      const collapsed = depth > SHOW_INITIAL_DEPTH;
      $li.toggleClass('is-hidden', collapsed);
      if ($li.hasClass('has-children')) {
        const expanded = depth < SHOW_INITIAL_DEPTH; // depth-0 starts collapsed when SHOW_INITIAL_DEPTH=0
        $li.toggleClass('is-collapsed', !expanded)
           .find('> .facet-caret').attr('aria-expanded', String(expanded));
      }
    });
  }

  function getParent($li) {
    const pid = $li.attr('data-parent-id');
    return pid ? $li.closest('ul').find('li[data-node-id="'+pid+'"]') : $();
  }
  function hasCollapsedAncestor($li) {
    let $p = getParent($li);
    while ($p && $p.length) { if ($p.hasClass('is-collapsed')) return true; $p = getParent($p); }
    return false;
  }

  function toggleBranch($li, expand) {
    const depth = Number($li.attr('data-depth') || 0);
    $li.toggleClass('is-collapsed', !expand).find('> .facet-caret').attr('aria-expanded', String(expand));
    let $n = $li.next();
    while ($n.length) {
      const nd = Number($n.attr('data-depth') || 0);
      if (nd <= depth) break;
      if (expand) {
        $n.toggleClass('is-hidden', nd > depth + 1 || hasCollapsedAncestor($n));
      } else {
        $n.addClass('is-hidden');
        if ($n.hasClass('has-children')) { $n.addClass('is-collapsed').find('> .facet-caret').attr('aria-expanded','false'); }
      }
      $n = $n.next();
    }
  }

  function expandForSearch($results, on) {
    if (on) $results.find('li').removeClass('is-hidden'); else setInitialCollapse($results.find('li'));
  }

  Drupal.behaviors.facetChosenTree = {
    attach(context) {
      once('facetChosenTree-any', TARGET_SELECTOR, context).forEach((el) => {
        const $select = $(el);
        if (!looksHierarchical($select)) return;

        $select.on('chosen:showing_dropdown', function () {
          const $container = $select.next('.chosen-container');
          const $results   = $container.find('.chosen-results');
          const $lis       = $results.find('li');

          // Normalize depth/label/count from leading dashes
          $lis.each(function () {
            const $li = $(this);
            const original = $li.data('orig') ?? $li.text();
            if (!$li.data('orig')) $li.data('orig', original);
            const m = original.match(/^(?<dashes>[-–—]+)?\s*(?<label>.*?)(?:\s*\((?<count>\d+)\))?$/);
            if (!m) return;
            const depth = (m.groups.dashes || '').length;
            const label = (m.groups.label || '').trim();
            const count = (m.groups.count || '').trim();
            const safe  = Drupal.checkPlain(label);

            // reset any old depth-* classes; set CSS var for depth (no hard-coding in CSS)
            const cls = ($li.attr('class') || '').replace(/(^|\s)depth-\d+/g, '').trim();
            $li.attr('class', cls)
               .attr('data-depth', depth)
               .css('--depth', depth)
               .addClass('depth-' + depth) // keep if you still use it elsewhere; safe to leave
               .html('<span class="facet-label">' + safe + '</span>' + (count ? ' <span class="facet-count">(' + count + ')</span>' : ''))
               .toggleClass('count-zero', count === '0' && count !== '');
          });

          buildTree($lis);
          addCarets($lis);
          setInitialCollapse($lis);

          // Capture-phase (caret-only) so clicks on caret never select
          const resultsEl = $results.get(0);
          if (resultsEl && !resultsEl.__facetCaretCaptureBound) {
            const handleToggle = (evt) => {
              const t = evt.target; if (!t || !t.closest) return;
              const caret = t.closest('.facet-caret');
              // ignore non-caret and leaf caret (non-interactive)
              if (!caret || caret.classList.contains('facet-caret--leaf')) return;

              evt.preventDefault(); evt.stopPropagation(); if (evt.stopImmediatePropagation) evt.stopImmediatePropagation();
              const li = caret.closest('li'); if (!li) return;
              toggleBranch($(li), $(li).hasClass('is-collapsed'));
            };
            resultsEl.addEventListener('mousedown', handleToggle, true);
            resultsEl.addEventListener('mouseup',   handleToggle, true);
            resultsEl.addEventListener('click',     handleToggle, true);
            resultsEl.addEventListener('keydown', (evt) => {
              const t = evt.target; if (!t || !t.closest) return;
              const caret = t.closest('.facet-caret');
              if (!caret || caret.classList.contains('facet-caret--leaf')) return;
              if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault(); evt.stopPropagation(); if (evt.stopImmediatePropagation) evt.stopImmediatePropagation();
                const li = caret.closest('li'); if (li) toggleBranch($(li), $(li).hasClass('is-collapsed'));
              }
            }, true);
            resultsEl.__facetCaretCaptureBound = true;
          }

          // Expand while searching
          const $search = $container.find('.chosen-search input');
          $search.off('input.facetFilter').on('input.facetFilter', function () {
            expandForSearch($results, $(this).val().trim().length > 0);
          });
        });

        // Clean selected pills (remove leading dashes)
        $select.on('change', function () {
          const $container = $select.next('.chosen-container');
          $container.find('.chosen-choices .search-choice span').each(function () {
            $(this).text($(this).text().replace(/^[\-–—]+\s*/, ''));
          });
        });
      });
    }
  };
});
