<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Traits\Filament\BlockAccessInConflict;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Tevo\ZomboidWorkshop\Services\ModListService;
use Tevo\ZomboidWorkshop\Services\SteamWorkshopService;

class ZomboidWorkshopPage extends Page implements HasTable
{
    use BlockAccessInConflict;
    use HasTabs;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-packages';

    protected static ?string $slug = 'workshop-mods';

    /** @var array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>}|null */
    protected ?array $data = null;

    protected static function t(string $key, array $replace = []): string
    {
        return trans('zomboid-workshop::strings.'.$key, $replace);
    }

    /** 1234 → "1.2k", 5600000 → "5.6M" (sem depender da extensão intl) */
    protected static function compactNumber(int $number): string
    {
        return match (true) {
            $number >= 1_000_000 => rtrim(rtrim(number_format($number / 1_000_000, 1), '0'), '.').'M',
            $number >= 1_000 => rtrim(rtrim(number_format($number / 1_000, 1), '0'), '.').'k',
            default => (string) $number,
        };
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

    protected static function isZomboidServer(Server $server): bool
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

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'mods' => Tab::make(static::t('tabs.mods')),
            'candidates' => Tab::make(static::t('tabs.candidates')),
            'search' => Tab::make(static::t('tabs.search')),
        ];
    }

    // ------------------------------------------------------------------
    // Estado (lista persistida no volume do servidor)
    // ------------------------------------------------------------------

    protected function modList(): ModListService
    {
        return app(ModListService::class);
    }

    protected function steam(): SteamWorkshopService
    {
        return app(SteamWorkshopService::class);
    }

    /** @return array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>} */
    protected function getData(): array
    {
        if ($this->data === null) {
            /** @var Server $server */
            $server = Filament::getTenant();
            $this->data = $this->modList()->load($server);
        }

        return $this->data;
    }

    /** @param array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>} $data */
    protected function saveData(array $data): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $data['mods'] = array_values($data['mods']);
        $this->modList()->save($server, $data);
        $this->data = $data;
        $this->js('$wire.$refresh()');
    }

    protected function findIndex(string $workshopId): ?int
    {
        foreach ($this->getData()['mods'] as $index => $entry) {
            if ((string) $entry['workshop_id'] === $workshopId) {
                return $index;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Homologação (candidatos + servidor de teste)
    // ------------------------------------------------------------------

    protected static function isCandidate(array $entry): bool
    {
        return ($entry['status'] ?? null) === 'candidate';
    }

    /** @return array<int, array<string, mixed>> */
    protected function candidates(): array
    {
        return array_values(array_filter($this->getData()['mods'], fn ($entry) => static::isCandidate($entry)));
    }

    protected function homologationEnabled(): bool
    {
        return !empty($this->getData()['homologation']['hml_server_id']);
    }

    /** O servidor de homologação configurado, se existir e o usuário puder mexer nele. */
    protected function hmlServer(): ?Server
    {
        $id = $this->getData()['homologation']['hml_server_id'] ?? null;
        if (!$id) {
            return null;
        }

        $server = Server::find($id);
        if (!$server || !static::isZomboidServer($server) || !auth()->user()?->can('update', $server)) {
            return null;
        }

        return $server;
    }

    /** @return array<int, string> Servers zomboid que podem servir de HML (menos o atual). */
    protected function hmlServerOptions(): array
    {
        /** @var Server $current */
        $current = Filament::getTenant();

        return Server::query()->where('id', '!=', $current->id)->get()
            ->filter(fn (Server $server) => static::isZomboidServer($server) && auth()->user()?->can('update', $server))
            ->mapWithKeys(fn (Server $server) => [$server->id => $server->name])
            ->all();
    }

    /**
     * Adiciona um item da workshop à lista (detalhes + detecção de Mod IDs).
     *
     * @param array{workshop_id: string, title?: string, preview_url?: ?string, description?: string} $item
     */
    protected function addWorkshopItem(array $item, bool $notify = true): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $workshopId = (string) $item['workshop_id'];

        if ($this->findIndex($workshopId) !== null) {
            if ($notify) {
                Notification::make()->title(static::t('notifications.already_in_list'))->info()->send();
            }

            return;
        }

        $description = $item['description'] ?? null;
        if ($description === null || !isset($item['title'])) {
            $details = $this->steam()->getDetails([$workshopId]);
            $item = array_merge($details[$workshopId] ?? ['workshop_id' => $workshopId, 'title' => "Item $workshopId"], $item);
            $description = $item['description'] ?? '';
        }

        $modIds = SteamWorkshopService::extractModIds($description ?? '');

        // Se o item já foi baixado neste servidor, o disco é a fonte da verdade
        $diskIds = $this->modList()->scanModIdsOnDisk($server, $workshopId);
        if (!empty($diskIds)) {
            $modIds = array_values(array_unique(array_merge($diskIds, $modIds)));
        }

        // Com homologação configurada, mod novo entra como candidato — só
        // chega na lista ativa depois de aprovado.
        $asCandidate = $this->homologationEnabled();

        $entry = [
            'workshop_id' => $workshopId,
            'title' => $item['title'] ?? "Item $workshopId",
            'preview_url' => $item['preview_url'] ?? null,
            'mod_ids' => $modIds,
            'selected_mod_ids' => $modIds,
            'enabled' => true,
        ];
        if ($asCandidate) {
            $entry['status'] = 'candidate';
        }

        $data = $this->getData();
        $data['mods'][] = $entry;
        $this->saveData($data);

        if ($notify) {
            if ($asCandidate && !empty($modIds)) {
                Notification::make()
                    ->title(static::t('notifications.added_candidate'))
                    ->body(static::t('notifications.added_candidate_body', ['title' => $entry['title']]))
                    ->success()
                    ->send();
            } elseif (empty($modIds)) {
                Notification::make()
                    ->title(static::t('notifications.added_no_ids'))
                    ->body(static::t('notifications.added_no_ids_body', ['rescan' => static::t('row.rescan')]))
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title(static::t('notifications.added'))
                    ->body(static::t('notifications.added_body', ['title' => $item['title'], 'ids' => implode(', ', $modIds)]))
                    ->success()
                    ->send();
            }
        }
    }

    // ------------------------------------------------------------------
    // Tabela
    // ------------------------------------------------------------------

    /** Tags/categorias oficiais da workshop do PZ */
    protected const PZ_TAGS = [
        'Animations', 'Balance', 'Building', 'Clothing/Armor', 'Food', 'Framework',
        'Hardmode', 'Interface', 'Items', 'Language/Translation', 'Literature', 'Map',
        'Military', 'Misc', 'Models', 'Multiplayer', 'Pop Culture', 'Realistic',
        'Silly/Fun', 'Textures', 'Traits', 'Vehicles', 'Weapons',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page, ?array $filters = null) {
                $filters ??= [];

                if ($this->activeTab === 'search') {
                    try {
                        $tags = array_values(array_filter([
                            $filters['build']['value'] ?? null,
                            $filters['category']['value'] ?? null,
                        ]));

                        $result = $this->steam()->search(
                            $search,
                            $page,
                            20,
                            $filters['sort']['value'] ?? 'auto',
                            (int) (($filters['period']['value'] ?? null) ?: 7),
                            $tags,
                        );

                        return new LengthAwarePaginator($result['items'], $result['total'], 20, $page);
                    } catch (Exception $exception) {
                        Notification::make()
                            ->title(static::t('notifications.search_unavailable'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return new LengthAwarePaginator([], 0, 20, $page);
                    }
                }

                $wantCandidates = $this->activeTab === 'candidates';
                $mods = array_values(array_filter(
                    $this->getData()['mods'],
                    fn ($entry) => static::isCandidate($entry) === $wantCandidates
                ));

                foreach ($mods as $index => $entry) {
                    $mods[$index]['position'] = $index;
                }

                if (filled($search)) {
                    $searchLower = strtolower($search);
                    $mods = array_values(array_filter($mods, function (array $entry) use ($searchLower) {
                        return str_contains(strtolower($entry['title'] ?? ''), $searchLower)
                            || str_contains(strtolower(implode(' ', $entry['mod_ids'] ?? [])), $searchLower)
                            || str_contains((string) $entry['workshop_id'], $searchLower);
                    }));
                }

                $offset = ($page - 1) * 20;

                return new LengthAwarePaginator(array_slice($mods, $offset, 20), count($mods), 20, $page);
            })
            ->paginated([20])
            ->columns([
                ImageColumn::make('preview_url')
                    ->label(''),
                TextColumn::make('title')
                    ->label(static::t('columns.mod'))
                    ->searchable()
                    ->description(function (array $record): string {
                        if ($this->activeTab === 'search') {
                            $description = $record['short_description'] ?? '';

                            return strlen($description) > 120 ? substr($description, 0, 120).'…' : $description;
                        }

                        return static::t('columns.workshop', ['id' => $record['workshop_id']]);
                    }),
                TextColumn::make('selected_mod_ids')
                    ->label(static::t('columns.mod_ids'))
                    ->badge()
                    ->placeholder(static::t('columns.none_detected'))
                    ->visible(fn () => $this->activeTab !== 'search'),
                IconColumn::make('enabled')
                    ->label(static::t('columns.active'))
                    ->boolean()
                    ->visible(fn () => $this->activeTab === 'mods'),
                TextColumn::make('score')
                    ->label(static::t('columns.rating'))
                    ->color('warning')
                    ->state(function (array $record): string {
                        if (($record['votes'] ?? 0) < 1 || !isset($record['score'])) {
                            return '—';
                        }
                        $stars = (int) round(((float) $record['score']) * 5);

                        return str_repeat('★', $stars).str_repeat('☆', 5 - $stars);
                    })
                    ->description(fn (array $record) => ($record['votes'] ?? 0) > 0
                        ? static::t('columns.votes', ['count' => number_format($record['votes'])])
                        : null)
                    ->visible(fn () => $this->activeTab === 'search'),
                TextColumn::make('subscriptions')
                    ->label(static::t('columns.subscribers'))
                    ->state(fn (array $record) => isset($record['subscriptions'])
                        ? static::compactNumber((int) $record['subscriptions'])
                        : '—')
                    ->visible(fn () => $this->activeTab === 'search'),
                TextColumn::make('time_updated')
                    ->label(static::t('columns.updated'))
                    ->state(fn (array $record) => isset($record['time_updated'])
                        ? \Carbon\Carbon::createFromTimestamp($record['time_updated'])->diffForHumans()
                        : '—')
                    ->visible(fn () => $this->activeTab === 'search'),
            ])
            ->filters([
                SelectFilter::make('sort')
                    ->label(static::t('filters.sort'))
                    ->options([
                        'trend' => static::t('filters.sort_trend'),
                        'relevance' => static::t('filters.sort_relevance'),
                        'newest' => static::t('filters.sort_newest'),
                        'top' => static::t('filters.sort_top'),
                        'subscribed' => static::t('filters.sort_subscribed'),
                        'updated' => static::t('filters.sort_updated'),
                    ])
                    ->visible(fn () => $this->activeTab === 'search'),
                SelectFilter::make('period')
                    ->label(static::t('filters.period'))
                    ->options([
                        '1' => static::t('filters.period_day'),
                        '7' => static::t('filters.period_week'),
                        '30' => static::t('filters.period_month'),
                        '365' => static::t('filters.period_year'),
                    ])
                    ->visible(fn () => $this->activeTab === 'search'),
                SelectFilter::make('build')
                    ->label(static::t('filters.build'))
                    ->options([
                        'Build 42' => 'Build 42',
                        'Build 41' => 'Build 41',
                    ])
                    ->visible(fn () => $this->activeTab === 'search'),
                SelectFilter::make('category')
                    ->label(static::t('filters.category'))
                    ->options(array_combine(self::PZ_TAGS, self::PZ_TAGS))
                    ->searchable()
                    ->visible(fn () => $this->activeTab === 'search'),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordUrl(fn (array $record) => 'https://steamcommunity.com/sharedfiles/filedetails/?id='.$record['workshop_id'], true)
            ->recordActions([
                // --- aba de busca ---
                Action::make('add')
                    ->label(static::t('row.add'))
                    ->icon('tabler-plus')
                    ->color('success')
                    ->visible(fn () => $this->activeTab === 'search')
                    ->hidden(fn (array $record) => $this->findIndex((string) $record['workshop_id']) !== null)
                    ->action(fn (array $record) => $this->addWorkshopItem($record)),
                Action::make('already_added')
                    ->label(static::t('row.in_list'))
                    ->icon('tabler-check')
                    ->color('success')
                    ->disabled()
                    ->visible(fn (array $record) => $this->activeTab === 'search' && $this->findIndex((string) $record['workshop_id']) !== null),

                // --- aba candidatos ---
                Action::make('approve')
                    ->iconButton()
                    ->icon('tabler-rosette-discount-check')
                    ->color('success')
                    ->tooltip(static::t('row.approve'))
                    ->visible(fn () => $this->activeTab === 'candidates')
                    ->requiresConfirmation()
                    ->modalHeading(static::t('modals.approve_heading'))
                    ->modalDescription(fn (array $record) => static::t('modals.approve_description', ['title' => $record['title']]))
                    ->action(function (array $record) {
                        $data = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        unset($data['mods'][$index]['status']);
                        $data['mods'][$index]['enabled'] = true;
                        $data['mods'][$index]['approved_at'] = now()->toIso8601String();
                        $this->saveData($data);

                        Notification::make()
                            ->title(static::t('notifications.approved'))
                            ->body(static::t('notifications.approved_body', ['title' => $record['title']]))
                            ->success()
                            ->send();
                    }),

                // --- aba meus mods ---
                Action::make('toggle')
                    ->iconButton()
                    ->icon(fn (array $record) => empty($record['enabled']) ? 'tabler-toggle-left' : 'tabler-toggle-right')
                    ->color(fn (array $record) => empty($record['enabled']) ? 'gray' : 'success')
                    ->tooltip(fn (array $record) => empty($record['enabled']) ? static::t('row.enable') : static::t('row.disable'))
                    ->visible(fn () => $this->activeTab === 'mods')
                    ->action(function (array $record) {
                        $data = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        $data['mods'][$index]['enabled'] = empty($data['mods'][$index]['enabled']);
                        $this->saveData($data);
                    }),
                Action::make('move_up')
                    ->iconButton()
                    ->icon('tabler-arrow-up')
                    ->tooltip(static::t('row.move_up'))
                    ->visible(fn () => $this->activeTab !== 'search')
                    ->disabled(fn (array $record) => ($record['position'] ?? 0) === 0)
                    ->action(fn (array $record) => $this->move((string) $record['workshop_id'], -1)),
                Action::make('move_down')
                    ->iconButton()
                    ->icon('tabler-arrow-down')
                    ->tooltip(static::t('row.move_down'))
                    ->visible(fn () => $this->activeTab !== 'search')
                    ->disabled(function (array $record) {
                        $sameTab = $this->activeTab === 'candidates'
                            ? count($this->candidates())
                            : count($this->getData()['mods']) - count($this->candidates());

                        return ($record['position'] ?? 0) >= $sameTab - 1;
                    })
                    ->action(fn (array $record) => $this->move((string) $record['workshop_id'], 1)),
                Action::make('edit_mod_ids')
                    ->iconButton()
                    ->icon('tabler-edit')
                    ->tooltip(static::t('row.edit_ids'))
                    ->visible(fn () => $this->activeTab !== 'search')
                    ->schema(fn (array $record) => [
                        CheckboxList::make('selected')
                            ->label(static::t('forms.selected_label'))
                            ->options(array_combine($record['mod_ids'] ?? [], $record['mod_ids'] ?? []))
                            ->default($record['selected_mod_ids'] ?? []),
                        TagsInput::make('manual')
                            ->label(static::t('forms.manual_label'))
                            ->placeholder(static::t('forms.manual_placeholder')),
                    ])
                    ->action(function (array $data, array $record) {
                        $listData = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        $manual = array_map('trim', $data['manual'] ?? []);
                        $selected = array_values(array_unique(array_merge($data['selected'] ?? [], $manual)));

                        $listData['mods'][$index]['mod_ids'] = array_values(array_unique(array_merge($listData['mods'][$index]['mod_ids'] ?? [], $manual)));
                        $listData['mods'][$index]['selected_mod_ids'] = $selected;
                        $this->saveData($listData);

                        Notification::make()->title(static::t('notifications.ids_updated'))->success()->send();
                    }),
                Action::make('rescan')
                    ->iconButton()
                    ->icon('tabler-refresh')
                    ->tooltip(static::t('row.rescan'))
                    ->visible(fn () => $this->activeTab !== 'search')
                    ->action(function (array $record) {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        $diskIds = $this->modList()->scanModIdsOnDisk($server, (string) $record['workshop_id']);

                        if (empty($diskIds)) {
                            Notification::make()
                                ->title(static::t('notifications.rescan_empty'))
                                ->body(static::t('notifications.rescan_empty_body'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $data = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        $data['mods'][$index]['mod_ids'] = $diskIds;
                        if (empty($data['mods'][$index]['selected_mod_ids'])) {
                            $data['mods'][$index]['selected_mod_ids'] = $diskIds;
                        }
                        $this->saveData($data);

                        Notification::make()
                            ->title(static::t('notifications.rescan_found', ['ids' => implode(', ', $diskIds)]))
                            ->success()
                            ->send();
                    }),
                Action::make('remove')
                    ->iconButton()
                    ->icon('tabler-trash')
                    ->color('danger')
                    ->tooltip(static::t('row.remove'))
                    ->visible(fn () => $this->activeTab !== 'search')
                    ->requiresConfirmation()
                    ->modalHeading(static::t('modals.remove_heading'))
                    ->modalDescription(fn (array $record) => static::t('modals.remove_description', ['title' => $record['title']]))
                    ->action(function (array $record) {
                        $data = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        unset($data['mods'][$index]);
                        $this->saveData($data);
                        Notification::make()->title(static::t('notifications.removed'))->success()->send();
                    }),
            ]);
    }

    protected function move(string $workshopId, int $delta): void
    {
        $data = $this->getData();
        $index = $this->findIndex($workshopId);

        if ($index === null) {
            return;
        }

        // troca com o vizinho da MESMA aba (ativos e candidatos convivem no
        // mesmo array, então o vizinho imediato pode ser de outro status)
        $isCandidate = static::isCandidate($data['mods'][$index]);
        $target = $index + $delta;
        while ($target >= 0 && $target < count($data['mods'])
            && static::isCandidate($data['mods'][$target]) !== $isCandidate) {
            $target += $delta;
        }

        if ($target < 0 || $target >= count($data['mods'])) {
            return;
        }

        [$data['mods'][$index], $data['mods'][$target]] = [$data['mods'][$target], $data['mods'][$index]];
        $this->saveData($data);
    }

    // ------------------------------------------------------------------
    // Ações de cabeçalho
    // ------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_by_url')
                ->label(static::t('actions.add_by_url'))
                ->icon('tabler-link-plus')
                ->schema([
                    TextInput::make('input')
                        ->label(static::t('forms.url_label'))
                        ->placeholder('https://steamcommunity.com/sharedfiles/filedetails/?id=2875848298')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $workshopId = SteamWorkshopService::parseWorkshopId($data['input']);

                    if (!$workshopId) {
                        Notification::make()->title(static::t('notifications.invalid_url'))->danger()->send();

                        return;
                    }

                    try {
                        $this->addWorkshopItem(['workshop_id' => $workshopId]);
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.steam_error'))
                            ->body(static::t('notifications.steam_error_body'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('import_collection')
                ->label(static::t('actions.import_collection'))
                ->icon('tabler-stack-2')
                ->schema([
                    TextInput::make('input')
                        ->label(static::t('forms.collection_label'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $collectionId = SteamWorkshopService::parseWorkshopId($data['input']);

                    if (!$collectionId) {
                        Notification::make()->title(static::t('notifications.invalid_url'))->danger()->send();

                        return;
                    }

                    try {
                        $children = $this->steam()->getCollectionChildren($collectionId);

                        if (empty($children)) {
                            Notification::make()->title(static::t('notifications.collection_empty'))->warning()->send();

                            return;
                        }

                        $details = $this->steam()->getDetails($children);
                        $added = 0;

                        foreach ($children as $childId) {
                            if ($this->findIndex($childId) !== null) {
                                continue;
                            }

                            $this->addWorkshopItem($details[$childId] ?? ['workshop_id' => $childId], notify: false);
                            $added++;
                        }

                        Notification::make()
                            ->title(static::t('notifications.collection_imported', ['count' => $added]))
                            ->success()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.steam_error'))
                            ->body(static::t('notifications.steam_error_body'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('import_ini')
                ->label(static::t('actions.import_ini'))
                ->icon('tabler-file-import')
                ->requiresConfirmation()
                ->modalHeading(static::t('modals.import_ini_heading'))
                ->modalDescription(static::t('modals.import_ini_description'))
                ->action(function () {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    try {
                        $ini = $this->modList()->readIni($server);

                        if (empty($ini['workshop_ids'])) {
                            Notification::make()->title(static::t('notifications.ini_no_items'))->warning()->send();

                            return;
                        }

                        $details = $this->steam()->getDetails($ini['workshop_ids']);
                        $iniModIdsLower = array_map('strtolower', $ini['mod_ids']);
                        $claimed = [];

                        // Reconcilia com o ini em vez de só acrescentar: o ini é a
                        // fonte da verdade (pode ter sido editado por fora).
                        $existing = [];
                        foreach ($this->getData()['mods'] as $entry) {
                            $existing[(string) $entry['workshop_id']] = $entry;
                        }

                        $mods = [];
                        foreach ($ini['workshop_ids'] as $workshopId) {
                            $entry = $existing[$workshopId] ?? null;
                            unset($existing[$workshopId]);

                            if ($entry === null) {
                                $item = $details[$workshopId] ?? ['workshop_id' => $workshopId, 'title' => "Item $workshopId"];

                                $detected = SteamWorkshopService::extractModIds($item['description'] ?? '');
                                $diskIds = $this->modList()->scanModIdsOnDisk($server, $workshopId);

                                $entry = [
                                    'workshop_id' => $workshopId,
                                    'title' => $item['title'] ?? "Item $workshopId",
                                    'preview_url' => $item['preview_url'] ?? null,
                                    'mod_ids' => array_values(array_unique(array_merge($diskIds, $detected))),
                                ];
                            }

                            // marca só os Mod IDs que realmente estão no Mods= de hoje
                            $known = $entry['mod_ids'] ?? [];
                            $selected = array_values(array_filter($known, fn ($id) => in_array(strtolower($id), $iniModIdsLower)));
                            foreach ($selected as $id) {
                                $claimed[strtolower($id)] = true;
                            }

                            $entry['selected_mod_ids'] = $selected;
                            $entry['enabled'] = true;
                            unset($entry['status']); // está no ini → é ativo por definição
                            $mods[] = $entry;
                        }

                        // o que está na lista mas não no ini vira desligado (sem
                        // perder os metadados, pra poder religar depois)
                        $disabled = 0;
                        foreach ($existing as $entry) {
                            if (!empty($entry['enabled'])) {
                                $disabled++;
                            }
                            $entry['enabled'] = false;
                            $mods[] = $entry;
                        }

                        // Mod IDs do ini sem dono continuam preservados
                        $extras = array_values(array_unique(array_filter(
                            $ini['mod_ids'],
                            fn ($id) => !isset($claimed[strtolower($id)])
                        )));

                        $this->saveData(array_merge($this->getData(), ['mods' => $mods, 'extra_mod_ids' => $extras]));

                        $extraNote = empty($extras) ? '' : static::t('notifications.ini_imported_extras', ['count' => count($extras)]);
                        $offNote = $disabled === 0 ? '' : static::t('notifications.ini_imported_disabled', ['count' => $disabled]);
                        Notification::make()
                            ->title(static::t('notifications.ini_imported', ['count' => count($ini['workshop_ids'])]).$extraNote.$offNote)
                            ->success()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.steam_error'))
                            ->body(static::t('notifications.steam_error_body'))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('configure_hml')
                ->label(static::t('actions.configure_hml'))
                ->icon('tabler-flask-2')
                ->visible(fn () => $this->activeTab === 'candidates')
                ->schema([
                    Select::make('hml_server_id')
                        ->label(static::t('forms.hml_server_label'))
                        ->helperText(static::t('forms.hml_server_help'))
                        ->options(fn () => $this->hmlServerOptions())
                        ->placeholder('—'),
                ])
                ->fillForm(fn () => [
                    'hml_server_id' => $this->getData()['homologation']['hml_server_id'] ?? null,
                ])
                ->action(function (array $data) {
                    $listData = $this->getData();
                    $listData['homologation']['hml_server_id'] = $data['hml_server_id'] ? (int) $data['hml_server_id'] : null;
                    $this->saveData($listData);

                    Notification::make()
                        ->title(static::t($data['hml_server_id'] ? 'notifications.hml_saved' : 'notifications.hml_cleared'))
                        ->success()
                        ->send();
                }),
            Action::make('test_hml')
                ->label(static::t('actions.test_hml'))
                ->icon('tabler-flask')
                ->color('warning')
                ->visible(fn () => $this->activeTab === 'candidates' && $this->hmlServer() !== null)
                ->requiresConfirmation()
                ->modalHeading(static::t('modals.test_hml_heading'))
                ->modalDescription(fn () => static::t('modals.test_hml_description', [
                    'count' => count($this->candidates()),
                    'server' => $this->hmlServer()?->name ?? '?',
                ]))
                ->action(function (DaemonServerRepository $serverRepository) {
                    $hml = $this->hmlServer();
                    $candidates = $this->candidates();

                    if ($hml === null) {
                        return;
                    }

                    if (empty($candidates)) {
                        Notification::make()->title(static::t('notifications.no_candidates'))->warning()->send();

                        return;
                    }

                    try {
                        // HML recebe a lista ativa + candidatos (ligados), na
                        // mesma ordem de load — o teste roda em cima do stack real
                        $data = $this->getData();
                        $hmlMods = [];
                        foreach ($data['mods'] as $entry) {
                            if (static::isCandidate($entry)) {
                                $entry['enabled'] = true;
                            }
                            unset($entry['status'], $entry['approved_at']);
                            $hmlMods[] = $entry;
                        }

                        $hmlData = ['mods' => $hmlMods, 'extra_mod_ids' => $data['extra_mod_ids']];
                        $this->modList()->save($hml, $hmlData);
                        $this->modList()->applyToIni($hml, $hmlData);
                        $serverRepository->setServer($hml)->power('restart');

                        $data['homologation']['last_test'] = [
                            'at' => now()->toIso8601String(),
                            'workshop_ids' => array_map(fn ($entry) => (string) $entry['workshop_id'], $candidates),
                        ];
                        $this->saveData($data);

                        Notification::make()
                            ->title(static::t('notifications.test_sent', ['server' => $hml->name]))
                            ->body(static::t('notifications.test_sent_body', ['count' => count($candidates)]))
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.test_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('apply')
                ->label(static::t('actions.apply'))
                ->icon('tabler-device-floppy')
                ->color('warning')
                ->visible(fn () => $this->activeTab !== 'candidates')
                ->requiresConfirmation()
                ->modalHeading(static::t('modals.apply_heading'))
                ->modalDescription(function () {
                    $data = $this->getData();
                    $enabled = count(array_filter($data['mods'], fn ($entry) => !empty($entry['enabled']) && !static::isCandidate($entry)));

                    return static::t('modals.apply_description', ['enabled' => $enabled, 'total' => count($data['mods'])]);
                })
                ->action(function () {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    try {
                        $result = $this->modList()->applyToIni($server, $this->getData());

                        Notification::make()
                            ->title(static::t('notifications.applied'))
                            ->body(static::t('notifications.applied_body', [
                                'workshop' => count($result['workshop_ids']),
                                'ids' => count($result['mod_ids']),
                            ]))
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.apply_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('restart')
                ->label(static::t('actions.restart'))
                ->icon('tabler-refresh-alert')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(static::t('modals.restart_heading'))
                ->modalDescription(static::t('modals.restart_description'))
                ->action(function (DaemonServerRepository $serverRepository) {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    try {
                        $serverRepository->setServer($server)->power('restart');
                        Notification::make()->title(static::t('notifications.restart_sent'))->success()->send();
                    } catch (Exception $exception) {
                        report($exception);
                        Notification::make()
                            ->title(static::t('notifications.restart_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    // ------------------------------------------------------------------
    // Conteúdo (badges + tabs + tabela)
    // ------------------------------------------------------------------

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
                            ->state(fn () => count($this->candidates()))
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
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }
}
