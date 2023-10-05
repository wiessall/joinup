"use strict";

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

// Polyfill Object.assign()
if (typeof Object.assign != "function") {
  // Must be writable: true, enumerable: false, configurable: true
  Object.defineProperty(Object, "assign", {
    value: function assign(target, varArgs) {
      // .length of function is 2
      "use strict";

      if (target == null) {
        // TypeError if undefined or null
        throw new TypeError("Cannot convert undefined or null to object");
      }

      var to = Object(target);

      for (var index = 1; index < arguments.length; index++) {
        var nextSource = arguments[index];

        if (nextSource != null) {
          // Skip over if undefined or null
          for (var nextKey in nextSource) {
            // Avoid bugs when hasOwnProperty is shadowed
            if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
              to[nextKey] = nextSource[nextKey];
            }
          }
        }
      }
      return to;
    },
    writable: true,
    configurable: true
  });
}
// Polyfill NodeList.prototype.forEach()
if (window.NodeList && !NodeList.prototype.forEach) {
  NodeList.prototype.forEach = function (callback, thisArg) {
    thisArg = thisArg || window;
    for (var i = 0; i < this.length; i++) {
      callback.call(thisArg, this[i], i, this);
    }
  };
}

var init = function init(dataSet, highchartContainerId, dropdownIds, csvButtonClass, dropdownContainersClass, dropdownClass, titleClass, descriptionClass, bodyClass, allMembersString, seriesColors, legendClass, legendData, statuses) {
  var currPrinciple = void 0;
  var state = dropdownIds.reduce(function (a, b) {
    return Object.assign({}, a, _defineProperty({}, b, ""));
  }, {});

  var chart = Highcharts.chart(highchartContainerId, {
    chart: {
      polar: true,
      type: "area",
      marginLeft: 70,
      marginRight: 70
    },

    title: {
      text: ""
    },

    pane: {
      size: "90%",
      startAngle: 0
    },

    xAxis: {
      allowDecimals: false,
      categories: [],
      tickmarkPlacement: "on",
      lineWidth: 0
    },

    yAxis: {
      allowDecimals: false,
      plotLines: legendData.map(function (l, i) {
        return {
          color: l.color,
          width: 2,
          value: i
        };
      }),
      labels: {
        enabled: false
      },
      gridLineDashStyle: "dot",
      gridLineInterpolation: "polygon",
      lineWidth: 0,
      max: 4,
      min: 0
    },

    exporting: {
      csv: {
        itemDelimiter: ";",
        columnHeaderFormatter: function columnHeaderFormatter(series, key) {
          return series.name;
        }
      }
    },

    navigation: {
      buttonOptions: {
        enabled: false
      }
    },

    tooltip: _defineProperty({
      valueDecimals: 0,
      followPointer: true,
      shared: true,
      formatter: function formatter() {
        var s = currPrinciple ? dataSet[currPrinciple].actions[this.points[0].point.index].explanation : dataSet[this.points[0].point.index].description;

        this.points.forEach(function (current) {
          s += "<br/><b>" + current.series.name + ":</b> " + statuses[Math.round(current.y)];
        });
        return s;
      }
    }, "shared", true),
    plotOptions: {
      series: {
        fillOpacity: 0.4
      }
    },

    legend: {
      enabled: false
    },

    responsive: {
      rules: [{
        condition: {
          maxWidth: 480
        },
        // Make the labels less space demanding on mobile
        chartOptions: {
          xAxis: {
            labels: {
              formatter: function formatter() {
                return String(this.value).substring(0, 10) + "...";
              }
            }
          }
        }
      }]
    }
  });

  var setState = function setState(newState) {
    state = Object.assign({}, state, newState);
    return state;
  };

  var setLabels = function setLabels(index) {
    currPrinciple = index;
    document.querySelector(titleClass).innerHTML = "";
    document.querySelector(bodyClass).innerHTML = "";
    document.querySelector(descriptionClass).innerHTML = "";

    if (index !== null) {
      if (index === "") {
        chart.setTitle({ text: "" });
        chart.xAxis[0].setCategories(dataSet.map(function (el) {
          return el.principle;
        }));
      } else {
        chart.setTitle({ text: dataSet[index].principle });
        chart.xAxis[0].setCategories(dataSet[index].actions.map(function (el) {
          return el.title;
        }));

        document.querySelector(bodyClass).insertAdjacentHTML("beforeend", "<h3>" + d1.options[d1.selectedIndex].innerText + "</h3>\n          <p>" + dataSet[index].description + "</p>");

        dataSet[index].actions.map(function (el) {
          return Object.keys(el.countries).forEach(function (key) {
            if (key == d1.options[d1.selectedIndex].value) {
              var relatedWebsite = el.countries[key].related_website === null ? "[not provided]" : '<a href="' + el.countries[key].related_website + '">' + el.countries[key].related_website + "</a>";
              document.querySelector(bodyClass).insertAdjacentHTML("beforeend", "<p><strong>" + el.title + " - " + el.explanation + "</strong></p>\n                <p>" + el.countries[key].report + "</p>\n                <p>Status:\n                    <span class=\"" + el.countries[key].status.toLowerCase().replace(" ", "-") + "\">\n                    " + el.countries[key].status + "\n                    </span>\n                </p>\n                <p>Related website: " + relatedWebsite + "</p>");
            }
          });
        });
      }
    }
  };

  var getIndex = function getIndex(str) {
    return statuses.indexOf(str);
  };

  var computeAllAverage = function computeAllAverage(data) {
    return Object.keys(data).reduce(function (a, b) {
      return a + getIndex(data[b].status);
    }, 0) / Object.keys(data).length;
  };

  var setSeries = function setSeries() {
    var data1 = [];
    var data2 = [];

    if (state[dropdownIds[2]] === "") {
      // All Principles - calculate the average
      dataSet.forEach(function (e) {
        var total1 = 0;
        var total2 = 0;

        Object.keys(e.actions).forEach(function (z) {
          total1 += state[dropdownIds[0]] !== allMembersString ? getIndex(e.actions[z].countries[state[dropdownIds[0]]].status) : computeAllAverage(e.actions[z].countries);
          // test for all member states
          if (state[dropdownIds[1]]) {
            total2 += state[dropdownIds[1]] !== allMembersString ? getIndex(e.actions[z].countries[state[dropdownIds[1]]].status) : computeAllAverage(e.actions[z].countries);
          }
        });

        data1.push(parseFloat((total1 / Object.keys(e.actions).length).toFixed(1)));
        data2.push(parseFloat((total2 / Object.keys(e.actions).length).toFixed(1)));
      });
    } else {
      // on a selected principle
      Object.keys(dataSet[state[dropdownIds[2]]].actions).forEach(function (z) {
        data1.push(state[dropdownIds[0]] !== allMembersString ? getIndex(dataSet[state[dropdownIds[2]]].actions[z].countries[state[dropdownIds[0]]].status) : computeAllAverage(dataSet[state[dropdownIds[2]]].actions[z].countries));
        if (state[dropdownIds[1]]) {
          data2.push(state[dropdownIds[1]] !== allMembersString ? getIndex(dataSet[state[dropdownIds[2]]].actions[z].countries[state[dropdownIds[1]]].status) : computeAllAverage(dataSet[state[dropdownIds[2]]].actions[z].countries));
        }
      });
    }

    // Remove all previous series
    for (var i = chart.series.length - 1; i > -1; i--) {
      chart.series[i].remove();
    }

    var d1 = document.getElementById(dropdownIds[0]);
    var d2 = document.getElementById(dropdownIds[1]);

    // Add new ones
    chart.addSeries({
      name: d1.options[d1.selectedIndex].text,
      data: data1,
      color: seriesColors[0]
    });
    chart.addSeries({
      name: d2.options[d2.selectedIndex].text,
      data: data2,
      color: seriesColors[1]
    });
    chart.addSeries({
      name: "Numeric values are averages, calculated with the formula: (Sum of each action status value in principle) / (number of actions in principle). The action status values are as follows: No Data = 1, No progress = 2, In progress = 3, Completed = 4. The numerical values are rounded as follows: 1.00 - 1.49 = No data, 1.50 - 2.49 = No progress, 2.50 - 3.49 = In progress, 3.50 - 4.00 = Completed.",
      data: []
    });
  };

  // add colors for country labels
  document.querySelectorAll(dropdownContainersClass + " span").forEach(function (el, i) {
    return el.style["background-color"] = seriesColors[i];
  });

  // populate Principles inside 3rd dropdown
  dataSet.forEach(function (k, i) {
    var opt = document.createElement("option");
    opt.value = i;
    opt.innerHTML = k.principle;
    document.querySelector("#" + dropdownIds[2]).appendChild(opt);
  });

  // populate the legend
  legendData.reverse().map(function (l) {
    var li = document.createElement("li");
    var s = document.createElement("span");

    li.innerHTML = l.label;
    s.style.cssText = "background-color: " + l.color;
    li.insertBefore(s, null);
    document.querySelector(legendClass + " ul").appendChild(li);
  });

  // Init categories based on the 3rd dropdown
  var d1 = document.getElementById(dropdownIds[0]);
  setLabels(document.querySelector("#" + dropdownIds[2]).value);

  // add event listeners
  document.querySelectorAll(dropdownClass).forEach(function (el) {
    // Read the defaults if any
    setState(_defineProperty({}, el.id, el.value));

    // Add an event listeners to selects
    el.addEventListener("change", function (evt) {
      setState(_defineProperty({}, evt.currentTarget.id, evt.currentTarget.value));

      // Principle dropdown, change categories and tiles
      if (evt.currentTarget.id === dropdownIds[2]) {
        setLabels(evt.currentTarget.value);
      }

      if (evt.currentTarget.id === dropdownIds[0]) {
        var _d = document.getElementById(dropdownIds[0]);
        setLabels(document.querySelector("#" + dropdownIds[2]).value);
      }

      // redraw
      setSeries();
    });
  });

  // add CSV download event listener
  document.querySelector(csvButtonClass).addEventListener("click", function (e) {
    chart.downloadCSV();
  });

  return {
    dataSet: dataSet,
    chart: chart,
    state: state,
    setState: setState,
    setLabels: setLabels,
    setSeries: setSeries
  };
};

(function (drupalSettings) {
  var req = new XMLHttpRequest();
  req.overrideMimeType("application/json");
  req.open("GET", drupalSettings.tallinn.dataEndpoint, true);
  req.onload = function () {
    var TALLINNHI = init(JSON.parse(req.responseText), "tallinn-chart__container", ["select1", "select2", "select3"], ".tallinn-chart__button--csv", ".tallinn-chart__selector-container", ".tallinn-chart__selector", ".tallinn-chart__country", ".tallinn-chart__description", ".tallinn-chart__body", "All members", ["#0399FB", "#F13601"], ".tallinn-chart__legend", [{ color: "", label: "" }, { color: "#aaa", label: "No data" }, { color: "#faa", label: "No progress" }, { color: "#fc0", label: "In progress" }, { color: "#BC7", label: "Completed" }], ["", "No data", "No progress", "In progress", "Completed"]);

    // Draw
    TALLINNHI.setSeries();
  };
  req.send();
})(drupalSettings);