<?php

namespace Manthra\ZomboidWorkshop;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;

class ZomboidWorkshopPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'zomboid-workshop';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "Manthra\\ZomboidWorkshop\\Filament\\$id\\Pages"
        );
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('steam_api_key')
                ->label('Steam Web API Key')
                ->helperText('Necessária só para a busca embutida. Crie a sua em steamcommunity.com/dev/apikey.')
                ->password()
                ->revealable()
                ->default(fn () => config('zomboid-workshop.steam_api_key')),
            TextInput::make('nav_sort')
                ->label('Posição no menu')
                ->numeric()
                ->default(fn () => (int) config('zomboid-workshop.nav_sort', 11)),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'STEAM_WEB_API_KEY' => $data['steam_api_key'],
            'ZOMBOID_WORKSHOP_NAV_SORT' => $data['nav_sort'],
        ]);

        Notification::make()
            ->title('Configurações salvas')
            ->success()
            ->send();
    }
}
