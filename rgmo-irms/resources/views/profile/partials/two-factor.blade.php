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
            
            <button id="enable-2fa" class="btn btn-cmu px-4">Enable 2FA</button>

            <div id="2fa-setup" class="mt-4 d-none p-4 rounded border bg-light">
                <div id="qr-container" class="mb-3 text-center"></div>
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
            </div>
        @endif
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableBtn = document.getElementById('enable-2fa');
    const setupDiv = document.getElementById('2fa-setup');
    const qrContainer = document.getElementById('qr-container');
    const confirmForm = document.getElementById('confirm-2fa-form');
    const disableForm = document.getElementById('disable-2fa-form');

    function getCsrf() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    if (enableBtn) {
        enableBtn.addEventListener('click', async function() {
            enableBtn.disabled = true;
            try {
                const res = await fetch('{{ route('2fa.enable') }}', { headers: { 'Accept': 'application/json' }});
                const data = await res.json();
                const otpauth = encodeURIComponent(data.otpauth_url);
                qrContainer.innerHTML = `<img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${otpauth}" alt="QR" class="img-thumbnail shadow-sm"/>`;
                setupDiv.classList.remove('d-none');
            } catch (error) {
                console.error('Error enabling 2FA:', error);
            } finally {
                enableBtn.disabled = false;
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
            location.reload();
        });
    }

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