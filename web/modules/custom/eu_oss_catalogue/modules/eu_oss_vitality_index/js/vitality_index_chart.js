/**
 * @file
 * Draws the OSS Solution vitality index chart.
 */

((Drupal, once) => {
  Drupal.behaviors.joinupEuOssCatalogueVitalityIndexChart = {
    attach(context) {
      once(
        "vitality-index-chart",
        "canvas.js-vitality-index-chart",
        context,
      ).forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.vitalityIndexRange);
        const ctx = canvas.getContext("2d");
        ctx.canvas.width = 490;
        ctx.canvas.height = 80;
        const labels = [...Array(60)].map((x, i) => i.toString());

        // eslint-disable-next-line no-new
        new Chart(ctx, {
          type: "line",
          data: {
            labels,
            datasets: [
              {
                fill: false,
                tension: 0.1,
                data,
                borderWidth: 3,
                borderColor: "#06c06c",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                display: false,
                stacked: true,
              },
              y: {
                display: false,
                stacked: true,
                beginAtZero: true,
              },
            },
            plugins: {
              legend: {
                display: false,
              },
              tooltip: {
                enabled: false,
              },
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
          },
        });
      });
    },
  };
})(Drupal, once);
