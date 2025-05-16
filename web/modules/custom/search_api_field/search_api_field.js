(function ($, Drupal, drupalSettings, once) {
  // Define the rowHandlers object first.
  const facetUIRowHandlers = {
    field(row, data) {
      this.row = row;
      this.name = data.name;
      this.region = data.region;
      this.tableDrag = data.tableDrag;
      this.$regionSelect = $(row).find("select.field-region");
      $(row)
        .find("select, input")
        .on("change", Drupal.facetUIOverview.onChange);
      return this;
    },
  };

  facetUIRowHandlers.field.prototype = {
    getRegion() {
      return this.$regionSelect.length ? this.$regionSelect[0].value : "hidden";
    },

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

  Drupal.behaviors.facetUIDisplayOverview = {
    attach(context, settings) {
      once(
        "facet-display-overview",
        "table#facet-display-overview",
        context,
      ).forEach((overview) => {
        Drupal.facetUIOverview.attach(
          overview,
          settings.fieldUIRowsData || {},
          facetUIRowHandlers,
        );
      });
    },
  };

  Drupal.facetUIOverview = {
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

            const handlerClass = rowHandlers[data.rowHandler];
            if (typeof handlerClass === "function") {
              const rowHandler = new handlerClass(row, data);
              $(row).data("facetUIRowHandler", rowHandler);
            }
          }
        });
    },

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

      Drupal.facetUIOverview.AJAXRefreshRows(refreshRows);
    },

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
          Drupal.facetUIOverview.AJAXRefreshRows(refreshRows);
        }
      }
    },

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

    AJAXRefreshRows(rows) {
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
})(jQuery, Drupal, drupalSettings, once);
