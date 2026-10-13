{{-- Revision preview: notice bar over the page so nobody mistakes it for the current state --}}
<div role="status" style="position: fixed; left: 50%; bottom: 1.25rem; z-index: 2147483000; transform: translateX(-50%); display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; max-width: calc(100vw - 2rem); padding: 0.625rem 0.75rem 0.625rem 1rem; border-radius: 0.75rem; background: #111827; color: #F9FAFB; font: 500 0.875rem/1.4 system-ui, sans-serif; box-shadow: 0 10px 30px rgba(0,0,0,0.35);">
    <span>{{ $notice['text'] }}</span>
    <a href="{{ $notice['url'] }}" style="padding: 0.375rem 0.75rem; border-radius: 0.5rem; background: #F9FAFB; color: #111827; text-decoration: none; font-weight: 600;">{{ $notice['back'] }}</a>
</div>
