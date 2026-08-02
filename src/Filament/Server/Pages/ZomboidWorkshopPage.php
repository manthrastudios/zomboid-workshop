<?php

namespace Manthra\ZomboidWorkshop\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonPowerRepository;
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
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Manthra\ZomboidWorkshop\Services\ModListService;
use Manthra\ZomboidWorkshop\Services\SteamWorkshopService;

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
        /** @var Server $server */
        $server = Filament::getTenant();

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

        $data = $this->getData();
        $data['mods'][] = [
            'workshop_id' => $workshopId,
            'title' => $item['title'] ?? "Item $workshopId",
            'preview_url' => $item['preview_url'] ?? null,
            'mod_ids' => $modIds,
            'selected_mod_ids' => $modIds,
            'enabled' => true,
        ];
        $this->saveData($data);

        if ($notify) {
            if (empty($modIds)) {
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

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, int $page) {
                if ($this->activeTab === 'search') {
                    try {
                        $result = $this->steam()->search($search, $page);

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

                $mods = $this->getData()['mods'];

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
                    ->visible(fn () => $this->activeTab !== 'search'),
            ])
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

                // --- aba meus mods ---
                Action::make('toggle')
                    ->iconButton()
                    ->icon(fn (array $record) => empty($record['enabled']) ? 'tabler-toggle-left' : 'tabler-toggle-right')
                    ->color(fn (array $record) => empty($record['enabled']) ? 'gray' : 'success')
                    ->tooltip(fn (array $record) => empty($record['enabled']) ? static::t('row.enable') : static::t('row.disable'))
                    ->visible(fn () => $this->activeTab !== 'search')
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
                    ->disabled(fn (array $record) => ($record['position'] ?? 0) >= count($this->getData()['mods']) - 1)
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
        $target = $index + $delta;

        if ($index === null || $target < 0 || $target >= count($data['mods'])) {
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

                        foreach ($ini['workshop_ids'] as $workshopId) {
                            if ($this->findIndex($workshopId) !== null) {
                                continue;
                            }

                            $item = $details[$workshopId] ?? ['workshop_id' => $workshopId, 'title' => "Item $workshopId"];

                            $detected = SteamWorkshopService::extractModIds($item['description'] ?? '');
                            $diskIds = $this->modList()->scanModIdsOnDisk($server, $workshopId);
                            $known = array_values(array_unique(array_merge($diskIds, $detected)));

                            // seleciona só os que estão de fato no Mods= atual
                            $selected = array_values(array_filter($known, fn ($id) => in_array(strtolower($id), $iniModIdsLower)));
                            foreach ($selected as $id) {
                                $claimed[strtolower($id)] = true;
                            }

                            $data = $this->getData();
                            $data['mods'][] = [
                                'workshop_id' => $workshopId,
                                'title' => $item['title'] ?? "Item $workshopId",
                                'preview_url' => $item['preview_url'] ?? null,
                                'mod_ids' => $known,
                                'selected_mod_ids' => $selected,
                                'enabled' => true,
                            ];
                            $this->data = $data;
                        }

                        // Mod IDs do ini que não casaram com nenhum item ficam preservados
                        $data = $this->getData();
                        $extras = array_values(array_filter($ini['mod_ids'], fn ($id) => !isset($claimed[strtolower($id)])));
                        $data['extra_mod_ids'] = array_values(array_unique(array_merge($data['extra_mod_ids'], $extras)));
                        $this->saveData($data);

                        $extraNote = empty($extras) ? '' : static::t('notifications.ini_imported_extras', ['count' => count($extras)]);
                        Notification::make()
                            ->title(static::t('notifications.ini_imported', ['count' => count($ini['workshop_ids'])]).$extraNote)
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
            Action::make('apply')
                ->label(static::t('actions.apply'))
                ->icon('tabler-device-floppy')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(static::t('modals.apply_heading'))
                ->modalDescription(function () {
                    $data = $this->getData();
                    $enabled = count(array_filter($data['mods'], fn ($entry) => !empty($entry['enabled'])));

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
                ->action(function (DaemonPowerRepository $powerRepository) {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    try {
                        $powerRepository->setServer($server)->send('restart');
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
                Grid::make(3)
                    ->schema([
                        TextEntry::make('total')
                            ->label(static::t('badges.total'))
                            ->state(fn () => count($this->getData()['mods']))
                            ->badge()
                            ->size(TextSize::Large),
                        TextEntry::make('ativos')
                            ->label(static::t('badges.active'))
                            ->state(fn () => count(array_filter($this->getData()['mods'], fn ($entry) => !empty($entry['enabled']))))
                            ->badge()
                            ->color('success')
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
