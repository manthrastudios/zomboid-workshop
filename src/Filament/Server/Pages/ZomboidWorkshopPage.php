<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Pages;

use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\DeployedModsTable;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\FindModsTable;
use Tevo\ZomboidWorkshop\Filament\Server\Widgets\TestDeployTable;
use Tevo\ZomboidWorkshop\Services\ModListService;

/**
 * A página é um contêiner fino: badges de resumo + abas numeradas na ordem
 * do fluxo ("1 Buscar → 2 Testar → 3 No servidor"), cada aba renderizando
 * seu próprio widget de tabela, sincronizados via evento "zw-mods-updated".
 */
class ZomboidWorkshopPage extends Page
{
    use BlockAccessInConflict;
    use HasTabs;

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

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    /** @return array<string, Tab> Abas numeradas na ordem do fluxo, com contadores. */
    public function getTabs(): array
    {
        $mods = $this->getData()['mods'];
        $queued = count(array_filter($mods, fn ($entry) => static::isCandidate($entry)));
        $active = count(array_filter($mods, fn ($entry) => !empty($entry['enabled']) && !static::isCandidate($entry)));

        return [
            'find' => Tab::make('1 · '.static::t('sections.find'))
                ->icon('tabler-search'),
            'test' => Tab::make('2 · '.static::t('sections.test'))
                ->icon('tabler-flask')
                ->badge($queued ?: null)
                ->badgeColor('warning'),
            'deployed' => Tab::make('3 · '.static::t('sections.deployed'))
                ->icon('tabler-rocket')
                ->badge($active ?: null)
                ->badgeColor('success'),
        ];
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
                $this->getTabsContentComponent(),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return match ($this->activeTab) {
            'test' => [TestDeployTable::class],
            'deployed' => [DeployedModsTable::class],
            default => [FindModsTable::class],
        };
    }
}
