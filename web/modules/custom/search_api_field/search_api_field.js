/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @param once
 * @file
 * Attaches the behaviors for the Search API field module.
 */

(function ($, Drupal, drupalSettings, once) {
  /**
   * Attaches the searchApiFieldDisplayOverview behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the searchApiFieldDisplayOverview behavior.
   *
   * @see Drupal.searchApiFieldDisplayOverview.attach
   */
  Drupal.behaviors.searchApiFieldDisplayOverview = {
    attach(context, settings) {
      once(
        "facet-display-overview",
        "table#facet-display-overview",
        context,
      ).forEach((overview) => {
        Drupal.searchApiFieldOverview.attach(
          overview,
          settings.fieldUIRowsData || {},
          Drupal.searchApiFieldDisplayOverview,
        );
      });
    },
  };

  // Override the beforeSend method to disable the submit button until
  // the AJAX request is completed. This is done to avoid the race
  // condition that is being caused by change event listener that is
  // attached to every form element inside field storage config edit
  // form to update the field config form based on changes made to the
  // storage settings.
  const originalAjaxBeforeSend = Drupal.Ajax.prototype.beforeSend;
  // eslint-disable-next-line func-names
  Drupal.Ajax.prototype.beforeSend = function () {
    // eslint-disable-next-line prefer-rest-params
    return originalAjaxBeforeSend.apply(this, arguments);
  };

  /**
   * Namespace for the field UI overview.
   *
   * @namespace
   */
  Drupal.searchApiFieldOverview = {
    /**
     * Attaches the fieldUIOverview behavior.
     *
     * @param {HTMLTableElement} table
     *   The table element for the overview.
     * @param {object} rowsData
     *   The data of the rows in the table.
     * @param {object} rowHandlers
     *   Handlers to be added to the rows.
     */
    attach(table, rowsData, rowHandlers) {
      const tableDrag = Drupal.tableDrag[table.id];
      tableDrag.onDrop = this.onDrop;
      tableDrag.row.prototype.onSwap = this.onSwap;

      $(table)
        .find("tr.draggable")
        .each(function () {
          const row = this;
          const rowId = row.id;

          if (rowId in rowsData) {
            const data = rowsData[rowId];
            data.tableDrag = tableDrag;

            // Create the row handler, make it accessible from the DOM row
            // element.
            const rowHandler = new rowHandlers[data.rowHandler](row, data);
            $(row).data("facetUIRowHandler", rowHandler);
          }
        });
    },

    /**
     * Event handler to be attached to form inputs triggering a region change.
     */
    onChange() {
      const $trigger = $(this);
      const $row = $trigger.closest("tr");
      if ($trigger.closest(".ajax-new-content").length) return;

      const rowHandler = $row.data("facetUIRowHandler");
      if (!rowHandler) return;

      const refreshRows = {};
      refreshRows[rowHandler.name] = $trigger.get(0);

      const region = rowHandler.getRegion();
      if (region !== rowHandler.region) {
        const $parentSelect = $row.find("select.js-field-parent");
        if ($parentSelect.length) {
          $parentSelect.val("");
        }
        $.extend(refreshRows, rowHandler.regionChange(region));
        rowHandler.region = region;
      }

      Drupal.searchApiFieldOverview.ajaxRefreshRows(refreshRows);
    },

    /**
     * Lets row handlers react when a row is dropped into a new region.
     */
    onDrop() {
      const row = this.rowObject.element;
      const $row = $(row);
      const rowHandler = $row.data("facetUIRowHandler");

      if (rowHandler) {
        const regionRow = $row.prevAll("tr.region-message").get(0);
        const region = regionRow.className.replace(
          /([^ ]+[ ]+)*region-([^ ]+)-message([ ]+[^ ]+)*/,
          "$2",
        );

        if (region !== rowHandler.region) {
          const refreshRows = rowHandler.regionChange(region);
          rowHandler.region = region;
          Drupal.searchApiFieldOverview.ajaxRefreshRows(refreshRows);
        }
      }
    },

    /**
     * Refreshes placeholder rows in empty regions while a row is being dragged.
     *
     * Copied from block.js.
     *
     * @param {HTMLElement} draggedRow
     *   The tableDrag rowObject for the row being dragged.
     */
    onSwap(draggedRow) {
      const rowObject = this;
      $(rowObject.table)
        .find("tr.region-message")
        .each(function () {
          const $msgRow = $(this);
          if (
            $msgRow.prev("tr").get(0) ===
            rowObject.group[rowObject.group.length - 1]
          ) {
            if (
              rowObject.method !== "keyboard" ||
              rowObject.direction === "down"
            ) {
              rowObject.swap("after", this);
            }
          }
          const next = $msgRow.next("tr");
          if (next.length === 0 || !next.hasClass("draggable")) {
            $msgRow.removeClass("region-populated").addClass("region-empty");
          } else if ($msgRow.hasClass("region-empty")) {
            $msgRow.removeClass("region-empty").addClass("region-populated");
          }
        });
    },

    /**
     * Triggers Ajax refresh of selected rows.
     *
     * The 'format type' selects can trigger a series of changes in child rows.
     * The #ajax behavior is therefore not attached directly to the selects, but
     * triggered manually through a hidden #ajax 'Refresh' button.
     *
     * @param {object} rows
     *   A hash object, whose keys are the names of the rows to refresh (they
     *   will receive the 'ajax-new-content' effect on the server side), and
     *   whose values are the DOM element in the row that should get an Ajax
     *   throbber.
     */
    ajaxRefreshRows(rows) {
      const rowNames = [];
      const ajaxElements = [];

      Object.keys(rows || {}).forEach((rowName) => {
        rowNames.push(rowName);
        ajaxElements.push(rows[rowName]);
      });

      if (rowNames.length) {
        const $refreshInput = $('input[name="wrapper[refresh_rows]"]');
        if ($refreshInput.length) {
          $refreshInput.val(rowNames.join(" "));
        }
        $('input[data-drupal-selector="edit-wrapper-refresh"]').trigger(
          "mousedown",
        );
      }
    },
  };

  /**
   * Row handlers for the 'Manage display' screen.
   *
   * @namespace
   */
  Drupal.searchApiFieldDisplayOverview = {};

  /**
   * Constructor for a 'field' row handler.
   *
   * This handler is used for both fields and 'extra fields' rows.
   *
   * @class
   *
   * @param {HTMLTableRowElement} row
   *   The row DOM element.
   * @param {object} data
   *   Additional data to be populated in the constructed object.
   *
   * @return {Drupal.searchApiFieldDisplayOverview.field}
   *   The field row handler constructed.
   */
  Drupal.searchApiFieldDisplayOverview.field = function (row, data) {
    this.row = row;
    this.name = data.name;
    this.region = data.region;
    this.tableDrag = data.tableDrag;
    this.$regionSelect = $(row).find("select.field-region");
    $(row)
      .find("select, input")
      .on("change", Drupal.searchApiFieldOverview.onChange);
    return this;
  };

  /**
   * Constructor for a 'field' row handler.
   *
   * This handler is used for both fields and 'extra fields' rows.
   *
   * @class
   *
   * @param {HTMLTableRowElement} row
   *   The row DOM element.
   * @param {object} data
   *   Additional data to be populated in the constructed object.
   *
   * @return {Drupal.fieldUIDisplayOverview.field}
   *   The field row handler constructed.
   */
  Drupal.searchApiFieldDisplayOverview.field.prototype = {
    /**
     * Returns the region corresponding to the current form values of the row.
     *
     * @return {string}
     *   Either 'hidden' or 'content'.
     */
    getRegion() {
      return this.$regionSelect.length ? this.$regionSelect[0].value : "hidden";
    },

    /**
     * Reacts to a row being changed regions.
     *
     * This function is called when the row is moved to a different region, as
     * a
     * result of either :
     * - a drag-and-drop action (the row's form elements then probably need to
     * be updated accordingly)
     * - user input in one of the form elements watched by the
     *   {@link Drupal.searchApiFieldOverview.onChange} change listener.
     *
     * @param {string} region
     *   The name of the new region for the row.
     *
     * @return {object}
     *   A hash object indicating which rows should be Ajax-updated as a result
     *   of the change, in the format expected by
     *   {@link Drupal.searchApiFieldOverview.ajaxRefreshRows}.
     */
    regionChange(region) {
      region = region.replace(/-/g, "_");
      if (this.$regionSelect.length) {
        this.$regionSelect[0].value = region;
      }

      const refreshRows = {};
      refreshRows[this.name] = this.$regionSelect.get(0);
      return refreshRows;
    },
  };
})(jQuery, Drupal, drupalSettings, once);
