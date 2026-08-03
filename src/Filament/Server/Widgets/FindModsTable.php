<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Widgets;

use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Tevo\ZomboidWorkshop\Services\SteamWorkshopService;

/**
 * Seção 1 — busca na workshop. Tudo que é adicionado aqui cai na fila
 * de teste (seção 2).
 */
class FindModsTable extends BaseModsTable
{
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
            ->heading('1 · '.static::t('sections.find'))
            ->description(static::t('sections.find_desc'))
            ->records(function (?string $search, int $page, ?array $filters = null) {
                $filters ??= [];

                try {
                    $tags = array_values(array_filter([
                        $filters['build']['value'] ?? null,
                        $filters['category']['value'] ?? null,
                    ]));

                    $result = $this->steam()->search(
                        $search,
                        $page,
                        10,
                        $filters['sort']['value'] ?? 'auto',
                        (int) (($filters['period']['value'] ?? null) ?: 7),
                        $tags,
                    );

                    return new LengthAwarePaginator($result['items'], $result['total'], 10, $page);
                } catch (Exception $exception) {
                    Notification::make()
                        ->title(static::t('notifications.search_unavailable'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return new LengthAwarePaginator([], 0, 10, $page);
                }
            })
            ->paginated([10])
            ->columns([
                ImageColumn::make('preview_url')
                    ->label(''),
                TextColumn::make('title')
                    ->label(static::t('columns.mod'))
                    ->searchable()
                    ->description(function (array $record): string {
                        $description = $record['short_description'] ?? '';

                        return strlen($description) > 120 ? substr($description, 0, 120).'…' : $description;
                    }),
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
                        : null),
                TextColumn::make('subscriptions')
                    ->label(static::t('columns.subscribers'))
                    ->state(fn (array $record) => isset($record['subscriptions'])
                        ? static::compactNumber((int) $record['subscriptions'])
                        : '—'),
                TextColumn::make('time_updated')
                    ->label(static::t('columns.updated'))
                    ->state(fn (array $record) => isset($record['time_updated'])
                        ? \Carbon\Carbon::createFromTimestamp($record['time_updated'])->diffForHumans()
                        : '—'),
            ])
            ->recordUrl(fn (array $record) => 'https://steamcommunity.com/sharedfiles/filedetails/?id='.$record['workshop_id'], true)
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
                    ]),
                SelectFilter::make('period')
                    ->label(static::t('filters.period'))
                    ->options([
                        '1' => static::t('filters.period_day'),
                        '7' => static::t('filters.period_week'),
                        '30' => static::t('filters.period_month'),
                        '365' => static::t('filters.period_year'),
                    ]),
                SelectFilter::make('build')
                    ->label(static::t('filters.build'))
                    ->options([
                        'Build 42' => 'Build 42',
                        'Build 41' => 'Build 41',
                    ]),
                SelectFilter::make('category')
                    ->label(static::t('filters.category'))
                    ->options(array_combine(self::PZ_TAGS, self::PZ_TAGS))
                    ->searchable(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters(false)
            ->recordActions([
                Action::make('add')
                    ->label(static::t('row.add'))
                    ->icon('tabler-plus')
                    ->color('success')
                    ->hidden(fn (array $record) => $this->findIndex((string) $record['workshop_id']) !== null)
                    ->action(fn (array $record) => $this->addWorkshopItem($record)),
                Action::make('already_added')
                    ->label(static::t('row.in_list'))
                    ->icon('tabler-check')
                    ->color('success')
                    ->disabled()
                    ->visible(fn (array $record) => $this->findIndex((string) $record['workshop_id']) !== null),
            ])
            ->headerActions([
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
            ]);
    }
}
