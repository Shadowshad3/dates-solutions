document.addEventListener('DOMContentLoaded', () => {
  const selectors = document.querySelectorAll('[data-service-selector]');

  selectors.forEach((selector) => {
    const buttons = selector.querySelectorAll('[data-service-selector-button]');
    const panels = selector.querySelectorAll('[data-service-selector-panel]');

    if (!buttons.length || !panels.length) {
      return;
    }

    const activatePanel = (targetId) => {
      buttons.forEach((button) => {
        const isActive = button.dataset.target === targetId;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        const isActive = panel.id === targetId;
        panel.classList.toggle('is-active', isActive);

        if (isActive) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', 'hidden');
        }
      });
    };

    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        activatePanel(button.dataset.target);
      });
    });

    const firstButton = buttons[0];
    if (firstButton) {
      activatePanel(firstButton.dataset.target);
    }
  });
});