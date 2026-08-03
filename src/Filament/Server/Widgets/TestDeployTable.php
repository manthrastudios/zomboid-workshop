<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Widgets;

use App\Repositories\Daemon\DaemonServerRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Seção 2 — fila de teste. Mods esperando validação no servidor de
 * homologação; aprovar move pra lista do servidor (seção 3).
 */
class TestDeployTable extends BaseModsTable
{
    public function table(Table $table): Table
    {
        $hml = $this->hmlServer();

        return $table
            ->heading('2 · '.static::t('sections.test'))
            ->description($hml !== null
                ? static::t('queue.hml_configured', ['server' => $hml->name]).' — '.static::t('queue.hml_autostop')
                : static::t('queue.hml_missing'))
            ->records(function (?string $search) {
                $mods = static::filterBySearch($this->candidates(), $search);

                foreach ($mods as $index => $entry) {
                    $mods[$index]['position'] = $index;
                }

                return $mods;
            })
            ->paginated(false)
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
                TextColumn::make('test_status')
                    ->label(static::t('columns.test_status'))
                    ->badge()
                    ->state(fn (array $record) => $this->isUnderTest($record)
                        ? static::t('queue.status_testing', ['time' => $this->lastTestTime()])
                        : static::t('queue.status_untested'))
                    ->color(fn (array $record) => $this->isUnderTest($record) ? 'info' : 'gray'),
            ])
            ->recordUrl(fn (array $record) => 'https://steamcommunity.com/sharedfiles/filedetails/?id='.$record['workshop_id'], true)
            ->recordActions([
                Action::make('approve')
                    ->iconButton()
                    ->icon('tabler-rosette-discount-check')
                    ->color('success')
                    ->tooltip(static::t('row.approve'))
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
                $this->moveUpAction(true),
                $this->moveDownAction(true),
                $this->editIdsAction(),
                $this->rescanAction(),
                $this->removeAction(),
            ])
            ->headerActions([
                Action::make('start_hml')
                    ->label(static::t('actions.start_hml'))
                    ->icon('tabler-player-play')
                    ->visible(fn () => $this->hmlServer() !== null)
                    ->action(function (DaemonServerRepository $serverRepository) {
                        $hml = $this->hmlServer();
                        if ($hml === null) {
                            return;
                        }

                        try {
                            $serverRepository->setServer($hml)->power('start');
                            Notification::make()
                                ->title(static::t('notifications.hml_starting', ['server' => $hml->name]))
                                ->body(static::t('notifications.hml_starting_body'))
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            report($exception);
                            Notification::make()
                                ->title(static::t('notifications.hml_start_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                $this->configureHmlAction('configure_hml'),
                Action::make('test_hml')
                    ->label(static::t('actions.test_hml'))
                    ->icon('tabler-flask')
                    ->color('warning')
                    ->visible(fn () => $this->hmlServer() !== null && count($this->candidates()) > 0)
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
            ])
            ->emptyStateHeading(static::t('queue.empty_heading'))
            ->emptyStateDescription(static::t('queue.empty_description'))
            ->emptyStateActions([
                $this->configureHmlAction('configure_hml_empty')
                    ->button()
                    ->visible(fn () => $this->hmlServer() === null),
            ]);
    }

    /** O mod está no lote do último teste enviado pro HML? */
    protected function isUnderTest(array $record): bool
    {
        $lastTest = $this->getData()['homologation']['last_test'] ?? null;

        return $lastTest !== null
            && in_array((string) $record['workshop_id'], $lastTest['workshop_ids'] ?? [], true);
    }

    protected function lastTestTime(): string
    {
        $at = $this->getData()['homologation']['last_test']['at'] ?? null;

        try {
            return $at ? \Carbon\Carbon::parse($at)->diffForHumans() : '?';
        } catch (Exception) {
            return '?';
        }
    }
}
