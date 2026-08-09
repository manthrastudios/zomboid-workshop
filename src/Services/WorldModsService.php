<?php

namespace Tevo\ZomboidWorkshop\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Exception;
use Illuminate\Support\Facades\Cache;

/**
 * Que mods o mundo atual já tem dentro.
 *
 * Tirar do `Mods=` um mod que o mundo usa corrompe o save de TODOS os
 * jogadores — o WorldDictionary não sabe resolver o que sumiu. Este serviço
 * existe pra que a interface consiga avisar antes, em vez de descobrir depois.
 *
 * A fonte é `<cachedir>/Saves/Multiplayer/<mundo>/WorldDictionaryReadable.lua`,
 * que o PZ escreve em texto puro ao lado do `.bin` que ele mesmo lê. Cada
 * entrada declara de qual mod veio, em seis seções (items, entities, objects,
 * sprites, strings, scripts):
 *
 *     {
 *         registryID = 0,
 *         fulltype = "Base.Bag_ClothSatchel_Denim",
 *         modID = "pz-vanilla",
 *         ...
 *     },
 *
 * O `modID` é o Mod ID do PZ — a mesma chave que o `scanModIdsOnDisk()`
 * extrai dos `mod.info`. Não precisa de tradução entre os dois mundos.
 *
 * ⚠️ **Presença prova, ausência não.** Um mod que não registra conteúdo (Lua
 * puro de comportamento) pode não aparecer em seção nenhuma. Por isso o
 * "desconhecido" é um estado de primeira classe aqui, e quem consome NUNCA
 * pode traduzir "não achei" em "seguro remover".
 */
class WorldModsService
{
    public const VANILLA_MOD_ID = 'pz-vanilla';

    /** Mundo só ganha mod novo com restart; 60 s é folgado e evita 1 MB por render. */
    protected const CACHE_SECONDS = 60;

    public function __construct(
        protected DaemonFileRepository $fileRepository,
        protected ModListService $modList,
    ) {}

    /**
     * Mods que o mundo atual tem dentro (sem o vanilla).
     *
     * @return array<int, string>|null  null = desconhecido — NÃO significa "nenhum"
     */
    public function worldModIds(Server $server): ?array
    {
        $result = Cache::remember(
            "zomboid-workshop:world-mods:{$server->id}",
            self::CACHE_SECONDS,
            fn () => $this->fetch($server),
        );

        return $result['known'] ? $result['mods'] : null;
    }

    /**
     * Mods que o mundo tem e que um "Salvar no servidor" agora deixaria de
     * carregar — ou seja, exatamente quem seria arrancado do mundo.
     *
     * Cobre de graça um caso que nenhuma tela cobre: mod que está no mundo mas
     * nunca esteve na lista do plugin (ini editado por fora).
     *
     * @param  array{mods: array<int, array<string, mixed>>, extra_mod_ids: array<int, string>}  $data
     * @return array<int, string>|null  null = desconhecido
     */
    public function victims(Server $server, array $data): ?array
    {
        $world = $this->worldModIds($server);

        if ($world === null) {
            return null;
        }

        // Comparação sem caixa: o ini casa com o mod.info exatamente, mas um
        // alarme falso por causa de maiúscula seria pior que o improvável par
        // de mods que difere só nisso.
        $planned = array_map('strtolower', $this->modList->plan($data)['mod_ids']);

        $victims = array_filter(
            $world,
            fn (string $id) => !in_array(strtolower($id), $planned, true),
        );

        // A ordem do dicionário é a de registro, arbitrária pra quem lê a modal.
        sort($victims, SORT_NATURAL | SORT_FLAG_CASE);

        return $victims;
    }

    /**
     * Os mods DESTA entrada que o mundo já tem dentro — o que torna desligar ou
     * remover ela um ato destrutivo.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, string>|null  null = desconhecido; vazio = não achei,
     *                                  que **não** é o mesmo que "seguro"
     */
    public function entryModsInWorld(Server $server, array $entry): ?array
    {
        $world = $this->worldModIds($server);

        if ($world === null) {
            return null;
        }

        $ids = array_map('strtolower', ModListService::entryModIds($entry));

        $hits = array_filter($world, fn (string $id) => in_array(strtolower($id), $ids, true));
        sort($hits, SORT_NATURAL | SORT_FLAG_CASE);

        return $hits;
    }

    public function forget(Server $server): void
    {
        Cache::forget("zomboid-workshop:world-mods:{$server->id}");
    }

    /** @return array{known: bool, mods: array<int, string>} */
    protected function fetch(Server $server): array
    {
        $unknown = ['known' => false, 'mods' => []];

        try {
            // O nome do mundo e o do ini saem os dois do -servername, então o
            // ini já achado é o jeito confiável de saber QUAL das pastas de
            // Saves/Multiplayer é a que está em uso (as outras são mundos
            // arquivados, e olhar a errada seria pior que não olhar).
            $world = preg_replace('/\.ini$/i', '', basename($this->modList->getIniPath($server)));
        } catch (Exception) {
            return $unknown;
        }

        if (!is_string($world) || $world === '') {
            return $unknown;
        }

        $path = $this->modList->getCacheDir($server)."/Saves/Multiplayer/$world/WorldDictionaryReadable.lua";

        try {
            $raw = $this->fileRepository->setServer($server)->getContent($path);
        } catch (Exception) {
            // Servidor que nunca bootou não tem mundo — nada a proteger ainda.
            return $unknown;
        }

        if (!is_string($raw) || $raw === '') {
            return $unknown;
        }

        $total = preg_match_all('/modID = "([^"]*)"/', $raw, $matches);

        // Um mundo real tem milhares de linhas de vanilla. Zero casamento não é
        // "mundo sem mod": é o formato tendo mudado debaixo de nós, e nesse caso
        // calar a boca é mais honesto que jurar que não há risco.
        if ($total === 0) {
            return $unknown;
        }

        $mods = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $id) => $id !== '' && $id !== self::VANILLA_MOD_ID,
        ));

        return ['known' => true, 'mods' => $mods];
    }
}
