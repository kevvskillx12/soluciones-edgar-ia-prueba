<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AIChat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Chat';

    protected static string $view = 'filament.pages.a-i-chat';
}