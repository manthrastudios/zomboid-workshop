<?php

namespace Manthra\ZomboidWorkshop\Services;

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
 *       "mod_ids": ["A", "B"], "selected_mod_ids": ["A"], "enabled": true}
 *    ],
 *    "extra_mod_ids": ["ids do ini que não casaram com nenhum item"]
 *  }
 *
 * A ordem do array "mods" é a ordem de load (importa no PZ).
 */
class ModListService
{
    public const METADATA_FILE = '.zomboid-workshop.json';

    public function __construct(protected DaemonFileRepository $fileRepository) {}

    /** @return array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>} */
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
        $content = $this->fileRepository->setServer($server)->getContent($this->getIniPath($server));

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
        $workshopIds = [];
        $modIds = [];

        foreach ($data['mods'] as $entry) {
            if (empty($entry['enabled'])) {
                continue;
            }

            $workshopIds[] = (string) $entry['workshop_id'];

            $selected = $entry['selected_mod_ids'] ?? $entry['mod_ids'] ?? [];
            foreach ($selected as $modId) {
                $modIds[] = (string) $modId;
            }
        }

        foreach ($data['extra_mod_ids'] as $modId) {
            $modIds[] = (string) $modId;
        }

        $modIds = array_values(array_unique($modIds));

        $iniPath = $this->getIniPath($server);
        $content = $this->fileRepository->setServer($server)->getContent($iniPath);

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
