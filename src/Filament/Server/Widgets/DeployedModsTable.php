<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Widgets;

use App\Repositories\Daemon\DaemonServerRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tevo\ZomboidWorkshop\Services\SteamWorkshopService;

/**
 * Seção 3 — a lista que vale no servidor. Reordenar, desligar, remover,
 * salvar no ini e reiniciar.
 */
class DeployedModsTable extends BaseModsTable
{
    public function table(Table $table): Table
    {
        $extras = count($this->getData()['extra_mod_ids']);
        $extrasNote = $extras > 0 ? ' · '.static::t('badges.loose').': '.$extras : '';

        return $table
            ->heading('3 · '.static::t('sections.deployed'))
            ->description(static::t('sections.deployed_desc').$extrasNote)
            ->records(function (?string $search) {
                $mods = static::filterBySearch($this->activeMods(), $search);

                foreach ($mods as $index => $entry) {
                    $mods[$index]['position'] = $index;
                }

                return $mods;
            })
            ->paginated([20])
            ->columns([
                ImageColumn::make('preview_url')
                    ->label(''),
                TextColumn::make('title')
                    ->label(static::t('columns.mod'))
                    ->searchable()
                    ->description(fn (array $record): string => static::t('columns.workshop', ['id' => $record['workshop_id']])),
                TextColumn::make('selected_mod_ids')
                    ->label(static::t('columns.mod_ids'))
                    ->badge()
                    ->placeholder(static::t('columns.none_detected')),
                IconColumn::make('enabled')
                    ->label(static::t('columns.active'))
                    ->boolean(),
            ])
            ->recordUrl(fn (array $record) => 'https://steamcommunity.com/sharedfiles/filedetails/?id='.$record['workshop_id'], true)
            ->recordActions([
                Action::make('toggle')
                    ->iconButton()
                    ->icon(fn (array $record) => empty($record['enabled']) ? 'tabler-toggle-left' : 'tabler-toggle-right')
                    ->color(fn (array $record) => empty($record['enabled']) ? 'gray' : 'success')
                    ->tooltip(fn (array $record) => empty($record['enabled']) ? static::t('row.enable') : static::t('row.disable'))
                    ->action(function (array $record) {
                        $data = $this->getData();
                        $index = $this->findIndex((string) $record['workshop_id']);
                        if ($index === null) {
                            return;
                        }

                        $data['mods'][$index]['enabled'] = empty($data['mods'][$index]['enabled']);
                        $this->saveData($data);
                    }),
                $this->moveUpAction(false),
                $this->moveDownAction(false),
                $this->editIdsAction(),
                $this->rescanAction(),
                $this->removeAction(),
            ])
            ->headerActions([
                Action::make('import_ini')
                    ->label(static::t('actions.import_ini'))
                    ->icon('tabler-file-import')
                    ->requiresConfirmation()
                    ->modalHeading(static::t('modals.import_ini_heading'))
                    ->modalDescription(static::t('modals.import_ini_description'))
                    ->action(fn () => $this->importFromIni()),
                Action::make('apply')
                    ->label(static::t('actions.apply'))
                    ->icon('tabler-device-floppy')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(static::t('modals.apply_heading'))
                    ->modalDescription(function () {
                        $data = $this->getData();
                        $enabled = count(array_filter($data['mods'], fn ($entry) => !empty($entry['enabled']) && !static::isCandidate($entry)));

                        return static::t('modals.apply_description', ['enabled' => $enabled, 'total' => count($data['mods'])]);
                    })
                    ->action(function () {
                        try {
                            $result = $this->modList()->applyToIni($this->server(), $this->getData());

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
                        try {
                            $serverRepository->setServer($this->server())->power('restart');
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
            ]);
    }

    /** Reconcilia a lista com o que o servidor realmente roda (ini como fonte da verdade). */
    protected function importFromIni(): void
    {
        try {
            $server = $this->server();
            $ini = $this->modList()->readIni($server);

            if (empty($ini['workshop_ids'])) {
                Notification::make()->title(static::t('notifications.ini_no_items'))->warning()->send();

                return;
            }

            $details = $this->steam()->getDetails($ini['workshop_ids']);
            $iniModIdsLower = array_map('strtolower', $ini['mod_ids']);
            $claimed = [];

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
                if (!empty($entry['enabled']) && !static::isCandidate($entry)) {
                    $disabled++;
                    $entry['enabled'] = false;
                }
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
    }
}
