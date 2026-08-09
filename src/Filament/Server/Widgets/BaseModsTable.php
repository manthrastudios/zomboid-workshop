<?php

namespace Tevo\ZomboidWorkshop\Filament\Server\Widgets;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;
use Tevo\ZomboidWorkshop\Filament\Server\Pages\ZomboidWorkshopPage;
use Tevo\ZomboidWorkshop\Services\ModListService;
use Tevo\ZomboidWorkshop\Services\SteamWorkshopService;
use Tevo\ZomboidWorkshop\Services\WorldModsService;

/**
 * Base das três tabelas do fluxo (Buscar → Testar → No servidor).
 * Todas leem/escrevem a mesma lista no volume; qualquer escrita dispara
 * "zw-mods-updated" pra que as irmãs (e as badges da página) se atualizem.
 */
abstract class BaseModsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, homologation: array<string, mixed>}|null */
    protected ?array $data = null;

    protected static function t(string $key, array $replace = []): string
    {
        return trans('zomboid-workshop::strings.'.$key, $replace);
    }

    /**
     * Mods desta linha que o mundo já tem dentro — o que faz desligar ou remover
     * virar ato destrutivo ([WorldModsService]).
     *
     * ⚠️ Vazio **e** `null` significam coisas diferentes: `null` é "não deu pra
     * ler o mundo". Nenhum dos dois autoriza a tela a dizer que remover é
     * seguro.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, string>|null
     */
    protected function worldModsFor(array $record): ?array
    {
        return app(WorldModsService::class)->entryModsInWorld($this->server(), $record);
    }

    protected function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    protected function modList(): ModListService
    {
        return app(ModListService::class);
    }

    protected function steam(): SteamWorkshopService
    {
        return app(SteamWorkshopService::class);
    }

    /** @return array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, homologation: array<string, mixed>} */
    protected function getData(): array
    {
        if ($this->data === null) {
            $this->data = $this->modList()->load($this->server());
        }

        return $this->data;
    }

    protected function saveData(array $data): void
    {
        $data['mods'] = array_values($data['mods']);
        $this->modList()->save($this->server(), $data);
        $this->data = $data;
        $this->dispatch('zw-mods-updated');
    }

    #[On('zw-mods-updated')]
    public function refreshModList(): void
    {
        // o re-render do Livewire recarrega a lista do volume
        $this->data = null;
    }

    protected static function isCandidate(array $entry): bool
    {
        return ($entry['status'] ?? null) === 'candidate';
    }

    /** @return array<int, array<string, mixed>> */
    protected function candidates(): array
    {
        return array_values(array_filter($this->getData()['mods'], fn ($entry) => static::isCandidate($entry)));
    }

    /** @return array<int, array<string, mixed>> */
    protected function activeMods(): array
    {
        return array_values(array_filter($this->getData()['mods'], fn ($entry) => !static::isCandidate($entry)));
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

    /** 1234 → "1.2k", 5600000 → "5.6M" (sem depender da extensão intl) */
    protected static function compactNumber(int $number): string
    {
        return match (true) {
            $number >= 1_000_000 => rtrim(rtrim(number_format($number / 1_000_000, 1), '0'), '.').'M',
            $number >= 1_000 => rtrim(rtrim(number_format($number / 1_000, 1), '0'), '.').'k',
            default => (string) $number,
        };
    }

    // ------------------------------------------------------------------
    // Homologação
    // ------------------------------------------------------------------

    /** O servidor de homologação configurado, se existir e o usuário puder mexer nele. */
    protected function hmlServer(): ?Server
    {
        $id = $this->getData()['homologation']['hml_server_id'] ?? null;
        if (!$id) {
            return null;
        }

        $server = Server::find($id);
        if (!$server || !ZomboidWorkshopPage::isZomboidServer($server) || !auth()->user()?->can('update', $server)) {
            return null;
        }

        return $server;
    }

    /** @return array<int, string> Servers zomboid que podem servir de HML (menos o atual). */
    protected function hmlServerOptions(): array
    {
        return Server::query()->where('id', '!=', $this->server()->id)->get()
            ->filter(fn (Server $server) => ZomboidWorkshopPage::isZomboidServer($server) && auth()->user()?->can('update', $server))
            ->mapWithKeys(fn (Server $server) => [$server->id => $server->name])
            ->all();
    }

    /** Ação de escolher o servidor de testes. */
    protected function configureHmlAction(string $name): Action
    {
        return Action::make($name)
            ->label(static::t('actions.configure_hml'))
            ->icon('tabler-flask-2')
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
            });
    }

    // ------------------------------------------------------------------
    // Lista (adicionar / mover)
    // ------------------------------------------------------------------

    /**
     * Adiciona um item da workshop à fila de teste (detalhes + detecção de Mod IDs).
     *
     * @param array{workshop_id: string, title?: string, preview_url?: ?string, description?: string} $item
     */
    protected function addWorkshopItem(array $item, bool $notify = true): void
    {
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
        $diskIds = $this->modList()->scanModIdsOnDisk($this->server(), $workshopId);
        if (!empty($diskIds)) {
            $modIds = array_values(array_unique(array_merge($diskIds, $modIds)));
        }

        // Mod novo SEMPRE entra na fila — só chega na lista ativa via "Aprovar".
        $entry = [
            'workshop_id' => $workshopId,
            'title' => $item['title'] ?? "Item $workshopId",
            'preview_url' => $item['preview_url'] ?? null,
            'mod_ids' => $modIds,
            'selected_mod_ids' => $modIds,
            'enabled' => true,
            'status' => 'candidate',
        ];

        $data = $this->getData();
        $data['mods'][] = $entry;
        $this->saveData($data);

        if ($notify) {
            if (!empty($modIds)) {
                Notification::make()
                    ->title(static::t('notifications.added_candidate'))
                    ->body(static::t('notifications.added_candidate_body', ['title' => $entry['title']]))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(static::t('notifications.added_no_ids'))
                    ->body(static::t('notifications.added_no_ids_body', ['rescan' => static::t('row.rescan')]))
                    ->warning()
                    ->send();
            }
        }
    }

    protected function move(string $workshopId, int $delta): void
    {
        $data = $this->getData();
        $index = $this->findIndex($workshopId);

        if ($index === null) {
            return;
        }

        // troca com o vizinho da MESMA seção (ativos e candidatos convivem no
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
    // Ações de linha compartilhadas (fila e servidor)
    // ------------------------------------------------------------------

    protected function moveUpAction(bool $candidates): Action
    {
        return Action::make('move_up')
            ->iconButton()
            ->icon('tabler-arrow-up')
            ->tooltip(static::t('row.move_up'))
            ->disabled(fn (array $record) => ($record['position'] ?? 0) === 0)
            ->action(fn (array $record) => $this->move((string) $record['workshop_id'], -1));
    }

    protected function moveDownAction(bool $candidates): Action
    {
        return Action::make('move_down')
            ->iconButton()
            ->icon('tabler-arrow-down')
            ->tooltip(static::t('row.move_down'))
            ->disabled(function (array $record) use ($candidates) {
                $sameSection = $candidates ? count($this->candidates()) : count($this->activeMods());

                return ($record['position'] ?? 0) >= $sameSection - 1;
            })
            ->action(fn (array $record) => $this->move((string) $record['workshop_id'], 1));
    }

    protected function editIdsAction(): Action
    {
        return Action::make('edit_mod_ids')
            ->iconButton()
            ->icon('tabler-edit')
            ->tooltip(static::t('row.edit_ids'))
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
            });
    }

    protected function rescanAction(): Action
    {
        return Action::make('rescan')
            ->iconButton()
            ->icon('tabler-refresh')
            ->tooltip(static::t('row.rescan'))
            ->action(function (array $record) {
                $diskIds = $this->modList()->scanModIdsOnDisk($this->server(), (string) $record['workshop_id']);

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
            });
    }

    protected function removeAction(): Action
    {
        return Action::make('remove')
            ->iconButton()
            ->icon('tabler-trash')
            ->color('danger')
            ->tooltip(static::t('row.remove'))
            ->requiresConfirmation()
            ->modalIcon(fn (array $record) => $this->worldModsFor($record) ? 'tabler-alert-triangle' : null)
            ->modalHeading(fn (array $record) => static::t(
                $this->worldModsFor($record) ? 'modals.remove_heading_danger' : 'modals.remove_heading'
            ))
            ->modalDescription(function (array $record) {
                $inWorld = $this->worldModsFor($record);

                if ($inWorld) {
                    return static::t('modals.remove_description_danger', [
                        'title' => $record['title'],
                        'mods' => implode(', ', $inWorld),
                    ]);
                }

                return static::t('modals.remove_description', ['title' => $record['title']]);
            })
            ->action(function (array $record) {
                $data = $this->getData();
                $index = $this->findIndex((string) $record['workshop_id']);
                if ($index === null) {
                    return;
                }

                unset($data['mods'][$index]);
                $this->saveData($data);

                // Remover é remover: quem só quer parar de carregar tem o
                // Ligar/Desligar da linha. Deixar os arquivos pra trás fazia o
                // lixinho mentir e o volume crescer sem ninguém ver.
                $wiped = $this->deleteModFiles((string) $record['workshop_id']);

                Notification::make()
                    ->title(static::t($wiped ? 'notifications.removed' : 'notifications.removed_files_kept'))
                    ->status($wiped ? 'success' : 'warning')
                    ->send();
            });
    }

    /**
     * Apaga o conteúdo baixado do mod no volume. Mesmo caminho que o reject do
     * HML já usava (`TestDeployTable::cleanupOnHml`).
     *
     * Falha não é fatal: a lista já foi salva, e sumir com a linha e ainda
     * assim avisar que o arquivo ficou é melhor que abortar tudo. Por isso o
     * retorno vira o tom da notificação em vez de exceção.
     */
    protected function deleteModFiles(string $workshopId): bool
    {
        try {
            app(DaemonFileRepository::class)
                ->setServer($this->server())
                ->deleteFiles('steamapps/workshop/content/'.SteamWorkshopService::PZ_APP_ID, [$workshopId]);

            return true;
        } catch (Exception $exception) {
            report($exception);

            return false;
        }
    }

    /** Busca local por título/mod id/workshop id, usada pela fila e pela lista do servidor. */
    protected static function filterBySearch(array $mods, ?string $search): array
    {
        if (blank($search)) {
            return $mods;
        }

        $searchLower = strtolower($search);

        return array_values(array_filter($mods, function (array $entry) use ($searchLower) {
            return str_contains(strtolower($entry['title'] ?? ''), $searchLower)
                || str_contains(strtolower(implode(' ', $entry['mod_ids'] ?? [])), $searchLower)
                || str_contains((string) $entry['workshop_id'], $searchLower);
        }));
    }
}
