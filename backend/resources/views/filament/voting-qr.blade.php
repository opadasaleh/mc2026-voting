<div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; text-align: center;">
    <img
        src="data:image/svg+xml;base64,{{ base64_encode($svg) }}"
        alt="QR code for {{ $url }}"
        style="width: 16rem; height: 16rem; background: #fff; border-radius: 0.5rem;"
    >

    <div>
        <p style="font-size: 0.875rem; opacity: 0.7;">Opens</p>
        <p style="font-family: ui-monospace, monospace; font-weight: 600; word-break: break-all;">{{ $url }}</p>
    </div>

    @if ($isLocal)
        <p style="font-size: 0.875rem; color: rgb(220 38 38);">
            This is a localhost address: phones cannot open it. Set FRONTEND_URL (or a non-localhost FRONTEND_URLS entry) in the backend .env.
        </p>
    @else
        <p style="font-size: 0.875rem; opacity: 0.7;">
            Scan it with a phone on the venue Wi-Fi to check before printing. The TV screen shows the same code.
        </p>
    @endif
</div>
