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

    const frame = document.createElement('iframe');
    frame.className = 'device-preview-dialog__frame';
    frame.title = 'Responsive preview';

    const canvas = document.createElement('div');
    canvas.className = 'device-preview-canvas';
    canvas.append(frame);
    dialog.append(canvas);
    document.body.append(toolbar, dialog);

    const fitPreview = () => {
        if (dialog.dataset.mode !== 'mobile') {
            frame.style.transform = '';
            canvas.style.width = '';
            canvas.style.height = '';
            return;
        }

        const availableWidth = dialog.clientWidth - 32;
        const availableHeight = dialog.clientHeight - 32;
        const scale = Math.min(1, availableWidth / 390, availableHeight / 844);

        frame.style.transform = `scale(${scale})`;
        frame.style.transformOrigin = 'top left';
        canvas.style.width = `${390 * scale}px`;
        canvas.style.height = `${844 * scale}px`;
    };

    const closePreview = () => {
        dialog.hidden = true;
        frame.src = 'about:blank';
        document.body.classList.remove('device-preview-open');
    };

    const openPreview = (mode, button) => {
        const wasOpen = !dialog.hidden;
        const modeChanged = dialog.dataset.mode !== mode;
        dialog.dataset.mode = mode;
        for (const control of toolbar.querySelectorAll('.device-preview-toolbar__button')) {
            control.setAttribute('aria-pressed', String(control === button));
        }

        if (!wasOpen || modeChanged) {
            frame.src = window.location.href;
        }

        dialog.hidden = false;
        document.body.classList.add('device-preview-open');
        fitPreview();
    };

    const viewportQuery = window.matchMedia('(max-width: 767px)');
    const button = document.createElement('button');
    button.className = 'device-preview-toolbar__button';
    button.type = 'button';
    toolbar.append(button);

    const updatePreviewButton = () => {
        const mode = viewportQuery.matches ? 'desktop' : 'mobile';
        button.dataset.mode = mode;
        button.textContent = dialog.hidden ? (mode === 'desktop' ? 'Desktop' : 'Mobile') : 'Normal';
        button.setAttribute('aria-label', dialog.hidden ? `Preview in ${mode} view` : 'Return to normal view');
        button.setAttribute('aria-pressed', String(!dialog.hidden));
    };

    button.addEventListener('click', () => {
        if (dialog.hidden) {
            openPreview(button.dataset.mode, button);
        } else {
            closePreview();
        }
        updatePreviewButton();
    });

    updatePreviewButton();
    viewportQuery.addEventListener('change', updatePreviewButton);
    window.addEventListener('resize', fitPreview);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.hidden) {
            closePreview();
            updatePreviewButton();
        }
    });
}
