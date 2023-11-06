/**
 * @file
 */

document.addEventListener("DOMContentLoaded", () => {
  const actions = document.querySelectorAll("[id*='action-']");
  actions.forEach((action) => {
    const actionId = action.getAttribute("id").split("-")[1];
    const reaction = `reaction-${actionId}`;
    const reactionEl = document.getElementById(reaction);
    const substitude = `substitute-${actionId}`;
    const substitudeEl = document.getElementById(substitude);
    if (reactionEl && substitudeEl) {
      action.addEventListener("mouseover", () => {
        reactionEl.style.display = "block";
        substitudeEl.style.display = "none";
      });
      action.addEventListener("mouseleave", () => {
        reactionEl.style.display = "none";
        substitudeEl.style.display = "block";
      });
    }
  });
});
