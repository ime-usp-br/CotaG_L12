<x-filament-widgets::widget>
    <style>
        :root {
            --bg-color: white;
            --border-color: #e5e7eb;
            --text-color: #111827;
            --text-muted: #6b7280;
            --badge-bg: #f3f4f6;
            --badge-text: #374151;
        }
        .dark {
            --bg-color: #1f2937;
            --border-color: #374151;
            --text-color: white;
            --text-muted: #9ca3af;
            --badge-bg: #374151;
            --badge-text: #d1d5db;
        }
    </style>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
        @foreach ($this->getNavigationCards() as $card)
            <a href="{{ $card['url'] }}"
               style="display: block;
                      padding: 24px;
                      background: var(--bg-color, white);
                      border-radius: 12px;
                      border: 2px solid var(--border-color, #e5e7eb);
                      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                      text-decoration: none;
                      transition: all 0.2s;"
               onmouseover="this.style.borderColor='#1094AB'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';"
               onmouseout="this.style.borderColor='var(--border-color, #e5e7eb)'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';">

                {{-- Header --}}
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    {{-- Icon container --}}
                    <div style="flex-shrink: 0; padding: 10px; border-radius: 8px; background-color: {{ $card['color'] === 'primary' ? 'rgba(16, 148, 171, 0.1)' : ($card['color'] === 'success' ? 'rgba(16, 185, 129, 0.1)' : ($card['color'] === 'warning' ? 'rgba(252, 180, 33, 0.1)' : ($card['color'] === 'info' ? 'rgba(59, 130, 246, 0.1)' : ($card['color'] === 'indigo' ? 'rgba(79, 70, 229, 0.1)' : ($card['color'] === 'fuchsia' ? 'rgba(162, 28, 175, 0.1)' : ($card['color'] === 'teal' ? 'rgba(13, 148, 136, 0.1)' : 'rgba(107, 114, 128, 0.1)'))) ))) }};">                        <svg style="width: 20px; height: 20px; color: {{ $card['color'] === 'primary' ? '#1094AB' : ($card['color'] === 'success' ? '#10b981' : ($card['color'] === 'warning' ? '#FCB421' : ($card['color'] === 'info' ? '#3b82f6' : ($card['color'] === 'indigo' ? '#4f46e5' : ($card['color'] === 'fuchsia' ? '#a21caf' : ($card['color'] === 'teal' ? '#0d9488' : '#6b7280'))) ))) }};"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24"
                             stroke-width="2">
                            @if($card['icon'] === 'heroicon-o-users')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            @elseif($card['icon'] === 'heroicon-o-shield-check')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            @elseif($card['icon'] === 'heroicon-o-key')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            @elseif($card['icon'] === 'heroicon-o-document-text')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            {{-- Ícone para "Cotas" (heroicon-o-rectangle-stack) --}}
                            @elseif($card['icon'] === 'heroicon-o-rectangle-stack')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />

                            {{-- Ícone para "Cotas Especiais" (heroicon-o-star) --}}
                            @elseif($card['icon'] === 'heroicon-o-star')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />

                            {{-- Ícone para "Extrato" (heroicon-o-clipboard-document-list) --}}
                            @elseif($card['icon'] === 'heroicon-o-clipboard-document-list')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />

                            @endif
                        </svg>
                    </div>

                    {{-- Badge --}}
                    <div style="flex-shrink: 0; padding: 6px 12px; font-size: 12px; font-weight: bold; border-radius: 999px; background-color: var(--badge-bg, #f3f4f6); color: var(--badge-text, #374151);">
                        {{ $card['stats'] }}
                    </div>
                </div>

                {{-- Title --}}
                <h3 style="font-size: 18px; font-weight: bold; color: var(--text-color, #111827); margin-bottom: 8px;">
                    {{ $card['title'] }}
                </h3>

                {{-- Description --}}
                <p style="font-size: 14px; color: var(--text-muted, #6b7280); margin-bottom: 16px; line-height: 1.5;">
                    {{ $card['description'] }}
                </p>

                {{-- Footer --}}
                <div style="display: flex; align-items: center; font-size: 13px; font-weight: 600; color: {{ $card['color'] === 'primary' ? '#1094AB' : ($card['color'] === 'success' ? '#10b981' : ($card['color'] === 'warning' ? '#FCB421' : '#3b82f6')) }};">
                    <span>Acessar</span>
                    <svg style="width: 14px; height: 14px; margin-left: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
