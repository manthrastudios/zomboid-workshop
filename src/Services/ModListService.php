<?php

namespace Tevo\ZomboidWorkshop\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exception;

/**
 * Lista de mods do servidor, persistida em um JSON no próprio volume
 * (.zomboid-workshop.json) — sobrevive a reinstalação do painel e viaja
 * junto com o servidor em backups.
 *
 * Estrutura:
 *  {
 *    "mods": [
 *      {"workshop_id": "123", "title": "...", "preview_url": "...",
 *       "mod_ids": ["A", "B"], "selected_mod_ids": ["A"], "enabled": true,
 *       "status": "candidate"}
 *    ],
 *    "extra_mod_ids": ["ids do ini que não casaram com nenhum item"],
 *    "homologation": {"hml_server_id": 2, "last_test": {"at": "...", "workshop_ids": []}}
 *  }
 *
 * A ordem do array "mods" é a ordem de load (importa no PZ).
 * "status" ausente = mod da lista ativa; "candidate" = em homologação
 * (nunca entra no ini deste servidor — só no servidor de teste).
 */
class ModListService
{
    public const METADATA_FILE = '.zomboid-workshop.json';

    public function __construct(protected DaemonFileRepository $fileRepository) {}

    /** @return array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, homologation: array<string, mixed>} */
    public function load(Server $server): array
    {
        try {
            $content = $this->fileRepository->setServer($server)->getContent(self::METADATA_FILE);
            $data = json_decode($content, true);
        } catch (Exception) {
            $data = null;
        }

        return [
            'mods' => array_values($data['mods'] ?? []),
            'extra_mod_ids' => array_values($data['extra_mod_ids'] ?? []),
            'homologation' => is_array($data['homologation'] ?? null) ? $data['homologation'] : [],
            // Precisa vir no load, senão o próximo save o apaga: quem grava
            // escreve exatamente o que este método devolve.
            'last_apply' => is_array($data['last_apply'] ?? null) ? $data['last_apply'] : null,
        ];
    }

    /** @param array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>} $data */
    public function save(Server $server, array $data): void
    {
        $this->fileRepository->setServer($server)->putContent(
            self::METADATA_FILE,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Identifica a SESSÃO do servidor: o nome do DebugLog mais novo, que o PZ
     * batiza com o horário do boot.
     *
     * Serve pra saber se o servidor subiu depois de um "Salvar". Comparar por
     * nome é o jeito confiável — `find -newermt` e tail do console dão falso
     * positivo (aprendido na operação do chupacabra).
     *
     * `null` = não deu pra ler (servidor que nunca bootou, pasta ausente).
     */
    public function bootMarker(Server $server): ?string
    {
        try {
            $contents = $this->fileRepository->setServer($server)->getDirectory($this->getCacheDir($server).'/Logs');
        } catch (Exception) {
            return null;
        }

        if (isset($contents['error'])) {
            return null;
        }

        return collect($contents)
            ->filter(fn ($item) => is_string($item['name'] ?? null) && str_ends_with($item['name'], 'DebugLog-server.txt'))
            ->pluck('name')
            ->sortDesc()
            ->first();
    }

    /**
     * O cachedir vem do comando de startup (-cachedir=/home/container/.cache).
     */
    public function getCacheDir(Server $server): string
    {
        if (preg_match('~-cachedir=/home/container/([^\s"\']+)~', $server->startup ?? '', $matches)) {
            return trim($matches[1], '/');
        }

        return '.cache';
    }

    /**
     * Encontra o ini principal em <cachedir>/Server/.
     *
     * @throws Exception
     */
    public function getIniPath(Server $server): string
    {
        $serverDir = $this->getCacheDir($server).'/Server';
        $contents = $this->fileRepository->setServer($server)->getDirectory($serverDir);

        if (isset($contents['error'])) {
            throw new Exception($contents['error']);
        }

        $iniFiles = collect($contents)
            ->filter(fn ($item) => ($item['file'] ?? true) !== false && str_ends_with(strtolower($item['name'] ?? ''), '.ini'))
            ->pluck('name')
            ->values();

        if ($iniFiles->isEmpty()) {
            throw new Exception("Nenhum .ini encontrado em $serverDir — o servidor já rodou ao menos uma vez?");
        }

        // Com mais de um ini, prefere o de nome mais curto (os demais costumam
        // ser cópias tipo "Pterodactyl.ini.bak" que não terminam em .ini, mas
        // por garantia).
        $name = $iniFiles->sortBy(fn ($name) => strlen($name))->first();

        return "$serverDir/$name";
    }

    /** @return array{workshop_ids: array<int, string>, mod_ids: array<int, string>} */
    public function readIni(Server $server): array
    {
        return $this->parseIni($this->fileRepository->setServer($server)->getContent($this->getIniPath($server)));
    }

    /** @return array{workshop_ids: array<int, string>, mod_ids: array<int, string>} */
    protected function parseIni(string $content): array
    {
        $parse = function (string $key) use ($content): array {
            if (!preg_match('/^'.$key.'=(.*)$/mi', $content, $matches)) {
                return [];
            }

            return array_values(array_filter(array_map('trim', explode(';', $matches[1])), fn ($v) => $v !== ''));
        };

        return [
            'workshop_ids' => $parse('WorkshopItems'),
            'mod_ids' => $parse('Mods'),
        ];
    }

    /**
     * A janela de arrependimento ainda está aberta?
     *
     * Existe último salvamento E o servidor não subiu desde então. Depois do
     * boot o estrago no mundo já aconteceu, e devolver o ini não desfaz save
     * corrompido — oferecer "desfazer" ali seria consolo falso, que é pior que
     * botão nenhum.
     *
     * @param  array{last_apply?: array<string, mixed>|null}  $data
     */
    public function canUndoApply(Server $server, array $data): bool
    {
        $last = $data['last_apply'] ?? null;

        if (!is_array($last) || !isset($last['ini'])) {
            return false;
        }

        return ($last['boot'] ?? null) === $this->bootMarker($server);
    }

    /**
     * Devolve ao ini o `Mods=`/`WorkshopItems=` de antes do último salvamento.
     *
     * ⚠️ **Só o ini volta — a lista fica como o dono deixou.** É de propósito:
     * o estado resultante é exatamente o de um clique antes do "Salvar", que é
     * onde ele quer voltar. Reconstruir a lista exigiria a API da Steam e
     * inventaria uma decisão que não é nossa.
     *
     * @param  array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>, last_apply?: array<string, mixed>|null}  $data
     * @return array{workshop_ids: array<int, string>, mod_ids: array<int, string>}
     *
     * @throws Exception
     */
    public function undoApply(Server $server, array $data): array
    {
        if (!$this->canUndoApply($server, $data)) {
            throw new Exception('Sem salvamento para desfazer, ou o servidor já reiniciou desde então.');
        }

        $workshopIds = array_map('strval', $data['last_apply']['ini']['workshop_ids'] ?? []);
        $modIds = array_map('strval', $data['last_apply']['ini']['mod_ids'] ?? []);

        $iniPath = $this->getIniPath($server);
        $content = $this->fileRepository->setServer($server)->getContent($iniPath);

        $replace = function (string $content, string $key, string $value): string {
            $line = "$key=$value";
            $replaced = preg_replace('/^'.$key.'=.*$/mi', $line, $content, 1, $count);

            return $count === 0 ? rtrim($content, "\r\n")."\n$line\n" : $replaced;
        };

        $content = $replace($content, 'WorkshopItems', implode(';', $workshopIds));
        $content = $replace($content, 'Mods', implode(';', $modIds));

        $this->fileRepository->setServer($server)->putContent($iniPath, $content);

        // Um salvamento, um desfazer: manter o retrato deixaria um botão que
        // devolve um estado que já não é o anterior.
        $data['last_apply'] = null;
        $this->save($server, $data);

        return ['workshop_ids' => $workshopIds, 'mod_ids' => $modIds];
    }

    /**
     * Os Mod IDs de UMA entrada da lista.
     *
     * `selected_mod_ids` manda quando existe (o dono escolheu quais dos ids do
     * pacote entram); senão vale a lista inteira detectada no disco. Regra
     * usada tanto pra escrever o ini quanto pra cruzar com o mundo — se as duas
     * pontas discordarem, a guarda vigia um estado que não é o que será escrito.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    public static function entryModIds(array $entry): array
    {
        $selected = $entry['selected_mod_ids'] ?? $entry['mod_ids'] ?? [];

        return array_values(array_map('strval', is_array($selected) ? $selected : []));
    }

    /**
     * O que um "Salvar no servidor" gravaria agora — sem gravar nada.
     *
     * Extraído do `applyToIni` de propósito: a guarda de mundo
     * ([WorldModsService]) precisa enxergar exatamente o que o apply faria, e
     * duas cópias da regra (habilitado? candidato? extra_mod_ids?) divergiriam
     * na primeira mudança, fazendo a guarda avisar sobre um estado que não é o
     * que vai ser escrito.
     *
     * @param  array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>}  $data
     * @return array{workshop_ids: array<int, string>, mod_ids: array<int, string>}
     */
    public function plan(array $data): array
    {
        $workshopIds = [];
        $modIds = [];

        foreach ($data['mods'] as $entry) {
            // candidatos em homologação nunca entram no ini deste servidor
            if (empty($entry['enabled']) || ($entry['status'] ?? null) === 'candidate') {
                continue;
            }

            $workshopIds[] = (string) $entry['workshop_id'];

            foreach (self::entryModIds($entry) as $modId) {
                $modIds[] = $modId;
            }
        }

        foreach ($data['extra_mod_ids'] as $modId) {
            $modIds[] = (string) $modId;
        }

        return [
            'workshop_ids' => $workshopIds,
            'mod_ids' => array_values(array_unique($modIds)),
        ];
    }

    /**
     * Reescreve as linhas Mods= e WorkshopItems= do ini a partir da lista.
     * Só entram entradas habilitadas; a ordem do array é preservada.
     *
     * @param array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>} $data
     * @return array{workshop_ids: array<int, string>, mod_ids: array<int, string>}
     *
     * @throws Exception
     */
    public function applyToIni(Server $server, array $data): array
    {
        ['workshop_ids' => $workshopIds, 'mod_ids' => $modIds] = $this->plan($data);

        $iniPath = $this->getIniPath($server);
        $content = $this->fileRepository->setServer($server)->getContent($iniPath);

        // Retrato do que o ini tinha ANTES — é o que o desfazer devolve. A
        // mudança só machuca o mundo no próximo boot, então existe uma janela
        // real de arrependimento entre gravar e reiniciar.
        $before = $this->parseIni($content);

        $replace = function (string $content, string $key, string $value): string {
            $line = "$key=$value";
            $replaced = preg_replace('/^'.$key.'=.*$/mi', $line, $content, 1, $count);

            if ($count === 0) {
                return rtrim($content, "\r\n")."\n$line\n";
            }

            return $replaced;
        };

        $content = $replace($content, 'WorkshopItems', implode(';', $workshopIds));
        $content = $replace($content, 'Mods', implode(';', $modIds));

        $this->fileRepository->setServer($server)->putContent($iniPath, $content);

        $data['last_apply'] = [
            'boot' => $this->bootMarker($server),
            'ini' => $before,
        ];
        $this->save($server, $data);

        return ['workshop_ids' => $workshopIds, 'mod_ids' => $modIds];
    }

    /**
     * Lê os mod.info de um item já baixado no volume para descobrir os Mod IDs
     * reais (mais confiável que a descrição da workshop). Cobre o layout B42
     * com subpastas de versão (mods/<Nome>/42/mod.info etc).
     *
     * @return array<int, string>
     */
    public function scanModIdsOnDisk(Server $server, string $workshopId): array
    {
        $base = 'steamapps/workshop/content/'.SteamWorkshopService::PZ_APP_ID."/$workshopId/mods";
        $modIds = [];

        try {
            $modDirs = $this->fileRepository->setServer($server)->getDirectory($base);
        } catch (Exception) {
            return [];
        }

        if (isset($modDirs['error'])) {
            return [];
        }

        foreach ($modDirs as $modDir) {
            if (($modDir['file'] ?? false) === true) {
                continue;
            }

            $modName = $modDir['name'] ?? null;
            if (!$modName) {
                continue;
            }

            $candidates = ["$base/$modName/mod.info"];

            try {
                $versionDirs = $this->fileRepository->setServer($server)->getDirectory("$base/$modName");
                foreach ($versionDirs as $versionDir) {
                    if (($versionDir['file'] ?? true) === false && preg_match('/^(42|common)/i', $versionDir['name'] ?? '')) {
                        $candidates[] = "$base/$modName/{$versionDir['name']}/mod.info";
                    }
                }
            } catch (Exception) {
                // segue só com o mod.info da raiz
            }

            foreach ($candidates as $candidate) {
                try {
                    $info = $this->fileRepository->setServer($server)->getContent($candidate);
                } catch (Exception) {
                    continue;
                }

                if (preg_match('/^\s*id\s*=\s*(.+?)\s*$/mi', $info, $matches)) {
                    $modIds[] = trim($matches[1]);
                }
            }
        }

        return array_values(array_unique($modIds));
    }
}
