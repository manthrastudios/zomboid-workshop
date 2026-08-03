<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Pages;

use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Livewire\Attributes\On;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\DeployedModsTable;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\FindModsTable;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\TestDeployTable;
use Tevo\ZomboidWorkshop\Services\ModListService;

/**
 * A página é um contêiner fino: badges de resumo + as três tabelas do
 * fluxo (Buscar → Testar → No servidor), cada uma um widget Livewire
 * próprio que se sincroniza via evento "zw-mods-updated".
 */
class ZomboidWorkshopPage extends Page
{
    use BlockAccessInConflict;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?string $slug = 'workshop-mods';

    /** @var array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, homologation: array<string, mixed>}|null */
    protected ?array $data = null;

    protected static function t(string $key, array $replace = []): string
    {
        return trans('zomboid-workshop::strings.'.$key, $replace);
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('zomboid-workshop.nav_sort', 11);
    }

    public static function getNavigationLabel(): string
    {
        return static::t('nav_label');
    }

    public function getTitle(): string
    {
        return static::t('title');
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        // o tenant pode vir nulo em alguns ciclos de hidratação do Livewire
        if (!$server instanceof Server) {
            return false;
        }

        return parent::canAccess() && static::isZomboidServer($server);
    }

    public static function isZomboidServer(Server $server): bool
    {
        $server->loadMissing('egg');

        $features = $server->egg->features ?? [];
        if (in_array('zomboid_workshop', $features)) {
            return true;
        }

        return str_contains(strtolower($server->egg->name ?? ''), 'zomboid');
    }

    /** @return array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, homologation: array<string, mixed>} */
    protected function getData(): array
    {
        if ($this->data === null) {
            /** @var Server $server */
            $server = Filament::getTenant();
            $this->data = app(ModListService::class)->load($server);
        }

        return $this->data;
    }

    #[On('zw-mods-updated')]
    public function refreshBadges(): void
    {
        $this->data = null;
    }

    protected static function isCandidate(array $entry): bool
    {
        return ($entry['status'] ?? null) === 'candidate';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('total')
                            ->label(static::t('badges.total'))
                            ->state(fn () => count($this->getData()['mods']))
                            ->badge()
                            ->size(TextSize::Large),
                        TextEntry::make('ativos')
                            ->label(static::t('badges.active'))
                            ->state(fn () => count(array_filter($this->getData()['mods'], fn ($entry) => !empty($entry['enabled']) && !static::isCandidate($entry))))
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large),
                        TextEntry::make('candidatos')
                            ->label(static::t('badges.candidates'))
                            ->state(fn () => count(array_filter($this->getData()['mods'], fn ($entry) => static::isCandidate($entry))))
                            ->badge()
                            ->color('warning')
                            ->size(TextSize::Large),
                        TextEntry::make('extras')
                            ->label(static::t('badges.loose'))
                            ->state(fn () => count($this->getData()['extra_mod_ids']))
                            ->badge()
                            ->color('gray')
                            ->size(TextSize::Large),
                    ]),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            FindModsTable::class,
            TestDeployTable::class,
            DeployedModsTable::class,
        ];
    }
}
