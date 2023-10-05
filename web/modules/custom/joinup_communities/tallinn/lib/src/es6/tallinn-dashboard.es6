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
  NodeList.prototype.forEach = function(callback, thisArg) {
    thisArg = thisArg || window;
    for (var i = 0; i < this.length; i++) {
      callback.call(thisArg, this[i], i, this);
    }
  };
}

const init = (
  dataSet,
  highchartContainerId,
  dropdownIds,
  csvButtonClass,
  dropdownContainersClass,
  dropdownClass,
  titleClass,
  descriptionClass,
  bodyClass,
  allMembersString,
  seriesColors,
  legendClass,
  legendData,
  statuses
) => {
  let currPrinciple;
  let state = dropdownIds.reduce(
    (a, b) => Object.assign({}, a, { [b]: "" }),
    {}
  );

  const chart = Highcharts.chart(highchartContainerId, {
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
      plotLines: legendData.map((l, i) => ({
        color: l.color,
        width: 2,
        value: i
      })),
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
        columnHeaderFormatter: function(series, key) {
          return series.name;
        }
      }
    },

    navigation: {
      buttonOptions: {
        enabled: false
      }
    },

    tooltip: {
      valueDecimals: 0,
      followPointer: true,
      shared: true,
      formatter: function() {
        var s = currPrinciple
          ? dataSet[currPrinciple].actions[this.points[0].point.index]
              .explanation
          : dataSet[this.points[0].point.index].description;

        this.points.forEach(function(current) {
          s +=
            "<br/><b>" +
            current.series.name +
            ":</b> " +
            statuses[Math.round(current.y)];
        });
        return s;
      },
      shared: true
    },
    plotOptions: {
      series: {
        fillOpacity: 0.4
      }
    },

    legend: {
      enabled: false
    },

    responsive: {
      rules: [
        {
          condition: {
            maxWidth: 480
          },
          // Make the labels less space demanding on mobile
          chartOptions: {
            xAxis: {
              labels: {
                formatter: function() {
                  return String(this.value).substring(0, 10) + "...";
                }
              }
            }
          }
        }
      ]
    }
  });

  const setState = newState => {
    state = Object.assign({}, state, newState);
    return state;
  };

  const setLabels = index => {
    currPrinciple = index;
    document.querySelector(titleClass).innerHTML = "";
    document.querySelector(bodyClass).innerHTML = "";
    document.querySelector(descriptionClass).innerHTML = "";

    if (index !== null) {
      if (index === "") {
        chart.setTitle({ text: "" });
        chart.xAxis[0].setCategories(dataSet.map(el => el.principle));
      } else {
        chart.setTitle({ text: dataSet[index].principle });
        chart.xAxis[0].setCategories(
          dataSet[index].actions.map(el => el.title)
        );

        document.querySelector(bodyClass).insertAdjacentHTML(
          "beforeend",
          `<h3>${d1.options[d1.selectedIndex].innerText}</h3>
          <p>${dataSet[index].description}</p>`
        );

        dataSet[index].actions.map(el =>
          Object.keys(el.countries).forEach(key => {
            if (key == d1.options[d1.selectedIndex].value) {
              let relatedWebsite =
                el.countries[key].related_website === null
                  ? "[not provided]"
                  : '<a href="' +
                    el.countries[key].related_website +
                    '">' +
                    el.countries[key].related_website +
                    "</a>";
              document.querySelector(bodyClass).insertAdjacentHTML(
                "beforeend",
                `<p><strong>${el.title} - ${el.explanation}</strong></p>
                <p>${el.countries[key].report}</p>
                <p>Status:
                    <span class="${el.countries[key].status
                      .toLowerCase()
                      .replace(" ", "-")}">
                    ${el.countries[key].status}
                    </span>
                </p>
                <p>Related website: ${relatedWebsite}</p>`
              );
            }
          })
        );
      }
    }
  };

  const getIndex = str => statuses.indexOf(str);

  const computeAllAverage = data =>
    Object.keys(data).reduce((a, b) => a + getIndex(data[b].status), 0) /
    Object.keys(data).length;

  const setSeries = () => {
    let data1 = [];
    let data2 = [];

    if (state[dropdownIds[2]] === "") {
      // All Principles - calculate the average
      dataSet.forEach(e => {
        let total1 = 0;
        let total2 = 0;

        Object.keys(e.actions).forEach(z => {
          total1 +=
            state[dropdownIds[0]] !== allMembersString
              ? getIndex(e.actions[z].countries[state[dropdownIds[0]]].status)
              : computeAllAverage(e.actions[z].countries);
          // test for all member states
          if (state[dropdownIds[1]]) {
            total2 +=
              state[dropdownIds[1]] !== allMembersString
                ? getIndex(e.actions[z].countries[state[dropdownIds[1]]].status)
                : computeAllAverage(e.actions[z].countries);
          }
        });

        data1.push(
          parseFloat((total1 / Object.keys(e.actions).length).toFixed(1))
        );
        data2.push(
          parseFloat((total2 / Object.keys(e.actions).length).toFixed(1))
        );
      });
    } else {
      // on a selected principle
      Object.keys(dataSet[state[dropdownIds[2]]].actions).forEach(z => {
        data1.push(
          state[dropdownIds[0]] !== allMembersString
            ? getIndex(
                dataSet[state[dropdownIds[2]]].actions[z].countries[
                  state[dropdownIds[0]]
                ].status
              )
            : computeAllAverage(
                dataSet[state[dropdownIds[2]]].actions[z].countries
              )
        );
        if (state[dropdownIds[1]]) {
          data2.push(
            state[dropdownIds[1]] !== allMembersString
              ? getIndex(
                  dataSet[state[dropdownIds[2]]].actions[z].countries[
                    state[dropdownIds[1]]
                  ].status
                )
              : computeAllAverage(
                  dataSet[state[dropdownIds[2]]].actions[z].countries
                )
          );
        }
      });
    }

    // Remove all previous series
    for (let i = chart.series.length - 1; i > -1; i--) {
      chart.series[i].remove();
    }

    const d1 = document.getElementById(dropdownIds[0]);
    const d2 = document.getElementById(dropdownIds[1]);

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
      name:
        "Numeric values are averages, calculated with the formula: (Sum of each action status value in principle) / (number of actions in principle). The action status values are as follows: No Data = 1, No progress = 2, In progress = 3, Completed = 4. The numerical values are rounded as follows: 1.00 - 1.49 = No data, 1.50 - 2.49 = No progress, 2.50 - 3.49 = In progress, 3.50 - 4.00 = Completed.",
      data: []
    });
  };

  // add colors for country labels
  document
    .querySelectorAll(`${dropdownContainersClass} span`)
    .forEach((el, i) => (el.style["background-color"] = seriesColors[i]));

  // populate Principles inside 3rd dropdown
  dataSet.forEach((k, i) => {
    let opt = document.createElement("option");
    opt.value = i;
    opt.innerHTML = k.principle;
    document.querySelector(`#${dropdownIds[2]}`).appendChild(opt);
  });

  // populate the legend
  legendData.reverse().map(l => {
    let li = document.createElement("li");
    let s = document.createElement("span");

    li.innerHTML = l.label;
    s.style.cssText = `background-color: ${l.color}`;
    li.insertBefore(s, null);
    document.querySelector(`${legendClass} ul`).appendChild(li);
  });

  // Init categories based on the 3rd dropdown
  const d1 = document.getElementById(dropdownIds[0]);
  setLabels(document.querySelector(`#${dropdownIds[2]}`).value);

  // add event listeners
  document.querySelectorAll(dropdownClass).forEach(el => {
    // Read the defaults if any
    setState({
      [el.id]: el.value
    });

    // Add an event listeners to selects
    el.addEventListener("change", evt => {
      setState({
        [evt.currentTarget.id]: evt.currentTarget.value
      });

      // Principle dropdown, change categories and tiles
      if (evt.currentTarget.id === dropdownIds[2]) {
        setLabels(evt.currentTarget.value);
      }

      if (evt.currentTarget.id === dropdownIds[0]) {
        const d1 = document.getElementById(dropdownIds[0]);
        setLabels(document.querySelector(`#${dropdownIds[2]}`).value);
      }

      // redraw
      setSeries();
    });
  });

  // add CSV download event listener
  document.querySelector(csvButtonClass).addEventListener("click", e => {
    chart.downloadCSV();
  });

  return {
    dataSet,
    chart,
    state,
    setState,
    setLabels,
    setSeries
  };
};

(drupalSettings => {
  const req = new XMLHttpRequest();
  req.overrideMimeType("application/json");
  req.open("GET", drupalSettings.tallinn.dataEndpoint, true);
  req.onload = () => {
    const TALLINNHI = init(
      JSON.parse(req.responseText),
      "tallinn-chart__container",
      ["select1", "select2", "select3"],
      ".tallinn-chart__button--csv",
      ".tallinn-chart__selector-container",
      ".tallinn-chart__selector",
      ".tallinn-chart__country",
      ".tallinn-chart__description",
      ".tallinn-chart__body",
      "All members",
      ["#0399FB", "#F13601"],
      ".tallinn-chart__legend",
      [
        { color: "", label: "" },
        { color: "#aaa", label: "No data" },
        { color: "#faa", label: "No progress" },
        { color: "#fc0", label: "In progress" },
        { color: "#BC7", label: "Completed" }
      ],
      ["", "No data", "No progress", "In progress", "Completed"]
    );

    // Draw
    TALLINNHI.setSeries();
  };
  req.send();
})(drupalSettings);
