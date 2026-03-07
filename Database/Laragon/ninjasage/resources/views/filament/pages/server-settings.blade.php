<x-filament-panels::page>
@php
    $cfg           = \App\Models\GameConfig::get('version_config', []);
    $inMaintenance = (bool) ($cfg['maintenance_mode'] ?? false);
    $serverLabel   = $cfg['server_label']     ?? 'Private Server';
    $versionLabel  = $cfg['version_label']    ?? 'Version 0.1';
    $reqVersion    = $cfg['required_version'] ?? 'Version 0.1';
@endphp

{{-- ── Maintenance banner ──────────────────────────────────────────────────── --}}
@if ($inMaintenance)
<div style="display:flex;align-items:flex-start;gap:16px;padding:18px 22px;margin-bottom:20px;border-radius:12px;border:2px solid #f87171;background:rgba(254,226,226,0.1);">
    <div style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:rgba(248,113,113,0.2);display:flex;align-items:center;justify-content:center;">
        <svg style="width:20px;height:20px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#f87171">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
    </div>
    <div style="flex:1;min-width:0;">
        <p style="margin:0;font-size:15px;font-weight:700;color:#fca5a5;">Maintenance Mode is Active</p>
        <p style="margin:4px 0 0;font-size:13px;color:#f87171;opacity:0.85;">All clients are currently blocked from logging in. Disable maintenance mode below to reopen the server.</p>
    </div>
    <div style="flex-shrink:0;align-self:center;">
        <span style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:rgba(248,113,113,0.2);padding:4px 12px;font-size:11px;font-weight:700;letter-spacing:0.08em;color:#fca5a5;">
            <span style="width:7px;height:7px;border-radius:50%;background:#f87171;display:inline-block;"></span>
            LIVE
        </span>
    </div>
</div>
@endif

{{-- ── Status cards ─────────────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;">

    {{-- Server Name --}}
    <div style="border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,0.15);display:flex;align-items:center;justify-content:center;">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#818cf8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                </svg>
            </div>
            <div style="min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;">Server</p>
                <p style="margin:3px 0 0;font-size:14px;font-weight:700;color:#e5e7eb;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $serverLabel }}</p>
            </div>
        </div>
    </div>

    {{-- Version --}}
    <div style="border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:rgba(139,92,246,0.15);display:flex;align-items:center;justify-content:center;">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#a78bfa">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                </svg>
            </div>
            <div style="min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;">Version</p>
                <p style="margin:3px 0 0;font-size:14px;font-weight:700;color:#a78bfa;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $versionLabel }}</p>
            </div>
        </div>
    </div>

    {{-- Required build_num --}}
    <div style="border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fbbf24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                </svg>
            </div>
            <div style="min-width:0;">
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;">Client build_num</p>
                <p style="margin:3px 0 0;font-size:13px;font-weight:600;font-family:monospace;color:#fcd34d;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $reqVersion }}</p>
            </div>
        </div>
    </div>

    {{-- Status --}}
    @if ($inMaintenance)
    <div style="border-radius:12px;border:1px solid rgba(248,113,113,0.3);background:rgba(248,113,113,0.08);padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:rgba(248,113,113,0.2);display:flex;align-items:center;justify-content:center;">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#f87171">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
            <div>
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;">Status</p>
                <p style="margin:3px 0 0;font-size:14px;font-weight:700;color:#fca5a5;display:flex;align-items:center;gap:6px;">
                    <span style="display:inline-flex;border-radius:50%;width:8px;height:8px;background:#f87171;"></span>
                    Maintenance
                </p>
            </div>
        </div>
    </div>
    @else
    <div style="border-radius:12px;border:1px solid rgba(52,211,153,0.3);background:rgba(52,211,153,0.08);padding:16px 18px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:rgba(52,211,153,0.2);display:flex;align-items:center;justify-content:center;">
                <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#34d399">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:#6b7280;">Status</p>
                <p style="margin:3px 0 0;font-size:14px;font-weight:700;color:#6ee7b7;display:flex;align-items:center;gap:6px;">
                    <span style="display:inline-flex;border-radius:50%;width:8px;height:8px;background:#34d399;"></span>
                    Online
                </p>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- ── Settings form ────────────────────────────────────────────────────────── --}}
<x-filament::section>
    {{ $this->form }}
</x-filament::section>

</x-filament-panels::page>