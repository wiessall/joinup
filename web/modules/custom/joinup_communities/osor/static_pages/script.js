/**
 * @file
 */

document.addEventListener("DOMContentLoaded", function () {
  const actions = document.querySelectorAll("[id*='action-']");
  actions.forEach(function (action) {
    const actionId = action.getAttribute("id").split("-")[1];
    const reaction = `reaction-${actionId}`;
    const reactionEl = document.getElementById(reaction);
    const substitude = `substitute-${actionId}`;
    const substitudeEl = document.getElementById(substitude);
    if (reactionEl && substitudeEl) {
      action.addEventListener("mouseover", function () {
        reactionEl.style.display = "block";
        substitudeEl.style.display = "none";
      });
      action.addEventListener("mouseleave", function () {
        reactionEl.style.display = "none";
        substitudeEl.style.display = "block";
      });
    }
  });
});
