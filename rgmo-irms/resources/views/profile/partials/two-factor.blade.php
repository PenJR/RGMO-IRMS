<section>
    <header class="mb-4">
        <h3 class="h5 fw-bold mb-1">
            {{ __('Two-Factor Authentication') }}
        </h3>

        <p class="text-muted small mb-0">
            {{ __('Add an extra layer of security to your account using an authenticator app (Google Authenticator, Authy, etc.).') }}
        </p>
    </header>

    <div id="2fa-status" class="mt-4">
        @if(auth()->user()->two_factor_enabled)
            <div class="alert alert-success d-flex align-items-center mb-4">
                <i data-lucide="shield-check" class="me-3" style="width: 24px"></i>
                <div>Two-factor authentication is currently <strong>enabled</strong>.</div>
            </div>

            <form id="disable-2fa-form" method="POST" action="{{ route('2fa.disable') }}" class="mt-4">
                @csrf
                <div class="row items-bottom">
                    <div class="col-md-6">
                        <label for="disable-password" class="form-label fw-semibold small">Confirm Password to Disable</label>
                        <input id="disable-password" name="password" type="password" required class="form-control" />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-danger w-100">Disable 2FA</button>
                    </div>
                </div>
            </form>
        @else
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i data-lucide="shield-alert" class="me-3" style="width: 24px"></i>
                <div>Two-factor authentication is currently <strong>disabled</strong>.</div>
            </div>
            
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="enable-2fa-password" class="form-label fw-semibold small">Confirm Password to Start</label>
                    <input id="enable-2fa-password" type="password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-md-4">
                    <button id="enable-2fa" type="button" class="btn btn-cmu px-4 w-100">Enable 2FA</button>
                </div>
            </div>

            <div id="2fa-setup" class="mt-4 d-none p-4 rounded border bg-light">
                <div id="qr-container" class="mb-3 text-center"></div>
                <div class="two-factor-secret-panel mb-3">
                    <div>
                        <span class="fw-semibold">Manual setup key:</span>
                        <code id="2fa-manual-secret" class="two-factor-secret-value ms-1" aria-live="polite">•••• •••• •••• ••••</code>
                        <div id="2fa-secret-timer" class="two-factor-secret-timer d-none" aria-live="polite">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <span>Key automatically hides in</span>
                                <strong id="2fa-secret-countdown">10:00</strong>
                            </div>
                            <div class="two-factor-secret-progress" aria-hidden="true">
                                <span id="2fa-secret-progress-bar"></span>
                            </div>
                        </div>
                    </div>
                    <button id="reveal-2fa-secret" type="button" class="btn btn-sm btn-outline-secondary">Reveal key</button>
                </div>
                <p class="small text-muted mb-3">Scan the QR code with your authenticator app, then enter the code below to confirm setup.</p>
                <form id="confirm-2fa-form" class="row g-2">
                    @csrf
                    <div class="col-md-8">
                        <input id="2fa-code" name="code" type="text" required class="form-control" placeholder="6-digit code" />
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-cmu w-100">Confirm</button>
                    </div>
                </form>
                <div id="2fa-recovery-codes" class="alert alert-warning mt-3 d-none" role="status">
                    <strong>Save these one-time recovery codes now.</strong>
                    <p class="small mb-2">They will not be shown again.</p>
                    <pre id="2fa-recovery-code-list" class="mb-0 user-select-all"></pre>
                    <button id="finish-2fa-setup" type="button" class="btn btn-sm btn-dark mt-3">I saved them</button>
                </div>
            </div>
        @endif
    </div>

    <dialog id="2fa-secret-dialog" class="two-factor-secret-dialog">
        <form id="2fa-secret-reveal-form">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <h4 class="h5 fw-bold mb-1">Reveal manual setup key</h4>
                    <p class="small text-muted mb-0">Confirm your current password. The key will be visible for 10 minutes.</p>
                </div>
                <button id="close-2fa-secret-dialog" type="button" class="btn-close" aria-label="Close"></button>
            </div>
            <label for="2fa-reveal-password" class="form-label">Current password</label>
            <input id="2fa-reveal-password" type="password" class="form-control" required autocomplete="current-password">
            <div id="2fa-reveal-error" class="text-danger small mt-2 d-none" role="alert"></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button id="cancel-2fa-secret-dialog" type="button" class="btn btn-outline-secondary">Cancel</button>
                <button id="confirm-2fa-secret-reveal" type="submit" class="btn btn-cmu">Confirm and reveal</button>
            </div>
        </form>
    </dialog>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableBtn = document.getElementById('enable-2fa');
    const setupDiv = document.getElementById('2fa-setup');
    const enablePassword = document.getElementById('enable-2fa-password');
    const qrContainer = document.getElementById('qr-container');
    const manualSecret = document.getElementById('2fa-manual-secret');
    const secretTimerDisplay = document.getElementById('2fa-secret-timer');
    const secretCountdown = document.getElementById('2fa-secret-countdown');
    const secretProgressBar = document.getElementById('2fa-secret-progress-bar');
    const revealSecretButton = document.getElementById('reveal-2fa-secret');
    const revealDialog = document.getElementById('2fa-secret-dialog');
    const revealForm = document.getElementById('2fa-secret-reveal-form');
    const revealPassword = document.getElementById('2fa-reveal-password');
    const revealError = document.getElementById('2fa-reveal-error');
    const confirmRevealButton = document.getElementById('confirm-2fa-secret-reveal');
    const confirmForm = document.getElementById('confirm-2fa-form');
    const disableForm = document.getElementById('disable-2fa-form');
    let secretTimer = null;

    function getCsrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function hideManualSecret() {
        if (secretTimer) {
            clearInterval(secretTimer);
            secretTimer = null;
        }

        if (manualSecret) manualSecret.textContent = '•••• •••• •••• ••••';
        if (secretTimerDisplay) secretTimerDisplay.classList.add('d-none');
        if (secretCountdown) secretCountdown.textContent = '10:00';
        if (secretProgressBar) secretProgressBar.style.width = '100%';
        if (revealSecretButton) revealSecretButton.textContent = 'Reveal key';
    }

    function revealManualSecret(secret, expiresIn) {
        const expiresAt = Date.now() + (expiresIn * 1000);
        manualSecret.textContent = secret;
        manualSecret.classList.add('user-select-all');
        secretTimerDisplay.classList.remove('d-none');
        revealSecretButton.textContent = 'Hide key';

        const updateCountdown = () => {
            const secondsLeft = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            const minutes = Math.floor(secondsLeft / 60);
            const seconds = String(secondsLeft % 60).padStart(2, '0');
            secretCountdown.textContent = `${minutes}:${seconds}`;
            secretProgressBar.style.width = `${(secondsLeft / expiresIn) * 100}%`;

            if (secondsLeft === 0) hideManualSecret();
        };

        updateCountdown();
        secretTimer = setInterval(updateCountdown, 1000);
    }

    if (enableBtn) {
        enableBtn.addEventListener('click', async function() {
            enableBtn.disabled = true;
            try {
                const res = await fetch('{{ route('2fa.enable') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ password: enablePassword.value })
                });
                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.message || 'Unable to start two-factor authentication setup.');
                }

                const qrDataUrl = await window.QRCode.toDataURL(data.otpauth_url, {
                    errorCorrectionLevel: 'M',
                    margin: 2,
                    width: 220,
                });
                const qrImage = document.createElement('img');
                qrImage.src = qrDataUrl;
                qrImage.alt = 'Authenticator setup QR code';
                qrImage.className = 'img-thumbnail shadow-sm';
                qrContainer.replaceChildren(qrImage);
                hideManualSecret();
                setupDiv.classList.remove('d-none');
            } catch (error) {
                console.error('Error enabling 2FA:', error);
                alert(error.message || 'Unable to start two-factor authentication setup.');
            } finally {
                enableBtn.disabled = false;
            }
        });
    }

    if (revealSecretButton) {
        revealSecretButton.addEventListener('click', function() {
            if (secretTimer) {
                hideManualSecret();
                return;
            }

            revealError.classList.add('d-none');
            revealError.textContent = '';
            revealPassword.value = '';
            revealDialog.showModal();
            window.setTimeout(() => revealPassword.focus(), 50);
        });
    }

    ['close-2fa-secret-dialog', 'cancel-2fa-secret-dialog'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => revealDialog.close());
    });

    if (revealForm) {
        revealForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            confirmRevealButton.disabled = true;
            revealError.classList.add('d-none');

            try {
                const res = await fetch('{{ route('2fa.reveal-secret') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ password: revealPassword.value })
                });
                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.message || 'Unable to reveal the setup key.');
                }

                revealDialog.close();
                revealManualSecret(data.secret, data.expires_in || 600);
            } catch (error) {
                revealError.textContent = error.message || 'Unable to reveal the setup key.';
                revealError.classList.remove('d-none');
            } finally {
                confirmRevealButton.disabled = false;
            }
        });
    }

    if (confirmForm) {
        confirmForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const code = document.getElementById('2fa-code').value;
            const res = await fetch('{{ route('2fa.confirm') }}', { 
                method: 'POST', 
                headers: { 
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN': getCsrf(), 
                    'Accept':'application/json' 
                }, 
                body: JSON.stringify({ code }) 
            });
            const j = await res.json();
            if (!res.ok) {
                alert(j.message || 'Invalid code');
                return;
            }
            const recoveryPanel = document.getElementById('2fa-recovery-codes');
            const recoveryList = document.getElementById('2fa-recovery-code-list');
            recoveryList.textContent = (j.recovery_codes || []).join('\n');
            recoveryPanel.classList.remove('d-none');
            confirmForm.classList.add('d-none');
        });
    }

    document.getElementById('finish-2fa-setup')?.addEventListener('click', () => location.reload());

    if (disableForm) {
        disableForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const pw = document.getElementById('disable-password').value;
            const res = await fetch('{{ route('2fa.disable') }}', { 
                method: 'POST', 
                headers: { 
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN': getCsrf(), 
                    'Accept':'application/json' 
                }, 
                body: JSON.stringify({ password: pw }) 
            });
            const j = await res.json();
            if (!res.ok) { 
                alert(j.message || 'Unable to disable'); 
                return; 
            }
            location.reload();
        });
    }
    
    // Refresh icons if any were added
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
