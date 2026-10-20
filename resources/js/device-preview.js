if (window.self === window.top) {
    const toolbar = document.createElement('div');
    toolbar.className = 'device-preview-toolbar';
    toolbar.setAttribute('aria-label', 'Responsive preview controls');

    const label = document.createElement('span');
    label.className = 'device-preview-toolbar__label';
    label.textContent = 'Preview';
    toolbar.append(label);

    const dialog = document.createElement('section');
    dialog.className = 'device-preview-dialog';
    dialog.hidden = true;
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-label', 'Responsive page preview');

    const header = document.createElement('header');
    header.className = 'device-preview-dialog__header';

    const title = document.createElement('strong');
    title.className = 'device-preview-dialog__title';

    const closeButton = document.createElement('button');
    closeButton.className = 'device-preview-dialog__close';
    closeButton.type = 'button';
    closeButton.textContent = 'Close';
    closeButton.setAttribute('aria-label', 'Close responsive preview');

    const frame = document.createElement('iframe');
    frame.className = 'device-preview-dialog__frame';
    frame.title = 'Responsive preview';

    header.append(title, closeButton);
    dialog.append(header, frame);
    document.body.append(toolbar, dialog);

    let activeButton = null;

    const closePreview = () => {
        dialog.hidden = true;
        frame.src = 'about:blank';
        document.body.classList.remove('device-preview-open');
        activeButton?.focus();
    };

    const openPreview = (mode, button) => {
        activeButton = button;
        dialog.dataset.mode = mode;
        title.textContent = mode === 'mobile' ? 'Mobile · 390 × 844' : 'Desktop · 1440 × 900';
        frame.src = window.location.href;
        dialog.hidden = false;
        document.body.classList.add('device-preview-open');
        closeButton.focus();
    };

    for (const [mode, text] of [['desktop', 'Desktop'], ['mobile', 'Mobile']]) {
        const button = document.createElement('button');
        button.className = 'device-preview-toolbar__button';
        button.type = 'button';
        button.textContent = text;
        button.addEventListener('click', () => openPreview(mode, button));
        toolbar.append(button);
    }

    closeButton.addEventListener('click', closePreview);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closePreview();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.hidden) closePreview();
    });
}
