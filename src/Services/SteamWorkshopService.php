<?php

namespace Tevo\ZomboidWorkshop\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class SteamWorkshopService
{
    public const PZ_APP_ID = 108600;

    /**
     * Aceita URL da workshop ("...?id=123"), URL curta ou o ID puro.
     */
    public static function parseWorkshopId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^\d{6,}$/', $input)) {
            return $input;
        }

        if (preg_match('/[?&]id=(\d{6,})/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Detalhes de um ou mais itens da workshop. Não precisa de API key.
     *
     * @param  array<int, string>  $workshopIds
     * @return array<string, array{workshop_id: string, title: string, description: string, preview_url: ?string, time_updated: ?int}>
     */
    public function getDetails(array $workshopIds): array
    {
        if (empty($workshopIds)) {
            return [];
        }

        $params = ['itemcount' => count($workshopIds)];
        foreach (array_values($workshopIds) as $i => $id) {
            $params["publishedfileids[$i]"] = $id;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/', $params)
            ->throw()
            ->json();

        $details = [];
        foreach ($response['response']['publishedfiledetails'] ?? [] as $item) {
            if ((int) ($item['result'] ?? 0) !== 1) {
                continue;
            }

            $details[(string) $item['publishedfileid']] = [
                'workshop_id' => (string) $item['publishedfileid'],
                'title' => $item['title'] ?? ('Item '.$item['publishedfileid']),
                'description' => $item['description'] ?? '',
                'preview_url' => $item['preview_url'] ?? null,
                'time_updated' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
            ];
        }

        return $details;
    }

    /**
     * Busca na workshop do PZ. Precisa da Steam Web API key.
     *
     * @return array{total: int, items: array<int, array{workshop_id: string, title: string, short_description: string, preview_url: ?string}>}
     *
     * @throws Exception
     */
    /**
     * @param string $sort auto|relevance|trend|newest|top|subscribed|updated
     * @param array<int, string> $tags tags exigidas (ex.: ["Build 42", "Weapons"])
     */
    public function search(?string $text, int $page = 1, int $perPage = 20, string $sort = 'auto', int $days = 7, array $tags = []): array
    {
        $key = config('zomboid-workshop.steam_api_key');
        if (empty($key)) {
            throw new Exception(trans('zomboid-workshop::strings.notifications.search_needs_key'));
        }

        // query_type: 12 = por texto, 3 = em alta (days), 1 = recentes,
        // 0 = mais votados, 9 = mais assinados, 21 = atualizados há pouco
        $queryType = match ($sort) {
            'relevance' => 12,
            'trend' => 3,
            'newest' => 1,
            'top' => 0,
            'subscribed' => 9,
            'updated' => 21,
            default => filled($text) ? 12 : 3,
        };

        $params = [
            'key' => $key,
            'appid' => self::PZ_APP_ID,
            'page' => $page,
            'numperpage' => $perPage,
            'return_previews' => true,
            'return_short_description' => true,
            'return_vote_data' => true,
            'query_type' => $queryType,
            'days' => $days,
        ];

        if (filled($text)) {
            $params['search_text'] = $text;
        }

        foreach (array_values($tags) as $i => $tag) {
            $params["requiredtags[$i]"] = $tag;
        }
        if (count($tags) > 1) {
            $params['match_all_tags'] = true;
        }

        $response = Http::timeout(15)
            ->get('https://api.steampowered.com/IPublishedFileService/QueryFiles/v1/', $params)
            ->throw()
            ->json();

        $items = [];
        foreach ($response['response']['publishedfiledetails'] ?? [] as $item) {
            $votes = ((int) ($item['vote_data']['votes_up'] ?? 0)) + ((int) ($item['vote_data']['votes_down'] ?? 0));

            $items[] = [
                'workshop_id' => (string) $item['publishedfileid'],
                'title' => $item['title'] ?? ('Item '.$item['publishedfileid']),
                'short_description' => $item['short_description'] ?? '',
                'preview_url' => $item['preview_url'] ?? null,
                'score' => isset($item['vote_data']['score']) ? (float) $item['vote_data']['score'] : null,
                'votes' => $votes,
                'subscriptions' => isset($item['subscriptions']) ? (int) $item['subscriptions'] : null,
                'time_updated' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
            ];
        }

        return [
            'total' => (int) ($response['response']['total'] ?? count($items)),
            'items' => $items,
        ];
    }

    /**
     * IDs dos itens de uma coleção, na ordem da coleção. Não precisa de API key.
     *
     * @return array<int, string>
     */
    public function getCollectionChildren(string $collectionId): array
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post('https://api.steampowered.com/ISteamRemoteStorage/GetCollectionDetails/v1/', [
                'collectioncount' => 1,
                'publishedfileids[0]' => $collectionId,
            ])
            ->throw()
            ->json();

        $collection = $response['response']['collectiondetails'][0] ?? [];
        $children = $collection['children'] ?? [];

        usort($children, fn ($a, $b) => ($a['sortorder'] ?? 0) <=> ($b['sortorder'] ?? 0));

        return array_map(fn ($child) => (string) $child['publishedfileid'], $children);
    }

    /**
     * Extrai Mod IDs da descrição do item ("Mod ID: XYZ"). A descrição usa
     * BBCode da Steam, então limpamos as tags antes.
     *
     * @return array<int, string>
     */
    public static function extractModIds(string $description): array
    {
        $text = preg_replace('/\[\/?[a-z*][^\]]*\]/i', ' ', $description) ?? $description;

        preg_match_all('/Mod\s*ID\s*[:=]\s*([A-Za-z0-9_.\-\' ]+?)(?=[\r\n;,]|Mod\s*ID|Workshop\s*ID|Map\s*Folder|$)/i', $text, $matches);

        $ids = array_map('trim', $matches[1] ?? []);
        $ids = array_filter($ids, fn ($id) => $id !== '' && strlen($id) <= 100);

        return array_values(array_unique($ids));
    }
}
