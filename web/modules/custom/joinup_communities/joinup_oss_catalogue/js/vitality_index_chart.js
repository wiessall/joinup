/**
 * @file
 * Draws the OSS Solution vitality index chart.
 */

((once) => {
  Drupal.behaviors.joinupEuOssCatalogueVitalityIndexChart = {
    attach(context) {
      once(
        "vitality-index-chart",
        "canvas#vitality-index-chart",
        context
      ).forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.vitalityIndexRange);
        const ctx = canvas.getContext("2d");
        ctx.canvas.width = 230;
        ctx.canvas.height = 38;
        const labels = [...Array(60)].map((x, i) => i.toString());

        // eslint-disable-next-line no-new
        new Chart(ctx, {
          responsive: false,
          type: "line",
          data: {
            labels,
            datasets: [
              {
                fill: false,
                lineTension: 0.1,
                data,
                borderWidth: 3,
                borderColor: "#06c06c",
              },
            ],
          },
          options: {
            scales: {
              xAxes: [
                {
                  display: false,
                  stacked: true,
                },
              ],
              yAxes: [
                {
                  display: false,
                  stacked: true,
                  ticks: {
                    beginAtZero: true,
                  },
                },
              ],
            },
            legend: {
              display: false,
            },
            elements: {
              point: {
                radius: 0,
                hoverRadius: 0,
                hoverBorderWidth: 0,
              },
            },
            layout: {
              padding: {
                left: 5,
                right: 5,
                top: 5,
                bottom: 5,
              },
            },
            tooltips: {
              enabled: false,
            },
          },
        });
      });
    },
  };
})(once);
