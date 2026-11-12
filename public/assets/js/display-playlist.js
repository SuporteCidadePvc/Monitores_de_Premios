(() => {
    const shell = document.querySelector('[data-playlist]');

    if (! shell) {
        return;
    }

    const status = document.querySelector('[data-playlist-status]');
    const pauseToggle = document.querySelector('[data-playlist-toggle]');
    const pauseToggleLabel = document.querySelector('[data-playlist-toggle-label]');
    const screenSelectors = document.querySelectorAll('[data-playlist-screen-selector]');
    const controls = document.querySelector('[data-display-controls]');
    const timing = document.querySelector('[data-playlist-timing]');
    const countdown = document.querySelector('[data-playlist-countdown]');
    const nextLabel = document.querySelector('[data-playlist-next-label]');
    const progress = document.querySelector('[data-playlist-progress]');
    const serverEpochMilliseconds = Number(shell.dataset.serverEpochMilliseconds);
    const startedAtClientMilliseconds = Date.now();
    const sectorKey = document.body.dataset.sector || 'default';
    const pauseStorageKey = `monitor-playlist-paused:${sectorKey}`;
    const screenLabels = Object.fromEntries(
        Array.from(screenSelectors, (screenSelector) => [
            screenSelector.dataset.playlistScreen,
            screenSelector.textContent.trim(),
        ]),
    );
    let pendingSynchronization;
    let pendingProgressUpdate;
    let controlsTimer;
    let paused = false;

    let steps;

    try {
        steps = JSON.parse(shell.dataset.playlistSteps || '[]');
    } catch {
        return;
    }

    if (! Number.isFinite(serverEpochMilliseconds) || ! Array.isArray(steps) || steps.length === 0) {
        return;
    }

    const cycleDurationSeconds = steps.reduce(
        (total, step) => total + Number(step.duration_seconds || 0),
        0,
    );

    if (! Number.isInteger(cycleDurationSeconds) || cycleDurationSeconds < 1) {
        return;
    }

    const updateControls = () => {
        document.body.dataset.playlistPaused = paused ? 'true' : 'false';

        if (pauseToggle) {
            pauseToggle.setAttribute('aria-pressed', String(paused));
            pauseToggle.setAttribute('aria-label', paused ? 'Retomar playlist' : 'Pausar playlist');
        }

        if (pauseToggleLabel) {
            pauseToggleLabel.textContent = paused ? 'Retomar' : 'Pausar';
        }
    };

    const updateStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const showControls = () => {
        if (! controls) {
            return;
        }

        controls.classList.remove('is-hidden');
        window.clearTimeout(controlsTimer);
        controlsTimer = window.setTimeout(() => controls.classList.add('is-hidden'), 4000);
    };

    const persistPauseState = () => {
        try {
            window.sessionStorage.setItem(pauseStorageKey, String(paused));
        } catch {
            // The current page can still be paused when session storage is unavailable.
        }
    };

    const setPaused = (value) => {
        paused = value;
        persistPauseState();
        updateControls();

        if (paused) {
            window.clearTimeout(pendingSynchronization);
            stopProgressUpdates();
            showPausedTiming();
            updateStatus('Playlist pausada neste ecrã.');
        }
    };

    try {
        paused = window.sessionStorage.getItem(pauseStorageKey) === 'true';
    } catch {
        paused = false;
    }

    updateControls();

    const serverNowMilliseconds = () => serverEpochMilliseconds + (Date.now() - startedAtClientMilliseconds);

    const activeStep = () => {
        const seconds = Math.floor(serverNowMilliseconds() / 1000);
        const secondInCycle = ((seconds % cycleDurationSeconds) + cycleDurationSeconds) % cycleDurationSeconds;
        let elapsedSeconds = 0;

        for (const step of steps) {
            elapsedSeconds += Number(step.duration_seconds);

            if (secondInCycle < elapsedSeconds) {
                return { step, elapsedSeconds, secondInCycle };
            }
        }

        return null;
    };

    const stopProgressUpdates = () => {
        window.clearInterval(pendingProgressUpdate);
    };

    const showPausedTiming = () => {
        if (timing) {
            timing.dataset.state = 'paused';
        }

        if (countdown) {
            countdown.textContent = 'Playlist pausada';
        }

        if (nextLabel) {
            nextLabel.textContent = 'Pausado nesta tela';
        }
    };

    const labelForScreen = (screen) => screenLabels[screen] || (screen === 'comunicado' ? 'Comunicado' : screen);

    const updateNextLabel = (active) => {
        if (! nextLabel) {
            return;
        }

        const activeStepIndex = steps.findIndex((step) => step.screen === active.step.screen);
        const nextStep = steps[(activeStepIndex + 1) % steps.length];

        nextLabel.textContent = `Próxima: ${labelForScreen(nextStep.screen)}`;
    };

    const updateTiming = () => {
        if (paused) {
            showPausedTiming();
            return;
        }

        const active = activeStep();

        if (! active) {
            return;
        }

        const durationMilliseconds = Number(active.step.duration_seconds) * 1000;
        const cycleMilliseconds = cycleDurationSeconds * 1000;
        const currentMilliseconds = serverNowMilliseconds();
        const millisecondInCycle = ((currentMilliseconds % cycleMilliseconds) + cycleMilliseconds) % cycleMilliseconds;
        const stepStartedAtMilliseconds = (active.elapsedSeconds * 1000) - durationMilliseconds;
        const progressPercentage = Math.min(100, Math.max(0, ((millisecondInCycle - stepStartedAtMilliseconds) / durationMilliseconds) * 100));
        const millisecondsUntilChange = Math.max(1, (active.elapsedSeconds * 1000) - millisecondInCycle);

        if (timing) {
            timing.dataset.state = 'active';
        }

        if (countdown) {
            countdown.textContent = `Próxima tela em ${Math.ceil(millisecondsUntilChange / 1000)} s`;
        }

        updateNextLabel(active);

        if (progress) {
            progress.style.width = `${progressPercentage}%`;
        }
    };

    const startProgressUpdates = () => {
        stopProgressUpdates();
        updateTiming();
        pendingProgressUpdate = window.setInterval(updateTiming, 250);
    };

    const synchronize = () => {
        window.clearTimeout(pendingSynchronization);

        if (paused) {
            stopProgressUpdates();
            showPausedTiming();
            updateStatus('Playlist pausada neste ecrã.');
            return;
        }

        const active = activeStep();

        if (! active) {
            return;
        }

        const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
        const targetPath = new URL(active.step.url, window.location.origin).pathname.replace(/\/$/, '') || '/';

        if (currentPath !== targetPath) {
            stopProgressUpdates();
            updateStatus('A atualizar o painel sincronizado…');
            window.location.replace(active.step.url);
            return;
        }

        const secondsUntilChange = active.elapsedSeconds - active.secondInCycle;

        updateStatus(`Playlist sincronizada — próxima etapa em ${secondsUntilChange} s.`);
        startProgressUpdates();
        pendingSynchronization = window.setTimeout(synchronize, (secondsUntilChange * 1000) + 120);
    };

    if (pauseToggle) {
        pauseToggle.addEventListener('click', () => {
            setPaused(! paused);

            if (! paused) {
                synchronize();
            }
        });
    }

    screenSelectors.forEach((screenSelector) => {
        screenSelector.addEventListener('click', (event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            setPaused(true);

            if (window.location.href !== screenSelector.href) {
                window.location.assign(screenSelector.href);
            }
        });
    });

    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            synchronize();
        }
    });

    document.addEventListener('mousemove', showControls);
    document.addEventListener('touchstart', showControls, { passive: true });
    document.addEventListener('keydown', showControls);

    if (controls) {
        controls.addEventListener('click', showControls);
    }

    window.addEventListener('pageshow', synchronize, { once: true });
    showControls();
})();
