<div>
    <h3 class="text-lg font-medium">Two-Factor Authentication</h3>
    <p class="mt-1 text-sm text-gray-600">Add an extra layer of security to your account using an authenticator app (Google Authenticator, Authy, etc.).</p>

    <div id="2fa-status" class="mt-4">
        @if(auth()->user()->two_factor_enabled)
            <p class="text-green-600">Two-factor authentication is currently <strong>enabled</strong>.</p>
            <form id="disable-2fa-form" method="POST" action="{{ route('2fa.disable') }}">
                @csrf
                <label for="disable-password">Confirm Password to Disable</label>
                <input id="disable-password" name="password" type="password" required class="form-input" />
                <button type="submit" class="btn btn-outline-danger mt-2">Disable 2FA</button>
            </form>
        @else
            <p class="text-yellow-600">Two-factor authentication is currently <strong>disabled</strong>.</p>
            <button id="enable-2fa" class="btn btn-cmu mt-2">Enable 2FA</button>

            <div id="2fa-setup" class="mt-4 hidden">
                <div id="qr-container"></div>
                <p class="mt-2">Scan the QR code with your authenticator app, then enter the code below to confirm.</p>
                <form id="confirm-2fa-form">
                    @csrf
                    <input id="2fa-code" name="code" type="text" required class="form-input" />
                    <button type="submit" class="btn btn-cmu mt-2">Confirm & Enable</button>
                </form>
            </div>
        @endif
    </div>
</div>

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
            const res = await fetch('{{ route('2fa.enable') }}', { headers: { 'Accept': 'application/json' }});
            const data = await res.json();
            const otpauth = encodeURIComponent(data.otpauth_url);
            qrContainer.innerHTML = `<img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${otpauth}" alt="QR"/>`;
            setupDiv.classList.remove('hidden');
            enableBtn.disabled = false;
        });
    }

    if (confirmForm) {
        confirmForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const code = document.getElementById('2fa-code').value;
            const res = await fetch('{{ route('2fa.confirm') }}', { method: 'POST', headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': getCsrf(), 'Accept':'application/json' }, body: JSON.stringify({ code }) });
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
            const res = await fetch('{{ route('2fa.disable') }}', { method: 'POST', headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': getCsrf(), 'Accept':'application/json' }, body: JSON.stringify({ password: pw }) });
            const j = await res.json();
            if (!res.ok) { alert(j.message || 'Unable to disable'); return; }
            location.reload();
        });
    }
});
</script>