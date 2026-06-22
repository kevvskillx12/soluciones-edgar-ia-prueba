<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AIChat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Asistente de trámites';

    protected static ?string $title = 'Asistente de trámites';

    protected ?string $maxContentWidth = 'full';

    protected static string $view = 'filament.pages.a-i-chat';
}
